<?php
class DD_Rest_API {

    private static $namespace = 'dingdong-lms/v1';

    public static function register_routes() {
        $ns = self::$namespace;
        $admin = array( __CLASS__, 'check_admin' );

        // 강좌 CRUD
        register_rest_route( $ns, '/courses', array(
            array( 'methods' => 'GET',  'callback' => array( __CLASS__, 'get_courses' ), 'permission_callback' => $admin ),
            array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'create_course' ), 'permission_callback' => $admin ),
        ) );

        register_rest_route( $ns, '/courses/(?P<id>\d+)', array(
            array( 'methods' => 'GET',    'callback' => array( __CLASS__, 'get_course' ), 'permission_callback' => $admin ),
            array( 'methods' => 'PUT',    'callback' => array( __CLASS__, 'update_course' ), 'permission_callback' => $admin ),
            array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'delete_course' ), 'permission_callback' => $admin ),
        ) );

        // 강의 CRUD
        register_rest_route( $ns, '/courses/(?P<course_id>\d+)/lessons', array(
            array( 'methods' => 'GET',  'callback' => array( __CLASS__, 'get_lessons' ), 'permission_callback' => $admin ),
            array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'create_lesson' ), 'permission_callback' => $admin ),
        ) );

        register_rest_route( $ns, '/lessons/(?P<id>\d+)', array(
            array( 'methods' => 'GET',    'callback' => array( __CLASS__, 'get_lesson' ), 'permission_callback' => $admin ),
            array( 'methods' => 'PUT',    'callback' => array( __CLASS__, 'update_lesson' ), 'permission_callback' => $admin ),
            array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'delete_lesson' ), 'permission_callback' => $admin ),
        ) );

        // AI 생성
        register_rest_route( $ns, '/generate/course', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'generate_course' ),
            'permission_callback' => $admin,
        ) );

        // 1단계 — Gemini 본문 생성 + 저장까지만 (짧게 끝남)
        register_rest_route( $ns, '/generate/lesson', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'generate_lesson' ),
            'permission_callback' => $admin,
        ) );

        // 2단계 — 이미지/YouTube 를 단계별 개별 요청으로 생성
        register_rest_route( $ns, '/generate/lesson-assets', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'generate_lesson_assets' ),
            'permission_callback' => $admin,
        ) );

        // 복구 — 응답이 타임아웃으로 유실됐을 때 실제 생성 여부 확인
        register_rest_route( $ns, '/generate/lesson-lookup', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'generate_lesson_lookup' ),
            'permission_callback' => $admin,
        ) );

        // 공개 링크 토글
        register_rest_route( $ns, '/lessons/(?P<id>\d+)/toggle-public', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'toggle_public' ),
            'permission_callback' => $admin,
        ) );

        // 설정
        register_rest_route( $ns, '/settings/api-key', array(
            'methods'             => 'PUT',
            'callback'            => array( __CLASS__, 'save_api_key' ),
            'permission_callback' => $admin,
        ) );

        register_rest_route( $ns, '/settings/api-key-status', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'api_key_status' ),
            'permission_callback' => $admin,
        ) );

        register_rest_route( $ns, '/settings/model', array(
            array( 'methods' => 'GET',  'callback' => array( __CLASS__, 'get_model' ), 'permission_callback' => $admin ),
            array( 'methods' => 'PUT',  'callback' => array( __CLASS__, 'save_model' ), 'permission_callback' => $admin ),
        ) );

        // Pixabay 설정
        register_rest_route( $ns, '/settings/pixabay-key', array(
            'methods'             => 'PUT',
            'callback'            => array( __CLASS__, 'save_pixabay_key' ),
            'permission_callback' => $admin,
        ) );

        // 삭제 시 콘텐츠 제거 여부 (기본 false = 보존)
        register_rest_route( $ns, '/settings/purge-content', array(
            array( 'methods' => 'GET',  'callback' => array( __CLASS__, 'get_purge_content' ),  'permission_callback' => $admin ),
            array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'save_purge_content' ), 'permission_callback' => $admin ),
        ) );

        register_rest_route( $ns, '/settings/pixabay-key-status', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'pixabay_key_status' ),
            'permission_callback' => $admin,
        ) );

        // YouTube 설정
        register_rest_route( $ns, '/settings/youtube-key', array(
            'methods'             => 'PUT',
            'callback'            => array( __CLASS__, 'save_youtube_key' ),
            'permission_callback' => $admin,
        ) );

        register_rest_route( $ns, '/settings/youtube-key-status', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'youtube_key_status' ),
            'permission_callback' => $admin,
        ) );

        register_rest_route( $ns, '/settings/youtube-check', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'youtube_check' ),
            'permission_callback' => $admin,
        ) );

        // 기존 강의에 YouTube 영상 재검색
        register_rest_route( $ns, '/lessons/(?P<id>\d+)/retry-youtube', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'retry_youtube' ),
            'permission_callback' => $admin,
        ) );

        // 강의 이미지 개별 재생성
        register_rest_route( $ns, '/lessons/(?P<id>\d+)/regenerate-image', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'regenerate_image' ),
            'permission_callback' => $admin,
        ) );

        // 인터랙티브 스토리 CRUD
        register_rest_route( $ns, '/stories', array(
            array( 'methods' => 'GET',  'callback' => array( __CLASS__, 'get_stories' ),  'permission_callback' => $admin ),
            array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'create_story' ), 'permission_callback' => $admin ),
        ) );

        register_rest_route( $ns, '/stories/(?P<id>\d+)', array(
            array( 'methods' => 'GET',    'callback' => array( __CLASS__, 'get_story' ),    'permission_callback' => $admin ),
            array( 'methods' => 'PUT',    'callback' => array( __CLASS__, 'update_story' ), 'permission_callback' => $admin ),
            array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'delete_story' ), 'permission_callback' => $admin ),
        ) );

        register_rest_route( $ns, '/generate/story', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'generate_story' ),
            'permission_callback' => $admin,
        ) );

        register_rest_route( $ns, '/stories/(?P<id>\d+)/toggle-public', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'toggle_story_public' ),
            'permission_callback' => $admin,
        ) );

        // 중국어 노래 학습 (YouTube 자막 파이프라인 재사용, 별도 generator)
        register_rest_route( $ns, '/song/fetch-info', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'song_fetch_info' ),
            'permission_callback' => $admin,
        ) );

        register_rest_route( $ns, '/song/fetch-subtitles', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'song_fetch_subtitles' ),
            'permission_callback' => $admin,
        ) );

        register_rest_route( $ns, '/song/generate', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'song_generate' ),
            'permission_callback' => $admin,
        ) );

        register_rest_route( $ns, '/song/generate-track', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'song_generate_track' ),
            'permission_callback' => $admin,
        ) );

        // 학습 자료 첨부
        register_rest_route( $ns, '/lessons/(?P<id>\d+)/materials', array(
            array( 'methods' => 'GET',  'callback' => array( __CLASS__, 'get_materials' ),  'permission_callback' => $admin ),
            array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'add_material' ),   'permission_callback' => $admin ),
        ) );

        register_rest_route( $ns, '/lessons/(?P<id>\d+)/materials/(?P<index>\d+)', array(
            array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'delete_material' ), 'permission_callback' => $admin ),
        ) );

        // 뉴스레터 CRUD
        register_rest_route( $ns, '/newsletters', array(
            array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'get_newsletters' ), 'permission_callback' => $admin ),
        ) );

        register_rest_route( $ns, '/generate/newsletter', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'generate_newsletter' ),
            'permission_callback' => $admin,
        ) );

        register_rest_route( $ns, '/newsletters/(?P<id>\d+)', array(
            array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'delete_newsletter' ), 'permission_callback' => $admin ),
        ) );

        register_rest_route( $ns, '/newsletters/(?P<id>\d+)/toggle-public', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'toggle_newsletter_public' ),
            'permission_callback' => $admin,
        ) );

        // 데이터 백업 / 복원 (관리자 전용)
        register_rest_route( $ns, '/backup/info', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'backup_info' ),
            'permission_callback' => $admin,
        ) );

        // ⚠️ GET 이 아니라 POST 다. 백업 생성은 포스트에 영구 UID 를 부여하는
        //    **쓰기 작업**이므로, 부수효과 없는 GET 으로 두면 안 된다.
        register_rest_route( $ns, '/backup/export', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'backup_export' ),
            'permission_callback' => $admin,
        ) );

        register_rest_route( $ns, '/backup/import', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'backup_import' ),
            'permission_callback' => $admin,
        ) );

        // 공개 API (인증 불필요)
        register_rest_route( $ns, '/public/newsletters', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'public_newsletters' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( $ns, '/public/newsletter/(?P<token>[a-f0-9\-]+)', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'public_newsletter' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( $ns, '/public/lesson/(?P<token>[a-f0-9\-]+)', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'public_lesson' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( $ns, '/public/story/(?P<token>[a-f0-9\-]+)', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'public_story' ),
            'permission_callback' => '__return_true',
        ) );
    }

    public static function check_admin() {
        return current_user_can( 'manage_options' );
    }

    // --- 강좌 ---

    public static function get_courses() {
        $posts = get_posts( array(
            'post_type'   => 'dd_course',
            'numberposts' => 100,
            'post_status' => 'publish',
            'orderby'     => 'date',
            'order'       => 'DESC',
        ) );

        $courses = array();
        foreach ( $posts as $p ) {
            $lesson_count = self::count_course_lessons( $p->ID );
            $courses[] = array(
                'id'          => $p->ID,
                'title'       => $p->post_title,
                'description' => $p->post_content,
                'status'      => get_post_meta( $p->ID, '_dd_course_status', true ) ?: 'draft',
                'lesson_count' => $lesson_count,
                'thumbnail'   => get_post_meta( $p->ID, '_dd_course_thumbnail', true ) ?: '',
                'course_type' => get_post_meta( $p->ID, '_dd_course_type', true ) ?: '',
                'genre'       => get_post_meta( $p->ID, '_dd_course_genre', true ) ?: '',
                'created'     => $p->post_date,
            );
        }

        return rest_ensure_response( $courses );
    }

    public static function get_course( $request ) {
        $post = get_post( $request['id'] );
        if ( ! $post || $post->post_type !== 'dd_course' ) {
            return new WP_Error( 'not_found', '강좌를 찾을 수 없습니다.', array( 'status' => 404 ) );
        }

        $lesson_posts = get_posts( array(
            'post_type'      => 'dd_lesson',
            'numberposts'    => 100,
            'post_status'    => 'publish',
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC',
            'meta_query'     => array(
                array( 'key' => '_dd_course_id', 'value' => $post->ID ),
            ),
            'meta_key'       => '_dd_lesson_order',
        ) );

        $lessons = array();
        foreach ( $lesson_posts as $lp ) {
            $lessons[] = self::format_lesson( $lp );
        }

        return rest_ensure_response( array(
            'id'           => $post->ID,
            'title'        => $post->post_title,
            'description'  => $post->post_content,
            'intro'        => get_post_meta( $post->ID, '_dd_course_intro', true ) ?: '',
            'genre'        => get_post_meta( $post->ID, '_dd_course_genre', true ) ?: '',
            'level'        => get_post_meta( $post->ID, '_dd_course_level', true ) ?: '',
            'artist'       => get_post_meta( $post->ID, '_dd_course_artist', true ) ?: '',
            'status'       => get_post_meta( $post->ID, '_dd_course_status', true ) ?: 'draft',
            'lesson_count' => count( $lessons ),
            'lessons'      => $lessons,
        ) );
    }

    public static function create_course( $request ) {
        $title = sanitize_text_field( $request->get_param( 'title' ) );
        $desc  = sanitize_textarea_field( $request->get_param( 'description' ) );

        $id = wp_insert_post( array(
            'post_type'    => 'dd_course',
            'post_title'   => $title,
            'post_content' => $desc,
            'post_status'  => 'publish',
        ) );

        if ( is_wp_error( $id ) ) {
            return $id;
        }

        update_post_meta( $id, '_dd_course_status', 'draft' );

        return rest_ensure_response( array( 'id' => $id, 'title' => $title ) );
    }

    public static function update_course( $request ) {
        $post = get_post( $request['id'] );
        if ( ! $post || $post->post_type !== 'dd_course' ) {
            return new WP_Error( 'not_found', '강좌를 찾을 수 없습니다.', array( 'status' => 404 ) );
        }

        $args = array( 'ID' => $post->ID );
        if ( $request->get_param( 'title' ) ) {
            $args['post_title'] = sanitize_text_field( $request->get_param( 'title' ) );
        }
        if ( $request->get_param( 'description' ) ) {
            $args['post_content'] = sanitize_textarea_field( $request->get_param( 'description' ) );
        }

        wp_update_post( $args );

        // 강좌 메타: 소개(강의 페이지 표시), 장르, 레벨, 가수
        if ( $request->get_param( 'intro' ) !== null ) {
            update_post_meta( $post->ID, '_dd_course_intro', sanitize_textarea_field( $request->get_param( 'intro' ) ) );
        }
        if ( $request->get_param( 'genre' ) !== null ) {
            update_post_meta( $post->ID, '_dd_course_genre', sanitize_text_field( $request->get_param( 'genre' ) ) );
        }
        if ( $request->get_param( 'level' ) !== null ) {
            update_post_meta( $post->ID, '_dd_course_level', sanitize_text_field( $request->get_param( 'level' ) ) );
        }
        if ( $request->get_param( 'artist' ) !== null ) {
            update_post_meta( $post->ID, '_dd_course_artist', sanitize_text_field( $request->get_param( 'artist' ) ) );
        }

        return rest_ensure_response( array( 'id' => $post->ID, 'updated' => true ) );
    }

    public static function delete_course( $request ) {
        $post = get_post( $request['id'] );
        if ( ! $post || $post->post_type !== 'dd_course' ) {
            return new WP_Error( 'not_found', '강좌를 찾을 수 없습니다.', array( 'status' => 404 ) );
        }

        $lessons = get_posts( array(
            'post_type'   => 'dd_lesson',
            'meta_key'    => '_dd_course_id',
            'meta_value'  => $post->ID,
            'numberposts' => -1,
            'fields'      => 'ids',
        ) );

        foreach ( $lessons as $lid ) {
            wp_delete_post( $lid, true );
        }

        wp_delete_post( $post->ID, true );

        return rest_ensure_response( array( 'deleted' => true ) );
    }

    // --- 강의 ---

    public static function get_lessons( $request ) {
        $posts = get_posts( array(
            'post_type'   => 'dd_lesson',
            'numberposts' => 100,
            'post_status' => 'publish',
            'orderby'     => 'meta_value_num',
            'order'       => 'ASC',
            'meta_query'  => array(
                array(
                    'key'   => '_dd_course_id',
                    'value' => $request['course_id'],
                ),
            ),
            'meta_key' => '_dd_lesson_order',
        ) );

        $lessons = array();
        foreach ( $posts as $p ) {
            $lessons[] = self::format_lesson( $p );
        }

        return rest_ensure_response( $lessons );
    }

    public static function get_lesson( $request ) {
        $post = get_post( $request['id'] );
        if ( ! $post || $post->post_type !== 'dd_lesson' ) {
            return new WP_Error( 'not_found', '강의를 찾을 수 없습니다.', array( 'status' => 404 ) );
        }
        return rest_ensure_response( self::format_lesson( $post ) );
    }

    public static function create_lesson( $request ) {
        $course_id = (int) $request['course_id'];
        $title     = sanitize_text_field( $request->get_param( 'title' ) );
        $content   = $request->get_param( 'content' ) ?: '';
        $order     = (int) $request->get_param( 'order' );

        $id = wp_insert_post( array(
            'post_type'    => 'dd_lesson',
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'publish',
        ) );

        if ( is_wp_error( $id ) ) {
            return $id;
        }

        $token = wp_generate_uuid4();
        update_post_meta( $id, '_dd_course_id', $course_id );
        update_post_meta( $id, '_dd_lesson_order', $order );
        update_post_meta( $id, '_dd_public_token', $token );
        update_post_meta( $id, '_dd_public_active', '1' );
        update_post_meta( $id, '_dd_slides_data', wp_json_encode( array(), JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $id, '_dd_quiz_data', wp_json_encode( array(), JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $id, '_dd_video_urls', wp_json_encode( array(), JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $id, '_dd_embed_urls', wp_json_encode( array(), JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $id, '_dd_storybook_data', wp_json_encode( array(), JSON_UNESCAPED_UNICODE ) );

        return rest_ensure_response( array( 'id' => $id, 'token' => $token ) );
    }

    public static function update_lesson( $request ) {
        $post = get_post( $request['id'] );
        if ( ! $post || $post->post_type !== 'dd_lesson' ) {
            return new WP_Error( 'not_found', '강의를 찾을 수 없습니다.', array( 'status' => 404 ) );
        }

        $args = array( 'ID' => $post->ID );
        if ( $request->get_param( 'title' ) !== null ) {
            $args['post_title'] = sanitize_text_field( $request->get_param( 'title' ) );
        }
        if ( $request->get_param( 'content' ) !== null ) {
            $args['post_content'] = $request->get_param( 'content' );
        }
        wp_update_post( $args );

        if ( $request->get_param( 'slides' ) !== null ) {
            update_post_meta( $post->ID, '_dd_slides_data', wp_json_encode( $request->get_param( 'slides' ), JSON_UNESCAPED_UNICODE ) );
        }
        if ( $request->get_param( 'quiz' ) !== null ) {
            update_post_meta( $post->ID, '_dd_quiz_data', wp_json_encode( $request->get_param( 'quiz' ), JSON_UNESCAPED_UNICODE ) );
        }
        if ( $request->get_param( 'videos' ) !== null ) {
            update_post_meta( $post->ID, '_dd_video_urls', wp_json_encode( $request->get_param( 'videos' ), JSON_UNESCAPED_UNICODE ) );
        }
        if ( $request->get_param( 'year' ) !== null ) {
            update_post_meta( $post->ID, '_dd_lesson_year', sanitize_text_field( $request->get_param( 'year' ) ) );
        }
        if ( $request->get_param( 'artist' ) !== null ) {
            update_post_meta( $post->ID, '_dd_lesson_artist', sanitize_text_field( $request->get_param( 'artist' ) ) );
        }

        return rest_ensure_response( array( 'id' => $post->ID, 'updated' => true ) );
    }

    public static function delete_lesson( $request ) {
        $post = get_post( $request['id'] );
        if ( ! $post || $post->post_type !== 'dd_lesson' ) {
            return new WP_Error( 'not_found', '강의를 찾을 수 없습니다.', array( 'status' => 404 ) );
        }
        wp_delete_post( $post->ID, true );
        return rest_ensure_response( array( 'deleted' => true ) );
    }

    // --- AI 생성 ---

    public static function generate_course( $request ) {
        @set_time_limit( 300 );
        try {
            $topic = sanitize_text_field( $request->get_param( 'topic' ) );
            if ( empty( $topic ) ) {
                return new WP_Error( 'missing_topic', '주제를 입력해 주세요.', array( 'status' => 400 ) );
            }

            $lesson_count = (int) $request->get_param( 'lesson_count' );
            if ( $lesson_count < 1 || $lesson_count > 12 ) {
                $lesson_count = 4;
            }

            $level = sanitize_text_field( $request->get_param( 'level' ) );
            if ( ! in_array( $level, array( 'beginner', 'intermediate', 'advanced' ), true ) ) {
                $level = 'beginner';
            }

            $result = DD_Course_Generator::generate_outline( $topic, $lesson_count, $level );
            if ( is_wp_error( $result ) ) {
                return $result;
            }

            $course_id = $result['course_id'];
            $total     = count( $result['lessons'] );
            update_post_meta( $course_id, '_dd_course_total_lessons', $total );

            return rest_ensure_response( $result );

        } catch ( \Exception $e ) {
            return new WP_Error( 'generate_error', '강좌 생성 오류: ' . $e->getMessage(), array( 'status' => 500 ) );
        } catch ( \Error $e ) {
            return new WP_Error( 'generate_fatal', '강좌 생성 치명적 오류: ' . $e->getMessage(), array( 'status' => 500 ) );
        }
    }

    /**
     * 1단계 — Gemini 본문만 생성해 강의를 저장하고 즉시 반환한다.
     * 이미지/YouTube 는 generate_lesson_assets() 가 단계별로 처리한다.
     */
    public static function generate_lesson( $request ) {
        @set_time_limit( 300 );
        try {
            $course_id    = (int) $request->get_param( 'course_id' );
            $lesson_title = sanitize_text_field( $request->get_param( 'title' ) );
            $order        = (int) $request->get_param( 'order' );
            $client_ref   = sanitize_text_field( (string) $request->get_param( 'client_ref' ) );

            if ( ! $course_id || empty( $lesson_title ) ) {
                return new WP_Error( 'missing_params', '필수 파라미터가 없습니다.', array( 'status' => 400 ) );
            }

            $result = DD_Course_Generator::generate_lesson_text( $course_id, $lesson_title, $order, $client_ref );
            if ( is_wp_error( $result ) ) {
                return $result;
            }

            return rest_ensure_response( $result );

        } catch ( \Exception $e ) {
            return new WP_Error( 'lesson_error', '강의 생성 오류: ' . $e->getMessage(), array( 'status' => 500 ) );
        } catch ( \Error $e ) {
            return new WP_Error( 'lesson_fatal', '강의 생성 치명적 오류: ' . $e->getMessage(), array( 'status' => 500 ) );
        }
    }

    /**
     * 2단계 — 에셋 한 단계만 생성한다. 요청 하나가 짧게 끝나도록 의도적으로 쪼갠 것.
     * 에셋 실패는 강의 자체를 실패로 만들지 않으므로 200 + ok:false 로 응답한다.
     */
    public static function generate_lesson_assets( $request ) {
        @set_time_limit( 300 );
        try {
            $lesson_id = (int) $request->get_param( 'lesson_id' );
            $phase     = sanitize_text_field( (string) $request->get_param( 'phase' ) );

            if ( ! $lesson_id || $phase === '' ) {
                return new WP_Error( 'missing_params', 'lesson_id 와 phase 가 필요합니다.', array( 'status' => 400 ) );
            }

            $result = DD_Course_Generator::generate_lesson_assets( $lesson_id, $phase );
            if ( is_wp_error( $result ) ) {
                return $result;
            }

            return rest_ensure_response( $result );

        } catch ( \Exception $e ) {
            return new WP_Error( 'asset_error', '에셋 생성 오류: ' . $e->getMessage(), array( 'status' => 500 ) );
        } catch ( \Error $e ) {
            return new WP_Error( 'asset_fatal', '에셋 생성 치명적 오류: ' . $e->getMessage(), array( 'status' => 500 ) );
        }
    }

    /**
     * 복구 — client_ref 로 강의가 실제로 만들어졌는지 확인한다.
     * 본문 생성 응답이 프록시 타임아웃으로 유실돼도 클라이언트가 결과를 회수해
     * "실패" 로 오인하지 않게 해 준다.
     */
    public static function generate_lesson_lookup( $request ) {
        $course_id  = (int) $request->get_param( 'course_id' );
        $client_ref = sanitize_text_field( (string) $request->get_param( 'client_ref' ) );

        if ( ! $course_id || $client_ref === '' ) {
            return new WP_Error( 'missing_params', 'course_id 와 client_ref 가 필요합니다.', array( 'status' => 400 ) );
        }

        $lesson_id = DD_Course_Generator::find_by_client_ref( $course_id, $client_ref );
        if ( ! $lesson_id ) {
            return rest_ensure_response( array( 'found' => false ) );
        }

        $result = DD_Course_Generator::lesson_lookup_response( $lesson_id, $client_ref );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( array_merge( array( 'found' => true ), $result ) );
    }

    // --- 공개 링크 ---

    public static function toggle_public( $request ) {
        $post = get_post( $request['id'] );
        if ( ! $post || $post->post_type !== 'dd_lesson' ) {
            return new WP_Error( 'not_found', '강의를 찾을 수 없습니다.', array( 'status' => 404 ) );
        }

        $current = get_post_meta( $post->ID, '_dd_public_active', true );
        $new_val = $current === '1' ? '0' : '1';
        update_post_meta( $post->ID, '_dd_public_active', $new_val );

        return rest_ensure_response( array(
            'id'     => $post->ID,
            'active' => $new_val === '1',
            'url'    => $new_val === '1' ? DD_Public_Access::get_public_url( $post->ID ) : '',
        ) );
    }

    // --- 설정 ---

    public static function save_api_key( $request ) {
        $key = sanitize_text_field( $request->get_param( 'api_key' ) );
        if ( empty( $key ) ) {
            DD_API_Key::delete();
            return rest_ensure_response( array( 'saved' => true, 'has_key' => false ) );
        }

        DD_API_Key::save( $key );
        return rest_ensure_response( array( 'saved' => true, 'has_key' => true ) );
    }

    public static function api_key_status() {
        $has = DD_API_Key::has_key();
        return rest_ensure_response( array( 'has_key' => $has, 'is_set' => $has ) );
    }

    /**
     * 빈 문자열 = "자동" — DD_Gemini 의 모델 체인을 그대로 따른다.
     * 예전에는 옵션이 없을 때 특정 모델명을 기본값으로 응답해, 실제로는 쓰이지 않는
     * 모델이 설정 화면에 선택된 것처럼 보였다.
     */
    public static function get_model() {
        return rest_ensure_response( array( 'model' => (string) get_option( 'dd_lms_gemini_model', '' ) ) );
    }

    public static function save_model( $request ) {
        $model = sanitize_text_field( (string) $request->get_param( 'model' ) );
        $allowed = array( '', 'gemini-3.5-flash', 'gemini-3-flash-preview', 'gemini-3.1-flash-lite', 'gemini-3.1-flash-lite-preview' );
        if ( ! in_array( $model, $allowed, true ) ) {
            return new WP_Error( 'invalid_model', '지원하지 않는 모델입니다.', array( 'status' => 400 ) );
        }
        update_option( 'dd_lms_gemini_model', $model );
        return rest_ensure_response( array( 'model' => $model, 'saved' => true ) );
    }

    // --- Pixabay ---

    public static function save_pixabay_key( $request ) {
        $key = sanitize_text_field( $request->get_param( 'api_key' ) );
        if ( empty( $key ) ) {
            DD_Thumbnail::delete_key();
            return rest_ensure_response( array( 'saved' => true, 'has_key' => false ) );
        }

        DD_Thumbnail::save_key( $key );
        return rest_ensure_response( array( 'saved' => true, 'has_key' => true ) );
    }

    public static function get_purge_content() {
        return rest_ensure_response( array(
            'purge' => (bool) get_option( 'dd_lms_purge_content_on_uninstall', false ),
        ) );
    }

    public static function save_purge_content( $request ) {
        $purge = (bool) $request->get_param( 'purge' );
        update_option( 'dd_lms_purge_content_on_uninstall', $purge );
        return rest_ensure_response( array( 'purge' => $purge, 'saved' => true ) );
    }

    public static function pixabay_key_status() {
        $has = DD_Thumbnail::has_key();
        return rest_ensure_response( array( 'has_key' => $has ) );
    }

    // --- YouTube ---

    public static function save_youtube_key( $request ) {
        $key = sanitize_text_field( $request->get_param( 'api_key' ) );
        if ( empty( $key ) ) {
            DD_YouTube_Search::delete_key();
            return rest_ensure_response( array( 'saved' => true, 'has_key' => false ) );
        }

        DD_YouTube_Search::save_key( $key );
        return rest_ensure_response( array( 'saved' => true, 'has_key' => true ) );
    }

    public static function youtube_key_status() {
        $has = DD_YouTube_Search::has_key();
        return rest_ensure_response( array( 'has_key' => $has ) );
    }

    public static function youtube_check() {
        if ( ! DD_YouTube_Search::has_key() ) {
            return rest_ensure_response( array(
                'available' => false,
                'message'   => 'YouTube API 키가 설정되지 않았습니다. 먼저 키를 입력하세요.',
            ) );
        }

        $test = DD_YouTube_Search::search( 'Chinese culture test', 1 );

        if ( is_wp_error( $test ) ) {
            $code = $test->get_error_code();
            $msg  = $test->get_error_message();

            if ( $code === 'yt_not_enabled' || strpos( $msg, 'has not been used' ) !== false || strpos( $msg, 'is not enabled' ) !== false ) {
                return rest_ensure_response( array(
                    'available' => false,
                    'message'   => 'YouTube Data API v3가 활성화되지 않았습니다. Google Cloud Console에서 활성화하세요.',
                ) );
            }

            return rest_ensure_response( array(
                'available' => false,
                'message'   => 'YouTube API 오류: ' . $msg,
            ) );
        }

        return rest_ensure_response( array(
            'available' => true,
            'message'   => 'YouTube Data API v3가 정상 작동합니다.',
        ) );
    }

    // --- YouTube 재검색 ---

    public static function retry_youtube( $request ) {
        $lesson_id = (int) $request['id'];
        $lesson    = get_post( $lesson_id );

        if ( ! $lesson || $lesson->post_type !== 'dd_lesson' ) {
            return new WP_Error( 'not_found', '강의를 찾을 수 없습니다.', array( 'status' => 404 ) );
        }

        if ( ! DD_YouTube_Search::has_key() ) {
            return new WP_Error( 'no_key', 'YouTube API 키가 설정되지 않았습니다.', array( 'status' => 400 ) );
        }

        $keywords = json_decode( get_post_meta( $lesson_id, '_dd_video_keywords', true ), true ) ?: array();
        if ( empty( $keywords ) ) {
            return new WP_Error( 'no_keywords', '검색 키워드가 없습니다.', array( 'status' => 400 ) );
        }

        $keywords = array_slice( $keywords, 0, 2 );
        $results  = DD_YouTube_Search::auto_embed_for_lesson( $lesson_id, $keywords );

        if ( is_wp_error( $results ) ) {
            return rest_ensure_response( array(
                'success' => false,
                'message' => $results->get_error_message(),
            ) );
        }

        return rest_ensure_response( array(
            'success' => true,
            'count'   => count( $results ),
            'embeds'  => $results,
        ) );
    }

    /**
     * 강의 이미지 개별 재생성
     * POST body: { "type": "key_expr" | "dialogue" | "comic_panel" | "storybook_page", "index": 0 }
     */
    public static function regenerate_image( $request ) {
        $lesson_id = (int) $request['id'];
        $lesson    = get_post( $lesson_id );

        if ( ! $lesson || $lesson->post_type !== 'dd_lesson' ) {
            return new WP_Error( 'not_found', '강의를 찾을 수 없습니다.', array( 'status' => 404 ) );
        }

        $body  = $request->get_json_params();
        $type  = isset( $body['type'] ) ? sanitize_text_field( $body['type'] ) : '';
        $index = isset( $body['index'] ) ? (int) $body['index'] : 0;

        if ( ! in_array( $type, array( 'key_expr', 'dialogue', 'comic_panel', 'storybook_page' ), true ) ) {
            return new WP_Error( 'invalid_type', '유효하지 않은 이미지 타입입니다.', array( 'status' => 400 ) );
        }

        $result = null;

        switch ( $type ) {
            case 'key_expr':
                $key_exprs = json_decode( get_post_meta( $lesson_id, '_dd_key_expressions', true ), true ) ?: array();
                if ( empty( $key_exprs ) ) {
                    return new WP_Error( 'no_data', '핵심 표현 데이터가 없습니다.', array( 'status' => 400 ) );
                }
                $result = DD_Image_Generator::generate_key_expressions_image( $lesson_id, $key_exprs, $lesson->post_title );
                if ( ! is_wp_error( $result ) ) {
                    $result = array( 'url' => $result, 'type' => 'key_expr' );
                }
                break;

            case 'dialogue':
                $scene_desc = get_post_meta( $lesson_id, '_dd_dialogue_scene', true );
                if ( empty( $scene_desc ) ) {
                    return new WP_Error( 'no_data', '대화 장면 설명이 없습니다.', array( 'status' => 400 ) );
                }
                $result = DD_Image_Generator::generate_dialogue_image( $lesson_id, $scene_desc );
                if ( ! is_wp_error( $result ) ) {
                    $result = array( 'url' => $result, 'type' => 'dialogue' );
                }
                break;

            case 'comic_panel':
                $panels = json_decode( get_post_meta( $lesson_id, '_dd_comic_data', true ), true ) ?: array();
                if ( empty( $panels ) || ! isset( $panels[ $index ] ) ) {
                    return new WP_Error( 'no_data', '만화 패널 데이터가 없습니다. (index: ' . $index . ')', array( 'status' => 400 ) );
                }
                $panel = $panels[ $index ];
                $image_prompt = ! empty( $panel['image_prompt'] ) ? $panel['image_prompt'] : ( ! empty( $panel['scene'] ) ? $panel['scene'] : '' );
                if ( empty( $image_prompt ) ) {
                    return new WP_Error( 'no_prompt', '패널 이미지 프롬프트가 없습니다.', array( 'status' => 400 ) );
                }
                $character_seed = DD_Image_Generator::build_character_seed( $panels );
                $prompt = 'Create panel ' . ( $index + 1 ) . ' of a 4-panel Chinese language learning comic. '
                        . $character_seed
                        . 'Scene: ' . $image_prompt . ' '
                        . DD_Image_Generator::get_unified_style()
                        . ' Pure illustration only. Square composition.';
                $gen = DD_Image_Generator::generate( $prompt, '1:1' );
                if ( is_wp_error( $gen ) ) {
                    $result = $gen;
                } else {
                    $saved = DD_Image_Generator::save_image( $gen, 'comic-' . $lesson_id . '-' . ( $index + 1 ) );
                    if ( is_wp_error( $saved ) ) {
                        $result = $saved;
                    } else {
                        $existing = json_decode( get_post_meta( $lesson_id, '_dd_comic_images', true ), true ) ?: array();
                        while ( count( $existing ) <= $index ) { $existing[] = ''; }
                        $existing[ $index ] = $saved['url'];
                        update_post_meta( $lesson_id, '_dd_comic_images', wp_json_encode( $existing, JSON_UNESCAPED_UNICODE ) );
                        $result = array( 'url' => $saved['url'], 'type' => 'comic_panel', 'index' => $index );
                    }
                }
                break;

            case 'storybook_page':
                $storybook = json_decode( get_post_meta( $lesson_id, '_dd_storybook_data', true ), true ) ?: array();
                if ( empty( $storybook ) || ! isset( $storybook[ $index ] ) ) {
                    return new WP_Error( 'no_data', '스토리북 페이지가 없습니다. (index: ' . $index . ')', array( 'status' => 400 ) );
                }
                $page = $storybook[ $index ];
                $scene = ! empty( $page['image_prompt'] ) ? $page['image_prompt'] : ( ! empty( $page['scene'] ) ? $page['scene'] : '' );
                if ( empty( $scene ) ) {
                    return new WP_Error( 'no_prompt', '스토리북 이미지 프롬프트가 없습니다.', array( 'status' => 400 ) );
                }
                $prompt = 'Create a storybook illustration for a Chinese language textbook. '
                        . 'Scene: ' . $scene . ' '
                        . DD_Image_Generator::get_unified_style()
                        . ' Warm, storybook atmosphere.';
                $gen = DD_Image_Generator::generate( $prompt, '16:9' );
                if ( is_wp_error( $gen ) ) {
                    $result = $gen;
                } else {
                    $saved = DD_Image_Generator::save_image( $gen, 'storybook-' . $lesson_id . '-' . ( $index + 1 ) );
                    if ( is_wp_error( $saved ) ) {
                        $result = $saved;
                    } else {
                        $storybook[ $index ]['image'] = $saved['url'];
                        update_post_meta( $lesson_id, '_dd_storybook_data', wp_json_encode( $storybook, JSON_UNESCAPED_UNICODE ) );
                        $result = array( 'url' => $saved['url'], 'type' => 'storybook_page', 'index' => $index );
                    }
                }
                break;
        }

        if ( is_wp_error( $result ) ) {
            return rest_ensure_response( array(
                'success' => false,
                'message' => $result->get_error_message(),
            ) );
        }

        return rest_ensure_response( array(
            'success' => true,
            'image'   => $result,
        ) );
    }

    // --- 공개 API ---

    public static function public_lesson( $request ) {
        $token = sanitize_text_field( $request['token'] );

        $lessons = get_posts( array(
            'post_type'   => 'dd_lesson',
            'meta_key'    => '_dd_public_token',
            'meta_value'  => $token,
            'numberposts' => 1,
            'post_status' => 'publish',
        ) );

        if ( empty( $lessons ) ) {
            return new WP_Error( 'not_found', '강의를 찾을 수 없습니다.', array( 'status' => 404 ) );
        }

        $lesson = $lessons[0];
        $active = get_post_meta( $lesson->ID, '_dd_public_active', true );

        if ( $active !== '1' ) {
            return new WP_Error( 'inactive', '이 링크는 비활성화되었습니다.', array( 'status' => 403 ) );
        }

        return rest_ensure_response( DD_Public_Access::get_lesson_data( $lesson ) );
    }

    // --- 인터랙티브 스토리 ---

    public static function get_stories( $request ) {
        $posts = get_posts( array(
            'post_type'   => 'dd_story',
            'numberposts' => 100,
            'post_status' => 'publish',
            'orderby'     => 'date',
            'order'       => 'DESC',
        ) );

        $stories = array();
        foreach ( $posts as $p ) {
            $stories[] = self::format_story( $p );
        }
        return rest_ensure_response( $stories );
    }

    public static function get_story( $request ) {
        $post = get_post( $request['id'] );
        if ( ! $post || $post->post_type !== 'dd_story' ) {
            return new WP_Error( 'not_found', '스토리를 찾을 수 없습니다.', array( 'status' => 404 ) );
        }
        return rest_ensure_response( self::format_story( $post ) );
    }

    public static function create_story( $request ) {
        $title = sanitize_text_field( $request->get_param( 'title' ) );
        $desc  = sanitize_textarea_field( $request->get_param( 'description' ) ?: '' );

        $id = wp_insert_post( array(
            'post_type'    => 'dd_story',
            'post_title'   => $title,
            'post_content' => $desc,
            'post_status'  => 'publish',
        ) );

        if ( is_wp_error( $id ) ) {
            return $id;
        }

        $token = wp_generate_uuid4();
        update_post_meta( $id, '_dd_story_nodes', wp_json_encode( array(), JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $id, '_dd_story_course_id', 0 );
        update_post_meta( $id, '_dd_story_level', 'beginner' );
        update_post_meta( $id, '_dd_story_public_token', $token );
        update_post_meta( $id, '_dd_story_public_active', '1' );
        update_post_meta( $id, '_dd_story_cover_image', '' );

        return rest_ensure_response( array( 'id' => $id, 'token' => $token ) );
    }

    public static function update_story( $request ) {
        $post = get_post( $request['id'] );
        if ( ! $post || $post->post_type !== 'dd_story' ) {
            return new WP_Error( 'not_found', '스토리를 찾을 수 없습니다.', array( 'status' => 404 ) );
        }

        $args = array( 'ID' => $post->ID );
        if ( $request->get_param( 'title' ) !== null ) {
            $args['post_title'] = sanitize_text_field( $request->get_param( 'title' ) );
        }
        if ( $request->get_param( 'description' ) !== null ) {
            $args['post_content'] = sanitize_textarea_field( $request->get_param( 'description' ) );
        }
        wp_update_post( $args );

        if ( $request->get_param( 'nodes' ) !== null ) {
            update_post_meta( $post->ID, '_dd_story_nodes', wp_json_encode( $request->get_param( 'nodes' ), JSON_UNESCAPED_UNICODE ) );
        }

        return rest_ensure_response( array( 'id' => $post->ID, 'updated' => true ) );
    }

    public static function delete_story( $request ) {
        $post = get_post( $request['id'] );
        if ( ! $post || $post->post_type !== 'dd_story' ) {
            return new WP_Error( 'not_found', '스토리를 찾을 수 없습니다.', array( 'status' => 404 ) );
        }
        wp_delete_post( $post->ID, true );
        return rest_ensure_response( array( 'deleted' => true ) );
    }

    public static function generate_story( $request ) {
        @set_time_limit( 600 );
        try {
            $topic     = sanitize_text_field( $request->get_param( 'topic' ) );
            $level     = sanitize_text_field( $request->get_param( 'level' ) ?: 'beginner' );
            $course_id = (int) $request->get_param( 'course_id' );

            // 이미지 옵션 — 미지정 시 기존 기본값을 쓴다 (구버전 클라이언트 호환).
            $scene_images = $request->get_param( 'scene_images' );
            $scene_images = ( $scene_images === null )
                ? DD_Story_Generator::DEFAULT_SCENE_IMAGES
                : (int) $scene_images;
            $cover_image = $request->get_param( 'cover_image' );
            $cover_image = ( $cover_image === null ) ? true : (bool) $cover_image;

            if ( empty( $topic ) ) {
                return new WP_Error( 'missing_topic', '주제를 입력해 주세요.', array( 'status' => 400 ) );
            }

            $result = DD_Story_Generator::generate( $topic, $level, $course_id, $scene_images, $cover_image );
            if ( is_wp_error( $result ) ) {
                return $result;
            }

            return rest_ensure_response( $result );
        } catch ( \Exception $e ) {
            return new WP_Error( 'story_error', '스토리 생성 오류: ' . $e->getMessage(), array( 'status' => 500 ) );
        } catch ( \Error $e ) {
            return new WP_Error( 'story_fatal', '스토리 생성 치명적 오류: ' . $e->getMessage(), array( 'status' => 500 ) );
        }
    }

    public static function toggle_story_public( $request ) {
        $post = get_post( $request['id'] );
        if ( ! $post || $post->post_type !== 'dd_story' ) {
            return new WP_Error( 'not_found', '스토리를 찾을 수 없습니다.', array( 'status' => 404 ) );
        }

        $current = get_post_meta( $post->ID, '_dd_story_public_active', true );
        $new_val = $current === '1' ? '0' : '1';
        update_post_meta( $post->ID, '_dd_story_public_active', $new_val );

        $url = '';
        if ( $new_val === '1' ) {
            $token = get_post_meta( $post->ID, '_dd_story_public_token', true );
            $url   = home_url( '/story/' . $token . '/' );
        }

        return rest_ensure_response( array(
            'id'     => $post->ID,
            'active' => $new_val === '1',
            'url'    => $url,
        ) );
    }

    public static function public_story( $request ) {
        $token = sanitize_text_field( $request['token'] );
        $posts = get_posts( array(
            'post_type'   => 'dd_story',
            'meta_key'    => '_dd_story_public_token',
            'meta_value'  => $token,
            'numberposts' => 1,
            'post_status' => 'publish',
        ) );

        if ( empty( $posts ) ) {
            return new WP_Error( 'not_found', '스토리를 찾을 수 없습니다.', array( 'status' => 404 ) );
        }

        $story  = $posts[0];
        $active = get_post_meta( $story->ID, '_dd_story_public_active', true );
        if ( $active !== '1' ) {
            return new WP_Error( 'inactive', '이 링크는 비활성화되었습니다.', array( 'status' => 403 ) );
        }

        return rest_ensure_response( self::format_story( $story ) );
    }

    private static function format_story( $post ) {
        $token = get_post_meta( $post->ID, '_dd_story_public_token', true );
        return array(
            'id'            => $post->ID,
            'title'         => $post->post_title,
            'description'   => $post->post_content,
            'nodes'         => json_decode( get_post_meta( $post->ID, '_dd_story_nodes', true ), true ) ?: array(),
            'course_id'     => (int) get_post_meta( $post->ID, '_dd_story_course_id', true ),
            'level'         => get_post_meta( $post->ID, '_dd_story_level', true ) ?: 'beginner',
            'cover_image'   => get_post_meta( $post->ID, '_dd_story_cover_image', true ),
            'token'         => $token,
            'public_active' => get_post_meta( $post->ID, '_dd_story_public_active', true ) === '1',
            'public_url'    => ! empty( $token ) ? home_url( '/story/' . $token . '/' ) : '',
            'created'       => $post->post_date,
        );
    }

    // --- 중국어 노래 학습 ---

    public static function song_fetch_info( $request ) {
        $url = sanitize_text_field( $request->get_param( 'url' ) );
        if ( empty( $url ) ) {
            return new WP_Error( 'missing_url', 'YouTube URL을 입력해 주세요.', array( 'status' => 400 ) );
        }
        $result = DD_Song_Course_Generator::fetch_info( $url );
        if ( is_wp_error( $result ) ) return $result;
        return rest_ensure_response( $result );
    }

    public static function song_fetch_subtitles( $request ) {
        $video_id = sanitize_text_field( $request->get_param( 'video_id' ) );
        DD_Youtube_Subtitles::log_public( "REST 진입: song/fetch-subtitles (video_id='{$video_id}')" );
        if ( empty( $video_id ) ) {
            return new WP_Error( 'missing_id', '영상 ID가 필요합니다.', array( 'status' => 400 ) );
        }
        $result = DD_Song_Course_Generator::fetch_subtitles( $video_id );
        return rest_ensure_response( $result );
    }

    public static function song_generate( $request ) {
        @set_time_limit( 600 );
        try {
            $params = array(
                'title'              => sanitize_text_field( $request->get_param( 'title' ) ?? '' ),
                'level'              => sanitize_text_field( $request->get_param( 'level' ) ?? 'beginner' ),
                'genre'              => sanitize_text_field( $request->get_param( 'genre' ) ?? '' ),
                'existing_course_id' => (int) ( $request->get_param( 'existing_course_id' ) ?? 0 ),
                'tracks'             => $request->get_param( 'tracks' ) ?? array(),
            );
            $result = DD_Song_Course_Generator::generate( $params );
            if ( is_wp_error( $result ) ) return $result;
            return rest_ensure_response( $result );
        } catch ( \Exception $e ) {
            return new WP_Error( 'song_error', '중국어 노래 학습 생성 오류: ' . $e->getMessage(), array( 'status' => 500 ) );
        } catch ( \Error $e ) {
            return new WP_Error( 'song_fatal', '중국어 노래 학습 생성 치명적 오류: ' . $e->getMessage(), array( 'status' => 500 ) );
        }
    }

    public static function song_generate_track( $request ) {
        @set_time_limit( 600 );
        try {
            $course_id = (int) $request->get_param( 'course_id' );
            $order     = (int) $request->get_param( 'order' );
            $level     = sanitize_text_field( $request->get_param( 'level' ) ?? 'beginner' );
            $track     = $request->get_param( 'track' );

            if ( ! $course_id || empty( $track ) ) {
                return new WP_Error( 'missing_params', '필수 파라미터가 없습니다.', array( 'status' => 400 ) );
            }

            // SRT/SMI 가사 파일 자동 인식
            if ( ! empty( $track['subtitle_text'] ) ) {
                $raw    = $track['subtitle_text'];
                $source = $track['subtitle_source'] ?? 'manual';
                if ( $source === 'srt' || preg_match( '/^\d+\r?\n\d{2}:\d{2}:\d{2}/', $raw ) ) {
                    $track['subtitle_text'] = DD_Song_Course_Generator::parse_srt( $raw );
                } elseif ( $source === 'smi' || stripos( $raw, '<SAMI>' ) !== false ) {
                    $track['subtitle_text'] = DD_Song_Course_Generator::parse_smi( $raw );
                }
            }

            $result = DD_Song_Course_Generator::generate_song_lesson( $course_id, $track, $order, $level );
            if ( is_wp_error( $result ) ) return $result;
            return rest_ensure_response( $result );
        } catch ( \Exception $e ) {
            return new WP_Error( 'track_error', '곡 생성 오류: ' . $e->getMessage(), array( 'status' => 500 ) );
        } catch ( \Error $e ) {
            return new WP_Error( 'track_fatal', '곡 생성 치명적 오류: ' . $e->getMessage(), array( 'status' => 500 ) );
        }
    }

    // --- 학습 자료 ---

    public static function get_materials( $request ) {
        $post = get_post( $request['id'] );
        if ( ! $post || $post->post_type !== 'dd_lesson' ) {
            return new WP_Error( 'not_found', '강의를 찾을 수 없습니다.', array( 'status' => 404 ) );
        }
        $materials = json_decode( get_post_meta( $post->ID, '_dd_lesson_materials', true ), true ) ?: array();
        return rest_ensure_response( $materials );
    }

    public static function add_material( $request ) {
        $post = get_post( $request['id'] );
        if ( ! $post || $post->post_type !== 'dd_lesson' ) {
            return new WP_Error( 'not_found', '강의를 찾을 수 없습니다.', array( 'status' => 404 ) );
        }

        $url      = esc_url_raw( $request->get_param( 'url' ) ?? '' );
        $filename = sanitize_text_field( $request->get_param( 'filename' ) ?? '' );
        $filetype = sanitize_text_field( $request->get_param( 'filetype' ) ?? '' );

        if ( empty( $url ) || empty( $filename ) ) {
            return new WP_Error( 'missing_data', 'URL과 파일명이 필요합니다.', array( 'status' => 400 ) );
        }

        $allowed = array( 'pdf', 'docx', 'pptx', 'doc', 'ppt', 'xlsx', 'xls' );
        if ( ! empty( $filetype ) && ! in_array( strtolower( $filetype ), $allowed, true ) ) {
            return new WP_Error( 'invalid_type', '허용되지 않는 파일 형식입니다.', array( 'status' => 400 ) );
        }

        $materials = json_decode( get_post_meta( $post->ID, '_dd_lesson_materials', true ), true ) ?: array();
        $materials[] = array(
            'url'      => $url,
            'filename' => $filename,
            'filetype' => $filetype,
            'added'    => current_time( 'Y-m-d H:i:s' ),
        );
        update_post_meta( $post->ID, '_dd_lesson_materials', wp_json_encode( $materials, JSON_UNESCAPED_UNICODE ) );

        return rest_ensure_response( $materials );
    }

    public static function delete_material( $request ) {
        $post = get_post( $request['id'] );
        if ( ! $post || $post->post_type !== 'dd_lesson' ) {
            return new WP_Error( 'not_found', '강의를 찾을 수 없습니다.', array( 'status' => 404 ) );
        }

        $index     = (int) $request['index'];
        $materials = json_decode( get_post_meta( $post->ID, '_dd_lesson_materials', true ), true ) ?: array();
        if ( ! isset( $materials[ $index ] ) ) {
            return new WP_Error( 'not_found', '자료를 찾을 수 없습니다.', array( 'status' => 404 ) );
        }

        array_splice( $materials, $index, 1 );
        update_post_meta( $post->ID, '_dd_lesson_materials', wp_json_encode( $materials, JSON_UNESCAPED_UNICODE ) );

        return rest_ensure_response( $materials );
    }

    // --- 뉴스레터 ---

    public static function get_newsletters() {
        $posts = get_posts( array(
            'post_type'   => 'dd_newsletter',
            'numberposts' => 100,
            'post_status' => 'publish',
            'orderby'     => 'date',
            'order'       => 'DESC',
        ) );

        $items = array();
        foreach ( $posts as $p ) {
            $items[] = self::format_newsletter( $p );
        }
        return rest_ensure_response( $items );
    }

    public static function generate_newsletter( $request ) {
        $topic = sanitize_text_field( $request->get_param( 'topic' ) ?? '' );

        // 이미지 옵션 — 미지정 시 기존 동작(전부 생성)을 유지한다.
        $cover    = $request->get_param( 'cover_image' );
        $sections = $request->get_param( 'section_images' );

        $result = DD_Newsletter_Generator::generate(
            $topic,
            ( $cover === null ) ? true : (bool) $cover,
            ( $sections === null ) ? true : (bool) $sections
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( $result );
    }

    public static function delete_newsletter( $request ) {
        $post = get_post( $request['id'] );
        if ( ! $post || $post->post_type !== 'dd_newsletter' ) {
            return new WP_Error( 'not_found', '뉴스레터를 찾을 수 없습니다.', array( 'status' => 404 ) );
        }
        wp_delete_post( $post->ID, true );
        return rest_ensure_response( array( 'deleted' => true ) );
    }

    public static function toggle_newsletter_public( $request ) {
        $post = get_post( $request['id'] );
        if ( ! $post || $post->post_type !== 'dd_newsletter' ) {
            return new WP_Error( 'not_found', '뉴스레터를 찾을 수 없습니다.', array( 'status' => 404 ) );
        }

        $current = get_post_meta( $post->ID, '_dd_newsletter_public_active', true );
        $new_val = $current === '1' ? '0' : '1';
        update_post_meta( $post->ID, '_dd_newsletter_public_active', $new_val );

        $token = get_post_meta( $post->ID, '_dd_newsletter_public_token', true );
        if ( empty( $token ) ) {
            $token = wp_generate_uuid4();
            update_post_meta( $post->ID, '_dd_newsletter_public_token', $token );
        }

        return rest_ensure_response( array(
            'id'     => $post->ID,
            'active' => $new_val === '1',
            'url'    => home_url( '/newsletter/' . $token . '/' ),
        ) );
    }

    public static function public_newsletters() {
        $posts = get_posts( array(
            'post_type'   => 'dd_newsletter',
            'numberposts' => 50,
            'post_status' => 'publish',
            'orderby'     => 'date',
            'order'       => 'DESC',
            'meta_query'  => array(
                array( 'key' => '_dd_newsletter_public_active', 'value' => '1' ),
            ),
        ) );

        $items = array();
        foreach ( $posts as $p ) {
            $items[] = array(
                'id'          => $p->ID,
                'title'       => $p->post_title,
                'title_zh'    => get_post_meta( $p->ID, '_dd_newsletter_title_zh', true ),
                'summary'     => get_post_meta( $p->ID, '_dd_newsletter_summary', true ),
                'category'    => get_post_meta( $p->ID, '_dd_newsletter_category', true ),
                'emoji'       => get_post_meta( $p->ID, '_dd_newsletter_cover_emoji', true ),
                'cover_image' => get_post_meta( $p->ID, '_dd_newsletter_cover_image', true ),
                'token'       => get_post_meta( $p->ID, '_dd_newsletter_public_token', true ),
                'date'        => get_the_date( 'Y-m-d', $p ),
            );
        }
        return rest_ensure_response( $items );
    }

    public static function public_newsletter( $request ) {
        $token = sanitize_text_field( $request['token'] );
        $posts = get_posts( array(
            'post_type'   => 'dd_newsletter',
            'meta_key'    => '_dd_newsletter_public_token',
            'meta_value'  => $token,
            'numberposts' => 1,
            'post_status' => 'publish',
        ) );

        if ( empty( $posts ) ) {
            return new WP_Error( 'not_found', '뉴스레터를 찾을 수 없습니다.', array( 'status' => 404 ) );
        }

        $p      = $posts[0];
        $active = get_post_meta( $p->ID, '_dd_newsletter_public_active', true );
        if ( $active !== '1' ) {
            return new WP_Error( 'inactive', '이 뉴스레터는 비공개 상태입니다.', array( 'status' => 403 ) );
        }

        return rest_ensure_response( array(
            'id'          => $p->ID,
            'title'       => $p->post_title,
            'title_zh'    => get_post_meta( $p->ID, '_dd_newsletter_title_zh', true ),
            'summary'     => get_post_meta( $p->ID, '_dd_newsletter_summary', true ),
            'category'    => get_post_meta( $p->ID, '_dd_newsletter_category', true ),
            'emoji'       => get_post_meta( $p->ID, '_dd_newsletter_cover_emoji', true ),
            'cover_image' => get_post_meta( $p->ID, '_dd_newsletter_cover_image', true ),
            'sections'    => json_decode( get_post_meta( $p->ID, '_dd_newsletter_sections', true ), true ) ?: array(),
            'vocab'       => json_decode( get_post_meta( $p->ID, '_dd_newsletter_vocab', true ), true ) ?: array(),
            'date'        => get_the_date( 'Y-m-d', $p ),
        ) );
    }

    private static function format_newsletter( $post ) {
        $token = get_post_meta( $post->ID, '_dd_newsletter_public_token', true );
        return array(
            'id'            => $post->ID,
            'title'         => $post->post_title,
            'title_zh'      => get_post_meta( $post->ID, '_dd_newsletter_title_zh', true ),
            'summary'       => get_post_meta( $post->ID, '_dd_newsletter_summary', true ),
            'category'      => get_post_meta( $post->ID, '_dd_newsletter_category', true ),
            'emoji'         => get_post_meta( $post->ID, '_dd_newsletter_cover_emoji', true ),
            'cover_image'   => get_post_meta( $post->ID, '_dd_newsletter_cover_image', true ),
            'sections'      => json_decode( get_post_meta( $post->ID, '_dd_newsletter_sections', true ), true ) ?: array(),
            'vocab'         => json_decode( get_post_meta( $post->ID, '_dd_newsletter_vocab', true ), true ) ?: array(),
            'token'         => $token,
            'public_active' => get_post_meta( $post->ID, '_dd_newsletter_public_active', true ) === '1',
            'public_url'    => ! empty( $token ) ? home_url( '/newsletter/' . $token . '/' ) : '',
            'created'       => $post->post_date,
        );
    }

    // --- 데이터 백업 / 복원 ---

    /**
     * 설정 화면에서 "무엇이 백업되는지" 미리 보여 주기 위한 요약.
     */
    public static function backup_info() {
        $counts = DD_Backup::counts();

        $media = DD_Backup::media_summary();

        return rest_ensure_response( array(
            'counts'         => $counts,
            'total'          => array_sum( $counts ),
            'plugin_version' => DD_LMS_VERSION,
            'format_version' => DD_Backup::FORMAT_VERSION,
            'filename'       => DD_Backup::filename( current_time( 'timestamp' ) ),
            'media'          => array(
                'count'      => $media['count'],
                'bytes'      => $media['bytes'],
                'human'      => size_format( $media['bytes'] ),
                'zip_ready'  => class_exists( 'ZipArchive' ),
            ),
            'upload_limit'   => array(
                'bytes' => wp_max_upload_size(),
                'human' => size_format( wp_max_upload_size() ),
            ),
            // 휴지통 콘텐츠는 백업에 담기지 않는다 — 조용히 빠지지 않도록 알린다.
            'trashed'        => DD_Backup::trashed_count(),
            // 자동 안전 백업 폴더가 웹에서 열리는 서버인지 (nginx·IIS 는 .htaccess 무시)
            'backup_dir'     => DD_Backup::backup_dir_protection(),
        ) );
    }

    /**
     * 백업 데이터를 응답 본문으로 내려보낸다.
     *
     * ⚠️ 서버에 파일을 만들지 않는다. 브라우저가 받은 JSON 을 Blob 으로 저장하므로
     *    uploads 폴더에 백업 파일이 남아 유출될 여지가 없다.
     */
    public static function backup_export() {
        @set_time_limit( 300 );

        return rest_ensure_response( array(
            'filename' => DD_Backup::filename( current_time( 'timestamp' ) ),
            'backup'   => DD_Backup::export(),
        ) );
    }

    /**
     * 업로드한 백업 파일로 데이터를 복원한다.
     *
     * 검증 순서: nonce → 업로드 오류/확장자/크기 → JSON 유효성 → 백업 포맷 →
     * (선택) 현재 데이터 자동 백업 → 복원.
     */
    public static function backup_import( $request ) {
        @set_time_limit( 300 );

        // 실행 시간 초과·메모리 부족은 catch 로 잡을 수 없다. 죽더라도 이유가
        // debug.log 에 남도록 shutdown 감시를 먼저 걸어 둔다.
        DD_Backup::watch_for_fatal( '복원(backup_import)' );
        DD_Backup::log( '복원 시작 — PHP 제한: 실행 ' . ini_get( 'max_execution_time' ) . '초 / 메모리 ' . ini_get( 'memory_limit' ) );

        try {
            return self::run_backup_import( $request );
        } catch ( \Exception $e ) {
            DD_Backup::log( '복원 예외: ' . $e->getMessage() );
            return new WP_Error( 'dd_backup_failed', '복원 중 오류가 발생했습니다: ' . $e->getMessage(), array( 'status' => 500 ) );
        } catch ( \Error $e ) {
            DD_Backup::log( '복원 오류: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
            return new WP_Error(
                'dd_backup_failed',
                '복원 중 서버 오류가 발생했습니다. wp-content/uploads/dingdong-lms/debug.log 를 확인하세요. ('
                    . $e->getMessage() . ')',
                array( 'status' => 500 )
            );
        }
    }

    /** 실제 복원 절차. 오류 포착은 backup_import() 가 감싼다. */
    private static function run_backup_import( $request ) {

        // 권한은 permission_callback 이 이미 확인했다.
        // 되돌리기 어려운 작업이므로 전용 nonce 로 CSRF 를 한 번 더 막는다.
        $nonce = $request->get_param( '_dd_nonce' );
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'dd_backup' ) ) {
            return new WP_Error(
                'dd_backup_bad_nonce',
                '보안 검증에 실패했습니다. 페이지를 새로고침한 뒤 다시 시도하세요.',
                array( 'status' => 403 )
            );
        }

        $restore_media = $request->get_param( 'restore_media' );
        $restore_media = ( $restore_media === null ) ? true : rest_sanitize_boolean( $restore_media );

        // 서버 실행 시간 제한 안에서 끊어서 처리한다. 못 끝내면 done=false 로
        // 돌려주고, 클라이언트가 같은 파일로 다시 호출해 이어받는다.
        $budget = DD_Backup::time_budget( ini_get( 'max_execution_time' ) );

        $data        = null;
        $media_stats = null;
        $files       = $request->get_file_params();

        if ( ! empty( $files['file'] ) && is_array( $files['file'] ) ) {
            $file = $files['file'];

            // 업로드 필드 검증은 DD_Backup 이 담당한다 (배열 주입·확장자·크기).
            $checked = DD_Backup::inspect_upload( $file );
            if ( is_wp_error( $checked ) ) {
                return $checked;
            }
            $ext = $checked['ext'];

            if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
                return new WP_Error(
                    'dd_backup_upload_error',
                    '업로드된 파일을 읽을 수 없습니다.',
                    array( 'status' => 400 )
                );
            }

            DD_Backup::log( '업로드 접수: ' . $ext . ' / ' . size_format( (int) $checked['size'] ) );

            if ( $ext === 'zip' ) {
                $archive = DD_Backup::read_archive( $file['tmp_name'], $restore_media, $budget );
                @unlink( $file['tmp_name'] );

                if ( is_wp_error( $archive ) ) {
                    return $archive;
                }

                $data        = $archive['data'];
                $media_stats = $archive['media'];
            } else {
                $raw = file_get_contents( $file['tmp_name'] );

                // 업로드 임시 파일을 서버에 남기지 않는다.
                @unlink( $file['tmp_name'] );

                $data = DD_Backup::decode( $raw );
            }
        } else {
            // FormData 업로드가 막히는 환경(Playground 등)을 위한 대체 경로. JSON 전용이다.
            $data = DD_Backup::decode( (string) $request->get_param( 'payload' ) );
        }

        if ( is_wp_error( $data ) ) {
            return $data;
        }

        $valid = DD_Backup::validate( $data );
        if ( is_wp_error( $valid ) ) {
            return $valid;
        }

        $mode = sanitize_key( (string) $request->get_param( 'mode' ) );
        if ( ! in_array( $mode, array( 'skip', 'replace', 'duplicate' ), true ) ) {
            $mode = 'skip';
        }

        $restore_options = $request->get_param( 'restore_options' );
        $restore_options = ( $restore_options === null ) ? true : rest_sanitize_boolean( $restore_options );

        $want_safety = $request->get_param( 'safety_backup' );
        $want_safety = ( $want_safety === null ) ? true : rest_sanitize_boolean( $want_safety );

        $safety_note = '';
        if ( $want_safety ) {
            $safety = DD_Backup::write_safety_backup();
            if ( is_wp_error( $safety ) ) {
                // 안전장치 실패가 복원 자체를 막지는 않는다 — 대신 분명히 알린다.
                $safety_note = '⚠️ 복원 전 자동 백업에 실패했습니다: ' . $safety->get_error_message();
            } else {
                $safety_note = '복원 전 현재 데이터를 자동 백업했습니다: ' . $safety['file'];
            }
        }

        DD_Backup::log( '복원 대상 ' . count( $data['posts'] ) . '건 / 모드 ' . $mode . ' — 시작' );
        $started = microtime( true );

        $report = DD_Backup::import( $data, array(
            'mode'            => $mode,
            'restore_options' => $restore_options,
            'time_budget'     => $budget,
            // 이어서 복원할 위치. 클라이언트가 직전 응답의 next_offset 을 돌려준다.
            'offset'          => absint( $request->get_param( 'offset' ) ),
        ) );

        if ( is_wp_error( $report ) ) {
            DD_Backup::log( '복원 실패: ' . $report->get_error_message() );
            return $report;
        }

        DD_Backup::log( sprintf(
            '복원 완료 — 생성 %d / 덮어씀 %d / 이어받음 %d / 건너뜀 %d / 실패 %d (%.1f초)',
            $report['created'], $report['updated'], $report['resumed'],
            $report['skipped'], $report['failed'], microtime( true ) - $started
        ) );

        // 이미지 해제가 남았어도 아직 끝난 게 아니다.
        if ( is_array( $media_stats ) && isset( $media_stats['done'] ) && ! $media_stats['done'] ) {
            $report['done'] = false;
        }

        $report['safety_backup'] = $safety_note;
        $report['media']         = $media_stats;
        $report['source']        = array(
            'generated_at'   => isset( $data['generated_at'] ) ? sanitize_text_field( (string) $data['generated_at'] ) : '',
            'plugin_version' => isset( $data['plugin_version'] ) ? sanitize_text_field( (string) $data['plugin_version'] ) : '',
            'home_url'       => isset( $data['site']['home_url'] ) ? esc_url_raw( (string) $data['site']['home_url'] ) : '',
        );

        return rest_ensure_response( $report );
    }

    // --- 헬퍼 ---

    private static function format_lesson( $post ) {
        return array(
            'id'             => $post->ID,
            'title'          => $post->post_title,
            'content'        => $post->post_content,
            'order'          => (int) get_post_meta( $post->ID, '_dd_lesson_order', true ),
            'year'           => get_post_meta( $post->ID, '_dd_lesson_year', true ) ?: '',
            'artist'         => get_post_meta( $post->ID, '_dd_lesson_artist', true ) ?: '',
            'course_id'      => (int) get_post_meta( $post->ID, '_dd_course_id', true ),
            'slides'         => json_decode( get_post_meta( $post->ID, '_dd_slides_data', true ), true ) ?: array(),
            'quiz'           => json_decode( get_post_meta( $post->ID, '_dd_quiz_data', true ), true ) ?: array(),
            'cultural_note'   => get_post_meta( $post->ID, '_dd_cultural_note', true ),
            'key_expressions' => json_decode( get_post_meta( $post->ID, '_dd_key_expressions', true ), true ) ?: array(),
            'key_expr_image'  => get_post_meta( $post->ID, '_dd_key_expr_image', true ) ?: '',
            'dialogue_image'  => get_post_meta( $post->ID, '_dd_dialogue_image', true ),
            'comic_panels'    => json_decode( get_post_meta( $post->ID, '_dd_comic_data', true ), true ) ?: array(),
            'comic_images'    => json_decode( get_post_meta( $post->ID, '_dd_comic_images', true ), true ) ?: array(),
            'comic_strip_image' => get_post_meta( $post->ID, '_dd_comic_strip_image', true ) ?: '',
            'video_keywords'  => json_decode( get_post_meta( $post->ID, '_dd_video_keywords', true ), true ) ?: array(),
            'video_embeds'    => json_decode( get_post_meta( $post->ID, '_dd_video_embeds', true ), true ) ?: array(),
            'videos'         => json_decode( get_post_meta( $post->ID, '_dd_video_urls', true ), true ) ?: array(),
            'storybook'      => json_decode( get_post_meta( $post->ID, '_dd_storybook_data', true ), true ) ?: array(),
            'token'          => get_post_meta( $post->ID, '_dd_public_token', true ),
            'public_active'  => get_post_meta( $post->ID, '_dd_public_active', true ) === '1',
            'public_url'     => DD_Public_Access::get_public_url( $post->ID ),
        );
    }

    private static function count_course_lessons( $course_id ) {
        $q = new WP_Query( array(
            'post_type'      => 'dd_lesson',
            'meta_key'       => '_dd_course_id',
            'meta_value'     => $course_id,
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ) );
        return $q->found_posts;
    }
}
