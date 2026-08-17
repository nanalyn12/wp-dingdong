<?php
/**
 * DD_Chinese — 번체 → 간체 변환 (AGENTS.md 규칙 19).
 *
 * "관리자가 가져온 자막/자료가 번체자여도 프론트에는 반드시 간체자만 노출"
 * 이 규칙을 테스트로 고정한다.
 */

require_once DD_PLUGIN_DIR . '/includes/class-dd-chinese.php';

/* =============================================================
   기본 변환
   ============================================================= */

test( 'to_simplified — 단자 번체를 간체로 변환한다', function () {
	assert_same( '爱', DD_Chinese::to_simplified( '愛' ) );
} );

test( 'to_simplified — 여러 글자로 된 단어를 변환한다', function () {
	assert_same( '学习', DD_Chinese::to_simplified( '學習' ) );
	assert_same( '这样', DD_Chinese::to_simplified( '這樣' ) );
	assert_same( '没关系', DD_Chinese::to_simplified( '沒關係' ) );
} );

test( 'to_simplified — 이미 간체인 텍스트는 그대로 유지한다 (멱등)', function () {
	$simplified = '我爱学习中文';
	assert_same( $simplified, DD_Chinese::to_simplified( $simplified ) );
} );

test( 'to_simplified — 두 번 적용해도 결과가 같다 (멱등성)', function () {
	$once  = DD_Chinese::to_simplified( '學習這樣' );
	$twice = DD_Chinese::to_simplified( $once );
	assert_same( $once, $twice );
} );

test( 'to_simplified — 한글은 건드리지 않는다', function () {
	assert_same( '안녕하세요', DD_Chinese::to_simplified( '안녕하세요' ) );
} );

test( 'to_simplified — 한중 혼합문에서 중국어만 변환한다', function () {
	$out = DD_Chinese::to_simplified( '사랑은 愛입니다' );
	assert_contains( '사랑은', $out, '한글은 보존되어야 함' );
	assert_contains( '爱', $out, '번체는 간체로 변환되어야 함' );
	assert_not_contains( '愛', $out, '번체가 남아 있으면 안 됨' );
} );

test( 'to_simplified — 병음/영문/숫자/구두점을 보존한다', function () {
	assert_same( 'Ni hao 123, ok!', DD_Chinese::to_simplified( 'Ni hao 123, ok!' ) );
} );

test( 'to_simplified — 빈 문자열과 비문자열은 그대로 반환한다', function () {
	assert_same( '', DD_Chinese::to_simplified( '' ) );
	assert_same( null, DD_Chinese::to_simplified( null ) );
	assert_same( 123, DD_Chinese::to_simplified( 123 ) );
} );

test( 'to_simplified — 변환표에 없는 한자는 무손실 통과시킨다', function () {
	// 매핑에 없더라도 글자가 삭제되거나 깨지면 안 된다.
	$out = DD_Chinese::to_simplified( '龘' );
	assert_not_empty( $out );
	assert_same( 1, count( preg_split( '//u', $out, -1, PREG_SPLIT_NO_EMPTY ) ) );
} );

test( 'to_simplified — 줄바꿈 구조를 보존한다 (가사 줄 단위 처리 대응)', function () {
	$out = DD_Chinese::to_simplified( "愛\n學習\n" );
	assert_same( "爱\n学习\n", $out );
} );

/* =============================================================
   번체 감지
   ============================================================= */

test( 'has_traditional — 번체가 있으면 true', function () {
	assert_true( DD_Chinese::has_traditional( '學習' ) );
} );

test( 'has_traditional — 간체만 있으면 false', function () {
	assert_false( DD_Chinese::has_traditional( '学习' ) );
} );

test( 'has_traditional — 한글만 있으면 false', function () {
	assert_false( DD_Chinese::has_traditional( '안녕하세요' ) );
} );

test( 'has_traditional — 빈 값은 false', function () {
	assert_false( DD_Chinese::has_traditional( '' ) );
	assert_false( DD_Chinese::has_traditional( null ) );
} );

/* =============================================================
   중첩 배열 재귀 변환 (convert_deep)
   생성기가 저장 직전 content·가사·대화·슬라이드·퀴즈를 한 번에 간체화한다.
   ============================================================= */

test( 'convert_deep — 중첩 배열 안의 모든 문자열을 변환한다', function () {
	$input = array(
		'title'     => '學習',
		'dialogues' => array(
			array( 'zh' => '這樣', 'ko' => '이렇게' ),
			array( 'zh' => '沒關係', 'ko' => '괜찮아요' ),
		),
	);
	$out = DD_Chinese::convert_deep( $input );

	assert_same( '学习', $out['title'] );
	assert_same( '这样', $out['dialogues'][0]['zh'] );
	assert_same( '没关系', $out['dialogues'][1]['zh'] );
	assert_same( '이렇게', $out['dialogues'][0]['ko'], '한국어 필드는 보존되어야 함' );
} );

test( 'convert_deep — 배열 키 구조를 유지한다', function () {
	$out = DD_Chinese::convert_deep( array( 'a' => array( 'b' => array( 'c' => '愛' ) ) ) );
	assert_same( '爱', $out['a']['b']['c'] );
} );

test( 'convert_deep — 숫자/불리언/null 값을 훼손하지 않는다', function () {
	$out = DD_Chinese::convert_deep(
		array( 'n' => 42, 'b' => true, 'z' => null, 'f' => 1.5 )
	);
	assert_same( 42, $out['n'] );
	assert_same( true, $out['b'] );
	assert_same( null, $out['z'] );
	assert_same( 1.5, $out['f'] );
} );

/* =============================================================
   Gemini 프롬프트 규칙 상수
   ============================================================= */

test( 'PROMPT_RULE — 간체자 지시가 프롬프트 상수에 포함되어 있다', function () {
	assert_contains( '简体字', DD_Chinese::PROMPT_RULE );
	assert_not_empty( DD_Chinese::PROMPT_RULE );
} );

/* =============================================================
   mbstring 비의존 (Playground/PHP-WASM 안전성)
   ============================================================= */

test( '변환기는 mbstring 함수에 의존하지 않는다 (PHP-WASM 안전)', function () {
	$source = file_get_contents( DD_PLUGIN_DIR . '/includes/class-dd-chinese.php' );
	assert_not_contains( 'mb_substr', $source );
	assert_not_contains( 'mb_str_split', $source );
	assert_not_contains( 'mb_strlen', $source );
} );
