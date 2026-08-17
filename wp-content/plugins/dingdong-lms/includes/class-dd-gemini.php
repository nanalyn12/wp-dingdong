<?php
class DD_Gemini {

    private static $api_base = 'https://generativelanguage.googleapis.com/v1beta/models/';

    /**
     * 모델 fallback 체인 — 안정 모델(2.x) 우선, preview(3.x) 후순위.
     * JS 측 window.DDGeminiModels와 동일하게 유지.
     * dd_lms_gemini_model 옵션에 사용자가 지정한 모델이 있으면 맨 앞에 추가.
     */
    private static function get_model_chain() {
        $chain = array(
            'gemini-2.5-flash',
            'gemini-2.0-flash',
            'gemini-2.5-flash-lite',
            'gemini-3.1-flash-lite',
            'gemini-3.5-flash',
            'gemini-3-flash-preview',
        );
        $preferred = get_option( 'dd_lms_gemini_model', '' );
        if ( ! empty( $preferred ) ) {
            // 중복 제거 후 맨 앞에 push
            $chain = array_values( array_unique( array_merge( array( $preferred ), $chain ) ) );
        }
        return $chain;
    }

    public static function generate( $prompt, $system_instruction = '' ) {
        $api_key = DD_API_Key::get();
        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_api_key', 'Gemini API 키가 설정되지 않았습니다.' );
        }

        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array( 'text' => $prompt ),
                    ),
                ),
            ),
            'generationConfig' => array(
                'temperature'      => 0.7,
                'topP'             => 0.9,
                'maxOutputTokens'  => 65536,
                'responseMimeType' => 'application/json',
            ),
        );

        if ( ! empty( $system_instruction ) ) {
            $body['systemInstruction'] = array(
                'parts' => array(
                    array( 'text' => $system_instruction ),
                ),
            );
        }

        return self::call_with_fallback( $api_key, $body );
    }

    /**
     * 모델 체인 따라가며 호출 — 404/429/5xx는 다음 모델로, 401/403은 즉시 종료.
     */
    private static function call_with_fallback( $api_key, $body ) {
        $models      = self::get_model_chain();
        $last_status = 0;
        $last_msg    = '';

        foreach ( $models as $idx => $model ) {
            $url = self::$api_base . $model . ':generateContent?key=' . $api_key;

            // 요청 사이 2~3초 간격 보장 (429 rate limit 완화)
            self::throttle();

            $response = wp_remote_post( $url, array(
                'headers' => array( 'Content-Type' => 'application/json' ),
                'body'    => wp_json_encode( $body ),
                'timeout' => 300,
            ) );

            if ( is_wp_error( $response ) ) {
                $last_msg = $response->get_error_message();
                self::log( "model={$model} http error: {$last_msg}" );
                continue; // 네트워크 오류는 다음 모델 시도
            }

            $code = wp_remote_retrieve_response_code( $response );
            $raw  = wp_remote_retrieve_body( $response );
            $data = json_decode( $raw, true );

            if ( $code === 200 ) {
                if ( empty( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
                    $last_msg    = '응답에 candidates 없음';
                    $last_status = 200;
                    self::log( "model={$model} empty candidates" );
                    continue; // 빈 응답은 다음 모델 시도
                }
                self::log( "model={$model} OK (try " . ( $idx + 1 ) . '/' . count( $models ) . ')' );
                return self::parse_json_response( $data['candidates'][0]['content']['parts'][0]['text'] );
            }

            $last_status = $code;
            $last_msg    = isset( $data['error']['message'] ) ? $data['error']['message'] : "HTTP {$code}";

            // 401/403 — 키 문제. 모델 바꿔도 동일하므로 즉시 종료.
            if ( $code === 401 || $code === 403 ) {
                self::log( "model={$model} auth error {$code} — chain abort" );
                return new WP_Error( 'auth_error', 'API 키 인증 실패: ' . $last_msg );
            }

            // 400 — 요청 자체가 잘못됨 (대부분 모델 바꿔도 안 됨). 즉시 종료.
            if ( $code === 400 ) {
                self::log( "model={$model} bad request 400 — chain abort: {$last_msg}" );
                return new WP_Error( 'bad_request', '요청 오류: ' . $last_msg );
            }

            // 404 (모델 미지원) / 429 (rate limit) / 5xx — 다음 모델로 fallback
            self::log( "model={$model} status {$code} — try next" );
        }

        // 모든 모델 실패
        if ( $last_status === 429 ) {
            return new WP_Error( 'rate_limit', 'API 사용량 한도 초과 (429). 잠시 후 다시 시도하세요.' );
        }
        return new WP_Error( 'api_error', '모든 모델 실패 (마지막: HTTP ' . $last_status . ' - ' . $last_msg . ')' );
    }

    /**
     * 연속된 Gemini 호출 사이에 2~3초 간격을 둔다 (429 rate limit 완화).
     * 마지막 호출 시각을 DB 옵션에 저장하므로, 강의별로 나뉜 별도 요청(별도 PHP 프로세스)
     * 사이에도 간격이 유지된다. 옵션은 autoload=false 로 저장해 부하를 줄인다.
     */
    private static function throttle() {
        $min      = mt_rand( 2000, 3000 ) / 1000; // 2.0 ~ 3.0초
        $last     = (float) get_option( 'dd_gemini_last_call', 0 );
        $now      = microtime( true );
        $elapsed  = $now - $last;

        if ( $last > 0 && $elapsed < $min ) {
            $wait = $min - $elapsed;
            self::log( 'throttle: ' . round( $wait, 2 ) . 's 대기 후 요청' );
            usleep( (int) ( $wait * 1000000 ) );
        }

        update_option( 'dd_gemini_last_call', microtime( true ), false );
    }

    private static function parse_json_response( $text ) {
        $text = trim( $text );

        // 코드블록(```json ... ```) 제거
        if ( preg_match( '/```(?:json)?\s*([\s\S]*?)\s*```/', $text, $m ) ) {
            $text = trim( $m[1] );
        }

        $parsed = json_decode( $text, true );
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $parsed ) ) {
            return $parsed;
        }

        // JSON 객체 { ... } 추출 시도
        $start = strpos( $text, '{' );
        $end   = strrpos( $text, '}' );
        if ( $start !== false && $end !== false && $end > $start ) {
            $json_str = substr( $text, $start, $end - $start + 1 );
            $parsed   = json_decode( $json_str, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $parsed ) ) {
                return $parsed;
            }
        }

        return new WP_Error( 'parse_error', 'JSON 파싱 실패: ' . mb_substr( $text, 0, 200 ) );
    }

    private static function log( $message ) {
        $dir = WP_CONTENT_DIR . '/uploads/dingdong-lms';
        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
        }
        $time = current_time( 'Y-m-d H:i:s' );
        @file_put_contents( $dir . '/debug.log', "[{$time}] [GEMINI] {$message}\n", FILE_APPEND | LOCK_EX );
    }
}
