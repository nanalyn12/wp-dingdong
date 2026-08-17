<?php
/**
 * Dingdong LMS Admin — Interactive Stories Page
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="dd-admin-wrap" id="dd-stories">
    <!-- Alert Container -->
    <div id="dd-stories-alert-container"></div>

    <!-- Story List View -->
    <div id="dd-view-story-list" data-view="story-list">
        <div class="dd-admin-header">
            <h1><?php echo esc_html( 'Dingdong LMS — 인터랙티브 스토리' ); ?></h1>
            <div class="dd-header-actions">
                <button type="button" class="dd-btn dd-btn-primary" id="dd-btn-new-story">
                    + <?php echo esc_html( '새 스토리 만들기' ); ?>
                </button>
            </div>
        </div>

        <!-- Stats Bar -->
        <div class="dd-stats-bar">
            <div class="dd-stat-item">
                <span class="dd-stat-number" id="dd-stat-stories">0</span>
                <span class="dd-stat-label"><?php echo esc_html( '전체 스토리' ); ?></span>
            </div>
            <div class="dd-stat-item">
                <span class="dd-stat-number" id="dd-stat-story-public">0</span>
                <span class="dd-stat-label"><?php echo esc_html( '활성 공개 링크' ); ?></span>
            </div>
        </div>

        <!-- Story Grid -->
        <div class="dd-card-grid" id="dd-story-grid">
            <!-- Stories populated by JS -->
        </div>

        <!-- Empty State -->
        <div class="dd-empty-state dd-hidden" id="dd-story-empty">
            <div class="dd-empty-icon">&#x1F4D6;</div>
            <div class="dd-empty-title"><?php echo esc_html( '아직 인터랙티브 스토리가 없습니다' ); ?></div>
            <div class="dd-empty-desc">
                <?php echo esc_html( 'AI 생성으로 분기형 스토리를 만들어 보세요!' ); ?>
            </div>
        </div>

        <!-- Loading -->
        <div class="dd-loading" id="dd-story-loading"></div>
    </div>

    <!-- Story Detail View -->
    <div id="dd-view-story-detail" data-view="story-detail" class="dd-hidden">
        <div class="dd-admin-header">
            <div>
                <button type="button" class="dd-back-btn" id="dd-btn-back-stories">
                    &larr; <?php echo esc_html( '스토리 목록으로' ); ?>
                </button>
                <h1 id="dd-story-detail-title"></h1>
            </div>
            <div class="dd-header-actions">
                <button type="button" class="dd-btn dd-btn-secondary dd-btn-sm" id="dd-btn-edit-story-nodes">
                    <?php echo esc_html( '노드 편집' ); ?>
                </button>
            </div>
        </div>

        <div class="dd-card dd-mb-2">
            <div class="dd-card-body">
                <p id="dd-story-detail-desc"></p>
                <div class="dd-flex dd-gap-1 dd-mt-1">
                    <span class="dd-badge" id="dd-story-detail-level"></span>
                    <span class="dd-badge dd-badge-count" id="dd-story-detail-node-count"></span>
                </div>
            </div>
        </div>

        <div class="dd-card dd-mb-2">
            <div class="dd-card-header">
                <h2><?php echo esc_html( '공개 링크' ); ?></h2>
            </div>
            <div class="dd-card-body">
                <div class="dd-flex dd-gap-1" style="align-items: center;">
                    <label class="dd-toggle" title="공개 링크">
                        <input type="checkbox" id="dd-story-toggle-public">
                        <span class="dd-toggle-slider"></span>
                    </label>
                    <span id="dd-story-public-url" style="font-size: 0.85rem; color: var(--dd-text-light); word-break: break-all;"></span>
                    <button type="button" class="dd-btn dd-btn-secondary dd-btn-sm dd-hidden" id="dd-btn-copy-story-link">
                        <?php echo esc_html( '복사' ); ?>
                    </button>
                </div>
            </div>
        </div>

        <div class="dd-card">
            <div class="dd-card-header">
                <h2><?php echo esc_html( '커버 이미지' ); ?></h2>
            </div>
            <div class="dd-card-body" style="text-align: center;">
                <img id="dd-story-cover-preview" src="" alt="" style="max-width: 300px; border-radius: 8px; display: none;">
                <p id="dd-story-cover-none" class="dd-text-muted"><?php echo esc_html( '커버 이미지 없음' ); ?></p>
            </div>
        </div>
    </div>

    <!-- New Story Modal (AI Generate) -->
    <div class="dd-modal" id="dd-modal-new-story">
        <div class="dd-modal-overlay" data-close-modal="dd-modal-new-story"></div>
        <div class="dd-modal-content">
            <button type="button" class="dd-modal-close" data-close-modal="dd-modal-new-story">&times;</button>
            <h2><?php echo esc_html( '새 인터랙티브 스토리 만들기' ); ?></h2>
            <form id="dd-form-new-story">
                <div class="dd-form-group">
                    <label for="dd-story-topic"><?php echo esc_html( '스토리 주제' ); ?></label>
                    <input type="text" id="dd-story-topic" class="dd-input"
                           placeholder="<?php echo esc_attr( '예: 중국 식당에서 음식 주문하기' ); ?>" required>
                </div>
                <div class="dd-form-group">
                    <label for="dd-story-level"><?php echo esc_html( '난이도' ); ?></label>
                    <select id="dd-story-level" class="dd-input">
                        <option value="beginner"><?php echo esc_html( '입문 (HSK 1-2)' ); ?></option>
                        <option value="intermediate"><?php echo esc_html( '중급 (HSK 3-4)' ); ?></option>
                        <option value="advanced"><?php echo esc_html( '고급 (HSK 5+)' ); ?></option>
                    </select>
                </div>
                <div class="dd-form-group">
                    <label for="dd-story-scene-images"><?php echo esc_html( '장면 삽화' ); ?></label>
                    <select id="dd-story-scene-images" class="dd-input">
                        <option value="0"><?php echo esc_html( '없음 — 이미지 요금 0원' ); ?></option>
                        <option value="2"><?php echo esc_html( '2장' ); ?></option>
                        <option value="4" selected><?php echo esc_html( '4장 (기본)' ); ?></option>
                        <option value="7"><?php echo esc_html( '7장 (최대)' ); ?></option>
                    </select>
                    <span class="dd-help-text">
                        <?php echo esc_html( '스토리 분기·대사·어휘는 삽화 설정과 무관하게 항상 생성됩니다. 삽화만 요금이 발생합니다.' ); ?>
                    </span>
                </div>
                <div class="dd-form-group">
                    <label class="dd-check">
                        <input type="checkbox" id="dd-story-cover-image" checked>
                        <span><?php echo esc_html( '커버 이미지 생성' ); ?> <em>1장</em></span>
                    </label>
                </div>
                <div class="dd-modal-footer">
                    <button type="button" class="dd-btn dd-btn-secondary" data-close-modal="dd-modal-new-story">
                        <?php echo esc_html( '취소' ); ?>
                    </button>
                    <button type="submit" class="dd-btn dd-btn-primary" id="dd-btn-generate-story">
                        <?php echo esc_html( 'AI로 생성하기' ); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Story Node Editor Modal -->
    <div class="dd-modal" id="dd-modal-edit-nodes">
        <div class="dd-modal-overlay" data-close-modal="dd-modal-edit-nodes"></div>
        <div class="dd-modal-content" style="max-width: 700px;">
            <button type="button" class="dd-modal-close" data-close-modal="dd-modal-edit-nodes">&times;</button>
            <h2><?php echo esc_html( '스토리 노드 편집' ); ?></h2>
            <form id="dd-form-edit-nodes">
                <input type="hidden" id="dd-edit-story-id">
                <div class="dd-form-group">
                    <label for="dd-story-nodes-json"><?php echo esc_html( '노드 데이터 (JSON)' ); ?></label>
                    <textarea id="dd-story-nodes-json" class="dd-textarea" rows="16"
                              placeholder="<?php echo esc_attr( '{"start": "node_1", "nodes": {...}}' ); ?>" style="font-family: monospace; font-size: 0.8rem;"></textarea>
                    <span class="dd-help-text"><?php echo esc_html( 'start, nodes 필드를 포함한 JSON 객체입니다. 직접 수정 시 주의하세요.' ); ?></span>
                </div>
                <div class="dd-modal-footer">
                    <button type="button" class="dd-btn dd-btn-secondary" data-close-modal="dd-modal-edit-nodes">
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
    <div class="dd-modal" id="dd-modal-story-confirm">
        <div class="dd-modal-overlay" data-close-modal="dd-modal-story-confirm"></div>
        <div class="dd-modal-content" style="max-width: 400px;">
            <button type="button" class="dd-modal-close" data-close-modal="dd-modal-story-confirm">&times;</button>
            <h2 id="dd-story-confirm-title"><?php echo esc_html( '삭제 확인' ); ?></h2>
            <p id="dd-story-confirm-message"><?php echo esc_html( '정말 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.' ); ?></p>
            <div class="dd-modal-footer">
                <button type="button" class="dd-btn dd-btn-secondary" data-close-modal="dd-modal-story-confirm">
                    <?php echo esc_html( '취소' ); ?>
                </button>
                <button type="button" class="dd-btn dd-btn-danger" id="dd-btn-story-confirm-action">
                    <?php echo esc_html( '삭제' ); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Story Generation Progress Modal -->
    <div class="dd-modal" id="dd-modal-story-progress">
        <div class="dd-modal-overlay"></div>
        <div class="dd-modal-content" style="max-width: 450px;">
            <h2><?php echo esc_html( '스토리 생성 중...' ); ?></h2>
            <div class="dd-progress" id="dd-story-gen-progress-bar" style="margin: 1.5rem 0;">
                <div class="dd-progress-fill"></div>
            </div>
            <p id="dd-story-gen-progress-text" style="text-align: center; font-size: 0.9rem; color: var(--dd-text-light);">
                <?php echo esc_html( 'AI가 분기형 스토리를 구성하고 있습니다...' ); ?>
            </p>
        </div>
    </div>

    <!-- Public Link / QR Modal -->
    <div class="dd-modal" id="dd-modal-story-qr">
        <div class="dd-modal-overlay" data-close-modal="dd-modal-story-qr"></div>
        <div class="dd-modal-content" style="max-width: 400px;">
            <button type="button" class="dd-modal-close" data-close-modal="dd-modal-story-qr">&times;</button>
            <h2><?php echo esc_html( '공개 링크' ); ?></h2>
            <div class="dd-qr-container">
                <img id="dd-story-qr-image" src="" alt="QR Code">
                <div class="dd-qr-url" id="dd-story-qr-url"></div>
                <button type="button" class="dd-btn dd-btn-secondary dd-btn-sm dd-mt-2" id="dd-btn-copy-story-qr">
                    <?php echo esc_html( '링크 복사' ); ?>
                </button>
            </div>
        </div>
    </div>
</div>
