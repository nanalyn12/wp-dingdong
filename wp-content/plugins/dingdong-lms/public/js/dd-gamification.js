/**
 * DDGamification — 스트릭, XP, 레벨, 업적, 데일리 챌린지, 통계
 * 叮叮 감정 시스템 + 스마트 추천 + 미니퀴즈 통합
 * v1.0
 */
var DDGamification = (function () {
    'use strict';

    var STORAGE_KEY = 'dd_gamification';

    /* ─────────────────────────────────────
       Level & XP config
       ───────────────────────────────────── */
    var LEVELS = [
        { level: 1,  title: '새싹',       icon: '🌱', xpNeeded: 0 },
        { level: 2,  title: '초보 학습자',  icon: '📗', xpNeeded: 50 },
        { level: 3,  title: '열정 학습자',  icon: '🔥', xpNeeded: 150 },
        { level: 4,  title: '중급 도전자',  icon: '📈', xpNeeded: 350 },
        { level: 5,  title: '실력파',      icon: '⭐', xpNeeded: 600 },
        { level: 6,  title: '고급 학습자',  icon: '💎', xpNeeded: 1000 },
        { level: 7,  title: '중국어 달인',  icon: '🏆', xpNeeded: 1500 },
        { level: 8,  title: '마스터',      icon: '👑', xpNeeded: 2200 },
        { level: 9,  title: '그랜드마스터', icon: '🐉', xpNeeded: 3200 },
        { level: 10, title: '레전드',      icon: '🌟', xpNeeded: 5000 }
    ];

    var XP_REWARDS = {
        visit_lesson:   5,
        complete_tab:   10,
        quiz_pass:      25,
        quiz_perfect:   50,
        vocab_add:      3,
        vocab_master:   15,
        roleplay_done:  20,
        daily_challenge: 30,
        streak_3:       20,
        streak_7:       50,
        streak_14:      100,
        streak_30:      200,
        mini_quiz:      10,
        story_ending:   15
    };

    /* ─────────────────────────────────────
       Achievements
       ───────────────────────────────────── */
    var ACHIEVEMENTS = [
        { id: 'first_lesson',  icon: '📖', title: '첫 발걸음',     desc: '첫 강의 방문', condition: function (d) { return d.stats.lessonsVisited >= 1; } },
        { id: 'quiz_ace',      icon: '🎯', title: '퀴즈 에이스',   desc: '퀴즈 만점 달성', condition: function (d) { return d.stats.perfectQuizzes >= 1; } },
        { id: 'vocab_10',      icon: '📝', title: '단어 수집가',   desc: '단어 10개 저장', condition: function (d) { return d.stats.vocabAdded >= 10; } },
        { id: 'vocab_50',      icon: '📚', title: '단어 박사',     desc: '단어 50개 저장', condition: function (d) { return d.stats.vocabAdded >= 50; } },
        { id: 'streak_3',      icon: '🔥', title: '3일 연속!',     desc: '3일 연속 학습', condition: function (d) { return d.streak.best >= 3; } },
        { id: 'streak_7',      icon: '💪', title: '일주일 달성',   desc: '7일 연속 학습', condition: function (d) { return d.streak.best >= 7; } },
        { id: 'streak_30',     icon: '🏆', title: '한 달 전사',    desc: '30일 연속 학습', condition: function (d) { return d.streak.best >= 30; } },
        { id: 'roleplay_first',icon: '🎭', title: '역할극 데뷔',   desc: '첫 역할극 완료', condition: function (d) { return d.stats.roleplaysCompleted >= 1; } },
        { id: 'daily_5',       icon: '⭐', title: '챌린지 5회',    desc: '데일리 챌린지 5회 완료', condition: function (d) { return d.stats.dailyChallenges >= 5; } },
        { id: 'xp_500',        icon: '💎', title: 'XP 수확자',     desc: '총 500 XP 획득', condition: function (d) { return d.xp >= 500; } },
        { id: 'xp_2000',       icon: '👑', title: 'XP 마스터',     desc: '총 2000 XP 획득', condition: function (d) { return d.xp >= 2000; } },
        { id: 'lessons_10',    icon: '🎓', title: '열공 학생',     desc: '10개 강의 방문', condition: function (d) { return d.stats.lessonsVisited >= 10; } },
        { id: 'stories_3',     icon: '🗺️', title: '스토리 탐험가',  desc: '스토리 엔딩 3개 수집', condition: function (d) { return d.stats.storyEndings >= 3; } },
        { id: 'mini_quiz_10',  icon: '⚡', title: '퀴즈 번개',     desc: '미니퀴즈 10회 정답', condition: function (d) { return d.stats.miniQuizCorrect >= 10; } }
    ];

    /* ─────────────────────────────────────
       Daily Challenge templates
       ───────────────────────────────────── */
    var DAILY_CHALLENGES = [
        { type: 'translate', q: '"안녕하세요"를 중국어로?', a: ['你好', 'nǐ hǎo', '니하오'], hint: 'nǐ hǎo' },
        { type: 'translate', q: '"감사합니다"를 중국어로?', a: ['谢谢', 'xièxie', '시에시에'], hint: 'xiè xie' },
        { type: 'translate', q: '"사랑해"를 중국어로?', a: ['我爱你', 'wǒ ài nǐ'], hint: 'wǒ ài nǐ' },
        { type: 'translate', q: '"밥 먹었어?"를 중국어로?', a: ['你吃饭了吗', '你吃了吗', 'nǐ chī fàn le ma'], hint: 'nǐ chī fàn le ma?' },
        { type: 'translate', q: '"괜찮아"를 중국어로?', a: ['没关系', 'méi guānxi'], hint: 'méi guān xi' },
        { type: 'translate', q: '"얼마예요?"를 중국어로?', a: ['多少钱', 'duōshao qián'], hint: 'duō shao qián?' },
        { type: 'translate', q: '"물 한 잔 주세요"를 중국어로?', a: ['请给我一杯水', '一杯水'], hint: 'qǐng gěi wǒ yī bēi shuǐ' },
        { type: 'meaning', q: '"加油" 무슨 뜻?', a: ['화이팅', '파이팅', '힘내'], hint: '응원할 때 쓰는 말' },
        { type: 'meaning', q: '"好吃" 무슨 뜻?', a: ['맛있다', '맛있어'], hint: '음식이 ___' },
        { type: 'meaning', q: '"漂亮" 무슨 뜻?', a: ['예쁘다', '아름답다', '예뻐'], hint: '외모를 칭찬할 때' },
        { type: 'meaning', q: '"开心" 무슨 뜻?', a: ['기쁘다', '즐겁다', '행복하다'], hint: '기분이 좋을 때' },
        { type: 'meaning', q: '"朋友" 무슨 뜻?', a: ['친구'], hint: 'péng yǒu' },
        { type: 'meaning', q: '"学习" 무슨 뜻?', a: ['공부', '학습', '공부하다'], hint: 'xué xí' },
        { type: 'tone', q: '"妈麻马骂" 각각 몇 성?', a: ['1234', '1 2 3 4'], hint: 'mā má mǎ mà' },
        { type: 'fill', q: '我____中国人 (나는 중국사람이다)', a: ['是', 'shì'], hint: '~이다' },
        { type: 'fill', q: '你____什么名字？ (이름이 뭐예요?)', a: ['叫', 'jiào'], hint: '~라 부르다' },
        { type: 'fill', q: '今天天气很____ (오늘 날씨가 좋다)', a: ['好', 'hǎo'], hint: '좋다' },
        { type: 'count', q: '"我" 몇 획?', a: ['7', '七'], hint: '나, 저' },
        { type: 'translate', q: '"미안해"를 중국어로?', a: ['对不起', 'duìbuqǐ'], hint: 'duì bu qǐ' },
        { type: 'meaning', q: '"再见" 무슨 뜻?', a: ['안녕', '잘 가', '잘가', '안녕히'], hint: 'zài jiàn — 헤어질 때' },
        { type: 'translate', q: '"맛있어요!"를 중국어로?', a: ['好吃', '很好吃', 'hǎo chī'], hint: 'hǎo chī!' },
        { type: 'fill', q: '我喜欢____音乐 (나는 중국 음악을 좋아한다)', a: ['中国', 'zhōngguó'], hint: '중국' },
        { type: 'meaning', q: '"太棒了" 무슨 뜻?', a: ['대단하다', '최고', '굉장하다', '대박'], hint: '감탄할 때 쓰는 말' },
        { type: 'translate', q: '"어디?"를 중국어로?', a: ['哪里', '哪儿', 'nǎlǐ', 'nǎr'], hint: 'nǎ lǐ / nǎr' },
        { type: 'meaning', q: '"回家" 무슨 뜻?', a: ['집에 가다', '귀가', '귀가하다'], hint: 'huí jiā' },
        { type: 'translate', q: '"배고파"를 중국어로?', a: ['我饿了', '饿了', 'wǒ è le'], hint: 'wǒ è le' },
        { type: 'fill', q: '他是我的____（그는 내 친구야）', a: ['朋友', 'péngyou'], hint: 'péng yǒu' },
        { type: 'translate', q: '"화이팅!"을 중국어로?', a: ['加油', 'jiā yóu'], hint: 'jiā yóu!' },
        { type: 'meaning', q: '"上班" 무슨 뜻?', a: ['출근', '출근하다'], hint: 'shàng bān' },
        { type: 'meaning', q: '"下班" 무슨 뜻?', a: ['퇴근', '퇴근하다'], hint: 'xià bān' }
    ];

    /* ─────────────────────────────────────
       Mini Quiz pool (for 叮叮 instant quiz)
       ───────────────────────────────────── */
    var MINI_QUIZZES = [
        { q: '🍎 "사과"를 중국어로?', choices: ['苹果', '香蕉', '西瓜', '草莓'], answer: 0 },
        { q: '☕ "커피"를 중국어로?', choices: ['茶', '牛奶', '咖啡', '果汁'], answer: 2 },
        { q: '🐱 "고양이"를 중국어로?', choices: ['狗', '猫', '鸟', '鱼'], answer: 1 },
        { q: '📱 "핸드폰"을 중국어로?', choices: ['电脑', '电视', '手机', '相机'], answer: 2 },
        { q: '🏫 "학교"를 중국어로?', choices: ['医院', '学校', '银行', '公司'], answer: 1 },
        { q: '🚗 "자동차"를 중국어로?', choices: ['自行车', '飞机', '火车', '汽车'], answer: 3 },
        { q: '👨‍👩‍👧 "가족"을 중국어로?', choices: ['家庭', '朋友', '同学', '老师'], answer: 0 },
        { q: '🌧️ "비"를 중국어로?', choices: ['雪', '风', '雨', '云'], answer: 2 },
        { q: '📚 "책"을 중국어로?', choices: ['笔', '书', '纸', '本'], answer: 1 },
        { q: '🍚 "밥"을 중국어로?', choices: ['面', '饭', '菜', '汤'], answer: 1 },
        { q: '⏰ "시간"을 중국어로?', choices: ['天', '年', '时间', '月'], answer: 2 },
        { q: '🏠 "집"을 중국어로?', choices: ['家', '店', '门', '路'], answer: 0 },
        { q: '🎵 "음악"을 중국어로?', choices: ['电影', '音乐', '游戏', '运动'], answer: 1 },
        { q: '🌸 "꽃"을 중국어로?', choices: ['树', '草', '花', '叶'], answer: 2 },
        { q: '💧 "물"을 중국어로?', choices: ['水', '火', '冰', '雾'], answer: 0 },
        { q: '😊 "행복"을 중국어로?', choices: ['难过', '生气', '幸福', '害怕'], answer: 2 },
        { q: '✈️ "비행기"를 중국어로?', choices: ['火车', '汽车', '轮船', '飞机'], answer: 3 },
        { q: '🍜 "라면"을 중국어로?', choices: ['方便面', '米饭', '饺子', '包子'], answer: 0 },
        { q: '🏥 "병원"을 중국어로?', choices: ['学校', '医院', '银行', '药店'], answer: 1 },
        { q: '👕 "옷"을 중국어로?', choices: ['鞋', '帽子', '衣服', '裤子'], answer: 2 }
    ];

    /* ─────────────────────────────────────
       Emotion system — state definitions
       ───────────────────────────────────── */
    var EMOTIONS = {
        neutral:  { mouth: 'M54 62 Q57 66 60 64 Q63 66 66 62', eyes: 'normal', blush: 0.25, sparkle: false, label: '' },
        happy:    { mouth: 'M52 60 Q56 68 60 66 Q64 68 68 60', eyes: 'happy',  blush: 0.45, sparkle: true,  label: '😊' },
        excited:  { mouth: 'M50 58 Q55 70 60 68 Q65 70 70 58', eyes: 'star',   blush: 0.55, sparkle: true,  label: '🤩' },
        proud:    { mouth: 'M52 61 Q56 67 60 65 Q64 67 68 61', eyes: 'proud',  blush: 0.4,  sparkle: true,  label: '😤✨' },
        sad:      { mouth: 'M54 66 Q57 62 60 63 Q63 62 66 66', eyes: 'sad',    blush: 0.15, sparkle: false, label: '😢' },
        sleepy:   { mouth: 'M56 64 Q60 62 64 64',              eyes: 'sleepy', blush: 0.1,  sparkle: false, label: '😴' },
        thinking: { mouth: 'M56 64 Q62 64 64 62',              eyes: 'think',  blush: 0.2,  sparkle: false, label: '🤔' },
        love:     { mouth: 'M52 60 Q56 68 60 66 Q64 68 68 60', eyes: 'heart',  blush: 0.6,  sparkle: true,  label: '😍' },
        cheering: { mouth: 'M50 58 Q55 70 60 68 Q65 70 70 58', eyes: 'happy',  blush: 0.5,  sparkle: true,  label: '🎉' }
    };

    /* ─────────────────────────────────────
       Data CRUD
       ───────────────────────────────────── */
    function _default() {
        return {
            xp: 0,
            level: 1,
            streak: { current: 0, best: 0, lastDate: null },
            achievements: [],
            stats: {
                lessonsVisited: 0,
                tabsCompleted: 0,
                quizzesPassed: 0,
                perfectQuizzes: 0,
                vocabAdded: 0,
                vocabMastered: 0,
                roleplaysCompleted: 0,
                dailyChallenges: 0,
                miniQuizCorrect: 0,
                storyEndings: 0,
                totalStudyMinutes: 0
            },
            daily: { date: null, challengeIdx: null, completed: false },
            emotion: 'neutral',
            emotionUntil: 0,
            calendarDays: [],
            sessionStart: null,
            combo: { current: 0, best: 0 },
            shop: {
                streakFreezes: 0,
                hintTokens: 0,
                unlockedCostumes: [],
                freezeActive: null
            },
            legendary: {}
        };
    }

    function load() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return _default();
            var d = JSON.parse(raw);
            var def = _default();
            Object.keys(def).forEach(function (k) {
                if (d[k] === undefined) d[k] = def[k];
            });
            Object.keys(def.stats).forEach(function (k) {
                if (d.stats[k] === undefined) d.stats[k] = def.stats[k];
            });
            return d;
        } catch (e) { return _default(); }
    }

    function save(d) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(d));
    }

    /* ─────────────────────────────────────
       Date helpers
       ───────────────────────────────────── */
    function todayStr() {
        var d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function yesterdayStr() {
        var d = new Date();
        d.setDate(d.getDate() - 1);
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function daysBetween(dateStr1, dateStr2) {
        var d1 = new Date(dateStr1), d2 = new Date(dateStr2);
        return Math.round(Math.abs(d2 - d1) / 86400000);
    }

    /* ─────────────────────────────────────
       Streak logic
       ───────────────────────────────────── */
    function updateStreak(d) {
        var today = todayStr();
        if (d.streak.lastDate === today) return;
        if (d.streak.lastDate === yesterdayStr()) {
            d.streak.current += 1;
        } else if (d.streak.lastDate && d.streak.current > 0) {
            if (_useStreakFreeze(d)) {
                d.streak.current += 1;
                _pendingNotifications.push({ type: 'freeze', text: '스트릭 프리즈 사용! 🧊 연속 기록 보호됨!' });
            } else {
                d.streak.current = 1;
            }
        } else {
            d.streak.current = 1;
        }
        d.streak.lastDate = today;
        if (d.streak.current > d.streak.best) d.streak.best = d.streak.current;
        if (d.calendarDays.indexOf(today) === -1) d.calendarDays.push(today);
        if (d.calendarDays.length > 90) d.calendarDays = d.calendarDays.slice(-90);
    }

    function _useStreakFreeze(d) {
        if (!d.shop) return false;
        if (d.shop.freezeActive === yesterdayStr()) return true;
        if (d.shop.streakFreezes > 0) {
            d.shop.streakFreezes--;
            d.shop.freezeActive = yesterdayStr();
            return true;
        }
        return false;
    }

    /* ─────────────────────────────────────
       XP & Level
       ───────────────────────────────────── */
    var _pendingNotifications = [];

    function addXP(d, amount, reason) {
        var oldLevel = d.level;
        d.xp += amount;
        d.level = computeLevel(d.xp);
        _pendingNotifications.push({ type: 'xp', amount: amount, reason: reason });
        if (d.level > oldLevel) {
            var lvl = LEVELS[d.level - 1] || LEVELS[LEVELS.length - 1];
            _pendingNotifications.push({ type: 'levelup', level: d.level, title: lvl.title, icon: lvl.icon });
        }
    }

    function computeLevel(xp) {
        var lvl = 1;
        for (var i = LEVELS.length - 1; i >= 0; i--) {
            if (xp >= LEVELS[i].xpNeeded) { lvl = LEVELS[i].level; break; }
        }
        return lvl;
    }

    function getLevelInfo(level) {
        return LEVELS[Math.min(level, LEVELS.length) - 1] || LEVELS[0];
    }

    function getXPToNext(d) {
        var next = LEVELS[d.level] || null;
        if (!next) return { current: d.xp, needed: d.xp, percent: 100 };
        var prev = LEVELS[d.level - 1] || LEVELS[0];
        var cur = d.xp - prev.xpNeeded;
        var needed = next.xpNeeded - prev.xpNeeded;
        return { current: cur, needed: needed, percent: Math.min(100, Math.round(cur / needed * 100)) };
    }

    /* ─────────────────────────────────────
       Achievement check
       ───────────────────────────────────── */
    function checkAchievements(d) {
        ACHIEVEMENTS.forEach(function (a) {
            if (d.achievements.indexOf(a.id) === -1 && a.condition(d)) {
                d.achievements.push(a.id);
                _pendingNotifications.push({ type: 'achievement', id: a.id, icon: a.icon, title: a.title, desc: a.desc });
            }
        });
    }

    /* ─────────────────────────────────────
       Emotion engine
       ───────────────────────────────────── */
    function setEmotion(d, emotion, durationMs) {
        d.emotion = emotion;
        d.emotionUntil = Date.now() + (durationMs || 15000);
        save(d);
        _applyEmotionToDOM(emotion);
    }

    function getCurrentEmotion(d) {
        if (Date.now() > d.emotionUntil) {
            d.emotion = _inferIdleEmotion(d);
        }
        return d.emotion;
    }

    function _inferIdleEmotion(d) {
        if (!d.streak.lastDate) return 'neutral';
        var daysSince = daysBetween(d.streak.lastDate, todayStr());
        if (daysSince >= 7) return 'sad';
        if (daysSince >= 3) return 'sleepy';
        if (d.streak.current >= 7) return 'proud';
        if (d.streak.current >= 3) return 'happy';
        return 'neutral';
    }

    function _applyEmotionToDOM(emotionKey) {
        var emo = EMOTIONS[emotionKey] || EMOTIONS.neutral;
        var character = document.querySelector('.dd-asst-character');
        if (!character) return;

        character.setAttribute('data-emotion', emotionKey);

        var svg = character.querySelector('svg');
        if (!svg) return;

        var mouth = svg.querySelector('.dd-emo-mouth');
        if (mouth) mouth.setAttribute('d', emo.mouth);

        var blushL = svg.querySelector('.dd-emo-blush-l');
        var blushR = svg.querySelector('.dd-emo-blush-r');
        if (blushL) blushL.setAttribute('opacity', emo.blush);
        if (blushR) blushR.setAttribute('opacity', emo.blush);

        var sparkle = svg.querySelector('.dd-emo-sparkle');
        if (sparkle) sparkle.style.display = emo.sparkle ? '' : 'none';

        if (emo.eyes === 'happy') {
            _setEyeStyle(svg, 'happy');
        } else if (emo.eyes === 'sad') {
            _setEyeStyle(svg, 'sad');
        } else if (emo.eyes === 'star') {
            _setEyeStyle(svg, 'star');
        } else if (emo.eyes === 'heart') {
            _setEyeStyle(svg, 'heart');
        } else if (emo.eyes === 'sleepy') {
            _setEyeStyle(svg, 'sleepy');
        } else {
            _setEyeStyle(svg, 'normal');
        }
    }

    function _setEyeStyle(svg, style) {
        var eyeOverlayL = svg.querySelector('.dd-emo-eye-l');
        var eyeOverlayR = svg.querySelector('.dd-emo-eye-r');
        if (!eyeOverlayL || !eyeOverlayR) return;

        switch (style) {
            case 'happy':
                eyeOverlayL.setAttribute('d', 'M33 44 Q42 38 51 44');
                eyeOverlayR.setAttribute('d', 'M69 44 Q78 38 87 44');
                eyeOverlayL.setAttribute('opacity', '1');
                eyeOverlayR.setAttribute('opacity', '1');
                break;
            case 'sad':
                eyeOverlayL.setAttribute('d', 'M35 41 Q42 47 49 41');
                eyeOverlayR.setAttribute('d', 'M71 41 Q78 47 85 41');
                eyeOverlayL.setAttribute('opacity', '1');
                eyeOverlayR.setAttribute('opacity', '1');
                break;
            case 'star':
                eyeOverlayL.setAttribute('d', 'M42 38 L43.5 42 L47 42 L44.5 44.5 L45.5 48 L42 46 L38.5 48 L39.5 44.5 L37 42 L40.5 42 Z');
                eyeOverlayR.setAttribute('d', 'M78 38 L79.5 42 L83 42 L80.5 44.5 L81.5 48 L78 46 L74.5 48 L75.5 44.5 L73 42 L76.5 42 Z');
                eyeOverlayL.setAttribute('opacity', '1');
                eyeOverlayR.setAttribute('opacity', '1');
                break;
            case 'heart':
                eyeOverlayL.setAttribute('d', 'M38 42 C38 38 42 36 42 40 C42 36 46 38 46 42 C46 46 42 48 42 48 C42 48 38 46 38 42');
                eyeOverlayR.setAttribute('d', 'M74 42 C74 38 78 36 78 40 C78 36 82 38 82 42 C82 46 78 48 78 48 C78 48 74 46 74 42');
                eyeOverlayL.setAttribute('opacity', '1');
                eyeOverlayR.setAttribute('opacity', '1');
                break;
            case 'sleepy':
                eyeOverlayL.setAttribute('d', 'M34 44 Q42 42 50 44');
                eyeOverlayR.setAttribute('d', 'M70 44 Q78 42 86 44');
                eyeOverlayL.setAttribute('opacity', '1');
                eyeOverlayR.setAttribute('opacity', '1');
                break;
            default:
                eyeOverlayL.setAttribute('opacity', '0');
                eyeOverlayR.setAttribute('opacity', '0');
        }
    }

    /* ─────────────────────────────────────
       Daily Challenge
       ───────────────────────────────────── */
    function getTodaysChallenge(d) {
        var today = todayStr();
        if (d.daily.date !== today) {
            var seed = 0;
            for (var i = 0; i < today.length; i++) seed += today.charCodeAt(i);
            d.daily = { date: today, challengeIdx: seed % DAILY_CHALLENGES.length, completed: false };
            save(d);
        }
        return DAILY_CHALLENGES[d.daily.challengeIdx];
    }

    function checkDailyAnswer(d, userAnswer) {
        var challenge = getTodaysChallenge(d);
        if (!challenge || d.daily.completed) return { correct: false, already: true };
        var answer = userAnswer.trim().toLowerCase().replace(/\s+/g, '');
        var correct = challenge.a.some(function (a) {
            return a.toLowerCase().replace(/\s+/g, '') === answer;
        });
        if (correct) {
            d.daily.completed = true;
            d.stats.dailyChallenges++;
            addXP(d, XP_REWARDS.daily_challenge, '데일리 챌린지 정답');
            checkAchievements(d);
            save(d);
        }
        return { correct: correct, already: false, hint: challenge.hint };
    }

    /* ─────────────────────────────────────
       Mini Quiz
       ───────────────────────────────────── */
    function getRandomMiniQuiz(excludeIdx) {
        var pool = [];
        for (var i = 0; i < MINI_QUIZZES.length; i++) {
            if (i !== excludeIdx) pool.push(i);
        }
        var idx = pool[Math.floor(Math.random() * pool.length)];
        return { idx: idx, quiz: MINI_QUIZZES[idx] };
    }

    function checkMiniQuizAnswer(d, quizIdx, choiceIdx) {
        var quiz = MINI_QUIZZES[quizIdx];
        if (!quiz) return { correct: false };
        var correct = quiz.answer === choiceIdx;
        if (correct) {
            d.stats.miniQuizCorrect++;
            addXP(d, XP_REWARDS.mini_quiz, '미니퀴즈 정답');
            checkAchievements(d);
            save(d);
        }
        return { correct: correct, correctAnswer: quiz.choices[quiz.answer] };
    }

    /* ─────────────────────────────────────
       Smart Context Recommendations
       ───────────────────────────────────── */
    function getSmartRecommendation(d) {
        var recs = [];
        var today = todayStr();

        if (!d.streak.lastDate || d.streak.lastDate !== today) {
            recs.push({ priority: 10, text: '오늘 아직 공부 안 했지? 출석하면 스트릭 이어가!' });
        }
        if (d.daily.date !== today || !d.daily.completed) {
            recs.push({ priority: 9, text: '오늘의 데일리 챌린지가 기다리고 있어! 🎯' });
        }

        var dueReviews = _getDueReviewCount();
        if (dueReviews > 0) {
            recs.push({ priority: 8, text: '복습할 단어가 ' + dueReviews + '개 있어! 단어장에서 복습해봐 📝' });
        }

        if (d.streak.current >= 3 && d.streak.current < 7) {
            recs.push({ priority: 5, text: d.streak.current + '일 연속이야! 7일 달성하면 50XP! 🔥' });
        }
        if (d.streak.current >= 7 && d.streak.current < 14) {
            recs.push({ priority: 5, text: d.streak.current + '일 연속! 대단해! 14일까지 가보자! 💪' });
        }

        var xpInfo = getXPToNext(d);
        if (xpInfo.percent >= 80 && xpInfo.percent < 100) {
            var lvlInfo = getLevelInfo(d.level + 1);
            if (lvlInfo) recs.push({ priority: 7, text: '레벨업까지 ' + (xpInfo.needed - xpInfo.current) + 'XP만 더! ' + lvlInfo.icon + ' ' + lvlInfo.title + '이 눈앞이야!' });
        }

        if (d.stats.quizzesPassed === 0) {
            recs.push({ priority: 4, text: '퀴즈를 아직 안 풀어봤네! 도전해봐 🧩' });
        }
        if (d.stats.roleplaysCompleted === 0 && d.stats.lessonsVisited >= 2) {
            recs.push({ priority: 3, text: '역할극 해봤어? 실전 중국어 대화 연습! 🎭' });
        }

        recs.sort(function (a, b) { return b.priority - a.priority; });
        return recs[0] || null;
    }

    function _getDueReviewCount() {
        try {
            var prog = JSON.parse(localStorage.getItem('dd_learning_progress')) || {};
            var now = Date.now();
            var count = 0;
            Object.keys(prog).forEach(function (lid) {
                var entry = prog[lid];
                if (!entry.reviews) return;
                entry.reviews.forEach(function (r) { if (!r.done && r.at <= now) count++; });
            });
            return count;
        } catch (e) { return 0; }
    }

    /* ─────────────────────────────────────
       Statistics summary
       ───────────────────────────────────── */
    function getStatsSummary(d) {
        var lvl = getLevelInfo(d.level);
        var xpInfo = getXPToNext(d);
        var vocabCount = 0;
        try {
            var v = JSON.parse(localStorage.getItem('dd_vocabulary'));
            vocabCount = v && v.words ? v.words.length : 0;
        } catch (e) {}

        return {
            xp: d.xp,
            level: d.level,
            levelTitle: lvl.title,
            levelIcon: lvl.icon,
            xpToNext: xpInfo,
            streak: d.streak.current,
            bestStreak: d.streak.best,
            achievements: d.achievements.length,
            totalAchievements: ACHIEVEMENTS.length,
            vocabCount: vocabCount,
            lessonsVisited: d.stats.lessonsVisited,
            quizzesPassed: d.stats.quizzesPassed,
            dailyChallenges: d.stats.dailyChallenges,
            calendarDays: d.calendarDays || [],
            studyMinutes: d.stats.totalStudyMinutes
        };
    }

    /* ─────────────────────────────────────
       Public event hooks — called from other modules
       ───────────────────────────────────── */
    function onLessonVisit() {
        var d = load();
        updateStreak(d);
        d.stats.lessonsVisited++;
        addXP(d, XP_REWARDS.visit_lesson, '강의 방문');
        _checkStreakRewards(d);
        checkAchievements(d);
        save(d);
        _fireNotifications();
        setEmotion(d, 'happy', 8000);
    }

    function onTabComplete(tabName) {
        var d = load();
        d.stats.tabsCompleted++;
        addXP(d, XP_REWARDS.complete_tab, tabName + ' 탭 완료');
        checkAchievements(d);
        save(d);
        _fireNotifications();
    }

    function onQuizPass(score, total) {
        var d = load();
        updateStreak(d);
        d.stats.quizzesPassed++;
        var perfect = score === total;
        if (perfect) {
            d.stats.perfectQuizzes++;
            addXP(d, XP_REWARDS.quiz_perfect, '퀴즈 만점!');
            setEmotion(d, 'excited', 15000);
        } else {
            addXP(d, XP_REWARDS.quiz_pass, '퀴즈 통과');
            setEmotion(d, 'happy', 10000);
        }
        checkAchievements(d);
        save(d);
        _fireNotifications();
    }

    function onVocabAdd() {
        var d = load();
        d.stats.vocabAdded++;
        addXP(d, XP_REWARDS.vocab_add, '단어 저장');
        checkAchievements(d);
        save(d);
        _fireNotifications();
    }

    function onVocabMaster() {
        var d = load();
        d.stats.vocabMastered++;
        addXP(d, XP_REWARDS.vocab_master, '단어 마스터');
        checkAchievements(d);
        save(d);
        _fireNotifications();
        setEmotion(d, 'proud', 10000);
    }

    function onRoleplayComplete() {
        var d = load();
        d.stats.roleplaysCompleted++;
        addXP(d, XP_REWARDS.roleplay_done, '역할극 완료');
        checkAchievements(d);
        save(d);
        _fireNotifications();
        setEmotion(d, 'cheering', 12000);
    }

    function onStoryEnding() {
        var d = load();
        d.stats.storyEndings++;
        addXP(d, XP_REWARDS.story_ending, '스토리 엔딩');
        checkAchievements(d);
        save(d);
        _fireNotifications();
    }

    function onPageVisit() {
        var d = load();
        updateStreak(d);
        if (!d.sessionStart) d.sessionStart = Date.now();
        save(d);
        _applyEmotionToDOM(getCurrentEmotion(d));
    }

    function trackStudyTime() {
        var d = load();
        if (d.sessionStart) {
            var mins = Math.round((Date.now() - d.sessionStart) / 60000);
            if (mins > 0 && mins < 120) {
                d.stats.totalStudyMinutes += mins;
            }
        }
        d.sessionStart = null;
        save(d);
    }

    function _checkStreakRewards(d) {
        var s = d.streak.current;
        if (s === 3)  addXP(d, XP_REWARDS.streak_3, '3일 연속 학습');
        if (s === 7)  addXP(d, XP_REWARDS.streak_7, '7일 연속 학습');
        if (s === 14) addXP(d, XP_REWARDS.streak_14, '14일 연속 학습');
        if (s === 30) addXP(d, XP_REWARDS.streak_30, '30일 연속 학습');
    }

    /* ─────────────────────────────────────
       Notification system — toasts
       ───────────────────────────────────── */
    function _fireNotifications() {
        var notes = _pendingNotifications.slice();
        _pendingNotifications = [];
        notes.forEach(function (n, i) {
            setTimeout(function () { _showToast(n); }, i * 1200);
        });
    }

    function _showToast(n) {
        var container = document.getElementById('dd-gami-toasts');
        if (!container) {
            container = document.createElement('div');
            container.id = 'dd-gami-toasts';
            container.className = 'dd-gami-toasts';
            document.body.appendChild(container);
        }

        var toast = document.createElement('div');
        toast.className = 'dd-gami-toast';

        if (n.type === 'xp') {
            toast.innerHTML = '<span class="dd-gami-toast-icon">✨</span><span class="dd-gami-toast-text">+' + n.amount + ' XP <small>' + n.reason + '</small></span>';
            toast.classList.add('dd-gami-toast--xp');
        } else if (n.type === 'levelup') {
            toast.innerHTML = '<span class="dd-gami-toast-icon">' + n.icon + '</span><span class="dd-gami-toast-text">레벨 UP! Lv.' + n.level + ' ' + n.title + '</span>';
            toast.classList.add('dd-gami-toast--level');
        } else if (n.type === 'achievement') {
            toast.innerHTML = '<span class="dd-gami-toast-icon">' + n.icon + '</span><span class="dd-gami-toast-text">업적 달성! ' + n.title + '<br><small>' + n.desc + '</small></span>';
            toast.classList.add('dd-gami-toast--achievement');
        } else if (n.type === 'combo') {
            toast.innerHTML = '<span class="dd-gami-toast-icon">🔥</span><span class="dd-gami-toast-text">' + n.streak + '연속! ' + n.label + ' <small>x' + n.multiplier + ' 보너스</small></span>';
            toast.classList.add('dd-gami-toast--combo');
            toast.style.borderLeftColor = n.color;
        } else if (n.type === 'freeze') {
            toast.innerHTML = '<span class="dd-gami-toast-icon">🧊</span><span class="dd-gami-toast-text">' + n.text + '</span>';
            toast.classList.add('dd-gami-toast--freeze');
        } else if (n.type === 'shop') {
            toast.innerHTML = '<span class="dd-gami-toast-icon">' + n.icon + '</span><span class="dd-gami-toast-text">' + n.name + ' 구매 완료!</span>';
            toast.classList.add('dd-gami-toast--shop');
        }

        container.appendChild(toast);
        setTimeout(function () { toast.classList.add('is-visible'); }, 50);
        setTimeout(function () {
            toast.classList.remove('is-visible');
            toast.classList.add('is-leaving');
            setTimeout(function () { toast.remove(); }, 400);
        }, 3500);
    }

    /* ─────────────────────────────────────
       Combo System — 연속 정답 보너스
       ───────────────────────────────────── */
    var COMBO_TIERS = [
        { streak: 3,  multiplier: 1.5, label: 'GOOD!',      color: '#22C55E' },
        { streak: 5,  multiplier: 2.0, label: 'GREAT!',     color: '#3B82F6' },
        { streak: 8,  multiplier: 2.5, label: 'AMAZING!',   color: '#8B5CF6' },
        { streak: 10, multiplier: 3.0, label: 'LEGENDARY!', color: '#F59E0B' }
    ];

    function onComboHit(d) {
        d.combo.current++;
        if (d.combo.current > d.combo.best) d.combo.best = d.combo.current;
        var tier = _getComboTier(d.combo.current);
        if (tier) {
            var bonus = Math.round(XP_REWARDS.mini_quiz * (tier.multiplier - 1));
            if (bonus > 0) {
                addXP(d, bonus, tier.label + ' 콤보 보너스');
            }
            _pendingNotifications.push({
                type: 'combo',
                streak: d.combo.current,
                label: tier.label,
                color: tier.color,
                multiplier: tier.multiplier
            });
        }
        save(d);
        return { combo: d.combo.current, tier: tier };
    }

    function onComboBreak(d) {
        var was = d.combo.current;
        d.combo.current = 0;
        save(d);
        return was;
    }

    function _getComboTier(streak) {
        var tier = null;
        for (var i = COMBO_TIERS.length - 1; i >= 0; i--) {
            if (streak >= COMBO_TIERS[i].streak) { tier = COMBO_TIERS[i]; break; }
        }
        return tier;
    }

    function getComboState(d) {
        return { current: d.combo.current, best: d.combo.best, tier: _getComboTier(d.combo.current) };
    }

    /* ─────────────────────────────────────
       XP Shop — 아이템 구매
       ───────────────────────────────────── */
    var SHOP_ITEMS = [
        { id: 'streak_freeze',   icon: '🧊', name: '스트릭 프리즈',  desc: '하루 빼먹어도 연속 기록 유지!', price: 100, category: 'utility' },
        { id: 'hint_token_3',    icon: '💡', name: '힌트 토큰 x3',   desc: '퀴즈/챌린지에서 힌트 3회',     price: 60,  category: 'utility' },
        { id: 'hint_token_10',   icon: '💡', name: '힌트 토큰 x10',  desc: '퀴즈/챌린지에서 힌트 10회',    price: 150, category: 'utility' },
        { id: 'costume_hanbok',  icon: '👘', name: '한복 의상',      desc: '叮叮에게 한복을 입혀줘!',       price: 200, category: 'costume', costumeKey: 'hanbok' },
        { id: 'costume_qipao',   icon: '👗', name: '치파오 의상',    desc: '叮叮에게 치파오를 입혀줘!',     price: 200, category: 'costume', costumeKey: 'qipao' },
        { id: 'costume_uniform', icon: '🎓', name: '교복 의상',      desc: '叮叮에게 교복을 입혀줘!',       price: 150, category: 'costume', costumeKey: 'uniform' },
        { id: 'costume_party',   icon: '🎉', name: '파티 의상',      desc: '叮叮에게 파티복을 입혀줘!',     price: 250, category: 'costume', costumeKey: 'party' },
        { id: 'xp_boost',        icon: '⚡', name: 'XP 부스트',      desc: '1시간 동안 XP 1.5배!',        price: 300, category: 'utility' }
    ];

    function getShopItems(d) {
        return SHOP_ITEMS.map(function (item) {
            var owned = false;
            if (item.category === 'costume') {
                owned = d.shop.unlockedCostumes && d.shop.unlockedCostumes.indexOf(item.costumeKey) !== -1;
            }
            return {
                id: item.id, icon: item.icon, name: item.name,
                desc: item.desc, price: item.price, category: item.category,
                canBuy: d.xp >= item.price && !owned, owned: owned
            };
        });
    }

    function buyShopItem(d, itemId) {
        var item = null;
        for (var i = 0; i < SHOP_ITEMS.length; i++) {
            if (SHOP_ITEMS[i].id === itemId) { item = SHOP_ITEMS[i]; break; }
        }
        if (!item) return { success: false, msg: '아이템을 찾을 수 없어!' };
        if (d.xp < item.price) return { success: false, msg: 'XP가 부족해! (' + d.xp + '/' + item.price + ')' };

        if (item.category === 'costume') {
            if (d.shop.unlockedCostumes.indexOf(item.costumeKey) !== -1) {
                return { success: false, msg: '이미 가지고 있는 의상이야!' };
            }
            d.shop.unlockedCostumes.push(item.costumeKey);
        } else if (item.id === 'streak_freeze') {
            d.shop.streakFreezes++;
        } else if (item.id === 'hint_token_3') {
            d.shop.hintTokens += 3;
        } else if (item.id === 'hint_token_10') {
            d.shop.hintTokens += 10;
        } else if (item.id === 'xp_boost') {
            d.xpBoostUntil = Date.now() + 3600000;
        }

        d.xp -= item.price;
        d.level = computeLevel(d.xp);
        save(d);
        _pendingNotifications.push({ type: 'shop', icon: item.icon, name: item.name });
        _fireNotifications();
        return { success: true, msg: item.icon + ' ' + item.name + ' 구매 완료!' };
    }

    function useHintToken(d) {
        if (!d.shop || d.shop.hintTokens <= 0) return false;
        d.shop.hintTokens--;
        save(d);
        return true;
    }

    function getHintTokenCount(d) {
        return d.shop ? d.shop.hintTokens : 0;
    }

    function getStreakFreezeCount(d) {
        return d.shop ? d.shop.streakFreezes : 0;
    }

    /* ─────────────────────────────────────
       Legendary Challenge — 하드모드 퀴즈
       ───────────────────────────────────── */
    var LEGENDARY_QUIZZES = [
        { q: '"도서관에서 조용히 해주세요"를 중국어로?', a: ['请在图书馆保持安静', '图书馆里请安静'], hint: null },
        { q: '"이 근처에 지하철역이 있나요?"를 중국어로?', a: ['这附近有地铁站吗', '附近有地铁站吗'], hint: null },
        { q: '"어제 비가 와서 오늘 날씨가 시원하다"를 중국어로?', a: ['昨天下雨了所以今天天气凉快', '因为昨天下了雨今天很凉快'], hint: null },
        { q: '"나는 3년 동안 중국어를 공부했다"를 중국어로?', a: ['我学了三年中文', '我学了三年汉语', '我学中文学了三年'], hint: null },
        { q: '"그는 나보다 키가 크다"를 중국어로?', a: ['他比我高', '他个子比我高'], hint: null },
        { q: '"여행을 좋아하는 친구와 같이 갈 것이다"를 중국어로?', a: ['我要和喜欢旅游的朋友一起去', '我会跟喜欢旅行的朋友一起去'], hint: null },
        { q: '"배가 아파서 병원에 갔다"를 중국어로?', a: ['肚子疼去了医院', '因为肚子疼所以去了医院', '我肚子疼去了医院'], hint: null },
        { q: '"중국 음식 중에서 마라탕이 제일 좋아"를 중국어로?', a: ['中国菜里面我最喜欢麻辣烫', '中国菜中我最喜欢麻辣烫'], hint: null },
        { q: '"혹시 이 영화 본 적 있어?"를 중국어로?', a: ['你看过这部电影吗', '你有没有看过这部电影'], hint: null },
        { q: '"내일 아침 7시에 일어나야 한다"를 중국어로?', a: ['明天早上七点要起床', '我明天早上七点得起床', '明天早上7点要起床'], hint: null }
    ];

    var LEGENDARY_XP = 80;

    function getLegendaryChallenge(d) {
        var today = todayStr();
        if (!d.legendary) d.legendary = {};
        if (d.legendary.date === today) {
            return {
                completed: d.legendary.completed,
                score: d.legendary.score || 0,
                total: 5,
                questions: d.legendary.questions || _pickLegendaryQuestions(today)
            };
        }
        var questions = _pickLegendaryQuestions(today);
        d.legendary = { date: today, completed: false, score: 0, questions: questions, answers: [] };
        save(d);
        return { completed: false, score: 0, total: 5, questions: questions };
    }

    function _pickLegendaryQuestions(dateStr) {
        var seed = 0;
        for (var i = 0; i < dateStr.length; i++) seed = (seed * 31 + dateStr.charCodeAt(i)) & 0x7FFFFFFF;
        var indices = [];
        var pool = [];
        for (var j = 0; j < LEGENDARY_QUIZZES.length; j++) pool.push(j);
        for (var k = 0; k < 5 && pool.length > 0; k++) {
            var pick = seed % pool.length;
            indices.push(pool[pick]);
            pool.splice(pick, 1);
            seed = (seed * 37 + 13) & 0x7FFFFFFF;
        }
        return indices;
    }

    function checkLegendaryAnswer(d, questionIdx, userAnswer) {
        if (!d.legendary || d.legendary.completed) return { correct: false, done: true };
        var qIdx = d.legendary.questions[questionIdx];
        var quiz = LEGENDARY_QUIZZES[qIdx];
        if (!quiz) return { correct: false, done: false };

        var answer = userAnswer.trim().replace(/\s+/g, '');
        var correct = quiz.a.some(function (a) {
            return a.replace(/\s+/g, '') === answer;
        });

        if (!d.legendary.answers) d.legendary.answers = [];
        d.legendary.answers[questionIdx] = correct;

        if (correct) d.legendary.score = (d.legendary.score || 0) + 1;

        var allAnswered = d.legendary.answers.filter(function (a) { return a !== undefined; }).length >= 5;
        if (allAnswered) {
            d.legendary.completed = true;
            var perfect = d.legendary.score === 5;
            if (perfect) {
                addXP(d, LEGENDARY_XP * 2, '레전드 챌린지 퍼펙트!');
                setEmotion(d, 'love', 20000);
            } else if (d.legendary.score >= 3) {
                addXP(d, LEGENDARY_XP, '레전드 챌린지 통과');
                setEmotion(d, 'proud', 12000);
            } else {
                addXP(d, Math.round(LEGENDARY_XP * 0.3), '레전드 챌린지 도전');
            }
            checkAchievements(d);
        }

        save(d);
        return {
            correct: correct,
            done: allAnswered,
            score: d.legendary.score,
            total: 5,
            perfect: allAnswered && d.legendary.score === 5
        };
    }

    /* ─────────────────────────────────────
       Enhanced Smart Alerts — streak danger
       ───────────────────────────────────── */
    function getStreakAlert(d) {
        var today = todayStr();
        if (d.streak.lastDate === today) return null;

        var freezes = d.shop ? d.shop.streakFreezes : 0;
        if (d.streak.current >= 7) {
            return {
                urgent: true,
                text: '🚨 ' + d.streak.current + '일 연속 기록이 위험해!\n오늘 안 하면 스트릭이 깨져!' +
                    (freezes > 0 ? '\n🧊 프리즈 ' + freezes + '개 있어!' : '\n상점에서 프리즈를 사둬!'),
                emotion: 'sad'
            };
        }
        if (d.streak.current >= 3) {
            return {
                urgent: true,
                text: '⚠️ ' + d.streak.current + '일 연속인데 오늘 아직 안 했어!\n빨리 공부해서 스트릭 지키자! 🔥',
                emotion: 'thinking'
            };
        }
        if (d.streak.current > 0) {
            return {
                urgent: false,
                text: '오늘 공부하면 ' + (d.streak.current + 1) + '일 연속이야! 화이팅! 💪',
                emotion: 'neutral'
            };
        }
        return null;
    }

    /* ─────────────────────────────────────
       Public API
       ───────────────────────────────────── */
    return {
        load: load,
        save: save,
        LEVELS: LEVELS,
        ACHIEVEMENTS: ACHIEVEMENTS,
        EMOTIONS: EMOTIONS,
        DAILY_CHALLENGES: DAILY_CHALLENGES,
        MINI_QUIZZES: MINI_QUIZZES,
        XP_REWARDS: XP_REWARDS,

        getLevelInfo: getLevelInfo,
        getXPToNext: getXPToNext,
        getStatsSummary: getStatsSummary,
        getSmartRecommendation: getSmartRecommendation,
        getTodaysChallenge: getTodaysChallenge,
        checkDailyAnswer: checkDailyAnswer,
        getRandomMiniQuiz: getRandomMiniQuiz,
        checkMiniQuizAnswer: checkMiniQuizAnswer,
        getCurrentEmotion: getCurrentEmotion,
        setEmotion: setEmotion,

        onLessonVisit: onLessonVisit,
        onTabComplete: onTabComplete,
        onQuizPass: onQuizPass,
        onVocabAdd: onVocabAdd,
        onVocabMaster: onVocabMaster,
        onRoleplayComplete: onRoleplayComplete,
        onStoryEnding: onStoryEnding,
        onPageVisit: onPageVisit,
        trackStudyTime: trackStudyTime,

        COMBO_TIERS: COMBO_TIERS,
        SHOP_ITEMS: SHOP_ITEMS,
        LEGENDARY_QUIZZES: LEGENDARY_QUIZZES,
        onComboHit: onComboHit,
        onComboBreak: onComboBreak,
        getComboState: getComboState,
        getShopItems: getShopItems,
        buyShopItem: buyShopItem,
        useHintToken: useHintToken,
        getHintTokenCount: getHintTokenCount,
        getStreakFreezeCount: getStreakFreezeCount,
        getLegendaryChallenge: getLegendaryChallenge,
        checkLegendaryAnswer: checkLegendaryAnswer,
        getStreakAlert: getStreakAlert
    };
})();

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { DDGamification.onPageVisit(); });
} else {
    DDGamification.onPageVisit();
}
window.addEventListener('beforeunload', function () { DDGamification.trackStudyTime(); });
