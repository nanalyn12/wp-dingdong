(function() {
    'use strict';

    /* ─────────────────────────────────────
       Gemini API — 모델 fallback 체인
       ───────────────────────────────────── */
    /* Gemini 모델 fallback 체인 — 공용 상수 (dd-shared.js) */
    var GEMINI_MODELS = window.DDGeminiModels || ['gemini-2.5-flash'];
    var API_PREFIX = window.DDGeminiApiBase || 'https://generativelanguage.googleapis.com/v1beta/models/';

    function getModelUrl(model) {
        return API_PREFIX + model + ':generateContent';
    }

    /** Translate common API errors to Korean */
    function friendlyError(errMsg) {
        if (!errMsg) return 'API 오류가 발생했습니다.';
        var lower = errMsg.toLowerCase();
        if (lower.indexOf('spending cap') !== -1 || lower.indexOf('quota') !== -1 || lower.indexOf('exceeded') !== -1) {
            return 'API 사용량 한도를 초과했습니다.\nGoogle AI Studio → 설정에서 한도를 늘리거나 잠시 후 다시 시도하세요.';
        }
        if (lower.indexOf('api key') !== -1 || lower.indexOf('api_key') !== -1 || lower.indexOf('invalid') !== -1) {
            return 'API 키가 유효하지 않습니다. 키를 다시 확인해주세요.';
        }
        if (lower.indexOf('permission') !== -1 || lower.indexOf('denied') !== -1) {
            return 'API 접근 권한이 없습니다. API 키를 확인해주세요.';
        }
        if (lower.indexOf('rate limit') !== -1 || lower.indexOf('resource') !== -1) {
            return '요청이 너무 많습니다. 잠시 후 다시 시도해주세요.';
        }
        if (lower.indexOf('not found') !== -1 || lower.indexOf('404') !== -1) {
            return '모델을 사용할 수 없습니다. 잠시 후 다시 시도해주세요.';
        }
        return errMsg;
    }

    /** Check if error is retryable with a different model */
    function isRetryable(errMsg) {
        if (!errMsg) return false;
        var lower = errMsg.toLowerCase();
        return lower.indexOf('spending cap') !== -1 ||
               lower.indexOf('quota') !== -1 ||
               lower.indexOf('exceeded') !== -1 ||
               lower.indexOf('not found') !== -1 ||
               lower.indexOf('resource') !== -1 ||
               lower.indexOf('rate limit') !== -1 ||
               lower.indexOf('overloaded') !== -1 ||
               lower.indexOf('unavailable') !== -1;
    }

    /**
     * Call Gemini API with automatic model fallback.
     * Tries each model in GEMINI_MODELS until one succeeds.
     */
    async function callGeminiWithFallback(apiKey, body, extraConfig) {
        var lastError = null;

        for (var m = 0; m < GEMINI_MODELS.length; m++) {
            var model = GEMINI_MODELS[m];
            var url = getModelUrl(model);

            // Clone body with optional extra config
            var reqBody = JSON.parse(JSON.stringify(body));
            if (extraConfig) {
                Object.keys(extraConfig).forEach(function(k) {
                    reqBody.generationConfig[k] = extraConfig[k];
                });
            }

            try {
                var res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'x-goog-api-key': apiKey
                    },
                    body: JSON.stringify(reqBody)
                });
                var data = await res.json();

                if (res.ok) {
                    var text = data.candidates &&
                              data.candidates[0] &&
                              data.candidates[0].content &&
                              data.candidates[0].content.parts &&
                              data.candidates[0].content.parts[0] &&
                              data.candidates[0].content.parts[0].text;
                    if (text) {
                        return { text: text, model: model };
                    }
                    lastError = 'Empty response from API';
                    continue;
                }

                var errMsg = data.error ? data.error.message : 'API error ' + res.status;
                lastError = errMsg;

                // If this error is retryable, try the next model
                if (isRetryable(errMsg) && m < GEMINI_MODELS.length - 1) {
                    continue;
                }

                // Non-retryable error (e.g. invalid API key) — stop immediately
                return { error: friendlyError(errMsg) };

            } catch (e) {
                lastError = '네트워크 오류가 발생했습니다.';
                // Network errors are retryable
                if (m < GEMINI_MODELS.length - 1) continue;
            }
        }

        // All models failed
        return { error: friendlyError(lastError) };
    }

    /* ─────────────────────────────────────
       Chat UI elements
       ───────────────────────────────────── */
    var floating = document.getElementById('dd-ai-floating');
    var fab = document.getElementById('dd-ai-fab');
    var chatbox = document.getElementById('dd-ai-chatbox');
    var closeBtn = document.getElementById('dd-ai-chatbox-close');
    var messagesEl = document.getElementById('dd-ai-messages');
    var inputEl = document.getElementById('dd-ai-input');
    var sendBtn = document.getElementById('dd-ai-send');
    var keyNotice = document.getElementById('dd-ai-key-notice');
    var chatUI = document.getElementById('dd-ai-chat');

    if (!floating) return;

    var lessonContext = floating.getAttribute('data-lesson-context') || '';

    /* ─────────────────────────────────────
       Mode: 'tutor' (기본) | 'roleplay'
       ───────────────────────────────────── */
    var currentMode = 'tutor';
    var roleplayScenario = null;
    var conversationHistory = [];

    var SCENARIOS = [
        { id: 'restaurant', icon: '🍜', title: '식당 주문',   desc: '중국 식당에서 음식 주문하기',
          setup: '你是一家中国餐厅的服务员，正在为一位韩国游客点餐。餐厅菜单包括：宫保鸡丁(38元)、麻婆豆腐(28元)、鱼香肉丝(32元)、西红柿炒鸡蛋(22元)、米饭(3元)、可乐(5元)、啤酒(8元)。',
          opening: '欢迎光临！请问几位？需要看一下菜单吗？(환영합니다! 몇 분이세요? 메뉴를 보시겠어요?)' },
        { id: 'taxi', icon: '🚕', title: '택시 탑승',   desc: '택시를 타고 목적지까지',
          setup: '你是一名北京的出租车司机。乘客要去天安门广场。从当前位置到天安门大约15公里，需要30-40分钟，费用大约50元。你要和乘客聊天，介绍一下路上看到的景点。',
          opening: '您好！请问去哪儿？(안녕하세요! 어디로 가시나요?)' },
        { id: 'shopping', icon: '🛍️', title: '쇼핑/흥정',   desc: '시장에서 물건 사며 흥정하기',
          setup: '你是一个中国市场的小贩，卖茶叶和丝绸围巾。茶叶标价200元(可以降到80元)，围巾标价300元(可以降到120元)。顾客是韩国游客，你要热情推销但可以慢慢降价。',
          opening: '来看看！我们的茶叶是最好的龙井茶！要不要试试？(보세요! 우리 차는 최고 용정차예요! 한번 드셔볼래요?)' },
        { id: 'hotel', icon: '🏨', title: '호텔 체크인',   desc: '호텔에서 체크인하고 요청하기',
          setup: '你是一家上海五星级酒店的前台。客人预订了一间标准双人房，3晚，每晚680元。你要办理入住手续：确认姓名、护照、付款方式，并介绍早餐时间(7-10点)、WiFi密码、健身房位置等。',
          opening: '您好，欢迎入住上海国际大酒店！请问您有预订吗？(안녕하세요, 상하이 국제호텔에 오신 것을 환영합니다! 예약하셨나요?)' }
    ];

    function toggleChatbox() {
        var isOpen = chatbox.style.display !== 'none';
        chatbox.style.display = isOpen ? 'none' : 'flex';
        fab.querySelector('.dd-ai-fab-icon').style.display = isOpen ? '' : 'none';
        fab.querySelector('.dd-ai-fab-close').style.display = isOpen ? 'none' : '';
        if (!isOpen && inputEl) {
            setTimeout(function() { inputEl.focus(); }, 100);
        }
    }

    if (fab) fab.addEventListener('click', toggleChatbox);
    if (closeBtn) closeBtn.addEventListener('click', toggleChatbox);

    function updateVisibility() {
        if (window.DDApiKeyManager && window.DDApiKeyManager.hasKey()) {
            if (keyNotice) keyNotice.style.display = 'none';
            if (chatUI) chatUI.style.display = 'flex';
        } else {
            if (keyNotice) keyNotice.style.display = 'block';
            if (chatUI) chatUI.style.display = 'none';
        }
    }

    updateVisibility();
    window.addEventListener('dd-api-key-changed', updateVisibility);

    /* ─── Message rendering ─── */
    function addMessage(text, role, extra) {
        if (!messagesEl) return;
        var div = document.createElement('div');
        div.className = 'dd-ai-message ' + role;
        if (extra) div.className += ' ' + extra;
        div.innerHTML = formatMessage(text);
        messagesEl.appendChild(div);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function formatMessage(text) {
        return text
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\n/g, '<br>');
    }

    function addSystemCard(html) {
        if (!messagesEl) return;
        var div = document.createElement('div');
        div.className = 'dd-ai-message system-card';
        div.innerHTML = html;
        messagesEl.appendChild(div);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    /* ─── Tutor mode ─── */
    function getTutorPrompt() {
        return '당신은 한국인 학생과 중국어로 대화하는 친절한 중국인 친구 "叮叮"입니다. ' +
            '성격: 다정하고 밝으며 학생을 격려하는 따뜻한 말투를 씁니다. ' +
            '현재 학습 중인 강의 내용: ' + lessonContext + '. ' +
            '규칙: ' +
            '1. 한국어로 대화하되 중국어 표현을 자연스럽게 섞어 사용 ' +
            '2. 중국어 예문에는 반드시 병음과 한국어 번역 포함 ' +
            '3. 호칭 없이 바로 답변 (오빠, 언니, 형, 누나 등 호칭 사용 금지) ' +
            '4. 학생이 틀리면 친절하게 정정 ' +
            '5. 학생이 맞추면 칭찬 ' +
            '6. 가끔 중국 문화나 맛집 이야기를 곁들여 자연스러운 대화 유도 ' +
            '7. 답변은 3-5문장으로 간결하게';
    }

    /* ─── Roleplay mode ─── */
    function getRoleplayPrompt(scenario) {
        return '당신은 역할극 시뮬레이션의 NPC입니다. ' +
            '상황 설정: ' + scenario.setup + '\n\n' +
            '규칙:\n' +
            '1. 당신은 반드시 중국어로만 말하세요 (괄호 안에 한국어 번역 포함)\n' +
            '2. 학생의 중국어가 틀리면, 먼저 역할극 대사를 한 뒤 마지막에 짧게 교정해 주세요\n' +
            '3. 대화가 자연스럽게 진행되도록 하세요\n' +
            '4. 학생이 한국어로 말해도 중국어로 응답하세요 (한국어 번역 병기)\n' +
            '5. 3-4번의 주고받기 후 대화가 자연스럽게 마무리되면 "---역할극 종료---"를 포함하고,\n' +
            '   그 뒤에 평가를 아래 형식으로 작성하세요:\n' +
            '   **사용한 표현**: (학생이 잘 사용한 중국어 표현)\n' +
            '   **교정 사항**: (틀린 부분과 올바른 표현)\n' +
            '   **종합 평가**: (적절성/문법/유창성 각 점수 /10 + 한 줄 코멘트)\n' +
            '6. 답변은 2-4문장으로 자연스럽게';
    }

    /* ─── Send handler ─── */
    async function handleSend() {
        if (!inputEl) return;
        var text = inputEl.value.trim();
        if (!text) return;

        var apiKey = window.DDApiKeyManager ? window.DDApiKeyManager.getKey() : '';
        if (!apiKey) return;

        addMessage(text, 'user');
        inputEl.value = '';

        var systemPrompt;
        var contents = [];

        if (currentMode === 'roleplay' && roleplayScenario) {
            systemPrompt = getRoleplayPrompt(roleplayScenario);
            conversationHistory.push({ role: 'user', text: text });
            conversationHistory.forEach(function(h) {
                contents.push({ role: h.role, parts: [{ text: h.text }] });
            });
        } else {
            systemPrompt = getTutorPrompt();
            contents.push({ role: 'user', parts: [{ text: text }] });
        }

        // Loading indicator
        addMessage('...', 'ai', 'is-loading');

        var body = {
            contents: contents,
            systemInstruction: { parts: [{ text: systemPrompt }] },
            generationConfig: {
                temperature: 0.7,
                maxOutputTokens: 1024
            }
        };

        var result = await callGeminiWithFallback(apiKey, body);

        // Remove loading
        var loading = messagesEl.querySelector('.is-loading');
        if (loading) loading.remove();

        if (result.error) {
            addMessage(result.error, 'ai', 'is-error');
        } else if (result.text) {
            addMessage(result.text, 'ai');
            if (currentMode === 'roleplay') {
                conversationHistory.push({ role: 'model', text: result.text });
                if (result.text.indexOf('역할극 종료') !== -1) {
                    switchMode('tutor');
                }
            }
        }
    }

    if (sendBtn) sendBtn.addEventListener('click', handleSend);
    if (inputEl) {
        inputEl.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleSend();
            }
        });
    }

    /* ─── Mode switching ─── */
    function switchMode(mode) {
        currentMode = mode;
        var modeBar = document.getElementById('dd-ai-mode-bar');
        if (modeBar) {
            modeBar.querySelectorAll('.dd-ai-mode-btn').forEach(function(btn) {
                btn.classList.toggle('is-active', btn.getAttribute('data-mode') === mode);
            });
        }
        if (mode === 'tutor') {
            roleplayScenario = null;
            conversationHistory = [];
            if (inputEl) inputEl.placeholder = '질문을 입력하세요...';
        }
    }

    function startRoleplay(scenario) {
        roleplayScenario = scenario;
        currentMode = 'roleplay';
        conversationHistory = [];
        if (messagesEl) messagesEl.innerHTML = '';

        addSystemCard(
            '<div class="dd-rp-intro">' +
            '<span class="dd-rp-icon">' + scenario.icon + '</span>' +
            '<strong>' + scenario.title + '</strong>' +
            '<p>' + scenario.desc + '</p>' +
            '<small>중국어로 대화해 보세요. 한국어도 괜찮아요!</small>' +
            '</div>'
        );

        addMessage(scenario.opening, 'ai');
        conversationHistory.push({ role: 'model', text: scenario.opening });

        if (inputEl) {
            inputEl.placeholder = '중국어로 대답하세요...';
            inputEl.focus();
        }

        var modeBar = document.getElementById('dd-ai-mode-bar');
        if (modeBar) {
            modeBar.querySelectorAll('.dd-ai-mode-btn').forEach(function(btn) {
                btn.classList.toggle('is-active', btn.getAttribute('data-mode') === 'roleplay');
            });
        }
    }

    function showScenarioList() {
        if (!messagesEl) return;
        messagesEl.innerHTML = '';

        var html = '<div class="dd-rp-list">' +
            '<strong>역할극 시나리오 선택</strong>' +
            '<p>실전 상황을 골라 중국어로 대화해 보세요!</p>' +
            '<div class="dd-rp-grid">';
        SCENARIOS.forEach(function(s, i) {
            html += '<button class="dd-rp-card" data-rp-idx="' + i + '">' +
                '<span class="dd-rp-card-icon">' + s.icon + '</span>' +
                '<span class="dd-rp-card-title">' + s.title + '</span>' +
                '<span class="dd-rp-card-desc">' + s.desc + '</span>' +
                '</button>';
        });
        html += '</div></div>';
        addSystemCard(html);

        messagesEl.querySelectorAll('.dd-rp-card').forEach(function(card) {
            card.addEventListener('click', function() {
                startRoleplay(SCENARIOS[parseInt(card.getAttribute('data-rp-idx'))]);
            });
        });
    }

    /* ─── Build mode bar ─── */
    function buildModeBar() {
        if (!chatbox) return; // 플로팅 AI 채팅이 페이지에 없으면 모드바도 불필요
        var header = chatbox.querySelector('.dd-ai-chatbox-header');
        if (!header || document.getElementById('dd-ai-mode-bar')) return;

        var bar = document.createElement('div');
        bar.id = 'dd-ai-mode-bar';
        bar.className = 'dd-ai-mode-bar';
        bar.innerHTML =
            '<button class="dd-ai-mode-btn is-active" data-mode="tutor">' +
            '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>' +
            ' 튜터</button>' +
            '<button class="dd-ai-mode-btn" data-mode="roleplay">' +
            '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>' +
            ' 역할극</button>';

        header.after(bar);

        bar.querySelectorAll('.dd-ai-mode-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var mode = btn.getAttribute('data-mode');
                if (mode === 'roleplay') {
                    switchMode('roleplay');
                    showScenarioList();
                } else {
                    switchMode('tutor');
                    messagesEl.innerHTML = '';
                    addMessage('안녕~ 나 叮叮이야! 오늘 공부 같이 하자! 궁금한 거 있으면 뭐든 물어봐~', 'ai');
                }
            });
        });
    }

    buildModeBar();

    /* ─────────────────────────────────────
       작문 채점
       ───────────────────────────────────── */
    var writingSection = document.getElementById('section-writing');
    if (writingSection) {
        var wKeyNotice = document.getElementById('dd-writing-key-notice');
        var wForm = document.getElementById('dd-writing-form');
        var wInput = document.getElementById('dd-writing-input');
        var wCounter = document.getElementById('dd-writing-counter');
        var wSubmit = document.getElementById('dd-writing-submit');
        var wResult = document.getElementById('dd-writing-result');
        var expressions = [];

        try { expressions = JSON.parse(writingSection.getAttribute('data-expressions') || '[]'); } catch(e) {}

        function updateWritingVisibility() {
            if (window.DDApiKeyManager && window.DDApiKeyManager.hasKey()) {
                if (wKeyNotice) wKeyNotice.style.display = 'none';
                if (wForm) wForm.style.display = 'block';
            } else {
                if (wKeyNotice) wKeyNotice.style.display = 'block';
                if (wForm) wForm.style.display = 'none';
            }
        }
        updateWritingVisibility();
        window.addEventListener('dd-api-key-changed', updateWritingVisibility);

        if (wInput && wCounter) {
            wInput.addEventListener('input', function() {
                wCounter.textContent = wInput.value.length + '자';
            });
        }

        if (wSubmit) {
            wSubmit.addEventListener('click', async function() {
                var text = wInput ? wInput.value.trim() : '';
                if (!text) return;
                if (!window.DDApiKeyManager || !window.DDApiKeyManager.hasKey()) return;

                var apiKey = window.DDApiKeyManager.getKey();
                wSubmit.disabled = true;
                wSubmit.textContent = '채점 중...';
                wResult.style.display = 'none';

                var exprList = expressions.map(function(e) { return e.zh + '(' + e.ko + ')'; }).join(', ');

                var systemPrompt = '당신은 중국어 작문 채점 전문가입니다. 한국인 학습자가 쓴 중국어 문장을 채점합니다.\n' +
                    '한국인 학습자가 자주 혼동하는 지점(예: 把자문, 了의 완료/변화 용법, 양사, 是와 在의 구분)을 우선적으로 짚어주세요.\n' +
                    '이 강의의 핵심 어휘: ' + exprList + '\n\n' +
                    '반드시 아래 JSON 형식으로만 응답하세요:\n' +
                    '{\n' +
                    '  "score": 85,\n' +
                    '  "grammar_score": 80,\n' +
                    '  "vocab_score": 90,\n' +
                    '  "natural_score": 85,\n' +
                    '  "corrections": [\n' +
                    '    {"original": "틀린 부분", "corrected": "올바른 표현", "reason": "이유 (한국어)"}\n' +
                    '  ],\n' +
                    '  "vocab_used": ["사용한 핵심 어휘1", "어휘2"],\n' +
                    '  "vocab_unused": ["미사용 어휘1"],\n' +
                    '  "feedback": "전체 피드백 2-3문장 (한국어)",\n' +
                    '  "improved": "교정된 전체 문장 (중국어)"\n' +
                    '}';

                var body = {
                    contents: [{ parts: [{ text: '다음 중국어 작문을 채점해주세요:\n\n' + text }] }],
                    systemInstruction: { parts: [{ text: systemPrompt }] },
                    generationConfig: {
                        temperature: 0.3,
                        maxOutputTokens: 2048
                    }
                };

                var result = await callGeminiWithFallback(apiKey, body, {
                    responseMimeType: 'application/json'
                });

                if (result.error) {
                    wResult.innerHTML = '<div class="dd-wr-error-box">' +
                        '<p class="dd-wr-error">' + escHtml(result.error).replace(/\n/g, '<br>') + '</p>' +
                        '<a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener" class="dd-wr-error-link">Google AI Studio에서 확인하기</a>' +
                        '</div>';
                    wResult.style.display = 'block';
                } else if (result.text) {
                    var parsed;
                    try { parsed = JSON.parse(result.text); } catch(e) {
                        // Try extracting JSON from markdown code block
                        var match = result.text.match(/```(?:json)?\s*([\s\S]*?)```/);
                        if (match) {
                            try { parsed = JSON.parse(match[1]); } catch(e2) {}
                        }
                    }
                    if (parsed) {
                        renderWritingResult(parsed);
                    } else {
                        wResult.innerHTML = '<p class="dd-wr-error">채점 결과를 분석하지 못했습니다. 다시 시도해주세요.</p>';
                        wResult.style.display = 'block';
                    }
                }

                wSubmit.disabled = false;
                wSubmit.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> 채점하기';
            });
        }

        function renderWritingResult(r) {
            var html = '<div class="dd-wr-scores">';
            html += scoreBar('종합', r.score || 0, '#DB7F8E');
            html += scoreBar('문법', r.grammar_score || 0, '#5B8CDB');
            html += scoreBar('어휘 활용', r.vocab_score || 0, '#4CAF50');
            html += scoreBar('자연스러움', r.natural_score || 0, '#FF9800');
            html += '</div>';

            if (r.corrections && r.corrections.length > 0) {
                html += '<div class="dd-wr-corrections"><h5>교정 사항</h5>';
                r.corrections.forEach(function(c) {
                    html += '<div class="dd-wr-correction">';
                    html += '<span class="dd-wr-orig">' + escHtml(c.original) + '</span>';
                    html += ' → <span class="dd-wr-fixed">' + escHtml(c.corrected) + '</span>';
                    html += '<p class="dd-wr-reason">' + escHtml(c.reason) + '</p>';
                    html += '</div>';
                });
                html += '</div>';
            }

            if (r.improved) {
                html += '<div class="dd-wr-improved"><h5>교정된 문장</h5><p>' + escHtml(r.improved) + '</p></div>';
            }

            if (r.vocab_used && r.vocab_used.length > 0) {
                html += '<div class="dd-wr-vocab"><span class="dd-wr-vl dd-wr-used">사용한 어휘: ' + r.vocab_used.map(escHtml).join(', ') + '</span>';
                if (r.vocab_unused && r.vocab_unused.length > 0) {
                    html += ' <span class="dd-wr-vl dd-wr-unused">미사용: ' + r.vocab_unused.map(escHtml).join(', ') + '</span>';
                }
                html += '</div>';
            }

            if (r.feedback) {
                html += '<div class="dd-wr-feedback"><p>' + escHtml(r.feedback) + '</p></div>';
            }

            wResult.innerHTML = html;
            wResult.style.display = 'block';
        }

        function scoreBar(label, score, color) {
            return '<div class="dd-wr-score-row">' +
                '<span class="dd-wr-score-label">' + label + '</span>' +
                '<div class="dd-wr-score-bar"><div class="dd-wr-score-fill" style="width:' + Math.min(score, 100) + '%;background:' + color + ';"></div></div>' +
                '<span class="dd-wr-score-num">' + score + '</span>' +
                '</div>';
        }

        function escHtml(str) {
            var d = document.createElement('div');
            d.textContent = str || '';
            return d.innerHTML;
        }
    }
})();
