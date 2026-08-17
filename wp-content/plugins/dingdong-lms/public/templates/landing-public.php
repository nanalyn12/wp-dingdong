<?php
$courses = get_posts( array(
    'post_type'   => 'dd_course',
    'numberposts' => 50,
    'post_status' => 'publish',
    'orderby'     => 'date',
    'order'       => 'DESC',
) );

$total_courses = count( $courses );
$total_lessons = 0;
$recent_courses = array();

foreach ( $courses as $c ) {
    $lesson_count = (int) get_post_meta( $c->ID, '_dd_course_total_lessons', true );
    $total_lessons += $lesson_count;
    if ( count( $recent_courses ) < 3 ) {
        // 첫 번째 공개 강의 URL 찾기
        $first_url = '';
        $course_type = get_post_meta( $c->ID, '_dd_course_type', true );
        $first_lesson = get_posts( array(
            'post_type' => 'dd_lesson', 'numberposts' => 1, 'post_status' => 'publish',
            'orderby' => 'meta_value_num', 'order' => 'ASC', 'meta_key' => '_dd_lesson_order',
            'meta_query' => array(
                array( 'key' => '_dd_course_id', 'value' => $c->ID ),
                array( 'key' => '_dd_public_active', 'value' => '1' ),
            ),
        ) );
        if ( ! empty( $first_lesson ) ) {
            $first_url = home_url( '/lesson/' . get_post_meta( $first_lesson[0]->ID, '_dd_public_token', true ) . '/' );
        }
        $recent_courses[] = array(
            'title'       => $c->post_title,
            'description' => wp_trim_words( $c->post_content, 20, '...' ),
            'level'       => get_post_meta( $c->ID, '_dd_course_level', true ),
            'lessons'     => $lesson_count,
            'thumbnail'   => get_post_meta( $c->ID, '_dd_course_thumbnail', true ),
            'url'         => $first_url,
            'type'        => $course_type ?: 'ai',
        );
    }
}

$newsletters_count = wp_count_posts( 'dd_newsletter' );
$total_newsletters = isset( $newsletters_count->publish ) ? $newsletters_count->publish : 0;

$stories_count = wp_count_posts( 'dd_story' );
$total_stories = isset( $stories_count->publish ) ? $stories_count->publish : 0;

// 스토리 목록 (최근 3개)
$recent_stories = array();
$stories = get_posts( array( 'post_type' => 'dd_story', 'numberposts' => 3, 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC' ) );
foreach ( $stories as $s ) {
    if ( get_post_meta( $s->ID, '_dd_story_public_active', true ) === '1' ) {
        $recent_stories[] = array(
            'title' => $s->post_title,
            'level' => get_post_meta( $s->ID, '_dd_story_level', true ) ?: 'beginner',
            'cover' => get_post_meta( $s->ID, '_dd_story_cover_image', true ),
            'url'   => home_url( '/story/' . get_post_meta( $s->ID, '_dd_story_public_token', true ) . '/' ),
        );
    }
}

$level_labels = array(
    'beginner'     => '입문',
    'intermediate' => '중급',
    'advanced'     => '고급',
);

$base_url = DD_LMS_URL;
$home     = home_url( '/' );
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DingDong 叮咚 — AI 중국어 학습 플랫폼</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;700&display=swap">
    <link rel="stylesheet" href="<?php echo esc_url( $base_url . 'public/css/dd-lesson.css' ); ?>">
    <style>
        body {
            font-family: 'Pretendard', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--dd-bg);
            color: var(--dd-text);
        }

        /* ── Hero ── */
        .dd-hero {
            text-align: center;
            padding: 4rem 1.5rem 3.5rem;
            background: linear-gradient(160deg, #FFF5F5 0%, #FFFFFF 40%, #FFF0F3 100%);
            position: relative;
            overflow: hidden;
        }

        /* ── Hero 叮叮 ── */
        .dd-hero-panda-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0;
            margin-bottom: 1.5rem;
            position: relative;
        }
        .dd-hero-panda {
            width: 160px;
            height: 200px;
            flex-shrink: 0;
            animation: ddHeroPandaBounce 3s ease-in-out infinite;
            filter: drop-shadow(0 8px 20px rgba(0,0,0,0.08));
        }
        @keyframes ddHeroPandaBounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        .dd-hero-panda svg { width: 100%; height: 100%; }
        .dd-hero-speech {
            position: relative;
            background: #fff;
            border: 1.5px solid #F0E0E4;
            border-radius: 20px 20px 20px 4px;
            padding: 1rem 1.4rem;
            max-width: 260px;
            box-shadow: 0 4px 20px rgba(219,127,142,0.1);
            text-align: left;
            animation: ddHeroSpeechIn 0.6s 0.5s cubic-bezier(0.34,1.56,0.64,1) both;
            margin-left: -10px;
        }
        @keyframes ddHeroSpeechIn {
            from { transform: scale(0.7) translateX(-10px); opacity: 0; }
            to { transform: scale(1) translateX(0); opacity: 1; }
        }
        .dd-hero-speech::before {
            content: '';
            position: absolute;
            bottom: 18px;
            left: -9px;
            width: 18px;
            height: 18px;
            background: #fff;
            border-left: 1.5px solid #F0E0E4;
            border-bottom: 1.5px solid #F0E0E4;
            transform: rotate(45deg);
        }
        .dd-hero-speech-name {
            font-weight: 700;
            color: var(--dd-primary);
            font-size: 0.8rem;
            margin-bottom: 0.3rem;
        }
        .dd-hero-speech-text {
            font-size: 0.95rem;
            line-height: 1.6;
            color: #3D3235;
        }
        .dd-hero-speech-zh {
            display: block;
            font-size: 1.05rem;
            font-family: 'Noto Sans SC', sans-serif;
            color: #1a1a2e;
            margin-top: 0.3rem;
            font-weight: 500;
        }

        @media (max-width: 600px) {
            .dd-hero-panda-wrap { flex-direction: column; gap: 0; }
            .dd-hero-panda { width: 120px; height: 150px; }
            .dd-hero-speech {
                margin-left: 0;
                margin-top: -10px;
                border-radius: 20px;
                text-align: center;
            }
            .dd-hero-speech::before { display: none; }
        }
        .dd-hero-badge {
            display: inline-block;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--dd-primary);
            background: var(--dd-soft);
            padding: 0.3rem 1rem;
            border-radius: 20px;
            margin-bottom: 1.5rem;
        }
        .dd-hero h1 {
            font-size: 3rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.2;
            margin-bottom: 1rem;
        }
        .dd-hero h1 span { color: var(--dd-primary); }
        .dd-hero p {
            font-size: 1.1rem;
            color: var(--dd-text-light);
            max-width: 560px;
            margin: 0 auto 2rem;
            line-height: 1.7;
        }
        .dd-hero-actions { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }
        .dd-btn-primary {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.75rem 2rem;
            background: var(--dd-primary);
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 10px;
            text-decoration: none;
            transition: background 0.2s, transform 0.15s;
        }
        .dd-btn-primary:hover { background: var(--dd-primary-hover); transform: translateY(-1px); }
        .dd-btn-outline {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.75rem 2rem;
            background: #fff;
            color: var(--dd-text);
            font-size: 1rem;
            font-weight: 500;
            border: 1px solid var(--dd-border);
            border-radius: 10px;
            text-decoration: none;
            transition: border-color 0.2s;
        }
        .dd-btn-outline:hover { border-color: var(--dd-primary); color: var(--dd-primary); }

        /* ── Stats ── */
        .dd-stats {
            display: flex;
            justify-content: center;
            gap: 3rem;
            padding: 2.5rem 1.5rem;
            background: #fff;
            border-top: 1px solid var(--dd-border);
            border-bottom: 1px solid var(--dd-border);
        }
        .dd-stat { text-align: center; }
        .dd-stat-num {
            font-size: 2rem;
            font-weight: 800;
            color: var(--dd-primary);
        }
        .dd-stat-label {
            font-size: 0.82rem;
            color: var(--dd-text-light);
            margin-top: 0.2rem;
        }

        /* ── Features ── */
        .dd-features {
            max-width: 1080px;
            margin: 0 auto;
            padding: 4rem 1.5rem;
        }
        .dd-features-title {
            text-align: center;
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .dd-features-sub {
            text-align: center;
            font-size: 0.95rem;
            color: var(--dd-text-light);
            margin-bottom: 2.5rem;
        }
        .dd-features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
        }
        .dd-feature-card {
            background: #fff;
            border: 1px solid var(--dd-border);
            border-radius: var(--dd-radius);
            padding: 2rem 1.5rem;
            transition: box-shadow 0.25s, transform 0.2s;
        }
        .dd-feature-card:hover {
            box-shadow: 0 8px 24px rgba(219,127,142,0.12);
            transform: translateY(-3px);
        }
        .dd-feature-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 1rem;
        }
        .dd-feature-icon--ai       { background: #FFF0F3; color: var(--dd-primary); }
        .dd-feature-icon--slides   { background: #EEF6FF; color: #3B82F6; }
        .dd-feature-icon--vocab    { background: #F0FFF4; color: #22C55E; }
        .dd-feature-icon--news     { background: #F5F0FF; color: #8B5CF6; }
        .dd-feature-icon--writing  { background: #FFF5EB; color: #F97316; }
        .dd-feature-card h3 {
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .dd-feature-card p {
            font-size: 0.88rem;
            color: var(--dd-text-light);
            line-height: 1.6;
        }

        /* ── Recent Courses ── */
        .dd-recent {
            max-width: 1080px;
            margin: 0 auto;
            padding: 0 1.5rem 4rem;
        }
        .dd-recent-title {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .dd-recent-sub {
            font-size: 0.95rem;
            color: var(--dd-text-light);
            margin-bottom: 2rem;
        }
        .dd-recent-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
        }
        .dd-recent-card {
            background: #fff;
            border: 1px solid var(--dd-border);
            border-radius: var(--dd-radius);
            overflow: hidden;
            transition: box-shadow 0.25s;
        }
        .dd-recent-card:hover { box-shadow: var(--dd-shadow); }
        .dd-recent-thumb {
            width: 100%;
            height: 160px;
            object-fit: cover;
            background: var(--dd-soft);
        }
        .dd-recent-thumb-placeholder {
            width: 100%;
            height: 160px;
            background: linear-gradient(135deg, var(--dd-soft) 0%, var(--dd-mid) 100%);
            display: flex; align-items: center; justify-content: center;
            font-size: 2.5rem; color: var(--dd-primary); opacity: 0.5;
        }
        .dd-recent-thumb--song  { background: linear-gradient(135deg, #F3E8FF 0%, #C084FC 100%); color: #6B21A8; opacity: 0.7; }
        .dd-recent-body { padding: 1.25rem; }
        .dd-recent-body h3 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 0.4rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .dd-recent-body p {
            font-size: 0.82rem;
            color: var(--dd-text-light);
            line-height: 1.5;
            margin-bottom: 0.75rem;
        }
        .dd-recent-meta { display: flex; gap: 0.5rem; }
        .dd-recent-badge {
            font-size: 0.72rem;
            font-weight: 600;
            padding: 0.2rem 0.55rem;
            border-radius: 20px;
            background: var(--dd-soft);
            color: var(--dd-primary);
        }

        /* ── CTA Banner ── */
        .dd-cta-banner {
            text-align: center;
            padding: 4rem 1.5rem;
            background: linear-gradient(135deg, #FFF0F3 0%, #FFE4E8 100%);
        }
        .dd-cta-banner h2 {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
        }
        .dd-cta-banner p {
            font-size: 0.95rem;
            color: var(--dd-text-light);
            margin-bottom: 1.5rem;
        }

        /* ── Footer ── */
        .dd-footer {
            text-align: center;
            padding: 2rem 1.5rem;
            font-size: 0.8rem;
            color: var(--dd-text-light);
            border-top: 1px solid var(--dd-border);
            background: #fff;
        }
        .dd-footer a { color: var(--dd-primary); text-decoration: none; }
        .dd-footer a:hover { text-decoration: underline; }

        /* ── Badge Types ── */
        .dd-badge-ai { background: #FFF0F3; color: var(--dd-primary); }
        .dd-badge-song { background: #F3E8FF; color: #7E22CE; }
        .dd-badge-story { background: #EDE9FE; color: #7C3AED; }

        /* ── Feature Guide Popup ── */
        .dd-guide-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            animation: ddGuideIn 0.3s ease;
        }
        @keyframes ddGuideIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .dd-guide-card {
            background: #fff;
            border-radius: 20px;
            max-width: 440px;
            width: 100%;
            padding: 2.5rem 2rem 2rem;
            text-align: center;
            box-shadow: 0 16px 48px rgba(0,0,0,0.18);
            position: relative;
            animation: ddGuideCardIn 0.35s ease;
        }
        @keyframes ddGuideCardIn {
            from { transform: scale(0.9) translateY(20px); opacity: 0; }
            to { transform: scale(1) translateY(0); opacity: 1; }
        }
        .dd-guide-close {
            position: absolute;
            top: 12px;
            right: 16px;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #aaa;
            cursor: pointer;
            line-height: 1;
        }
        .dd-guide-close:hover { color: #333; }
        .dd-guide-icon {
            font-size: 3rem;
            margin-bottom: 0.75rem;
        }
        .dd-guide-card h3 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: var(--dd-text);
        }
        .dd-guide-card p {
            font-size: 1rem;
            color: var(--dd-text-light);
            line-height: 1.8;
            margin-bottom: 1.25rem;
        }
        .dd-guide-steps {
            text-align: left;
            margin: 0 auto 1.5rem;
            max-width: 340px;
        }
        .dd-guide-step {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
            font-size: 0.92rem;
            line-height: 1.5;
        }
        .dd-guide-step-num {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--dd-primary);
            color: #fff;
            font-size: 0.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .dd-guide-nav {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.75rem;
        }
        .dd-guide-dots {
            display: flex;
            gap: 0.4rem;
        }
        .dd-guide-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--dd-border);
            transition: background 0.2s;
        }
        .dd-guide-dot.is-active {
            background: var(--dd-primary);
            width: 20px;
            border-radius: 4px;
        }
        .dd-guide-btn {
            padding: 0.6rem 1.5rem;
            background: var(--dd-primary);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 0.92rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .dd-guide-btn:hover { background: var(--dd-primary-hover); }
        .dd-guide-btn--outline {
            background: none;
            color: var(--dd-text-light);
            border: 1px solid var(--dd-border);
        }
        .dd-guide-btn--outline:hover { border-color: var(--dd-primary); color: var(--dd-primary); background: none; }

        /* ── Help FAB ── */
        .dd-help-fab {
            position: fixed;
            bottom: 36px;
            right: 150px;
            z-index: 1000;
            width: 44px;
            height: 44px;
            background: var(--dd-primary);
            color: #fff;
            border: none;
            border-radius: 50%;
            font-size: 1.4rem;
            cursor: pointer;
            box-shadow: 0 4px 16px rgba(219,127,142,0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .dd-help-fab:hover {
            transform: scale(1.08);
            box-shadow: 0 6px 20px rgba(219,127,142,0.45);
        }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .dd-hero h1 { font-size: 2rem; }
            .dd-hero p { font-size: 0.95rem; }
            .dd-features-grid,
            .dd-recent-grid { grid-template-columns: 1fr; }
            .dd-stats { gap: 1.5rem; flex-wrap: wrap; }
            .dd-landing-nav { gap: 0.8rem; }
            .dd-landing-nav a { font-size: 0.8rem; }
        }
        @media (min-width: 769px) and (max-width: 1024px) {
            .dd-features-grid,
            .dd-recent-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
    <link rel="stylesheet" href="<?php echo esc_url( $base_url . 'public/css/dd-assistant.css' ); ?>">
</head>
<body data-dd-page="landing">

<!-- Topbar -->
<nav class="dd-topbar">
    <div class="dd-topbar-inner">
        <a href="<?php echo esc_url( $home ); ?>" class="dd-topbar-brand">
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

<!-- Hero Section -->
<section class="dd-hero">
    <!-- 叮叮 판다 + 말풍선 -->
    <div class="dd-hero-panda-wrap">
        <div class="dd-hero-panda" id="dd-hero-panda"></div>
        <div class="dd-hero-speech">
            <div class="dd-hero-speech-name">叮叮 🐼</div>
            <div class="dd-hero-speech-text">
                안녕! 나는 叮叮이야~<br>
                같이 중국어 공부하자!
                <span class="dd-hero-speech-zh">我们一起学中文吧！</span>
            </div>
        </div>
    </div>

    <span class="dd-hero-badge">AI-Powered Chinese Learning</span>
    <h1>AI로 배우는<br><span>중국어 &amp; 중국문화</span></h1>
    <p>
        Gemini AI가 생성하는 맞춤형 강좌, 인터랙티브 퀴즈, 오디오북,
        스토리북으로 중국어를 재미있게 마스터하세요.
    </p>
    <div class="dd-hero-actions">
        <a href="<?php echo esc_url( home_url( '/courses/' ) ); ?>" class="dd-btn-primary">강좌 보기 &rarr;</a>
        <a href="<?php echo esc_url( home_url( '/vocabulary/' ) ); ?>" class="dd-btn-outline">단어장 열기</a>
    </div>
</section>

<!-- Stats -->
<?php if ( $total_courses > 0 ) : ?>
<section class="dd-stats">
    <div class="dd-stat">
        <div class="dd-stat-num"><?php echo esc_html( $total_courses ); ?></div>
        <div class="dd-stat-label">개설 강좌</div>
    </div>
    <div class="dd-stat">
        <div class="dd-stat-num"><?php echo esc_html( $total_lessons ); ?></div>
        <div class="dd-stat-label">강의</div>
    </div>
    <div class="dd-stat">
        <div class="dd-stat-num"><?php echo esc_html( $total_newsletters ); ?></div>
        <div class="dd-stat-label">뉴스레터</div>
    </div>
    <div class="dd-stat">
        <div class="dd-stat-num">5</div>
        <div class="dd-stat-label">학습 탭</div>
    </div>
</section>
<?php endif; ?>

<!-- Features -->
<section class="dd-features">
    <h2 class="dd-features-title">학습 기능</h2>
    <p class="dd-features-sub">AI 기반 중국어 교육에 필요한 모든 것</p>

    <div class="dd-features-grid">
        <div class="dd-feature-card">
            <div class="dd-feature-icon dd-feature-icon--ai">&#x2728;</div>
            <h3>AI 강좌 생성</h3>
            <p>주제와 난이도만 선택하면 Gemini AI가 강좌, 슬라이드, 퀴즈, 스토리북까지 자동 생성합니다.</p>
        </div>
        <div class="dd-feature-card">
            <div class="dd-feature-icon dd-feature-icon--slides">&#x1F4CA;</div>
            <h3>인터랙티브 학습</h3>
            <p>5장 슬라이드, 3종 퀴즈, 오디오북, 6페이지 스토리북으로 몰입형 학습 경험을 제공합니다.</p>
        </div>
        <div class="dd-feature-card">
            <div class="dd-feature-icon dd-feature-icon--vocab">&#x1F4D6;</div>
            <h3>AI 단어장</h3>
            <p>플래시카드, 4종 미니게임, HSK 급수 표시로 어휘를 체계적으로 암기합니다.</p>
        </div>
        <div class="dd-feature-card">
            <div class="dd-feature-icon dd-feature-icon--news">&#x1F4F0;</div>
            <h3>뉴스레터</h3>
            <p>중국 대중문화 트렌드를 중국어로 읽으며 독해력과 문화 감각을 키웁니다.</p>
        </div>
        <div class="dd-feature-card">
            <div class="dd-feature-icon dd-feature-icon--writing">&#x270D;</div>
            <h3>작문 채점</h3>
            <p>AI가 중국어 작문의 문법, 어휘, 자연스러움을 실시간으로 채점하고 피드백합니다.</p>
        </div>
    </div>
</section>

<!-- Recent Courses -->
<?php if ( ! empty( $recent_courses ) ) : ?>
<section class="dd-recent">
    <h2 class="dd-recent-title">최근 강좌</h2>
    <p class="dd-recent-sub">최근 개설된 강좌를 확인하세요</p>
    <div class="dd-recent-grid">
        <?php foreach ( $recent_courses as $rc ) :
            $card_url = ! empty( $rc['url'] ) ? $rc['url'] : home_url( '/courses/' );
            $rc_type   = $rc['type'] ?? 'ai';
            $is_song   = ( $rc_type === 'song' );
            $type_icon = $is_song ? '&#x1F3B5;' : '&#x53E1;';
        ?>
        <a href="<?php echo esc_url( $card_url ); ?>" class="dd-recent-card" style="text-decoration:none;color:inherit;display:block;">
            <?php if ( ! empty( $rc['thumbnail'] ) ) : ?>
                <img class="dd-recent-thumb" src="<?php echo esc_url( $rc['thumbnail'] ); ?>" alt="">
            <?php else : ?>
                <div class="dd-recent-thumb-placeholder dd-recent-thumb--<?php echo esc_attr( $rc_type ); ?>"><?php echo $type_icon; ?></div>
            <?php endif; ?>
            <div class="dd-recent-body">
                <h3><?php echo esc_html( $rc['title'] ); ?></h3>
                <p><?php echo esc_html( $rc['description'] ); ?></p>
                <div class="dd-recent-meta">
                    <?php if ( $is_song ) : ?>
                        <span class="dd-recent-badge dd-badge-song">&#x1F3B5; 노래</span>
                    <?php else : ?>
                        <span class="dd-recent-badge dd-badge-ai">&#x2728; AI 강좌</span>
                    <?php endif; ?>
                    <?php if ( ! empty( $rc['level'] ) && isset( $level_labels[ $rc['level'] ] ) ) : ?>
                        <span class="dd-recent-badge"><?php echo esc_html( $level_labels[ $rc['level'] ] ); ?></span>
                    <?php endif; ?>
                    <span class="dd-recent-badge"><?php echo esc_html( $rc['lessons'] ); ?>개 강의</span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <div style="text-align:center; margin-top:2rem;">
        <a href="<?php echo esc_url( home_url( '/courses/' ) ); ?>" class="dd-btn-outline">전체 강좌 보기 &rarr;</a>
    </div>
</section>
<?php endif; ?>

<!-- Interactive Stories -->
<?php if ( ! empty( $recent_stories ) ) : ?>
<section class="dd-recent" id="dd-stories-section">
    <h2 class="dd-recent-title">&#x1F4D6; 인터랙티브 스토리</h2>
    <p class="dd-recent-sub">선택에 따라 이야기가 달라지는 게임북 형식의 중국어 학습</p>
    <a href="<?php echo esc_url( home_url( '/stories/' ) ); ?>" style="display:inline-block;font-size:0.88rem;color:var(--dd-primary);text-decoration:none;font-weight:600;margin-bottom:1rem;">전체 스토리 보기 &rarr;</a>
    <div class="dd-recent-grid">
        <?php foreach ( $recent_stories as $rs ) : ?>
        <a href="<?php echo esc_url( $rs['url'] ); ?>" class="dd-recent-card" style="text-decoration:none;color:inherit;display:block;">
            <?php if ( ! empty( $rs['cover'] ) ) : ?>
                <img class="dd-recent-thumb" src="<?php echo esc_url( $rs['cover'] ); ?>" alt="">
            <?php else : ?>
                <div class="dd-recent-thumb-placeholder">&#x1F4D6;</div>
            <?php endif; ?>
            <div class="dd-recent-body">
                <h3><?php echo esc_html( $rs['title'] ); ?></h3>
                <div class="dd-recent-meta">
                    <span class="dd-recent-badge dd-badge-story">&#x1F3AE; 인터랙티브</span>
                    <?php if ( isset( $level_labels[ $rs['level'] ] ) ) : ?>
                        <span class="dd-recent-badge"><?php echo esc_html( $level_labels[ $rs['level'] ] ); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- CTA Banner -->
<section class="dd-cta-banner">
    <h2>지금 바로 중국어 학습을 시작하세요</h2>
    <p>로그인 없이 공유 링크만으로 누구나 강의를 열람할 수 있습니다.</p>
    <a href="<?php echo esc_url( home_url( '/courses/' ) ); ?>" class="dd-btn-primary">강좌 둘러보기 &rarr;</a>
</section>

<!-- Footer -->
<footer class="dd-footer">
    <p>DingDong 叮咚 &mdash; AI 기반 중국어 &amp; 중국문화 교육 플랫폼</p>
    <p style="margin-top:0.4rem;">
        Powered by <a href="https://wordpress.org" target="_blank" rel="noopener">WordPress</a>
        &amp; <a href="https://ai.google.dev" target="_blank" rel="noopener">Gemini AI</a>
    </p>
</footer>

<!-- Help FAB -->
<button class="dd-help-fab" id="dd-help-fab" title="사용법 안내">?</button>

<!-- Feature Guide Popup -->
<div class="dd-guide-overlay" id="dd-guide-overlay" style="display:none;">
    <div class="dd-guide-card">
        <button class="dd-guide-close" id="dd-guide-close">&times;</button>
        <div id="dd-guide-content"></div>
        <div class="dd-guide-nav">
            <button class="dd-guide-btn dd-guide-btn--outline" id="dd-guide-prev">이전</button>
            <div class="dd-guide-dots" id="dd-guide-dots"></div>
            <button class="dd-guide-btn" id="dd-guide-next">다음</button>
        </div>
    </div>
</div>

<script>
(function() {
    var guides = [
        {
            icon: '&#x1F44B;',
            title: 'DingDong 叮咚에 오신 것을 환영합니다!',
            body: 'AI가 만들어주는 중국어 강좌로<br>누구나 쉽게 중국어를 배울 수 있어요.',
            steps: [
                '아래 <b>강좌</b>를 클릭하면 바로 학습 시작!',
                '<b>로그인이 필요 없어요</b> — 링크만 클릭하세요',
                '모든 기능을 <b>무료</b>로 이용할 수 있습니다'
            ]
        },
        {
            icon: '&#x1F4DA;',
            title: '강좌 학습하기',
            body: '강좌를 클릭하면 5가지 탭으로<br>다양하게 중국어를 배울 수 있어요.',
            steps: [
                '<b>학습 내용</b> — 본문, 핵심 표현, 실전 대화',
                '<b>슬라이드</b> — 한눈에 보는 핵심 정리',
                '<b>오디오북</b> — 중국어 발음을 들으며 학습',
                '<b>퀴즈</b> — 배운 내용 테스트 (3종류 문제)'
            ]
        },
        {
            icon: '&#x1F3AE;',
            title: '인터랙티브 스토리',
            body: '선택에 따라 이야기가 달라져요!<br>게임하듯 중국어를 배울 수 있어요.',
            steps: [
                '스토리를 읽고 <b>선택지를 클릭</b>하세요',
                '<b>밑줄 친 단어</b>를 클릭하면 뜻을 볼 수 있어요',
                '<b>스피커 버튼</b>으로 중국어 발음을 들어보세요',
                '여러 번 플레이하면 <b>다른 엔딩</b>을 발견해요!'
            ]
        },
        {
            icon: '&#x1F4DD;',
            title: 'AI 튜터 & 작문 채점',
            body: 'AI 친구 "叮叮"과 중국어로 대화하고,<br>작문을 채점받을 수 있어요.',
            steps: [
                '강의 페이지 왼쪽 아래 <b>말풍선 버튼</b> 클릭',
                '<b>튜터 모드</b>: 질문하면 叮叮이 답변해요',
                '<b>역할극 모드</b>: 식당/택시/쇼핑/호텔 실전 연습',
                '먼저 <b>API 키 설정</b> (오른쪽 아래 열쇠 버튼)'
            ]
        },
        {
            icon: '&#x1F4D6;',
            title: 'AI 단어장',
            body: '학습 중 만난 단어를 저장하고<br>게임으로 복습할 수 있어요.',
            steps: [
                '핵심 표현 카드의 <b>북마크 아이콘</b>을 클릭해 저장',
                '<b>플래시카드</b>로 앞뒤 뒤집기 암기',
                '<b>4종 미니게임</b>으로 재미있게 복습',
                '상단 메뉴 <b>단어장</b>에서 전체 관리'
            ]
        },
        {
            icon: '&#x1F3AC;',
            title: '드라마 학습',
            body: '좋아하는 중국 드라마로 배우는<br>진짜 중국어! (관리자 기능)',
            steps: [
                '관리자가 YouTube 드라마 URL을 입력',
                'AI가 자막을 분석해 <b>강의를 자동 생성</b>',
                '드라마 속 실제 대화로 <b>생활 중국어</b> 학습',
                '강좌 목록에서 <b>🎬 드라마</b> 뱃지를 찾아보세요'
            ]
        }
    ];

    var currentIdx = 0;
    var overlay = document.getElementById('dd-guide-overlay');
    var content = document.getElementById('dd-guide-content');
    var dots = document.getElementById('dd-guide-dots');
    var prevBtn = document.getElementById('dd-guide-prev');
    var nextBtn = document.getElementById('dd-guide-next');
    var fab = document.getElementById('dd-help-fab');
    var closeBtn = document.getElementById('dd-guide-close');

    function renderGuide(idx) {
        var g = guides[idx];
        var html = '<div class="dd-guide-icon">' + g.icon + '</div>';
        html += '<h3>' + g.title + '</h3>';
        html += '<p>' + g.body + '</p>';
        html += '<div class="dd-guide-steps">';
        g.steps.forEach(function(s, i) {
            html += '<div class="dd-guide-step"><span class="dd-guide-step-num">' + (i+1) + '</span><span>' + s + '</span></div>';
        });
        html += '</div>';
        content.innerHTML = html;

        dots.innerHTML = '';
        guides.forEach(function(_, i) {
            var dot = document.createElement('span');
            dot.className = 'dd-guide-dot' + (i === idx ? ' is-active' : '');
            dot.addEventListener('click', function() { currentIdx = i; renderGuide(i); });
            dots.appendChild(dot);
        });

        prevBtn.style.visibility = idx === 0 ? 'hidden' : '';
        nextBtn.textContent = idx === guides.length - 1 ? '완료!' : '다음';
    }

    function show() { overlay.style.display = 'flex'; currentIdx = 0; renderGuide(0); }
    function hide() { overlay.style.display = 'none'; }

    fab.addEventListener('click', show);
    closeBtn.addEventListener('click', hide);
    overlay.addEventListener('click', function(e) { if (e.target === overlay) hide(); });
    prevBtn.addEventListener('click', function() { if (currentIdx > 0) { currentIdx--; renderGuide(currentIdx); } });
    nextBtn.addEventListener('click', function() { if (currentIdx < guides.length - 1) { currentIdx++; renderGuide(currentIdx); } else { hide(); } });

    // 첫 방문 시 자동 표시
    if (!localStorage.getItem('dd_guide_seen')) {
        setTimeout(show, 1500);
        localStorage.setItem('dd_guide_seen', '1');
    }
})();
</script>
<script src="<?php echo esc_url( $base_url . 'public/js/dd-gamification.js' ); ?>"></script>
<script src="<?php echo esc_url( $base_url . 'public/js/dd-shared.js' ); ?>"></script>
<script src="<?php echo esc_url( $base_url . 'public/js/dd-assistant.js' ); ?>"></script>
<script>
/* 히어로 叮叮이 — dd-assistant.js의 buildPandaBody SVG를 삽입 */
(function() {
    var heroEl = document.getElementById('dd-hero-panda');
    if (!heroEl) return;
    /* dd-assistant.js에서 PANDA_BODY는 IIFE 내부라 접근 불가 →
       독립 인라인 SVG 직접 생성 (히어로 전용 큰 판다) */
    heroEl.innerHTML =
    '<svg viewBox="0 0 120 150" xmlns="http://www.w3.org/2000/svg">' +
    /* 그림자 */
    '<ellipse cx="60" cy="145" rx="28" ry="5" fill="#000" opacity="0.06"/>' +
    /* 뒷발 (작고 둥글게) */
    '<path d="M36 132 Q38 127 46 127 Q54 127 56 132 Q54 140 46 141 Q38 140 36 132Z" fill="#2D2D2D"/>' +
    '<path d="M64 132 Q66 127 74 127 Q82 127 84 132 Q82 140 74 141 Q66 140 64 132Z" fill="#2D2D2D"/>' +
    '<path d="M40 134 Q46 132 52 134 Q52 138 46 139 Q40 138 40 134Z" fill="#6B6B6B" opacity="0.25"/>' +
    '<path d="M68 134 Q74 132 80 134 Q80 138 74 139 Q68 138 68 134Z" fill="#6B6B6B" opacity="0.25"/>' +
    /* 몸통 (아기 비율) */
    '<path d="M34 88 Q28 100 30 118 Q34 138 60 140 Q86 138 90 118 Q92 100 86 88 Q76 82 60 82 Q44 82 34 88Z" fill="#2D2D2D"/>' +
    '<path d="M40 92 Q36 102 38 118 Q42 134 60 136 Q78 134 82 118 Q84 102 80 92 Q72 88 60 88 Q48 88 40 92Z" fill="#FAFAFA"/>' +
    /* 후디 기본 */
    '<path d="M36 88 Q48 83 60 83 Q72 83 84 88 L84 94 Q74 91 60 91 Q46 91 36 94 Z" fill="#D06B83"/>' +
    '<path d="M36 94 Q46 91 60 91 Q74 91 84 94 L88 132 Q74 136 60 136 Q46 136 32 132 Z" fill="#E8839B"/>' +
    '<line x1="60" y1="91" x2="60" y2="132" stroke="#D06B83" stroke-width="1" opacity="0.5"/>' +
    '<path d="M48 112 Q54 110 60 110 Q66 110 72 112 L72 120 Q66 122 60 122 Q54 122 48 120 Z" fill="#D06B83" opacity="0.25"/>' +
    /* 왼팔 (소매 + 손) */
    '<path d="M30 90 Q18 94 14 104 Q12 112 19 113 Q26 112 32 104 Q34 96 30 90Z" fill="#E8839B"/>' +
    '<path d="M17 110 Q24 109 30 105" fill="none" stroke="#D06B83" stroke-width="0.8" opacity="0.5"/>' +
    '<path d="M14 109 Q10 111 11 115 Q13 118 18 117 Q22 115 21 111 Q19 108 14 109Z" fill="#2D2D2D"/>' +
    '<path d="M13 114 Q16 112 19 114 Q19 117 16 117 Q13 117 13 114Z" fill="#FFC1C9" opacity="0.4"/>' +
    /* 오른팔 (소매 + 흔드는 손) */
    '<g>' +
    '<path d="M90 90 Q102 94 106 104 Q108 112 101 113 Q94 112 88 104 Q86 96 90 90Z" fill="#E8839B"/>' +
    '<path d="M103 110 Q96 109 90 105" fill="none" stroke="#D06B83" stroke-width="0.8" opacity="0.5"/>' +
    '<path d="M106 109 Q110 111 109 115 Q107 118 102 117 Q98 115 99 111 Q101 108 106 109Z" fill="#2D2D2D"/>' +
    '<path d="M107 114 Q104 112 101 114 Q101 117 104 117 Q107 117 107 114Z" fill="#FFC1C9" opacity="0.4"/>' +
    '<animateTransform attributeName="transform" type="rotate" values="0,96,100;-12,96,100;0,96,100;6,96,100;0,96,100" dur="3.2s" repeatCount="indefinite"/>' +
    '</g>' +
    /* 귀 (둥글고 큼직) */
    '<path d="M14 22 Q8 4 22 0 Q36 -2 38 14 Q38 24 28 28 Q18 30 14 22Z" fill="#2D2D2D"/>' +
    '<path d="M106 22 Q112 4 98 0 Q84 -2 82 14 Q82 24 92 28 Q102 30 106 22Z" fill="#2D2D2D"/>' +
    '<path d="M19 18 Q15 8 25 5 Q33 4 33 14 Q32 22 26 24 Q21 24 19 18Z" fill="#E8839B" opacity="0.4"/>' +
    '<path d="M101 18 Q105 8 95 5 Q87 4 87 14 Q88 22 94 24 Q99 24 101 18Z" fill="#E8839B" opacity="0.4"/>' +
    /* 머리 (아기 판다 비율) */
    '<path d="M16 48 Q12 22 38 12 Q50 8 60 8 Q70 8 82 12 Q108 22 104 48 Q104 78 82 86 Q70 90 60 90 Q50 90 38 86 Q16 78 16 48Z" fill="#FAFAFA"/>' +
    /* 이마 털 (단순한 한 가닥) */
    '<path d="M48 16 Q54 11 60 13 Q66 11 72 16 Q70 21 66 19 Q60 17 54 19 Q50 21 48 16Z" fill="#2D2D2D"/>' +
    /* 눈 패치 */
    '<path d="M26 38 Q28 28 42 28 Q54 30 56 42 Q56 54 46 58 Q34 60 26 54 Q22 46 26 38Z" fill="#2D2D2D"/>' +
    '<path d="M94 38 Q92 28 78 28 Q66 30 64 42 Q64 54 74 58 Q86 60 94 54 Q98 46 94 38Z" fill="#2D2D2D"/>' +
    /* 눈 */
    '<circle cx="42" cy="44" r="9" fill="#fff"/>' +
    '<circle cx="78" cy="44" r="9" fill="#fff"/>' +
    '<circle cx="43" cy="45" r="6.5" fill="#1C1108"/>' +
    '<circle cx="79" cy="45" r="6.5" fill="#1C1108"/>' +
    '<circle cx="46" cy="42" r="3" fill="#fff"/>' +
    '<circle cx="82" cy="42" r="3" fill="#fff"/>' +
    '<circle cx="41" cy="47" r="1.3" fill="#fff" opacity="0.5"/>' +
    '<circle cx="77" cy="47" r="1.3" fill="#fff" opacity="0.5"/>' +
    /* 눈깜빡 */
    '<path d="M33 44 Q42 40 51 44" stroke="#3A3A3A" stroke-width="2" fill="none" stroke-linecap="round" opacity="0"><animate attributeName="opacity" values="0;0;1;0;0" keyTimes="0;0.95;0.965;0.985;1" dur="4.5s" repeatCount="indefinite"/></path>' +
    '<path d="M69 44 Q78 40 87 44" stroke="#3A3A3A" stroke-width="2" fill="none" stroke-linecap="round" opacity="0"><animate attributeName="opacity" values="0;0;1;0;0" keyTimes="0;0.95;0.965;0.985;1" dur="4.5s" repeatCount="indefinite"/></path>' +
    /* 코 */
    '<path d="M56 56 Q60 52 64 56 Q62 60 60 61 Q58 60 56 56Z" fill="#3A3A3A"/>' +
    '<path d="M58 55 Q60 54 61 56" fill="none" stroke="#555" stroke-width="0.8" opacity="0.3"/>' +
    /* 입 */
    '<path d="M54 62 Q57 66 60 64 Q63 66 66 62" fill="none" stroke="#3A3A3A" stroke-width="1.2" stroke-linecap="round"/>' +
    /* 볼 홍조 */
    '<path d="M22 54 Q28 50 34 54 Q34 60 28 62 Q22 60 22 54Z" fill="#FFB7C5" opacity="0.25"/>' +
    '<path d="M86 54 Q92 50 98 54 Q98 60 92 62 Q86 60 86 54Z" fill="#FFB7C5" opacity="0.25"/>' +
    /* 반짝이 */
    '<path d="M15 36 L16.5 40 L20 40.5 L17 43 L18 46.5 L15 44 L12 46.5 L13 43 L10 40.5 L13.5 40Z" fill="#FFD700" opacity="0.35"><animate attributeName="opacity" values="0.4;0.1;0.4" dur="3.5s" repeatCount="indefinite"/></path>' +
    '</svg>';
})();
</script>

</body>
</html>
