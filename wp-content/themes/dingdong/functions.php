<?php
/**
 * Dingdong Theme functions and definitions
 */

// Enqueue Google Fonts and theme stylesheet for both front-end and block editor
function dingdong_enqueue_assets() {
    wp_enqueue_style(
        'dingdong-fonts',
        'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Noto+Sans+KR:wght@300;400;500;700&family=Noto+Sans+TC:wght@300;400;500;700&display=swap',
        array(),
        null
    );

    wp_enqueue_style(
        'dingdong-style',
        get_stylesheet_uri(),
        array( 'dingdong-fonts' ),
        wp_get_theme()->get( 'Version' )
    );
}
add_action( 'enqueue_block_assets', 'dingdong_enqueue_assets' );

// Scroll animation observer
function dingdong_scroll_animations() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var els = document.querySelectorAll('.animate-on-scroll, .stagger-children');
        if (!els.length) return;
        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });
        els.forEach(function(el) { observer.observe(el); });
    });
    </script>
    <?php
}
add_action( 'wp_footer', 'dingdong_scroll_animations' );

// Theme Color Switcher
function dingdong_color_switcher() {
    ?>
    <button class="dd-color-switcher-toggle" aria-label="Change theme colors">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <path d="M12 2a10 10 0 0 1 0 20 7 7 0 0 1 0-14 3.5 3.5 0 0 0 0-6"/>
        </svg>
    </button>
    <div class="dd-color-switcher-panel">
        <h4>Theme Colors</h4>
        <button class="dd-color-option is-active" data-preset="pink">
            <span class="dd-color-swatch" style="background:#DB7F8E"></span>
            <span class="dd-color-label">Pink / Blush</span>
        </button>
        <button class="dd-color-option" data-preset="blue">
            <span class="dd-color-swatch" style="background:#7BADE2"></span>
            <span class="dd-color-label">Sky Blue</span>
        </button>
        <button class="dd-color-option" data-preset="green">
            <span class="dd-color-swatch" style="background:#7BAE7F"></span>
            <span class="dd-color-label">Sage Green</span>
        </button>
        <button class="dd-color-option" data-preset="purple">
            <span class="dd-color-swatch" style="background:#9B7FDB"></span>
            <span class="dd-color-label">Lavender</span>
        </button>
        <button class="dd-color-option" data-preset="orange">
            <span class="dd-color-swatch" style="background:#DB9F7F"></span>
            <span class="dd-color-label">Warm Orange</span>
        </button>
    </div>
    <script>
    (function() {
        var presets = {
            pink: { accent: '#DB7F8E', accentHover: '#c96e7d', soft: '#FFDBDA', mid: '#EBCCC5', shadow: 'rgba(219,127,142,0.3)', shadowHover: 'rgba(219,127,142,0.4)' },
            blue: { accent: '#7BADE2', accentHover: '#5f9ad4', soft: '#D6EAFF', mid: '#C5DDF0', shadow: 'rgba(123,173,226,0.3)', shadowHover: 'rgba(123,173,226,0.4)' },
            green: { accent: '#7BAE7F', accentHover: '#5f9a63', soft: '#D6F0D8', mid: '#C5E0C7', shadow: 'rgba(123,174,127,0.3)', shadowHover: 'rgba(123,174,127,0.4)' },
            purple: { accent: '#9B7FDB', accentHover: '#8468c9', soft: '#E0D6FF', mid: '#D0C5F0', shadow: 'rgba(155,127,219,0.3)', shadowHover: 'rgba(155,127,219,0.4)' },
            orange: { accent: '#DB9F7F', accentHover: '#c98b6a', soft: '#FFE5DA', mid: '#F0D5C5', shadow: 'rgba(219,159,127,0.3)', shadowHover: 'rgba(219,159,127,0.4)' }
        };

        var toggle = document.querySelector('.dd-color-switcher-toggle');
        var panel = document.querySelector('.dd-color-switcher-panel');
        var options = document.querySelectorAll('.dd-color-option');

        // Restore saved
        var saved = localStorage.getItem('dd-color-preset') || 'pink';
        applyPreset(saved);
        markActive(saved);

        toggle.addEventListener('click', function() {
            panel.classList.toggle('is-open');
        });

        document.addEventListener('click', function(e) {
            if (!panel.contains(e.target) && !toggle.contains(e.target)) {
                panel.classList.remove('is-open');
            }
        });

        options.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var preset = this.getAttribute('data-preset');
                applyPreset(preset);
                markActive(preset);
                localStorage.setItem('dd-color-preset', preset);
            });
        });

        function applyPreset(name) {
            var p = presets[name];
            if (!p) return;
            var root = document.documentElement;
            root.style.setProperty('--dd-accent', p.accent);
            root.style.setProperty('--dd-accent-hover', p.accentHover);
            root.style.setProperty('--dd-soft', p.soft);
            root.style.setProperty('--dd-mid', p.mid);
            root.style.setProperty('--dd-accent-shadow', p.shadow);
            root.style.setProperty('--dd-accent-shadow-hover', p.shadowHover);
        }

        function markActive(name) {
            options.forEach(function(btn) {
                btn.classList.toggle('is-active', btn.getAttribute('data-preset') === name);
            });
        }
    })();
    </script>
    <?php
}
add_action( 'wp_footer', 'dingdong_color_switcher' );

// Newsletter Popup
function dingdong_newsletter_popup() {
    ?>
    <div class="dd-newsletter-overlay" id="dd-newsletter-overlay"></div>
    <div class="dd-newsletter-popup" id="dd-newsletter-popup">
        <div class="dd-newsletter-popup-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--dd-accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <h4>구독해 주셔서 감사합니다!</h4>
        <p>유익한 소식으로 찾아갈게요!</p>
        <button class="dd-newsletter-popup-close">확인</button>
    </div>
    <script>
    (function() {
        var btn = document.querySelector('.dd-newsletter-submit');
        var popup = document.getElementById('dd-newsletter-popup');
        var overlay = document.getElementById('dd-newsletter-overlay');
        var closeBtn = popup.querySelector('.dd-newsletter-popup-close');
        if (!btn) return;

        btn.addEventListener('click', function() {
            var input = btn.parentElement.querySelector('input[type="email"]');
            if (input && input.value && input.value.indexOf('@') > -1) {
                popup.classList.add('is-visible');
                overlay.classList.add('is-visible');
                input.value = '';
            }
        });

        function closePopup() {
            popup.classList.remove('is-visible');
            overlay.classList.remove('is-visible');
        }

        closeBtn.addEventListener('click', closePopup);
        overlay.addEventListener('click', closePopup);
    })();
    </script>
    <?php
}
add_action( 'wp_footer', 'dingdong_newsletter_popup' );

// Contact Modal
function dingdong_contact_modal() {
    ?>
    <div class="dd-modal-overlay" id="dd-contact-modal">
        <div class="dd-modal">
            <button class="dd-modal-close" aria-label="Close">&times;</button>
            <h3>문의하기</h3>
            <form class="dd-modal-form" onsubmit="return false;">
                <label for="dd-name">이름</label>
                <input type="text" id="dd-name" name="name" placeholder="이름을 입력하세요" required>
                <label for="dd-email">이메일</label>
                <input type="email" id="dd-email" name="email" placeholder="이메일을 입력하세요" required>
                <label for="dd-message">문의 내용</label>
                <textarea id="dd-message" name="message" placeholder="문의 내용을 작성해주세요" required></textarea>
                <button type="submit" class="dd-modal-submit">보내기</button>
            </form>
        </div>
    </div>
    <script>
    (function() {
        var modal = document.getElementById('dd-contact-modal');
        var closeBtn = modal.querySelector('.dd-modal-close');

        // Open modal from any button with data-open-contact
        document.addEventListener('click', function(e) {
            var trigger = e.target.closest('[data-open-contact]');
            if (trigger) {
                e.preventDefault();
                modal.classList.add('is-open');
            }
        });

        // Also open from footer contact button link
        document.addEventListener('click', function(e) {
            var link = e.target.closest('.dd-open-contact-modal');
            if (link) {
                e.preventDefault();
                modal.classList.add('is-open');
            }
        });

        closeBtn.addEventListener('click', function() {
            modal.classList.remove('is-open');
        });

        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.classList.remove('is-open');
            }
        });

        // Handle form submission
        var form = modal.querySelector('.dd-modal-form');
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            alert('문의가 접수되었습니다. 감사합니다!');
            modal.classList.remove('is-open');
            form.reset();
        });
    })();
    </script>
    <?php
}
add_action( 'wp_footer', 'dingdong_contact_modal' );
