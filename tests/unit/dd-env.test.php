<?php
/**
 * DD_Env — API 키 오버라이드 체인 및 .env 파서 테스트.
 *
 * 설계 의도(AGENTS.md 규칙 8 보존):
 *   이 플러그인은 zip 으로 배포되어 타 사이트 관리자가 본인 키를 관리자 화면에서
 *   등록하는 것이 기본 동작이다. 따라서 .env 는 "로컬 개발용 오버라이드"일 뿐이고,
 *   키가 없으면 반드시 기존 Options API 경로로 폴백해야 한다.
 *
 * 해석 우선순위: PHP 상수 → 환경변수 → (호출부의) wp_options
 */

require_once DD_PLUGIN_DIR . '/includes/class-dd-env.php';

/* =============================================================
   해석 우선순위
   ============================================================= */

test( 'DD_Env::get — 상수가 정의되어 있으면 그 값을 반환한다', function () {
	define( 'DD_TEST_KEY_CONST', 'value-from-constant' );
	assert_same( 'value-from-constant', DD_Env::get( 'DD_TEST_KEY_CONST' ) );
} );

test( 'DD_Env::get — 상수가 없으면 환경변수를 사용한다', function () {
	putenv( 'DD_TEST_KEY_ENV=value-from-env' );
	assert_same( 'value-from-env', DD_Env::get( 'DD_TEST_KEY_ENV' ) );
	putenv( 'DD_TEST_KEY_ENV' );
} );

test( 'DD_Env::get — 상수가 환경변수보다 우선한다', function () {
	define( 'DD_TEST_KEY_BOTH', 'from-constant' );
	putenv( 'DD_TEST_KEY_BOTH=from-env' );
	assert_same( 'from-constant', DD_Env::get( 'DD_TEST_KEY_BOTH' ) );
	putenv( 'DD_TEST_KEY_BOTH' );
} );

test( 'DD_Env::get — 아무 데도 없으면 빈 문자열을 반환한다 (옵션 폴백은 호출부 책임)', function () {
	assert_same( '', DD_Env::get( 'DD_TEST_KEY_ABSENT' ) );
} );

test( 'DD_Env::get — 공백뿐인 값은 미설정으로 취급한다', function () {
	putenv( 'DD_TEST_KEY_BLANK=   ' );
	assert_same( '', DD_Env::get( 'DD_TEST_KEY_BLANK' ) );
	putenv( 'DD_TEST_KEY_BLANK' );
} );

test( 'DD_Env::get — 앞뒤 공백을 제거한다', function () {
	putenv( 'DD_TEST_KEY_PAD=  padded-key  ' );
	assert_same( 'padded-key', DD_Env::get( 'DD_TEST_KEY_PAD' ) );
	putenv( 'DD_TEST_KEY_PAD' );
} );

test( 'DD_Env::has — 값이 있으면 true, 없으면 false', function () {
	putenv( 'DD_TEST_KEY_HAS=x' );
	assert_true( DD_Env::has( 'DD_TEST_KEY_HAS' ) );
	assert_false( DD_Env::has( 'DD_TEST_KEY_MISSING' ) );
	putenv( 'DD_TEST_KEY_HAS' );
} );

/* =============================================================
   .env 파서
   ============================================================= */

test( 'parse_dotenv — 기본 KEY=VALUE 를 파싱한다', function () {
	$out = DD_Env::parse_dotenv( "DD_GEMINI_API_KEY=abc123\nDD_YOUTUBE_API_KEY=xyz789\n" );
	assert_same( 'abc123', $out['DD_GEMINI_API_KEY'] );
	assert_same( 'xyz789', $out['DD_YOUTUBE_API_KEY'] );
} );

test( 'parse_dotenv — 주석과 빈 줄을 무시한다', function () {
	$out = DD_Env::parse_dotenv( "# 주석입니다\n\nDD_A=1\n   # 들여쓴 주석\nDD_B=2\n" );
	assert_same( array( 'DD_A' => '1', 'DD_B' => '2' ), $out );
} );

test( 'parse_dotenv — 값을 감싼 따옴표를 제거한다', function () {
	$out = DD_Env::parse_dotenv( "DD_A=\"double\"\nDD_B='single'\nDD_C=bare\n" );
	assert_same( 'double', $out['DD_A'] );
	assert_same( 'single', $out['DD_B'] );
	assert_same( 'bare', $out['DD_C'] );
} );

test( 'parse_dotenv — export 접두사를 허용한다', function () {
	$out = DD_Env::parse_dotenv( "export DD_A=1\n" );
	assert_same( '1', $out['DD_A'] );
} );

test( 'parse_dotenv — 값 안의 = 기호를 보존한다', function () {
	$out = DD_Env::parse_dotenv( "DD_A=key=with=equals\n" );
	assert_same( 'key=with=equals', $out['DD_A'] );
} );

test( 'parse_dotenv — 키 이름 주변 공백을 정리한다', function () {
	$out = DD_Env::parse_dotenv( "  DD_A  =  spaced  \n" );
	assert_same( 'spaced', $out['DD_A'] );
} );

test( 'parse_dotenv — = 가 없는 줄은 건너뛴다', function () {
	$out = DD_Env::parse_dotenv( "GARBAGE LINE\nDD_A=1\n" );
	assert_same( array( 'DD_A' => '1' ), $out );
} );

test( 'parse_dotenv — CRLF 줄바꿈을 처리한다 (Windows 편집기 대응)', function () {
	$out = DD_Env::parse_dotenv( "DD_A=1\r\nDD_B=2\r\n" );
	assert_same( '1', $out['DD_A'] );
	assert_same( '2', $out['DD_B'] );
} );

test( 'parse_dotenv — 빈 값은 빈 문자열로 파싱한다 (.env.example 형태)', function () {
	$out = DD_Env::parse_dotenv( "DD_A=\n" );
	assert_same( '', $out['DD_A'] );
} );

/* =============================================================
   보안: 로그/노출 방지
   ============================================================= */

test( 'DD_Env::mask — 앞뒤 4글자만 남기고 가린다', function () {
	assert_same( 'AIza********cdef', DD_Env::mask( 'AIzaSyA1234567890abcdef' ) );
} );

test( 'DD_Env::mask — 별표 개수가 항상 고정이라 키 길이가 새지 않는다', function () {
	$short_key = DD_Env::mask( 'AIza' . str_repeat( 'x', 10 ) . 'cdef' );
	$long_key  = DD_Env::mask( 'AIza' . str_repeat( 'x', 90 ) . 'cdef' );
	assert_same( $short_key, $long_key, '길이가 다른 두 키의 마스킹 결과가 같아야 함' );
} );

test( 'DD_Env::mask — 짧은 값은 전부 가린다', function () {
	assert_same( '********', DD_Env::mask( 'short' ) );
} );

test( 'DD_Env::mask — 빈 값은 빈 문자열을 반환한다', function () {
	assert_same( '', DD_Env::mask( '' ) );
} );
