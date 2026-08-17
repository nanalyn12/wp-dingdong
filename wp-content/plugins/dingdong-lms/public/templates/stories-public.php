<?php
$level_labels = array(
    'beginner'     => '🌱 입문',
    'elementary'   => '📗 초급',
    'intermediate' => '📘 중급',
    'advanced'     => '📕 고급',
);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>인터랙티브 스토리 - DingDong</title>
    <link rel="stylesheet" href="<?php echo esc_url( DD_LMS_URL . 'public/css/dd-lesson.css' ); ?>">
    <link rel="stylesheet" href="<?php echo esc_url( DD_LMS_URL . 'public/css/dd-assistant.css' ); ?>">
    <style>
        .dd-stories-page {
            max-width: 960px;
            margin: 0 auto;
            padding: 2rem 1.5rem 4rem;
        }
        .dd-stories-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        .dd-stories-header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin-bottom: 0.5rem;
        }
        .dd-stories-header p {
            color: var(--dd-text-light);
            font-size: 1rem;
        }
        .dd-stories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        .dd-stories-card {
            background: var(--dd-card);
            border: 1px solid var(--dd-border);
            border-radius: var(--dd-radius);
            overflow: hidden;
            box-shadow: var(--dd-shadow);
            text-decoration: none;
            color: var(--dd-text);
            display: flex;
            flex-direction: column;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .dd-stories-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        }
        .dd-stories-card-img {
            width: 100%; aspect-ratio: 16/9; overflow: hidden;
            background: linear-gradient(135deg, #EDE9FE 0%, #DDD6FE 100%);
            display: flex; align-items: center; justify-content: center;
        }
        .dd-stories-card-img img {
            width: 100%; height: 100%; object-fit: cover; display: block;
        }
        .dd-stories-card-placeholder {
            font-size: 3rem;
        }
        .dd-stories-card-body {
            padding: 1.5rem; display: flex; flex-direction: column; flex-grow: 1;
        }
        .dd-stories-card h3 {
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            line-height: 1.4;
        }
        .dd-stories-card-meta {
            display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;
        }
        .dd-stories-badge {
            display: inline-block;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.15rem 0.6rem;
            border-radius: 20px;
        }
        .dd-stories-badge--type {
            background: #EDE9FE; color: #7C3AED;
        }
        .dd-stories-badge--level {
            background: var(--dd-soft); color: var(--dd-primary);
        }
        .dd-stories-date {
            font-size: 0.78rem;
            color: #999;
            margin-top: 0.75rem;
        }
        .dd-stories-empty {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--dd-text-light);
        }
        .dd-stories-back {
            display: inline-block;
            margin-top: 2rem;
            padding: 0.6rem 1.5rem;
            background: var(--dd-primary);
            color: #fff;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .dd-stories-back:hover {
            background: var(--dd-primary-hover);
        }
    </style>
</head>
<body data-dd-page="stories">

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

<div class="dd-stories-page">
    <header class="dd-stories-header">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="display:inline-block;font-size:1.1rem;font-weight:700;color:var(--dd-primary);text-decoration:none;margin-bottom:1.5rem;">Dingdong 叮咚</a>
        <h1>인터랙티브 스토리</h1>
        <p>선택에 따라 이야기가 달라지는 게임북 형식의 중국어 학습</p>
    </header>

    <?php if ( empty( $stories ) ) : ?>
    <div class="dd-stories-empty">
        <p style="font-size:2.5rem;margin-bottom:1rem;">📖</p>
        <p style="font-size:1.1rem;margin-bottom:0.5rem;">아직 공개된 스토리가 없습니다.</p>
        <p style="font-size:0.85rem;">곧 재미있는 인터랙티브 스토리가 업데이트됩니다!</p>
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="dd-stories-back">홈으로 돌아가기</a>
    </div>
    <?php else : ?>
    <div class="dd-stories-grid">
        <?php foreach ( $stories as $st ) : ?>
        <a href="<?php echo esc_url( $st['url'] ); ?>" class="dd-stories-card">
            <div class="dd-stories-card-img">
                <?php if ( ! empty( $st['cover'] ) ) : ?>
                    <img src="<?php echo esc_url( $st['cover'] ); ?>" alt="<?php echo esc_attr( $st['title'] ); ?>">
                <?php else : ?>
                    <span class="dd-stories-card-placeholder">📖</span>
                <?php endif; ?>
            </div>
            <div class="dd-stories-card-body">
                <h3><?php echo esc_html( $st['title'] ); ?></h3>
                <div class="dd-stories-card-meta">
                    <span class="dd-stories-badge dd-stories-badge--type">🎮 인터랙티브</span>
                    <?php if ( isset( $level_labels[ $st['level'] ] ) ) : ?>
                        <span class="dd-stories-badge dd-stories-badge--level"><?php echo esc_html( $level_labels[ $st['level'] ] ); ?></span>
                    <?php endif; ?>
                </div>
                <span class="dd-stories-date"><?php echo esc_html( $st['date'] ); ?></span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <div style="text-align:center;margin-top:2rem;">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="dd-stories-back">홈으로 돌아가기</a>
    </div>
    <?php endif; ?>
</div>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-shared.js' ); ?>"></script>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-gamification.js' ); ?>"></script>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-assistant.js' ); ?>"></script>
</body>
</html>
