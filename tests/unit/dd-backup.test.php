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

/* =============================================================
   8) C-1 — 중단된 복원을 이어받는다 (부분 복원 영구 잔존 방지)
   ============================================================= */

test( '★ 백업에 없던 콘텐츠는 새로 만든다', function () {
    assert_same( 'insert', DD_Backup::decide_action( 0, false, 'skip' ) );
} );

test( '★ 이미 온전히 있는 콘텐츠는 건너뛴다', function () {
    assert_same( 'skip', DD_Backup::decide_action( 42, false, 'skip' ) );
} );

test( '★ 복원이 중간에 끊긴 콘텐츠는 건너뛰지 않고 이어받는다', function () {
    // 예전 동작: skip → 껍데기만 남은 강의가 영구히 고쳐지지 않았다.
    assert_same( 'resume', DD_Backup::decide_action( 42, true, 'skip' ) );
    assert_same( 'resume', DD_Backup::decide_action( 42, true, 'replace' ) );
} );

test( '덮어쓰기 모드는 온전한 콘텐츠도 교체한다', function () {
    assert_same( 'replace', DD_Backup::decide_action( 42, false, 'replace' ) );
} );

test( '항상 새로 추가 모드는 기존 콘텐츠를 보지 않는다', function () {
    assert_same( 'insert', DD_Backup::decide_action( 42, false, 'duplicate' ) );
    assert_same( 'insert', DD_Backup::decide_action( 42, true, 'duplicate' ) );
} );

test( '미완료 표식은 백업 파일에 담기지 않는다', function () {
    $meta = DD_Backup::filter_meta( array(
        DD_Backup::UID_META        => 'uid-1',
        DD_Backup::INCOMPLETE_META => '1',
        '_dd_slides_data'          => '[]',
    ) );
    assert_false( array_key_exists( DD_Backup::UID_META, $meta ) );
    assert_false( array_key_exists( DD_Backup::INCOMPLETE_META, $meta ), '내부 표식이 백업에 새면 안 됨' );
    assert_same( '[]', $meta['_dd_slides_data'] );
} );

/* =============================================================
   9) H-1 — 압축 폭탄 방어
   ============================================================= */

test( '정상 크기의 이미지는 통과한다', function () {
    assert_same( '', DD_Backup::archive_entry_rejection( 1500000, 1400000, 0 ) );
} );

test( '★ 항목 하나가 상한을 넘으면 거부한다', function () {
    $over = DD_Backup::MAX_MEDIA_FILE_BYTES + 1;
    assert_same( 'too_large', DD_Backup::archive_entry_rejection( $over, 1000, 0 ) );
} );

test( '★ 누적 해제 용량이 상한을 넘으면 거부한다', function () {
    $reason = DD_Backup::archive_entry_rejection( 1048576, 1000000, DD_Backup::MAX_EXTRACT_BYTES );
    assert_same( 'quota', $reason );
} );

test( '★ 압축 폭탄(비정상 압축비)을 거부한다', function () {
    // 1KB → 100MB 로 풀리는 전형적 zip bomb. (크기 상한에서 먼저 걸린다)
    assert_not_empty( DD_Backup::archive_entry_rejection( 104857600, 1024, 0 ) );

    // 크기 상한은 통과하지만 압축비만으로 잡아야 하는 경우 (1KB → 10MB).
    assert_same( 'ratio', DD_Backup::archive_entry_rejection( 10485760, 1024, 0 ) );
} );

test( '작은 파일의 높은 압축비는 오탐하지 않는다', function () {
    // 단색 PNG 등은 정상적으로도 압축비가 높다 — 큰 파일에만 비율을 적용해야 한다
    assert_same( '', DD_Backup::archive_entry_rejection( 300000, 500, 0 ) );
} );

/* =============================================================
   10) H-4 — 업로드 필드 검증 (배열 주입으로 500 나지 않게)
   ============================================================= */

test( '정상 업로드는 확장자를 돌려준다', function () {
    $ok = DD_Backup::inspect_upload( array(
        'name' => 'dingdong-lms-backup-2026-08-17.json', 'tmp_name' => '/tmp/php123',
        'size' => 1000, 'error' => 0,
    ) );
    assert_false( is_wp_error( $ok ) );
    assert_same( 'json', $ok['ext'] );

    $zip = DD_Backup::inspect_upload( array(
        'name' => 'backup.zip', 'tmp_name' => '/tmp/php124', 'size' => 999999999, 'error' => 0,
    ) );
    assert_same( 'zip', $zip['ext'], 'ZIP 은 JSON 용량 상한을 적용하지 않는다' );
} );

test( '★ tmp_name 이 배열이면 500 대신 오류를 돌려준다', function () {
    $res = DD_Backup::inspect_upload( array(
        'name' => 'a.json', 'tmp_name' => array( '/tmp/a', '/tmp/b' ), 'size' => 10, 'error' => 0,
    ) );
    assert_true( is_wp_error( $res ) );
    assert_same( 'dd_backup_upload_error', $res->get_error_code() );
} );

test( '★ name 이 배열이면 오류를 돌려준다', function () {
    $res = DD_Backup::inspect_upload( array(
        'name' => array( 'a.json' ), 'tmp_name' => '/tmp/a', 'size' => 10, 'error' => 0,
    ) );
    assert_true( is_wp_error( $res ) );
} );

test( '업로드 오류 코드가 있으면 거부한다', function () {
    $res = DD_Backup::inspect_upload( array( 'name' => 'a.json', 'tmp_name' => '/tmp/a', 'size' => 0, 'error' => 1 ) );
    assert_true( is_wp_error( $res ) );
} );

test( '허용하지 않는 확장자를 거부한다', function () {
    foreach ( array( 'a.php', 'a.sql', 'a.json.php', 'a' ) as $name ) {
        $res = DD_Backup::inspect_upload( array( 'name' => $name, 'tmp_name' => '/tmp/a', 'size' => 10, 'error' => 0 ) );
        assert_true( is_wp_error( $res ), $name . ' 이 통과됨' );
    }
} );

test( 'JSON 은 용량 상한을 넘으면 거부한다', function () {
    $res = DD_Backup::inspect_upload( array(
        'name' => 'a.json', 'tmp_name' => '/tmp/a', 'size' => DD_Backup::MAX_BYTES + 1, 'error' => 0,
    ) );
    assert_true( is_wp_error( $res ) );
    assert_same( 'dd_backup_too_large', $res->get_error_code() );
} );

/* =============================================================
   11) B-2 — 깨진 UTF-8 때문에 백업 전체가 실패하지 않게
   ============================================================= */

test( '정상 한글·중국어는 그대로 보존된다', function () {
    $in  = array( 'ko' => '안녕하세요', 'zh' => '你好', 'n' => 3, 'b' => true, 'nil' => null );
    $out = DD_Backup::scrub_utf8( $in );
    assert_same( '안녕하세요', $out['ko'] );
    assert_same( '你好', $out['zh'] );
    assert_same( 3, $out['n'] );
    assert_same( true, $out['b'] );
} );

test( '★ 깨진 UTF-8 이 섞여 있어도 JSON 인코딩에 성공한다', function () {
    // "\xB0\xA1" 은 EUC-KR '가' — UTF-8 로는 잘못된 바이트열이다.
    $dirty = array( 'title' => "정상 텍스트 \xB0\xA1 뒤쪽", 'nested' => array( "\xFF\xFE" ) );

    assert_false( json_encode( $dirty ) !== false, '전제 확인: 원본은 인코딩에 실패해야 함' );

    $clean = DD_Backup::scrub_utf8( $dirty );
    assert_not_same( false, json_encode( $clean ), '세척 후에는 인코딩에 성공해야 함' );
    // ⚠️ 한글이 통째로 사라지면 안 된다 — iconv 에 의존하던 구현이 런타임에 따라
    //    한글을 전부 날리는 것을 실사이트에서 확인했다. 이 단언이 그 회귀를 막는다.
    assert_contains( '정상 텍스트', $clean['title'], '멀쩡한 부분은 살아남아야 함' );
    assert_contains( '뒤쪽', $clean['title'] );
    assert_same( '', $clean['nested'][0], '복구 불가능한 바이트만 있으면 빈 문자열' );
} );

test( '배열 키에 깨진 바이트가 있어도 처리한다', function () {
    $clean = DD_Backup::scrub_utf8( array( "\xB0\xA1key" => '값' ) );
    assert_not_same( false, json_encode( $clean ) );
} );

/* =============================================================
   12) M-2 — .htaccess 가 통하지 않는 서버를 알아본다
   ============================================================= */

test( 'Apache 계열에서는 .htaccess 보호가 유효하다', function () {
    assert_true( DD_Backup::htaccess_effective( 'Apache/2.4.57 (Unix)' ) );
    assert_true( DD_Backup::htaccess_effective( 'LiteSpeed' ) );
    assert_true( DD_Backup::htaccess_effective( 'Apache' ) );
} );

test( '★ nginx 에서는 .htaccess 가 무시되므로 보호되지 않는다고 판단한다', function () {
    assert_false( DD_Backup::htaccess_effective( 'nginx/1.24.0' ) );
    assert_false( DD_Backup::htaccess_effective( 'Microsoft-IIS/10.0' ) );
    assert_false( DD_Backup::htaccess_effective( '' ), '알 수 없는 서버는 안전한 쪽으로 가정한다' );
} );

/* =============================================================
   13) M-3 — 최종 경로가 허용 폴더 안인지 확인 (심층 방어)
   ============================================================= */

test( '허용 폴더 안의 경로는 통과한다', function () {
    assert_true( DD_Backup::is_inside( '/var/www/uploads/dingdong-lms/a.png', '/var/www/uploads/dingdong-lms' ) );
    assert_true( DD_Backup::is_inside( '/var/www/uploads/dingdong-lms/sub/a.png', '/var/www/uploads/dingdong-lms/' ) );
} );

test( '★ 허용 폴더 밖의 경로를 거부한다', function () {
    assert_false( DD_Backup::is_inside( '/var/www/wp-config.php', '/var/www/uploads/dingdong-lms' ) );
    assert_false( DD_Backup::is_inside( '/etc/passwd', '/var/www/uploads/dingdong-lms' ) );
} );

test( '★ 이름만 비슷한 옆 폴더를 안쪽으로 착각하지 않는다', function () {
    // 단순 문자열 prefix 비교였다면 통과해 버리는 고전적 실수
    assert_false( DD_Backup::is_inside( '/var/www/uploads/dingdong-lms-evil/a.png', '/var/www/uploads/dingdong-lms' ) );
} );

test( 'Windows 역슬래시 경로도 같은 기준으로 판단한다', function () {
    assert_true( DD_Backup::is_inside( 'C:\site\uploads\dingdong-lms\a.png', 'C:\site\uploads\dingdong-lms' ) );
    assert_false( DD_Backup::is_inside( 'C:\site\wp-config.php', 'C:\site\uploads\dingdong-lms' ) );
} );

test( '빈 경로는 거부한다', function () {
    assert_false( DD_Backup::is_inside( '', '/var/www/uploads' ) );
    assert_false( DD_Backup::is_inside( '/var/www/uploads/a.png', '' ) );
} );

/* =============================================================
   14) 치명적 오류를 사람이 읽을 수 있는 안내로 바꾼다
   ============================================================= */

test( '★ 실행 시간 초과를 알아보고 원인을 짚어 준다', function () {
    $res = DD_Backup::explain_fatal( array(
        'type'    => E_ERROR,
        'message' => 'Maximum execution time of 30 seconds exceeded',
        'file'    => '/x/class-dd-backup.php',
        'line'    => 100,
    ) );

    assert_same( 'timeout', $res['reason'] );
    assert_contains( '시간', $res['message'] );
    assert_contains( '다시', $res['message'], '재시도하면 이어받는다는 안내가 있어야 함' );
} );

test( '★ 메모리 부족을 알아본다', function () {
    $res = DD_Backup::explain_fatal( array(
        'type'    => E_ERROR,
        'message' => 'Allowed memory size of 268435456 bytes exhausted (tried to allocate 20480 bytes)',
        'file'    => '/x/class-dd-backup.php',
        'line'    => 100,
    ) );

    assert_same( 'memory', $res['reason'] );
    assert_contains( '메모리', $res['message'] );
} );

test( '그 밖의 치명적 오류도 안내 문구를 만든다', function () {
    $res = DD_Backup::explain_fatal( array(
        'type'    => E_ERROR,
        'message' => 'Call to undefined function foo()',
        'file'    => '/x/class-dd-backup.php',
        'line'    => 42,
    ) );

    assert_same( 'other', $res['reason'] );
    assert_not_empty( $res['message'] );
} );

test( '치명적이지 않은 오류(경고)는 무시한다', function () {
    assert_false( DD_Backup::explain_fatal( array( 'type' => E_WARNING, 'message' => 'x', 'file' => '', 'line' => 0 ) ) );
    assert_false( DD_Backup::explain_fatal( array( 'type' => E_NOTICE, 'message' => 'x', 'file' => '', 'line' => 0 ) ) );
    assert_false( DD_Backup::explain_fatal( null ) );
} );

/* =============================================================
   15) 타임아웃 대응 — 서버 제한 안에서 끊어서 처리한다
   ============================================================= */

test( '★ 서버 실행 시간 제한보다 넉넉히 앞서 멈추도록 예산을 잡는다', function () {
    // 30초 제한이면 그 안에서 여유를 두고 끝내야 500 이 아니라 "이어서 진행"이 된다.
    $budget = DD_Backup::time_budget( 30 );
    assert_true( $budget > 0 && $budget < 30, '예산 ' . $budget . '초' );
    assert_true( $budget <= 20, '제한의 절반 이하로 잡아야 안전 — 실제 ' . $budget );
} );

test( '실행 시간 제한이 넉넉하면 예산도 늘지만 상한이 있다', function () {
    assert_true( DD_Backup::time_budget( 300 ) >= DD_Backup::time_budget( 30 ) );
    assert_true( DD_Backup::time_budget( 300 ) <= DD_Backup::MAX_TIME_BUDGET );
} );

test( '★ 제한이 0(무제한)이어도 무한정 잡지 않는다', function () {
    $budget = DD_Backup::time_budget( 0 );
    assert_true( $budget > 0 && $budget <= DD_Backup::MAX_TIME_BUDGET, '예산 ' . $budget . '초' );
} );

test( '제한이 아주 짧아도 최소 한 건은 처리할 예산을 준다', function () {
    assert_true( DD_Backup::time_budget( 5 ) >= 2, '예산 ' . DD_Backup::time_budget( 5 ) . '초' );
    assert_true( DD_Backup::time_budget( 1 ) >= 2 );
} );

test( '이상한 값이 들어와도 안전한 기본값을 쓴다', function () {
    assert_true( DD_Backup::time_budget( -10 ) > 0 );
    assert_true( DD_Backup::time_budget( 'abc' ) > 0 );
} );

/* =============================================================
   16) 진단 로그 보기 — 관리자 화면에서 원인을 확인한다
   ============================================================= */

test( '로그 꼬리를 원하는 줄 수만큼 잘라 온다', function () {
    $text = "1번줄\n2번줄\n3번줄\n4번줄\n5번줄\n";
    assert_same( "4번줄\n5번줄", DD_Backup::tail_lines( $text, 2 ) );
} );

test( '요청한 줄 수보다 짧으면 전부 돌려준다', function () {
    assert_same( "가\n나", DD_Backup::tail_lines( "가\n나", 10 ) );
} );

test( '빈 로그는 빈 문자열', function () {
    assert_same( '', DD_Backup::tail_lines( '', 10 ) );
    assert_same( '', DD_Backup::tail_lines( "\n\n", 10 ) );
} );

test( '줄 끝 공백과 빈 줄을 정리한다', function () {
    assert_same( "가\n나", DD_Backup::tail_lines( "가\n\n나\n\n", 10 ) );
} );

test( '★ 진단 정보에는 자격증명이 섞이지 않는다', function () {
    // 로그에 실수로 키가 남았더라도 화면에 그대로 뿌리지 않는다.
    $dirty = "[BACKUP] 복원 시작\n[BACKUP] key=AIzaSyD-1234567890abcdefghijklmnopqrstuv\n[BACKUP] 완료";
    $clean = DD_Backup::redact( $dirty );

    assert_not_contains( 'AIzaSyD-1234567890abcdefghijklmnopqrstuv', $clean );
    assert_contains( '복원 시작', $clean );
    assert_contains( '완료', $clean );
} );

test( '일반 문장은 그대로 둔다', function () {
    $text  = '[BACKUP] 복원 완료 — 생성 4 / 건너뜀 40';
    assert_same( $text, DD_Backup::redact( $text ) );
} );

/* =============================================================
   17) 이미지 묶음 나눠 받기 — FTP 없이 이미지를 옮긴다
   ============================================================= */

test( '조각 크기 안에서 파일을 순서대로 묶는다', function () {
    $files = array(
        array( 'path' => 'a.png', 'bytes' => 4 ),
        array( 'path' => 'b.png', 'bytes' => 4 ),
        array( 'path' => 'c.png', 'bytes' => 4 ),
    );
    $parts = DD_Backup::pack_into_parts( $files, 10 );

    assert_same( 2, count( $parts ) );
    assert_same( array( 'a.png', 'b.png' ), $parts[0]['files'] );
    assert_same( array( 'c.png' ), $parts[1]['files'] );
    assert_same( 8, $parts[0]['bytes'] );
} );

test( '★ 조각 크기보다 큰 파일도 버리지 않고 혼자 한 묶음이 된다', function () {
    // 이미지 1장이 상한보다 크다고 백업에서 빠지면 안 된다.
    $files = array(
        array( 'path' => 'small.png', 'bytes' => 2 ),
        array( 'path' => 'huge.png',  'bytes' => 50 ),
        array( 'path' => 'tail.png',  'bytes' => 2 ),
    );
    $parts = DD_Backup::pack_into_parts( $files, 10 );

    $all = array();
    foreach ( $parts as $p ) { $all = array_merge( $all, $p['files'] ); }
    assert_same( array( 'small.png', 'huge.png', 'tail.png' ), $all, '어떤 파일도 빠지면 안 됨' );
    assert_true( in_array( 'huge.png', $parts[1]['files'], true ) );
    assert_same( 1, count( $parts[1]['files'] ), '큰 파일은 혼자 담긴다' );
} );

test( '파일이 없으면 묶음도 없다', function () {
    assert_same( array(), DD_Backup::pack_into_parts( array(), 10 ) );
} );

test( '조각 크기가 이상하면 안전한 최소값을 쓴다', function () {
    $files = array( array( 'path' => 'a.png', 'bytes' => 1 ) );
    assert_same( 1, count( DD_Backup::pack_into_parts( $files, 0 ) ) );
    assert_same( 1, count( DD_Backup::pack_into_parts( $files, -5 ) ) );
} );

test( '★ 이미지 묶음도 정상 백업 파일로 인정받는다', function () {
    // 묶음 ZIP 안의 backup.json 은 콘텐츠가 비어 있지만 검증은 통과해야 한다.
    // (통과하지 못하면 복원 화면이 "백업 파일이 아니다"라며 거부한다)
    $doc = DD_Backup::media_part_document( 3, 19 );

    assert_same( true, DD_Backup::validate( $doc ) );
    assert_same( array(), $doc['posts'] );
    assert_same( 3, $doc['media_part']['index'] );
    assert_same( 19, $doc['media_part']['total'] );
} );

test( '★ 플러그인 버전이 "0" 이어도 정상 백업으로 인정한다 (empty 함정)', function () {
    // PHP 의 empty('0') 은 true 다. 이걸 그대로 쓰면 멀쩡한 백업이 거부된다.
    $data                   = dd_backup_fixture();
    $data['plugin_version'] = '0';
    assert_same( true, DD_Backup::validate( $data ) );
} );

test( '생성 정보가 정말로 비어 있으면 거부한다', function () {
    $data                   = dd_backup_fixture();
    $data['plugin_version'] = '';
    $res                    = DD_Backup::validate( $data );
    assert_true( is_wp_error( $res ) );
    assert_same( 'dd_backup_malformed', $res->get_error_code() );
} );
