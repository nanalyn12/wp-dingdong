(function() {
    'use strict';

    // 탭 전환 (상단 탭바 + sticky 사이드 레일 양쪽 동기화)
    var tabs = document.querySelectorAll('.dd-tab');
    var panels = document.querySelectorAll('.dd-tab-panel');
    var railItems = document.querySelectorAll('.dd-feature-rail-item');

    function activateFeature(target) {
        if (!target) return;
        tabs.forEach(function(t) { t.classList.toggle('is-active', t.getAttribute('data-tab') === target); });
        panels.forEach(function(p) { p.classList.toggle('is-active', p.id === 'panel-' + target); });
        railItems.forEach(function(r) { r.classList.toggle('is-active', r.getAttribute('data-feature') === target); });
        // 방문 상태 기록 (다음 방문 시 done 표시)
        try {
            var visited = JSON.parse(localStorage.getItem('dd_tabs_visited') || '{}');
            visited[target] = true;
            localStorage.setItem('dd_tabs_visited', JSON.stringify(visited));
        } catch (e) {}
        markRailDone();
        // 탭 전환 시 스크롤 최상단으로 (사용자 혼란 방지)
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function markRailDone() {
        try {
            var visited = JSON.parse(localStorage.getItem('dd_tabs_visited') || '{}');
            railItems.forEach(function(r) {
                var key = r.getAttribute('data-feature');
                r.classList.toggle('is-done', !!visited[key]);
            });
        } catch (e) {}
    }
    markRailDone();

    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            activateFeature(this.getAttribute('data-tab'));
        });
    });

    railItems.forEach(function(item) {
        item.addEventListener('click', function() {
            activateFeature(this.getAttribute('data-feature'));
        });
    });

    // 레일 접기/펼치기 — body 클래스로 토글 (rail/toggle 둘 다 sibling이라 부모에서 제어)
    var railToggle = document.getElementById('dd-feature-rail-toggle');
    if (railToggle) {
        // 이전 상태 복원
        if (localStorage.getItem('dd_rail_collapsed') === '1') {
            document.body.classList.add('dd-rail-collapsed');
        }
        railToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            var collapsed = document.body.classList.toggle('dd-rail-collapsed');
            localStorage.setItem('dd_rail_collapsed', collapsed ? '1' : '0');
            railToggle.setAttribute('aria-label', collapsed ? '기능 레일 펼치기' : '기능 레일 접기');
        });
    }

    // 슬라이드 네비게이션
    var slides = document.querySelectorAll('.dd-slide');
    var prevBtn = document.getElementById('dd-slide-prev');
    var nextBtn = document.getElementById('dd-slide-next');
    var indicator = document.getElementById('dd-slide-indicator');
    var currentSlide = 0;

    function showSlide(index) {
        if (slides.length === 0) return;
        slides.forEach(function(s) { s.classList.remove('is-active'); });
        slides[index].classList.add('is-active');
        currentSlide = index;
        if (indicator) indicator.textContent = (index + 1) + ' / ' + slides.length;
        if (prevBtn) prevBtn.disabled = index === 0;
        if (nextBtn) nextBtn.disabled = index === slides.length - 1;
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', function() {
            if (currentSlide > 0) showSlide(currentSlide - 1);
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function() {
            if (currentSlide < slides.length - 1) showSlide(currentSlide + 1);
        });
    }

    if (slides.length > 0) showSlide(0);

    // 키보드 슬라이드 조작
    document.addEventListener('keydown', function(e) {
        var slidesPanel = document.getElementById('panel-slides');
        if (!slidesPanel || !slidesPanel.classList.contains('is-active')) return;
        if (e.key === 'ArrowLeft' && currentSlide > 0) showSlide(currentSlide - 1);
        if (e.key === 'ArrowRight' && currentSlide < slides.length - 1) showSlide(currentSlide + 1);
    });

    // ====== 퀴즈 완료 트래커 ======
    var quizItems = document.querySelectorAll('.dd-quiz-item');
    var quizTotal = quizItems.length;
    var quizAnswered = {};
    var quizCorrect = {};

    function markQuizAnswered(item, correct) {
        var idx = Array.prototype.indexOf.call(quizItems, item);
        if (idx === -1 || quizAnswered[idx]) return;
        quizAnswered[idx] = true;
        quizCorrect[idx] = correct;
        var answeredCount = Object.keys(quizAnswered).length;
        if (answeredCount === quizTotal && quizTotal > 0) {
            setTimeout(showQuizComplete, 600);
        }

        // SM-2 SRS 연동: 퀴즈 결과를 적응형 복습 엔진에 피드백
        if (window.DDSRS) {
            var quality = DDSRS.quizToQuality(correct, false);
            // 퀴즈 항목에서 중국어 텍스트 추출 시도
            var zhEl = item.querySelector('[data-answer]') || item.querySelector('.dd-quiz-question-zh');
            var zh = '';
            if (zhEl) {
                zh = zhEl.getAttribute('data-answer') || zhEl.textContent.trim();
            }
            // fill 타입: sentence_zh에서 답어 추출
            if (!zh) {
                var answerAttr = item.getAttribute('data-answer');
                if (answerAttr) zh = answerAttr;
            }
            if (zh) {
                DDSRS.recordReview(zh, quality, 'quiz');
            }
        }
    }

    function showQuizComplete() {
        var correctCount = 0;
        for (var k in quizCorrect) { if (quizCorrect[k]) correctCount++; }
        var pct = Math.round((correctCount / quizTotal) * 100);
        var grade = pct >= 80 ? 'excellent' : pct >= 50 ? 'good' : 'retry';
        var emoji = pct >= 80 ? '🎉' : pct >= 50 ? '👍' : '💪';
        var msg = pct >= 80 ? '대단해요! 완벽에 가까워요!' : pct >= 50 ? '잘했어요! 조금만 더 복습하면 완벽!' : '괜찮아요! 복습하고 다시 도전해봐요!';

        var existing = document.querySelector('.dd-quiz-complete');
        if (existing) existing.remove();

        var panel = document.createElement('div');
        panel.className = 'dd-quiz-complete dd-quiz-complete-' + grade;
        panel.innerHTML =
            '<div class="dd-qc-confetti" id="dd-qc-confetti"></div>' +
            '<div class="dd-qc-content">' +
                '<div class="dd-qc-emoji">' + emoji + '</div>' +
                '<h3 class="dd-qc-title">퀴즈 완료!</h3>' +
                '<p class="dd-qc-msg">' + msg + '</p>' +
                '<div class="dd-qc-score">' +
                    '<div class="dd-qc-ring">' +
                        '<svg viewBox="0 0 100 100"><circle cx="50" cy="50" r="42" stroke="#eee" stroke-width="8" fill="none"/>' +
                        '<circle class="dd-qc-ring-fill" cx="50" cy="50" r="42" stroke-width="8" fill="none" stroke-dasharray="264" stroke-dashoffset="' + (264 - (264 * pct / 100)) + '" stroke-linecap="round"/></svg>' +
                        '<span class="dd-qc-pct">' + pct + '<small>%</small></span>' +
                    '</div>' +
                    '<p class="dd-qc-detail">' + correctCount + ' / ' + quizTotal + ' 정답</p>' +
                '</div>' +
                '<div class="dd-qc-actions">' +
                    '<button class="dd-qc-btn dd-qc-review">오답 복습</button>' +
                    '<button class="dd-qc-btn dd-qc-retry">다시 풀기</button>' +
                '</div>' +
            '</div>';

        var quizPanel = document.getElementById('panel-quiz');
        if (quizPanel) quizPanel.appendChild(panel);

        // 학습 진도에 퀴즈 점수 저장
        if (window.DDProgress) {
            var lessonEl = document.querySelector('[data-lesson-id]');
            if (lessonEl) {
                DDProgress.saveQuizScore(lessonEl.getAttribute('data-lesson-id'), correctCount, quizTotal);
            }
        }

        // 컨페티 애니메이션
        if (pct >= 50) launchConfetti(document.getElementById('dd-qc-confetti'));

        // 링 애니메이션 트리거
        requestAnimationFrame(function() {
            panel.classList.add('is-visible');
        });

        // 오답 복습 버튼
        panel.querySelector('.dd-qc-review').addEventListener('click', function() {
            panel.style.display = 'none';
            quizItems.forEach(function(item, i) {
                if (!quizCorrect[i]) {
                    item.classList.add('dd-quiz-highlight');
                    item.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else {
                    item.style.opacity = '0.4';
                }
            });
        });

        // 다시 풀기 버튼
        panel.querySelector('.dd-qc-retry').addEventListener('click', function() {
            quizAnswered = {};
            quizCorrect = {};
            panel.remove();
            quizItems.forEach(function(item) {
                item.style.opacity = '';
                item.classList.remove('dd-quiz-highlight');
                // 4지선다 리셋
                item.querySelectorAll('.dd-quiz-option').forEach(function(o) {
                    o.classList.remove('is-correct', 'is-wrong', 'is-disabled');
                });
                // fill 리셋
                var fillInput = item.querySelector('.dd-quiz-fill-input');
                var fillCheck = item.querySelector('.dd-quiz-fill-check');
                var fillResult = item.querySelector('.dd-quiz-fill-result');
                if (fillInput) { fillInput.disabled = false; fillInput.value = ''; }
                if (fillCheck) fillCheck.disabled = false;
                if (fillResult) { fillResult.textContent = ''; fillResult.className = 'dd-quiz-fill-result'; }
                // order 리셋
                var orderSlots = item.querySelector('.dd-quiz-order-slots');
                var orderCheck = item.querySelector('.dd-quiz-order-check');
                var orderResult = item.querySelector('.dd-quiz-order-result');
                if (orderSlots) orderSlots.innerHTML = '';
                if (orderCheck) orderCheck.disabled = false;
                if (orderResult) { orderResult.textContent = ''; orderResult.className = 'dd-quiz-order-result'; }
                item.querySelectorAll('.dd-quiz-word').forEach(function(w) { w.classList.remove('is-used'); w.style.pointerEvents = ''; });
                // 설명 숨김
                var expl = item.querySelector('.dd-quiz-explanation');
                if (expl) expl.classList.remove('is-visible');
            });
        });
    }

    function launchConfetti(container) {
        if (!container) return;
        var colors = ['#FF6B6B', '#4ECDC4', '#FFE66D', '#A8E6CF', '#DDA0DD', '#87CEEB', '#FF9800', '#E91E63'];
        for (var i = 0; i < 60; i++) {
            var piece = document.createElement('div');
            piece.className = 'dd-confetti-piece';
            piece.style.left = Math.random() * 100 + '%';
            piece.style.background = colors[Math.floor(Math.random() * colors.length)];
            piece.style.animationDelay = (Math.random() * 0.8) + 's';
            piece.style.animationDuration = (1.5 + Math.random() * 1.5) + 's';
            var size = 6 + Math.random() * 6;
            piece.style.width = size + 'px';
            piece.style.height = size * (0.4 + Math.random() * 0.6) + 'px';
            container.appendChild(piece);
        }
        setTimeout(function() { container.innerHTML = ''; }, 4000);
    }

    // 퀴즈 인터랙션 — 4지선다
    document.querySelectorAll('.dd-quiz-item:not(.dd-quiz-fill):not(.dd-quiz-order)').forEach(function(item) {
        var options = item.querySelectorAll('.dd-quiz-option');
        var explanation = item.querySelector('.dd-quiz-explanation');
        var correctIndex = parseInt(item.getAttribute('data-correct'), 10);

        options.forEach(function(option, idx) {
            option.addEventListener('click', function() {
                if (option.classList.contains('is-disabled')) return;
                options.forEach(function(o) { o.classList.add('is-disabled'); });
                var correct = idx === correctIndex;
                if (correct) {
                    option.classList.add('is-correct');
                } else {
                    option.classList.add('is-wrong');
                    if (options[correctIndex]) options[correctIndex].classList.add('is-correct');
                }
                if (explanation) explanation.classList.add('is-visible');
                markQuizAnswered(item, correct);
            });
        });
    });

    // 퀴즈 — 빈칸 채우기
    document.querySelectorAll('.dd-quiz-fill').forEach(function(item) {
        var input = item.querySelector('.dd-quiz-fill-input');
        var checkBtn = item.querySelector('.dd-quiz-fill-check');
        var result = item.querySelector('.dd-quiz-fill-result');
        var explanation = item.querySelector('.dd-quiz-explanation');
        var answer = (item.getAttribute('data-answer') || '').trim();

        function checkFill() {
            var val = (input.value || '').trim();
            if (!val) return;
            input.disabled = true;
            checkBtn.disabled = true;
            var correct = val === answer;
            if (correct) {
                result.textContent = '정답입니다! ✅';
                result.className = 'dd-quiz-fill-result is-correct';
            } else {
                result.textContent = '오답! 정답: ' + answer + ' ❌';
                result.className = 'dd-quiz-fill-result is-wrong';
            }
            if (explanation) explanation.classList.add('is-visible');
            markQuizAnswered(item, correct);
        }

        if (checkBtn) checkBtn.addEventListener('click', checkFill);
        if (input) input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') checkFill();
        });
    });

    // 퀴즈 — 어순 배열
    document.querySelectorAll('.dd-quiz-order').forEach(function(item) {
        var wordBtns = item.querySelectorAll('.dd-quiz-word');
        var slots = item.querySelector('.dd-quiz-order-slots');
        var resetBtn = item.querySelector('.dd-quiz-order-reset');
        var checkBtn = item.querySelector('.dd-quiz-order-check');
        var resultEl = item.querySelector('.dd-quiz-order-result');
        var explanation = item.querySelector('.dd-quiz-explanation');
        var correctOrder = JSON.parse(item.getAttribute('data-correct-order') || '[]');
        var answerText = item.getAttribute('data-answer-text') || '';
        var placed = [];

        wordBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (btn.classList.contains('is-used')) return;
                btn.classList.add('is-used');
                var idx = parseInt(btn.getAttribute('data-word-index'), 10);
                placed.push(idx);
                var tag = document.createElement('button');
                tag.className = 'dd-quiz-placed-word';
                tag.textContent = btn.textContent;
                tag.addEventListener('click', function() {
                    btn.classList.remove('is-used');
                    placed = placed.filter(function(i) { return i !== idx; });
                    tag.remove();
                });
                slots.appendChild(tag);
            });
        });

        if (resetBtn) resetBtn.addEventListener('click', function() {
            placed = [];
            wordBtns.forEach(function(b) { b.classList.remove('is-used'); });
            slots.innerHTML = '';
        });

        if (checkBtn) checkBtn.addEventListener('click', function() {
            if (placed.length !== wordBtns.length) return;
            var isCorrect = placed.length === correctOrder.length &&
                placed.every(function(v, i) { return v === correctOrder[i]; });
            wordBtns.forEach(function(b) { b.style.pointerEvents = 'none'; });
            checkBtn.disabled = true;
            if (isCorrect) {
                resultEl.textContent = '정답입니다! ✅';
                resultEl.className = 'dd-quiz-order-result is-correct';
            } else {
                resultEl.textContent = '오답! 정답: ' + answerText + ' ❌';
                resultEl.className = 'dd-quiz-order-result is-wrong';
            }
            if (explanation) explanation.classList.add('is-visible');
            markQuizAnswered(item, isCorrect);
        });
    });

    // 슬라이드 PDF 다운로드 버튼 — 모든 슬라이드를 한 페이지에 풀어 인쇄
    var slidePdfBtn = document.getElementById('dd-slide-pdf-btn');
    if (slidePdfBtn) {
        slidePdfBtn.addEventListener('click', function() {
            // 인쇄 시 모든 슬라이드가 보이도록 is-active 토글 해제
            var allSlides = document.querySelectorAll('.dd-slide');
            var activeIdx = -1;
            allSlides.forEach(function(s, i) {
                if (s.classList.contains('is-active')) { activeIdx = i; }
                s.classList.add('dd-slide--print-show');
            });
            document.body.classList.add('dd-print-slides');
            window.print();
            var restore = function() {
                document.body.classList.remove('dd-print-slides');
                allSlides.forEach(function(s) { s.classList.remove('dd-slide--print-show'); });
            };
            window.addEventListener('afterprint', function cleanup() {
                restore();
                window.removeEventListener('afterprint', cleanup);
            });
            // afterprint 안 뜨는 브라우저 대비
            setTimeout(restore, 5000);
        });
    }

    // 학습 자료 미리보기
    document.querySelectorAll('.dd-material-view-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var idx = btn.getAttribute('data-index');
            var url = btn.getAttribute('data-url');
            var type = btn.getAttribute('data-type');
            var viewer = document.getElementById('dd-mat-viewer-' + idx);
            if (!viewer) return;

            if (viewer.style.display !== 'none') {
                viewer.style.display = 'none';
                viewer.innerHTML = '';
                btn.textContent = '미리보기';
                return;
            }

            if (type === 'pdf') {
                viewer.innerHTML = '<iframe src="' + url + '" class="dd-mat-iframe"></iframe>';
            } else {
                var isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
                if (isLocal) {
                    viewer.innerHTML = '<p class="dd-mat-local-notice">Office 파일 미리보기는 사이트가 공개 배포된 후 사용할 수 있습니다. 파일을 다운로드해 주세요.</p>';
                } else {
                    viewer.innerHTML = '<iframe src="https://docs.google.com/gview?url=' + encodeURIComponent(url) + '&embedded=true" class="dd-mat-iframe"></iframe>';
                }
            }
            viewer.style.display = 'block';
            btn.textContent = '닫기';
        });
    });

    // 섹션 네비게이션 사이드바
    var sectionNav = document.getElementById('dd-section-nav');
    if (sectionNav) {
        var navItems = sectionNav.querySelectorAll('.dd-section-nav-item');
        var sectionIds = [];
        navItems.forEach(function(item) {
            var id = item.getAttribute('data-section');
            if (id) sectionIds.push(id);
        });

        // 부드러운 스크롤
        navItems.forEach(function(item) {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                var id = this.getAttribute('data-section');
                var target = document.getElementById(id);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });

        // 스크롤 시 현재 섹션 하이라이트
        var scrollTimeout;
        window.addEventListener('scroll', function() {
            if (scrollTimeout) return;
            scrollTimeout = setTimeout(function() {
                scrollTimeout = null;
                var scrollPos = window.scrollY + 120;
                var activeId = sectionIds[0];

                for (var i = 0; i < sectionIds.length; i++) {
                    var el = document.getElementById(sectionIds[i]);
                    if (el && el.offsetTop <= scrollPos) {
                        activeId = sectionIds[i];
                    }
                }

                navItems.forEach(function(item) {
                    if (item.getAttribute('data-section') === activeId) {
                        item.classList.add('is-active');
                    } else {
                        item.classList.remove('is-active');
                    }
                });
            }, 80);
        });

        // 학습 내용 탭이 아닐 때 네비 숨김
        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                var target = this.getAttribute('data-tab');
                sectionNav.style.display = target === 'content' ? '' : 'none';
            });
        });
    }

    // ── 노래 가사 뷰어 표시 토글 (한자/병음/한국어 ON/OFF) ──
    var lyricsViewer = document.querySelector('.dd-song-lyrics-viewer');
    if (lyricsViewer) {
        var toggles = lyricsViewer.querySelectorAll('.dd-song-toggle');
        toggles.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var key = btn.getAttribute('data-toggle');
                btn.classList.toggle('is-active');
                var attr = 'data-hide-' + key;
                if (btn.classList.contains('is-active')) {
                    lyricsViewer.removeAttribute(attr);
                } else {
                    lyricsViewer.setAttribute(attr, '1');
                }
            });
        });

        // 가사 클릭하면 해당 줄 하이라이트 (학습용 마킹)
        lyricsViewer.querySelectorAll('.dd-song-lyrics-line').forEach(function (line) {
            line.addEventListener('click', function () {
                lyricsViewer.querySelectorAll('.dd-song-lyrics-line').forEach(function (l) {
                    if (l !== line) l.classList.remove('is-active');
                });
                line.classList.toggle('is-active');
            });
        });
    }
})();
