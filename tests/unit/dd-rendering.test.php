<?php
/**
 * DD_Public_Access — 프론트 렌더링 필수 규칙 회귀 방지.
 *
 * 여기서 고정하는 규칙 (AGENTS.md):
 *   규칙 1) 마크다운 원본 기호(**, __, ##)가 화면에 노출되면 안 된다.
 *   규칙 2) 오디오북 TTS 는 중국어만 — 한글이 TTS 로 읽히면 안 된다.
 *
 * 이 두 규칙은 "사용자가 눈으로 발견하는 버그"라서 회귀 시 비용이 크다.
 */

require_once DD_PLUGIN_DIR . '/includes/class-dd-public-access.php';

/* =============================================================
   규칙 1 — 마크다운 원본 기호 비표시
   ============================================================= */

test( 'inline_format — **굵게** 를 <strong> 으로 변환한다', function () {
	assert_same( '<strong>굵게</strong>', DD_Public_Access::inline_format( '**굵게**' ) );
} );

test( 'inline_format — 변환 후 ** 기호가 화면에 남지 않는다', function () {
	assert_not_contains( '**', DD_Public_Access::inline_format( '**핵심 표현**' ) );
} );

test( 'inline_format — 짝이 맞지 않는 ** 도 제거한다 (규칙 1)', function () {
	// 마크다운이 깨진 상태로 Gemini 가 출력해도 화면에 별표가 새면 안 된다.
	assert_not_contains( '**', DD_Public_Access::inline_format( '**닫히지 않은 굵게' ) );
} );

test( 'inline_format — 문장 여러 곳의 굵게를 모두 처리한다', function () {
	$out = DD_Public_Access::inline_format( '**하나** 그리고 **둘**' );
	assert_not_contains( '**', $out );
	assert_contains( '<strong>하나</strong>', $out );
	assert_contains( '<strong>둘</strong>', $out );
} );

test( 'inline_format — `코드` 를 code 태그로 변환한다', function () {
	assert_contains( '<code', DD_Public_Access::inline_format( '`ni hao`' ) );
} );

test( 'inline_format — 중국어 텍스트의 굵게 처리도 동일하게 동작한다', function () {
	$out = DD_Public_Access::inline_format( '**你好**' );
	assert_same( '<strong>你好</strong>', $out );
} );

test( 'render_markdown — 렌더링 결과에 마크다운 헤딩 기호가 남지 않는다 (규칙 1)', function () {
	$out = DD_Public_Access::render_markdown( "## 핵심 표현\n\n**중요** 내용입니다.\n" );
	assert_not_contains( '##', $out );
	assert_not_contains( '**', $out );
} );

test( 'render_markdown — 헤딩을 실제 HTML 헤딩 태그로 만든다', function () {
	$out = DD_Public_Access::render_markdown( "## 본문\n" );
	assert_matches( '/<h[1-6][^>]*>/', $out, '헤딩이 HTML 태그로 변환되어야 함' );
} );

test( 'render_markdown — 빈 입력에서 예외를 던지지 않는다', function () {
	$out = DD_Public_Access::render_markdown( '' );
	assert_true( is_string( $out ) );
} );

/* =============================================================
   규칙 2 — TTS 는 중국어만 (한글 완전 제거)
   ============================================================= */

test( 'chinese_only — 한글을 완전히 제거한다 (규칙 2)', function () {
	$out = DD_Public_Access::chinese_only( '안녕하세요 你好' );
	assert_not_contains( '안녕하세요', $out );
	assert_contains( '你好', $out );
} );

test( 'chinese_only — 결과에 한글 코드포인트가 하나도 남지 않는다', function () {
	$out = DD_Public_Access::chinese_only( '이것은 중국어 문장입니다: 我是韩国人' );
	assert_same( 0, preg_match( '/[\x{AC00}-\x{D7A3}]/u', $out ), '완성형 한글이 남으면 TTS 가 한국어를 읽게 됨' );
} );

test( 'chinese_only — 자모(ㄱ, ㅏ)까지 제거한다', function () {
	$out = DD_Public_Access::chinese_only( 'ㄱㄴㄷ 你好 ㅏㅑ' );
	assert_same( 0, preg_match( '/[\x{3130}-\x{318F}]/u', $out ) );
	assert_contains( '你好', $out );
} );

test( 'chinese_only — 한자를 보존한다', function () {
	assert_contains( '我爱中国', DD_Public_Access::chinese_only( '나는 중국을 사랑해 我爱中国' ) );
} );

test( 'chinese_only — 마크다운 기호를 남기지 않는다 (TTS 가 별표를 읽으면 안 됨)', function () {
	$out = DD_Public_Access::chinese_only( '**你好**' );
	assert_not_contains( '*', $out );
} );

test( 'chinese_only — 한글 제거 후 이중 공백을 정리한다', function () {
	$out = DD_Public_Access::chinese_only( '你好 안녕 再见' );
	assert_not_contains( '  ', $out, '한글이 빠진 자리에 공백이 겹치면 안 됨' );
} );

test( 'chinese_only — 빈 입력은 빈 문자열을 반환한다', function () {
	assert_same( '', DD_Public_Access::chinese_only( '' ) );
	assert_same( '', DD_Public_Access::chinese_only( null ) );
} );

test( 'chinese_only — 한국어만 있는 문장은 결국 빈 문자열이 된다 (TTS 로 넘기지 않음)', function () {
	assert_same( '', DD_Public_Access::chinese_only( '완전히 한국어 문장입니다' ) );
} );

/* =============================================================
   규칙 19 연계 — 렌더링 경로에서도 번체가 새지 않아야 한다
   ============================================================= */

test( '간체 변환기와 TTS 정제를 함께 통과시키면 번체도 한글도 남지 않는다', function () {
	require_once DD_PLUGIN_DIR . '/includes/class-dd-chinese.php';
	$out = DD_Public_Access::chinese_only( DD_Chinese::to_simplified( '학습은 **學習** 입니다' ) );
	assert_not_contains( '學', $out, '번체가 남으면 안 됨' );
	assert_contains( '学习', $out );
	assert_same( 0, preg_match( '/[\x{AC00}-\x{D7A3}]/u', $out ) );
} );
