/**
 * DDVocab — localStorage-based vocabulary notebook
 * Used by lesson tabs, newsletter detail, and standalone /vocabulary/ page
 */
var DDVocab = (function() {
    var STORAGE_KEY = 'dd_vocabulary';

    function _load() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return { version: 1, words: [] };
            var d = JSON.parse(raw);
            if (!d.words) d.words = [];
            return d;
        } catch(e) {
            return { version: 1, words: [] };
        }
    }

    function _save(data) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
    }

    function _uuid() {
        return 'xxxx-xxxx'.replace(/x/g, function() {
            return (Math.random() * 16 | 0).toString(16);
        });
    }

    function add(word) {
        var data = _load();
        var exists = data.words.find(function(w) { return w.zh === word.zh; });
        if (exists) return false;
        data.words.push({
            id: _uuid(),
            zh: word.zh || '',
            pinyin: word.pinyin || '',
            ko: word.ko || '',
            hsk: word.hsk || 0,
            examples: word.examples || [],
            source: word.source || { lesson_id: 0, lesson_title: '' },
            status: 'new',
            saved_at: new Date().toISOString(),
            last_reviewed: null
        });
        _save(data);
        if (window.DDGamification) DDGamification.onVocabAdd();
        return true;
    }

    function remove(zh) {
        var data = _load();
        data.words = data.words.filter(function(w) { return w.zh !== zh; });
        _save(data);
    }

    function has(zh) {
        var data = _load();
        return data.words.some(function(w) { return w.zh === zh; });
    }

    function getAll() {
        return _load().words;
    }

    function getByLesson(lessonId) {
        return _load().words.filter(function(w) {
            return w.source && w.source.lesson_id == lessonId;
        });
    }

    function updateStatus(zh, status) {
        var data = _load();
        var w = data.words.find(function(w) { return w.zh === zh; });
        if (w) {
            w.status = status;
            w.last_reviewed = new Date().toISOString();
            _save(data);
        }

        // SM-2 SRS 연동: 상태 변경을 적응형 복습 엔진에 피드백
        if (window.DDSRS) {
            var quality = DDSRS.flashcardToQuality(status);
            DDSRS.recordReview(zh, quality, 'flashcard');
        }
    }

    function getStats() {
        var words = _load().words;
        var total = words.length;
        var mastered = words.filter(function(w) { return w.status === 'mastered'; }).length;
        var learning = words.filter(function(w) { return w.status === 'learning'; }).length;
        var newCount = total - mastered - learning;
        return { total: total, mastered: mastered, learning: learning, new: newCount };
    }

    function exportData() {
        var words = _load().words;
        var BOM = '﻿';
        var header = '중국어,병음,한국어,HSK,상태,저장일,출처';
        var rows = words.map(function(w) {
            var statusLabel = w.status === 'mastered' ? '완료' : w.status === 'learning' ? '학습 중' : '안 외움';
            var date = w.saved_at ? w.saved_at.split('T')[0] : '';
            var source = w.source && w.source.lesson_title ? w.source.lesson_title : '';
            return [w.zh, w.pinyin, w.ko, w.hsk ? 'HSK' + w.hsk : '', statusLabel, date, source]
                .map(function(v) { return '"' + String(v || '').replace(/"/g, '""') + '"'; }).join(',');
        });
        return BOM + header + '\n' + rows.join('\n');
    }

    function exportFilename() {
        var d = new Date();
        return 'dingdong-vocabulary-' + d.getFullYear() + ('0' + (d.getMonth()+1)).slice(-2) + ('0' + d.getDate()).slice(-2) + '.csv';
    }

    function importData(csvOrJson) {
        // JSON 호환 유지
        try {
            var d = JSON.parse(csvOrJson);
            if (d.words && Array.isArray(d.words)) {
                _save(d);
                return true;
            }
        } catch(e) {}
        // CSV 가져오기
        try {
            var lines = csvOrJson.replace(/^﻿/, '').split('\n').filter(function(l) { return l.trim(); });
            if (lines.length < 2) return false;
            var data = _load();
            for (var i = 1; i < lines.length; i++) {
                var cols = lines[i].match(/("([^"]|"")*"|[^,]*)/g);
                if (!cols || cols.length < 3) continue;
                var clean = cols.map(function(c) { return c.replace(/^"|"$/g, '').replace(/""/g, '"').trim(); });
                var zh = clean[0];
                if (!zh || data.words.some(function(w) { return w.zh === zh; })) continue;
                data.words.push({
                    id: _uuid(),
                    zh: zh,
                    pinyin: clean[1] || '',
                    ko: clean[2] || '',
                    hsk: parseInt((clean[3] || '').replace(/HSK/i, '')) || 0,
                    examples: [],
                    source: { lesson_id: 0, lesson_title: clean[6] || '' },
                    status: 'new',
                    saved_at: new Date().toISOString(),
                    last_reviewed: null
                });
            }
            _save(data);
            return true;
        } catch(e) {}
        return false;
    }

    return {
        add: add, remove: remove, has: has,
        getAll: getAll, getByLesson: getByLesson,
        updateStatus: updateStatus, getStats: getStats,
        exportData: exportData, exportFilename: exportFilename, importData: importData
    };
})();

/**
 * DDVocabUI — renders vocabulary tab panel content and standalone page
 */
var DDVocabUI = (function() {

    var HSK_LABELS = {
        1: 'HSK1 기초', 2: 'HSK2 기초', 3: 'HSK3 기초',
        4: 'HSK4 중급', 5: 'HSK5 중급',
        6: 'HSK6 고급', 7: 'HSK7 고급',
        8: 'HSK8 최고급', 9: 'HSK9 최고급'
    };

    var HSK_COLORS = {
        1: '#4CAF50', 2: '#66BB6A', 3: '#81C784',
        4: '#5B8CDB', 5: '#42A5F5',
        6: '#FF9800', 7: '#FFA726',
        8: '#E91E63', 9: '#C2185B'
    };

    function renderPanel(container, opts) {
        opts = opts || {};
        var words = opts.lessonId ? DDVocab.getByLesson(opts.lessonId) : DDVocab.getAll();
        container.innerHTML = '';

        // Mode selector
        var modes = el('div', 'dd-vocab-modes');
        modes.innerHTML =
            '<button class="dd-vocab-mode-btn is-active" data-mode="list">단어 목록</button>' +
            '<button class="dd-vocab-mode-btn" data-mode="flashcard">플래시카드</button>' +
            '<button class="dd-vocab-mode-btn" data-mode="game">미니 게임</button>';
        container.appendChild(modes);

        // Progress bar
        var stats = DDVocab.getStats();
        var progressWrap = el('div', 'dd-vocab-progress');
        if (stats.total > 0) {
            var mp = Math.round(stats.mastered / stats.total * 100);
            var lp = Math.round(stats.learning / stats.total * 100);
            progressWrap.innerHTML =
                '<div class="dd-vocab-progress-bar">' +
                '<div class="dd-vp-mastered" style="width:' + mp + '%"></div>' +
                '<div class="dd-vp-learning" style="width:' + lp + '%"></div>' +
                '</div>' +
                '<div class="dd-vocab-progress-labels">' +
                '<span class="dd-vp-label"><span class="dd-vp-dot" style="background:#4CAF50"></span>완료 ' + stats.mastered + '</span>' +
                '<span class="dd-vp-label"><span class="dd-vp-dot" style="background:#FF9800"></span>학습 중 ' + stats.learning + '</span>' +
                '<span class="dd-vp-label"><span class="dd-vp-dot" style="background:#E0E0E0"></span>안 외움 ' + stats.new + '</span>' +
                '</div>';
        }
        container.appendChild(progressWrap);

        // Content area
        var content = el('div', 'dd-vocab-content');
        container.appendChild(content);

        renderWordList(content, words, opts);

        modes.addEventListener('click', function(e) {
            var btn = e.target.closest('.dd-vocab-mode-btn');
            if (!btn) return;
            modes.querySelectorAll('.dd-vocab-mode-btn').forEach(function(b) { b.classList.remove('is-active'); });
            btn.classList.add('is-active');
            var mode = btn.dataset.mode;
            words = opts.lessonId ? DDVocab.getByLesson(opts.lessonId) : DDVocab.getAll();
            if (mode === 'list') renderWordList(content, words, opts);
            else if (mode === 'flashcard') renderFlashcards(content, words);
            else if (mode === 'game') renderGameMenu(content, words);
        });
    }

    // --- Word List ---
    function renderWordList(container, words, opts) {
        container.innerHTML = '';
        if (!words.length) {
            container.innerHTML = '<div class="dd-vocab-empty"><p>저장된 단어가 없습니다.</p><p style="font-size:0.85rem;color:#999;">핵심 표현 카드의 북마크 버튼을 눌러 단어를 저장하세요.</p></div>';
            return;
        }

        // Filters for standalone page
        if (opts && opts.showFilters) {
            var filters = el('div', 'dd-vocab-filters');
            var hskSet = {};
            var statusSet = { new: '안 외움', learning: '학습 중', mastered: '완료' };
            words.forEach(function(w) { if (w.hsk) hskSet[w.hsk] = 'HSK' + w.hsk; });

            var filterHTML = '<select class="dd-vocab-filter" id="dd-vf-hsk"><option value="">HSK 전체</option>';
            Object.keys(hskSet).sort().forEach(function(k) { filterHTML += '<option value="' + k + '">' + hskSet[k] + '</option>'; });
            filterHTML += '</select>';
            filterHTML += '<select class="dd-vocab-filter" id="dd-vf-status"><option value="">상태 전체</option>';
            Object.keys(statusSet).forEach(function(k) { filterHTML += '<option value="' + k + '">' + statusSet[k] + '</option>'; });
            filterHTML += '</select>';
            filters.innerHTML = filterHTML;
            container.appendChild(filters);

            filters.addEventListener('change', function() {
                var hsk = document.getElementById('dd-vf-hsk').value;
                var st = document.getElementById('dd-vf-status').value;
                var filtered = words.filter(function(w) {
                    if (hsk && w.hsk != hsk) return false;
                    if (st && w.status !== st) return false;
                    return true;
                });
                renderCards(grid, filtered);
            });
        }

        var grid = el('div', 'dd-vocab-grid');
        container.appendChild(grid);
        renderCards(grid, words);
    }

    function renderCards(grid, words) {
        grid.innerHTML = '';
        words.forEach(function(w) {
            var card = el('div', 'dd-vocab-card');
            card.style.cursor = 'pointer';
            var hskColor = HSK_COLORS[w.hsk] || '#999';
            var statusLabel = w.status === 'mastered' ? '완료' : w.status === 'learning' ? '학습 중' : '안 외움';
            var statusClass = 'dd-vs-' + w.status;

            card.innerHTML =
                (w.hsk ? '<span class="dd-vocab-hsk" style="background:' + hskColor + '">HSK' + w.hsk + '</span>' : '') +
                '<div class="dd-vocab-zh">' + esc(w.zh) + '</div>' +
                '<div class="dd-vocab-pinyin">' + esc(w.pinyin) + '</div>' +
                '<div class="dd-vocab-ko">' + esc(w.ko) + '</div>' +
                (w.examples && w.examples.length ? '<div class="dd-vocab-example"><div class="dd-vocab-ex-zh">' + esc(w.examples[0].zh) + '</div><div>' + esc(w.examples[0].ko) + '</div></div>' : '') +
                '<div class="dd-vocab-card-footer">' +
                '<span class="dd-vocab-status ' + statusClass + '">' + statusLabel + '</span>' +
                '<button class="dd-vocab-del" data-zh="' + esc(w.zh) + '" title="삭제">✕</button>' +
                '</div>';
            grid.appendChild(card);

            // 카드 클릭 → 상세 팝업 (삭제 버튼 제외)
            card.addEventListener('click', function(e) {
                if (e.target.closest('.dd-vocab-del')) return;
                showWordPopup(w);
            });
        });

        grid.querySelectorAll('.dd-vocab-del').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                DDVocab.remove(btn.dataset.zh);
                btn.closest('.dd-vocab-card').remove();
                if (!grid.children.length) {
                    grid.innerHTML = '<div class="dd-vocab-empty"><p>저장된 단어가 없습니다.</p></div>';
                }
            });
        });
    }

    // --- 단어 상세 팝업 ---
    var EXAMPLE_CACHE_KEY = 'dd_vocab_examples_cache';

    function getCachedExamples(zh) {
        try {
            var cache = JSON.parse(localStorage.getItem(EXAMPLE_CACHE_KEY) || '{}');
            return cache[zh] || null;
        } catch(e) { return null; }
    }

    function setCachedExamples(zh, data) {
        try {
            var cache = JSON.parse(localStorage.getItem(EXAMPLE_CACHE_KEY) || '{}');
            cache[zh] = data;
            localStorage.setItem(EXAMPLE_CACHE_KEY, JSON.stringify(cache));
        } catch(e) {}
    }

    function showWordPopup(w) {
        // 기존 팝업 제거
        var existing = document.querySelector('.dd-wp-overlay');
        if (existing) existing.remove();

        var hskColor = HSK_COLORS[w.hsk] || '#999';
        var overlay = el('div', 'dd-wp-overlay');
        overlay.innerHTML =
            '<div class="dd-wp-card">' +
                '<button class="dd-wp-close">&times;</button>' +
                '<div class="dd-wp-header" style="border-bottom-color:' + hskColor + '">' +
                    '<div class="dd-wp-zh">' + esc(w.zh) + '</div>' +
                    '<div class="dd-wp-pinyin">' + esc(w.pinyin) + '</div>' +
                    '<div class="dd-wp-ko">' + esc(w.ko) + '</div>' +
                    (w.hsk ? '<span class="dd-wp-hsk" style="background:' + hskColor + '">' + (HSK_LABELS[w.hsk] || 'HSK' + w.hsk) + '</span>' : '') +
                '</div>' +
                '<div class="dd-wp-tabs">' +
                    '<button class="dd-wp-tab is-active" data-level="beginner">초급</button>' +
                    '<button class="dd-wp-tab" data-level="intermediate">중급</button>' +
                    '<button class="dd-wp-tab" data-level="advanced">고급</button>' +
                '</div>' +
                '<div class="dd-wp-examples" id="dd-wp-examples">' +
                    '<div class="dd-wp-loading"><div class="dd-wp-spinner"></div><p>AI가 예문을 생성하고 있어요...</p></div>' +
                '</div>' +
                '<div class="dd-wp-source">' + (w.source && w.source.lesson_title ? '출처: ' + esc(w.source.lesson_title) : '') + '</div>' +
            '</div>';

        document.body.appendChild(overlay);
        requestAnimationFrame(function() { overlay.classList.add('is-visible'); });

        // 닫기
        overlay.querySelector('.dd-wp-close').addEventListener('click', closePopup);
        overlay.addEventListener('click', function(e) { if (e.target === overlay) closePopup(); });

        function closePopup() {
            overlay.classList.remove('is-visible');
            setTimeout(function() { overlay.remove(); }, 300);
        }

        // 레벨 탭
        var currentLevel = 'beginner';
        var examplesData = null;

        overlay.querySelectorAll('.dd-wp-tab').forEach(function(tab) {
            tab.addEventListener('click', function() {
                overlay.querySelectorAll('.dd-wp-tab').forEach(function(t) { t.classList.remove('is-active'); });
                tab.classList.add('is-active');
                currentLevel = tab.dataset.level;
                if (examplesData) renderExamples(examplesData, currentLevel);
            });
        });

        // 캐시 확인 후 생성
        var cached = getCachedExamples(w.zh);
        if (cached) {
            examplesData = cached;
            renderExamples(cached, currentLevel);
        } else {
            generateExamples(w, function(data) {
                examplesData = data;
                setCachedExamples(w.zh, data);
                renderExamples(data, currentLevel);
            });
        }
    }

    function renderExamples(data, level) {
        var container = document.getElementById('dd-wp-examples');
        if (!container) return;
        var items = data[level] || [];
        if (!items.length) {
            container.innerHTML = '<p class="dd-wp-no-ex">예문을 불러올 수 없습니다.</p>';
            return;
        }

        var levelLabels = { beginner: '초급', intermediate: '중급', advanced: '고급' };
        var levelColors = { beginner: '#4CAF50', intermediate: '#FF9800', advanced: '#E53935' };

        var html = '';
        if (data.explanation) {
            html += '<div class="dd-wp-explanation">' + esc(data.explanation) + '</div>';
        }
        items.forEach(function(ex, i) {
            html += '<div class="dd-wp-ex-card">' +
                '<span class="dd-wp-ex-num" style="background:' + levelColors[level] + '">' + (i + 1) + '</span>' +
                '<div class="dd-wp-ex-zh">' + esc(ex.zh) + '</div>' +
                '<div class="dd-wp-ex-pinyin">' + esc(ex.pinyin) + '</div>' +
                '<div class="dd-wp-ex-ko">' + esc(ex.ko) + '</div>' +
                '</div>';
        });
        container.innerHTML = html;
    }

    function generateExamples(word, callback) {
        var apiKey = null;
        if (typeof DDApiKeyManager !== 'undefined') {
            apiKey = DDApiKeyManager.getKey();
        }
        if (!apiKey) {
            // API 키 없으면 기본 예문만 표시
            var fallback = { explanation: word.ko + ' (' + word.pinyin + ')', beginner: [], intermediate: [], advanced: [] };
            if (word.examples && word.examples.length) {
                word.examples.forEach(function(ex) {
                    fallback.beginner.push({ zh: ex.zh || '', pinyin: ex.pinyin || '', ko: ex.ko || '' });
                });
            }
            callback(fallback);
            return;
        }

        var prompt = '중국어 단어 "' + word.zh + '" (' + word.pinyin + ', 뜻: ' + word.ko + ')에 대해 다음을 JSON으로 생성해주세요:\n' +
            '1. explanation: 이 단어의 상세 설명 (한국어, 2-3문장, 용법/뉘앙스 포함)\n' +
            '2. beginner: 초급 예문 3개 (HSK 1~3급 수준, 짧고 단순한 문장)\n' +
            '3. intermediate: 중급 예문 3개 (HSK 4~6급 수준, 복합문)\n' +
            '4. advanced: 고급 예문 3개 (HSK 7~9급 수준, 성어/관용어 포함 가능)\n' +
            '각 예문은 어휘와 문법을 해당 HSK 급수 수준에 맞추고, {zh, pinyin, ko} 형태로 작성.\n' +
            'JSON만 출력하세요.';

        var model = 'gemini-2.5-flash';
        // API 키는 URL 쿼리스트링이 아니라 x-goog-api-key 헤더로 보낸다.
        // ?key= 로 붙이면 브라우저 방문기록·Referer·중계 서버 로그에 키가 그대로 남는다.
        var url = 'https://generativelanguage.googleapis.com/v1beta/models/' + model + ':generateContent';

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'x-goog-api-key': apiKey
            },
            body: JSON.stringify({
                contents: [{ parts: [{ text: prompt }] }],
                generationConfig: {
                    responseMimeType: 'application/json',
                    responseSchema: {
                        type: 'OBJECT',
                        properties: {
                            explanation: { type: 'STRING' },
                            beginner: { type: 'ARRAY', items: { type: 'OBJECT', properties: { zh: { type: 'STRING' }, pinyin: { type: 'STRING' }, ko: { type: 'STRING' } }, required: ['zh', 'pinyin', 'ko'] } },
                            intermediate: { type: 'ARRAY', items: { type: 'OBJECT', properties: { zh: { type: 'STRING' }, pinyin: { type: 'STRING' }, ko: { type: 'STRING' } }, required: ['zh', 'pinyin', 'ko'] } },
                            advanced: { type: 'ARRAY', items: { type: 'OBJECT', properties: { zh: { type: 'STRING' }, pinyin: { type: 'STRING' }, ko: { type: 'STRING' } }, required: ['zh', 'pinyin', 'ko'] } }
                        },
                        required: ['explanation', 'beginner', 'intermediate', 'advanced']
                    }
                }
            })
        }).then(function(res) { return res.json(); })
        .then(function(json) {
            try {
                var text = json.candidates[0].content.parts[0].text;
                var data = JSON.parse(text);
                callback(data);
            } catch(e) {
                callback({ explanation: word.ko, beginner: [], intermediate: [], advanced: [] });
            }
        }).catch(function() {
            callback({ explanation: word.ko, beginner: [], intermediate: [], advanced: [] });
        });
    }

    // --- Flashcards ---
    function renderFlashcards(container, words) {
        container.innerHTML = '';
        if (words.length < 1) {
            container.innerHTML = '<div class="dd-vocab-empty"><p>플래시카드를 사용하려면 단어를 먼저 저장하세요.</p></div>';
            return;
        }

        var idx = 0;
        var flipped = false;

        var wrap = el('div', 'dd-fc-wrap');
        var cardOuter = el('div', 'dd-fc-card-outer');
        var card = el('div', 'dd-fc-card');
        var front = el('div', 'dd-fc-front');
        var back = el('div', 'dd-fc-back');
        card.appendChild(front);
        card.appendChild(back);
        cardOuter.appendChild(card);
        wrap.appendChild(cardOuter);

        var counter = el('div', 'dd-fc-counter');
        wrap.appendChild(counter);

        var nav = el('div', 'dd-fc-nav');
        nav.innerHTML =
            '<button class="dd-fc-btn" id="dd-fc-prev">◀ 이전</button>' +
            '<button class="dd-fc-btn dd-fc-flip" id="dd-fc-flip">뒤집기</button>' +
            '<button class="dd-fc-btn" id="dd-fc-next">다음 ▶</button>';
        wrap.appendChild(nav);

        var status = el('div', 'dd-fc-status');
        status.innerHTML =
            '<button class="dd-fc-st-btn dd-fcs-new" data-st="new">모르겠어요</button>' +
            '<button class="dd-fc-st-btn dd-fcs-learning" data-st="learning">학습 중</button>' +
            '<button class="dd-fc-st-btn dd-fcs-mastered" data-st="mastered">완료!</button>';
        wrap.appendChild(status);

        container.appendChild(wrap);

        function show() {
            var w = words[idx];
            flipped = false;
            card.classList.remove('is-flipped');
            front.innerHTML = '<div class="dd-fc-zh">' + esc(w.zh) + '</div>' +
                (w.hsk ? '<span class="dd-fc-hsk" style="background:' + (HSK_COLORS[w.hsk] || '#999') + '">HSK' + w.hsk + '</span>' : '');
            back.innerHTML = '<div class="dd-fc-pinyin">' + esc(w.pinyin) + '</div>' +
                '<div class="dd-fc-ko">' + esc(w.ko) + '</div>' +
                (w.examples && w.examples[0] ? '<div class="dd-fc-example"><div>' + esc(w.examples[0].zh) + '</div><div>' + esc(w.examples[0].ko) + '</div></div>' : '');
            counter.textContent = (idx + 1) + ' / ' + words.length;
        }

        cardOuter.addEventListener('click', function() {
            flipped = !flipped;
            card.classList.toggle('is-flipped');
        });

        document.getElementById('dd-fc-flip').addEventListener('click', function() {
            flipped = !flipped;
            card.classList.toggle('is-flipped');
        });

        document.getElementById('dd-fc-prev').addEventListener('click', function() {
            idx = (idx - 1 + words.length) % words.length;
            show();
        });

        document.getElementById('dd-fc-next').addEventListener('click', function() {
            idx = (idx + 1) % words.length;
            show();
        });

        status.addEventListener('click', function(e) {
            var btn = e.target.closest('.dd-fc-st-btn');
            if (!btn) return;
            DDVocab.updateStatus(words[idx].zh, btn.dataset.st);
            words[idx].status = btn.dataset.st;
            idx = (idx + 1) % words.length;
            show();
        });

        show();
    }

    // --- Game Menu ---
    function renderGameMenu(container, words) {
        container.innerHTML = '';
        if (words.length < 4) {
            container.innerHTML = '<div class="dd-vocab-empty"><p>미니 게임을 하려면 최소 4개 단어가 필요합니다.</p><p style="font-size:0.85rem;color:#999;">현재 ' + words.length + '개 저장됨</p></div>';
            return;
        }

        var menu = el('div', 'dd-game-menu');
        menu.innerHTML =
            '<h3 class="dd-game-title">미니 게임</h3>' +
            '<div class="dd-game-grid">' +
            '<button class="dd-game-card" data-game="match"><span class="dd-game-icon">🎯</span><span class="dd-game-name">단어 맞추기</span><span class="dd-game-desc">중국어를 보고 뜻을 골라보세요</span></button>' +
            '<button class="dd-game-card" data-game="order"><span class="dd-game-icon">🔤</span><span class="dd-game-name">단어 배열</span><span class="dd-game-desc">글자를 올바른 순서로 배열하세요</span></button>' +
            '<button class="dd-game-card" data-game="fill"><span class="dd-game-icon">📝</span><span class="dd-game-name">빈칸 채우기</span><span class="dd-game-desc">문장 속 빈칸에 들어갈 단어는?</span></button>' +
            '<button class="dd-game-card" data-game="connect"><span class="dd-game-icon">🔗</span><span class="dd-game-name">뜻 연결</span><span class="dd-game-desc">중국어와 한국어 뜻을 연결하세요</span></button>' +
            '</div>';
        container.appendChild(menu);

        menu.addEventListener('click', function(e) {
            var btn = e.target.closest('.dd-game-card');
            if (!btn) return;
            var game = btn.dataset.game;
            if (game === 'match') gameMatch(container, words);
            else if (game === 'order') gameOrder(container, words);
            else if (game === 'fill') gameFill(container, words);
            else if (game === 'connect') gameConnect(container, words);
        });
    }

    // --- Game 1: 단어 맞추기 (4-choice quiz) ---
    function gameMatch(container, words) {
        container.innerHTML = '';
        var shuffled = shuffle(words.slice());
        var qi = 0, score = 0, total = Math.min(shuffled.length, 10);

        var wrap = el('div', 'dd-game-wrap');
        container.appendChild(wrap);

        function showQ() {
            if (qi >= total) {
                wrap.innerHTML = '<div class="dd-game-result"><h3>결과</h3><p class="dd-game-score">' + score + ' / ' + total + '</p><p>' + (score === total ? '완벽합니다!' : score >= total * 0.7 ? '잘했어요!' : '더 연습해보세요!') + '</p><button class="dd-game-retry">다시 하기</button><button class="dd-game-back">게임 목록</button></div>';
                wrap.querySelector('.dd-game-retry').addEventListener('click', function() { gameMatch(container, words); });
                wrap.querySelector('.dd-game-back').addEventListener('click', function() { renderGameMenu(container, words); });
                return;
            }

            var correct = shuffled[qi];
            var options = [correct.ko];
            var pool = words.filter(function(w) { return w.zh !== correct.zh; });
            pool = shuffle(pool);
            for (var i = 0; i < 3 && i < pool.length; i++) options.push(pool[i].ko);
            options = shuffle(options);

            wrap.innerHTML =
                '<div class="dd-game-header"><span>' + (qi + 1) + ' / ' + total + '</span><span>점수: ' + score + '</span></div>' +
                '<div class="dd-game-question"><div class="dd-game-q-zh">' + esc(correct.zh) + '</div>' +
                (correct.pinyin ? '<div class="dd-game-q-pinyin">' + esc(correct.pinyin) + '</div>' : '') + '</div>' +
                '<div class="dd-game-options">' +
                options.map(function(o) { return '<button class="dd-game-opt" data-val="' + esc(o) + '">' + esc(o) + '</button>'; }).join('') +
                '</div>';

            wrap.querySelectorAll('.dd-game-opt').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var isCorrect = btn.dataset.val === correct.ko;
                    if (isCorrect) { score++; btn.classList.add('dd-go-correct'); }
                    else {
                        btn.classList.add('dd-go-wrong');
                        wrap.querySelectorAll('.dd-game-opt').forEach(function(b) {
                            if (b.dataset.val === correct.ko) b.classList.add('dd-go-correct');
                        });
                    }
                    wrap.querySelectorAll('.dd-game-opt').forEach(function(b) { b.disabled = true; });
                    DDVocab.updateStatus(correct.zh, isCorrect ? 'learning' : 'new');
                    setTimeout(function() { qi++; showQ(); }, 1000);
                });
            });
        }

        showQ();
    }

    // --- Game 2: 단어 배열 (character ordering) ---
    function gameOrder(container, words) {
        container.innerHTML = '';
        var eligible = words.filter(function(w) { return w.zh && w.zh.length >= 2 && w.zh.length <= 6; });
        if (eligible.length < 1) { container.innerHTML = '<div class="dd-vocab-empty"><p>배열 게임에 적합한 단어가 없습니다.</p><button class="dd-game-back">게임 목록</button></div>'; container.querySelector('.dd-game-back').addEventListener('click', function() { renderGameMenu(container, words); }); return; }

        var shuffled = shuffle(eligible.slice());
        var qi = 0, score = 0, total = Math.min(shuffled.length, 8);
        var wrap = el('div', 'dd-game-wrap');
        container.appendChild(wrap);

        function showQ() {
            if (qi >= total) {
                wrap.innerHTML = '<div class="dd-game-result"><h3>결과</h3><p class="dd-game-score">' + score + ' / ' + total + '</p><button class="dd-game-retry">다시 하기</button><button class="dd-game-back">게임 목록</button></div>';
                wrap.querySelector('.dd-game-retry').addEventListener('click', function() { gameOrder(container, words); });
                wrap.querySelector('.dd-game-back').addEventListener('click', function() { renderGameMenu(container, words); });
                return;
            }

            var w = shuffled[qi];
            var chars = w.zh.split('');
            var scrambled = shuffle(chars.slice());
            while (scrambled.join('') === w.zh && chars.length > 1) scrambled = shuffle(chars.slice());
            var selected = [];

            wrap.innerHTML =
                '<div class="dd-game-header"><span>' + (qi + 1) + ' / ' + total + '</span><span>점수: ' + score + '</span></div>' +
                '<div class="dd-game-question"><div class="dd-game-q-hint">' + esc(w.ko) + '</div>' +
                (w.pinyin ? '<div class="dd-game-q-pinyin">' + esc(w.pinyin) + '</div>' : '') + '</div>' +
                '<div class="dd-go-answer" id="dd-go-answer"></div>' +
                '<div class="dd-go-bank" id="dd-go-bank">' +
                scrambled.map(function(c, i) { return '<button class="dd-go-char" data-idx="' + i + '">' + esc(c) + '</button>'; }).join('') +
                '</div>' +
                '<button class="dd-go-check dd-hidden" id="dd-go-check">확인</button>';

            var answerEl = document.getElementById('dd-go-answer');
            var bankEl = document.getElementById('dd-go-bank');
            var checkBtn = document.getElementById('dd-go-check');

            bankEl.querySelectorAll('.dd-go-char').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    if (btn.disabled) return;
                    selected.push({ char: scrambled[btn.dataset.idx], idx: btn.dataset.idx });
                    btn.disabled = true;
                    btn.classList.add('dd-go-used');
                    renderAnswer();
                });
            });

            function renderAnswer() {
                answerEl.innerHTML = selected.map(function(s, i) {
                    return '<span class="dd-go-placed" data-i="' + i + '">' + esc(s.char) + '</span>';
                }).join('');

                answerEl.querySelectorAll('.dd-go-placed').forEach(function(sp) {
                    sp.addEventListener('click', function() {
                        var i = parseInt(sp.dataset.i);
                        var removed = selected.splice(i, 1)[0];
                        bankEl.querySelector('[data-idx="' + removed.idx + '"]').disabled = false;
                        bankEl.querySelector('[data-idx="' + removed.idx + '"]').classList.remove('dd-go-used');
                        renderAnswer();
                    });
                });

                if (selected.length === chars.length) checkBtn.classList.remove('dd-hidden');
                else checkBtn.classList.add('dd-hidden');
            }

            checkBtn.addEventListener('click', function() {
                var answer = selected.map(function(s) { return s.char; }).join('');
                var correct = answer === w.zh;
                if (correct) { score++; answerEl.classList.add('dd-go-correct-answer'); }
                else {
                    answerEl.classList.add('dd-go-wrong-answer');
                    answerEl.innerHTML += '<div class="dd-go-correct-text">정답: ' + esc(w.zh) + '</div>';
                }
                DDVocab.updateStatus(w.zh, correct ? 'learning' : 'new');
                setTimeout(function() { qi++; showQ(); }, 1200);
            });
        }

        showQ();
    }

    // --- Game 3: 빈칸 채우기 (fill-in-blank) ---
    function gameFill(container, words) {
        container.innerHTML = '';
        var eligible = words.filter(function(w) { return w.examples && w.examples.length > 0 && w.examples[0].zh; });
        if (eligible.length < 1) {
            container.innerHTML = '<div class="dd-vocab-empty"><p>예문이 있는 단어가 필요합니다.</p><button class="dd-game-back">게임 목록</button></div>';
            container.querySelector('.dd-game-back').addEventListener('click', function() { renderGameMenu(container, words); });
            return;
        }

        var shuffled = shuffle(eligible.slice());
        var qi = 0, score = 0, total = Math.min(shuffled.length, 8);
        var wrap = el('div', 'dd-game-wrap');
        container.appendChild(wrap);

        function showQ() {
            if (qi >= total) {
                wrap.innerHTML = '<div class="dd-game-result"><h3>결과</h3><p class="dd-game-score">' + score + ' / ' + total + '</p><button class="dd-game-retry">다시 하기</button><button class="dd-game-back">게임 목록</button></div>';
                wrap.querySelector('.dd-game-retry').addEventListener('click', function() { gameFill(container, words); });
                wrap.querySelector('.dd-game-back').addEventListener('click', function() { renderGameMenu(container, words); });
                return;
            }

            var w = shuffled[qi];
            var sentence = w.examples[0].zh;
            var blanked = sentence.replace(w.zh, '______');

            wrap.innerHTML =
                '<div class="dd-game-header"><span>' + (qi + 1) + ' / ' + total + '</span><span>점수: ' + score + '</span></div>' +
                '<div class="dd-game-question"><div class="dd-game-q-sentence">' + esc(blanked) + '</div>' +
                '<div class="dd-game-q-hint" style="margin-top:0.5rem;">' + esc(w.examples[0].ko) + '</div></div>' +
                '<div class="dd-gf-input-wrap"><input type="text" class="dd-gf-input" id="dd-gf-input" placeholder="빈칸에 들어갈 단어를 입력하세요" autocomplete="off"><button class="dd-gf-submit" id="dd-gf-submit">확인</button></div>' +
                '<div class="dd-gf-feedback dd-hidden" id="dd-gf-feedback"></div>';

            var input = document.getElementById('dd-gf-input');
            var submitBtn = document.getElementById('dd-gf-submit');
            var feedback = document.getElementById('dd-gf-feedback');

            function check() {
                var answer = input.value.trim();
                var correct = answer === w.zh;
                feedback.classList.remove('dd-hidden');
                if (correct) {
                    score++;
                    feedback.className = 'dd-gf-feedback dd-gf-correct';
                    feedback.textContent = '정답! ' + w.zh + ' (' + w.pinyin + ') = ' + w.ko;
                } else {
                    feedback.className = 'dd-gf-feedback dd-gf-wrong';
                    feedback.textContent = '오답! 정답: ' + w.zh + ' (' + w.pinyin + ')';
                }
                input.disabled = true;
                submitBtn.disabled = true;
                DDVocab.updateStatus(w.zh, correct ? 'learning' : 'new');
                setTimeout(function() { qi++; showQ(); }, 1500);
            }

            submitBtn.addEventListener('click', check);
            input.addEventListener('keydown', function(e) { if (e.key === 'Enter') check(); });
            input.focus();
        }

        showQ();
    }

    // --- Game 4: 뜻 연결 (match pairs) ---
    function gameConnect(container, words) {
        container.innerHTML = '';
        var pool = shuffle(words.slice()).slice(0, 4);
        if (pool.length < 4) pool = shuffle(words.slice()).slice(0, words.length);

        var wrap = el('div', 'dd-game-wrap');
        var zhCol = shuffle(pool.slice());
        var koCol = shuffle(pool.slice());
        var matched = 0;
        var selectedZh = null;

        wrap.innerHTML =
            '<div class="dd-game-header"><span>뜻 연결</span><span id="dd-gc-matched">0 / ' + pool.length + '</span></div>' +
            '<div class="dd-gc-board">' +
            '<div class="dd-gc-col" id="dd-gc-zh">' +
            zhCol.map(function(w) { return '<button class="dd-gc-item dd-gc-zh-item" data-zh="' + esc(w.zh) + '">' + esc(w.zh) + '</button>'; }).join('') +
            '</div>' +
            '<div class="dd-gc-col" id="dd-gc-ko">' +
            koCol.map(function(w) { return '<button class="dd-gc-item dd-gc-ko-item" data-zh="' + esc(w.zh) + '">' + esc(w.ko) + '</button>'; }).join('') +
            '</div>' +
            '</div>' +
            '<button class="dd-game-back" style="margin-top:1rem;">게임 목록</button>';

        container.appendChild(wrap);

        wrap.querySelector('.dd-game-back').addEventListener('click', function() { renderGameMenu(container, words); });

        wrap.querySelectorAll('.dd-gc-zh-item').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (btn.disabled) return;
                wrap.querySelectorAll('.dd-gc-zh-item').forEach(function(b) { b.classList.remove('dd-gc-selected'); });
                btn.classList.add('dd-gc-selected');
                selectedZh = btn.dataset.zh;
            });
        });

        wrap.querySelectorAll('.dd-gc-ko-item').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (!selectedZh || btn.disabled) return;
                if (btn.dataset.zh === selectedZh) {
                    matched++;
                    btn.disabled = true;
                    btn.classList.add('dd-gc-matched');
                    var zhBtn = wrap.querySelector('.dd-gc-zh-item[data-zh="' + CSS.escape(selectedZh) + '"]');
                    if (zhBtn) { zhBtn.disabled = true; zhBtn.classList.add('dd-gc-matched'); zhBtn.classList.remove('dd-gc-selected'); }
                    DDVocab.updateStatus(selectedZh, 'learning');
                    document.getElementById('dd-gc-matched').textContent = matched + ' / ' + pool.length;
                    selectedZh = null;
                    if (matched === pool.length) {
                        setTimeout(function() {
                            wrap.innerHTML = '<div class="dd-game-result"><h3>완료!</h3><p>모든 단어를 연결했습니다!</p><button class="dd-game-retry">다시 하기</button><button class="dd-game-back">게임 목록</button></div>';
                            wrap.querySelector('.dd-game-retry').addEventListener('click', function() { gameConnect(container, words); });
                            wrap.querySelector('.dd-game-back').addEventListener('click', function() { renderGameMenu(container, words); });
                        }, 500);
                    }
                } else {
                    btn.classList.add('dd-gc-wrong');
                    setTimeout(function() { btn.classList.remove('dd-gc-wrong'); }, 600);
                    var zhBtn = wrap.querySelector('.dd-gc-zh-item.dd-gc-selected');
                    if (zhBtn) zhBtn.classList.remove('dd-gc-selected');
                    selectedZh = null;
                }
            });
        });
    }

    // --- Helpers ---
    function el(tag, cls) {
        var e = document.createElement(tag);
        if (cls) e.className = cls;
        return e;
    }

    function esc(str) {
        if (!str) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(str));
        return d.innerHTML;
    }

    function shuffle(arr) {
        for (var i = arr.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var t = arr[i]; arr[i] = arr[j]; arr[j] = t;
        }
        return arr;
    }

    return { renderPanel: renderPanel, showWordPopup: showWordPopup };
})();
