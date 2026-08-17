<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="dd-admin-wrap" id="dd-newsletters">
    <div id="dd-alert-container"></div>

    <div class="dd-admin-header">
        <h1>뉴스레터 관리</h1>
        <div class="dd-header-actions">
            <button type="button" class="dd-btn dd-btn-primary" id="dd-btn-gen-newsletter">
                + AI 뉴스레터 생성
            </button>
        </div>
    </div>

    <!-- 생성 폼 (접혀 있다가 버튼 클릭 시 표시) -->
    <div class="dd-gen-form dd-hidden" id="dd-newsletter-gen-form">
        <div class="dd-form-card">
            <h3>AI 뉴스레터 생성</h3>
            <p class="dd-form-desc">중국 대중문화 관련 주제를 입력하면 Gemini가 뉴스레터를 자동 작성합니다.</p>
            <div class="dd-form-group">
                <label for="dd-nl-topic">주제 (선택)</label>
                <input type="text" id="dd-nl-topic" placeholder="예: 최신 중국 드라마 트렌드, C-POP 신곡, 중국 음식 문화" class="dd-input">
                <small>비워두면 최신 중국 대중문화 트렌드로 자동 생성됩니다.</small>
            </div>
            <div class="dd-form-group">
                <label>이미지 생성</label>
                <div class="dd-asset-options">
                    <label class="dd-check">
                        <input type="checkbox" id="dd-nl-cover-image" checked>
                        <span>커버 이미지 <em>1장</em></span>
                    </label>
                    <label class="dd-check">
                        <input type="checkbox" id="dd-nl-section-images" checked>
                        <span>섹션별 삽화 <em>3~4장</em></span>
                    </label>
                </div>
                <small>본문·어휘는 항상 생성됩니다. 요금은 이미지에서만 발생합니다.</small>
            </div>
            <div class="dd-form-actions">
                <button type="button" class="dd-btn dd-btn-primary" id="dd-btn-submit-newsletter">
                    생성하기
                </button>
                <button type="button" class="dd-btn dd-btn-secondary" id="dd-btn-cancel-newsletter">
                    취소
                </button>
            </div>
            <div class="dd-loading dd-hidden" id="dd-newsletter-loading">
                <div class="dd-spinner"></div>
                <p>뉴스레터를 생성하고 있습니다... (최대 1분 소요)</p>
            </div>
        </div>
    </div>

    <!-- 뉴스레터 그리드 -->
    <div class="dd-card-grid" id="dd-newsletter-grid"></div>

    <!-- 빈 상태 -->
    <div class="dd-empty-state dd-hidden" id="dd-newsletter-empty">
        <div class="dd-empty-icon">📰</div>
        <div class="dd-empty-title">아직 뉴스레터가 없습니다</div>
        <div class="dd-empty-desc">
            AI 생성 버튼을 눌러 중국 대중문화 뉴스레터를 만들어보세요!
        </div>
    </div>

    <div class="dd-loading" id="dd-newsletters-loading"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const apiFetch = (wp && wp.apiFetch) ? wp.apiFetch : null;
    if (!apiFetch) {
        document.getElementById('dd-alert-container').innerHTML =
            '<div class="dd-alert dd-alert-error">wp.apiFetch를 불러올 수 없습니다. 페이지를 새로고침 해주세요.</div>';
        return;
    }

    const genBtn = document.getElementById('dd-btn-gen-newsletter');
    const genForm = document.getElementById('dd-newsletter-gen-form');
    const cancelBtn = document.getElementById('dd-btn-cancel-newsletter');
    const submitBtn = document.getElementById('dd-btn-submit-newsletter');
    const grid = document.getElementById('dd-newsletter-grid');
    const empty = document.getElementById('dd-newsletter-empty');
    const loading = document.getElementById('dd-newsletters-loading');
    const genLoading = document.getElementById('dd-newsletter-loading');

    const catLabels = {
        entertainment: '엔터테인먼트',
        music: '음악',
        food: '음식',
        tech: '테크',
        drama: '드라마',
        social: '소셜미디어',
    };

    genBtn.addEventListener('click', () => {
        genForm.classList.toggle('dd-hidden');
    });
    cancelBtn.addEventListener('click', () => {
        genForm.classList.add('dd-hidden');
    });

    submitBtn.addEventListener('click', async () => {
        const topic = document.getElementById('dd-nl-topic').value.trim();
        submitBtn.disabled = true;
        genLoading.classList.remove('dd-hidden');

        try {
            const res = await wp.apiFetch({
                path: 'dingdong-lms/v1/generate/newsletter',
                method: 'POST',
                data: {
                    topic: topic,
                    // 이미지가 요금의 대부분 — 관리자가 생성 전에 직접 고른다
                    cover_image: document.getElementById('dd-nl-cover-image').checked,
                    section_images: document.getElementById('dd-nl-section-images').checked
                },
            });
            showAlert('뉴스레터가 생성되었습니다!', 'success');
            genForm.classList.add('dd-hidden');
            document.getElementById('dd-nl-topic').value = '';
            loadNewsletters();
        } catch (e) {
            showAlert('생성 실패: ' + (e.message || '알 수 없는 오류'), 'error');
        } finally {
            submitBtn.disabled = false;
            genLoading.classList.add('dd-hidden');
        }
    });

    async function loadNewsletters() {
        loading.style.display = 'block';
        grid.innerHTML = '';

        try {
            const items = await wp.apiFetch({ path: 'dingdong-lms/v1/newsletters' });
            loading.style.display = 'none';

            if (!items.length) {
                empty.classList.remove('dd-hidden');
                return;
            }
            empty.classList.add('dd-hidden');

            items.forEach(nl => {
                const card = document.createElement('div');
                card.className = 'dd-card';
                const catLabel = catLabels[nl.category] || nl.category;
                const date = new Date(nl.created).toLocaleDateString('ko-KR');
                card.innerHTML = `
                    <div class="dd-card-header">
                        <span class="dd-nl-emoji">${nl.emoji || '📰'}</span>
                        <span class="dd-nl-category">${catLabel}</span>
                    </div>
                    <h3 class="dd-card-title">${nl.title}</h3>
                    <p class="dd-card-desc">${nl.summary || ''}</p>
                    <div class="dd-card-meta">${date}</div>
                    <div class="dd-card-actions">
                        <button class="dd-btn dd-btn-sm dd-btn-toggle ${nl.public_active ? 'is-active' : ''}"
                                data-id="${nl.id}" title="${nl.public_active ? '공개 중' : '비공개'}">
                            ${nl.public_active ? '🔓 공개' : '🔒 비공개'}
                        </button>
                        ${nl.public_active && nl.public_url ? `<a href="${nl.public_url}" target="_blank" class="dd-btn dd-btn-sm">🔗 보기</a>` : ''}
                        <button class="dd-btn dd-btn-sm dd-btn-danger dd-btn-delete" data-id="${nl.id}">삭제</button>
                    </div>
                `;
                grid.appendChild(card);
            });

            grid.querySelectorAll('.dd-btn-toggle').forEach(btn => {
                btn.addEventListener('click', async () => {
                    try {
                        const res = await wp.apiFetch({
                            path: `dingdong-lms/v1/newsletters/${btn.dataset.id}/toggle-public`,
                            method: 'POST',
                        });
                        loadNewsletters();
                    } catch(e) {
                        showAlert('토글 실패: ' + e.message, 'error');
                    }
                });
            });

            grid.querySelectorAll('.dd-btn-delete').forEach(btn => {
                btn.addEventListener('click', async () => {
                    if (!confirm('이 뉴스레터를 삭제할까요?')) return;
                    try {
                        await wp.apiFetch({
                            path: `dingdong-lms/v1/newsletters/${btn.dataset.id}`,
                            method: 'DELETE',
                        });
                        showAlert('삭제되었습니다.', 'success');
                        loadNewsletters();
                    } catch(e) {
                        showAlert('삭제 실패: ' + e.message, 'error');
                    }
                });
            });
        } catch(e) {
            loading.style.display = 'none';
            showAlert('목록 로드 실패: ' + e.message, 'error');
        }
    }

    function showAlert(msg, type) {
        const container = document.getElementById('dd-alert-container');
        const el = document.createElement('div');
        el.className = 'dd-alert dd-alert-' + type;
        el.textContent = msg;
        container.appendChild(el);
        setTimeout(() => el.remove(), 4000);
    }

    loadNewsletters();
})();
</script>
