(function() {
    'use strict';

    var pages = document.querySelectorAll('.dd-storybook-page');
    var prevBtn = document.getElementById('dd-sb-prev');
    var nextBtn = document.getElementById('dd-sb-next');
    var indicator = document.getElementById('dd-sb-indicator');
    var currentPage = 0;

    if (!pages.length) return;

    function showPage(index) {
        pages.forEach(function(p) { p.classList.remove('is-active'); });
        pages[index].classList.add('is-active');
        currentPage = index;
        if (indicator) indicator.textContent = (index + 1) + ' / ' + pages.length;
        if (prevBtn) prevBtn.disabled = index === 0;
        if (nextBtn) nextBtn.disabled = index === pages.length - 1;
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', function() {
            if (currentPage > 0) showPage(currentPage - 1);
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function() {
            if (currentPage < pages.length - 1) showPage(currentPage + 1);
        });
    }

    // 키보드 좌우 화살표
    document.addEventListener('keydown', function(e) {
        var panel = document.getElementById('panel-storybook');
        if (!panel || !panel.classList.contains('is-active')) return;
        if (e.key === 'ArrowLeft' && currentPage > 0) showPage(currentPage - 1);
        if (e.key === 'ArrowRight' && currentPage < pages.length - 1) showPage(currentPage + 1);
    });

    // 모바일 스와이프
    var touchStartX = 0;
    var container = document.querySelector('.dd-storybook-pages');
    if (container) {
        container.addEventListener('touchstart', function(e) {
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });

        container.addEventListener('touchend', function(e) {
            var diff = touchStartX - e.changedTouches[0].screenX;
            if (Math.abs(diff) > 50) {
                if (diff > 0 && currentPage < pages.length - 1) showPage(currentPage + 1);
                if (diff < 0 && currentPage > 0) showPage(currentPage - 1);
            }
        }, { passive: true });
    }

    showPage(0);
})();
