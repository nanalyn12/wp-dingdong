<?php
class DD_Newsletter_Generator {

    /**
     * @param bool $want_cover    커버 이미지 생성 여부
     * @param bool $want_sections 섹션별 삽화 생성 여부 (섹션 3~4개 = 이미지 3~4장)
     */
    public static function generate( $topic = '', $want_cover = true, $want_sections = true ) {
        if ( empty( $topic ) ) {
            $topic = '최신 중국 대중문화 트렌드';
        }

        $system = implode( "\n", array(
            'You are a Chinese pop culture journalist writing newsletters for Korean learners of Chinese.',
            'Write about the latest trends in Chinese entertainment, music, drama, food, social media, and internet culture.',
            'Target audience: Korean adults learning Chinese (HSK 2-6 level).',
            'Each newsletter should be informative, engaging, and educational.',
            'Include relevant Chinese vocabulary with pinyin and Korean translations.',
            'HSK levels follow 《国际中文教育中文水平等级标准》(2021): levels 1-9.',
            'IMPORTANT: All content text must be in Korean. Chinese appears only in key_terms and vocab_spotlight fields.',
            'CRITICAL: All Chinese text MUST be Simplified Chinese (简体字) only. Never output Traditional Chinese (繁體字); convert it if your source is Traditional.',
        ) );

        $prompt = implode( "\n", array(
            "Generate a newsletter about: {$topic}",
            '',
            'Return a JSON object with this exact structure:',
            '{',
            '  "title": "newsletter title in Korean (catchy, 10-20 chars)",',
            '  "title_zh": "same title in Chinese (4-8 characters)",',
            '  "summary": "2-3 sentence summary in Korean for the card preview",',
            '  "category": "one of: entertainment, music, food, tech, drama, social",',
            '  "cover_emoji": "one emoji representing the topic",',
            '  "cover_image_prompt": "English description of a cover illustration (no text/characters in image)",',
            '  "sections": [',
            '    {',
            '      "title": "section title in Korean",',
            '      "content": "2-3 paragraphs in Korean explaining the trend (150-300 chars per paragraph)",',
            '      "image_prompt": "English description of an illustration for this section (no text/characters in image)",',
            '      "key_terms": [',
            '        { "zh": "Chinese term", "pinyin": "pinyin", "ko": "Korean meaning", "hsk": 3 }',
            '      ]',
            '    }',
            '  ],',
            '  "vocab_spotlight": [',
            '    {',
            '      "zh": "Chinese word/phrase",',
            '      "pinyin": "pinyin with tones",',
            '      "ko": "Korean meaning",',
            '      "example_zh": "example sentence in Chinese",',
            '      "example_ko": "example sentence translated to Korean",',
            '      "hsk": 3',
            '    }',
            '  ]',
            '}',
            '',
            'Requirements:',
            '- 3-4 sections, each with 2-4 key_terms',
            '- 4-6 items in vocab_spotlight',
            '- HSK levels must accurately follow 《国际中文教育中文水平等级标准》',
            '- Content should reference real, recent Chinese pop culture trends',
            '- Make it interesting and educational',
            '- cover_image_prompt: vivid scene representing the newsletter theme (e.g. concert crowd, food market)',
            '- image_prompt per section: specific scene related to that section topic',
            '- All image prompts MUST be in English and must NOT contain any Chinese/Korean text or characters',
        ) );

        $result = DD_Gemini::generate( $prompt, $system );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( empty( $result['title'] ) || empty( $result['sections'] ) ) {
            return new WP_Error( 'invalid_response', '뉴스레터 생성 결과가 올바르지 않습니다.' );
        }

        $post_id = wp_insert_post( array(
            'post_type'    => 'dd_newsletter',
            'post_title'   => sanitize_text_field( $result['title'] ),
            'post_content' => '',
            'post_status'  => 'publish',
        ) );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        $token = wp_generate_uuid4();

        update_post_meta( $post_id, '_dd_newsletter_topic', sanitize_text_field( $topic ) );
        update_post_meta( $post_id, '_dd_newsletter_title_zh', sanitize_text_field( $result['title_zh'] ?? '' ) );
        update_post_meta( $post_id, '_dd_newsletter_summary', sanitize_text_field( $result['summary'] ?? '' ) );
        update_post_meta( $post_id, '_dd_newsletter_category', sanitize_text_field( $result['category'] ?? 'entertainment' ) );
        update_post_meta( $post_id, '_dd_newsletter_cover_emoji', sanitize_text_field( $result['cover_emoji'] ?? '📰' ) );
        update_post_meta( $post_id, '_dd_newsletter_vocab', wp_json_encode( $result['vocab_spotlight'] ?? array(), JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $post_id, '_dd_newsletter_public_token', $token );
        update_post_meta( $post_id, '_dd_newsletter_public_active', '1' );

        // --- 이미지 생성 (non-blocking: 실패해도 뉴스레터 생성은 완료) ---
        $cover_image = '';
        if ( $want_cover && ! empty( $result['cover_image_prompt'] ) && ! DD_Image_Generator::is_quota_exhausted() ) {
            $img_prompt = 'Create a vibrant editorial illustration for a Chinese culture newsletter. '
                . $result['cover_image_prompt'] . ' '
                . 'Style: Modern editorial illustration, bold colors, clean composition. '
                . 'No text, no characters, no writing, no letters of any language in the image.';
            $img_result = DD_Image_Generator::generate( $img_prompt, '16:9' );
            if ( ! is_wp_error( $img_result ) ) {
                $saved = DD_Image_Generator::save_image( $img_result, 'newsletter-cover-' . $post_id );
                if ( ! is_wp_error( $saved ) ) {
                    $cover_image = $saved['url'];
                }
            }
        }
        update_post_meta( $post_id, '_dd_newsletter_cover_image', $cover_image );

        $sections = $result['sections'];
        foreach ( $sections as $i => &$sec ) {
            $sec['image_url'] = '';
            if ( ! $want_sections || empty( $sec['image_prompt'] ) || DD_Image_Generator::is_quota_exhausted() ) {
                continue;
            }
            $img_prompt = 'Create an illustration for a Chinese culture newsletter section. '
                . $sec['image_prompt'] . ' '
                . 'Style: Modern editorial illustration, warm tones, clean composition. '
                . 'No text, no characters, no writing, no letters of any language in the image.';
            $img_result = DD_Image_Generator::generate( $img_prompt, '16:9' );
            if ( ! is_wp_error( $img_result ) ) {
                $saved = DD_Image_Generator::save_image( $img_result, 'newsletter-sec-' . $post_id . '-' . ( $i + 1 ) );
                if ( ! is_wp_error( $saved ) ) {
                    $sec['image_url'] = $saved['url'];
                }
            }
        }
        unset( $sec );

        update_post_meta( $post_id, '_dd_newsletter_sections', wp_json_encode( $sections, JSON_UNESCAPED_UNICODE ) );

        return array(
            'id'          => $post_id,
            'title'       => $result['title'],
            'title_zh'    => $result['title_zh'] ?? '',
            'summary'     => $result['summary'] ?? '',
            'category'    => $result['category'] ?? 'entertainment',
            'emoji'       => $result['cover_emoji'] ?? '📰',
            'cover_image' => $cover_image,
            'token'       => $token,
        );
    }
}
