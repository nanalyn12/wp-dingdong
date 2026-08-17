<?php
class DD_API_Key {

    private static function get_encryption_key() {
        return defined( 'AUTH_KEY' ) ? AUTH_KEY : 'dd-lms-fallback-key';
    }

    public static function save( $api_key ) {
        $key    = self::get_encryption_key();
        $iv     = openssl_random_pseudo_bytes( 16 );
        $cipher = openssl_encrypt( $api_key, 'AES-256-CBC', $key, 0, $iv );
        $stored = base64_encode( $iv . '::' . $cipher );
        update_option( 'dd_lms_gemini_api_key', $stored );
        return true;
    }

    /**
     * Gemini API 키 조회.
     *
     * 해석 순서: DD_GEMINI_API_KEY 상수/환경변수(개발용 오버라이드) → wp_options.
     * 오버라이드가 없으면 기존 배포 동작(관리자 화면 등록 키)이 그대로 유지된다.
     */
    public static function get() {
        $override = DD_Env::get( 'DD_GEMINI_API_KEY' );
        if ( $override !== '' ) {
            return $override;
        }

        $stored = get_option( 'dd_lms_gemini_api_key', '' );
        if ( empty( $stored ) ) {
            return '';
        }
        $decoded = base64_decode( $stored );
        $parts   = explode( '::', $decoded, 2 );
        if ( count( $parts ) !== 2 ) {
            return '';
        }
        $key = self::get_encryption_key();
        $decrypted = openssl_decrypt( $parts[1], 'AES-256-CBC', $key, 0, $parts[0] );
        return $decrypted !== false ? $decrypted : '';
    }

    public static function has_key() {
        if ( DD_Env::has( 'DD_GEMINI_API_KEY' ) ) {
            return true;
        }
        return ! empty( get_option( 'dd_lms_gemini_api_key', '' ) );
    }

    public static function delete() {
        delete_option( 'dd_lms_gemini_api_key' );
    }
}
