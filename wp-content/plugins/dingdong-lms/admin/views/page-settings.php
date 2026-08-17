<?php
/**
 * Dingdong LMS Settings Page View
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="dd-admin-wrap" id="dd-settings">
    <!-- Alert Container -->
    <div id="dd-settings-alert-container"></div>

    <div class="dd-admin-header">
        <h1><?php echo esc_html( 'Dingdong LMS — 설정' ); ?></h1>
    </div>

    <!-- Gemini API Key Card -->
    <div class="dd-card">
        <div class="dd-card-header">
            <h2><?php echo esc_html( 'Gemini API 키 설정' ); ?></h2>
            <div class="dd-status-indicator" id="dd-api-key-status">
                <span class="dd-status-dot"></span>
                <span class="dd-status-text"><?php echo esc_html( '확인 중...' ); ?></span>
            </div>
        </div>
        <div class="dd-card-body">
            <p class="dd-mb-2">
                <?php echo esc_html( '강좌 및 콘텐츠 자동 생성을 위한 Gemini API 키를 입력하세요.' ); ?>
            </p>

            <form id="dd-form-api-key">
                <div class="dd-form-group">
                    <label for="dd-api-key-input"><?php echo esc_html( 'API 키' ); ?></label>
                    <input type="password" id="dd-api-key-input" class="dd-input"
                           placeholder="<?php echo esc_attr( 'API 키를 입력하세요...' ); ?>"
                           autocomplete="off">
                    <span class="dd-help-text">
                        <?php echo esc_html( 'Google AI Studio에서 API 키를 발급받을 수 있습니다.' ); ?>
                        <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener noreferrer">
                            <?php echo esc_html( '키 발급받기 →' ); ?>
                        </a>
                    </span>
                </div>

                <div class="dd-flex dd-gap-1">
                    <button type="submit" class="dd-btn dd-btn-primary" id="dd-btn-save-key">
                        <?php echo esc_html( '저장' ); ?>
                    </button>
                    <button type="button" class="dd-btn dd-btn-danger" id="dd-btn-delete-key">
                        <?php echo esc_html( '삭제' ); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Model Selection Card -->
    <div class="dd-card dd-mt-2">
        <div class="dd-card-header">
            <h2><?php echo esc_html( 'Gemini 모델 선택' ); ?></h2>
        </div>
        <div class="dd-card-body">
            <p class="dd-mb-2">
                <?php echo esc_html( 'AI 강좌 생성에 사용할 Gemini 모델을 선택하세요.' ); ?>
            </p>

            <div class="dd-form-group">
                <label for="dd-model-select"><?php echo esc_html( '모델' ); ?></label>
                <select id="dd-model-select" class="dd-input">
                    <option value="">자동 (권장 — 안정 모델부터 순서대로 시도)</option>
                    <option value="gemini-3.1-flash-lite">Gemini 3.1 Flash Lite</option>
                    <option value="gemini-3.5-flash">Gemini 3.5 Flash</option>
                    <option value="gemini-3-flash-preview">Gemini 3 Flash Preview</option>
                    <option value="gemini-3.1-flash-lite-preview">Gemini 3.1 Flash Lite Preview</option>
                </select>
                <span class="dd-help-text">
                    <?php echo esc_html( 'Flash 모델은 빠른 응답에, Lite 모델은 비용 절감에 적합합니다.' ); ?>
                </span>
            </div>

            <button type="button" class="dd-btn dd-btn-primary" id="dd-btn-save-model">
                <?php echo esc_html( '모델 저장' ); ?>
            </button>
        </div>
    </div>

    <!-- YouTube API Key Card -->
    <div class="dd-card dd-mt-2">
        <div class="dd-card-header">
            <h2><?php echo esc_html( 'YouTube 영상 자동 임베드' ); ?></h2>
            <div class="dd-status-indicator" id="dd-yt-api-status">
                <span class="dd-status-dot"></span>
                <span class="dd-status-text"><?php echo esc_html( '확인 중...' ); ?></span>
            </div>
        </div>
        <div class="dd-card-body">
            <p class="dd-mb-2">
                <?php echo esc_html( '강의 생성 시 관련 YouTube 영상을 자동으로 검색하여 임베드합니다.' ); ?>
            </p>

            <form id="dd-form-youtube-key">
                <div class="dd-form-group">
                    <label for="dd-youtube-key-input"><?php echo esc_html( 'YouTube Data API v3 키' ); ?></label>
                    <input type="password" id="dd-youtube-key-input" class="dd-input"
                           placeholder="<?php echo esc_attr( 'YouTube API 키를 입력하세요...' ); ?>"
                           autocomplete="off">
                    <span class="dd-help-text">
                        <?php echo esc_html( 'Google Cloud Console에서 YouTube Data API v3 키를 발급받으세요.' ); ?>
                        <a href="https://console.cloud.google.com/apis/library/youtube.googleapis.com" target="_blank" rel="noopener noreferrer">
                            <?php echo esc_html( 'API 활성화 & 키 발급 →' ); ?>
                        </a>
                    </span>
                </div>

                <div class="dd-flex dd-gap-1">
                    <button type="submit" class="dd-btn dd-btn-primary" id="dd-btn-save-youtube">
                        <?php echo esc_html( '저장' ); ?>
                    </button>
                    <button type="button" class="dd-btn dd-btn-danger" id="dd-btn-delete-youtube">
                        <?php echo esc_html( '삭제' ); ?>
                    </button>
                    <button type="button" class="dd-btn dd-btn-secondary" id="dd-btn-check-yt">
                        <?php echo esc_html( '연결 확인' ); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Pixabay API Key Card (썸네일) -->
    <div class="dd-card dd-mt-2">
        <div class="dd-card-header">
            <h2><?php echo esc_html( '강좌 썸네일 설정 (Pixabay)' ); ?></h2>
            <div class="dd-status-indicator" id="dd-pixabay-status">
                <span class="dd-status-dot"></span>
                <span class="dd-status-text"><?php echo esc_html( '확인 중...' ); ?></span>
            </div>
        </div>
        <div class="dd-card-body">
            <p class="dd-mb-2">
                <?php echo esc_html( '강좌 생성 시 주제에 맞는 고화질 썸네일을 Pixabay에서 자동으로 가져옵니다.' ); ?>
            </p>

            <form id="dd-form-pixabay-key">
                <div class="dd-form-group">
                    <label for="dd-pixabay-key-input"><?php echo esc_html( 'Pixabay API 키' ); ?></label>
                    <input type="password" id="dd-pixabay-key-input" class="dd-input"
                           placeholder="<?php echo esc_attr( 'Pixabay API 키를 입력하세요...' ); ?>"
                           autocomplete="off">
                    <span class="dd-help-text">
                        <?php echo esc_html( 'Pixabay에서 무료로 API 키를 발급받을 수 있습니다.' ); ?>
                        <a href="https://pixabay.com/api/docs/" target="_blank" rel="noopener noreferrer">
                            <?php echo esc_html( '키 발급받기 →' ); ?>
                        </a>
                    </span>
                </div>

                <div class="dd-flex dd-gap-1">
                    <button type="submit" class="dd-btn dd-btn-primary" id="dd-btn-save-pixabay">
                        <?php echo esc_html( '저장' ); ?>
                    </button>
                    <button type="button" class="dd-btn dd-btn-danger" id="dd-btn-delete-pixabay">
                        <?php echo esc_html( '삭제' ); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 플러그인 삭제 시 데이터 처리 -->
    <div class="dd-card dd-mt-2">
        <div class="dd-card-header">
            <h2><?php echo esc_html( '플러그인 삭제 시 데이터' ); ?></h2>
        </div>
        <div class="dd-card-body">
            <p class="dd-mb-2">
                <?php echo esc_html( '기본값은 콘텐츠 보존입니다. 플러그인을 삭제하거나 재설치해도 강좌·강의·스토리·뉴스레터는 그대로 남습니다.' ); ?>
            </p>

            <label class="dd-check">
                <input type="checkbox" id="dd-purge-content">
                <span><?php echo esc_html( '삭제 시 학습 콘텐츠도 완전히 제거' ); ?></span>
            </label>

            <p class="dd-help-text dd-mt-2">
                <?php echo esc_html( '⚠️ 이 옵션을 켜고 플러그인을 삭제하면 강좌·강의·스토리·뉴스레터가 휴지통을 거치지 않고 영구 삭제됩니다. AI 생성에 지불한 API 요금은 되돌릴 수 없습니다. 사이트를 완전히 정리할 때만 사용하세요.' ); ?>
            </p>

            <div class="dd-mt-2">
                <button type="button" class="dd-btn dd-btn-primary" id="dd-btn-save-purge">
                    <?php echo esc_html( '저장' ); ?>
                </button>
            </div>
        </div>
    </div>

</div>
