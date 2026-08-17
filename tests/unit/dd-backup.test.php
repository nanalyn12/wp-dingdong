<?php
/**
 * DD_Backup — 데이터 백업 / 복원의 순수 로직 검증.
 *
 * 여기서 검증하는 것은 "WordPress 없이 판정할 수 있는 규칙"뿐이다.
 *   - 백업 파일 검증 (무엇을 거부하는가)
 *   - 자격증명·공개토큰이 백업에 새지 않는가
 *   - 복원 시 포스트 ID 참조가 새 ID 로 다시 이어지는가
 *   - 업로드 URL 이 대상 사이트 기준으로 바뀌는가
 *   - 파일명 규칙
 *
 * 실제 DB 왕복(wp_insert_post / update_post_meta / wp_set_object_terms)은
 * 이 계층에서 검증할 수 없으므로 Studio 사이트에서 수동 확인한다.
 */

require_once DD_PLUGIN_DIR . '/includes/class-dd-backup.php';

/* =============================================================
   1) 백업 파일 검증 — 무엇을 거부해야 하는가
   ============================================================= */

test( '올바른 백업 파일은 검증을 통과한다', function () {
    $ok = DD_Backup::validate( dd_backup_fixture() );
    assert_true( $ok === true, '정상 백업이 거부됨: ' . ( is_object( $ok ) ? $ok->get_error_message() : '' ) );
} );

test( 'JSON 이 아닌 파일은 거부한다', function () {
    $res = DD_Backup::decode( '이건 JSON 이 아닙니다' );
    assert_true( is_wp_error( $res ) );
    assert_same( 'dd_backup_invalid_json', $res->get_error_code() );
} );

test( 'JSON 이지만 배열이 아니면 거부한다', function () {
    $res = DD_Backup::decode( '"문자열"' );
    assert_true( is_wp_error( $res ) );
    assert_same( 'dd_backup_invalid_json', $res->get_error_code() );
} );

test( '다른 플러그인의 백업 파일은 거부한다', function () {
    $data           = dd_backup_fixture();
    $data['format'] = 'some-other-plugin-backup';
    $res            = DD_Backup::validate( $data );
    assert_true( is_wp_error( $res ) );
    assert_same( 'dd_backup_not_ours', $res->get_error_code() );
} );

test( '지원하지 않는 포맷 버전은 거부한다', function () {
    $data                   = dd_backup_fixture();
    $data['format_version'] = DD_Backup::FORMAT_VERSION + 1;
    $res                    = DD_Backup::validate( $data );
    assert_true( is_wp_error( $res ) );
    assert_same( 'dd_backup_unsupported_version', $res->get_error_code() );
} );

test( '필수 필드가 빠지면 거부한다', function () {
    $data = dd_backup_fixture();
    unset( $data['posts'] );
    $res = DD_Backup::validate( $data );
    assert_true( is_wp_error( $res ) );
    assert_same( 'dd_backup_malformed', $res->get_error_code() );
} );

test( '용량 상한을 넘는 업로드는 파싱 전에 거부한다', function () {
    $res = DD_Backup::decode( str_repeat( 'x', DD_Backup::MAX_BYTES + 1 ) );
    assert_true( is_wp_error( $res ) );
    assert_same( 'dd_backup_too_large', $res->get_error_code() );
} );

test( '플러그인 소유가 아닌 post_type 은 복원 대상에서 제외한다', function () {
    assert_false( DD_Backup::is_supported_post_type( 'post' ) );
    assert_false( DD_Backup::is_supported_post_type( 'attachment' ) );
    assert_true( DD_Backup::is_supported_post_type( 'dd_lesson' ) );
} );

/* =============================================================
   2) 민감정보는 백업에 절대 들어가지 않는다
   ============================================================= */

test( 'API 키 옵션은 백업 허용목록에 없다', function () {
    foreach ( DD_Backup::SECRET_OPTION_KEYS as $secret ) {
        assert_false(
            in_array( $secret, DD_Backup::OPTION_KEYS, true ),
            $secret . ' 가 백업 대상 옵션에 포함되어 있음'
        );
    }
} );

test( '백업 옵션 필터는 자격증명을 걸러낸다', function () {
    $filtered = DD_Backup::filter_options( array(
        'dd_lms_gemini_model'   => 'gemini-3.5-flash',
        'dd_lms_gemini_api_key' => 'AIza-비밀키',
        'dd_lms_youtube_key'    => '비밀키',
        'dd_lms_pixabay_key'    => '비밀키',
        'some_other_option'     => '무관한 값',
    ) );

    assert_same( 'gemini-3.5-flash', $filtered['dd_lms_gemini_model'] );
    assert_false( array_key_exists( 'dd_lms_gemini_api_key', $filtered ) );
    assert_false( array_key_exists( 'dd_lms_youtube_key', $filtered ) );
    assert_false( array_key_exists( 'dd_lms_pixabay_key', $filtered ) );
    assert_false( array_key_exists( 'some_other_option', $filtered ), '허용목록 밖 옵션까지 백업하면 안 됨' );
} );

test( '공개 공유 토큰은 백업 메타에서 제거된다', function () {
    $meta = DD_Backup::filter_meta( array(
        '_dd_public_token'            => 'a1b2c3-비밀토큰',
        '_dd_story_public_token'      => 'd4e5f6-비밀토큰',
        '_dd_newsletter_public_token' => 'g7h8i9-비밀토큰',
        '_dd_public_active'           => '1',
        '_dd_slides_data'             => '[]',
        'other_plugin_meta'           => '남의 데이터',
        '_edit_lock'                  => '코어 내부 메타',
    ) );

    assert_false( array_key_exists( '_dd_public_token', $meta ) );
    assert_false( array_key_exists( '_dd_story_public_token', $meta ) );
    assert_false( array_key_exists( '_dd_newsletter_public_token', $meta ) );
    assert_same( '1', $meta['_dd_public_active'], '공개 여부 자체는 보존되어야 함' );
    assert_same( '[]', $meta['_dd_slides_data'] );
    assert_false( array_key_exists( 'other_plugin_meta', $meta ), '_dd_ 접두사가 아닌 메타는 백업하지 않음' );
    assert_false( array_key_exists( '_edit_lock', $meta ) );
} );

/* =============================================================
   3) ID 재매핑 — 복원의 핵심
   ============================================================= */

test( '강의의 _dd_course_id 는 새로 만들어진 강좌 ID 로 재매핑된다', function () {
    $meta = array(
        '_dd_course_id'   => '12',   // 백업 원본 사이트의 강좌 ID
        '_dd_lesson_order' => '1',
    );

    $remapped = DD_Backup::remap_refs(
        $meta,
        array( 'uid-course-1' => 4001 ),          // uid → 새 ID
        array( 12 => 4001 ),                      // 원본 ID → 새 ID
        array( '_dd_course_id' => 'uid-course-1' ) // 백업에 기록된 참조 uid
    );

    assert_same( '4001', $remapped['_dd_course_id'] );
    assert_same( '1', $remapped['_dd_lesson_order'], '참조가 아닌 메타는 그대로' );
} );

test( '참조 uid 가 없어도 원본 ID 매핑으로 복구한다', function () {
    $remapped = DD_Backup::remap_refs(
        array( '_dd_story_course_id' => '12' ),
        array(),
        array( 12 => 4001 ),
        array()
    );
    assert_same( '4001', $remapped['_dd_story_course_id'] );
} );

test( '대상 강좌가 백업에 없으면 깨진 ID 를 남기지 않고 비운다', function () {
    $remapped = DD_Backup::remap_refs(
        array( '_dd_course_id' => '999' ),
        array(),
        array(),
        array()
    );
    assert_same( '', $remapped['_dd_course_id'], '기존 사이트의 남의 포스트를 가리키면 안 됨' );
} );

/* =============================================================
   4) 사이트 이동 — 업로드 URL 재작성
   ============================================================= */

test( '업로드 URL 은 복원 사이트 기준으로 바뀐다', function () {
    $meta = array(
        '_dd_key_expr_image' => 'http://old.test/wp-content/uploads/dingdong-lms/a.png',
        '_dd_comic_images'   => '["http://old.test/wp-content/uploads/dingdong-lms/b.png"]',
        '_dd_slides_data'    => '{"t":"관계없는 텍스트"}',
    );

    $out = DD_Backup::rewrite_uploads_url(
        $meta,
        'http://old.test/wp-content/uploads',
        'http://new.test/wp-content/uploads'
    );

    assert_same( 'http://new.test/wp-content/uploads/dingdong-lms/a.png', $out['_dd_key_expr_image'] );
    assert_contains( 'http://new.test/wp-content/uploads/dingdong-lms/b.png', $out['_dd_comic_images'] );
    assert_same( '{"t":"관계없는 텍스트"}', $out['_dd_slides_data'] );
} );

test( '같은 사이트로 복원하면 URL 을 건드리지 않는다', function () {
    $meta = array( '_dd_key_expr_image' => 'http://same.test/wp-content/uploads/x.png' );
    $out  = DD_Backup::rewrite_uploads_url( $meta, 'http://same.test/wp-content/uploads', 'http://same.test/wp-content/uploads' );
    assert_same( 'http://same.test/wp-content/uploads/x.png', $out['_dd_key_expr_image'] );
} );

/* =============================================================
   5) 복원 입력 새니타이즈 — 악의적 백업 파일 방어
   ============================================================= */

test( '메타 JSON 안의 스크립트 태그는 구조를 유지한 채 제거된다', function () {
    $dirty = wp_json_encode( array(
        array( 'zh' => '你好<script>alert(1)</script>', 'ko' => '안녕' ),
    ) );

    $clean = DD_Backup::sanitize_meta_value( '_dd_dialogues_data', $dirty );
    $rows  = json_decode( $clean, true );

    assert_true( is_array( $rows ), 'JSON 구조가 깨지면 안 됨' );
    assert_not_contains( '<script', $rows[0]['zh'] );
    assert_contains( '你好', $rows[0]['zh'], '중국어 본문은 보존되어야 함' );
    assert_same( '안녕', $rows[0]['ko'] );
} );

test( '일반 문자열 메타의 스크립트 태그도 제거된다', function () {
    $clean = DD_Backup::sanitize_meta_value( '_dd_cultural_note', '문화<script>alert(1)</script>노트' );
    assert_not_contains( '<script', $clean );
    assert_contains( '문화', $clean );
} );

/* =============================================================
   6) 파일명 규칙
   ============================================================= */

test( '백업 파일명은 plugin-backup-YYYY-MM-DD-HH-mm-ss.json 형식이다', function () {
    $name = DD_Backup::filename( 1755400496 ); // 2025-08-17 UTC
    assert_matches( '/^dingdong-lms-backup-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}\.json$/', $name );
} );

/* =============================================================
   7) ZIP 아카이브 — zip slip 방어가 핵심
   ============================================================= */

test( 'ZIP 안의 이미지 경로는 uploads/ 아래로만 풀린다', function () {
    assert_same( 'comic-46-1.png', DD_Backup::safe_archive_path( 'uploads/comic-46-1.png' ) );
    assert_same( 'thumbnails/course-12.jpg', DD_Backup::safe_archive_path( 'uploads/thumbnails/course-12.jpg' ) );
} );

test( '★ 상위 디렉터리 탈출(zip slip) 시도를 거부한다', function () {
    $attacks = array(
        'uploads/../../wp-config.php',
        'uploads/../../../wp-config.php',
        '../evil.png',
        '../../wp-content/plugins/evil.php',
        '/etc/passwd',
        'C:/Windows/system32/evil.png',
        'uploads/sub/../../../evil.png',
    );
    foreach ( $attacks as $path ) {
        assert_same( '', DD_Backup::safe_archive_path( $path ), '탈출 경로가 통과됨: ' . $path );
    }
} );

test( '★ ZIP 안의 실행 가능한 파일은 풀지 않는다', function () {
    $bad = array(
        'uploads/shell.php',
        'uploads/shell.PHP',
        'uploads/shell.phtml',
        'uploads/a.png.php',
        'uploads/script.js',
        'uploads/page.html',
        'uploads/vector.svg', // SVG 는 스크립트를 품을 수 있어 제외한다
    );
    foreach ( $bad as $path ) {
        assert_same( '', DD_Backup::safe_archive_path( $path ), '실행 가능한 파일이 통과됨: ' . $path );
    }
} );

test( '널바이트·역슬래시가 섞인 경로를 거부한다', function () {
    assert_same( '', DD_Backup::safe_archive_path( "uploads/a.png\0.php" ) );
    assert_same( '', DD_Backup::safe_archive_path( 'uploads\\..\\evil.png' ) );
} );

test( 'uploads/ 밖의 항목은 이미지로 취급하지 않는다', function () {
    assert_same( '', DD_Backup::safe_archive_path( 'backup.json' ) );
    assert_same( '', DD_Backup::safe_archive_path( 'manifest.json' ) );
    assert_same( '', DD_Backup::safe_archive_path( 'uploads/' ) );
} );

test( '백업 폴더와 보호 파일은 아카이브에 담지 않는다', function () {
    assert_true( DD_Backup::should_archive_file( 'comic-46-1.png' ) );
    assert_true( DD_Backup::should_archive_file( 'thumbnails/course-12.jpg' ) );

    // 백업 안에 백업을 다시 넣지 않는다
    assert_false( DD_Backup::should_archive_file( 'backups/dingdong-lms-autobackup-x.json' ) );
    assert_false( DD_Backup::should_archive_file( 'index.php' ) );
    assert_false( DD_Backup::should_archive_file( '.htaccess' ) );
    assert_false( DD_Backup::should_archive_file( 'debug.log' ) );
    assert_false( DD_Backup::should_archive_file( 'notes.txt' ) );
} );

test( 'ZIP 백업 파일명은 .zip 확장자를 쓰고 JSON 과 같은 시각 규칙을 따른다', function () {
    $name = DD_Backup::archive_filename( 1755400496 );
    assert_matches( '/^dingdong-lms-backup-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}\.zip$/', $name );
} );

/* =============================================================
   픽스처
   ============================================================= */

function dd_backup_fixture() {
    return array(
        'format'         => DD_Backup::FORMAT,
        'format_version' => DD_Backup::FORMAT_VERSION,
        'plugin'         => 'dingdong-lms',
        'plugin_version' => '2.4.0',
        'wp_version'     => '6.8',
        'generated_at'   => '2026-08-17T00:00:00+00:00',
        'site'           => array( 'home_url' => 'http://old.test', 'uploads_url' => 'http://old.test/wp-content/uploads' ),
        'options'        => array( 'dd_lms_gemini_model' => '' ),
        'posts'          => array(),
        'terms'          => array(),
        'tables'         => array(),
    );
}
