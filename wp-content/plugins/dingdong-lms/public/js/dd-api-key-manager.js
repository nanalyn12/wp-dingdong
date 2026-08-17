(function() {
    'use strict';

    var STORAGE_KEY = 'dd_student_gemini_key';

    window.DDApiKeyManager = {
        getKey: function() {
            return localStorage.getItem(STORAGE_KEY) || '';
        },

        setKey: function(key) {
            if (key) {
                localStorage.setItem(STORAGE_KEY, key);
            } else {
                localStorage.removeItem(STORAGE_KEY);
            }
            window.dispatchEvent(new CustomEvent('dd-api-key-changed'));
        },

        hasKey: function() {
            return !!localStorage.getItem(STORAGE_KEY);
        },

        removeKey: function() {
            localStorage.removeItem(STORAGE_KEY);
            window.dispatchEvent(new CustomEvent('dd-api-key-changed'));
        }
    };

    // --- Elements ---
    var overlay   = document.getElementById('dd-key-modal-overlay');
    var input     = document.getElementById('dd-key-input');
    var saveBtn   = document.getElementById('dd-key-save');
    var cancelBtn = document.getElementById('dd-key-cancel');
    var deleteBtn = document.getElementById('dd-key-delete');
    var openBtns  = document.querySelectorAll('.dd-open-key-modal');

    // Floating key button
    var keyFab   = document.getElementById('dd-key-fab');
    var keyLabel = document.getElementById('dd-key-fab-label');

    if (!overlay) return;

    // --- Floating button state ---
    function updateFabState() {
        if (!keyFab) return;
        var hasKey = DDApiKeyManager.hasKey();
        if (hasKey) {
            keyFab.classList.add('is-set');
            if (keyLabel) keyLabel.textContent = 'API 키 설정됨';
        } else {
            keyFab.classList.remove('is-set');
            if (keyLabel) keyLabel.textContent = 'API 키 설정';
        }
    }

    updateFabState();
    window.addEventListener('dd-api-key-changed', updateFabState);

    // --- Modal ---
    function openModal() {
        overlay.classList.add('is-open');
        if (input) {
            input.value = DDApiKeyManager.getKey();
            input.type = 'password';
        }
        if (deleteBtn) {
            deleteBtn.style.display = DDApiKeyManager.hasKey() ? '' : 'none';
        }
    }

    function closeModal() {
        overlay.classList.remove('is-open');
    }

    // Floating button opens modal
    if (keyFab) {
        keyFab.addEventListener('click', openModal);
    }

    // Existing open buttons inside chatbot etc.
    openBtns.forEach(function(btn) {
        btn.addEventListener('click', openModal);
    });

    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) closeModal();
    });

    if (saveBtn && input) {
        saveBtn.addEventListener('click', function() {
            var val = input.value.trim();
            if (val) {
                DDApiKeyManager.setKey(val);
                closeModal();
            }
        });
    }

    if (deleteBtn) {
        deleteBtn.addEventListener('click', function() {
            DDApiKeyManager.removeKey();
            if (input) input.value = '';
            closeModal();
        });
    }

    // Keyboard: Escape closes, Enter saves
    document.addEventListener('keydown', function(e) {
        if (!overlay.classList.contains('is-open')) return;
        if (e.key === 'Escape') closeModal();
        if (e.key === 'Enter' && document.activeElement === input) {
            e.preventDefault();
            if (saveBtn) saveBtn.click();
        }
    });
})();
