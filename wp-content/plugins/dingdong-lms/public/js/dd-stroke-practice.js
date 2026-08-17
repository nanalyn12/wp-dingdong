/**
 * DD Stroke Practice & Idiom Tab (한자 따라쓰기 + 고사성어)
 *
 * Canvas 기반 한자 필기 연습 + 고사성어(成语) 학습.
 * 새 탭 "한자"로 독립 운영.
 *
 * 기능:
 *  1) 핵심표현 한자 개별 연습 (Canvas 필기)
 *  2) 고사성어 카드 (Gemini로 레슨 주제 연관 성어 생성 + 캐시)
 *  3) 획순 가이드 (Gemini → JSON 캐시)
 *  4) 별점 평가 → DDVocab, DDSRS 연동
 *
 * localStorage:
 *   dd_stroke_data — 획순 데이터 캐시
 *   dd_stroke_progress — 연습 기록 (별점, 시도 횟수)
 *   dd_idioms_cache — 고사성어 캐시
 */
(function() {
    'use strict';

    var STROKE_CACHE_KEY = 'dd_stroke_data';
    var PROGRESS_KEY = 'dd_stroke_progress';
    var IDIOMS_CACHE_KEY = 'dd_idioms_cache';

    /* ─── localStorage helpers ─── */
    function loadCache(key) {
        try { return JSON.parse(localStorage.getItem(key)) || {}; }
        catch (e) { return {}; }
    }
    function saveCache(key, data) {
        localStorage.setItem(key, JSON.stringify(data));
    }

    function escapeHtml(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    /* ─── Canvas Stroke Practice ─── */
    function openStrokePractice(char, pinyin, ko) {
        // 기존 모달 제거
        var existing = document.getElementById('dd-sp-modal');
        if (existing) existing.remove();

        var overlay = document.createElement('div');
        overlay.id = 'dd-sp-modal';
        overlay.className = 'dd-sp-overlay';

        var modal = document.createElement('div');
        modal.className = 'dd-sp-modal';

        // Header
        var header = document.createElement('div');
        header.className = 'dd-sp-header';
        header.innerHTML = '<div class="dd-sp-char-info">'
            + '<span class="dd-sp-target-char">' + escapeHtml(char) + '</span>'
            + '<span class="dd-sp-target-pinyin">' + escapeHtml(pinyin) + '</span>'
            + '<span class="dd-sp-target-ko">' + escapeHtml(ko) + '</span>'
            + '</div>'
            + '<button class="dd-sp-close" id="dd-sp-close">&times;</button>';

        // Canvas area
        var canvasWrap = document.createElement('div');
        canvasWrap.className = 'dd-sp-canvas-wrap';

        var canvas = document.createElement('canvas');
        canvas.className = 'dd-sp-canvas';
        canvas.width = 300;
        canvas.height = 300;

        // Guide character (background)
        var guide = document.createElement('div');
        guide.className = 'dd-sp-guide';
        guide.textContent = char;

        canvasWrap.appendChild(guide);
        canvasWrap.appendChild(canvas);

        // Controls
        var controls = document.createElement('div');
        controls.className = 'dd-sp-controls';
        controls.innerHTML = '<button class="dd-sp-btn dd-sp-clear" id="dd-sp-clear">🗑️ 지우기</button>'
            + '<button class="dd-sp-btn dd-sp-hint" id="dd-sp-hint">💡 힌트</button>'
            + '<button class="dd-sp-btn dd-sp-submit" id="dd-sp-submit">✅ 평가</button>';

        // Progress (별점 표시)
        var progress = loadCache(PROGRESS_KEY);
        var charProgress = progress[char] || { bestScore: 0, attempts: 0 };
        var progressEl = document.createElement('div');
        progressEl.className = 'dd-sp-progress';
        progressEl.id = 'dd-sp-progress';
        progressEl.innerHTML = renderStars(charProgress.bestScore)
            + '<span class="dd-sp-attempts">시도 ' + charProgress.attempts + '회</span>';

        modal.appendChild(header);
        modal.appendChild(canvasWrap);
        modal.appendChild(controls);
        modal.appendChild(progressEl);
        overlay.appendChild(modal);
        document.body.appendChild(overlay);

        // Canvas drawing
        var ctx = canvas.getContext('2d');
        var isDrawing = false;
        var strokes = []; // 현재 그리기의 모든 경로
        var currentStroke = [];

        ctx.lineWidth = 6;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#333';

        function getPos(e) {
            var rect = canvas.getBoundingClientRect();
            var scaleX = canvas.width / rect.width;
            var scaleY = canvas.height / rect.height;
            if (e.touches) {
                return { x: (e.touches[0].clientX - rect.left) * scaleX,
                         y: (e.touches[0].clientY - rect.top) * scaleY };
            }
            return { x: (e.clientX - rect.left) * scaleX,
                     y: (e.clientY - rect.top) * scaleY };
        }

        function onStart(e) {
            e.preventDefault();
            isDrawing = true;
            var pos = getPos(e);
            currentStroke = [pos];
            ctx.beginPath();
            ctx.moveTo(pos.x, pos.y);
        }

        function onMove(e) {
            if (!isDrawing) return;
            e.preventDefault();
            var pos = getPos(e);
            currentStroke.push(pos);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
        }

        function onEnd(e) {
            if (!isDrawing) return;
            isDrawing = false;
            if (currentStroke.length > 1) {
                strokes.push(currentStroke.slice());
            }
            currentStroke = [];
        }

        canvas.addEventListener('mousedown', onStart);
        canvas.addEventListener('mousemove', onMove);
        canvas.addEventListener('mouseup', onEnd);
        canvas.addEventListener('mouseleave', onEnd);
        canvas.addEventListener('touchstart', onStart, { passive: false });
        canvas.addEventListener('touchmove', onMove, { passive: false });
        canvas.addEventListener('touchend', onEnd);

        // Clear
        document.getElementById('dd-sp-clear').addEventListener('click', function() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            strokes = [];
        });

        // Hint: show guide animation
        document.getElementById('dd-sp-hint').addEventListener('click', function() {
            guide.classList.add('dd-sp-guide-flash');
            setTimeout(function() { guide.classList.remove('dd-sp-guide-flash'); }, 1500);
        });

        // Submit: evaluate
        document.getElementById('dd-sp-submit').addEventListener('click', function() {
            evaluateStroke(char, strokes, progressEl);
        });

        // Close
        document.getElementById('dd-sp-close').addEventListener('click', function() {
            overlay.remove();
        });
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) overlay.remove();
        });
    }

    function evaluateStroke(char, strokes, progressEl) {
        // 획 수 기반 간단 평가 (실제 획순 데이터 없이도 작동)
        // 일반적인 한자 획수: 한 글자당 평균 8-12획
        var strokeCount = strokes.length;
        var score = 0;

        if (strokeCount === 0) {
            score = 0;
        } else if (strokeCount >= 2) {
            // 획을 그렸으면 기본 1점, 적절한 수의 획이면 2-3점
            score = 1;
            // 각 획의 길이/방향 다양성 체크
            var totalLength = 0;
            var directions = {};
            strokes.forEach(function(stroke) {
                if (stroke.length < 2) return;
                var dx = stroke[stroke.length-1].x - stroke[0].x;
                var dy = stroke[stroke.length-1].y - stroke[0].y;
                var len = Math.sqrt(dx*dx + dy*dy);
                totalLength += len;
                // 8방향 분류
                var angle = Math.atan2(dy, dx);
                var dir = Math.round(angle / (Math.PI/4));
                directions[dir] = true;
            });
            var dirCount = Object.keys(directions).length;
            if (dirCount >= 2 && totalLength > 200) score = 2;
            if (dirCount >= 3 && strokeCount >= 3 && totalLength > 400) score = 3;
        }

        // 진행 기록
        var progress = loadCache(PROGRESS_KEY);
        if (!progress[char]) progress[char] = { bestScore: 0, attempts: 0 };
        progress[char].attempts++;
        if (score > progress[char].bestScore) progress[char].bestScore = score;
        saveCache(PROGRESS_KEY, progress);

        // UI 업데이트
        progressEl.innerHTML = renderStars(score)
            + '<span class="dd-sp-attempts">시도 ' + progress[char].attempts + '회</span>'
            + '<span class="dd-sp-score-msg">' + (score >= 3 ? '🎉 완벽!' : score >= 2 ? '👍 좋아요!' : score >= 1 ? '💪 더 연습!' : '그려보세요!') + '</span>';

        // SRS 피드백
        if (window.DDSRS) {
            var quality = score >= 3 ? 5 : score >= 2 ? 4 : score >= 1 ? 3 : 1;
            DDSRS.recordReview(char, quality, 'stroke');
        }
    }

    function renderStars(score) {
        var html = '<span class="dd-sp-stars">';
        for (var i = 1; i <= 3; i++) {
            html += '<span class="dd-sp-star' + (i <= score ? ' is-filled' : '') + '">★</span>';
        }
        html += '</span>';
        return html;
    }

    /* ─── 고사성어 (Idiom) Cards ─── */
    function loadIdiomsForLesson(lessonId, lessonTitle, level, callback) {
        var cache = loadCache(IDIOMS_CACHE_KEY);
        if (cache[lessonId] && cache[lessonId].idioms) {
            callback(cache[lessonId].idioms);
            return;
        }

        // Gemini로 생성
        var apiKey = '';
        if (window.DDApiKeyManager) apiKey = DDApiKeyManager.getKey();
        if (!apiKey) {
            callback(getDefaultIdioms());
            return;
        }

        var prompt = '한국인 중국어 학습자를 위한 고사성어 4개를 생성하세요.\n'
            + '강의 주제: ' + lessonTitle + '\n'
            + '난이도: ' + level + '\n\n'
            + 'JSON만 출력:\n'
            + '[{"idiom":"四字成语","pinyin":"병음","meaning":"한국어 뜻","story":"유래 이야기 2-3문장 (한국어)","example":{"zh":"예문 (중국어)","ko":"예문 번역 (한국어)"},"difficulty":"easy|medium|hard"}]';

        var body = {
            contents: [{ parts: [{ text: prompt }] }],
            generationConfig: { temperature: 0.5, maxOutputTokens: 2048, responseMimeType: 'application/json' }
        };

        // API 키는 URL 쿼리스트링이 아니라 x-goog-api-key 헤더로 보낸다.
        // ?key= 로 붙이면 브라우저 방문기록·Referer·중계 서버 로그에 키가 그대로 남는다.
        var url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent';

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'x-goog-api-key': apiKey
            },
            body: JSON.stringify(body)
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            var text = data.candidates && data.candidates[0] && data.candidates[0].content &&
                       data.candidates[0].content.parts && data.candidates[0].content.parts[0] &&
                       data.candidates[0].content.parts[0].text;
            if (!text) throw new Error('empty');
            var parsed = JSON.parse(text.replace(/```json?\s*/g, '').replace(/```/g, '').trim());
            if (Array.isArray(parsed) && parsed.length > 0) {
                cache[lessonId] = { idioms: parsed, timestamp: Date.now() };
                saveCache(IDIOMS_CACHE_KEY, cache);
                callback(parsed);
            } else {
                callback(getDefaultIdioms());
            }
        })
        .catch(function() {
            callback(getDefaultIdioms());
        });
    }

    function getDefaultIdioms() {
        return [
            { idiom: '入乡随俗', pinyin: 'rù xiāng suí sú', meaning: '로마에 가면 로마법을 따르라', story: '다른 지역에 가면 그곳의 풍습을 따라야 한다는 뜻. 중국 여행이나 유학 시 자주 사용되는 표현이다.', example: { zh: '到了中国就要入乡随俗。', ko: '중국에 갔으면 그곳 풍습을 따라야지.' }, difficulty: 'easy' },
            { idiom: '学以致用', pinyin: 'xué yǐ zhì yòng', meaning: '배운 것을 실제로 활용하다', story: '학문은 실용적이어야 한다는 유교 사상에서 비롯된 성어. 현대에도 교육 관련 문맥에서 자주 쓰인다.', example: { zh: '学中文要学以致用。', ko: '중국어를 배우면 실생활에 써먹어야 해.' }, difficulty: 'easy' },
            { idiom: '一举两得', pinyin: 'yì jǔ liǎng dé', meaning: '일석이조, 한 번에 두 가지를 얻다', story: '한 가지 행동으로 두 가지 이득을 얻는다는 뜻. 한국의 "일석이조"와 같은 의미이다.', example: { zh: '这个方法一举两得。', ko: '이 방법은 일석이조야.' }, difficulty: 'easy' },
            { idiom: '熟能生巧', pinyin: 'shú néng shēng qiǎo', meaning: '숙달하면 요령이 생긴다', story: '송나라 때 기름장수의 고사에서 유래. 반복 연습을 통해 숙련된다는 뜻으로, 언어 학습에 딱 맞는 성어이다.', example: { zh: '多练习，熟能生巧。', ko: '많이 연습하면 능숙해져.' }, difficulty: 'easy' }
        ];
    }

    /* ─── 한자 탭 렌더링 ─── */
    function renderHanziTab(panel, lessonId, lessonTitle, level, keyExpressions) {
        var html = '';

        // Section 1: 한자 쓰기 연습
        html += '<div class="dd-hz-section">';
        html += '<h3 class="dd-hz-title"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> 한자 쓰기 연습</h3>';
        html += '<p class="dd-hz-desc">핵심 표현의 한자를 직접 써보세요. 터치 또는 마우스로 그릴 수 있습니다.</p>';
        html += '<div class="dd-hz-char-grid" id="dd-hz-char-grid">';

        if (keyExpressions && keyExpressions.length > 0) {
            // 핵심표현의 개별 글자들을 추출
            var chars = [];
            var seen = {};
            keyExpressions.forEach(function(ke) {
                var zhChars = (ke.zh || '').split('');
                zhChars.forEach(function(c) {
                    if (/[一-鿿]/.test(c) && !seen[c]) {
                        seen[c] = true;
                        chars.push({ char: c, pinyin: '', ko: '', source: ke.zh + ' (' + (ke.ko || '') + ')' });
                    }
                });
            });

            var progress = loadCache(PROGRESS_KEY);
            chars.forEach(function(ch) {
                var p = progress[ch.char] || { bestScore: 0, attempts: 0 };
                html += '<button class="dd-hz-char-btn" data-char="' + escapeHtml(ch.char) + '" data-source="' + escapeHtml(ch.source) + '">';
                html += '<span class="dd-hz-char">' + escapeHtml(ch.char) + '</span>';
                html += '<span class="dd-hz-char-stars">' + miniStars(p.bestScore) + '</span>';
                html += '</button>';
            });
        } else {
            html += '<p class="dd-hz-empty">핵심 표현이 없습니다.</p>';
        }
        html += '</div></div>';

        // Section 2: 강좌 단어 연습 (이 강의에 실제 등장한 표현 — 단어 단위로 그룹)
        html += '<div class="dd-hz-section">';
        html += '<h3 class="dd-hz-title"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v15H6.5A2.5 2.5 0 0 0 4 19.5z"/></svg> 강좌 단어 연습</h3>';
        html += '<p class="dd-hz-desc">이 강의에 나온 표현이에요. 단어의 글자를 눌러 직접 써보세요.</p>';
        html += '<div class="dd-hz-words">';
        if (keyExpressions && keyExpressions.length > 0) {
            var wordProgress = loadCache(PROGRESS_KEY);
            keyExpressions.forEach(function(ke) {
                var wzh = ke.zh || '';
                if (!wzh || !/[一-鿿]/.test(wzh)) return;
                var wsource = wzh + (ke.ko ? ' (' + ke.ko + ')' : '');
                html += '<div class="dd-hz-word-card">';
                html += '<div class="dd-hz-word-head">';
                html += '<span class="dd-hz-word-zh">' + escapeHtml(wzh) + '</span>';
                if (ke.pinyin) html += '<span class="dd-hz-word-pinyin">' + escapeHtml(ke.pinyin) + '</span>';
                if (ke.ko) html += '<span class="dd-hz-word-ko">' + escapeHtml(ke.ko) + '</span>';
                html += '<button class="dd-hz-word-tts" data-zh="' + escapeHtml(wzh) + '" title="발음 듣기">🔊</button>';
                html += '</div>';
                html += '<div class="dd-hz-word-chars">';
                wzh.split('').forEach(function(c) {
                    if (/[一-鿿]/.test(c)) {
                        var wp = wordProgress[c] || { bestScore: 0 };
                        html += '<button class="dd-hz-word-char-btn" data-char="' + escapeHtml(c) + '" data-source="' + escapeHtml(wsource) + '">';
                        html += '<span class="dd-hz-wc">' + escapeHtml(c) + '</span>';
                        html += '<span class="dd-hz-wc-stars">' + miniStars(wp.bestScore) + '</span>';
                        html += '</button>';
                    }
                });
                html += '</div></div>';
            });
        } else {
            html += '<p class="dd-hz-empty">강좌 단어가 없습니다.</p>';
        }
        html += '</div></div>';

        // Section 3: 고사성어
        html += '<div class="dd-hz-section">';
        html += '<h3 class="dd-hz-title"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg> 고사성어 (成语)</h3>';
        html += '<p class="dd-hz-desc">이 강의와 관련된 중국 고사성어를 배워보세요.</p>';
        html += '<div class="dd-hz-idioms" id="dd-hz-idioms">';
        html += '<div class="dd-hz-loading">고사성어 불러오는 중...</div>';
        html += '</div></div>';

        panel.innerHTML = html;

        // 한자 버튼 이벤트
        panel.querySelectorAll('.dd-hz-char-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var ch = btn.getAttribute('data-char');
                var source = btn.getAttribute('data-source');
                openStrokePractice(ch, '', source);
            });
        });

        // 강좌 단어 — 글자별 쓰기 연습
        panel.querySelectorAll('.dd-hz-word-char-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                openStrokePractice(btn.getAttribute('data-char'), '', btn.getAttribute('data-source'));
            });
        });

        // 강좌 단어 — 발음 듣기 (중국어 zh-CN, pitch 1.0 유지)
        panel.querySelectorAll('.dd-hz-word-tts').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var zh = btn.getAttribute('data-zh');
                if (window.speechSynthesis && zh) {
                    window.speechSynthesis.cancel();
                    var utter = new SpeechSynthesisUtterance(zh);
                    utter.lang = 'zh-CN';
                    utter.rate = 0.85;
                    utter.pitch = 1.0;
                    window.speechSynthesis.speak(utter);
                }
            });
        });

        // 고사성어 로드
        loadIdiomsForLesson(lessonId, lessonTitle, level, function(idioms) {
            renderIdiomCards(document.getElementById('dd-hz-idioms'), idioms);
        });
    }

    function miniStars(score) {
        var s = '';
        for (var i = 1; i <= 3; i++) {
            s += '<span class="dd-hz-mini-star' + (i <= score ? ' is-on' : '') + '">★</span>';
        }
        return s;
    }

    function renderIdiomCards(container, idioms) {
        if (!idioms || idioms.length === 0) {
            container.innerHTML = '<p class="dd-hz-empty">고사성어를 불러올 수 없습니다.</p>';
            return;
        }

        var diffColors = {
            easy: { bg: '#E8F5E9', border: '#4CAF50', label: '초급' },
            medium: { bg: '#FFF3E0', border: '#FF9800', label: '중급' },
            hard: { bg: '#FFE4E8', border: '#C2185B', label: '고급' }
        };

        var html = '';
        idioms.forEach(function(idiom, i) {
            var dc = diffColors[idiom.difficulty] || diffColors.easy;
            html += '<div class="dd-hz-idiom-card" style="border-left: 4px solid ' + dc.border + ';">';
            html += '<div class="dd-hz-idiom-header">';
            html += '<span class="dd-hz-idiom-text">' + escapeHtml(idiom.idiom) + '</span>';
            html += '<span class="dd-hz-idiom-diff" style="background:' + dc.bg + ';color:' + dc.border + ';">' + dc.label + '</span>';
            html += '</div>';
            html += '<div class="dd-hz-idiom-pinyin">' + escapeHtml(idiom.pinyin) + '</div>';
            html += '<div class="dd-hz-idiom-meaning">' + escapeHtml(idiom.meaning) + '</div>';

            if (idiom.story) {
                html += '<div class="dd-hz-idiom-story">';
                html += '<span class="dd-hz-idiom-story-label">📖 유래</span>';
                html += '<p>' + escapeHtml(idiom.story) + '</p>';
                html += '</div>';
            }

            if (idiom.example && idiom.example.zh) {
                html += '<div class="dd-hz-idiom-example">';
                html += '<span class="dd-hz-idiom-ex-zh">' + escapeHtml(idiom.example.zh) + '</span>';
                html += '<span class="dd-hz-idiom-ex-ko">' + escapeHtml(idiom.example.ko || '') + '</span>';
                html += '</div>';
            }

            // 쓰기 연습 버튼 (성어의 각 글자)
            html += '<div class="dd-hz-idiom-chars">';
            var idiomChars = (idiom.idiom || '').split('');
            idiomChars.forEach(function(c) {
                if (/[一-鿿]/.test(c)) {
                    html += '<button class="dd-hz-idiom-char-btn" data-char="' + escapeHtml(c) + '" data-source="' + escapeHtml(idiom.idiom) + '">' + escapeHtml(c) + '</button>';
                }
            });
            html += '<span class="dd-hz-idiom-write-hint">글자를 눌러 쓰기 연습</span>';
            html += '</div>';

            // TTS 재생 버튼
            html += '<button class="dd-hz-idiom-tts" data-zh="' + escapeHtml(idiom.idiom) + '">🔊 발음 듣기</button>';

            // 단어장 저장 버튼
            html += '<button class="dd-hz-idiom-save" data-zh="' + escapeHtml(idiom.idiom) + '" data-pinyin="' + escapeHtml(idiom.pinyin) + '" data-ko="' + escapeHtml(idiom.meaning) + '">📚 단어장에 저장</button>';

            html += '</div>';
        });

        container.innerHTML = html;

        // 이벤트 바인딩
        container.querySelectorAll('.dd-hz-idiom-char-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                openStrokePractice(btn.getAttribute('data-char'), '', btn.getAttribute('data-source'));
            });
        });

        container.querySelectorAll('.dd-hz-idiom-tts').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var zh = btn.getAttribute('data-zh');
                if (window.speechSynthesis && zh) {
                    window.speechSynthesis.cancel();
                    var utter = new SpeechSynthesisUtterance(zh);
                    utter.lang = 'zh-CN';
                    utter.rate = 0.8;
                    window.speechSynthesis.speak(utter);
                }
            });
        });

        container.querySelectorAll('.dd-hz-idiom-save').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (!window.DDVocab) return;
                var added = DDVocab.add({
                    zh: btn.getAttribute('data-zh'),
                    pinyin: btn.getAttribute('data-pinyin'),
                    ko: btn.getAttribute('data-ko'),
                    hsk: 0,
                    examples: [],
                    source: { lesson_id: 0, lesson_title: '고사성어' }
                });
                btn.textContent = added ? '✅ 저장됨' : '이미 저장됨';
                btn.disabled = true;
            });
        });
    }

    /* ─── 외부 API ─── */
    window.DDStrokePractice = {
        openPractice: openStrokePractice,
        renderTab: renderHanziTab
    };
})();
