<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI 단어장 - Dingdong</title>
    <link rel="stylesheet" href="<?php echo esc_url( DD_LMS_URL . 'public/css/dd-lesson.css' ); ?>">
    <link rel="stylesheet" href="<?php echo esc_url( DD_LMS_URL . 'public/css/dd-assistant.css' ); ?>">
</head>
<body data-dd-page="vocabulary">

<nav class="dd-topbar">
    <div class="dd-topbar-inner">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="dd-topbar-brand">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            DingDong
        </a>
        <div class="dd-topbar-nav">
            <a href="<?php echo esc_url( home_url( '/courses/' ) ); ?>" class="dd-topbar-link">강좌 목록</a>
            <a href="<?php echo esc_url( home_url( '/stories/' ) ); ?>" class="dd-topbar-link">AI 스토리</a>
            <a href="<?php echo esc_url( home_url( '/newsletters/' ) ); ?>" class="dd-topbar-link">뉴스레터</a>
            <a href="<?php echo esc_url( home_url( '/vocabulary/' ) ); ?>" class="dd-topbar-link">단어장</a>
        </div>
    </div>
</nav>

<div class="dd-vocab-page">
    <header class="dd-vocab-page-header">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="display:inline-block;font-size:1.1rem;font-weight:700;color:var(--dd-primary);text-decoration:none;margin-bottom:1.5rem;">Dingdong 叮咚</a>
        <h1>AI 단어장</h1>
        <p>학습한 단어를 저장하고 게임으로 복습하세요</p>
    </header>

    <div class="dd-vocab-page-actions">
        <button class="dd-vocab-export-btn" id="dd-vocab-export" title="단어 내보내기">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            내보내기
        </button>
        <label class="dd-vocab-import-btn" title="단어 가져오기">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            가져오기
            <input type="file" accept=".csv,.json" id="dd-vocab-import" style="display:none;">
        </label>
    </div>

    <div id="dd-vocab-standalone"></div>
</div>

<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-vocabulary.js' ); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    DDVocabUI.renderPanel(document.getElementById('dd-vocab-standalone'), { showFilters: true });

    document.getElementById('dd-vocab-export').addEventListener('click', function() {
        var data = DDVocab.exportData();
        var blob = new Blob([data], { type: 'text/csv;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = DDVocab.exportFilename();
        a.click();
        URL.revokeObjectURL(url);
    });

    document.getElementById('dd-vocab-import').addEventListener('change', function(e) {
        var file = e.target.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function() {
            if (DDVocab.importData(reader.result)) {
                alert('단어장을 가져왔습니다!');
                DDVocabUI.renderPanel(document.getElementById('dd-vocab-standalone'), { showFilters: true });
            } else {
                alert('잘못된 파일 형식입니다.');
            }
        };
        reader.readAsText(file);
    });
});
</script>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-shared.js' ); ?>"></script>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-gamification.js' ); ?>"></script>
<script src="<?php echo esc_url( DD_LMS_URL . 'public/js/dd-assistant.js' ); ?>"></script>
</body>
</html>
