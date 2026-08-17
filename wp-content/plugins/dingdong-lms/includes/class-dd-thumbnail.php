<?php
/**
 * Pixabay API를 사용한 강좌 썸네일 자동 가져오기
 * 고화질 + 주제 연관도 높은 이미지 검색
 */
class DD_Thumbnail {

    private static $api_url = 'https://pixabay.com/api/';

    /**
     * Pixabay API 키 저장
     */
    public static function save_key( $key ) {
        update_option( 'dd_lms_pixabay_key', sanitize_text_field( $key ) );
    }

    /**
     * Pixabay API 키 조회
     *
     * 해석 순서: DD_PIXABAY_API_KEY 상수/환경변수(개발용 오버라이드) → wp_options.
     */
    public static function get_key() {
        $override = DD_Env::get( 'DD_PIXABAY_API_KEY' );
        if ( $override !== '' ) {
            return $override;
        }
        return get_option( 'dd_lms_pixabay_key', '' );
    }

    /**
     * Pixabay API 키 삭제
     */
    public static function delete_key() {
        delete_option( 'dd_lms_pixabay_key' );
    }

    /**
     * 키 존재 여부
     */
    public static function has_key() {
        return ! empty( self::get_key() );
    }

    /**
     * Pixabay에서 이미지 검색
     *
     * @param string $query      검색어
     * @param int    $per_page   결과 수
     * @return array|WP_Error    이미지 배열 또는 에러
     */
    public static function search( $query, $per_page = 5 ) {
        $api_key = self::get_key();
        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_pixabay_key', 'Pixabay API 키가 설정되지 않았습니다.' );
        }

        // per_page 최솟값 3 (Pixabay 제한)
        $per_page = max( 3, (int) $per_page );

        $url = add_query_arg( array(
            'key'         => $api_key,
            'q'           => $query,  // add_query_arg가 자동 인코딩
            'image_type'  => 'photo',
            'orientation' => 'horizontal',
            'per_page'    => $per_page,
            'safesearch'  => 'true',
            'order'       => 'popular',
            'min_width'   => 800,
            'min_height'  => 400,
        ), self::$api_url );

        $response = wp_remote_get( $url, array( 'timeout' => 15 ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'pixabay_http', 'Pixabay 요청 실패: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $raw  = wp_remote_retrieve_body( $response );
        $data = json_decode( $raw, true );

        if ( $code === 401 ) {
            return new WP_Error( 'pixabay_invalid_key', 'Pixabay API 키가 유효하지 않습니다.' );
        }

        if ( $code !== 200 ) {
            return new WP_Error( 'pixabay_error', 'Pixabay API 오류 (HTTP ' . $code . ')' );
        }

        if ( empty( $data['hits'] ) ) {
            return array();
        }

        $results = array();
        foreach ( $data['hits'] as $hit ) {
            $results[] = array(
                'id'        => $hit['id'],
                'url_small' => $hit['webformatURL'],          // 640px
                'url_large' => $hit['largeImageURL'],         // 1280px
                'url_full'  => $hit['fullHDURL'] ?? $hit['largeImageURL'], // 1920px
                'tags'      => $hit['tags'],
                'user'      => $hit['user'],
                'width'     => $hit['imageWidth'],
                'height'    => $hit['imageHeight'],
            );
        }

        return $results;
    }

    /**
     * 강좌 생성 시 최적 썸네일 자동 가져오기
     * 여러 검색어로 시도하여 가장 좋은 결과 선택
     *
     * @param int    $course_id   강좌 ID
     * @param string $topic       강좌 주제 (한국어)
     * @param string $topic_en    영어 검색어 (선택)
     * @return string|WP_Error    저장된 이미지 URL 또는 에러
     */
    public static function auto_fetch( $course_id, $topic, $topic_en = '' ) {
        if ( ! self::has_key() ) {
            return new WP_Error( 'no_key', 'Pixabay 키 없음' );
        }

        // 검색 전략: 영어 키워드 → 주제 직접 → 일반 중국어/교육 키워드
        $search_queries = array();

        if ( ! empty( $topic_en ) ) {
            $search_queries[] = $topic_en;
        }

        // 주제에서 핵심 키워드 추출 (중국어/중국문화 관련)
        $search_queries[] = $topic . ' China culture';
        $search_queries[] = 'Chinese ' . $topic;
        $search_queries[] = 'China education culture';

        $best_image = null;

        foreach ( $search_queries as $q ) {
            $results = self::search( $q, 3 );

            if ( is_wp_error( $results ) ) {
                return $results; // API 키 오류 등은 바로 반환
            }

            if ( ! empty( $results ) ) {
                $best_image = $results[0]; // 관련도 + 인기순 첫 번째
                break;
            }
        }

        if ( empty( $best_image ) ) {
            return new WP_Error( 'no_results', '적합한 썸네일을 찾지 못했습니다.' );
        }

        // 고화질 이미지 다운로드 후 WordPress uploads에 저장
        $image_url = $best_image['url_large']; // 1280px
        $saved     = self::download_and_save( $image_url, $course_id );

        if ( is_wp_error( $saved ) ) {
            // 다운로드 실패 시 외부 URL 직접 사용
            update_post_meta( $course_id, '_dd_course_thumbnail', $image_url );
            return $image_url;
        }

        update_post_meta( $course_id, '_dd_course_thumbnail', $saved );
        return $saved;
    }

    /**
     * 이미지를 다운로드하여 로컬에 저장
     */
    private static function download_and_save( $url, $course_id ) {
        $response = wp_remote_get( $url, array( 'timeout' => 30 ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) {
            return new WP_Error( 'empty_body', '이미지 다운로드 실패' );
        }

        $upload_dir = wp_upload_dir();
        $dd_dir     = $upload_dir['basedir'] . '/dingdong-lms/thumbnails';

        if ( ! file_exists( $dd_dir ) ) {
            wp_mkdir_p( $dd_dir );
        }

        $content_type = wp_remote_retrieve_header( $response, 'content-type' );
        $ext          = ( strpos( $content_type, 'jpeg' ) !== false || strpos( $content_type, 'jpg' ) !== false ) ? '.jpg' : '.png';
        $filename     = 'course-' . $course_id . '-' . time() . $ext;
        $filepath     = $dd_dir . '/' . $filename;

        $written = file_put_contents( $filepath, $body );
        if ( $written === false ) {
            return new WP_Error( 'write_failed', '파일 저장 실패' );
        }

        return $upload_dir['baseurl'] . '/dingdong-lms/thumbnails/' . $filename;
    }
}
