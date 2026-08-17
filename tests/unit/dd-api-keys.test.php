<?php
/**
 * API 키 게터 — 오버라이드 체인 + 기존 Options 경로 회귀 방지 테스트.
 *
 * ⚠️ 테스트 순서 주의:
 *    PHP 는 한번 define() 한 상수를 해제할 수 없다. 따라서 이 파일에서는
 *    반드시 "옵션 폴백 경로"를 먼저 검증한 뒤, 그 다음에 상수를 정의해
 *    "오버라이드 경로"를 검증한다. 러너는 등록 순서대로 실행하므로 안전하다.
 *    (순서를 뒤집으면 폴백 테스트가 오염되어 거짓 통과한다.)
 */

require_once DD_PLUGIN_DIR . '/includes/class-dd-env.php';
require_once DD_PLUGIN_DIR . '/includes/class-dd-api-key.php';
require_once DD_PLUGIN_DIR . '/includes/class-dd-youtube-search.php';
require_once DD_PLUGIN_DIR . '/includes/class-dd-thumbnail.php';

/* =============================================================
   1단계 — 기존 동작(Options API) 회귀 방지
   배포 사이트에서 관리자 화면으로 등록한 키가 계속 동작해야 한다.
   ============================================================= */

test( 'DD_API_Key — 저장한 Gemini 키를 그대로 복호화해 반환한다 (기존 동작 유지)', function () {
	require_that( extension_loaded( 'openssl' ), 'openssl 확장 필요 (이 PHP CLI 빌드에는 없음 — Playground 런타임에는 존재)' );
	DD_API_Key::save( 'AIzaSy-gemini-original' );
	assert_same( 'AIzaSy-gemini-original', DD_API_Key::get() );
} );

test( 'DD_API_Key — 저장된 키는 평문으로 보관되지 않는다', function () {
	require_that( extension_loaded( 'openssl' ), 'openssl 확장 필요' );
	DD_API_Key::save( 'AIzaSy-gemini-original' );
	$raw = get_option( 'dd_lms_gemini_api_key', '' );
	assert_not_empty( $raw );
	assert_not_contains( 'AIzaSy-gemini-original', $raw, '옵션에 평문 키가 노출되면 안 됨' );
} );

test( 'DD_API_Key — 키가 없으면 빈 문자열을 반환한다', function () {
	assert_same( '', DD_API_Key::get() );
	assert_false( DD_API_Key::has_key() );
} );

test( 'DD_API_Key — 옵션이 손상된 값이어도 예외 없이 빈 문자열을 반환한다', function () {
	// base64 도 아니고 '::' 구분자도 없는 쓰레기 값 → 복호화 시도 전에 방어되어야 한다.
	update_option( 'dd_lms_gemini_api_key', 'not-a-valid-payload' );
	assert_same( '', DD_API_Key::get() );
} );

test( 'DD_API_Key — delete 후에는 키가 사라진다', function () {
	update_option( 'dd_lms_gemini_api_key', 'anything' );
	assert_true( DD_API_Key::has_key() );
	DD_API_Key::delete();
	assert_false( DD_API_Key::has_key() );
	assert_same( '', DD_API_Key::get() );
} );

test( 'DD_YouTube_Search — 옵션에 저장된 키를 반환한다 (기존 동작 유지)', function () {
	DD_YouTube_Search::save_key( 'youtube-option-key' );
	assert_same( 'youtube-option-key', DD_YouTube_Search::get_key() );
} );

test( 'DD_Thumbnail — 옵션에 저장된 키를 반환한다 (기존 동작 유지)', function () {
	DD_Thumbnail::save_key( 'pixabay-option-key' );
	assert_same( 'pixabay-option-key', DD_Thumbnail::get_key() );
} );

/* =============================================================
   2단계 — 오버라이드 경로
   여기서부터 상수를 정의한다. 이 아래 순서를 바꾸지 말 것.
   ============================================================= */

test( 'DD_API_Key — DD_GEMINI_API_KEY 상수가 옵션보다 우선한다', function () {
	// 옵션에 (복호화 불가능한) 값이 들어 있어도 오버라이드가 먼저 반환되어야 한다.
	// 이 동작 덕분에 openssl 이 없는 환경에서도 오버라이드 경로는 검증 가능하다.
	update_option( 'dd_lms_gemini_api_key', 'some-encrypted-blob' );
	define( 'DD_GEMINI_API_KEY', 'from-constant' );
	assert_same( 'from-constant', DD_API_Key::get() );
} );

test( 'DD_API_Key — 오버라이드가 있으면 옵션이 비어 있어도 키가 있는 것으로 본다', function () {
	// 옵션은 비어 있는 상태 (러너가 매 테스트마다 초기화).
	assert_true( DD_API_Key::has_key(), '상수 오버라이드만으로도 키 보유로 인식해야 함' );
} );

test( 'DD_YouTube_Search — DD_YOUTUBE_API_KEY 상수가 옵션보다 우선한다', function () {
	DD_YouTube_Search::save_key( 'from-option' );
	define( 'DD_YOUTUBE_API_KEY', 'yt-from-constant' );
	assert_same( 'yt-from-constant', DD_YouTube_Search::get_key() );
} );

test( 'DD_Thumbnail — DD_PIXABAY_API_KEY 상수가 옵션보다 우선한다', function () {
	DD_Thumbnail::save_key( 'from-option' );
	define( 'DD_PIXABAY_API_KEY', 'px-from-constant' );
	assert_same( 'px-from-constant', DD_Thumbnail::get_key() );
} );

/* =============================================================
   3단계 — 보안 회귀 방지
   ============================================================= */

test( '어떤 키 게터도 값을 그대로 로그에 흘릴 수 있는 형태로 노출하지 않는다', function () {
	// mask() 를 거치면 원문이 남지 않아야 한다.
	$masked = DD_Env::mask( DD_YouTube_Search::get_key() );
	assert_not_contains( 'yt-from-constant', $masked );
} );
