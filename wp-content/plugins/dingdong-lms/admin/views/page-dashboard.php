<?php
/**
 * Dingdong LMS Admin Dashboard View
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="dd-admin-wrap" id="dd-dashboard">
    <!-- Alert Container -->
    <div id="dd-alert-container"></div>

    <!-- Dashboard View -->
    <div id="dd-view-dashboard" data-view="dashboard">
        <div class="dd-admin-header">
            <h1><?php echo esc_html( 'Dingdong LMS — 강좌 관리' ); ?></h1>
            <div class="dd-header-actions">
                <button type="button" class="dd-btn dd-btn-primary" id="dd-btn-new-course">
                    + <?php echo esc_html( '새 강좌 만들기' ); ?>
                </button>
            </div>
        </div>

        <!-- Stats Bar -->
        <div class="dd-stats-bar">
            <div class="dd-stat-item">
                <span class="dd-stat-number" id="dd-stat-courses">0</span>
                <span class="dd-stat-label"><?php echo esc_html( '전체 강좌' ); ?></span>
            </div>
            <div class="dd-stat-item">
                <span class="dd-stat-number" id="dd-stat-lessons">0</span>
                <span class="dd-stat-label"><?php echo esc_html( '전체 강의' ); ?></span>
            </div>
            <div class="dd-stat-item">
                <span class="dd-stat-number" id="dd-stat-public">0</span>
                <span class="dd-stat-label"><?php echo esc_html( '활성 공개 링크' ); ?></span>
            </div>
        </div>

        <!-- Course Grid -->
        <div class="dd-card-grid" id="dd-course-grid">
            <!-- Courses populated by JS -->
        </div>

        <!-- Empty State -->
        <div class="dd-empty-state dd-hidden" id="dd-empty-state">
            <div class="dd-empty-icon">&#x1F4DA;</div>
            <div class="dd-empty-title"><?php echo esc_html( '아직 강좌가 없습니다' ); ?></div>
            <div class="dd-empty-desc">
                <?php echo esc_html( 'AI 생성 또는 직접 만들기로 시작하세요!' ); ?>
            </div>
        </div>

        <!-- Loading -->
        <div class="dd-loading" id="dd-dashboard-loading"></div>
    </div>

    <!-- Course Detail View -->
    <div id="dd-view-course-detail" data-view="course-detail" class="dd-hidden">
        <div class="dd-admin-header">
            <div>
                <button type="button" class="dd-back-btn" id="dd-btn-back-dashboard">
                    &larr; <?php echo esc_html( '강좌 목록으로' ); ?>
                </button>
                <span class="dd-course-title-wrap">
                    <h1 id="dd-detail-title"></h1>
                    <button type="button" class="dd-btn-icon dd-btn-edit-course-title" id="dd-btn-edit-course-title" title="강좌 제목 수정">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </button>
                </span>
            </div>
            <div class="dd-header-actions">
                <button type="button" class="dd-btn dd-btn-secondary dd-btn-sm" id="dd-btn-add-lesson">
                    + <?php echo esc_html( '강의 추가' ); ?>
                </button>
            </div>
        </div>

        <div class="dd-card dd-mb-2">
            <div class="dd-form-group">
                <label for="dd-detail-intro"><?php echo esc_html( '강좌 소개' ); ?>
                    <span class="dd-help-text"><?php echo esc_html( '강의 페이지에 표시됩니다. 줄 앞에 "-" 를 붙이면 글머리 기호로 보입니다.' ); ?></span>
                </label>
                <textarea id="dd-detail-intro" class="dd-textarea" rows="4"
                          placeholder="<?php echo esc_attr( '이 강좌에서 무엇을 배우는지 2~3줄로 적어주세요. 예) - 핵심 표현 익히기  - 실전 회화 연습' ); ?>"></textarea>
            </div>
            <div class="dd-form-row">
                <div class="dd-form-group">
                    <label for="dd-detail-genre"><?php echo esc_html( '장르' ); ?></label>
                    <select id="dd-detail-genre" class="dd-input">
                        <option value="">장르 선택...</option>
                        <option value="C-Pop">C-Pop</option>
                        <option value="발라드">발라드</option>
                        <option value="댄스/팝">댄스/팝</option>
                        <option value="R&B/소울">R&B/소울</option>
                        <option value="락">락</option>
                        <option value="힙합/랩">힙합/랩</option>
                        <option value="포크/어쿠스틱">포크/어쿠스틱</option>
                        <option value="OST/드라마">OST/드라마</option>
                        <option value="동요">동요</option>
                        <option value="__custom">직접 입력...</option>
                    </select>
                    <input type="text" id="dd-detail-genre-custom" class="dd-input dd-hidden" style="margin-top:0.4rem;" placeholder="<?php echo esc_attr( '장르 직접 입력' ); ?>">
                </div>
                <div class="dd-form-group">
                    <label for="dd-detail-level"><?php echo esc_html( '레벨' ); ?></label>
                    <select id="dd-detail-level" class="dd-input">
                        <option value=""><?php echo esc_html( '미설정' ); ?></option>
                        <option value="beginner"><?php echo esc_html( '입문' ); ?></option>
                        <option value="intermediate"><?php echo esc_html( '중급' ); ?></option>
                        <option value="advanced"><?php echo esc_html( '고급' ); ?></option>
                    </select>
                </div>
                <div class="dd-form-group">
                    <label for="dd-detail-artist"><?php echo esc_html( '가수 / 아티스트' ); ?></label>
                    <input type="text" id="dd-detail-artist" class="dd-input" placeholder="<?php echo esc_attr( '예: 毛不易' ); ?>">
                </div>
            </div>
            <div class="dd-flex dd-gap-1 dd-mt-1" style="align-items:center;">
                <button type="button" class="dd-btn dd-btn-primary dd-btn-sm" id="dd-btn-save-course-meta">
                    <?php echo esc_html( '강좌 정보 저장' ); ?>
                </button>
                <span class="dd-badge dd-badge-count" id="dd-detail-lesson-count"></span>
                <span class="dd-badge" id="dd-detail-status"></span>
            </div>
        </div>

        <!-- Lesson List -->
        <div class="dd-card">
            <div class="dd-card-header">
                <h2><?php echo esc_html( '강의 목록' ); ?></h2>
            </div>
            <ol class="dd-lesson-list" id="dd-lesson-list">
                <!-- Lessons populated by JS -->
            </ol>
            <div class="dd-empty-state dd-hidden" id="dd-lessons-empty">
                <div class="dd-empty-desc"><?php echo esc_html( '이 강좌에 아직 강의가 없습니다.' ); ?></div>
            </div>
            <div class="dd-loading dd-hidden" id="dd-lessons-loading"></div>
        </div>
    </div>

    <!-- New Course Modal -->
    <div class="dd-modal" id="dd-modal-new-course">
        <div class="dd-modal-overlay" data-close-modal="dd-modal-new-course"></div>
        <div class="dd-modal-content">
            <button type="button" class="dd-modal-close" data-close-modal="dd-modal-new-course">&times;</button>
            <h2><?php echo esc_html( '새 강좌 만들기' ); ?></h2>
            <form id="dd-form-new-course">
                <div class="dd-form-group">
                    <label for="dd-new-course-title"><?php echo esc_html( '강좌 제목' ); ?></label>
                    <input type="text" id="dd-new-course-title" class="dd-input"
                           placeholder="<?php echo esc_attr( '예: HSK 4급 필수 문법' ); ?>" required>
                </div>
                <div class="dd-form-group">
                    <label for="dd-new-course-desc"><?php echo esc_html( '강좌 설명' ); ?></label>
                    <textarea id="dd-new-course-desc" class="dd-textarea"
                              placeholder="<?php echo esc_attr( '강좌에 대한 간단한 설명을 입력하세요.' ); ?>"></textarea>
                </div>
                <div class="dd-modal-footer">
                    <button type="button" class="dd-btn dd-btn-secondary" data-close-modal="dd-modal-new-course">
                        <?php echo esc_html( '취소' ); ?>
                    </button>
                    <button type="submit" class="dd-btn dd-btn-primary">
                        <?php echo esc_html( '만들기' ); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Lesson Edit Modal -->
    <div class="dd-modal" id="dd-modal-edit-lesson">
        <div class="dd-modal-overlay" data-close-modal="dd-modal-edit-lesson"></div>
        <div class="dd-modal-content" style="max-width: 700px;">
            <button type="button" class="dd-modal-close" data-close-modal="dd-modal-edit-lesson">&times;</button>
            <h2 id="dd-edit-lesson-title"><?php echo esc_html( '강의 수정' ); ?></h2>
            <div class="dd-tabs">
                <button type="button" class="dd-tab-btn active" data-tab="content">
                    <?php echo esc_html( '콘텐츠' ); ?>
                </button>
                <button type="button" class="dd-tab-btn" data-tab="slides">
                    <?php echo esc_html( '슬라이드' ); ?>
                </button>
                <button type="button" class="dd-tab-btn" data-tab="video">
                    <?php echo esc_html( '영상' ); ?>
                </button>
                <button type="button" class="dd-tab-btn" data-tab="quiz">
                    <?php echo esc_html( '퀴즈' ); ?>
                </button>
            </div>

            <form id="dd-form-edit-lesson">
                <input type="hidden" id="dd-edit-lesson-id">

                <!-- Content Tab -->
                <div class="dd-tab-panel active" data-panel="content">
                    <div class="dd-form-group">
                        <label for="dd-lesson-content"><?php echo esc_html( '강의 내용' ); ?></label>
                        <textarea id="dd-lesson-content" class="dd-textarea" rows="10"
                                  placeholder="<?php echo esc_attr( '강의 콘텐츠를 입력하세요...' ); ?>"></textarea>
                    </div>
                </div>

                <!-- Slides Tab -->
                <div class="dd-tab-panel" data-panel="slides">
                    <div class="dd-form-group">
                        <label for="dd-lesson-slides"><?php echo esc_html( '슬라이드 JSON' ); ?></label>
                        <textarea id="dd-lesson-slides" class="dd-textarea" rows="10"
                                  placeholder="<?php echo esc_attr( '슬라이드 데이터를 JSON 형식으로 입력하세요...' ); ?>"></textarea>
                        <span class="dd-help-text"><?php echo esc_html( 'JSON 배열 형식으로 슬라이드 데이터를 입력합니다.' ); ?></span>
                    </div>
                </div>

                <!-- Video Tab -->
                <div class="dd-tab-panel" data-panel="video">
                    <div class="dd-form-group">
                        <label for="dd-lesson-video-url"><?php echo esc_html( '영상 URL' ); ?></label>
                        <input type="url" id="dd-lesson-video-url" class="dd-input"
                               placeholder="<?php echo esc_attr( 'https://...' ); ?>">
                    </div>
                    <div class="dd-form-group">
                        <label for="dd-lesson-video-thumb"><?php echo esc_html( '썸네일 URL' ); ?></label>
                        <input type="url" id="dd-lesson-video-thumb" class="dd-input"
                               placeholder="<?php echo esc_attr( 'https://...' ); ?>">
                    </div>
                    <div class="dd-form-row">
                        <div class="dd-form-group">
                            <label for="dd-lesson-year"><?php echo esc_html( '연도' ); ?>
                                <span class="dd-help-text"><?php echo esc_html( '중국어 노래 학습 필터에 사용' ); ?></span>
                            </label>
                            <input type="text" id="dd-lesson-year" class="dd-input"
                                   placeholder="<?php echo esc_attr( '예: 2024' ); ?>" maxlength="4" inputmode="numeric">
                        </div>
                        <div class="dd-form-group">
                            <label for="dd-lesson-artist"><?php echo esc_html( '가수 / 아티스트' ); ?></label>
                            <input type="text" id="dd-lesson-artist" class="dd-input"
                                   placeholder="<?php echo esc_attr( '예: 毛不易' ); ?>">
                        </div>
                    </div>
                </div>

                <!-- Quiz Tab -->
                <div class="dd-tab-panel" data-panel="quiz">
                    <div class="dd-form-group">
                        <label><?php echo esc_html( '퀴즈 문항' ); ?></label>
                        <div id="dd-quiz-builder">
                            <!-- Quiz items populated by JS -->
                        </div>
                        <button type="button" class="dd-btn dd-btn-secondary dd-btn-sm dd-mt-1" id="dd-btn-add-quiz-item">
                            + <?php echo esc_html( '문항 추가' ); ?>
                        </button>
                    </div>
                </div>

                <div class="dd-modal-footer">
                    <button type="button" class="dd-btn dd-btn-secondary" data-close-modal="dd-modal-edit-lesson">
                        <?php echo esc_html( '취소' ); ?>
                    </button>
                    <button type="submit" class="dd-btn dd-btn-primary">
                        <?php echo esc_html( '저장' ); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="dd-modal" id="dd-modal-confirm">
        <div class="dd-modal-overlay" data-close-modal="dd-modal-confirm"></div>
        <div class="dd-modal-content" style="max-width: 400px;">
            <button type="button" class="dd-modal-close" data-close-modal="dd-modal-confirm">&times;</button>
            <h2 id="dd-confirm-title"><?php echo esc_html( '삭제 확인' ); ?></h2>
            <p id="dd-confirm-message"><?php echo esc_html( '정말 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.' ); ?></p>
            <div class="dd-modal-footer">
                <button type="button" class="dd-btn dd-btn-secondary" data-close-modal="dd-modal-confirm">
                    <?php echo esc_html( '취소' ); ?>
                </button>
                <button type="button" class="dd-btn dd-btn-danger" id="dd-btn-confirm-action">
                    <?php echo esc_html( '삭제' ); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Public Link / QR Modal -->
    <div class="dd-modal" id="dd-modal-qr">
        <div class="dd-modal-overlay" data-close-modal="dd-modal-qr"></div>
        <div class="dd-modal-content" style="max-width: 400px;">
            <button type="button" class="dd-modal-close" data-close-modal="dd-modal-qr">&times;</button>
            <h2><?php echo esc_html( '공개 링크' ); ?></h2>
            <div class="dd-qr-container">
                <img id="dd-qr-image" src="" alt="QR Code">
                <div class="dd-qr-url" id="dd-qr-url"></div>
                <button type="button" class="dd-btn dd-btn-secondary dd-btn-sm dd-mt-2" id="dd-btn-copy-link">
                    <?php echo esc_html( '링크 복사' ); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Image Regeneration Modal -->
    <div class="dd-modal" id="dd-modal-images">
        <div class="dd-modal-overlay" data-close-modal="dd-modal-images"></div>
        <div class="dd-modal-content" style="max-width: 720px;">
            <button type="button" class="dd-modal-close" data-close-modal="dd-modal-images">&times;</button>
            <h2 id="dd-images-title"><?php echo esc_html( '이미지 관리' ); ?></h2>
            <p class="dd-help-text"><?php echo esc_html( '재생성할 이미지를 선택하세요. 개별 클릭으로 해당 이미지만 재생성됩니다.' ); ?></p>
            <input type="hidden" id="dd-images-lesson-id">
            <div id="dd-images-grid" class="dd-images-grid">
                <!-- Populated by JS -->
            </div>
            <div class="dd-modal-footer">
                <button type="button" class="dd-btn dd-btn-secondary" data-close-modal="dd-modal-images">
                    <?php echo esc_html( '닫기' ); ?>
                </button>
            </div>
        </div>
    </div>
</div>
