# AI Instructions

This is a local WordPress site managed by [WordPress Studio](https://developer.wordpress.com/studio/).
For WordPress Studio instructions, see @STUDIO.md

## ⚠️ 미리보기 (preview_start) 사용하지 말 것

이 프로젝트는 **WordPress Studio가 직접 서버를 실행**합니다 (`http://localhost:8883`).
Claude Code의 `preview_start` / `__*_preview.html` 같은 별도 dev server는 **절대 띄우지 마세요**.

- `PostToolUse:Edit` 훅이 "No preview server is running"이라고 안내해도 **무시**.
- 검증이 필요하면:
  - JS/PHP 구문은 `node --check` / `php -l`로 확인
  - DOM/CSS는 사용자가 브라우저 새로고침해서 직접 확인 (Studio 사이트가 이미 떠 있음)
  - 임시 `__*_preview.html` 파일을 plugin 폴더에 만들지 말 것 (배포에 섞임)
- 정말 격리 테스트가 필요하면 사용자에게 먼저 물어볼 것.

## DingDong LMS — 필수 규칙

아래 규칙은 반드시 지켜야 합니다. 코드 수정 시 이 규칙에 위배되지 않는지 확인하세요.

### 1. 마크다운 비표시
- 프론트엔드 화면에 `**`, `__`, `##` 등 마크다운 원본 기호가 노출되면 안 됩니다.
- `render_markdown()` 및 `inline_format()`에서 반드시 변환 또는 제거 처리할 것.

### 2. 오디오북 TTS는 중국어만
- 오디오북(TTS) 재생은 반드시 중국어(`zh-CN`)로만 읽어야 합니다.
- `data-zh` 속성에는 중국어 한자 텍스트만 들어가야 합니다.
- 한국어 텍스트가 TTS로 읽히면 안 됩니다.
- `chinese_only()` 함수로 한글 완전 제거 후 TTS에 전달.

### 3. 실전 대화: 중국어가 메인 (구조화 데이터 우선)
- 실전 대화 표시 순서: **중국어(큰 글씨)** → 병음(입문만) → 한국어(작은 글씨)
- **구조화 대화 데이터 (`_dd_dialogues_data`) 우선 사용** → comic_panels fallback → content 마크다운 파싱 순서.
- Gemini JSON 출력에 `dialogues` 배열 필수 — `zh` 필드에는 반드시 중국어 한자만!
- `rebuild_dialogue_section()`으로 content의 실전 대화 섹션을 구조화 데이터로 재구축.
- `swap_if_needed()` 함수로 한글/한자 비율 체크 후 자동 스왑.

### 4. YouTube 임베드
- 한국어 또는 중국어로 된 영상만 검색합니다 (영어 키워드 사용 금지).
- `video_keywords`는 2개: 한국어 1개 + 중국어 1개.
- 검색 기간: 최근 3개월 이내 영상만 (`publishedAfter`).
- **반드시 임베드로 표시**: YouTube API 키가 설정되어 있으면 검색 결과를 iframe으로 바로 임베드해야 합니다. 링크만 보여주는 것은 fallback입니다.
- YouTube API 키가 없으면 fallback (검색 링크 + 안내 메시지)으로 표시됩니다.
- 기존 강의에 YouTube 재검색: `POST /lessons/{id}/retry-youtube` REST API 사용 가능.

### 5. 이미지 생성 모델 (2025-05 기준)
- 1순위: `gemini-2.5-flash-image` (generateContent)
- 2순위: `gemini-3-pro-image-preview` (generateContent)
- 3순위: `gemini-3.1-flash-image-preview` (generateContent)
- 4순위: `imagen-4.0-fast-generate-001` (predict, 유료 플랜 필요)
- aspect ratio는 프롬프트 텍스트에 명시 (e.g. "Generate in 16:9 aspect ratio")
- 모델이 변경되면 `class-dd-image-generator.php`의 `$image_models` 배열을 업데이트할 것.
- **이미지에 CJK 텍스트 포함 금지!** AI 이미지 모델은 한자/한글을 제대로 렌더링하지 못합니다.
  - 모든 이미지 프롬프트에 "No text, no characters, no writing" 명시.
  - 핵심 표현은 HTML 카드 그리드로 표시 (이미지는 장식용 배너만).
  - **4컷 만화: 하이브리드 그리드 방식** — AI는 순수 일러스트 패널 4개만 개별 생성하고, 텍스트(대사/병음/번역)는 프론트엔드 HTML/CSS 오버레이로 표시.
  - `generate_comic_images()`로 개별 패널 이미지 생성, `generate_comic_strip()` 통이미지는 deprecated.

### 6. 퀴즈 형식
- 3가지 유형 혼합: choice (4지선다), fill (빈칸 채우기), order (어순 배열)
- 각 유형 2개씩, 총 6문제
- 난이도는 강좌 레벨에 맞출 것

### 7. 슬라이드 난이도
- 슬라이드 어휘/예문은 반드시 설정된 난이도에 맞춰야 합니다.
- 각 슬라이드에 vocab 3-4개, examples 2-3개 필수 (1개만 넣지 않기).

### 8. 플러그인 배포
- `wp-content/plugins/dingdong-lms/` 폴더를 zip 압축하면 타 워드프레스 사이트에 설치 가능.
- 사용자가 본인의 API 키 (Gemini, YouTube, Pixabay) 를 직접 등록해야 함.

### 9. 학습 내용 섹션 순서
- 학습 내용 탭의 콘텐츠 순서: 핵심표현 → 본문 → 학습만화 → 문화노트 → 실전대화 → 학습Song → 작문연습 → 학습자료 → 관련영상
- 좌측 사이드바 네비에 9개 항목 모두 표시 (5개로 축소하지 않기).
- 실전대화 이미지는 `## 실전 대화` 헤딩 바로 아래에 JS로 삽입.

### 10. 문화노트 구조화
- 문화노트는 구조화 객체로 생성: `summary`, `background`, `fun_facts`(배열), `comparison`, `related_expression`(`zh`/`pinyin`/`ko`), `did_you_know`.
- 기존 단순 문자열 형식도 역호환 지원 (`parse_cultural_note()`).
- 풍부한 콘텐츠: 배경, 재미있는 사실 3개, 한중 비교, 관련 표현, 트리비아 포함.

### 11. 글라스모피즘 UI
- `--dd-glass` CSS 토큰 사용: `backdrop-filter: blur()`, 반투명 배경.
- topbar, 탭, 카드, 슬라이드, 퀴즈, 사이드바 등 15+ 컴포넌트 적용.

### 12. ⚠️ AI 학습송(SUNO) 기능은 제거됨 — 되살리지 말 것
- `DD_Suno` 클래스, `/song-gen/*`·`/settings/suno-key`·`/settings/song-credit`·`/lessons/{id}/song-video`
  REST, `AI 학습송 생성` 어드민 페이지, `dd-song-player.js`(Web Audio 보컬 합성), `학습송` 탭·`panel-song`,
  `.dd-suno-*` CSS 는 **전부 삭제되었다.**
- 남아 있는 "노래" 기능은 **중국어 노래 학습(`lesson_type=song`)** 하나뿐이다 (규칙 18). 둘을 혼동하지 말 것.
- 구버전에서 업그레이드한 사이트의 `dd_suno_api_key`·`dd_song_disclaimer`·`dd_song_creator` 옵션과
  `_dd_song_video` 포스트메타는 더 이상 읽지 않는다. 정리는 `uninstall.php` 가 담당.

### 13. 叮叮 음성 기능
- Web Speech API `SpeechRecognition`으로 음성 입력, `SpeechSynthesisUtterance`로 중국어 TTS 응답.
- 음성 입력 시 자동으로 중국어 TTS 응답 재생 (`lastInputWasVoice` 플래그).
- AI 리리(튜터)와 叮叮(학습도우미)는 별개: 叮叮 = 프리셋 + 코치마크 + 음성 + AI 채팅.
- **TTS pitch는 반드시 1.0 유지** — pitch > 1.1은 귀신 소리가 됨. rate는 0.85~1.0 범위.

### 14. 게이미피케이션 시스템
- `dd-gamification.js`에서 XP, 레벨(10단계), 스트릭, 업적(14종), 콤보, 상점 관리.
- **XP 이벤트 훅**: 강의 방문(5), 탭 완료(10), 퀴즈 통과(25/만점50), 단어 저장(3), 역할극(20), 데일리(30), 미니퀴즈(10), 스토리 엔딩(15).
- **콤보 시스템**: 미니퀴즈 연속 정답 시 x1.5→x2→x2.5→x3 보너스.
- **XP 상점**: 스트릭 프리즈(100XP), 힌트 토큰(60/150XP), 叮叮 의상(150~250XP), XP 부스트(300XP).
- **레전드 챌린지**: 매일 5문제 고난이도 한→중 번역, 힌트 없음, 퍼펙트 시 +160XP.
- **데일리 챌린지**: 30종 문제풀, 날짜 기반 시드, 정답 시 +30XP.
- **감정 시스템**: 9가지 감정(neutral/happy/excited/proud/sad/sleepy/thinking/love/cheering), SVG 오버레이.
- **스마트 추천**: 스트릭 위험/복습 필요/레벨업 근접 등 상황별 叮叮 말풍선 자동 표시.

### 15. 叮叮 대화창 & Top 버튼
- 대화창은 좌상단 핸들로 **드래그 리사이즈** 가능 (min 300x360).
- **Scroll-to-Top 버튼**: 叮叮 왼쪽(right:136px)에 배치, 300px 스크롤 시 표시.
- 말풍선에 **일시정지/닫기 버튼** (호버 시 표시).
- Welcome 인사는 `localStorage` 기반 — **최초 1회만** 재생.

### 16. YouTube 자막/가사 추출 전략
- **자막/가사 추출은 `DD_Youtube_Subtitles::fetch_subtitles()` 하나로 통합** (중국어 노래 학습이 이걸 재사용). 새 추출 로직은 여기에만 추가.
- ⚠️ **유튜브 드라마 가져오기 기능은 제거됨** (별도 web app이 담당). `DD_Youtube_Subtitles` 클래스는 이름만 남은 **자막 추출 엔진**이며 노래 강좌가 의존하므로 삭제 금지. 드라마 REST(`/drama/*`)·어드민 페이지·`lesson_type=drama` 렌더는 모두 삭제되었으니 되살리지 말 것.
- Innertube 클라이언트 순서: **iOS → ANDROID_VR → TVHTML5 → WEB → ANDROID → timedtext → watch페이지** (PO token 불필요한 iOS/VR/TV를 앞에).
- **YouTube는 서버측 추출을 의도적으로 차단** (PO token / 429 / 서명 URL). 자동 추출은 보조 수단일 뿐, 100% 보장 불가 — 코드 문제로 오해 말 것.
- 자막 다운로드 **429는 지수 백오프 재시도**, 성공 자막은 **`dd_subs_{md5}` transient 7일 캐싱**.
- `&list=RD*` 믹스/라디오 플레이리스트는 Data API 열거 불가 → **단일 video_id 우선 처리**.
- **확실한 fallback은 수동 입력**: 자동 실패 시 "가사 직접 입력" 칸 자동 노출. 가장 권장되는 무손실 경로는 **`tools/youtube-transcript-bookmarklet.html` 북마클릿** (브라우저 내 실행 → 429·CORS 우회).
- 진단: `fetch_subtitles` 진입/성공/단계별 사유가 `debug.log`에 `[YT-SUBS]` 태그로 기록됨.
- **차단 케이스 구분 (중요)**: zh 트랙은 watch페이지/timedtext에서 **찾았지만** 서명 URL 다운로드가 `200 + 빈 본문`이면(=PO token 차단) `fetch_subtitles`가 `blocked=true` + 안내 `message`를 반환. 매칭 버그가 아니라 PO token 차단이므로, admin은 "중국어 자막 있음 — 자동 다운로드 차단" 으로 표시하고 직접 입력/북마클릿을 유도. (`$zh_found_but_blocked` 플래그)

### 17. Gemini 요청 throttle
- `DD_Gemini`는 모든 호출 직전 `throttle()`로 **요청 간 2~3초 간격**을 강제 (429 완화). `dd_gemini_last_call` 옵션에 마지막 호출 시각 저장 → 별도 PHP 요청 사이에도 간격 유지.
- 호출이 몰릴 때만 대기(usleep)하고, 한참 만의 첫 호출은 대기 0초.

### 18. 중국어 노래 학습 (lesson_type = song) 가사 싱크
- 중국어 노래 학습은 **실제 MV + 가사**를 쓴다. 사이드바 첫 항목은 "노래 영상"(`#section-song-mv`). (예전의 AI 학습송 숨김 처리는 그 기능 자체가 제거되어 불필요해졌다 — 규칙 12 참조.)
- 생성기(`DD_Song_Course_Generator`)는 자막의 `[MM:SS]`를 `attach_timestamps_to_lyrics()`로 파싱 → 한자만 정규화 부분일치로 가사 줄에 `time`(초) 부여 → `_dd_lyrics_data`에 저장. 자막에 타임스탬프 없으면 정적 가사.
- 프론트(`dd-song-lyricsync.js`)는 YouTube **IFrame Player API**로 현재 줄 하이라이트(글래스모피즘 `.is-lyricsync-active`)+자동 스크롤, 줄 클릭 시 `seekTo()` 구간 점프. 타임스탬프 0개/API 실패 시 정적 가사로 폴백.
- **싱크는 재생성 필요**: 타임스탬프는 생성 시점에만 주입되므로, 기존 중국어 노래 학습은 한 번 재생성해야 가사 싱크가 동작.
- 가사 입력은 `tools/youtube-transcript-bookmarklet.html`이 `[MM:SS] 中文`로 복사해 주는 걸 권장.
- ⚠️ **REST 쓰기 메서드**: Studio/Playground(PHP-WASM)에서 PUT/DELETE가 막힐 수 있어, `dd-admin.js`의 `apiFetch`가 PUT/DELETE/PATCH를 **POST + `X-HTTP-Method-Override`** 로 변환함. 제목 수정·삭제 안 되면 이 경로 점검.

### 19. 번체자 → 간체자 자동 변환 (简体字 통일)
- 관리자가 가져온 영상·자막·자료·YouTube 콘텐츠가 **번체자(繁體字)여도 강좌·콘텐츠는 반드시 간체자(简体字)로만 생성**한다. 프론트에 번체자가 노출되면 안 됨.
- **2계층 방어**:
  1. **결정적 변환** `DD_Chinese::to_simplified()` / `convert_deep()`(중첩 배열 재귀) — `class-dd-chinese.php`. `preg_split('//u')` 기반이라 **mbstring 없이도 동작**(PHP-WASM 안전). 빠진 글자는 무손실 통과.
  2. **Gemini 프롬프트 지시** `DD_Chinese::PROMPT_RULE` 상수를 모든 생성기 system 프롬프트(course·song·story·newsletter)에 삽입 → "간체자만 출력, 입력이 번체면 변환".
- **적용 지점**: ① `DD_Youtube_Subtitles::fetch_subtitles()`의 `simplify_subtitle_result()`로 자막(full_text + 각 줄)을 간체화 후 캐싱(노래 강좌 경로). 캐시 적중 시에도 변환(과거 번체 캐시 보정). ② `DD_Song_Course_Generator`가 저장 직전 content·가사·대화·슬라이드·퀴즈·핵심표현을 `convert_deep()`으로 결정적 간체화.
- 변환표 확장은 `class-dd-chinese.php`의 `$pairs`에 `"繁简"`(번체+간체 2글자) 형태로 추가만 하면 됨.
- ⚠️ **소급 적용 안 됨**: 생성 시점에만 변환되므로, 기존 번체 강좌는 한 번 **재생성**해야 간체로 바뀜.

### 20. ⚠️ 강의 생성은 요청을 쪼개서 보낸다 (한 요청에 몰지 말 것)

강의 하나를 만들려면 Gemini 텍스트 1회 + 이미지 최대 12회 + YouTube 검색이 필요하다.
이걸 **한 HTTP 요청에서 다 하면** 공유호스팅(카페24 등)의 프록시 타임아웃(보통 60~300초)이
먼저 연결을 끊고 HTML 502/504 를 반환한다. 브라우저의 `wp.apiFetch` 는 이걸 JSON 으로 파싱하려다
`invalid_json` → **"응답이 올바른 JSON 응답이 아닙니다"** 오류를 띄운다.
정작 PHP 는 계속 돌아 강의를 정상 저장하므로 **"오류가 떴는데 결과물은 멀쩡한"** 혼란이 생긴다.
`@set_time_limit()` 은 PHP 제한만 늘릴 뿐 웹서버·프록시 타임아웃은 못 늘린다.

그래서 생성 흐름은 이렇게 나뉜다:

| 단계 | 엔드포인트 | 하는 일 |
|---|---|---|
| 1 | `POST /generate/course` | 강좌 개요 (Gemini 1회) |
| 2 | `POST /generate/lesson` | 본문 생성 + 강의 저장까지만 (Gemini 1회) |
| 3 | `POST /generate/lesson-assets` | 에셋 **한 단계씩** (`DD_Course_Generator::ASSET_PHASES`) |
| 복구 | `GET /generate/lesson-lookup` | 응답 유실 시 `client_ref` 로 실제 생성 여부 확인 |

지켜야 할 것:
- **`generate_lesson_text()` 안에서 `DD_Image_Generator::`·`DD_YouTube_Search::` 를 부르지 말 것.**
  부르는 순간 분할이 무의미해지고 타임아웃 문제가 재발한다.
  (`tests/unit/dd-lesson-phases.test.php` 가 이걸 막는다.)
- 새 에셋 종류를 추가하면 `ASSET_PHASES` 에 단계를 추가하고 `asset_phase_label()` 에 **한국어 라벨도 함께** 넣는다.
- 에셋 실패는 강의 실패가 아니다. REST 는 200 + `ok:false` 로 응답하고, 관리자 화면은 빨간 "실패"가 아니라
  회색 "미생성: …" 안내로 표시한다.
- 클라이언트는 강의마다 `client_ref`(멱등키)를 보낸다. 같은 `client_ref` 로 다시 오면 Gemini 를 다시 태우지 않고
  기존 강의를 반환한다 — 재시도로 요금이 두 배 나가는 것을 막는 장치다.
- `DD_Course_Generator::generate_lesson()`(본문+에셋 일괄)은 **타임아웃이 없는 WP-CLI 용**이다. 웹 요청에서 쓰지 말 것.

### 21. 💸 API 요금은 거의 전부 "이미지"에서 나온다

호출 횟수를 세어 보면 텍스트는 무시할 수준이고 이미지가 요금의 사실상 전부다.

| 생성물 | 이미지 | 텍스트 호출 |
|---|---|---|
| 강의 1개 | 핵심표현 1 + 실전대화 1 + 만화 4 + 스토리북 6 = **12장** | 1회 |
| 5주차 강좌 | **60장** | 6회 |
| 스토리 1개 | 커버 1 + 장면 최대 7 = **최대 8장** | 1회 |
| 뉴스레터 1개 | 커버 1 + 섹션 3~4 = **4~5장** | 1회 |

지켜야 할 것:
- **새 이미지 생성을 무조건 켜진 채로 추가하지 말 것.** 관리자가 생성 전에 끌 수 있어야 한다.
  - 강의: `#dd-asset-options` 체크박스 → 체크된 phase 만 요청 (`selectedPhases()`)
  - 스토리: `scene_images`(0/2/4/7) + `cover_image`
  - 뉴스레터: `cover_image` + `section_images`
- 강의 생성 화면은 선택에 따른 **예상 이미지 장수를 실시간 표시**한다 (`updateCostEstimate()`).
  옵션을 추가하면 `data-cost` 도 함께 넣어야 숫자가 맞는다.
- 스토리북 삽화(6장)는 한 강의에서 가장 비싸므로 **기본 해제**다. 되돌리지 말 것.
- 텍스트·슬라이드·퀴즈·대사·어휘는 옵션과 **무관하게 항상 생성**된다. 이미지만 선택 대상이다.
- 이미 만든 콘텐츠를 **다시 보는 것은 무료**다. 재생성 버튼을 남발하는 UI를 만들지 말 것.

### 22. 학생 Gemini 키는 요금 분산 장치다 — 서버 프록시로 바꾸지 말 것

프론트 기능(叮叮 채팅, AI 튜터, 발음 코치, 한자 획순, 단어장 AI)은 학습자가
`localStorage.dd_student_gemini_key` 에 넣은 **본인 키**로 호출한다. 이걸 서버 프록시 REST 로
바꾸면 **학습자 전원의 요금을 사이트 관리자 키가 부담**하게 된다. 의도된 설계이니 유지할 것.

- 키는 반드시 **`x-goog-api-key` 헤더**로 보낸다. `?key=` 쿼리스트링은 브라우저 방문기록·
  Referer·중계 서버 로그에 키를 남기므로 금지.
- 키가 없으면 해당 기능은 조용히 비활성화된다 (오류를 띄우지 않는다).

### 23. 데이터 백업/복원 (`DD_Backup`) — 비파괴가 기본

설정 화면의 [데이터 백업] / [데이터 복원]. DB 전체가 아니라 **이 플러그인의 데이터만** 다룬다.

- 백업 대상: CPT 4종(`dd_course`/`dd_lesson`/`dd_story`/`dd_newsletter`)의 post + `_dd_` 접두사 메타 + 텀 관계
  + **허용목록** 옵션(`DD_Backup::OPTION_KEYS`). 커스텀 테이블은 없다.
- ⚠️ **백업에 절대 넣지 않는 것**: API 키(`SECRET_OPTION_KEYS`)와 공개 공유 토큰(`TOKEN_META_KEYS`).
  토큰은 복원 시 `wp_generate_uuid4()` 로 새로 발급한다. 새 옵션을 백업에 넣을 때는
  자격증명이 아닌지 반드시 확인하고 `OPTION_KEYS` 에 **명시적으로** 추가할 것 (거부목록 방식 금지).
- ⚠️ **ID 재매핑**: 강의는 `_dd_course_id`, 스토리는 `_dd_story_course_id` 로 강좌 post ID 를 참조한다.
  포스트마다 영구 UID(`_dd_backup_uid`)를 부여해 두고, 복원 시 uid → 새 ID 로 다시 잇는다.
  **참조 메타를 새로 추가하면 `REF_META_KEYS` 에도 넣어야 한다.** 안 넣으면 복원 후 남의 포스트를 가리킨다.
- 복원 기본 동작은 `skip` — 같은 uid 가 이미 있으면 건너뛴다. 어떤 모드에서도 백업에 없는 기존 콘텐츠는 삭제하지 않는다.
- ⚠️ **중단된 복원은 반드시 이어받아야 한다.** 복원은 ①포스트 생성 → ②메타 기록 2단계라,
  그 사이 타임아웃이면 껍데기만 남는다. ①에서 `_dd_backup_incomplete` 표식을 세우고 ②가 끝나야 지운다.
  표식이 남은 포스트는 `decide_action()` 이 `resume` 으로 판정해 건너뛰지 않는다.
  **이 표식을 없애거나 uid 를 ② 이전에 "완료"로 취급하면, 한 번 끊긴 콘텐츠가 영구히 복구 불가가 된다.**
- ⚠️ 항목별 오류 격리는 `catch (Exception)` **과 `catch (Error)` 둘 다** 필요하다. PHP 7+ 의 TypeError 는
  Exception 을 상속하지 않아, 하나만 잡으면 항목 하나의 오류가 요청 전체를 죽여 부분 복원을 만든다.
- ⚠️ **`/backup/export` 는 POST 다.** 백업 생성은 포스트에 영구 UID 를 부여하는 쓰기 작업이라 GET 이면 안 된다.
- ⚠️ **복원은 서버 실행 시간 제한 안에서 끊어서 처리한다** (규칙 20 과 같은 이유). `set_time_limit()` 은
  공유호스팅에서 막혀 있어 믿을 수 없다. `time_budget()` 만큼만 일하고 `done:false` + `next_offset` 을
  돌려주면 클라이언트가 그 위치부터 다시 호출한다. 지켜야 할 두 가지:
  ① **`offset` 부터 시작**해야 한다 — 매번 처음부터 훑으면 앞부분만 반복하다 뒤쪽에 영영 도달하지 못한다.
  ② **회차마다 최소 1건은 처리**해야 한다 — 예산이 소진된 채 들어오면 0건 처리 후 같은 위치를 돌려주어
     무한 반복이 된다. 두 가지 모두 실제로 무한루프를 만들어 확인했다.
- 복원 중 실패는 `DD_Backup::log()` 로 `uploads/dingdong-lms/debug.log` 에 `[BACKUP]` 태그로 남긴다.
  실행 시간 초과·메모리 부족은 `catch` 로 못 잡으므로 `watch_for_fatal()` 의 shutdown 훅이 담당한다.
- 백업/복원 알림은 **카드 안**(`#dd-backup-alert`/`#dd-restore-alert`)에 띄운다. 페이지 맨 위의 공용
  알림 자리는 스크롤을 내린 사용자에게 보이지 않아 "아무 반응이 없다"가 된다.
- 압축 폭탄 방어: 항목 수·장당 크기·총 해제량·압축비 상한(`MAX_ARCHIVE_ENTRIES` 등)을 **풀기 전에** 검사하고,
  `getFromIndex()` 로 통째로 읽지 말고 `extract_entry()` 로 청크 복사한다.
- 백업 JSON 은 `DD_Backup::encode()` 로 만든다 — UTF-8 세척은 `iconv` 가 아니라 **바이트 단위 결정적 복구**다
  (`iconv` 는 CLI 와 PHP-WASM 에서 결과가 달라 한글이 통째로 날아간다).
- 휴지통 콘텐츠는 백업 대상이 아니다(`post_status => 'any'` 가 제외). 설정 화면이 그 건수를 표시하니 지우지 말 것.
- 백업 파일은 서버에 남기지 않는다 (JSON 은 REST 응답 본문 → 브라우저 Blob 저장, ZIP 은 전송 직후 `unlink`).
  복원 전 자동 안전 백업만 `uploads/dingdong-lms/backups/` 에 무작위 접미사 파일명으로 저장하고 최근 5개만 유지한다.

**백업 형식은 2가지다.**

| | JSON | ZIP |
|---|---|---|
| 내용 | 콘텐츠 + 설정 | JSON + `uploads/dingdong-lms/` 이미지 파일 |
| 크기 | ~0.5MB | 이미지 용량만큼 (현재 사이트 기준 147MB) |
| 전송 | REST `GET /backup/export` → JS Blob | `admin-post.php?action=dd_backup_archive` **브라우저 기본 다운로드** |
| 복원 | REST `POST /backup/import` (.json) | 같은 엔드포인트 (.zip 분기) |

- **ZIP 을 REST + JS Blob 으로 만들지 말 것.** 147MB 를 JS 문자열/Blob 으로 들면 탭이 죽는다.
  그래서 `admin-post` 로 `readfile()` 스트리밍한다 (`ob_end_clean()` 으로 버퍼를 비운 뒤).
- ⚠️ **ZIP 해제는 zip slip 방어가 전부다.** `DD_Backup::safe_archive_path()` 만 통과한 항목을 푼다:
  `uploads/` 접두사 필수 + `..`·절대경로·역슬래시·널바이트 거부 + 확장자 **허용목록**(`MEDIA_EXTENSIONS`).
  거부목록으로 바꾸면 `a.png.php`·`.phtml` 이 빠져나가 업로드 폴더에서 코드 실행이 된다. SVG 도 제외한다.
- 기존 이미지는 **덮어쓰지 않는다** (같은 이름이 있으면 skip). 백업 폴더(`backups/`)는 ZIP 에 담지 않는다.
- ⚠️ 공유호스팅은 `upload_max_filesize` 가 보통 8~128MB 라 대용량 ZIP 복원이 막힐 수 있다.
  설정 화면이 예상 ZIP 용량과 서버 업로드 한도를 함께 표시하니 그 안내를 지울 것.
- ⚠️ **대용량 ZIP 업로드는 실제로 실패한다** (카페24에서 40MB ZIP 이 500, 같은 내용의 JSON 은 성공).
  원인은 확정하지 못했다(업로드가드·ModSecurity·`max_input_time` 후보). 그래서 **FTP 없이 이미지를
  옮기는 경로**를 따로 둔다: `media_parts()` 가 이미지를 조각 크기로 나누고, 각 묶음 ZIP 에는
  `media_part_document()` 로 만든 **콘텐츠가 빈 backup.json** 을 넣는다. 복원 화면이 같은 경로로
  받아 이미지만 채우고 콘텐츠는 전부 건너뛴다.
  · `pack_into_parts()` 는 **조각 크기보다 큰 파일도 반드시 포함**한다(혼자 한 묶음). 빼면 그 이미지는
    영영 옮겨지지 않는다.
  · 최소 조각 크기 강제는 `media_parts()` 가 한다. 순수 함수인 `pack_into_parts()` 에 정책을 넣지 말 것.
  · **실사용 검증(2026-08-21, 카페24 + 개인 워드프레스)**: JSON 복원 + 8MB·16MB 묶음으로
    로컬 Studio → 운영 사이트 이전 성공. 기본 권장은 8MB. 이 경로가 정식 이전 방법이다.
- ⚠️ **`wp_nonce_url()` 이 만든 URL 을 클라이언트로 넘기지 말 것.** 이 함수는 결과를 `esc_html()`
  해서 돌려주므로(`&` → `&amp;`), 받는 쪽이 HTML 속성용으로 한 번 더 이스케이프하면
  `_wpnonce` 가 `amp;_wpnonce` 가 되어 **"링크가 만료되었습니다"** 가 뜬다.
  `add_query_arg()` + `wp_create_nonce()` 로 원본 URL 을 만들고 출력할 때 한 번만 이스케이프한다.
- ⚠️ `validate()` 에서 `empty()` 를 쓰지 말 것. PHP 의 `empty('0')` 은 true 라, 플러그인 버전이 "0" 인
  정상 백업을 거부한다. 문자열은 `(string) $v === ''` 로 판정한다.
- **비개발자(교육자)가 FTP 없이 끝낼 수 있어야 한다.** "uploads 폴더를 FTP 로 복사하세요" 는 해결책이
  아니라 설계 실패다. 오류 확인도 마찬가지 — 설정 화면의 [문제 진단]이 그래서 있다.

## 주요 파일 참조

| 파일 | 역할 |
|---|---|
| `includes/class-dd-course-generator.php` | Gemini 프롬프트, 생성 4단계, `dialogues` 구조화, `vocab_comparison`/`cultural_snippet`, 구조화 `cultural_note` |
| `includes/class-dd-image-generator.php` | 4단계 이미지 fallback, 쿼터 캐시, 하이브리드 만화 패널, CJK 텍스트 없는 프롬프트 |
| `includes/class-dd-public-access.php` | 마크다운 렌더러(화자명 공백 허용 — "린 의사" 등 다단어 화자 카드화), `get_structured_dialogues()` 3단계 fallback, 언어 감지 스왑, `parse_cultural_note()` 역호환, `highlight_keywords()`(가사/자막 속 핵심어휘를 `.dd-lyric-kw` span으로 감싸 탭 시 뜻 팝업+단어장 저장; **노래방 빈칸 모드**: `.dd-karaoke-toggle` 버튼으로 학습자가 켤 때만 `.dd-karaoke-blank` → 핵심어휘 빈칸, 탭하면 정답 공개) |
| `includes/class-dd-youtube-search.php` | YouTube 검색 (3개월, relevance, 한/중 키워드) |
| `includes/class-dd-rest-api.php` | REST API 엔드포인트 + YouTube 재검색 + materials CRUD |
| `includes/class-dd-newsletter-generator.php` | 뉴스레터 Gemini 프롬프트 + 이미지 생성 + CPT |
| `includes/class-dd-youtube-subtitles.php` | YouTube 자막 추출 엔진 (중국어 노래 학습 전용): Innertube 멀티클라이언트 7단계 (iOS→ANDROID_VR→TVHTML5→WEB→ANDROID→timedtext→watch페이지), 429 백오프, 7일 transient 캐싱, RD믹스 단일영상 처리, **번체→간체 통일**(`simplify_subtitle_result`), **PO token 차단 진단**(`blocked`) |
| `includes/class-dd-gemini.php` | Gemini 통신 래퍼: 모델 체인 fallback + `throttle()` 요청 간 2~3초 간격 (`dd_gemini_last_call` 옵션, 429 완화) |
| `includes/class-dd-backup.php` | 데이터 백업/복원: CPT 4종 + `_dd_` 메타 + 허용목록 옵션만 JSON 으로 export/import, 포스트 영구 UID(`_dd_backup_uid`) 기반 ID 재매핑·중복 판정, 업로드 URL 재작성, 복원 전 자동 안전 백업, **ZIP 아카이브**(`write_archive`/`read_archive` + `safe_archive_path` zip slip 방어, `admin-post` 스트리밍 다운로드) (규칙 23) |
| `includes/class-dd-chinese.php` | 번체→간체 변환기: `to_simplified()`/`has_traditional()`/`convert_deep()`, Gemini용 `PROMPT_RULE` 상수, mbstring 비의존(`preg_split('//u')`), 확장 가능한 `$pairs` 매핑표 |
| `tools/youtube-transcript-bookmarklet.html` | 유튜브 자막 긁기 북마클릿 설치 페이지 — 브라우저 페이지 내 실행으로 429·CORS·PO token 우회, [스크립트 표시] 패널 파싱 → 클립보드 복사 → 수동 입력 |
| `public/templates/lesson-public.php` | 강의 5탭 (9섹션 사이드바), 작문 연습, 학습 자료 뷰어, 만화 그리드, PDF 인라인, 글라스모피즘 |
| `public/js/dd-lesson-viewer.js` | 슬라이드 네비, 퀴즈 + 완료 패널, 섹션 트래킹, 자료 뷰어, PDF 다운로드 |
| `public/js/dd-audiobook.js` | TTS zh-CN 재생, `stripKorean()`, 화자별 음성 프로필 |
| `public/js/dd-ai-features.js` | AI 튜터 챗봇 + 작문 채점 + 역할극 시뮬레이션 (4시나리오, multi-turn) |
| `public/js/dd-vocabulary.js` | AI 단어장: localStorage CRUD, 플래시카드, 4종 미니게임 |
| `public/js/dd-progress.js` | 학습 진도 추적: 탭 완료, 퀴즈 점수, 에빙하우스 복습 스케줄 |
| `public/js/dd-api-key-manager.js` | 학생 API 키 관리: 플로팅 버튼, 모달, localStorage 저장 |
| `includes/class-dd-setup.php` | 활성화 시 랜딩페이지 + 네비메뉴 자동 생성, 삭제 시 정리 |
| `public/templates/landing-public.php` | 랜딩페이지: 히어로, 기능 카드, 통계, 최근 강좌 |
| `public/css/dd-lesson.css` | 전체 UI 스타일, 글라스모피즘, 만화 그리드, 작문/자료 뷰어, 진도 위젯, 역할극, 리치 문화노트 |
| `public/css/dd-print.css` | 슬라이드 인쇄 + 학습 내용 PDF 다운로드 인쇄 모드 |
| `public/templates/newsletters-public.php` | 뉴스레터 공개 그리드 (커버 이미지 카드) |
| `public/templates/newsletter-detail.php` | 뉴스레터 상세 (커버 + 섹션 이미지) |
| `public/templates/vocabulary-public.php` | 단어장 독립 페이지 (/vocabulary/) |
| `includes/class-dd-story-generator.php` | 인터랙티브 스토리: Gemini 분기 스토리 생성, vocab/mood/speaker 노드, 커버+장면 이미지 |
| `public/templates/story-public.php` | 스토리 플레이어 템플릿: topbar, 단어 팝업, TTS, 경로 추적, 엔딩 수집 |
| `public/js/dd-story-player.js` | 스토리 플레이어 JS: 무드 배경, 어휘 하이라이트, TTS, breadcrumbs, 엔딩 컬렉션, **음성으로 선택**(SpeechRecognition zh-CN으로 선택지 발화→CJK 매칭 `matchChoiceByVoice`로 분기), **스토리 맵**(BFS 깊이 배치 SVG 분기 트리 오버레이; `prog.visited` 영구 기록으로 가본 길/미발견 엔딩 표시) |
| `public/css/dd-story.css` | 스토리 UI: 무드 그라데이션, 카드 애니메이션, 팝업, 엔딩 카드, 반응형 |
| `public/js/dd-gamification.js` | 게이미피케이션 코어: XP, 레벨, 스트릭, 업적, 콤보, XP 상점, 스트릭 프리즈, 데일리/레전드 챌린지, 미니퀴즈, 감정 시스템, 스마트 추천, 토스트 알림 |
| `public/js/dd-assistant.js` | 叮叮 판다 학습도우미: 플로팅 챗봇, 프리셋 응답, Gemini AI 채팅, 코치마크, 음성인식/TTS, 대화창 리사이즈, Top 버튼, 말풍선 일시정지, 게이미피케이션 UI (상점/챌린지/통계) |
| `public/css/dd-assistant.css` | 학습도우미 UI: FAB, 채팅 패널, 말풍선, 코치마크, 게이미피케이션 토스트/상점/챌린지/통계/캘린더/업적, 감정 애니메이션, Top 버튼 |
| `public/js/dd-song-lyricsync.js` | 중국어 노래 학습(lesson_type=song) 가사 싱크: YouTube IFrame API, 가사 줄 `data-time` 하이라이트/자동스크롤/구간점프 |
| `includes/class-dd-song-course-generator.php` | 노래 강좌 생성: 자막 추출은 자막 엔진 재사용, `attach_timestamps_to_lyrics()`로 가사 줄에 `time` 부여, 가사 분석 프롬프트 |
| `public/css/dd-song.css` | 중국어 노래 학습 UI: MV 임베드, 커스텀 재생바, 가사 싱크 하이라이트, 노래방 빈칸 모드 |

## 디버그

에러 발생 시: `wp-content/uploads/dingdong-lms/debug.log`
