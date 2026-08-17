<?php
class DD_Setup {

    public static function activate() {
        self::create_landing_page();
        self::create_nav_menu();

        // Rewrite rules 즉시 등록 + flush
        DD_Public_Access::add_rewrite_rules();
        delete_option( 'dd_lms_rewrite_version' );
        flush_rewrite_rules();
    }

    private static function create_landing_page() {
        $page_id = get_option( 'dd_lms_landing_page_id' );
        if ( $page_id && get_post( $page_id ) ) {
            return;
        }

        $page_id = wp_insert_post( array(
            'post_title'   => 'DingDong 叮咚',
            'post_content' => '',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_name'    => 'dingdong',
        ) );

        if ( is_wp_error( $page_id ) ) {
            return;
        }

        update_option( 'dd_lms_landing_page_id', $page_id );

        // 프론트페이지는 활성화 시 1회만, 그것도 사이트가 아직 기본값(최신 글 목록)일 때만 가져온다.
        // 이미 고정 페이지를 홈으로 쓰는 사이트의 홈페이지를 빼앗지 않기 위함.
        // (관리자는 언제든 [설정 → 읽기]에서 바꿀 수 있고, 이후 되돌려지지 않는다.)
        if ( get_option( 'show_on_front' ) !== 'page' ) {
            update_option( 'show_on_front', 'page' );
            update_option( 'page_on_front', $page_id );
        }
    }

    private static function create_nav_menu() {
        $menu_name = 'DingDong LMS';
        if ( wp_get_nav_menu_object( $menu_name ) ) {
            return;
        }

        $menu_id = wp_create_nav_menu( $menu_name );
        if ( is_wp_error( $menu_id ) ) {
            return;
        }

        $items = array(
            array( 'title' => '홈',       'url' => home_url( '/' ),              'pos' => 1 ),
            array( 'title' => '강좌',     'url' => home_url( '/courses/' ),      'pos' => 2 ),
            array( 'title' => '뉴스레터', 'url' => home_url( '/newsletters/' ),  'pos' => 3 ),
            array( 'title' => '단어장',   'url' => home_url( '/vocabulary/' ),   'pos' => 4 ),
        );

        foreach ( $items as $item ) {
            wp_update_nav_menu_item( $menu_id, 0, array(
                'menu-item-title'    => $item['title'],
                'menu-item-url'      => $item['url'],
                'menu-item-type'     => 'custom',
                'menu-item-status'   => 'publish',
                'menu-item-position' => $item['pos'],
            ) );
        }

        $theme_locations = get_registered_nav_menus();
        if ( empty( $theme_locations ) ) {
            return;
        }

        $locations = get_theme_mod( 'nav_menu_locations', array() );
        $target    = null;

        foreach ( array( 'primary', 'main', 'header', 'navigation' ) as $loc ) {
            if ( isset( $theme_locations[ $loc ] ) ) {
                $target = $loc;
                break;
            }
        }

        if ( ! $target ) {
            $keys   = array_keys( $theme_locations );
            $target = $keys[0];
        }

        $locations[ $target ] = $menu_id;
        set_theme_mod( 'nav_menu_locations', $locations );
    }

    /* 제거 정리는 uninstall.php 가 담당한다 (WordPress 가 직접 실행).
       여기에 중복 구현을 두면 어느 쪽이 진짜인지 헷갈리므로 두지 않는다. */
}
