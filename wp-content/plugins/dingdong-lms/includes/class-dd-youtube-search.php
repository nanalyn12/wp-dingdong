<?php
/**
 * YouTube Data API v3를 사용한 영상 검색 및 자동 임베드
 * 별도의 YouTube API 키를 사용
 */
class DD_YouTube_Search {

    private static $search_url = 'https://www.googleapis.com/youtube/v3/search';
    private static $videos_url = 'https://www.googleapis.com/youtube/v3/videos';

    /**
     * YouTube API 키 저장
     */
    public static function save_key( $key ) {
        update_option( 'dd_lms_youtube_key', sanitize_text_field( $key ) );
    }

    /**
     * YouTube API 키 조회
     *
     * 해석 순서: DD_YOUTUBE_API_KEY 상수/환경변수(개발용 오버라이드) → wp_options.
     */
    public static function get_key() {
        $override = DD_Env::get( 'DD_YOUTUBE_API_KEY' );
        if ( $override !== '' ) {
            return $override;
        }
        return get_option( 'dd_lms_youtube_key', '' );
    }

    /**
     * YouTube API 키 삭제
     */
    public static function delete_key() {
        delete_option( 'dd_lms_youtube_key' );
    }

    /**
     * 키 존재 여부
     */
    public static function has_key() {
        return ! empty( self::get_key() );
    }

    /**
     * YouTube에서 영상 검색
     * shorts 제외 (videoDuration=medium → 4-20분)
     */
    public static function search( $query, $max_results = 2 ) {
        $api_key = self::get_key();
        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_api_key', 'YouTube API 키가 설정되지 않았습니다.' );
        }

        $three_months_ago = gmdate( 'Y-m-d\TH:i:s\Z', strtotime( '-3 months' ) );

        $url = add_query_arg( array(
            'part'             => 'snippet',
            'q'                => $query,
            'type'             => 'video',
            'videoEmbeddable'  => 'true',
            'videoDuration'    => 'medium',
            'relevanceLanguage' => 'ko',
            'maxResults'       => $max_results,
            'order'            => 'relevance',
            'publishedAfter'   => $three_months_ago,
            'key'              => $api_key,
        ), self::$search_url );

        $response = wp_remote_get( $url, array( 'timeout' => 15 ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'yt_http_error', 'YouTube API 요청 실패: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $raw  = wp_remote_retrieve_body( $response );
        $data = json_decode( $raw, true );

        if ( $code === 403 ) {
            $error_reason = $data['error']['errors'][0]['reason'] ?? '';
            if ( $error_reason === 'accessNotConfigured' || strpos( $raw, 'YouTube Data API' ) !== false ) {
                return new WP_Error(
                    'yt_not_enabled',
                    'YouTube Data API v3가 활성화되지 않았습니다. Google Cloud Console에서 API를 활성화해 주세요: https://console.cloud.google.com/apis/library/youtube.googleapis.com'
                );
            }
            $msg = $data['error']['message'] ?? 'API 접근 거부';
            return new WP_Error( 'yt_forbidden', $msg );
        }

        if ( $code !== 200 ) {
            $msg = $data['error']['message'] ?? 'HTTP ' . $code;
            return new WP_Error( 'yt_api_error', 'YouTube API 오류: ' . $msg );
        }

        if ( empty( $data['items'] ) ) {
            return array();
        }

        $results = array();
        foreach ( $data['items'] as $item ) {
            if ( empty( $item['id']['videoId'] ) ) {
                continue;
            }
            $video_id = $item['id']['videoId'];
            $results[] = array(
                'video_id'  => $video_id,
                'title'     => $item['snippet']['title'] ?? '',
                'channel'   => $item['snippet']['channelTitle'] ?? '',
                'thumbnail' => $item['snippet']['thumbnails']['medium']['url'] ?? '',
                'embed_url' => 'https://www.youtube.com/embed/' . $video_id,
            );
        }

        return $results;
    }

    /**
     * 여러 키워드로 검색하여 가장 관련도 높은 영상 찾기
     * shorts 제외, 중복 제거
     */
    public static function search_best( $keywords, $max_total = 2 ) {
        if ( empty( $keywords ) || ! is_array( $keywords ) ) {
            return array();
        }

        $all_results = array();
        $seen_ids    = array();

        foreach ( $keywords as $kw ) {
            if ( empty( $kw ) ) {
                continue;
            }

            $results = self::search( $kw, 2 );

            if ( is_wp_error( $results ) ) {
                // YouTube API 자체가 비활성이면 바로 중단
                if ( $results->get_error_code() === 'yt_not_enabled' ) {
                    return $results;
                }
                continue;
            }

            foreach ( $results as $r ) {
                if ( isset( $seen_ids[ $r['video_id'] ] ) ) {
                    continue;
                }
                $seen_ids[ $r['video_id'] ] = true;
                $all_results[] = $r;

                if ( count( $all_results ) >= $max_total ) {
                    break 2;
                }
            }
        }

        return $all_results;
    }

    /**
     * 강의 생성 시 자동으로 YouTube 영상 검색 및 저장
     */
    public static function auto_embed_for_lesson( $lesson_id, $keywords ) {
        $results = self::search_best( $keywords, 2 );

        if ( is_wp_error( $results ) ) {
            // API 비활성 등의 오류 — 조용히 실패
            update_post_meta( $lesson_id, '_dd_video_embeds', wp_json_encode( array(), JSON_UNESCAPED_UNICODE ) );
            return $results;
        }

        if ( empty( $results ) ) {
            update_post_meta( $lesson_id, '_dd_video_embeds', wp_json_encode( array(), JSON_UNESCAPED_UNICODE ) );
            return array();
        }

        update_post_meta( $lesson_id, '_dd_video_embeds', wp_json_encode( $results, JSON_UNESCAPED_UNICODE ) );
        return $results;
    }
}
