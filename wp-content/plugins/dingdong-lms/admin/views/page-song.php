<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="dd-admin-wrap" id="dd-song">
    <div id="dd-song-alert-container"></div>

    <div class="dd-admin-header">
        <h1><?php echo esc_html( 'Dingdong LMS — 중국어 노래 학습' ); ?></h1>
    </div>

    <div class="dd-alert dd-alert-warning dd-hidden" id="dd-song-api-warning">
        <?php echo esc_html( 'Gemini API 키 또는 YouTube API 키가 설정되지 않았습니다.' ); ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=dd-lms-settings' ) ); ?>" class="dd-btn dd-btn-secondary dd-btn-sm" style="margin-left: auto;">
            <?php echo esc_html( '설정으로 이동' ); ?>
        </a>
    </div>

    <!-- Step 1: URL Input -->
    <div class="dd-card" id="dd-song-url-card">
        <div class="dd-card-header">
            <h2><?php echo esc_html( 'Step 1 — YouTube URL 입력' ); ?></h2>
        </div>
        <div class="dd-card-body">
            <p class="dd-mb-2">
                <?php echo esc_html( '정식 발매된 중국어 노래의 뮤직비디오/가사영상 URL 또는 앨범 재생목록 URL을 입력하세요.' ); ?>
            </p>
            <div class="dd-sub-upload-tip" style="background:#fef3f7;border:1px solid #f8c4d4;border-radius:8px;padding:0.75rem 1rem;margin-bottom:1rem;font-size:0.85rem;color:#9d2b56;">
                <strong>💡 가장 잘 동작하는 영상:</strong>
                공식 아티스트 채널 MV · 가사영상(歌词版/Lyric Video) · 자막 트랙이 등록된 라이브 영상.
                자막이 영상 위에 박힌(burn-in) 가사 영상은 자막 추출이 어렵습니다.
            </div>
            <form id="dd-form-song-url">
                <div class="dd-form-group">
                    <label for="dd-song-url"><?php echo esc_html( 'YouTube URL' ); ?></label>
                    <input type="url" id="dd-song-url" class="dd-input"
                           placeholder="https://www.youtube.com/watch?v=... 또는 앨범 재생목록 URL"
                           required>
                    <span class="dd-help-text">
                        <?php echo esc_html( '단일 곡 URL = 1강의, 앨범/플레이리스트 = 곡당 1강의로 묶음 강좌 생성.' ); ?>
                    </span>
                </div>
                <button type="submit" class="dd-btn dd-btn-primary" id="dd-btn-song-fetch-info">
                    <?php echo esc_html( '영상 정보 가져오기' ); ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Step 2: Song Selection -->
    <div class="dd-card dd-hidden dd-mt-2" id="dd-song-list-card">
        <div class="dd-card-header">
            <h2><?php echo esc_html( 'Step 2 — 곡 선택' ); ?></h2>
            <span class="dd-badge" id="dd-song-channel"></span>
        </div>
        <div class="dd-card-body">
            <!-- 생성 방식: 새 장르 강좌 / 기존 강좌에 추가 -->
            <div class="dd-form-group dd-mb-2">
                <label><?php echo esc_html( '생성 방식' ); ?></label>
                <div class="dd-flex dd-gap-1" style="flex-wrap:wrap;">
                    <label class="dd-flex" style="gap:0.35rem;align-items:center;cursor:pointer;">
                        <input type="radio" name="dd-song-mode" value="new" checked> <?php echo esc_html( '새 장르 강좌 만들기' ); ?>
                    </label>
                    <label class="dd-flex" style="gap:0.35rem;align-items:center;cursor:pointer;">
                        <input type="radio" name="dd-song-mode" value="existing"> <?php echo esc_html( '기존 장르 강좌에 곡 추가' ); ?>
                    </label>
                </div>
            </div>

            <!-- 새 강좌: 장르 + 제목 -->
            <div id="dd-song-new-fields">
                <div class="dd-flex dd-gap-1 dd-mb-2" style="flex-wrap:wrap;align-items:flex-end;">
                    <div class="dd-form-group" style="margin-bottom:0;min-width:200px;flex:1;">
                        <label for="dd-song-genre"><?php echo esc_html( '장르' ); ?></label>
                        <select id="dd-song-genre" class="dd-input">
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
                        <input type="text" id="dd-song-genre-custom" class="dd-input dd-hidden" style="margin-top:0.4rem;" placeholder="장르 직접 입력">
                    </div>
                    <div class="dd-form-group" style="margin-bottom:0;min-width:200px;flex:1.4;">
                        <label for="dd-song-title"><?php echo esc_html( '강좌 제목 (장르명, 수정 가능)' ); ?></label>
                        <input type="text" id="dd-song-title" class="dd-input" placeholder="예: C-Pop 발라드 모음">
                    </div>
                </div>
            </div>

            <!-- 기존 강좌에 추가 -->
            <div id="dd-song-existing-fields" class="dd-hidden">
                <div class="dd-form-group dd-mb-2">
                    <label for="dd-song-existing-course"><?php echo esc_html( '추가할 중국어 노래 학습 선택' ); ?></label>
                    <select id="dd-song-existing-course" class="dd-input">
                        <option value="">불러오는 중...</option>
                    </select>
                    <span class="dd-help-text"><?php echo esc_html( '선택한 강좌 뒤에 곡이 추가됩니다.' ); ?></span>
                </div>
            </div>

            <p class="dd-help-text dd-mb-2"><?php echo esc_html( '각 곡의 연도를 입력하면 강좌 안에서 연도순 정렬·필터에 사용됩니다.' ); ?></p>
            <div class="dd-song-tracklist" id="dd-song-track-list">
                <!-- Tracks populated by JS -->
            </div>

            <div class="dd-flex-between dd-mt-2" style="align-items: flex-end; gap: 1rem; flex-wrap: wrap;">
                <div class="dd-form-group" style="margin-bottom: 0; min-width: 200px;">
                    <label for="dd-song-level"><?php echo esc_html( '난이도' ); ?></label>
                    <select id="dd-song-level" class="dd-input">
                        <option value="beginner" selected><?php echo esc_html( '입문 (병음 포함)' ); ?></option>
                        <option value="intermediate"><?php echo esc_html( '중급 (병음 없음)' ); ?></option>
                        <option value="advanced"><?php echo esc_html( '고급 (병음 없음)' ); ?></option>
                    </select>
                </div>
                <div>
                    <button type="button" class="dd-btn dd-btn-secondary dd-btn-sm" id="dd-btn-song-check-subs">
                        <?php echo esc_html( '가사 자막 확인' ); ?>
                    </button>
                    <button type="button" class="dd-btn dd-btn-primary" id="dd-btn-song-generate">
                        <?php echo esc_html( '선택한 곡으로 강좌 생성' ); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Subtitle Fallback -->
    <div class="dd-card dd-hidden dd-mt-2" id="dd-song-subtitle-card">
        <div class="dd-card-header">
            <h2><?php echo esc_html( '가사 직접 입력' ); ?></h2>
        </div>
        <div class="dd-card-body">
            <p class="dd-mb-2">
                <?php echo esc_html( '자막이 없는 곡은 가사를 직접 입력하세요. 파일 업로드 또는 텍스트 붙여넣기를 지원합니다.' ); ?>
            </p>
            <div class="dd-sub-upload-tip" style="background:#f0f7ff;border:1px solid #c8dff5;border-radius:8px;padding:0.75rem 1rem;margin-bottom:1rem;font-size:0.85rem;color:#1565C0;">
                <strong>💡 가사 출처 추천:</strong> 음악 플랫폼(QQ音乐, 网易云音乐, Spotify)의 공식 가사를 복사하거나, <a href="https://downsub.com/" target="_blank" rel="noopener noreferrer" style="color:#1565C0;font-weight:600;">downsub.com</a>에서 자동생성 자막을 받아오세요.
            </div>
            <div id="dd-song-subtitle-inputs">
                <!-- Per-song textareas populated by JS -->
            </div>
            <button type="button" class="dd-btn dd-btn-primary dd-mt-2" id="dd-btn-song-subtitle-continue">
                <?php echo esc_html( '가사 입력 완료 — 생성 계속' ); ?>
            </button>
        </div>
    </div>

    <!-- Progress -->
    <div class="dd-card dd-hidden dd-mt-2" id="dd-song-progress-card">
        <div class="dd-card-header">
            <h2><?php echo esc_html( '생성 진행 상황' ); ?></h2>
        </div>
        <div class="dd-card-body">
            <div class="dd-progress">
                <div class="dd-progress-bar animated" id="dd-song-progress-bar" style="--dd-progress: 0%;"></div>
                <div class="dd-progress-text" id="dd-song-progress-text">
                    <?php echo esc_html( '준비 중...' ); ?>
                </div>
            </div>
            <div class="dd-mt-2">
                <h3 style="font-size: 0.9rem; font-weight: 600; margin-bottom: 0.5rem;">
                    <?php echo esc_html( '생성된 강의' ); ?>
                </h3>
                <ul class="dd-generated-lessons" id="dd-song-lessons-list"></ul>
            </div>
        </div>
    </div>

    <!-- Result -->
    <div class="dd-card dd-hidden dd-mt-2" id="dd-song-result-card">
        <div class="dd-card-header">
            <h2><?php echo esc_html( '생성 완료!' ); ?></h2>
        </div>
        <div class="dd-card-body">
            <div class="dd-alert dd-alert-success">
                <?php echo esc_html( '중국어 노래 학습 강좌가 성공적으로 생성되었습니다.' ); ?>
            </div>
            <p id="dd-song-result-summary"></p>
            <div class="dd-mt-2">
                <a href="#" class="dd-btn dd-btn-primary" id="dd-btn-view-song-course">
                    <?php echo esc_html( '강좌 보기' ); ?>
                </a>
                <button type="button" class="dd-btn dd-btn-secondary" id="dd-btn-song-another">
                    <?php echo esc_html( '다른 중국어 노래 학습 생성' ); ?>
                </button>
            </div>
        </div>
    </div>
</div>
