<?php
/**
 * 플러그인 삭제 시 정리.
 *
 * ⚠️ 기본 동작: 설정만 지우고 **학습 콘텐츠는 보존한다.**
 *
 * 예전에는 여기서 강좌·강의·스토리·뉴스레터를 무조건 완전 삭제했다.
 * 관리자가 플러그인을 재설치하려고 [삭제]를 누르는 순간, AI 로 생성하느라
 * 실제 API 요금을 지불한 콘텐츠가 경고 하나 없이 전부 사라지는 구조였다.
 * (게다가 휴지통이 아니라 wp_delete_post($id, true) — 복구 불가)
 *
 * 지금은 관리자가 [설정] 화면에서 "삭제 시 콘텐츠도 함께 제거"를 명시적으로
 * 켠 경우에만 콘텐츠를 지운다. 재설치·업그레이드는 이제 안전하다.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$dd_purge_content = (bool) get_option( 'dd_lms_purge_content_on_uninstall', false );

if ( $dd_purge_content ) {
    foreach ( array( 'dd_course', 'dd_lesson', 'dd_story', 'dd_newsletter' ) as $dd_type ) {
        $dd_ids = get_posts( array(
            'post_type'   => $dd_type,
            'numberposts' => -1,
            'post_status' => 'any',
            'fields'      => 'ids',
        ) );

        foreach ( $dd_ids as $dd_id ) {
            wp_delete_post( $dd_id, true );
        }
    }
}

// 랜딩페이지 — 플러그인이 만든 빈 페이지이므로 콘텐츠 보존 여부와 무관하게 정리한다.
$dd_landing_id = get_option( 'dd_lms_landing_page_id' );
if ( $dd_landing_id ) {
    if ( (int) get_option( 'page_on_front' ) === (int) $dd_landing_id ) {
        update_option( 'show_on_front', 'posts' );
        delete_option( 'page_on_front' );
    }
    wp_delete_post( $dd_landing_id, true );
}

$dd_menu = wp_get_nav_menu_object( 'DingDong LMS' );
if ( $dd_menu ) {
    wp_delete_nav_menu( $dd_menu->term_id );
}

// 설정·자격증명은 항상 제거한다.
delete_option( 'dd_lms_gemini_api_key' );
delete_option( 'dd_lms_landing_page_id' );
delete_option( 'dd_lms_gemini_model' );
delete_option( 'dd_lms_youtube_key' );
delete_option( 'dd_lms_pixabay_key' );
delete_option( 'dd_lms_version' );
delete_option( 'dd_lms_rewrite_version' );
delete_option( 'dd_gemini_last_call' );
delete_option( 'dd_lms_purge_content_on_uninstall' );

// 제거된 AI 학습송(SUNO) 기능이 남긴 옵션 — 구버전에서 업그레이드한 사이트 정리용
delete_option( 'dd_song_disclaimer' );
delete_option( 'dd_song_creator' );
delete_option( 'dd_suno_api_key' );
