/**
 * DD Adaptive Review Engine (SM-2)
 *
 * SuperMemo SM-2 알고리즘 기반 적응형 간격 반복(Spaced Repetition) 시스템.
 * 기존 DDProgress의 고정 간격(1,3,7,30일)을 대체하여
 * 각 학습 항목별 난이도 계수(easiness factor)에 따라 복습 간격을 개인화한다.
 *
 * localStorage keys:
 *   dd_srs_items  — 항목별 SRS 상태 (easiness, interval, repetitions, nextReview)
 *   dd_srs_stats  — 일일 통계 (todayReviewed, todayNew, streak, lastStudyDate)
 *
 * Integration:
 *   - DDProgress.saveQuizScore() 후 퀴즈 결과를 SRS에 반영
 *   - DDVocab 플래시카드 상태 변경 시 SRS 품질 매핑
 *   - 미니게임 결과 → 해당 단어 SRS 업데이트
 *   - getDueItems()로 복습 기한 항목 조회
 */
(function() {
    'use strict';

    var ITEMS_KEY = 'dd_srs_items';
    var STATS_KEY = 'dd_srs_stats';

    /* ─────────────────────────────────────
       SM-2 Core Algorithm
       ───────────────────────────────────── */

    /**
     * SM-2 간격 계산
     * @param {number} quality    회상 품질 0-5 (0=완전 망각, 5=완벽 기억)
     * @param {number} easiness   난이도 계수 (초기 2.5, 최소 1.3)
     * @param {number} interval   현재 간격 (일 단위)
     * @param {number} repetitions 연속 정답 횟수
     * @returns {{ easiness, interval, repetitions, nextReview }}
     */
    function sm2(quality, easiness, interval, repetitions) {
        if (quality >= 3) {
            // 정답: 간격 확장
            if (repetitions === 0) {
                interval = 1;
            } else if (repetitions === 1) {
                interval = 6;
            } else {
                interval = Math.round(interval * easiness);
            }
            repetitions++;
        } else {
            // 오답: 처음부터 다시
            repetitions = 0;
            interval = 1;
        }

        // 난이도 계수 조정
        easiness = easiness + 0.1 - (5 - quality) * (0.08 + (5 - quality) * 0.02);
        if (easiness < 1.3) easiness = 1.3;

        var nextReview = Date.now() + interval * 86400000;

        return {
            easiness: Math.round(easiness * 100) / 100,
            interval: interval,
            repetitions: repetitions,
            nextReview: nextReview
        };
    }

    /* ─────────────────────────────────────
       localStorage CRUD
       ───────────────────────────────────── */

    function loadItems() {
        try { return JSON.parse(localStorage.getItem(ITEMS_KEY)) || {}; }
        catch (e) { return {}; }
    }

    function saveItems(data) {
        localStorage.setItem(ITEMS_KEY, JSON.stringify(data));
    }

    function loadStats() {
        try { return JSON.parse(localStorage.getItem(STATS_KEY)) || defaultStats(); }
        catch (e) { return defaultStats(); }
    }

    function saveStats(data) {
        localStorage.setItem(STATS_KEY, JSON.stringify(data));
    }

    function defaultStats() {
        return {
            todayReviewed: 0,
            todayNew: 0,
            streak: 0,
            longestStreak: 0,
            lastStudyDate: null,
            totalReviews: 0
        };
    }

    /**
     * 항목 고유 키 생성 (zh 기반)
     */
    function itemKey(zh) {
        return 'zh_' + zh;
    }

    /**
     * 오늘 날짜 문자열 (YYYY-MM-DD)
     */
    function todayStr() {
        var d = new Date();
        return d.getFullYear() + '-' +
            String(d.getMonth() + 1).padStart(2, '0') + '-' +
            String(d.getDate()).padStart(2, '0');
    }

    /**
     * 일일 통계 초기화 (날짜 변경 시)
     */
    function ensureTodayStats() {
        var stats = loadStats();
        var today = todayStr();
        if (stats.lastStudyDate !== today) {
            // 어제 학습했으면 연속 유지, 아니면 리셋
            if (stats.lastStudyDate) {
                var last = new Date(stats.lastStudyDate);
                var now = new Date(today);
                var diff = Math.round((now - last) / 86400000);
                if (diff > 1) {
                    stats.streak = 0;
                }
            }
            stats.todayReviewed = 0;
            stats.todayNew = 0;
            saveStats(stats);
        }
        return stats;
    }

    /* ─────────────────────────────────────
       Public API: window.DDSRS
       ───────────────────────────────────── */

    window.DDSRS = {

        /**
         * SRS에 새 항목 등록 (이미 있으면 무시)
         * @param {{ zh, pinyin, ko, hsk, lessonId, lessonTitle }} word
         */
        addItem: function(word) {
            if (!word || !word.zh) return;
            var items = loadItems();
            var key = itemKey(word.zh);
            if (items[key]) return; // 이미 존재

            items[key] = {
                zh: word.zh,
                pinyin: word.pinyin || '',
                ko: word.ko || '',
                hsk: word.hsk || 0,
                lessonId: word.lessonId || 0,
                lessonTitle: word.lessonTitle || '',
                type: word.type || 'vocab', // vocab | expression | grammar
                easiness: 2.5,
                interval: 0,
                repetitions: 0,
                nextReview: Date.now(), // 즉시 복습 대상
                lastQuality: null,
                lastReviewed: null,
                createdAt: Date.now()
            };
            saveItems(items);
        },

        /**
         * 복수 항목 일괄 등록
         * @param {Array} words
         */
        addItems: function(words) {
            if (!words || !Array.isArray(words)) return;
            var items = loadItems();
            var changed = false;

            for (var i = 0; i < words.length; i++) {
                var w = words[i];
                if (!w || !w.zh) continue;
                var key = itemKey(w.zh);
                if (items[key]) continue;

                items[key] = {
                    zh: w.zh,
                    pinyin: w.pinyin || '',
                    ko: w.ko || '',
                    hsk: w.hsk || 0,
                    lessonId: w.lessonId || 0,
                    lessonTitle: w.lessonTitle || '',
                    type: w.type || 'vocab',
                    easiness: 2.5,
                    interval: 0,
                    repetitions: 0,
                    nextReview: Date.now(),
                    lastQuality: null,
                    lastReviewed: null,
                    createdAt: Date.now()
                };
                changed = true;
            }

            if (changed) saveItems(items);
        },

        /**
         * 학습 결과 기록 + SM-2 재계산
         * @param {string} zh       중국어 텍스트
         * @param {number} quality  회상 품질 0-5
         * @param {string} source   출처 (quiz|flashcard|game|listening|pronunciation)
         */
        recordReview: function(zh, quality, source) {
            if (!zh || quality === undefined) return;
            quality = Math.max(0, Math.min(5, Math.round(quality)));

            var items = loadItems();
            var key = itemKey(zh);

            // 항목이 없으면 자동 등록
            if (!items[key]) {
                items[key] = {
                    zh: zh,
                    pinyin: '',
                    ko: '',
                    hsk: 0,
                    lessonId: 0,
                    lessonTitle: '',
                    type: 'vocab',
                    easiness: 2.5,
                    interval: 0,
                    repetitions: 0,
                    nextReview: Date.now(),
                    lastQuality: null,
                    lastReviewed: null,
                    createdAt: Date.now()
                };
            }

            var item = items[key];
            var result = sm2(quality, item.easiness, item.interval, item.repetitions);

            item.easiness = result.easiness;
            item.interval = result.interval;
            item.repetitions = result.repetitions;
            item.nextReview = result.nextReview;
            item.lastQuality = quality;
            item.lastReviewed = Date.now();
            item.lastSource = source || 'unknown';

            saveItems(items);

            // 일일 통계 업데이트
            var stats = ensureTodayStats();
            stats.todayReviewed++;
            stats.totalReviews++;
            stats.lastStudyDate = todayStr();
            if (stats.todayReviewed === 1) {
                stats.streak++;
                if (stats.streak > (stats.longestStreak || 0)) {
                    stats.longestStreak = stats.streak;
                }
            }
            saveStats(stats);

            // 에러 로그에도 기록 (약점 분석기용)
            this._logToErrorLog(zh, quality, source);
        },

        /**
         * 복습 기한 도래 항목 조회
         * @param {number} limit 최대 반환 수 (기본 20)
         * @returns {Array} SRS 항목 배열 (기한 초과 순으로 정렬)
         */
        getDueItems: function(limit) {
            limit = limit || 20;
            var items = loadItems();
            var now = Date.now();
            var due = [];

            var keys = Object.keys(items);
            for (var i = 0; i < keys.length; i++) {
                var item = items[keys[i]];
                if (item.nextReview <= now) {
                    due.push(item);
                }
            }

            // 가장 오래 밀린 순 정렬
            due.sort(function(a, b) { return a.nextReview - b.nextReview; });

            return due.slice(0, limit);
        },

        /**
         * 전체 SRS 항목 조회
         */
        getAllItems: function() {
            return loadItems();
        },

        /**
         * 특정 항목 조회
         */
        getItem: function(zh) {
            var items = loadItems();
            return items[itemKey(zh)] || null;
        },

        /**
         * SRS 통계 조회
         */
        getStats: function() {
            return ensureTodayStats();
        },

        /**
         * SRS 요약 정보
         */
        getSummary: function() {
            var items = loadItems();
            var now = Date.now();
            var stats = ensureTodayStats();
            var total = 0, due = 0, learning = 0, mastered = 0, newItems = 0;

            var keys = Object.keys(items);
            for (var i = 0; i < keys.length; i++) {
                var item = items[keys[i]];
                total++;
                if (item.nextReview <= now) due++;
                if (item.repetitions === 0) newItems++;
                else if (item.interval >= 21) mastered++;
                else learning++;
            }

            return {
                total: total,
                due: due,
                learning: learning,
                mastered: mastered,
                newItems: newItems,
                todayReviewed: stats.todayReviewed,
                streak: stats.streak,
                longestStreak: stats.longestStreak || 0
            };
        },

        /**
         * 항목 제거
         */
        removeItem: function(zh) {
            var items = loadItems();
            delete items[itemKey(zh)];
            saveItems(items);
        },

        /* ─────────────────────────────────────
           품질 매핑 헬퍼
           ───────────────────────────────────── */

        /**
         * 퀴즈 정답/오답 → SM-2 품질 매핑
         * @param {boolean} correct  정답 여부
         * @param {boolean} hesitated  망설임 여부 (선택)
         * @returns {number} 0-5 품질
         */
        quizToQuality: function(correct, hesitated) {
            if (correct && !hesitated) return 5;
            if (correct && hesitated) return 4;
            return 1; // 오답
        },

        /**
         * 플래시카드 상태 → SM-2 품질 매핑
         * '모르겠어요' → 1, '학습 중' → 3, '완료' → 5
         */
        flashcardToQuality: function(status) {
            if (status === 'mastered') return 5;
            if (status === 'learning') return 3;
            return 1; // new
        },

        /**
         * 미니게임 점수 → SM-2 품질 매핑
         * @param {boolean} correct
         * @returns {number}
         */
        gameToQuality: function(correct) {
            return correct ? 4 : 1;
        },

        /* ─────────────────────────────────────
           에러 로그 (약점 분석기 연동)
           ───────────────────────────────────── */

        _logToErrorLog: function(zh, quality, source) {
            var LOG_KEY = 'dd_error_log';
            var MAX_LOG = 500;
            var log;
            try { log = JSON.parse(localStorage.getItem(LOG_KEY)) || []; }
            catch (e) { log = []; }

            log.push({
                timestamp: Date.now(),
                zh: zh,
                quality: quality,
                correct: quality >= 3,
                source: source || 'unknown'
            });

            // 롤링 윈도우: 최대 500건
            if (log.length > MAX_LOG) {
                log = log.slice(log.length - MAX_LOG);
            }

            localStorage.setItem(LOG_KEY, JSON.stringify(log));
        },

        /* ─────────────────────────────────────
           복습 배너 렌더링 (기존 DDProgress 배너 대체)
           ───────────────────────────────────── */

        renderReviewBanner: function() {
            var banner = document.getElementById('dd-review-banner');
            if (!banner) return;

            var summary = this.getSummary();

            if (summary.due === 0) {
                banner.style.display = 'none';
                return;
            }

            var html = '<div class="dd-rb-icon">'
                + '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">'
                + '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>';
            html += '<div class="dd-rb-text">';
            html += '<strong>복습할 단어가 ' + summary.due + '개 있습니다</strong>';
            html += '<p>SM-2 알고리즘이 최적의 복습 시점을 계산했습니다.</p>';
            html += '</div>';
            html += '<button class="dd-rb-btn" id="dd-srs-start-review">복습 시작</button>';

            banner.innerHTML = html;
            banner.style.display = 'flex';

            // 복습 시작 버튼
            var startBtn = document.getElementById('dd-srs-start-review');
            if (startBtn) {
                startBtn.addEventListener('click', function() {
                    DDSRS.openReviewSession();
                });
            }
        },

        /* ─────────────────────────────────────
           복습 세션 모달
           ───────────────────────────────────── */

        openReviewSession: function() {
            var due = this.getDueItems(15);
            if (due.length === 0) return;

            // 기존 모달 제거
            var existing = document.getElementById('dd-srs-modal');
            if (existing) existing.remove();

            var modal = document.createElement('div');
            modal.id = 'dd-srs-modal';
            modal.className = 'dd-srs-modal-overlay';

            var total = due.length;
            var currentIdx = 0;
            var results = [];

            function renderCard(idx) {
                var item = due[idx];
                if (!item) {
                    renderComplete();
                    return;
                }

                var progress = (idx + 1) + ' / ' + total;
                var pct = Math.round(((idx + 1) / total) * 100);

                var html = '<div class="dd-srs-modal">';
                html += '<div class="dd-srs-header">';
                html += '<span class="dd-srs-progress">' + progress + '</span>';
                html += '<button class="dd-srs-close" id="dd-srs-close">&times;</button>';
                html += '</div>';
                html += '<div class="dd-srs-bar"><div class="dd-srs-bar-fill" style="width:' + pct + '%"></div></div>';

                // 카드 앞면 (중국어)
                html += '<div class="dd-srs-card" id="dd-srs-card">';
                html += '<div class="dd-srs-front" id="dd-srs-front">';
                html += '<div class="dd-srs-zh">' + escapeHtml(item.zh) + '</div>';
                html += '<div class="dd-srs-pinyin">' + escapeHtml(item.pinyin) + '</div>';
                html += '<button class="dd-srs-reveal-btn" id="dd-srs-reveal">뜻 보기</button>';
                html += '</div>';

                // 카드 뒷면 (한국어 + 평가)
                html += '<div class="dd-srs-back" id="dd-srs-back" style="display:none;">';
                html += '<div class="dd-srs-zh">' + escapeHtml(item.zh) + '</div>';
                html += '<div class="dd-srs-pinyin">' + escapeHtml(item.pinyin) + '</div>';
                html += '<div class="dd-srs-ko">' + escapeHtml(item.ko) + '</div>';
                html += '<div class="dd-srs-ratings">';
                html += '<button class="dd-srs-rate dd-srs-rate-again" data-quality="1">다시</button>';
                html += '<button class="dd-srs-rate dd-srs-rate-hard" data-quality="3">어려움</button>';
                html += '<button class="dd-srs-rate dd-srs-rate-good" data-quality="4">좋음</button>';
                html += '<button class="dd-srs-rate dd-srs-rate-easy" data-quality="5">쉬움</button>';
                html += '</div>';

                // 간격 미리보기
                var previews = [1, 3, 4, 5];
                html += '<div class="dd-srs-intervals">';
                previews.forEach(function(q) {
                    var preview = sm2(q, item.easiness, item.interval, item.repetitions);
                    var label = preview.interval <= 0 ? '< 1일' :
                                preview.interval === 1 ? '1일' :
                                preview.interval + '일';
                    html += '<span class="dd-srs-interval-preview">' + label + '</span>';
                });
                html += '</div>';

                html += '</div>'; // back
                html += '</div>'; // card
                html += '</div>'; // modal

                modal.innerHTML = html;

                // Event: 뜻 보기
                var revealBtn = document.getElementById('dd-srs-reveal');
                if (revealBtn) {
                    revealBtn.addEventListener('click', function() {
                        document.getElementById('dd-srs-front').style.display = 'none';
                        document.getElementById('dd-srs-back').style.display = 'block';
                    });
                }

                // Event: 평가 버튼
                var rateButtons = modal.querySelectorAll('.dd-srs-rate');
                rateButtons.forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var quality = parseInt(this.getAttribute('data-quality'));
                        DDSRS.recordReview(item.zh, quality, 'srs_review');
                        results.push({ zh: item.zh, quality: quality });
                        currentIdx++;
                        renderCard(currentIdx);
                    });
                });

                // Event: 닫기
                var closeBtn = document.getElementById('dd-srs-close');
                if (closeBtn) {
                    closeBtn.addEventListener('click', function() {
                        modal.remove();
                    });
                }
            }

            function renderComplete() {
                var correct = results.filter(function(r) { return r.quality >= 3; }).length;
                var pct = total > 0 ? Math.round((correct / total) * 100) : 0;
                var emoji = pct >= 80 ? '🎉' : pct >= 50 ? '👍' : '💪';
                var msg = pct >= 80 ? '완벽해요! 기억력이 대단합니다!' :
                          pct >= 50 ? '좋아요! 꾸준히 복습하면 완벽해질 거예요!' :
                          '괜찮아요! 반복이 최고의 학습법입니다!';

                var summary = DDSRS.getSummary();

                var html = '<div class="dd-srs-modal">';
                html += '<div class="dd-srs-complete">';
                html += '<div class="dd-srs-complete-emoji">' + emoji + '</div>';
                html += '<h3>복습 완료!</h3>';
                html += '<p class="dd-srs-complete-msg">' + msg + '</p>';
                html += '<div class="dd-srs-complete-stats">';
                html += '<div class="dd-srs-stat"><span class="dd-srs-stat-num">' + correct + '/' + total + '</span><span class="dd-srs-stat-label">정답</span></div>';
                html += '<div class="dd-srs-stat"><span class="dd-srs-stat-num">' + summary.streak + '</span><span class="dd-srs-stat-label">연속 학습</span></div>';
                html += '<div class="dd-srs-stat"><span class="dd-srs-stat-num">' + summary.mastered + '</span><span class="dd-srs-stat-label">마스터</span></div>';
                html += '</div>';
                html += '<button class="dd-srs-close-btn" id="dd-srs-done">확인</button>';
                html += '</div></div>';

                modal.innerHTML = html;

                var doneBtn = document.getElementById('dd-srs-done');
                if (doneBtn) {
                    doneBtn.addEventListener('click', function() {
                        modal.remove();
                        // 배너 갱신
                        DDSRS.renderReviewBanner();
                    });
                }
            }

            document.body.appendChild(modal);
            renderCard(0);
        },

        /* ─────────────────────────────────────
           진도 위젯에 SRS 요약 추가
           ───────────────────────────────────── */

        renderWidgetAddon: function() {
            var container = document.getElementById('dd-progress-widget');
            if (!container) return;

            // 기존 위젯 아래에 SRS 정보 추가
            var existing = document.getElementById('dd-srs-widget-addon');
            if (existing) existing.remove();

            var summary = this.getSummary();
            if (summary.total === 0) return;

            var addon = document.createElement('div');
            addon.id = 'dd-srs-widget-addon';
            addon.className = 'dd-srs-widget-addon';

            var html = '<div class="dd-srs-widget-row">';
            html += '<span class="dd-srs-widget-label">SRS 단어</span>';
            html += '<span class="dd-srs-widget-value">' + summary.total + '개</span>';
            html += '</div>';

            if (summary.due > 0) {
                html += '<div class="dd-srs-widget-row dd-srs-widget-due">';
                html += '<span class="dd-srs-widget-label">복습 대기</span>';
                html += '<span class="dd-srs-widget-value dd-srs-due-badge">' + summary.due + '개</span>';
                html += '</div>';
            }

            if (summary.streak > 0) {
                html += '<div class="dd-srs-widget-row">';
                html += '<span class="dd-srs-widget-label">🔥 연속</span>';
                html += '<span class="dd-srs-widget-value">' + summary.streak + '일</span>';
                html += '</div>';
            }

            addon.innerHTML = html;
            container.appendChild(addon);
        },

        /* ─────────────────────────────────────
           강의 진입 시 핵심표현 자동 등록
           ───────────────────────────────────── */

        initFromLesson: function(lessonId, lessonTitle, keyExpressions) {
            if (!keyExpressions || !Array.isArray(keyExpressions)) return;
            var words = keyExpressions.map(function(ke) {
                return {
                    zh: ke.zh,
                    pinyin: ke.pinyin || '',
                    ko: ke.ko || '',
                    hsk: ke.hsk || 0,
                    lessonId: lessonId,
                    lessonTitle: lessonTitle,
                    type: 'expression'
                };
            });
            this.addItems(words);
        }
    };

    /* ─────────────────────────────────────
       Utility
       ───────────────────────────────────── */

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
})();
