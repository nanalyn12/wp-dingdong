<?php
/**
 * DD_Youtube_Subtitles — YouTube 정보/자막 추출 엔진
 *
 * ⚠️ 드라마 학습(유튜브 드라마 가져오기) 기능은 제거되었습니다.
 *    (해당 기능은 별도 web app에서 담당)
 *
 * 이 클래스는 이제 **중국어 노래 학습(lesson_type=song) 전용 공통 유틸**로만 남습니다:
 *   parse_youtube_url / fetch_info / fetch_subtitles / parse_srt / parse_smi
 * → DD_Song_Course_Generator 가 그대로 재사용하므로 삭제하면 안 됩니다.
 */
class DD_Youtube_Subtitles {

    /** fetch 단계별 진단용 — log() 호출 시 last_log/call_log 양쪽 갱신 */
    private static $last_log = '';
    /** 한 fetch_subtitles 호출 안에서 누적된 log 메시지들 (null이면 누적 미사용) */
    private static $call_log = null;
    /** 중국어 트랙은 찾았으나 다운로드가 차단(PO token/서명 URL, 200+빈본문)된 경우 true */
    private static $zh_found_but_blocked = false;

    public static function parse_youtube_url( $url ) {
        $url = trim( $url );

        // Playlist: ?list=PLxxx or &list=PLxxx
        $playlist_id = '';
        if ( preg_match( '/[?&]list=([A-Za-z0-9_-]+)/', $url, $m ) ) {
            $playlist_id = $m[1];
        }

        // Video ID
        $video_id = '';
        if ( preg_match( '/(?:youtu\.be\/|[?&]v=)([A-Za-z0-9_-]{11})/', $url, $m ) ) {
            $video_id = $m[1];
        }

        if ( empty( $video_id ) && empty( $playlist_id ) ) {
            return new WP_Error( 'invalid_url', '유효한 YouTube URL이 아닙니다.' );
        }

        return array(
            'video_id'    => $video_id,
            'playlist_id' => $playlist_id,
        );
    }

    public static function fetch_info( $url ) {
        $parsed = self::parse_youtube_url( $url );
        if ( is_wp_error( $parsed ) ) {
            return $parsed;
        }

        $api_key = DD_YouTube_Search::get_key();
        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_api_key', 'YouTube API 키가 설정되지 않았습니다.' );
        }

        // RD* 플레이리스트는 YouTube 자동 생성 믹스/라디오 → Data API 로 항목 열거 불가.
        // 이 경우 함께 들어온 단일 video_id 가 있으면 그것을 우선 사용한다.
        $is_mix = ! empty( $parsed['playlist_id'] ) && strpos( $parsed['playlist_id'], 'RD' ) === 0;

        if ( ! empty( $parsed['playlist_id'] ) && ! ( $is_mix && ! empty( $parsed['video_id'] ) ) ) {
            return self::fetch_playlist_info( $parsed['playlist_id'], $api_key );
        }

        return self::fetch_video_info( $parsed['video_id'], $api_key );
    }

    private static function fetch_video_info( $video_id, $api_key ) {
        $url = add_query_arg( array(
            'part' => 'snippet,contentDetails',
            'id'   => $video_id,
            'key'  => $api_key,
        ), 'https://www.googleapis.com/youtube/v3/videos' );

        $response = wp_remote_get( $url, array( 'timeout' => 15 ) );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $data['items'][0] ) ) {
            return new WP_Error( 'not_found', '영상을 찾을 수 없습니다.' );
        }

        $item = $data['items'][0];
        return array(
            'type'    => 'video',
            'channel' => $item['snippet']['channelTitle'] ?? '',
            'items'   => array(
                array(
                    'video_id'  => $video_id,
                    'title'     => $item['snippet']['title'] ?? '',
                    'thumbnail' => $item['snippet']['thumbnails']['medium']['url'] ?? '',
                    'duration'  => $item['contentDetails']['duration'] ?? '',
                ),
            ),
        );
    }

    private static function fetch_playlist_info( $playlist_id, $api_key ) {
        $items     = array();
        $page      = '';
        $pl_title  = '';
        $channel   = '';

        for ( $i = 0; $i < 5; $i++ ) {
            $args = array(
                'part'       => 'snippet,contentDetails',
                'playlistId' => $playlist_id,
                'maxResults' => 50,
                'key'        => $api_key,
            );
            if ( $page ) {
                $args['pageToken'] = $page;
            }

            $url      = add_query_arg( $args, 'https://www.googleapis.com/youtube/v3/playlistItems' );
            $response = wp_remote_get( $url, array( 'timeout' => 15 ) );
            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( empty( $data['items'] ) ) {
                break;
            }

            if ( empty( $channel ) && ! empty( $data['items'][0]['snippet']['channelTitle'] ) ) {
                $channel = $data['items'][0]['snippet']['channelTitle'];
            }

            foreach ( $data['items'] as $item ) {
                $vid = $item['contentDetails']['videoId'] ?? ( $item['snippet']['resourceId']['videoId'] ?? '' );
                if ( empty( $vid ) ) {
                    continue;
                }
                $items[] = array(
                    'video_id'  => $vid,
                    'title'     => $item['snippet']['title'] ?? '',
                    'thumbnail' => $item['snippet']['thumbnails']['medium']['url'] ?? '',
                    'duration'  => '',
                );
            }

            $page = $data['nextPageToken'] ?? '';
            if ( empty( $page ) ) {
                break;
            }
        }

        // Fetch playlist title
        $pl_url = add_query_arg( array(
            'part' => 'snippet',
            'id'   => $playlist_id,
            'key'  => $api_key,
        ), 'https://www.googleapis.com/youtube/v3/playlists' );

        $pl_resp = wp_remote_get( $pl_url, array( 'timeout' => 10 ) );
        if ( ! is_wp_error( $pl_resp ) ) {
            $pl_data = json_decode( wp_remote_retrieve_body( $pl_resp ), true );
            $pl_title = $pl_data['items'][0]['snippet']['title'] ?? '';
        }

        return array(
            'type'           => 'playlist',
            'playlist_title' => $pl_title,
            'channel'        => $channel,
            'items'          => $items,
        );
    }

    /** REST 콜백 등 외부에서 진입 로그를 남길 수 있도록 공개 */
    public static function log_public( $message ) {
        self::log( $message );
    }

    public static function fetch_subtitles( $video_id ) {
        @set_time_limit( 120 );
        self::$zh_found_but_blocked = false;
        self::log( "=== fetch_subtitles START: video={$video_id}" );

        // 캐싱: 한 번 성공적으로 추출한 자막은 7일간 재사용 → 같은 영상 재확인 시
        // YouTube 재요청을 건너뛰어 429(rate limit) 누적을 막는다. 성공만 캐싱하고
        // 실패는 캐싱하지 않아, 잠시 후 재시도하면 다시 정상 경로를 탄다.
        $cache_key = 'dd_subs_' . md5( $video_id );
        $cached    = get_transient( $cache_key );
        if ( is_array( $cached ) && ! empty( $cached['found'] ) ) {
            self::log( "=== fetch_subtitles CACHE HIT: video={$video_id} (source=" . ( $cached['source'] ?? '?' ) . ')' );
            $cached['cached'] = true;
            // 과거(번체) 캐시 보정 포함 — 읽을 때도 간체로 통일.
            return self::simplify_subtitle_result( $cached );
        }

        $attempts = array();

        // stage_key = 사용자에게 보여줄 라벨/로그 키 / method = 실제 PHP 메서드 이름
        // 2025년 이후 YouTube 가 WEB/구 ANDROID 클라이언트와 watch 페이지의 player response 에서
        // PO token 없이는 captionTracks 를 더 이상 내려주지 않음. PO token 이 필요 없는
        // iOS / ANDROID_VR / TVHTML5 클라이언트를 먼저 시도한다.
        $stages = array(
            array( 'key' => 'innertube_ios',        'label' => 'Innertube iOS',                      'method' => 'fetch_subtitles_via_innertube_ios' ),
            array( 'key' => 'innertube_android_vr', 'label' => 'Innertube ANDROID_VR',               'method' => 'fetch_subtitles_via_innertube_android_vr' ),
            array( 'key' => 'innertube_tv',         'label' => 'Innertube TV',                       'method' => 'fetch_subtitles_via_innertube_tv' ),
            array( 'key' => 'innertube_web',        'label' => 'Innertube WEB',                      'method' => 'fetch_subtitles_via_innertube' ),
            array( 'key' => 'innertube_android',    'label' => 'Innertube ANDROID',                  'method' => 'fetch_subtitles_via_innertube_android' ),
            array( 'key' => 'timedtext_list',       'label' => 'timedtext list API',                 'method' => 'fetch_subtitles_via_timedtext_list' ),
            array( 'key' => 'watch_page',           'label' => 'watch page ytInitialPlayerResponse', 'method' => 'fetch_subtitles_via_watch_page' ),
        );

        foreach ( $stages as $stage ) {
            $method = $stage['method'];
            if ( ! method_exists( __CLASS__, $method ) ) {
                $attempts[] = array(
                    'stage'  => $stage['key'],
                    'label'  => $stage['label'],
                    'reason' => '메서드 미정의: ' . $method,
                );
                continue;
            }

            // call_log 리셋 후 메서드 호출 → 메서드가 self::log()로 남긴 모든 메시지 누적
            self::$last_log = '';
            self::$call_log = array();
            $result = self::$method( $video_id );
            $logs = self::$call_log;
            self::$call_log = null;

            if ( $result && ! empty( $result['found'] ) ) {
                // 번체자(繁體) 자막을 간체자(简体)로 통일 → Gemini 입력/가사 표시를 모두 간체로.
                $result = self::simplify_subtitle_result( $result );
                $result['attempts'] = $attempts; // 이전 실패도 함께 첨부
                set_transient( $cache_key, $result, 7 * DAY_IN_SECONDS ); // 성공(간체화된) 자막 캐싱
                self::log( "=== fetch_subtitles OK via {$stage['key']} (source=" . ( $result['source'] ?? '?' ) . ', lines=' . count( $result['subtitles'] ?? array() ) . ')' );
                return $result;
            }

            // 가장 마지막 log를 1차 사유로, 그 직전 log를 2차 사유로 (다운로드 에러 등이 보통 직전에 찍힘)
            $primary   = ! empty( $logs ) ? end( $logs ) : '';
            $secondary = ( count( $logs ) >= 2 ) ? $logs[ count( $logs ) - 2 ] : '';
            $combined  = $primary;
            if ( $secondary && stripos( $primary, $secondary ) === false ) {
                $combined = $secondary . ' → ' . $primary;
            }

            $attempts[] = array(
                'stage'   => $stage['key'],
                'label'   => $stage['label'],
                'reason'  => $combined ?: '실패 (사유 미상, 로그 없음)',
                'all_logs' => $logs, // UI hover 툴팁용 전체 로그
            );
        }

        self::log( "Subtitle: all methods failed for video {$video_id}" );

        // 진단 메시지 — 두 실패 케이스를 구분해 사용자에게 정확히 안내.
        if ( self::$zh_found_but_blocked ) {
            // 중국어 자막은 존재하지만 YouTube가 서버측 다운로드를 차단(PO token/서명 URL).
            $reason  = 'blocked';
            $message = '이 영상에는 중국어 자막이 있지만, YouTube가 서버에서의 자동 다운로드를 차단했습니다(PO token). '
                     . '자막 자체는 멀쩡하니 고장이 아니며, 아래 "자막 직접 입력"에 붙여넣으면 그대로 강좌를 만들 수 있습니다. '
                     . '북마클릿(tools/youtube-transcript-bookmarklet.html)을 쓰면 브라우저에서 1클릭으로 전체 자막을 복사할 수 있습니다.';
        } else {
            $reason  = 'not_found';
            $message = '이 영상에서 중국어 자막을 찾지 못했습니다. 자동 추출이 막혔거나 중국어 자막이 없는 영상일 수 있습니다. '
                     . '아래 "자막 직접 입력"에 자막을 붙여넣어 주세요.';
        }

        return array(
            'found'    => false,
            'reason'   => $reason,
            'blocked'  => self::$zh_found_but_blocked,
            'message'  => $message,
            'attempts' => $attempts,
        );
    }

    /**
     * 자막 결과의 중국어 텍스트(full_text + 각 줄 text)를 간체자로 변환한다.
     * 번체자(繁體) 자막이 들어와도 강좌·콘텐츠는 간체자(简体)로만 생성되도록 통일.
     * 번체 글자가 없으면 변환 비용 없이 그대로 통과한다.
     */
    private static function simplify_subtitle_result( $result ) {
        if ( ! is_array( $result ) || ! class_exists( 'DD_Chinese' ) ) {
            return $result;
        }

        $converted = false;

        if ( isset( $result['full_text'] ) && is_string( $result['full_text'] ) ) {
            if ( DD_Chinese::has_traditional( $result['full_text'] ) ) {
                $converted = true;
            }
            $result['full_text'] = DD_Chinese::to_simplified( $result['full_text'] );
        }

        if ( ! empty( $result['subtitles'] ) && is_array( $result['subtitles'] ) ) {
            foreach ( $result['subtitles'] as $i => $sub ) {
                if ( isset( $sub['text'] ) && is_string( $sub['text'] ) ) {
                    $result['subtitles'][ $i ]['text'] = DD_Chinese::to_simplified( $sub['text'] );
                }
            }
        }

        if ( $converted ) {
            self::log( 'Subtitle: 번체자 감지 → 간체자로 변환 완료' );
        }

        return $result;
    }


    /**
     * Innertube player API 공통 호출기.
     * 여러 클라이언트(iOS/ANDROID_VR/TV/WEB/ANDROID)에서 재사용한다.
     * captionTracks 추출 → zh 직접 다운로드 → 실패 시 tlang=zh-Hans 자동번역 순으로 시도.
     *
     * @param string $label   로그/진단용 라벨 (예: 'Innertube iOS')
     * @param array  $client  context.client 에 들어갈 클라이언트 정의
     * @param array  $headers 추가 HTTP 헤더 (X-YouTube-Client-Name/Version, User-Agent 등)
     * @param array  $cookies WP_Http_Cookie 배열 (선택)
     */
    private static function fetch_via_innertube_client( $video_id, $label, $client, $headers = array(), $cookies = array() ) {
        $url  = 'https://www.youtube.com/youtubei/v1/player?prettyPrint=false';
        $body = array(
            'videoId'        => $video_id,
            'context'        => array( 'client' => $client ),
            'contentCheckOk' => true,
            'racyCheckOk'    => true,
        );

        $args = array(
            'headers' => array_merge( array( 'Content-Type' => 'application/json' ), $headers ),
            'body'    => wp_json_encode( $body ),
            'timeout' => 20,
        );
        if ( ! empty( $cookies ) ) {
            $args['cookies'] = $cookies;
        }

        $response = wp_remote_post( $url, $args );
        if ( is_wp_error( $response ) ) {
            self::log( "{$label}: error " . $response->get_error_message() );
            return null;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            self::log( "{$label}: HTTP {$code}" );
            return null;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) ) {
            self::log( "{$label}: JSON parse failed" );
            return null;
        }

        $tracks = $data['captions']['playerCaptionsTracklistRenderer']['captionTracks'] ?? array();
        if ( empty( $tracks ) ) {
            // playability 상태도 함께 기록 (LOGIN_REQUIRED / 봇 차단 진단용)
            $status       = $data['playabilityStatus']['status'] ?? '?';
            $has_captions = isset( $data['captions'] ) ? 'true' : 'false';
            self::log( "{$label}: no captionTracks (playability={$status}, captions={$has_captions})" );
            return null;
        }

        self::log( "{$label}: found " . count( $tracks ) . ' caption tracks' );

        // 1순위: zh 직접 트랙
        $zh_track = self::find_chinese_track( $tracks );
        if ( $zh_track && ! empty( $zh_track['baseUrl'] ) ) {
            $result = self::download_and_parse_subtitles( $zh_track['baseUrl'], $zh_track );
            if ( $result ) return $result;
            // 트랙은 찾았는데 다운로드 실패 → PO token/서명 URL 차단 신호.
            self::$zh_found_but_blocked = true;
            self::log( "{$label}: zh 트랙은 찾았으나 다운로드 차단(빈 응답) — PO token 차단 추정" );
        }

        // 2순위: 다른 언어 트랙을 zh-Hans로 자동번역
        $translation_langs = $data['captions']['playerCaptionsTracklistRenderer']['translationLanguages'] ?? array();
        $fallback_track    = self::find_translatable_to_zh_track( $tracks, $translation_langs );
        if ( $fallback_track && ! empty( $fallback_track['baseUrl'] ) ) {
            $orig = $fallback_track['languageCode'] ?? '?';
            self::log( "{$label}: trying tlang fallback from {$orig} → zh-Hans" );
            $result = self::download_and_parse_subtitles( $fallback_track['baseUrl'], $fallback_track, 'zh-Hans' );
            if ( $result ) return $result;
        }

        $available = array_map( function( $t ) { return $t['languageCode'] ?? '?'; }, $tracks );
        self::log( "{$label}: no Chinese (zh 직접/tlang 모두 실패). Available: " . implode( ', ', $available ) );
        return null;
    }

    /**
     * Innertube iOS 클라이언트 — 2025년 현재 PO token 없이 captionTracks 를 가장 잘 내려줌.
     */
    private static function fetch_subtitles_via_innertube_ios( $video_id ) {
        return self::fetch_via_innertube_client(
            $video_id,
            'Innertube iOS',
            array(
                'clientName'    => 'IOS',
                'clientVersion' => '19.45.4',
                'deviceMake'    => 'Apple',
                'deviceModel'   => 'iPhone16,2',
                'osName'        => 'iOS',
                'osVersion'     => '18.1.0.22B83',
                'hl'            => 'ko',
                'gl'            => 'KR',
            ),
            array(
                'User-Agent'               => 'com.google.ios.youtube/19.45.4 (iPhone16,2; U; CPU iOS 18_1_0 like Mac OS X)',
                'X-YouTube-Client-Name'    => '5',
                'X-YouTube-Client-Version' => '19.45.4',
            )
        );
    }

    /**
     * Innertube ANDROID_VR 클라이언트 — PO token / attestation 이 필요 없는 또 다른 경로.
     */
    private static function fetch_subtitles_via_innertube_android_vr( $video_id ) {
        return self::fetch_via_innertube_client(
            $video_id,
            'Innertube ANDROID_VR',
            array(
                'clientName'        => 'ANDROID_VR',
                'clientVersion'     => '1.61.48',
                'deviceMake'        => 'Oculus',
                'deviceModel'       => 'Quest 3',
                'osName'            => 'Android',
                'osVersion'         => '12',
                'androidSdkVersion' => 32,
                'hl'                => 'ko',
                'gl'                => 'KR',
            ),
            array(
                'User-Agent'               => 'com.google.android.apps.youtube.vr.oculus/1.61.48 (Linux; U; Android 12; GB; Quest 3) gzip',
                'X-YouTube-Client-Name'    => '28',
                'X-YouTube-Client-Version' => '1.61.48',
            )
        );
    }

    /**
     * Innertube TVHTML5 클라이언트 — 거실 TV/콘솔용. PO token 없이 동작하는 경우가 많음.
     */
    private static function fetch_subtitles_via_innertube_tv( $video_id ) {
        return self::fetch_via_innertube_client(
            $video_id,
            'Innertube TV',
            array(
                'clientName'    => 'TVHTML5',
                'clientVersion' => '7.20250120.19.00',
                'hl'            => 'ko',
                'gl'            => 'KR',
            ),
            array(
                'User-Agent'               => 'Mozilla/5.0 (PlayStation; PlayStation 4/12.00) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Safari/605.1.15',
                'X-YouTube-Client-Name'    => '7',
                'X-YouTube-Client-Version' => '7.20250120.19.00',
            )
        );
    }

    /**
     * Innertube WEB 클라이언트 — YouTube 웹이 사용하는 내부 API.
     * 2025년 이후 PO token 없이는 captionTracks 를 자주 생략하므로 후순위.
     */
    private static function fetch_subtitles_via_innertube( $video_id ) {
        return self::fetch_via_innertube_client(
            $video_id,
            'Innertube WEB',
            array(
                'clientName'    => 'WEB',
                'clientVersion' => '2.20260501.01.00',
                'hl'            => 'ko',
                'gl'            => 'KR',
                'userAgent'     => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
            ),
            array(
                'User-Agent'               => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
                'X-YouTube-Client-Name'    => '1',
                'X-YouTube-Client-Version' => '2.20260501.01.00',
                'Origin'                   => 'https://www.youtube.com',
                'Referer'                  => 'https://www.youtube.com/watch?v=' . $video_id,
            ),
            array(
                new WP_Http_Cookie( array( 'name' => 'SOCS', 'value' => 'CAESEwgDEgk2NjE5MTY2NzQaAmVuIAEaBgiA_L2uBg', 'domain' => '.youtube.com' ) ),
                new WP_Http_Cookie( array( 'name' => 'CONSENT', 'value' => 'PENDING+987', 'domain' => '.youtube.com' ) ),
            )
        );
    }

    /**
     * Innertube ANDROID 클라이언트 — 구버전. 현재는 PO token 을 요구하는 경우가 많아 후순위.
     */
    private static function fetch_subtitles_via_innertube_android( $video_id ) {
        return self::fetch_via_innertube_client(
            $video_id,
            'Innertube ANDROID',
            array(
                'clientName'        => 'ANDROID',
                'clientVersion'     => '19.29.37',
                'androidSdkVersion' => 30,
                'osName'            => 'Android',
                'osVersion'         => '11',
                'hl'                => 'ko',
                'gl'                => 'KR',
            ),
            array(
                'User-Agent'               => 'com.google.android.youtube/19.29.37 (Linux; U; Android 11) gzip',
                'X-YouTube-Client-Name'    => '3',
                'X-YouTube-Client-Version' => '19.29.37',
            )
        );
    }

    /**
     * captionTracks 배열에서 중국어 트랙 찾기 (manual 우선, ASR 후순위)
     */
    private static function find_chinese_track( $tracks ) {
        $zh_langs = array( 'zh', 'zh-Hans', 'zh-CN', 'zh-TW', 'zh-Hant', 'zh-HK' );

        // 1순위: 정확한 zh 매치 + manual (kind 없음)
        foreach ( $tracks as $track ) {
            $lang = $track['languageCode'] ?? '';
            $kind = $track['kind'] ?? '';
            if ( in_array( $lang, $zh_langs, true ) && $kind !== 'asr' ) {
                return $track;
            }
        }

        // 2순위: zh 부분 매치 + manual
        foreach ( $tracks as $track ) {
            $lang = $track['languageCode'] ?? '';
            $kind = $track['kind'] ?? '';
            if ( strpos( $lang, 'zh' ) === 0 && $kind !== 'asr' ) {
                return $track;
            }
        }

        // 3순위: zh ASR도 허용
        foreach ( $tracks as $track ) {
            $lang = $track['languageCode'] ?? '';
            if ( strpos( $lang, 'zh' ) === 0 ) {
                return $track;
            }
        }

        return null;
    }

    /**
     * zh 트랙이 없을 때 사용할 fallback 트랙 선택
     * 우선순위: 영어 manual > 영어 ASR > 기타 manual > 기타 ASR
     * 이 트랙을 baseUrl + tlang=zh로 다운로드하면 zh로 자동번역됨.
     */
    private static function find_translatable_to_zh_track( $tracks, $translation_langs ) {
        // translationLanguages에 zh가 포함되어야 자동번역 가능
        $can_translate = false;
        foreach ( $translation_langs as $lang ) {
            $code = $lang['languageCode'] ?? '';
            if ( strpos( $code, 'zh' ) === 0 ) { $can_translate = true; break; }
        }
        if ( ! $can_translate && ! empty( $translation_langs ) ) {
            // YouTube가 번역 언어를 노출하지 않더라도 tlang=zh-Hans는 거의 항상 동작
            // → 일단 시도 허용
            $can_translate = true;
        }
        // translation_langs가 비어있어도 시도 — 많은 케이스에서 tlang=zh가 그냥 동작함
        if ( ! $can_translate ) $can_translate = true;

        $rank = function ( $track ) {
            $lang = $track['languageCode'] ?? '';
            $is_asr = ! empty( $track['kind'] ) && $track['kind'] === 'asr';
            if ( $lang === 'en' && ! $is_asr ) return 1;
            if ( $lang === 'en' && $is_asr )   return 2;
            if ( strpos( $lang, 'en' ) === 0 && ! $is_asr ) return 3;
            if ( strpos( $lang, 'en' ) === 0 && $is_asr )   return 4;
            if ( ! $is_asr ) return 5; // 다른 언어 manual
            return 6;                  // 다른 언어 ASR
        };

        $sorted = $tracks;
        usort( $sorted, function ( $a, $b ) use ( $rank ) {
            return $rank( $a ) - $rank( $b );
        } );

        return $sorted[0] ?? null;
    }

    /**
     * baseUrl에서 자막을 다운로드하고 파싱
     * @param string $translate_to YouTube tlang 파라미터 ('zh-Hans' 등). 빈 문자열이면 원본 언어 그대로.
     *
     * 다운로드 신뢰도 향상 트릭:
     *  - baseUrl이 HTML에서 추출됐을 수 있으니 &amp; → & 등 엔티티 디코드
     *  - srv3 → json3 → srv1 → vtt 순서로 fmt 다중 시도
     *  - fmt 파라미터가 baseUrl에 이미 있을 수 있어 깔끔히 제거 후 재부여
     */
    private static function download_and_parse_subtitles( $base_url, $track_info, $translate_to = '' ) {
        // 1) HTML 엔티티 디코드 (watch_page에서 추출한 경우 &amp;가 들어있음)
        $base_url = html_entity_decode( $base_url, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

        // 2) 기존 fmt/tlang 파라미터 제거 (재부여를 위해)
        $base_url = preg_replace( '/([?&])fmt=[^&]*/', '$1', $base_url );
        $base_url = preg_replace( '/([?&])tlang=[^&]*/', '$1', $base_url );
        $base_url = rtrim( $base_url, '?&' );
        $base_url = preg_replace( '/[?&]+$/', '', $base_url );

        $glue  = ( strpos( $base_url, '?' ) === false ) ? '?' : '&';
        $tlang = ! empty( $translate_to ) ? ( '&tlang=' . urlencode( $translate_to ) ) : '';

        // 3) 여러 fmt 시도 — YouTube가 트랙마다 지원 포맷이 다름.
        //    json3가 파싱 안정성이 가장 좋아 1순위. 같은 baseUrl을 짧은 시간에 너무 많이
        //    때리면 YouTube가 429(rate limit)를 주므로 포맷 수는 최소화하고, 429는
        //    지수 백오프로 재시도한다. 그래도 429가 지속되면 IP 단위 throttle 이므로 중단.
        $fmts = array( 'json3', 'srv3', 'srv1', 'vtt' );
        $orig_lang_log = $track_info['languageCode'] ?? '?';
        $kind_log = ! empty( $track_info['kind'] ) ? $track_info['kind'] : 'manual';

        $sub_body     = '';
        $subtitles    = array();
        $picked_fmt   = '';
        $last_http    = 0;
        $rate_limited = false;
        foreach ( $fmts as $fmt ) {
            $sub_url = $base_url . $glue . 'fmt=' . $fmt . $tlang;

            // 429 대응: 같은 fmt를 지수 백오프(2초→4초)로 최대 3회 재시도
            $sub_resp = null;
            for ( $attempt = 1; $attempt <= 3; $attempt++ ) {
                $sub_resp = wp_remote_get( $sub_url, array(
                    'timeout'    => 15,
                    'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                    'headers'    => array(
                        'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
                    ),
                ) );
                if ( is_wp_error( $sub_resp ) ) {
                    break;
                }
                if ( wp_remote_retrieve_response_code( $sub_resp ) !== 429 ) {
                    break;
                }
                self::log( "Subtitle dl HTTP 429 (fmt={$fmt}, {$orig_lang_log}/{$kind_log}, tlang={$translate_to}) — 백오프 후 재시도 {$attempt}/3" );
                if ( $attempt < 3 ) {
                    sleep( $attempt ); // 1초 → 2초
                }
            }

            if ( is_wp_error( $sub_resp ) ) {
                self::log( "Subtitle dl error (fmt={$fmt}, {$orig_lang_log}/{$kind_log}, tlang={$translate_to}): " . $sub_resp->get_error_message() );
                continue;
            }
            $last_http = wp_remote_retrieve_response_code( $sub_resp );
            $sub_body  = wp_remote_retrieve_body( $sub_resp );

            if ( $last_http === 429 ) {
                // IP 단위 rate limit — 다른 fmt도 동일하게 429일 것이므로 더 시도하지 않음
                $rate_limited = true;
                self::log( "Subtitle dl 429 지속 (fmt={$fmt}, {$orig_lang_log}/{$kind_log}, tlang={$translate_to}) — fmt 루프 중단 (IP rate limit)" );
                break;
            }
            if ( $last_http !== 200 ) {
                self::log( "Subtitle dl HTTP {$last_http} (fmt={$fmt}, {$orig_lang_log}/{$kind_log}, tlang={$translate_to})" );
                continue;
            }
            if ( strlen( $sub_body ) === 0 ) {
                self::log( "Subtitle dl empty body (fmt={$fmt}, {$orig_lang_log}/{$kind_log}, tlang={$translate_to})" );
                continue;
            }

            $subtitles = self::parse_timedtext_xml( $sub_body );
            if ( ! empty( $subtitles ) ) {
                $picked_fmt = $fmt;
                break;
            }
            self::log( "Subtitle parse empty (fmt={$fmt}, body_len=" . strlen( $sub_body ) . ", first40=" . mb_substr( $sub_body, 0, 40 ) . ')' );
        }

        if ( empty( $subtitles ) ) {
            $hint = $rate_limited ? ' [YouTube 속도제한(429): 잠시 후 재시도 필요]' : '';
            self::log( "Subtitle: all fmts failed for {$orig_lang_log}/{$kind_log} (last HTTP {$last_http}, tlang={$translate_to}){$hint}" );
            return null;
        }

        $full_text   = implode( "\n", array_column( $subtitles, 'text' ) );
        $orig_lang   = $track_info['languageCode'] ?? '?';
        $is_asr      = ! empty( $track_info['kind'] ) && $track_info['kind'] === 'asr';
        $translated  = ! empty( $translate_to );
        $final_lang  = $translated ? $translate_to : $orig_lang;

        // 소스 라벨 — UI에서 사용자에게 보여줄 용도
        if ( $translated ) {
            $source = 'translated';   // 다른 언어 → zh 자동번역
            $detail = "{$orig_lang}→{$translate_to}" . ( $is_asr ? ' (ASR)' : '' );
        } elseif ( $is_asr ) {
            $source = 'asr';          // YouTube 자동생성 zh 자막
            $detail = $orig_lang . ' ASR';
        } else {
            $source = 'manual';       // 수동 업로드된 zh 자막
            $detail = $orig_lang;
        }

        self::log( "Subtitle OK: source={$source} ({$detail}), lines=" . count( $subtitles ) );

        return array(
            'found'     => true,
            'language'  => $final_lang,
            'type'      => $is_asr ? 'asr' : 'manual',
            'source'    => $source,
            'detail'    => $detail,
            'subtitles' => $subtitles,
            'full_text' => $full_text,
        );
    }

    /**
     * timedtext list API 로 자막 트랙 목록 조회 후 중국어 트랙 다운로드
     */
    private static function fetch_subtitles_via_timedtext_list( $video_id ) {
        $list_url = 'https://www.youtube.com/api/timedtext?type=list&v=' . urlencode( $video_id );
        $response = wp_remote_get( $list_url, array(
            'timeout'    => 15,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        ) );

        if ( is_wp_error( $response ) ) {
            self::log( 'timedtext list error: ' . $response->get_error_message() );
            return null;
        }

        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) {
            self::log( 'timedtext list: empty response' );
            return null;
        }

        // XML 파싱
        libxml_use_internal_errors( true );
        $xml = simplexml_load_string( $body );
        if ( ! $xml ) {
            self::log( 'timedtext list: XML parse failed, body=' . mb_substr( $body, 0, 200 ) );
            return null;
        }

        // 트랙 후보 정리 + zh 우선/영어 fallback 선정
        $zh_langs    = array( 'zh', 'zh-Hans', 'zh-CN', 'zh-TW', 'zh-Hant', 'zh-HK' );
        $zh_track    = null;
        $fallback    = null;
        $fallback_rank = 999;
        $track_count = 0;

        foreach ( $xml->track as $track ) {
            $track_count++;
            $lang_code = (string) ( $track['lang_code'] ?? '' );
            $kind      = (string) ( $track['kind'] ?? '' );
            if ( $zh_track === null && ( in_array( $lang_code, $zh_langs, true ) || strpos( $lang_code, 'zh' ) === 0 ) ) {
                $zh_track = $track;
                continue;
            }
            // fallback 랭킹: 영어 manual > 영어 ASR > 기타 manual > 기타 ASR
            $is_asr = ( $kind === 'asr' );
            $is_en  = ( strpos( $lang_code, 'en' ) === 0 );
            $rank = $is_en && ! $is_asr ? 1 : ( $is_en && $is_asr ? 2 : ( ! $is_asr ? 3 : 4 ) );
            if ( $rank < $fallback_rank ) {
                $fallback      = $track;
                $fallback_rank = $rank;
            }
        }

        self::log( "timedtext list: found {$track_count} tracks" );

        // 자막 1회 다운로드 시도 헬퍼
        $download = function ( $track, $tlang_to ) use ( $video_id ) {
            $lang = (string) ( $track['lang_code'] ?? '' );
            $name = (string) ( $track['name'] ?? '' );
            $args = array(
                'type' => 'track',
                'v'    => $video_id,
                'lang' => $lang,
                'name' => $name,
                'fmt'  => 'srv3',
            );
            if ( ! empty( $tlang_to ) ) {
                $args['tlang'] = $tlang_to;
            }
            $sub_url  = add_query_arg( $args, 'https://www.youtube.com/api/timedtext' );
            $sub_resp = wp_remote_get( $sub_url, array(
                'timeout'    => 15,
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            ) );
            if ( is_wp_error( $sub_resp ) ) { return null; }
            $body = wp_remote_retrieve_body( $sub_resp );
            $subs = DD_Youtube_Subtitles::parse_timedtext_xml_public( $body );
            if ( empty( $subs ) ) { return null; }
            $is_asr = ( (string) ( $track['kind'] ?? '' ) === 'asr' );
            $translated = ! empty( $tlang_to );
            $source = $translated ? 'translated' : ( $is_asr ? 'asr' : 'manual' );
            $detail = $translated ? ( $lang . '→' . $tlang_to . ( $is_asr ? ' (ASR)' : '' ) ) : $lang . ( $is_asr ? ' ASR' : '' );
            return array(
                'found'     => true,
                'language'  => $translated ? $tlang_to : $lang,
                'type'      => $is_asr ? 'asr' : 'manual',
                'source'    => $source,
                'detail'    => $detail,
                'subtitles' => $subs,
                'full_text' => implode( "\n", array_column( $subs, 'text' ) ),
            );
        };

        // 1순위: zh 직접
        if ( $zh_track ) {
            $result = $download( $zh_track, '' );
            if ( $result ) {
                self::log( 'timedtext OK (direct zh)' );
                return $result;
            }
            self::$zh_found_but_blocked = true;
            self::log( 'timedtext: zh 트랙은 찾았으나 다운로드 차단(빈 응답)' );
        }

        // 2순위: 영어/기타 → zh-Hans 자동번역
        if ( $fallback ) {
            $orig = (string) ( $fallback['lang_code'] ?? '?' );
            self::log( "timedtext: trying tlang fallback from {$orig} → zh-Hans" );
            $result = $download( $fallback, 'zh-Hans' );
            if ( $result ) return $result;
        }

        $langs = array();
        foreach ( $xml->track as $t ) {
            $langs[] = (string) ( $t['lang_code'] ?? '?' );
        }
        self::log( 'timedtext list: zh 직접/tlang 모두 실패. Available: ' . implode( ', ', $langs ) );
        return null;
    }

    // PHP closure에서 self:: 호출 불가 → public으로 노출
    public static function parse_timedtext_xml_public( $xml_str ) {
        return self::parse_timedtext_xml( $xml_str );
    }

    /**
     * watch 페이지에서 ytInitialPlayerResponse 추출 (fallback)
     */
    private static function fetch_subtitles_via_watch_page( $video_id ) {
        $watch_url = 'https://www.youtube.com/watch?v=' . urlencode( $video_id );
        $response  = wp_remote_get( $watch_url, array(
            'timeout'    => 20,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            'headers'    => array(
                'Accept-Language' => 'en-US,en;q=0.9',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            self::log( 'watch page fetch error: ' . $response->get_error_message() );
            return null;
        }

        $html = wp_remote_retrieve_body( $response );
        self::log( 'watch page: fetched ' . strlen( $html ) . ' bytes' );

        // Extract ytInitialPlayerResponse — brace-counting 방식으로 정확한 JSON 추출
        $player = self::extract_player_response( $html );
        if ( ! $player ) {
            self::log( 'watch page: ytInitialPlayerResponse extraction failed' );
            return null;
        }

        $tracks = $player['captions']['playerCaptionsTracklistRenderer']['captionTracks'] ?? array();
        if ( empty( $tracks ) ) {
            self::log( 'watch page: no caption tracks in player response' );
            return null;
        }

        self::log( 'watch page: found ' . count( $tracks ) . ' caption tracks' );

        // 1순위: zh 직접
        $zh_track = self::find_chinese_track( $tracks );
        if ( $zh_track && ! empty( $zh_track['baseUrl'] ) ) {
            $result = self::download_and_parse_subtitles( $zh_track['baseUrl'], $zh_track );
            if ( $result ) return $result;
            // 트랙은 watch 페이지에 분명히 있는데 다운로드가 빈 응답 → PO token/서명 URL 차단.
            self::$zh_found_but_blocked = true;
            self::log( 'watch page: zh 트랙은 찾았으나 다운로드 차단(빈 응답) — PO token 차단 추정' );
        }

        // 2순위: 영어/기타 → zh 자동번역
        $translation_langs = $player['captions']['playerCaptionsTracklistRenderer']['translationLanguages'] ?? array();
        $fallback_track    = self::find_translatable_to_zh_track( $tracks, $translation_langs );
        if ( $fallback_track && ! empty( $fallback_track['baseUrl'] ) ) {
            $orig = $fallback_track['languageCode'] ?? '?';
            self::log( "watch page: trying tlang fallback from {$orig} → zh-Hans" );
            $result = self::download_and_parse_subtitles( $fallback_track['baseUrl'], $fallback_track, 'zh-Hans' );
            if ( $result ) return $result;
        }

        $available = array_map( function( $t ) { return $t['languageCode'] ?? '?'; }, $tracks );
        self::log( 'watch page: no Chinese (zh 직접/tlang 모두 실패). Available: ' . implode( ', ', $available ) );
        return null;
    }

    /**
     * HTML에서 ytInitialPlayerResponse JSON을 brace-counting으로 추출
     */
    private static function extract_player_response( $html ) {
        // 여러 패턴 시도
        $patterns = array(
            'ytInitialPlayerResponse\s*=\s*',
            'window\s*\[\s*["\']ytInitialPlayerResponse["\']\s*\]\s*=\s*',
        );

        foreach ( $patterns as $pattern ) {
            if ( preg_match( '/' . $pattern . '/', $html, $m, PREG_OFFSET_CAPTURE ) ) {
                $start = $m[0][1] + strlen( $m[0][0] );
                $json  = self::extract_json_object( $html, $start );
                if ( $json !== null ) {
                    $parsed = json_decode( $json, true );
                    if ( $parsed && is_array( $parsed ) ) {
                        return $parsed;
                    }
                    self::log( 'extract_player_response: JSON parse failed, len=' . strlen( $json ) );
                }
            }
        }

        return null;
    }

    /**
     * 문자열의 지정 위치부터 brace-counting으로 JSON 객체 추출
     */
    private static function extract_json_object( $str, $start ) {
        $len   = strlen( $str );
        $depth = 0;
        $in_string = false;
        $escape    = false;

        if ( $start >= $len || $str[ $start ] !== '{' ) {
            return null;
        }

        for ( $i = $start; $i < $len && $i < $start + 5000000; $i++ ) {
            $ch = $str[ $i ];

            if ( $escape ) {
                $escape = false;
                continue;
            }

            if ( $ch === '\\' && $in_string ) {
                $escape = true;
                continue;
            }

            if ( $ch === '"' ) {
                $in_string = ! $in_string;
                continue;
            }

            if ( $in_string ) {
                continue;
            }

            if ( $ch === '{' ) {
                $depth++;
            } elseif ( $ch === '}' ) {
                $depth--;
                if ( $depth === 0 ) {
                    return substr( $str, $start, $i - $start + 1 );
                }
            }
        }

        return null;
    }

    private static function parse_timedtext_xml( $xml_str ) {
        libxml_use_internal_errors( true );
        $xml = simplexml_load_string( $xml_str );
        if ( ! $xml ) {
            // Try JSON3 format
            $json = json_decode( $xml_str, true );
            if ( $json && ! empty( $json['events'] ) ) {
                return self::parse_json3_subtitles( $json );
            }
            return array();
        }

        $subs = array();
        foreach ( $xml->children() as $node ) {
            $name = $node->getName();
            if ( $name === 'p' || $name === 'text' ) {
                $start = (float) ( $node['t'] ?? $node['start'] ?? 0 );
                $dur   = (float) ( $node['d'] ?? $node['dur'] ?? 0 );
                $text  = strip_tags( (string) $node );
                $text  = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
                $text  = trim( $text );
                if ( ! empty( $text ) ) {
                    $subs[] = array(
                        'start'    => $start / 1000,
                        'duration' => $dur / 1000,
                        'text'     => $text,
                    );
                }
            }
        }

        return $subs;
    }

    private static function parse_json3_subtitles( $data ) {
        $subs = array();
        foreach ( $data['events'] as $event ) {
            $start = ( $event['tStartMs'] ?? 0 ) / 1000;
            $dur   = ( $event['dDurationMs'] ?? 0 ) / 1000;
            $parts = $event['segs'] ?? array();
            $text  = '';
            foreach ( $parts as $seg ) {
                $text .= $seg['utf8'] ?? '';
            }
            $text = trim( $text );
            if ( ! empty( $text ) && $text !== "\n" ) {
                $subs[] = array(
                    'start'    => $start,
                    'duration' => $dur,
                    'text'     => $text,
                );
            }
        }
        return $subs;
    }

    public static function parse_srt( $srt_text ) {
        $lines = preg_split( '/\r?\n/', trim( $srt_text ) );
        $text_parts = array();

        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( empty( $line ) || is_numeric( $line ) ) {
                continue;
            }
            if ( preg_match( '/^\d{2}:\d{2}:\d{2}/', $line ) ) {
                continue;
            }
            $line = strip_tags( $line );
            if ( ! empty( $line ) ) {
                $text_parts[] = $line;
            }
        }

        return implode( "\n", $text_parts );
    }

    public static function parse_smi( $smi_text ) {
        // Extract text between <SYNC> tags
        preg_match_all( '/<SYNC[^>]*>\s*<P[^>]*>(.*?)(?=<SYNC|<\/BODY)/si', $smi_text, $matches );
        $text_parts = array();

        foreach ( $matches[1] as $chunk ) {
            $text = strip_tags( $chunk );
            $text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
            $text = trim( $text );
            if ( ! empty( $text ) && $text !== '&nbsp;' ) {
                $text_parts[] = $text;
            }
        }

        return implode( "\n", $text_parts );
    }

    private static function log( $message ) {
        // 진단용 — 마지막 log 호출 메시지 + 누적 버퍼 양쪽에 기록
        self::$last_log = $message;
        if ( self::$call_log !== null ) {
            self::$call_log[] = $message;
        }

        $dir = WP_CONTENT_DIR . '/uploads/dingdong-lms';
        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
        }
        $log_file = $dir . '/debug.log';
        $time     = current_time( 'Y-m-d H:i:s' );
        file_put_contents( $log_file, "[{$time}] [YT-SUBS] {$message}\n", FILE_APPEND | LOCK_EX );
    }
}
