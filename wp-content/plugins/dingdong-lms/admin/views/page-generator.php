<?php
/**
 * Dingdong LMS AI Generator Page View
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="dd-admin-wrap" id="dd-generator">
    <!-- Alert Container -->
    <div id="dd-generator-alert-container"></div>

    <div class="dd-admin-header">
        <h1><?php echo esc_html( 'Dingdong LMS — AI 강좌 생성' ); ?></h1>
    </div>

    <!-- API Key Warning -->
    <div class="dd-alert dd-alert-warning dd-hidden" id="dd-generator-api-warning">
        <?php echo esc_html( 'Gemini API 키가 설정되지 않았습니다. 설정 페이지에서 API 키를 먼저 등록하세요.' ); ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=dd-lms-settings' ) ); ?>" class="dd-btn dd-btn-secondary dd-btn-sm" style="margin-left: auto;">
            <?php echo esc_html( '설정으로 이동' ); ?>
        </a>
    </div>

    <!-- Generator Form Card -->
    <div class="dd-card" id="dd-generator-form-card">
        <div class="dd-card-header">
            <h2><?php echo esc_html( '새 강좌 자동 생성' ); ?></h2>
        </div>
        <div class="dd-card-body">
            <p class="dd-mb-2">
                <?php echo esc_html( '학습 주제를 입력하면 AI가 강좌 구조와 강의 콘텐츠를 자동으로 생성합니다.' ); ?>
            </p>

            <form id="dd-form-generate">
                <div class="dd-form-group">
                    <label for="dd-generate-topic"><?php echo esc_html( '학습 주제' ); ?></label>
                    <input type="text" id="dd-generate-topic" class="dd-input"
                           placeholder="<?php echo esc_attr( '예: 중국 음식 문화, HSK 3급 문법 정리, 중국 영화 속 표현' ); ?>"
                           required>
                </div>

                <div class="dd-form-group">
                    <label for="dd-generate-weeks"><?php echo esc_html( '강좌 주차 수' ); ?></label>
                    <select id="dd-generate-weeks" class="dd-input">
                        <option value="1">1주차 (강의 1개)</option>
                        <option value="2">2주차 (강의 2개)</option>
                        <option value="3">3주차 (강의 3개)</option>
                        <option value="4" selected>4주차 (강의 4개)</option>
                        <option value="5">5주차 (강의 5개)</option>
                        <option value="6">6주차 (강의 6개)</option>
                        <option value="8">8주차 (강의 8개)</option>
                        <option value="10">10주차 (강의 10개)</option>
                        <option value="12">12주차 (강의 12개)</option>
                    </select>
                    <span class="dd-help-text">
                        <?php echo esc_html( '선택한 주차 수만큼 세부 강의가 생성됩니다.' ); ?>
                    </span>
                </div>

                <div class="dd-form-group">
                    <label for="dd-generate-level"><?php echo esc_html( '난이도' ); ?></label>
                    <select id="dd-generate-level" class="dd-input">
                        <option value="beginner" selected><?php echo esc_html( '입문 (중국어 + 병음 + 한국어)' ); ?></option>
                        <option value="intermediate"><?php echo esc_html( '중급 (중국어 + 한국어, 병음 없음)' ); ?></option>
                        <option value="advanced"><?php echo esc_html( '고급 (중국어 + 한국어, 병음 없음)' ); ?></option>
                    </select>
                    <span class="dd-help-text">
                        <?php echo esc_html( '중급/고급은 실전 대화에서 병음 없이 중국어-한국어만 표시됩니다.' ); ?>
                    </span>
                </div>

                <!-- 이미지 생성 옵션 — API 요금의 대부분이 이미지에서 발생하므로 직접 고른다 -->
                <div class="dd-form-group">
                    <label><?php echo esc_html( '이미지 생성' ); ?></label>
                    <div class="dd-asset-options" id="dd-asset-options">
                        <label class="dd-check">
                            <input type="checkbox" data-phase="key_expr_image" data-cost="1" checked>
                            <span><?php echo esc_html( '핵심표현 배너' ); ?> <em>1장</em></span>
                        </label>
                        <label class="dd-check">
                            <input type="checkbox" data-phase="dialogue_image" data-cost="1" checked>
                            <span><?php echo esc_html( '실전대화 삽화' ); ?> <em>1장</em></span>
                        </label>
                        <label class="dd-check">
                            <input type="checkbox" data-phase="comic_images" data-cost="4" checked>
                            <span><?php echo esc_html( '학습만화' ); ?> <em>4장</em></span>
                        </label>
                        <label class="dd-check">
                            <input type="checkbox" data-phase="storybook_images" data-cost="6">
                            <span><?php echo esc_html( '스토리북 삽화' ); ?> <em>6장</em></span>
                        </label>
                        <label class="dd-check">
                            <input type="checkbox" data-phase="youtube" data-cost="0" checked>
                            <span><?php echo esc_html( '관련 영상 검색' ); ?> <em>무료</em></span>
                        </label>
                    </div>
                    <span class="dd-help-text">
                        <?php echo esc_html( '체크 해제한 항목은 생성하지 않습니다. 강의 텍스트·슬라이드·퀴즈·만화 대사는 옵션과 무관하게 항상 생성됩니다.' ); ?>
                        <br>
                        <?php echo esc_html( '스토리북 삽화는 한 강의에서 가장 많은 6장을 쓰므로 기본 해제 상태입니다. 나중에 강의별 [이미지] 버튼으로 하나씩 추가할 수 있습니다.' ); ?>
                    </span>
                </div>

                <div class="dd-cost-estimate" id="dd-cost-estimate">
                    <?php echo esc_html( '예상 이미지' ); ?>
                    <strong id="dd-cost-total">—</strong>
                    <span class="dd-cost-note"><?php echo esc_html( '텍스트 생성 비용은 이미지에 비해 미미합니다.' ); ?></span>
                </div>

                <button type="submit" class="dd-btn dd-btn-primary" id="dd-btn-generate">
                    <?php echo esc_html( '강좌 생성하기' ); ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Progress Section -->
    <div class="dd-card dd-hidden dd-mt-2" id="dd-generator-progress-card">
        <div class="dd-card-header">
            <h2><?php echo esc_html( '생성 진행 상황' ); ?></h2>
        </div>
        <div class="dd-card-body">
            <div class="dd-progress">
                <div class="dd-progress-bar animated" id="dd-gen-progress-bar" style="--dd-progress: 0%;"></div>
                <div class="dd-progress-text" id="dd-gen-progress-text">
                    <?php echo esc_html( '강좌 개요 생성 중...' ); ?>
                </div>
            </div>

            <div class="dd-mt-2">
                <h3 style="font-size: 0.9rem; font-weight: 600; margin-bottom: 0.5rem;">
                    <?php echo esc_html( '생성된 강의' ); ?>
                </h3>
                <ul class="dd-generated-lessons" id="dd-generated-lessons-list">
                    <!-- Generated lessons appear here -->
                </ul>
            </div>
        </div>
    </div>

    <!-- Result Section -->
    <div class="dd-card dd-hidden dd-mt-2" id="dd-generator-result-card">
        <div class="dd-card-header">
            <h2><?php echo esc_html( '생성 완료!' ); ?></h2>
        </div>
        <div class="dd-card-body">
            <div class="dd-alert dd-alert-success">
                <?php echo esc_html( '강좌가 성공적으로 생성되었습니다.' ); ?>
            </div>
            <p id="dd-gen-result-summary"></p>
            <div class="dd-mt-2">
                <a href="#" class="dd-btn dd-btn-primary" id="dd-btn-view-generated-course">
                    <?php echo esc_html( '강좌 보기' ); ?>
                </a>
                <button type="button" class="dd-btn dd-btn-secondary" id="dd-btn-generate-another">
                    <?php echo esc_html( '다른 강좌 생성' ); ?>
                </button>
            </div>
        </div>
    </div>
</div>
