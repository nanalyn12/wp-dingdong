/**
 * DD Listening Drills (듣기 훈련소)
 *
 * TTS 기반 4종 듣기 훈련:
 *  1) 받아쓰기 (dictation) — 듣고 입력
 *  2) 의미 매칭 (meaning) — 듣고 한국어 뜻 선택
 *  3) 빈칸 듣기 (gap) — 빠진 단어 채우기
 *  4) 대화 응답 (response) — 적절한 다음 대화 선택
 *
 * 기존 데이터(key_expressions, dialogues)만 활용, Gemini 불필요.
 * 오디오북 탭 내 하단에 자동 삽입.
 */
(function() {
    'use strict';

    var synth = window.speechSynthesis;
    if (!synth) return;

    var STORAGE_KEY = 'dd_listening_progress';
    var drillData = { keyExprs: [], dialogues: [] };
    var isActive = false;

    /* ─── localStorage ─── */
    function loadProgress() {
        try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || {}; }
        catch (e) { return {}; }
    }
    function saveProgress(data) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
    }

    /* ─── TTS ─── */
    function speak(text, rate, callback) {
        synth.cancel();
        var utter = new SpeechSynthesisUtterance(text);
        utter.lang = 'zh-CN';
        utter.rate = rate || 0.9;
        utter.onend = function() { if (callback) callback(); };
        utter.onerror = function() { if (callback) callback(); };
        synth.speak(utter);
    }

    /* ─── Shuffle ─── */
    function shuffle(arr) {
        var a = arr.slice();
        for (var i = a.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var t = a[i]; a[i] = a[j]; a[j] = t;
        }
        return a;
    }

    function escapeHtml(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    /* ─── 데이터 수집 ─── */
    function collectData() {
        // 핵심표현: data-zh 속성에서
        document.querySelectorAll('.dd-ab-item[data-type="expr"]').forEach(function(el) {
            var zh = el.getAttribute('data-zh') || '';
            var koEl = el.querySelector('.dd-ab-ko');
            var pinyinEl = el.querySelector('.dd-ab-pinyin');
            if (zh) {
                drillData.keyExprs.push({
                    zh: zh,
                    ko: koEl ? koEl.textContent.trim() : '',
                    pinyin: pinyinEl ? pinyinEl.textContent.trim() : ''
                });
            }
        });

        // 대화: data-zh + speaker
        document.querySelectorAll('.dd-ab-item[data-type="dialogue"]').forEach(function(el) {
            var zh = el.getAttribute('data-zh') || '';
            var koEl = el.querySelector('.dd-ab-ko');
            var speakerEl = el.querySelector('.dd-ab-speaker');
            if (zh) {
                drillData.dialogues.push({
                    zh: zh,
                    ko: koEl ? koEl.textContent.trim() : '',
                    speaker: speakerEl ? speakerEl.textContent.trim() : ''
                });
            }
        });
    }

    /* ─── 훈련 유형 1: 받아쓰기 ─── */
    function drillDictation(container, items, onComplete) {
        if (items.length === 0) { onComplete(0, 0); return; }
        var pool = shuffle(items).slice(0, 5);
        var idx = 0, score = 0;

        function showQ() {
            if (idx >= pool.length) { onComplete(score, pool.length); return; }
            var item = pool[idx];
            var charCount = item.zh ? item.zh.length : 0;
            var hintText = item.zh ? item.zh.substring(0, Math.max(1, Math.floor(charCount / 3))) + '...' : '';
            var html = '<div class="dd-ld-card">';
            html += '<div class="dd-ld-num">' + (idx+1) + ' / ' + pool.length + '</div>';
            html += '<h4 class="dd-ld-type-label">✍️ 받아쓰기</h4>';
            html += '<p class="dd-ld-instruction">들리는 중국어를 입력하세요</p>';
            html += '<button class="dd-ld-play-btn" id="dd-ld-play">🔊 재생</button>';
            html += '<div class="dd-ld-char-hint">💡 총 <strong>' + charCount + '글자</strong>' + (item.pinyin ? ' · 병음: ' + escapeHtml(item.pinyin) : '') + '</div>';
            html += '<div class="dd-ld-input-wrap dd-ld-input-wrap--wide">';
            html += '<input type="text" class="dd-ld-input dd-ld-input--full" id="dd-ld-input" placeholder="' + charCount + '글자 중국어를 입력하세요..." autocomplete="off" maxlength="' + (charCount + 5) + '">';
            html += '<button class="dd-ld-check-btn" id="dd-ld-check">확인</button>';
            html += '</div>';
            html += '<button class="dd-ld-hint-btn" id="dd-ld-hint">💡 힌트 보기</button>';
            html += '<div class="dd-ld-hint-text" id="dd-ld-hint-text" style="display:none;">앞부분: <strong>' + escapeHtml(hintText) + '</strong></div>';
            html += '<div class="dd-ld-feedback" id="dd-ld-feedback" style="display:none;"></div>';
            html += '</div>';
            container.innerHTML = html;

            document.getElementById('dd-ld-play').addEventListener('click', function() {
                speak(item.zh, 0.85);
            });
            // 자동 재생
            setTimeout(function() { speak(item.zh, 0.85); }, 300);

            var hintBtn = document.getElementById('dd-ld-hint');
            var hintTextEl = document.getElementById('dd-ld-hint-text');
            if (hintBtn) {
                hintBtn.addEventListener('click', function() {
                    hintTextEl.style.display = '';
                    hintBtn.style.display = 'none';
                });
            }

            var input = document.getElementById('dd-ld-input');
            var checkBtn = document.getElementById('dd-ld-check');
            var feedback = document.getElementById('dd-ld-feedback');

            function check() {
                var answer = input.value.trim();
                var correct = answer === item.zh || answer.replace(/\s/g,'') === item.zh.replace(/\s/g,'');
                input.disabled = true;
                checkBtn.disabled = true;
                feedback.style.display = 'block';

                if (correct) {
                    score++;
                    feedback.className = 'dd-ld-feedback dd-ld-correct';
                    feedback.innerHTML = '✅ 정답! ' + escapeHtml(item.zh);
                } else {
                    feedback.className = 'dd-ld-feedback dd-ld-wrong';
                    feedback.innerHTML = '❌ 오답<br>정답: ' + escapeHtml(item.zh) + '<br>입력: ' + escapeHtml(answer);
                }
                if (item.ko) feedback.innerHTML += '<br><span class="dd-ld-ko">' + escapeHtml(item.ko) + '</span>';

                // SRS
                if (window.DDSRS) DDSRS.recordReview(item.zh, correct ? 4 : 1, 'listening');

                setTimeout(function() { idx++; showQ(); }, 1500);
            }

            checkBtn.addEventListener('click', check);
            input.addEventListener('keydown', function(e) { if (e.key === 'Enter') check(); });
            input.focus();
        }
        showQ();
    }

    /* ─── 훈련 유형 2: 의미 매칭 ─── */
    function drillMeaning(container, items, onComplete) {
        if (items.length < 4) { onComplete(0, 0); return; }
        var pool = shuffle(items).slice(0, 5);
        var idx = 0, score = 0;

        function showQ() {
            if (idx >= pool.length) { onComplete(score, pool.length); return; }
            var item = pool[idx];
            // 오답 보기 생성
            var distractors = items.filter(function(x) { return x.zh !== item.zh && x.ko; });
            distractors = shuffle(distractors).slice(0, 3).map(function(x) { return x.ko; });
            var options = shuffle(distractors.concat([item.ko]));

            var html = '<div class="dd-ld-card">';
            html += '<div class="dd-ld-num">' + (idx+1) + ' / ' + pool.length + '</div>';
            html += '<h4 class="dd-ld-type-label">👂 의미 매칭</h4>';
            html += '<p class="dd-ld-instruction">중국어를 듣고 알맞은 뜻을 고르세요</p>';
            html += '<button class="dd-ld-play-btn dd-ld-play-large" id="dd-ld-play">🔊 듣기</button>';
            html += '<div class="dd-ld-options" id="dd-ld-opts">';
            options.forEach(function(opt) {
                html += '<button class="dd-ld-opt" data-val="' + escapeHtml(opt) + '">' + escapeHtml(opt) + '</button>';
            });
            html += '</div>';
            html += '</div>';
            container.innerHTML = html;

            document.getElementById('dd-ld-play').addEventListener('click', function() { speak(item.zh, 0.85); });
            setTimeout(function() { speak(item.zh, 0.85); }, 300);

            var opts = container.querySelectorAll('.dd-ld-opt');
            opts.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var correct = btn.getAttribute('data-val') === item.ko;
                    opts.forEach(function(b) {
                        b.disabled = true;
                        if (b.getAttribute('data-val') === item.ko) b.classList.add('dd-ld-opt-correct');
                    });
                    if (correct) { score++; btn.classList.add('dd-ld-opt-correct'); }
                    else { btn.classList.add('dd-ld-opt-wrong'); }

                    if (window.DDSRS) DDSRS.recordReview(item.zh, correct ? 4 : 1, 'listening');
                    setTimeout(function() { idx++; showQ(); }, 1200);
                });
            });
        }
        showQ();
    }

    /* ─── 훈련 유형 3: 빈칸 듣기 ─── */
    function drillGap(container, items, onComplete) {
        var suitable = items.filter(function(x) { return x.zh && x.zh.length >= 2; });
        if (suitable.length < 3) { onComplete(0, 0); return; }
        var pool = shuffle(suitable).slice(0, 5);
        var idx = 0, score = 0;

        function showQ() {
            if (idx >= pool.length) { onComplete(score, pool.length); return; }
            var item = pool[idx];
            var chars = item.zh.split('');
            var gapIdx = Math.floor(Math.random() * chars.length);
            var answer = chars[gapIdx];
            var display = chars.map(function(c, i) { return i === gapIdx ? '____' : c; }).join('');
            // TTS는 전체 문장 재생 (빈칸 없이)
            var ttsText = item.zh;

            var html = '<div class="dd-ld-card">';
            html += '<div class="dd-ld-num">' + (idx+1) + ' / ' + pool.length + '</div>';
            html += '<h4 class="dd-ld-type-label">🔍 빈칸 듣기</h4>';
            html += '<p class="dd-ld-instruction">듣고 빈칸에 들어갈 글자를 입력하세요</p>';
            html += '<button class="dd-ld-play-btn" id="dd-ld-play">🔊 재생</button>';
            html += '<div class="dd-ld-sentence">' + escapeHtml(display) + '</div>';
            html += '<div class="dd-ld-char-hint">💡 빈칸에 들어갈 <strong>1글자</strong>를 입력하세요</div>';
            html += '<div class="dd-ld-input-wrap">';
            html += '<input type="text" class="dd-ld-input dd-ld-input-gap" id="dd-ld-input" placeholder="한 글자" maxlength="2" autocomplete="off">';
            html += '<button class="dd-ld-check-btn" id="dd-ld-check">확인</button>';
            html += '</div>';
            html += '<div class="dd-ld-feedback" id="dd-ld-feedback" style="display:none;"></div>';
            html += '</div>';
            container.innerHTML = html;

            document.getElementById('dd-ld-play').addEventListener('click', function() { speak(ttsText, 0.85); });
            setTimeout(function() { speak(ttsText, 0.85); }, 300);

            var input = document.getElementById('dd-ld-input');
            var checkBtn = document.getElementById('dd-ld-check');
            var feedback = document.getElementById('dd-ld-feedback');

            function check() {
                var val = input.value.trim();
                var correct = val === answer;
                input.disabled = true;
                checkBtn.disabled = true;
                feedback.style.display = 'block';
                if (correct) {
                    score++;
                    feedback.className = 'dd-ld-feedback dd-ld-correct';
                    feedback.textContent = '✅ 정답! ' + item.zh;
                } else {
                    feedback.className = 'dd-ld-feedback dd-ld-wrong';
                    feedback.innerHTML = '❌ 정답: <strong>' + escapeHtml(answer) + '</strong> → ' + escapeHtml(item.zh);
                }
                if (window.DDSRS) DDSRS.recordReview(item.zh, correct ? 4 : 1, 'listening');
                setTimeout(function() { idx++; showQ(); }, 1500);
            }

            checkBtn.addEventListener('click', check);
            input.addEventListener('keydown', function(e) { if (e.key === 'Enter') check(); });
            input.focus();
        }
        showQ();
    }

    /* ─── 훈련 유형 4: 대화 응답 ─── */
    function drillResponse(container, dialogues, onComplete) {
        if (dialogues.length < 4) { onComplete(0, 0); return; }
        var pairs = [];
        for (var i = 0; i < dialogues.length - 1; i++) {
            pairs.push({ prompt: dialogues[i], answer: dialogues[i+1] });
        }
        var pool = shuffle(pairs).slice(0, 5);
        var idx = 0, score = 0;

        function showQ() {
            if (idx >= pool.length) { onComplete(score, pool.length); return; }
            var pair = pool[idx];
            var distractors = dialogues.filter(function(d) { return d.zh !== pair.answer.zh; });
            distractors = shuffle(distractors).slice(0, 3);
            var options = shuffle(distractors.concat([pair.answer]));

            var html = '<div class="dd-ld-card">';
            html += '<div class="dd-ld-num">' + (idx+1) + ' / ' + pool.length + '</div>';
            html += '<h4 class="dd-ld-type-label">💬 대화 응답</h4>';
            html += '<p class="dd-ld-instruction">상대방 말을 듣고 적절한 응답을 고르세요</p>';
            html += '<div class="dd-ld-prompt-speaker">' + escapeHtml(pair.prompt.speaker || 'A') + ':</div>';
            html += '<button class="dd-ld-play-btn" id="dd-ld-play">🔊 듣기</button>';
            html += '<div class="dd-ld-options" id="dd-ld-opts">';
            options.forEach(function(opt) {
                html += '<button class="dd-ld-opt dd-ld-opt-zh" data-zh="' + escapeHtml(opt.zh) + '">';
                html += escapeHtml(opt.zh);
                if (opt.ko) html += '<span class="dd-ld-opt-ko">' + escapeHtml(opt.ko) + '</span>';
                html += '</button>';
            });
            html += '</div>';
            html += '</div>';
            container.innerHTML = html;

            document.getElementById('dd-ld-play').addEventListener('click', function() { speak(pair.prompt.zh, 0.85); });
            setTimeout(function() { speak(pair.prompt.zh, 0.85); }, 300);

            container.querySelectorAll('.dd-ld-opt').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var correct = btn.getAttribute('data-zh') === pair.answer.zh;
                    container.querySelectorAll('.dd-ld-opt').forEach(function(b) {
                        b.disabled = true;
                        if (b.getAttribute('data-zh') === pair.answer.zh) b.classList.add('dd-ld-opt-correct');
                    });
                    if (correct) { score++; } else { btn.classList.add('dd-ld-opt-wrong'); }
                    if (window.DDSRS) DDSRS.recordReview(pair.answer.zh, correct ? 4 : 1, 'listening');
                    setTimeout(function() { idx++; showQ(); }, 1200);
                });
            });
        }
        showQ();
    }

    /* ─── 결과 화면 ─── */
    function showResult(container, score, total, drillType) {
        var pct = total > 0 ? Math.round((score / total) * 100) : 0;
        var emoji = pct >= 80 ? '🎉' : pct >= 50 ? '👍' : '💪';
        var html = '<div class="dd-ld-result">';
        html += '<div class="dd-ld-result-emoji">' + emoji + '</div>';
        html += '<h4>' + drillType + ' 완료!</h4>';
        html += '<p class="dd-ld-result-score">' + score + ' / ' + total + ' 정답 (' + pct + '%)</p>';
        html += '<div class="dd-ld-result-actions">';
        html += '<button class="dd-ld-retry-btn" data-type="' + drillType + '">다시 하기</button>';
        html += '<button class="dd-ld-menu-btn">훈련 목록</button>';
        html += '</div></div>';
        container.innerHTML = html;

        container.querySelector('.dd-ld-retry-btn').addEventListener('click', function() {
            startDrill(container, drillType);
        });
        container.querySelector('.dd-ld-menu-btn').addEventListener('click', function() {
            renderMenu(container);
        });
    }

    /* ─── 메뉴 ─── */
    function renderMenu(container) {
        var allItems = drillData.keyExprs.concat(drillData.dialogues);
        var hasDialogues = drillData.dialogues.length >= 4;

        var html = '<div class="dd-ld-menu">';
        html += '<h4 class="dd-ld-menu-title">🎧 듣기 훈련소</h4>';
        html += '<p class="dd-ld-menu-desc">중국어를 듣고 이해력을 테스트하세요</p>';
        html += '<div class="dd-ld-menu-grid">';

        html += '<button class="dd-ld-menu-item" data-drill="dictation"' + (allItems.length < 1 ? ' disabled' : '') + '>';
        html += '<span class="dd-ld-menu-icon">✍️</span>';
        html += '<span class="dd-ld-menu-name">받아쓰기</span>';
        html += '<span class="dd-ld-menu-info">듣고 입력</span>';
        html += '</button>';

        html += '<button class="dd-ld-menu-item" data-drill="meaning"' + (allItems.length < 4 ? ' disabled' : '') + '>';
        html += '<span class="dd-ld-menu-icon">👂</span>';
        html += '<span class="dd-ld-menu-name">의미 매칭</span>';
        html += '<span class="dd-ld-menu-info">뜻 고르기</span>';
        html += '</button>';

        html += '<button class="dd-ld-menu-item" data-drill="gap"' + (allItems.length < 3 ? ' disabled' : '') + '>';
        html += '<span class="dd-ld-menu-icon">🔍</span>';
        html += '<span class="dd-ld-menu-name">빈칸 듣기</span>';
        html += '<span class="dd-ld-menu-info">빠진 글자</span>';
        html += '</button>';

        html += '<button class="dd-ld-menu-item" data-drill="response"' + (!hasDialogues ? ' disabled' : '') + '>';
        html += '<span class="dd-ld-menu-icon">💬</span>';
        html += '<span class="dd-ld-menu-name">대화 응답</span>';
        html += '<span class="dd-ld-menu-info">대화 이어가기</span>';
        html += '</button>';

        html += '</div></div>';
        container.innerHTML = html;

        container.querySelectorAll('.dd-ld-menu-item').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var type = btn.getAttribute('data-drill');
                startDrill(container, type);
            });
        });
    }

    function startDrill(container, type) {
        var allItems = drillData.keyExprs.concat(drillData.dialogues);
        var onDone = function(s, t) { showResult(container, s, t, type); };

        switch (type) {
            case 'dictation': drillDictation(container, allItems, onDone); break;
            case 'meaning':   drillMeaning(container, allItems, onDone); break;
            case 'gap':       drillGap(container, allItems, onDone); break;
            case 'response':  drillResponse(container, drillData.dialogues, onDone); break;
            default: renderMenu(container);
        }
    }

    /* ─── 섹션 삽입 ─── */
    function inject() {
        var audioPanel = document.getElementById('panel-audiobook');
        if (!audioPanel) return;

        collectData();
        if (drillData.keyExprs.length === 0 && drillData.dialogues.length === 0) return;

        var section = document.createElement('div');
        section.className = 'dd-ld-section';
        section.id = 'dd-listening-drills';

        var toggle = document.createElement('button');
        toggle.className = 'dd-ld-toggle';
        toggle.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg> 듣기 훈련 시작';

        var drillContainer = document.createElement('div');
        drillContainer.className = 'dd-ld-container';
        drillContainer.style.display = 'none';

        toggle.addEventListener('click', function() {
            if (drillContainer.style.display === 'none') {
                drillContainer.style.display = 'block';
                toggle.classList.add('is-active');
                renderMenu(drillContainer);
            } else {
                drillContainer.style.display = 'none';
                toggle.classList.remove('is-active');
            }
        });

        section.appendChild(toggle);
        section.appendChild(drillContainer);
        audioPanel.querySelector('.dd-audiobook').appendChild(section);
    }

    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(inject, 600);
    });
})();
