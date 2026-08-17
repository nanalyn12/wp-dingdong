/**
 * DD Pronunciation Coach (발음 코치)
 *
 * Web Speech API SpeechRecognition으로 학습자 발음을 인식하고,
 * Gemini AI로 성조 분석 + 한국인 특유 오류 + 개선 팁을 제공한다.
 *
 * Integration:
 *   - 핵심표현 카드 (.dd-key-expr-card)에 마이크 버튼 추가
 *   - 오디오북 항목 (.dd-ab-item)에 마이크 버튼 추가
 *   - DDSRS.recordReview()로 발음 점수 SRS 피드백
 *
 * localStorage: dd_pronunciation_history
 */
(function() {
    'use strict';

    var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) return; // 미지원 브라우저

    var STORAGE_KEY = 'dd_pronunciation_history';
    var synth = window.speechSynthesis;
    var isRecording = false;
    var currentTarget = null;
    var activeRecognition = null;

    /* ─── 결과 모달 (중앙 팝업) ─────────────────────────────
       좁은 카드 칼럼에 결과를 욱여넣으면 줄바꿈이 깨지므로
       화면 중앙 모달에 넓게 표시한다. body 요소를 렌더 대상으로 반환. */
    var modalEls = null;
    function ensurePronModal() {
        if (modalEls) return modalEls;
        var overlay = document.createElement('div');
        overlay.className = 'dd-pron-modal-overlay';
        overlay.innerHTML =
            '<div class="dd-pron-modal" role="dialog" aria-modal="true" aria-label="발음 분석 결과">' +
                '<button class="dd-pron-modal-close" type="button" aria-label="닫기">&times;</button>' +
                '<div class="dd-pron-modal-body"></div>' +
            '</div>';
        document.body.appendChild(overlay);

        var close = function () { closePronModal(); };
        overlay.querySelector('.dd-pron-modal-close').addEventListener('click', close);
        overlay.addEventListener('mousedown', function (e) {
            if (e.target === overlay) close(); /* 바깥 클릭으로 닫기 */
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.classList.contains('is-open')) close();
        });

        modalEls = { overlay: overlay, body: overlay.querySelector('.dd-pron-modal-body') };
        return modalEls;
    }
    function openPronModal() {
        var m = ensurePronModal();
        m.overlay.classList.add('is-open');
        document.body.classList.add('dd-pron-modal-lock');
        return m.body;
    }
    function closePronModal() {
        if (!modalEls) return;
        modalEls.overlay.classList.remove('is-open');
        document.body.classList.remove('dd-pron-modal-lock');
        /* 녹음 중 닫으면 인식 중단 */
        if (isRecording && activeRecognition) { try { activeRecognition.abort(); } catch (e) {} }
        if (synth) synth.cancel();
    }

    /* ─── localStorage ─── */
    function loadHistory() {
        try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || {}; }
        catch (e) { return {}; }
    }
    function saveHistory(data) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
    }

    /* ─── 문자열 유사도 (Levenshtein) ─── */
    function similarity(a, b) {
        if (!a || !b) return 0;
        a = a.replace(/\s+/g, ''); b = b.replace(/\s+/g, '');
        if (a === b) return 100;
        var len = Math.max(a.length, b.length);
        if (len === 0) return 100;
        var matrix = [];
        for (var i = 0; i <= a.length; i++) { matrix[i] = [i]; }
        for (var j = 0; j <= b.length; j++) { matrix[0][j] = j; }
        for (var i2 = 1; i2 <= a.length; i2++) {
            for (var j2 = 1; j2 <= b.length; j2++) {
                var cost = a[i2-1] === b[j2-1] ? 0 : 1;
                matrix[i2][j2] = Math.min(matrix[i2-1][j2]+1, matrix[i2][j2-1]+1, matrix[i2-1][j2-1]+cost);
            }
        }
        var dist = matrix[a.length][b.length];
        return Math.round((1 - dist / len) * 100);
    }

    /* ─── TTS 참조 재생 ─── */
    function playReference(text, callback) {
        if (!synth || !text) { if (callback) callback(); return; }
        synth.cancel();
        var utter = new SpeechSynthesisUtterance(text);
        utter.lang = 'zh-CN';
        utter.rate = 0.85;
        utter.onend = function() { if (callback) callback(); };
        utter.onerror = function() { if (callback) callback(); };
        synth.speak(utter);
    }

    /* ─── 음성 인식 ─── */
    function startRecognition(targetZh, targetPinyin, targetKo, feedbackEl, onDone) {
        if (isRecording) { if (onDone) onDone(); return; }
        isRecording = true;

        /* 결과는 중앙 모달에 표시 — feedbackEl(좁은 카드 칸)은 더 이상 렌더 대상이 아님 */
        var panel = openPronModal();

        /* "딩딩아" 호출어 대기가 마이크를 쓰고 있으면 잠시 양보 — 동시에 켜지면 'aborted' 발생 */
        if (window.DDWake && window.DDWake.suspend) window.DDWake.suspend();
        var wakeResumed = false;
        var resumeWake = function () {
            if (wakeResumed) return;
            wakeResumed = true;
            if (window.DDWake && window.DDWake.resume) window.DDWake.resume();
        };

        var recognition = new SpeechRecognition();
        recognition.lang = 'zh-CN';
        recognition.continuous = false;
        recognition.interimResults = false;
        recognition.maxAlternatives = 3;
        activeRecognition = recognition;

        panel.innerHTML =
            '<div class="dd-pron-modal-target">' + escapeHtml(targetZh) +
                (targetPinyin ? '<span class="dd-pron-modal-pinyin">' + escapeHtml(targetPinyin) + '</span>' : '') +
            '</div>' +
            '<div class="dd-pron-status dd-pron-recording">🎙️ 지금 따라 말해보세요...</div>';

        recognition.onresult = function(event) {
            var result = event.results[0][0];
            var recognized = result.transcript;
            var confidence = Math.round(result.confidence * 100);
            var score = similarity(targetZh, recognized);

            // 기록 저장
            var history = loadHistory();
            var key = targetZh;
            if (!history[key]) history[key] = { attempts: [], bestScore: 0 };
            history[key].attempts.push({ text: recognized, score: score, confidence: confidence, timestamp: Date.now() });
            if (history[key].attempts.length > 10) history[key].attempts = history[key].attempts.slice(-10);
            if (score > history[key].bestScore) history[key].bestScore = score;
            saveHistory(history);

            // SRS 피드백
            if (window.DDSRS) {
                var quality = score >= 90 ? 5 : score >= 70 ? 4 : score >= 50 ? 3 : score >= 30 ? 2 : 1;
                DDSRS.recordReview(targetZh, quality, 'pronunciation');
            }

            // 기본 피드백 표시
            renderBasicFeedback(panel, targetZh, recognized, score, confidence, history[key]);

            // Gemini 상세 분석 (API 키 있을 때만)
            requestGeminiAnalysis(targetZh, targetPinyin, targetKo, recognized, score, panel);
        };

        recognition.onerror = function(event) {
            isRecording = false;
            activeRecognition = null;
            resumeWake();
            var msg;
            if (event.error === 'no-speech') msg = '음성이 감지되지 않았습니다. 다시 시도해주세요.';
            else if (event.error === 'not-allowed') msg = '마이크 권한을 허용해주세요.';
            else if (event.error === 'aborted') { if (onDone) onDone(); return; } /* 사용자가 닫음 */
            else msg = '인식 오류: ' + event.error;
            renderError(panel, msg);
            if (onDone) onDone();
        };

        recognition.onend = function() {
            isRecording = false;
            activeRecognition = null;
            resumeWake();
            if (onDone) onDone();
        };

        /* 호출어 인식이 마이크를 완전히 놓을 시간을 잠깐 주고 시작 (중첩 abort 방지) */
        setTimeout(function () {
            try { recognition.start(); }
            catch (e) {
                isRecording = false;
                activeRecognition = null;
                resumeWake();
                renderError(panel, '마이크를 시작할 수 없어요. 다시 시도해주세요.');
                if (onDone) onDone();
            }
        }, (window.DDWake && window.DDWake.suspend) ? 250 : 0);
    }

    /* 에러도 모달 안에서 — 닫기/다시 시도 버튼 포함 */
    function renderError(panel, message) {
        panel.innerHTML =
            '<div class="dd-pron-status dd-pron-error">' + escapeHtml(message) + '</div>' +
            '<div class="dd-pron-actions"><button class="dd-pron-listen-btn dd-pron-close-btn" type="button">닫기</button></div>';
        var closeBtn = panel.querySelector('.dd-pron-close-btn');
        if (closeBtn) closeBtn.addEventListener('click', closePronModal);
    }

    /* ─── 기본 피드백 렌더링 ─── */
    function renderBasicFeedback(el, target, recognized, score, confidence, history) {
        var grade = score >= 90 ? 'excellent' : score >= 70 ? 'good' : score >= 50 ? 'fair' : 'retry';
        var emoji = score >= 90 ? '🎉' : score >= 70 ? '👍' : score >= 50 ? '🤔' : '💪';
        var msg = score >= 90 ? '완벽해요!' : score >= 70 ? '좋아요!' : score >= 50 ? '괜찮아요, 조금 더!' : '다시 시도해봐요!';

        var html = '<div class="dd-pron-result dd-pron-' + grade + '">';
        html += '<div class="dd-pron-score-row">';
        html += '<span class="dd-pron-emoji">' + emoji + '</span>';
        html += '<div class="dd-pron-score-info">';
        html += '<span class="dd-pron-score">' + score + '<small>점</small></span>';
        html += '<span class="dd-pron-msg">' + msg + '</span>';
        html += '</div>';
        html += '</div>';

        html += '<div class="dd-pron-compare">';
        html += '<div class="dd-pron-target"><span class="dd-pron-label">목표</span><span class="dd-pron-text">' + escapeHtml(target) + '</span></div>';
        html += '<div class="dd-pron-spoken"><span class="dd-pron-label">인식</span><span class="dd-pron-text">' + escapeHtml(recognized) + '</span></div>';
        html += '</div>';

        if (history && history.bestScore > 0) {
            html += '<div class="dd-pron-best">최고 점수: ' + history.bestScore + '점 (시도 ' + history.attempts.length + '회)</div>';
        }

        html += '<div class="dd-pron-actions">';
        html += '<button class="dd-pron-retry-btn">🎙️ 다시 시도</button>';
        html += '<button class="dd-pron-listen-btn">🔊 다시 듣기</button>';
        html += '</div>';

        // Gemini 분석 영역 (로딩 표시용)
        html += '<div class="dd-pron-ai-analysis" id="dd-pron-ai-area"></div>';

        html += '</div>';
        el.innerHTML = html;

        // 버튼 이벤트
        var retryBtn = el.querySelector('.dd-pron-retry-btn');
        var listenBtn = el.querySelector('.dd-pron-listen-btn');
        if (retryBtn) {
            retryBtn.addEventListener('click', function() {
                startRecognition(target, '', '', el);
            });
        }
        if (listenBtn) {
            listenBtn.addEventListener('click', function() {
                playReference(target);
            });
        }
    }

    /* ─── Gemini 상세 분석 ─── */
    function requestGeminiAnalysis(targetZh, targetPinyin, targetKo, recognized, score, feedbackEl) {
        var apiKey = '';
        if (window.DDApiKeyManager) apiKey = DDApiKeyManager.getKey();
        if (!apiKey) return; // API 키 없으면 기본 피드백만

        var aiArea = feedbackEl.querySelector('#dd-pron-ai-area');
        if (!aiArea) return;
        aiArea.innerHTML = '<div class="dd-pron-ai-loading">🤖 AI 발음 분석 중...</div>';

        var prompt = '당신은 한국인 중국어 학습자의 발음 코치입니다.\n\n'
            + '목표 문장: ' + targetZh + (targetPinyin ? ' (' + targetPinyin + ')' : '') + '\n'
            + '학습자 발음 인식 결과: ' + recognized + '\n'
            + '문자 일치율: ' + score + '%\n\n'
            + '아래 JSON으로만 응답하세요:\n'
            + '{"toneScore":0~100,"issues":["한국인이 자주 틀리는 발음 문제 1","문제 2"],"tips":["개선 팁 1","팁 2"],"encouragement":"격려 한마디"}';

        var body = {
            contents: [{ parts: [{ text: prompt }] }],
            generationConfig: { temperature: 0.3, maxOutputTokens: 512, responseMimeType: 'application/json' }
        };

        var models = ['gemini-3.1-flash-lite', 'gemini-3.5-flash'];
        // API 키는 URL 쿼리스트링이 아니라 x-goog-api-key 헤더로 보낸다.
        // ?key= 로 붙이면 브라우저 방문기록·Referer·중계 서버 로그에 키가 그대로 남는다.
        var url = 'https://generativelanguage.googleapis.com/v1beta/models/' + models[0] + ':generateContent';

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
            renderAiAnalysis(aiArea, parsed);
        })
        .catch(function() {
            aiArea.innerHTML = '';
        });
    }

    function renderAiAnalysis(el, analysis) {
        var html = '<div class="dd-pron-ai-result">';
        html += '<div class="dd-pron-ai-header">🤖 AI 발음 분석</div>';

        if (analysis.toneScore !== undefined) {
            html += '<div class="dd-pron-ai-tone">성조 정확도: <strong>' + analysis.toneScore + '%</strong></div>';
        }

        if (analysis.issues && analysis.issues.length > 0) {
            html += '<div class="dd-pron-ai-section"><span class="dd-pron-ai-label">⚠️ 주의할 점</span>';
            html += '<ul>';
            for (var i = 0; i < analysis.issues.length; i++) {
                html += '<li>' + escapeHtml(analysis.issues[i]) + '</li>';
            }
            html += '</ul></div>';
        }

        if (analysis.tips && analysis.tips.length > 0) {
            html += '<div class="dd-pron-ai-section"><span class="dd-pron-ai-label">💡 개선 팁</span>';
            html += '<ul>';
            for (var j = 0; j < analysis.tips.length; j++) {
                html += '<li>' + escapeHtml(analysis.tips[j]) + '</li>';
            }
            html += '</ul></div>';
        }

        if (analysis.encouragement) {
            html += '<div class="dd-pron-ai-encourage">' + escapeHtml(analysis.encouragement) + '</div>';
        }

        html += '</div>';
        el.innerHTML = html;
    }

    /* ─── 마이크 버튼 주입 ─── */
    function injectMicButtons() {
        // 1) 핵심표현 카드에 마이크 버튼 (액션 행 안에 삽입)
        document.querySelectorAll('.dd-key-expr-card').forEach(function(card) {
            if (card.querySelector('.dd-pron-mic')) return;
            var zh = (card.querySelector('.dd-ke-zh') || {}).textContent || '';
            var pinyin = (card.querySelector('.dd-ke-pinyin') || {}).textContent || '';
            var ko = (card.querySelector('.dd-ke-ko') || {}).textContent || '';
            if (!zh.trim()) return;

            var actions = card.querySelector('.dd-ke-actions');

            var mic = document.createElement('button');
            mic.className = 'dd-pron-mic dd-ke-btn dd-ke-btn--primary';
            mic.title = '말하기 — 발음 연습을 시작합니다';
            mic.setAttribute('aria-label', '발음 연습 시작');
            mic.innerHTML =
                '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>' +
                '<span>발음연습</span>';

            var feedback = document.createElement('div');
            feedback.className = 'dd-pron-feedback';
            feedback.style.display = 'none';

            mic.addEventListener('click', function(e) {
                e.stopPropagation();
                mic.classList.add('is-recording');
                // 먼저 참조 음성 재생, 그 후 녹음
                playReference(zh.trim(), function() {
                    setTimeout(function() {
                        startRecognition(zh.trim(), pinyin.trim(), ko.trim(), feedback, function() {
                            mic.classList.remove('is-recording');
                        });
                    }, 300);
                });
            });

            if (actions) {
                // 발음/저장 앞쪽(맨 왼쪽)에 마이크 추가 — 주요 액션으로 강조
                actions.insertBefore(mic, actions.firstChild);
            } else {
                card.appendChild(mic);
            }
            card.appendChild(feedback);
        });

        // 2) 오디오북 항목에 마이크 버튼
        document.querySelectorAll('.dd-ab-item').forEach(function(item) {
            if (item.querySelector('.dd-pron-mic')) return;
            var zh = item.getAttribute('data-zh') || '';
            if (!zh.trim()) return;

            var textEl = item.querySelector('.dd-ab-text');
            if (!textEl) return;

            var mic = document.createElement('button');
            mic.className = 'dd-pron-mic dd-pron-mic-ab';
            mic.title = '발음 연습';
            mic.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>';

            var feedback = document.createElement('div');
            feedback.className = 'dd-pron-feedback';
            feedback.style.display = 'none';

            mic.addEventListener('click', function(e) {
                e.stopPropagation();
                playReference(zh.trim(), function() {
                    setTimeout(function() {
                        startRecognition(zh.trim(), '', '', feedback);
                    }, 300);
                });
            });

            item.appendChild(mic);
            item.appendChild(feedback);
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    /* ─── Init ─── */
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(injectMicButtons, 500);
    });
})();
