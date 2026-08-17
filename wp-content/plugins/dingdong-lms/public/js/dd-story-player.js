(function() {
    'use strict';

    var storyData = window.ddStoryData;
    if (!storyData || !storyData.nodes || !storyData.start) return;

    var nodes = storyData.nodes;
    var startNode = storyData.start;
    var storyTitle = storyData.title || '';
    var storyId = storyData.story_id || storyTitle.substring(0, 20);
    var STORAGE_KEY = 'dd_story_progress_' + storyId;

    var history = [];
    var currentNodeId = null;
    var collectedVocab = [];
    var totalNodes = Object.keys(nodes).length;

    /* ─── DOM refs ─── */
    var coverEl = document.getElementById('dd-story-cover');
    var playerEl = document.getElementById('dd-story-player');
    var endingEl = document.getElementById('dd-story-ending');

    var nodeContainer = document.getElementById('dd-story-node');
    var nodeImg = document.getElementById('dd-story-node-img');
    var nodeImgEl = document.getElementById('dd-story-node-img-el');
    var zhEl = document.getElementById('dd-story-zh');
    var pinyinEl = document.getElementById('dd-story-pinyin');
    var koEl = document.getElementById('dd-story-ko');
    var choicesEl = document.getElementById('dd-story-choices');
    var speakerEl = document.getElementById('dd-story-speaker');
    var speakerAvatar = document.getElementById('dd-story-speaker-avatar');
    var speakerName = document.getElementById('dd-story-speaker-name');
    var grammarTip = document.getElementById('dd-story-grammar-tip');
    var grammarText = document.getElementById('dd-story-grammar-text');
    var nodeVocab = document.getElementById('dd-story-node-vocab');
    var nodeVocabList = document.getElementById('dd-story-node-vocab-list');
    var progressFill = document.getElementById('dd-story-progress-fill');
    var breadcrumbs = document.getElementById('dd-story-breadcrumbs');
    var stepCounter = document.getElementById('dd-story-step-counter');
    var backBtn = document.getElementById('dd-story-back');
    var vocabCount = document.getElementById('dd-story-vocab-count');

    var startBtn = document.getElementById('dd-story-start');
    var restartBtn = document.getElementById('dd-story-restart');
    var homeBtn = document.getElementById('dd-story-home');
    var ttsBtn = document.getElementById('dd-story-tts-btn');
    var vocabToggle = document.getElementById('dd-story-vocab-toggle');

    var wordPopup = document.getElementById('dd-word-popup');

    /* ─── TTS ─── */
    var ttsVoices = [];
    function loadVoices() {
        ttsVoices = speechSynthesis.getVoices().filter(function(v) {
            return v.lang.indexOf('zh') === 0;
        });
    }
    if (speechSynthesis) {
        loadVoices();
        speechSynthesis.onvoiceschanged = loadVoices;
    }

    function speakChinese(text) {
        if (!speechSynthesis || !text) return;
        speechSynthesis.cancel();
        var stripped = text.replace(/[㐀-鿿豈-﫿]/g, function(m) { return m; });
        // Remove Korean
        stripped = text.replace(/[가-힯ᄀ-ᇿ㄰-㆏ꥠ-꥿ힰ-퟿]+/g, '');
        var utter = new SpeechSynthesisUtterance(stripped);
        utter.lang = 'zh-CN';
        utter.rate = 0.85;
        if (ttsVoices.length > 0) utter.voice = ttsVoices[0];
        speechSynthesis.speak(utter);
    }

    /* ─── Screen management ─── */
    function showScreen(screen) {
        coverEl.style.display = screen === 'cover' ? '' : 'none';
        playerEl.style.display = screen === 'player' ? '' : 'none';
        endingEl.style.display = screen === 'ending' ? '' : 'none';

        // Reset mood
        document.body.className = '';
    }

    /* ─── Mood background ─── */
    function setMood(mood) {
        document.body.className = '';
        if (mood) {
            document.body.classList.add('mood-' + mood);
        }
    }

    /* ─── Breadcrumbs ─── */
    function updateBreadcrumbs() {
        if (!breadcrumbs) return;
        breadcrumbs.innerHTML = '';
        var path = history.concat([currentNodeId]);
        path.forEach(function(nid, i) {
            if (i > 0) {
                var sep = document.createElement('span');
                sep.className = 'dd-story-crumb-sep';
                breadcrumbs.appendChild(sep);
            }
            var crumb = document.createElement('span');
            crumb.className = 'dd-story-crumb';
            if (i === path.length - 1) {
                crumb.classList.add('is-current');
            } else {
                crumb.classList.add('is-visited');
            }
            breadcrumbs.appendChild(crumb);
        });
        // Scroll to end
        breadcrumbs.scrollLeft = breadcrumbs.scrollWidth;
    }

    /* ─── Vocab highlight in Chinese text ─── */
    function highlightVocab(textZh, vocab) {
        if (!vocab || vocab.length === 0) return escHtml(textZh);

        var html = escHtml(textZh);
        vocab.forEach(function(v) {
            if (!v.zh) return;
            var escaped = escHtml(v.zh);
            var regex = new RegExp('(' + escRegex(escaped) + ')', 'g');
            html = html.replace(regex,
                '<span class="dd-vocab-word" data-zh="' + escAttr(v.zh) + '" data-pinyin="' + escAttr(v.pinyin || '') + '" data-ko="' + escAttr(v.ko || '') + '">$1</span>'
            );
        });
        return html;
    }

    /* ─── Show node ─── */
    function showNode(nodeId) {
        var node = nodes[nodeId];
        if (!node) return;

        currentNodeId = nodeId;
        markVisited(nodeId);   // 스토리 맵용 — 가본 노드 영구 기록
        hideWordPopup();

        if (node.is_ending) {
            showEnding(node);
            return;
        }

        showScreen('player');
        setMood(node.mood || '');

        // Re-animate node card
        nodeContainer.style.animation = 'none';
        nodeContainer.offsetHeight; // trigger reflow
        nodeContainer.style.animation = '';

        // Speaker
        if (node.speaker) {
            speakerEl.style.display = 'flex';
            speakerAvatar.textContent = node.speaker.substring(0, 1);
            speakerName.textContent = node.speaker;
        } else {
            speakerEl.style.display = 'none';
        }

        // Image
        if (node.image_url) {
            nodeImgEl.src = node.image_url;
            nodeImgEl.alt = '';
            nodeImg.style.display = '';
        } else {
            nodeImg.style.display = 'none';
        }

        // Text with vocab highlight
        var vocab = node.vocab || [];
        zhEl.innerHTML = highlightVocab(node.text_zh || '', vocab);
        pinyinEl.textContent = node.pinyin || '';
        koEl.textContent = node.text_ko || '';

        // Grammar tip
        if (node.grammar_tip) {
            grammarTip.style.display = 'flex';
            grammarText.textContent = node.grammar_tip;
        } else {
            grammarTip.style.display = 'none';
        }

        // Node vocab panel
        if (vocab.length > 0) {
            nodeVocab.style.display = '';
            nodeVocabList.innerHTML = '';
            vocab.forEach(function(v) {
                var chip = document.createElement('span');
                chip.className = 'dd-story-vocab-chip';
                chip.innerHTML = '<span class="vc-zh">' + escHtml(v.zh || '') + '</span>'
                    + '<span class="vc-ko">' + escHtml(v.ko || '') + '</span>';
                chip.addEventListener('click', function() {
                    showWordPopup(v, chip);
                });
                nodeVocabList.appendChild(chip);
            });
            // Collect unique vocab
            vocab.forEach(function(v) {
                if (!v.zh) return;
                var exists = collectedVocab.some(function(cv) { return cv.zh === v.zh; });
                if (!exists) collectedVocab.push(v);
            });
            updateVocabCount();
        } else {
            nodeVocab.style.display = 'none';
        }

        // Choices
        choicesEl.innerHTML = '';
        var choices = node.choices || [];
        choices.forEach(function(choice) {
            var btn = document.createElement('button');
            btn.className = 'dd-story-choice-btn';
            var emoji = choice.emoji ? '<span class="dd-choice-emoji">' + choice.emoji + '</span>' : '';
            btn.innerHTML = emoji +
                '<span class="dd-choice-text">' +
                '<span class="dd-choice-zh">' + escHtml(choice.text_zh || '') + '</span>' +
                '<span class="dd-choice-ko">' + escHtml(choice.text_ko || '') + '</span>' +
                '</span>';
            btn.addEventListener('click', function() {
                makeChoice(choice.next);
            });
            choicesEl.appendChild(btn);
        });

        // 음성으로 선택 — 선택지를 소리내어 말하면 그 선택으로 진행
        renderVoiceSelect(choices);

        // Progress
        var pct = Math.round(((history.length + 1) / totalNodes) * 100);
        if (pct > 100) pct = 100;
        progressFill.style.width = pct + '%';

        stepCounter.textContent = (history.length + 1) + '단계';
        backBtn.style.display = history.length > 0 ? '' : 'none';

        updateBreadcrumbs();

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function makeChoice(nextNodeId) {
        history.push(currentNodeId);
        saveProgress();
        showNode(nextNodeId);
    }

    function goBack() {
        if (history.length === 0) return;
        var prevId = history.pop();
        saveProgress();
        showNode(prevId);
    }

    /* ─── Ending ─── */
    function showEnding(node) {
        showScreen('ending');
        setMood('');
        if (window.DDGamification) DDGamification.onStoryEnding();

        var endingType = node.ending_type || 'neutral';
        var icons = { good: '🎉', neutral: '🎯', bad: '💨' };
        var titles = {
            good: node.ending_title || '좋은 결말!',
            neutral: node.ending_title || '이야기 끝',
            bad: node.ending_title || '아쉬운 결말...'
        };

        document.getElementById('dd-story-ending-icon').textContent = icons[endingType] || icons.neutral;
        document.getElementById('dd-story-ending-title').textContent = titles[endingType] || titles.neutral;
        document.getElementById('dd-story-ending-zh').textContent = node.text_zh || '';
        document.getElementById('dd-story-ending-pinyin').textContent = node.pinyin || '';
        document.getElementById('dd-story-ending-ko').textContent = node.text_ko || '';

        var card = document.getElementById('dd-story-ending-card');
        card.className = 'dd-story-ending-card dd-ending-' + endingType;

        // Stats
        var statGrid = document.getElementById('dd-story-stat-grid');
        statGrid.innerHTML =
            statItem(history.length + 1, '방문한 장면') +
            statItem(collectedVocab.length, '만난 어휘') +
            statItem(history.length, '선택한 횟수');

        // Vocab summary
        var evSection = document.getElementById('dd-story-ending-vocab');
        var evList = document.getElementById('dd-story-ending-vocab-list');
        if (collectedVocab.length > 0) {
            evSection.style.display = '';
            evList.innerHTML = '';
            collectedVocab.forEach(function(v) {
                var chip = document.createElement('span');
                chip.className = 'dd-story-ev-chip';
                chip.innerHTML = '<span class="ev-zh">' + escHtml(v.zh) + '</span>'
                    + '<span class="ev-ko">' + escHtml(v.ko || '') + '</span>';
                evList.appendChild(chip);
            });
        } else {
            evSection.style.display = 'none';
        }

        // Save ending to collection
        saveEnding(currentNodeId, endingType, node.ending_title || titles[endingType]);
        renderEndingCollection();
    }

    function statItem(num, label) {
        return '<div class="dd-story-stat-item">' +
            '<span class="dd-story-stat-num">' + num + '</span>' +
            '<span class="dd-story-stat-label">' + label + '</span>' +
            '</div>';
    }

    /* ─── Ending Collection ─── */
    function getEndingNodes() {
        var endings = [];
        Object.keys(nodes).forEach(function(nid) {
            if (nodes[nid].is_ending) {
                endings.push({
                    id: nid,
                    type: nodes[nid].ending_type || 'neutral',
                    title: nodes[nid].ending_title || ''
                });
            }
        });
        return endings;
    }

    function getSavedProgress() {
        try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || {}; }
        catch(e) { return {}; }
    }

    function saveProgress() {
        var prog = getSavedProgress();
        prog.history = history;
        prog.current = currentNodeId;
        prog.lastPlayed = Date.now();
        localStorage.setItem(STORAGE_KEY, JSON.stringify(prog));
    }

    function saveEnding(nodeId, type, title) {
        var prog = getSavedProgress();
        if (!prog.endings) prog.endings = {};
        if (!prog.endings[nodeId]) {
            prog.endings[nodeId] = { type: type, title: title, foundAt: Date.now() };
        }
        prog.playCount = (prog.playCount || 0) + 1;
        localStorage.setItem(STORAGE_KEY, JSON.stringify(prog));
    }

    function renderEndingCollection() {
        var container = document.getElementById('dd-story-ending-collection');
        if (!container) return;

        var allEndings = getEndingNodes();
        var prog = getSavedProgress();
        var found = prog.endings || {};
        var foundCount = Object.keys(found).length;

        if (allEndings.length === 0) {
            container.innerHTML = '';
            return;
        }

        var html = '<h4>엔딩 수집: ' + foundCount + '/' + allEndings.length + '</h4>';
        html += '<div class="dd-story-collection-badges">';
        allEndings.forEach(function(e) {
            var isFound = !!found[e.id];
            var icons = { good: '⭐', neutral: '🎯', bad: '💨' };
            var icon = icons[e.type] || '❓';
            html += '<span class="dd-story-ending-badge ' + (isFound ? 'is-found' : 'is-locked') + '">';
            html += isFound ? (icon + ' ' + (e.title || e.type)) : '🔒 ???';
            html += '</span>';
        });
        html += '</div>';
        container.innerHTML = html;
    }

    function renderCollectionPreview() {
        var preview = document.getElementById('dd-story-collection-preview');
        if (!preview) return;

        var prog = getSavedProgress();
        var found = prog.endings || {};
        var foundCount = Object.keys(found).length;

        if (foundCount === 0) {
            preview.style.display = 'none';
            return;
        }

        var allEndings = getEndingNodes();
        var html = '<h4>엔딩 수집 현황: ' + foundCount + '/' + allEndings.length + '</h4>';
        html += '<div class="dd-story-collection-badges">';
        allEndings.forEach(function(e) {
            var isFound = !!found[e.id];
            var icons = { good: '⭐', neutral: '🎯', bad: '💨' };
            var icon = icons[e.type] || '❓';
            html += '<span class="dd-story-ending-badge ' + (isFound ? 'is-found' : 'is-locked') + '">';
            html += isFound ? (icon + ' ' + (e.title || e.type)) : '🔒 ???';
            html += '</span>';
        });
        html += '</div>';

        if (prog.playCount) {
            html += '<p style="font-size:0.78rem;color:var(--dd-text-light);margin-top:0.5rem;">플레이 횟수: ' + prog.playCount + '회</p>';
        }

        preview.innerHTML = html;
        preview.style.display = '';
    }

    /* ─── Word popup ─── */
    function showWordPopup(vocab, anchorEl) {
        if (!wordPopup) return;

        document.getElementById('dd-word-popup-zh').textContent = vocab.zh || '';
        document.getElementById('dd-word-popup-pinyin').textContent = vocab.pinyin || '';
        document.getElementById('dd-word-popup-ko').textContent = vocab.ko || '';

        // Position near anchor
        var rect = anchorEl.getBoundingClientRect();
        var top = rect.bottom + 8;
        var left = rect.left;

        // Keep within viewport
        if (left + 260 > window.innerWidth) left = window.innerWidth - 270;
        if (left < 10) left = 10;
        if (top + 160 > window.innerHeight) top = rect.top - 168;

        wordPopup.style.top = top + 'px';
        wordPopup.style.left = left + 'px';
        wordPopup.style.display = '';
        wordPopup._currentVocab = vocab;

        // Check if saved
        var saveBtn = document.getElementById('dd-word-popup-save');
        if (saveBtn) {
            saveBtn.classList.toggle('is-saved', isVocabSaved(vocab.zh));
        }
    }

    function hideWordPopup() {
        if (wordPopup) wordPopup.style.display = 'none';
    }

    function isVocabSaved(zh) {
        try {
            var saved = JSON.parse(localStorage.getItem('dd_vocabulary')) || [];
            return saved.some(function(v) { return v.zh === zh; });
        } catch(e) { return false; }
    }

    function saveVocabWord(vocab) {
        try {
            var saved = JSON.parse(localStorage.getItem('dd_vocabulary')) || [];
            if (saved.some(function(v) { return v.zh === vocab.zh; })) return;
            saved.push({
                zh: vocab.zh,
                pinyin: vocab.pinyin || '',
                ko: vocab.ko || '',
                hsk: '',
                lessonId: storyId,
                lessonTitle: storyTitle,
                addedAt: Date.now(),
                source: 'story'
            });
            localStorage.setItem('dd_vocabulary', JSON.stringify(saved));
        } catch(e) {}
    }

    // Click on vocab word in text
    document.addEventListener('click', function(e) {
        var word = e.target.closest('.dd-vocab-word');
        if (word) {
            e.stopPropagation();
            showWordPopup({
                zh: word.getAttribute('data-zh'),
                pinyin: word.getAttribute('data-pinyin'),
                ko: word.getAttribute('data-ko')
            }, word);
            return;
        }

        // Click on vocab chip
        // (handled by individual chip listeners)

        // Close popup on outside click
        if (wordPopup && wordPopup.style.display !== 'none' && !wordPopup.contains(e.target)) {
            hideWordPopup();
        }
    });

    // Popup TTS button
    var popupTts = document.getElementById('dd-word-popup-tts');
    if (popupTts) {
        popupTts.addEventListener('click', function() {
            if (wordPopup._currentVocab) {
                speakChinese(wordPopup._currentVocab.zh);
            }
        });
    }

    // Popup save button
    var popupSave = document.getElementById('dd-word-popup-save');
    if (popupSave) {
        popupSave.addEventListener('click', function() {
            if (wordPopup._currentVocab) {
                saveVocabWord(wordPopup._currentVocab);
                popupSave.classList.add('is-saved');
            }
        });
    }

    /* ─── Vocab count badge ─── */
    function updateVocabCount() {
        if (vocabCount) {
            vocabCount.textContent = collectedVocab.length > 0 ? collectedVocab.length : '';
        }
    }

    /* ─── Tool buttons ─── */
    if (ttsBtn) {
        ttsBtn.addEventListener('click', function() {
            if (currentNodeId && nodes[currentNodeId]) {
                speakChinese(nodes[currentNodeId].text_zh || '');
            }
        });
    }

    if (vocabToggle) {
        vocabToggle.addEventListener('click', function() {
            if (!nodeVocab) return;
            var isShown = nodeVocab.style.display !== 'none';
            nodeVocab.style.display = isShown ? 'none' : '';
            vocabToggle.classList.toggle('is-active', !isShown);
        });
    }

    /* ─── Restart / home ─── */
    function restart() {
        history = [];
        collectedVocab = [];
        updateVocabCount();
        saveProgress();
        showNode(startNode);
    }

    function goHome() {
        history = [];
        collectedVocab = [];
        updateVocabCount();
        showScreen('cover');
        renderCollectionPreview();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    /* ─── Event listeners ─── */
    if (startBtn) startBtn.addEventListener('click', function() { showNode(startNode); });
    if (restartBtn) restartBtn.addEventListener('click', restart);
    if (homeBtn) homeBtn.addEventListener('click', goHome);
    if (backBtn) backBtn.addEventListener('click', goBack);

    /* ════════════════════════════════════════════════════════
       음성으로 선택하기 — 선택지(중국어)를 말하면 그 선택으로 진행
       ════════════════════════════════════════════════════════ */
    var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    var voiceRec = null, voiceListening = false;

    function cjkOnly(s) {
        return (s || '').replace(/[^一-鿿]/g, '');
    }

    // 인식된 발화를 선택지들과 매칭 → 최적 선택지 index (없으면 -1)
    function matchChoiceByVoice(transcript, choices) {
        var heard = cjkOnly(transcript);
        if (!heard) return -1;
        var best = -1, bestScore = 0, second = 0;
        choices.forEach(function(c, i) {
            var target = cjkOnly(c.text_zh || '');
            if (!target) return;
            var hit = 0;
            // target의 각 글자가 발화에 포함되는 비율 + 부분문자열 보너스
            for (var k = 0; k < target.length; k++) {
                if (heard.indexOf(target.charAt(k)) !== -1) hit++;
            }
            var score = hit / target.length;
            if (heard.indexOf(target) !== -1 || target.indexOf(heard) !== -1) score += 0.5;
            if (score > bestScore) { second = bestScore; bestScore = score; best = i; }
            else if (score > second) { second = score; }
        });
        // 충분히 닮았고, 2등과 뚜렷이 차이날 때만 채택
        if (bestScore >= 0.5 && (bestScore - second) >= 0.15) return best;
        return -1;
    }

    function renderVoiceSelect(choices) {
        if (!SR || !choices || choices.length === 0) return;
        var bar = document.createElement('div');
        bar.className = 'dd-story-voice-bar';
        bar.innerHTML =
            '<button type="button" class="dd-story-voice-btn" id="dd-story-voice-btn">' +
            '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/></svg>' +
            '<span>🎤 말해서 선택</span></button>' +
            '<span class="dd-story-voice-status" id="dd-story-voice-status"></span>';
        choicesEl.appendChild(bar);

        var btn = bar.querySelector('#dd-story-voice-btn');
        var status = bar.querySelector('#dd-story-voice-status');
        btn.addEventListener('click', function() {
            if (voiceListening) { stopVoice(); return; }
            startVoice(choices, btn, status);
        });
    }

    function startVoice(choices, btn, status) {
        try {
            voiceRec = new SR();
        } catch (e) { return; }
        voiceRec.lang = 'zh-CN';
        voiceRec.interimResults = false;
        voiceRec.maxAlternatives = 3;
        voiceListening = true;
        btn.classList.add('is-listening');
        status.textContent = '듣고 있어요… 선택지를 중국어로 말해보세요';

        voiceRec.onresult = function(ev) {
            var picked = -1, heardText = '';
            for (var r = 0; r < ev.results.length; r++) {
                for (var a = 0; a < ev.results[r].length; a++) {
                    var t = ev.results[r][a].transcript;
                    if (!heardText) heardText = t;
                    var m = matchChoiceByVoice(t, choices);
                    if (m !== -1) { picked = m; break; }
                }
                if (picked !== -1) break;
            }
            stopVoice();
            if (picked !== -1) {
                status.textContent = '들림: "' + heardText + '" ✓';
                var btns = choicesEl.querySelectorAll('.dd-story-choice-btn');
                if (btns[picked]) btns[picked].classList.add('is-voice-picked');
                setTimeout(function() { makeChoice(choices[picked].next); }, 600);
            } else {
                status.textContent = heardText ? ('"' + heardText + '" — 다시 말해볼까요?') : '잘 못 들었어요. 다시!';
            }
        };
        voiceRec.onerror = function(ev) {
            stopVoice();
            status.textContent = (ev && ev.error === 'not-allowed')
                ? '마이크 권한이 필요해요 🎤'
                : '인식 실패 — 다시 시도해주세요';
        };
        voiceRec.onend = function() { stopVoice(); };
        try { voiceRec.start(); } catch (e) { stopVoice(); }
    }

    function stopVoice() {
        voiceListening = false;
        if (voiceRec) { try { voiceRec.stop(); } catch (e) {} voiceRec = null; }
        var b = document.getElementById('dd-story-voice-btn');
        if (b) b.classList.remove('is-listening');
    }

    /* ════════════════════════════════════════════════════════
       스토리 맵 — 분기 트리 시각화 (가본 길 / 못 가본 엔딩)
       ════════════════════════════════════════════════════════ */
    function markVisited(nodeId) {
        var prog = getSavedProgress();
        if (!prog.visited) prog.visited = [];
        if (prog.visited.indexOf(nodeId) === -1) {
            prog.visited.push(nodeId);
            localStorage.setItem(STORAGE_KEY, JSON.stringify(prog));
        }
    }

    // start로부터 BFS 깊이 계산 → 열 배치
    function computeDepths() {
        var depth = {}; depth[startNode] = 0;
        var queue = [startNode];
        while (queue.length) {
            var id = queue.shift();
            var n = nodes[id];
            if (!n || !n.choices) continue;
            n.choices.forEach(function(c) {
                if (c.next && nodes[c.next] && depth[c.next] === undefined) {
                    depth[c.next] = depth[id] + 1;
                    queue.push(c.next);
                }
            });
        }
        // 연결 안 된 노드도 포함
        Object.keys(nodes).forEach(function(id) { if (depth[id] === undefined) depth[id] = 0; });
        return depth;
    }

    function buildStoryMapSVG() {
        var prog = getSavedProgress();
        var visited = prog.visited || [];
        var found = prog.endings || {};
        var depth = computeDepths();

        // 열(깊이)별 그룹
        var cols = {};
        Object.keys(nodes).forEach(function(id) {
            var d = depth[id];
            (cols[d] = cols[d] || []).push(id);
        });
        var maxDepth = Math.max.apply(null, Object.keys(cols).map(Number));
        var colW = 150, rowH = 64, padX = 40, padY = 40, r = 16;
        var pos = {};
        var maxRows = 0;
        Object.keys(cols).forEach(function(d) {
            cols[d].forEach(function(id, i) {
                pos[id] = { x: padX + Number(d) * colW, y: padY + i * rowH };
            });
            if (cols[d].length > maxRows) maxRows = cols[d].length;
        });
        var W = padX * 2 + maxDepth * colW + r * 2;
        var H = padY * 2 + (maxRows - 1) * rowH + r * 2;

        var edges = '', nodesSvg = '';
        Object.keys(nodes).forEach(function(id) {
            var n = nodes[id], p = pos[id];
            if (!n.choices) return;
            n.choices.forEach(function(c) {
                if (!c.next || !pos[c.next]) return;
                var q = pos[c.next];
                var seen = visited.indexOf(id) !== -1 && visited.indexOf(c.next) !== -1;
                edges += '<path d="M' + (p.x + r) + ',' + p.y + ' C' + (p.x + colW / 2) + ',' + p.y + ' ' +
                    (q.x - colW / 2) + ',' + q.y + ' ' + (q.x - r) + ',' + q.y + '" ' +
                    'fill="none" stroke="' + (seen ? 'var(--dd-story-map-on,#DB7F8E)' : 'rgba(0,0,0,0.12)') +
                    '" stroke-width="' + (seen ? 2.5 : 1.5) + '"/>';
            });
        });
        Object.keys(nodes).forEach(function(id) {
            var n = nodes[id], p = pos[id];
            var isVisited = visited.indexOf(id) !== -1;
            var isCurrent = id === currentNodeId;
            var isStart = id === startNode;
            var cls = 'dd-map-node';
            var label = '', fill = '#fff', stroke = 'rgba(0,0,0,0.18)';
            if (n.is_ending) {
                var et = n.ending_type || 'neutral';
                var ic = { good: '⭐', neutral: '🎯', bad: '💨' };
                var unlocked = !!found[id];
                label = unlocked ? (ic[et] || '🏁') : '🔒';
                fill = unlocked ? (et === 'good' ? '#FFF3D6' : et === 'bad' ? '#EAEAEA' : '#FDE7EC') : '#F3F3F3';
                stroke = unlocked ? 'var(--dd-story-map-on,#DB7F8E)' : 'rgba(0,0,0,0.15)';
            } else {
                label = isStart ? '▶' : (isVisited ? '✓' : '');
                fill = isVisited ? 'var(--dd-story-map-soft,#FDE7EC)' : '#fff';
                stroke = isVisited ? 'var(--dd-story-map-on,#DB7F8E)' : 'rgba(0,0,0,0.18)';
            }
            nodesSvg += '<g class="' + cls + (isCurrent ? ' is-current' : '') + '">' +
                '<circle cx="' + p.x + '" cy="' + p.y + '" r="' + r + '" fill="' + fill + '" stroke="' + stroke + '" stroke-width="' + (isCurrent ? 3.5 : 2) + '"/>' +
                '<text x="' + p.x + '" y="' + (p.y + 5) + '" text-anchor="middle" font-size="14">' + label + '</text>' +
                '</g>';
        });

        return '<svg viewBox="0 0 ' + W + ' ' + H + '" width="' + W + '" height="' + H + '" xmlns="http://www.w3.org/2000/svg">' +
            edges + nodesSvg + '</svg>';
    }

    function openStoryMap() {
        var allEndings = getEndingNodes();
        var found = (getSavedProgress().endings) || {};
        var foundCount = Object.keys(found).length;
        var visitedCount = ((getSavedProgress().visited) || []).length;

        var overlay = document.getElementById('dd-story-map-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'dd-story-map-overlay';
            overlay.className = 'dd-story-map-overlay';
            document.body.appendChild(overlay);
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay || e.target.closest('.dd-story-map-close')) closeStoryMap();
            });
        }
        overlay.innerHTML =
            '<div class="dd-story-map-modal">' +
              '<div class="dd-story-map-head">' +
                '<h3>🗺️ 스토리 맵</h3>' +
                '<button type="button" class="dd-story-map-close" aria-label="닫기">✕</button>' +
              '</div>' +
              '<div class="dd-story-map-legend">' +
                '<span><i class="lg-on"></i> 가본 길</span>' +
                '<span><i class="lg-cur"></i> 현재</span>' +
                '<span>⭐🎯💨 발견한 엔딩</span><span>🔒 미발견</span>' +
              '</div>' +
              '<div class="dd-story-map-canvas">' + buildStoryMapSVG() + '</div>' +
              '<div class="dd-story-map-foot">방문 장면 ' + visitedCount + '/' + totalNodes +
                ' · 엔딩 ' + foundCount + '/' + allEndings.length + '</div>' +
            '</div>';
        overlay.style.display = 'flex';
    }
    function closeStoryMap() {
        var o = document.getElementById('dd-story-map-overlay');
        if (o) o.style.display = 'none';
    }

    var mapBtn = document.getElementById('dd-story-map-btn');
    if (mapBtn) mapBtn.addEventListener('click', openStoryMap);
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeStoryMap(); });

    /* ─── Helpers ─── */
    function escHtml(str) {
        var d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    }

    function escAttr(str) {
        return (str || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function escRegex(str) {
        return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    /* ─── Init ─── */
    showScreen('cover');
    renderCollectionPreview();
})();
