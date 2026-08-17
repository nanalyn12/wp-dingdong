<?php
/**
 * DD_Song_Course_Generator
 *
 * 정식 발매된 중국어 노래 (MV/가사영상) YouTube URL을 학습 강의로 변환.
 * YouTube info/자막 fetch는 DD_Youtube_Subtitles(자막 추출 엔진)를 재사용,
 * Gemini 프롬프트만 가사 분석용으로 새로 작성.
 *
 * - 1곡 = 1강의
 * - 이미지 생성 0건 (MV 임베드가 시각적 콘텐츠 역할)
 * - 만화 / 스토리북 / 대화 장면 모두 미생성
 * - lyrics 객체에 한자/병음/한국어 3단으로 가사 전체 저장 → 프론트 가사 뷰어가 사용
 */
class DD_Song_Course_Generator {

    public static function parse_youtube_url( $url ) {
        return DD_Youtube_Subtitles::parse_youtube_url( $url );
    }

    public static function fetch_info( $url ) {
        return DD_Youtube_Subtitles::fetch_info( $url );
    }

    public static function fetch_subtitles( $video_id ) {
        return DD_Youtube_Subtitles::fetch_subtitles( $video_id );
    }

    public static function parse_srt( $text ) {
        return DD_Youtube_Subtitles::parse_srt( $text );
    }

    public static function parse_smi( $text ) {
        return DD_Youtube_Subtitles::parse_smi( $text );
    }

    /**
     * 강좌 컨테이너 생성 (type=song)
     */
    public static function generate( $params ) {
        @set_time_limit( 600 );

        $title    = sanitize_text_field( $params['title'] ?? '' );
        $level    = sanitize_text_field( $params['level'] ?? 'beginner' );
        $genre    = sanitize_text_field( $params['genre'] ?? '' );
        $tracks   = $params['tracks'] ?? array();
        $existing = (int) ( $params['existing_course_id'] ?? 0 );

        if ( empty( $tracks ) ) {
            return new WP_Error( 'no_tracks', '곡을 선택해 주세요.', array( 'status' => 400 ) );
        }

        // ── 기존 장르 강좌에 곡 추가 (append) ──
        if ( $existing ) {
            $course = get_post( $existing );
            if ( ! $course || $course->post_type !== 'dd_course' ) {
                return new WP_Error( 'no_course', '추가할 강좌를 찾을 수 없습니다.', array( 'status' => 404 ) );
            }
            $prev_total = (int) get_post_meta( $existing, '_dd_course_total_lessons', true );
            $offset     = self::count_lessons( $existing ); // 현재 곡 수 = 새 곡들의 order 시작점
            update_post_meta( $existing, '_dd_course_total_lessons', max( $prev_total, $offset ) + count( $tracks ) );
            update_post_meta( $existing, '_dd_course_status', 'generating' );
            if ( $genre !== '' ) {
                update_post_meta( $existing, '_dd_course_genre', $genre );
            }
            self::log( "=== song course APPEND: course={$existing}, +" . count( $tracks ) . " tracks, offset={$offset}" );
            return array(
                'course_id'    => $existing,
                'title'        => $course->post_title,
                'total'        => count( $tracks ),
                'order_offset' => $offset,
                'append'       => true,
            );
        }

        // ── 새 장르 강좌 생성 ──
        if ( empty( $title ) ) {
            return new WP_Error( 'missing_title', '강좌(장르) 제목을 입력해 주세요.', array( 'status' => 400 ) );
        }

        self::log( "=== song course START: title={$title}, level={$level}, genre={$genre}, tracks=" . count( $tracks ) );

        $course_id = wp_insert_post( array(
            'post_type'    => 'dd_course',
            'post_title'   => $title,
            'post_content' => $title . ' 중국어 노래 학습 강좌',
            'post_status'  => 'publish',
        ) );

        if ( is_wp_error( $course_id ) ) {
            return $course_id;
        }

        update_post_meta( $course_id, '_dd_course_status', 'generating' );
        update_post_meta( $course_id, '_dd_course_generated_at', current_time( 'mysql' ) );
        update_post_meta( $course_id, '_dd_course_total_lessons', count( $tracks ) );
        update_post_meta( $course_id, '_dd_course_level', $level );
        update_post_meta( $course_id, '_dd_course_type', 'song' );
        if ( $genre !== '' ) {
            update_post_meta( $course_id, '_dd_course_genre', $genre );
        }

        return array(
            'course_id'    => $course_id,
            'title'        => $title,
            'total'        => count( $tracks ),
            'order_offset' => 0,
            'append'       => false,
        );
    }

    /**
     * 한 곡 → 한 강의 생성
     */
    public static function generate_song_lesson( $course_id, $track, $order, $level ) {
        @set_time_limit( 600 );

        $video_id      = sanitize_text_field( $track['video_id'] ?? '' );
        $song_title    = sanitize_text_field( $track['title'] ?? "곡 {$order}" );
        $subtitle_text = $track['subtitle_text'] ?? '';
        $year          = sanitize_text_field( $track['year'] ?? '' );

        if ( empty( $subtitle_text ) ) {
            return new WP_Error( 'no_subtitles', "{$song_title}: 가사 자막이 없습니다." );
        }

        // 가사는 보통 짧아서 컷팅 거의 안 걸림 — 5분 곡 ≈ 1500자 안팎
        if ( mb_strlen( $subtitle_text ) > 6000 ) {
            $subtitle_text = mb_substr( $subtitle_text, 0, 6000 );
        }

        $course      = get_post( $course_id );
        $album_title = $course ? $course->post_title : '';

        self::log( "song lesson: course={$course_id}, video={$video_id}, title={$song_title}" );

        $system = '당신은 중국어 노래 가사를 분석하여 한국인 중국어 학습자를 위한 교육 콘텐츠를 만드는 전문가입니다.
가사에서 핵심 단어, 비유 표현, 문법 패턴, 감정 흐름, 문화적 맥락을 추출하여 구조화된 학습 자료를 만듭니다.
반드시 유효한 JSON만 출력하세요. JSON 외의 텍스트는 절대 포함하지 마세요.

[절대 규칙]
- content 안의 모든 중국어는 반드시 한자로!
- lyrics 배열의 zh 필드는 반드시 중국어 한자만 (한글/영문 금지)
- key_expressions의 zh 필드도 반드시 한자만!
- ⭐ lyrics 배열은 입력 자막의 **모든 줄을 빠짐없이 1:1로** 포함해야 함. 요약·중복제거·생략 절대 금지. 반복되는 후렴구도 나타난 횟수만큼 모두 포함. (입력이 24줄이면 lyrics도 24개)
' . DD_Chinese::PROMPT_RULE . '';

        if ( $level === 'beginner' ) {
            $level_note = '난이도: 입문 — 모든 가사 줄에 병음 표기 필수';
        } else {
            $level_note = $level === 'intermediate'
                ? '난이도: 중급 — 가사에 병음 표기 (어려운 단어만)'
                : '난이도: 고급 — 병음 없이 한자와 한국어만';
        }

        $prompt = sprintf(
            '앨범/강좌명: "%s"
곡 제목: "%s"
%s

아래는 이 곡의 중국어 가사(자막)입니다:
---
%s
---

위 가사를 분석하여 아래 JSON 구조의 학습 콘텐츠를 생성하세요.
JSON만 출력하고 다른 텍스트는 포함하지 마세요.

{
  "content": "강의 본문 (마크다운 형식, 최소 500자)",
  "song_meta": {
    "artist_guess": "가사 톤·주제로 추정한 아티스트 분위기 (한국어 1줄)",
    "mood": "곡의 감정/분위기 (예: 서정, 활기, 비장)",
    "theme": "가사 핵심 주제 (한국어 1-2문장)",
    "literary_devices": ["사용된 비유/수사 1", "비유 2"]
  },
  "lyrics": [
    {"zh": "中文歌词第一行", "pinyin": "병음", "ko": "한국어 번역", "section": "verse|chorus|bridge|intro|outro|interlude"}
  ],
  "key_expressions": [
    {"zh": "你好", "pinyin": "nǐ hǎo", "ko": "안녕하세요", "hsk": 1, "usage": "가사 안에서 어떻게 쓰였는지 1줄 설명"}
  ],
  "cultural_note": {
    "summary": "이 곡 가사가 담은 문화/시대 배경 1-2문장",
    "background": "구체적 배경 설명 3-4문장",
    "fun_facts": ["흥미로운 사실 1", "사실 2"],
    "comparison": "한국 음악/문화와의 비교 1-2문장",
    "related_expression": {"zh": "관련 중국어 표현", "pinyin": "병음", "ko": "한국어 뜻"},
    "did_you_know": "흥미로운 한 줄 트리비아"
  },
  "dialogues": [
    {"speaker": "친구A", "zh": "你听过这首歌吗？", "pinyin": "nǐ tīng guò zhè shǒu gē ma?", "ko": "이 노래 들어봤어?"}
  ],
  "slides": [
    {
      "title": "슬라이드 제목",
      "subtitle": "부제목",
      "key_point": "이 슬라이드의 핵심 학습 포인트 2-3문장",
      "bullets": ["내용1", "내용2", "내용3"],
      "vocab": [{"zh": "歌词", "pinyin": "gē cí", "ko": "가사"}],
      "examples": [{"zh": "예문 한자", "pinyin": "병음", "ko": "한국어"}],
      "usage_context": "이 표현/단어 사용 상황",
      "common_mistake": "한국 학습자가 자주 틀리는 부분",
      "practice": {"question_ko": "한국어 문제", "answer_zh": "정답 한자", "answer_pinyin": "병음", "answer_ko": "해설"},
      "tip": "학습 팁"
    }
  ],
  "quiz": [
    {"type":"choice", "question_ko": "질문", "question_zh": "问题", "options": ["A","B","C","D"], "correct": 0, "explanation": "해설"},
    {"type":"fill", "question_ko": "빈칸 질문", "sentence_zh": "我___你。", "answer": "爱", "hint": "사랑하다", "explanation": "해설"},
    {"type":"order", "question_ko": "어순 배열", "words": ["你","我","爱"], "correct_order": [1,2,0], "answer_text": "我爱你", "explanation": "해설"}
  ]
}

=== content 작성 규칙 (가사 분석) ===
- 마크다운 형식
- 구조: ## 곡 소개 → ## 가사 한눈에 → ## 핵심 표현 → ## 문법·표현 포인트 → ## 비유·수사 분석 → ## 실전 대화 → ## 문화 속 언어
- "## 가사 한눈에": 가사의 전체 흐름을 3-4문장으로 요약 (어떤 감정/이야기인지)
- "## 핵심 표현": 가사에서 실제 등장한 표현 분석 (각 표현별 의미·예문 2개)
- "## 문법·표현 포인트": 가사에서 추출 가능한 문법 패턴 (예: 把자문, 정도보어, 시간보어 등) 2-3개 + 예문
- "## 비유·수사 분석": 가사의 비유/대구/반복 등 수사 기법 분석 (한국어로 친절하게)
- "## 실전 대화": 곡의 주제·정서를 친구와 이야기하는 대화 (8-10턴)
- 최소 500자

=== lyrics 배열 작성 규칙 ===
- ⭐⭐ 입력 자막의 **모든 줄**을 순서대로 1:1 매핑. 줄을 합치거나 빼거나 요약하지 말 것. 출력 lyrics 개수 = 입력 자막 줄 수 (예: 자막 24줄 → lyrics 정확히 24개)
- 반복되는 후렴구도 등장한 횟수만큼 전부 포함 (중복 제거 금지)
- zh: 반드시 한자만 (한국어/영문 섞지 말 것)
- 자막 줄 앞에 [분:초] 형태의 시간 표기가 있을 수 있음 → zh/pinyin/ko 어디에도 넣지 말고 무시 (가사 텍스트만 사용)
- 줄 순서는 자막 순서를 그대로 유지 (가사 싱크용 시간 매칭에 사용됨)
- pinyin: 모든 줄에 표기 (입문/중급 난이도). 고급은 어려운 단어만
- ko: 자연스러운 한국어 번역 (직역보다 의역 우선, 단 의미 왜곡 금지)
- section: 가사 위치 표시 — verse/chorus/bridge/intro/outro/interlude 중 선택. 알 수 없으면 verse
- 가사가 길어도(20줄, 30줄+) 절대 줄이지 말 것 — 한 줄도 빠뜨리면 가사 싱크가 어긋남

=== key_expressions ===
- 가사에서 실제 사용된 핵심 어휘/표현 6-9개
- usage 필드에 "가사 ○줄째에서 ~ 의미로 사용" 같이 가사 맥락 포함
- hsk 1-9 (《国际中文教育中文水平等级标准》 기준)

=== dialogues ===
- 곡 주제를 친구와 이야기하는 대화 8-10턴
- speaker, zh(한자만!), pinyin, ko 필수

=== slides ===
- 5장. 각 슬라이드에 8개 필드(title/subtitle/key_point/bullets/vocab/examples/usage_context/common_mistake/practice/tip) 모두 포함
- 슬라이드 주제: ① 곡 소개·정서 ② 핵심 어휘 ③ 문법·표현 ④ 비유·수사 ⑤ 정리

=== quiz ===
- 6개 (choice 2 + fill 2 + order 2), 가사·표현 기반

⚠️ 절대 만들지 말 것 (이 강의는 이미지/만화/스토리북 없음):
- comic_panels, storybook_pages, dialogue_scene 필드 모두 출력 금지',
            $album_title,
            $song_title,
            $level_note,
            $subtitle_text
        );

        self::log( 'Song Gemini call start...' );
        $result    = null;
        $max_tries = 2;

        for ( $try = 1; $try <= $max_tries; $try++ ) {
            $result = DD_Gemini::generate( $prompt, $system );
            if ( is_wp_error( $result ) ) {
                self::log( "Song Gemini try {$try} error: " . $result->get_error_message() );
                if ( $try < $max_tries ) continue;
                return $result;
            }
            if ( ! is_array( $result ) ) {
                self::log( "Song try {$try} bad format" );
                if ( $try < $max_tries ) continue;
                return new WP_Error( 'bad_lesson', '강의 콘텐츠 응답이 올바르지 않습니다.' );
            }
            break;
        }

        self::log( 'Song Gemini OK, keys: ' . implode( ',', array_keys( $result ) ) );

        $content         = isset( $result['content'] ) && is_string( $result['content'] ) ? $result['content'] : '';
        $cultural_note   = isset( $result['cultural_note'] ) ? $result['cultural_note'] : array();
        $key_expressions = isset( $result['key_expressions'] ) && is_array( $result['key_expressions'] ) ? $result['key_expressions'] : array();
        $slides          = isset( $result['slides'] ) && is_array( $result['slides'] ) ? $result['slides'] : array();
        $quiz            = isset( $result['quiz'] ) && is_array( $result['quiz'] ) ? $result['quiz'] : array();
        $dialogues_data  = isset( $result['dialogues'] ) && is_array( $result['dialogues'] ) ? $result['dialogues'] : array();
        $lyrics          = isset( $result['lyrics'] ) && is_array( $result['lyrics'] ) ? $result['lyrics'] : array();
        $song_meta       = isset( $result['song_meta'] ) && is_array( $result['song_meta'] ) ? $result['song_meta'] : array();

        // 번체→간체 안전망: 입력 MV 자막이 번체(대만/홍콩)인 경우 Gemini가 번체를 흘릴 수 있으므로
        // 표시·싱크에 쓰이는 중국어 필드를 결정적으로 간체자로 통일한다.
        if ( class_exists( 'DD_Chinese' ) ) {
            $content         = DD_Chinese::to_simplified( $content );
            $cultural_note   = DD_Chinese::convert_deep( $cultural_note );
            $key_expressions = DD_Chinese::convert_deep( $key_expressions );
            $slides          = DD_Chinese::convert_deep( $slides );
            $quiz            = DD_Chinese::convert_deep( $quiz );
            $dialogues_data  = DD_Chinese::convert_deep( $dialogues_data );
            $lyrics          = DD_Chinese::convert_deep( $lyrics );
        }

        // 가사 싱크: 자막의 [MM:SS] 타임스탬프를 가사 줄에 매칭해 time(초) 부여
        $lyrics = self::attach_timestamps_to_lyrics( $lyrics, $subtitle_text );
        $timed_count = 0;
        foreach ( $lyrics as $ll ) { if ( isset( $ll['time'] ) ) { $timed_count++; } }
        self::log( "lyrics timestamp match: {$timed_count}/" . count( $lyrics ) . ' lines timed' );

        // 실전 대화 섹션 구조화 데이터로 재구축 (course generator의 기존 헬퍼 재사용)
        if ( ! empty( $dialogues_data ) && class_exists( 'DD_Course_Generator' ) ) {
            $content = DD_Course_Generator::rebuild_dialogue_section_static( $content, $dialogues_data, $level );
        }

        $lesson_id = wp_insert_post( array(
            'post_type'    => 'dd_lesson',
            'post_title'   => $song_title,
            'post_content' => $content,
            'post_status'  => 'publish',
        ) );

        if ( is_wp_error( $lesson_id ) ) {
            return $lesson_id;
        }

        $token = wp_generate_uuid4();

        update_post_meta( $lesson_id, '_dd_course_id', $course_id );
        update_post_meta( $lesson_id, '_dd_lesson_order', $order );
        update_post_meta( $lesson_id, '_dd_lesson_type', 'song' );
        if ( $year !== '' ) {
            update_post_meta( $lesson_id, '_dd_lesson_year', $year );
        }
        update_post_meta( $lesson_id, '_dd_slides_data', wp_json_encode( $slides, JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $lesson_id, '_dd_quiz_data', wp_json_encode( $quiz, JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $lesson_id, '_dd_cultural_note', is_array( $cultural_note ) ? wp_json_encode( $cultural_note, JSON_UNESCAPED_UNICODE ) : $cultural_note );
        update_post_meta( $lesson_id, '_dd_key_expressions', wp_json_encode( $key_expressions, JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $lesson_id, '_dd_dialogues_data', wp_json_encode( $dialogues_data, JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $lesson_id, '_dd_lyrics_data', wp_json_encode( $lyrics, JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $lesson_id, '_dd_song_meta', wp_json_encode( $song_meta, JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $lesson_id, '_dd_public_token', $token );
        update_post_meta( $lesson_id, '_dd_public_active', '1' );
        update_post_meta( $lesson_id, '_dd_lesson_level', $level );

        // MV iframe 임베드
        $embed_data = array(
            array(
                'video_id'  => $video_id,
                'title'     => $song_title,
                'embed_url' => 'https://www.youtube.com/embed/' . $video_id,
            ),
        );
        update_post_meta( $lesson_id, '_dd_video_embeds', wp_json_encode( $embed_data, JSON_UNESCAPED_UNICODE ) );

        // 이 강의 타입은 이미지 생성 0건 (만화/스토리북/대화이미지/핵심표현 이미지 모두 skip)

        // 완료 체크
        $lessons_done  = self::count_lessons( $course_id );
        $total_planned = (int) get_post_meta( $course_id, '_dd_course_total_lessons', true );
        if ( $total_planned > 0 && $lessons_done >= $total_planned ) {
            update_post_meta( $course_id, '_dd_course_status', 'complete' );
        }

        self::log( "=== song lesson DONE: lesson_id={$lesson_id}" );

        return array(
            'lesson_id'   => $lesson_id,
            'token'       => $token,
            'title'       => $song_title,
            'order'       => $order,
            'has_content' => ! empty( $content ),
            'slide_count' => count( $slides ),
            'quiz_count'  => count( $quiz ),
            'lyric_count' => count( $lyrics ),
        );
    }

    /**
     * 자막 텍스트의 [MM:SS]/[HH:MM:SS] 타임스탬프를 Gemini 가사 줄에 매칭해 time(초)을 부여한다.
     * - 자막이 타임스탬프를 포함하지 않으면 가사를 그대로(time 없음) 반환.
     * - 한자만 정규화 후 순서 포인터 기반 부분일치로 매칭 (반복 후렴구도 순서대로 처리).
     */
    private static function attach_timestamps_to_lyrics( $lyrics, $subtitle_text ) {
        if ( empty( $lyrics ) || empty( $subtitle_text ) ) {
            return $lyrics;
        }

        // 1) 자막에서 시간 표기가 있는 줄 파싱 → [{time, norm}]
        $timed = array();
        foreach ( preg_split( '/\r?\n/', $subtitle_text ) as $ln ) {
            if ( preg_match( '/^\s*\[?(\d{1,2}):(\d{2})(?::(\d{2}))?\]?\s*(.+\S)\s*$/u', $ln, $m ) ) {
                $sec = ( isset( $m[3] ) && $m[3] !== '' )
                    ? ( (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3] )
                    : ( (int) $m[1] * 60 + (int) $m[2] );
                $timed[] = array( 'time' => $sec, 'norm' => self::cjk_only( $m[4] ) );
            }
        }
        if ( empty( $timed ) ) {
            return $lyrics; // 타임스탬프 없는 자막 → 정적 가사
        }

        // 2) 순서 포인터로 가사↔자막 매칭 (정규화된 한자 부분일치)
        $n   = count( $timed );
        $ptr = 0;
        foreach ( $lyrics as &$line ) {
            $zh = self::cjk_only( $line['zh'] ?? '' );
            if ( $zh === '' ) {
                continue;
            }
            $found = -1;
            // ptr 부터 작은 윈도우 우선 탐색
            for ( $j = $ptr; $j < min( $ptr + 6, $n ); $j++ ) {
                $src = $timed[ $j ]['norm'];
                if ( $src !== '' && ( $src === $zh || mb_strpos( $src, $zh ) !== false || mb_strpos( $zh, $src ) !== false ) ) {
                    $found = $j;
                    break;
                }
            }
            // 윈도우 밖 전체 탐색 (반복 후렴 등)
            if ( $found < 0 ) {
                for ( $j = 0; $j < $n; $j++ ) {
                    $src = $timed[ $j ]['norm'];
                    if ( $src !== '' && ( $src === $zh || mb_strpos( $src, $zh ) !== false || mb_strpos( $zh, $src ) !== false ) ) {
                        $found = $j;
                        break;
                    }
                }
            }
            if ( $found >= 0 ) {
                $line['time'] = $timed[ $found ]['time'];
                $ptr          = $found + 1;
            }
        }
        unset( $line );

        return $lyrics;
    }

    /** 한자(CJK 통합 한자)만 남기고 모두 제거 — 공백/병음/문장부호/타임스탬프 무시한 비교용 */
    private static function cjk_only( $s ) {
        return preg_replace( '/[^\x{4e00}-\x{9fff}\x{3400}-\x{4dbf}]/u', '', (string) $s );
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

    private static function log( $message ) {
        $dir = WP_CONTENT_DIR . '/uploads/dingdong-lms';
        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
        }
        $time = current_time( 'Y-m-d H:i:s' );
        @file_put_contents( $dir . '/debug.log', "[{$time}] [SONG] {$message}\n", FILE_APPEND | LOCK_EX );
    }
}
