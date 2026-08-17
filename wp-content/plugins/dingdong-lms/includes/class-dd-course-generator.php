<?php
class DD_Course_Generator {

    public static function generate_outline( $topic, $lesson_count = 4, $level = 'beginner' ) {
        self::log( "=== generate_outline START: topic={$topic}, count={$lesson_count}, level={$level}" );

        $system = '당신은 한국인을 위한 중국어·중국문화 교육 전문가입니다. 반드시 유효한 JSON만 출력하세요. 설명이나 마크다운은 넣지 마세요.' . "\n" . DD_Chinese::PROMPT_RULE;

        $prompt = sprintf(
            '주제: "%s"

아래 형식의 JSON을 출력하세요. JSON 외의 텍스트는 절대 포함하지 마세요.

{"title":"강좌 제목","description":"강좌 설명 2-3문장","topic_en":"English keyword for thumbnail image search (e.g. Chinese tea culture, Chinese calligraphy)","lessons":[{"order":1,"title":"강의 제목","summary":"1줄 요약"}]}

규칙:
- lessons 배열에 정확히 %d개 항목
- 한국어로 작성
- 난이도: 입문에서 심화 순서
- 어휘와 문법은 설정된 난이도의 HSK 급수 수준에 맞추세요 (입문=HSK 1~3급, 중급=HSK 4~6급, 고급=HSK 7~9급)
- topic_en: 강좌 주제를 영어로 요약한 키워드 (Pixabay 이미지 검색용, 2-4 단어)',
            $topic,
            $lesson_count
        );

        $result = DD_Gemini::generate( $prompt, $system );

        if ( is_wp_error( $result ) ) {
            self::log( 'Outline Gemini error: ' . $result->get_error_message() );
            return $result;
        }

        if ( ! is_array( $result ) || empty( $result['title'] ) || empty( $result['lessons'] ) ) {
            self::log( 'Outline bad format: ' . ( is_array( $result ) ? wp_json_encode( array_keys( $result ) ) : gettype( $result ) ) );
            return new WP_Error( 'bad_outline', '강좌 개요 응답 형식이 올바르지 않습니다.' );
        }

        self::log( 'Outline OK: ' . $result['title'] . ', lessons=' . count( $result['lessons'] ) );

        $course_id = wp_insert_post( array(
            'post_type'    => 'dd_course',
            'post_title'   => sanitize_text_field( $result['title'] ),
            'post_content' => sanitize_textarea_field( $result['description'] ?? '' ),
            'post_status'  => 'publish',
        ) );

        if ( is_wp_error( $course_id ) ) {
            return $course_id;
        }

        update_post_meta( $course_id, '_dd_course_status', 'generating' );
        update_post_meta( $course_id, '_dd_course_generated_at', current_time( 'mysql' ) );
        update_post_meta( $course_id, '_dd_course_total_lessons', count( $result['lessons'] ) );
        update_post_meta( $course_id, '_dd_course_level', $level );

        // --- 썸네일 자동 가져오기 (Pixabay, 실패해도 계속) ---
        $thumbnail_url = '';
        $topic_en = isset( $result['topic_en'] ) ? $result['topic_en'] : '';
        try {
            if ( DD_Thumbnail::has_key() ) {
                $thumb_result = DD_Thumbnail::auto_fetch( $course_id, $topic, $topic_en );
                if ( ! is_wp_error( $thumb_result ) ) {
                    $thumbnail_url = $thumb_result;
                } else {
                    self::log( 'Thumbnail fetch error: ' . $thumb_result->get_error_message() );
                }
            }
        } catch ( \Exception $e ) {
            self::log( 'Thumbnail exception: ' . $e->getMessage() );
        } catch ( \Error $e ) {
            self::log( 'Thumbnail fatal: ' . $e->getMessage() );
        }

        return array(
            'course_id'   => $course_id,
            'title'       => $result['title'],
            'description' => $result['description'] ?? '',
            'thumbnail'   => $thumbnail_url,
            'lessons'     => $result['lessons'],
        );
    }

    /**
     * 에셋 생성 단계 목록 — 클라이언트가 이 순서대로 개별 요청을 보낸다.
     * 각 단계를 별도 HTTP 요청으로 나누는 이유는 CLAUDE.md 참조:
     * 한 요청에 Gemini 호출을 몰아넣으면 공유호스팅 프록시 타임아웃(60~300초)에
     * 걸려 브라우저가 HTML 502/504 를 받고 invalid_json 오류를 띄운다.
     */
    const ASSET_PHASES = array( 'key_expr_image', 'dialogue_image', 'comic_images', 'youtube', 'storybook_images' );

    /** 각 단계의 사용자 표시 이름 */
    public static function asset_phase_label( $phase ) {
        $labels = array(
            'key_expr_image'   => '핵심표현 이미지',
            'dialogue_image'   => '실전대화 이미지',
            'comic_images'     => '학습만화 4컷',
            'youtube'          => '관련 영상 검색',
            'storybook_images' => '스토리북 이미지',
        );
        return isset( $labels[ $phase ] ) ? $labels[ $phase ] : $phase;
    }

    /**
     * 전체 강의 생성 (본문 + 모든 에셋).
     * 한 요청에서 전부 처리하므로 오래 걸린다 — 웹 요청에서는
     * generate_lesson_text() + generate_lesson_assets() 를 나눠 쓸 것.
     * WP-CLI 등 타임아웃이 없는 환경을 위해 남겨둔다.
     */
    public static function generate_lesson( $course_id, $lesson_title, $order ) {
        $result = self::generate_lesson_text( $course_id, $lesson_title, $order );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        foreach ( self::ASSET_PHASES as $phase ) {
            $phase_result = self::generate_lesson_assets( $result['lesson_id'], $phase );
            if ( ! is_wp_error( $phase_result ) ) {
                $result = array_merge( $result, $phase_result['counts'] );
            }
        }

        $result['pending_assets'] = array();
        return $result;
    }

    /**
     * 1단계 — Gemini 본문 생성 + 강의 저장까지만.
     * 외부 호출이 Gemini 텍스트 1회(재시도 포함 최대 2회)뿐이라 짧게 끝난다.
     *
     * @param string $client_ref 클라이언트 멱등키. 응답이 유실되어도 이 값으로
     *                           이미 만들어진 강의를 되찾을 수 있다 (find_by_client_ref).
     */
    public static function generate_lesson_text( $course_id, $lesson_title, $order, $client_ref = '' ) {
        self::log( "=== generate_lesson START: course={$course_id}, title={$lesson_title}, order={$order}, ref={$client_ref}" );

        // 같은 client_ref 로 이미 생성된 강의가 있으면 재생성하지 않고 그대로 반환한다
        // (네트워크 재시도로 Gemini 를 두 번 태우지 않기 위함).
        if ( $client_ref !== '' ) {
            $existing = self::find_by_client_ref( $course_id, $client_ref );
            if ( $existing ) {
                self::log( "generate_lesson: client_ref 중복 — 기존 강의 {$existing} 반환" );
                return self::lesson_text_response( $existing, $client_ref, true );
            }
        }


        $course = get_post( $course_id );
        if ( ! $course ) {
            return new WP_Error( 'invalid_course', '강좌를 찾을 수 없습니다.' );
        }

        $level = get_post_meta( $course_id, '_dd_course_level', true ) ?: 'beginner';
        self::log( "Lesson level: {$level}" );

        $system = '당신은 한국인을 위한 중국어·중국문화 교육 콘텐츠 전문가입니다. 언어를 통해 문화적 맥락을 가르치고, 문화를 통해 언어를 가르치는 통합 교육 콘텐츠를 만듭니다. 반드시 유효한 JSON만 출력하세요. JSON 외의 텍스트는 절대 포함하지 마세요.

[절대 규칙] 이 플랫폼은 중국어 학습이 핵심입니다!
- content 안의 "## 실전 대화"에서 각 대화 줄은 반드시 중국어 한자(한자)를 포함해야 합니다.
- dialogues 배열의 zh 필드에는 반드시 중국어 한자만 넣으세요.
- 한국어만 있는 대화는 절대 허용되지 않습니다.
- 올바른 예: **지수:** 你好吗？ — 잘 지내?
- 잘못된 예: **지수:** 잘 지내? (중국어 없음 — 이렇게 하면 안됩니다!)

[한국인 학습자 오류 포인트] 한국인 학습자가 자주 혼동하는 지점(예: 把자문, 了의 완료/변화 용법, 양사, 是와 在의 구분)을 우선적으로 짚어주세요.
' . DD_Chinese::PROMPT_RULE . '';

        if ( $level === 'beginner' ) {
            $dialogue_format = '- "## 실전 대화": 반드시 아래 형식으로 작성 (각 대화를 새 줄에):
  **A:** 你好吗？ (nǐ hǎo ma?) — 잘 지내?
  **B:** 我很好，谢谢！ (wǒ hěn hǎo, xièxie!) — 잘 지내, 고마워!
  주의: 中文台词는 반드시 한자(中文字)로 작성! 절대 pinyin으로 대체하지 마세요.
  형식: **이름:** 한자문장 (병음) — 한국어
  [중요] 대화 작성 지침:
  - 대화는 8-10턴 (A/B 번갈아 가며), 하나의 완결된 대화 시나리오로 구성
  - 단편적인 인사/단답이 아니라, 실제 상황에서의 자연스러운 대화 흐름
  - 각 턴은 완성된 문장 1-2개로 구성 (너무 짧은 단답 금지)
  - 대화 속에 핵심 표현과 문법이 자연스럽게 반복 사용되어야 함
  - 대화 흐름: 인사→화제 도입→정보 교환→감정 표현→마무리 순서로 전개';
            $dialogue_example = '**예문:** 你好吗？ (nǐ hǎo ma?) — 잘 지내?';
            $level_note = '난이도: 입문 — 모든 중국어에 병음 표기';
        } else {
            $dialogue_format = '- "## 실전 대화": 반드시 아래 형식으로 작성 (각 대화를 새 줄에):
  **A:** 你好吗？ — 잘 지내?
  **B:** 我很好，谢谢！ — 잘 지내, 고마워!
  주의: 中文台词는 반드시 한자(中文字)로 작성! 절대 pinyin으로 대체하지 마세요.
  형식: **이름:** 한자문장 — 한국어 (병음 없이, 중국어와 한국어만)
  [중요] 대화 작성 지침:
  - 대화는 8-10턴 (A/B 번갈아 가며), 하나의 완결된 대화 시나리오로 구성
  - 단편적인 인사/단답이 아니라, 실제 상황에서의 자연스러운 대화 흐름
  - 각 턴은 완성된 문장 1-2개로 구성 (너무 짧은 단답 금지)
  - 대화 속에 핵심 표현과 문법이 자연스럽게 반복 사용되어야 함
  - 대화 흐름: 인사→화제 도입→정보 교환→감정 표현→마무리 순서로 전개';
            $dialogue_example = '**예문:** 你好吗？ — 잘 지내?';
            $level_note = $level === 'intermediate'
                ? '난이도: 중급 — 실전 대화에서 병음 제외, 한자와 한국어만 표시'
                : '난이도: 고급 — 실전 대화에서 병음 제외, 한자와 한국어만 표시';
        }

        $prompt = sprintf(
            '강좌명: "%s"
강의 제목: "%s"
%s

아래 JSON 구조를 정확히 따라 강의 콘텐츠를 생성하세요.
JSON만 출력하고 다른 텍스트는 포함하지 마세요.

[중요] 모든 중국어 문장은 반드시 한자(中文字)로 작성하세요! pinyin(병음)을 중국어 대신 쓰지 마세요.
올바른 예: "你好" (nǐ hǎo) — 안녕
잘못된 예: "nǐ hǎo" — 안녕 (한자 없이 병음만 쓴 것은 오류!)

{
  "content": "강의 본문 (마크다운 형식, 최소 500자)",
  "key_expressions": [
    {"zh": "你好", "pinyin": "nǐ hǎo", "ko": "안녕하세요", "hsk": 1}
  ],
  "cultural_note": {
    "summary": "문화 배경 요약 1-2문장",
    "background": "이 주제와 관련된 중국 문화/역사 배경 설명 3-4문장",
    "fun_facts": ["재미있는 사실 1", "재미있는 사실 2"],
    "comparison": "한국 문화와의 비교 1-2문장",
    "related_expression": {"zh": "관련 중국어 표현", "pinyin": "병음", "ko": "한국어 뜻"},
    "did_you_know": "알고 있었나요? 흥미로운 한 줄 트리비아"
  },
  "dialogue_scene": "실전 대화 장면의 시각적 묘사 (이미지 생성용, 영어로 작성)",
  "comic_panels": [
    {
      "scene": "장소/상황 설명",
      "characters": ["지수", "叮叮"],
      "dialogue": [
        {"speaker": "지수", "zh": "你好！", "pinyin": "nǐ hǎo!", "ko": "안녕!"}
      ],
      "narration": "이 칸의 상황 설명 (한국어)",
      "image_prompt": "이 패널의 시각적 묘사 (이미지 생성용, 영어로 작성)"
    }
  ],
  "dialogues": [
    {"speaker": "지수", "zh": "你好！最近怎么样？", "pinyin": "nǐ hǎo! zuìjìn zěnmeyàng?", "ko": "안녕! 요즘 어때?"},
    {"speaker": "叮叮", "zh": "我很好，谢谢！", "pinyin": "wǒ hěn hǎo, xièxie!", "ko": "잘 지내, 고마워!"}
  ],
  "video_keywords": ["YouTube 검색 키워드(한국어)", "YouTube 검색 키워드(중국어)"],
  "slides": [
    {
      "title": "슬라이드 제목",
      "subtitle": "부제목",
      "key_point": "이 슬라이드의 핵심 학습 포인트를 2-3문장으로 상세 설명. 학생이 이것만 읽어도 무엇을 배우는지 이해할 수 있어야 함",
      "bullets": ["내용1", "내용2", "내용3", "내용4"],
      "vocab": [{"zh": "你好", "pinyin": "nǐ hǎo", "ko": "안녕하세요"}],
      "examples": [{"zh": "你好吗？", "pinyin": "nǐ hǎo ma?", "ko": "잘 지내?"}],
      "usage_context": "언제/어디서 사용하는지 1-2문장 (예: 일상 인사, 비즈니스 미팅, 친구와 격식 없는 대화)",
      "common_mistake": "한국 학습자가 자주 틀리는 부분 1-2문장 (한국어와의 차이, 발음 함정, 어순 등)",
      "practice": {"question_ko": "한국어로 된 짧은 연습 문제 (번역/빈칸/대답하기)", "answer_zh": "정답 한자", "answer_pinyin": "정답 병음", "answer_ko": "정답 한국어 해설"},
      "tip": "학습 팁"
    }
  ],
  "quiz": [
    {"type":"choice", "question_ko": "한국어 질문", "question_zh": "中文问题", "options": ["A", "B", "C", "D"], "correct": 0, "explanation": "해설"},
    {"type":"fill", "question_ko": "빈칸에 알맞은 단어는?", "sentence_zh": "我___去中国。", "answer": "想要", "hint": "원하다", "explanation": "해설"},
    {"type":"order", "question_ko": "올바른 어순으로 배열하세요", "words": ["去","我","中国","想"], "correct_order": [1,3,0,2], "answer_text": "我想去中国", "explanation": "해설"}
  ],
  "storybook_pages": [
    {
      "page": 1,
      "text_zh": "지수来到了中国。她很开心。",
      "pinyin": "Zhīxiù lái dào le Zhōngguó. Tā hěn kāixīn.",
      "text_ko": "지수는 중국에 왔다. 그녀는 매우 기뻤다.",
      "image_prompt": "Warm watercolor illustration of a young Korean woman arriving at a Chinese airport, looking excited. Soft pastel colors, storybook style."
    }
  ],
  "vocab_comparison": [
    {
      "word_a": {"zh": "看", "pinyin": "kàn", "ko": "보다"},
      "word_b": {"zh": "见", "pinyin": "jiàn", "ko": "보이다/만나다"},
      "diff": "看은 의도적 행위, 见은 결과적 인지. 看书=책을 읽다, 看见=보이다",
      "example_a": {"zh": "我在看电视。", "ko": "TV를 보고 있어."},
      "example_b": {"zh": "我看见他了。", "ko": "그를 봤어."}
    }
  ],
  "cultural_snippet": {
    "title": "문화 한 토막 제목 (한국어)",
    "content": "중국 문화에 대한 흥미로운 이야기 3-5문장 (한국어)",
    "related_expression": {"zh": "관련 중국어 표현", "pinyin": "병음", "ko": "한국어 뜻"}
  }
}

=== content 작성 규칙 ===
- 마크다운 형식 (##, ###, **, -, 등)
- 구조: ## 도입 → ## 핵심 표현 → ## 문법 포인트 → ## 실전 대화 → ## 문화 속 언어
- [중요] 중국어는 반드시 한자(中文字)로! 예: 你好, 谢谢, 中国 (pinyin인 nǐ hǎo, xièxie 등을 중국어 대신 사용하지 마세요)
- 중국어 예문마다 병음과 한국어 번역 필수
- "## 핵심 표현": 각 표현을 **굵게** 표시하고, 반드시 각 표현별로 (1) 상세 의미 설명 1-2문장, (2) 예시 문장 2개 (중국어 + 한국어 번역) 포함. 단순 나열 금지!
- "## 문법 포인트": 문법 설명 후 반드시 예시 문장 2-3개를 별도 줄에 작성. 형식:
  - %s
%s
- "## 문화 속 언어": 문화적 맥락에서의 표현 설명
- 어휘와 문법은 설정된 난이도의 HSK 급수 수준에 맞추세요 (입문=HSK 1~3급, 중급=HSK 4~6급, 고급=HSK 7~9급)
- 최소 500자

=== key_expressions 작성 규칙 ===
- 이 강의에서 배우는 핵심 단어/표현 6-9개
- 각 항목에 zh(반드시 한자!), pinyin, ko, hsk(급수 1-9) 필수
- hsk 급수는 《国际中文教育中文水平等级标准》(2021) 기준
- 가장 중요한 순서대로 나열

=== cultural_note 작성 규칙 (풍부한 문화 노트) ===
- summary: 전체 문화 주제를 요약하는 1-2문장
- background: 이 강의 주제와 관련된 중국 문화/역사 배경 3-4문장. 구체적인 사례나 숫자 포함
- fun_facts: 흥미로운 사실 2개 (짧은 문장). 학습자가 "오!" 할 만한 내용
- comparison: 한국 문화와 비교 1-2문장. 유사점과 차이점
- related_expression: 이 문화와 관련된 중국어 관용구/사자성어/표현 1개 (zh 한자/pinyin/ko)
- did_you_know: "알고 있었나요?" 스타일의 흥미로운 트리비아 1줄
- 전체적으로 읽기 재미있게, 교과서적이지 않게 작성

=== dialogue_scene 작성 규칙 ===
- 영어로 작성 (이미지 생성 AI용)
- "실전 대화"의 장면을 시각적으로 묘사
- 형식: "Two young people (a Korean woman and a Chinese man) [동작/상황], in [장소]. Warm lighting, anime illustration style, soft colors."
- 인물의 표정, 동작, 배경을 구체적으로

=== comic_panels 작성 규칙 ===
- 정확히 4개 패널
- cultural_note의 문화 내용을 일상 대화로 재현
- 캐릭터: 한국인 학습자(지수) + 중국인 친구(叮叮) 고정
- 각 패널에 1-2개 대화, zh(반드시 한자!)/pinyin/ko 필수
- narration: 한국어 상황 설명
- image_prompt: 영어로 해당 패널의 시각적 묘사 ("Anime style illustration of [장면]. Soft pastel colors, clean lines.")

=== dialogues 작성 규칙 (필수!!) ===
- "## 실전 대화" 내용을 구조화한 배열. 8-10개 항목
- 각 항목에 speaker(화자명), zh(반드시 한자!), pinyin, ko 필수
- zh에는 반드시 중국어 한자 문장! 한국어를 넣으면 안 됩니다!
- ko에는 한국어 번역
- 이 데이터는 content의 "## 실전 대화" 섹션과 동일한 대화 내용이어야 합니다
- [중요] zh 필드에 한국어(hangul)가 들어가면 절대 안 됩니다!! 반드시 중국어 한자(hanzi)만!!
- 예: {"speaker": "지수", "zh": "你好！", "pinyin": "nǐ hǎo!", "ko": "안녕!"}

=== video_keywords 작성 규칙 ===
- 정확히 2개. YouTube에서 한국어 또는 중국어 교육 영상을 찾기 위한 검색어
- 1번: 한국어 (예: "중국 차 문화 설명")
- 2번: 중국어 (예: "中国茶文化介绍")
- 영어 키워드는 포함하지 마세요 (한국어/중국어 영상만 검색)
- 5분 이상의 교육/설명 영상을 찾을 수 있는 키워드

=== slides (자가학습 가능한 마이크로 강의) ===
- 정확히 5장. 학생이 슬라이드만 봐도 완전히 학습 가능하도록 풍부하게 작성!
- 각 슬라이드는 "독립된 한 강의"라고 생각하고 다음 8개 필드 모두 포함 (절대 생략 금지):
  - title: 슬라이드 제목
  - subtitle: 부제목 (1줄, 핵심 메시지)
  - key_point: 이 슬라이드의 핵심 학습 포인트를 2-3문장 (필수!). 단순 나열 X, 왜 이게 중요한지, 무엇을 이해해야 하는지 친절한 강의 톤으로 서술
  - bullets: 핵심 내용 4-5개 (포인트 정리)
  - vocab: 반드시 3-4개 어휘 [{zh(한자), pinyin, ko}, ...]
  - examples: 반드시 2-3개 예문 [{zh(한자), pinyin, ko}, ...]
  - usage_context: 이 표현을 언제/어디서 쓰는지 1-2문장 (예: "친구와 격식 없는 대화에서", "비즈니스 이메일 시작 인사로")
  - common_mistake: 한국 학습자가 자주 틀리는 부분 1-2문장. 한국어와의 어순/발음/뉘앙스 차이 등 구체적으로 (예: 한국어 어미와 달리 중국어는 시제 표시가 없어서 了를 빠뜨리기 쉬움)
  - practice: 직접 연습 문제 1개 {"question_ko": "한국어 문제(번역/빈칸/대답하기)", "answer_zh": "정답 한자", "answer_pinyin": "병음", "answer_ko": "한국어 해설 1줄"}
  - tip: 추가 학습 팁 1줄 (key_point/usage_context와 중복되지 않게)
- 슬라이드 주제 배분: ① 도입/개요 ② 핵심 표현 ③ 문법 포인트 ④ 실전 활용 ⑤ 정리/복습
- [중요] 어휘와 문법·예문 난이도는 반드시 설정된 난이도(%s)의 HSK 급수 수준에 맞추세요!
  - 입문: HSK 1~3급 어휘, 간단한 문장
  - 중급: HSK 4~6급 어휘, 복합 문장, 관용 표현
  - 고급: HSK 7~9급 어휘, 성어/숙어, 서면어체, 뉴스/비즈니스 표현

=== quiz ===
- 정확히 6개. 3가지 유형을 섞어 출제:
  - type "choice": 4지선다 {type:"choice", question_ko, question_zh(한자!), options:[4개], correct:0-3, explanation}
  - type "fill": 빈칸 채우기 {type:"fill", question_ko, sentence_zh:"我___去中国。", answer:"想要", hint:"원하다", explanation}
  - type "order": 어순 배열 {type:"order", question_ko, words:["去","我","中国","想"], correct_order:[1,3,0,2], answer_text:"我想去中国", explanation}
- 배분: choice 2개, fill 2개, order 2개
- 난이도는 설정된 레벨(%s)의 HSK 급수 수준에 맞추세요 (입문=HSK 1~3급, 중급=HSK 4~6급, 고급=HSK 7~9급)

=== storybook_pages 작성 규칙 ===
- 정확히 6페이지. 이 강의의 핵심 표현과 문화를 담은 짧은 이야기
- 캐릭터: 한국인 학습자(지수)가 중국 여행/생활 중 겪는 이야기
- text_zh: 간결한 중국어 한자문장 (HSK 3-4급 수준, 2-3문장)
- pinyin: text_zh의 전체 병음
- text_ko: 자연스러운 한국어 번역
- image_prompt: 영어로 시각적 묘사. "Warm watercolor children\'s book illustration of [장면]. Soft pastel colors, gentle brushstrokes, storybook style."
- 이야기 흐름: 시작 → 전개 → 절정 → 해결 (기승전결)
- 이 강의의 핵심 표현을 자연스럽게 이야기 속에 포함

=== vocab_comparison 작성 규칙 (오디오북 부가 학습) ===
- 2-3개. 이 강의 핵심 표현 중 혼동하기 쉬운 단어 쌍을 비교
- 각 항목에 word_a, word_b (zh 한자/pinyin/ko), diff (차이점 설명, 한국어 2-3문장), example_a, example_b (zh 한자/ko)
- 비교 대상: 의미가 비슷하지만 용법이 다른 단어, 형태가 비슷한 단어, 한국어 번역은 같지만 중국어 뉘앙스가 다른 단어

=== cultural_snippet 작성 규칙 (오디오북 부가 학습) ===
- 이 강의 주제와 관련된 중국 문화 이야기 1개
- title: 한국어 제목 (흥미를 끌 수 있는 제목)
- content: 한국어 3-5문장. 문화적 배경, 재미있는 사실, 한국과의 비교 등
- related_expression: 이 문화와 관련된 중국어 표현 1개 (zh 한자/pinyin/ko)

=== 교육 철학 ===
- 언어를 통해 문화, 문화를 통해 언어를 배운다
- 실제 상황에서의 자연스러운 사용을 강조',
            $course->post_title,
            $lesson_title,
            $level_note,
            $dialogue_example,
            $dialogue_format,
            $level,
            $level
        );

        self::log( 'Lesson Gemini call start...' );
        $result    = null;
        $last_err  = null;
        $max_tries = 2;

        for ( $try = 1; $try <= $max_tries; $try++ ) {
            $result = DD_Gemini::generate( $prompt, $system );

            if ( is_wp_error( $result ) ) {
                $last_err = $result;
                self::log( "Lesson Gemini attempt {$try} error: " . $result->get_error_message() );
                if ( $try < $max_tries ) {
                    continue;
                }
                return $last_err;
            }

            if ( ! is_array( $result ) ) {
                self::log( "Lesson attempt {$try} bad format: " . gettype( $result ) );
                if ( $try < $max_tries ) {
                    continue;
                }
                return new WP_Error( 'bad_lesson', '강의 콘텐츠 응답이 올바르지 않습니다.' );
            }

            break;
        }

        self::log( 'Lesson Gemini OK, keys: ' . implode( ',', array_keys( $result ) ) );

        $content         = isset( $result['content'] ) && is_string( $result['content'] ) ? $result['content'] : '';
        /* cultural_note: 새 구조(배열) 또는 기존 문자열 모두 지원 */
        $cultural_note   = '';
        if ( isset( $result['cultural_note'] ) ) {
            if ( is_array( $result['cultural_note'] ) ) {
                $cultural_note = wp_json_encode( $result['cultural_note'], JSON_UNESCAPED_UNICODE );
            } elseif ( is_string( $result['cultural_note'] ) ) {
                $cultural_note = $result['cultural_note'];
            }
        }
        $key_expressions = isset( $result['key_expressions'] ) && is_array( $result['key_expressions'] ) ? $result['key_expressions'] : array();
        $dialogue_scene  = isset( $result['dialogue_scene'] ) && is_string( $result['dialogue_scene'] ) ? $result['dialogue_scene'] : '';
        $slides          = isset( $result['slides'] ) && is_array( $result['slides'] ) ? $result['slides'] : array();
        $quiz            = isset( $result['quiz'] ) && is_array( $result['quiz'] ) ? $result['quiz'] : array();
        $comic_panels    = isset( $result['comic_panels'] ) && is_array( $result['comic_panels'] ) ? $result['comic_panels'] : array();
        $video_keywords  = isset( $result['video_keywords'] ) && is_array( $result['video_keywords'] ) ? $result['video_keywords'] : array();
        $storybook_pages = isset( $result['storybook_pages'] ) && is_array( $result['storybook_pages'] ) ? $result['storybook_pages'] : array();
        $dialogues_data   = isset( $result['dialogues'] ) && is_array( $result['dialogues'] ) ? $result['dialogues'] : array();
        $vocab_comparison = isset( $result['vocab_comparison'] ) && is_array( $result['vocab_comparison'] ) ? $result['vocab_comparison'] : array();
        $cultural_snippet = isset( $result['cultural_snippet'] ) && is_array( $result['cultural_snippet'] ) ? $result['cultural_snippet'] : array();

        // --- 실전 대화 검증: 구조화 데이터로 content의 ## 실전 대화 섹션 재구축 ---
        if ( ! empty( $dialogues_data ) ) {
            $content = self::rebuild_dialogue_section( $content, $dialogues_data, $level );
        } elseif ( ! empty( $comic_panels ) ) {
            // dialogues 배열이 없으면 comic_panels에서 대화 추출
            $fallback_dialogues = array();
            foreach ( $comic_panels as $panel ) {
                if ( ! empty( $panel['dialogue'] ) && is_array( $panel['dialogue'] ) ) {
                    foreach ( $panel['dialogue'] as $dl ) {
                        if ( ! empty( $dl['zh'] ) ) {
                            $fallback_dialogues[] = $dl;
                        }
                    }
                }
            }
            if ( ! empty( $fallback_dialogues ) ) {
                $dialogues_data = $fallback_dialogues;
                $content = self::rebuild_dialogue_section( $content, $dialogues_data, $level );
                self::log( 'rebuild_dialogue: used comic_panels fallback (' . count( $fallback_dialogues ) . ' lines)' );
            }
        }

        $lesson_id = wp_insert_post( array(
            'post_type'    => 'dd_lesson',
            'post_title'   => $lesson_title,
            'post_content' => $content,
            'post_status'  => 'publish',
        ) );

        if ( is_wp_error( $lesson_id ) ) {
            return $lesson_id;
        }

        $token = wp_generate_uuid4();

        update_post_meta( $lesson_id, '_dd_course_id', $course_id );
        update_post_meta( $lesson_id, '_dd_lesson_order', $order );
        update_post_meta( $lesson_id, '_dd_slides_data', wp_json_encode( $slides, JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $lesson_id, '_dd_quiz_data', wp_json_encode( $quiz, JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $lesson_id, '_dd_cultural_note', $cultural_note );
        update_post_meta( $lesson_id, '_dd_key_expressions', wp_json_encode( $key_expressions, JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $lesson_id, '_dd_dialogue_scene', $dialogue_scene );
        update_post_meta( $lesson_id, '_dd_comic_data', wp_json_encode( $comic_panels, JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $lesson_id, '_dd_video_keywords', wp_json_encode( $video_keywords, JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $lesson_id, '_dd_storybook_data', wp_json_encode( $storybook_pages, JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $lesson_id, '_dd_dialogues_data', wp_json_encode( $dialogues_data, JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $lesson_id, '_dd_vocab_comparison', wp_json_encode( $vocab_comparison, JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $lesson_id, '_dd_cultural_snippet', wp_json_encode( $cultural_snippet, JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $lesson_id, '_dd_public_token', $token );
        update_post_meta( $lesson_id, '_dd_public_active', '1' );
        update_post_meta( $lesson_id, '_dd_lesson_level', $level );
        update_post_meta( $lesson_id, '_dd_video_urls', wp_json_encode( array(), JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $lesson_id, '_dd_embed_urls', wp_json_encode( array(), JSON_UNESCAPED_UNICODE ) );

        if ( $client_ref !== '' ) {
            update_post_meta( $lesson_id, '_dd_client_ref', $client_ref );
        }

        $lessons_done  = self::count_lessons( $course_id );
        $total_planned = (int) get_post_meta( $course_id, '_dd_course_total_lessons', true );
        if ( $total_planned > 0 && $lessons_done >= $total_planned ) {
            update_post_meta( $course_id, '_dd_course_status', 'complete' );
        }

        self::log( "=== generate_lesson TEXT DONE: lesson_id={$lesson_id}" );

        return self::lesson_text_response( $lesson_id, $client_ref, false );
    }

    /**
     * 본문 생성 응답 포맷 — 신규 생성과 client_ref 복구가 같은 모양을 반환하도록 한 곳에서 만든다.
     */
    private static function lesson_text_response( $lesson_id, $client_ref, $recovered ) {
        $lesson = get_post( $lesson_id );
        if ( ! $lesson ) {
            return new WP_Error( 'lesson_missing', '강의를 찾을 수 없습니다.' );
        }

        $meta_array = function ( $key ) use ( $lesson_id ) {
            $decoded = json_decode( get_post_meta( $lesson_id, $key, true ), true );
            return is_array( $decoded ) ? $decoded : array();
        };

        return array(
            'lesson_id'       => (int) $lesson_id,
            'token'           => get_post_meta( $lesson_id, '_dd_public_token', true ),
            'title'           => $lesson->post_title,
            'order'           => (int) get_post_meta( $lesson_id, '_dd_lesson_order', true ),
            'client_ref'      => $client_ref,
            'recovered'       => (bool) $recovered,
            'has_content'     => trim( (string) $lesson->post_content ) !== '',
            'slide_count'     => count( $meta_array( '_dd_slides_data' ) ),
            'quiz_count'      => count( $meta_array( '_dd_quiz_data' ) ),
            'comic_count'     => count( $meta_array( '_dd_comic_data' ) ),
            'storybook_count' => count( $meta_array( '_dd_storybook_data' ) ),
            'pending_assets'  => self::ASSET_PHASES,
        );
    }

    /**
     * 복구 조회용 응답 — find_by_client_ref() 로 찾은 강의를 본문 생성과 같은 모양으로 돌려준다.
     */
    public static function lesson_lookup_response( $lesson_id, $client_ref ) {
        return self::lesson_text_response( $lesson_id, $client_ref, true );
    }

    /**
     * client_ref 로 이미 만들어진 강의를 찾는다.
     * 응답이 프록시 타임아웃으로 유실되어도 클라이언트가 결과를 회수할 수 있게 해 준다.
     *
     * @return int|null 강의 ID, 없으면 null
     */
    public static function find_by_client_ref( $course_id, $client_ref ) {
        if ( $client_ref === '' ) {
            return null;
        }

        $q = new WP_Query( array(
            'post_type'      => 'dd_lesson',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => array(
                'relation' => 'AND',
                array( 'key' => '_dd_client_ref', 'value' => $client_ref ),
                array( 'key' => '_dd_course_id', 'value' => (int) $course_id ),
            ),
        ) );

        return ! empty( $q->posts ) ? (int) $q->posts[0] : null;
    }

    /**
     * 2단계 — 에셋을 단계 하나씩만 생성한다.
     * 실패해도 강의 본문은 이미 저장돼 있으므로 치명적이지 않다.
     *
     * @param string $phase self::ASSET_PHASES 중 하나
     * @return array|WP_Error { phase, label, ok, message, counts }
     */
    public static function generate_lesson_assets( $lesson_id, $phase ) {
        $lesson = get_post( $lesson_id );
        if ( ! $lesson || $lesson->post_type !== 'dd_lesson' ) {
            return new WP_Error( 'invalid_lesson', '강의를 찾을 수 없습니다.' );
        }
        if ( ! in_array( $phase, self::ASSET_PHASES, true ) ) {
            return new WP_Error( 'invalid_phase', '알 수 없는 생성 단계입니다: ' . $phase );
        }

        $meta_array = function ( $key ) use ( $lesson_id ) {
            $decoded = json_decode( get_post_meta( $lesson_id, $key, true ), true );
            return is_array( $decoded ) ? $decoded : array();
        };

        $result = array(
            'phase'   => $phase,
            'label'   => self::asset_phase_label( $phase ),
            'ok'      => false,
            'skipped' => false,
            'message' => '',
            'counts'  => array(),
        );

        try {
            switch ( $phase ) {
                case 'key_expr_image':
                    $key_expressions = $meta_array( '_dd_key_expressions' );
                    if ( empty( $key_expressions ) ) {
                        $result['skipped'] = true;
                        break;
                    }
                    $url = DD_Image_Generator::generate_key_expressions_image( $lesson_id, $key_expressions, $lesson->post_title );
                    if ( is_wp_error( $url ) ) {
                        $result['message'] = $url->get_error_message();
                        self::log( 'Key expr image error: ' . $result['message'] );
                        break;
                    }
                    $result['ok'] = true;
                    $result['counts']['has_images'] = true;
                    break;

                case 'dialogue_image':
                    $scene = get_post_meta( $lesson_id, '_dd_dialogue_scene', true );
                    if ( empty( $scene ) ) {
                        $result['skipped'] = true;
                        break;
                    }
                    $url = DD_Image_Generator::generate_dialogue_image( $lesson_id, $scene );
                    if ( is_wp_error( $url ) ) {
                        $result['message'] = $url->get_error_message();
                        self::log( 'Dialogue image error: ' . $result['message'] );
                        break;
                    }
                    $result['ok'] = true;
                    $result['counts']['has_images'] = true;
                    break;

                case 'comic_images':
                    $comic_panels = $meta_array( '_dd_comic_data' );
                    if ( empty( $comic_panels ) ) {
                        $result['skipped'] = true;
                        break;
                    }
                    $urls = DD_Image_Generator::generate_comic_images( $lesson_id, $comic_panels );
                    $made = is_array( $urls ) ? count( array_filter( $urls ) ) : 0;
                    self::log( 'Comic panel images: ' . $made . '/' . count( $comic_panels ) );
                    $result['ok'] = $made > 0;
                    $result['counts']['comic_images'] = $made;
                    if ( $made > 0 ) {
                        $result['counts']['has_images'] = true;
                    } else {
                        $result['message'] = '만화 패널 이미지를 생성하지 못했습니다.';
                    }
                    break;

                case 'youtube':
                    $video_keywords = $meta_array( '_dd_video_keywords' );
                    if ( empty( $video_keywords ) ) {
                        $result['skipped'] = true;
                        break;
                    }
                    $embeds = DD_YouTube_Search::auto_embed_for_lesson( $lesson_id, array_slice( $video_keywords, 0, 2 ) );
                    if ( is_wp_error( $embeds ) ) {
                        $result['message'] = $embeds->get_error_message();
                        self::log( 'YouTube error: ' . $result['message'] );
                        break;
                    }
                    $result['ok'] = true;
                    $result['counts']['video_embeds'] = is_array( $embeds ) ? count( $embeds ) : 0;
                    break;

                case 'storybook_images':
                    $pages = $meta_array( '_dd_storybook_data' );
                    if ( empty( $pages ) ) {
                        $result['skipped'] = true;
                        break;
                    }
                    $pages = DD_Image_Generator::generate_storybook_images( $lesson_id, $pages );
                    update_post_meta( $lesson_id, '_dd_storybook_data', wp_json_encode( $pages, JSON_UNESCAPED_UNICODE ) );
                    $result['ok'] = true;
                    $result['counts']['storybook_count'] = count( $pages );
                    break;
            }
        } catch ( \Exception $e ) {
            $result['message'] = $e->getMessage();
            self::log( "Asset phase {$phase} exception: " . $e->getMessage() );
        } catch ( \Error $e ) {
            $result['message'] = $e->getMessage();
            self::log( "Asset phase {$phase} fatal: " . $e->getMessage() );
        }

        self::log( "Asset phase {$phase}: " . ( $result['ok'] ? 'OK' : ( $result['skipped'] ? 'SKIP' : 'FAIL — ' . $result['message'] ) ) );

        return $result;
    }

    public static function rebuild_dialogue_section_static( $content, $dialogues, $level ) {
        return self::rebuild_dialogue_section( $content, $dialogues, $level );
    }

    /**
     * 구조화된 대화 데이터로 content의 ## 실전 대화 섹션 재구축
     * Gemini가 한국어만 출력해도 structured dialogues에 중국어가 있으면 보정됨
     */
    private static function rebuild_dialogue_section( $content, $dialogues, $level ) {
        if ( empty( $dialogues ) ) {
            return $content;
        }

        // 구조화 데이터의 zh가 정말 중국어인지 검증
        $has_valid_zh = false;
        foreach ( $dialogues as $dl ) {
            $zh = $dl['zh'] ?? '';
            if ( ! empty( $zh ) && preg_match( '/[\x{4E00}-\x{9FFF}]/u', $zh ) ) {
                $has_valid_zh = true;
                break;
            }
        }

        if ( ! $has_valid_zh ) {
            self::log( 'rebuild_dialogue: structured dialogues have no valid Chinese text, skipping rebuild' );
            return $content;
        }

        // 새 대화 섹션 마크다운 생성
        $new_dialogue = "## 실전 대화\n\n";
        foreach ( $dialogues as $dl ) {
            $speaker = $dl['speaker'] ?? 'A';
            $zh      = $dl['zh'] ?? '';
            $pinyin  = $dl['pinyin'] ?? '';
            $ko      = $dl['ko'] ?? '';

            if ( empty( $zh ) ) {
                continue;
            }

            if ( $level === 'beginner' && ! empty( $pinyin ) ) {
                $new_dialogue .= "**{$speaker}:** {$zh} ({$pinyin}) — {$ko}\n";
            } else {
                $new_dialogue .= "**{$speaker}:** {$zh} — {$ko}\n";
            }
        }

        // content에서 기존 ## 실전 대화 섹션을 교체 (또는 추가)
        if ( preg_match( '/## 실전 대화.*?(?=\n## |\z)/su', $content ) ) {
            $content = preg_replace( '/## 실전 대화.*?(?=\n## |\z)/su', $new_dialogue, $content );
            self::log( 'rebuild_dialogue: replaced existing dialogue section with structured data' );
        } else {
            // 실전 대화 섹션이 없으면 문화 속 언어 앞에 추가
            if ( preg_match( '/\n## 문화 속 언어/u', $content ) ) {
                $content = preg_replace( '/\n## 문화 속 언어/u', "\n" . $new_dialogue . "\n## 문화 속 언어", $content );
            } else {
                $content .= "\n\n" . $new_dialogue;
            }
            self::log( 'rebuild_dialogue: appended new dialogue section' );
        }

        return $content;
    }

    /**
     * 디버그 로깅
     */
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

    private static function count_lessons( $course_id ) {
        $q = new WP_Query( array(
            'post_type'      => 'dd_lesson',
            'meta_key'       => '_dd_course_id',
            'meta_value'     => $course_id,
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ) );
        return $q->found_posts;
    }
}
