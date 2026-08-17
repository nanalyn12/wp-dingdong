(function() {
    'use strict';

    var STORAGE_KEY = 'dd_learning_progress';

    /* ─────────────────────────────────────
       Core: localStorage CRUD
       ───────────────────────────────────── */
    function loadAll() {
        try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || {}; }
        catch(e) { return {}; }
    }

    function saveAll(data) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
    }

    window.DDProgress = {
        /* Record lesson visit */
        markVisited: function(lessonId) {
            var d = loadAll();
            if (!d[lessonId]) d[lessonId] = {};
            if (!d[lessonId].firstVisit) d[lessonId].firstVisit = Date.now();
            d[lessonId].lastVisit = Date.now();
            d[lessonId].visits = (d[lessonId].visits || 0) + 1;
            saveAll(d);
        },

        /* Mark tab completed */
        markTabDone: function(lessonId, tab) {
            var d = loadAll();
            if (!d[lessonId]) d[lessonId] = {};
            if (!d[lessonId].tabs) d[lessonId].tabs = {};
            d[lessonId].tabs[tab] = Date.now();
            d[lessonId].lastActivity = Date.now();
            this._checkComplete(d, lessonId);
            saveAll(d);
            this._renderWidget(lessonId);
            if (window.DDGamification) DDGamification.onTabComplete(tab);
        },

        /* Save quiz score */
        saveQuizScore: function(lessonId, score, total) {
            var d = loadAll();
            if (!d[lessonId]) d[lessonId] = {};
            d[lessonId].quizScore = score;
            d[lessonId].quizTotal = total;
            d[lessonId].quizDate = Date.now();
            if (!d[lessonId].quizBest || score > d[lessonId].quizBest) {
                d[lessonId].quizBest = score;
            }
            d[lessonId].lastActivity = Date.now();
            this._checkComplete(d, lessonId);
            saveAll(d);
            this._renderWidget(lessonId);
            if (window.DDGamification) DDGamification.onQuizPass(score, total);
        },

        /* Get lesson progress */
        get: function(lessonId) {
            var d = loadAll();
            return d[lessonId] || null;
        },

        /* Get all progress */
        getAll: function() {
            return loadAll();
        },

        /* Check complete: all 5 tabs + quiz done */
        _checkComplete: function(d, lessonId) {
            var entry = d[lessonId];
            if (!entry || !entry.tabs) return;
            var tabs = entry.tabs;
            var allTabs = ['content', 'slides', 'audiobook', 'storybook', 'quiz'];
            var done = allTabs.every(function(t) { return !!tabs[t]; });
            if (done && entry.quizScore !== undefined) {
                entry.completed = true;
                entry.completedAt = entry.completedAt || Date.now();
                /* Schedule reviews: 1d, 3d, 7d, 30d */
                if (!entry.reviews) {
                    var now = Date.now();
                    entry.reviews = [
                        { day: 1,  at: now + 86400000,      done: false },
                        { day: 3,  at: now + 86400000 * 3,  done: false },
                        { day: 7,  at: now + 86400000 * 7,  done: false },
                        { day: 30, at: now + 86400000 * 30, done: false }
                    ];
                }
            }
        },

        /* Get due reviews */
        getDueReviews: function() {
            var d = loadAll();
            var now = Date.now();
            var due = [];
            Object.keys(d).forEach(function(lid) {
                var entry = d[lid];
                if (!entry.reviews) return;
                entry.reviews.forEach(function(r) {
                    if (!r.done && r.at <= now) {
                        due.push({ lessonId: lid, day: r.day, title: entry.title || '' });
                    }
                });
            });
            return due;
        },

        /* Mark review done */
        markReviewDone: function(lessonId, day) {
            var d = loadAll();
            if (!d[lessonId] || !d[lessonId].reviews) return;
            d[lessonId].reviews.forEach(function(r) {
                if (r.day === day) r.done = true;
            });
            saveAll(d);
        },

        /* ─── Progress Widget ─── */
        _renderWidget: function(lessonId) {
            var container = document.getElementById('dd-progress-widget');
            if (!container) return;

            var entry = this.get(lessonId);
            if (!entry) {
                container.innerHTML = '';
                return;
            }

            var allTabs = [
                { key: 'content',   label: '학습' },
                { key: 'slides',    label: '슬라이드' },
                { key: 'audiobook', label: '오디오북' },
                { key: 'storybook', label: '스토리북' },
                { key: 'quiz',      label: '퀴즈' }
            ];
            var tabs = entry.tabs || {};
            var doneCount = allTabs.filter(function(t) { return !!tabs[t.key]; }).length;
            var pct = Math.round((doneCount / allTabs.length) * 100);

            var html = '<div class="dd-pw-header">';
            html += '<span class="dd-pw-title">학습 진도</span>';
            html += '<span class="dd-pw-pct">' + pct + '%</span>';
            html += '</div>';

            html += '<div class="dd-pw-bar"><div class="dd-pw-fill" style="width:' + pct + '%;"></div></div>';

            html += '<div class="dd-pw-tabs">';
            allTabs.forEach(function(t) {
                var done = !!tabs[t.key];
                html += '<span class="dd-pw-tab' + (done ? ' is-done' : '') + '">';
                html += (done ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>' : '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>');
                html += ' ' + t.label;
                html += '</span>';
            });
            html += '</div>';

            if (entry.quizBest !== undefined) {
                html += '<div class="dd-pw-quiz">퀴즈 최고 점수: <strong>' + entry.quizBest + '/' + (entry.quizTotal || '?') + '</strong></div>';
            }

            if (entry.completed) {
                html += '<div class="dd-pw-complete">학습 완료!</div>';
            }

            container.innerHTML = html;
        },

        /* ─── Review Banner ─── */
        renderReviewBanner: function() {
            var banner = document.getElementById('dd-review-banner');
            if (!banner) return;

            var due = this.getDueReviews();
            if (due.length === 0) {
                banner.style.display = 'none';
                return;
            }

            var html = '<div class="dd-rb-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>';
            html += '<div class="dd-rb-text">';
            html += '<strong>복습할 강의가 ' + due.length + '개 있습니다</strong>';
            html += '<p>에빙하우스 망각곡선에 따라 최적의 복습 시점입니다.</p>';
            html += '</div>';

            banner.innerHTML = html;
            banner.style.display = 'flex';
        },

        /* Init on lesson page */
        initLesson: function(lessonId, title) {
            var d = loadAll();
            if (!d[lessonId]) d[lessonId] = {};
            d[lessonId].title = title;
            saveAll(d);

            this.markVisited(lessonId);
            this._renderWidget(lessonId);
            this.renderReviewBanner();
            if (window.DDGamification) DDGamification.onLessonVisit();

            /* Auto-mark review done if revisiting */
            if (d[lessonId] && d[lessonId].reviews) {
                var now = Date.now();
                var self = this;
                d[lessonId].reviews.forEach(function(r) {
                    if (!r.done && r.at <= now) {
                        self.markReviewDone(lessonId, r.day);
                    }
                });
            }
        }
    };

    /* ─────────────────────────────────────
       Auto-track tab switches
       ───────────────────────────────────── */
    var tabMap = {
        'content':   'content',
        'slides':    'slides',
        'audiobook': 'audiobook',
        'storybook': 'storybook',
        'quiz':      'quiz'
    };

    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.dd-tab');
        if (!btn) return;
        var tabId = btn.getAttribute('data-tab');
        var mapped = tabMap[tabId];
        if (!mapped) return;

        var lessonEl = document.querySelector('[data-lesson-id]');
        if (!lessonEl) return;
        var lessonId = lessonEl.getAttribute('data-lesson-id');
        if (!lessonId) return;

        /* Mark tab as done after 5 seconds on it */
        clearTimeout(window._ddTabTimer);
        window._ddTabTimer = setTimeout(function() {
            DDProgress.markTabDone(lessonId, mapped);
        }, 5000);
    });
})();
