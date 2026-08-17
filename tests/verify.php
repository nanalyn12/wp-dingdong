<?php
/**
 * DingDong LMS — 통합 검증 게이트.
 *
 * 실행:  php tests/verify.php
 *
 * 단계:
 *   1) PHP 구문 검사   (php -l)      — 커스텀 플러그인 + 테마 전체
 *   2) JS 구문 검사    (node --check) — node 가 없으면 SKIP
 *   3) 자격증명 스캔                  — 하드코딩된 키가 새로 들어왔는지
 *   4) 단위 테스트     (tests/run.php)
 *
 * 종료 코드: 0 = 전부 통과, 1 = 하나라도 실패.
 * AGENTS.md 규칙에 따라 별도 dev server(preview)는 절대 띄우지 않는다.
 */

$root       = dirname( __DIR__ );
$plugin_dir = $root . '/wp-content/plugins/dingdong-lms';
$theme_dir  = $root . '/wp-content/themes/dingdong';

$failures = array();
$summary  = array();

/**
 * 디렉터리에서 확장자에 해당하는 파일을 모두 찾는다.
 */
function dd_collect( $dir, $ext ) {
	if ( ! is_dir( $dir ) ) {
		return array();
	}
	$out = array();
	$it  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		if ( $file->isFile() && strtolower( $file->getExtension() ) === $ext ) {
			$out[] = $file->getPathname();
		}
	}
	sort( $out );
	return $out;
}

function dd_section( $title ) {
	echo "\n" . str_repeat( '=', 62 ) . "\n";
	echo $title . "\n";
	echo str_repeat( '=', 62 ) . "\n";
}

/* =============================================================
   1) PHP 구문 검사
   ============================================================= */

dd_section( '1/4  PHP 구문 검사 (php -l)' );

$php_files = array_merge( dd_collect( $plugin_dir, 'php' ), dd_collect( $theme_dir, 'php' ) );
$php_bad   = 0;

foreach ( $php_files as $f ) {
	$out  = array();
	$code = 0;
	exec( escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $f ) . ' 2>&1', $out, $code );
	if ( $code !== 0 ) {
		++$php_bad;
		echo "  [FAIL] " . str_replace( $root . DIRECTORY_SEPARATOR, '', $f ) . "\n";
		echo "         " . implode( "\n         ", $out ) . "\n";
	}
}

echo "  검사 " . count( $php_files ) . "개 파일 / 실패 {$php_bad}개\n";
$summary['PHP 구문'] = $php_bad === 0 ? 'PASS' : 'FAIL';
if ( $php_bad > 0 ) {
	$failures[] = 'PHP 구문 검사';
}

/* =============================================================
   2) JS 구문 검사
   ============================================================= */

dd_section( '2/4  JS 구문 검사 (node --check)' );

$node_ok = false;
$probe   = array();
$pcode    = 0;
exec( 'node --version 2>&1', $probe, $pcode );
$node_ok = ( $pcode === 0 );

if ( ! $node_ok ) {
	echo "  [SKIP] node 를 찾을 수 없어 JS 구문 검사를 건너뜁니다.\n";
	$summary['JS 구문'] = 'SKIP';
} else {
	$js_files = dd_collect( $plugin_dir, 'js' );
	$js_bad   = 0;
	foreach ( $js_files as $f ) {
		$out  = array();
		$code = 0;
		exec( 'node --check ' . escapeshellarg( $f ) . ' 2>&1', $out, $code );
		if ( $code !== 0 ) {
			++$js_bad;
			echo "  [FAIL] " . str_replace( $root . DIRECTORY_SEPARATOR, '', $f ) . "\n";
			echo "         " . implode( "\n         ", array_slice( $out, 0, 4 ) ) . "\n";
		}
	}
	echo "  검사 " . count( $js_files ) . "개 파일 / 실패 {$js_bad}개\n";
	$summary['JS 구문'] = $js_bad === 0 ? 'PASS' : 'FAIL';
	if ( $js_bad > 0 ) {
		$failures[] = 'JS 구문 검사';
	}
}

/* =============================================================
   3) 하드코딩 자격증명 스캔
   ============================================================= */

dd_section( '3/4  하드코딩 자격증명 스캔' );

$secret_patterns = array(
	'Google API 키'   => '/\bAIza[0-9A-Za-z_\-]{30,}/',
	'OpenAI 키'       => '/\bsk-[A-Za-z0-9]{32,}/',
	'GitHub 토큰'     => '/\bghp_[A-Za-z0-9]{30,}/',
	'AWS 액세스 키'   => '/\bAKIA[0-9A-Z]{16}\b/',
	'Slack 토큰'      => '/\bxox[baprs]-[A-Za-z0-9\-]{10,}/',
	'개인키 블록'     => '/-----BEGIN [A-Z ]*PRIVATE KEY-----/',
);

$scan_files = array_merge(
	$php_files,
	dd_collect( $plugin_dir, 'js' ),
	dd_collect( $theme_dir, 'js' )
);

$secret_hits = 0;
foreach ( $scan_files as $f ) {
	$contents = file_get_contents( $f );
	if ( $contents === false ) {
		continue;
	}
	foreach ( $secret_patterns as $label => $pattern ) {
		if ( preg_match( $pattern, $contents, $m ) ) {
			++$secret_hits;
			// 발견된 값 자체는 출력하지 않는다 (로그로 재유출 방지).
			echo "  [FAIL] {$label} 로 보이는 값 발견: "
				. str_replace( $root . DIRECTORY_SEPARATOR, '', $f ) . "\n";
		}
	}
}

if ( $secret_hits === 0 ) {
	echo "  검사 " . count( $scan_files ) . "개 파일 / 하드코딩된 자격증명 없음\n";
}
$summary['자격증명 스캔'] = $secret_hits === 0 ? 'PASS' : 'FAIL';
if ( $secret_hits > 0 ) {
	$failures[] = '자격증명 스캔';
}

/* =============================================================
   4) 단위 테스트
   ============================================================= */

dd_section( '4/4  단위 테스트' );

$test_out  = array();
$test_code = 0;
exec(
	escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/run.php' ) . ' 2>&1',
	$test_out,
	$test_code
);

// 요약 줄만 보여준다 (전체 목록은 php tests/run.php 로 확인).
foreach ( $test_out as $line ) {
	if ( strpos( $line, '총 ' ) === 0 || strpos( $line, '  [FAIL]' ) === 0
		|| strpos( $line, '  [ERROR]' ) === 0 || strpos( $line, '건너뛴' ) === 0
		|| strpos( $line, '  - ' ) === 0 ) {
		echo '  ' . $line . "\n";
	}
}

$summary['단위 테스트'] = $test_code === 0 ? 'PASS' : 'FAIL';
if ( $test_code !== 0 ) {
	$failures[] = '단위 테스트';
}

/* =============================================================
   최종 요약
   ============================================================= */

dd_section( '검증 요약' );

foreach ( $summary as $name => $result ) {
	printf( "  %-20s %s\n", $name, $result );
}

if ( ! empty( $failures ) ) {
	echo "\n결과: FAIL — " . implode( ', ', $failures ) . "\n";
	exit( 1 );
}

echo "\n결과: PASS — 모든 검증 통과\n";
exit( 0 );
