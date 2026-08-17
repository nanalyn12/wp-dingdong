(function() {
    'use strict';

    var synth = window.speechSynthesis;
    if (!synth) {
        var notice = document.getElementById('dd-ab-no-support');
        if (notice) notice.style.display = 'block';
        return;
    }

    var zhVoices = [];
    var rate = 1.0;
    var isPlayingAll = false;
    var currentItem = null;
    var items = [];
    var playAllBtn = document.getElementById('dd-ab-play-all');
    var stopBtn = document.getElementById('dd-ab-stop');

    var voiceProfiles = [
        { label: 'young-female', pitchRange: [1.0, 1.15], rateRange: [0.95, 1.05] },
        { label: 'young-male',   pitchRange: [0.75, 0.9],  rateRange: [0.9, 1.0] },
        { label: 'elder',        pitchRange: [0.6, 0.75],  rateRange: [0.8, 0.9] },
        { label: 'child',        pitchRange: [1.2, 1.4],   rateRange: [1.0, 1.1] },
        { label: 'adult-female', pitchRange: [0.95, 1.1],  rateRange: [0.9, 1.0] },
        { label: 'adult-male',   pitchRange: [0.8, 0.95],  rateRange: [0.85, 0.95] },
    ];

    var speakerVoiceMap = {};

    function collectItems() {
        items = Array.prototype.slice.call(document.querySelectorAll('.dd-ab-item'));
    }

    function findChineseVoices() {
        var voices = synth.getVoices();
        if (!voices.length) return;
        zhVoices = [];
        for (var i = 0; i < voices.length; i++) {
            var lang = voices[i].lang.toLowerCase().replace(/_/g, '-');
            if (lang === 'zh-cn' || lang === 'zh') {
                zhVoices.push(voices[i]);
            }
        }
        if (zhVoices.length === 0) {
            for (var j = 0; j < voices.length; j++) {
                var l2 = voices[j].lang.toLowerCase().replace(/_/g, '-');
                if (l2.indexOf('zh') === 0) {
                    zhVoices.push(voices[j]);
                }
            }
        }
    }

    if (synth.onvoiceschanged !== undefined) {
        synth.addEventListener('voiceschanged', findChineseVoices);
    }
    findChineseVoices();

    function randInRange(min, max) {
        return min + Math.random() * (max - min);
    }

    function getProfileForSpeaker(speaker) {
        if (!speaker) {
            return voiceProfiles[Math.floor(Math.random() * voiceProfiles.length)];
        }
        if (!speakerVoiceMap[speaker]) {
            var available = voiceProfiles.filter(function(p) {
                for (var k in speakerVoiceMap) {
                    if (speakerVoiceMap[k].label === p.label) return false;
                }
                return true;
            });
            if (available.length === 0) available = voiceProfiles;
            speakerVoiceMap[speaker] = available[Math.floor(Math.random() * available.length)];
        }
        return speakerVoiceMap[speaker];
    }

    function highlightItem(item) {
        if (currentItem) currentItem.classList.remove('is-playing');
        currentItem = item;
        if (item) {
            item.classList.add('is-playing');
            item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    function showPlayingUI(playing) {
        if (playAllBtn) playAllBtn.style.display = playing ? 'none' : '';
        if (stopBtn) stopBtn.style.display = playing ? '' : 'none';
    }

    function stripKorean(str) {
        return str.replace(/[가-힣ᄀ-ᇿ㄰-㆏]+/g, '').replace(/\s{2,}/g, ' ').trim();
    }

    function speakItem(item, callback) {
        var text = stripKorean(item.getAttribute('data-zh') || '');
        if (!text) { if (callback) callback(); return; }

        if (zhVoices.length === 0) {
            findChineseVoices();
        }
        if (zhVoices.length === 0) {
            var noVoice = document.getElementById('dd-ab-no-support');
            if (noVoice) {
                noVoice.style.display = 'block';
                noVoice.querySelector('p').textContent = '중국어(zh-CN) 음성을 찾을 수 없습니다. Chrome 브라우저를 사용해 주세요.';
            }
            if (callback) callback();
            return;
        }

        synth.cancel();
        var utter = new SpeechSynthesisUtterance(text);
        utter.lang = 'zh-CN';

        var voiceIdx = Math.floor(Math.random() * zhVoices.length);
        utter.voice = zhVoices[voiceIdx];

        var speakerEl = item.querySelector('.dd-ab-speaker');
        var speaker = speakerEl ? speakerEl.textContent.trim() : '';
        var type = item.getAttribute('data-type');

        if (type === 'dialogue' && speaker) {
            var profile = getProfileForSpeaker(speaker);
            utter.pitch = randInRange(profile.pitchRange[0], profile.pitchRange[1]);
            utter.rate = rate * randInRange(profile.rateRange[0], profile.rateRange[1]);
        } else {
            var exprProfiles = voiceProfiles.slice(0, 2);
            var p = exprProfiles[item.getAttribute('data-index') % exprProfiles.length];
            utter.pitch = randInRange(p.pitchRange[0], p.pitchRange[1]);
            utter.rate = rate;
        }

        highlightItem(item);

        utter.onend = function() {
            highlightItem(null);
            if (callback) callback();
        };

        utter.onerror = function() {
            highlightItem(null);
            if (callback) callback();
        };

        synth.speak(utter);
    }

    function playAll() {
        collectItems();
        if (!items.length) return;
        isPlayingAll = true;
        speakerVoiceMap = {};
        showPlayingUI(true);
        var idx = 0;

        function next() {
            if (!isPlayingAll || idx >= items.length) {
                isPlayingAll = false;
                showPlayingUI(false);
                return;
            }
            speakItem(items[idx], function() {
                idx++;
                next();
            });
        }
        next();
    }

    function stopAll() {
        isPlayingAll = false;
        synth.cancel();
        highlightItem(null);
        showPlayingUI(false);
    }

    collectItems();

    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.dd-ab-play-btn');
        if (!btn) return;
        var item = btn.closest('.dd-ab-item');
        if (!item) return;

        if (isPlayingAll) stopAll();
        speakItem(item, function() {});
    });

    if (playAllBtn) {
        playAllBtn.addEventListener('click', function() {
            playAll();
        });
    }

    if (stopBtn) {
        stopBtn.addEventListener('click', function() {
            stopAll();
        });
    }

    var speedBtns = document.querySelectorAll('.dd-ab-speed-btn');
    speedBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            speedBtns.forEach(function(b) { b.classList.remove('is-active'); });
            btn.classList.add('is-active');
            rate = parseFloat(btn.getAttribute('data-rate')) || 1;
        });
    });
})();
