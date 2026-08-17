<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>뉴스레터 - Dingdong</title>
    <link rel="stylesheet" href="<?php echo esc_url( DD_LMS_URL . 'public/css/dd-lesson.css' ); ?>">
    <link rel="stylesheet" href="<?php echo esc_url( DD_LMS_URL . 'public/css/dd-assistant.css' ); ?>">
    <style>
        .dd-nl-page {
            max-width: 960px;
            margin: 0 auto;
            padding: 2rem 1.5rem 4rem;
        }
        .dd-nl-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        .dd-nl-header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin-bottom: 0.5rem;
        }
        .dd-nl-header p {
            color: var(--dd-text-light);
            font-size: 1rem;
        }
        .dd-nl-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        .dd-nl-card {
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
        .dd-nl-card-img {
            width: 100%; aspect-ratio: 16/9; overflow: hidden;
        }
        .dd-nl-card-img img {
            width: 100%; height: 100%; object-fit: cover; display: block;
        }
        .dd-nl-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        }
        .dd-nl-card-body {
            padding: 1.5rem; display: flex; flex-direction: column; flex-grow: 1;
        }
        .dd-nl-card-top {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }
        .dd-nl-emoji {
            font-size: 2rem;
            line-height: 1;
        }
        .dd-nl-cat {
            display: inline-block;
            background: var(--dd-soft);
            color: var(--dd-primary);
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.15rem 0.5rem;
            border-radius: 20px;
        }
        .dd-nl-card h3 {
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            line-height: 1.4;
        }
        .dd-nl-card .dd-nl-summary {
            font-size: 0.85rem;
            color: var(--dd-text-light);
            line-height: 1.6;
            flex-grow: 1;
            margin-bottom: 0.75rem;
        }
        .dd-nl-card .dd-nl-date {
            font-size: 0.78rem;
            color: #999;
        }
        .dd-nl-empty {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--dd-text-light);
        }
        .dd-nl-back {
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
        .dd-nl-back:hover {
            background: var(--dd-primary-hover);
        }
    </style>
</head>
<body data-dd-page="newsletters">

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

<div class="dd-nl-page">
    <header class="dd-nl-header">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="display:inline-block;font-size:1.1rem;font-weight:700;color:var(--dd-primary);text-decoration:none;margin-bottom:1.5rem;">Dingdong 叮咚</a>
        <h1>뉴스레터</h1>
        <p>중국 대중문화 트렌드와 함께 중국어를 배워보세요</p>
    </header>

    <?php
    $cat_labels = array(
        'entertainment' => '엔터테인먼트',
        'music'         => '음악',
        'food'          => '음식',
        'tech'          => '테크',
        'drama'         => '드라마',
        'social'        => '소셜미디어',
    );
    ?>

    <?php if ( empty( $newsletters ) ) : ?>
    <div class="dd-nl-empty">
        <p style="font-size:2.5rem;margin-bottom:1rem;">📰</p>
        <p style="font-size:1.1rem;margin-bottom:0.5rem;">아직 발행된 뉴스레터가 없습니다.</p>
        <p style="font-size:0.85rem;">곧 중국 대중문화 소식이 업데이트됩니다!</p>
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="dd-nl-back">홈으로 돌아가기</a>
    </div>
    <?php else : ?>
    <div class="dd-nl-grid">
        <?php foreach ( $newsletters as $nl ) : ?>
        <a href="<?php echo esc_url( $nl['url'] ); ?>" class="dd-nl-card">
            <?php if ( ! empty( $nl['cover_image'] ) ) : ?>
            <div class="dd-nl-card-img">
                <img src="<?php echo esc_url( $nl['cover_image'] ); ?>" alt="<?php echo esc_attr( $nl['title'] ); ?>">
            </div>
            <?php endif; ?>
            <div class="dd-nl-card-body">
                <div class="dd-nl-card-top">
                    <span class="dd-nl-emoji"><?php echo esc_html( $nl['emoji'] ?: '📰' ); ?></span>
                    <span class="dd-nl-cat"><?php echo esc_html( $cat_labels[ $nl['category'] ] ?? $nl['category'] ); ?></span>
                </div>
                <h3><?php echo esc_html( $nl['title'] ); ?></h3>
                <p class="dd-nl-summary"><?php echo esc_html( $nl['summary'] ); ?></p>
                <span class="dd-nl-date"><?php echo esc_html( $nl['date'] ); ?></span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <div style="text-align:center;margin-top:2rem;">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="dd-nl-back">홈으로 돌아가기</a>
    </div>
    <?php endif; ?>
</div>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-shared.js' ); ?>"></script>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-gamification.js' ); ?>"></script>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-assistant.js' ); ?>"></script>
</body>
</html>
