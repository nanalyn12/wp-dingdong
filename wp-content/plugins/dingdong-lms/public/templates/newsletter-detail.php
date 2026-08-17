<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html( $data['title'] ); ?> - Dingdong 뉴스레터</title>
    <link rel="stylesheet" href="<?php echo esc_url( DD_LMS_URL . 'public/css/dd-lesson.css' ); ?>">
    <link rel="stylesheet" href="<?php echo esc_url( DD_LMS_URL . 'public/css/dd-assistant.css' ); ?>">
    <style>
        .dd-nld-page { max-width: 720px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
        .dd-nld-cover {
            border-radius: var(--dd-radius); overflow: hidden;
            margin-bottom: 1.5rem; box-shadow: var(--dd-shadow);
        }
        .dd-nld-cover img {
            width: 100%; height: auto; display: block;
            aspect-ratio: 16/9; object-fit: cover;
        }
        .dd-nld-sec-img {
            border-radius: 10px; overflow: hidden;
            margin-bottom: 1rem;
        }
        .dd-nld-sec-img img {
            width: 100%; height: auto; display: block;
            aspect-ratio: 16/9; object-fit: cover;
        }
        .dd-nld-header { text-align: center; margin-bottom: 2.5rem; }
        .dd-nld-emoji { font-size: 3rem; margin-bottom: 0.75rem; display: block; }
        .dd-nld-cat {
            display: inline-block;
            background: var(--dd-soft); color: var(--dd-primary);
            font-size: 0.78rem; font-weight: 600;
            padding: 0.2rem 0.7rem; border-radius: 20px;
            margin-bottom: 0.75rem;
        }
        .dd-nld-header h1 { font-size: 1.8rem; font-weight: 700; margin-bottom: 0.5rem; letter-spacing: -0.02em; }
        .dd-nld-header .dd-nld-zh { font-size: 1.1rem; color: var(--dd-primary); font-weight: 500; margin-bottom: 0.5rem; }
        .dd-nld-header .dd-nld-date { font-size: 0.85rem; color: #999; }

        .dd-nld-section {
            background: var(--dd-card); border: 1px solid var(--dd-border);
            border-radius: var(--dd-radius); padding: 1.5rem;
            margin-bottom: 1.5rem; box-shadow: var(--dd-shadow);
        }
        .dd-nld-section h2 {
            font-size: 1.15rem; font-weight: 700;
            margin-bottom: 1rem; padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--dd-soft);
        }
        .dd-nld-content { font-size: 0.92rem; line-height: 1.8; color: var(--dd-text); white-space: pre-line; }

        .dd-nld-terms { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem; }
        .dd-nld-term {
            display: inline-flex; align-items: center; gap: 0.4rem;
            background: linear-gradient(135deg, #FFF5F5, #FFF0F7);
            border: 1px solid rgba(219,127,142,0.2);
            border-radius: 8px; padding: 0.35rem 0.6rem;
            font-size: 0.82rem;
        }
        .dd-nld-term-zh { font-family: 'Noto Sans SC', sans-serif; font-weight: 700; color: var(--dd-text); }
        .dd-nld-term-pinyin { font-size: 0.72rem; color: #888; font-style: italic; }
        .dd-nld-term-ko { color: var(--dd-text-light); }
        .dd-nld-term-hsk {
            font-size: 0.65rem; font-weight: 700;
            background: var(--dd-primary); color: #fff;
            border-radius: 4px; padding: 0.1rem 0.3rem;
        }

        .dd-nld-vocab-section { margin-top: 2rem; }
        .dd-nld-vocab-title {
            font-size: 1.15rem; font-weight: 700;
            margin-bottom: 1rem; text-align: center;
        }
        .dd-nld-vocab-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }
        .dd-nld-vocab-card {
            background: var(--dd-card); border: 1px solid var(--dd-border);
            border-radius: var(--dd-radius); padding: 1rem;
            border-left: 4px solid var(--dd-primary);
            position: relative;
        }
        .dd-nld-vocab-card .dd-vc-zh {
            font-family: 'Noto Sans SC', sans-serif;
            font-size: 1.3rem; font-weight: 700; margin-bottom: 0.25rem;
        }
        .dd-nld-vocab-card .dd-vc-pinyin { font-size: 0.78rem; color: #888; font-style: italic; margin-bottom: 0.2rem; }
        .dd-nld-vocab-card .dd-vc-ko { font-size: 0.88rem; color: var(--dd-text); font-weight: 500; margin-bottom: 0.5rem; }
        .dd-nld-vocab-card .dd-vc-example { font-size: 0.78rem; color: var(--dd-text-light); line-height: 1.5; }
        .dd-nld-vocab-card .dd-vc-example-zh { font-family: 'Noto Sans SC', sans-serif; }
        .dd-nld-vocab-card .dd-vc-hsk {
            position: absolute; top: 0.5rem; right: 0.5rem;
            font-size: 0.65rem; font-weight: 700;
            background: var(--dd-primary); color: #fff;
            border-radius: 4px; padding: 0.1rem 0.35rem;
        }
        .dd-nld-vocab-card .dd-vc-save {
            display: flex; align-items: center; gap: 0.3rem;
            margin-top: 0.5rem; padding: 0.3rem 0.5rem;
            background: none; border: 1px solid var(--dd-border);
            border-radius: 6px; cursor: pointer; font-size: 0.75rem;
            color: var(--dd-text-light); transition: all 0.2s;
        }
        .dd-vc-save:hover { border-color: var(--dd-primary); color: var(--dd-primary); }
        .dd-vc-save.is-saved { background: var(--dd-soft); border-color: var(--dd-primary); color: var(--dd-primary); }

        .dd-nld-back {
            display: inline-block; margin-top: 2rem;
            padding: 0.6rem 1.5rem;
            background: var(--dd-primary); color: #fff;
            border-radius: 8px; text-decoration: none;
            font-size: 0.9rem; font-weight: 500;
        }
    </style>
</head>
<body data-dd-page="newsletter">

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

<div class="dd-nld-page">
    <?php if ( ! empty( $data['cover_image'] ) ) : ?>
    <div class="dd-nld-cover">
        <img src="<?php echo esc_url( $data['cover_image'] ); ?>" alt="<?php echo esc_attr( $data['title'] ); ?>">
    </div>
    <?php endif; ?>

    <header class="dd-nld-header">
        <span class="dd-nld-emoji"><?php echo esc_html( $data['emoji'] ?: '📰' ); ?></span>
        <span class="dd-nld-cat"><?php echo esc_html( $cat_labels[ $data['category'] ] ?? $data['category'] ); ?></span>
        <h1><?php echo esc_html( $data['title'] ); ?></h1>
        <?php if ( ! empty( $data['title_zh'] ) ) : ?>
        <p class="dd-nld-zh"><?php echo esc_html( $data['title_zh'] ); ?></p>
        <?php endif; ?>
        <p class="dd-nld-date"><?php echo esc_html( $data['date'] ); ?></p>
    </header>

    <?php if ( ! empty( $data['sections'] ) ) : ?>
        <?php foreach ( $data['sections'] as $section ) : ?>
        <div class="dd-nld-section">
            <?php if ( ! empty( $section['image_url'] ) ) : ?>
            <div class="dd-nld-sec-img">
                <img src="<?php echo esc_url( $section['image_url'] ); ?>" alt="<?php echo esc_attr( $section['title'] ?? '' ); ?>">
            </div>
            <?php endif; ?>
            <h2><?php echo esc_html( $section['title'] ?? '' ); ?></h2>
            <div class="dd-nld-content"><?php echo esc_html( $section['content'] ?? '' ); ?></div>
            <?php if ( ! empty( $section['key_terms'] ) ) : ?>
            <div class="dd-nld-terms">
                <?php foreach ( $section['key_terms'] as $term ) : ?>
                <span class="dd-nld-term">
                    <span class="dd-nld-term-zh"><?php echo esc_html( $term['zh'] ?? '' ); ?></span>
                    <span class="dd-nld-term-pinyin"><?php echo esc_html( $term['pinyin'] ?? '' ); ?></span>
                    <span class="dd-nld-term-ko"><?php echo esc_html( $term['ko'] ?? '' ); ?></span>
                    <?php if ( ! empty( $term['hsk'] ) ) : ?>
                    <span class="dd-nld-term-hsk">HSK<?php echo (int) $term['hsk']; ?></span>
                    <?php endif; ?>
                </span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ( ! empty( $data['vocab'] ) ) : ?>
    <div class="dd-nld-vocab-section">
        <h2 class="dd-nld-vocab-title">오늘의 어휘</h2>
        <div class="dd-nld-vocab-grid">
            <?php foreach ( $data['vocab'] as $vi => $v ) : ?>
            <div class="dd-nld-vocab-card">
                <?php if ( ! empty( $v['hsk'] ) ) : ?>
                <span class="dd-vc-hsk">HSK<?php echo (int) $v['hsk']; ?></span>
                <?php endif; ?>
                <div class="dd-vc-zh"><?php echo esc_html( $v['zh'] ?? '' ); ?></div>
                <div class="dd-vc-pinyin"><?php echo esc_html( $v['pinyin'] ?? '' ); ?></div>
                <div class="dd-vc-ko"><?php echo esc_html( $v['ko'] ?? '' ); ?></div>
                <?php if ( ! empty( $v['example_zh'] ) ) : ?>
                <div class="dd-vc-example">
                    <div class="dd-vc-example-zh"><?php echo esc_html( $v['example_zh'] ); ?></div>
                    <div><?php echo esc_html( $v['example_ko'] ?? '' ); ?></div>
                </div>
                <?php endif; ?>
                <button class="dd-vc-save"
                        data-zh="<?php echo esc_attr( $v['zh'] ?? '' ); ?>"
                        data-pinyin="<?php echo esc_attr( $v['pinyin'] ?? '' ); ?>"
                        data-ko="<?php echo esc_attr( $v['ko'] ?? '' ); ?>"
                        data-hsk="<?php echo esc_attr( $v['hsk'] ?? '' ); ?>"
                        data-example-zh="<?php echo esc_attr( $v['example_zh'] ?? '' ); ?>"
                        data-example-ko="<?php echo esc_attr( $v['example_ko'] ?? '' ); ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                    단어장에 저장
                </button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div style="text-align:center;">
        <a href="<?php echo esc_url( home_url( '/newsletters/' ) ); ?>" class="dd-nld-back">뉴스레터 목록</a>
    </div>
</div>

<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-vocabulary.js' ); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof DDVocab === 'undefined') return;
    document.querySelectorAll('.dd-vc-save').forEach(function(btn) {
        var zh = btn.dataset.zh;
        if (DDVocab.has(zh)) btn.classList.add('is-saved');

        btn.addEventListener('click', function() {
            if (DDVocab.has(zh)) {
                DDVocab.remove(zh);
                btn.classList.remove('is-saved');
                btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg> 단어장에 저장';
            } else {
                DDVocab.add({
                    zh: btn.dataset.zh,
                    pinyin: btn.dataset.pinyin,
                    ko: btn.dataset.ko,
                    hsk: parseInt(btn.dataset.hsk) || 0,
                    examples: btn.dataset.exampleZh ? [{
                        zh: btn.dataset.exampleZh,
                        ko: btn.dataset.exampleKo
                    }] : [],
                    source: { lesson_id: 0, lesson_title: '뉴스레터' }
                });
                btn.classList.add('is-saved');
                btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg> 저장됨';
            }
        });
    });
});
</script>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-shared.js' ); ?>"></script>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-gamification.js' ); ?>"></script>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-assistant.js' ); ?>"></script>
</body>
</html>
