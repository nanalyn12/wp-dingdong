<?php
class DD_Post_Types {

    public static function register() {
        register_post_type( 'dd_course', array(
            'labels' => array(
                'name'          => '강좌',
                'singular_name' => '강좌',
            ),
            'public'       => false,
            'show_ui'      => false,
            'supports'     => array( 'title', 'editor' ),
            'has_archive'  => false,
        ) );

        register_post_type( 'dd_lesson', array(
            'labels' => array(
                'name'          => '강의',
                'singular_name' => '강의',
            ),
            'public'       => false,
            'show_ui'      => false,
            'supports'     => array( 'title', 'editor' ),
            'has_archive'  => false,
        ) );

        register_post_type( 'dd_story', array(
            'labels' => array(
                'name'          => '인터랙티브 스토리',
                'singular_name' => '스토리',
            ),
            'public'       => false,
            'show_ui'      => false,
            'supports'     => array( 'title', 'editor' ),
            'has_archive'  => false,
        ) );

        register_post_type( 'dd_newsletter', array(
            'labels' => array(
                'name'          => '뉴스레터',
                'singular_name' => '뉴스레터',
            ),
            'public'       => false,
            'show_ui'      => false,
            'supports'     => array( 'title' ),
            'has_archive'  => false,
        ) );
    }
}
