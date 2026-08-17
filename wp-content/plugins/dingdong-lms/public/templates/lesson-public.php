<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html( $data['title'] ); ?> - Dingdong</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;700&display=swap">
    <link rel="stylesheet" href="<?php echo esc_url( DD_LMS_URL . 'public/css/dd-lesson.css' ); ?>">
    <link rel="stylesheet" href="<?php echo esc_url( DD_LMS_URL . 'public/css/dd-print.css' ); ?>">
    <link rel="stylesheet" href="<?php echo esc_url( DD_LMS_URL . 'public/css/dd-assistant.css' ); ?>">
    <link rel="stylesheet" href="<?php echo esc_url( DD_LMS_URL . 'public/css/dd-song.css' ); ?>">
    <link rel="stylesheet" href="<?php echo esc_url( DD_LMS_URL . 'public/css/dd-adaptive-review.css' ); ?>">
    <link rel="stylesheet" href="<?php echo esc_url( DD_LMS_URL . 'public/css/dd-pronunciation.css' ); ?>">
    <link rel="stylesheet" href="<?php echo esc_url( DD_LMS_URL . 'public/css/dd-listening-drills.css' ); ?>">
    <link rel="stylesheet" href="<?php echo esc_url( DD_LMS_URL . 'public/css/dd-stroke-practice.css' ); ?>">
</head>
<body data-dd-page="lesson"<?php
    $dd_lt = $data['lesson_type'] ?? '';
    if ( $dd_lt === 'song' ) { echo ' class="dd-lesson-song"'; }
?>>

<nav class="dd-topbar">
    <div class="dd-topbar-inner">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="dd-topbar-brand">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            DingDong
        </a>
        <div class="dd-topbar-nav">
            <a href="<?php echo esc_url( home_url( '/courses/' ) ); ?>" class="dd-topbar-link">강좌 목록</a>
            <a href="<?php echo esc_url( home_url( '/stories/' ) ); ?>" class="dd-topbar-link">AI 스토리</a>
            <a href="<?php echo esc_url( home_url( '/newsletters/' ) ); ?>" class="dd-topbar-link">뉴스레터</a>
            <a href="<?php echo esc_url( home_url( '/vocabulary/' ) ); ?>" class="dd-topbar-link">단어장</a>
        </div>
    </div>
</nav>

<div class="dd-lesson-wrapper" data-level="<?php echo esc_attr( $data['level'] ?? 'beginner' ); ?>" data-lesson-type="<?php echo esc_attr( $data['lesson_type'] ?? '' ); ?>">

    <!-- 복습 알림 배너 -->
    <div class="dd-review-banner" id="dd-review-banner" style="display:none;"></div>

    <header class="dd-lesson-header">
        <p class="dd-course-title"><?php echo esc_html( $data['course_title'] ); ?></p>
        <h1><?php echo esc_html( $data['title'] ); ?></h1>
        <span class="dd-lesson-order"><?php echo esc_html( $data['order'] ); ?>/<?php
            $total = (int) get_post_meta(
                get_post_meta( $data['id'], '_dd_course_id', true ),
                '_dd_course_total_lessons',
                true
            );
            echo esc_html( $total ?: '?' );
        ?></span>

        <!-- 강좌 소개: 제목과 진도 사이, 무엇을 배우는지 + 적정 레벨 -->
        <?php if ( ! empty( $data['course_intro'] ) ) : ?>
        <div class="dd-course-intro">
            <?php
            $dd_level_labels = array( 'beginner' => '입문', 'intermediate' => '중급', 'advanced' => '고급' );
            $dd_lvl = $data['level'] ?? '';
            if ( isset( $dd_level_labels[ $dd_lvl ] ) ) {
                echo '<span class="dd-course-intro-level">&#x1F3AF; ' . esc_html( $dd_level_labels[ $dd_lvl ] ) . '</span>';
            }
            $dd_intro_lines = preg_split( '/\r\n|\r|\n/', trim( $data['course_intro'] ) );
            $dd_intro_paras = array();
            $dd_intro_bullets = array();
            foreach ( $dd_intro_lines as $dd_ln ) {
                $dd_ln = trim( $dd_ln );
                if ( $dd_ln === '' ) {
                    continue;
                }
                if ( preg_match( '/^[-\x{2022}*]\s*(.+)/u', $dd_ln, $dd_m ) ) {
                    $dd_intro_bullets[] = $dd_m[1];
                } else {
                    $dd_intro_paras[] = $dd_ln;
                }
            }
            foreach ( $dd_intro_paras as $dd_p ) {
                echo '<p class="dd-course-intro-text">' . esc_html( $dd_p ) . '</p>';
            }
            if ( ! empty( $dd_intro_bullets ) ) {
                echo '<ul class="dd-course-intro-list">';
                foreach ( $dd_intro_bullets as $dd_b ) {
                    echo '<li>' . esc_html( $dd_b ) . '</li>';
                }
                echo '</ul>';
            }
            ?>
        </div>
        <?php endif; ?>

        <!-- 학습 진도 위젯 -->
        <div class="dd-progress-widget" id="dd-progress-widget"></div>
    </header>

    <nav class="dd-tabs">
        <button class="dd-tab is-active" data-tab="content"><?php echo esc_html( '학습 내용' ); ?></button>
        <button class="dd-tab" data-tab="slides"><?php echo esc_html( '슬라이드' ); ?></button>
        <button class="dd-tab" data-tab="audiobook"><?php echo esc_html( '오디오북' ); ?></button>
        <button class="dd-tab" data-tab="storybook"><?php echo esc_html( '스토리북' ); ?></button>
        <button class="dd-tab" data-tab="quiz"><?php echo esc_html( '퀴즈' ); ?></button>
        <button class="dd-tab" data-tab="hanzi"><?php echo esc_html( '한자' ); ?></button>
        <button class="dd-tab" data-tab="vocabulary"><?php echo esc_html( '단어장' ); ?></button>
    </nav>

    <!-- ========== Sticky Feature Rail — 모든 탭을 한 번에 이동 ========== -->
    <aside class="dd-feature-rail" id="dd-feature-rail" aria-label="강의 기능 빠른 이동">
        <button class="dd-feature-rail-item is-active" data-feature="content" title="학습 내용">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
            <span class="dd-feature-rail-label">학습 내용</span>
            <span class="dd-feature-rail-dot" aria-hidden="true"></span>
        </button>
        <button class="dd-feature-rail-item" data-feature="slides" title="슬라이드">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="14" rx="2"/><line x1="8" y1="20" x2="16" y2="20"/><line x1="12" y1="18" x2="12" y2="20"/></svg>
            <span class="dd-feature-rail-label">슬라이드</span>
            <span class="dd-feature-rail-dot" aria-hidden="true"></span>
        </button>
        <button class="dd-feature-rail-item" data-feature="audiobook" title="오디오북">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
            <span class="dd-feature-rail-label">오디오북</span>
            <span class="dd-feature-rail-dot" aria-hidden="true"></span>
        </button>
        <button class="dd-feature-rail-item" data-feature="storybook" title="스토리북">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 12h18M12 3v18"/></svg>
            <span class="dd-feature-rail-label">스토리북</span>
            <span class="dd-feature-rail-dot" aria-hidden="true"></span>
        </button>
        <button class="dd-feature-rail-item" data-feature="quiz" title="퀴즈">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            <span class="dd-feature-rail-label">퀴즈</span>
            <span class="dd-feature-rail-dot" aria-hidden="true"></span>
        </button>
        <button class="dd-feature-rail-item" data-feature="hanzi" title="한자">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16M4 12h16M4 20h16M8 4v16M16 4v16"/></svg>
            <span class="dd-feature-rail-label">한자</span>
            <span class="dd-feature-rail-dot" aria-hidden="true"></span>
        </button>
        <button class="dd-feature-rail-item" data-feature="vocabulary" title="단어장">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
            <span class="dd-feature-rail-label">단어장</span>
            <span class="dd-feature-rail-dot" aria-hidden="true"></span>
        </button>
    </aside>
    <!-- 토글은 rail 외부에 고정 — collapse transform의 영향을 받지 않음 -->
    <button class="dd-feature-rail-toggle" id="dd-feature-rail-toggle" type="button" title="기능 레일 접기/펼치기" aria-label="기능 레일 접기">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
    </button>

    <!-- ========== 학습 내용 ========== -->
    <div id="panel-content" class="dd-tab-panel is-active">

        <!-- 섹션 네비게이션 사이드바 -->
        <nav class="dd-section-nav" id="dd-section-nav">
            <?php if ( ( $data['lesson_type'] ?? '' ) === 'song' ) : ?>
            <a href="#section-song-mv" class="dd-section-nav-item is-active" data-section="section-song-mv" title="노래 영상">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
                <span>노래 영상</span>
            </a>
            <?php endif; ?>
            <?php if ( ! empty( $data['key_expressions'] ) ) : ?>
            <a href="#section-key-expr" class="dd-section-nav-item<?php echo ( ( $data['lesson_type'] ?? '' ) !== 'song' ) ? ' is-active' : ''; ?>" data-section="section-key-expr" title="핵심 표현">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                <span>핵심 표현</span>
            </a>
            <?php endif; ?>
            <a href="#section-content" class="dd-section-nav-item" data-section="section-content" title="본문">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                <span>본문</span>
            </a>
            <?php if ( ! empty( $data['comic_panels'] ) ) : ?>
            <a href="#section-comic" class="dd-section-nav-item" data-section="section-comic" title="학습만화">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 12h18M12 3v18"/></svg>
                <span>학습만화</span>
            </a>
            <?php endif; ?>
            <?php if ( ! empty( $data['cultural_note'] ) ) : ?>
            <a href="#section-culture" class="dd-section-nav-item" data-section="section-culture" title="문화 노트">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                <span>문화 노트</span>
            </a>
            <?php endif; ?>
            <?php if ( ! empty( $data['dialogue_image'] ) ) : ?>
            <a href="#section-dialogue" class="dd-section-nav-item" data-section="section-dialogue" title="실전 대화">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <span>실전 대화</span>
            </a>
            <?php endif; ?>
            <a href="#section-writing" class="dd-section-nav-item" data-section="section-writing" title="작문 연습">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                <span>작문 연습</span>
            </a>
            <?php if ( ! empty( $data['materials'] ) ) : ?>
            <a href="#section-materials" class="dd-section-nav-item" data-section="section-materials" title="학습 자료">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <span>학습 자료</span>
            </a>
            <?php endif; ?>
            <?php if ( ( $data['lesson_type'] ?? '' ) !== 'song' && ( ! empty( $data['video_embeds'] ) || ! empty( $data['video_keywords'] ) ) ) : ?>
            <a href="#section-video" class="dd-section-nav-item" data-section="section-video" title="관련 영상">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                <span>관련 영상</span>
            </a>
            <?php endif; ?>
        </nav>

        <!-- 노래 모드: MV 임베드 + 가사 3단 뷰어 (lesson_type=song에서만 렌더) -->
        <?php if ( ( $data['lesson_type'] ?? '' ) === 'song' ) :
            $song_video = isset( $data['video_embeds'][0] ) ? $data['video_embeds'][0] : array();
            $song_lyrics = $data['lyrics'] ?? array();
            $song_meta = $data['song_meta'] ?? array();
        ?>
        <div class="dd-lyricsync" id="section-song-mv">
            <button type="button" class="dd-lyricsync-immersive-btn" id="dd-lyricsync-immersive" aria-label="몰입 모드">
                <svg class="dd-imm-off" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3M3 16v3a2 2 0 0 0 2 2h3m13-5v3a2 2 0 0 1-2 2h-3"/></svg>
                <svg class="dd-imm-on" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3M3 16h3a2 2 0 0 1 2 2v3m13-5h-3a2 2 0 0 0-2 2v3"/></svg>
                <span class="dd-imm-label-off">몰입 모드</span>
                <span class="dd-imm-label-on">복귀</span>
            </button>
            <?php if ( ! empty( $song_meta ) ) : ?>
            <div class="dd-song-meta-bar">
                <?php if ( ! empty( $song_meta['mood'] ) ) : ?>
                <span class="dd-song-meta-chip dd-song-meta-mood">🎭 <?php echo esc_html( $song_meta['mood'] ); ?></span>
                <?php endif; ?>
                <?php if ( ! empty( $song_meta['theme'] ) ) : ?>
                <span class="dd-song-meta-chip dd-song-meta-theme">📖 <?php echo esc_html( $song_meta['theme'] ); ?></span>
                <?php endif; ?>
                <?php if ( ! empty( $song_meta['artist_guess'] ) ) : ?>
                <span class="dd-song-meta-chip dd-song-meta-artist">🎤 <?php echo esc_html( $song_meta['artist_guess'] ); ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- 음악 플레이어 스테이지: 영상 (가사는 재생바 아래 풀폭) -->
            <div class="dd-lyricsync-stage">
                <?php if ( ! empty( $song_video['embed_url'] ) ) : ?>
                <div class="dd-lyricsync-video">
                    <div class="dd-song-mv-iframe">
                        <iframe id="dd-lyricsync-iframe"
                                src="<?php echo esc_url( add_query_arg( array( 'enablejsapi' => 1, 'rel' => 0, 'playsinline' => 1, 'controls' => 0, 'modestbranding' => 1 ), $song_video['embed_url'] ) ); ?>"
                                title="<?php echo esc_attr( $song_video['title'] ?? '' ); ?>"
                                frameborder="0"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen></iframe>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- 커스텀 재생바 (liquid glass) -->
            <?php if ( ! empty( $song_video['embed_url'] ) ) : ?>
            <div class="dd-lyricsync-bar" id="dd-lyricsync-bar">
                <button type="button" class="dd-kbar-btn" id="dd-kbar-play" aria-label="재생/일시정지">
                    <svg class="dd-kbar-ic dd-kbar-ic-play" width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                    <svg class="dd-kbar-ic dd-kbar-ic-pause" width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M6 5h4v14H6zM14 5h4v14h-4z"/></svg>
                </button>
                <span class="dd-kbar-time" id="dd-kbar-cur">0:00</span>
                <div class="dd-kbar-track" id="dd-kbar-track" role="slider" aria-label="재생 위치" tabindex="0">
                    <div class="dd-kbar-fill" id="dd-kbar-fill"></div>
                    <div class="dd-kbar-thumb" id="dd-kbar-thumb"></div>
                </div>
                <span class="dd-kbar-time dd-kbar-dur" id="dd-kbar-dur">0:00</span>
            </div>
            <?php endif; ?>

            <!-- 가사 뷰어: 재생바 아래 풀폭 (한 눈에 보이도록) -->
            <?php if ( ! empty( $song_lyrics ) ) : ?>
            <div class="dd-song-lyrics-viewer dd-lyricsync-lyrics" id="section-lyrics">
                <div class="dd-song-lyrics-header">
                    <h3 class="dd-song-lyrics-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
                        가사
                    </h3>
                    <div class="dd-song-lyrics-toggles">
                        <button type="button" class="dd-song-toggle is-active" data-toggle="zh">한자</button>
                        <button type="button" class="dd-song-toggle is-active" data-toggle="pinyin">병음</button>
                        <button type="button" class="dd-song-toggle is-active" data-toggle="ko">한국어</button>
                        <?php if ( ! empty( $data['key_expressions'] ) ) : ?>
                        <button type="button" class="dd-karaoke-toggle" title="핵심어휘를 빈칸으로 — 탭하면 정답 공개">🎤 빈칸 모드</button>
                        <?php endif; ?>
                    </div>
                </div>
                <ol class="dd-song-lyrics-list">
                    <?php foreach ( $song_lyrics as $li => $line ) :
                        $section = $line['section'] ?? 'verse';
                    ?>
                    <li class="dd-song-lyrics-line dd-song-section-<?php echo esc_attr( $section ); ?>"
                        data-line-index="<?php echo (int) $li; ?>"<?php if ( isset( $line['time'] ) && $line['time'] !== '' ) : ?> data-time="<?php echo esc_attr( $line['time'] ); ?>"<?php endif; ?>>
                        <span class="dd-song-lyrics-num"><?php echo (int) ( $li + 1 ); ?></span>
                        <span class="dd-song-section-badge"><?php echo esc_html( strtoupper( $section ) ); ?></span>
                        <div class="dd-song-lyrics-text">
                            <?php if ( ! empty( $line['zh'] ) ) : ?>
                            <span class="dd-song-line-zh"><?php echo DD_Public_Access::highlight_keywords( DD_Public_Access::clean_text( $line['zh'] ), $data['key_expressions'] ?? array(), $data['id'] ?? 0, $data['title'] ?? '' ); ?></span>
                            <?php endif; ?>
                            <?php if ( ! empty( $line['pinyin'] ) ) : ?>
                            <span class="dd-song-line-pinyin"><?php echo esc_html( $line['pinyin'] ); ?></span>
                            <?php endif; ?>
                            <?php if ( ! empty( $line['ko'] ) ) : ?>
                            <span class="dd-song-line-ko"><?php echo esc_html( $line['ko'] ); ?></span>
                            <?php endif; ?>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ol>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- 핵심 표현 섹션 -->
        <?php if ( ! empty( $data['key_expressions'] ) ) : ?>
        <div class="dd-key-expr-section" id="section-key-expr">
            <?php if ( ! empty( $data['key_expr_image'] ) ) : ?>
            <div class="dd-key-expr-banner">
                <img src="<?php echo esc_url( $data['key_expr_image'] ); ?>" alt="<?php echo esc_attr( '핵심 표현 배너' ); ?>" loading="lazy">
            </div>
            <?php endif; ?>
            <h3 class="dd-key-expr-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                <?php echo esc_html( '핵심 표현' ); ?>
            </h3>
            <div class="dd-key-expr-grid">
                <?php
                $ke_colors = array(
                    array( '#FFE4E8', '#DB7F8E' ),
                    array( '#E8F4FD', '#5B8CDB' ),
                    array( '#E8F8E8', '#4CAF50' ),
                    array( '#FFF3E0', '#FF9800' ),
                    array( '#F3E8FF', '#9C27B0' ),
                    array( '#E0F7FA', '#00838F' ),
                    array( '#FFF8E1', '#F9A825' ),
                    array( '#FCE4EC', '#C2185B' ),
                    array( '#E8EAF6', '#3F51B5' ),
                );
                foreach ( $data['key_expressions'] as $ki => $kexpr ) :
                    $kc = $ke_colors[ $ki % count( $ke_colors ) ];
                ?>
                <div class="dd-key-expr-card" style="background: <?php echo esc_attr( $kc[0] ); ?>; border-left: 4px solid <?php echo esc_attr( $kc[1] ); ?>;">
                    <div class="dd-ke-head">
                        <span class="dd-ke-num" style="background: <?php echo esc_attr( $kc[1] ); ?>;"><?php echo $ki + 1; ?></span>
                    </div>
                    <span class="dd-ke-zh"><?php echo esc_html( DD_Public_Access::clean_text( $kexpr['zh'] ?? '' ) ); ?></span>
                    <span class="dd-ke-pinyin"><?php echo esc_html( $kexpr['pinyin'] ?? '' ); ?></span>
                    <span class="dd-ke-ko"><?php echo esc_html( $kexpr['ko'] ?? '' ); ?></span>
                    <div class="dd-ke-actions">
                        <button class="dd-ke-btn dd-ke-listen"
                                data-zh="<?php echo esc_attr( DD_Public_Access::chinese_only( $kexpr['zh'] ?? '' ) ); ?>"
                                title="원어민 발음 듣기">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>
                            <span>듣기</span>
                        </button>
                        <button class="dd-ke-btn dd-ke-save"
                                data-zh="<?php echo esc_attr( DD_Public_Access::clean_text( $kexpr['zh'] ?? '' ) ); ?>"
                                data-pinyin="<?php echo esc_attr( $kexpr['pinyin'] ?? '' ); ?>"
                                data-ko="<?php echo esc_attr( $kexpr['ko'] ?? '' ); ?>"
                                data-hsk="<?php echo esc_attr( $kexpr['hsk'] ?? '' ); ?>"
                                data-lesson-id="<?php echo esc_attr( $data['id'] ); ?>"
                                data-lesson-title="<?php echo esc_attr( $data['title'] ); ?>"
                                title="단어장에 저장">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                            <span>저장</span>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 본문 콘텐츠 (마크다운 → HTML) -->
        <div class="dd-content" id="section-content">
            <?php echo DD_Public_Access::render_markdown( $data['content'] ); ?>
        </div>

        <!-- 학습만화 (하이브리드 그리드: AI 이미지 + HTML 텍스트 오버레이) -->
        <?php if ( ! empty( $data['comic_panels'] ) ) : ?>
        <div class="dd-comic-section" id="section-comic">
            <h3 class="dd-comic-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 12h18M12 3v18"/></svg>
                <?php echo esc_html( '학습만화' ); ?>
            </h3>
            <div class="dd-comic-hybrid-grid">
                <?php
                $comic_images = $data['comic_images'] ?? array();
                $level = $data['level'] ?? 'beginner';
                foreach ( array_slice( $data['comic_panels'], 0, 4 ) as $pi => $panel ) :
                    $panel_img = isset( $comic_images[ $pi ] ) ? $comic_images[ $pi ] : '';
                    $dialogues = ! empty( $panel['dialogue'] ) ? $panel['dialogue'] : array();
                    $first_dl  = ! empty( $dialogues[0] ) ? $dialogues[0] : array();
                    $zh_text   = $first_dl['zh'] ?? '';
                    $pinyin    = $first_dl['pinyin'] ?? '';
                    $ko_text   = $first_dl['ko'] ?? '';
                    $speaker   = $first_dl['speaker'] ?? '';
                    $narration = $panel['narration'] ?? ( $panel['scene'] ?? '' );
                ?>
                <div class="dd-comic-hybrid-panel">
                    <span class="dd-comic-panel-num"><?php echo esc_html( $pi + 1 ); ?></span>
                    <?php if ( ! empty( $panel_img ) ) : ?>
                    <div class="dd-comic-hybrid-img">
                        <img src="<?php echo esc_url( $panel_img ); ?>" alt="<?php echo esc_attr( '패널 ' . ( $pi + 1 ) ); ?>" loading="lazy">
                    </div>
                    <?php else : ?>
                    <div class="dd-comic-hybrid-img dd-comic-hybrid-placeholder">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity="0.3"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                    </div>
                    <?php endif; ?>
                    <div class="dd-comic-hybrid-text">
                        <?php if ( ! empty( $speaker ) ) : ?>
                        <span class="dd-comic-hybrid-speaker"><?php echo esc_html( $speaker ); ?></span>
                        <?php endif; ?>
                        <?php if ( ! empty( $zh_text ) ) : ?>
                        <span class="dd-comic-hybrid-zh"><?php echo esc_html( $zh_text ); ?></span>
                        <?php endif; ?>
                        <?php if ( $level === 'beginner' && ! empty( $pinyin ) ) : ?>
                        <span class="dd-comic-hybrid-pinyin"><?php echo esc_html( $pinyin ); ?></span>
                        <?php endif; ?>
                        <?php if ( ! empty( $ko_text ) ) : ?>
                        <span class="dd-comic-hybrid-ko"><?php echo esc_html( $ko_text ); ?></span>
                        <?php elseif ( ! empty( $narration ) ) : ?>
                        <span class="dd-comic-hybrid-ko"><?php echo esc_html( $narration ); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 문화 노트 (구조화 또는 텍스트) -->
        <?php if ( ! empty( $data['cultural_note'] ) ) : ?>
        <div class="dd-cultural-note" id="section-culture">
            <div class="dd-cultural-note-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
            </div>
            <?php if ( is_array( $data['cultural_note'] ) ) : ?>
            <div class="dd-cultural-note-body dd-cultural-note-rich">
                <h3><?php echo esc_html( '문화 노트' ); ?></h3>
                <?php if ( ! empty( $data['cultural_note']['summary'] ) ) : ?>
                <p class="dd-cn-summary"><?php echo esc_html( $data['cultural_note']['summary'] ); ?></p>
                <?php endif; ?>

                <?php if ( ! empty( $data['cultural_note']['background'] ) ) : ?>
                <div class="dd-cn-block">
                    <h4><span class="dd-cn-block-icon">📖</span> 문화 배경</h4>
                    <p><?php echo wp_kses_post( DD_Public_Access::inline_format( $data['cultural_note']['background'] ) ); ?></p>
                </div>
                <?php endif; ?>

                <?php if ( ! empty( $data['cultural_note']['fun_facts'] ) && is_array( $data['cultural_note']['fun_facts'] ) ) : ?>
                <div class="dd-cn-block">
                    <h4><span class="dd-cn-block-icon">💡</span> 재미있는 사실</h4>
                    <ul class="dd-cn-facts">
                        <?php foreach ( $data['cultural_note']['fun_facts'] as $fact ) : ?>
                        <li><?php echo esc_html( $fact ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if ( ! empty( $data['cultural_note']['comparison'] ) ) : ?>
                <div class="dd-cn-block dd-cn-comparison">
                    <h4><span class="dd-cn-block-icon">🇰🇷🇨🇳</span> 한중 비교</h4>
                    <p><?php echo esc_html( $data['cultural_note']['comparison'] ); ?></p>
                </div>
                <?php endif; ?>

                <?php if ( ! empty( $data['cultural_note']['related_expression'] ) ) : ?>
                <?php $re = $data['cultural_note']['related_expression']; ?>
                <div class="dd-cn-expr">
                    <span class="dd-cn-expr-label">관련 표현</span>
                    <span class="dd-cn-expr-zh"><?php echo esc_html( $re['zh'] ?? '' ); ?></span>
                    <span class="dd-cn-expr-pinyin"><?php echo esc_html( $re['pinyin'] ?? '' ); ?></span>
                    <span class="dd-cn-expr-ko"><?php echo esc_html( $re['ko'] ?? '' ); ?></span>
                </div>
                <?php endif; ?>

                <?php if ( ! empty( $data['cultural_note']['did_you_know'] ) ) : ?>
                <div class="dd-cn-trivia">
                    <span class="dd-cn-trivia-icon">🤔</span>
                    <span><?php echo esc_html( $data['cultural_note']['did_you_know'] ); ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php else : ?>
            <div class="dd-cultural-note-body">
                <h3><?php echo esc_html( '문화 노트' ); ?></h3>
                <p><?php echo wp_kses_post( DD_Public_Access::inline_format( $data['cultural_note'] ) ); ?></p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- 실전 대화 장면 이미지 — 본문 "실전 대화" heading 바로 아래 배치 -->
        <?php if ( ! empty( $data['dialogue_image'] ) ) : ?>
        <div class="dd-dialogue-image" id="section-dialogue">
            <img src="<?php echo esc_url( $data['dialogue_image'] ); ?>" alt="<?php echo esc_attr( '실전 대화 장면' ); ?>" loading="lazy">
        </div>
        <?php endif; ?>


        <!-- 작문 연습 -->
        <?php if ( ! empty( $data['key_expressions'] ) ) : ?>
        <div class="dd-writing-section" id="section-writing" data-expressions="<?php echo esc_attr( wp_json_encode( array_map( function( $e ) { return array( 'zh' => $e['zh'] ?? '', 'ko' => $e['ko'] ?? '' ); }, $data['key_expressions'] ), JSON_UNESCAPED_UNICODE ) ); ?>">
            <h3 class="dd-writing-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                작문 연습
            </h3>
            <p class="dd-writing-desc">아래 핵심 어휘를 활용하여 중국어 문장을 작성해 보세요. AI가 문법, 어휘 활용, 자연스러움을 채점합니다.</p>
            <div class="dd-writing-keywords">
                <?php foreach ( array_slice( $data['key_expressions'], 0, 6 ) as $expr ) : ?>
                <span class="dd-writing-kw"><?php echo esc_html( $expr['zh'] ?? '' ); ?> <small><?php echo esc_html( $expr['ko'] ?? '' ); ?></small></span>
                <?php endforeach; ?>
            </div>
            <div id="dd-writing-key-notice" class="dd-writing-key-notice" style="display:none;">
                <p>작문 채점을 이용하려면 본인의 Gemini API 키를 등록해주세요.</p>
                <button class="dd-open-key-modal dd-writing-key-btn">API 키 설정</button>
            </div>
            <div id="dd-writing-form" style="display:none;">
                <textarea id="dd-writing-input" class="dd-writing-input" rows="4" placeholder="중국어로 문장을 작성하세요..."></textarea>
                <div class="dd-writing-actions">
                    <span class="dd-writing-counter" id="dd-writing-counter">0자</span>
                    <button class="dd-writing-submit" id="dd-writing-submit">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        채점하기
                    </button>
                </div>
                <div id="dd-writing-result" class="dd-writing-result" style="display:none;"></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- 학습 자료 뷰어 -->
        <?php if ( ! empty( $data['materials'] ) ) : ?>
        <div class="dd-materials-section" id="section-materials">
            <h3 class="dd-materials-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                학습 자료
            </h3>
            <div class="dd-materials-list">
                <?php foreach ( $data['materials'] as $mi => $mat ) :
                    $ext = strtolower( $mat['filetype'] ?? '' );
                    $ext_colors = array( 'pdf' => '#E53935', 'docx' => '#1565C0', 'doc' => '#1565C0', 'pptx' => '#D84315', 'ppt' => '#D84315', 'xlsx' => '#2E7D32', 'xls' => '#2E7D32' );
                    $ext_color = $ext_colors[ $ext ] ?? '#666';
                ?>
                <div class="dd-material-item">
                    <div class="dd-material-info">
                        <span class="dd-material-ext" style="background: <?php echo esc_attr( $ext_color ); ?>;"><?php echo esc_html( strtoupper( $ext ) ); ?></span>
                        <span class="dd-material-name"><?php echo esc_html( $mat['filename'] ?? '' ); ?></span>
                        <a href="<?php echo esc_url( $mat['url'] ?? '' ); ?>" target="_blank" class="dd-material-dl" download>다운로드</a>
                        <?php if ( $ext === 'pdf' ) : ?>
                        <button class="dd-material-view-btn" data-index="<?php echo $mi; ?>" data-url="<?php echo esc_attr( $mat['url'] ?? '' ); ?>" data-type="pdf">미리보기</button>
                        <?php elseif ( in_array( $ext, array( 'docx', 'doc', 'pptx', 'ppt', 'xlsx', 'xls' ), true ) ) : ?>
                        <button class="dd-material-view-btn" data-index="<?php echo $mi; ?>" data-url="<?php echo esc_attr( $mat['url'] ?? '' ); ?>" data-type="office">미리보기</button>
                        <?php endif; ?>
                    </div>
                    <div class="dd-material-viewer" id="dd-mat-viewer-<?php echo $mi; ?>" style="display:none;"></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- YouTube 임베드 영상 (노래 강좌는 상단 노래 영상과 중복이므로 제외) -->
        <?php if ( ( $data['lesson_type'] ?? '' ) !== 'song' && ! empty( $data['video_embeds'] ) ) : ?>
        <div class="dd-video-section" id="section-video">
            <h3 class="dd-video-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                <?php echo esc_html( '관련 영상' ); ?>
            </h3>
            <div class="dd-video-embed-grid">
                <?php foreach ( $data['video_embeds'] as $ve ) : ?>
                    <?php if ( ! empty( $ve['embed_url'] ) ) : ?>
                    <div class="dd-video-embed-item">
                        <div class="dd-video-responsive">
                            <iframe src="<?php echo esc_url( $ve['embed_url'] ); ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>
                        </div>
                        <?php if ( ! empty( $ve['title'] ) ) : ?>
                        <p class="dd-video-embed-title"><?php echo esc_html( $ve['title'] ); ?></p>
                        <?php endif; ?>
                        <?php if ( ! empty( $ve['channel'] ) ) : ?>
                        <p class="dd-video-embed-channel"><?php echo esc_html( $ve['channel'] ); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php elseif ( ( $data['lesson_type'] ?? '' ) !== 'song' && ! empty( $data['video_keywords'] ) ) : ?>
        <!-- YouTube API 비활성 시 검색 링크 표시 -->
        <div class="dd-video-section dd-video-fallback" id="section-video">
            <h3 class="dd-video-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                <?php echo esc_html( '관련 영상' ); ?>
            </h3>
            <div class="dd-video-fallback-notice">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                <span><?php echo esc_html( 'YouTube 영상 임베드를 사용하려면 관리자 설정에서 YouTube API 키를 등록하세요.' ); ?></span>
            </div>
            <div class="dd-video-links">
                <?php foreach ( $data['video_keywords'] as $kw ) :
                    $yt_url = 'https://www.youtube.com/results?search_query=' . rawurlencode( $kw );
                ?>
                <div class="dd-video-keyword">
                    <span class="dd-kw-label"><?php echo esc_html( $kw ); ?></span>
                    <a href="<?php echo esc_url( $yt_url ); ?>" target="_blank" rel="noopener noreferrer" class="dd-kw-btn dd-kw-yt">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814z"/><polygon fill="#fff" points="9.545 15.568 15.818 12 9.545 8.432"/></svg>
                        YouTube <?php echo esc_html( '검색' ); ?>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>


    </div>

    <!-- ========== 슬라이드 ========== -->
    <div id="panel-slides" class="dd-tab-panel dd-slides-panel">
        <?php
        $slide_icons = array( '📖', '✨', '📝', '💬', '🎯' );
        $slide_colors = array(
            array( '#FFE4E8', '#DB7F8E' ),
            array( '#E8F4FD', '#5B8CDB' ),
            array( '#E8F8E8', '#4CAF50' ),
            array( '#FFF3E0', '#FF9800' ),
            array( '#F3E8FF', '#9C27B0' ),
        );
        ?>
        <div class="dd-slides">
            <?php foreach ( $data['slides'] as $i => $slide ) :
                $color = $slide_colors[ $i % count( $slide_colors ) ];
                $icon  = $slide_icons[ $i % count( $slide_icons ) ];
            ?>
            <div class="dd-slide<?php echo $i === 0 ? ' is-active' : ''; ?>">
                <div class="dd-slide-header" style="background: <?php echo esc_attr( $color[0] ); ?>; border-left: 4px solid <?php echo esc_attr( $color[1] ); ?>;">
                    <span class="dd-slide-number" style="background: <?php echo esc_attr( $color[1] ); ?>;"><?php echo $i + 1; ?></span>
                    <div>
                        <h2><?php echo esc_html( $slide['title'] ?? '' ); ?> <?php echo $icon; ?></h2>
                        <?php if ( ! empty( $slide['subtitle'] ) ) : ?>
                        <p class="dd-slide-subtitle"><?php echo esc_html( $slide['subtitle'] ); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ( ! empty( $slide['key_point'] ) ) : ?>
                <div class="dd-slide-section dd-slide-keypoint" style="border-left: 4px solid <?php echo esc_attr( $color[1] ); ?>;">
                    <h4>🎯 <?php echo esc_html( '핵심 포인트' ); ?></h4>
                    <p><?php echo esc_html( $slide['key_point'] ); ?></p>
                </div>
                <?php endif; ?>

                <?php if ( ! empty( $slide['bullets'] ) ) : ?>
                <div class="dd-slide-section">
                    <ul class="dd-slide-bullets">
                        <?php foreach ( $slide['bullets'] as $bi => $bullet ) : ?>
                        <li>
                            <span class="dd-bullet-marker" style="background: <?php echo esc_attr( $color[0] ); ?>; color: <?php echo esc_attr( $color[1] ); ?>;"><?php echo $bi + 1; ?></span>
                            <span><?php echo esc_html( $bullet ); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if ( ! empty( $slide['examples'] ) ) : ?>
                <div class="dd-slide-section dd-slide-examples">
                    <h4>📖 <?php echo esc_html( '예문' ); ?></h4>
                    <?php foreach ( $slide['examples'] as $ex ) : ?>
                    <div class="dd-slide-example-card">
                        <p class="dd-ex-zh"><?php echo esc_html( DD_Public_Access::clean_text( $ex['zh'] ?? '' ) ); ?></p>
                        <p class="dd-ex-pinyin"><?php echo esc_html( $ex['pinyin'] ?? '' ); ?></p>
                        <p class="dd-ex-ko"><?php echo esc_html( $ex['ko'] ?? '' ); ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ( ! empty( $slide['vocab'] ) ) : ?>
                <div class="dd-slide-section">
                    <h4>💡 <?php echo esc_html( '핵심 어휘' ); ?></h4>
                    <div class="dd-vocab-grid">
                        <?php foreach ( $slide['vocab'] as $vi => $v ) :
                            $vc = $slide_colors[ $vi % count( $slide_colors ) ];
                        ?>
                        <div class="dd-vocab-card" style="background: <?php echo esc_attr( $vc[0] ); ?>; border-left: 3px solid <?php echo esc_attr( $vc[1] ); ?>;">
                            <span class="dd-vc-zh"><?php echo esc_html( DD_Public_Access::clean_text( $v['zh'] ?? '' ) ); ?></span>
                            <span class="dd-vc-pinyin"><?php echo esc_html( $v['pinyin'] ?? '' ); ?></span>
                            <span class="dd-vc-ko"><?php echo esc_html( $v['ko'] ?? '' ); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ( ! empty( $slide['usage_context'] ) ) : ?>
                <div class="dd-slide-section dd-slide-usage">
                    <h4>📍 <?php echo esc_html( '언제 사용?' ); ?></h4>
                    <p><?php echo esc_html( $slide['usage_context'] ); ?></p>
                </div>
                <?php endif; ?>

                <?php if ( ! empty( $slide['common_mistake'] ) ) : ?>
                <div class="dd-slide-section dd-slide-mistake">
                    <h4>⚠️ <?php echo esc_html( '자주 틀리는 부분' ); ?></h4>
                    <p><?php echo esc_html( $slide['common_mistake'] ); ?></p>
                </div>
                <?php endif; ?>

                <?php if ( ! empty( $slide['practice'] ) && ! empty( $slide['practice']['question_ko'] ) ) : ?>
                <div class="dd-slide-section dd-slide-practice">
                    <h4>✍️ <?php echo esc_html( '직접 연습해보기' ); ?></h4>
                    <p class="dd-practice-q"><?php echo esc_html( $slide['practice']['question_ko'] ); ?></p>
                    <details class="dd-practice-answer">
                        <summary>👁 정답 확인</summary>
                        <div class="dd-practice-answer-body">
                            <?php if ( ! empty( $slide['practice']['answer_zh'] ) ) : ?>
                            <p class="dd-pa-zh"><?php echo esc_html( DD_Public_Access::clean_text( $slide['practice']['answer_zh'] ) ); ?></p>
                            <?php endif; ?>
                            <?php if ( ! empty( $slide['practice']['answer_pinyin'] ) ) : ?>
                            <p class="dd-pa-pinyin"><?php echo esc_html( $slide['practice']['answer_pinyin'] ); ?></p>
                            <?php endif; ?>
                            <?php if ( ! empty( $slide['practice']['answer_ko'] ) ) : ?>
                            <p class="dd-pa-ko"><?php echo esc_html( $slide['practice']['answer_ko'] ); ?></p>
                            <?php endif; ?>
                        </div>
                    </details>
                </div>
                <?php endif; ?>

                <?php if ( ! empty( $slide['tip'] ) ) : ?>
                <div class="dd-slide-tip">
                    <span class="dd-tip-icon">💡</span>
                    <p><?php echo esc_html( $slide['tip'] ); ?></p>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ( ! empty( $data['slides'] ) ) : ?>
        <div class="dd-slide-controls">
            <button class="dd-slide-btn" id="dd-slide-prev" disabled>&larr; <?php echo esc_html( '이전' ); ?></button>
            <span class="dd-slide-indicator" id="dd-slide-indicator">1 / <?php echo count( $data['slides'] ); ?></span>
            <button class="dd-slide-btn" id="dd-slide-next"><?php echo esc_html( '다음' ); ?> &rarr;</button>
            <button class="dd-slide-btn dd-slide-pdf-btn" id="dd-slide-pdf-btn" title="모든 슬라이드를 PDF로 저장 (브라우저 인쇄 → PDF로 저장)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                슬라이드 PDF 다운로드
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- ========== 오디오북 ========== -->
    <div id="panel-audiobook" class="dd-tab-panel">
        <div class="dd-audiobook">
            <div class="dd-ab-header">
                <h3 class="dd-ab-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
                    <?php echo esc_html( '음성으로 듣기' ); ?>
                </h3>
                <div class="dd-ab-controls">
                    <button class="dd-ab-play-all" id="dd-ab-play-all">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                        <?php echo esc_html( '전체 듣기' ); ?>
                    </button>
                    <button class="dd-ab-stop" id="dd-ab-stop" style="display:none;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="6" width="12" height="12"/></svg>
                        <?php echo esc_html( '정지' ); ?>
                    </button>
                    <div class="dd-ab-speed">
                        <button class="dd-ab-speed-btn" data-rate="0.5">0.5x</button>
                        <button class="dd-ab-speed-btn" data-rate="0.75">0.75x</button>
                        <button class="dd-ab-speed-btn is-active" data-rate="1">1x</button>
                    </div>
                </div>
            </div>

            <div id="dd-ab-no-support" class="dd-ab-notice" style="display:none;">
                <p><?php echo esc_html( '이 브라우저에서는 음성 재생을 지원하지 않습니다. Chrome 브라우저를 사용해 주세요.' ); ?></p>
            </div>

            <?php if ( ! empty( $data['key_expressions'] ) ) : ?>
            <div class="dd-ab-section">
                <h4 class="dd-ab-section-title"><?php echo esc_html( '핵심 표현' ); ?></h4>
                <p class="dd-ab-guide">🔊 <strong>▶ 버튼</strong>을 누르면 중국어 원어민 발음을 들을 수 있어요. 따라 읽으며 발음을 연습해보세요!</p>
                <div class="dd-ab-list">
                    <?php foreach ( $data['key_expressions'] as $i => $expr ) : ?>
                    <div class="dd-ab-item" data-zh="<?php echo esc_attr( DD_Public_Access::chinese_only( $expr['zh'] ?? '' ) ); ?>" data-type="expr" data-index="<?php echo $i; ?>">
                        <button class="dd-ab-play-btn" title="<?php echo esc_attr( '클릭하여 발음 듣기' ); ?>">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                        </button>
                        <div class="dd-ab-text">
                            <span class="dd-ab-zh"><?php echo esc_html( DD_Public_Access::clean_text( $expr['zh'] ?? '' ) ); ?></span>
                            <span class="dd-ab-pinyin"><?php echo esc_html( DD_Public_Access::clean_text( $expr['pinyin'] ?? '' ) ); ?></span>
                            <span class="dd-ab-ko"><?php echo esc_html( DD_Public_Access::clean_text( $expr['ko'] ?? '' ) ); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ( ! empty( $data['dialogues'] ) ) : ?>
            <div class="dd-ab-section">
                <h4 class="dd-ab-section-title"><?php echo esc_html( '실전 대화' ); ?></h4>
                <p class="dd-ab-guide">🎭 대화문을 <strong>한 줄씩 ▶ 재생</strong>하며 따라 읽어보세요. 화자별로 다른 목소리로 읽어줍니다!</p>
                <?php if ( ! empty( $data['dialogue_image'] ) ) : ?>
                <div class="dd-ab-scene-img">
                    <img src="<?php echo esc_url( $data['dialogue_image'] ); ?>" alt="<?php echo esc_attr( '오디오북 장면' ); ?>" loading="lazy">
                </div>
                <?php endif; ?>
                <div class="dd-ab-list">
                    <?php foreach ( $data['dialogues'] as $di => $dl ) : ?>
                    <div class="dd-ab-item" data-zh="<?php echo esc_attr( DD_Public_Access::chinese_only( $dl['zh'] ?? '' ) ); ?>" data-type="dialogue" data-index="<?php echo $di; ?>">
                        <button class="dd-ab-play-btn" title="<?php echo esc_attr( '클릭하여 발음 듣기' ); ?>">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                        </button>
                        <div class="dd-ab-text">
                            <span class="dd-ab-speaker"><?php echo esc_html( $dl['speaker'] ?? '' ); ?></span>
                            <span class="dd-ab-zh"><?php echo esc_html( DD_Public_Access::clean_text( $dl['zh'] ?? '' ) ); ?></span>
                            <?php if ( ! empty( $dl['pinyin'] ) ) : ?>
                            <span class="dd-ab-pinyin"><?php echo esc_html( $dl['pinyin'] ); ?></span>
                            <?php endif; ?>
                            <?php if ( ! empty( $dl['ko'] ) ) : ?>
                            <span class="dd-ab-ko"><?php echo esc_html( $dl['ko'] ); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ( empty( $data['key_expressions'] ) && empty( $data['dialogues'] ) ) : ?>
            <p class="dd-ab-empty"><?php echo esc_html( '재생할 콘텐츠가 없습니다.' ); ?></p>
            <?php endif; ?>

            <?php if ( ! empty( $data['vocab_comparison'] ) ) : ?>
            <div class="dd-ab-extra">
                <h4 class="dd-ab-extra-title">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    실전 어휘 비교
                </h4>
                <?php foreach ( $data['vocab_comparison'] as $vc ) : ?>
                <div class="dd-ab-vc-card">
                    <div class="dd-ab-vc-pair">
                        <div class="dd-ab-vc-word">
                            <span class="dd-ab-vc-zh"><?php echo esc_html( $vc['word_a']['zh'] ?? '' ); ?></span>
                            <span class="dd-ab-vc-pinyin"><?php echo esc_html( $vc['word_a']['pinyin'] ?? '' ); ?></span>
                            <span class="dd-ab-vc-ko"><?php echo esc_html( $vc['word_a']['ko'] ?? '' ); ?></span>
                        </div>
                        <span class="dd-ab-vc-vs">VS</span>
                        <div class="dd-ab-vc-word">
                            <span class="dd-ab-vc-zh"><?php echo esc_html( $vc['word_b']['zh'] ?? '' ); ?></span>
                            <span class="dd-ab-vc-pinyin"><?php echo esc_html( $vc['word_b']['pinyin'] ?? '' ); ?></span>
                            <span class="dd-ab-vc-ko"><?php echo esc_html( $vc['word_b']['ko'] ?? '' ); ?></span>
                        </div>
                    </div>
                    <p class="dd-ab-vc-diff"><?php echo esc_html( $vc['diff'] ?? '' ); ?></p>
                    <?php if ( ! empty( $vc['example_a'] ) ) : ?>
                    <div class="dd-ab-vc-examples">
                        <div class="dd-ab-vc-ex"><span class="dd-ab-vc-ex-zh"><?php echo esc_html( $vc['example_a']['zh'] ?? '' ); ?></span> <span class="dd-ab-vc-ex-ko"><?php echo esc_html( $vc['example_a']['ko'] ?? '' ); ?></span></div>
                        <div class="dd-ab-vc-ex"><span class="dd-ab-vc-ex-zh"><?php echo esc_html( $vc['example_b']['zh'] ?? '' ); ?></span> <span class="dd-ab-vc-ex-ko"><?php echo esc_html( $vc['example_b']['ko'] ?? '' ); ?></span></div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ( ! empty( $data['cultural_snippet']['title'] ) ) : ?>
            <div class="dd-ab-extra">
                <h4 class="dd-ab-extra-title">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                    문화 한 토막
                </h4>
                <div class="dd-ab-cs-card">
                    <h5 class="dd-ab-cs-title"><?php echo esc_html( $data['cultural_snippet']['title'] ); ?></h5>
                    <p class="dd-ab-cs-content"><?php echo esc_html( $data['cultural_snippet']['content'] ?? '' ); ?></p>
                    <?php if ( ! empty( $data['cultural_snippet']['related_expression'] ) ) :
                        $re = $data['cultural_snippet']['related_expression']; ?>
                    <div class="dd-ab-cs-expr">
                        <span class="dd-ab-cs-zh"><?php echo esc_html( $re['zh'] ?? '' ); ?></span>
                        <span class="dd-ab-cs-pinyin"><?php echo esc_html( $re['pinyin'] ?? '' ); ?></span>
                        <span class="dd-ab-cs-ko"><?php echo esc_html( $re['ko'] ?? '' ); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ========== 스토리북 ========== -->
    <div id="panel-storybook" class="dd-tab-panel">
        <?php if ( empty( $data['storybook'] ) ) : ?>
        <p class="dd-sb-empty"><?php echo esc_html( '스토리북이 아직 준비되지 않았습니다.' ); ?></p>
        <?php else : ?>
        <div class="dd-storybook">
            <div class="dd-storybook-pages">
                <?php foreach ( $data['storybook'] as $si => $sp ) : ?>
                <div class="dd-storybook-page<?php echo $si === 0 ? ' is-active' : ''; ?>">
                    <?php if ( ! empty( $sp['image_url'] ) ) : ?>
                    <div class="dd-sb-img">
                        <img src="<?php echo esc_url( $sp['image_url'] ); ?>" alt="<?php echo esc_attr( '스토리북 ' . ( $si + 1 ) . '페이지' ); ?>" loading="lazy">
                    </div>
                    <?php else : ?>
                    <div class="dd-sb-img dd-sb-img-placeholder">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                    </div>
                    <?php endif; ?>
                    <div class="dd-sb-text">
                        <p class="dd-sb-zh"><?php echo esc_html( $sp['text_zh'] ?? '' ); ?></p>
                        <p class="dd-sb-pinyin"><?php echo esc_html( $sp['pinyin'] ?? '' ); ?></p>
                        <p class="dd-sb-ko"><?php echo esc_html( $sp['text_ko'] ?? '' ); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="dd-sb-controls">
                <button class="dd-sb-btn" id="dd-sb-prev" disabled>&larr; <?php echo esc_html( '이전' ); ?></button>
                <span class="dd-sb-indicator" id="dd-sb-indicator">1 / <?php echo count( $data['storybook'] ); ?></span>
                <button class="dd-sb-btn" id="dd-sb-next"><?php echo esc_html( '다음' ); ?> &rarr;</button>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ========== 퀴즈 ========== -->
    <div id="panel-quiz" class="dd-tab-panel">
        <?php if ( empty( $data['quiz'] ) ) : ?>
        <p style="text-align:center; color:var(--dd-text-light); padding:2rem;"><?php echo esc_html( '퀴즈가 아직 준비되지 않았습니다.' ); ?></p>
        <?php else : ?>
            <?php foreach ( $data['quiz'] as $qi => $q ) :
                $qtype = $q['type'] ?? 'choice';
            ?>

            <?php if ( $qtype === 'fill' ) : ?>
            <!-- 빈칸 채우기 -->
            <div class="dd-quiz-item dd-quiz-fill" data-answer="<?php echo esc_attr( $q['answer'] ?? '' ); ?>">
                <p class="dd-quiz-number"><?php echo esc_html( '문제 ' . ( $qi + 1 ) . ' ✏️' ); ?></p>
                <p class="dd-quiz-question"><?php echo esc_html( $q['question_ko'] ?? '' ); ?></p>
                <p class="dd-quiz-sentence"><?php echo esc_html( $q['sentence_zh'] ?? '' ); ?></p>
                <?php if ( ! empty( $q['hint'] ) ) : ?>
                <p class="dd-quiz-hint"><?php echo esc_html( '힌트: ' . $q['hint'] ); ?></p>
                <?php endif; ?>
                <div class="dd-quiz-fill-row">
                    <input type="text" class="dd-quiz-fill-input" placeholder="<?php echo esc_attr( '정답 입력...' ); ?>">
                    <button class="dd-quiz-fill-check"><?php echo esc_html( '확인' ); ?></button>
                </div>
                <div class="dd-quiz-fill-result"></div>
                <?php if ( ! empty( $q['explanation'] ) ) : ?>
                <div class="dd-quiz-explanation"><?php echo esc_html( $q['explanation'] ); ?></div>
                <?php endif; ?>
            </div>

            <?php elseif ( $qtype === 'order' ) : ?>
            <!-- 어순 배열 -->
            <div class="dd-quiz-item dd-quiz-order" data-correct-order="<?php echo esc_attr( wp_json_encode( $q['correct_order'] ?? array() ) ); ?>" data-answer-text="<?php echo esc_attr( $q['answer_text'] ?? '' ); ?>">
                <p class="dd-quiz-number"><?php echo esc_html( '문제 ' . ( $qi + 1 ) . ' 🔀' ); ?></p>
                <p class="dd-quiz-question"><?php echo esc_html( $q['question_ko'] ?? '' ); ?></p>
                <div class="dd-quiz-word-bank">
                    <?php foreach ( ( $q['words'] ?? array() ) as $wi => $word ) : ?>
                    <button class="dd-quiz-word" data-word-index="<?php echo $wi; ?>"><?php echo esc_html( $word ); ?></button>
                    <?php endforeach; ?>
                </div>
                <div class="dd-quiz-order-answer">
                    <div class="dd-quiz-order-slots"></div>
                    <button class="dd-quiz-order-reset"><?php echo esc_html( '다시' ); ?></button>
                    <button class="dd-quiz-order-check"><?php echo esc_html( '확인' ); ?></button>
                </div>
                <div class="dd-quiz-order-result"></div>
                <?php if ( ! empty( $q['explanation'] ) ) : ?>
                <div class="dd-quiz-explanation"><?php echo esc_html( $q['explanation'] ); ?></div>
                <?php endif; ?>
            </div>

            <?php else : ?>
            <!-- 4지선다 -->
            <div class="dd-quiz-item" data-correct="<?php echo esc_attr( $q['correct'] ?? 0 ); ?>">
                <p class="dd-quiz-number"><?php echo esc_html( '문제 ' . ( $qi + 1 ) ); ?></p>
                <p class="dd-quiz-question"><?php echo esc_html( $q['question_ko'] ?? '' ); ?></p>
                <?php if ( ! empty( $q['question_zh'] ) ) : ?>
                <p class="dd-quiz-question-zh"><?php echo esc_html( $q['question_zh'] ); ?></p>
                <?php endif; ?>
                <div class="dd-quiz-options">
                    <?php foreach ( ( $q['options'] ?? array() ) as $oi => $opt ) : ?>
                    <button class="dd-quiz-option" data-index="<?php echo $oi; ?>"><?php echo esc_html( $opt ); ?></button>
                    <?php endforeach; ?>
                </div>
                <?php if ( ! empty( $q['explanation'] ) ) : ?>
                <div class="dd-quiz-explanation"><?php echo esc_html( $q['explanation'] ); ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ========== 한자 (쓰기 연습 + 고사성어) ========== -->
    <div id="panel-hanzi" class="dd-tab-panel">
        <div id="dd-hanzi-panel"></div>
    </div>

    <!-- ========== 단어장 ========== -->
    <div id="panel-vocabulary" class="dd-tab-panel">
        <div id="dd-vocab-panel" data-lesson-id="<?php echo esc_attr( $data['id'] ); ?>"></div>
    </div>

</div>

<!-- ========== 이전/다음 강의 네비게이션 ========== -->
<?php if ( $data['prev_lesson'] || $data['next_lesson'] ) : ?>
<nav class="dd-lesson-nav">
    <div class="dd-lesson-nav-inner">
        <?php if ( $data['prev_lesson'] ) : ?>
        <a href="<?php echo esc_url( $data['prev_lesson']['url'] ); ?>" class="dd-lesson-nav-btn dd-lesson-nav-prev">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            <span>
                <small>이전 강의<?php if ( ! empty( $data['prev_lesson']['course'] ) ) echo ' &middot; ' . esc_html( $data['prev_lesson']['course'] ); ?></small>
                <?php echo esc_html( $data['prev_lesson']['title'] ); ?>
            </span>
        </a>
        <?php else : ?>
        <span></span>
        <?php endif; ?>

        <?php if ( $data['next_lesson'] ) : ?>
        <a href="<?php echo esc_url( $data['next_lesson']['url'] ); ?>" class="dd-lesson-nav-btn dd-lesson-nav-next">
            <span>
                <small>다음 강의<?php if ( ! empty( $data['next_lesson']['course'] ) ) echo ' &middot; ' . esc_html( $data['next_lesson']['course'] ); ?></small>
                <?php echo esc_html( $data['next_lesson']['title'] ); ?>
            </span>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
        <?php endif; ?>
    </div>
</nav>
<?php endif; ?>

<!-- ========== API 키 플로팅 버튼 (별도) ========== -->
<div class="dd-key-floating" id="dd-key-floating">
    <button class="dd-key-fab" id="dd-key-fab" title="Gemini API 키 설정">
        <svg class="dd-key-fab-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>
        </svg>
        <span class="dd-key-fab-badge" id="dd-key-fab-badge"></span>
    </button>
    <span class="dd-key-fab-label" id="dd-key-fab-label">API 키 설정</span>
</div>

<!-- AI 튜터는 叮叮에게 통합됨 — stub for writing features -->
<div id="dd-ai-floating" style="display:none;" data-lesson-context="<?php echo esc_attr( $data['title'] . ' - ' . mb_substr( strip_tags( $data['content'] ), 0, 200 ) ); ?>"></div>

<!-- API 키 모달 -->
<div class="dd-key-modal-overlay" id="dd-key-modal-overlay">
    <div class="dd-key-modal">
        <h3>Gemini API 키 설정</h3>
        <p class="dd-key-modal-desc">AI 튜터, 작문 채점 등 AI 기능을 사용하려면 본인의 Gemini API 키가 필요합니다.</p>
        <div class="dd-key-modal-steps">
            <div class="dd-key-step">
                <span class="dd-key-step-num">1</span>
                <span><a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">Google AI Studio</a> 접속 (무료)</span>
            </div>
            <div class="dd-key-step">
                <span class="dd-key-step-num">2</span>
                <span>"Create API Key" 클릭하여 키 발급</span>
            </div>
            <div class="dd-key-step">
                <span class="dd-key-step-num">3</span>
                <span>발급받은 키를 아래에 붙여넣기</span>
            </div>
        </div>
        <input type="password" id="dd-key-input" placeholder="AIza...">
        <p class="dd-key-modal-privacy">키는 이 브라우저의 localStorage에만 저장되며 서버로 전송되지 않습니다.</p>
        <div class="dd-key-modal-actions">
            <button class="dd-btn-delete" id="dd-key-delete" style="display:none;">삭제</button>
            <div style="flex:1;"></div>
            <button class="dd-btn-cancel" id="dd-key-cancel">취소</button>
            <button class="dd-btn-save" id="dd-key-save">저장</button>
        </div>
    </div>
</div>

<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-api-key-manager.js' ); ?>"></script>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-vocabulary.js' ); ?>"></script>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-shared.js' ); ?>"></script>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-progress.js' ); ?>"></script>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-adaptive-review.js' ); ?>"></script>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-lesson-viewer.js' ); ?>"></script>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-audiobook.js' ); ?>"></script>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-storybook.js' ); ?>"></script>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-ai-features.js' ); ?>"></script>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-pronunciation.js' ); ?>"></script>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-listening-drills.js' ); ?>"></script>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-stroke-practice.js' ); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 실전 대화 이미지 → 본문 "실전 대화" heading 바로 아래로 재배치
    (function() {
        var dialogueImg = document.getElementById('section-dialogue');
        if (!dialogueImg) return;
        var headings = document.querySelectorAll('#section-content .dd-section-heading');
        for (var i = 0; i < headings.length; i++) {
            if (headings[i].textContent.indexOf('실전 대화') !== -1) {
                headings[i].insertAdjacentElement('afterend', dialogueImg);
                return;
            }
        }
    })();

    // Initialize progress tracking
    if (window.DDProgress) {
        DDProgress.initLesson(<?php echo (int) $data['id']; ?>, <?php echo wp_json_encode( $data['title'] ); ?>);
    }

    // Initialize hanzi tab (한자 쓰기 + 고사성어)
    (function() {
        var hanziInited = false;
        var hanziPanel = document.getElementById('panel-hanzi');
        if (!hanziPanel) return;
        var obs = new MutationObserver(function() {
            if (hanziPanel.classList.contains('is-active') && !hanziInited) {
                hanziInited = true;
                if (window.DDStrokePractice) {
                    DDStrokePractice.renderTab(
                        document.getElementById('dd-hanzi-panel'),
                        <?php echo (int) $data['id']; ?>,
                        <?php echo wp_json_encode( $data['title'] ); ?>,
                        <?php echo wp_json_encode( $data['level'] ?? 'beginner' ); ?>,
                        <?php echo wp_json_encode( $data['key_expressions'] ?? array() ); ?>
                    );
                }
            }
        });
        obs.observe(hanziPanel, { attributes: true, attributeFilter: ['class'] });
    })();

    // Initialize SM-2 adaptive review: 핵심표현을 SRS에 자동 등록 + 복습 배너 렌더링
    if (window.DDSRS) {
        DDSRS.initFromLesson(
            <?php echo (int) $data['id']; ?>,
            <?php echo wp_json_encode( $data['title'] ); ?>,
            <?php echo wp_json_encode( $data['key_expressions'] ?? array() ); ?>
        );
        DDSRS.renderReviewBanner();
        DDSRS.renderWidgetAddon();
    }

    // Initialize save buttons on key expression cards
    document.querySelectorAll('.dd-ke-save').forEach(function(btn) {
        var zh = btn.dataset.zh;
        if (DDVocab.has(zh)) {
            btn.classList.add('is-saved');
            btn.querySelector('svg').setAttribute('fill', 'currentColor');
        }
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (DDVocab.has(zh)) {
                DDVocab.remove(zh);
                btn.classList.remove('is-saved');
                btn.querySelector('svg').setAttribute('fill', 'none');
            } else {
                DDVocab.add({
                    zh: btn.dataset.zh,
                    pinyin: btn.dataset.pinyin,
                    ko: btn.dataset.ko,
                    hsk: parseInt(btn.dataset.hsk) || 0,
                    examples: [],
                    source: { lesson_id: parseInt(btn.dataset.lessonId), lesson_title: btn.dataset.lessonTitle }
                });
                btn.classList.add('is-saved');
                btn.querySelector('svg').setAttribute('fill', 'currentColor');
            }
        });
    });

    // Key expression listen buttons → TTS
    document.querySelectorAll('.dd-ke-listen').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var zh = btn.dataset.zh;
            if (!zh || !window.speechSynthesis) return;
            window.speechSynthesis.cancel();
            var u = new SpeechSynthesisUtterance(zh);
            u.lang = 'zh-CN'; u.rate = 0.85; u.pitch = 1.0;
            btn.classList.add('is-playing');
            u.onend = function() { btn.classList.remove('is-playing'); };
            u.onerror = function() { btn.classList.remove('is-playing'); };
            speechSynthesis.speak(u);
        });
    });

    // Key expression card click → word popup
    document.querySelectorAll('.dd-key-expr-card').forEach(function(card) {
        card.style.cursor = 'pointer';
        card.addEventListener('click', function(e) {
            if (e.target.closest('.dd-ke-save') || e.target.closest('.dd-ke-listen')) return;
            var saveBtn = card.querySelector('.dd-ke-save');
            if (!saveBtn) return;
            var word = {
                zh: saveBtn.dataset.zh || '',
                pinyin: saveBtn.dataset.pinyin || '',
                ko: saveBtn.dataset.ko || '',
                hsk: parseInt(saveBtn.dataset.hsk) || 0,
                examples: [],
                source: { lesson_id: parseInt(saveBtn.dataset.lessonId), lesson_title: saveBtn.dataset.lessonTitle || '' }
            };
            if (typeof DDVocabUI !== 'undefined' && DDVocabUI.showWordPopup) {
                DDVocabUI.showWordPopup(word);
            }
        });
    });

    // 노래방 빈칸 모드 토글 — 학습자가 버튼 누를 때만 켜짐. 켜면 핵심어휘가 빈칸이 됨.
    document.querySelectorAll('.dd-karaoke-toggle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var viewer = btn.closest('.dd-song-lyrics-viewer');
            if (!viewer) return;
            var on = viewer.classList.toggle('dd-karaoke-blank');
            btn.classList.toggle('is-active', on);
            btn.textContent = on ? '🎤 정답 보기' : '🎤 빈칸 모드';
            // 모드를 끄면(또는 다시 켜면) 공개됐던 빈칸을 모두 리셋 → 다음에 켜면 전부 다시 빈칸
            viewer.querySelectorAll('.dd-lyric-kw.is-revealed').forEach(function(k) {
                k.classList.remove('is-revealed');
            });
        });
    });

    // 가사/자막 속 핵심어휘 탭 (줄 클릭 seek은 막음)
    //  - 빈칸 모드 + 아직 안 공개 → 정답 공개
    //  - 그 외 → 뜻 팝업 + 단어장 저장
    document.addEventListener('click', function(e) {
        var kw = e.target.closest && e.target.closest('.dd-lyric-kw');
        if (!kw) return;
        e.stopPropagation();
        e.preventDefault();

        var blanked = kw.closest('.dd-karaoke-blank');
        if (blanked && !kw.classList.contains('is-revealed')) {
            kw.classList.add('is-revealed'); // 빈칸 → 정답 공개
            return;
        }

        var word = {
            zh: kw.dataset.zh || '',
            pinyin: kw.dataset.pinyin || '',
            ko: kw.dataset.ko || '',
            hsk: parseInt(kw.dataset.hsk) || 0,
            examples: [],
            source: { lesson_id: parseInt(kw.dataset.lessonId) || 0, lesson_title: kw.dataset.lessonTitle || '' }
        };
        if (typeof DDVocabUI !== 'undefined' && DDVocabUI.showWordPopup) {
            DDVocabUI.showWordPopup(word);
        }
        if (typeof DDVocab !== 'undefined' && DDVocab.has(word.zh)) {
            kw.classList.add('is-saved');
        }
    }, true);

    // 저장 상태 초기 반영: 이미 단어장에 있는 키워드는 표시
    if (typeof DDVocab !== 'undefined') {
        document.querySelectorAll('.dd-lyric-kw').forEach(function(kw) {
            if (DDVocab.has(kw.dataset.zh)) { kw.classList.add('is-saved'); }
        });
    }

    // Initialize vocabulary panel when tab is activated
    var vocabPanel = document.getElementById('dd-vocab-panel');
    var vocabInited = false;
    var observer = new MutationObserver(function() {
        var panel = document.getElementById('panel-vocabulary');
        if (panel && panel.classList.contains('is-active') && !vocabInited) {
            vocabInited = true;
            DDVocabUI.renderPanel(vocabPanel, { lessonId: vocabPanel.dataset.lessonId });
        }
    });
    var panelVocab = document.getElementById('panel-vocabulary');
    if (panelVocab) observer.observe(panelVocab, { attributes: true, attributeFilter: ['class'] });

    // Also check on tab click
    document.querySelectorAll('.dd-tab').forEach(function(tab) {
        tab.addEventListener('click', function() {
            if (tab.dataset.tab === 'vocabulary' && !vocabInited) {
                vocabInited = true;
                setTimeout(function() {
                    DDVocabUI.renderPanel(vocabPanel, { lessonId: vocabPanel.dataset.lessonId });
                }, 50);
            } else if (tab.dataset.tab === 'vocabulary') {
                DDVocabUI.renderPanel(vocabPanel, { lessonId: vocabPanel.dataset.lessonId });
            }
        });
    });
});
</script>
<?php if ( ( $data['lesson_type'] ?? '' ) === 'song' ) : ?>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-song-lyricsync.js' ); ?>"></script>
<?php endif; ?>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-gamification.js' ); ?>"></script>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-assistant.js' ); ?>"></script>
</body>
</html>
