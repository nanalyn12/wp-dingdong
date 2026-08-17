<?php
/**
 * Gemini / Imagen 이미지 생성 API 래퍼
 * 1순위: gemini-2.5-flash-image (generateContent)
 * 2순위: gemini-3-pro-image-preview (generateContent)
 * 3순위: gemini-3.1-flash-image-preview (generateContent)
 * 4순위: imagen-4.0-fast-generate-001 (predict, 유료)
 */
class DD_Image_Generator {

    private static $api_base = 'https://generativelanguage.googleapis.com/v1beta/models/';

    private static $image_models = array(
        'gemini-2.5-flash-image',
        'gemini-3-pro-image-preview',
        'gemini-3.1-flash-image-preview',
    );

    /** 쿼터 초과 캐시 키 (transient, 5분 TTL) */
    private static $quota_cache_key = 'dd_img_quota_exhausted';

    /**
     * 모든 이미지 모델이 쿼터 초과 상태인지 확인
     */
    public static function is_quota_exhausted() {
        return (bool) get_transient( self::$quota_cache_key );
    }

    /**
     * 쿼터 초과 상태를 캐시 (5분 TTL)
     */
    private static function mark_quota_exhausted() {
        set_transient( self::$quota_cache_key, time(), 5 * MINUTE_IN_SECONDS );
        self::log( 'All image models quota exhausted — skipping for 5 minutes' );
    }

    /**
     * 쿼터 캐시 초기화
     */
    public static function clear_quota_cache() {
        delete_transient( self::$quota_cache_key );
    }

    public static function generate( $prompt, $aspect_ratio = '16:9' ) {
        // 쿼터 초과 캐시 확인 — 5분 내 전체 실패 시 즉시 스킵
        if ( self::is_quota_exhausted() ) {
            return new WP_Error( 'quota_exhausted', '이미지 생성 쿼터가 소진되었습니다. 잠시 후 다시 시도해 주세요.' );
        }

        $api_key = DD_API_Key::get();
        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_api_key', 'API 키가 없습니다.' );
        }

        $first_error = null;
        $quota_fails = 0;
        foreach ( self::$image_models as $model ) {
            $result = self::call_image_model( $model, $prompt, $api_key, $aspect_ratio );
            if ( ! is_wp_error( $result ) ) {
                // 성공하면 쿼터 캐시 클리어
                self::clear_quota_cache();
                return $result;
            }
            self::log( $model . ' failed: ' . $result->get_error_message() );
            if ( ! $first_error ) {
                $first_error = $result;
            }
            // 쿼터 초과 에러 감지
            if ( strpos( $result->get_error_message(), 'quota' ) !== false
                || strpos( $result->get_error_message(), 'Quota' ) !== false ) {
                $quota_fails++;
            }
        }

        $imagen = self::call_imagen( $prompt, $api_key, $aspect_ratio );
        if ( ! is_wp_error( $imagen ) ) {
            self::clear_quota_cache();
            return $imagen;
        }
        self::log( 'imagen-4.0 failed: ' . $imagen->get_error_message() );

        // 모든 Gemini 모델 쿼터 초과 + Imagen 실패 시 캐시
        if ( $quota_fails >= count( self::$image_models ) ) {
            self::mark_quota_exhausted();
        }

        return $first_error;
    }

    private static function call_image_model( $model, $prompt, $api_key, $aspect_ratio = '16:9' ) {
        $url = self::$api_base . $model . ':generateContent?key=' . $api_key;

        if ( ! empty( $aspect_ratio ) ) {
            $prompt .= "\n\nIMPORTANT: Generate the image in " . $aspect_ratio . " aspect ratio.";
        }

        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array( 'text' => $prompt ),
                    ),
                ),
            ),
            'generationConfig' => array(
                'responseModalities' => array( 'IMAGE', 'TEXT' ),
            ),
        );

        $response = wp_remote_post( $url, array(
            'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
            'body'    => wp_json_encode( $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
            'timeout' => 120,
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'image_gen_failed', $model . ' HTTP 오류: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $raw  = wp_remote_retrieve_body( $response );
        $data = json_decode( $raw, true );

        if ( $code !== 200 ) {
            $msg = isset( $data['error']['message'] ) ? $data['error']['message'] : 'HTTP ' . $code;
            return new WP_Error( 'image_gen_api_error', $model . ' 오류: ' . $msg );
        }

        if ( ! empty( $data['candidates'][0]['content']['parts'] ) ) {
            foreach ( $data['candidates'][0]['content']['parts'] as $part ) {
                if ( ! empty( $part['inlineData']['data'] ) ) {
                    return array(
                        'base64'    => $part['inlineData']['data'],
                        'mime_type' => $part['inlineData']['mimeType'] ?? 'image/png',
                    );
                }
            }
        }

        return new WP_Error( 'no_image_data', $model . ' 응답에 이미지가 없습니다.' );
    }

    private static function call_imagen( $prompt, $api_key, $aspect_ratio = '16:9' ) {
        $url = self::$api_base . 'imagen-4.0-fast-generate-001:predict?key=' . $api_key;

        $body = array(
            'instances'  => array(
                array( 'prompt' => $prompt ),
            ),
            'parameters' => array(
                'sampleCount' => 1,
                'aspectRatio' => $aspect_ratio,
            ),
        );

        $response = wp_remote_post( $url, array(
            'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
            'body'    => wp_json_encode( $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
            'timeout' => 120,
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'imagen_http', 'Imagen HTTP 오류: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $raw  = wp_remote_retrieve_body( $response );
        $data = json_decode( $raw, true );

        if ( $code !== 200 ) {
            $msg = isset( $data['error']['message'] ) ? $data['error']['message'] : 'HTTP ' . $code;
            return new WP_Error( 'imagen_api_error', 'Imagen 오류: ' . $msg );
        }

        if ( ! empty( $data['predictions'][0]['bytesBase64Encoded'] ) ) {
            return array(
                'base64'    => $data['predictions'][0]['bytesBase64Encoded'],
                'mime_type' => $data['predictions'][0]['mimeType'] ?? 'image/png',
            );
        }

        return new WP_Error( 'imagen_no_image', 'Imagen 응답에 이미지가 없습니다.' );
    }

    private static function log( $message ) {
        $upload_dir = wp_upload_dir();
        $log_file   = $upload_dir['basedir'] . '/dingdong-lms/debug.log';
        $dir        = dirname( $log_file );
        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
        }
        @file_put_contents( $log_file, '[' . current_time( 'Y-m-d H:i:s' ) . '] [IMG] ' . $message . "\n", FILE_APPEND );
    }

    /**
     * 이미지를 WordPress uploads에 저장
     */
    public static function save_image( $image_data, $filename ) {
        if ( is_wp_error( $image_data ) ) {
            return $image_data;
        }

        $upload_dir = wp_upload_dir();
        $dd_dir     = $upload_dir['basedir'] . '/dingdong-lms';

        if ( ! file_exists( $dd_dir ) ) {
            wp_mkdir_p( $dd_dir );
        }

        $ext       = ( strpos( $image_data['mime_type'], 'jpeg' ) !== false ) ? '.jpg' : '.png';
        $file_name = sanitize_file_name( $filename . '-' . time() . $ext );
        $file_path = $dd_dir . '/' . $file_name;

        $decoded = base64_decode( $image_data['base64'] );
        if ( $decoded === false ) {
            return new WP_Error( 'decode_failed', 'Base64 디코딩 실패' );
        }

        $written = file_put_contents( $file_path, $decoded );
        if ( $written === false ) {
            return new WP_Error( 'write_failed', '파일 저장 실패' );
        }

        $file_url = $upload_dir['baseurl'] . '/dingdong-lms/' . $file_name;

        return array(
            'url'  => $file_url,
            'path' => $file_path,
        );
    }

    /**
     * 강의 대화 장면 이미지 생성
     */
    /**
     * 통일된 아트 스타일 지시자 — 만화/대화/문화 이미지 공통 사용
     */
    public static function get_unified_style() {
        return 'Style: Cheerful kawaii anime style, pastel color palette (pink, sky blue, mint, lavender), '
             . 'clean black outlines, cute chibi-like proportions (large head, expressive eyes), '
             . 'soft gradient backgrounds with sparkles and stars. '
             . 'CRITICAL: Keep all characters visually consistent — same hair color, hairstyle, and outfit throughout the series. '
             . 'CRITICAL: Absolutely NO text, NO speech bubbles, NO letters, NO writing, NO characters of any language in the image.';
    }

    /**
     * 패널 전체에 공유할 캐릭터 시드 생성 — 한 강의 안에서 캐릭터 일관성 유지
     */
    public static function build_character_seed( $panels ) {
        $speakers = array();
        foreach ( $panels as $panel ) {
            $dialogues = ! empty( $panel['dialogue'] ) ? $panel['dialogue'] : array();
            foreach ( $dialogues as $dl ) {
                $name = $dl['speaker'] ?? '';
                if ( $name && ! isset( $speakers[ $name ] ) ) {
                    $speakers[ $name ] = true;
                }
            }
        }
        $names = array_keys( $speakers );
        if ( count( $names ) === 0 ) {
            return '';
        }

        // 미리 정의된 캐릭터 디자인 풀 (일관된 외모 보장)
        $designs = array(
            'a girl with long pink hair tied in twin buns, blue eyes, wearing a lavender hoodie',
            'a girl with short sky-blue bob hair, green eyes, wearing a mint polka-dot shirt',
            'a boy with short brown hair, brown eyes, wearing a yellow t-shirt and denim overalls',
            'a girl with long black hair in a ponytail, dark eyes, wearing a pink sweater',
        );

        $seed = 'Character reference (MUST keep consistent in all panels): ';
        foreach ( $names as $idx => $name ) {
            $design = $designs[ $idx % count( $designs ) ];
            $seed .= $name . ' is ' . $design . '. ';
        }
        return $seed;
    }

    public static function generate_dialogue_image( $lesson_id, $scene_desc ) {
        if ( empty( $scene_desc ) ) {
            return new WP_Error( 'no_scene', '대화 장면 설명이 없습니다.' );
        }

        $prompt = 'Create an illustration for a Chinese language learning textbook. '
                . $scene_desc . ' '
                . self::get_unified_style();

        $result = self::generate( $prompt, '16:9' );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $saved = self::save_image( $result, 'dialogue-' . $lesson_id );
        if ( is_wp_error( $saved ) ) {
            return $saved;
        }

        update_post_meta( $lesson_id, '_dd_dialogue_image', $saved['url'] );
        return $saved['url'];
    }

    /**
     * 만화 패널 이미지 생성 (하이브리드 그리드: 순수 일러스트 패널 + HTML 텍스트 오버레이)
     * 텍스트/말풍선은 프론트엔드 CSS로 표시하므로 이미지에는 절대 텍스트를 넣지 않음
     */
    public static function generate_comic_images( $lesson_id, $panels ) {
        if ( empty( $panels ) || ! is_array( $panels ) ) {
            return array();
        }

        // 쿼터 초과 시 전체 스킵
        if ( self::is_quota_exhausted() ) {
            self::log( 'Comic panel images skipped — quota exhausted' );
            return array_fill( 0, min( count( $panels ), 4 ), '' );
        }

        $urls   = array();
        $panels = array_slice( $panels, 0, 4 );

        // 캐릭터 시드 생성 — 전체 패널에서 공통 사용
        $character_seed = self::build_character_seed( $panels );

        foreach ( $panels as $i => $panel ) {
            // 쿼터 중간 소진 시 나머지 스킵
            if ( self::is_quota_exhausted() ) {
                $urls[] = '';
                continue;
            }

            $image_prompt = ! empty( $panel['image_prompt'] ) ? $panel['image_prompt'] : '';
            if ( empty( $image_prompt ) && ! empty( $panel['scene'] ) ) {
                $image_prompt = $panel['scene'];
            }

            if ( empty( $image_prompt ) ) {
                $urls[] = '';
                continue;
            }

            $prompt = 'Create panel ' . ( $i + 1 ) . ' of a 4-panel Chinese language learning comic. '
                    . $character_seed
                    . 'Scene: ' . $image_prompt . ' '
                    . self::get_unified_style()
                    . ' Pure illustration only. Square composition.';

            self::log( 'Comic panel ' . ( $i + 1 ) . ' prompt for lesson ' . $lesson_id );

            $result = self::generate( $prompt, '1:1' );
            if ( is_wp_error( $result ) ) {
                $urls[] = '';
                continue;
            }

            $saved = self::save_image( $result, 'comic-' . $lesson_id . '-' . ( $i + 1 ) );
            if ( is_wp_error( $saved ) ) {
                $urls[] = '';
                continue;
            }

            $urls[] = $saved['url'];
        }

        update_post_meta( $lesson_id, '_dd_comic_images', wp_json_encode( $urls, JSON_UNESCAPED_UNICODE ) );
        return $urls;
    }

    /**
     * 핵심 표현 인포그래픽 이미지 생성 (카와이 학습카드 스타일)
     */
    public static function generate_key_expressions_image( $lesson_id, $key_expressions, $lesson_title = '' ) {
        if ( empty( $key_expressions ) || ! is_array( $key_expressions ) ) {
            return new WP_Error( 'no_data', '핵심 표현 데이터가 없습니다.' );
        }

        $count = min( count( $key_expressions ), 9 );

        $prompt = 'Create a kawaii-style decorative header banner for a Chinese language learning page. '
            . 'The overall aesthetic is cheerful, pastel-colored (pink, sky blue, mint, lavender, cream), '
            . 'with clean black outlines and cute decorative elements. '
            . "\n\n"
            . 'The image should feature: '
            . '- A cute kawaii scene with two young women characters (a Korean girl and Chinese girl) surrounded by study items '
            . '- Decorative elements: books, pencils, light bulbs, Chinese lanterns, tea cups, stars, sparkles, washi tape, notebooks '
            . '- Soft pastel color palette with pink, sky blue, mint, lavender tones '
            . '- Warm, inviting educational atmosphere '
            . '- The characters are cheerful and excited about learning '
            . "\n\n"
            . 'IMPORTANT: Do NOT include any text, letters, characters, words, or writing in the image. '
            . 'No Chinese characters, no Korean text, no English text, no numbers. '
            . 'The image should be purely illustrative and decorative. '
            . 'Matte-finish, no watermarks, no logos.';

        self::log( 'Key expressions infographic prompt for lesson ' . $lesson_id );

        $result = self::generate( $prompt, '3:4' );
        if ( is_wp_error( $result ) ) {
            self::log( 'Key expr image 3:4 failed: ' . $result->get_error_message() );
            $result = self::generate( $prompt, '1:1' );
        }
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $saved = self::save_image( $result, 'key-expr-' . $lesson_id );
        if ( is_wp_error( $saved ) ) {
            return $saved;
        }

        update_post_meta( $lesson_id, '_dd_key_expr_image', $saved['url'] );
        return $saved['url'];
    }

    /**
     * 4패널 학습만화 스트립 이미지 생성 (카와이 스타일)
     */
    public static function generate_comic_strip( $lesson_id, $comic_data ) {
        if ( empty( $comic_data ) || ! is_array( $comic_data ) ) {
            return new WP_Error( 'no_comic_data', '만화 데이터가 없습니다.' );
        }

        $lesson = get_post( $lesson_id );
        $lesson_title = $lesson ? $lesson->post_title : '';
        $level = get_post_meta( $lesson_id, '_dd_lesson_level', true ) ?: 'beginner';

        $topic_cn = '';
        $topic_kr = $lesson_title;
        $key_exprs = json_decode( get_post_meta( $lesson_id, '_dd_key_expressions', true ), true ) ?: array();
        if ( ! empty( $key_exprs[0]['zh'] ) ) {
            $topic_cn = $key_exprs[0]['zh'];
        }

        $panels = array_slice( $comic_data, 0, 4 );
        while ( count( $panels ) < 4 ) {
            $panels[] = array( 'dialogue' => array(), 'characters' => array(), 'scene' => '', 'narration' => '' );
        }

        $chars_a = ! empty( $panels[0]['characters'][0] ) ? $panels[0]['characters'][0] : '지수';
        $chars_b = ! empty( $panels[0]['characters'][1] ) ? $panels[0]['characters'][1] : '叮叮';

        $genre = 'Chinese language learning / daily life';
        $scene_context = ! empty( $panels[0]['scene'] ) ? $panels[0]['scene'] : 'everyday conversation';

        $dialogues = array();
        foreach ( $panels as $pi => $panel ) {
            $dl = ! empty( $panel['dialogue'][0] ) ? $panel['dialogue'][0] : array();
            $cn = ! empty( $dl['zh'] ) ? $dl['zh'] : '';
            $kr = ! empty( $dl['ko'] ) ? $dl['ko'] : '';
            $dialogues[] = array( 'cn' => $cn, 'kr' => $kr );
        }

        $decor_list = 'Chinese lanterns, tea cups, chopsticks, dumplings, books, pencils, hearts';

        $prompt = 'A vertical 4-panel comic strip presented as a single page composition, '
            . 'using a vibrant and cohesive "kawaii" Japanese sticker sheet style with clean black outlines. '
            . 'The overall aesthetic is cheerful, pastel-colored, and dense with a varied collage of individual, '
            . 'well-defined elements on soft backgrounds. The borders are star-shaped.'
            . "\n\n"
            . '[GENRE-SPECIFIC SETTING] '
            . 'The entire composition, including backgrounds, attire, and objects, is styled to match the '
            . $genre . ' theme. '
            . 'Characters: a young Korean woman in casual modern attire, '
            . 'and a young Chinese woman in casual modern attire. Both must be human. '
            . 'Characters are designed to strictly match the requested gender and attire.'
            . "\n\n"
            . '[4 SEQUENTIAL COMIC PANELS] '
            . 'The four sequential grid panels depict scenes from a learning story. '
            . 'Show empty speech bubbles to indicate conversation, but do NOT write any text inside them.'
            . "\n\n";

        $panel_positions = array( 'Top-Left', 'Top-Right', 'Bottom-Left', 'Bottom-Right' );
        $char_cycle = array( $chars_a, $chars_b, $chars_a, $chars_b );

        foreach ( $panels as $pi => $panel ) {
            $pos  = $panel_positions[ $pi ];
            $char = $char_cycle[ $pi ];
            $desc = ! empty( $panel['narration'] ) ? $panel['narration'] : ( ! empty( $panel['scene'] ) ? $panel['scene'] : 'talking' );
            $prompt .= '- Panel ' . ( $pi + 1 ) . ' (' . $pos . '): ' . $char . ' ' . $desc . '. '
                . 'Empty speech bubble (no text inside).'
                . "\n";
        }

        $prompt .= "\n"
            . '[GENRE-MATCHING DECORATIVE ELEMENTS] '
            . 'Intersperse the spaces between panels and filling the border with cute, genre-matching decorative sticker elements: '
            . 'small icons of ' . $decor_list . ', paper clips, notebooks, stars, and sparkles.'
            . "\n\n"
            . 'The entire composition is perfectly balanced with no large empty spaces, creating a playful, '
            . 'immersive learning material. Matte-finish, no external watermarks, no logos.'
            . "\n\n"
            . 'CRITICAL: Do NOT include any text, letters, characters, or writing of any kind in the image. '
            . 'No Chinese characters, no Korean text, no English text. '
            . 'Speech bubbles must be completely empty. The image is purely visual illustration.';

        self::log( 'Comic strip prompt for lesson ' . $lesson_id . ': ' . mb_substr( $prompt, 0, 300 ) . '...' );

        $result = self::generate( $prompt, '3:4' );
        if ( is_wp_error( $result ) ) {
            self::log( 'Comic strip 3:4 failed, trying 9:16: ' . $result->get_error_message() );
            $result = self::generate( $prompt, '9:16' );
        }
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $saved = self::save_image( $result, 'comic-strip-' . $lesson_id );
        if ( is_wp_error( $saved ) ) {
            return $saved;
        }

        update_post_meta( $lesson_id, '_dd_comic_strip_image', $saved['url'] );
        return $saved['url'];
    }

    /**
     * 스토리북 이미지 스타일 감지
     * 학습 내용이 현대적이면 현대적 일러스트, 전통적이면 수채화 스타일
     */
    private static function detect_storybook_style( $pages ) {
        $all_prompts = '';
        foreach ( $pages as $p ) {
            $all_prompts .= ' ' . ( $p['image_prompt'] ?? '' ) . ' ' . ( $p['text_zh'] ?? '' ) . ' ' . ( $p['text_ko'] ?? '' );
        }
        $all_prompts = mb_strtolower( $all_prompts );

        // 현대 키워드 감지
        $modern_keywords = array(
            'phone', 'smartphone', 'app', 'internet', 'wifi', 'computer', 'laptop', 'tablet',
            'café', 'cafe', 'coffee', 'subway', 'metro', 'bus', 'taxi', 'uber', 'didi',
            'delivery', 'express', 'courier', 'shopping', 'mall', 'store', 'supermarket',
            'office', 'company', 'meeting', 'email', 'presentation',
            'social media', 'wechat', 'weibo', 'tiktok', 'douyin', 'bilibili',
            'movie', 'cinema', 'concert', 'gym', 'fitness', 'yoga',
            'apartment', 'hotel', 'restaurant', 'bar', 'club',
            'modern', 'contemporary', 'urban', 'city', 'downtown',
            '핸드폰', '스마트폰', '앱', '인터넷', '컴퓨터', '노트북',
            '카페', '커피', '지하철', '버스', '택시', '배달', '쇼핑',
            '회사', '사무실', '회의', '이메일',
            'SNS', '위챗', '웨이보', '틱톡', '더우인',
            '영화', '콘서트', '헬스', '레스토랑', '호텔',
            '현대', '도시', '아파트',
            '手机', '电脑', '网络', '咖啡', '地铁', '公交', '外卖',
            '公司', '办公', '电影', '健身', '酒店', '餐厅', '购物',
        );

        $modern_score = 0;
        foreach ( $modern_keywords as $kw ) {
            if ( mb_strpos( $all_prompts, $kw ) !== false ) {
                $modern_score++;
            }
        }

        // 3개 이상의 현대 키워드 → 현대적 스타일
        if ( $modern_score >= 2 ) {
            return array(
                'prefix' => 'Create a vibrant modern illustration for a Chinese language storybook. ',
                'suffix' => ' Style: Clean modern flat illustration, bright vivid colors, contemporary urban setting, warm lighting. No text, words, or letters in the image.',
            );
        }

        return array(
            'prefix' => 'Create a beautiful illustration for a Chinese language storybook. ',
            'suffix' => ' Style: Watercolor painting, soft pastel colors, gentle brushstrokes, storybook illustration. No text, words, or letters in the image.',
        );
    }

    public static function generate_storybook_images( $lesson_id, $pages ) {
        if ( empty( $pages ) || ! is_array( $pages ) ) {
            return $pages;
        }

        // 쿼터 초과 상태면 이미지 생성 전체 스킵
        if ( self::is_quota_exhausted() ) {
            self::log( 'Storybook images skipped — quota exhausted' );
            foreach ( $pages as &$p ) { $p['image_url'] = ''; }
            unset( $p );
            return $pages;
        }

        // 스타일 자동 감지 (현대적 vs 전통적)
        $style = self::detect_storybook_style( $pages );
        self::log( 'Storybook style: ' . ( strpos( $style['prefix'], 'modern' ) !== false ? 'modern' : 'traditional' ) );

        foreach ( $pages as $i => &$page ) {
            // 중간에 쿼터 소진되면 나머지 스킵
            if ( self::is_quota_exhausted() ) {
                $page['image_url'] = '';
                continue;
            }

            $prompt = ! empty( $page['image_prompt'] ) ? $page['image_prompt'] : '';
            if ( empty( $prompt ) ) {
                $page['image_url'] = '';
                continue;
            }

            $full_prompt = $style['prefix'] . $prompt . $style['suffix'];

            $result = self::generate( $full_prompt, '3:4' );
            if ( is_wp_error( $result ) ) {
                $result = self::generate( $full_prompt, '1:1' );
            }
            if ( is_wp_error( $result ) ) {
                $page['image_url'] = '';
                continue;
            }

            $saved = self::save_image( $result, 'storybook-' . $lesson_id . '-' . ( $i + 1 ) );
            $page['image_url'] = is_wp_error( $saved ) ? '' : $saved['url'];
        }
        unset( $page );

        return $pages;
    }
}
