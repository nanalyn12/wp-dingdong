/**
 * dd-song-lyricsync.js
 * 중국어 노래 학습 / 드라마 장면 자막 음악 플레이어: YouTube IFrame Player API 기반
 *  - 커스텀 재생바(재생/일시정지, 탐색, 시간)
 *  - 자막/가사 줄(data-time) 가사 싱크 하이라이트 + 자동 스크롤 + 줄 클릭 점프
 *
 * 시각 정보:
 *  - 줄에 data-time 이 있으면 그 값으로 정확히 동작(노래 가사·타임스탬프 자막).
 *  - 줄에 data-time 이 없고 컨테이너에 data-interp="1" 이면(드라마 장면 자막) 영상
 *    길이(getDuration) 또는 data-scene-start/end 구간으로 줄을 균등 보간해 클릭→seek +
 *    하이라이트가 동작하게 한다. (노래 정적 가사는 data-interp 가 없으므로 보간하지 않음)
 */
(function () {
    'use strict';

    var iframe = document.getElementById('dd-lyricsync-iframe');
    if (!iframe) return;

    var list = document.querySelector('.dd-song-lyrics-list');
    var allLines = list
        ? Array.prototype.slice.call(list.querySelectorAll('.dd-song-lyrics-line'))
        : [];

    // 타임스탬프(data-time) 있는 줄 수집 → 시간순 정렬
    var timed = [];
    function buildTimed() {
        timed = allLines.map(function (el) {
            var t = el.getAttribute('data-time');
            return { el: el, time: (t === null || t === '') ? NaN : parseFloat(t) };
        }).filter(function (x) { return !isNaN(x.time); });
        timed.sort(function (a, b) { return a.time - b.time; });
    }
    buildTimed();

    /* 줄별 data-time 이 없을 때(드라마 장면 자막) 구간 보간으로 시각을 부여한다.
       - data-interp="1" 인 컨테이너에서만 동작(노래 정적 가사 보호).
       - 이미 2줄 이상 타임스탬프가 있으면(실측) 보간하지 않는다. */
    function deriveTimes(duration) {
        if (!list || list.getAttribute('data-interp') !== '1') return;
        if (timed.length >= 2) return;
        if (!allLines.length) return;
        var s = parseFloat(list.getAttribute('data-scene-start'));
        var e = parseFloat(list.getAttribute('data-scene-end'));
        if (isNaN(s)) s = 0;
        if (isNaN(e) || e <= s) e = (duration && duration > s) ? duration : 0;
        if (!e || e <= s) return; // 아직 구간을 알 수 없음 → 재생 시작 후 재시도
        var n = allLines.length;
        var span = e - s;
        allLines.forEach(function (el, i) {
            // 각 줄을 자기 구간의 시작점에 배치(마지막 줄이 정확히 끝에 닿지 않게)
            el.setAttribute('data-time', String(Math.round(s + span * (i / n))));
        });
        buildTimed();
    }

    // 재생바 요소
    var bar = document.getElementById('dd-lyricsync-bar');
    var playBtn = document.getElementById('dd-kbar-play');
    var track = document.getElementById('dd-kbar-track');
    var fill = document.getElementById('dd-kbar-fill');
    var thumb = document.getElementById('dd-kbar-thumb');
    var curEl = document.getElementById('dd-kbar-cur');
    var durEl = document.getElementById('dd-kbar-dur');

    var player = null, timer = null, activeIdx = -1;
    var userScrolling = false, userTimer = null, scrubbing = false;

    /* 가사는 재생바 아래 풀폭으로 분리됨 → 높이는 CSS(max-height clamp)에 위임.
       인라인 높이가 남아 있으면 제거해 CSS 값이 적용되도록 한다. */
    function syncLyricsHeight() {
        if (!list) return;
        list.style.maxHeight = '';
    }
    syncLyricsHeight();
    window.addEventListener('resize', syncLyricsHeight);
    setTimeout(syncLyricsHeight, 350);
    setTimeout(syncLyricsHeight, 1200);

    /* 몰입 모드 토글: 양옆 네비 숨기고 플레이어 풀블리드 → 가사 높이 재동기화 */
    var immBtn = document.getElementById('dd-lyricsync-immersive');
    if (immBtn) {
        immBtn.addEventListener('click', function () {
            document.body.classList.toggle('dd-lyricsync-immersive');
            setTimeout(syncLyricsHeight, 60);
            setTimeout(syncLyricsHeight, 380);
        });
    }

    function fmtClock(s) {
        s = Math.floor(s || 0); if (s < 0) s = 0;
        var m = Math.floor(s / 60), ss = s % 60;
        return m + ':' + (ss < 10 ? '0' : '') + ss;
    }

    /* 활성 줄(현재 칩) 갱신 — timed 인덱스 기준 */
    function setActiveIdx(idx) {
        if (idx === activeIdx) return;
        if (activeIdx >= 0 && timed[activeIdx]) {
            timed[activeIdx].el.classList.remove('is-lyricsync-active');
        }
        if (idx >= 0 && timed[idx]) {
            timed[idx].el.classList.add('is-lyricsync-active');
            if (!userScrolling) centerLine(timed[idx].el);
        }
        activeIdx = idx;
    }

    /* 줄 클릭 → 즉시 활성칩 하이라이트 + 해당 시각으로 정확히 점프(있으면).
       모든 줄에 위임(보간으로 나중에 data-time 이 채워질 수 있으므로 클릭 시점에 재조회). */
    allLines.forEach(function (el) {
        el.addEventListener('click', function () {
            var raw = el.getAttribute('data-time');
            var t = (raw === null || raw === '') ? NaN : parseFloat(raw);
            // 즉시 하이라이트
            var idx = -1;
            for (var i = 0; i < timed.length; i++) { if (timed[i].el === el) { idx = i; break; } }
            if (idx >= 0) {
                setActiveIdx(idx);
            } else {
                var prev = list && list.querySelector('.is-lyricsync-active');
                if (prev) prev.classList.remove('is-lyricsync-active');
                el.classList.add('is-lyricsync-active');
                activeIdx = -1;
            }
            // 정확히 seek
            if (!isNaN(t) && player && player.seekTo) {
                player.seekTo(t, true);
                if (player.playVideo) player.playVideo();
            }
        });
    });

    /* 사용자 스크롤 시 자동 스크롤 일시정지 */
    if (list) {
        ['wheel', 'touchstart'].forEach(function (ev) {
            list.addEventListener(ev, function () {
                userScrolling = true;
                clearTimeout(userTimer);
                userTimer = setTimeout(function () { userScrolling = false; }, 3500);
            }, { passive: true });
        });
    }

    /* ── 재생바 컨트롤 ── */
    if (playBtn) {
        playBtn.addEventListener('click', function () {
            if (!player || !player.getPlayerState) return;
            if (player.getPlayerState() === YT.PlayerState.PLAYING) player.pauseVideo();
            else player.playVideo();
        });
    }
    if (track) {
        var seekFrom = function (clientX) {
            if (!player || !player.getDuration) return;
            var d = player.getDuration();
            if (!d) return;
            var r = track.getBoundingClientRect();
            var x = Math.max(0, Math.min(1, (clientX - r.left) / r.width));
            player.seekTo(x * d, true);
            updateBar(x * d, d);
        };
        track.addEventListener('pointerdown', function (e) {
            scrubbing = true;
            try { track.setPointerCapture(e.pointerId); } catch (_) {}
            seekFrom(e.clientX);
        });
        track.addEventListener('pointermove', function (e) { if (scrubbing) seekFrom(e.clientX); });
        track.addEventListener('pointerup', function () { scrubbing = false; });
        track.addEventListener('pointercancel', function () { scrubbing = false; });
    }

    function updateBar(t, d) {
        if (curEl) curEl.textContent = fmtClock(t);
        if (d > 0) {
            if (durEl) durEl.textContent = fmtClock(d);
            var pct = Math.max(0, Math.min(100, (t / d) * 100));
            if (fill) fill.style.width = pct + '%';
            if (thumb) thumb.style.left = pct + '%';
        }
    }

    /* ── YouTube IFrame API ── */
    function loadApi(cb) {
        if (window.YT && window.YT.Player) { cb(); return; }
        var prev = window.onYouTubeIframeAPIReady;
        window.onYouTubeIframeAPIReady = function () {
            if (typeof prev === 'function') { try { prev(); } catch (e) {} }
            cb();
        };
        if (!document.getElementById('dd-yt-iframe-api')) {
            var s = document.createElement('script');
            s.id = 'dd-yt-iframe-api';
            s.src = 'https://www.youtube.com/iframe_api';
            document.head.appendChild(s);
        }
    }

    function syncOnClass() {
        if (list && timed.length) list.classList.add('dd-lyricsync-on');
    }

    loadApi(function () {
        player = new YT.Player('dd-lyricsync-iframe', {
            events: {
                onReady: function () {
                    var d = 0;
                    try { d = player.getDuration ? player.getDuration() : 0; } catch (e) {}
                    deriveTimes(d);          // 드라마 장면 자막: 시각 보간(필요 시)
                    syncOnClass();
                    try { updateBar(0, d); } catch (e) {}
                },
                onStateChange: function (e) {
                    var playing = e.data === YT.PlayerState.PLAYING;
                    if (bar) bar.classList.toggle('is-playing', playing);
                    if (playing) startLoop(); else stopLoop();
                }
            }
        });
    });

    function startLoop() { if (!timer) { timer = setInterval(update, 200); update(); } }
    function stopLoop() { if (timer) { clearInterval(timer); timer = null; } }

    function update() {
        if (!player || !player.getCurrentTime) return;
        var t, d;
        try {
            t = player.getCurrentTime();
            d = player.getDuration ? player.getDuration() : 0;
        } catch (e) { return; }

        updateBar(t, d);

        // onReady 시점에 구간(영상 길이)을 못 구했으면 재생 시작 후 보간 재시도
        if (!timed.length) {
            deriveTimes(d);
            if (!timed.length) return;
            syncOnClass();
        }

        var idx = -1;
        for (var i = 0; i < timed.length; i++) {
            if (timed[i].time <= t + 0.25) idx = i; else break;
        }
        setActiveIdx(idx);
    }

    function centerLine(el) {
        if (!list) return;
        var cr = list.getBoundingClientRect();
        var er = el.getBoundingClientRect();
        var target = list.scrollTop + (er.top - cr.top) - (list.clientHeight / 2) + (el.clientHeight / 2);
        try { list.scrollTo({ top: target, behavior: 'smooth' }); }
        catch (e) { list.scrollTop = target; }
    }
})();
