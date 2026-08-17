<?php
/**
 * DingDong LMS — 테스트 러너.
 *
 * 실행:
 *   php tests/run.php              모든 테스트 실행
 *   php tests/run.php chinese      파일명에 'chinese' 가 포함된 테스트만 실행
 *
 * 종료 코드: 0 = 전부 통과, 1 = 실패 존재 (CI/훅에서 게이트로 사용 가능)
 */

declare( strict_types=0 );

// Windows 콘솔에서 한글/한자가 깨지지 않도록 UTF-8 고정.
if ( function_exists( 'mb_internal_encoding' ) ) {
	mb_internal_encoding( 'UTF-8' );
}
ini_set( 'display_errors', '1' );
error_reporting( E_ALL );

define( 'DD_TESTS_DIR', __DIR__ );
define( 'DD_PLUGIN_DIR', dirname( __DIR__ ) . '/wp-content/plugins/dingdong-lms' );

// 플러그인 파일이 `if ( ! defined( 'ABSPATH' ) ) exit;` 로 직접 접근을 막으므로
// 테스트에서도 WordPress 안에서 로드된 것처럼 보이게 한다.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require_once __DIR__ . '/framework.php';
require_once __DIR__ . '/wp-stubs.php';

/* -------------------------------------------------------------
   테스트 파일 수집
   ------------------------------------------------------------- */

$filter = isset( $argv[1] ) ? (string) $argv[1] : '';
$files  = glob( __DIR__ . '/unit/*.test.php' );
sort( $files );

if ( $filter !== '' ) {
	$files = array_values(
		array_filter(
			$files,
			function ( $f ) use ( $filter ) {
				return strpos( basename( $f ), $filter ) !== false;
			}
		)
	);
}

if ( empty( $files ) ) {
	fwrite( STDERR, "테스트 파일을 찾지 못했습니다 (tests/unit/*.test.php)\n" );
	exit( 1 );
}

foreach ( $files as $file ) {
	$GLOBALS['dd_current_file'] = basename( $file );
	require_once $file;
}

/* -------------------------------------------------------------
   실행
   ------------------------------------------------------------- */

$passed  = 0;
$failed  = 0;
$errored = 0;
$skipped = 0;
$results = array();
$skips   = array();
$started = microtime( true );

$current_group = null;

foreach ( $GLOBALS['dd_tests'] as $t ) {
	if ( $current_group !== $t['file'] ) {
		$current_group = $t['file'];
		echo "\n" . $current_group . "\n";
	}

	// 테스트 간 상태 격리.
	dd_test_reset_options();

	try {
		call_user_func( $t['fn'] );
		++$passed;
		echo "  [PASS] " . $t['name'] . "\n";
	} catch ( DD_Test_Skipped $e ) {
		++$skipped;
		echo "  [SKIP] " . $t['name'] . "\n";
		echo "         사유: " . $e->getMessage() . "\n";
		$skips[] = array( 'name' => $t['name'], 'file' => $t['file'], 'msg' => $e->getMessage() );
	} catch ( DD_Assertion_Failure $e ) {
		++$failed;
		echo "  [FAIL] " . $t['name'] . "\n";
		echo "         " . $e->getMessage() . "\n";
		$results[] = array( 'type' => 'FAIL', 'name' => $t['name'], 'file' => $t['file'], 'msg' => $e->getMessage() );
	} catch ( Throwable $e ) {
		++$errored;
		echo "  [ERROR] " . $t['name'] . "\n";
		echo "         " . get_class( $e ) . ': ' . $e->getMessage() . "\n";
		echo "         " . $e->getFile() . ':' . $e->getLine() . "\n";
		$results[] = array( 'type' => 'ERROR', 'name' => $t['name'], 'file' => $t['file'], 'msg' => $e->getMessage() );
	}
}

$elapsed = round( ( microtime( true ) - $started ) * 1000 );
$total   = $passed + $failed + $errored + $skipped;

echo "\n" . str_repeat( '-', 60 ) . "\n";
echo "총 {$total}건 / 통과 {$passed} / 실패 {$failed} / 오류 {$errored} / 건너뜀 {$skipped}  ({$elapsed}ms)\n";

if ( $skipped > 0 ) {
	// 건너뛴 항목은 "검증되지 않음"이므로 통과로 착각하지 않도록 반드시 나열한다.
	echo "\n건너뛴 항목 (이 환경에서 검증 불가):\n";
	foreach ( $skips as $s ) {
		echo "  - {$s['file']} :: {$s['name']}  ({$s['msg']})\n";
	}
}

if ( $failed > 0 || $errored > 0 ) {
	echo "\n실패 항목:\n";
	foreach ( $results as $r ) {
		echo "  - [{$r['type']}] {$r['file']} :: {$r['name']}\n";
	}
	echo "\n결과: FAIL\n";
	exit( 1 );
}

echo "결과: PASS\n";
exit( 0 );
