<?php
/**
 * 강의 생성 단계 분할 — 계약 회귀 방지.
 *
 * 배경: 예전에는 강의 하나를 만들 때 Gemini 텍스트 1회 + 이미지 최대 12회 + YouTube 검색을
 * 단일 HTTP 요청에서 처리했다. 공유호스팅의 프록시 타임아웃(60~300초)에 걸리면 브라우저는
 * HTML 502/504 를 받아 "응답이 올바른 JSON 응답이 아닙니다"(invalid_json) 오류를 띄웠고,
 * 정작 PHP 는 계속 돌아 강의를 정상 저장했다 — 결과물은 멀쩡한데 화면엔 실패로 보이는 상태.
 *
 * 그래서 에셋 생성을 단계(phase)별 개별 요청으로 쪼갰다. 이 파일은 그 분할 계약을 지킨다.
 *
 * ⚠️ 여기서 검증할 수 없는 것(스텁 계층의 한계 — Studio 사이트에서 수동 확인):
 *    실제 Gemini/YouTube 호출, wp_insert_post, WP_Query 기반 client_ref 조회.
 */

require_once DD_PLUGIN_DIR . '/includes/class-dd-course-generator.php';

test( '에셋 단계 목록이 비어 있지 않고 중복이 없다', function () {
	$phases = DD_Course_Generator::ASSET_PHASES;

	assert_not_empty( $phases, '단계가 하나도 없으면 이미지·영상이 전혀 생성되지 않는다' );
	assert_same(
		count( $phases ),
		count( array_unique( $phases ) ),
		'같은 단계가 두 번 실행되면 이미지 생성 쿼터를 두 배로 쓴다'
	);
} );

test( '모든 에셋 단계에 사람이 읽을 수 있는 이름이 있다', function () {
	// 관리자 화면은 실패한 단계를 "미생성: {label}" 로 보여준다.
	// 라벨이 없으면 슬러그(storybook_images)가 그대로 노출된다.
	foreach ( DD_Course_Generator::ASSET_PHASES as $phase ) {
		$label = DD_Course_Generator::asset_phase_label( $phase );

		assert_not_empty( $label, "단계 '{$phase}' 에 라벨이 없다" );
		assert_not_same( $phase, $label, "단계 '{$phase}' 의 라벨이 슬러그 그대로다 — 사용자에게 노출된다" );
		assert_not_contains( '_', $label, "라벨 '{$label}' 에 밑줄이 있다 — 슬러그가 새어 나온 것" );
	}
} );

test( '본문 생성 단계는 이미지·영상 작업을 포함하지 않는다', function () {
	// generate_lesson_text() 가 이미지 생성기를 직접 부르면 분할의 의미가 사라지고
	// 예전의 타임아웃 문제가 그대로 재발한다. 소스를 읽어 호출 여부를 확인한다.
	$src = file_get_contents( DD_PLUGIN_DIR . '/includes/class-dd-course-generator.php' );

	$start = strpos( $src, 'public static function generate_lesson_text' );
	$end   = strpos( $src, 'private static function lesson_text_response' );
	assert_true( $start !== false && $end !== false && $end > $start, '함수 경계를 찾지 못했다' );

	$body = substr( $src, $start, $end - $start );

	assert_not_contains( 'DD_Image_Generator::', $body, '본문 생성 단계에서 이미지를 만들면 안 된다' );
	assert_not_contains( 'DD_YouTube_Search::', $body, '본문 생성 단계에서 YouTube 를 검색하면 안 된다' );
} );

test( '알 수 없는 단계 이름은 거부된다', function () {
	// REST 는 클라이언트가 보낸 phase 문자열을 그대로 넘긴다.
	// 화이트리스트가 없으면 오타가 조용히 아무 일도 안 하는 성공으로 처리된다.
	assert_false(
		in_array( 'storybook', DD_Course_Generator::ASSET_PHASES, true ),
		'실제 단계명은 storybook_images 다 — 축약형이 통과하면 안 된다'
	);
} );
