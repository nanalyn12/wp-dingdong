/**
 * Dingdong LMS Admin JavaScript
 * Vanilla JS using wp.apiFetch
 */

(function () {
    'use strict';

    const API_BASE = '/dingdong-lms/v1';

    // ===== Utility Functions =====

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showAlert(containerId, type, message) {
        const container = document.getElementById(containerId);
        if (!container) return;
        const alert = document.createElement('div');
        alert.className = `dd-alert dd-alert-${type}`;
        alert.textContent = message;
        container.prepend(alert);
        setTimeout(() => alert.remove(), 5000);
    }

    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.add('active');
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.remove('active');
    }

    function showElement(el) {
        if (typeof el === 'string') el = document.getElementById(el);
        if (el) el.classList.remove('dd-hidden');
    }

    function hideElement(el) {
        if (typeof el === 'string') el = document.getElementById(el);
        if (el) el.classList.add('dd-hidden');
    }

    function setProgress(barId, percent) {
        const bar = document.getElementById(barId);
        if (bar) bar.style.setProperty('--dd-progress', percent + '%');
    }

    /** 요청별 멱등키 — 응답이 유실돼도 서버에서 결과를 되찾는 데 쓴다. */
    function makeClientRef() {
        if (window.crypto && window.crypto.randomUUID) return window.crypto.randomUUID();
        return 'ref-' + Date.now() + '-' + Math.random().toString(16).slice(2);
    }

    /**
     * apiFetch 오류를 사용자가 이해할 수 있는 문장으로 바꾼다.
     * wp.apiFetch 의 invalid_json 은 사실 "서버가 JSON 대신 HTML을 반환" —
     * 대부분 공유호스팅 프록시 타임아웃(502/504)이나 PHP 치명적 오류다.
     */
    function describeError(err) {
        if (!err) return '알 수 없는 오류';
        if (err.code === 'invalid_json' || /JSON/.test(err.message || '')) {
            return '서버 응답이 끊겼습니다 (호스팅 타임아웃 가능성). 잠시 후 다시 시도하세요.';
        }
        return err.message || '알 수 없는 오류';
    }

    /**
     * 응답이 유실됐을 때 강의가 실제로 만들어졌는지 확인한다.
     * 서버 작업은 브라우저 연결이 끊겨도 계속 진행되므로, 잠깐 기다렸다가 조회한다.
     */
    async function recoverLesson(courseId, clientRef, attempts = 6, delayMs = 5000) {
        for (let i = 0; i < attempts; i++) {
            await new Promise((r) => setTimeout(r, delayMs));
            try {
                const res = await apiFetch({
                    path: API_BASE + '/generate/lesson-lookup?course_id=' + encodeURIComponent(courseId) +
                          '&client_ref=' + encodeURIComponent(clientRef)
                });
                if (res && res.found) return res;
            } catch (e) {
                // 조회 자체가 실패하면 다음 시도로 넘어간다
            }
        }
        return null;
    }

    async function apiFetch(options) {
        // WordPress Studio/Playground(PHP-WASM) 환경에서는 PUT/DELETE/PATCH HTTP 메서드가
        // service worker를 제대로 통과하지 못해 실패하는 경우가 있다.
        // → POST + X-HTTP-Method-Override 헤더로 변환한다 (WordPress REST 서버 공식 지원).
        //   이 한 곳을 고치면 강의/강좌 제목 수정·삭제 등 모든 쓰기 요청이 안정화된다.
        if (options && options.method && /^(PUT|DELETE|PATCH)$/i.test(options.method)) {
            options.headers = Object.assign(
                { 'X-HTTP-Method-Override': options.method.toUpperCase() },
                options.headers || {}
            );
            options.method = 'POST';
        }
        try {
            return await wp.apiFetch(options);
        } catch (error) {
            throw error;
        }
    }

    // ===== Dashboard Page =====

    function initDashboard() {
        const wrap = document.getElementById('dd-dashboard');
        if (!wrap) return;

        let currentCourseId = null;
        let confirmCallback = null;

        // Load courses
        loadCourses();

        // New course button
        const btnNew = document.getElementById('dd-btn-new-course');
        if (btnNew) {
            btnNew.addEventListener('click', () => openModal('dd-modal-new-course'));
        }

        // New course form
        const formNew = document.getElementById('dd-form-new-course');
        if (formNew) {
            formNew.addEventListener('submit', async (e) => {
                e.preventDefault();
                const title = document.getElementById('dd-new-course-title').value.trim();
                const description = document.getElementById('dd-new-course-desc').value.trim();

                if (!title) return;

                try {
                    await apiFetch({
                        path: API_BASE + '/courses',
                        method: 'POST',
                        data: { title, description }
                    });
                    closeModal('dd-modal-new-course');
                    formNew.reset();
                    showAlert('dd-alert-container', 'success', '강좌가 생성되었습니다.');
                    loadCourses();
                } catch (err) {
                    showAlert('dd-alert-container', 'error', '강좌 생성에 실패했습니다: ' + (err.message || '알 수 없는 오류'));
                }
            });
        }

        // Back to dashboard
        const btnBack = document.getElementById('dd-btn-back-dashboard');
        if (btnBack) {
            btnBack.addEventListener('click', () => {
                hideElement('dd-view-course-detail');
                showElement('dd-view-dashboard');
                currentCourseId = null;
            });
        }

        // Confirm action
        const btnConfirm = document.getElementById('dd-btn-confirm-action');
        if (btnConfirm) {
            btnConfirm.addEventListener('click', () => {
                if (confirmCallback) confirmCallback();
                closeModal('dd-modal-confirm');
                confirmCallback = null;
            });
        }

        // Tab buttons
        document.querySelectorAll('.dd-tab-btn[data-tab]').forEach(btn => {
            btn.addEventListener('click', () => {
                const tabName = btn.dataset.tab;
                const modal = btn.closest('.dd-modal-content') || btn.closest('.dd-admin-wrap');

                modal.querySelectorAll('.dd-tab-btn').forEach(b => b.classList.remove('active'));
                modal.querySelectorAll('.dd-tab-panel').forEach(p => p.classList.remove('active'));

                btn.classList.add('active');
                const panel = modal.querySelector(`[data-panel="${tabName}"]`);
                if (panel) panel.classList.add('active');
            });
        });

        // Close modals
        document.querySelectorAll('[data-close-modal]').forEach(el => {
            el.addEventListener('click', () => closeModal(el.dataset.closeModal));
        });

        // Lesson edit form
        const formEdit = document.getElementById('dd-form-edit-lesson');
        if (formEdit) {
            formEdit.addEventListener('submit', async (e) => {
                e.preventDefault();
                const lessonId = document.getElementById('dd-edit-lesson-id').value;
                const data = {
                    content: document.getElementById('dd-lesson-content').value,
                    slides_json: document.getElementById('dd-lesson-slides').value,
                    video_url: document.getElementById('dd-lesson-video-url').value,
                    video_thumbnail: document.getElementById('dd-lesson-video-thumb').value,
                    quiz_json: getQuizData(),
                    year: document.getElementById('dd-lesson-year').value,
                    artist: document.getElementById('dd-lesson-artist').value
                };

                try {
                    await apiFetch({
                        path: API_BASE + '/lessons/' + lessonId,
                        method: 'PUT',
                        data: data
                    });
                    closeModal('dd-modal-edit-lesson');
                    showAlert('dd-alert-container', 'success', '강의가 저장되었습니다.');
                    if (currentCourseId) loadCourseDetail(currentCourseId);
                } catch (err) {
                    showAlert('dd-alert-container', 'error', '강의 저장에 실패했습니다: ' + (err.message || '알 수 없는 오류'));
                }
            });
        }

        // Add quiz item
        const btnAddQuiz = document.getElementById('dd-btn-add-quiz-item');
        if (btnAddQuiz) {
            btnAddQuiz.addEventListener('click', () => addQuizItem());
        }

        // Add lesson button
        const btnAddLesson = document.getElementById('dd-btn-add-lesson');
        if (btnAddLesson) {
            btnAddLesson.addEventListener('click', async () => {
                if (!currentCourseId) return;
                try {
                    await apiFetch({
                        path: API_BASE + '/courses/' + currentCourseId + '/lessons',
                        method: 'POST',
                        data: { title: '새 강의', content: '' }
                    });
                    showAlert('dd-alert-container', 'success', '강의가 추가되었습니다.');
                    loadCourseDetail(currentCourseId);
                } catch (err) {
                    showAlert('dd-alert-container', 'error', '강의 추가에 실패했습니다: ' + (err.message || '알 수 없는 오류'));
                }
            });
        }

        // Copy link
        const btnCopy = document.getElementById('dd-btn-copy-link');
        if (btnCopy) {
            btnCopy.addEventListener('click', () => {
                const url = document.getElementById('dd-qr-url').textContent;
                if (url && navigator.clipboard) {
                    navigator.clipboard.writeText(url).then(() => {
                        showAlert('dd-alert-container', 'success', '링크가 복사되었습니다.');
                    });
                }
            });
        }

        // ===== Load Courses =====
        async function loadCourses() {
            const grid = document.getElementById('dd-course-grid');
            const loading = document.getElementById('dd-dashboard-loading');
            const empty = document.getElementById('dd-empty-state');

            showElement(loading);
            hideElement(empty);
            grid.innerHTML = '';

            try {
                const courses = await apiFetch({ path: API_BASE + '/courses' });

                hideElement(loading);

                if (!courses || courses.length === 0) {
                    showElement(empty);
                    updateStats(0, 0, 0);
                    return;
                }

                hideElement(empty);

                let totalLessons = 0;
                let totalPublic = 0;

                courses.forEach(course => {
                    const lessonCount = course.lesson_count || 0;
                    const publicCount = course.public_link_count || 0;
                    totalLessons += lessonCount;
                    totalPublic += publicCount;

                    grid.appendChild(createCourseCard(course));
                });

                updateStats(courses.length, totalLessons, totalPublic);

            } catch (err) {
                hideElement(loading);
                showAlert('dd-alert-container', 'error', '강좌 목록을 불러오는데 실패했습니다.');
            }
        }

        function updateStats(courses, lessons, publicLinks) {
            const elCourses = document.getElementById('dd-stat-courses');
            const elLessons = document.getElementById('dd-stat-lessons');
            const elPublic = document.getElementById('dd-stat-public');
            if (elCourses) elCourses.textContent = courses;
            if (elLessons) elLessons.textContent = lessons;
            if (elPublic) elPublic.textContent = publicLinks;
        }

        function createCourseCard(course) {
            const card = document.createElement('div');
            card.className = 'dd-course-card';

            // 생성 완료(complete)된 강좌는 공개 링크로 접근 가능하므로 '공개'로 표시
            const isLive = course.status === 'published' || course.status === 'complete';
            const statusClass = isLive ? 'dd-badge-published' : 'dd-badge-draft';
            const statusText = isLive ? '공개' : '임시저장';
            const lessonCount = course.lesson_count || 0;
            const desc = course.description || '';

            const thumbStyle = course.thumbnail
                ? `background-image:url('${escapeHtml(course.thumbnail)}');background-size:cover;background-position:center;`
                : '';

            card.innerHTML = `
                <div class="dd-course-thumb" style="${thumbStyle}"></div>
                <div class="dd-course-info">
                    <h3 class="dd-course-title">${escapeHtml(course.title)}</h3>
                    <p class="dd-course-desc">${escapeHtml(desc)}</p>
                    <div class="dd-course-meta">
                        <span class="dd-badge dd-badge-count">${lessonCount}개 강의</span>
                        <span class="dd-badge ${statusClass}">${statusText}</span>
                    </div>
                    <div class="dd-course-actions">
                        <button type="button" class="dd-btn dd-btn-secondary dd-btn-sm" data-action="detail" data-id="${course.id}">
                            상세보기
                        </button>
                        <button type="button" class="dd-btn dd-btn-danger dd-btn-sm" data-action="delete-course" data-id="${course.id}">
                            삭제
                        </button>
                    </div>
                </div>
            `;

            // Event listeners
            card.querySelector('[data-action="detail"]').addEventListener('click', () => {
                showCourseDetail(course.id);
            });

            card.querySelector('[data-action="delete-course"]').addEventListener('click', () => {
                confirmCallback = async () => {
                    try {
                        await apiFetch({
                            path: API_BASE + '/courses/' + course.id,
                            method: 'DELETE'
                        });
                        showAlert('dd-alert-container', 'success', '강좌가 삭제되었습니다.');
                        loadCourses();
                    } catch (err) {
                        showAlert('dd-alert-container', 'error', '강좌 삭제에 실패했습니다.');
                    }
                };
                document.getElementById('dd-confirm-title').textContent = '강좌 삭제';
                document.getElementById('dd-confirm-message').textContent = `"${course.title}" 강좌를 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.`;
                openModal('dd-modal-confirm');
            });

            return card;
        }

        // ===== Course Detail =====
        function showCourseDetail(courseId) {
            currentCourseId = courseId;
            hideElement('dd-view-dashboard');
            showElement('dd-view-course-detail');
            loadCourseDetail(courseId);
        }

        // Inline course title edit
        document.getElementById('dd-btn-edit-course-title').addEventListener('click', () => {
            const titleEl = document.getElementById('dd-detail-title');
            if (titleEl.querySelector('input')) return;
            const currentTitle = titleEl.textContent.trim();
            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'dd-input dd-inline-title-input';
            input.value = currentTitle;
            input.style.fontSize = '1.4rem';
            input.style.fontWeight = '700';
            let saving = false;

            const cancel = () => {
                if (saving) return;
                saving = true;
                titleEl.textContent = currentTitle;
            };

            const save = async () => {
                if (saving) return;
                const newTitle = input.value.trim();
                if (!newTitle || newTitle === currentTitle) { cancel(); return; }
                saving = true;
                input.disabled = true;
                try {
                    await apiFetch({
                        path: API_BASE + '/courses/' + currentCourseId,
                        method: 'PUT',
                        data: { title: newTitle }
                    });
                    titleEl.textContent = newTitle;
                    showAlert('dd-alert-container', 'success', '강좌 제목이 수정되었습니다.');
                    // Also update card in dashboard grid
                    loadCourses();
                } catch (err) {
                    saving = false;
                    cancel();
                    showAlert('dd-alert-container', 'error', '강좌 제목 수정에 실패했습니다.');
                }
            };

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') { e.preventDefault(); save(); }
                if (e.key === 'Escape') { cancel(); }
            });
            input.addEventListener('blur', () => { setTimeout(save, 150); });

            titleEl.textContent = '';
            titleEl.appendChild(input);
            input.focus();
            input.select();
        });

        // 장르 드롭다운: "직접 입력" 선택 시 커스텀 입력칸 토글
        const detailGenreSel = document.getElementById('dd-detail-genre');
        if (detailGenreSel) {
            detailGenreSel.addEventListener('change', function() {
                const c = document.getElementById('dd-detail-genre-custom');
                if (c) c.classList.toggle('dd-hidden', this.value !== '__custom');
            });
        }

        // 강좌 정보(소개/장르/레벨/가수) 저장
        const btnSaveCourseMeta = document.getElementById('dd-btn-save-course-meta');
        if (btnSaveCourseMeta) {
            btnSaveCourseMeta.addEventListener('click', async () => {
                if (!currentCourseId) return;
                const gSel = document.getElementById('dd-detail-genre');
                const genreVal = (gSel && gSel.value === '__custom')
                    ? (document.getElementById('dd-detail-genre-custom').value || '').trim()
                    : (gSel ? gSel.value : '');
                btnSaveCourseMeta.disabled = true;
                try {
                    await apiFetch({
                        path: API_BASE + '/courses/' + currentCourseId,
                        method: 'PUT',
                        data: {
                            intro:  document.getElementById('dd-detail-intro').value,
                            genre:  genreVal,
                            level:  document.getElementById('dd-detail-level').value,
                            artist: document.getElementById('dd-detail-artist').value
                        }
                    });
                    showAlert('dd-alert-container', 'success', '강좌 정보가 저장되었습니다.');
                    loadCourses();
                } catch (err) {
                    showAlert('dd-alert-container', 'error', '강좌 정보 저장에 실패했습니다: ' + (err.message || '알 수 없는 오류'));
                } finally {
                    btnSaveCourseMeta.disabled = false;
                }
            });
        }

        async function loadCourseDetail(courseId) {
            const lessonList = document.getElementById('dd-lesson-list');
            const loading = document.getElementById('dd-lessons-loading');
            const empty = document.getElementById('dd-lessons-empty');

            showElement(loading);
            hideElement(empty);
            lessonList.innerHTML = '';

            try {
                const course = await apiFetch({ path: API_BASE + '/courses/' + courseId });

                document.getElementById('dd-detail-title').textContent = course.title || '';
                const introEl = document.getElementById('dd-detail-intro');
                if (introEl) introEl.value = course.intro || course.description || '';
                const genreEl = document.getElementById('dd-detail-genre');
                const genreCustomEl = document.getElementById('dd-detail-genre-custom');
                if (genreEl) {
                    const g = course.genre || '';
                    const inList = Array.prototype.some.call(genreEl.options, function(o) {
                        return o.value === g && o.value !== '__custom';
                    });
                    if (g && !inList) {
                        genreEl.value = '__custom';
                        if (genreCustomEl) { genreCustomEl.value = g; genreCustomEl.classList.remove('dd-hidden'); }
                    } else {
                        genreEl.value = g;
                        if (genreCustomEl) { genreCustomEl.value = ''; genreCustomEl.classList.add('dd-hidden'); }
                    }
                }
                const levelEl = document.getElementById('dd-detail-level');
                if (levelEl) levelEl.value = course.level || '';
                const artistEl = document.getElementById('dd-detail-artist');
                if (artistEl) artistEl.value = course.artist || '';

                const lessons = course.lessons || [];
                const lessonCountBadge = document.getElementById('dd-detail-lesson-count');
                if (lessonCountBadge) lessonCountBadge.textContent = lessons.length + '개 강의';

                const statusBadge = document.getElementById('dd-detail-status');
                if (statusBadge) {
                    // 생성 완료(complete)된 강좌는 공개 링크로 접근 가능하므로 '공개'로 표시
                    const isLive = course.status === 'published' || course.status === 'complete';
                    statusBadge.className = 'dd-badge ' + (isLive ? 'dd-badge-published' : 'dd-badge-draft');
                    statusBadge.textContent = isLive ? '공개' : '임시저장';
                }

                hideElement(loading);

                if (lessons.length === 0) {
                    showElement(empty);
                    return;
                }

                lessons.forEach((lesson, index) => {
                    lessonList.appendChild(createLessonItem(lesson, index + 1));
                });

            } catch (err) {
                hideElement(loading);
                showAlert('dd-alert-container', 'error', '강좌 정보를 불러오는데 실패했습니다.');
            }
        }

        function createLessonItem(lesson, order) {
            const li = document.createElement('li');
            li.className = 'dd-lesson-item';

            const isPublic = lesson.is_public || false;

            li.innerHTML = `
                <span class="dd-lesson-order">${order}</span>
                <span class="dd-lesson-title-wrap">
                    <span class="dd-lesson-title" data-id="${lesson.id}">${escapeHtml(lesson.title)}</span>
                    <button type="button" class="dd-btn-icon dd-btn-edit-title" data-id="${lesson.id}" title="제목 수정">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </button>
                </span>
                <div class="dd-lesson-actions">
                    <label class="dd-toggle" title="공개 링크">
                        <input type="checkbox" ${isPublic ? 'checked' : ''} data-lesson-id="${lesson.id}" data-action="toggle-public">
                        <span class="dd-toggle-slider"></span>
                    </label>
                    <button type="button" class="dd-btn dd-btn-secondary dd-btn-sm" data-action="edit-lesson" data-id="${lesson.id}">
                        수정
                    </button>
                    <button type="button" class="dd-btn dd-btn-info dd-btn-sm" data-action="manage-images" data-id="${lesson.id}">
                        이미지
                    </button>
                    <button type="button" class="dd-btn dd-btn-info dd-btn-sm" data-action="attach-material" data-id="${lesson.id}">
                        자료첨부
                    </button>
                    <button type="button" class="dd-btn dd-btn-danger dd-btn-sm" data-action="delete-lesson" data-id="${lesson.id}">
                        삭제
                    </button>
                </div>
            `;

            // Toggle public
            li.querySelector('[data-action="toggle-public"]').addEventListener('change', async (e) => {
                try {
                    const result = await apiFetch({
                        path: API_BASE + '/lessons/' + lesson.id + '/toggle-public',
                        method: 'POST'
                    });
                    if (result && result.public_url) {
                        document.getElementById('dd-qr-url').textContent = result.public_url;
                        const qrImg = document.getElementById('dd-qr-image');
                        qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(result.public_url)}`;
                        openModal('dd-modal-qr');
                    }
                    showAlert('dd-alert-container', 'success', '공개 링크가 ' + (e.target.checked ? '활성화' : '비활성화') + '되었습니다.');
                } catch (err) {
                    e.target.checked = !e.target.checked;
                    showAlert('dd-alert-container', 'error', '공개 링크 변경에 실패했습니다.');
                }
            });

            // Edit lesson
            li.querySelector('[data-action="edit-lesson"]').addEventListener('click', async () => {
                try {
                    const lessonData = await apiFetch({ path: API_BASE + '/lessons/' + lesson.id });
                    openLessonEditor(lessonData);
                } catch (err) {
                    showAlert('dd-alert-container', 'error', '강의 정보를 불러오는데 실패했습니다.');
                }
            });

            // Delete lesson
            li.querySelector('[data-action="delete-lesson"]').addEventListener('click', () => {
                confirmCallback = async () => {
                    try {
                        await apiFetch({
                            path: API_BASE + '/lessons/' + lesson.id,
                            method: 'DELETE'
                        });
                        showAlert('dd-alert-container', 'success', '강의가 삭제되었습니다.');
                        if (currentCourseId) loadCourseDetail(currentCourseId);
                    } catch (err) {
                        showAlert('dd-alert-container', 'error', '강의 삭제에 실패했습니다.');
                    }
                };
                document.getElementById('dd-confirm-title').textContent = '강의 삭제';
                document.getElementById('dd-confirm-message').textContent = `"${lesson.title}" 강의를 삭제하시겠습니까?`;
                openModal('dd-modal-confirm');
            });

            // Attach material (WP Media Library)
            li.querySelector('[data-action="attach-material"]').addEventListener('click', () => {
                if (!wp || !wp.media) {
                    showAlert('dd-alert-container', 'error', 'WordPress 미디어 라이브러리를 불러올 수 없습니다.');
                    return;
                }
                const frame = wp.media({
                    title: '학습 자료 첨부',
                    button: { text: '첨부하기' },
                    multiple: false,
                    library: { type: ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/msword', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'] }
                });
                frame.on('select', async () => {
                    const att = frame.state().get('selection').first().toJSON();
                    const ext = (att.filename || '').split('.').pop().toLowerCase();
                    try {
                        await apiFetch({
                            path: API_BASE + '/lessons/' + lesson.id + '/materials',
                            method: 'POST',
                            data: { url: att.url, filename: att.filename, filetype: ext }
                        });
                        showAlert('dd-alert-container', 'success', '자료가 첨부되었습니다: ' + att.filename);
                    } catch (err) {
                        showAlert('dd-alert-container', 'error', '자료 첨부에 실패했습니다.');
                    }
                });
                frame.open();
            });

            // Manage images
            li.querySelector('[data-action="manage-images"]').addEventListener('click', async () => {
                document.getElementById('dd-images-lesson-id').value = lesson.id;
                document.getElementById('dd-images-title').textContent = '이미지 관리 — ' + escapeHtml(lesson.title);
                const grid = document.getElementById('dd-images-grid');
                grid.innerHTML = '<div class="dd-loading"></div>';
                openModal('dd-modal-images');

                try {
                    const data = await apiFetch({ path: API_BASE + '/lessons/' + lesson.id });
                    renderImageGrid(grid, lesson.id, data);
                } catch (err) {
                    grid.innerHTML = '<p style="color:red;">강의 데이터를 불러올 수 없습니다.</p>';
                }
            });

            // Inline title edit
            li.querySelector('.dd-btn-edit-title').addEventListener('click', () => {
                const titleSpan = li.querySelector('.dd-lesson-title');
                if (titleSpan.querySelector('input')) return; // already editing
                const currentTitle = titleSpan.textContent.trim();
                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'dd-input dd-inline-title-input';
                input.value = currentTitle;
                let saving = false;

                const cancel = () => {
                    if (saving) return;
                    saving = true;
                    titleSpan.textContent = currentTitle;
                };

                const save = async () => {
                    if (saving) return;
                    const newTitle = input.value.trim();
                    if (!newTitle || newTitle === currentTitle) {
                        cancel();
                        return;
                    }
                    saving = true;
                    input.disabled = true;
                    try {
                        await apiFetch({
                            path: API_BASE + '/lessons/' + lesson.id,
                            method: 'PUT',
                            data: { title: newTitle }
                        });
                        titleSpan.textContent = newTitle;
                        lesson.title = newTitle;
                        showAlert('dd-alert-container', 'success', '제목이 수정되었습니다.');
                    } catch (err) {
                        saving = false;
                        cancel();
                        showAlert('dd-alert-container', 'error', '제목 수정에 실패했습니다.');
                    }
                };

                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') { e.preventDefault(); save(); }
                    if (e.key === 'Escape') { cancel(); }
                });
                input.addEventListener('blur', () => {
                    setTimeout(save, 150);
                });

                titleSpan.textContent = '';
                titleSpan.appendChild(input);
                input.focus();
                input.select();
            });

            return li;
        }

        function openLessonEditor(lesson) {
            document.getElementById('dd-edit-lesson-id').value = lesson.id;
            document.getElementById('dd-edit-lesson-title').textContent = lesson.title + ' 수정';
            document.getElementById('dd-lesson-content').value = lesson.content || '';
            document.getElementById('dd-lesson-slides').value = lesson.slides_json || '';
            document.getElementById('dd-lesson-video-url').value = lesson.video_url || '';
            document.getElementById('dd-lesson-video-thumb').value = lesson.video_thumbnail || '';
            const yearEl = document.getElementById('dd-lesson-year');
            if (yearEl) yearEl.value = lesson.year || '';
            const lessonArtistEl = document.getElementById('dd-lesson-artist');
            if (lessonArtistEl) lessonArtistEl.value = lesson.artist || '';

            // Load quiz data
            const quizBuilder = document.getElementById('dd-quiz-builder');
            quizBuilder.innerHTML = '';
            if (lesson.quiz_json) {
                try {
                    const quizItems = JSON.parse(lesson.quiz_json);
                    quizItems.forEach(item => addQuizItem(item));
                } catch (e) {
                    // Invalid JSON, start empty
                }
            }

            // Reset tabs
            const modal = document.getElementById('dd-modal-edit-lesson');
            modal.querySelectorAll('.dd-tab-btn').forEach(b => b.classList.remove('active'));
            modal.querySelectorAll('.dd-tab-panel').forEach(p => p.classList.remove('active'));
            modal.querySelector('[data-tab="content"]').classList.add('active');
            modal.querySelector('[data-panel="content"]').classList.add('active');

            openModal('dd-modal-edit-lesson');
        }

        function addQuizItem(data) {
            const builder = document.getElementById('dd-quiz-builder');
            const index = builder.children.length + 1;
            const item = document.createElement('div');
            item.className = 'dd-card dd-mb-1';
            item.style.padding = '1rem';

            item.innerHTML = `
                <div class="dd-flex-between dd-mb-1">
                    <strong style="font-size: 0.82rem;">문항 ${index}</strong>
                    <button type="button" class="dd-btn dd-btn-danger dd-btn-sm" data-action="remove-quiz">삭제</button>
                </div>
                <div class="dd-form-group">
                    <label>질문</label>
                    <input type="text" class="dd-input dd-quiz-question" value="${escapeHtml((data && data.question) || '')}" placeholder="질문을 입력하세요">
                </div>
                <div class="dd-form-group">
                    <label>보기 (쉼표로 구분)</label>
                    <input type="text" class="dd-input dd-quiz-options" value="${escapeHtml((data && data.options) ? data.options.join(', ') : '')}" placeholder="보기1, 보기2, 보기3, 보기4">
                </div>
                <div class="dd-form-group">
                    <label>정답</label>
                    <input type="text" class="dd-input dd-quiz-answer" value="${escapeHtml((data && data.answer) || '')}" placeholder="정답을 입력하세요">
                </div>
            `;

            item.querySelector('[data-action="remove-quiz"]').addEventListener('click', () => {
                item.remove();
            });

            builder.appendChild(item);
        }

        function getQuizData() {
            const builder = document.getElementById('dd-quiz-builder');
            const items = [];
            builder.querySelectorAll('.dd-card').forEach(card => {
                const question = card.querySelector('.dd-quiz-question').value.trim();
                const optionsStr = card.querySelector('.dd-quiz-options').value.trim();
                const answer = card.querySelector('.dd-quiz-answer').value.trim();

                if (question) {
                    items.push({
                        question,
                        options: optionsStr ? optionsStr.split(',').map(s => s.trim()) : [],
                        answer
                    });
                }
            });
            return JSON.stringify(items);
        }
    }

    // ===== Settings Page =====

    function initSettings() {
        const wrap = document.getElementById('dd-settings');
        if (!wrap) return;

        checkApiKeyStatus();
        loadModelSetting();
        checkPixabayKeyStatus();
        checkYouTubeKeyStatus();
        loadPurgeSetting();

        // 삭제 시 콘텐츠 제거 여부 — 기본값은 보존(체크 해제)
        async function loadPurgeSetting() {
            const box = document.getElementById('dd-purge-content');
            if (!box) return;
            try {
                const res = await apiFetch({ path: API_BASE + '/settings/purge-content' });
                box.checked = !!(res && res.purge);
            } catch (e) {
                box.checked = false;
            }
        }

        const btnPurge = document.getElementById('dd-btn-save-purge');
        if (btnPurge) {
            btnPurge.addEventListener('click', async () => {
                const box = document.getElementById('dd-purge-content');
                const purge = box ? box.checked : false;

                // 되돌릴 수 없는 설정이므로 켤 때만 한 번 더 확인한다.
                if (purge && !confirm(
                    '플러그인을 삭제할 때 강좌·강의·스토리·뉴스레터가 영구 삭제됩니다.\n' +
                    '휴지통을 거치지 않으며 복구할 수 없습니다.\n\n정말 이 설정을 켜시겠습니까?'
                )) {
                    box.checked = false;
                    return;
                }

                try {
                    await apiFetch({
                        path: API_BASE + '/settings/purge-content',
                        method: 'POST',
                        data: { purge }
                    });
                    showAlert('dd-settings-alert-container', 'success',
                        purge ? '삭제 시 콘텐츠도 제거하도록 설정되었습니다.'
                              : '삭제해도 콘텐츠는 보존됩니다.');
                } catch (err) {
                    showAlert('dd-settings-alert-container', 'error', '저장에 실패했습니다: ' + (err.message || ''));
                }
            });
        }

        // Save key
        const form = document.getElementById('dd-form-api-key');
        if (form) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const apiKey = document.getElementById('dd-api-key-input').value.trim();
                if (!apiKey) {
                    showAlert('dd-settings-alert-container', 'error', 'API 키를 입력하세요.');
                    return;
                }

                try {
                    await apiFetch({
                        path: API_BASE + '/settings/api-key',
                        method: 'PUT',
                        data: { api_key: apiKey }
                    });
                    document.getElementById('dd-api-key-input').value = '';
                    showAlert('dd-settings-alert-container', 'success', 'API 키가 저장되었습니다.');
                    checkApiKeyStatus();
                } catch (err) {
                    showAlert('dd-settings-alert-container', 'error', 'API 키 저장에 실패했습니다: ' + (err.message || '알 수 없는 오류'));
                }
            });
        }

        // Delete key
        const btnDelete = document.getElementById('dd-btn-delete-key');
        if (btnDelete) {
            btnDelete.addEventListener('click', async () => {
                if (!confirm('API 키를 삭제하시겠습니까?')) return;

                try {
                    await apiFetch({
                        path: API_BASE + '/settings/api-key',
                        method: 'PUT',
                        data: { api_key: '' }
                    });
                    showAlert('dd-settings-alert-container', 'success', 'API 키가 삭제되었습니다.');
                    checkApiKeyStatus();
                } catch (err) {
                    showAlert('dd-settings-alert-container', 'error', 'API 키 삭제에 실패했습니다.');
                }
            });
        }

        // Save model
        const btnModel = document.getElementById('dd-btn-save-model');
        if (btnModel) {
            btnModel.addEventListener('click', async () => {
                const select = document.getElementById('dd-model-select');
                if (!select) return;

                try {
                    await apiFetch({
                        path: API_BASE + '/settings/model',
                        method: 'PUT',
                        data: { model: select.value }
                    });
                    showAlert('dd-settings-alert-container', 'success', '모델이 저장되었습니다.');
                } catch (err) {
                    showAlert('dd-settings-alert-container', 'error', '모델 저장에 실패했습니다: ' + (err.message || ''));
                }
            });
        }

        // Save Pixabay key
        const formPixabay = document.getElementById('dd-form-pixabay-key');
        if (formPixabay) {
            formPixabay.addEventListener('submit', async (e) => {
                e.preventDefault();
                const key = document.getElementById('dd-pixabay-key-input').value.trim();
                if (!key) {
                    showAlert('dd-settings-alert-container', 'error', 'Pixabay API 키를 입력하세요.');
                    return;
                }

                try {
                    await apiFetch({
                        path: API_BASE + '/settings/pixabay-key',
                        method: 'PUT',
                        data: { api_key: key }
                    });
                    document.getElementById('dd-pixabay-key-input').value = '';
                    showAlert('dd-settings-alert-container', 'success', 'Pixabay API 키가 저장되었습니다.');
                    checkPixabayKeyStatus();
                } catch (err) {
                    showAlert('dd-settings-alert-container', 'error', 'Pixabay 키 저장 실패: ' + (err.message || ''));
                }
            });
        }

        // Delete Pixabay key
        const btnDeletePixabay = document.getElementById('dd-btn-delete-pixabay');
        if (btnDeletePixabay) {
            btnDeletePixabay.addEventListener('click', async () => {
                if (!confirm('Pixabay API 키를 삭제하시겠습니까?')) return;

                try {
                    await apiFetch({
                        path: API_BASE + '/settings/pixabay-key',
                        method: 'PUT',
                        data: { api_key: '' }
                    });
                    showAlert('dd-settings-alert-container', 'success', 'Pixabay API 키가 삭제되었습니다.');
                    checkPixabayKeyStatus();
                } catch (err) {
                    showAlert('dd-settings-alert-container', 'error', 'Pixabay 키 삭제 실패.');
                }
            });
        }

        // Save YouTube key
        const formYT = document.getElementById('dd-form-youtube-key');
        if (formYT) {
            formYT.addEventListener('submit', async (e) => {
                e.preventDefault();
                const key = document.getElementById('dd-youtube-key-input').value.trim();
                if (!key) {
                    showAlert('dd-settings-alert-container', 'error', 'YouTube API 키를 입력하세요.');
                    return;
                }

                try {
                    await apiFetch({
                        path: API_BASE + '/settings/youtube-key',
                        method: 'PUT',
                        data: { api_key: key }
                    });
                    document.getElementById('dd-youtube-key-input').value = '';
                    showAlert('dd-settings-alert-container', 'success', 'YouTube API 키가 저장되었습니다.');
                    checkYouTubeKeyStatus();
                } catch (err) {
                    showAlert('dd-settings-alert-container', 'error', 'YouTube 키 저장 실패: ' + (err.message || ''));
                }
            });
        }

        // Delete YouTube key
        const btnDeleteYT = document.getElementById('dd-btn-delete-youtube');
        if (btnDeleteYT) {
            btnDeleteYT.addEventListener('click', async () => {
                if (!confirm('YouTube API 키를 삭제하시겠습니까?')) return;

                try {
                    await apiFetch({
                        path: API_BASE + '/settings/youtube-key',
                        method: 'PUT',
                        data: { api_key: '' }
                    });
                    showAlert('dd-settings-alert-container', 'success', 'YouTube API 키가 삭제되었습니다.');
                    checkYouTubeKeyStatus();
                } catch (err) {
                    showAlert('dd-settings-alert-container', 'error', 'YouTube 키 삭제 실패.');
                }
            });
        }

        // YouTube check button
        const btnCheckYT = document.getElementById('dd-btn-check-yt');
        if (btnCheckYT) {
            btnCheckYT.addEventListener('click', async () => {
                btnCheckYT.disabled = true;
                btnCheckYT.textContent = '확인 중...';
                await checkYouTubeConnection();
                btnCheckYT.disabled = false;
                btnCheckYT.textContent = '연결 확인';
            });
        }

        async function checkYouTubeKeyStatus() {
            const statusEl = document.getElementById('dd-yt-api-status');
            if (!statusEl) return;

            try {
                const result = await apiFetch({ path: API_BASE + '/settings/youtube-key-status' });
                const isSet = result && result.has_key;

                statusEl.className = 'dd-status-indicator ' + (isSet ? 'dd-status-active' : 'dd-status-inactive');
                statusEl.querySelector('.dd-status-text').textContent = isSet ? '설정됨' : '미설정';
            } catch (err) {
                statusEl.className = 'dd-status-indicator dd-status-inactive';
                statusEl.querySelector('.dd-status-text').textContent = '확인 불가';
            }
        }

        async function checkYouTubeConnection() {
            const statusEl = document.getElementById('dd-yt-api-status');

            try {
                const result = await apiFetch({ path: API_BASE + '/settings/youtube-check' });
                const isOk = result && result.available;

                if (statusEl) {
                    statusEl.className = 'dd-status-indicator ' + (isOk ? 'dd-status-active' : 'dd-status-inactive');
                    statusEl.querySelector('.dd-status-text').textContent = isOk ? '연결됨' : '미연결';
                }

                if (result.message && !isOk) {
                    showAlert('dd-settings-alert-container', 'error', result.message);
                } else if (isOk) {
                    showAlert('dd-settings-alert-container', 'success', result.message);
                }
            } catch (err) {
                if (statusEl) {
                    statusEl.className = 'dd-status-indicator dd-status-inactive';
                    statusEl.querySelector('.dd-status-text').textContent = '확인 불가';
                }
            }
        }

        async function checkPixabayKeyStatus() {
            const statusEl = document.getElementById('dd-pixabay-status');
            if (!statusEl) return;

            try {
                const result = await apiFetch({ path: API_BASE + '/settings/pixabay-key-status' });
                const isSet = result && result.has_key;

                statusEl.className = 'dd-status-indicator ' + (isSet ? 'dd-status-active' : 'dd-status-inactive');
                statusEl.querySelector('.dd-status-text').textContent = isSet ? '설정됨' : '미설정';
            } catch (err) {
                statusEl.className = 'dd-status-indicator dd-status-inactive';
                statusEl.querySelector('.dd-status-text').textContent = '확인 불가';
            }
        }

        async function checkApiKeyStatus() {
            const statusEl = document.getElementById('dd-api-key-status');
            if (!statusEl) return;

            try {
                const result = await apiFetch({ path: API_BASE + '/settings/api-key-status' });
                const isSet = result && (result.has_key || result.is_set);

                statusEl.className = 'dd-status-indicator ' + (isSet ? 'dd-status-active' : 'dd-status-inactive');
                statusEl.querySelector('.dd-status-text').textContent = isSet ? '설정됨' : '미설정';
            } catch (err) {
                statusEl.className = 'dd-status-indicator dd-status-inactive';
                statusEl.querySelector('.dd-status-text').textContent = '확인 불가';
            }
        }

        async function loadModelSetting() {
            const select = document.getElementById('dd-model-select');
            if (!select) return;

            try {
                const result = await apiFetch({ path: API_BASE + '/settings/model' });
                if (result && result.model) {
                    select.value = result.model;
                }
            } catch (err) {
                // 기본값 사용
            }
        }

        // ===== 데이터 백업 / 복원 =====

        initBackup();

        function initBackup() {
            loadBackupInfo();

            const btnDownload = document.getElementById('dd-btn-backup-download');
            if (btnDownload) btnDownload.addEventListener('click', downloadBackup);

            const btnRestore = document.getElementById('dd-btn-restore');
            if (btnRestore) btnRestore.addEventListener('click', restoreBackup);
        }

        /** 백업하면 몇 건이 담기는지 미리 보여 준다. */
        async function loadBackupInfo() {
            const statusEl = document.getElementById('dd-backup-status');
            if (!statusEl) return;

            try {
                const info = await apiFetch({ path: API_BASE + '/backup/info' });
                const c = (info && info.counts) || {};
                const label = `강좌 ${c.dd_course || 0} · 강의 ${c.dd_lesson || 0} · `
                            + `스토리 ${c.dd_story || 0} · 뉴스레터 ${c.dd_newsletter || 0}`;
                statusEl.className = 'dd-status-indicator '
                    + ((info && info.total > 0) ? 'dd-status-active' : 'dd-status-inactive');
                statusEl.querySelector('.dd-status-text').textContent = label;

                renderArchiveNote(info);
                renderUploadLimit(info);
                renderWarnings(info);
            } catch (err) {
                statusEl.className = 'dd-status-indicator dd-status-inactive';
                statusEl.querySelector('.dd-status-text').textContent = '확인 불가';
            }
        }

        /** ZIP 버튼 옆에 예상 용량을 보여 준다 — 147MB 를 모르고 누르지 않도록. */
        function renderArchiveNote(info) {
            const note = document.getElementById('dd-backup-archive-note');
            const link = document.getElementById('dd-btn-backup-archive');
            const media = (info && info.media) || {};

            if (link && media.zip_ready === false) {
                link.classList.add('dd-hidden');
                if (note) {
                    note.textContent = '이 서버에는 ZIP 확장(ZipArchive)이 없어 전체 백업을 만들 수 없습니다. JSON 백업을 사용하세요.';
                }
                return;
            }

            if (!note) return;
            note.textContent =
                'JSON 백업은 콘텐츠와 설정만 담아 가볍고, 다른 사이트로 옮기기 쉽습니다. '
                + `ZIP 백업은 여기에 이미지 ${media.count || 0}개(약 ${media.human || '0 B'})를 함께 담습니다.`
                + (info.upload_limit && media.bytes > info.upload_limit.bytes
                    ? ` ⚠️ 이 서버의 업로드 한도(${info.upload_limit.human})보다 커서, 만든 ZIP 을 이 사이트에 그대로 복원하지 못할 수 있습니다.`
                    : '');
        }

        /** 백업에서 빠지는 것·서버 환경 문제를 미리 알려 준다. */
        function renderWarnings(info) {
            const box = document.getElementById('dd-backup-warnings');
            if (!box || !info) return;

            const notes = [];

            if (info.trashed > 0) {
                notes.push(`휴지통에 있는 콘텐츠 ${info.trashed}건은 백업에 포함되지 않습니다. `
                    + '보존하려면 먼저 복원(휴지통에서 꺼내기)한 뒤 백업하세요.');
            }

            if (info.backup_dir && info.backup_dir.protected === false) {
                notes.push('이 서버(' + escapeHtml(info.backup_dir.server || '알 수 없음') + ')는 .htaccess 를 '
                    + '읽지 않아 백업 폴더를 웹에서 막지 못합니다. 복원 전 자동 백업 파일은 추측하기 어려운 '
                    + '무작위 이름으로 저장되지만, 민감한 사이트라면 uploads/dingdong-lms/backups/ 에 대한 '
                    + '접근 차단을 서버 설정에 직접 추가하세요.');
            }

            if (notes.length === 0) {
                box.classList.add('dd-hidden');
                box.innerHTML = '';
                return;
            }

            box.innerHTML = '<div class="dd-info-box"><strong>알아두실 점</strong><br>'
                + notes.map((n) => '· ' + n).join('<br>') + '</div>';
            box.classList.remove('dd-hidden');
        }

        function renderUploadLimit(info) {
            const el = document.getElementById('dd-restore-limit');
            if (el && info && info.upload_limit) {
                el.textContent = ` 업로드 가능한 최대 크기: ${info.upload_limit.human}.`;
            }
        }

        /**
         * 백업 JSON 을 받아 브라우저에서 파일로 저장한다.
         * 서버에 파일을 만들지 않으므로 백업본이 uploads 에 남지 않는다.
         */
        async function downloadBackup() {
            const btn = document.getElementById('dd-btn-backup-download');
            const original = btn ? btn.textContent : '';
            if (btn) { btn.disabled = true; btn.textContent = '백업 준비 중...'; }

            try {
                // POST 다 — 백업 생성은 포스트에 고유 식별자를 부여하는 쓰기 작업이다.
                const res = await apiFetch({ path: API_BASE + '/backup/export', method: 'POST' });
                if (!res || !res.backup) throw new Error('백업 데이터를 받지 못했습니다.');

                const json = JSON.stringify(res.backup, null, 2);
                const blob = new Blob([json], { type: 'application/json;charset=utf-8' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = res.filename || 'dingdong-lms-backup.json';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                setTimeout(() => URL.revokeObjectURL(url), 1000);

                const total = (res.backup.counts && res.backup.counts.posts) || 0;
                showAlert('dd-settings-alert-container', 'success',
                    `백업 성공 — 콘텐츠 ${total}건을 ${res.filename} 파일로 저장했습니다.`);
            } catch (err) {
                showAlert('dd-settings-alert-container', 'error', '백업 실패: ' + describeError(err));
            }

            if (btn) { btn.disabled = false; btn.textContent = original; }
        }

        async function restoreBackup() {
            const input = document.getElementById('dd-restore-file');
            const file = input && input.files && input.files[0];

            if (!file) {
                showAlert('dd-settings-alert-container', 'error', '복원할 백업 파일을 선택하세요.');
                return;
            }
            const isZip = /\.zip$/i.test(file.name);
            if (!isZip && !/\.json$/i.test(file.name)) {
                showAlert('dd-settings-alert-container', 'error',
                    '파일 형식이 잘못되었습니다. .json 또는 .zip 백업 파일을 선택하세요.');
                return;
            }

            if (!confirm('백업 데이터를 복원하면 기존 데이터와 중복될 수 있습니다. 계속하시겠습니까?')) {
                return;
            }

            const mode = (document.getElementById('dd-restore-mode') || {}).value || 'skip';
            if (mode === 'replace' && !confirm(
                '덮어쓰기 모드입니다.\n같은 콘텐츠가 이미 있으면 백업 내용으로 교체되며 현재 내용은 사라집니다.\n\n계속하시겠습니까?'
            )) {
                return;
            }

            const safety = !!(document.getElementById('dd-restore-safety') || {}).checked;
            const withOptions = !!(document.getElementById('dd-restore-options') || {}).checked;
            const withMedia = !!(document.getElementById('dd-restore-media') || {}).checked;

            const btn = document.getElementById('dd-btn-restore');
            const original = btn ? btn.textContent : '';
            if (btn) { btn.disabled = true; btn.textContent = '복원 중...'; }

            try {
                const form = new FormData();
                form.append('file', file);
                form.append('_dd_nonce', (window.ddLms && window.ddLms.backupNonce) || '');
                form.append('mode', mode);
                form.append('safety_backup', safety ? '1' : '0');
                form.append('restore_options', withOptions ? '1' : '0');
                form.append('restore_media', withMedia ? '1' : '0');

                let report;
                try {
                    report = await apiFetch({ path: API_BASE + '/backup/import', method: 'POST', body: form });
                } catch (uploadErr) {
                    // Playground 등에서 multipart 업로드가 막히면 본문을 직접 보낸다.
                    // ZIP 은 텍스트로 보낼 수 없으므로 JSON 백업일 때만 시도한다.
                    if (!isZip && uploadErr && /upload|no_file|multipart|empty/i.test(uploadErr.code || '')) {
                        const text = await file.text();
                        report = await apiFetch({
                            path: API_BASE + '/backup/import',
                            method: 'POST',
                            data: {
                                payload: text,
                                _dd_nonce: (window.ddLms && window.ddLms.backupNonce) || '',
                                mode: mode,
                                safety_backup: safety ? '1' : '0',
                                restore_options: withOptions ? '1' : '0'
                            }
                        });
                    } else {
                        throw uploadErr;
                    }
                }

                renderRestoreReport(report);

                if (report.failed > 0) {
                    showAlert('dd-settings-alert-container', 'warning',
                        `일부 데이터 복원 실패 — 성공 ${report.created + report.updated}건 / 실패 ${report.failed}건`);
                } else {
                    const resumed = report.resumed ? ` / 이어받음 ${report.resumed}건` : '';
                    showAlert('dd-settings-alert-container', 'success',
                        `복원 성공 — 새로 추가 ${report.created}건 / 덮어씀 ${report.updated}건 / 건너뜀 ${report.skipped}건${resumed}`);
                }

                loadBackupInfo();
            } catch (err) {
                showAlert('dd-settings-alert-container', 'error', '복원 실패: ' + describeError(err));
            }

            if (btn) { btn.disabled = false; btn.textContent = original; }
        }

        function renderRestoreReport(report) {
            const box = document.getElementById('dd-restore-result');
            if (!box || !report) return;

            const rows = [
                ['새로 추가된 콘텐츠', report.created + '건'],
                ['덮어쓴 콘텐츠', report.updated + '건'],
                ['중복이라 건너뛴 콘텐츠', report.skipped + '건'],
                ['복원 실패', report.failed + '건'],
                ['복원된 설정값', report.options + '개']
            ];

            // 지난번 복원이 중간에 끊겼던 항목을 이어받았을 때만 보여 준다.
            if (report.resumed) {
                rows.splice(2, 0, ['이전에 끊겼다가 이어받은 콘텐츠', report.resumed + '건']);
            }

            if (report.media) {
                rows.push(['복원된 이미지 파일', report.media.extracted + '개']);
                if (report.media.skipped) {
                    rows.push(['이미 있어 건너뛴 이미지', report.media.skipped + '개']);
                }
                if (report.media.rejected) {
                    rows.push(['⚠️ 안전하지 않아 거부한 파일', report.media.rejected + '개']);
                }
            }

            let html = '<div class="dd-info-box"><strong>복원 결과</strong><br>'
                + rows.map((r) => escapeHtml(r[0]) + ': ' + escapeHtml(r[1])).join('<br>');

            if (report.source && report.source.generated_at) {
                html += '<br><br><strong>백업 파일 정보</strong><br>'
                    + '생성 시각: ' + escapeHtml(report.source.generated_at) + '<br>'
                    + '플러그인 버전: ' + escapeHtml(report.source.plugin_version);
            }
            if (report.safety_backup) {
                html += '<br><br>' + escapeHtml(report.safety_backup);
            }
            if (report.errors && report.errors.length) {
                html += '<br><br><strong>실패 항목</strong><br>'
                    + report.errors.slice(0, 20).map(escapeHtml).join('<br>');
            }
            html += '</div>';

            box.innerHTML = html;
            box.classList.remove('dd-hidden');
        }
    }

    // ===== Generator Page =====

    function initGenerator() {
        const wrap = document.getElementById('dd-generator');
        if (!wrap) return;

        checkGeneratorApiKey();

        const form = document.getElementById('dd-form-generate');
        if (form) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const topic = document.getElementById('dd-generate-topic').value.trim();
                if (!topic) return;

                const weeksSelect = document.getElementById('dd-generate-weeks');
                const lessonCount = weeksSelect ? parseInt(weeksSelect.value, 10) : 4;
                const levelSelect = document.getElementById('dd-generate-level');
                const level = levelSelect ? levelSelect.value : 'beginner';

                await startGeneration(topic, lessonCount, level, selectedPhases());
            });
        }

        /** 체크된 에셋 단계만 반환 — 해제한 항목은 아예 요청하지 않는다. */
        function selectedPhases() {
            const boxes = document.querySelectorAll('#dd-asset-options input[data-phase]');
            return Array.prototype.filter.call(boxes, (b) => b.checked)
                                         .map((b) => b.dataset.phase);
        }

        /** 선택에 따른 예상 이미지 장수를 실시간으로 보여 준다. */
        function updateCostEstimate() {
            const totalEl = document.getElementById('dd-cost-total');
            if (!totalEl) return;
            const weeksSelect = document.getElementById('dd-generate-weeks');
            const lessons = weeksSelect ? parseInt(weeksSelect.value, 10) : 4;
            const perLesson = Array.prototype.reduce.call(
                document.querySelectorAll('#dd-asset-options input[data-phase]'),
                (sum, b) => sum + (b.checked ? parseInt(b.dataset.cost, 10) || 0 : 0),
                0
            );
            const total = perLesson * lessons;
            totalEl.textContent = total === 0
                ? `0장 (텍스트만 — 이미지 요금 없음)`
                : `${total}장 (강의당 ${perLesson}장 × ${lessons}강의)`;
        }

        document.querySelectorAll('#dd-asset-options input[data-phase]').forEach((b) => {
            b.addEventListener('change', updateCostEstimate);
        });
        const weeksEl = document.getElementById('dd-generate-weeks');
        if (weeksEl) weeksEl.addEventListener('change', updateCostEstimate);
        updateCostEstimate();

        // Generate another
        const btnAnother = document.getElementById('dd-btn-generate-another');
        if (btnAnother) {
            btnAnother.addEventListener('click', () => {
                hideElement('dd-generator-result-card');
                hideElement('dd-generator-progress-card');
                showElement('dd-generator-form-card');
                document.getElementById('dd-generate-topic').value = '';
                document.getElementById('dd-generated-lessons-list').innerHTML = '';
            });
        }

        async function checkGeneratorApiKey() {
            try {
                const result = await apiFetch({ path: API_BASE + '/settings/api-key-status' });
                if (!result || (!result.has_key && !result.is_set)) {
                    showElement('dd-generator-api-warning');
                }
            } catch (err) {
                showElement('dd-generator-api-warning');
            }
        }

        async function startGeneration(topic, lessonCount, level, wantedPhases) {
            const btnGenerate = document.getElementById('dd-btn-generate');
            btnGenerate.disabled = true;
            btnGenerate.textContent = '생성 중...';

            hideElement('dd-generator-result-card');
            showElement('dd-generator-progress-card');
            setProgress('dd-gen-progress-bar', 5);
            document.getElementById('dd-gen-progress-text').textContent = '강좌 개요 생성 중...';
            document.getElementById('dd-generated-lessons-list').innerHTML = '';

            try {
                // Step 1: Generate course outline
                const courseResult = await apiFetch({
                    path: API_BASE + '/generate/course',
                    method: 'POST',
                    data: { topic, lesson_count: lessonCount, level }
                });

                if (!courseResult || !courseResult.course_id || !courseResult.lessons) {
                    throw new Error('강좌 생성 응답이 올바르지 않습니다.');
                }

                const courseId = courseResult.course_id;
                const lessonOutlines = courseResult.lessons;
                const total = lessonOutlines.length;

                setProgress('dd-gen-progress-bar', 20);
                document.getElementById('dd-gen-progress-text').textContent = `강좌 개요 생성 완료. ${total}개 강의 생성 시작...`;

                // Step 2: Generate each lesson sequentially
                for (let i = 0; i < total; i++) {
                    const lessonOutline = lessonOutlines[i];
                    const progressPercent = 20 + Math.round(((i + 1) / total) * 75);

                    document.getElementById('dd-gen-progress-text').textContent = `강의 ${i + 1}/${total} 생성 중...`;

                    const clientRef = makeClientRef();
                    const li = document.createElement('li');
                    li.textContent = `${i + 1}. ${lessonOutline.title || ''} — 본문 생성 중...`;
                    document.getElementById('dd-generated-lessons-list').appendChild(li);

                    let lessonResult = null;
                    try {
                        lessonResult = await apiFetch({
                            path: API_BASE + '/generate/lesson',
                            method: 'POST',
                            data: {
                                course_id: courseId,
                                title: lessonOutline.title,
                                order: lessonOutline.order || (i + 1),
                                client_ref: clientRef
                            }
                        });
                    } catch (lessonErr) {
                        // 서버는 강의를 다 만들었는데 응답만 프록시 타임아웃으로 유실되는
                        // 경우가 있다 (HTML 502/504 → invalid_json). 실패로 단정하기 전에
                        // client_ref 로 실제 생성 여부를 확인한다.
                        lessonResult = await recoverLesson(courseId, clientRef);
                        if (!lessonResult) {
                            li.textContent = `${i + 1}. ${lessonOutline.title || ''} — 실패: ${describeError(lessonErr)}`;
                            li.style.color = 'var(--dd-error)';
                            continue;
                        }
                    }

                    const info = [];
                    if (lessonResult.has_content) info.push('본문');
                    if (lessonResult.slide_count > 0) info.push('슬라이드 ' + lessonResult.slide_count);
                    if (lessonResult.quiz_count > 0) info.push('퀴즈 ' + lessonResult.quiz_count);
                    if (lessonResult.comic_count > 0) info.push('만화 ' + lessonResult.comic_count + '컷');
                    if (lessonResult.storybook_count > 0) info.push('스토리북 ' + lessonResult.storybook_count + '쪽');

                    const baseLabel = `${i + 1}. ${lessonResult.title || lessonOutline.title}` +
                        (info.length ? ' (' + info.join(', ') + ')' : '');
                    li.textContent = baseLabel;
                    li.style.color = 'var(--dd-success)';

                    // 에셋(이미지·영상)은 단계별 개별 요청으로 생성한다.
                    // 실패해도 강의 본문은 이미 저장돼 있으므로 빨간 "실패"가 아니라 회색 안내로 표시.
                    // 관리자가 체크 해제한 단계는 요청조차 보내지 않는다 (이미지 요금 절감).
                    const phases = (lessonResult.pending_assets || [])
                        .filter((p) => !wantedPhases || wantedPhases.indexOf(p) !== -1);
                    const skippedLabels = [];
                    for (let p = 0; p < phases.length; p++) {
                        document.getElementById('dd-gen-progress-text').textContent =
                            `강의 ${i + 1}/${total} — 부가 콘텐츠 ${p + 1}/${phases.length} 생성 중...`;
                        try {
                            const assetRes = await apiFetch({
                                path: API_BASE + '/generate/lesson-assets',
                                method: 'POST',
                                data: { lesson_id: lessonResult.lesson_id, phase: phases[p] }
                            });
                            if (assetRes && !assetRes.ok && !assetRes.skipped) {
                                skippedLabels.push(assetRes.label || phases[p]);
                            }
                        } catch (assetErr) {
                            skippedLabels.push(phases[p]);
                        }
                        setProgress('dd-gen-progress-bar',
                            progressPercent - Math.round((1 - (p + 1) / phases.length) * (75 / total)));
                    }

                    if (skippedLabels.length) {
                        const note = document.createElement('span');
                        note.style.color = 'var(--dd-text-light)';
                        note.textContent = ` · 미생성: ${skippedLabels.join(', ')} (강의 화면의 [이미지] 버튼으로 다시 시도 가능)`;
                        li.appendChild(note);
                    }

                    setProgress('dd-gen-progress-bar', progressPercent);
                }

                // Complete
                setProgress('dd-gen-progress-bar', 100);
                document.getElementById('dd-gen-progress-text').textContent = '모든 강의 생성 완료!';

                // Show result
                showElement('dd-generator-result-card');
                document.getElementById('dd-gen-result-summary').textContent =
                    `"${topic}" 주제로 ${total}개 강의가 포함된 강좌가 생성되었습니다.`;

                const viewLink = document.getElementById('dd-btn-view-generated-course');
                viewLink.href = window.location.href.split('?')[0] + '?page=dd-lms&course=' + courseId;

            } catch (err) {
                showAlert('dd-generator-alert-container', 'error', '강좌 생성에 실패했습니다: ' + (err.message || '알 수 없는 오류'));
                setProgress('dd-gen-progress-bar', 0);
                document.getElementById('dd-gen-progress-text').textContent = '생성 실패';
            } finally {
                btnGenerate.disabled = false;
                btnGenerate.textContent = '강좌 생성하기';
            }
        }
    }

    // ===== Stories Page =====

    function initStories() {
        const wrap = document.getElementById('dd-stories');
        if (!wrap) return;

        let currentStoryId = null;
        let currentStoryData = null;
        let storyConfirmCallback = null;

        loadStories();

        // New story button
        const btnNew = document.getElementById('dd-btn-new-story');
        if (btnNew) {
            btnNew.addEventListener('click', () => openModal('dd-modal-new-story'));
        }

        // Back to list
        const btnBack = document.getElementById('dd-btn-back-stories');
        if (btnBack) {
            btnBack.addEventListener('click', () => {
                hideElement('dd-view-story-detail');
                showElement('dd-view-story-list');
                currentStoryId = null;
                currentStoryData = null;
                loadStories();
            });
        }

        // Confirm action
        const btnConfirm = document.getElementById('dd-btn-story-confirm-action');
        if (btnConfirm) {
            btnConfirm.addEventListener('click', () => {
                if (storyConfirmCallback) storyConfirmCallback();
                closeModal('dd-modal-story-confirm');
                storyConfirmCallback = null;
            });
        }

        // Close modals
        wrap.querySelectorAll('[data-close-modal]').forEach(el => {
            el.addEventListener('click', () => closeModal(el.dataset.closeModal));
        });

        // Generate story form
        const formGen = document.getElementById('dd-form-new-story');
        if (formGen) {
            formGen.addEventListener('submit', async (e) => {
                e.preventDefault();
                const topic = document.getElementById('dd-story-topic').value.trim();
                const level = document.getElementById('dd-story-level').value;
                if (!topic) return;

                const sceneSel = document.getElementById('dd-story-scene-images');
                const sceneImages = sceneSel ? parseInt(sceneSel.value, 10) : 4;
                const coverBox = document.getElementById('dd-story-cover-image');
                const coverImage = coverBox ? coverBox.checked : true;

                const btnGen = document.getElementById('dd-btn-generate-story');
                btnGen.disabled = true;
                btnGen.textContent = '생성 중...';

                closeModal('dd-modal-new-story');
                openModal('dd-modal-story-progress');
                setProgress('dd-story-gen-progress-bar', 10);
                document.getElementById('dd-story-gen-progress-text').textContent = 'AI가 분기형 스토리를 구성하고 있습니다...';

                try {
                    setProgress('dd-story-gen-progress-bar', 30);

                    // 진행 표시 타이머 (30% → 90%까지 서서히 증가)
                    let storyProg = 30;
                    const storyProgTimer = setInterval(() => {
                        if (storyProg < 90) {
                            storyProg += 2;
                            setProgress('dd-story-gen-progress-bar', storyProg);
                        }
                        // 경과 시간 표시
                        const elapsed = Math.round((Date.now() - storyStartTime) / 1000);
                        const progText = document.getElementById('dd-story-gen-progress-text');
                        if (progText && elapsed > 10) {
                            progText.textContent = 'AI가 분기형 스토리를 구성하고 있습니다... (' + elapsed + '초)';
                        }
                    }, 3000);
                    const storyStartTime = Date.now();

                    // 330초 타임아웃 (서버 300초 + 여유 30초)
                    const storyController = new AbortController();
                    const storyTimeout = setTimeout(() => storyController.abort(), 330000);

                    let result;
                    try {
                        result = await apiFetch({
                            path: API_BASE + '/generate/story',
                            method: 'POST',
                            data: {
                                topic,
                                level,
                                course_id: 0,
                                // 이미지가 요금의 대부분이라 관리자가 장수를 직접 고른다
                                scene_images: sceneImages,
                                cover_image: coverImage
                            },
                            signal: storyController.signal
                        });
                    } finally {
                        clearTimeout(storyTimeout);
                        clearInterval(storyProgTimer);
                    }

                    setProgress('dd-story-gen-progress-bar', 100);
                    document.getElementById('dd-story-gen-progress-text').textContent = '스토리 생성 완료!';

                    setTimeout(() => {
                        closeModal('dd-modal-story-progress');
                        showAlert('dd-stories-alert-container', 'success', '인터랙티브 스토리가 생성되었습니다!');
                        formGen.reset();
                        loadStories();
                    }, 800);

                } catch (err) {
                    closeModal('dd-modal-story-progress');
                    let errMsg = err.message || '알 수 없는 오류';
                    if (err.name === 'AbortError') {
                        errMsg = '서버 응답 시간이 초과되었습니다 (5분). Gemini API가 일시적으로 응답하지 않을 수 있습니다. 잠시 후 다시 시도해 주세요.';
                    }
                    showAlert('dd-stories-alert-container', 'error', '스토리 생성에 실패했습니다: ' + errMsg);
                } finally {
                    btnGen.disabled = false;
                    btnGen.textContent = 'AI로 생성하기';
                }
            });
        }

        // Node editor form
        const formNodes = document.getElementById('dd-form-edit-nodes');
        if (formNodes) {
            formNodes.addEventListener('submit', async (e) => {
                e.preventDefault();
                const storyId = document.getElementById('dd-edit-story-id').value;
                const jsonStr = document.getElementById('dd-story-nodes-json').value.trim();

                let nodesData;
                try {
                    nodesData = JSON.parse(jsonStr);
                } catch (parseErr) {
                    showAlert('dd-stories-alert-container', 'error', 'JSON 형식이 올바르지 않습니다.');
                    return;
                }

                try {
                    await apiFetch({
                        path: API_BASE + '/stories/' + storyId,
                        method: 'PUT',
                        data: { nodes: nodesData }
                    });
                    closeModal('dd-modal-edit-nodes');
                    showAlert('dd-stories-alert-container', 'success', '노드 데이터가 저장되었습니다.');
                    if (currentStoryId) showStoryDetail(currentStoryId);
                } catch (err) {
                    showAlert('dd-stories-alert-container', 'error', '노드 저장에 실패했습니다: ' + (err.message || ''));
                }
            });
        }

        // Edit nodes button
        const btnEditNodes = document.getElementById('dd-btn-edit-story-nodes');
        if (btnEditNodes) {
            btnEditNodes.addEventListener('click', () => {
                if (!currentStoryData) return;
                document.getElementById('dd-edit-story-id').value = currentStoryData.id;
                document.getElementById('dd-story-nodes-json').value = JSON.stringify(currentStoryData.nodes || {}, null, 2);
                openModal('dd-modal-edit-nodes');
            });
        }

        // Toggle public in detail view
        const togglePublic = document.getElementById('dd-story-toggle-public');
        if (togglePublic) {
            togglePublic.addEventListener('change', async () => {
                if (!currentStoryId) return;
                try {
                    const result = await apiFetch({
                        path: API_BASE + '/stories/' + currentStoryId + '/toggle-public',
                        method: 'POST'
                    });
                    updatePublicLinkUI(result.active, result.url);
                    showAlert('dd-stories-alert-container', 'success', '공개 링크가 ' + (result.active ? '활성화' : '비활성화') + '되었습니다.');

                    if (result.active && result.url) {
                        document.getElementById('dd-story-qr-url').textContent = result.url;
                        document.getElementById('dd-story-qr-image').src =
                            'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(result.url);
                        openModal('dd-modal-story-qr');
                    }
                } catch (err) {
                    togglePublic.checked = !togglePublic.checked;
                    showAlert('dd-stories-alert-container', 'error', '공개 링크 변경에 실패했습니다.');
                }
            });
        }

        // Copy link buttons
        const btnCopyLink = document.getElementById('dd-btn-copy-story-link');
        if (btnCopyLink) {
            btnCopyLink.addEventListener('click', () => {
                const url = document.getElementById('dd-story-public-url').textContent;
                if (url && navigator.clipboard) {
                    navigator.clipboard.writeText(url).then(() => {
                        showAlert('dd-stories-alert-container', 'success', '링크가 복사되었습니다.');
                    });
                }
            });
        }

        const btnCopyQR = document.getElementById('dd-btn-copy-story-qr');
        if (btnCopyQR) {
            btnCopyQR.addEventListener('click', () => {
                const url = document.getElementById('dd-story-qr-url').textContent;
                if (url && navigator.clipboard) {
                    navigator.clipboard.writeText(url).then(() => {
                        showAlert('dd-stories-alert-container', 'success', '링크가 복사되었습니다.');
                    });
                }
            });
        }

        // ===== Load Stories =====
        async function loadStories() {
            const grid = document.getElementById('dd-story-grid');
            const loading = document.getElementById('dd-story-loading');
            const empty = document.getElementById('dd-story-empty');

            showElement(loading);
            hideElement(empty);
            grid.innerHTML = '';

            try {
                const stories = await apiFetch({ path: API_BASE + '/stories' });
                hideElement(loading);

                if (!stories || stories.length === 0) {
                    showElement(empty);
                    updateStoryStats(0, 0);
                    return;
                }

                hideElement(empty);
                let publicCount = 0;

                stories.forEach(story => {
                    if (story.public_active) publicCount++;
                    grid.appendChild(createStoryCard(story));
                });

                updateStoryStats(stories.length, publicCount);
            } catch (err) {
                hideElement(loading);
                showAlert('dd-stories-alert-container', 'error', '스토리 목록을 불러오는데 실패했습니다.');
            }
        }

        function updateStoryStats(total, publicCount) {
            const elTotal = document.getElementById('dd-stat-stories');
            const elPublic = document.getElementById('dd-stat-story-public');
            if (elTotal) elTotal.textContent = total;
            if (elPublic) elPublic.textContent = publicCount;
        }

        function createStoryCard(story) {
            const card = document.createElement('div');
            card.className = 'dd-course-card';

            const levelLabels = { beginner: '입문', intermediate: '중급', advanced: '고급' };
            const levelLabel = levelLabels[story.level] || story.level;
            const storyNodes = (story.nodes && story.nodes.nodes) ? story.nodes.nodes : story.nodes || {};
            const nodeCount = (typeof storyNodes === 'object' && !Array.isArray(storyNodes)) ? Object.keys(storyNodes).filter(k => k.startsWith('node')).length : 0;
            const desc = story.description || '';
            const isPublic = story.public_active;

            const thumbStyle = story.cover_image
                ? `background-image:url('${escapeHtml(story.cover_image)}');background-size:cover;background-position:center;`
                : '';

            card.innerHTML = `
                <div class="dd-course-thumb" style="${thumbStyle}"></div>
                <div class="dd-course-info">
                    <h3 class="dd-course-title">${escapeHtml(story.title)}</h3>
                    <p class="dd-course-desc">${escapeHtml(desc.substring(0, 80))}${desc.length > 80 ? '...' : ''}</p>
                    <div class="dd-course-meta">
                        <span class="dd-badge">${escapeHtml(levelLabel)}</span>
                        <span class="dd-badge dd-badge-count">${nodeCount}개 노드</span>
                        <span class="dd-badge ${isPublic ? 'dd-badge-published' : 'dd-badge-draft'}">${isPublic ? '공개' : '비공개'}</span>
                    </div>
                    <div class="dd-course-actions">
                        <button type="button" class="dd-btn dd-btn-secondary dd-btn-sm" data-action="detail-story" data-id="${story.id}">
                            상세보기
                        </button>
                        <button type="button" class="dd-btn dd-btn-danger dd-btn-sm" data-action="delete-story" data-id="${story.id}">
                            삭제
                        </button>
                    </div>
                </div>
            `;

            card.querySelector('[data-action="detail-story"]').addEventListener('click', () => {
                showStoryDetail(story.id);
            });

            card.querySelector('[data-action="delete-story"]').addEventListener('click', () => {
                storyConfirmCallback = async () => {
                    try {
                        await apiFetch({
                            path: API_BASE + '/stories/' + story.id,
                            method: 'DELETE'
                        });
                        showAlert('dd-stories-alert-container', 'success', '스토리가 삭제되었습니다.');
                        loadStories();
                    } catch (err) {
                        showAlert('dd-stories-alert-container', 'error', '스토리 삭제에 실패했습니다.');
                    }
                };
                document.getElementById('dd-story-confirm-title').textContent = '스토리 삭제';
                document.getElementById('dd-story-confirm-message').textContent = `"${story.title}" 스토리를 삭제하시겠습니까?`;
                openModal('dd-modal-story-confirm');
            });

            return card;
        }

        // ===== Story Detail =====
        async function showStoryDetail(storyId) {
            currentStoryId = storyId;
            hideElement('dd-view-story-list');
            showElement('dd-view-story-detail');

            try {
                const story = await apiFetch({ path: API_BASE + '/stories/' + storyId });
                currentStoryData = story;

                document.getElementById('dd-story-detail-title').textContent = story.title || '';
                document.getElementById('dd-story-detail-desc').textContent = story.description || '';

                const levelLabels = { beginner: '입문', intermediate: '중급', advanced: '고급' };
                document.getElementById('dd-story-detail-level').textContent = levelLabels[story.level] || story.level;

                const storyNodes = (story.nodes && story.nodes.nodes) ? story.nodes.nodes : story.nodes || {};
            const nodeCount = (typeof storyNodes === 'object' && !Array.isArray(storyNodes)) ? Object.keys(storyNodes).filter(k => k.startsWith('node')).length : 0;
                document.getElementById('dd-story-detail-node-count').textContent = nodeCount + '개 노드';

                // Public link
                updatePublicLinkUI(story.public_active, story.public_url);
                document.getElementById('dd-story-toggle-public').checked = story.public_active;

                // Cover image
                const coverImg = document.getElementById('dd-story-cover-preview');
                const coverNone = document.getElementById('dd-story-cover-none');
                if (story.cover_image) {
                    coverImg.src = story.cover_image;
                    coverImg.style.display = '';
                    coverNone.style.display = 'none';
                } else {
                    coverImg.style.display = 'none';
                    coverNone.style.display = '';
                }

            } catch (err) {
                showAlert('dd-stories-alert-container', 'error', '스토리 정보를 불러오는데 실패했습니다.');
            }
        }

        function updatePublicLinkUI(active, url) {
            const urlEl = document.getElementById('dd-story-public-url');
            const copyBtn = document.getElementById('dd-btn-copy-story-link');
            if (active && url) {
                urlEl.textContent = url;
                showElement(copyBtn);
            } else {
                urlEl.textContent = '비활성화됨';
                hideElement(copyBtn);
            }
        }
    }

    // ===== 중국어 노래 학습 (드라마와 같은 자막 파이프라인 재사용) =====
    function initSong() {
        const wrap = document.getElementById('dd-song');
        if (!wrap) return;

        let songTracks = [];
        let subtitleData = {};
        let courseId = null;

        checkSongApiKeys();

        // ── 장르/연도 재구조화: 모드 토글 + 장르 직접입력 + 기존 강좌 로딩 ──
        wrap.querySelectorAll('input[name="dd-song-mode"]').forEach(function (r) {
            r.addEventListener('change', function () {
                const isExisting = this.value === 'existing';
                document.getElementById('dd-song-new-fields').classList.toggle('dd-hidden', isExisting);
                document.getElementById('dd-song-existing-fields').classList.toggle('dd-hidden', !isExisting);
            });
        });
        const songGenreSel = document.getElementById('dd-song-genre');
        if (songGenreSel) {
            songGenreSel.addEventListener('change', function () {
                document.getElementById('dd-song-genre-custom').classList.toggle('dd-hidden', this.value !== '__custom');
            });
        }
        (async function loadExistingSongCourses() {
            const sel = document.getElementById('dd-song-existing-course');
            if (!sel) return;
            try {
                const courses = await apiFetch({ path: API_BASE + '/courses' });
                const songCourses = (courses || []).filter(c => c.course_type === 'song');
                if (!songCourses.length) {
                    sel.innerHTML = '<option value="">기존 중국어 노래 학습 없음</option>';
                    return;
                }
                sel.innerHTML = '<option value="">강좌 선택...</option>';
                songCourses.forEach(c => {
                    const o = document.createElement('option');
                    o.value = c.id;
                    o.textContent = c.title + (c.genre ? ' [' + c.genre + ']' : '') + ' · ' + (c.lesson_count || 0) + '곡';
                    sel.appendChild(o);
                });
            } catch (e) {
                sel.innerHTML = '<option value="">불러오기 실패</option>';
            }
        })();

        // Step 1: Fetch URL info
        const formUrl = document.getElementById('dd-form-song-url');
        if (formUrl) {
            formUrl.addEventListener('submit', async (e) => {
                e.preventDefault();
                const url = document.getElementById('dd-song-url').value.trim();
                if (!url) return;
                const btn = document.getElementById('dd-btn-song-fetch-info');
                btn.disabled = true; btn.textContent = '가져오는 중...';
                try {
                    const result = await apiFetch({
                        path: API_BASE + '/song/fetch-info',
                        method: 'POST',
                        data: { url }
                    });
                    songTracks = result.items || [];
                    const title = result.playlist_title || (songTracks[0] && songTracks[0].title) || '';
                    document.getElementById('dd-song-title').value = title;
                    const channelEl = document.getElementById('dd-song-channel');
                    if (result.channel) channelEl.textContent = result.channel;
                    renderTrackList(songTracks, result.type === 'video');
                    showElement('dd-song-list-card');
                    subtitleData = {};
                } catch (err) {
                    showAlert('dd-song-alert-container', 'error', '영상 정보를 가져올 수 없습니다: ' + (err.message || ''));
                } finally {
                    btn.disabled = false; btn.textContent = '영상 정보 가져오기';
                }
            });
        }

        function renderTrackList(items, isSingle) {
            const list = document.getElementById('dd-song-track-list');
            list.innerHTML = '';
            items.forEach((item, idx) => {
                const row = document.createElement('label');
                row.className = 'dd-song-track-row';
                const checked = isSingle || idx < 5 ? 'checked' : '';
                row.innerHTML = `
                    <input type="checkbox" class="dd-song-track-check" data-idx="${idx}" ${checked}>
                    <img class="dd-song-track-thumb" src="${escapeHtml(item.thumbnail || '')}" alt="">
                    <div class="dd-song-track-info">
                        <span class="dd-song-track-title">${escapeHtml(item.title)}</span>
                        <span class="dd-song-track-status" data-video="${escapeHtml(item.video_id)}"></span>
                    </div>
                    <input type="text" class="dd-song-track-year dd-input" data-idx="${idx}" placeholder="연도" value="${escapeHtml(item.year || '')}" maxlength="4" inputmode="numeric" style="width:80px;flex:none;margin-left:auto;" onclick="event.stopPropagation()">
                `;
                list.appendChild(row);
            });
        }

        // 가사 자막 확인
        const btnCheckSubs = document.getElementById('dd-btn-song-check-subs');
        if (btnCheckSubs) {
            btnCheckSubs.addEventListener('click', async () => {
                btnCheckSubs.disabled = true; btnCheckSubs.textContent = '확인 중...';
                const checked = getSelectedTracks();
                for (const item of checked) {
                    const statusEl = wrap.querySelector(`[data-video="${item.video_id}"]`);
                    if (statusEl) statusEl.textContent = '확인 중...';
                    try {
                        const result = await apiFetch({
                            path: API_BASE + '/song/fetch-subtitles',
                            method: 'POST',
                            data: { video_id: item.video_id }
                        });
                        subtitleData[item.video_id] = result;
                        if (statusEl) {
                            if (result.found) {
                                const src = result.source || result.type;
                                const lbl = src === 'translated' ? '자동번역' : (src === 'asr' ? '자동생성' : '수동 가사');
                                statusEl.textContent = '가사 있음 (' + lbl + (result.detail ? ' · ' + result.detail : '') + ')';
                                statusEl.className = 'dd-song-track-status dd-sub-found';
                            } else {
                                // 진단 정보 표시 — 마지막 시도의 사유 요약
                                const attempts = result.attempts || [];
                                let detail = '가사 없음';
                                if (attempts.length > 0) {
                                    const last = attempts[attempts.length - 1];
                                    detail += ' (' + last.label + ': ' + (last.reason || '미상') + ')';
                                }
                                statusEl.textContent = detail;
                                statusEl.className = 'dd-song-track-status dd-sub-missing';
                                // hover 시 전체 진단 로그 (모든 단계의 모든 log call)
                                const tooltip = attempts.map(a => {
                                    const lines = (a.all_logs && a.all_logs.length)
                                        ? a.all_logs.map(l => '  · ' + l).join('\n')
                                        : '  · ' + (a.reason || '미상');
                                    return '[' + a.label + ']\n' + lines;
                                }).join('\n\n');
                                statusEl.title = tooltip;
                            }
                        }
                    } catch (err) {
                        if (statusEl) {
                            statusEl.textContent = '확인 실패: ' + (err.message || '');
                            statusEl.className = 'dd-song-track-status dd-sub-missing';
                        }
                    }
                }
                btnCheckSubs.disabled = false; btnCheckSubs.textContent = '가사 자막 확인';

                // 자막을 못 가져온 곡은 '가사 직접 입력' 칸을 바로 노출해 헤매지 않게 한다.
                const notFound = checked.filter(it => {
                    const sd = subtitleData[it.video_id];
                    return !sd || !sd.found;
                });
                if (notFound.length > 0) {
                    showSubtitleFallback(notFound);
                    showAlert('dd-song-alert-container', 'warning',
                        notFound.length + '곡은 자동 자막 추출에 실패했습니다 (YouTube 차단 또는 자막 없음). ' +
                        '아래 “가사 직접 입력”에 가사를 붙여넣으면 그대로 강좌를 만들 수 있어요.');
                }
            });
        }

        // Generate
        const btnGenerate = document.getElementById('dd-btn-song-generate');
        if (btnGenerate) {
            btnGenerate.addEventListener('click', async () => {
                const selected = getSelectedTracks();
                if (selected.length === 0) {
                    showAlert('dd-song-alert-container', 'error', '곡을 선택해 주세요.');
                    return;
                }
                const needManual = [];
                for (const item of selected) {
                    const sd = subtitleData[item.video_id];
                    if (!sd || !sd.found) needManual.push(item);
                }
                if (needManual.length > 0) {
                    showSubtitleFallback(needManual);
                    return;
                }
                await startSongGeneration(selected);
            });
        }

        // Subtitle fallback continue
        const btnSubContinue = document.getElementById('dd-btn-song-subtitle-continue');
        if (btnSubContinue) {
            btnSubContinue.addEventListener('click', async () => {
                const textareas = wrap.querySelectorAll('.dd-song-sub-textarea');
                let allFilled = true;
                textareas.forEach(ta => {
                    const vid = ta.dataset.video;
                    const text = ta.value.trim();
                    if (text) {
                        subtitleData[vid] = { found: true, type: 'manual', full_text: text };
                    } else {
                        allFilled = false;
                    }
                });
                if (!allFilled) {
                    showAlert('dd-song-alert-container', 'error', '모든 곡의 가사를 입력하거나, 가사 없는 곡 선택을 해제하세요.');
                    return;
                }
                hideElement('dd-song-subtitle-card');
                const selected = getSelectedTracks();
                await startSongGeneration(selected);
            });
        }

        function showSubtitleFallback(needManual) {
            const container = document.getElementById('dd-song-subtitle-inputs');
            container.innerHTML = '';
            needManual.forEach((item, idx) => {
                const div = document.createElement('div');
                div.className = 'dd-form-group dd-sub-fallback-item';
                div.innerHTML = `
                    <label>${escapeHtml(item.title)} — 가사 입력</label>
                    <div class="dd-sub-upload-row" style="display:flex;gap:0.5rem;margin-bottom:0.5rem;align-items:center;">
                        <label class="dd-btn dd-btn-secondary dd-btn-sm" style="cursor:pointer;display:inline-flex;align-items:center;gap:0.3rem;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            파일 업로드 (.srt, .txt)
                            <input type="file" class="dd-song-sub-file-input" data-video="${escapeHtml(item.video_id)}" data-idx="${idx}" accept=".srt,.txt,.vtt" style="display:none;">
                        </label>
                        <span class="dd-sub-file-name" id="dd-song-sub-fname-${idx}" style="font-size:0.82rem;color:#666;"></span>
                    </div>
                    <textarea class="dd-input dd-song-sub-textarea" data-video="${escapeHtml(item.video_id)}"
                              rows="8" placeholder="가사를 줄 단위로 붙여넣으세요. 한자만 또는 한자+병음+한국어 어느 형식이든 OK..."></textarea>
                    <span class="dd-help-text">QQ音乐·网易云音乐·Spotify 가사를 복사하거나 downsub.com에서 SRT 받아 업로드.</span>
                `;
                container.appendChild(div);
                div.querySelector('.dd-song-sub-file-input').addEventListener('change', function (e) {
                    const file = e.target.files[0];
                    if (!file) return;
                    document.getElementById('dd-song-sub-fname-' + idx).textContent = '📄 ' + file.name;
                    const reader = new FileReader();
                    reader.onload = function () {
                        div.querySelector('.dd-song-sub-textarea').value = reader.result;
                    };
                    reader.readAsText(file, 'UTF-8');
                });
            });
            showElement('dd-song-subtitle-card');
        }

        async function startSongGeneration(selected) {
            const level = document.getElementById('dd-song-level').value;
            const mode = (wrap.querySelector('input[name="dd-song-mode"]:checked') || {}).value || 'new';
            let genre = '', existingCourseId = 0, title;
            if (mode === 'existing') {
                existingCourseId = parseInt(document.getElementById('dd-song-existing-course').value, 10) || 0;
                if (!existingCourseId) {
                    showAlert('dd-song-alert-container', 'error', '곡을 추가할 기존 강좌를 선택하세요.');
                    return;
                }
                title = '';
            } else {
                const gsel = document.getElementById('dd-song-genre').value;
                genre = gsel === '__custom'
                    ? document.getElementById('dd-song-genre-custom').value.trim()
                    : gsel;
                title = document.getElementById('dd-song-title').value.trim() || genre || '중국어 노래 학습';
            }
            const btnGen = document.getElementById('dd-btn-song-generate');
            btnGen.disabled = true; btnGen.textContent = '생성 중...';
            hideElement('dd-song-result-card');
            hideElement('dd-song-subtitle-card');
            showElement('dd-song-progress-card');
            setProgress('dd-song-progress-bar', 5);
            document.getElementById('dd-song-progress-text').textContent = '중국어 노래 학습 생성 중...';
            document.getElementById('dd-song-lessons-list').innerHTML = '';
            try {
                const tracks = selected.map(item => ({
                    video_id: item.video_id,
                    title: item.title,
                    year: item.year || '',
                    subtitle_text: (subtitleData[item.video_id] || {}).full_text || '',
                    subtitle_source: (subtitleData[item.video_id] || {}).type || 'manual'
                }));
                const courseResult = await apiFetch({
                    path: API_BASE + '/song/generate',
                    method: 'POST',
                    data: { title, level, genre, existing_course_id: existingCourseId, tracks }
                });
                if (!courseResult || !courseResult.course_id) {
                    throw new Error('강좌 생성 응답이 올바르지 않습니다.');
                }
                courseId = courseResult.course_id;
                const courseTitle = courseResult.title || title;
                const orderOffset = courseResult.order_offset || 0;
                const total = tracks.length;
                setProgress('dd-song-progress-bar', 15);
                document.getElementById('dd-song-progress-text').textContent = `강좌 생성 완료. ${total}곡 강의 생성 시작...`;
                for (let i = 0; i < total; i++) {
                    const track = tracks[i];
                    const pct = 15 + Math.round(((i + 1) / total) * 80);
                    document.getElementById('dd-song-progress-text').textContent = `곡 ${i + 1}/${total} 생성 중: ${track.title}`;
                    try {
                        const lessonResult = await apiFetch({
                            path: API_BASE + '/song/generate-track',
                            method: 'POST',
                            data: { course_id: courseId, order: orderOffset + i + 1, level, track }
                        });
                        const li = document.createElement('li');
                        const info = [];
                        if (lessonResult.has_content) info.push('본문');
                        if (lessonResult.slide_count > 0) info.push('슬라이드 ' + lessonResult.slide_count);
                        if (lessonResult.quiz_count > 0) info.push('퀴즈 ' + lessonResult.quiz_count);
                        if (lessonResult.lyric_count > 0) info.push('가사 ' + lessonResult.lyric_count + '줄');
                        const detail = info.length > 0 ? ' (' + info.join(', ') + ')' : '';
                        li.textContent = `${i + 1}. ${lessonResult.title || track.title}${detail}`;
                        li.style.color = 'var(--dd-success)';
                        document.getElementById('dd-song-lessons-list').appendChild(li);
                        setProgress('dd-song-progress-bar', pct);
                    } catch (lessonErr) {
                        const li = document.createElement('li');
                        li.textContent = `${i + 1}. ${track.title} — 실패: ${describeError(lessonErr)}`;
                        li.style.color = 'var(--dd-error)';
                        document.getElementById('dd-song-lessons-list').appendChild(li);
                    }
                }
                setProgress('dd-song-progress-bar', 100);
                document.getElementById('dd-song-progress-text').textContent = '모든 곡 생성 완료!';
                showElement('dd-song-result-card');
                document.getElementById('dd-song-result-summary').textContent =
                    `"${courseTitle}" 강좌에 ${total}곡 노래 강의가 ${courseResult.append ? '추가' : '생성'}되었습니다.`;
                const viewLink = document.getElementById('dd-btn-view-song-course');
                viewLink.href = window.location.href.split('?')[0] + '?page=dd-lms&course=' + courseId;
            } catch (err) {
                showAlert('dd-song-alert-container', 'error', '중국어 노래 학습 생성에 실패했습니다: ' + (err.message || ''));
                setProgress('dd-song-progress-bar', 0);
                document.getElementById('dd-song-progress-text').textContent = '생성 실패';
            } finally {
                btnGen.disabled = false; btnGen.textContent = '선택한 곡으로 강좌 생성';
            }
        }

        function getSelectedTracks() {
            const checks = wrap.querySelectorAll('.dd-song-track-check:checked');
            const selected = [];
            checks.forEach(cb => {
                const idx = parseInt(cb.dataset.idx, 10);
                if (songTracks[idx]) {
                    const yearEl = wrap.querySelector(`.dd-song-track-year[data-idx="${idx}"]`);
                    selected.push(Object.assign({}, songTracks[idx], { year: yearEl ? yearEl.value.trim() : '' }));
                }
            });
            return selected;
        }

        const btnAnother = document.getElementById('dd-btn-song-another');
        if (btnAnother) {
            btnAnother.addEventListener('click', () => {
                hideElement('dd-song-result-card');
                hideElement('dd-song-progress-card');
                hideElement('dd-song-list-card');
                hideElement('dd-song-subtitle-card');
                showElement('dd-song-url-card');
                document.getElementById('dd-song-url').value = '';
                document.getElementById('dd-song-lessons-list').innerHTML = '';
                songTracks = []; subtitleData = {}; courseId = null;
            });
        }

        async function checkSongApiKeys() {
            try {
                const [gemini, yt] = await Promise.all([
                    apiFetch({ path: API_BASE + '/settings/api-key-status' }),
                    apiFetch({ path: API_BASE + '/settings/youtube-key-status' })
                ]);
                if ((!gemini || !gemini.has_key) || (!yt || !yt.has_key)) {
                    showElement('dd-song-api-warning');
                }
            } catch (err) {
                showElement('dd-song-api-warning');
            }
        }
    }

    // ===== Initialize on DOM Ready =====

    document.addEventListener('DOMContentLoaded', () => {
        initDashboard();
        initSettings();
        initGenerator();
        initStories();
        initSong();
    });

    /* ─── Image Regeneration Grid ─── */
    function renderImageGrid(container, lessonId, data) {
        let html = '';

        // 1. 핵심표현 배너
        html += buildImageCard(
            '핵심표현 배너',
            data.key_expr_image || '',
            'key_expr', 0
        );

        // 2. 실전대화 이미지
        html += buildImageCard(
            '실전대화 이미지',
            data.dialogue_image || '',
            'dialogue', 0
        );

        // 3. 만화 패널 (4개)
        const comicImages = data.comic_images || [];
        for (let i = 0; i < 4; i++) {
            html += buildImageCard(
                '만화 패널 ' + (i + 1),
                comicImages[i] || '',
                'comic_panel', i
            );
        }

        // 4. 스토리북 페이지 (최대 6개)
        const storybook = data.storybook || [];
        for (let i = 0; i < storybook.length; i++) {
            html += buildImageCard(
                '스토리북 ' + (i + 1) + '페이지',
                (storybook[i] && storybook[i].image) || '',
                'storybook_page', i
            );
        }

        container.innerHTML = html || '<p>이미지 데이터가 없습니다.</p>';

        // Bind regeneration buttons
        container.querySelectorAll('.dd-img-regen-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const type = btn.dataset.type;
                const index = parseInt(btn.dataset.index);
                const card = btn.closest('.dd-img-card');
                const img = card.querySelector('img');
                const status = card.querySelector('.dd-img-status');

                btn.disabled = true;
                btn.textContent = '생성 중...';
                if (status) status.textContent = 'AI 이미지 생성 중... (최대 30초)';
                if (status) status.className = 'dd-img-status dd-img-status--loading';

                try {
                    const result = await apiFetch({
                        path: API_BASE + '/lessons/' + lessonId + '/regenerate-image',
                        method: 'POST',
                        data: { type, index }
                    });

                    if (result.success && result.image) {
                        if (img) {
                            img.src = result.image.url + '?t=' + Date.now();
                            img.style.display = '';
                        } else {
                            const placeholder = card.querySelector('.dd-img-placeholder');
                            if (placeholder) {
                                placeholder.outerHTML = '<img src="' + escapeHtml(result.image.url) + '?t=' + Date.now() + '" alt="" class="dd-img-thumb">';
                            }
                        }
                        if (status) { status.textContent = '재생성 완료!'; status.className = 'dd-img-status dd-img-status--success'; }
                        btn.textContent = '재생성';
                    } else {
                        if (status) { status.textContent = result.message || '생성 실패'; status.className = 'dd-img-status dd-img-status--error'; }
                        btn.textContent = '재시도';
                    }
                } catch (err) {
                    if (status) { status.textContent = '오류: ' + (err.message || '알 수 없는 오류'); status.className = 'dd-img-status dd-img-status--error'; }
                    btn.textContent = '재시도';
                }
                btn.disabled = false;
            });
        });
    }

    function buildImageCard(label, imageUrl, type, index) {
        const hasImage = !!imageUrl;
        const imgHtml = hasImage
            ? '<img src="' + escapeHtml(imageUrl) + '" alt="" class="dd-img-thumb">'
            : '<div class="dd-img-placeholder">이미지 없음</div>';

        return '<div class="dd-img-card">'
            + imgHtml
            + '<div class="dd-img-info">'
            + '<span class="dd-img-label">' + escapeHtml(label) + '</span>'
            + '<span class="dd-img-status">' + (hasImage ? '✓ 있음' : '✗ 없음') + '</span>'
            + '</div>'
            + '<button type="button" class="dd-btn dd-btn-sm dd-img-regen-btn" data-type="' + type + '" data-index="' + index + '">'
            + (hasImage ? '재생성' : '생성')
            + '</button>'
            + '</div>';
    }

})();
