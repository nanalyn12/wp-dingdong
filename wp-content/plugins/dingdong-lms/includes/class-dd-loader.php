<?php
class DD_Loader {

    public static function init() {
        add_action( 'init', array( 'DD_Post_Types', 'register' ) );
        add_action( 'init', array( 'DD_Public_Access', 'add_rewrite_rules' ) );
        add_action( 'init', array( __CLASS__, 'maybe_flush_rewrite_rules' ) );
        add_action( 'init', array( __CLASS__, 'ensure_landing_page' ) );
        add_action( 'rest_api_init', array( 'DD_Rest_API', 'register_routes' ) );
        add_action( 'template_redirect', array( 'DD_Public_Access', 'handle_request' ) );
        add_filter( 'query_vars', array( 'DD_Public_Access', 'query_vars' ) );
        add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
        // ZIP 전체 백업은 REST 가 아니라 admin-post 로 스트리밍한다 (대용량 대응).
        add_action( 'admin_post_dd_backup_archive', array( 'DD_Backup', 'handle_archive_download' ) );
        // 이미지 묶음 나눠 받기 — 대용량 ZIP 업로드가 막히는 호스팅용 우회 경로.
        add_action( 'admin_post_dd_backup_media_part', array( 'DD_Backup', 'handle_media_part_download' ) );
        add_action( 'http_api_curl', array( __CLASS__, 'fix_curl_timeout' ), 10, 3 );
    }

    /**
     * 랜딩페이지가 없으면 자동 생성한다 (활성화 훅이 실패했을 경우 자동 복구).
     *
     * ⚠️ 프론트페이지(show_on_front / page_on_front)는 여기서 건드리지 않는다.
     *    예전 구현은 매 init 마다 프론트페이지를 랜딩페이지로 덮어썼는데,
     *    그러면 이 플러그인을 설치한 사이트의 홈페이지가 영구 점거되고
     *    관리자가 [설정 → 읽기]에서 바꿔도 다음 요청에 되돌아간다.
     *    프론트페이지 지정은 플러그인 활성화 시 1회만 (DD_Setup::activate) 수행하고,
     *    그 뒤로는 사이트 소유자의 선택을 존중한다.
     */
    public static function ensure_landing_page() {
        $page_id = (int) get_option( 'dd_lms_landing_page_id' );

        if ( $page_id && get_post( $page_id ) && get_post_status( $page_id ) === 'publish' ) {
            return;
        }

        $page_id = wp_insert_post( array(
            'post_title'   => 'DingDong LMS',
            'post_content' => '',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_name'    => 'dingdong',
        ) );

        if ( is_wp_error( $page_id ) ) {
            return;
        }

        update_option( 'dd_lms_landing_page_id', $page_id );
    }

    public static function fix_curl_timeout( $handle, $parsed_args, $url ) {
        if ( strpos( $url, 'generativelanguage.googleapis.com' ) !== false ) {
            curl_setopt( $handle, CURLOPT_LOW_SPEED_LIMIT, 0 );
            curl_setopt( $handle, CURLOPT_LOW_SPEED_TIME, 0 );
            curl_setopt( $handle, CURLOPT_TIMEOUT, 300 );
            curl_setopt( $handle, CURLOPT_CONNECTTIMEOUT, 30 );
        }
    }

    public static function maybe_flush_rewrite_rules() {
        $key = 'dd_lms_rewrite_version';
        $ver = DD_LMS_VERSION;
        if ( get_option( $key ) !== $ver ) {
            flush_rewrite_rules();
            update_option( $key, $ver );
        }
    }

    public static function admin_menu() {
        add_menu_page(
            'Dingdong LMS',
            'Dingdong LMS',
            'manage_options',
            'dd-lms',
            array( __CLASS__, 'page_dashboard' ),
            'dashicons-welcome-learn-more',
            30
        );

        add_submenu_page(
            'dd-lms',
            '강좌 관리',
            '강좌 관리',
            'manage_options',
            'dd-lms',
            array( __CLASS__, 'page_dashboard' )
        );

        add_submenu_page(
            'dd-lms',
            'AI 강좌 생성',
            'AI 강좌 생성',
            'manage_options',
            'dd-lms-generator',
            array( __CLASS__, 'page_generator' )
        );

        add_submenu_page(
            'dd-lms',
            '인터랙티브 스토리',
            '인터랙티브 스토리',
            'manage_options',
            'dd-lms-stories',
            array( __CLASS__, 'page_stories' )
        );

        add_submenu_page(
            'dd-lms',
            '중국어 노래 학습',
            '중국어 노래 학습',
            'manage_options',
            'dd-lms-song',
            array( __CLASS__, 'page_song' )
        );

        add_submenu_page(
            'dd-lms',
            '뉴스레터',
            '뉴스레터',
            'manage_options',
            'dd-lms-newsletters',
            array( __CLASS__, 'page_newsletters' )
        );

        add_submenu_page(
            'dd-lms',
            '설정',
            '설정',
            'manage_options',
            'dd-lms-settings',
            array( __CLASS__, 'page_settings' )
        );
    }

    public static function admin_assets( $hook ) {
        if ( strpos( $hook, 'dd-lms' ) === false ) {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_style(
            'dd-admin-css',
            DD_LMS_URL . 'admin/css/dd-admin.css',
            array(),
            DD_LMS_VERSION
        );

        wp_enqueue_script(
            'dd-admin-js',
            DD_LMS_URL . 'admin/js/dd-admin.js',
            array( 'wp-api-fetch' ),
            DD_LMS_VERSION,
            true
        );

        wp_localize_script( 'dd-admin-js', 'ddLms', array(
            'restUrl' => rest_url( 'dingdong-lms/v1/' ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
            'siteUrl' => home_url(),
            // 데이터 복원 전용 nonce — REST 권한 확인에 더해 CSRF 를 한 번 더 막는다.
            'backupNonce' => wp_create_nonce( 'dd_backup' ),
        ) );
    }

    public static function page_dashboard() {
        require DD_LMS_PATH . 'admin/views/page-dashboard.php';
    }

    public static function page_generator() {
        require DD_LMS_PATH . 'admin/views/page-generator.php';
    }

    public static function page_stories() {
        require DD_LMS_PATH . 'admin/views/page-stories.php';
    }

    public static function page_song() {
        require DD_LMS_PATH . 'admin/views/page-song.php';
    }

    public static function page_newsletters() {
        require DD_LMS_PATH . 'admin/views/page-newsletters.php';
    }

    public static function page_settings() {
        require DD_LMS_PATH . 'admin/views/page-settings.php';
    }
}
