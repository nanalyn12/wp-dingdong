<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html( $data['title'] ); ?> - Dingdong</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;700&display=swap">
    <link rel="stylesheet" href="<?php echo esc_url( DD_LMS_URL . 'public/css/dd-story.css' ); ?>">
    <link rel="stylesheet" href="<?php echo esc_url( DD_LMS_URL . 'public/css/dd-assistant.css' ); ?>">
</head>
<body data-dd-page="story">

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

<div class="dd-story-wrapper">

    <!-- ========== 커버 화면 ========== -->
    <div class="dd-story-cover" id="dd-story-cover">
        <?php if ( ! empty( $data['cover_image'] ) ) : ?>
        <div class="dd-story-cover-img">
            <img src="<?php echo esc_url( $data['cover_image'] ); ?>" alt="<?php echo esc_attr( $data['title'] ); ?>">
            <div class="dd-story-cover-overlay"></div>
        </div>
        <?php endif; ?>
        <div class="dd-story-cover-content">
            <span class="dd-story-level-badge dd-level-<?php echo esc_attr( $data['level'] ); ?>">
                <?php
                $level_labels = array( 'beginner' => '입문', 'intermediate' => '중급', 'advanced' => '고급' );
                echo esc_html( $level_labels[ $data['level'] ] ?? $data['level'] );
                ?>
            </span>
            <h1 class="dd-story-title"><?php echo esc_html( $data['title'] ); ?></h1>
            <?php
            $nodes_data = is_string( $data['nodes'] ) ? json_decode( $data['nodes'], true ) : $data['nodes'];
            $title_zh = '';
            if ( ! empty( $nodes_data['title_zh'] ) ) {
                $title_zh = $nodes_data['title_zh'];
            }
            if ( $title_zh ) :
            ?>
            <p class="dd-story-title-zh"><?php echo esc_html( $title_zh ); ?></p>
            <?php endif; ?>
            <p class="dd-story-desc"><?php echo esc_html( $data['description'] ); ?></p>

            <div class="dd-story-meta">
                <?php
                $node_count = 0;
                $ending_count = 0;
                if ( ! empty( $nodes_data['nodes'] ) ) {
                    $node_count = count( $nodes_data['nodes'] );
                    foreach ( $nodes_data['nodes'] as $n ) {
                        if ( ! empty( $n['is_ending'] ) ) $ending_count++;
                    }
                }
                ?>
                <span class="dd-story-meta-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 12l2 2 4-4"/></svg>
                    <?php echo esc_html( $node_count ); ?>개 장면
                </span>
                <span class="dd-story-meta-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                    <?php echo esc_html( $ending_count ); ?>개 엔딩
                </span>
            </div>

            <button class="dd-story-start-btn" id="dd-story-start">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                이야기 시작하기
            </button>

            <!-- 엔딩 수집 현황 (재방문 시) -->
            <div class="dd-story-collection-preview" id="dd-story-collection-preview" style="display:none;"></div>
        </div>
    </div>

    <!-- ========== 스토리 플레이어 ========== -->
    <div class="dd-story-player" id="dd-story-player" style="display:none;">

        <!-- 상단 바: 진행도 + 도구 -->
        <div class="dd-story-toolbar">
            <div class="dd-story-breadcrumbs" id="dd-story-breadcrumbs"></div>
            <div class="dd-story-tools">
                <button class="dd-story-tool-btn" id="dd-story-tts-btn" title="중국어 읽기">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/></svg>
                </button>
                <button class="dd-story-tool-btn" id="dd-story-vocab-toggle" title="단어장">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                    <span class="dd-story-vocab-count" id="dd-story-vocab-count"></span>
                </button>
                <button class="dd-story-tool-btn" id="dd-story-map-btn" title="경로 맵">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>
                </button>
            </div>
        </div>

        <!-- 진행 바 -->
        <div class="dd-story-progress-bar">
            <div class="dd-story-progress-fill" id="dd-story-progress-fill"></div>
        </div>

        <!-- 노드 카드 -->
        <div class="dd-story-node" id="dd-story-node">
            <!-- 화자 표시 -->
            <div class="dd-story-speaker" id="dd-story-speaker" style="display:none;">
                <span class="dd-story-speaker-avatar" id="dd-story-speaker-avatar"></span>
                <span class="dd-story-speaker-name" id="dd-story-speaker-name"></span>
            </div>

            <!-- 장면 이미지 -->
            <div class="dd-story-node-img" id="dd-story-node-img" style="display:none;">
                <img id="dd-story-node-img-el" src="" alt="" loading="lazy">
            </div>

            <!-- 텍스트 -->
            <div class="dd-story-node-text">
                <p class="dd-story-zh" id="dd-story-zh"></p>
                <p class="dd-story-pinyin" id="dd-story-pinyin"></p>
                <p class="dd-story-ko" id="dd-story-ko"></p>

                <!-- 문법 팁 -->
                <div class="dd-story-grammar-tip" id="dd-story-grammar-tip" style="display:none;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                    <span id="dd-story-grammar-text"></span>
                </div>
            </div>

            <!-- 노드별 어휘 패널 -->
            <div class="dd-story-node-vocab" id="dd-story-node-vocab" style="display:none;">
                <div class="dd-story-node-vocab-header">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/></svg>
                    이 장면의 핵심 어휘
                </div>
                <div class="dd-story-node-vocab-list" id="dd-story-node-vocab-list"></div>
            </div>

            <!-- 선택지 -->
            <div class="dd-story-choices" id="dd-story-choices"></div>
        </div>

        <!-- 하단 네비 -->
        <div class="dd-story-nav-bar">
            <button class="dd-story-back-btn" id="dd-story-back" style="display:none;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                이전
            </button>
            <span class="dd-story-step-counter" id="dd-story-step-counter"></span>
        </div>
    </div>

    <!-- ========== 엔딩 화면 ========== -->
    <div class="dd-story-ending" id="dd-story-ending" style="display:none;">
        <div class="dd-story-ending-card" id="dd-story-ending-card">
            <div class="dd-story-ending-icon" id="dd-story-ending-icon"></div>
            <h2 class="dd-story-ending-title" id="dd-story-ending-title"></h2>
            <div class="dd-story-ending-text">
                <p class="dd-story-zh" id="dd-story-ending-zh"></p>
                <p class="dd-story-pinyin" id="dd-story-ending-pinyin"></p>
                <p class="dd-story-ko" id="dd-story-ending-ko"></p>
            </div>

            <!-- 학습 요약 -->
            <div class="dd-story-ending-stats" id="dd-story-ending-stats">
                <h3>학습 요약</h3>
                <div class="dd-story-stat-grid" id="dd-story-stat-grid"></div>
            </div>

            <!-- 배운 어휘 -->
            <div class="dd-story-ending-vocab" id="dd-story-ending-vocab" style="display:none;">
                <h3>이번에 만난 어휘</h3>
                <div class="dd-story-ending-vocab-list" id="dd-story-ending-vocab-list"></div>
            </div>

            <!-- 엔딩 수집 -->
            <div class="dd-story-ending-collection" id="dd-story-ending-collection"></div>

            <div class="dd-story-ending-actions">
                <button class="dd-story-restart-btn" id="dd-story-restart">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
                    다른 경로 탐험하기
                </button>
                <button class="dd-story-home-btn" id="dd-story-home">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                    처음으로
                </button>
            </div>
        </div>
    </div>

    <!-- ========== 단어 팝업 ========== -->
    <div class="dd-word-popup" id="dd-word-popup" style="display:none;">
        <div class="dd-word-popup-zh" id="dd-word-popup-zh"></div>
        <div class="dd-word-popup-pinyin" id="dd-word-popup-pinyin"></div>
        <div class="dd-word-popup-ko" id="dd-word-popup-ko"></div>
        <div class="dd-word-popup-actions">
            <button class="dd-word-popup-tts" id="dd-word-popup-tts" title="발음 듣기">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>
            </button>
            <button class="dd-word-popup-save" id="dd-word-popup-save" title="단어장 저장">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
            </button>
        </div>
    </div>

</div>

<script>
var ddStoryData = <?php
    $story_js = $data['nodes'];
    $story_js['title'] = $data['title'];
    $story_js['level'] = $data['level'];
    $story_js['story_id'] = $data['id'];
    echo wp_json_encode( $story_js, JSON_UNESCAPED_UNICODE );
?>;
</script>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-shared.js' ); ?>"></script>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-story-player.js' ); ?>"></script>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-gamification.js' ); ?>"></script>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-assistant.js' ); ?>"></script>
</body>
</html>
