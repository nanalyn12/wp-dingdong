/**
 * 叮叮 (Dīngding) — AI 학습도우미 판다
 * Cute Idol + Coachmark + Navigation + Roleplay + Costume
 *
 * v2.1 — 친근한 디자인, 옷 커스터마이징, 역할극, 페이지 이동
 */
(function () {
    'use strict';

    /* ============================================================
       Costume System — 의상 & 색상 커스터마이징
       ============================================================ */
    var OUTFITS = {
        hoodie:  { label: '후디',  icon: '🧥' },
        hanbok:  { label: '한복',  icon: '👘' },
        uniform: { label: '교복',  icon: '🎓' },
        qipao:   { label: '치파오', icon: '👗' },
        casual:  { label: '캐주얼', icon: '👕' },
        party:   { label: '파티',  icon: '🎉' }
    };
    var COLORS = {
        pink:   { label: '핑크',  main: '#E8839B', dark: '#D06B83', light: '#FFF0F3' },
        purple: { label: '퍼플',  main: '#9370DB', dark: '#7C5CBF', light: '#F3EEFF' },
        mint:   { label: '민트',  main: '#5CBFAD', dark: '#4AA393', light: '#EEFFF9' },
        sky:    { label: '스카이', main: '#6BA3E8', dark: '#5289CC', light: '#EEF6FF' },
        coral:  { label: '코랄',  main: '#E88370', dark: '#CC6B5A', light: '#FFF3EE' },
        gold:   { label: '골드',  main: '#D4A843', dark: '#B89035', light: '#FFF8EB' }
    };

    var costume = {
        outfit: localStorage.getItem('dd_outfit') || 'hoodie',
        color:  localStorage.getItem('dd_color')  || 'pink'
    };

    function saveCostume() {
        localStorage.setItem('dd_outfit', costume.outfit);
        localStorage.setItem('dd_color', costume.color);
    }

    function getColor() { return COLORS[costume.color] || COLORS.pink; }

    /* ============================================================
       SVG Builder — 자연스럽고 사랑스러운 판다 캐릭터
       v2.2 — 아기 판다 비율, 부드러운 곡선, 대나무 장식
       디자인 원칙: 따뜻한 검은 눈, 둥근 형태, 절대 무섭지 않게
       ============================================================ */
    function buildPandaBody() {
        var c = getColor();
        var outfitSVG = '';
        switch (costume.outfit) {
            case 'hanbok':
                outfitSVG =
                    /* 한복 저고리 — 부드러운 라인 */
                    '<path d="M36 88 Q48 83 60 83 Q72 83 84 88 L86 95 Q74 92 60 92 Q46 92 34 95 Z" fill="' + c.main + '"/>' +
                    '<path d="M34 95 Q46 92 60 92 Q74 92 86 95 L88 132 Q74 136 60 136 Q46 136 32 132 Z" fill="#FAFAFA" stroke="' + c.main + '" stroke-width="0.8"/>' +
                    /* 깃 */
                    '<path d="M53 92 L53 132" stroke="' + c.main + '" stroke-width="0.8" opacity="0.5"/>' +
                    '<path d="M67 92 L67 132" stroke="' + c.main + '" stroke-width="0.8" opacity="0.5"/>' +
                    /* 고름 매듭 */
                    '<path d="M56 94 Q60 100 64 94" fill="none" stroke="' + c.dark + '" stroke-width="1.2"/>' +
                    '<circle cx="60" cy="98" r="2" fill="' + c.main + '"/>';
                break;
            case 'uniform':
                outfitSVG =
                    '<path d="M36 88 Q48 83 60 83 Q72 83 84 88 L88 132 Q74 136 60 136 Q46 136 32 132 Z" fill="#FAFAFA" stroke="#E0E0E0" stroke-width="0.6"/>' +
                    /* 카라 */
                    '<path d="M36 88 Q48 83 60 83 Q72 83 84 88 L82 98 Q70 94 60 94 Q50 94 38 98 Z" fill="' + c.main + '"/>' +
                    '<line x1="60" y1="94" x2="60" y2="132" stroke="#EEEEEE" stroke-width="0.8"/>' +
                    /* 리본 타이 */
                    '<path d="M55 86 L60 94 L65 86" fill="none" stroke="' + c.dark + '" stroke-width="1.2"/>' +
                    '<circle cx="60" cy="94" r="1.8" fill="' + c.dark + '"/>';
                break;
            case 'qipao':
                outfitSVG =
                    '<path d="M36 88 Q48 83 60 83 Q72 83 84 88 L86 134 Q72 138 60 138 Q48 138 34 134 Z" fill="' + c.main + '"/>' +
                    /* 반달 깃 */
                    '<path d="M53 88 Q57 92 60 88 Q63 92 67 88" fill="none" stroke="' + c.dark + '" stroke-width="1"/>' +
                    '<circle cx="65" cy="90" r="1.3" fill="#FFD700"/>' +
                    /* 구름 무늬 */
                    '<path d="M44 108 Q52 105 60 108 Q68 105 76 108" fill="none" stroke="' + c.light + '" stroke-width="0.6" opacity="0.7"/>' +
                    '<path d="M44 118 Q52 115 60 118 Q68 115 76 118" fill="none" stroke="' + c.light + '" stroke-width="0.6" opacity="0.7"/>';
                break;
            case 'casual':
                outfitSVG =
                    '<path d="M36 88 Q48 83 60 83 Q72 83 84 88 L88 132 Q74 136 60 136 Q46 136 32 132 Z" fill="' + c.main + '"/>' +
                    /* 가슴 포인트 원 */
                    '<circle cx="60" cy="110" r="8" fill="#fff" opacity="0.2"/>' +
                    '<circle cx="60" cy="110" r="5" fill="#fff" opacity="0.15"/>';
                break;
            case 'party':
                outfitSVG =
                    '<path d="M36 88 Q48 83 60 83 Q72 83 84 88 L88 132 Q74 136 60 136 Q46 136 32 132 Z" fill="' + c.main + '"/>' +
                    /* 반짝이 줄무늬 */
                    '<path d="M42 94 L46 132" stroke="#FFD700" stroke-width="0.8" opacity="0.4"/>' +
                    '<path d="M54 92 L56 132" stroke="#FFD700" stroke-width="0.8" opacity="0.4"/>' +
                    '<path d="M66 92 L64 132" stroke="#FFD700" stroke-width="0.8" opacity="0.4"/>' +
                    '<path d="M78 94 L74 132" stroke="#FFD700" stroke-width="0.8" opacity="0.4"/>' +
                    /* 별 */
                    '<polygon points="60,96 61.5,100 66,100.5 62.5,103 63.5,107.5 60,105 56.5,107.5 57.5,103 54,100.5 58.5,100" fill="#FFD700" opacity="0.7"/>';
                break;
            default: /* hoodie */
                outfitSVG =
                    /* 후드 */
                    '<path d="M36 88 Q48 83 60 83 Q72 83 84 88 L84 94 Q74 91 60 91 Q46 91 36 94 Z" fill="' + c.dark + '"/>' +
                    /* 몸통 */
                    '<path d="M36 94 Q46 91 60 91 Q74 91 84 94 L88 132 Q74 136 60 136 Q46 136 32 132 Z" fill="' + c.main + '"/>' +
                    /* 지퍼 라인 */
                    '<line x1="60" y1="91" x2="60" y2="132" stroke="' + c.dark + '" stroke-width="1" opacity="0.5"/>' +
                    /* 주머니 */
                    '<path d="M48 112 Q54 110 60 110 Q66 110 72 112 L72 120 Q66 122 60 122 Q54 122 48 120 Z" fill="' + c.dark + '" opacity="0.25" rx="4"/>';
        }

        return '<svg viewBox="0 0 120 150" xmlns="http://www.w3.org/2000/svg">' +

        /* ── 그림자 ── */
        '<ellipse cx="60" cy="145" rx="28" ry="5" fill="#000" opacity="0.06"/>' +

        /* ── 뒷발 (작고 둥글게 — 아기 비율) ── */
        '<path d="M36 132 Q38 127 46 127 Q54 127 56 132 Q54 140 46 141 Q38 140 36 132Z" fill="#2D2D2D"/>' +
        '<path d="M64 132 Q66 127 74 127 Q82 127 84 132 Q82 140 74 141 Q66 140 64 132Z" fill="#2D2D2D"/>' +
        /* 발바닥 패드 */
        '<path d="M40 134 Q46 132 52 134 Q52 138 46 139 Q40 138 40 134Z" fill="#6B6B6B" opacity="0.25"/>' +
        '<path d="M68 134 Q74 132 80 134 Q80 138 74 139 Q68 138 68 134Z" fill="#6B6B6B" opacity="0.25"/>' +

        /* ── 몸통 (아기 판다 — 작고 둥근 배) ── */
        '<path d="M34 88 Q28 100 30 118 Q34 138 60 140 Q86 138 90 118 Q92 100 86 88 Q76 82 60 82 Q44 82 34 88Z" fill="#2D2D2D"/>' +
        '<path d="M40 92 Q36 102 38 118 Q42 134 60 136 Q78 134 82 118 Q84 102 80 92 Q72 88 60 88 Q48 88 40 92Z" fill="#FAFAFA"/>' +

        /* ── 의상 (몸통 위에) ── */
        outfitSVG +

        /* ── 왼쪽 팔 (의상 소매 + 손) — 자연스럽게 어깨에서 연결 ── */
        '<path d="M30 90 Q18 94 14 104 Q12 112 19 113 Q26 112 32 104 Q34 96 30 90Z" fill="' + c.main + '"/>' +
        /* 소매 끝 트림 */
        '<path d="M17 110 Q24 109 30 105" fill="none" stroke="' + c.dark + '" stroke-width="0.8" opacity="0.5"/>' +
        /* 손 (소매에서 살짝 나옴) */
        '<path d="M14 109 Q10 111 11 115 Q13 118 18 117 Q22 115 21 111 Q19 108 14 109Z" fill="#2D2D2D"/>' +
        '<path d="M13 114 Q16 112 19 114 Q19 117 16 117 Q13 117 13 114Z" fill="#FFC1C9" opacity="0.4"/>' +

        /* ── 오른쪽 팔 (의상 소매 + 흔드는 손) ── */
        '<g>' +
        '<path d="M90 90 Q102 94 106 104 Q108 112 101 113 Q94 112 88 104 Q86 96 90 90Z" fill="' + c.main + '"/>' +
        '<path d="M103 110 Q96 109 90 105" fill="none" stroke="' + c.dark + '" stroke-width="0.8" opacity="0.5"/>' +
        '<path d="M106 109 Q110 111 109 115 Q107 118 102 117 Q98 115 99 111 Q101 108 106 109Z" fill="#2D2D2D"/>' +
        '<path d="M107 114 Q104 112 101 114 Q101 117 104 117 Q107 117 107 114Z" fill="#FFC1C9" opacity="0.4"/>' +
        '<animateTransform attributeName="transform" type="rotate" values="0,96,100;-12,96,100;0,96,100;6,96,100;0,96,100" dur="3.2s" repeatCount="indefinite"/>' +
        '</g>' +

        /* ── 귀 (둥글고 큼직 — 아기 판다답게) ── */
        '<path d="M14 22 Q8 4 22 0 Q36 -2 38 14 Q38 24 28 28 Q18 30 14 22Z" fill="#2D2D2D"/>' +
        '<path d="M106 22 Q112 4 98 0 Q84 -2 82 14 Q82 24 92 28 Q102 30 106 22Z" fill="#2D2D2D"/>' +
        /* 안쪽 귀 (코스튬 컬러) */
        '<path d="M19 18 Q15 8 25 5 Q33 4 33 14 Q32 22 26 24 Q21 24 19 18Z" fill="' + c.main + '" opacity="0.4"/>' +
        '<path d="M101 18 Q105 8 95 5 Q87 4 87 14 Q88 22 94 24 Q99 24 101 18Z" fill="' + c.main + '" opacity="0.4"/>' +

        /* ── 머리 (더 둥글고 크게 — 아기 판다 비율) ── */
        '<path d="M16 48 Q12 22 38 12 Q50 8 60 8 Q70 8 82 12 Q108 22 104 48 Q104 78 82 86 Q70 90 60 90 Q50 90 38 86 Q16 78 16 48Z" fill="#FAFAFA"/>' +

        /* ── 이마 털 (부드러운 한 가닥, 자연스럽게) ── */
        '<path d="M48 16 Q54 11 60 13 Q66 11 72 16 Q70 21 66 19 Q60 17 54 19 Q50 21 48 16Z" fill="#2D2D2D"/>' +

        /* ── 눈 주위 패치 (물방울 형태) ── */
        '<path d="M26 38 Q28 28 42 28 Q54 30 56 42 Q56 54 46 58 Q34 60 26 54 Q22 46 26 38Z" fill="#2D2D2D"/>' +
        '<path d="M94 38 Q92 28 78 28 Q66 30 64 42 Q64 54 74 58 Q86 60 94 54 Q98 46 94 38Z" fill="#2D2D2D"/>' +

        /* ── 눈 (유기적 크기, 맑고 따뜻한 하이라이트) ── */
        '<circle cx="42" cy="44" r="9" fill="#fff"/>' +
        '<circle cx="78" cy="44" r="9" fill="#fff"/>' +
        '<circle cx="43" cy="45" r="6.5" fill="#1C1108"/>' +
        '<circle cx="79" cy="45" r="6.5" fill="#1C1108"/>' +
        /* 하이라이트 */
        '<circle cx="46" cy="42" r="3" fill="#fff"/>' +
        '<circle cx="82" cy="42" r="3" fill="#fff"/>' +
        '<circle cx="41" cy="47" r="1.3" fill="#fff" opacity="0.5"/>' +
        '<circle cx="77" cy="47" r="1.3" fill="#fff" opacity="0.5"/>' +

        /* ── 눈깜빡 ── */
        '<path d="M33 44 Q42 40 51 44" stroke="#3A3A3A" stroke-width="2" fill="none" stroke-linecap="round" opacity="0">' +
        '<animate attributeName="opacity" values="0;0;1;0;0" keyTimes="0;0.95;0.965;0.985;1" dur="4.5s" repeatCount="indefinite"/>' +
        '</path>' +
        '<path d="M69 44 Q78 40 87 44" stroke="#3A3A3A" stroke-width="2" fill="none" stroke-linecap="round" opacity="0">' +
        '<animate attributeName="opacity" values="0;0;1;0;0" keyTimes="0;0.95;0.965;0.985;1" dur="4.5s" repeatCount="indefinite"/>' +
        '</path>' +

        /* ── 코 (삼각 코, 촉촉한 느낌) ── */
        '<path d="M56 56 Q60 52 64 56 Q62 60 60 61 Q58 60 56 56Z" fill="#3A3A3A"/>' +
        '<path d="M58 55 Q60 54 61 56" fill="none" stroke="#555" stroke-width="0.8" opacity="0.3"/>' +

        /* ── 입 (w형) ── */
        '<path d="M54 62 Q57 66 60 64 Q63 66 66 62" fill="none" stroke="#3A3A3A" stroke-width="1.2" stroke-linecap="round"/>' +

        /* ── 볼 홍조 (자연스러운 그라데이션 느낌) ── */
        '<path d="M22 54 Q28 50 34 54 Q34 60 28 62 Q22 60 22 54Z" fill="#FFB7C5" opacity="0.25"/>' +
        '<path d="M86 54 Q92 50 98 54 Q98 60 92 62 Q86 60 86 54Z" fill="#FFB7C5" opacity="0.25"/>' +

        /* ── 반짝이 ── */
        '<path d="M15 36 L16.5 40 L20 40.5 L17 43 L18 46.5 L15 44 L12 46.5 L13 43 L10 40.5 L13.5 40Z" fill="#FFD700" opacity="0.35">' +
        '<animate attributeName="opacity" values="0.4;0.1;0.4" dur="3.5s" repeatCount="indefinite"/>' +
        '</path>' +

        /* ── 감정 오버레이: 눈 (path로 표정 변경) ── */
        '<path class="dd-emo-eye-l" d="" fill="#3A3A3A" stroke="#3A3A3A" stroke-width="2" stroke-linecap="round" opacity="0"/>' +
        '<path class="dd-emo-eye-r" d="" fill="#3A3A3A" stroke="#3A3A3A" stroke-width="2" stroke-linecap="round" opacity="0"/>' +
        /* ── 감정 오버레이: 입 ── */
        '<path class="dd-emo-mouth" d="" fill="none" stroke="#3A3A3A" stroke-width="1.2" stroke-linecap="round" opacity="0"/>' +
        /* ── 감정 오버레이: 볼 (강도 조절) ── */
        '<path class="dd-emo-blush-l" d="M22 54 Q28 50 34 54 Q34 60 28 62 Q22 60 22 54Z" fill="#FFB7C5" opacity="0"/>' +
        '<path class="dd-emo-blush-r" d="M86 54 Q92 50 98 54 Q98 60 92 62 Q86 60 86 54Z" fill="#FFB7C5" opacity="0"/>' +
        /* ── 감정 오버레이: 반짝이 (흥분/기쁨) ── */
        '<g class="dd-emo-sparkle" style="display:none">' +
        '<path d="M105 30 L107 35 L112 36 L108 39 L109 44 L105 41 L101 44 L102 39 L98 36 L103 35Z" fill="#FFD700" opacity="0.7">' +
        '<animate attributeName="opacity" values="0.7;0.2;0.7" dur="1.5s" repeatCount="indefinite"/>' +
        '</path>' +
        '<path d="M10 70 L11.5 73 L15 73.5 L12.5 76 L13 79 L10 77.5 L7 79 L7.5 76 L5 73.5 L8.5 73Z" fill="#FFD700" opacity="0.5">' +
        '<animate attributeName="opacity" values="0.5;0.1;0.5" dur="2s" repeatCount="indefinite"/>' +
        '</path>' +
        '</g>' +

        '</svg>';
    }

    function buildPandaFace() {
        var c = getColor();
        return '<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">' +
        /* 귀 (머리 뒤, 자연스러운 형태) */
        '<path d="M14 30 Q8 12 18 6 Q28 2 32 16 Q32 24 26 30 Q20 32 14 30Z" fill="#3A3A3A"/>' +
        '<path d="M86 30 Q92 12 82 6 Q72 2 68 16 Q68 24 74 30 Q80 32 86 30Z" fill="#3A3A3A"/>' +
        '<path d="M17 26 Q14 16 22 11 Q28 8 30 18 Q29 24 24 27 Q20 28 17 26Z" fill="' + c.main + '" opacity="0.35"/>' +
        '<path d="M83 26 Q86 16 78 11 Q72 8 70 18 Q71 24 76 27 Q80 28 83 26Z" fill="' + c.main + '" opacity="0.35"/>' +
        /* 머리 */
        '<path d="M8 52 Q6 28 28 16 Q40 10 50 10 Q60 10 72 16 Q94 28 92 52 Q92 78 72 86 Q60 92 50 92 Q40 92 28 86 Q8 78 8 52Z" fill="#FAFAFA"/>' +
        /* 이마 털 */
        '<path d="M34 20 Q38 12 46 14 Q50 10 56 13 Q62 10 68 15 Q72 22 68 26 Q60 20 50 22 Q42 20 36 24 Q32 26 34 20Z" fill="#3A3A3A"/>' +
        /* 꽃 장식 */
        '<path d="M78 12 Q86 4 90 10 Q92 16 86 18 Q80 16 78 12Z" fill="#6B8E4E" opacity="0.75"/>' +
        '<path d="M76 16 Q72 12 76 10 Q80 8 82 12 Q84 8 88 10 Q92 12 88 16 Q84 20 82 16 Q80 20 76 16Z" fill="' + c.main + '"/>' +
        '<circle cx="82" cy="13" r="2" fill="' + c.dark + '"/>' +
        /* 눈 패치 */
        '<path d="M22 38 Q24 30 36 30 Q46 32 48 40 Q48 52 40 56 Q30 58 22 52 Q18 46 22 38Z" fill="#3A3A3A"/>' +
        '<path d="M78 38 Q76 30 64 30 Q54 32 52 40 Q52 52 60 56 Q70 58 78 52 Q82 46 78 38Z" fill="#3A3A3A"/>' +
        /* 눈 */
        '<circle cx="35" cy="42" r="8" fill="#fff"/><circle cx="65" cy="42" r="8" fill="#fff"/>' +
        '<circle cx="36" cy="43" r="5.5" fill="#1C1108"/><circle cx="66" cy="43" r="5.5" fill="#1C1108"/>' +
        '<circle cx="38" cy="40" r="2.5" fill="#fff"/><circle cx="68" cy="40" r="2.5" fill="#fff"/>' +
        '<circle cx="34" cy="45" r="1" fill="#fff" opacity="0.5"/><circle cx="64" cy="45" r="1" fill="#fff" opacity="0.5"/>' +
        /* 코 */
        '<path d="M46 54 Q50 50 54 54 Q52 58 50 59 Q48 58 46 54Z" fill="#3A3A3A"/>' +
        /* 입 */
        '<path d="M44 62 Q47 66 50 64 Q53 66 56 62" fill="none" stroke="#3A3A3A" stroke-width="1.2" stroke-linecap="round"/>' +
        /* 볼 홍조 */
        '<path d="M16 52 Q22 48 28 52 Q28 58 22 60 Q16 58 16 52Z" fill="#FFB7C5" opacity="0.25"/>' +
        '<path d="M72 52 Q78 48 84 52 Q84 58 78 60 Q72 58 72 52Z" fill="#FFB7C5" opacity="0.25"/>' +
        /* 옷깃 */
        '<path d="M26 82 Q36 90 50 88 Q64 90 74 82 Q66 86 50 84 Q34 86 26 82Z" fill="' + c.main + '" opacity="0.7"/>' +
        '</svg>';
    }

    var PANDA_BODY = buildPandaBody();
    var PANDA_FACE = buildPandaFace();

    function refreshCostume() {
        PANDA_BODY = buildPandaBody();
        PANDA_FACE = buildPandaFace();
        if (dom.character) {
            dom.character.innerHTML = PANDA_BODY + '<span class="dd-asst-nametag">叮叮</span>';
        }
    }

    var SEND_ICON =
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
        '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>';

    var MIC_ICON =
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
        '<path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>' +
        '<path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/>' +
        '<line x1="8" y1="23" x2="16" y2="23"/></svg>';

    var MIC_STOP_ICON =
        '<svg viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>';

    var SPEAKER_ICON =
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">' +
        '<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>' +
        '<path d="M19.07 4.93a10 10 0 0 1 0 14.14"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>';

    /* ============================================================
       Context / Page
       ============================================================ */
    var PAGE = document.body.getAttribute('data-dd-page') || 'landing';

    /* ============================================================
       Bubble Messages — 로테이션용
       ============================================================ */
    var BUBBLES = {
        landing: [
            '안녕! 나는 叮叮이야 🐼\n중국어 궁금한 거 물어봐!',
            '강좌를 클릭하면\n바로 공부할 수 있어!',
            '아래 쪽 강좌 카드를\n한번 둘러봐~ 📚',
            '뉴스레터도 재밌어!\n중국 트렌드를 중국어로 읽어봐~',
            '단어장에서 게임으로\n단어를 외울 수 있어! 🎮',
            '나를 클릭하면\n더 자세히 도와줄 수 있어! 💬'
        ],
        courses: [
            '어떤 강좌 들을지 고민이야?\n나한테 물어봐! 😊',
            '🌱 입문이면 "입문" 뱃지를 찾아봐!',
            '🎬 드라마 강좌는\n실전 회화에 짱이야!',
            '위의 탭으로 AI/드라마를\n필터할 수 있어!',
            '강의 제목을 클릭하면\n바로 공부 시작! 📖'
        ],
        lesson: [
            '열심히 공부하고 있구나!\n모르는 단어 물어봐~ 📖',
            '💡 "슬라이드" 탭으로\n핵심만 빠르게 복습해봐!',
            '🔊 "오디오북"에서\n발음도 들어봐!',
            '퀴즈 도전해봐!\n3종류 문제가 준비돼 있어 🧩',
            '📖 핵심 표현 카드에서\n단어를 저장할 수 있어!',
            '왼쪽 아래 💬 버튼으로\nAI 튜터 叮叮과 대화해봐!',
            '스토리북 탭도 재밌어!\n4컷 만화로 복습~ 📚',
            '오늘도 화이팅! 加油! 💪'
        ],
        stories: [
            '어떤 스토리를 해볼까? 🎮\n카드를 클릭하면 시작!',
            '선택에 따라\n이야기가 달라져!',
            '난이도 뱃지를 확인해봐!\n입문부터 고급까지 있어 📚',
            '모르는 게 있으면\n나한테 물어봐~ 🐼'
        ],
        story: [
            '스토리 재밌지? 🎮\n도움 필요하면 클릭해!',
            '선택에 따라\n이야기가 달라져!',
            '밑줄 친 단어를 클릭하면\n뜻을 볼 수 있어! 💡',
            '모든 엔딩을 모아봐!\n숨겨진 이야기가 있을지도? 🗺️'
        ],
        newsletters: [
            '뉴스레터를 읽으면\n독해력이 쑥쑥! 📰',
            '카드를 클릭해서\n전문을 읽어봐!',
            '모르는 단어는\n나한테 물어봐~ 🐼'
        ],
        newsletter: [
            '뉴스레터 재밌지?\n궁금한 표현 물어봐! 📖',
            '중국어 표현을 단어장에\n저장할 수 있어!',
            '다 읽으면\n다른 뉴스레터도 도전! 📰'
        ],
        vocabulary: [
            '단어 공부 화이팅! 💪',
            '플래시카드로\n앞뒤 뒤집기 암기해봐! 🃏',
            '미니게임 4종으로\n재밌게 복습해봐! 🎮',
            '매일 5분이면\n단어가 쏙쏙 들어와!'
        ]
    };

    /* ============================================================
       Preset Responses
       ============================================================ */
    var PRESETS = {
        /* ── 사이트 소개 ── */
        site_intro:  'DingDong(딩동)은 AI 기반 중국어 학습 플랫폼이야!\n\nGemini AI가 강좌, 퀴즈, 오디오북, 스토리북까지\n자동으로 만들어주는 똑똑한 학습 사이트야.\n\n로그인 필요 없고 완전 무료야!\n중국어 공부, 여기서 다 할 수 있어!',
        site_who:    '이 사이트는 한국인 중국어 학습자를 위해 만들어졌어!\n\n입문부터 고급까지 모든 레벨을 지원하고,\nAI가 주제에 맞는 강좌를 자동 생성해줘.\n\n나는 叮叮! 네 학습을 도와줄 판다 도우미야!',
        site_free:   '네! DingDong은 완전 무료야!\n\n✅ 로그인 없이 바로 학습\n✅ 모든 강좌 무료 열람\n✅ 퀴즈, 오디오북, 스토리북 모두 무료\n\nAI 기능(역할극, 작문채점)만 Gemini API 키가 필요한데\n이것도 무료로 발급받을 수 있어!',
        site_login:  '로그인이 필요 없어!\n\n바로 강좌를 클릭해서 학습하면 돼.\n진도나 단어장은 브라우저에 자동 저장돼.\n\n다른 기기에서 보려면 같은 브라우저를 사용하면 돼!',

        /* ── 기능 상세 ── */
        features:   'DingDong 주요 기능 6가지!\n\n📚 AI 강좌 — 주제+난이도 맞춤 강의\n🎯 퀴즈 — 4지선다/빈칸/어순 3종\n🔊 오디오북 — 중국어 TTS 발음 듣기\n📖 스토리북 — 4컷 만화로 복습\n✍️ 작문 채점 — AI가 문법/어휘 피드백\n🎮 인터랙티브 스토리 — 선택형 중국어 게임',
        features_detail: '각 강의에는 7개 학습 탭이 있어!\n\n1. 학습 내용 — 핵심표현, 본문, 만화, 실전대화\n2. 슬라이드 — 5장 요약 카드\n3. 오디오북 — 중국어 발음 재생\n4. 스토리북 — 그림책 6쪽\n5. 퀴즈 — 6문제 실력 테스트\n6. 한자 — 따라쓰기 + 고사성어\n7. 단어장 — 플래시카드 + 미니게임\n\n+ 작문연습, 관련영상도 있어!',
        ai_explain:  'AI 기능을 쓰려면 Gemini API 키가 필요해!\n\n🔑 발급 방법:\n1. Google AI Studio 접속 (무료)\n2. "Create API Key" 클릭\n3. 키를 복사해서 강의 페이지 열쇠 버튼에 입력\n\n키는 네 브라우저에만 저장되니 안전해!',

        /* ── 강좌 안내 ── */
        recommend:  '강좌 추천이야!\n\n🌱 입문자: "입문" 뱃지 강좌 → 기초 회화부터\n📈 중급자: "중급" 강좌 → 실전 대화, 문법 심화\n🎵 노래 팬: 중국어 노래 학습 → 실제 가사 분석\n\n메뉴 "강좌 목록"에서 확인해봐!\n"강좌 목록으로 이동해줘" 라고 해도 돼!',
        course_what: 'DingDong 강좌는 AI가 자동 생성한 중국어 수업이야!\n\n각 강좌에는 여러 개의 강의가 있고,\n강의마다 학습 내용, 슬라이드, 오디오북,\n스토리북, 퀴즈가 포함돼 있어.\n\n난이도도 입문/초급/중급/고급으로 나뉘어 있어!',
        course_how:  '강좌를 찾는 방법이야!\n\n1. 상단 메뉴에서 "강좌 목록" 클릭\n2. AI 강좌 / 노래 탭으로 필터\n3. 난이도 뱃지 확인 (🌱입문 📈중급 등)\n4. 강의 제목 클릭하면 바로 학습 시작!\n\n아니면 "강좌 목록으로 이동해줘" 라고 말해봐!',
        beginner:   '입문자 팁! 🌱\n\n1. "입문" 뱃지가 달린 강좌 선택\n2. "학습 내용" 탭 먼저 읽기\n3. "슬라이드"로 핵심 복습\n4. "오디오북"으로 발음 익히기\n5. "퀴즈"로 배운 내용 확인!\n\n하루 1강의씩 꾸준히 하는 게 좋아!',
        song_course: '중국어 노래 학습은 실전 중국어의 지름길! 🎵\n\n실제 중국 노래 가사를 AI가 분석해서\n생활 중국어를 자연스럽게 배울 수 있어.\n가사 싱크로 따라 부르며 표현도 익혀봐!\n\n강좌 목록에서 🎵 뱃지를 찾아봐!',
        level_info:  '난이도별 가이드야!\n\n🌱 입문: 기초 인사, 숫자, 자기소개\n📗 초급: 일상 회화, 쇼핑, 식당\n📈 중급: 비즈니스, 뉴스, 의견 표현\n📕 고급: 성어, 문학, 시사 토론\n\n네 수준에 맞는 강좌부터 시작해봐!',

        /* ── 학습 순서/팁 ── */
        start:      '시작 방법은 아주 간단해!\n\n1️⃣ 메뉴에서 "강좌 목록" 클릭\n2️⃣ 마음에 드는 강좌 선택\n3️⃣ 강의 클릭하면 바로 학습!\n\n✅ 로그인 필요 없음\n✅ 완전 무료\n✅ 모바일에서도 잘 돼!',
        order:      '추천 학습 순서 📋\n\n1. 학습 내용 → 본문 읽기\n2. 슬라이드 → 핵심 정리\n3. 오디오북 → 발음 듣기\n4. 스토리북 → 만화로 복습\n5. 퀴즈 → 실력 테스트\n\n다 끝나면 단어장에 모르는 단어 저장!',
        summary:    '이 강의 핵심을 보려면:\n\n📊 "슬라이드" 탭을 클릭해!\n→ 핵심 단어와 예문이 정리되어 있어.\n\n더 자세한 건 "학습 내용" 탭에서 확인!',

        /* ── 각 기능별 상세 ── */
        word_help:  '단어 도우미 사용법 💡\n\n1. 궁금한 중국어를 채팅에 입력해\n2. 핵심 표현 카드의 📖 아이콘으로 저장\n3. "단어장" 메뉴에서 복습\n\nAPI 키 설정하면 AI가 어떤 단어든 설명해줘!',
        quiz_tip:   '퀴즈 팁 🧩\n\n• 4지선다 → 소거법으로 오답 먼저 제외\n• 빈칸 채우기 → 병음만 입력해도 OK\n• 어순 배열 → 주어-동사-목적어 기억!\n\n각 강의에 6문제씩 있어. 틀려도 다시 도전! 💪',
        pronunciation: '발음 연습 팁 🔊\n\n• "오디오북" 탭에서 문장별 재생\n• 핵심 표현 카드의 🔊 버튼 활용\n• 따라 읽으면서 성조에 집중!\n\n중국어는 성조가 생명! 매일 조금씩 연습해봐.',
        audiobook_info: '오디오북은 중국어 본문을 TTS로 읽어줘! 🔊\n\n• 중국어 발음만 읽어줘 (한국어는 안 읽어)\n• 재생/정지 버튼으로 자유롭게 컨트롤\n• 핵심 표현 카드에서도 개별 발음 듣기 가능\n\n"오디오북" 탭에서 확인해봐!',
        slide_info:  '슬라이드는 강의 핵심을 5장으로 정리한 카드야! 📊\n\n• 각 슬라이드에 핵심 단어 3-4개\n• 예문 2-3개씩 포함\n• 좌우 화살표로 넘기면서 학습\n• 인쇄도 가능해!',
        storybook_info: '스토리북은 4컷 만화로 학습 내용을 복습하는 거야! 📖\n\n• AI가 그린 일러스트 4컷\n• 중국어 대사 + 한국어 번역\n• 재미있게 문장 구조를 익힐 수 있어!',
        writing_info: '작문 연습은 AI가 네 중국어 문장을 채점해줘! ✍️\n\n• 핵심 표현을 활용해서 문장 작성\n• 문법, 어휘, 자연스러움 점수 제공\n• API 키 필요 (무료 발급 가능)\n\n강의 "학습 내용" 탭 아래쪽에 있어!',

        /* ── 스토리 ── */
        story_tip:  '인터랙티브 스토리는 선택형 중국어 게임이야! 🎮\n\n• 네가 선택하면 이야기가 달라져\n• 밑줄 친 단어 클릭하면 뜻이 나와\n• 스피커 버튼으로 중국어 발음 듣기\n• 여러 번 플레이해서 모든 엔딩 모아봐!',
        ending_hint:'엔딩 힌트 🗺️\n\n• 보통 3~4개 엔딩이 있어\n• 다른 선택지를 골라보면 새 이야기 전개\n• 모든 엔딩 모으면 더 많은 단어를 배울 수 있어!',
        story_what:  '인터랙티브 스토리는 선택에 따라 이야기가\n달라지는 중국어 학습 게임이야! 🎮\n\n각 스토리마다 다른 주제와 어휘가 있고,\n분기마다 새로운 중국어 표현을 배울 수 있어.\n\n메뉴에서 "AI 스토리"를 클릭해봐!',

        /* ── 뉴스레터 ── */
        news_intro: '뉴스레터는 중국 트렌드를 중국어로 읽는 코너야! 📰\n\n중국 대중문화, 사회 이슈, 트렌드를\nAI가 학습용으로 작성한 기사야.\n핵심 단어 정리도 포함돼 있어!\n\n메뉴에서 "뉴스레터"를 클릭해봐!',
        reading_tip:'읽기 팁 💡\n\n1. 제목+이미지로 주제 파악\n2. 모르는 단어 있어도 끝까지 읽기\n3. 두 번째에 모르는 단어 체크\n4. 핵심 표현을 단어장에 저장!',

        /* ── 단어장 ── */
        vocab_intro: 'AI 단어장은 네가 저장한 단어를 관리하는 곳이야! 📝\n\n• 플래시카드로 앞뒤 뒤집기 암기\n• 4종 미니게임 (뒤집기/받아쓰기/스피드퀴즈/빈칸)\n• HSK 급수 표시\n• 에빙하우스 복습 일정 자동 관리\n\n메뉴에서 "단어장"을 클릭해봐!',
        game_rec:   '미니게임 추천 🎮\n\n• 뒤집기 — 한자↔뜻 매칭\n• 받아쓰기 — 병음 듣고 한자 입력\n• 스피드 퀴즈 — 제한시간 의미 맞추기\n• 빈칸 채우기 — 문장에서 단어 완성',
        study_method:'AI 단어장 학습법 📊\n\n1. 강의에서 모르는 단어 저장\n2. 플래시카드로 1차 암기\n3. 미니게임으로 복습\n4. 에빙하우스 일정 따르기\n\n핵심: 매일 조금씩, 꾸준히! 📅',
        review:     '복습 일정 (에빙하우스) 🔄\n\n• 1일 후 — 1차 복습\n• 3일 후 — 2차 복습\n• 7일 후 — 3차 복습\n• 14일 후 — 4차 복습\n• 30일 후 — 장기 기억 완성!',

        /* ── 叮叮 & 역할극 ── */
        dingding_who: '나는 叮叮(딩딩)! 네 학습을 도와주는 판다 도우미야!\n\n할 수 있는 것들:\n🧭 페이지 이동 — "강좌 목록으로 가줘"\n🎭 역할극 — 중국어 실전 대화 연습\n💡 학습 안내 — 기능 소개, 강좌 추천\n👗 옷 바꾸기 — 내 의상 커스텀\n🔊 음성 대화 — 마이크 버튼으로 말하기\n\n뭐든 물어봐!',
        roleplay_intro: '역할극은 실전 중국어 대화 연습이야! 🎭\n\n🍜 식당 주문 — 중국 식당에서 메뉴 주문\n🚕 택시 탑승 — 택시 타고 목적지 대화\n🛍️ 쇼핑 흥정 — 시장에서 가격 흥정\n🏨 호텔 체크인 — 호텔 숙박 대화\n🏥 병원 진료 — 증상 설명하고 진료\n🏦 은행 업무 — 환전, 계좌 문의\n\n"🎭 역할극" 버튼을 눌러봐!',

        /* ── 기타 유틸 ── */
        coach:      '지금 이 페이지의 기능을 하나씩 안내해줄게!\n잠시만 기다려~ 🐼',
        navigate:   '어디로 갈까? 페이지를 골라줘! 🧭\n\n🏠 홈 — "홈으로 이동해줘"\n📚 강좌 목록 — "강좌 목록 보여줘"\n📖 스토리 — "스토리 페이지로 가줘"\n📰 뉴스레터 — "뉴스레터 열어줘"\n📝 단어장 — "단어장으로 이동해줘"\n\n원하는 곳을 말하면 바로 이동시켜줄게!',
        mobile_ok:  '모바일에서도 잘 돼!\n\n스마트폰/태블릿 브라우저에서\n그냥 주소 입력하면 바로 사용 가능.\n앱 설치 필요 없어!',
        progress_info: '학습 진도는 자동으로 추적돼! 📈\n\n• 각 탭을 완료하면 진도율이 올라가\n• 퀴즈 점수도 기록돼\n• 에빙하우스 복습 일정이 자동 생성\n\n강의 오른쪽 위에 진도 위젯이 있어!',
        song_info:  '중국어 노래 학습 강좌가 있어! 🎵\n\n실제 중국 노래 뮤직비디오를 보면서\n가사가 재생 시간에 맞춰 하이라이트돼.\n\n가사 줄을 누르면 그 구간부터 다시 듣고,\n핵심 어휘를 누르면 뜻 팝업 + 단어장 저장!\n"🎤 빈칸 모드"로 받아쓰기 연습도 가능해.',
        culture_info: '문화노트는 중국 문화 배경을 알려주는 코너야!\n\n• 표현의 문화적 맥락\n• 한중 비교\n• 재미있는 사실 3가지\n• 관련 중국어 표현\n\n강의 "학습 내용" 탭에서 볼 수 있어!'
    };

    /** 현재 페이지 DOM에서 동적으로 강좌/강의 정보 수집 */
    function getDynamicSiteInfo() {
        var info = '';
        var stats = document.querySelectorAll('.dd-stat-num, .dd-stat-value');
        var labels = document.querySelectorAll('.dd-stat-label');
        if (stats.length && labels.length) {
            var pairs = [];
            for (var i = 0; i < Math.min(stats.length, labels.length); i++) {
                pairs.push(labels[i].textContent.trim() + ' ' + stats[i].textContent.trim());
            }
            info += '현재 사이트: ' + pairs.join(', ') + '\n';
        }
        var courseNames = [];
        document.querySelectorAll('.dd-recent-card h3, .dd-course-block-header h2, .dd-course-title').forEach(function (c) {
            var name = c.textContent.trim();
            if (name && courseNames.indexOf(name) === -1) courseNames.push(name);
        });
        if (courseNames.length) info += '\n현재 개설된 강좌:\n' + courseNames.map(function (n, i) { return (i + 1) + '. ' + n; }).join('\n');
        return info;
    }

    /* Quick Actions per page — gamification actions added */
    var GAMI_ACTIONS = [
        { label: '🎯 오늘의 도전',  id: 'daily_challenge' },
        { label: '⚡ 미니퀴즈',    id: 'mini_quiz' },
        { label: '🏆 레전드 도전',  id: 'legendary' },
        { label: '🛒 XP 상점',    id: 'xp_shop' },
        { label: '📊 내 통계',    id: 'my_stats' }
    ];

    var ACTIONS = {
        landing: [
            { label: '📚 추천 강좌',  id: 'recommend' },
            { label: '✨ 기능 소개',  id: 'features' },
            { label: '🎭 역할극',    id: 'roleplay' }
        ].concat(GAMI_ACTIONS).concat([
            { label: '🧭 페이지 이동', id: 'navigate' },
            { label: '👗 옷 바꾸기',  id: 'costume' }
        ]),
        courses: [
            { label: '🌱 입문 추천',  id: 'beginner' },
            { label: '🎵 노래 강좌', id: 'song_course' },
            { label: '🎭 역할극',    id: 'roleplay' }
        ].concat(GAMI_ACTIONS).concat([
            { label: '🧭 페이지 이동', id: 'navigate' },
            { label: '👗 옷 바꾸기',  id: 'costume' }
        ]),
        lesson: [
            { label: '📝 핵심 요약',  id: 'summary' },
            { label: '💡 단어 도우미', id: 'word_help' },
            { label: '🎭 역할극',    id: 'roleplay' }
        ].concat(GAMI_ACTIONS).concat([
            { label: '🧭 페이지 이동', id: 'navigate' },
            { label: '👗 옷 바꾸기',  id: 'costume' }
        ]),
        stories: [
            { label: '🎮 스토리 팁',  id: 'story_tip' },
            { label: '🎭 역할극',    id: 'roleplay' }
        ].concat(GAMI_ACTIONS).concat([
            { label: '🧭 페이지 이동', id: 'navigate' },
            { label: '👗 옷 바꾸기',  id: 'costume' }
        ]),
        story: [
            { label: '🎮 스토리 팁',  id: 'story_tip' },
            { label: '📖 단어 도우미', id: 'word_help' },
            { label: '🎭 역할극',    id: 'roleplay' }
        ].concat(GAMI_ACTIONS).concat([
            { label: '👗 옷 바꾸기',  id: 'costume' }
        ]),
        newsletters: [
            { label: '📰 뉴스레터 소개', id: 'news_intro' },
            { label: '🎭 역할극',       id: 'roleplay' }
        ].concat(GAMI_ACTIONS).concat([
            { label: '🧭 페이지 이동',   id: 'navigate' },
            { label: '👗 옷 바꾸기',     id: 'costume' }
        ]),
        newsletter: [
            { label: '📰 소개',    id: 'news_intro' },
            { label: '🎭 역할극',  id: 'roleplay' }
        ].concat(GAMI_ACTIONS).concat([
            { label: '🧭 페이지 이동', id: 'navigate' },
            { label: '👗 옷 바꾸기', id: 'costume' }
        ]),
        vocabulary: [
            { label: '🎮 게임 추천', id: 'game_rec' },
            { label: '🎭 역할극',   id: 'roleplay' }
        ].concat(GAMI_ACTIONS).concat([
            { label: '🧭 페이지 이동', id: 'navigate' },
            { label: '👗 옷 바꾸기',  id: 'costume' }
        ])
    };

    /* Gemini models */
    /* Gemini 모델 fallback 체인 — 공용 상수 (dd-shared.js) */
    var MODELS = window.DDGeminiModels || ['gemini-2.5-flash'];

    /* ============================================================
       State
       ============================================================ */
    /* Speech Recognition support check */
    var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition || null;
    var hasSpeech = !!SpeechRecognition;

    var state = {
        isOpen: false,
        bubbleTimer: null,
        bubbleIdx: 0,
        rotationTimer: null,
        apiKey: '',
        hasApiKey: false,
        /* Voice */
        isListening: false,
        recognition: null,
        ttsEnabled: true
    };
    var dom = {};

    /* ============================================================
       Build DOM
       ============================================================ */
    function build() {
        /* Character container */
        var character = el('div', 'dd-asst-character');
        character.innerHTML = PANDA_BODY + '<span class="dd-asst-nametag">叮叮</span>';
        character.title = '叮叮 학습도우미 — "딩딩아"라고 부르거나 클릭하면 음성으로 대화!';
        document.body.appendChild(character);
        dom.character = character;

        /* Speech bubble */
        var bubble = el('div', 'dd-asst-bubble');
        bubble.style.display = 'none';
        document.body.appendChild(bubble);
        dom.bubble = bubble;

        /* Panel placeholder */
        dom.panelWrap = null;

        /* Click → open voice panel first; chat is one tap away */
        character.addEventListener('click', openVoicePanel);
    }

    /* ============================================================
       Speech Bubble — rotation system
       ============================================================ */
    var PAUSE_ICON = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="6" y1="4" x2="6" y2="20"/><line x1="18" y1="4" x2="18" y2="20"/></svg>';
    var PLAY_ICON = '<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><polygon points="5,3 19,12 5,21"/></svg>';
    var CLOSE_BUBBLE_ICON = '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';

    var bubblePaused = false;

    function showBubble(text) {
        dom.bubble.innerHTML =
            '<div class="dd-asst-bubble-controls">' +
                '<button class="dd-asst-bubble-btn dd-asst-bubble-pause" title="일시정지">' + PAUSE_ICON + '</button>' +
                '<button class="dd-asst-bubble-btn dd-asst-bubble-dismiss" title="닫기">' + CLOSE_BUBBLE_ICON + '</button>' +
            '</div>' +
            '<span class="dd-asst-bubble-name">叮叮</span>' + escHtml(text);
        dom.bubble.style.display = '';
        dom.bubble.classList.remove('is-hidden');

        var pauseBtn = dom.bubble.querySelector('.dd-asst-bubble-pause');
        var dismissBtn = dom.bubble.querySelector('.dd-asst-bubble-dismiss');

        pauseBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            bubblePaused = !bubblePaused;
            pauseBtn.innerHTML = bubblePaused ? PLAY_ICON : PAUSE_ICON;
            pauseBtn.title = bubblePaused ? '재개' : '일시정지';
            pauseBtn.classList.toggle('is-paused', bubblePaused);
            if (bubblePaused) {
                clearTimeout(state.bubbleTimer);
                clearInterval(state.rotationTimer);
            } else {
                state.bubbleTimer = setTimeout(hideBubble, 5000);
                restartRotationTimer();
            }
        });

        dismissBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            hideBubble();
        });

        clearTimeout(state.bubbleTimer);
        if (!bubblePaused) {
            state.bubbleTimer = setTimeout(hideBubble, 5000);
        }
    }

    function hideBubble() {
        if (dom.bubble.style.display === 'none') return;
        dom.bubble.classList.add('is-hidden');
        setTimeout(function () { dom.bubble.style.display = 'none'; }, 300);
    }

    function restartRotationTimer() {
        clearInterval(state.rotationTimer);
        var pool = BUBBLES[PAGE] || BUBBLES.landing;
        var smartShown = false;
        state.rotationTimer = setInterval(function () {
            if (state.isOpen || bubblePaused) return;
            if (!smartShown) {
                var smart = getSmartBubble();
                if (smart) { showBubble(smart); smartShown = true; return; }
            }
            smartShown = false;
            state.bubbleIdx = (state.bubbleIdx + 1) % pool.length;
            showBubble(pool[state.bubbleIdx]);
        }, 20000);
    }

    function startBubbleRotation() {
        var pool = BUBBLES[PAGE] || BUBBLES.landing;
        state.bubbleIdx = 0;

        /* First greeting — try smart recommendation first */
        setTimeout(function () {
            if (state.isOpen) return;
            var smart = getSmartBubble();
            showBubble(smart || pool[0]);
        }, 2000);

        /* Rotation every 20s — mix in smart recommendations */
        var smartShown = false;
        state.rotationTimer = setInterval(function () {
            if (state.isOpen) return;
            if (!smartShown) {
                var smart = getSmartBubble();
                if (smart) { showBubble(smart); smartShown = true; return; }
            }
            smartShown = false;
            state.bubbleIdx = (state.bubbleIdx + 1) % pool.length;
            showBubble(pool[state.bubbleIdx]);
        }, 20000);
    }

    /* ============================================================
       Wake Word — "딩딩아" 라고 부르면 손 안 쓰고 음성 대화 시작
       항상 백그라운드로 듣다가 호출어가 들리면 음성 패널을 열고
       딩딩이 음성으로 응답한 뒤 바로 마이크를 켠다.
       ============================================================ */
    var wake = { recog: null, active: false, starting: false, restartTimer: null, blocked: false, suspended: 0 };

    /* 음성 인식이 "딩딩아"를 자주 다르게 받아쓰므로 변형을 폭넓게 매칭 */
    var WAKE_PATTERNS = [
        '딩딩', '띵띵', '딩딩아', '띵띵아', '딩딩아아', '딩동아',
        '딩 딩', '띵 띵', '딩딍', '딩딘', '딩디', '징징아', '띵띠'
    ];

    var WAKE_GREETINGS = [
        '왜 불렀어? 필요한 게 있니?',
        '응? 불렀어? 뭐 도와줄까?',
        '여기 있어! 무엇을 도와줄까?',
        '딩딩 여기 있어~ 뭐가 필요해?'
    ];

    function wakeEnabled() {
        return hasSpeech && localStorage.getItem('dd_wake_word') !== 'off';
    }

    function matchesWake(transcript) {
        var t = (transcript || '').toLowerCase().replace(/\s+/g, '');
        for (var i = 0; i < WAKE_PATTERNS.length; i++) {
            if (t.indexOf(WAKE_PATTERNS[i].replace(/\s+/g, '')) !== -1) return true;
        }
        return false;
    }

    function startWakeListening() {
        if (!hasSpeech || !wakeEnabled() || wake.blocked) return;
        if (wake.suspended > 0) return; /* 발음 연습 등 외부 음성 인식이 마이크를 쓰는 중 */
        if (wake.active || wake.starting) return;
        /* 다른 인식기가 마이크를 쓰는 동안에는 대기 */
        if (voicePanel.active || state.isOpen || welcome.listening || welcome.speaking) return;
        if (dom.welcomeScreen) return; /* 환영 화면이 떠 있으면 환영 마이크가 담당 */

        var recog = new SpeechRecognition();
        recog.lang = 'ko-KR';
        recog.continuous = true;
        recog.interimResults = true;
        recog.maxAlternatives = 1;
        wake.recog = recog;
        wake.starting = true;

        recog.onstart = function () { wake.starting = false; wake.active = true; setWakeIndicator(true); };
        recog.onresult = function (e) {
            var transcript = '';
            for (var i = e.resultIndex; i < e.results.length; i++) {
                transcript += e.results[i][0].transcript;
            }
            if (matchesWake(transcript)) triggerWake();
        };
        recog.onerror = function (e) {
            wake.active = false; wake.starting = false; setWakeIndicator(false);
            /* 권한 거부는 무한 재시도 루프를 막기 위해 비활성화 */
            if (e.error === 'not-allowed' || e.error === 'service-not-allowed') { wake.blocked = true; return; }
            /* abort는 우리가 의도적으로 멈춘 것 — 재시작은 호출 측이 관리 */
            if (e.error === 'aborted') return;
            scheduleWakeRestart();
        };
        recog.onend = function () {
            wake.active = false; wake.starting = false; setWakeIndicator(false);
            scheduleWakeRestart();
        };
        try { recog.start(); }
        catch (e) { wake.starting = false; scheduleWakeRestart(); }
    }

    function stopWakeListening() {
        if (wake.restartTimer) { clearTimeout(wake.restartTimer); wake.restartTimer = null; }
        wake.active = false; wake.starting = false;
        setWakeIndicator(false);
        if (wake.recog) { try { wake.recog.abort(); } catch (e) {} wake.recog = null; }
    }

    function scheduleWakeRestart() {
        if (!wakeEnabled() || wake.blocked || wake.suspended > 0) return;
        if (wake.restartTimer) clearTimeout(wake.restartTimer);
        wake.restartTimer = setTimeout(function () {
            wake.restartTimer = null;
            startWakeListening();
        }, 700);
    }

    function setWakeIndicator(on) {
        if (dom.character) dom.character.classList.toggle('is-wake-listening', !!on);
    }

    /* 외부 음성 인식(발음 연습 등)이 마이크를 쓰는 동안 호출어 대기를 양보.
       동시에 둘 이상 켜지면 브라우저가 한쪽을 'aborted' 시키므로 중첩 카운트로 관리. */
    function suspendWake() {
        wake.suspended = (wake.suspended || 0) + 1;
        stopWakeListening();
    }
    function resumeWake() {
        wake.suspended = Math.max(0, (wake.suspended || 0) - 1);
        if (wake.suspended === 0) scheduleWakeRestart();
    }
    /* 다른 모듈(dd-pronunciation.js 등)이 마이크를 점유하기 직전/직후에 호출 */
    window.DDWake = { suspend: suspendWake, resume: resumeWake };

    /* 호출어 감지 → 패널 열고 인사 후 자동으로 듣기 시작 */
    function triggerWake() {
        stopWakeListening();
        if (window.speechSynthesis) window.speechSynthesis.cancel();
        var greet = WAKE_GREETINGS[Math.floor(Math.random() * WAKE_GREETINGS.length)];

        if (voicePanel.active && dom.voicePanel) {
            wakeGreetAndListen(
                dom.voicePanel.querySelector('.dd-voice-bubble-text'),
                dom.voicePanel.querySelector('#dd-voice-state'),
                dom.voicePanel.querySelector('#dd-voice-mic'),
                greet
            );
            return;
        }
        openVoicePanel({ onReady: function (refs) {
            wakeGreetAndListen(refs.bubble, refs.stateLbl, refs.micBtn, greet);
        } });
    }

    function wakeGreetAndListen(bubble, stateLbl, micBtn, greet) {
        voicePanel.handsFree = true;
        voicePanel.emptyCount = 0;
        if (bubble) bubble.textContent = greet;
        if (stateLbl) stateLbl.textContent = '말하는 중...';
        speakKoreanThen(greet, function () {
            if (micBtn && voicePanel.active && !voicePanel.listening) {
                toggleVoicePanelMic(bubble, stateLbl, micBtn);
            }
        });
    }

    /* 한국어 한 문장을 말하고 끝나면 콜백 (인사/이어듣기용) */
    function speakKoreanThen(text, onEnd) {
        if (!state.ttsEnabled || !window.speechSynthesis) { if (onEnd) setTimeout(onEnd, 300); return; }
        var clean = (text || '').replace(/叮叮/g, '딩딩').replace(/DingDong/gi, '딩동');
        window.speechSynthesis.cancel();
        var u = new SpeechSynthesisUtterance(clean);
        u.lang = 'ko-KR'; u.rate = 1.0; u.pitch = 1.0; u.volume = 0.9;
        pickFriendlyVoice(u, 'ko');
        var done = false;
        var fin = function () { if (done) return; done = true; if (onEnd) onEnd(); };
        u.onend = fin; u.onerror = fin;
        window.speechSynthesis.speak(u);
        /* TTS onend가 안 올 때를 대비한 안전장치 */
        setTimeout(fin, Math.max(1600, clean.length * 130));
    }

    /* ============================================================
       Voice Panel — 음성 우선 인터페이스 (클릭 시 기본)
       ============================================================ */
    var voicePanel = { active: false, recog: null, listening: false, transcript: '', handsFree: false };

    function openVoicePanel(opts) {
        if (state.isOpen) return;
        if (voicePanel.active) return;
        stopWakeListening(); /* 패널이 마이크를 쓰는 동안 호출어 대기는 멈춤 */
        voicePanel.active = true;
        voicePanel.handsFree = false;
        hideBubble();
        dom.character.classList.add('is-open');

        var c = getColor();
        var panel = el('div', 'dd-asst-voice-panel');
        panel.innerHTML =
            '<div class="dd-voice-header" style="background:linear-gradient(135deg,' + c.light + ' 0%,#fff 100%);">' +
                '<div class="dd-voice-avatar" style="border-color:' + c.main + '55;">' + PANDA_FACE + '</div>' +
                '<div class="dd-voice-title">' +
                    '<div class="dd-voice-name">叮叮 <span class="dd-voice-tag">음성 대화</span></div>' +
                    '<div class="dd-voice-sub">마이크를 누르고 한국어로 말해봐!</div>' +
                '</div>' +
                '<button class="dd-voice-close" title="닫기">&times;</button>' +
            '</div>' +
            '<div class="dd-voice-stage">' +
                '<div class="dd-voice-bubble" id="dd-voice-bubble">' +
                    '<span class="dd-voice-bubble-text">안녕! 마이크 버튼을 누르면 대화를 시작할 수 있어. 한국어로 말하면 한국어로 안내하고, 중국어 예문은 원어로 들려줄게.</span>' +
                '</div>' +
                '<button class="dd-voice-mic" id="dd-voice-mic" style="border-color:' + c.main + ';color:' + c.main + ';" title="음성 입력 시작/중지">' +
                    MIC_ICON +
                '</button>' +
                '<div class="dd-voice-state" id="dd-voice-state">탭하여 말하기</div>' +
            '</div>' +
            '<div class="dd-voice-actions">' +
                '<button class="dd-voice-action" data-vact="chat" title="채팅 창으로 전환">' +
                    '<span class="dd-voice-action-ic">💬</span><span>채팅</span>' +
                '</button>' +
                '<button class="dd-voice-action" data-vact="costume" title="의상 변경">' +
                    '<span class="dd-voice-action-ic">🧥</span><span>의상</span>' +
                '</button>' +
                '<button class="dd-voice-action" data-vact="mute" title="자동 재생 토글" id="dd-voice-mute">' +
                    '<span class="dd-voice-action-ic">' + (state.ttsEnabled ? '🔊' : '🔇') + '</span><span>' + (state.ttsEnabled ? '소리 켬' : '소리 끔') + '</span>' +
                '</button>' +
                '<button class="dd-voice-action" data-vact="stop" title="현재 음성 멈춤">' +
                    '<span class="dd-voice-action-ic">⏹</span><span>멈춤</span>' +
                '</button>' +
                '<button class="dd-voice-action" data-vact="wake" id="dd-voice-wake" title="&quot;딩딩아&quot; 호출 켜기/끄기">' +
                    '<span class="dd-voice-action-ic">' + (wakeEnabled() ? '👂' : '🚫') + '</span><span>' + (wakeEnabled() ? '호출 켬' : '호출 끔') + '</span>' +
                '</button>' +
            '</div>';
        document.body.appendChild(panel);
        dom.voicePanel = panel;

        var bubble = panel.querySelector('.dd-voice-bubble-text');
        var stateLbl = panel.querySelector('#dd-voice-state');
        var micBtn = panel.querySelector('#dd-voice-mic');

        /* Mirror addMsg responses into the bubble so the user sees what's spoken */
        window.__ddVoiceMirror = function (text) {
            if (!bubble) return;
            bubble.textContent = text;
        };

        panel.querySelector('.dd-voice-close').addEventListener('click', closeVoicePanel);
        micBtn.addEventListener('click', function () { toggleVoicePanelMic(bubble, stateLbl, micBtn); });

        panel.querySelectorAll('[data-vact]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var act = btn.getAttribute('data-vact');
                if (act === 'chat') { closeVoicePanel(); openPanel(); }
                else if (act === 'costume') { closeVoicePanel(); openPanel(); setTimeout(showCostumePicker, 300); }
                else if (act === 'mute') { toggleVoiceMute(btn); }
                else if (act === 'stop') { if (window.speechSynthesis) window.speechSynthesis.cancel(); stateLbl.textContent = '멈춤'; }
                else if (act === 'wake') { toggleWakeWord(btn, stateLbl); }
            });
        });

        /* First-visit coachmark for the voice panel */
        if (!localStorage.getItem('dd_voice_coach')) {
            setTimeout(function () { showVoiceCoachmark(panel); }, 500);
        }

        /* 호출어로 열렸을 때: 인사 후 자동으로 마이크 켜기 */
        if (opts && typeof opts.onReady === 'function') {
            opts.onReady({ bubble: bubble, stateLbl: stateLbl, micBtn: micBtn });
        }
    }

    function closeVoicePanel() {
        if (!dom.voicePanel) return;
        dom.voicePanel.classList.add('is-closing');
        if (voicePanel.recog) { try { voicePanel.recog.stop(); } catch (e) {} voicePanel.recog = null; }
        if (window.speechSynthesis) window.speechSynthesis.cancel();
        window.__ddVoiceMirror = null;
        var p = dom.voicePanel;
        setTimeout(function () {
            if (p && p.parentNode) p.parentNode.removeChild(p);
            dom.voicePanel = null;
            voicePanel.active = false;
            voicePanel.listening = false;
            voicePanel.handsFree = false;
            dom.character.classList.remove('is-open');
            startWakeListening(); /* 패널 닫히면 다시 "딩딩아" 대기 */
        }, 220);
    }

    function toggleVoicePanelMic(bubble, stateLbl, micBtn) {
        if (!SpeechRecognition) {
            bubble.textContent = '이 브라우저는 음성 인식을 지원하지 않아. Chrome이나 Edge를 사용해줘!';
            return;
        }
        if (voicePanel.listening) {
            try { voicePanel.recog && voicePanel.recog.stop(); } catch (e) {}
            return;
        }
        var recog = new SpeechRecognition();
        recog.lang = 'ko-KR';
        recog.continuous = false;
        recog.interimResults = true;
        voicePanel.recog = recog;
        voicePanel.listening = true;

        micBtn.classList.add('is-listening');
        micBtn.innerHTML = MIC_STOP_ICON;
        stateLbl.textContent = '듣는 중...';
        bubble.textContent = '듣고 있어, 말해봐!';

        var finalText = '';
        recog.onresult = function (e) {
            var interim = '';
            for (var i = e.resultIndex; i < e.results.length; i++) {
                var r = e.results[i];
                if (r.isFinal) finalText += r[0].transcript;
                else interim += r[0].transcript;
            }
            bubble.textContent = (finalText + interim) || '...';
        };
        recog.onerror = function (ev) {
            stateLbl.textContent = '오류: ' + (ev.error || '알 수 없음');
            resetMic();
        };
        recog.onend = function () {
            resetMic();
            var said = (finalText || '').trim();
            if (!said) {
                stateLbl.textContent = '아무 말도 못 들었어. 다시 말해봐!';
                if (voicePanel.handsFree) {
                    voicePanel.emptyCount = (voicePanel.emptyCount || 0) + 1;
                    if (voicePanel.emptyCount <= 2) { rearmIfHandsFree(bubble, stateLbl); }
                    else { voicePanel.emptyCount = 0; stateLbl.textContent = '듣다가 멈췄어 — 마이크를 누르거나 "딩딩아"라고 불러줘!'; }
                }
                return;
            }
            voicePanel.emptyCount = 0;
            handleVoiceInput(said, bubble, stateLbl);
        };
        try { recog.start(); }
        catch (e) { stateLbl.textContent = '마이크 시작 실패'; resetMic(); }

        function resetMic() {
            voicePanel.listening = false;
            voicePanel.recog = null;
            micBtn.classList.remove('is-listening');
            micBtn.innerHTML = MIC_ICON;
        }
    }

    function handleVoiceInput(text, bubble, stateLbl) {
        bubble.textContent = '나: ' + text;
        stateLbl.textContent = '답하는 중...';

        /* 강의/강좌 직접 열기 (제목·유형 인식) — 프리셋보다 우선 */
        var lessonAct = tryOpenLesson(text);
        if (lessonAct) {
            setTimeout(function () {
                bubble.textContent = lessonAct.reply;
                stateLbl.textContent = lessonAct.href ? '이동할게!' : '답변 완료';
                speakBilingual(lessonAct.reply, lessonAct.href ? null : function () { rearmIfHandsFree(bubble, stateLbl); });
            }, 200);
            if (lessonAct.href) setTimeout(function () { window.location.href = lessonAct.href; }, 1500);
            return;
        }

        /* "학습 시작하기" / "강좌 보여줘" 등 → 강좌 목록으로 바로 이동 */
        if (isCourseStartIntent(text)) {
            var goReply = '좋아! 강좌 목록 페이지로 데려갈게! 🚀';
            setTimeout(function () {
                bubble.textContent = goReply;
                stateLbl.textContent = '이동할게!';
                speakBilingual(goReply, null);
            }, 200);
            setTimeout(function () { window.location.href = '/courses/'; }, 1500);
            return;
        }

        var reply = matchPattern(text.toLowerCase());
        var navHandled = false;

        /* Check navigation */
        if (!reply) {
            var t = text.toLowerCase();
            for (var i = 0; i < NAV_ROUTES.length; i++) {
                var route = NAV_ROUTES[i];
                for (var j = 0; j < route.keywords.length; j++) {
                    if (t.indexOf(route.keywords[j]) !== -1 && /(이동|가|열어|보여|페이지|넘어)/.test(t)) {
                        reply = '좋아! ' + route.label + ' 페이지로 이동할게!';
                        navHandled = true;
                        setTimeout(function () { window.location.href = route.path; }, 1200);
                        break;
                    }
                }
                if (navHandled) break;
            }
        }

        if (reply) {
            setTimeout(function () {
                bubble.textContent = reply;
                stateLbl.textContent = '답변 완료';
                /* 페이지 이동 중이면 이어듣기 하지 않음 */
                speakBilingual(reply, navHandled ? null : function () { rearmIfHandsFree(bubble, stateLbl); });
            }, 250);
            return;
        }

        /* No preset/nav match — fall back to AI if available */
        if (state.hasApiKey) {
            askGeminiVoicePanel(text, bubble, stateLbl);
        } else {
            var msg = '음, 그건 잘 모르겠어! 더 자세한 답변은 채팅에서 API 키를 설정하면 들을 수 있어. "강좌로 이동해줘" 같이 말하면 페이지도 이동할 수 있어!';
            setTimeout(function () {
                bubble.textContent = msg;
                stateLbl.textContent = '답변 완료';
                speakBilingual(msg, function () { rearmIfHandsFree(bubble, stateLbl); });
            }, 250);
        }
    }

    /* 호출어로 시작한 대화는 답변 후 자동으로 다시 듣기 → 손 안 쓰고 이어가기 */
    function rearmIfHandsFree(bubble, stateLbl) {
        if (!voicePanel.handsFree || !voicePanel.active) return;
        setTimeout(function () {
            if (!voicePanel.active || voicePanel.listening) return;
            var mb = dom.voicePanel && dom.voicePanel.querySelector('#dd-voice-mic');
            if (mb) toggleVoicePanelMic(bubble, stateLbl, mb);
        }, 500);
    }

    function askGeminiVoicePanel(question, bubble, stateLbl) {
        var url = 'https://generativelanguage.googleapis.com/v1beta/models/' + MODELS[0] + ':generateContent';
        var prompt = '너는 DingDong 중국어 학습 사이트의 판다 도우미 叮叮(딩딩)이야. ' +
                     '한국인 학습자를 친근한 반말로 도와줘. ' +
                     '답변은 2-3문장, 한국어로 말하고 필요하면 중국어 예문을 짧게 1개 포함해. ' +
                     '질문: ' + question;
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'x-goog-api-key': state.apiKey },
            body: JSON.stringify({ contents: [{ parts: [{ text: prompt }] }] })
        }).then(function (r) { return r.json(); }).then(function (d) {
            var txt = d && d.candidates && d.candidates[0] && d.candidates[0].content
                ? d.candidates[0].content.parts.map(function (p) { return p.text; }).join('').trim()
                : '음, 잘 모르겠어!';
            bubble.textContent = txt;
            stateLbl.textContent = '답변 완료';
            speakBilingual(txt, function () { rearmIfHandsFree(bubble, stateLbl); });
        }).catch(function () {
            var fallback = '잠깐, 지금 답을 못 가져왔어. 잠시 후 다시 시도해줘!';
            bubble.textContent = fallback;
            stateLbl.textContent = '오류';
            speakBilingual(fallback, function () { rearmIfHandsFree(bubble, stateLbl); });
        });
    }

    function toggleVoiceMute(btn) {
        state.ttsEnabled = !state.ttsEnabled;
        var ic = btn.querySelector('.dd-voice-action-ic');
        var lbl = btn.querySelector('span:last-child');
        if (ic) ic.textContent = state.ttsEnabled ? '🔊' : '🔇';
        if (lbl) lbl.textContent = state.ttsEnabled ? '소리 켬' : '소리 끔';
        if (!state.ttsEnabled && window.speechSynthesis) window.speechSynthesis.cancel();
    }

    function toggleWakeWord(btn, stateLbl) {
        var on = !wakeEnabled();
        localStorage.setItem('dd_wake_word', on ? 'on' : 'off');
        wake.blocked = false;
        var ic = btn.querySelector('.dd-voice-action-ic');
        var lbl = btn.querySelector('span:last-child');
        if (ic) ic.textContent = on ? '👂' : '🚫';
        if (lbl) lbl.textContent = on ? '호출 켬' : '호출 끔';
        if (stateLbl) stateLbl.textContent = on ? '"딩딩아"라고 부르면 내가 대답할게!' : '호출 대기를 껐어. 마이크로 말해줘.';
        /* 패널이 닫힐 때 startWakeListening이 다시 실행됨 — 끈 경우엔 즉시 중지 */
        if (!on) stopWakeListening();
    }

    /* ── Inline coachmark for voice panel ── */
    function showVoiceCoachmark(panel) {
        var steps = [
            { sel: '.dd-voice-mic',                            title: '🎤 음성 입력', body: '이 큰 버튼을 누르고 <b>한국어로 말해봐</b>! 나는 음성을 듣고 한국어로 답하면서 중국어 예문은 원어 발음으로 들려줄게.' },
            { sel: '.dd-voice-action[data-vact="chat"]',       title: '💬 채팅으로 전환', body: '키보드로 입력하고 싶으면 여기를 눌러. <b>역할극 / 단어 저장 / 작문 채점</b>은 채팅 패널에서 할 수 있어.' },
            { sel: '.dd-voice-action[data-vact="costume"]',    title: '🧥 의상 변경', body: '내 옷과 색을 바꿀 수 있어. <b>한복, 치파오, 후디</b> 등 6종 + 색상 6가지!' },
            { sel: '.dd-voice-action[data-vact="mute"]',       title: '🔊 자동 재생', body: '음성 자동 재생을 끄거나 켤 수 있어. 조용한 환경에서는 끄고 듣기 버튼만 사용해.' }
        ];
        var idx = 0;
        var overlay = el('div', 'dd-voice-coach-overlay');
        overlay.innerHTML = '<div class="dd-voice-coach-spot"></div><div class="dd-voice-coach-tip"></div>';
        document.body.appendChild(overlay);
        var spot = overlay.querySelector('.dd-voice-coach-spot');
        var tip = overlay.querySelector('.dd-voice-coach-tip');

        function render() {
            var step = steps[idx];
            var target = panel.querySelector(step.sel);
            if (!target) { idx++; if (idx >= steps.length) end(); else render(); return; }
            var r = target.getBoundingClientRect();
            var pad = 6;
            spot.style.left = (r.left - pad) + 'px';
            spot.style.top = (r.top - pad) + 'px';
            spot.style.width = (r.width + pad * 2) + 'px';
            spot.style.height = (r.height + pad * 2) + 'px';
            tip.innerHTML =
                '<div class="dd-voice-coach-title">' + step.title + '</div>' +
                '<div class="dd-voice-coach-body">' + step.body + '</div>' +
                '<div class="dd-voice-coach-footer">' +
                    '<span class="dd-voice-coach-count">' + (idx + 1) + ' / ' + steps.length + '</span>' +
                    '<button class="dd-voice-coach-skip">건너뛰기</button>' +
                    '<button class="dd-voice-coach-next">' + (idx === steps.length - 1 ? '시작!' : '다음') + '</button>' +
                '</div>';
            var th = tip.offsetHeight || 140;
            var tw = 280;
            var top = r.bottom + 10;
            if (top + th > window.innerHeight - 12) top = Math.max(12, r.top - th - 10);
            var left = Math.max(12, Math.min(r.left + r.width / 2 - tw / 2, window.innerWidth - tw - 12));
            tip.style.top = top + 'px';
            tip.style.left = left + 'px';
            tip.querySelector('.dd-voice-coach-skip').onclick = end;
            tip.querySelector('.dd-voice-coach-next').onclick = function () { idx++; if (idx >= steps.length) end(); else render(); };
        }
        function end() {
            if (overlay && overlay.parentNode) overlay.parentNode.removeChild(overlay);
            localStorage.setItem('dd_voice_coach', '1');
        }
        overlay.addEventListener('click', function (e) { if (e.target === overlay) end(); });
        render();
    }

    /* ============================================================
       Chat Panel
       ============================================================ */
    function openPanel() {
        if (state.isOpen) return;
        stopWakeListening();
        state.isOpen = true;
        hideBubble();
        dom.character.classList.add('is-open');

        var panel = el('div', 'dd-asst-panel');
        panel.innerHTML = buildPanelHTML();
        document.body.appendChild(panel);
        dom.panelWrap = panel;
        dom.messages = panel.querySelector('.dd-asst-messages');
        dom.input    = panel.querySelector('.dd-asst-input');
        dom.sendBtn  = panel.querySelector('.dd-asst-send');

        /* Welcome */
        var pool = BUBBLES[PAGE] || BUBBLES.landing;
        addMsg('bot', pool[0]);

        /* Resize handle */
        var resizeHandle = panel.querySelector('.dd-asst-resize-handle');
        if (resizeHandle) {
            resizeHandle.addEventListener('mousedown', function (e) {
                e.preventDefault();
                var startX = e.clientX, startY = e.clientY;
                var startW = panel.offsetWidth, startH = panel.offsetHeight;
                function onMove(ev) {
                    var dw = startX - ev.clientX;
                    var dh = startY - ev.clientY;
                    panel.style.width = Math.max(300, startW + dw) + 'px';
                    panel.style.maxHeight = Math.max(360, startH + dh) + 'px';
                }
                function onUp() {
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);
                }
                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
            });
        }

        /* Bind */
        panel.querySelector('.dd-asst-close').addEventListener('click', closePanel);
        dom.sendBtn.addEventListener('click', sendUserMsg);
        dom.input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendUserMsg(); }
        });
        panel.querySelectorAll('.dd-asst-quick-btn').forEach(function (btn) {
            btn.addEventListener('click', function () { handleAction(btn.getAttribute('data-action')); });
        });

        /* Voice mic button */
        dom.micBtn = panel.querySelector('.dd-asst-mic');
        if (dom.micBtn) {
            dom.micBtn.addEventListener('click', toggleVoice);
        }

        setTimeout(function () { dom.input.focus(); }, 350);
    }

    function closePanel() {
        if (!dom.panelWrap) return;
        dom.panelWrap.classList.add('is-closing');
        setTimeout(function () {
            if (dom.panelWrap) { dom.panelWrap.remove(); dom.panelWrap = null; }
            state.isOpen = false;
            dom.character.classList.remove('is-open');
            startWakeListening(); /* 채팅 닫히면 다시 "딩딩아" 대기 */
        }, 250);
    }

    function buildPanelHTML() {
        var actions = ACTIONS[PAGE] || ACTIONS.landing;
        var quickBtns = actions.map(function (a) {
            return '<button class="dd-asst-quick-btn" data-action="' + a.id + '">' + a.label + '</button>';
        }).join('');

        var apiNotice = '';
        if (!state.hasApiKey) {
            apiNotice =
                '<div class="dd-asst-api-notice">' +
                '💡 <span>AI 채팅은 <a onclick="document.getElementById(\'dd-key-fab\')&&document.getElementById(\'dd-key-fab\').click()">API 키 설정</a> 후 이용 가능</span>' +
                '</div>';
        }

        var c = getColor();
        return (
            '<div class="dd-asst-resize-handle" title="드래그하여 크기 조절">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h6v6M9 21H3v-6M21 3L3 21"/></svg>' +
            '</div>' +
            '<div class="dd-asst-header" style="background:linear-gradient(135deg, ' + c.light + ' 0%, #FFF0F3 50%, ' + c.light + ' 100%);">' +
                '<div class="dd-asst-header-avatar" style="border-color:' + c.main + '33;">' + PANDA_FACE + '</div>' +
                '<div class="dd-asst-header-info">' +
                    '<div class="dd-asst-header-name">叮叮 <small style="font-weight:400;font-size:0.72rem;color:' + c.main + '">학습도우미 · 역할극 · 가이드</small></div>' +
                    '<div class="dd-asst-header-status"><span class="dd-asst-header-dot"></span>' +
                        (state.hasApiKey ? 'AI 모드 활성' : '기본 모드') +
                    '</div>' +
                    getGamiBadgeHtml() +
                '</div>' +
                '<div class="dd-asst-header-actions">' +
                    '<button class="dd-asst-header-btn dd-asst-close" title="닫기">&times;</button>' +
                '</div>' +
            '</div>' +
            '<div class="dd-asst-messages"></div>' +
            '<div class="dd-asst-quick">' + quickBtns + '</div>' +
            apiNotice +
            '<div class="dd-asst-input-area">' +
                (hasSpeech ? '<button class="dd-asst-mic" title="음성으로 말하기">' + MIC_ICON + '</button>' : '') +
                '<input class="dd-asst-input" placeholder="질문, 역할극, 페이지 이동 뭐든 물어봐!" maxlength="300">' +
                '<button class="dd-asst-send">' + SEND_ICON + '</button>' +
            '</div>'
        );
    }

    /* ============================================================
       Messages
       ============================================================ */
    function addMsg(role, text, opts) {
        if (!dom.messages) return;
        var w = el('div', 'dd-asst-msg dd-asst-msg--' + role);
        var av = role === 'bot' ? '<div class="dd-asst-msg-avatar">' + PANDA_FACE + '</div>' : '';
        w.innerHTML = av + '<div class="dd-asst-msg-bubble">' + escHtml(text) + '</div>';
        dom.messages.appendChild(w);
        dom.messages.scrollTop = dom.messages.scrollHeight;
        /* Add TTS button to bot messages with Chinese */
        if (role === 'bot') {
            var bubble = w.querySelector('.dd-asst-msg-bubble');
            if (bubble) addTTSButton(bubble, text);
            /* Auto-speak if this is an AI response to voice input */
            if (opts && opts.autoSpeak) speakBilingual(text);
            /* If voice panel is open, mirror message into transcript */
            if (window.__ddVoiceMirror) window.__ddVoiceMirror(text);
        }
    }

    var typingEl = null;
    function showTyping() {
        if (typingEl) return;
        updateHeaderStatus('thinking');
        typingEl = el('div', 'dd-asst-msg dd-asst-msg--bot');
        typingEl.innerHTML =
            '<div class="dd-asst-msg-avatar">' + PANDA_FACE + '</div>' +
            '<div class="dd-asst-msg-bubble dd-asst-typing">' +
            '<span class="dd-asst-typing-dot"></span><span class="dd-asst-typing-dot"></span><span class="dd-asst-typing-dot"></span>' +
            '<span class="dd-asst-typing-label">생각하는 중...</span></div>';
        if (dom.messages) { dom.messages.appendChild(typingEl); dom.messages.scrollTop = dom.messages.scrollHeight; }
    }
    function hideTyping() {
        if (typingEl) { typingEl.remove(); typingEl = null; }
        updateHeaderStatus('done');
        setTimeout(function () { updateHeaderStatus('idle'); }, 2500);
    }

    function updateHeaderStatus(mode) {
        if (!dom.panelWrap) return;
        var statusEl = dom.panelWrap.querySelector('.dd-asst-header-status');
        if (!statusEl) return;
        if (mode === 'thinking') {
            statusEl.innerHTML = '<span class="dd-asst-header-dot is-thinking"></span>생각하는 중...';
        } else if (mode === 'done') {
            statusEl.innerHTML = '<span class="dd-asst-header-dot is-done"></span>답변 완료 ✓';
        } else if (mode === 'roleplay') {
            statusEl.innerHTML = '<span class="dd-asst-header-dot is-roleplay"></span>🎭 역할극 모드';
        } else {
            statusEl.innerHTML = '<span class="dd-asst-header-dot"></span>' + (state.hasApiKey ? 'AI 모드 활성' : '기본 모드');
        }
    }

    /* ============================================================
       Handle input
       ============================================================ */
    var lastInputWasVoice = false;

    function sendUserMsg() {
        var text = dom.input.value.trim();
        if (!text) return;
        dom.input.value = '';
        addMsg('user', text);
        /* Reset voice flag for keyboard input (voice sets it just before calling) */
        if (!lastInputWasVoice) lastInputWasVoice = false;
        handleFreeText(text);
    }

    function handleAction(id) {
        if (id === 'coach') {
            addMsg('bot', PRESETS.coach);
            setTimeout(function () { closePanel(); startCoachmarks(); }, 600);
            return;
        }
        if (id === 'costume') { showCostumePicker(); return; }
        if (id === 'roleplay') { showRoleplayScenarios(); return; }
        if (id === 'daily_challenge') { showDailyChallenge(); return; }
        if (id === 'mini_quiz') { showMiniQuiz(); return; }
        if (id === 'my_stats') { showStatsPanel(); return; }
        if (id === 'xp_shop') { showXPShop(); return; }
        if (id === 'legendary') { showLegendaryChallenge(); return; }
        if (PRESETS[id]) addMsg('bot', PRESETS[id]);
    }

    /* ============================================================
       Costume Picker
       ============================================================ */
    function showCostumePicker() {
        if (!dom.messages) return;
        var c = getColor();
        var html = '<div class="dd-asst-costume-picker">';
        html += '<div class="dd-asst-costume-section"><b>🧥 의상 선택</b><div class="dd-asst-costume-grid">';
        Object.keys(OUTFITS).forEach(function (key) {
            var o = OUTFITS[key];
            html += '<button class="dd-asst-costume-btn' + (costume.outfit === key ? ' is-active' : '') +
                '" data-outfit="' + key + '">' + o.icon + ' ' + o.label + '</button>';
        });
        html += '</div></div>';
        html += '<div class="dd-asst-costume-section"><b>🎨 색상 선택</b><div class="dd-asst-color-grid">';
        Object.keys(COLORS).forEach(function (key) {
            var col = COLORS[key];
            html += '<button class="dd-asst-color-btn' + (costume.color === key ? ' is-active' : '') +
                '" data-color="' + key + '" style="background:' + col.main + '" title="' + col.label + '"></button>';
        });
        html += '</div></div></div>';

        var w = el('div', 'dd-asst-msg dd-asst-msg--bot');
        w.innerHTML = '<div class="dd-asst-msg-avatar">' + PANDA_FACE + '</div><div class="dd-asst-msg-bubble">' + html + '</div>';
        dom.messages.appendChild(w);
        dom.messages.scrollTop = dom.messages.scrollHeight;

        w.querySelectorAll('.dd-asst-costume-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                costume.outfit = btn.dataset.outfit;
                saveCostume(); refreshCostume();
                w.querySelectorAll('.dd-asst-costume-btn').forEach(function (b) { b.classList.remove('is-active'); });
                btn.classList.add('is-active');
                addMsg('bot', '의상을 ' + OUTFITS[costume.outfit].icon + ' ' + OUTFITS[costume.outfit].label + '(으)로 바꿨어!');
            });
        });
        w.querySelectorAll('.dd-asst-color-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                costume.color = btn.dataset.color;
                saveCostume(); refreshCostume();
                w.querySelectorAll('.dd-asst-color-btn').forEach(function (b) { b.classList.remove('is-active'); });
                btn.classList.add('is-active');
                addMsg('bot', '색상을 ' + COLORS[costume.color].label + '(으)로 바꿨어!');
            });
        });
    }

    /* ============================================================
       Roleplay Scenarios — AI 시나리오 롤플레잉
       ============================================================ */
    var RP_SCENARIOS = [
        { id: 'restaurant', icon: '🍜', title: '식당 주문',
          desc: '중국 식당에서 음식 주문하기',
          setup: '你是一家中国餐厅的服务员，正在为一位韩国游客点餐。餐厅菜单包括：宫保鸡丁(38元)、麻婆豆腐(28元)、鱼香肉丝(32元)、西红柿炒鸡蛋(22元)、米饭(3元)、可乐(5元)、啤酒(8元)。',
          opening: '欢迎光临！请问几位？需要看一下菜单吗？\n(환영합니다! 몇 분이세요? 메뉴를 보시겠어요?)' },
        { id: 'taxi', icon: '🚕', title: '택시 탑승',
          desc: '택시를 타고 목적지까지',
          setup: '你是一名北京的出租车司机。乘客要去天安门广场。从当前位置到天安门大约15公里，需要30-40分钟，费用大约50元。你要和乘客聊天，介绍一下路上看到的景点。',
          opening: '您好！请问去哪儿？\n(안녕하세요! 어디로 가시나요?)' },
        { id: 'shopping', icon: '🛍️', title: '쇼핑/흥정',
          desc: '시장에서 물건 사며 흥정하기',
          setup: '你是一个中国市场的小贩，卖茶叶和丝绸围巾。茶叶标价200元(可以降到80元)，围巾标价300元(可以降到120元)。顾客是韩国游客，你要热情推销但可以慢慢降价。',
          opening: '来看看！我们的茶叶是最好的龙井茶！要不要试试？\n(보세요! 우리 차는 최고 용정차예요! 한번 드셔볼래요?)' },
        { id: 'hotel', icon: '🏨', title: '호텔 체크인',
          desc: '호텔에서 체크인하고 요청하기',
          setup: '你是一家上海五星级酒店的前台。客人预订了一间标准双人房，3晚，每晚680元。你要办理入住手续：确认姓名、护照、付款方式，并介绍早餐时间(7-10点)、WiFi密码、健身房位置等。',
          opening: '您好，欢迎入住上海国际大酒店！请问您有预订吗？\n(안녕하세요, 상하이 국제호텔에 오신 것을 환영합니다! 예약하셨나요?)' },
        { id: 'hospital', icon: '🏥', title: '병원 진료',
          desc: '병원에서 증상 설명하고 진료받기',
          setup: '你是一名中国医院的医生。韩国游客因为肚子疼来看病。你要问症状：什么时候开始的、吃了什么、有没有发烧等。最后开药方：肠胃药+多喝热水+清淡饮食。',
          opening: '你好，请坐。哪里不舒服？\n(안녕하세요, 앉으세요. 어디가 불편하세요?)' },
        { id: 'bank', icon: '🏦', title: '은행 업무',
          desc: '은행에서 환전하고 계좌 문의하기',
          setup: '你是中国银行的柜员。韩国游客想要把韩元换成人民币。今天汇率：1000韩元=5.2人民币。手续费50元。你要确认金额、护照、填写表格等。',
          opening: '您好，欢迎来到中国银行。请问您办什么业务？\n(안녕하세요, 중국은행에 오신 것을 환영합니다. 무슨 업무를 보시겠어요?)' }
    ];

    var rpState = { active: false, scenario: null, history: [] };

    function showRoleplayScenarios() {
        if (!state.hasApiKey) {
            addMsg('bot', '역할극을 하려면 API 키가 필요해!\n🔑 API 키를 먼저 설정해줘.');
            return;
        }
        var html = '<div class="dd-asst-rp-list">' +
            '<b>🎭 역할극 시나리오</b><p>실전 상황을 골라 중국어로 대화해봐!</p>' +
            '<div class="dd-asst-rp-grid">';
        RP_SCENARIOS.forEach(function (s, i) {
            html += '<button class="dd-asst-rp-card" data-rp="' + i + '">' +
                '<span class="dd-asst-rp-icon">' + s.icon + '</span>' +
                '<span class="dd-asst-rp-title">' + s.title + '</span>' +
                '<span class="dd-asst-rp-desc">' + s.desc + '</span></button>';
        });
        html += '</div></div>';

        var w = el('div', 'dd-asst-msg dd-asst-msg--bot');
        w.innerHTML = '<div class="dd-asst-msg-avatar">' + PANDA_FACE + '</div><div class="dd-asst-msg-bubble">' + html + '</div>';
        dom.messages.appendChild(w);
        dom.messages.scrollTop = dom.messages.scrollHeight;

        w.querySelectorAll('.dd-asst-rp-card').forEach(function (card) {
            card.addEventListener('click', function () {
                startRoleplay(RP_SCENARIOS[parseInt(card.dataset.rp)]);
            });
        });
    }

    function startRoleplay(scenario) {
        rpState.active = true;
        rpState.scenario = scenario;
        rpState.history = [];
        addMsg('bot', scenario.icon + ' ' + scenario.title + ' 시작!\n' + scenario.desc + '\n\n중국어로 대화해봐. 한국어도 괜찮아!');
        addMsg('bot', scenario.opening);
        rpState.history.push({ role: 'model', parts: [{ text: scenario.opening }] });
        if (dom.input) dom.input.placeholder = '중국어로 대답해봐...';
        updateHeaderStatus('roleplay');
    }

    function endRoleplay() {
        rpState.active = false;
        rpState.scenario = null;
        rpState.history = [];
        if (dom.input) dom.input.placeholder = '질문을 입력하세요...';
        updateHeaderStatus('idle');
    }

    function getRoleplayPrompt(scenario) {
        return '당신은 역할극 시뮬레이션의 NPC입니다. ' +
            '상황 설정: ' + scenario.setup + '\n\n' +
            '규칙:\n' +
            '1. 반드시 중국어로만 말하세요 (괄호 안에 한국어 번역 포함)\n' +
            '2. 학생의 중국어가 틀리면, 역할극 대사 후 짧게 교정\n' +
            '3. 학생이 한국어로 말해도 중국어로 응답 (한국어 번역 병기)\n' +
            '4. 3-4번 주고받기 후 자연스럽게 마무리되면 "---역할극 종료---"를 포함하고,\n' +
            '   평가: 사용한 표현 / 교정 사항 / 종합 평가(적절성/문법/유창성 각 /10)\n' +
            '5. 답변은 2-4문장으로 자연스럽게';
    }

    /* ============================================================
       Page Navigation — 叮叮이 사이트 내 페이지 이동
       ============================================================ */
    var NAV_ROUTES = [
        { keywords: ['홈', '메인', '첫 페이지', '랜딩', '처음'], path: '/', label: '홈' },
        { keywords: ['강좌 목록', '강좌', '코스', '수업 목록', '강의 목록'], path: '/courses/', label: '강좌 목록' },
        { keywords: ['단어장', '단어', '어휘', '보카', '플래시카드'], path: '/vocabulary/', label: '단어장' },
        { keywords: ['스토리 목록', '스토리', '이야기', '인터랙티브'], path: '/stories/', label: '스토리' },
        { keywords: ['뉴스레터 목록', '뉴스레터', '뉴스', '소식'], path: '/newsletters/', label: '뉴스레터' }
    ];

    /* "학습 시작하기" / "강의 목록 보여줘" 등 → 강좌 목록으로 가려는 의도인지 판별.
       (띄어쓰기/네비 동사 유무에 흔들리지 않게 공백 제거 후 매칭) */
    function isCourseStartIntent(text) {
        var t = (text || '').toLowerCase().replace(/\s+/g, '');
        if (/(스토리|이야기|단어|어휘|보카|뉴스|홈으로|메인으로)/.test(t)) return false; /* 다른 목적지 우선 */
        if (/(학습시작|공부시작|시작하기|시작할래|시작하자|학습하러|공부하러|배우러|배우고싶|공부하고싶|학습하고싶|중국어배우|중국어공부)/.test(t)) return true;
        if (/(강좌|강의|수업)(목록|리스트|보여|보고|구경|둘러|시작|이동|열어|가줘|가자|갈래|들어가)/.test(t)) return true;
        return false;
    }

    function tryNavigate(text) {
        var t = text.toLowerCase().replace(/\s+/g, ' ').trim();
        /* 학습 시작/강좌 보기 의도 → 강좌 목록으로 바로 이동 (클릭 안내 대신) */
        if (isCourseStartIntent(text)) {
            addMsg('bot', '좋아! 강좌 목록 페이지로 바로 데려갈게! 🚀');
            setTimeout(function () { window.location.href = '/courses/'; }, 800);
            return true;
        }
        var navMatch = t.match(/(이동|가줘|가자|갈래|열어|보여줘|페이지|으로 가|로 가|데려가|넘어가)/);
        if (!navMatch) return false;
        for (var i = 0; i < NAV_ROUTES.length; i++) {
            var route = NAV_ROUTES[i];
            for (var j = 0; j < route.keywords.length; j++) {
                if (t.indexOf(route.keywords[j]) !== -1) {
                    addMsg('bot', '좋아! ' + route.label + ' 페이지로 이동할게! 🚀');
                    setTimeout(function () { window.location.href = route.path; }, 800);
                    return true;
                }
            }
        }
        return false;
    }

    /* ─── 강의 카탈로그: 현재 페이지의 강의/강좌 링크를 수집 ───
       랜딩(.dd-recent-card), 강좌목록(.dd-lesson-link / .dd-course-block)에서
       제목·URL·유형(song/ai)을 긁어 음성으로 "틀어줘" 할 수 있게 한다. */
    function normTitle(s) {
        return (s || '').toLowerCase().replace(/[\s\-_·.,!?'"`()\[\]【】「」、。~]/g, '');
    }

    function getLessonCatalog() {
        var out = [], seen = {};
        function add(title, href, type) {
            title = (title || '').replace(/\s+/g, ' ').trim();
            href = (href || '').trim();
            if (!title || !href || href === '#') return;
            var key = href + '|' + title;
            if (seen[key]) return; seen[key] = true;
            out.push({ title: title, href: href, type: type || 'ai', n: normTitle(title) });
        }
        /* 강좌 목록 페이지: 개별 강의 링크 */
        document.querySelectorAll('a.dd-lesson-link').forEach(function (a) {
            var name = (a.querySelector('.dd-lesson-name') || {}).textContent || '';
            var block = a.closest('.dd-course-block');
            add(name, a.getAttribute('href'), block && block.getAttribute('data-type'));
        });
        /* 강좌 목록 페이지: 강좌 제목 → 첫 강의로 매핑 (강좌 단위 호출 대응) */
        document.querySelectorAll('.dd-course-block').forEach(function (b) {
            var h = b.querySelector('.dd-course-info h2, .dd-course-block-header h2');
            var first = b.querySelector('a.dd-lesson-link');
            if (h && first) add(h.textContent, first.getAttribute('href'), b.getAttribute('data-type'));
        });
        /* 랜딩 페이지: 최근 강좌 카드 */
        document.querySelectorAll('a.dd-recent-card').forEach(function (a) {
            var h = a.querySelector('h3');
            var type = 'ai';
            if (a.querySelector('.dd-badge-song') || a.querySelector('.dd-recent-thumb--song')) type = 'song';
            add(h && h.textContent, a.getAttribute('href'), type);
        });
        return out;
    }

    var DD_OPEN_VERBS = /(틀어|틀|들려|들을|들어|재생|열어|열|시작|공부|배울|배우|학습하|보여|보고\s*싶|가줘|가자|갈래|데려가|이동|해줘|주라|줘|플레이|play)/;
    var DD_QUESTION = /(뭐|무엇|뭔|뭘|설명|차이|이란|이라는|란\s*무|는\s*무엇)/;
    var DD_TYPES = [
        { kw: /(노래|학습\s*송|학습송|뮤직|뮤비|song|mv|가요)/, type: 'song', label: '노래' },
        { kw: /(ai\s*강|에이아이\s*강|일반\s*강)/, type: 'ai', label: 'AI' }
    ];

    /* "노래 강좌 틀어줘" / "<강의 제목> 열어줘" → 이동 액션 반환 (없으면 null) */
    function tryOpenLesson(text) {
        var raw = (text || '').toLowerCase().replace(/\s+/g, ' ').trim();
        if (!raw) return null;
        var catalog = getLessonCatalog();
        var nspoken = normTitle(raw);

        /* 1) 구체적 강의 제목 매칭 (부분 포함, 가장 긴 제목 우선) */
        if (catalog.length) {
            var best = null, bestLen = 0;
            for (var i = 0; i < catalog.length; i++) {
                var c = catalog[i];
                if (c.n.length < 2) continue;
                var hit = nspoken.indexOf(c.n) !== -1 || (nspoken.length >= 3 && c.n.indexOf(nspoken) !== -1);
                if (hit && c.n.length > bestLen) { best = c; bestLen = c.n.length; }
            }
            /* 짧은 제목이 긴 문장에 우연히 포함돼 오작동하는 걸 방지:
               열기 동사가 있거나, 제목이 충분히 길고 발화의 절반 이상을 차지할 때만 이동 */
            if (best) {
                var strong = DD_OPEN_VERBS.test(raw) || (bestLen >= 4 && bestLen >= nspoken.length * 0.5);
                if (strong) return { reply: '"' + best.title + '" 강의를 열어줄게! 🚀', href: best.href };
            }
        }

        /* 2) 유형 요청: "노래 강좌", "드라마 틀어줘" 등 */
        for (var k = 0; k < DD_TYPES.length; k++) {
            var tw = DD_TYPES[k];
            if (!tw.kw.test(raw)) continue;
            var hasIntent = DD_OPEN_VERBS.test(raw) || /(강좌|강의|수업|코스|레슨)/.test(raw);
            if (!hasIntent) return null;                       /* "노래 좋아" 같은 잡담 무시 */
            if (DD_QUESTION.test(raw) && !DD_OPEN_VERBS.test(raw)) return null; /* "노래 강좌가 뭐야" → 설명으로 */
            var matches = catalog.filter(function (c) { return c.type === tw.type; });
            if (matches.length === 1) {
                return { reply: '"' + matches[0].title + '" ' + tw.label + ' 강좌를 틀어줄게! 🎵', href: matches[0].href };
            }
            if (matches.length > 1) {
                return { reply: tw.label + ' 강좌 중 "' + matches[0].title + '"부터 틀어줄게! 다른 건 강좌 목록에서 볼 수 있어. 🎵', href: matches[0].href };
            }
            /* 이 페이지에 목록이 없으면 강좌 목록(유형 필터)으로 */
            return { reply: tw.label + ' 강좌 목록으로 데려갈게! 🎵', href: '/courses/?filter=' + tw.type };
        }
        return null;
    }

    function handleFreeText(text) {
        /* Roleplay mode */
        if (rpState.active && rpState.scenario) {
            if (/그만|종료|끝|나가/.test(text)) { endRoleplay(); addMsg('bot', '역할극 종료! 수고했어~ 💪'); return; }
            askRoleplay(text);
            return;
        }
        /* 강의/강좌 직접 열기 (제목·유형 인식) — 프리셋보다 우선 */
        var lessonAct = tryOpenLesson(text);
        if (lessonAct) {
            addMsg('bot', lessonAct.reply);
            if (lessonAct.href) setTimeout(function () { window.location.href = lessonAct.href; }, 900);
            return;
        }
        if (tryNavigate(text)) return;
        /* 프리셋 패턴 매칭 먼저 (API 키 유무 상관없이) */
        var matched = matchPattern(text);
        if (matched) { addMsg('bot', matched); return; }
        /* 중국어 단어 질문 */
        var zhMatch = text.match(/[一-鿿]+/g);
        if (zhMatch && state.hasApiKey) {
            askGemini('이 중국어 단어/표현의 뜻, 병음, 예문을 알려줘: ' + zhMatch.join(''));
            return;
        }
        if (state.hasApiKey) { askGemini(text); return; }
        addMsg('bot',
            '음, 이건 AI가 필요한 질문이야!\n\n' +
            '🔑 Gemini API 키를 설정하면\nAI가 어떤 질문이든 답해줄 수 있어.\n\n' +
            '지금은 위 버튼들로 도움받을 수 있어!\n"이 사이트 뭐야?" 같은 질문은 바로 답할 수 있어 😊');
    }

    function askRoleplay(userText) {
        showTyping();
        rpState.history.push({ role: 'user', parts: [{ text: userText }] });
        var body = {
            contents: rpState.history.slice(),
            systemInstruction: { parts: [{ text: getRoleplayPrompt(rpState.scenario) }] },
            generationConfig: { maxOutputTokens: 600, temperature: 0.7 }
        };
        var url = 'https://generativelanguage.googleapis.com/v1beta/models/' + MODELS[0] + ':generateContent';
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'x-goog-api-key': state.apiKey },
            body: JSON.stringify(body)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            hideTyping();
            try {
                var txt = data.candidates[0].content.parts[0].text.replace(/\*\*(.*?)\*\*/g, '$1');
                rpState.history.push({ role: 'model', parts: [{ text: txt }] });
                var wasVoice = lastInputWasVoice; lastInputWasVoice = false;
                addMsg('bot', txt, { autoSpeak: wasVoice });
                if (txt.indexOf('역할극 종료') !== -1) {
                    endRoleplay();
                    if (window.DDGamification) DDGamification.onRoleplayComplete();
                }
            } catch (e) { addMsg('bot', '응답을 처리하지 못했어 😢'); }
        }).catch(function (err) { hideTyping(); addMsg('bot', '오류: ' + err.message); });
    }

    function matchPattern(t) {
        t = t.toLowerCase().trim();

        /* ── 사이트 소개 ── */
        if (/이\s*사이트|딩동|dingdong|여기.*뭐|뭐.*사이트|뭐.*하는.*곳|어떤.*사이트|사이트.*소개/.test(t)) return PRESETS.site_intro;
        if (/누가.*만들|왜.*만들|만든.*이유|대상|누구.*위한/.test(t)) return PRESETS.site_who;
        if (/무료|유료|돈|요금|가격|결제|비용/.test(t)) return PRESETS.site_free;
        if (/로그인|회원.*가입|가입|계정|아이디|비밀번호/.test(t)) return PRESETS.site_login;
        if (/모바일|스마트폰|핸드폰|앱|어플|태블릿/.test(t)) return PRESETS.mobile_ok;

        /* ── 叮叮 자기소개 ── */
        if (/너.*누구|딩딩.*누구|네.*이름|자기.*소개|넌.*뭐|정체/.test(t)) return PRESETS.dingding_who;

        /* ── 기능 ── */
        if (/기능.*뭐|뭐.*있|할.*수.*있|무엇.*제공|어떤.*기능/.test(t)) return PRESETS.features;
        if (/기능.*자세|탭.*뭐|강의.*구성|강의.*안에/.test(t)) return PRESETS.features_detail;
        if (/api|키|열쇠|gemini|제미나이|ai.*설정|ai.*사용/.test(t)) return PRESETS.ai_explain;

        /* ── 강좌 ── */
        if (/추천|뭐.*(들을|배울|시작)|어떤.*강/.test(t)) return PRESETS.recommend;
        if (/강좌.*뭐|강의.*뭐|수업.*뭐|강좌.*종류|강의.*종류/.test(t)) return PRESETS.course_what;
        if (/강좌.*찾|강좌.*어디|강의.*어디|수업.*어디|강좌.*보는/.test(t)) return PRESETS.course_how;
        if (/입문|초보|기초|쉬운|처음/.test(t)) return PRESETS.beginner;
        if (/난이도|레벨|수준|급/.test(t)) return PRESETS.level_info;
        if (/강좌.*몇|강의.*몇|몇.*개|목록.*알려|어떤.*강좌.*있/.test(t)) { var di = getDynamicSiteInfo(); if (di) return '현재 사이트 현황이야!\n\n' + di + '\n\n자세히 보려면 "강좌 목록으로 이동해줘" 라고 해봐!'; return PRESETS.recommend; }

        /* ── 학습 순서/시작 ── */
        if (/시작|어떻게|방법|처음.*뭐/.test(t)) return PRESETS.start;
        if (/순서|차례|먼저/.test(t)) return PRESETS.order;

        /* ── 개별 기능 ── */
        if (/슬라이드/.test(t)) return PRESETS.slide_info;
        if (/오디오.*북|오디오북|tts|음성.*재생/.test(t)) return PRESETS.audiobook_info;
        if (/스토리.*북|스토리북|만화|4컷/.test(t)) return PRESETS.storybook_info;
        if (/작문|글쓰기|채점|피드백/.test(t)) return PRESETS.writing_info;
        if (/학습.*송|노래|song/.test(t)) return PRESETS.song_info;
        if (/문화.*노트|문화/.test(t)) return PRESETS.culture_info;
        if (/진도|진행|진척|완료.*율/.test(t)) return PRESETS.progress_info;

        /* ── 단어장 ── */
        if (/단어.*장|단어.*저장|vocab/.test(t)) return PRESETS.vocab_intro;
        if (/단어|어휘|뜻/.test(t)) return PRESETS.word_help;
        if (/게임|미니.*게임/.test(t)) return PRESETS.game_rec;
        if (/학습.*법|공부.*법|암기/.test(t)) return PRESETS.study_method;

        /* ── 퀴즈/발음/복습 ── */
        if (/퀴즈|시험|문제/.test(t)) return PRESETS.quiz_tip;
        if (/발음|성조|듣기/.test(t)) return PRESETS.pronunciation;
        if (/복습|일정|스케줄|에빙하우스/.test(t)) return PRESETS.review;

        /* ── 스토리 ── */
        if (/인터랙티브|스토리.*게임|선택.*이야기/.test(t)) return PRESETS.story_what;
        if (/스토리.*팁|스토리.*방법/.test(t)) return PRESETS.story_tip;
        if (/엔딩|결말/.test(t)) return PRESETS.ending_hint;

        /* ── 뉴스레터 ── */
        if (/뉴스.*레터|뉴스|읽기|독해/.test(t)) return PRESETS.news_intro;

        /* ── 역할극 ── */
        if (/역할.*극|롤플레이|시나리오|대화.*연습|실전.*대화/.test(t)) return PRESETS.roleplay_intro;

        /* ── 게이미피케이션 ── */
        if (/xp|경험치|레벨|레벨업/.test(t)) return '📊 "내 통계" 버튼을 눌러봐!\nXP를 모아서 레벨업할 수 있어!\n강의 방문, 퀴즈 통과, 단어 저장 등으로 XP 획득!';
        if (/스트릭|연속|출석/.test(t)) return '🔥 매일 접속하면 스트릭이 쌓여!\n3일 연속 → +20XP\n7일 연속 → +50XP\n30일 연속 → +200XP!\n"내 통계"에서 캘린더를 확인해봐!';
        if (/업적|뱃지|도전과제|어치브먼트/.test(t)) return '🏆 업적은 특별한 목표를 달성하면 열려!\n퀴즈 만점, 단어 50개 저장, 7일 연속 학습 등\n"내 통계" 버튼에서 확인할 수 있어!';
        if (/챌린지|오늘.*문제|데일리/.test(t)) { showDailyChallenge(); return ''; }
        if (/미니.*퀴즈|즉석.*퀴즈|퀴즈.*풀/.test(t)) { showMiniQuiz(); return ''; }
        if (/통계|내.*점수|내.*레벨|내.*기록/.test(t)) { showStatsPanel(); return ''; }
        if (/상점|샵|shop|구매|사고.*싶|프리즈|힌트.*토큰/.test(t)) { showXPShop(); return ''; }
        if (/레전드|전설|하드.*모드|고난이도/.test(t)) { showLegendaryChallenge(); return ''; }
        if (/콤보|연속.*정답|보너스/.test(t)) return '🔥 미니퀴즈에서 연속으로 맞추면 콤보가 쌓여!\n3연속 → x1.5 보너스\n5연속 → x2 보너스\n10연속 → x3 LEGENDARY!\n\n⚡ 미니퀴즈 버튼을 눌러봐!';

        /* ── 가이드/안내 ── */
        if (/안내|가이드|투어|설명/.test(t)) return PRESETS.coach;
        if (/소개/.test(t)) return PRESETS.site_intro;

        return null;
    }

    /* ============================================================
       Gemini API
       ============================================================ */
    function getPageContext() {
        var ctx = '';
        if (PAGE === 'landing') {
            var hero = document.querySelector('.dd-hero-title, .dd-hero h1, h1');
            if (hero) ctx += '페이지 제목: ' + hero.textContent.trim().substring(0, 100) + '\n';
            var desc = document.querySelector('.dd-hero-desc, .dd-hero p');
            if (desc) ctx += '설명: ' + desc.textContent.trim().substring(0, 200) + '\n';
            var cards = [];
            document.querySelectorAll('.dd-recent-card h3, .dd-feature-title, .dd-features-grid h3').forEach(function (c) {
                cards.push(c.textContent.trim());
            });
            if (cards.length) ctx += '주요 항목: ' + cards.join(', ') + '\n';
            var stats = [];
            document.querySelectorAll('.dd-stat').forEach(function (s) {
                var v = s.querySelector('.dd-stat-value');
                var l = s.querySelector('.dd-stat-label');
                if (v && l) stats.push(l.textContent.trim() + ' ' + v.textContent.trim());
            });
            if (stats.length) ctx += '통계: ' + stats.join(', ') + '\n';
        } else if (PAGE === 'lesson') {
            var title = document.querySelector('.dd-lesson-header h1, .dd-topbar-title');
            if (title) ctx += '강의 제목: ' + title.textContent.trim() + '\n';
            var contentBody = document.querySelector('#section-main, .dd-content-body, [data-section="content"]');
            if (contentBody) ctx += '강의 본문:\n' + contentBody.textContent.trim().substring(0, 800) + '\n';
            var keCards = document.querySelectorAll('.dd-ke-save');
            if (keCards.length) {
                var exprs = [];
                keCards.forEach(function (c) {
                    exprs.push((c.dataset.zh || '') + '(' + (c.dataset.pinyin || '') + ', ' + (c.dataset.ko || '') + ')');
                });
                ctx += '핵심 표현: ' + exprs.join(', ') + '\n';
            }
        } else if (PAGE === 'courses') {
            var courseNames = [];
            document.querySelectorAll('.dd-course-block-header h2, .dd-course-title').forEach(function (c) {
                courseNames.push(c.textContent.trim());
            });
            if (courseNames.length) ctx += '강좌 목록: ' + courseNames.join(', ') + '\n';
        } else if (PAGE === 'story') {
            var st = document.querySelector('.dd-story-title, .dd-story-cover h1, h1');
            if (st) ctx += '스토리 제목: ' + st.textContent.trim() + '\n';
        } else if (PAGE === 'newsletter' || PAGE === 'newsletters') {
            var nt = document.querySelector('.dd-nld-title, h1');
            if (nt) ctx += '뉴스레터 제목: ' + nt.textContent.trim() + '\n';
            var nb = document.querySelector('.dd-nld-body, .dd-nl-article');
            if (nb) ctx += '본문:\n' + nb.textContent.trim().substring(0, 500) + '\n';
        }
        return ctx;
    }

    function askGemini(userText) {
        if (tryNavigate(userText)) return;
        showTyping();
        var pageCtx = getPageContext();
        var sys =
            '너는 叮叮(Dīngding), 발랄하고 친근한 판다 버추얼 아이돌 학습도우미야. ' +
            'DingDong 중국어 학습 플랫폼의 AI 어시스턴트야. ' +
            '한국인 중국어 학습자를 도와줘. ' +
            '한국어 반말(~해, ~야, ~지)로 친구처럼 답변하되, 중국어 예시는 한자+병음+한국어 뜻을 같이 보여줘. ' +
            '답변은 200자 이내로 간결하게. 이모지 적절히 사용.\n\n';
        if (pageCtx) {
            sys += '=== 현재 페이지 정보 ===\n' + pageCtx + '\n';
        }
        sys += '중요: 사용자가 "이 사이트 설명해줘", "이 강의 내용 알려줘", "여기가 어디야" 등 현재 페이지에 대해 물으면, ' +
               '위의 페이지 정보를 기반으로 실제 내용(강좌명, 강의 주제, 핵심 표현, 통계 등)을 구체적으로 답변해. ' +
               '기능 목록만 나열하지 말고, 이 페이지에 실제로 있는 콘텐츠를 언급해.\n\n' +
               '페이지 이동: 사용자가 페이지 이동을 요청하면 "NAV:/courses/" 같은 형식으로 답해. ' +
               '가능한 경로: / (홈), /courses/ (강좌), /stories/ (스토리), /newsletters/ (뉴스레터), /vocabulary/ (단어장).';
        var body = {
            contents: [{ role: 'user', parts: [{ text: sys + '\n\n사용자: ' + userText }] }],
            generationConfig: { maxOutputTokens: 500, temperature: 0.7 }
        };
        callGemini(body, 0);
    }

    function callGemini(body, idx) {
        if (idx >= MODELS.length) { hideTyping(); addMsg('bot', 'AI 서버에 연결할 수 없어 😢\n잠시 후 다시 시도해줘!'); return; }
        var url = 'https://generativelanguage.googleapis.com/v1beta/models/' + MODELS[idx] + ':generateContent';
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'x-goog-api-key': state.apiKey },
            body: JSON.stringify(body)
        })
        .then(function (r) {
            if (!r.ok) { if (r.status === 404 || r.status === 429) { callGemini(body, idx + 1); return; } throw new Error(r.status); }
            return r.json();
        }).then(function (data) {
            if (!data) return;
            hideTyping();
            try {
                var txt = data.candidates[0].content.parts[0].text.replace(/\*\*(.*?)\*\*/g, '$1');
                var navCmd = txt.match(/NAV:(\/[a-z-]*\/?)$/m);
                if (navCmd) {
                    txt = txt.replace(/NAV:\/[a-z-]*\/?/g, '').trim();
                    if (!txt) txt = '이동할게! 🚀';
                    addMsg('bot', txt);
                    setTimeout(function () { window.location.href = navCmd[1]; }, 800);
                    return;
                }
                var wasVoice = lastInputWasVoice;
                lastInputWasVoice = false;
                addMsg('bot', txt, { autoSpeak: wasVoice });
            } catch (e) { addMsg('bot', '응답을 처리하지 못했어 😢'); }
        }).catch(function (err) { hideTyping(); addMsg('bot', '오류 발생: ' + err.message); });
    }

    /* ============================================================
       Daily Challenge UI
       ============================================================ */
    function showDailyChallenge() {
        if (!dom.messages || !window.DDGamification) return;
        var gd = DDGamification.load();
        var ch = DDGamification.getTodaysChallenge(gd);
        if (!ch) { addMsg('bot', '오늘의 챌린지를 불러올 수 없어 😢'); return; }

        if (gd.daily.completed) {
            addMsg('bot', '오늘의 챌린지는 이미 클리어했어! ✅\n내일 새 문제가 나와! +30XP 획득 완료 🎉');
            return;
        }

        var typeLabel = { translate: '번역', meaning: '뜻 맞추기', tone: '성조', fill: '빈칸 채우기', count: '한자 퀴즈' };
        var html = '<div class="dd-gami-challenge">' +
            '<div class="dd-gami-challenge-badge">🎯 오늘의 도전</div>' +
            '<div class="dd-gami-challenge-type">' + (typeLabel[ch.type] || ch.type) + '</div>' +
            '<div class="dd-gami-challenge-q">' + escHtml(ch.q) + '</div>' +
            '<div class="dd-gami-challenge-input">' +
                '<input class="dd-gami-challenge-answer" placeholder="답을 입력해봐!" maxlength="50">' +
                '<button class="dd-gami-challenge-submit">확인</button>' +
            '</div>' +
            '<div class="dd-gami-challenge-hint" style="display:none">💡 힌트: ' + escHtml(ch.hint) + '</div>' +
            '<button class="dd-gami-challenge-hint-btn">힌트 보기</button>' +
            '<div class="dd-gami-challenge-reward">정답 시 +30 XP!</div>' +
            '</div>';

        var w = el('div', 'dd-asst-msg dd-asst-msg--bot');
        w.innerHTML = '<div class="dd-asst-msg-avatar">' + PANDA_FACE + '</div><div class="dd-asst-msg-bubble">' + html + '</div>';
        dom.messages.appendChild(w);
        dom.messages.scrollTop = dom.messages.scrollHeight;

        var input = w.querySelector('.dd-gami-challenge-answer');
        var submitBtn = w.querySelector('.dd-gami-challenge-submit');
        var hintBtn = w.querySelector('.dd-gami-challenge-hint-btn');
        var hintDiv = w.querySelector('.dd-gami-challenge-hint');

        hintBtn.addEventListener('click', function () {
            hintDiv.style.display = '';
            hintBtn.style.display = 'none';
        });

        function submitAnswer() {
            var answer = input.value.trim();
            if (!answer) return;
            var result = DDGamification.checkDailyAnswer(DDGamification.load(), answer);
            if (result.already) {
                addMsg('bot', '이미 오늘 챌린지를 완료했어! ✅');
            } else if (result.correct) {
                addMsg('bot', '정답! 大棒了! 🎉\n+30 XP 획득!');
                DDGamification.setEmotion(DDGamification.load(), 'excited', 15000);
            } else {
                addMsg('bot', '아쉬워! 다시 한번 도전해봐!\n💡 힌트: ' + escHtml(result.hint));
            }
        }

        submitBtn.addEventListener('click', submitAnswer);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); submitAnswer(); }
        });
    }

    /* ============================================================
       Mini Quiz UI
       ============================================================ */
    var lastMiniQuizIdx = -1;

    function showMiniQuiz() {
        if (!dom.messages || !window.DDGamification) return;

        var mq = DDGamification.getRandomMiniQuiz(lastMiniQuizIdx);
        lastMiniQuizIdx = mq.idx;
        var quiz = mq.quiz;

        var html = '<div class="dd-gami-miniquiz">' +
            '<div class="dd-gami-miniquiz-badge">⚡ 미니퀴즈</div>' +
            '<div class="dd-gami-miniquiz-q">' + escHtml(quiz.q) + '</div>' +
            '<div class="dd-gami-miniquiz-choices">';
        quiz.choices.forEach(function (ch, i) {
            html += '<button class="dd-gami-miniquiz-choice" data-idx="' + i + '">' + escHtml(ch) + '</button>';
        });
        html += '</div>' +
            '<div class="dd-gami-miniquiz-reward">정답 시 +10 XP</div>' +
            '</div>';

        var w = el('div', 'dd-asst-msg dd-asst-msg--bot');
        w.innerHTML = '<div class="dd-asst-msg-avatar">' + PANDA_FACE + '</div><div class="dd-asst-msg-bubble">' + html + '</div>';
        dom.messages.appendChild(w);
        dom.messages.scrollTop = dom.messages.scrollHeight;

        w.querySelectorAll('.dd-gami-miniquiz-choice').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var idx = parseInt(btn.dataset.idx);
                var result = DDGamification.checkMiniQuizAnswer(DDGamification.load(), mq.idx, idx);

                w.querySelectorAll('.dd-gami-miniquiz-choice').forEach(function (b) {
                    b.disabled = true;
                    b.classList.add('is-disabled');
                    if (parseInt(b.dataset.idx) === quiz.answer) b.classList.add('is-correct');
                });

                if (result.correct) {
                    btn.classList.add('is-correct');
                    var comboResult = DDGamification.onComboHit(DDGamification.load());
                    var comboText = '';
                    if (comboResult.tier) {
                        comboText = '\n🔥 ' + comboResult.combo + '연속! ' + comboResult.tier.label + ' x' + comboResult.tier.multiplier;
                    } else if (comboResult.combo >= 2) {
                        comboText = '\n🔥 ' + comboResult.combo + '연속 정답!';
                    }
                    addMsg('bot', '정답! 👏 +10 XP!' + comboText + '\n' + result.correctAnswer + ' — 잘 알고 있네!');
                    DDGamification.setEmotion(DDGamification.load(), 'happy', 8000);
                } else {
                    btn.classList.add('is-wrong');
                    var wasCombo = DDGamification.onComboBreak(DDGamification.load());
                    var breakText = wasCombo >= 3 ? '\n(' + wasCombo + '연속 콤보가 끊겼어!)' : '';
                    addMsg('bot', '아쉬워! 정답은 "' + result.correctAnswer + '" 이야.' + breakText + '\n다음엔 맞출 수 있어! 💪');
                }

                setTimeout(function () {
                    addMsg('bot', '한 문제 더 풀어볼래? ⚡');
                    var again = el('div', 'dd-asst-msg dd-asst-msg--bot');
                    again.innerHTML = '<div class="dd-asst-msg-avatar">' + PANDA_FACE + '</div>' +
                        '<div class="dd-asst-msg-bubble"><button class="dd-gami-again-btn" data-action="mini_quiz">⚡ 한 문제 더!</button></div>';
                    dom.messages.appendChild(again);
                    dom.messages.scrollTop = dom.messages.scrollHeight;
                    again.querySelector('.dd-gami-again-btn').addEventListener('click', function () { showMiniQuiz(); });
                }, 1500);
            });
        });
    }

    /* ============================================================
       Stats Dashboard UI
       ============================================================ */
    function showStatsPanel() {
        if (!dom.messages || !window.DDGamification) return;

        var stats = DDGamification.getStatsSummary(DDGamification.load());
        var xp = stats.xpToNext;

        var calendarHtml = buildCalendarHtml(stats.calendarDays);

        var achHtml = '';
        DDGamification.ACHIEVEMENTS.forEach(function (a) {
            var unlocked = DDGamification.load().achievements.indexOf(a.id) !== -1;
            achHtml += '<div class="dd-gami-ach' + (unlocked ? ' is-unlocked' : '') + '" title="' + escHtml(a.desc) + '">' +
                '<span class="dd-gami-ach-icon">' + a.icon + '</span>' +
                '<span class="dd-gami-ach-name">' + escHtml(a.title) + '</span>' +
                '</div>';
        });

        var html = '<div class="dd-gami-stats">' +
            '<div class="dd-gami-stats-header">' +
                '<div class="dd-gami-stats-level">' + stats.levelIcon + ' Lv.' + stats.level + ' ' + escHtml(stats.levelTitle) + '</div>' +
                '<div class="dd-gami-stats-xp">' + stats.xp + ' XP</div>' +
            '</div>' +
            '<div class="dd-gami-xp-bar">' +
                '<div class="dd-gami-xp-fill" style="width:' + xp.percent + '%"></div>' +
                '<span class="dd-gami-xp-label">' + xp.current + ' / ' + xp.needed + ' XP</span>' +
            '</div>' +
            '<div class="dd-gami-stats-grid">' +
                '<div class="dd-gami-stat-card"><span class="dd-gami-stat-num">🔥 ' + stats.streak + '</span><span class="dd-gami-stat-label">연속 학습</span></div>' +
                '<div class="dd-gami-stat-card"><span class="dd-gami-stat-num">📚 ' + stats.lessonsVisited + '</span><span class="dd-gami-stat-label">강의 방문</span></div>' +
                '<div class="dd-gami-stat-card"><span class="dd-gami-stat-num">🧩 ' + stats.quizzesPassed + '</span><span class="dd-gami-stat-label">퀴즈 통과</span></div>' +
                '<div class="dd-gami-stat-card"><span class="dd-gami-stat-num">📝 ' + stats.vocabCount + '</span><span class="dd-gami-stat-label">저장 단어</span></div>' +
                '<div class="dd-gami-stat-card"><span class="dd-gami-stat-num">🎯 ' + stats.dailyChallenges + '</span><span class="dd-gami-stat-label">데일리 챌린지</span></div>' +
                '<div class="dd-gami-stat-card"><span class="dd-gami-stat-num">⏱️ ' + stats.studyMinutes + '</span><span class="dd-gami-stat-label">학습 분</span></div>' +
            '</div>' +
            '<div class="dd-gami-calendar-title">📅 학습 캘린더 (최근 28일)</div>' +
            calendarHtml +
            '<div class="dd-gami-ach-title">🏆 업적 (' + stats.achievements + '/' + stats.totalAchievements + ')</div>' +
            '<div class="dd-gami-ach-grid">' + achHtml + '</div>' +
            '</div>';

        var w = el('div', 'dd-asst-msg dd-asst-msg--bot');
        w.innerHTML = '<div class="dd-asst-msg-avatar">' + PANDA_FACE + '</div><div class="dd-asst-msg-bubble">' + html + '</div>';
        dom.messages.appendChild(w);
        dom.messages.scrollTop = dom.messages.scrollHeight;
    }

    function buildCalendarHtml(calendarDays) {
        var html = '<div class="dd-gami-calendar">';
        var today = new Date();
        for (var i = 27; i >= 0; i--) {
            var d = new Date(today);
            d.setDate(d.getDate() - i);
            var ds = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            var active = calendarDays.indexOf(ds) !== -1;
            var isToday = i === 0;
            html += '<div class="dd-gami-cal-day' + (active ? ' is-active' : '') + (isToday ? ' is-today' : '') + '" title="' + ds + '">' +
                '<span>' + d.getDate() + '</span></div>';
        }
        html += '</div>';
        return html;
    }

    /* ============================================================
       XP Shop UI
       ============================================================ */
    function showXPShop() {
        if (!dom.messages || !window.DDGamification) return;
        var d = DDGamification.load();
        var items = DDGamification.getShopItems(d);
        var freezes = DDGamification.getStreakFreezeCount(d);
        var hints = DDGamification.getHintTokenCount(d);

        var html = '<div class="dd-gami-shop">' +
            '<div class="dd-gami-shop-header">' +
                '<span class="dd-gami-shop-badge">🛒 XP 상점</span>' +
                '<span class="dd-gami-shop-balance">' + d.xp + ' XP 보유</span>' +
            '</div>' +
            '<div class="dd-gami-shop-inventory">' +
                '🧊 프리즈 ' + freezes + '개 · 💡 힌트 ' + hints + '개' +
            '</div>' +
            '<div class="dd-gami-shop-grid">';

        items.forEach(function (item) {
            var btnClass = 'dd-gami-shop-buy';
            var btnText = item.price + ' XP';
            if (item.owned) { btnClass += ' is-owned'; btnText = '보유중'; }
            else if (!item.canBuy) { btnClass += ' is-locked'; }

            html += '<div class="dd-gami-shop-item">' +
                '<div class="dd-gami-shop-icon">' + item.icon + '</div>' +
                '<div class="dd-gami-shop-info">' +
                    '<div class="dd-gami-shop-name">' + escHtml(item.name) + '</div>' +
                    '<div class="dd-gami-shop-desc">' + escHtml(item.desc) + '</div>' +
                '</div>' +
                '<button class="' + btnClass + '" data-item="' + item.id + '"' +
                    (item.owned || !item.canBuy ? ' disabled' : '') + '>' + btnText + '</button>' +
                '</div>';
        });

        html += '</div></div>';

        var w = el('div', 'dd-asst-msg dd-asst-msg--bot');
        w.innerHTML = '<div class="dd-asst-msg-avatar">' + PANDA_FACE + '</div><div class="dd-asst-msg-bubble">' + html + '</div>';
        dom.messages.appendChild(w);
        dom.messages.scrollTop = dom.messages.scrollHeight;

        w.querySelectorAll('.dd-gami-shop-buy:not([disabled])').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var result = DDGamification.buyShopItem(DDGamification.load(), btn.dataset.item);
                addMsg('bot', result.msg);
                if (result.success) {
                    btn.disabled = true;
                    btn.textContent = '완료!';
                    btn.classList.add('is-owned');
                }
            });
        });
    }

    /* ============================================================
       Legendary Challenge UI
       ============================================================ */
    function showLegendaryChallenge() {
        if (!dom.messages || !window.DDGamification) return;
        if (!state.hasApiKey) {
            addMsg('bot', '레전드 챌린지는 중급 이상의 번역 문제야!\n고난이도 · 힌트 없음 · 5문제 도전!\n\n🏆 퍼펙트 시 +160 XP!');
            return;
        }

        var d = DDGamification.load();
        var leg = DDGamification.getLegendaryChallenge(d);

        if (leg.completed) {
            addMsg('bot', '오늘의 레전드 챌린지는 이미 도전했어!\n결과: ' + leg.score + '/5 🏆\n내일 새 문제가 나와!');
            return;
        }

        addMsg('bot', '🏆 레전드 챌린지 시작!\n\n⚠️ 고난이도 번역 5문제\n⚠️ 힌트 없음 (힌트 토큰으로 구매 가능)\n⚠️ 퍼펙트 시 +160 XP!\n\n준비됐어?');

        showLegendaryQuestion(0);
    }

    function showLegendaryQuestion(qNum) {
        if (!window.DDGamification) return;
        var d = DDGamification.load();
        var leg = DDGamification.getLegendaryChallenge(d);
        if (qNum >= 5 || leg.completed) return;

        var qIdx = leg.questions[qNum];
        var quiz = DDGamification.LEGENDARY_QUIZZES[qIdx];
        if (!quiz) return;

        var hints = DDGamification.getHintTokenCount(d);

        var html = '<div class="dd-gami-legendary">' +
            '<div class="dd-gami-legendary-header">' +
                '<span class="dd-gami-legendary-badge">🏆 Q' + (qNum + 1) + '/5</span>' +
                '<span class="dd-gami-legendary-score">정답: ' + (d.legendary.score || 0) + '</span>' +
            '</div>' +
            '<div class="dd-gami-legendary-q">' + escHtml(quiz.q) + '</div>' +
            '<div class="dd-gami-challenge-input">' +
                '<input class="dd-gami-challenge-answer dd-gami-legendary-input" placeholder="중국어로 답을 입력..." maxlength="80">' +
                '<button class="dd-gami-challenge-submit dd-gami-legendary-submit">확인</button>' +
            '</div>' +
            (hints > 0 ? '<button class="dd-gami-legendary-hint-btn">💡 힌트 토큰 사용 (' + hints + '개 남음)</button>' : '') +
            '</div>';

        var w = el('div', 'dd-asst-msg dd-asst-msg--bot');
        w.innerHTML = '<div class="dd-asst-msg-avatar">' + PANDA_FACE + '</div><div class="dd-asst-msg-bubble">' + html + '</div>';
        dom.messages.appendChild(w);
        dom.messages.scrollTop = dom.messages.scrollHeight;

        var input = w.querySelector('.dd-gami-legendary-input');
        var submitBtn = w.querySelector('.dd-gami-legendary-submit');
        var hintBtn = w.querySelector('.dd-gami-legendary-hint-btn');

        if (hintBtn) {
            hintBtn.addEventListener('click', function () {
                var d2 = DDGamification.load();
                if (DDGamification.useHintToken(d2)) {
                    var answers = DDGamification.LEGENDARY_QUIZZES[qIdx].a;
                    var hint = answers[0].substring(0, Math.ceil(answers[0].length / 2)) + '...';
                    addMsg('bot', '💡 힌트: ' + hint);
                    hintBtn.disabled = true;
                    hintBtn.textContent = '💡 사용 완료';
                } else {
                    addMsg('bot', '힌트 토큰이 없어! 상점에서 구매해봐 🛒');
                }
            });
        }

        function submit() {
            var answer = input.value.trim();
            if (!answer) return;
            input.disabled = true;
            submitBtn.disabled = true;

            var result = DDGamification.checkLegendaryAnswer(DDGamification.load(), qNum, answer);

            if (result.correct) {
                addMsg('bot', '✅ 정답! (' + result.score + '/' + result.total + ')');
            } else {
                var correctAnswer = DDGamification.LEGENDARY_QUIZZES[qIdx].a[0];
                addMsg('bot', '❌ 오답! 정답: ' + correctAnswer + '\n(' + result.score + '/' + result.total + ')');
            }

            if (result.done) {
                setTimeout(function () {
                    if (result.perfect) {
                        addMsg('bot', '🏆✨ 퍼펙트! 5/5!\n+160 XP 획득!\n\n전설적인 실력이야! 🐉');
                    } else if (result.score >= 3) {
                        addMsg('bot', '🏆 레전드 챌린지 통과! ' + result.score + '/5\n+80 XP 획득!\n\n잘했어! 내일도 도전해봐!');
                    } else {
                        addMsg('bot', '레전드 챌린지 완료! ' + result.score + '/5\n+24 XP 획득\n\n더 연습하면 분명 잘할 수 있어! 💪');
                    }
                }, 800);
            } else {
                setTimeout(function () { showLegendaryQuestion(qNum + 1); }, 1200);
            }
        }

        submitBtn.addEventListener('click', submit);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); submit(); }
        });
        setTimeout(function () { input.focus(); }, 300);
    }

    /* ============================================================
       Smart Recommendation — 叮叮 bubble에 반영
       ============================================================ */
    function getSmartBubble() {
        if (!window.DDGamification) return null;
        var d = DDGamification.load();
        var alert = DDGamification.getStreakAlert(d);
        if (alert && alert.urgent) {
            DDGamification.setEmotion(d, alert.emotion, 20000);
            return alert.text;
        }
        var rec = DDGamification.getSmartRecommendation(d);
        if (rec) return rec.text;
        if (alert) return alert.text;
        return null;
    }

    /* ============================================================
       Gamification header badge (XP/Level in panel header)
       ============================================================ */
    function getGamiBadgeHtml() {
        if (!window.DDGamification) return '';
        var d = DDGamification.load();
        var stats = DDGamification.getStatsSummary(d);
        return '<div class="dd-gami-header-badge">' +
            '<span class="dd-gami-hb-level">' + stats.levelIcon + ' Lv.' + stats.level + '</span>' +
            '<span class="dd-gami-hb-xp">' + stats.xp + 'XP</span>' +
            (stats.streak > 0 ? '<span class="dd-gami-hb-streak">🔥' + stats.streak + '</span>' : '') +
            '</div>';
    }

    /* ============================================================
       Coachmark System
       ============================================================ */
    var coach = { steps: [], current: 0, overlay: null, spotlight: null, tooltip: null, active: false };

    var COACH_STEPS = {
        landing: [
            { sel: '.dd-hero-panda-wrap, .dd-hero', icon: '🐼', iconColor: 'pink',  title: '안녕! 나 叮叮이야',    body: '여기는 <b>DingDong 학습 사이트</b>의 첫 화면이야. 내가 함께 중국어 공부를 도와줄게!' },
            { sel: '.dd-hero-actions',              icon: '🎯', iconColor: 'pink',  title: '학습 시작하기',        body: '<b>강좌 보기</b> 버튼을 클릭하면 개설된 강좌 목록이 떠. 마음에 드는 강좌를 골라봐!' },
            { sel: '.dd-features-grid',             icon: '✨', iconColor: 'amber', title: '6가지 핵심 기능',     body: 'AI 강좌, 퀴즈, 오디오북, 스토리북, 작문 채점, 인터랙티브 스토리 — <b>모두 무료!</b>' },
            { sel: '.dd-recent-grid',               icon: '📚', iconColor: 'green', title: '최근 강좌 바로가기',   body: '카드를 <b>클릭하면 바로 학습</b>이 시작돼!' },
            { sel: '.dd-asst-character',            icon: '🐼', iconColor: 'pink',  title: '언제든 도움 요청',     body: '언제든 이 <b>판다(=나)를 클릭</b>하면 음성/채팅으로 도와줄게.' }
        ],
        courses: [
            { sel: '.dd-filter-bar',    icon: '🔍', iconColor: 'blue',  title: '강좌 필터',   body: '<b>전체 / AI 강좌 / 드라마</b> 탭으로 필터링할 수 있어요.' },
            { sel: '.dd-course-block:first-child .dd-course-block-header', icon: '📋', iconColor: 'green', title: '강좌 카드', body: '제목, 설명, <b>난이도 뱃지</b>, 강의 개수를 한눈에 확인!' },
            { sel: '.dd-course-block:first-child .dd-lesson-link', icon: '▶️', iconColor: 'pink', title: '강의 바로가기', body: '강의를 <b>클릭하면 바로 학습</b>을 시작할 수 있어요!' }
        ],
        lesson: [
            { sel: '.dd-tabs',          icon: '📑', iconColor: 'blue',   title: '학습 탭',     body: '<b>6개 탭</b>으로 다양하게 학습! 학습 내용→슬라이드→오디오북→스토리북→퀴즈→단어장 순서를 추천해요.' },
            { sel: '.dd-tab[data-tab="content"]', icon: '📖', iconColor: 'green', title: '학습 내용', body: '본문, <b>핵심 표현</b>, <b>실전 대화</b>를 볼 수 있는 메인 학습 탭이에요.' },
            { sel: '.dd-tab[data-tab="slides"]',  icon: '📊', iconColor: 'amber', title: '슬라이드', body: '핵심 단어와 예문을 <b>한눈에 정리</b>한 5장 슬라이드! 화살표로 넘겨보세요.' },
            { sel: '.dd-tab[data-tab="quiz"]',    icon: '🧩', iconColor: 'purple',title: '퀴즈',     body: '<b>4지선다/빈칸채우기/어순배열</b> 3종류 문제로 테스트해보세요!' },
            { sel: '#dd-progress-widget',          icon: '📈', iconColor: 'green', title: '학습 진도', body: '탭을 <b>완료하면 진도율이 올라가요</b>. 퀴즈 점수도 표시됩니다!' },
            { sel: '.dd-asst-character', icon: '🐼', iconColor: 'pink', title: '叮叮 도우미', body: '<b>叮叮</b>을 클릭하면 AI 질문, <b>역할극 시나리오</b>, 페이지 이동, 옷 바꾸기까지!' },
            { sel: '#dd-key-floating, .dd-key-floating', icon: '🔑', iconColor: 'amber', title: 'API 키',  body: '<b>Gemini API 키</b>를 설정하면 AI 튜터, 작문 채점, 叮叮 AI 채팅을 사용할 수 있어요.' }
        ],
        story: [
            { sel: '.dd-story-cover, .dd-story-card', icon: '📖', iconColor: 'purple', title: '스토리 시작', body: '<b>"시작하기"</b>를 클릭하면 인터랙티브 스토리가 시작돼요!' },
            { sel: '.dd-asst-character', icon: '🐼', iconColor: 'pink', title: '도움 요청', body: '모르는 단어가 있으면 <b>叮叮에게 물어보세요!</b>' }
        ],
        newsletters: [
            { sel: '.dd-nl-grid, .dd-nl-card:first-child', icon: '📰', iconColor: 'purple', title: '뉴스레터', body: '카드를 <b>클릭하면 전문</b>을 읽을 수 있어요!' }
        ],
        newsletter: [
            { sel: '.dd-nld-page, .dd-nl-article', icon: '📖', iconColor: 'blue', title: '본문 읽기', body: '중국어 본문을 읽으며 <b>독해력</b>을 키워보세요!' }
        ],
        vocabulary: [
            { sel: '#dd-vocab-app, .dd-vocab-panel', icon: '📚', iconColor: 'green', title: 'AI 단어장', body: '<b>플래시카드</b>로 암기하고 <b>4종 미니게임</b>으로 복습하세요!' }
        ]
    };

    function startCoachmarks() {
        if (coach.active) return; // 이미 실행 중이면 중복 호출 방지
        var steps = COACH_STEPS[PAGE];
        if (!steps || !steps.length) return;
        coach.steps = steps.filter(function (s) { return !!document.querySelector(s.sel); });
        if (!coach.steps.length) return;
        coach.current = 0;
        coach.active = true;
        coach.overlay = el('div', 'dd-coach-overlay');
        coach.spotlight = el('div', 'dd-coach-spotlight');
        coach.tooltip = el('div', 'dd-coach-tooltip');
        coach.overlay.appendChild(coach.spotlight);
        coach.overlay.appendChild(coach.tooltip);
        document.body.appendChild(coach.overlay);
        coach.overlay.addEventListener('click', function (e) { if (e.target === coach.overlay) nextCoach(); });
        showCoachStep(0);
        localStorage.setItem('dd_coach_' + PAGE, '1');
    }

    function showCoachStep(idx) {
        var step = coach.steps[idx];
        if (!step) { endCoachmarks(); return; }
        var target = document.querySelector(step.sel);
        if (!target) { nextCoach(); return; }
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(function () {
            posSpotlight(target);
            buildTip(step, idx);
            posTip(target);
        }, 350);
    }

    function posSpotlight(t) {
        // 오버레이가 position:fixed 이므로 자식 spotlight 은 뷰포트 좌표를 그대로 사용해야 함.
        // window.scrollX/Y 를 더하면 스크롤된 페이지(예: 핵심 기능 그리드)에서 spotlight 가
        // 고정 오버레이 영역 밖으로 밀려나 테두리가 사라진다.
        var r = t.getBoundingClientRect(); var p = 8;
        coach.spotlight.style.left   = (r.left - p) + 'px';
        coach.spotlight.style.top    = (r.top  - p) + 'px';
        coach.spotlight.style.width  = (r.width  + p * 2) + 'px';
        coach.spotlight.style.height = (r.height + p * 2) + 'px';
    }

    function buildTip(step, idx) {
        var total = coach.steps.length;
        var dots = '';
        for (var i = 0; i < total; i++) {
            var c = 'dd-coach-dot'; if (i < idx) c += ' is-done'; if (i === idx) c += ' is-active';
            dots += '<span class="' + c + '"></span>';
        }
        coach.tooltip.innerHTML =
            '<div class="dd-coach-header">' +
                '<div class="dd-coach-header-icon dd-coach-header-icon--' + (step.iconColor || 'pink') + '">' + step.icon + '</div>' +
                '<div class="dd-coach-title">' + step.title + '</div>' +
                '<span class="dd-coach-counter">' + (idx + 1) + '/' + total + '</span>' +
            '</div>' +
            '<div class="dd-coach-body">' + step.body + '</div>' +
            '<div class="dd-coach-footer">' +
                '<div class="dd-coach-progress">' + dots + '</div>' +
                '<div class="dd-coach-actions">' +
                    '<button class="dd-coach-btn dd-coach-btn--skip" data-act="skip">건너뛰기</button>' +
                    (idx > 0 ? '<button class="dd-coach-btn dd-coach-btn--prev" data-act="prev">이전</button>' : '') +
                    '<button class="dd-coach-btn dd-coach-btn--next" data-act="next">' + (idx === total - 1 ? '완료! 🎉' : '다음 →') + '</button>' +
                '</div>' +
            '</div>';
        coach.tooltip.style.animation = 'none'; coach.tooltip.offsetHeight; coach.tooltip.style.animation = '';
        coach.tooltip.querySelectorAll('[data-act]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var a = btn.getAttribute('data-act');
                if (a === 'skip') endCoachmarks(); if (a === 'prev') prevCoach(); if (a === 'next') nextCoach();
            });
        });
    }

    function posTip(target) {
        var r = target.getBoundingClientRect();
        var tw = 320, th = coach.tooltip.offsetHeight || 180;
        var vw = window.innerWidth, vh = window.innerHeight, gap = 16;
        var pos, left, top;
        // spotlight 과 동일하게 fixed 오버레이 기준 뷰포트 좌표 사용 (scrollX/Y 더하지 않음)
        if (r.bottom + gap + th < vh) {
            pos = 'bottom'; top = r.bottom + gap;
            left = Math.max(12, Math.min(r.left + r.width / 2 - tw / 2, vw - tw - 12));
        } else if (r.top - gap - th > 0) {
            pos = 'top'; top = r.top - gap - th;
            left = Math.max(12, Math.min(r.left + r.width / 2 - tw / 2, vw - tw - 12));
        } else if (r.right + gap + tw < vw) {
            pos = 'right'; top = r.top + r.height / 2 - th / 2; left = r.right + gap;
        } else {
            pos = 'left'; top = r.top + r.height / 2 - th / 2; left = r.left - gap - tw;
        }
        coach.tooltip.setAttribute('data-pos', pos);
        coach.tooltip.style.left = left + 'px';
        coach.tooltip.style.top  = top + 'px';
    }

    function nextCoach() { coach.current++; if (coach.current >= coach.steps.length) endCoachmarks(); else showCoachStep(coach.current); }
    function prevCoach() { if (coach.current > 0) { coach.current--; showCoachStep(coach.current); } }
    function endCoachmarks() {
        coach.active = false;
        if (coach.overlay) { coach.overlay.remove(); coach.overlay = null; }
    }

    function maybeAutoCoach() {
        if (PAGE === 'lesson' && !localStorage.getItem('dd_coach_lesson')) {
            setTimeout(startCoachmarks, 3500);
        }
        // 랜딩은 welcomeSequence가 있을 때는 dismissWelcome 콜백에서 호출됨.
        // welcome을 이미 본 사용자에게도 코치마크는 1회 보장 (별도 키).
        if (PAGE === 'landing' && !localStorage.getItem('dd_coach_landing')) {
            // welcomeSequence가 동시에 뜨면 그쪽에 양보 (welcomeScreen 존재 여부 확인)
            setTimeout(function () {
                if (dom.welcomeScreen) return; // welcome 진행 중이면 거기서 트리거
                startCoachmarks();
            }, 2800);
        }
    }

    /* ============================================================
       Voice — Speech Recognition + TTS
       ============================================================ */
    function toggleVoice() {
        if (state.isListening) { stopListening(); return; }
        startListening();
    }

    function startListening() {
        if (!SpeechRecognition || state.isListening) return;
        var recog = new SpeechRecognition();
        recog.lang = 'ko-KR';
        recog.interimResults = true;
        recog.maxAlternatives = 1;
        recog.continuous = false;

        state.recognition = recog;
        state.isListening = true;
        updateMicUI(true);

        recog.onresult = function (e) {
            var transcript = '';
            for (var i = e.resultIndex; i < e.results.length; i++) {
                transcript += e.results[i][0].transcript;
            }
            if (dom.input) dom.input.value = transcript;
            /* Auto-send on final result */
            if (e.results[e.results.length - 1].isFinal) {
                stopListening();
                if (transcript.trim()) {
                    lastInputWasVoice = true;
                    setTimeout(sendUserMsg, 200);
                }
            }
        };

        recog.onerror = function (e) {
            stopListening();
            if (e.error === 'not-allowed') {
                addMsg('bot', '🎤 마이크 권한이 필요해!\n브라우저 설정에서 마이크를 허용해줘.');
            } else if (e.error !== 'aborted' && e.error !== 'no-speech') {
                addMsg('bot', '🎤 음성 인식 오류: ' + e.error);
            }
        };

        recog.onend = function () { stopListening(); };

        try { recog.start(); } catch (err) { stopListening(); }
    }

    function stopListening() {
        state.isListening = false;
        if (state.recognition) {
            try { state.recognition.abort(); } catch (e) {}
            state.recognition = null;
        }
        updateMicUI(false);
    }

    function updateMicUI(listening) {
        if (!dom.micBtn) return;
        if (listening) {
            dom.micBtn.innerHTML = MIC_STOP_ICON;
            dom.micBtn.classList.add('is-listening');
            dom.micBtn.title = '음성 인식 중지';
        } else {
            dom.micBtn.innerHTML = MIC_ICON;
            dom.micBtn.classList.remove('is-listening');
            dom.micBtn.title = '음성으로 말하기';
        }
    }

    /** TTS — speak Chinese parts only (for 듣기 button) */
    function speakChinese(text) {
        if (!state.ttsEnabled || !window.speechSynthesis) return;
        var zhParts = text.match(/[一-鿿㐀-䶿]+[^\n]*/g);
        if (!zhParts || !zhParts.length) return;
        var toSpeak = zhParts.map(function (s) {
            return s.replace(/[^一-鿿㐀-䶿，。？！、：；\s]/g, '');
        }).filter(function (s) { return s.length >= 2; }).join('。');
        if (!toSpeak) return;

        window.speechSynthesis.cancel();
        var utter = new SpeechSynthesisUtterance(toSpeak);
        utter.lang = 'zh-CN';
        utter.rate = 0.85;
        utter.pitch = 1.0;
        utter.volume = 0.9;
        pickFriendlyVoice(utter, 'zh');
        window.speechSynthesis.speak(utter);
    }

    /** TTS — speak Korean + Chinese segments sequentially in the right voice */
    function speakBilingual(text, onEnd) {
        var fin = function () { if (onEnd) { var f = onEnd; onEnd = null; f(); } };
        if (!state.ttsEnabled || !window.speechSynthesis) { setTimeout(fin, 300); return; }
        if (!text) { fin(); return; }
        var clean = text.replace(/[🐼🎮📚💪✨📖🔊📊🧩📈🎬🌱📗📕✅💡🎯📰🃏✏️🗺️🔑🎭🧥👘🎓👗👕🎉🏨🏥🏦🍜🚕🛍️🧭📑📋▶️🐰🌸🎨⚙️💬🎤]/g, '')
                        .replace(/叮叮/g, '딩딩')
                        .replace(/DingDong/gi, '딩동');
        var segments = splitByLanguage(clean);
        if (!segments.length) { fin(); return; }
        window.speechSynthesis.cancel();
        segments.forEach(function (seg, idx) {
            var u = new SpeechSynthesisUtterance(seg.text);
            u.lang = seg.lang;
            u.rate = seg.lang === 'zh-CN' ? 0.85 : 1.0;
            u.pitch = 1.0;
            u.volume = 0.9;
            pickFriendlyVoice(u, seg.lang === 'zh-CN' ? 'zh' : 'ko');
            if (idx === segments.length - 1 && onEnd) { u.onend = fin; u.onerror = fin; }
            window.speechSynthesis.speak(u);
        });
        /* onend가 안 오는 환경(일부 브라우저) 대비 안전장치 */
        if (onEnd) setTimeout(fin, Math.max(2500, clean.length * 140));
    }

    /** Split mixed Ko/Zh text into per-language utterance segments */
    function splitByLanguage(text) {
        var raw = [];
        var cur = { lang: null, text: '' };
        for (var i = 0; i < text.length; i++) {
            var ch = text[i];
            var lang = null;
            if (/[一-鿿㐀-䶿]/.test(ch)) lang = 'zh-CN';
            else if (/[가-힯ᄀ-ᇿ]/.test(ch)) lang = 'ko-KR';
            if (!lang) { cur.text += ch; continue; }
            if (cur.lang === null) { cur.lang = lang; cur.text += ch; }
            else if (cur.lang === lang) { cur.text += ch; }
            else {
                if (cur.text.trim()) raw.push(cur);
                cur = { lang: lang, text: ch };
            }
        }
        if (cur.lang && cur.text.trim()) raw.push(cur);
        return raw
            .map(function (s) { return { lang: s.lang, text: s.text.trim() }; })
            .filter(function (s) { return s.text.length > 0; });
    }

    function pickFriendlyVoice(utter, lang) {
        var voices = speechSynthesis.getVoices();
        var preferred = lang === 'ko'
            ? ['Microsoft SunHi', 'Yuna', 'Heami', 'Google 한국어', 'Siri']
            : ['Microsoft Xiaoxiao', 'Microsoft Xiaoyi', 'Tingting', 'Lili', 'Google 普通话', 'Siri'];
        for (var p = 0; p < preferred.length; p++) {
            for (var v = 0; v < voices.length; v++) {
                if (voices[v].name.indexOf(preferred[p]) !== -1 && voices[v].lang.indexOf(lang) === 0) {
                    utter.voice = voices[v]; return;
                }
            }
        }
        for (var i = 0; i < voices.length; i++) {
            if (voices[i].lang.indexOf(lang) === 0) { utter.voice = voices[i]; return; }
        }
    }

    /** Add a TTS play button to bot messages with Chinese content */
    function addTTSButton(msgBubble, text) {
        var hasChinese = /[一-鿿]/.test(text);
        if (!hasChinese || !window.speechSynthesis) return;
        var btn = el('button', 'dd-asst-tts-btn');
        btn.innerHTML = SPEAKER_ICON + ' 듣기';
        btn.title = '중국어 발음 듣기';
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            speakChinese(text);
        });
        msgBubble.appendChild(btn);
    }

    /* ============================================================
       Helpers
       ============================================================ */
    function el(tag, cls) { var e = document.createElement(tag); if (cls) e.className = cls; return e; }
    function escHtml(s) { return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>'); }

    /* ============================================================
       Welcome Screen — 풀스크린 대화형 환영 (매일 1회)
       ============================================================ */
    var welcome = { speaking: false, listening: false, recog: null, dismissed: false };

    function shouldShowWelcomeToday() {
        var today = new Date();
        var ymd = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');
        var last = localStorage.getItem('dd_welcomed_date');
        return last !== ymd;
    }

    function welcomeSequence() {
        /* 매일 1회 — 날짜(YYYY-MM-DD)를 키로 저장해서 그날 다시 들어와도 안 뜸 */
        var today = new Date();
        var ymd = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');
        localStorage.setItem('dd_welcomed_date', ymd);
        /* 레거시 키도 함께 정리 — 한 번 진입한 사람이 더 이상 막히지 않도록 */
        localStorage.removeItem('dd_welcomed');

        /* Hide normal character */
        dom.character.style.display = 'none';

        /* Full-screen white welcome */
        var screen = el('div', 'dd-welcome-screen');
        screen.innerHTML =
            '<div class="dd-welcome-content">' +
                '<div class="dd-welcome-bubble" id="dd-welcome-bubble"><span></span></div>' +
                '<div class="dd-welcome-panda">' + PANDA_BODY + '</div>' +
                '<div class="dd-welcome-listen" id="dd-welcome-listen">' +
                    '<button class="dd-welcome-mic" id="dd-welcome-mic">' + MIC_ICON + '</button>' +
                    '<p class="dd-welcome-listen-text" id="dd-welcome-listen-text">잠시만 기다려줘...</p>' +
                '</div>' +
                '<button class="dd-welcome-start" id="dd-welcome-start">학습 시작하기 &rarr;</button>' +
            '</div>';
        document.body.appendChild(screen);
        dom.welcomeScreen = screen;

        /* Bind */
        screen.querySelector('#dd-welcome-start').addEventListener('click', dismissWelcome);
        screen.querySelector('#dd-welcome-mic').addEventListener('click', function () {
            if (welcome.listening) stopWelcomeListening();
            else if (!welcome.speaking) startWelcomeListening();
        });

        /* Phase 1: Greeting after panda entrance */
        setTimeout(function () {
            welcomeSpeak('안녕! 나는 叮叮이야!\nDingDong에 온 걸 환영해!\n중국어 학습, 내가 도와줄게!\n궁금한 거 있으면 말해봐!', function () {
                showWelcomeMicReady();
            });
        }, 1200);
    }

    /* ── Welcome TTS — 자연스러운 톤으로 음성 재생 ── */
    function welcomeSpeak(text, onEnd) {
        if (welcome.dismissed) return;
        welcome.speaking = true;
        updateWelcomeMicUI('speaking');

        var bubble = document.getElementById('dd-welcome-bubble');
        if (bubble) {
            bubble.querySelector('span').innerHTML = escHtml(text);
            bubble.classList.add('is-visible');
        }

        if (!window.speechSynthesis) {
            welcome.speaking = false;
            if (onEnd) setTimeout(onEnd, 2000);
            return;
        }
        window.speechSynthesis.cancel();

        var doSpeak = function () {
            var clean = text.replace(/\n/g, ' ').replace(/[🐼🎮📚💪✨]/g, '').replace(/叮叮/g, '딩딩').replace(/DingDong/gi, '딩동');
            var u = new SpeechSynthesisUtterance(clean);
            u.lang = 'ko-KR';
            u.rate = 1.0;
            u.pitch = 1.0;
            u.volume = 0.85;
            pickFriendlyVoice(u, 'ko');
            u.onend = function () { welcome.speaking = false; if (onEnd) onEnd(); };
            u.onerror = function () { welcome.speaking = false; if (onEnd) onEnd(); };
            speechSynthesis.speak(u);
        };

        var v = speechSynthesis.getVoices();
        if (v.length > 0) { doSpeak(); }
        else {
            speechSynthesis.addEventListener('voiceschanged', function h() {
                speechSynthesis.removeEventListener('voiceschanged', h);
                doSpeak();
            });
        }
    }

    /* ── Welcome Mic ── */
    function showWelcomeMicReady() {
        if (welcome.dismissed) return;
        updateWelcomeMicUI('ready');
        setTimeout(function () { startWelcomeListening(); }, 600);
    }

    function startWelcomeListening() {
        if (!SpeechRecognition || welcome.speaking || welcome.listening || welcome.dismissed) return;

        var recog = new SpeechRecognition();
        recog.lang = 'ko-KR';
        recog.interimResults = true;
        recog.maxAlternatives = 1;
        recog.continuous = false;
        welcome.recog = recog;
        welcome.listening = true;
        updateWelcomeMicUI('listening');

        recog.onresult = function (e) {
            var transcript = '';
            for (var i = e.resultIndex; i < e.results.length; i++) {
                transcript += e.results[i][0].transcript;
            }
            /* Show what user says in bubble */
            var bubble = document.getElementById('dd-welcome-bubble');
            if (bubble) bubble.querySelector('span').innerHTML = '🎤 ' + escHtml(transcript);

            if (e.results[e.results.length - 1].isFinal && transcript.trim()) {
                stopWelcomeListening();
                handleWelcomeInput(transcript.trim());
            }
        };

        recog.onerror = function (e) {
            stopWelcomeListening();
            if (e.error === 'no-speech') setTimeout(function () { showWelcomeMicReady(); }, 800);
        };
        recog.onend = function () {
            if (welcome.listening) { welcome.listening = false; updateWelcomeMicUI('ready'); }
        };

        try { recog.start(); } catch (e) { welcome.listening = false; updateWelcomeMicUI('ready'); }
    }

    function stopWelcomeListening() {
        welcome.listening = false;
        if (welcome.recog) { try { welcome.recog.abort(); } catch (e) {} welcome.recog = null; }
        updateWelcomeMicUI('thinking');
    }

    function updateWelcomeMicUI(mode) {
        var mic = document.getElementById('dd-welcome-mic');
        var txt = document.getElementById('dd-welcome-listen-text');
        if (!mic) return;
        mic.classList.remove('is-listening', 'is-speaking');
        if (mode === 'listening') {
            mic.innerHTML = MIC_STOP_ICON;
            mic.classList.add('is-listening');
            if (txt) txt.textContent = '듣고 있어요...';
        } else if (mode === 'speaking') {
            mic.innerHTML = SPEAKER_ICON;
            mic.classList.add('is-speaking');
            if (txt) txt.textContent = '말하는 중...';
        } else if (mode === 'thinking') {
            mic.innerHTML = MIC_ICON;
            if (txt) txt.textContent = '생각하는 중...';
        } else {
            mic.innerHTML = MIC_ICON;
            if (txt) txt.textContent = '🎤 말해보세요!';
        }
    }

    /* ── Welcome Conversation ── */
    /* 환영 화면에서도 페이지 이동을 실제로 수행 (NAV_ROUTES 재사용).
       이동 의도가 명확하면 안내만 하지 말고 바로 데려간다. */
    function welcomeNavTo(path, label) {
        var done = false;
        var go = function () { if (done) return; done = true; window.location.href = path; };
        welcomeSpeak('좋아! ' + label + ' 페이지로 데려갈게! 🚀', go);
        setTimeout(go, 2800); /* TTS onEnd가 안 와도 이동 보장 */
    }

    function tryWelcomeNavigate(text) {
        var t = text.toLowerCase().replace(/\s+/g, ' ').trim();
        /* 1) 명시적 목적지 (네비 동사 있을 때): 단어장/스토리/뉴스레터/홈/강좌목록 */
        if (/(이동|가줘|가자|갈래|열어|보여|페이지|으로 가|로 가|데려가|넘어가|보고\s*싶|로 이동|으로 이동)/.test(t)) {
            for (var i = 0; i < NAV_ROUTES.length; i++) {
                var route = NAV_ROUTES[i];
                for (var j = 0; j < route.keywords.length; j++) {
                    if (t.indexOf(route.keywords[j]) !== -1) { welcomeNavTo(route.path, route.label); return true; }
                }
            }
        }
        /* 2) "학습 시작하기" / "강좌 보여줘" 등 → 강좌 목록으로 바로 이동 (클릭 안내 대신) */
        if (isCourseStartIntent(text)) { welcomeNavTo('/courses/', '강좌 목록'); return true; }
        return false;
    }

    function handleWelcomeInput(text) {
        var t = text.toLowerCase();
        var response = '';

        /* 강의/강좌 직접 열기 (제목·유형 인식) — 안내 멘트보다 우선 */
        var lessonAct = tryOpenLesson(text);
        if (lessonAct && lessonAct.href) {
            var done = false;
            var go = function () { if (done) return; done = true; window.location.href = lessonAct.href; };
            welcomeSpeak(lessonAct.reply, go);
            setTimeout(go, 2800);
            return;
        }

        /* 페이지 이동 요청이면 실제로 이동 (안내 멘트보다 우선) */
        if (tryWelcomeNavigate(text)) return;

        /* 지식 DB 패턴 매칭 */
        if (/이.*사이트|딩동|여기.*뭐|뭐.*사이트|어떤.*곳/.test(t)) {
            response = 'DingDong은 AI 기반 중국어 학습 사이트야!\n강좌, 퀴즈, 오디오북, 스토리까지\n모두 무료로 제공해!\n아래 버튼 눌러서 둘러봐!';
        } else if (/무료|돈|유료|비용/.test(t)) {
            response = '완전 무료야!\n로그인도 필요 없고\n바로 공부할 수 있어!';
        } else if (/기능|뭐.*있|할.*수/.test(t)) {
            response = 'AI 강좌, 퀴즈, 오디오북,\n스토리북, 단어장, 작문채점,\n역할극까지 다양하게 있어!\n아래 버튼 눌러서 확인해봐!';
        } else if (/강좌|강의|수업|공부|배우/.test(t)) {
            welcomeNavTo('/courses/', '강좌 목록'); return;
        } else if (/스토리|이야기|게임/.test(t)) {
            response = '인터랙티브 스토리!\n선택에 따라 이야기가 달라지는\n재밌는 중국어 학습이야!';
        } else if (/단어|어휘|암기/.test(t)) {
            response = '단어장에서 플래시카드로 암기하고\n4종 미니게임으로 복습할 수 있어!\n아래 버튼 눌러서 시작해봐!';
        } else if (/안녕|반가|hello|hi|감사|고마/.test(t)) {
            response = '반가워!\n중국어 배우러 왔구나?\n아래 버튼 눌러서 시작해봐!';
        } else if (/중국어|중국|한자|니하오/.test(t)) {
            response = '중국어에 관심 있구나! 좋아!\n입문부터 고급까지\n맞춤 강좌가 준비돼 있어!';
        } else if (/시작|들어가|갈래|넘어가/.test(t)) {
            welcomeNavTo('/courses/', '강좌 목록'); return;
        } else if (/누구|이름|소개|너.*뭐/.test(t)) {
            response = '나는 叮叮! 판다 학습도우미야!\n중국어 공부할 때 항상 옆에서\n도와줄게! 뭐든 물어봐~';
        } else if (/어떻게|방법|처음/.test(t)) {
            response = '간단해! 아래 버튼 누르면\n강좌 목록이 나와.\n마음에 드는 거 클릭하면 바로 시작!';
        } else if (/뉴스|뉴스레터|읽기/.test(t)) {
            response = '뉴스레터에서 중국 트렌드를\n중국어로 읽으며 독해력을 키울 수 있어!';
        } else if (state.hasApiKey) {
            askWelcomeGemini(text);
            return;
        } else {
            response = '"' + text + '" 이라고 했구나!\n학습을 시작하면 더 자세히 도와줄게!\n아래 버튼을 눌러봐!';
        }

        welcomeSpeak(response, function () { showWelcomeMicReady(); });
    }

    function askWelcomeGemini(userText) {
        var sys = '너는 叮叮(Dīngding), 귀여운 판다 학습도우미야. ' +
            'DingDong 중국어 학습 플랫폼 환영 화면에서 학습자와 음성 대화 중이야. ' +
            '한국어 반말로 짧게(80자 이내) 답변해. 이모지 1~2개 사용. ' +
            '학습자가 궁금해하면 DingDong의 기능(강좌, 퀴즈, 오디오북, 스토리, 단어장)을 자연스럽게 소개해.\n' +
            '페이지 이동: 학습자가 강좌 목록/단어장/스토리/뉴스레터/홈으로 가달라고 하면 ' +
            '답변 끝에 "NAV:/courses/" 형식을 붙여. ' +
            '경로: / (홈), /courses/ (강좌 목록), /stories/ (스토리), /newsletters/ (뉴스레터), /vocabulary/ (단어장).';
        var body = {
            contents: [{ role: 'user', parts: [{ text: sys + '\n\n사용자: ' + userText }] }],
            generationConfig: { maxOutputTokens: 200, temperature: 0.8 }
        };
        var url = 'https://generativelanguage.googleapis.com/v1beta/models/' + MODELS[0] +
                  ':generateContent';
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'x-goog-api-key': state.apiKey },
            body: JSON.stringify(body)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var txt = data.candidates[0].content.parts[0].text.replace(/\*\*(.*?)\*\*/g, '$1');
            var navCmd = txt.match(/NAV:(\/[a-z-]*\/?)/);
            if (navCmd) {
                var navPath = navCmd[1];
                txt = txt.replace(/NAV:\/[a-z-]*\/?/g, '').trim();
                if (!txt) txt = '좋아! 바로 데려갈게! 🚀';
                var navDone = false;
                var navGo = function () { if (navDone) return; navDone = true; window.location.href = navPath; };
                welcomeSpeak(txt, navGo);
                setTimeout(navGo, 2800);
                return;
            }
            welcomeSpeak(txt, function () { showWelcomeMicReady(); });
        })
        .catch(function () {
            welcomeSpeak('음, 좀 어렵네!\n학습을 시작하면 더 잘 도와줄게!', function () { showWelcomeMicReady(); });
        });
    }

    /* ── Dismiss Welcome ── */
    function dismissWelcome() {
        if (welcome.dismissed) return;
        welcome.dismissed = true;
        if (window.speechSynthesis) window.speechSynthesis.cancel();
        if (welcome.recog) { try { welcome.recog.abort(); } catch (e) {} }

        var screen = dom.welcomeScreen;
        if (screen) {
            screen.classList.add('is-dismissing');
            setTimeout(function () { screen.remove(); dom.welcomeScreen = null; }, 800);
        }
        dom.character.style.display = '';
        startBubbleRotation();
        startWakeListening(); /* 환영 끝나면 "딩딩아" 호출 대기 시작 */

        // welcome 끝나면 랜딩 코치마크 자동 시작 (1회만)
        if (PAGE === 'landing' && !localStorage.getItem('dd_coach_landing')) {
            setTimeout(function () {
                startCoachmarks();
            }, 1200);
        }
    }

    /* ============================================================
       Scroll-to-Top Button — 叮叮 왼쪽에 배치
       ============================================================ */
    function buildTopButton() {
        var btn = el('button', 'dd-top-btn');
        btn.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>';
        btn.title = '맨 위로';
        btn.style.display = 'none';
        document.body.appendChild(btn);

        btn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        var visible = false;
        window.addEventListener('scroll', function () {
            var shouldShow = window.scrollY > 300;
            if (shouldShow !== visible) {
                visible = shouldShow;
                btn.style.display = visible ? '' : 'none';
            }
        }, { passive: true });
    }

    /* ============================================================
       Init
       ============================================================ */
    function init() {
        state.apiKey = localStorage.getItem('dd_student_gemini_key') || '';
        state.hasApiKey = !!state.apiKey;
        build();
        buildTopButton();
        if (PAGE === 'landing' && shouldShowWelcomeToday()) {
            welcomeSequence();
            /* 환영 화면이 끝나면(dismissWelcome) 호출 대기가 시작됨 */
        } else {
            startBubbleRotation();
            /* "딩딩아" 호출 대기 시작 — 마우스 없이 음성으로 부를 수 있게 */
            setTimeout(startWakeListening, 1200);
        }
        maybeAutoCoach();
        window.addEventListener('storage', function (e) {
            if (e.key === 'dd_student_gemini_key') { state.apiKey = e.newValue || ''; state.hasApiKey = !!state.apiKey; }
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();

})();
