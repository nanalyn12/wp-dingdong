<?php
/**
 * DingDong LMS — 의존성 없는 초경량 테스트 프레임워크.
 *
 * Composer/PHPUnit 없이 `php tests/run.php` 만으로 동작한다.
 * 이유: 이 플러그인은 zip으로 배포되므로 vendor/ 를 끌어들이지 않는 편이 안전하고,
 *      사이트 런타임이 Playground(PHP-WASM)라 외부 의존성 추가가 위험하다.
 *
 * 사용법:
 *   test( '설명', function () {
 *       assert_same( '爱', DD_Chinese::to_simplified( '愛' ) );
 *   } );
 */

class DD_Assertion_Failure extends Exception {}

/**
 * 테스트를 건너뛸 때 던진다. 실패가 아니라 "이 환경에서는 검증 불가"라는 뜻.
 * 통과로 위장하지 않고 SKIP 으로 집계된다.
 */
class DD_Test_Skipped extends Exception {}

/**
 * 조건이 거짓이면 테스트를 건너뛴다.
 *
 * 예) require_that( extension_loaded( 'openssl' ), 'openssl 확장 필요' );
 */
function require_that( $condition, $reason ) {
	if ( ! $condition ) {
		throw new DD_Test_Skipped( $reason );
	}
}

/** @var array<int,array{name:string,fn:callable,file:string}> */
$GLOBALS['dd_tests'] = array();

/** 현재 로딩 중인 테스트 파일 (러너가 설정). */
$GLOBALS['dd_current_file'] = '';

/**
 * 테스트 케이스를 등록한다.
 */
function test( $name, callable $fn ) {
	$GLOBALS['dd_tests'][] = array(
		'name' => $name,
		'fn'   => $fn,
		'file' => $GLOBALS['dd_current_file'],
	);
}

/* =============================================================
   단언(assertion) 함수
   ============================================================= */

function dd_export( $v ) {
	if ( is_string( $v ) ) {
		return "'" . $v . "'";
	}
	if ( is_bool( $v ) ) {
		return $v ? 'true' : 'false';
	}
	if ( is_null( $v ) ) {
		return 'null';
	}
	if ( is_array( $v ) ) {
		return 'array(' . count( $v ) . ') ' . json_encode( $v, JSON_UNESCAPED_UNICODE );
	}
	return (string) $v;
}

function dd_fail( $message ) {
	throw new DD_Assertion_Failure( $message );
}

/** 타입까지 동일한지(===) 검사. */
function assert_same( $expected, $actual, $message = '' ) {
	if ( $expected !== $actual ) {
		dd_fail(
			( $message ? $message . ' — ' : '' )
			. '기대값 ' . dd_export( $expected )
			. ' / 실제값 ' . dd_export( $actual )
		);
	}
}

function assert_not_same( $unexpected, $actual, $message = '' ) {
	if ( $unexpected === $actual ) {
		dd_fail( ( $message ? $message . ' — ' : '' ) . dd_export( $unexpected ) . ' 와 달라야 하는데 동일함' );
	}
}

function assert_true( $actual, $message = '' ) {
	assert_same( true, $actual, $message );
}

function assert_false( $actual, $message = '' ) {
	assert_same( false, $actual, $message );
}

/** $haystack 안에 $needle 이 포함되어 있는지. */
function assert_contains( $needle, $haystack, $message = '' ) {
	if ( strpos( (string) $haystack, (string) $needle ) === false ) {
		dd_fail(
			( $message ? $message . ' — ' : '' )
			. dd_export( $needle ) . ' 가 포함되어야 하는데 없음. 실제: ' . dd_export( $haystack )
		);
	}
}

/** $haystack 안에 $needle 이 없어야 함. */
function assert_not_contains( $needle, $haystack, $message = '' ) {
	if ( strpos( (string) $haystack, (string) $needle ) !== false ) {
		dd_fail(
			( $message ? $message . ' — ' : '' )
			. dd_export( $needle ) . ' 가 없어야 하는데 포함됨. 실제: ' . dd_export( $haystack )
		);
	}
}

/** 정규식 일치 검사. */
function assert_matches( $pattern, $subject, $message = '' ) {
	if ( ! preg_match( $pattern, (string) $subject ) ) {
		dd_fail(
			( $message ? $message . ' — ' : '' )
			. $pattern . ' 에 일치해야 하는데 불일치. 실제: ' . dd_export( $subject )
		);
	}
}

function assert_empty( $actual, $message = '' ) {
	if ( ! empty( $actual ) ) {
		dd_fail( ( $message ? $message . ' — ' : '' ) . '비어 있어야 하는데 값이 있음: ' . dd_export( $actual ) );
	}
}

function assert_not_empty( $actual, $message = '' ) {
	if ( empty( $actual ) ) {
		dd_fail( ( $message ? $message . ' — ' : '' ) . '값이 있어야 하는데 비어 있음' );
	}
}
