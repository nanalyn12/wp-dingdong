# Dingdong LMS

한국인을 위한 AI 기반 중국어 & 중국문화 교육 플랫폼 WordPress 플러그인

## 개요

Dingdong LMS는 관리자가 **Gemini API**를 활용하여 중국어 교육 강좌를 원클릭으로 생성하고, 학생은 **로그인 없이** 공유 링크만으로 강의를 열람할 수 있는 LMS 플러그인입니다.

- **버전**: 2.1.0
- **대상**: 중국어를 배우려는 한국인 학습자
- **난이도**: 입문 / 초급 / 중급 / 고급 선택 가능
- **배포**: zip 압축 후 타 워드프레스 사이트에 업로드 설치 가능

---

## 주요 기능 요약

| 기능 | 설명 |
|---|---|
| **AI 강좌 생성** | Gemini API로 주제+난이도 입력 시 강좌/강의 원클릭 자동 생성 |
| **5탭 학습** | 학습 내용 / 슬라이드 / 오디오북 / 스토리북 / 퀴즈 |
| **叮叮 학습도우미 v2.1** | 판다 AI 어시스턴트: 음성 대화 + 역할극 6시나리오 + 페이지 이동 + 옷 커스터마이징 + 35+ 지식 DB |
| **학습송** | Gemini 작사 + Web Audio 비트 합성 + TTS 가사 읽기 (인라인 플레이어) |
| **인터랙티브 스토리** | 분기형 게임북 중국어 학습 (/stories/ 목록 + /story/{uuid}/ 플레이) |
| **작문 AI 채점** | Gemini 기반 문법/어휘/자연스러움 실시간 채점 |
| **중국어 노래 학습** | YouTube 가사 자막 추출 (Innertube 멀티클라이언트 + 북마클릿) + Gemini 분석 |
| **뉴스레터** | 중국 대중문화 트렌드 중국어 독해 콘텐츠 |
| **AI 단어장** | localStorage CRUD + 플래시카드 + 4종 미니게임 + CSV 내보내기 |
| **글라스모피즘 UI** | `backdrop-filter: blur()` + 반투명 배경, 15+ 컴포넌트 |

---

## 叮叮 (Dīngding) AI 학습도우미

叮叮은 DingDong LMS 전체 페이지에 상주하는 **판다 캐릭터 AI 어시스턴트**입니다. 학습자를 환영하고, 음성 대화를 나누며, 페이지별 맞춤 학습 안내를 제공합니다.

### 환영 화면 (Welcome Screen)

랜딩페이지 첫 방문 시 **흰 배경 풀스크린 환영 화면**이 나타납니다.

```
┌────────────────────────────────┐
│                                │
│     ┌────────────────────┐     │
│     │ 안녕! 나는 叮叮이야!  │     │
│     │ DingDong에 온 걸     │     │
│     │ 환영해! ...          │     │
│     └────────┬───────────┘     │
│              ▼                 │
│         ┌────────┐             │
│         │  🐼    │  ← 바운스   │
│         │ (판다)  │    등장     │
│         └────────┘             │
│                                │
│          ( 🎤 )  ← 음성 입력   │
│        말해보세요...            │
│                                │
│     [ 학습 시작하기 → ]         │
│                                │
└────────────────────────────────┘
```

**동작 시퀀스:**

1. 흰 배경 풀스크린 표시 → 叮叮 판다 바운스 등장 (1.2초, 스프링 애니메이션)
2. 말풍선 팝업 + **한국어 TTS**로 인사: "안녕! 나는 叮叮이야! DingDong에 온 걸 환영해!"
3. TTS 종료 후 **마이크 자동 활성화** → 학습자 음성 대기
4. 학습자 발화 → 패턴 매칭 또는 **Gemini AI 응답** → TTS 재생 → 다시 듣기
5. 음성 대화 루프 계속 (학습자가 "학습 시작하기" 클릭할 때까지)
6. 환영 화면 페이드아웃 → 랜딩페이지 표시 + 叮叮 우하단 코너로 이동

**대화 패턴 (프리셋):**

| 학습자 발화 | 叮叮 응답 |
|---|---|
| "강좌 보고 싶어" | 강좌 안내 + "학습 시작하기" 유도 |
| "스토리 하고 싶어" | 인터랙티브 스토리 소개 |
| "중국어 배우고 싶어" | 난이도별 강좌 추천 |
| "너 누구야?" | 자기소개 |
| 그 외 | Gemini AI 자유 대화 (API 키 설정 시) |

**조건:**
- 랜딩페이지(`data-dd-page="landing"`)에서만 작동
- `sessionStorage`로 세션당 1회만 (새 탭/새 세션에서 재생)
- 마이크 버튼 클릭 또는 화면 아무 곳 클릭으로 대화 스킵 가능

### 상시 모드 (모든 페이지)

환영 화면 이후, 叮叮은 모든 페이지에서 **우하단 플로팅 캐릭터**로 상주합니다.

```
페이지 구성:

┌─────────────────────────────┐
│         (페이지 콘텐츠)       │
│                             │
│                    ┌──────┐ │
│                    │말풍선│ │
│                    └──┬───┘ │
│                    ┌──┴──┐  │
│                    │ 🐼  │  │ ← 플로팅 캐릭터
│                    │叮叮  │  │    (클릭 → 채팅 패널)
│                    └─────┘  │
└─────────────────────────────┘
```

**기능 목록:**

| 기능 | 설명 |
|---|---|
| **말풍선 로테이션** | 20초마다 페이지별 맞춤 팁 메시지 교체 |
| **채팅 패널** | 캐릭터 클릭 시 열림 — 텍스트 입력 + 음성 입력 |
| **음성 입력** | Web Speech API `SpeechRecognition` (ko-KR), 최종 결과 시 자동 전송 |
| **음성 응답** | 음성 질문에 대한 AI 응답은 중국어 부분을 자동 TTS 재생 (zh-CN) |
| **지식 DB** | API 키 없이도 35+ 프리셋 응답 (사이트 소개, 강좌 안내, 기능 상세, 학습법 등) |
| **동적 정보** | DOM에서 실제 강좌 수/이름을 읽어 "강좌 몇 개 있어?" 등에 실시간 답변 |
| **Gemini AI 채팅** | API 키 설정 시 자유 대화 (페이지 컨텍스트 포함) |
| **역할극 시나리오** | 6개 실전 상황(식당/택시/쇼핑/호텔/병원/은행) multi-turn 대화 + 자동 평가 |
| **페이지 이동** | "강좌 목록으로 이동해줘" 등 키워드 인식 → 자동 네비게이션 |
| **옷 커스터마이징** | 6벌 의상(후디/한복/교복/치파오/캐주얼/파티) × 6색상, localStorage 저장 |
| **퀵 액션 버튼** | 페이지별 5개 원클릭 도움말 (역할극/이동/옷 바꾸기 포함) |
| **코치마크 투어** | "페이지 안내" 클릭 시 UI 요소별 스포트라이트 가이드 |

### 페이지 컨텍스트 인식

叮叮은 현재 페이지의 **실제 콘텐츠를 스크래핑**하여 Gemini 프롬프트에 포함합니다.

| 페이지 | 수집 컨텍스트 |
|---|---|
| 랜딩 | 히어로 제목/설명, 기능 카드, 최근 강좌 목록, 통계 |
| 강의 | 강의 제목, 본문 앞부분 800자, 핵심 표현 (zh/pinyin/ko) |
| 강좌 목록 | 전체 강좌명 |
| 스토리 | 스토리 제목 |
| 뉴스레터 | 뉴스레터 제목, 본문 500자 |

"이 강의 내용 설명해줘"라고 질문하면 기능 목록이 아닌 **실제 강의 내용**(주제, 핵심 표현, 본문 요약)을 답변합니다.

### 상태 표시

| 상태 | 헤더 인디케이터 | 타이핑 영역 |
|---|---|---|
| **대기** | 초록 점 + "AI 모드 활성" | — |
| **생각 중** | 노랑 펄스 점 + "생각하는 중..." | 점 3개 바운스 + "생각하는 중..." 텍스트 |
| **답변 완료** | 초록 팝 + "답변 완료 ✓" (2.5초) | — |

### 페이지별 동작

| 페이지 | `data-dd-page` | 말풍선 수 | 퀵 액션 수 | 코치마크 |
|---|---|---|---|---|
| 랜딩 | `landing` | 6 | 4 | 5단계 |
| 강좌 목록 | `courses` | 5 | 4 | 3단계 |
| 강의 | `lesson` | 8 | 5 | 7단계 |
| 스토리 목록 | `stories` | 4 | 3 | — |
| 스토리 플레이 | `story` | 4 | 3 | 2단계 |
| 뉴스레터 목록 | `newsletters` | 3 | 2 | 1단계 |
| 뉴스레터 상세 | `newsletter` | 3 | 2 | 1단계 |
| 단어장 | `vocabulary` | 4 | 3 | 1단계 |

### 기술 스택

- **캐릭터**: 인라인 SVG (120x140 상반신 판다, 의상별 동적 SVG 빌드, 눈 깜빡임/손 흔들기 애니메이션)
- **의상 시스템**: 6벌 의상 x 6색상 = 36가지 조합, `buildPandaBody()` 동적 생성, localStorage 저장
- **음성 입력**: `window.SpeechRecognition || webkitSpeechRecognition` (ko-KR)
- **음성 출력**: `SpeechSynthesisUtterance` (ko-KR, zh-CN), `pickFriendlyVoice()` 밝은 음성 우선
- **지식 DB**: 35+ 패턴 매칭, 사이트 소개/강좌/기능/학습법 등 API 키 없이 응답
- **역할극**: 6개 시나리오 multi-turn, `systemInstruction` + `conversationHistory`
- **페이지 이동**: 키워드 인식 -> `window.location.href` 자동 네비게이션 (5개 경로)
- **AI 백엔드**: Gemini API 3모델 fallback (`gemini-3.1-flash-lite` -> `gemini-3.5-flash` -> `gemini-3-flash-preview`)
- **상태 관리**: 클로저 내부 `state` 객체 (IIFE 패턴)

### 관련 파일

| 파일 | 역할 |
|---|---|
| `public/js/dd-assistant.js` | 叮叮 전체 로직: 환영 화면, 채팅, 음성, 코치마크, Gemini 연동 |
| `public/css/dd-assistant.css` | 叮叮 UI: 캐릭터, 말풍선, 채팅 패널, 환영 화면, 코치마크, 반응형 |

---

## 학습송 플레이어

Gemini AI가 학습 단어를 가사에 녹인 중국어 노래를 생성하고, Web Audio API로 비트를 합성하며, TTS로 가사를 읽어줍니다.

### 구조

```
학습 내용 탭 → [학습송 만들기] 섹션 → [학습송 듣기] 클릭
  └→ 인라인 플레이어 펼침 (YouTube 임베드처럼 페이지 안에서 재생)
     ├→ Gemini API로 가사 생성 (6-8줄, verse + chorus)
     ├→ Web Audio API 비트 합성
     │   ├ Drums: kick + snare + hi-hat (128 BPM)
     │   ├ Bass: 중국 5음계 (宮商角徵羽 = C D E G A)
     │   ├ Melody: square wave + vibrato
     │   ├ Pad: atmospheric sine waves
     │   └ Vocal Synth: formant 기반 보컬 합성 (배경 텍스처)
     │       ├ sawtooth 기본파 + 3 bandpass formant 필터
     │       ├ 모음 매핑: pinyin → a/e/i/o/u → formant 주파수
     │       ├ vibrato (5.5Hz, ±4Hz)
     │       └ breath noise 레이어
     ├→ TTS 가사 읽기: 라인 전환 시 Web Speech API (zh-CN) 자동 재생
     └→ 가사 싱크 MV: 활성 가사 하이라이트 + 비주얼라이저 + 배경 오브
```

### 인라인 플레이어

- **이전**: 풀스크린 오버레이 (`position: fixed; inset: 0`)
- **현재**: `#section-song` 컨테이너 안에 인라인 임베드 (`position: relative; height: 520px; border-radius: 16px`)
- 트리거 버튼 → 인라인 플레이어 펼침 → X 버튼으로 닫기 → 트리거 버튼 복원

### 관련 파일

| 파일 | 역할 |
|---|---|
| `public/js/dd-song-player.js` | Gemini 작사, Web Audio 비트, 포먼트 보컬, TTS 가사 동기화, 가사 싱크 UI |
| `public/css/dd-song.css` | 인라인 플레이어, 가사 애니메이션, 비주얼라이저, 트리거 섹션, 가사 싱크 글래스 |

---

## 중국어 노래 학습 (lesson_type = song) 가사 싱크

실제 중국 노래 MV로 만든 **중국어 노래 학습**은 AI 작곡 학습송과 별개로, **영상에 맞춰 가사가 움직이는 가사 싱크 모드**를 제공합니다.

- **AI 학습송 숨김**: 중국어 노래 학습(`lesson_type === 'song'`)은 이미 실제 MV+가사가 있으므로, "AI 학습송 만들기" 섹션·사이드바를 **표시하지 않음** (대신 "노래 영상" 네비). 일반 AI 강좌는 학습송 유지.
- **타임스탬프 보존**: 생성 시 자막의 `[MM:SS]`를 `attach_timestamps_to_lyrics()`로 파싱 → 한자만 정규화 부분일치로 Gemini 가사 줄에 `time`(초) 부여 → `_dd_lyrics_data` 각 줄에 `time` 저장. (자막에 타임스탬프 없으면 정적 가사로 폴백)
- **싱크 엔진**(`dd-song-lyricsync.js`): YouTube **IFrame Player API**로 `getCurrentTime()` 폴링 → 현재 줄 `.is-lyricsync-active` 하이라이트(글래스모피즘) + 가사 컨테이너 내 자동 중앙 스크롤. 줄 클릭 시 `seekTo()`로 해당 구간 점프(따라부르기 학습). 사용자 스크롤 중엔 자동 스크롤 일시정지.
- **안전장치**: 타임스탬프 줄이 0개면 가사 싱크 비활성(정적 가사). IFrame API 로드 실패 시 dim 처리 안 함(정상 가사 유지).
- 가사를 손쉽게 채우려면 `tools/youtube-transcript-bookmarklet.html` 북마클릿이 `[MM:SS] 中文` 형식으로 복사해 줌.

---

## 파일 구조

```
dingdong-lms/
├── dingdong-lms.php                   # 메인 부트스트랩
├── uninstall.php                      # 삭제 시 데이터 정리
├── includes/
│   ├── class-dd-loader.php            # 훅 등록 (메뉴, 에셋, REST, rewrite)
│   ├── class-dd-post-types.php        # CPT 등록: dd_course, dd_lesson, dd_story, dd_newsletter
│   ├── class-dd-setup.php             # 활성화 시 랜딩페이지 + 네비메뉴 자동 생성, 삭제 시 정리
│   ├── class-dd-api-key.php           # Gemini API 키 AES-256-CBC 암호화 저장
│   ├── class-dd-gemini.php            # Gemini API 통신 래퍼 (모델 체인 fallback + 요청 간 2~3초 throttle)
│   ├── class-dd-course-generator.php  # AI 강좌/강의 생성 (구조화 대화, 문화노트, vocab_comparison)
│   ├── class-dd-image-generator.php   # Gemini/Imagen 이미지 생성 (4단계 fallback + 쿼터 캐시)
│   ├── class-dd-youtube-search.php    # YouTube Data API v3 영상 검색 (한/중 키워드, 3개월 이내)
│   ├── class-dd-thumbnail.php         # Pixabay API 강좌 썸네일 자동 가져오기
│   ├── class-dd-public-access.php     # 공개 링크, 마크다운 렌더러, 스토리 목록, 언어 감지 스왑
│   ├── class-dd-qrcode.php            # QR 코드 생성
│   ├── class-dd-rest-api.php          # REST API 엔드포인트 + YouTube 재검색 + materials CRUD
│   ├── class-dd-story-generator.php   # 인터랙티브 스토리 생성 (vocab/mood/speaker 노드)
│   ├── class-dd-newsletter-generator.php # 뉴스레터 Gemini 생성 + 이미지 + CPT
│   ├── class-dd-youtube-subtitles.php # YouTube 자막 추출 엔진 (중국어 노래 학습 전용): Innertube 멀티클라이언트(7단계) + 429 백오프 + 7일 캐싱
│   └── class-dd-song-course-generator.php # 중국어 노래 학습 강좌: 자막 추출은 자막 엔진 재사용, 가사 분석 프롬프트
├── admin/
│   ├── css/dd-admin.css               # 관리자 페이지 스타일
│   ├── js/dd-admin.js                 # 관리자 UI (vanilla JS + wp.apiFetch)
│   └── views/
│       ├── page-dashboard.php         # 강좌 목록 대시보드
│       ├── page-generator.php         # AI 강좌 생성 인터페이스 (난이도 선택)
│       ├── page-stories.php           # 인터랙티브 스토리 관리
│       ├── page-newsletters.php       # 뉴스레터 관리
│       └── page-settings.php          # API 키 설정 (Gemini, YouTube, Pixabay)
└── public/
    ├── css/
    │   ├── dd-lesson.css              # 전체 UI + 글라스모피즘 + 만화 그리드 + 작문/자료 뷰어
    │   ├── dd-story.css               # 스토리 UI: 무드 그라데이션, 팝업, 엔딩 카드
    │   ├── dd-song.css                # 학습송 인라인 플레이어 + 가사 애니메이션
    │   ├── dd-assistant.css           # 叮叮 학습도우미 UI: 환영 화면, 채팅, 코치마크
    │   └── dd-print.css               # 인쇄/PDF용 @media print
    ├── js/
    │   ├── dd-lesson-viewer.js        # 슬라이드, 퀴즈 3종 + 완료 인터랙션, 섹션 트래킹
    │   ├── dd-audiobook.js            # TTS 오디오북 (zh-CN 전용, 화자별 음성 프로필)
    │   ├── dd-storybook.js            # 스토리북 페이지 네비게이션
    │   ├── dd-vocabulary.js           # AI 단어장 (localStorage, 플래시카드, 4종 미니게임)
    │   ├── dd-ai-features.js          # AI 튜터 챗봇 + 작문 채점 + 역할극 4시나리오
    │   ├── dd-progress.js             # 학습 진도 추적 + 에빙하우스 복습 스케줄
    │   ├── dd-story-player.js         # 인터랙티브 스토리 (TTS, 단어 팝업, 엔딩 수집)
    │   ├── dd-song-player.js          # 학습송(AI 작곡): Gemini 작사 + Web Audio 비트 + 포먼트 보컬 + TTS
    │   ├── dd-song-lyricsync.js       # 중국어 노래 학습 가사 싱크: YouTube IFrame API + 가사 하이라이트/자동스크롤/구간점프
    │   ├── dd-assistant.js            # 叮叮: 환영 화면 + 채팅 + 음성 + 코치마크 + Gemini
    │   └── dd-api-key-manager.js      # 학생 API 키 localStorage 관리
    └── templates/
        ├── lesson-public.php          # 공개 강의 (5탭 + 9섹션 사이드바 + 작문 + 자료 뷰어)
        ├── courses-public.php         # 강좌 목록 공개 페이지
        ├── stories-public.php         # 인터랙티브 스토리 목록 페이지
        ├── story-public.php           # 인터랙티브 스토리 플레이어
        ├── landing-public.php         # 랜딩페이지 (히어로, 기능 카드, 통계, 최근 강좌/스토리)
        ├── newsletters-public.php     # 뉴스레터 그리드 페이지
        ├── newsletter-detail.php      # 뉴스레터 상세 페이지
        └── vocabulary-public.php      # 단어장 독립 페이지 (CSV/JSON 가져오기)
└── tools/
    └── youtube-transcript-bookmarklet.html  # 유튜브 자막 긁기 북마클릿 설치 페이지 (429·CORS 우회)
```

---

## 공개 페이지 라우팅

| URL 패턴 | 페이지 | 템플릿 |
|---|---|---|
| `/` (프론트 페이지) | 랜딩페이지 | `landing-public.php` |
| `/courses/` | 강좌 목록 | `courses-public.php` |
| `/stories/` | 인터랙티브 스토리 목록 | `stories-public.php` |
| `/story/{uuid}/` | 스토리 플레이어 | `story-public.php` |
| `/lesson/{uuid}/` | 강의 상세 | `lesson-public.php` |
| `/newsletters/` | 뉴스레터 목록 | `newsletters-public.php` |
| `/newsletter/{uuid}/` | 뉴스레터 상세 | `newsletter-detail.php` |
| `/vocabulary/` | AI 단어장 | `vocabulary-public.php` |

모든 페이지의 topbar에 동일한 네비게이션: **강좌 목록 / AI 스토리 / 뉴스레터 / 단어장**

---

## 데이터 구조

### CPT: `dd_course`

| 항목 | 설명 |
|---|---|
| `post_title` | 강좌 제목 |
| `post_content` | 강좌 설명 |
| `_dd_course_status` | draft / generating / complete |
| `_dd_course_generated_at` | 생성 일시 |
| `_dd_course_total_lessons` | 총 강의 수 |
| `_dd_course_level` | 난이도 (beginner / elementary / intermediate / advanced) |
| `_dd_course_thumbnail` | 썸네일 이미지 URL (Pixabay) |
| `_dd_course_type` | 강좌 유형 (일반 / song) |

### CPT: `dd_lesson`

| Meta Key | 설명 |
|---|---|
| `_dd_course_id` | 소속 강좌 ID |
| `_dd_lesson_order` | 순서 (1, 2, 3...) |
| `_dd_lesson_level` | 난이도 |
| `_dd_slides_data` | 슬라이드 JSON (5장, vocab 3-4개, examples 2-3개) |
| `_dd_quiz_data` | 퀴즈 JSON (choice 2 + fill 2 + order 2 = 6문제) |
| `_dd_cultural_note` | 구조화 문화노트 (summary/background/fun_facts/comparison/related_expression/did_you_know) |
| `_dd_key_expressions` | 핵심 표현 JSON (zh/pinyin/ko x 6-9개) |
| `_dd_dialogue_scene` | 대화 장면 영어 묘사 (이미지 생성용) |
| `_dd_dialogue_image` | 생성된 대화 이미지 URL |
| `_dd_dialogues_data` | 구조화된 실전 대화 JSON (speaker/zh/pinyin/ko x 8-10) |
| `_dd_comic_data` | 만화 4컷 데이터 JSON |
| `_dd_comic_images` | 하이브리드 만화 패널 이미지 URL 배열 |
| `_dd_storybook_data` | 스토리북 6페이지 JSON (zh/pinyin/ko/image) |
| `_dd_video_keywords` | YouTube 검색 키워드 JSON (한/중 2개) |
| `_dd_video_embeds` | 검색된 YouTube 영상 JSON |
| `_dd_key_expr_image` | 핵심 표현 장식 배너 이미지 URL |
| `_dd_lesson_materials` | 학습 자료 JSON (PDF/DOCX/PPTX 첨부) |
| `_dd_public_token` | UUID v4 공개 토큰 |
| `_dd_public_active` | 공개 링크 활성 여부 (0/1) |

### CPT: `dd_story`

| Meta Key | 설명 |
|---|---|
| `_dd_story_nodes` | 분기형 스토리 노드 JSON (12-18개 장면) |
| `_dd_story_level` | 난이도 |
| `_dd_story_cover_image` | 커버 이미지 URL |
| `_dd_story_public_token` | UUID v4 공개 토큰 |
| `_dd_story_public_active` | 공개 활성 여부 |
| `_dd_story_course_id` | 연결 강좌 ID |

### wp_options

| Key | 설명 |
|---|---|
| `dd_lms_gemini_api_key` | AES-256-CBC 암호화된 Gemini API 키 |
| `dd_lms_gemini_model` | 선택된 Gemini 모델 (기본: `gemini-3.1-flash-lite`) |
| `dd_lms_youtube_key` | YouTube Data API v3 키 (별도) |
| `dd_lms_pixabay_key` | Pixabay API 키 (별도) |
| `dd_lms_version` | 플러그인 버전 |
| `dd_lms_landing_page_id` | 자동 생성된 랜딩페이지 ID |
| `dd_img_quota_exhausted` | 이미지 생성 쿼터 초과 캐시 (transient, 5분 TTL) |
| `dd_lms_rewrite_version` | rewrite rule 버전 (flush 트리거) |
| `dd_gemini_last_call` | 마지막 Gemini 호출 시각 (요청 간 2~3초 throttle용, autoload=false) |
| `dd_subs_{md5(video_id)}` | 추출 성공 자막 캐시 (transient, 7일 TTL) |

---

## 강의 탭 구성 (9섹션 사이드바)

| 탭 | 내용 |
|---|---|
| **학습 내용** | 9섹션 사이드바: 핵심표현 → 본문 → 학습만화 → 문화노트 → 실전대화 → 학습Song → 작문연습 → 학습자료 → 관련영상 |
| **슬라이드** | 5장 인포그래픽 (어휘/예문/팁), 레벨별 난이도 조정 |
| **오디오북** | TTS 중국어 전용 (zh-CN), 화자별 음성 프로필, 속도 조절 |
| **스토리북** | 6페이지 그림책 (중국어/병음/한국어 + 스타일 감지 이미지) |
| **퀴즈** | 6문제 (4지선다 2 + 빈칸 채우기 2 + 어순 배열 2) + 완료 패널 |

---

## AI 강좌 생성 흐름

```
1. 관리자: 주제 + 난이도 입력
   └→ POST /generate/course
      ├→ Gemini: 강좌 개요 JSON (제목, 설명, topic_en, 강의 목록)
      ├→ dd_course CPT 생성 (level 메타 저장)
      └→ Pixabay: 썸네일 자동 검색 & 다운로드

2. 프론트엔드: 강의 순차 생성
   └→ POST /generate/lesson (xN)
      ├→ Phase 1: Gemini 텍스트 생성
      │   ├ 본문 마크다운 (레벨별 대화 형식)
      │   ├ 구조화 대화 데이터 (dialogues JSON)
      │   ├ → rebuild_dialogue_section()으로 content 실전대화 재구축
      │   ├ 핵심 표현, 슬라이드 (레벨별 HSK 난이도)
      │   ├ 퀴즈 6문제 (choice + fill + order)
      │   ├ 구조화 문화노트 (summary/background/fun_facts/comparison)
      │   ├ 만화 4컷 + 스토리북 6페이지
      │   └ YouTube 키워드 2개 (한국어 + 중국어)
      │
      ├→ Phase 2: 이미지 생성 (CJK 텍스트 없는 프롬프트)
      │   ├ 개별 만화 패널 (순수 일러스트, 텍스트 없음)
      │   ├ gemini-2.5-flash-image → gemini-3-pro-image-preview
      │   ├ → gemini-3.1-flash-image-preview → imagen-4.0-fast-generate-001
      │   └ 쿼터 초과 시 5분간 전체 스킵 (transient 캐시)
      │
      ├→ Phase 3: YouTube 검색 (3개월 이내, relevance)
      │   └ 재검색: POST /lessons/{id}/retry-youtube
      │
      └→ Phase 4: 스토리북 이미지 (스타일 자동 감지: 현대/전통)
```

---

## 중국어 노래 학습 자막 추출 흐름

```
관리자: YouTube URL 입력 (영상/플레이리스트)
  └→ POST /song/fetch-info
     └→ RD* 믹스/라디오 플레이리스트 + 단일 영상 ID 동시 존재 시 단일 영상 우선
  └→ POST /song/fetch-subtitles
       ├→ 캐시 HIT: 7일 내 성공 자막은 재요청 없이 즉시 반환 (transient)
       ├→ 1차: Innertube iOS         ← PO token 불필요, 2025+ 가장 안정
       ├→ 2차: Innertube ANDROID_VR  ← PO token 불필요
       ├→ 3차: Innertube TVHTML5
       ├→ 4차: Innertube WEB (SOCS/CONSENT 쿠키)
       ├→ 5차: Innertube ANDROID (구버전)
       ├→ 6차: timedtext list API
       └→ 7차: watch 페이지 스크래핑 (brace-counting JSON 추출)
       · 자막 다운로드 429 → 지수 백오프(1~2초) 재시도, 지속 시 중단
       · 성공 시 transient 캐싱, debug.log에 단계별 진단 로그
  └→ (권장) 수동 자막: SRT/TXT/SMI/VTT 업로드 또는 붙여넣기
       └→ 자동 추출 실패 시 "가사 직접 입력" 칸이 UI에 자동 노출
  └→ POST /song/generate → 강좌 + 곡별 강의 생성
```

> ⚠️ **YouTube는 서버측 자막 긁기를 의도적으로 차단**합니다 (PO token / 429 / 서명 URL). 자동
> 추출은 "되면 좋은" 보조 수단이며, 막힌 영상은 **수동 입력**(아래 북마클릿 권장)이 가장 확실합니다.

### YouTube 자막 긁기 북마클릿 (429·CORS 우회)

서버가 막힐 때 쓰는 클라이언트 사이드 수집 도구. 관리자 브라우저의 **youtube.com 페이지 안에서**
실행되므로 CORS·PO token·429·로그인 제약을 모두 우회합니다.

- 설치 파일: `tools/youtube-transcript-bookmarklet.html` (브라우저로 열어 버튼을 북마크바로 드래그)
- 사용: 유튜브 영상 → **[…더보기] → [스크립트 표시]** 패널 열기 → 북마크 클릭 → 자막이
  클립보드에 복사됨 → dingdong **"가사 직접 입력"** 칸에 붙여넣기 → 강좌 생성
- 동작: 자막 패널 DOM(`ytd-transcript-segment-renderer` / 신형 `timeline-item-view-model`)을
  파싱 → UI 버전 무관. 실패 시 패널 HTML을 진단용으로 복사.
- 출력 형식: `[MM:SS] 중국어가사` (한국어 UI의 `1분 19초` 표기도 `[01:19]`로 정규화).
  타임스탬프를 남겨 학습자가 영상 해당 구간으로 이동·반복 학습할 수 있도록 함.

---

## 인터랙티브 스토리

```
관리자: 주제 + 난이도 입력
  └→ Gemini: 분기형 스토리 JSON (12-18개 노드, 4개 엔딩)
     ├→ 각 노드: text_zh/pinyin/text_ko + vocab(2-4) + mood + speaker
     ├→ grammar_tip: 문법 팁 (노드별)
     └→ 선택지: emoji + 중국어/한국어 + 분기 노드

학생:
  ├→ /stories/ → 스토리 목록 (커버 이미지 카드, 난이도 뱃지)
  └→ /story/{uuid}/ → 스토리 플레이
      ├→ 커버: 커버 이미지 + 장면/엔딩 수 + 수집 현황
      ├→ 플레이: 무드 배경 전환 + 화자 아바타 + 장면 이미지
      │   ├→ 어휘 하이라이트: 밑줄 클릭 → 팝업 (발음/뜻/단어장 저장)
      │   ├→ TTS: 중국어 음성 재생 (zh-CN)
      │   └→ breadcrumbs + 진행률 바
      ├→ 엔딩: 학습 요약 (방문 장면/어휘/선택 횟수) + 배운 어휘 목록
      └→ 엔딩 수집: localStorage 기록 → 재방문 시 수집 현황
```

---

## AI 단어장

```
학생: 핵심 표현 카드에서 "저장" 클릭 → localStorage에 단어 저장
  ├→ 독립 페이지 (/vocabulary/) 또는 강의 내 접근
  ├→ 플래시카드 모드: 앞면(중국어) ↔ 뒷면(한국어), 그래디언트 배경
  ├→ 4종 미니게임: 단어 맞추기 / 랜덤 배열 / 문장 빈칸 / 뜻 연결
  ├→ 암기 진행률: 안 외움 / 학습 중 / 완료
  ├→ 단어 팝업: 초급/중급/고급 예문 (Gemini 실시간 생성, localStorage 캐시)
  ├→ HSK 급수 표시
  └→ CSV UTF-8 (BOM) 내보내기 / CSV+JSON 가져오기
```

---

## AI 역할극 (叮叮 통합) + 작문 채점

```
叮叮 역할극 (v2.1에서 AI 튜터에서 叮叮으로 통합)
  ├→ 채팅 패널 내 "🎭 역할극" 퀵 액션으로 접근
  ├→ Gemini API 3모델 fallback
  ├→ 역할극 6 시나리오:
  │   ├ 🍜 식당 주문 — 메뉴 주문, 가격 확인
  │   ├ 🚕 택시 탑승 — 목적지, 경로, 요금 대화
  │   ├ 🛍️ 쇼핑 흥정 — 가격 흥정, 구매 대화
  │   ├ 🏨 호텔 체크인 — 예약 확인, 시설 안내
  │   ├ 🏥 병원 진료 — 증상 설명, 처방 대화
  │   └ 🏦 은행 업무 — 환전, 계좌 문의
  │
  ├→ multi-turn 대화: systemInstruction + conversationHistory
  ├→ 자동 평가: 사용 표현/교정 사항/종합 점수 (/10)
  └→ "그만/종료/끝" 입력 시 역할극 종료

작문 채점 (dd-ai-features.js에서 독립 동작)
  └→ 문법/어휘/자연스러움 점수 + 교정 + 한국어 피드백
```

---

## 글라스모피즘 UI

`--dd-glass` CSS 토큰 기반 15+ 컴포넌트에 적용:
- `backdrop-filter: blur()` + 반투명 배경
- topbar, 탭, 카드, 슬라이드, 퀴즈, 사이드바, 진도 위젯, AI 튜터, 코치마크 등

---

## 학습 진도 추적

```
localStorage 기반 (로그인 불필요):
  ├→ 탭 5초 체류 시 자동 완료 기록 (5탭)
  ├→ 퀴즈 점수 저장 + 최고 점수 추적
  ├→ 전체 완료 시 에빙하우스 복습 스케줄 자동 생성 (1/3/7/30일)
  ├→ 진도 위젯: 프로그레스 바 + 탭별 체크마크
  └→ 복습 알림 배너 (복습할 강의 N개 안내)
```

---

## 외부 API 의존성

| API | 용도 | 키 관리 |
|---|---|---|
| **Gemini API** | 텍스트 생성, 단어 예문, 작문 채점, 뉴스레터, 학습송 작사, 叮叮 AI 대화 | `dd_lms_gemini_api_key` (AES-256-CBC) |
| **Gemini Image** | 이미지 생성 (`gemini-2.5-flash-image` 등 4단계 fallback) | 같은 Gemini 키 |
| **Imagen 4.0** | 이미지 생성 최종 fallback (유료 플랜 필요) | 같은 Gemini 키 |
| **YouTube Data API v3** | 교육 영상 검색/임베드, 노래 영상 메타데이터 | `dd_lms_youtube_key` |
| **YouTube Innertube** | 자막 트랙 조회 (iOS / ANDROID_VR / TVHTML5 / WEB / ANDROID + watch 페이지, 키 불필요) | — |
| **Pixabay API** | 강좌 썸네일 이미지 | `dd_lms_pixabay_key` |
| **Web Speech API** | 叮叮 음성 입력 (SpeechRecognition) + TTS 출력 (SpeechSynthesis) | 브라우저 내장 |

---

## 기술 스택

- **서버**: WordPress Studio (PHP WASM + SQLite)
- **DB**: CPT + post_meta (커스텀 테이블 없음)
- **프론트엔드**: Vanilla JS (빌드 도구 없음, React 없음)
- **스타일**: CSS Custom Properties + 글라스모피즘, 반응형, Noto Sans SC + Pretendard
- **마크다운**: PHP 자체 렌더러 (`DD_Public_Access::render_markdown`)
- **대화 데이터**: 구조화 JSON 우선 (`_dd_dialogues_data`) → comic_panels fallback → content 파싱
- **TTS**: Web Speech API (zh-CN 오디오북/학습송, ko-KR 叮叮 환영)
- **음성 입력**: Web Speech API SpeechRecognition (ko-KR, 叮叮 채팅)
- **오디오 합성**: Web Audio API (학습송 비트 + 포먼트 보컬)
- **이미지**: CJK 텍스트 없는 순수 일러스트 프롬프트 + HTML 텍스트 오버레이
- **만화**: 하이브리드 그리드 — AI 개별 패널 이미지 + 프론트엔드 CSS 텍스트 오버레이
- **단어장**: localStorage CRUD + Gemini 실시간 예문 + CSV UTF-8 내보내기
- **인쇄**: `@media print` CSS, 슬라이드 + 학습 내용 PDF 다운로드

---

## 설치 및 배포

1. `dingdong-lms/` 폴더를 zip으로 압축
2. 타 워드프레스 사이트에서 **관리자 > 플러그인 > 새로 추가 > 플러그인 업로드**
3. 활성화 시 **자동으로**:
   - 랜딩페이지 (히어로섹션) 생성 + 프론트 페이지 설정
   - 네비게이션 메뉴 "DingDong LMS" 생성 (홈/강좌/스토리/뉴스레터/단어장)
   - rewrite rule 등록 (/courses/, /stories/, /lesson/{id}/, /story/{id}/ 등)
4. **DingDong LMS > 설정**에서 API 키 등록:
   - **Gemini API 키** (필수) — [Google AI Studio](https://aistudio.google.com/)
   - **YouTube Data API v3 키** (선택) — [Google Cloud Console](https://console.cloud.google.com/)
   - **Pixabay API 키** (선택) — [Pixabay API](https://pixabay.com/api/docs/)
5. 학생은 강의 페이지에서 본인 Gemini API 키 등록 → AI 튜터/작문 채점/叮叮 AI 대화/학습송 사용 가능

---

## 디버그

에러 발생 시: `wp-content/uploads/dingdong-lms/debug.log`

WordPress debug 로그 활성화:
```bash
studio site set --debug-log --debug-display
```
