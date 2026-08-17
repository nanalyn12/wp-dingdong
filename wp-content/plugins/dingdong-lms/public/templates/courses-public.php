<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>개설 강좌 - Dingdong</title>
    <link rel="stylesheet" href="<?php echo esc_url( DD_LMS_URL . 'public/css/dd-lesson.css' ); ?>">
    <link rel="stylesheet" href="<?php echo esc_url( DD_LMS_URL . 'public/css/dd-assistant.css' ); ?>">
    <style>
        .dd-courses-page {
            max-width: 960px;
            margin: 0 auto;
            padding: 2rem 1.5rem 4rem;
        }
        .dd-courses-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        .dd-courses-header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin-bottom: 0.5rem;
        }
        .dd-courses-header p {
            color: var(--dd-text-light);
            font-size: 1rem;
        }

        /* ── Filter Tabs ── */
        .dd-filter-bar {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 2.5rem;
            flex-wrap: wrap;
        }
        .dd-filter-btn {
            padding: 0.45rem 1.2rem;
            background: #fff;
            border: 1px solid var(--dd-border);
            border-radius: 20px;
            font-size: 0.82rem;
            font-weight: 500;
            color: var(--dd-text-light);
            cursor: pointer;
            transition: all 0.2s;
        }
        .dd-filter-btn:hover { border-color: var(--dd-primary); color: var(--dd-primary); }
        .dd-filter-btn.is-active {
            background: var(--dd-primary);
            color: #fff;
            border-color: var(--dd-primary);
        }

        /* ── 노래 강좌: 연도/장르 복수 선택 드롭다운 ── */
        .dd-song-filters {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin: -1.4rem 0 2.2rem;
        }
        .dd-song-filters[hidden] { display: none; }
        .dd-msel-panel[hidden] { display: none; }
        .dd-msel { position: relative; }
        .dd-msel-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.4rem 0.9rem;
            background: #fff;
            border: 1px solid var(--dd-border);
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--dd-text-light);
            cursor: pointer;
            transition: all 0.2s;
        }
        .dd-msel-btn:hover { border-color: var(--dd-primary); color: var(--dd-primary); }
        .dd-msel.has-sel .dd-msel-btn {
            background: var(--dd-soft, #FFF0F3);
            border-color: var(--dd-primary);
            color: var(--dd-primary);
        }
        .dd-msel-count { font-weight: 700; }
        .dd-msel-caret { font-size: 0.65rem; opacity: 0.7; }
        .dd-msel-panel {
            position: absolute;
            top: calc(100% + 6px);
            left: 50%;
            transform: translateX(-50%);
            z-index: 20;
            min-width: 150px;
            max-height: 240px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid var(--dd-border);
            border-radius: 14px;
            box-shadow: 0 12px 30px rgba(80,60,120,0.18);
            padding: 0.4rem;
        }
        .dd-msel-opt {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0.6rem;
            border-radius: 10px;
            font-size: 0.82rem;
            color: var(--dd-text);
            cursor: pointer;
            white-space: nowrap;
        }
        .dd-msel-opt:hover { background: var(--dd-soft, #FFF0F3); }
        .dd-msel-opt input { accent-color: var(--dd-primary); cursor: pointer; }
        .dd-msel-clear {
            border: none;
            background: transparent;
            color: var(--dd-text-light);
            font-size: 0.78rem;
            cursor: pointer;
            text-decoration: underline;
            padding: 0.3rem 0.5rem;
        }
        .dd-msel-clear:hover { color: var(--dd-primary); }

        /* ── 묶기 토글 (장르별/가수별/목록) ── */
        .dd-song-group-toggle { display: inline-flex; align-items: center; gap: 0.3rem; }
        .dd-grp-label { font-size: 0.78rem; color: var(--dd-text-light); margin-right: 0.15rem; }
        .dd-grp-btn {
            padding: 0.35rem 0.75rem;
            background: #fff;
            border: 1px solid var(--dd-border);
            border-radius: 16px;
            font-size: 0.78rem;
            font-weight: 500;
            color: var(--dd-text-light);
            cursor: pointer;
            transition: all 0.2s;
        }
        .dd-grp-btn:hover { border-color: var(--dd-primary); color: var(--dd-primary); }
        .dd-grp-btn.is-active {
            background: var(--dd-primary);
            color: #fff;
            border-color: var(--dd-primary);
        }
        .dd-song-filters-divider {
            width: 1px;
            height: 20px;
            background: var(--dd-border);
            margin: 0 0.2rem;
        }

        /* ── 노래 묶음 섹션 ── */
        .dd-song-group {
            background: #fff;
            border-radius: 20px;
            padding: 1.5rem 1.75rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 20px rgba(120,100,160,0.08);
        }
        .dd-song-group-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0 0 1rem;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dd-text);
        }
        .dd-song-group-count {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--dd-primary);
            background: var(--dd-soft, #FFF0F3);
            padding: 0.15rem 0.6rem;
            border-radius: 20px;
        }
        .dd-song-group-list { list-style: none; margin: 0; padding: 0; }
        .dd-song-group-list li { margin-bottom: 0.5rem; }
        .dd-song-group-list li:last-child { margin-bottom: 0; }
        .dd-song-group-item {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            padding: 0.7rem 0.9rem;
            background: var(--dd-soft, #FBF7FB);
            border-radius: 12px;
            text-decoration: none;
            color: var(--dd-text);
            transition: background 0.15s, transform 0.15s;
        }
        .dd-song-group-item:hover { background: #FCE7F3; transform: translateX(2px); }
        .dd-song-group-num {
            flex: none;
            width: 26px;
            height: 26px;
            display: grid;
            place-items: center;
            background: var(--dd-primary);
            color: #fff;
            border-radius: 50%;
            font-size: 0.78rem;
            font-weight: 700;
        }
        .dd-song-group-name { font-size: 0.92rem; font-weight: 500; }
        #dd-song-groups[hidden] { display: none; }

        /* ── Course Block ── */
        .dd-course-block {
            background: var(--dd-card);
            border: 1px solid var(--dd-border);
            border-radius: var(--dd-radius);
            margin-bottom: 2rem;
            box-shadow: var(--dd-shadow);
            overflow: hidden;
            transition: box-shadow 0.2s;
        }
        .dd-course-block:hover {
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .dd-course-block-header {
            display: flex;
            align-items: flex-start;
            gap: 1.25rem;
            padding: 1.75rem 2rem 0;
        }
        .dd-course-thumb {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            object-fit: cover;
            flex-shrink: 0;
            background: var(--dd-soft);
        }
        .dd-course-thumb-placeholder {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--dd-soft) 0%, var(--dd-mid) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            flex-shrink: 0;
        }
        /* 타입별 placeholder 색 구분 */
        .dd-course-block[data-type="song"] .dd-course-thumb-placeholder {
            background: linear-gradient(135deg, #F3E8FF 0%, #C084FC 100%);
        }
        .dd-course-info { flex: 1; min-width: 0; }
        .dd-course-info h2 {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 0.4rem;
            color: var(--dd-text);
        }
        .dd-course-desc {
            color: var(--dd-text-light);
            font-size: 0.88rem;
            margin-bottom: 0.75rem;
            line-height: 1.6;
        }
        .dd-course-badges {
            display: flex;
            gap: 0.4rem;
            flex-wrap: wrap;
            margin-bottom: 0.25rem;
        }
        .dd-course-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.72rem;
            font-weight: 600;
            padding: 0.22rem 0.6rem;
            border-radius: 20px;
        }
        .dd-badge-type-ai { background: #FFF0F3; color: #DB7F8E; }
        .dd-badge-type-song { background: #F3E8FF; color: #7E22CE; }
        .dd-badge-level { background: var(--dd-soft); color: var(--dd-primary); }
        .dd-badge-count { background: #F0F4FF; color: #5B8CDB; }
        .dd-badge-genre { background: #FCE7F3; color: #BE185D; }

        .dd-course-block-body {
            padding: 1rem 2rem 1.75rem;
        }

        /* ── Lesson Links ── */
        .dd-lessons-grid {
            display: grid;
            gap: 0.6rem;
        }
        .dd-lesson-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.7rem 1rem;
            background: var(--dd-bg);
            border: 1px solid var(--dd-border);
            border-radius: 8px;
            text-decoration: none;
            color: var(--dd-text);
            transition: border-color 0.2s, box-shadow 0.2s, transform 0.15s;
        }
        .dd-lesson-link:hover {
            border-color: var(--dd-primary);
            box-shadow: 0 2px 8px rgba(219,127,142,0.15);
            transform: translateX(3px);
        }
        .dd-lesson-link .dd-lesson-num {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--dd-primary);
            color: #fff;
            font-size: 0.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .dd-lesson-link .dd-lesson-name {
            font-size: 0.88rem;
            font-weight: 500;
        }

        /* ── Empty State ── */
        .dd-empty-courses {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--dd-text-light);
        }
        .dd-empty-courses p {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }
        .dd-back-home {
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
        .dd-back-home:hover {
            background: var(--dd-primary-hover);
        }

        @media (max-width: 768px) {
            .dd-course-block-header { flex-direction: column; gap: 0.75rem; padding: 1.25rem 1.25rem 0; }
            .dd-course-block-body { padding: 0.75rem 1.25rem 1.25rem; }
            .dd-courses-header h1 { font-size: 1.6rem; }
        }
    </style>
</head>
<body data-dd-page="courses">

<!-- Topbar -->
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

<div class="dd-courses-page">

    <header class="dd-courses-header">
        <h1>개설 강좌</h1>
        <p>공개된 강좌와 강의를 확인하세요</p>
    </header>

    <?php if ( empty( $courses_data ) ) : ?>
    <div class="dd-empty-courses">
        <p>아직 공개된 강좌가 없습니다.</p>
        <p style="font-size:0.85rem;">관리자가 강좌를 생성하고 공개 링크를 활성화하면 여기에 표시됩니다.</p>
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="dd-back-home">홈으로 돌아가기</a>
    </div>
    <?php else : ?>

        <!-- Filter -->
        <div class="dd-filter-bar">
            <button class="dd-filter-btn is-active" data-filter="all">전체</button>
            <button class="dd-filter-btn" data-filter="ai">&#x2728; AI 강좌</button>
            <button class="dd-filter-btn" data-filter="song">&#x1F3B5; 노래</button>
        </div>

        <!-- 노래 강좌 전용: 묶기 방식 + 연도 / 장르 복수 선택 필터 (JS가 옵션 채움) -->
        <div class="dd-song-filters" id="dd-song-filters" hidden>
            <div class="dd-song-group-toggle" role="group" aria-label="묶기 방식">
                <span class="dd-grp-label">묶기</span>
                <button type="button" class="dd-grp-btn is-active" data-group="genre">장르별</button>
                <button type="button" class="dd-grp-btn" data-group="artist">가수별</button>
                <button type="button" class="dd-grp-btn" data-group="none">목록</button>
            </div>
            <span class="dd-song-filters-divider" aria-hidden="true"></span>
            <div class="dd-msel" data-facet="year">
                <button type="button" class="dd-msel-btn" aria-expanded="false">&#x1F4C5; 연도 <span class="dd-msel-count"></span> <span class="dd-msel-caret">&#9662;</span></button>
                <div class="dd-msel-panel" hidden></div>
            </div>
            <div class="dd-msel" data-facet="genre">
                <button type="button" class="dd-msel-btn" aria-expanded="false">&#x1F3B5; 장르 <span class="dd-msel-count"></span> <span class="dd-msel-caret">&#9662;</span></button>
                <div class="dd-msel-panel" hidden></div>
            </div>
            <button type="button" class="dd-msel-clear" id="dd-song-filters-clear" hidden>초기화</button>
        </div>

        <?php
        $level_labels = array( 'beginner' => '입문', 'intermediate' => '중급', 'advanced' => '고급' );
        foreach ( $courses_data as $course ) :
            $course_type = $course['type'] ?? '';
            $is_song  = ( $course_type === 'song' );
            $filter_type = $is_song ? 'song' : 'ai';
            $type_emoji  = $is_song ? '&#x1F3B5;' : '&#x53E1;';
            $level_text = isset( $level_labels[ $course['level'] ?? '' ] ) ? $level_labels[ $course['level'] ] : '';
        ?>
        <div class="dd-course-block" data-type="<?php echo esc_attr( $filter_type ); ?>" data-genre="<?php echo esc_attr( $course['genre'] ?? '' ); ?>" data-artist="<?php echo esc_attr( $course['artist'] ?? '' ); ?>" data-years="<?php echo esc_attr( implode( ',', $course['years'] ?? array() ) ); ?>">
            <div class="dd-course-block-header">
                <?php if ( ! empty( $course['thumbnail'] ) ) : ?>
                    <img class="dd-course-thumb" src="<?php echo esc_url( $course['thumbnail'] ); ?>" alt="">
                <?php else : ?>
                    <div class="dd-course-thumb-placeholder"><?php echo $type_emoji; ?></div>
                <?php endif; ?>
                <div class="dd-course-info">
                    <h2><?php echo esc_html( $course['title'] ); ?></h2>
                    <?php if ( ! empty( $course['description'] ) ) : ?>
                    <p class="dd-course-desc"><?php echo esc_html( wp_trim_words( $course['description'], 30, '...' ) ); ?></p>
                    <?php endif; ?>
                    <div class="dd-course-badges">
                        <?php if ( $is_song ) : ?>
                            <span class="dd-course-badge dd-badge-type-song">&#x1F3B5; 노래 학습</span>
                        <?php else : ?>
                            <span class="dd-course-badge dd-badge-type-ai">&#x2728; AI 강좌</span>
                        <?php endif; ?>
                        <?php if ( ! empty( $course['genre'] ) ) : ?>
                            <span class="dd-course-badge dd-badge-genre">&#x1F3B5; <?php echo esc_html( $course['genre'] ); ?></span>
                        <?php endif; ?>
                        <?php if ( $level_text ) : ?>
                            <span class="dd-course-badge dd-badge-level"><?php echo esc_html( $level_text ); ?></span>
                        <?php endif; ?>
                        <span class="dd-course-badge dd-badge-count"><?php echo count( $course['lessons'] ); ?>개 강의</span>
                    </div>
                </div>
            </div>
            <div class="dd-course-block-body">
                <div class="dd-lessons-grid">
                    <?php foreach ( $course['lessons'] as $lesson ) : ?>
                    <a href="<?php echo esc_url( $lesson['url'] ); ?>" class="dd-lesson-link">
                        <span class="dd-lesson-num"><?php echo esc_html( $lesson['order'] ); ?></span>
                        <span class="dd-lesson-name"><?php echo esc_html( $lesson['title'] ); ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- 노래 강좌 묶음 보기 (JS가 채움): 장르별/가수별 그룹 -->
        <div id="dd-song-groups" hidden></div>

        <div style="text-align:center; margin-top:2rem;">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="dd-back-home">홈으로 돌아가기</a>
        </div>
    <?php endif; ?>

</div>

<script>
(function() {
    var btns = document.querySelectorAll('.dd-filter-btn');
    var blocks = Array.prototype.slice.call(document.querySelectorAll('.dd-course-block'));
    var songFilters = document.getElementById('dd-song-filters');
    var clearBtn = document.getElementById('dd-song-filters-clear');
    var groupsContainer = document.getElementById('dd-song-groups');
    var state = { type: 'all', year: [], genre: [], groupMode: 'genre' };

    function escHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    /* 노래 강좌 블록에서 연도/장르 옵션 수집 */
    function collectOptions(facet) {
        var set = {};
        blocks.forEach(function(b) {
            if (b.getAttribute('data-type') !== 'song') return;
            if (facet === 'year') {
                (b.getAttribute('data-years') || '').split(',').forEach(function(y) {
                    y = y.trim(); if (y) set[y] = true;
                });
            } else {
                var g = (b.getAttribute('data-genre') || '').trim();
                if (g) set[g] = true;
            }
        });
        return Object.keys(set);
    }

    function buildPanel(facet) {
        if (!songFilters) return;
        var msel = songFilters.querySelector('.dd-msel[data-facet="' + facet + '"]');
        if (!msel) return;
        var panel = msel.querySelector('.dd-msel-panel');
        var opts = collectOptions(facet);
        if (facet === 'year') {
            opts.sort(function(a, b) { return b.localeCompare(a, undefined, { numeric: true }); }); /* 최신 연도 먼저 */
        } else {
            opts.sort(function(a, b) { return a.localeCompare(b); });
        }
        if (!opts.length) { msel.style.display = 'none'; return; }
        msel.style.display = '';
        panel.innerHTML = opts.map(function(o) {
            var v = o.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
            return '<label class="dd-msel-opt"><input type="checkbox" value="' + v + '"><span>' + v + '</span></label>';
        }).join('');
        panel.querySelectorAll('input').forEach(function(cb) {
            cb.addEventListener('change', function() {
                var arr = state[facet];
                var i = arr.indexOf(cb.value);
                if (cb.checked && i === -1) arr.push(cb.value);
                else if (!cb.checked && i !== -1) arr.splice(i, 1);
                updateCounts();
                applyFilter();
            });
        });
    }

    function updateCounts() {
        if (!songFilters) return;
        ['year', 'genre'].forEach(function(facet) {
            var msel = songFilters.querySelector('.dd-msel[data-facet="' + facet + '"]');
            if (!msel) return;
            var n = state[facet].length;
            msel.querySelector('.dd-msel-count').textContent = n ? '(' + n + ')' : '';
            msel.classList.toggle('has-sel', n > 0);
        });
        if (clearBtn) clearBtn.hidden = (state.year.length + state.genre.length) === 0;
    }

    function applyFilter() {
        var visibleSongBlocks = [];
        blocks.forEach(function(block) {
            var type = block.getAttribute('data-type');
            var show = (state.type === 'all' || type === state.type);
            if (show && state.type === 'song') {
                if (state.year.length) {
                    var ys = (block.getAttribute('data-years') || '').split(',').map(function(s) { return s.trim(); });
                    show = state.year.some(function(y) { return ys.indexOf(y) !== -1; });
                }
                if (show && state.genre.length) {
                    show = state.genre.indexOf((block.getAttribute('data-genre') || '').trim()) !== -1;
                }
            }
            block._ddShow = show;
            if (show && type === 'song') visibleSongBlocks.push(block);
        });

        var grouped = (state.type === 'song' && state.groupMode !== 'none');
        blocks.forEach(function(block) {
            /* 묶음 보기에서는 개별 강좌 블록을 숨기고 그룹 섹션으로 대체 */
            block.style.display = (block._ddShow && !grouped) ? '' : 'none';
        });

        if (grouped) {
            buildGroups(visibleSongBlocks, state.groupMode);
            if (groupsContainer) groupsContainer.hidden = false;
        } else if (groupsContainer) {
            groupsContainer.hidden = true;
            groupsContainer.innerHTML = '';
        }
    }

    /* 강좌 블록 → 그룹 키(장르/가수). 가수 미설정 시 제목의 "가수 - 곡명"에서 추출 */
    function groupKeyOf(block, mode) {
        if (mode === 'artist') {
            var a = (block.getAttribute('data-artist') || '').trim();
            if (a) return a;
            var h2 = block.querySelector('.dd-course-info h2');
            var title = h2 ? h2.textContent.trim() : '';
            var parts = title.split(/\s[-–—]\s/);
            if (parts.length > 1 && parts[0].trim()) return parts[0].trim();
            return '기타';
        }
        return (block.getAttribute('data-genre') || '').trim() || '기타';
    }

    function lessonsOf(block) {
        return Array.prototype.map.call(block.querySelectorAll('.dd-lesson-link'), function(a) {
            var n = a.querySelector('.dd-lesson-name');
            return { url: a.getAttribute('href'), name: n ? n.textContent.trim() : a.textContent.trim() };
        });
    }

    function buildGroups(songBlocks, mode) {
        if (!groupsContainer) return;
        var groups = {}, order = [];
        songBlocks.forEach(function(block) {
            var key = groupKeyOf(block, mode);
            if (!groups[key]) { groups[key] = []; order.push(key); }
            lessonsOf(block).forEach(function(ls) { groups[key].push(ls); });
        });
        order.sort(function(a, b) { return a.localeCompare(b); });

        var html = order.map(function(key) {
            var items = groups[key];
            var lis = items.map(function(ls, i) {
                return '<li><a class="dd-song-group-item" href="' + ls.url + '">' +
                       '<span class="dd-song-group-num">' + (i + 1) + '</span>' +
                       '<span class="dd-song-group-name">' + escHtml(ls.name) + '</span></a></li>';
            }).join('');
            var heading = (mode === 'artist')
                ? (escHtml(key) + ' 노래 모음')
                : (escHtml(key) + '로 중국어 배우기');
            return '<div class="dd-song-group">' +
                   '<h2 class="dd-song-group-title">&#x1F3B5; ' + heading +
                   ' <span class="dd-song-group-count">' + items.length + '곡</span></h2>' +
                   '<ol class="dd-song-group-list">' + lis + '</ol></div>';
        }).join('');

        groupsContainer.innerHTML = html ||
            '<p style="text-align:center;color:var(--dd-text-light);padding:2rem;">조건에 맞는 노래가 없어요.</p>';
    }

    function clearAllChecks() {
        if (!songFilters) return;
        songFilters.querySelectorAll('input[type="checkbox"]').forEach(function(cb) { cb.checked = false; });
    }

    btns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            btns.forEach(function(b) { b.classList.remove('is-active'); });
            btn.classList.add('is-active');
            state.type = btn.getAttribute('data-filter');
            if (state.type === 'song' && songFilters) {
                buildPanel('year');
                buildPanel('genre');
                songFilters.hidden = false;
            } else {
                if (songFilters) songFilters.hidden = true;
                state.year = [];
                state.genre = [];
                clearAllChecks();
                updateCounts();
            }
            applyFilter();
        });
    });

    /* 드롭다운 열기/닫기 */
    if (songFilters) {
        songFilters.querySelectorAll('.dd-msel-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var panel = btn.nextElementSibling;
                var willOpen = panel.hidden;
                songFilters.querySelectorAll('.dd-msel-panel').forEach(function(p) { p.hidden = true; });
                songFilters.querySelectorAll('.dd-msel-btn').forEach(function(b) { b.setAttribute('aria-expanded', 'false'); });
                panel.hidden = !willOpen;
                btn.setAttribute('aria-expanded', String(willOpen));
            });
        });
        songFilters.addEventListener('click', function(e) { e.stopPropagation(); });
        document.addEventListener('click', function() {
            songFilters.querySelectorAll('.dd-msel-panel').forEach(function(p) { p.hidden = true; });
            songFilters.querySelectorAll('.dd-msel-btn').forEach(function(b) { b.setAttribute('aria-expanded', 'false'); });
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            state.year = [];
            state.genre = [];
            clearAllChecks();
            updateCounts();
            applyFilter();
        });
    }

    /* 묶기 토글: 장르별 / 가수별 / 목록 */
    if (songFilters) {
        var grpBtns = songFilters.querySelectorAll('.dd-grp-btn');
        grpBtns.forEach(function(b) {
            b.addEventListener('click', function() {
                grpBtns.forEach(function(x) { x.classList.remove('is-active'); });
                b.classList.add('is-active');
                state.groupMode = b.getAttribute('data-group');
                applyFilter();
            });
        });
    }

    /* URL ?filter=song|ai 또는 #filter=song → 해당 필터 자동 적용
       (딩딩 음성도우미가 "노래 강좌 틀어줘" 시 여기로 보냄) */
    (function() {
        var m = /[?&]filter=([a-z]+)/.exec(location.search) || /#filter=([a-z]+)/.exec(location.hash);
        var f = m && m[1];
        if (!f) return;
        var target = null;
        btns.forEach(function(b) { if (b.getAttribute('data-filter') === f) target = b; });
        if (target) {
            target.click();
            var sec = document.querySelector('.dd-filter-bar');
            if (sec && sec.scrollIntoView) sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    })();
})();
</script>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-shared.js' ); ?>"></script>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-gamification.js' ); ?>"></script>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-assistant.js' ); ?>"></script>

</body>
</html>
