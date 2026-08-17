<?php
class DD_Public_Access {

    public static function add_rewrite_rules() {
        add_rewrite_rule(
            '^lesson/([a-f0-9\-]+)/?$',
            'index.php?dd_lesson_token=$matches[1]',
            'top'
        );
        add_rewrite_rule(
            '^story/([a-f0-9\-]+)/?$',
            'index.php?dd_story_token=$matches[1]',
            'top'
        );
        add_rewrite_rule(
            '^courses/?$',
            'index.php?dd_courses_page=1',
            'top'
        );
        add_rewrite_rule(
            '^newsletters/?$',
            'index.php?dd_newsletters_page=1',
            'top'
        );
        add_rewrite_rule(
            '^newsletter/([a-f0-9\-]+)/?$',
            'index.php?dd_newsletter_token=$matches[1]',
            'top'
        );
        add_rewrite_rule(
            '^stories/?$',
            'index.php?dd_stories_page=1',
            'top'
        );
        add_rewrite_rule(
            '^vocabulary/?$',
            'index.php?dd_vocabulary_page=1',
            'top'
        );
    }

    public static function query_vars( $vars ) {
        $vars[] = 'dd_lesson_token';
        $vars[] = 'dd_story_token';
        $vars[] = 'dd_courses_page';
        $vars[] = 'dd_newsletters_page';
        $vars[] = 'dd_newsletter_token';
        $vars[] = 'dd_stories_page';
        $vars[] = 'dd_vocabulary_page';
        return $vars;
    }

    public static function handle_request() {
        $landing_id = (int) get_option( 'dd_lms_landing_page_id' );
        if ( $landing_id && is_page( $landing_id ) ) {
            self::render_landing_page();
            exit;
        }

        if ( get_query_var( 'dd_courses_page' ) ) {
            self::render_courses_page();
            exit;
        }

        if ( get_query_var( 'dd_newsletters_page' ) ) {
            self::render_newsletters_page();
            exit;
        }

        $nl_token = get_query_var( 'dd_newsletter_token' );
        if ( ! empty( $nl_token ) ) {
            self::render_newsletter_detail( $nl_token );
            exit;
        }

        if ( get_query_var( 'dd_stories_page' ) ) {
            self::render_stories_page();
            exit;
        }

        if ( get_query_var( 'dd_vocabulary_page' ) ) {
            self::render_vocabulary_page();
            exit;
        }

        $story_token = get_query_var( 'dd_story_token' );
        if ( ! empty( $story_token ) ) {
            self::render_story_page( $story_token );
            exit;
        }

        $token = get_query_var( 'dd_lesson_token' );
        if ( empty( $token ) ) {
            return;
        }

        $lessons = get_posts( array(
            'post_type'   => 'dd_lesson',
            'meta_key'    => '_dd_public_token',
            'meta_value'  => sanitize_text_field( $token ),
            'numberposts' => 1,
            'post_status' => 'publish',
        ) );

        if ( empty( $lessons ) ) {
            wp_die( '강의를 찾을 수 없습니다.', '404', array( 'response' => 404 ) );
        }

        $lesson = $lessons[0];
        $active = get_post_meta( $lesson->ID, '_dd_public_active', true );

        if ( $active !== '1' ) {
            wp_die( '이 링크는 비활성화되었습니다.', '403', array( 'response' => 403 ) );
        }

        $data = self::get_lesson_data( $lesson );

        include DD_LMS_PATH . 'public/templates/lesson-public.php';
        exit;
    }

    public static function get_lesson_data( $lesson ) {
        $course_id = get_post_meta( $lesson->ID, '_dd_course_id', true );
        $course    = get_post( $course_id );
        $level     = get_post_meta( $lesson->ID, '_dd_lesson_level', true );
        if ( empty( $level ) && $course_id ) {
            $level = get_post_meta( $course_id, '_dd_course_level', true );
        }
        if ( empty( $level ) ) {
            $level = 'beginner';
        }

        $current_order = (int) get_post_meta( $lesson->ID, '_dd_lesson_order', true );
        $prev_lesson = null;
        $next_lesson = null;

        /* 1차: 같은 강좌 내 이전/다음 */
        if ( $course_id ) {
            $siblings = get_posts( array(
                'post_type'   => 'dd_lesson',
                'numberposts' => -1,
                'post_status' => 'publish',
                'orderby'     => 'meta_value_num',
                'order'       => 'ASC',
                'meta_key'    => '_dd_lesson_order',
                'meta_query'  => array(
                    array( 'key' => '_dd_course_id', 'value' => $course_id ),
                ),
            ) );

            foreach ( $siblings as $i => $s ) {
                if ( (int) $s->ID !== (int) $lesson->ID ) {
                    continue;
                }
                if ( $i > 0 ) {
                    $p = $siblings[ $i - 1 ];
                    if ( get_post_meta( $p->ID, '_dd_public_active', true ) === '1' ) {
                        $prev_lesson = array(
                            'title' => $p->post_title,
                            'url'   => self::get_public_url( $p->ID ),
                        );
                    }
                }
                if ( $i < count( $siblings ) - 1 ) {
                    $n = $siblings[ $i + 1 ];
                    if ( get_post_meta( $n->ID, '_dd_public_active', true ) === '1' ) {
                        $next_lesson = array(
                            'title' => $n->post_title,
                            'url'   => self::get_public_url( $n->ID ),
                        );
                    }
                }
                break;
            }
        }

        /* 2차 fallback: 강좌 간 이전/다음 (강좌에 강의 1개일 때) */
        if ( ! $prev_lesson || ! $next_lesson ) {
            $all_public = get_posts( array(
                'post_type'   => 'dd_lesson',
                'numberposts' => -1,
                'post_status' => 'publish',
                'orderby'     => 'date',
                'order'       => 'ASC',
                'meta_query'  => array(
                    array( 'key' => '_dd_public_active', 'value' => '1' ),
                ),
            ) );

            foreach ( $all_public as $i => $ap ) {
                if ( (int) $ap->ID !== (int) $lesson->ID ) {
                    continue;
                }
                if ( ! $prev_lesson && $i > 0 ) {
                    $p = $all_public[ $i - 1 ];
                    $p_course = get_post( get_post_meta( $p->ID, '_dd_course_id', true ) );
                    $prev_lesson = array(
                        'title' => $p->post_title,
                        'url'   => self::get_public_url( $p->ID ),
                        'course' => $p_course ? $p_course->post_title : '',
                    );
                }
                if ( ! $next_lesson && $i < count( $all_public ) - 1 ) {
                    $n = $all_public[ $i + 1 ];
                    $n_course = get_post( get_post_meta( $n->ID, '_dd_course_id', true ) );
                    $next_lesson = array(
                        'title' => $n->post_title,
                        'url'   => self::get_public_url( $n->ID ),
                        'course' => $n_course ? $n_course->post_title : '',
                    );
                }
                break;
            }
        }

        return array(
            'id'             => $lesson->ID,
            'title'          => $lesson->post_title,
            'content'        => $lesson->post_content,
            'course_title'   => $course ? $course->post_title : '',
            'course_intro'   => $course ? ( get_post_meta( $course->ID, '_dd_course_intro', true ) ?: $course->post_content ) : '',
            'course_id'      => $course_id,
            'order'          => $current_order,
            'level'          => $level,
            'prev_lesson'    => $prev_lesson,
            'next_lesson'    => $next_lesson,
            'slides'         => json_decode( get_post_meta( $lesson->ID, '_dd_slides_data', true ), true ) ?: array(),
            'quiz'           => json_decode( get_post_meta( $lesson->ID, '_dd_quiz_data', true ), true ) ?: array(),
            'cultural_note'   => self::parse_cultural_note( get_post_meta( $lesson->ID, '_dd_cultural_note', true ) ),
            'key_expressions' => json_decode( get_post_meta( $lesson->ID, '_dd_key_expressions', true ), true ) ?: array(),
            'key_expr_image'  => get_post_meta( $lesson->ID, '_dd_key_expr_image', true ) ?: '',
            'dialogue_scene'  => get_post_meta( $lesson->ID, '_dd_dialogue_scene', true ),
            'dialogue_image'  => get_post_meta( $lesson->ID, '_dd_dialogue_image', true ),
            'comic_panels'    => json_decode( get_post_meta( $lesson->ID, '_dd_comic_data', true ), true ) ?: array(),
            'comic_images'    => json_decode( get_post_meta( $lesson->ID, '_dd_comic_images', true ), true ) ?: array(),
            'comic_strip_image' => get_post_meta( $lesson->ID, '_dd_comic_strip_image', true ) ?: '',
            'video_keywords'  => json_decode( get_post_meta( $lesson->ID, '_dd_video_keywords', true ), true ) ?: array(),
            'video_embeds'    => json_decode( get_post_meta( $lesson->ID, '_dd_video_embeds', true ), true ) ?: array(),
            'videos'         => json_decode( get_post_meta( $lesson->ID, '_dd_video_urls', true ), true ) ?: array(),
            'embeds'         => json_decode( get_post_meta( $lesson->ID, '_dd_embed_urls', true ), true ) ?: array(),
            'token'          => get_post_meta( $lesson->ID, '_dd_public_token', true ),
            'dialogues'         => self::get_structured_dialogues( $lesson->ID, $lesson->post_content ),
            'storybook'         => json_decode( get_post_meta( $lesson->ID, '_dd_storybook_data', true ), true ) ?: array(),
            'vocab_comparison'  => json_decode( get_post_meta( $lesson->ID, '_dd_vocab_comparison', true ), true ) ?: array(),
            'cultural_snippet'  => json_decode( get_post_meta( $lesson->ID, '_dd_cultural_snippet', true ), true ) ?: array(),
            'materials'         => json_decode( get_post_meta( $lesson->ID, '_dd_lesson_materials', true ), true ) ?: array(),
            /* 노래 강의 (type=song) 전용 데이터 — 다른 타입에선 비어있음 */
            'lesson_type'       => get_post_meta( $lesson->ID, '_dd_lesson_type', true ) ?: '',
            'lyrics'            => json_decode( get_post_meta( $lesson->ID, '_dd_lyrics_data', true ), true ) ?: array(),
            'song_meta'         => json_decode( get_post_meta( $lesson->ID, '_dd_song_meta', true ), true ) ?: array(),
        );
    }

    /**
     * cultural_note 파싱 — JSON(새 구조) 또는 문자열(기존) 모두 지원
     * 새 구조: {summary, background, fun_facts, comparison, related_expression, did_you_know}
     * 기존 문자열: 그대로 반환 (backward compat)
     */
    public static function parse_cultural_note( $raw ) {
        if ( empty( $raw ) ) {
            return '';
        }
        /* Try JSON parse */
        $decoded = json_decode( $raw, true );
        if ( is_array( $decoded ) && ( isset( $decoded['summary'] ) || isset( $decoded['background'] ) ) ) {
            return $decoded; /* 새 구조화 포맷 */
        }
        /* 기존 문자열 포맷 → backward compat */
        return $raw;
    }

    /**
     * 마크다운 텍스트를 스타일링된 HTML로 변환
     */
    public static function render_markdown( $text ) {
        if ( empty( $text ) ) {
            return '';
        }

        $text  = trim( $text );
        $lines = explode( "\n", $text );
        $html  = '';
        $in_list   = false;
        $paragraph = '';

        foreach ( $lines as $line ) {
            $trimmed = trim( $line );

            // 빈 줄 — 단락 종료
            if ( empty( $trimmed ) ) {
                if ( $in_list ) {
                    $html   .= '</ul>';
                    $in_list = false;
                }
                if ( ! empty( $paragraph ) ) {
                    $html     .= '<p>' . self::inline_format( $paragraph ) . '</p>';
                    $paragraph = '';
                }
                continue;
            }

            // ### 소제목
            if ( preg_match( '/^#{3,}\s+(.+)$/', $trimmed, $m ) ) {
                $html = self::flush_block( $html, $paragraph, $in_list );
                $paragraph = '';
                $in_list   = false;
                $html .= '<h3 class="dd-sub-heading">' . esc_html( trim( $m[1] ) ) . '</h3>';
                continue;
            }

            // ## 섹션 제목 — 자동 ID 생성 + 섹션 타입별 아이콘/스타일
            if ( preg_match( '/^#{2}\s+(.+)$/', $trimmed, $m ) ) {
                $html = self::flush_block( $html, $paragraph, $in_list );
                $paragraph = '';
                $in_list   = false;
                $heading_text = trim( $m[1] );
                $heading_id   = 'section-md-' . sanitize_title( $heading_text );

                // 섹션 타입 감지 → CSS modifier + 아이콘
                $section_type = '';
                $icon_svg = '';
                if ( preg_match( '/핵심\s*표현/u', $heading_text ) ) {
                    $section_type = 'keyexpr';
                    $icon_svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>';
                } elseif ( preg_match( '/문법\s*포인트|문법/u', $heading_text ) ) {
                    $section_type = 'grammar';
                    $icon_svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 4h6v6h-6z"/><path d="M4 14h6v6H4z"/><path d="M17 17h.01"/><path d="M7 7h.01"/><path d="M14 7l3 3 3-3"/><path d="M7 14l3-3 3 3"/></svg>';
                } elseif ( preg_match( '/실전\s*대화|대화/u', $heading_text ) ) {
                    $section_type = 'dialogue';
                    $icon_svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
                } elseif ( preg_match( '/문화|문화\s*속/u', $heading_text ) ) {
                    $section_type = 'culture';
                    $icon_svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>';
                } elseif ( preg_match( '/도입|개요|소개/u', $heading_text ) ) {
                    $section_type = 'intro';
                    $icon_svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>';
                }

                $cls = 'dd-section-heading';
                if ( $section_type ) $cls .= ' dd-section-heading--' . $section_type;

                $html .= '<div class="dd-section-divider"></div>';
                $html .= '<h2 class="' . esc_attr( $cls ) . '" id="' . esc_attr( $heading_id ) . '">';
                if ( $icon_svg ) {
                    $html .= '<span class="dd-section-heading-icon">' . $icon_svg . '</span>';
                }
                $html .= '<span class="dd-section-heading-text">' . esc_html( $heading_text ) . '</span>';
                $html .= '</h2>';
                continue;
            }

            // # 대제목 (거의 사용 안 됨)
            if ( preg_match( '/^#\s+(.+)$/', $trimmed, $m ) ) {
                $html = self::flush_block( $html, $paragraph, $in_list );
                $paragraph = '';
                $in_list   = false;
                $html .= '<h2 class="dd-section-heading dd-section-heading--main">' . esc_html( trim( $m[1] ) ) . '</h2>';
                continue;
            }

            // 리스트 항목 (- 또는 *)
            if ( preg_match( '/^[-*]\s+(.+)$/', $trimmed, $m ) ) {
                if ( ! empty( $paragraph ) ) {
                    $html     .= '<p>' . self::inline_format( $paragraph ) . '</p>';
                    $paragraph = '';
                }
                if ( ! $in_list ) {
                    $html   .= '<ul class="dd-styled-list">';
                    $in_list = true;
                }
                $html .= '<li>' . self::inline_format( $m[1] ) . '</li>';
                continue;
            }

            // 번호 리스트 (1. 2. 3.)
            if ( preg_match( '/^\d+\.\s+(.+)$/', $trimmed, $m ) ) {
                if ( ! empty( $paragraph ) ) {
                    $html     .= '<p>' . self::inline_format( $paragraph ) . '</p>';
                    $paragraph = '';
                }
                if ( $in_list ) {
                    $html   .= '</ul>';
                    $in_list = false;
                }
                $html .= '<p class="dd-numbered-item">' . self::inline_format( $m[1] ) . '</p>';
                continue;
            }

            // 대화 줄 — **A:** ... 또는 A: ... 패턴 (한국어/영어/중국어 화자명)
            // 화자명에 공백 허용(단어+공백+단어) → "린 의사"처럼 띄어쓰기 있는 화자도 카드로 렌더.
            if ( preg_match( '/^\*{0,2}([A-Za-z\x{AC00}-\x{D7A3}\x{4E00}-\x{9FFF}]+(?:\x20[A-Za-z\x{AC00}-\x{D7A3}\x{4E00}-\x{9FFF}]+)*)\:?\*{0,2}\s*[:：]\s*(.+)$/u', $trimmed, $m ) ) {
                if ( ! empty( $paragraph ) ) {
                    $html     .= '<p>' . self::inline_format( $paragraph ) . '</p>';
                    $paragraph = '';
                }
                if ( $in_list ) {
                    $html   .= '</ul>';
                    $in_list = false;
                }
                $speaker = esc_html( trim( $m[1] ) );
                $rest    = trim( $m[2] );

                $zh_text     = $rest;
                $pinyin_text = '';
                $ko_text     = '';

                // 다양한 구분자 지원: — – - -- / |
                $sep = '(?:—|--|–|―|\s-\s)';

                // 패턴1: 중국어 (병음) — 한국어
                if ( preg_match( '/^(.+?)\s*\(([^)]+)\)\s*' . $sep . '\s*(.+)$/u', $rest, $dp ) ) {
                    $zh_text     = trim( $dp[1] );
                    $pinyin_text = trim( $dp[2] );
                    $ko_text     = trim( $dp[3] );
                }
                // 패턴2: 텍스트A — 텍스트B (구분자 분리)
                elseif ( preg_match( '/^(.+?)\s*' . $sep . '\s*(.+)$/u', $rest, $dp ) ) {
                    $zh_text = trim( $dp[1] );
                    $ko_text = trim( $dp[2] );
                }
                // 패턴3: 구분자 없음 — 중국어+한국어 혼합 텍스트를 언어별 분리 시도
                elseif ( self::has_chinese( $rest ) && self::has_korean( $rest ) ) {
                    $split = self::split_zh_ko( $rest );
                    $zh_text = $split['zh'];
                    $ko_text = $split['ko'];
                }

                $zh_text = preg_replace( '/\*\*/', '', $zh_text );
                $ko_text = preg_replace( '/\*\*/', '', $ko_text );
                self::swap_if_needed( $zh_text, $ko_text );

                $speech_html = '<div class="dd-speech">';
                if ( ! empty( $zh_text ) ) {
                    $speech_html .= '<span class="dd-speech-zh">' . esc_html( $zh_text ) . '</span>';
                }
                if ( ! empty( $pinyin_text ) ) {
                    $speech_html .= '<span class="dd-speech-pinyin">' . esc_html( $pinyin_text ) . '</span>';
                }
                if ( ! empty( $ko_text ) ) {
                    $speech_html .= '<span class="dd-speech-ko">' . esc_html( $ko_text ) . '</span>';
                }
                $speech_html .= '</div>';

                $html .= '<div class="dd-dialogue-line"><span class="dd-speaker">' . $speaker . '</span>' . $speech_html . '</div>';
                continue;
            }

            // 일반 텍스트 → 단락 누적
            if ( $in_list ) {
                $html   .= '</ul>';
                $in_list = false;
            }
            $paragraph .= ( empty( $paragraph ) ? '' : ' ' ) . $trimmed;
        }

        // 잔여 flush
        if ( $in_list ) {
            $html .= '</ul>';
        }
        if ( ! empty( $paragraph ) ) {
            $html .= '<p>' . self::inline_format( $paragraph ) . '</p>';
        }

        return $html;
    }

    /**
     * 인라인 마크다운 변환: **bold**, *italic*, `code`
     */
    public static function clean_text( $text ) {
        if ( empty( $text ) ) {
            return '';
        }
        return str_replace( array( '**', '__' ), '', $text );
    }

    // TTS용: 한국어(hangul) 제거, 중국어 한자+병음+구두점만 남김
    public static function chinese_only( $text ) {
        if ( empty( $text ) ) {
            return '';
        }
        $text = self::clean_text( $text );
        $text = preg_replace( '/[\x{AC00}-\x{D7A3}\x{1100}-\x{11FF}\x{3130}-\x{318F}]+/u', '', $text );
        return trim( preg_replace( '/\s{2,}/', ' ', $text ) );
    }

    private static function has_chinese( $text ) {
        return (bool) preg_match( '/[\x{4E00}-\x{9FFF}]/u', $text );
    }

    private static function has_korean( $text ) {
        return (bool) preg_match( '/[\x{AC00}-\x{D7A3}]/u', $text );
    }

    private static function is_mostly_korean( $text ) {
        $ko = preg_match_all( '/[\x{AC00}-\x{D7A3}]/u', $text );
        $zh = preg_match_all( '/[\x{4E00}-\x{9FFF}]/u', $text );
        return $ko > $zh;
    }

    /**
     * zh/ko 텍스트 쌍의 언어를 검증하고 필요 시 스왑/분리
     */
    private static function swap_if_needed( &$zh_text, &$ko_text ) {
        // Case 1: 둘 다 있을 때 — 언어 체크 후 스왑
        if ( ! empty( $zh_text ) && ! empty( $ko_text ) ) {
            if ( self::is_mostly_korean( $zh_text ) && ! self::is_mostly_korean( $ko_text ) ) {
                $tmp     = $zh_text;
                $zh_text = $ko_text;
                $ko_text = $tmp;
            }
            return;
        }

        // Case 2: zh만 있고 ko가 비어있는데, zh가 한국어 — ko로 이동
        if ( ! empty( $zh_text ) && empty( $ko_text ) ) {
            if ( self::is_mostly_korean( $zh_text ) && ! self::has_chinese( $zh_text ) ) {
                $ko_text = $zh_text;
                $zh_text = '';
            }
            return;
        }

        // Case 3: ko만 있고 zh가 비어있는데, ko가 중국어 — zh로 이동
        if ( empty( $zh_text ) && ! empty( $ko_text ) ) {
            if ( self::has_chinese( $ko_text ) && ! self::is_mostly_korean( $ko_text ) ) {
                $zh_text = $ko_text;
                $ko_text = '';
            }
        }
    }

    /**
     * 중국어+한국어 혼합 텍스트를 언어별로 분리
     * 예: "你好吗？ 잘 지내?" → zh="你好吗？", ko="잘 지내?"
     */
    private static function split_zh_ko( $text ) {
        $zh_parts = array();
        $ko_parts = array();

        // 공백/구두점으로 토큰 분리 후 각 토큰의 주요 언어 판별
        $tokens = preg_split( '/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
        $current_lang = '';

        foreach ( $tokens as $token ) {
            if ( trim( $token ) === '' ) {
                // 공백은 현재 언어에 추가
                if ( $current_lang === 'zh' ) {
                    $zh_parts[] = $token;
                } elseif ( $current_lang === 'ko' ) {
                    $ko_parts[] = $token;
                }
                continue;
            }

            $has_zh = self::has_chinese( $token );
            $has_ko = self::has_korean( $token );

            if ( $has_zh && ! $has_ko ) {
                $zh_parts[] = $token;
                $current_lang = 'zh';
            } elseif ( $has_ko && ! $has_zh ) {
                $ko_parts[] = $token;
                $current_lang = 'ko';
            } elseif ( $has_zh && $has_ko ) {
                // 혼합 — 더 많은 쪽에 추가
                if ( self::is_mostly_korean( $token ) ) {
                    $ko_parts[] = $token;
                    $current_lang = 'ko';
                } else {
                    $zh_parts[] = $token;
                    $current_lang = 'zh';
                }
            } else {
                // ASCII/구두점 — 현재 언어에 추가
                if ( $current_lang === 'zh' ) {
                    $zh_parts[] = $token;
                } elseif ( $current_lang === 'ko' ) {
                    $ko_parts[] = $token;
                } else {
                    $zh_parts[] = $token;
                }
            }
        }

        return array(
            'zh' => trim( implode( '', $zh_parts ) ),
            'ko' => trim( implode( '', $ko_parts ) ),
        );
    }

    public static function inline_format( $text ) {
        // **bold**
        $text = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );
        // *italic*
        $text = preg_replace( '/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/', '<em>$1</em>', $text );
        // `code`
        $text = preg_replace( '/`(.+?)`/', '<code class="dd-inline-code">$1</code>', $text );
        // stray ** cleanup
        $text = str_replace( '**', '', $text );

        return wp_kses_post( $text );
    }

    /**
     * 누적 블록 flush 헬퍼
     */
    private static function flush_block( $html, &$paragraph, &$in_list ) {
        if ( $in_list ) {
            $html   .= '</ul>';
            $in_list = false;
        }
        if ( ! empty( $paragraph ) ) {
            $html     .= '<p>' . self::inline_format( $paragraph ) . '</p>';
            $paragraph = '';
        }
        return $html;
    }

    /**
     * 가사/자막의 중국어 텍스트에서 강의 핵심어휘를 찾아 클릭 가능한 span으로 감싼다.
     * 노래 가사·자막 속에서 배운 단어를 만나면 강조 + 탭하면 뜻 팝업/단어장 저장(JS가 처리).
     * mbstring 비의존(preg_split '//u'), 긴 단어 우선 그리디 매칭(겹침 방지).
     *
     * @return string 핵심어휘는 <span class="dd-lyric-kw" ...>로, 나머지는 esc_html 처리된 HTML
     */
    public static function highlight_keywords( $text, $key_expressions, $lesson_id = 0, $lesson_title = '' ) {
        $text = (string) $text;
        if ( $text === '' || empty( $key_expressions ) || ! is_array( $key_expressions ) ) {
            return esc_html( $text );
        }

        // 키워드별 코드포인트 배열/길이/메타 캐시
        $kw = array();
        foreach ( $key_expressions as $k ) {
            if ( ! is_array( $k ) ) { continue; }
            $zh = trim( (string) ( $k['zh'] ?? '' ) );
            if ( $zh === '' ) { continue; }
            $zc = preg_split( '//u', $zh, -1, PREG_SPLIT_NO_EMPTY );
            if ( ! is_array( $zc ) || count( $zc ) === 0 ) { continue; }
            $kw[ $zh ] = array(
                'chars'  => $zc,
                'len'    => count( $zc ),
                'pinyin' => (string) ( $k['pinyin'] ?? '' ),
                'ko'     => (string) ( $k['ko'] ?? '' ),
                'hsk'    => (int) ( $k['hsk'] ?? 0 ),
            );
        }
        if ( empty( $kw ) ) {
            return esc_html( $text );
        }
        // 긴 단어 우선 매칭 (예: "做手术" 가 "手术" 보다 먼저)
        uasort( $kw, function ( $a, $b ) { return $b['len'] - $a['len']; } );

        $chars = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );
        if ( ! is_array( $chars ) ) {
            return esc_html( $text );
        }
        $n   = count( $chars );
        $out = '';
        $i   = 0;
        while ( $i < $n ) {
            $hit = null;
            foreach ( $kw as $word => $info ) {
                $wl = $info['len'];
                if ( $i + $wl > $n ) { continue; }
                $ok = true;
                for ( $j = 0; $j < $wl; $j++ ) {
                    if ( $chars[ $i + $j ] !== $info['chars'][ $j ] ) { $ok = false; break; }
                }
                if ( $ok ) { $hit = array( 'word' => $word, 'info' => $info ); break; }
            }
            if ( $hit ) {
                $info = $hit['info'];
                $out .= '<span class="dd-lyric-kw" role="button" tabindex="0"'
                      . ' data-zh="' . esc_attr( $hit['word'] ) . '"'
                      . ' data-pinyin="' . esc_attr( $info['pinyin'] ) . '"'
                      . ' data-ko="' . esc_attr( $info['ko'] ) . '"'
                      . ' data-hsk="' . esc_attr( $info['hsk'] ) . '"'
                      . ' data-lesson-id="' . esc_attr( $lesson_id ) . '"'
                      . ' data-lesson-title="' . esc_attr( $lesson_title ) . '">'
                      . esc_html( $hit['word'] ) . '</span>';
                $i += $info['len'];
            } else {
                $out .= esc_html( $chars[ $i ] );
                $i++;
            }
        }
        return $out;
    }

    public static function extract_dialogues( $content ) {
        if ( empty( $content ) ) {
            return array();
        }
        $dialogues = array();
        $lines = explode( "\n", $content );
        $sep = '(?:—|--|–|―|\s-\s)';

        foreach ( $lines as $line ) {
            $trimmed = trim( $line );
            if ( preg_match( '/^\*{0,2}([A-Za-z\x{AC00}-\x{D7A3}\x{4E00}-\x{9FFF}]+(?:\x20[A-Za-z\x{AC00}-\x{D7A3}\x{4E00}-\x{9FFF}]+)*)\:?\*{0,2}\s*[:：]\s*(.+)$/u', $trimmed, $m ) ) {
                $speaker = trim( $m[1] );
                $rest    = trim( $m[2] );
                $zh      = $rest;
                $pinyin  = '';
                $ko      = '';

                if ( preg_match( '/^(.+?)\s*\(([^)]+)\)\s*' . $sep . '\s*(.+)$/u', $rest, $parts ) ) {
                    $zh     = trim( $parts[1] );
                    $pinyin = trim( $parts[2] );
                    $ko     = trim( $parts[3] );
                } elseif ( preg_match( '/^(.+?)\s*' . $sep . '\s*(.+)$/u', $rest, $parts ) ) {
                    $zh = trim( $parts[1] );
                    $ko = trim( $parts[2] );
                } elseif ( self::has_chinese( $rest ) && self::has_korean( $rest ) ) {
                    $split = self::split_zh_ko( $rest );
                    $zh = $split['zh'];
                    $ko = $split['ko'];
                }

                $zh = preg_replace( '/\*\*/', '', $zh );
                $ko = preg_replace( '/\*\*/', '', $ko );
                self::swap_if_needed( $zh, $ko );

                $dialogues[] = array(
                    'speaker' => $speaker,
                    'zh'      => $zh,
                    'pinyin'  => $pinyin,
                    'ko'      => $ko,
                );
            }
        }
        return $dialogues;
    }

    /**
     * 구조화된 대화 데이터 우선 사용, 없으면 content에서 파싱
     */
    public static function get_structured_dialogues( $lesson_id, $content ) {
        // 1순위: _dd_dialogues_data (구조화된 JSON)
        $raw = get_post_meta( $lesson_id, '_dd_dialogues_data', true );
        $dialogues = ! empty( $raw ) ? json_decode( $raw, true ) : null;

        if ( ! empty( $dialogues ) && is_array( $dialogues ) ) {
            // 구조화 데이터에 실제 중국어가 있는지 확인
            $has_zh = false;
            foreach ( $dialogues as $dl ) {
                if ( ! empty( $dl['zh'] ) && preg_match( '/[\x{4E00}-\x{9FFF}]/u', $dl['zh'] ) ) {
                    $has_zh = true;
                    break;
                }
            }

            if ( $has_zh ) {
                // 유효한 구조화 데이터 — 직접 사용
                $result = array();
                foreach ( $dialogues as $dl ) {
                    $zh = $dl['zh'] ?? '';
                    $ko = $dl['ko'] ?? '';
                    self::swap_if_needed( $zh, $ko );
                    $result[] = array(
                        'speaker' => $dl['speaker'] ?? 'A',
                        'zh'      => $zh,
                        'pinyin'  => $dl['pinyin'] ?? '',
                        'ko'      => $ko,
                    );
                }
                return $result;
            }
        }

        // 2순위: comic_panels에서 대화 추출 시도
        $comic_raw = get_post_meta( $lesson_id, '_dd_comic_data', true );
        $comic_panels = ! empty( $comic_raw ) ? json_decode( $comic_raw, true ) : null;

        if ( ! empty( $comic_panels ) && is_array( $comic_panels ) ) {
            $comic_dialogues = array();
            foreach ( $comic_panels as $panel ) {
                if ( ! empty( $panel['dialogue'] ) && is_array( $panel['dialogue'] ) ) {
                    foreach ( $panel['dialogue'] as $dl ) {
                        if ( ! empty( $dl['zh'] ) && preg_match( '/[\x{4E00}-\x{9FFF}]/u', $dl['zh'] ) ) {
                            $zh = $dl['zh'] ?? '';
                            $ko = $dl['ko'] ?? '';
                            self::swap_if_needed( $zh, $ko );
                            $comic_dialogues[] = array(
                                'speaker' => $dl['speaker'] ?? 'A',
                                'zh'      => $zh,
                                'pinyin'  => $dl['pinyin'] ?? '',
                                'ko'      => $ko,
                            );
                        }
                    }
                }
            }
            if ( ! empty( $comic_dialogues ) ) {
                return $comic_dialogues;
            }
        }

        // 3순위: content 마크다운 파싱 (기존 방식)
        return self::extract_dialogues( $content );
    }

    public static function get_public_url( $lesson_id ) {
        $token = get_post_meta( $lesson_id, '_dd_public_token', true );
        if ( empty( $token ) ) {
            return '';
        }
        return home_url( '/lesson/' . $token . '/' );
    }

    public static function render_story_page( $token ) {
        $stories = get_posts( array(
            'post_type'   => 'dd_story',
            'meta_key'    => '_dd_story_public_token',
            'meta_value'  => sanitize_text_field( $token ),
            'numberposts' => 1,
            'post_status' => 'publish',
        ) );

        if ( empty( $stories ) ) {
            wp_die( '스토리를 찾을 수 없습니다.', '404', array( 'response' => 404 ) );
        }

        $story = $stories[0];
        if ( get_post_meta( $story->ID, '_dd_story_public_active', true ) !== '1' ) {
            wp_die( '이 링크는 비활성화되었습니다.', '403', array( 'response' => 403 ) );
        }

        $data = array(
            'id'          => $story->ID,
            'title'       => $story->post_title,
            'description' => $story->post_content,
            'nodes'       => json_decode( get_post_meta( $story->ID, '_dd_story_nodes', true ), true ) ?: array(),
            'level'       => get_post_meta( $story->ID, '_dd_story_level', true ) ?: 'beginner',
            'cover_image' => get_post_meta( $story->ID, '_dd_story_cover_image', true ),
        );

        include DD_LMS_PATH . 'public/templates/story-public.php';
    }

    public static function render_newsletters_page() {
        $posts = get_posts( array(
            'post_type'   => 'dd_newsletter',
            'numberposts' => 50,
            'post_status' => 'publish',
            'orderby'     => 'date',
            'order'       => 'DESC',
            'meta_query'  => array(
                array( 'key' => '_dd_newsletter_public_active', 'value' => '1' ),
            ),
        ) );

        $newsletters = array();
        foreach ( $posts as $p ) {
            $token = get_post_meta( $p->ID, '_dd_newsletter_public_token', true );
            $newsletters[] = array(
                'title'       => $p->post_title,
                'title_zh'    => get_post_meta( $p->ID, '_dd_newsletter_title_zh', true ),
                'summary'     => get_post_meta( $p->ID, '_dd_newsletter_summary', true ),
                'category'    => get_post_meta( $p->ID, '_dd_newsletter_category', true ),
                'emoji'       => get_post_meta( $p->ID, '_dd_newsletter_cover_emoji', true ),
                'cover_image' => get_post_meta( $p->ID, '_dd_newsletter_cover_image', true ),
                'url'         => home_url( '/newsletter/' . $token . '/' ),
                'date'        => get_the_date( 'Y년 n월 j일', $p ),
            );
        }

        include DD_LMS_PATH . 'public/templates/newsletters-public.php';
    }

    public static function render_newsletter_detail( $token ) {
        $posts = get_posts( array(
            'post_type'   => 'dd_newsletter',
            'meta_key'    => '_dd_newsletter_public_token',
            'meta_value'  => sanitize_text_field( $token ),
            'numberposts' => 1,
            'post_status' => 'publish',
        ) );

        if ( empty( $posts ) ) {
            wp_die( '뉴스레터를 찾을 수 없습니다.', '404', array( 'response' => 404 ) );
        }

        $post = $posts[0];
        if ( get_post_meta( $post->ID, '_dd_newsletter_public_active', true ) !== '1' ) {
            wp_die( '이 뉴스레터는 비공개 상태입니다.', '403', array( 'response' => 403 ) );
        }

        $data = array(
            'id'          => $post->ID,
            'title'       => $post->post_title,
            'title_zh'    => get_post_meta( $post->ID, '_dd_newsletter_title_zh', true ),
            'summary'     => get_post_meta( $post->ID, '_dd_newsletter_summary', true ),
            'category'    => get_post_meta( $post->ID, '_dd_newsletter_category', true ),
            'emoji'       => get_post_meta( $post->ID, '_dd_newsletter_cover_emoji', true ),
            'cover_image' => get_post_meta( $post->ID, '_dd_newsletter_cover_image', true ),
            'sections'    => json_decode( get_post_meta( $post->ID, '_dd_newsletter_sections', true ), true ) ?: array(),
            'vocab'       => json_decode( get_post_meta( $post->ID, '_dd_newsletter_vocab', true ), true ) ?: array(),
            'date'        => get_the_date( 'Y년 n월 j일', $post ),
        );

        include DD_LMS_PATH . 'public/templates/newsletter-detail.php';
    }

    public static function render_landing_page() {
        include DD_LMS_PATH . 'public/templates/landing-public.php';
    }

    public static function render_vocabulary_page() {
        include DD_LMS_PATH . 'public/templates/vocabulary-public.php';
    }

    public static function render_stories_page() {
        $posts = get_posts( array(
            'post_type'   => 'dd_story',
            'numberposts' => 50,
            'post_status' => 'publish',
            'orderby'     => 'date',
            'order'       => 'DESC',
        ) );

        $stories = array();
        foreach ( $posts as $p ) {
            if ( get_post_meta( $p->ID, '_dd_story_public_active', true ) !== '1' ) {
                continue;
            }
            $token = get_post_meta( $p->ID, '_dd_story_public_token', true );
            $stories[] = array(
                'title'       => $p->post_title,
                'level'       => get_post_meta( $p->ID, '_dd_story_level', true ) ?: 'beginner',
                'cover'       => get_post_meta( $p->ID, '_dd_story_cover_image', true ),
                'url'         => home_url( '/story/' . $token . '/' ),
                'date'        => get_the_date( 'Y년 n월 j일', $p ),
            );
        }

        include DD_LMS_PATH . 'public/templates/stories-public.php';
    }

    public static function render_courses_page() {
        $courses = get_posts( array(
            'post_type'   => 'dd_course',
            'numberposts' => 50,
            'post_status' => 'publish',
            'orderby'     => 'date',
            'order'       => 'DESC',
        ) );

        $courses_data = array();
        foreach ( $courses as $course ) {
            $lessons = get_posts( array(
                'post_type'   => 'dd_lesson',
                'numberposts' => 50,
                'post_status' => 'publish',
                'orderby'     => 'meta_value_num',
                'order'       => 'ASC',
                'meta_query'  => array(
                    array( 'key' => '_dd_course_id', 'value' => $course->ID ),
                ),
                'meta_key'    => '_dd_lesson_order',
            ) );

            $active_lessons  = array();
            $first_lesson_id = 0;
            $course_years    = array();
            foreach ( $lessons as $l ) {
                $active = get_post_meta( $l->ID, '_dd_public_active', true );
                if ( $active === '1' ) {
                    if ( ! $first_lesson_id ) {
                        $first_lesson_id = $l->ID;
                    }
                    $lesson_year = get_post_meta( $l->ID, '_dd_lesson_year', true );
                    if ( $lesson_year !== '' && $lesson_year !== false && ! in_array( $lesson_year, $course_years, true ) ) {
                        $course_years[] = $lesson_year;
                    }
                    $active_lessons[] = array(
                        'title' => $l->post_title,
                        'order' => (int) get_post_meta( $l->ID, '_dd_lesson_order', true ),
                        'url'   => self::get_public_url( $l->ID ),
                    );
                }
            }

            if ( ! empty( $active_lessons ) ) {
                $course_type  = get_post_meta( $course->ID, '_dd_course_type', true );
                $course_level = get_post_meta( $course->ID, '_dd_course_level', true );
                $course_thumb = get_post_meta( $course->ID, '_dd_course_thumbnail', true );
                $course_genre  = get_post_meta( $course->ID, '_dd_course_genre', true );
                $course_artist = get_post_meta( $course->ID, '_dd_course_artist', true );

                // 노래 강좌: 커스텀 썸네일이 없으면 첫 곡의 YouTube 썸네일을 자동 사용
                // (어차피 YouTube를 임베드하므로 별도 이미지 생성 불필요)
                if ( empty( $course_thumb ) && $course_type === 'song' && $first_lesson_id ) {
                    $embeds_raw = get_post_meta( $first_lesson_id, '_dd_video_embeds', true );
                    $embeds     = $embeds_raw ? json_decode( $embeds_raw, true ) : array();
                    if ( ! empty( $embeds[0]['video_id'] ) ) {
                        $course_thumb = 'https://img.youtube.com/vi/' . rawurlencode( $embeds[0]['video_id'] ) . '/hqdefault.jpg';
                    }
                }

                $courses_data[] = array(
                    'title'       => $course->post_title,
                    'description' => $course->post_content,
                    'lessons'     => $active_lessons,
                    'type'        => $course_type ?: 'ai',
                    'level'       => $course_level ?: 'beginner',
                    'thumbnail'   => $course_thumb ?: '',
                    'genre'       => $course_genre ?: '',
                    'artist'      => $course_artist ?: '',
                    'years'       => $course_years,
                );
            }
        }

        include DD_LMS_PATH . 'public/templates/courses-public.php';
    }
}
