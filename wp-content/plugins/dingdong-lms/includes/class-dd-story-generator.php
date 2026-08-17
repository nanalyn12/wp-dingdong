<?php
class DD_Story_Generator {

    /** 노드 삽화 기본 장수 — 이미지가 요금의 대부분이라 보수적으로 잡는다. */
    const DEFAULT_SCENE_IMAGES = 4;

    /** 노드 삽화 최대 장수 — 프롬프트가 image_prompt 를 7개까지만 지정한다. */
    const MAX_SCENE_IMAGES = 7;

    /**
     * @param int  $scene_images 생성할 노드 삽화 장수 (0 = 삽화 없음)
     * @param bool $cover_image  커버 이미지 생성 여부
     */
    public static function generate( $topic, $level = 'beginner', $course_id = 0, $scene_images = self::DEFAULT_SCENE_IMAGES, $cover_image = true ) {
        $scene_images = max( 0, min( (int) $scene_images, self::MAX_SCENE_IMAGES ) );
        self::log( "=== generate_story START: topic={$topic}, level={$level}" );

        $level_desc = array(
            'beginner'     => 'HSK 1-2급 수준 (기초 단어, 짧은 문장)',
            'intermediate' => 'HSK 3-4급 수준 (일상 대화, 복합 문장)',
            'advanced'     => 'HSK 5급 이상 (고급 어휘, 긴 문장)',
        );

        $level_text = isset( $level_desc[ $level ] ) ? $level_desc[ $level ] : $level_desc['beginner'];

        $system = '당신은 한국인 중국어 학습자를 위한 인터랙티브 스토리 작가입니다. 반드시 유효한 JSON만 출력하세요. JSON 외의 텍스트는 절대 포함하지 마세요.' . "\n" . DD_Chinese::PROMPT_RULE;

        $prompt = sprintf(
            '주제: "%s"
난이도: %s (%s)

아래 JSON 구조의 인터랙티브 스토리를 생성하세요.
선택에 따라 이야기가 달라지는 게임북 형식입니다.
JSON만 출력하고 다른 텍스트는 포함하지 마세요.

{
  "title": "스토리 제목 (한국어)",
  "title_zh": "스토리 제목 (중국어)",
  "description": "스토리 소개 1-2문장 (한국어)",
  "cover_prompt": "Cover illustration description in English, warm anime style, cinematic lighting",
  "start": "node_1",
  "nodes": {
    "node_1": {
      "id": "node_1",
      "text_zh": "중국어 이야기 텍스트 (4-6문장, 대화를 포함시켜 생동감 있게)",
      "pinyin": "전체 병음",
      "text_ko": "한국어 번역",
      "mood": "장면 분위기 (warm/tense/cheerful/mysterious/sad/exciting 중 1개)",
      "speaker": "현재 장면의 화자 이름 (예: 叮叮, 사장님, 택시기사). 없으면 빈 문자열",
      "vocab": [
        {"zh": "핵심 단어 중국어", "pinyin": "병음", "ko": "한국어 뜻"}
      ],
      "grammar_tip": "이 장면에서 사용된 핵심 문법 1개를 한국어로 짧게 설명 (없으면 빈 문자열)",
      "image_prompt": "장면 묘사 (영어, anime illustration, 구체적인 장면·표정·동작 묘사, cinematic composition). No text or writing in the image.",
      "choices": [
        {"text_zh": "선택지 중국어", "text_ko": "선택지 한국어", "next": "node_2", "emoji": "적절한 이모지 1개"},
        {"text_zh": "선택지 중국어", "text_ko": "선택지 한국어", "next": "node_3", "emoji": "적절한 이모지 1개"}
      ]
    }
  }
}

규칙:
- 총 12-18개 노드
- 각 비엔딩 노드에 2-3개 선택지 (선택지마다 이모지)
- 최소 4개 다른 엔딩 (good 2개/neutral 1개/bad 1개)
- 엔딩 노드: is_ending: true, ending_type: "good" 또는 "neutral" 또는 "bad", choices: [], ending_title: "엔딩 제목 (한국어)"
- 중국어 난이도: %s
- text_zh에 실용적인 중국어 표현과 대화를 풍부하게 포함
- vocab: 각 노드에서 학습할 핵심 단어 2-4개
- mood: 각 노드마다 반드시 장면 분위기 지정
- speaker: 대화 장면에서 화자 이름 지정
- image_prompt: 핵심 장면 노드 7개에 설정 (나머지는 빈 문자열 ""). 텍스트/글자 포함 금지
- 스토리가 중국 문화를 체험하는 내용이 되도록
- 모든 next 값은 실제 존재하는 노드 id를 참조해야 함
- 분기가 다양해서 재방문할 때 다른 경로를 경험할 수 있도록',
            $topic,
            $level,
            $level_text,
            $level_text
        );

        $result = DD_Gemini::generate( $prompt, $system );

        if ( is_wp_error( $result ) ) {
            self::log( 'Story Gemini error: ' . $result->get_error_message() );
            return $result;
        }

        if ( ! is_array( $result ) || empty( $result['title'] ) || empty( $result['nodes'] ) || empty( $result['start'] ) ) {
            self::log( 'Story bad format: ' . ( is_array( $result ) ? wp_json_encode( array_keys( $result ) ) : gettype( $result ) ) );
            return new WP_Error( 'bad_story', '스토리 응답 형식이 올바르지 않습니다.' );
        }

        $nodes = $result['nodes'];
        $valid = self::validate_nodes( $nodes, $result['start'] );
        if ( is_wp_error( $valid ) ) {
            self::log( 'Story node validation failed: ' . $valid->get_error_message() );
        }

        self::log( 'Story Gemini OK: ' . $result['title'] . ', nodes=' . count( $nodes ) );

        $story_id = wp_insert_post( array(
            'post_type'    => 'dd_story',
            'post_title'   => sanitize_text_field( $result['title'] ),
            'post_content' => sanitize_textarea_field( $result['description'] ?? '' ),
            'post_status'  => 'publish',
        ) );

        if ( is_wp_error( $story_id ) ) {
            return $story_id;
        }

        $token = wp_generate_uuid4();
        update_post_meta( $story_id, '_dd_story_nodes', wp_json_encode( $result, JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $story_id, '_dd_story_course_id', (int) $course_id );
        update_post_meta( $story_id, '_dd_story_level', sanitize_text_field( $level ) );
        update_post_meta( $story_id, '_dd_story_public_token', $token );
        update_post_meta( $story_id, '_dd_story_public_active', '1' );
        update_post_meta( $story_id, '_dd_story_cover_image', '' );

        // 커버 + 핵심 노드 이미지 생성 (쿼터 초과 시 스킵)
        try {
            if ( DD_Image_Generator::is_quota_exhausted() ) {
                self::log( 'Image quota exhausted — skipping all story images' );
            } else {
                $cover_prompt = ( $cover_image && ! empty( $result['cover_prompt'] ) ) ? $result['cover_prompt'] : '';
                if ( ! empty( $cover_prompt ) ) {
                    $cover_full = 'Create a book cover illustration. ' . $cover_prompt
                        . ' Style: Warm anime illustration, vibrant colors, inviting atmosphere. No text or letters.';
                    $img = DD_Image_Generator::generate( $cover_full, '3:4' );
                    if ( ! is_wp_error( $img ) ) {
                        $saved = DD_Image_Generator::save_image( $img, 'story-cover-' . $story_id );
                        if ( ! is_wp_error( $saved ) ) {
                            update_post_meta( $story_id, '_dd_story_cover_image', $saved['url'] );
                            self::log( 'Cover image saved: ' . $saved['url'] );
                        } else {
                            self::log( 'Cover image save error: ' . $saved->get_error_message() );
                        }
                    } else {
                        self::log( 'Cover image gen error: ' . $img->get_error_message() );
                    }
                }

                // 쿼터 소진되면 나머지 노드 이미지 스킵
                if ( $scene_images > 0 && ! DD_Image_Generator::is_quota_exhausted() ) {
                    $img_count = 0;
                    foreach ( $nodes as $nid => &$node ) {
                        if ( $img_count >= $scene_images ) break;
                        if ( DD_Image_Generator::is_quota_exhausted() ) {
                            self::log( 'Quota exhausted mid-generation — skipping remaining node images' );
                            break;
                        }
                        $ip = ! empty( $node['image_prompt'] ) ? $node['image_prompt'] : '';
                        if ( empty( $ip ) ) {
                            $node['image_url'] = '';
                            continue;
                        }
                        $full = 'Create an illustration for an interactive story. ' . $ip
                            . ' Style: Warm anime illustration, soft pastel colors. No text or letters.';
                        $img = DD_Image_Generator::generate( $full, '16:9' );
                        if ( ! is_wp_error( $img ) ) {
                            $saved = DD_Image_Generator::save_image( $img, 'story-' . $story_id . '-' . $nid );
                            $node['image_url'] = is_wp_error( $saved ) ? '' : $saved['url'];
                        } else {
                            $node['image_url'] = '';
                        }
                        $img_count++;
                    }
                    unset( $node );
                }
            }

            $result['nodes'] = $nodes;
            update_post_meta( $story_id, '_dd_story_nodes', wp_json_encode( $result, JSON_UNESCAPED_UNICODE ) );
        } catch ( \Exception $e ) {
            self::log( 'Story images exception: ' . $e->getMessage() );
        } catch ( \Error $e ) {
            self::log( 'Story images fatal: ' . $e->getMessage() );
        }

        self::log( "=== generate_story DONE: story_id={$story_id}" );

        return array(
            'story_id'    => $story_id,
            'title'       => $result['title'],
            'description' => $result['description'] ?? '',
            'token'       => $token,
            'node_count'  => count( $nodes ),
        );
    }

    private static function validate_nodes( $nodes, $start ) {
        if ( ! isset( $nodes[ $start ] ) ) {
            return new WP_Error( 'invalid_start', 'start 노드가 존재하지 않습니다.' );
        }
        foreach ( $nodes as $nid => $node ) {
            if ( ! empty( $node['choices'] ) ) {
                foreach ( $node['choices'] as $choice ) {
                    if ( ! empty( $choice['next'] ) && ! isset( $nodes[ $choice['next'] ] ) ) {
                        return new WP_Error( 'broken_link', "노드 {$nid}의 선택지가 존재하지 않는 노드 {$choice['next']}를 참조합니다." );
                    }
                }
            }
        }
        return true;
    }

    private static function log( $message ) {
        $upload_dir = wp_upload_dir();
        $log_file   = $upload_dir['basedir'] . '/dingdong-lms/debug.log';
        $dir        = dirname( $log_file );
        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
        }
        $timestamp = current_time( 'Y-m-d H:i:s' );
        @file_put_contents( $log_file, "[{$timestamp}] {$message}\n", FILE_APPEND );
    }
}
