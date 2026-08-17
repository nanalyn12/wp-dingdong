<?php
/**
 * DingDong LMS — 데이터 백업 / 복원.
 *
 * 이 플러그인은 커스텀 테이블을 쓰지 않는다. 모든 데이터가
 *   ① CPT 4종 (dd_course / dd_lesson / dd_story / dd_newsletter)
 *   ② `_dd_` 접두사 post meta
 *   ③ `dd_lms_` 접두사 wp_options
 * 에 들어 있으므로, 백업은 이 세 곳만 정확히 긁어 오면 된다.
 * DB 전체를 덤프하지 않는 이유이기도 하다.
 *
 * ⚠️ 복원에서 가장 중요한 것은 **포스트 ID 참조**다.
 *    강의는 `_dd_course_id`, 스토리는 `_dd_story_course_id` 메타에
 *    강좌의 post ID 를 문자열로 들고 있다. 복원 사이트에서는 wp_insert_post 가
 *    새 ID 를 발급하므로, 그대로 밀어 넣으면 강의가 강좌에서 떨어져 나가거나
 *    **남의 포스트를 가리키게 된다.** 그래서 포스트마다 영구 UID(`_dd_backup_uid`)를
 *    부여하고, 복원 시 uid → 새 ID 매핑으로 참조를 다시 잇는다.
 *
 * ⚠️ 백업에 넣지 않는 것:
 *    - Gemini/YouTube/Pixabay API 키 (자격증명)
 *    - 공개 공유 토큰 (`_dd_*_token`) — 복원 시 새로 발급한다
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DD_Backup {

    /** 백업 파일 시그니처 — 남의 플러그인 파일을 걸러내는 1차 관문. */
    const FORMAT = 'dingdong-lms-backup';

    /** 백업 포맷 버전. 구조를 바꾸면 올리고, import 쪽에 마이그레이션을 추가한다. */
    const FORMAT_VERSION = 1;

    /** 업로드 상한 (32MB). 파싱 전에 크기부터 본다 — 메모리 고갈 방지. */
    const MAX_BYTES = 33554432;

    /** 한 번에 복원할 수 있는 포스트 수 상한. */
    const MAX_POSTS = 2000;

    /** 백업 대상 CPT. 순서가 곧 복원 순서 — 강좌가 강의보다 먼저 와야 한다. */
    const POST_TYPES = array( 'dd_course', 'dd_lesson', 'dd_story', 'dd_newsletter' );

    /** 백업할 옵션 — **허용목록**. 여기 없는 옵션은 백업되지 않는다. */
    const OPTION_KEYS = array(
        'dd_lms_gemini_model',
        'dd_lms_purge_content_on_uninstall',
    );

    /**
     * 절대 백업하지 않는 옵션.
     * OPTION_KEYS 가 이미 허용목록이라 이론상 불필요하지만,
     * 누군가 실수로 허용목록에 키를 추가하는 것을 막는 이중 방어다.
     */
    const SECRET_OPTION_KEYS = array(
        'dd_lms_gemini_api_key',
        'dd_lms_youtube_key',
        'dd_lms_pixabay_key',
        'dd_suno_api_key',
    );

    /**
     * 공개 URL 시크릿. 백업 파일이 유출되면 비공개 강의가 열리므로 담지 않고,
     * 복원 시 `wp_generate_uuid4()` 로 새로 발급한다. 공개/비공개 여부
     * (`_dd_*_active`)는 설정값이므로 그대로 보존한다.
     */
    const TOKEN_META_KEYS = array(
        '_dd_public_token',
        '_dd_story_public_token',
        '_dd_newsletter_public_token',
    );

    /** 다른 포스트의 ID 를 담고 있는 메타 — 복원 시 반드시 재매핑한다. */
    const REF_META_KEYS = array(
        '_dd_course_id',
        '_dd_story_course_id',
    );

    /** 백업/복원 사이에서 동일 콘텐츠를 알아보는 영구 식별자. */
    const UID_META = '_dd_backup_uid';

    /** 자동 안전 백업 보관 개수. */
    const KEEP_AUTO_BACKUPS = 5;

    /* ---- ZIP 아카이브 (JSON + 이미지 파일) ---- */

    /** ZIP 안의 백업 JSON 이름. */
    const ARCHIVE_ENTRY_JSON = 'backup.json';

    /** ZIP 안에서 이미지가 들어가는 폴더. 이 밖의 항목은 절대 풀지 않는다. */
    const ARCHIVE_MEDIA_PREFIX = 'uploads/';

    /**
     * ZIP 에 담고, ZIP 에서 풀어도 되는 확장자.
     *
     * ⚠️ **허용목록이어야 한다.** 거부목록으로 만들면 `.phtml`·`.php5` 같은
     *    변종이나 이중 확장자(`a.png.php`)가 빠져나가 업로드 폴더에서
     *    임의 코드 실행으로 이어진다. SVG 도 스크립트를 품을 수 있어 제외한다.
     */
    const MEDIA_EXTENSIONS = array( 'png', 'jpg', 'jpeg', 'gif', 'webp' );

    /* =========================================================
       내보내기
       ========================================================= */

    /**
     * 플러그인이 관리하는 데이터 전체를 배열로 만든다.
     *
     * @return array 백업 문서 (그대로 json_encode 하면 백업 파일이 된다)
     */
    public static function export() {
        $posts       = array();
        $all_terms   = array();
        $post_counts = array();

        foreach ( self::POST_TYPES as $type ) {
            $items = get_posts( array(
                'post_type'        => $type,
                'numberposts'      => -1,
                'post_status'      => 'any',
                'orderby'          => 'ID',
                'order'            => 'ASC',
                'suppress_filters' => false,
            ) );

            $post_counts[ $type ] = count( $items );

            foreach ( $items as $p ) {
                $entry   = self::export_post( $p );
                $posts[] = $entry;

                foreach ( $entry['terms'] as $tax => $terms ) {
                    foreach ( $terms as $term ) {
                        $all_terms[ $tax ][ $term['slug'] ] = $term;
                    }
                }
            }
        }

        // 슬러그 키를 떼고 목록 형태로 정리한다.
        foreach ( $all_terms as $tax => $terms ) {
            $all_terms[ $tax ] = array_values( $terms );
        }

        $uploads = wp_upload_dir();

        return array(
            'format'         => self::FORMAT,
            'format_version' => self::FORMAT_VERSION,
            'plugin'         => 'dingdong-lms',
            'plugin_version' => defined( 'DD_LMS_VERSION' ) ? DD_LMS_VERSION : '',
            'wp_version'     => get_bloginfo( 'version' ),
            'generated_at'   => gmdate( 'c' ),
            'site'           => array(
                'home_url'    => home_url(),
                'uploads_url' => isset( $uploads['baseurl'] ) ? $uploads['baseurl'] : '',
                'charset'     => get_bloginfo( 'charset' ),
            ),
            'counts'         => array(
                'posts'   => count( $posts ),
                'by_type' => $post_counts,
            ),
            'options'        => self::filter_options( self::read_options() ),
            'posts'          => $posts,
            'terms'          => $all_terms,
            // 이 플러그인은 커스텀 테이블을 쓰지 않는다. 나중에 생기면 여기 채운다.
            'tables'         => array(),
            'notes'          => array(
                'API 키(Gemini / YouTube / Pixabay)는 보안상 백업에 포함되지 않습니다. 복원 후 직접 입력하세요.',
                '공개 공유 토큰은 백업에 포함되지 않으며 복원 시 새로 발급됩니다.',
                '이미지 파일 자체는 백업에 포함되지 않습니다 (wp-content/uploads/dingdong-lms/). 다른 사이트로 옮길 때는 이 폴더를 함께 복사하세요.',
            ),
        );
    }

    /**
     * 포스트 1건을 백업 항목으로 변환한다.
     */
    private static function export_post( $p ) {
        $meta = self::filter_meta( self::flatten_meta( get_post_meta( $p->ID ) ) );

        // 참조 대상의 uid 를 함께 기록해 둔다 — 원본 ID 만으로는 복원 사이트에서
        // 어떤 강좌인지 확정할 수 없기 때문이다.
        $refs = array();
        foreach ( self::REF_META_KEYS as $key ) {
            if ( empty( $meta[ $key ] ) ) {
                continue;
            }
            $target_uid = self::ensure_uid( (int) $meta[ $key ] );
            if ( $target_uid !== '' ) {
                $refs[ $key ] = $target_uid;
            }
        }

        return array(
            'uid'         => self::ensure_uid( $p->ID ),
            'original_id' => (int) $p->ID,
            'post_type'   => $p->post_type,
            'post'        => array(
                'post_title'    => $p->post_title,
                'post_content'  => $p->post_content,
                'post_excerpt'  => $p->post_excerpt,
                'post_status'   => $p->post_status,
                'post_name'     => $p->post_name,
                'post_date'     => $p->post_date,
                'post_date_gmt' => $p->post_date_gmt,
                'menu_order'    => (int) $p->menu_order,
            ),
            'meta'        => $meta,
            'refs'        => $refs,
            'terms'       => self::export_terms( $p ),
        );
    }

    /**
     * 포스트에 붙은 텀을 taxonomy → term 목록으로 뽑는다.
     * 현재 이 플러그인은 taxonomy 를 등록하지 않지만, 나중에 추가되거나
     * 다른 플러그인이 CPT 에 taxonomy 를 붙여도 관계가 보존되도록 한다.
     */
    private static function export_terms( $p ) {
        $out = array();

        $taxonomies = get_object_taxonomies( $p->post_type );
        if ( empty( $taxonomies ) ) {
            return $out;
        }

        foreach ( $taxonomies as $tax ) {
            $terms = wp_get_object_terms( $p->ID, $tax );
            if ( is_wp_error( $terms ) || empty( $terms ) ) {
                continue;
            }
            foreach ( $terms as $term ) {
                $out[ $tax ][] = array(
                    'slug'        => $term->slug,
                    'name'        => $term->name,
                    'description' => $term->description,
                );
            }
        }

        return $out;
    }

    /**
     * 포스트에 영구 UID 를 부여하고 반환한다 (없을 때만 생성).
     * 우리 CPT 가 아니거나 없는 포스트면 빈 문자열.
     */
    private static function ensure_uid( $post_id ) {
        $post_id = (int) $post_id;
        if ( $post_id <= 0 ) {
            return '';
        }

        $post = get_post( $post_id );
        if ( ! $post || ! self::is_supported_post_type( $post->post_type ) ) {
            return '';
        }

        $uid = get_post_meta( $post_id, self::UID_META, true );
        if ( ! is_string( $uid ) || $uid === '' ) {
            $uid = wp_generate_uuid4();
            update_post_meta( $post_id, self::UID_META, $uid );
        }

        return $uid;
    }

    /** get_post_meta($id) 의 [key => [값,...]] 을 [key => 값] 으로 편다. */
    private static function flatten_meta( $raw ) {
        $flat = array();
        if ( ! is_array( $raw ) ) {
            return $flat;
        }
        foreach ( $raw as $key => $values ) {
            if ( is_array( $values ) ) {
                $flat[ $key ] = count( $values ) === 1 ? $values[0] : array_values( $values );
            } else {
                $flat[ $key ] = $values;
            }
        }
        return $flat;
    }

    /** 허용목록 옵션을 현재 값과 함께 읽어 온다. */
    private static function read_options() {
        $out = array();
        foreach ( self::OPTION_KEYS as $key ) {
            $value = get_option( $key, null );
            if ( $value === null ) {
                continue;
            }
            $out[ $key ] = $value;
        }
        return $out;
    }

    /* =========================================================
       필터 — 무엇을 담고 무엇을 버리는가 (순수 함수)
       ========================================================= */

    /**
     * 옵션 배열에서 백업 대상만 남긴다.
     * 허용목록에 있고, 자격증명이 아닌 것만 통과한다.
     */
    public static function filter_options( $options ) {
        $out = array();
        if ( ! is_array( $options ) ) {
            return $out;
        }

        foreach ( $options as $key => $value ) {
            if ( ! in_array( $key, self::OPTION_KEYS, true ) ) {
                continue;
            }
            if ( in_array( $key, self::SECRET_OPTION_KEYS, true ) ) {
                continue;
            }
            $out[ $key ] = $value;
        }

        return $out;
    }

    /**
     * 포스트 메타에서 백업 대상만 남긴다.
     * `_dd_` 접두사 = 이 플러그인이 만든 것. 그중 토큰과 내부 UID 는 뺀다.
     */
    public static function filter_meta( $meta ) {
        $out = array();
        if ( ! is_array( $meta ) ) {
            return $out;
        }

        foreach ( $meta as $key => $value ) {
            if ( ! is_string( $key ) || strpos( $key, '_dd_' ) !== 0 ) {
                continue;
            }
            if ( in_array( $key, self::TOKEN_META_KEYS, true ) ) {
                continue;
            }
            if ( $key === self::UID_META ) {
                continue;
            }
            $out[ $key ] = $value;
        }

        return $out;
    }

    /** 우리가 복원해도 되는 post_type 인가. */
    public static function is_supported_post_type( $type ) {
        return in_array( $type, self::POST_TYPES, true );
    }

    /* =========================================================
       검증 — 업로드된 파일을 신뢰하지 않는다
       ========================================================= */

    /**
     * 업로드된 원문을 배열로 바꾼다. 크기 → JSON 유효성 순으로 본다.
     *
     * @return array|WP_Error
     */
    public static function decode( $raw ) {
        if ( ! is_string( $raw ) || $raw === '' ) {
            return new WP_Error( 'dd_backup_empty', '백업 파일이 비어 있습니다.', array( 'status' => 400 ) );
        }

        if ( strlen( $raw ) > self::MAX_BYTES ) {
            return new WP_Error(
                'dd_backup_too_large',
                '백업 파일이 너무 큽니다 (최대 ' . size_format_fallback( self::MAX_BYTES ) . ').',
                array( 'status' => 413 )
            );
        }

        $data = json_decode( $raw, true, 64 );

        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
            return new WP_Error(
                'dd_backup_invalid_json',
                '올바른 JSON 백업 파일이 아닙니다.',
                array( 'status' => 400 )
            );
        }

        return $data;
    }

    /**
     * 백업 문서의 구조를 검증한다.
     *
     * @return true|WP_Error
     */
    public static function validate( $data ) {
        if ( ! is_array( $data ) ) {
            return new WP_Error( 'dd_backup_malformed', '백업 데이터 구조가 올바르지 않습니다.', array( 'status' => 400 ) );
        }

        $format = isset( $data['format'] ) ? $data['format'] : '';
        if ( $format !== self::FORMAT ) {
            return new WP_Error(
                'dd_backup_not_ours',
                'Dingdong LMS 백업 파일이 아닙니다.',
                array( 'status' => 400 )
            );
        }

        $version = isset( $data['format_version'] ) ? $data['format_version'] : 0;
        if ( ! is_numeric( $version ) || (int) $version < 1 || (int) $version > self::FORMAT_VERSION ) {
            return new WP_Error(
                'dd_backup_unsupported_version',
                '지원하지 않는 백업 포맷 버전입니다 (파일: ' . (string) $version
                    . ' / 지원: 1~' . self::FORMAT_VERSION . '). 플러그인을 최신 버전으로 업데이트하세요.',
                array( 'status' => 400 )
            );
        }

        foreach ( array( 'posts', 'options' ) as $key ) {
            if ( ! isset( $data[ $key ] ) || ! is_array( $data[ $key ] ) ) {
                return new WP_Error(
                    'dd_backup_malformed',
                    '백업 파일에 필수 항목(' . $key . ')이 없습니다.',
                    array( 'status' => 400 )
                );
            }
        }

        if ( empty( $data['generated_at'] ) || empty( $data['plugin_version'] ) ) {
            return new WP_Error(
                'dd_backup_malformed',
                '백업 파일에 생성 정보(generated_at / plugin_version)가 없습니다.',
                array( 'status' => 400 )
            );
        }

        if ( count( $data['posts'] ) > self::MAX_POSTS ) {
            return new WP_Error(
                'dd_backup_too_many',
                '백업에 담긴 콘텐츠가 너무 많습니다 (최대 ' . self::MAX_POSTS . '건).',
                array( 'status' => 400 )
            );
        }

        return true;
    }

    /* =========================================================
       변환 — ID 재매핑 / URL 재작성 / 새니타이즈 (순수 함수)
       ========================================================= */

    /**
     * 참조 메타(`_dd_course_id` 등)를 복원 사이트의 새 ID 로 바꾼다.
     *
     * 우선순위: 백업에 기록된 uid → 원본 ID 매핑 → (둘 다 실패하면) 비운다.
     * 비우는 이유: 원본 ID 를 그대로 두면 복원 사이트에 우연히 같은 번호로
     * 존재하는 **남의 포스트**를 가리키게 된다.
     *
     * @param array $meta     대상 포스트의 메타
     * @param array $uid_map  uid => 새 post ID
     * @param array $id_map   원본 post ID => 새 post ID
     * @param array $ref_uids 메타키 => 참조 대상 uid (백업의 refs)
     */
    public static function remap_refs( $meta, $uid_map, $id_map, $ref_uids ) {
        foreach ( self::REF_META_KEYS as $key ) {
            if ( ! array_key_exists( $key, $meta ) ) {
                continue;
            }

            $new_id = 0;

            if ( isset( $ref_uids[ $key ] ) && isset( $uid_map[ $ref_uids[ $key ] ] ) ) {
                $new_id = (int) $uid_map[ $ref_uids[ $key ] ];
            }

            if ( ! $new_id ) {
                $old_id = (int) $meta[ $key ];
                if ( $old_id > 0 && isset( $id_map[ $old_id ] ) ) {
                    $new_id = (int) $id_map[ $old_id ];
                }
            }

            $meta[ $key ] = $new_id > 0 ? (string) $new_id : '';
        }

        return $meta;
    }

    /**
     * 백업 사이트의 업로드 URL 을 복원 사이트 기준으로 바꾼다.
     * 이미지 메타는 전부 URL 문자열이므로 이 치환만으로 이미지가 살아난다
     * (실제 파일은 uploads 폴더를 함께 복사해야 한다).
     */
    public static function rewrite_uploads_url( $value, $from, $to ) {
        if ( ! is_string( $from ) || $from === '' || $from === $to || ! is_string( $to ) || $to === '' ) {
            return $value;
        }

        if ( is_array( $value ) ) {
            foreach ( $value as $k => $v ) {
                $value[ $k ] = self::rewrite_uploads_url( $v, $from, $to );
            }
            return $value;
        }

        if ( ! is_string( $value ) ) {
            return $value;
        }

        // JSON 안에서는 슬래시가 이스케이프(\/)돼 있을 수 있으므로 두 형태 모두 치환한다.
        $value = str_replace( $from, $to, $value );
        $value = str_replace(
            str_replace( '/', '\\/', $from ),
            str_replace( '/', '\\/', $to ),
            $value
        );

        return $value;
    }

    /**
     * 복원할 메타 값을 새니타이즈한다.
     *
     * JSON blob 은 구조를 유지한 채 **말단 문자열만** wp_kses_post 로 거른다.
     * (문자열 전체에 kses 를 걸면 JSON 이 깨진다)
     */
    public static function sanitize_meta_value( $key, $value ) {
        if ( is_array( $value ) ) {
            return self::sanitize_tree( $value );
        }

        if ( ! is_string( $value ) ) {
            return $value;
        }

        $trimmed = ltrim( $value );
        if ( $trimmed !== '' && ( $trimmed[0] === '{' || $trimmed[0] === '[' ) ) {
            $decoded = json_decode( $value, true, 64 );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                return wp_json_encode( self::sanitize_tree( $decoded ), JSON_UNESCAPED_UNICODE );
            }
        }

        return wp_kses_post( $value );
    }

    /** 배열을 재귀적으로 훑으며 말단 문자열을 거른다. */
    private static function sanitize_tree( $node ) {
        if ( is_array( $node ) ) {
            $out = array();
            foreach ( $node as $k => $v ) {
                $key         = is_string( $k ) ? sanitize_text_field( $k ) : $k;
                $out[ $key ] = self::sanitize_tree( $v );
            }
            return $out;
        }

        if ( is_string( $node ) ) {
            return wp_kses_post( $node );
        }

        return $node;
    }

    /* =========================================================
       파일명
       ========================================================= */

    /**
     * `dingdong-lms-backup-YYYY-MM-DD-HH-mm-ss.json`
     *
     * @param int|null $timestamp 사이트 로컬 타임스탬프 (기본: 현재)
     */
    public static function filename( $timestamp = null ) {
        $timestamp = $timestamp ? (int) $timestamp : time();
        return 'dingdong-lms-backup-' . gmdate( 'Y-m-d-H-i-s', $timestamp ) . '.json';
    }

    /* =========================================================
       ZIP 아카이브 — JSON + 이미지 파일을 한 파일로
       ========================================================= */

    /** `dingdong-lms-backup-YYYY-MM-DD-HH-mm-ss.zip` */
    public static function archive_filename( $timestamp = null ) {
        return str_replace( '.json', '.zip', self::filename( $timestamp ) );
    }

    /**
     * ZIP 항목 이름을 이미지 저장 경로로 바꾼다. 안전하지 않으면 빈 문자열.
     *
     * ⚠️ 여기가 zip slip(경로 탈출) 방어선이다. 공격자가 만든 ZIP 의 항목 이름은
     *    `uploads/../../wp-config.php` 처럼 무엇이든 될 수 있고, 그대로 쓰면
     *    업로드 폴더 밖에 파일을 덮어쓸 수 있다. 그래서
     *      ① `uploads/` 로 시작해야 하고
     *      ② `..` · 절대경로 · 역슬래시 · 널바이트가 없어야 하고
     *      ③ 확장자가 **허용목록**에 있어야 한다
     *    세 조건을 모두 만족할 때만 통과시킨다.
     *
     * @return string media_dir() 기준 상대경로, 또는 '' (거부)
     */
    public static function safe_archive_path( $entry ) {
        if ( ! is_string( $entry ) || $entry === '' ) {
            return '';
        }

        // 널바이트 — 뒤쪽 확장자를 잘라내 검사를 속이는 고전 수법.
        if ( strpos( $entry, "\0" ) !== false ) {
            return '';
        }

        // 역슬래시는 Windows 에서 구분자로 해석될 수 있으므로 아예 거부한다.
        if ( strpos( $entry, '\\' ) !== false ) {
            return '';
        }

        if ( strpos( $entry, self::ARCHIVE_MEDIA_PREFIX ) !== 0 ) {
            return '';
        }

        $relative = substr( $entry, strlen( self::ARCHIVE_MEDIA_PREFIX ) );

        if ( $relative === '' || substr( $relative, -1 ) === '/' ) {
            return ''; // 디렉터리 항목
        }

        // 절대경로 (/etc/passwd, C:/...)
        if ( $relative[0] === '/' || preg_match( '#^[a-z]:#i', $relative ) ) {
            return '';
        }

        // 상위 이동은 어느 위치에 있든 거부한다.
        foreach ( explode( '/', $relative ) as $segment ) {
            if ( $segment === '..' || $segment === '.' || $segment === '' ) {
                return '';
            }
        }

        $ext = strtolower( pathinfo( $relative, PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, self::MEDIA_EXTENSIONS, true ) ) {
            return '';
        }

        return $relative;
    }

    /**
     * 이 파일을 ZIP 에 담을 것인가 (media_dir 기준 상대경로).
     * 백업 폴더·보호 파일·이미지가 아닌 것은 제외한다.
     */
    public static function should_archive_file( $relative ) {
        if ( ! is_string( $relative ) || $relative === '' ) {
            return false;
        }

        // 백업 안에 백업을 다시 넣지 않는다.
        if ( strpos( $relative, 'backups/' ) === 0 ) {
            return false;
        }

        $basename = basename( $relative );
        if ( $basename === '' || $basename[0] === '.' ) {
            return false; // .htaccess 등
        }

        $ext = strtolower( pathinfo( $relative, PATHINFO_EXTENSION ) );
        return in_array( $ext, self::MEDIA_EXTENSIONS, true );
    }

    /** 이 플러그인이 이미지를 저장하는 폴더. */
    public static function media_dir() {
        $uploads = wp_upload_dir();
        if ( ! empty( $uploads['error'] ) ) {
            return '';
        }
        return trailingslashit( $uploads['basedir'] ) . 'dingdong-lms';
    }

    /** media_dir 안에서 아카이브 대상 파일의 상대경로 목록. */
    private static function collect_media_files() {
        $dir = self::media_dir();
        if ( $dir === '' || ! is_dir( $dir ) ) {
            return array();
        }

        $out = array();
        $it  = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS )
        );

        foreach ( $it as $file ) {
            if ( ! $file->isFile() ) {
                continue;
            }
            $relative = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $dir ) + 1 ) );
            if ( self::should_archive_file( $relative ) ) {
                $out[] = $relative;
            }
        }

        sort( $out );
        return $out;
    }

    /**
     * JSON 백업 + 이미지 파일을 ZIP 한 개로 만든다.
     *
     * 파일은 임시 이름으로 만들고, 다운로드 핸들러가 전송 직후 삭제한다.
     *
     * @param bool $include_media 이미지 파일 포함 여부
     * @return array|WP_Error array{path,file,bytes,media_count,media_bytes}
     */
    public static function write_archive( $include_media = true ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return new WP_Error(
                'dd_backup_no_zip',
                '이 서버에는 ZIP 확장(ZipArchive)이 없어 전체 백업을 만들 수 없습니다. JSON 백업을 사용하세요.'
            );
        }

        $dir = self::backup_dir();
        if ( is_wp_error( $dir ) ) {
            return $dir;
        }

        $json = wp_json_encode( self::export(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
        if ( $json === false ) {
            return new WP_Error( 'dd_backup_encode_failed', '백업 데이터를 만들지 못했습니다.' );
        }

        $file = self::archive_filename( current_time( 'timestamp' ) );
        $path = trailingslashit( $dir ) . 'tmp-' . wp_generate_password( 12, false, false ) . '-' . $file;

        $zip = new ZipArchive();
        if ( $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
            return new WP_Error( 'dd_backup_zip_create_failed', 'ZIP 파일을 만들지 못했습니다.' );
        }

        $zip->addFromString( self::ARCHIVE_ENTRY_JSON, $json );

        $media_count = 0;
        $media_bytes = 0;

        if ( $include_media ) {
            $media_root = trailingslashit( self::media_dir() );
            foreach ( self::collect_media_files() as $relative ) {
                $abs = $media_root . $relative;
                if ( ! is_readable( $abs ) ) {
                    continue;
                }
                if ( $zip->addFile( $abs, self::ARCHIVE_MEDIA_PREFIX . $relative ) ) {
                    ++$media_count;
                    $media_bytes += (int) filesize( $abs );
                }
            }
        }

        $zip->addFromString( 'manifest.json', wp_json_encode( array(
            'format'         => self::FORMAT . '-archive',
            'format_version' => self::FORMAT_VERSION,
            'generated_at'   => gmdate( 'c' ),
            'json_entry'     => self::ARCHIVE_ENTRY_JSON,
            'media_prefix'   => self::ARCHIVE_MEDIA_PREFIX,
            'media_count'    => $media_count,
            'media_bytes'    => $media_bytes,
            'notes'          => 'uploads/ 안의 파일은 wp-content/uploads/dingdong-lms/ 로 복원됩니다. API 키와 공개 토큰은 포함되지 않습니다.',
        ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );

        if ( ! $zip->close() ) {
            @unlink( $path ); // phpcs:ignore
            return new WP_Error( 'dd_backup_zip_write_failed', 'ZIP 파일을 저장하지 못했습니다 (디스크 용량 확인).' );
        }

        return array(
            'path'        => $path,
            'file'        => $file,
            'bytes'       => (int) filesize( $path ),
            'media_count' => $media_count,
            'media_bytes' => $media_bytes,
        );
    }

    /**
     * 업로드된 ZIP 에서 백업 JSON 을 읽고, 이미지를 업로드 폴더로 푼다.
     *
     * 기존 이미지는 **덮어쓰지 않는다** (복원의 비파괴 원칙).
     *
     * @return array|WP_Error array{data, media:{extracted,skipped,rejected}}
     */
    public static function read_archive( $zip_path, $extract_media = true ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return new WP_Error(
                'dd_backup_no_zip',
                '이 서버에는 ZIP 확장(ZipArchive)이 없어 ZIP 백업을 복원할 수 없습니다. JSON 백업을 사용하세요.',
                array( 'status' => 400 )
            );
        }

        $zip = new ZipArchive();
        if ( $zip->open( $zip_path ) !== true ) {
            return new WP_Error( 'dd_backup_bad_zip', 'ZIP 파일을 열 수 없습니다. 파일이 손상되었을 수 있습니다.', array( 'status' => 400 ) );
        }

        $raw = $zip->getFromName( self::ARCHIVE_ENTRY_JSON );
        if ( $raw === false ) {
            $zip->close();
            return new WP_Error(
                'dd_backup_no_json',
                'ZIP 안에 ' . self::ARCHIVE_ENTRY_JSON . ' 이 없습니다. Dingdong LMS 백업 ZIP 이 아닙니다.',
                array( 'status' => 400 )
            );
        }

        $data = self::decode( $raw );
        if ( is_wp_error( $data ) ) {
            $zip->close();
            return $data;
        }

        $media = array( 'extracted' => 0, 'skipped' => 0, 'rejected' => 0, 'bytes' => 0 );

        if ( $extract_media ) {
            $dest = self::media_dir();
            if ( $dest === '' || ! wp_mkdir_p( $dest ) ) {
                $zip->close();
                return new WP_Error( 'dd_backup_no_uploads', '업로드 폴더를 사용할 수 없어 이미지를 복원할 수 없습니다.', array( 'status' => 500 ) );
            }
            $dest = trailingslashit( $dest );

            for ( $i = 0; $i < $zip->numFiles; $i++ ) {
                $name = $zip->getNameIndex( $i );
                if ( $name === self::ARCHIVE_ENTRY_JSON || $name === 'manifest.json' ) {
                    continue;
                }

                $relative = self::safe_archive_path( $name );
                if ( $relative === '' ) {
                    // 디렉터리 항목은 조용히 넘기고, 그 외에는 거부로 센다.
                    if ( substr( $name, -1 ) !== '/' ) {
                        ++$media['rejected'];
                    }
                    continue;
                }

                $target = $dest . $relative;

                if ( file_exists( $target ) ) {
                    ++$media['skipped']; // 기존 이미지를 덮어쓰지 않는다
                    continue;
                }

                if ( ! wp_mkdir_p( dirname( $target ) ) ) {
                    ++$media['rejected'];
                    continue;
                }

                $contents = $zip->getFromIndex( $i );
                if ( $contents === false || file_put_contents( $target, $contents ) === false ) { // phpcs:ignore
                    ++$media['rejected'];
                    continue;
                }

                ++$media['extracted'];
                $media['bytes'] += strlen( $contents );
            }
        }

        $zip->close();

        return array( 'data' => $data, 'media' => $media );
    }

    /**
     * [전체 백업(ZIP)] 다운로드. admin-post.php 로 직접 이동해서 받는다.
     *
     * REST + JS Blob 이 아니라 브라우저 기본 다운로드를 쓰는 이유:
     * 147MB 짜리를 JS 문자열/Blob 으로 들고 있으면 탭이 죽는다.
     */
    public static function handle_archive_download() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( '이 작업을 수행할 권한이 없습니다.', '권한 없음', array( 'response' => 403 ) );
        }

        check_admin_referer( 'dd_backup_archive' );

        @set_time_limit( 900 );

        $include_media = ! isset( $_GET['images'] ) || $_GET['images'] !== '0';

        $result = self::write_archive( $include_media );
        if ( is_wp_error( $result ) ) {
            wp_die( esc_html( $result->get_error_message() ), '백업 실패', array( 'response' => 500 ) );
        }

        nocache_headers();
        header( 'Content-Type: application/zip' );
        header( 'Content-Disposition: attachment; filename="' . $result['file'] . '"' );
        header( 'Content-Length: ' . $result['bytes'] );
        header( 'X-Content-Type-Options: nosniff' );

        // 버퍼를 비우고 스트리밍한다 — 147MB 를 메모리에 올리지 않기 위해.
        while ( ob_get_level() > 0 ) {
            ob_end_clean();
        }

        readfile( $result['path'] );

        // 백업본을 서버에 남기지 않는다.
        @unlink( $result['path'] ); // phpcs:ignore

        exit;
    }

    /* =========================================================
       복원
       ========================================================= */

    /**
     * 백업 문서를 현재 사이트에 복원한다.
     *
     * 기본 동작은 **비파괴**다. 기존 데이터를 지우지 않고, 같은 uid 를 가진
     * 콘텐츠가 이미 있으면 건너뛴다.
     *
     * @param array $data 검증된 백업 문서
     * @param array $args mode: skip|replace|duplicate, restore_options: bool
     * @return array|WP_Error 복원 리포트
     */
    public static function import( $data, $args = array() ) {
        $valid = self::validate( $data );
        if ( is_wp_error( $valid ) ) {
            return $valid;
        }

        $args = array_merge(
            array(
                'mode'            => 'skip',
                'restore_options' => true,
            ),
            is_array( $args ) ? $args : array()
        );

        $mode = in_array( $args['mode'], array( 'skip', 'replace', 'duplicate' ), true )
            ? $args['mode']
            : 'skip';

        $report = array(
            'mode'      => $mode,
            'created'   => 0,
            'updated'   => 0,
            'skipped'   => 0,
            'failed'    => 0,
            'by_type'   => array(),
            'options'   => 0,
            'terms'     => 0,
            'errors'    => array(),
            'warnings'  => array(),
        );

        $uploads      = wp_upload_dir();
        $to_uploads   = isset( $uploads['baseurl'] ) ? $uploads['baseurl'] : '';
        $from_uploads = isset( $data['site']['uploads_url'] ) ? (string) $data['site']['uploads_url'] : '';

        $uid_map  = array();  // uid          => 새 post ID
        $id_map   = array();  // 원본 post ID => 새 post ID
        $created  = array();  // 이번 복원에서 새로 만든 ID (실패 시 되돌리기용)
        $entries  = array();  // 2단계에서 메타를 쓸 항목만

        /* --- 1단계: 포스트 먼저 전부 만든다 (참조 해결을 위해) --- */
        foreach ( $data['posts'] as $index => $entry ) {
            try {
                $prepared = self::prepare_entry( $entry );
                if ( is_wp_error( $prepared ) ) {
                    ++$report['failed'];
                    $report['errors'][] = self::describe_entry( $entry, $index ) . ' — ' . $prepared->get_error_message();
                    continue;
                }

                $existing = ( $mode === 'duplicate' ) ? 0 : self::find_by_uid( $prepared['uid'] );

                if ( $existing && $mode === 'skip' ) {
                    ++$report['skipped'];
                    $uid_map[ $prepared['uid'] ]      = $existing;
                    $id_map[ $prepared['original_id'] ] = $existing;
                    continue;
                }

                $postarr = self::rewrite_uploads_url( $prepared['post'], $from_uploads, $to_uploads );

                if ( $existing && $mode === 'replace' ) {
                    $postarr['ID'] = $existing;
                    $result        = wp_update_post( $postarr, true );
                } else {
                    // ID 는 절대 지정하지 않는다 — WordPress 가 충돌 없는 새 ID 를 발급한다.
                    unset( $postarr['ID'] );
                    $result = wp_insert_post( $postarr, true );
                }

                if ( is_wp_error( $result ) || ! $result ) {
                    ++$report['failed'];
                    $report['errors'][] = self::describe_entry( $entry, $index ) . ' — 포스트 저장 실패: '
                        . ( is_wp_error( $result ) ? $result->get_error_message() : '알 수 없는 오류' );
                    continue;
                }

                $new_id = (int) $result;

                if ( $existing && $mode === 'replace' ) {
                    ++$report['updated'];
                } else {
                    ++$report['created'];
                    $created[] = $new_id;
                }

                $type = $prepared['post']['post_type'];
                $report['by_type'][ $type ] = ( isset( $report['by_type'][ $type ] ) ? $report['by_type'][ $type ] : 0 ) + 1;

                update_post_meta( $new_id, self::UID_META, $prepared['uid'] );

                $uid_map[ $prepared['uid'] ]        = $new_id;
                $id_map[ $prepared['original_id'] ] = $new_id;

                $entries[] = array(
                    'new_id'   => $new_id,
                    'prepared' => $prepared,
                    'entry'    => $entry,
                    'index'    => $index,
                    'replaced' => (bool) ( $existing && $mode === 'replace' ),
                );
            } catch ( Exception $e ) {
                ++$report['failed'];
                $report['errors'][] = self::describe_entry( $entry, $index ) . ' — ' . $e->getMessage();
            }
        }

        /* --- 2단계: 메타·텀 (이제 모든 새 ID 를 알고 있다) --- */
        foreach ( $entries as $item ) {
            $new_id   = $item['new_id'];
            $prepared = $item['prepared'];

            try {
                if ( $item['replaced'] ) {
                    // 덮어쓰기 모드에서만 우리 메타를 정리한다. 남의 메타는 건드리지 않는다.
                    self::clear_our_meta( $new_id );
                    update_post_meta( $new_id, self::UID_META, $prepared['uid'] );
                }

                $meta = self::remap_refs(
                    $prepared['meta'],
                    $uid_map,
                    $id_map,
                    $prepared['refs']
                );

                foreach ( $meta as $key => $value ) {
                    $value = self::rewrite_uploads_url( $value, $from_uploads, $to_uploads );
                    update_post_meta( $new_id, $key, self::sanitize_meta_value( $key, $value ) );
                }

                // 공개 토큰은 백업에 없으므로 새로 발급한다.
                self::ensure_public_token( $new_id, $prepared['post']['post_type'] );

                $report['terms'] += self::restore_terms( $new_id, $prepared['terms'] );
            } catch ( Exception $e ) {
                // 이 포스트만 되돌린다. 나머지 복원은 계속 진행한다.
                if ( in_array( $new_id, $created, true ) ) {
                    wp_delete_post( $new_id, true );
                    --$report['created'];
                    $type = $prepared['post']['post_type'];
                    if ( isset( $report['by_type'][ $type ] ) ) {
                        --$report['by_type'][ $type ];
                    }
                }
                ++$report['failed'];
                $report['errors'][] = self::describe_entry( $item['entry'], $item['index'] )
                    . ' — 상세 데이터 복원 실패(해당 항목만 취소): ' . $e->getMessage();
            }
        }

        /* --- 3단계: 설정값 --- */
        if ( $args['restore_options'] ) {
            $options = self::filter_options( $data['options'] );
            foreach ( $options as $key => $value ) {
                update_option( $key, self::sanitize_option_value( $key, $value ) );
                ++$report['options'];
            }
        }

        if ( $report['failed'] > 0 ) {
            $report['warnings'][] = $report['failed'] . '건은 복원하지 못했습니다. 나머지는 정상 복원되었습니다.';
        }

        return $report;
    }

    /**
     * 백업 항목 1건을 검증·정리한다.
     *
     * @return array|WP_Error
     */
    private static function prepare_entry( $entry ) {
        if ( ! is_array( $entry ) ) {
            return new WP_Error( 'dd_backup_entry_malformed', '항목 구조가 올바르지 않습니다.' );
        }

        $type = isset( $entry['post_type'] ) ? (string) $entry['post_type'] : '';
        if ( ! self::is_supported_post_type( $type ) ) {
            return new WP_Error(
                'dd_backup_entry_type',
                '이 플러그인이 관리하지 않는 콘텐츠 유형입니다: ' . sanitize_key( $type )
            );
        }

        $post = isset( $entry['post'] ) && is_array( $entry['post'] ) ? $entry['post'] : array();
        if ( ! isset( $post['post_title'] ) ) {
            return new WP_Error( 'dd_backup_entry_malformed', '제목이 없는 항목입니다.' );
        }

        $uid = isset( $entry['uid'] ) ? (string) $entry['uid'] : '';
        // uid 형식이 이상하면 신뢰하지 않고 새로 발급한다 (메타 주입 방지).
        if ( ! preg_match( '/^[a-f0-9\-]{16,64}$/i', $uid ) ) {
            $uid = wp_generate_uuid4();
        }

        $status = isset( $post['post_status'] ) ? sanitize_key( $post['post_status'] ) : 'publish';
        if ( ! in_array( $status, array( 'publish', 'draft', 'pending', 'private' ), true ) ) {
            $status = 'draft';
        }

        return array(
            'uid'         => $uid,
            'original_id' => isset( $entry['original_id'] ) ? absint( $entry['original_id'] ) : 0,
            'post'        => array(
                'post_type'    => $type,
                'post_title'   => sanitize_text_field( (string) $post['post_title'] ),
                'post_content' => wp_kses_post( isset( $post['post_content'] ) ? (string) $post['post_content'] : '' ),
                'post_excerpt' => sanitize_textarea_field( isset( $post['post_excerpt'] ) ? (string) $post['post_excerpt'] : '' ),
                'post_status'  => $status,
                'post_name'    => isset( $post['post_name'] ) ? sanitize_title( (string) $post['post_name'] ) : '',
                'post_date'    => self::sanitize_date( isset( $post['post_date'] ) ? $post['post_date'] : '' ),
                'menu_order'   => isset( $post['menu_order'] ) ? absint( $post['menu_order'] ) : 0,
            ),
            'meta'        => self::filter_meta( isset( $entry['meta'] ) && is_array( $entry['meta'] ) ? $entry['meta'] : array() ),
            'refs'        => self::sanitize_refs( isset( $entry['refs'] ) ? $entry['refs'] : array() ),
            'terms'       => isset( $entry['terms'] ) && is_array( $entry['terms'] ) ? $entry['terms'] : array(),
        );
    }

    private static function sanitize_refs( $refs ) {
        $out = array();
        if ( ! is_array( $refs ) ) {
            return $out;
        }
        foreach ( $refs as $key => $uid ) {
            if ( ! in_array( $key, self::REF_META_KEYS, true ) ) {
                continue;
            }
            if ( is_string( $uid ) && preg_match( '/^[a-f0-9\-]{16,64}$/i', $uid ) ) {
                $out[ $key ] = $uid;
            }
        }
        return $out;
    }

    private static function sanitize_date( $date ) {
        $date = (string) $date;
        if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date ) ) {
            return $date;
        }
        return ''; // WordPress 가 현재 시각으로 채운다.
    }

    private static function sanitize_option_value( $key, $value ) {
        if ( $key === 'dd_lms_purge_content_on_uninstall' ) {
            return $value ? 1 : 0;
        }
        if ( is_array( $value ) ) {
            return self::sanitize_tree( $value );
        }
        return sanitize_text_field( (string) $value );
    }

    /** uid 로 기존 콘텐츠를 찾는다 (중복 복원 방지). */
    private static function find_by_uid( $uid ) {
        if ( $uid === '' ) {
            return 0;
        }

        $found = get_posts( array(
            'post_type'        => self::POST_TYPES,
            'post_status'      => 'any',
            'numberposts'      => 1,
            'fields'           => 'ids',
            'meta_key'         => self::UID_META,
            'meta_value'       => $uid,
            'suppress_filters' => false,
        ) );

        return empty( $found ) ? 0 : (int) $found[0];
    }

    /** 덮어쓰기 모드에서 이 플러그인의 메타만 지운다. */
    private static function clear_our_meta( $post_id ) {
        $all = get_post_meta( $post_id );
        if ( ! is_array( $all ) ) {
            return;
        }
        foreach ( array_keys( $all ) as $key ) {
            if ( strpos( $key, '_dd_' ) === 0 ) {
                delete_post_meta( $post_id, $key );
            }
        }
    }

    /** 공개 공유 토큰을 새로 발급한다 (백업에 담지 않으므로). */
    private static function ensure_public_token( $post_id, $type ) {
        $map = array(
            'dd_lesson'     => '_dd_public_token',
            'dd_story'      => '_dd_story_public_token',
            'dd_newsletter' => '_dd_newsletter_public_token',
        );

        if ( ! isset( $map[ $type ] ) ) {
            return;
        }

        $existing = get_post_meta( $post_id, $map[ $type ], true );
        if ( ! $existing ) {
            update_post_meta( $post_id, $map[ $type ], wp_generate_uuid4() );
        }
    }

    /**
     * 텀 관계를 복원한다. 텀 ID 는 사이트마다 다르므로 **슬러그로 매칭**하고,
     * 없으면 새로 만든다 — 그래서 term ID 충돌이 발생하지 않는다.
     *
     * @return int 연결한 텀 개수
     */
    private static function restore_terms( $post_id, $terms_by_tax ) {
        $count = 0;

        foreach ( $terms_by_tax as $tax => $terms ) {
            $tax = sanitize_key( $tax );
            if ( ! taxonomy_exists( $tax ) || ! is_array( $terms ) ) {
                continue;
            }

            $slugs = array();
            foreach ( $terms as $term ) {
                if ( ! is_array( $term ) || empty( $term['slug'] ) ) {
                    continue;
                }
                $slug = sanitize_title( (string) $term['slug'] );
                $name = isset( $term['name'] ) ? sanitize_text_field( (string) $term['name'] ) : $slug;

                $existing = get_term_by( 'slug', $slug, $tax );
                if ( ! $existing ) {
                    $new = wp_insert_term( $name, $tax, array( 'slug' => $slug ) );
                    if ( is_wp_error( $new ) ) {
                        continue;
                    }
                }
                $slugs[] = $slug;
            }

            if ( ! empty( $slugs ) ) {
                // append = true — 기존에 붙어 있던 텀을 떼지 않는다.
                wp_set_object_terms( $post_id, $slugs, $tax, true );
                $count += count( $slugs );
            }
        }

        return $count;
    }

    private static function describe_entry( $entry, $index ) {
        $title = '';
        if ( is_array( $entry ) && isset( $entry['post']['post_title'] ) ) {
            $title = sanitize_text_field( (string) $entry['post']['post_title'] );
        }
        return '[' . ( (int) $index + 1 ) . '번 항목' . ( $title !== '' ? ': ' . $title : '' ) . ']';
    }

    /* =========================================================
       복원 전 자동 안전 백업
       ========================================================= */

    /**
     * 복원 직전에 현재 데이터를 서버에 저장해 둔다.
     * 복원 결과가 마음에 들지 않을 때 되돌릴 근거가 된다.
     *
     * @return array|WP_Error array{path,file,url}
     */
    public static function write_safety_backup() {
        $dir = self::backup_dir();
        if ( is_wp_error( $dir ) ) {
            return $dir;
        }

        $json = wp_json_encode( self::export(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
        if ( $json === false ) {
            return new WP_Error( 'dd_backup_encode_failed', '백업 데이터를 만들지 못했습니다.' );
        }

        // 파일명에 무작위 접미사 — uploads 는 웹에서 접근 가능하므로 경로를 추측하기 어렵게 한다.
        $file = 'dingdong-lms-autobackup-' . gmdate( 'Y-m-d-H-i-s', current_time( 'timestamp' ) )
            . '-' . wp_generate_password( 12, false, false ) . '.json';

        $path = trailingslashit( $dir ) . $file;

        if ( file_put_contents( $path, $json ) === false ) { // phpcs:ignore
            return new WP_Error( 'dd_backup_write_failed', '자동 백업 파일을 저장하지 못했습니다.' );
        }

        self::prune_auto_backups( $dir );

        return array(
            'path' => $path,
            'file' => $file,
        );
    }

    /**
     * 자동 백업 폴더를 준비한다 (웹 접근 차단 파일 포함).
     *
     * @return string|WP_Error
     */
    private static function backup_dir() {
        $uploads = wp_upload_dir();
        if ( ! empty( $uploads['error'] ) ) {
            return new WP_Error( 'dd_backup_no_uploads', '업로드 폴더를 사용할 수 없습니다: ' . $uploads['error'] );
        }

        $dir = trailingslashit( $uploads['basedir'] ) . 'dingdong-lms/backups';

        if ( ! wp_mkdir_p( $dir ) ) {
            return new WP_Error( 'dd_backup_mkdir_failed', '백업 폴더를 만들지 못했습니다.' );
        }

        $htaccess = trailingslashit( $dir ) . '.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            file_put_contents( $htaccess, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" ); // phpcs:ignore
        }

        $index = trailingslashit( $dir ) . 'index.php';
        if ( ! file_exists( $index ) ) {
            file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore
        }

        return $dir;
    }

    /** 오래된 자동 백업을 정리한다 — 서버에 파일이 무한히 쌓이지 않게. */
    private static function prune_auto_backups( $dir ) {
        $files = glob( trailingslashit( $dir ) . 'dingdong-lms-autobackup-*.json' );
        if ( ! is_array( $files ) || count( $files ) <= self::KEEP_AUTO_BACKUPS ) {
            return;
        }

        sort( $files ); // 파일명이 시간순이므로 이름 정렬 = 시간 정렬
        $stale = array_slice( $files, 0, count( $files ) - self::KEEP_AUTO_BACKUPS );

        foreach ( $stale as $file ) {
            @unlink( $file ); // phpcs:ignore
        }
    }

    /* =========================================================
       요약 (설정 화면 미리보기용)
       ========================================================= */

    /** ZIP 에 담길 이미지 개수·용량 (설정 화면에 미리 보여 주기 위해). */
    public static function media_summary() {
        $root  = trailingslashit( self::media_dir() );
        $count = 0;
        $bytes = 0;

        foreach ( self::collect_media_files() as $relative ) {
            ++$count;
            $bytes += (int) @filesize( $root . $relative ); // phpcs:ignore
        }

        return array( 'count' => $count, 'bytes' => $bytes );
    }

    public static function counts() {
        $out = array();
        foreach ( self::POST_TYPES as $type ) {
            $found = get_posts( array(
                'post_type'   => $type,
                'post_status' => 'any',
                'numberposts' => -1,
                'fields'      => 'ids',
            ) );
            $out[ $type ] = count( $found );
        }
        return $out;
    }
}

/**
 * size_format() 이 없는 환경(테스트 러너)에서도 메시지를 만들 수 있게 한다.
 */
if ( ! function_exists( 'size_format_fallback' ) ) {
    function size_format_fallback( $bytes ) {
        if ( function_exists( 'size_format' ) ) {
            return size_format( $bytes );
        }
        return round( $bytes / 1048576 ) . 'MB';
    }
}
