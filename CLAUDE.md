# DingDong LMS — 개발 규칙 (최상위)

이 파일이 이 프로젝트의 **최상위 개발 규칙**이다.
도메인 규칙(중국어 표기, TTS, 이미지, UI 등)은 `AGENTS.md`에 있고, 이 파일은
**"코드를 어떤 절차로 바꿀 것인가"** 를 규정한다. 둘 다 지켜야 한다.

@AGENTS.md

---

## 0. 코드 수정 전 필독

이 저장소의 루트는 **WordPress 설치 전체**다. 하지만 우리가 소유한 코드는 두 곳뿐이다.

| 소유 | 경로 | 수정 |
|---|---|---|
| 우리 코드 | `wp-content/plugins/dingdong-lms/` | 자유롭게 수정 |
| 우리 코드 | `wp-content/themes/dingdong/` | 자유롭게 수정 |
| 우리 코드 | `tests/` | 자유롭게 수정 |
| **WordPress Core** | `wp-admin/`, `wp-includes/`, 루트 `wp-*.php` | **수정 금지** |
| 서드파티 | `wp-content/plugins/akismet/`, `themes/twentytwenty*` | 수정 금지 |
| 인프라 | `wp-content/mu-plugins/sqlite-*`, `wp-content/db.php` | 삭제/수정 금지 |

WordPress 동작을 바꿔야 하면 **반드시** 훅(action/filter), 플러그인, 자식 테마,
REST API 중 하나를 쓴다. Studio 는 Playground(PHP-WASM)에서 동작하므로
Core 를 고쳐도 **서버 재시작 시 사라진다.** Core 수정이 정말 필요하다고 판단되면
직접 고치지 말고 **사용자에게 먼저 승인을 받는다.**

---

## 1. Harness 개발 사이클 (필수)

이 프로젝트에서 코드를 바꿀 때는 아래 순서를 따른다.
"요청 → 바로 수정"은 금지다.

```
요청
 ↓
① CLAUDE.md · AGENTS.md 규칙 확인
 ↓
② 관련 코드 · 기존 테스트 분석
 ↓
③ 변경 범위 정의 (건드리지 않을 것도 함께 정의)
 ↓
④ 테스트 먼저 작성          ← 순수 로직이면 필수
 ↓
⑤ RED 확인 (실제로 실패하는지 눈으로 확인)
 ↓
⑥ 최소 구현
 ↓
⑦ GREEN 확인
 ↓
⑧ Refactor (테스트 유지한 채)
 ↓
⑨ php tests/verify.php  ← 전체 검증 게이트
 ↓
⑩ git status / git diff 검토
 ↓
완료
```

### 왜 이 순서인가

- **⑤ RED 를 건너뛰지 말 것.** 실패를 확인하지 않은 테스트는 "항상 통과하는
  가짜 테스트"일 수 있다. 실제로 실패하는 것을 본 뒤에만 구현으로 넘어간다.
- **③ 변경 범위 정의**가 있어야 AI 가 요청 범위를 넘어 대규모 리팩터링을
  시작하는 사고를 막는다. 요청하지 않은 리팩터링은 하지 않는다.
- **⑨ 검증 게이트**가 없으면 "고쳤다"는 보고가 검증되지 않은 주장이 된다.

---

## 2. 검증 명령 (이 프로젝트에 실제로 존재하는 것만)

```bash
php tests/verify.php
```

이 한 줄이 4단계를 모두 실행한다.

| 단계 | 내용 |
|---|---|
| 1 | PHP 구문 검사 — 플러그인·테마 전체 `php -l` |
| 2 | JS 구문 검사 — `node --check` |
| 3 | 하드코딩 자격증명 스캔 — 새 API 키가 코드에 섞여 들어왔는지 |
| 4 | 단위 테스트 — `tests/run.php` |

개별 실행:

```bash
php tests/run.php
```

특정 테스트 파일만:

```bash
php tests/run.php dd-chinese
```

### 이 프로젝트에 **없는** 것 (억지로 실행하지 말 것)

Composer · PHPUnit · npm · 빌드 시스템 · PHPStan · PHPCS · Playwright 는
**설치되어 있지 않다.** `composer test`, `npm test`, `vendor/bin/phpunit` 같은
명령을 추측해서 실행하지 말 것. 배포 산출물은 플러그인 폴더를 zip 으로 압축하는
것이 전부이므로 별도 build 단계가 없다.

---

## 3. 테스트 작성 규칙

테스트는 `tests/unit/*.test.php` 에 둔다. 프레임워크는 의존성이 없다.

```php
require_once DD_PLUGIN_DIR . '/includes/class-dd-chinese.php';

test( '설명은 한국어로, 무엇을 보장하는지 쓴다', function () {
    assert_same( '爱', DD_Chinese::to_simplified( '愛' ) );
} );
```

사용 가능한 단언: `assert_same` `assert_not_same` `assert_true` `assert_false`
`assert_contains` `assert_not_contains` `assert_matches` `assert_empty` `assert_not_empty`

환경이 안 갖춰져 검증할 수 없으면 **통과로 위장하지 말고** 건너뛴다:

```php
require_that( extension_loaded( 'openssl' ), 'openssl 확장 필요' );
```

### 무엇을 테스트할 것인가

**우선순위 높음 — 순수 로직 (WordPress 없이 검증 가능)**
- 번체→간체 변환, 마크다운 렌더링, `chinese_only()` 같은 TTS 정제
- 자격증명 해석 순서, 파서, 데이터 변환
- `AGENTS.md` 의 필수 규칙 → **규칙 하나당 테스트 하나**로 고정한다

**테스트 계층으로 검증 불가 — Studio 사이트에서 수동 확인**
- `WP_Query`, `$wpdb`, 훅 실행, REST 라우트 등록, 권한 검사
- DOM/CSS/애니메이션, Web Speech API, YouTube IFrame API
- 실제 Gemini/YouTube/Pixabay/Suno API 응답

**테스트를 위한 테스트를 만들지 말 것.** 스텁이 스텁을 검증하는 테스트는
가치가 없다. 실제 기능의 동작을 보호하는 테스트만 쓴다.

### WordPress 함수 스텁

`tests/wp-stubs.php` 에 **실제로 필요한 함수만** 있다. WordPress 를
재구현하는 곳이 아니다. 필요한 함수가 생기면 그때 추가한다.

---

## 4. 자격증명 관리

### 해석 순서

```
PHP 상수  →  환경변수(.env)  →  wp_options (관리자 화면 등록 키)
```

`DD_Env::get( 'DD_GEMINI_API_KEY' )` 가 앞의 두 단계를 담당하고,
값이 없으면 각 게터가 기존 `wp_options` 경로로 폴백한다.

| 변수 | 용도 |
|---|---|
| `DD_GEMINI_API_KEY` | 강좌·이미지·스토리·뉴스레터 생성 |
| `DD_YOUTUBE_API_KEY` | 관련 영상 검색/임베드 |
| `DD_PIXABAY_API_KEY` | 강좌 썸네일 |

> `DD_SUNO_API_KEY` 는 **더 이상 쓰지 않는다.** AI 학습송(SUNO) 기능이 제거되었다 (`AGENTS.md` 규칙 12).

### 반드시 지킬 것

- **`.env` 를 커밋하지 않는다.** `.gitignore` 로 막혀 있지만 확인은 습관으로.
- **`.env.example` 에는 실제 값을 넣지 않는다.** 빈 값과 주석만.
- **키를 로그에 그대로 남기지 않는다.** 남겨야 하면 `DD_Env::mask()` 를 쓴다.
- **환경변수는 오버라이드일 뿐이다.** 관리자 화면 등록 경로를 제거하면
  zip 배포 모델(`AGENTS.md` 규칙 8)이 깨진다. 제거 금지.
- Playground 에서 `getenv()` 가 안 먹을 수 있다. 확실한 주입 경로는
  `wp-config.php` 의 `define()` 이다.

### 알려진 이슈 (미해결)

`wp-config.php` 의 인증 솔트 8개가 기본 플레이스홀더
(`'put your unique phrase here'`) 상태다. `DD_API_Key`·`DD_Suno` 는 `AUTH_KEY` 로
AES-256-CBC 암호화하므로, **현재 저장 키의 암호화는 사실상 난독화 수준**이다.

- 로컬 개발 사이트라 즉각적 위험은 낮아 **의도적으로 보류** 중이다.
- 이 사이트를 외부에 공개하거나 실제 키를 넣기 전에는 반드시 솔트를 재생성할 것.
- 솔트를 바꾸면 **로그인 세션이 만료되고 기존 저장 API 키는 복호화 불가**가 되어
  재입력이 필요하다. 사용자 승인 없이 바꾸지 말 것.

---

## 5. 승인 없이 하지 말아야 할 것

**Git**

```
git reset --hard      git clean -fd       git push --force
git rebase            git filter-repo     이력 재작성 전반
```

**데이터**

```
DB 초기화 / 삭제      wp_options 삭제     사용자 데이터 삭제
대량 데이터 변경      마이그레이션 실행
```

**보안**

```
API 키 revoke / rotation     자격증명 삭제     wp-config.php 솔트 변경
```

**WordPress Core** — 위 0절 표 참조.

**환경** — `preview_start` 등 별도 dev server 기동 (`AGENTS.md` 최상단 경고).
Studio 가 이미 `http://localhost:8883` 에서 사이트를 띄우고 있다.
임시 `__*_preview.html` 파일을 플러그인 폴더에 만들지 말 것 — 배포 zip 에 섞인다.

---

## 6. Git

이 저장소는 **커스텀 코드만** 추적한다. `.gitignore` 는 "전부 무시 후
필요한 것만 되살리는" 허용목록 방식이다. WordPress Core, uploads, SQLite DB,
`.env`, `paper_images/` 는 추적되지 않는다.

변경 후 반드시 확인할 것:

```bash
git status
```

- WordPress Core 파일이 올라오지 않았는가
- `.env` 가 추적되고 있지 않은가
- 자격증명이 diff 에 노출되지 않았는가
- 요청하지 않은 대규모 리팩터링이 섞이지 않았는가
- 테스트 없이 로직만 추가되지 않았는가

커밋과 푸시는 **사용자가 요청할 때만** 한다.

---

## 7. 문서 동기화

기능을 추가·변경한 뒤 사용자가 문서 갱신을 요청하면 `dingdong-docs-sync` 스킬을
사용한다 (GUIDE.md / MANUAL-PROMPTS.md / 학위논문 DOCX). 수동 트리거 전용이며,
기능 추가만으로 자동 실행하지 않는다.

`AGENTS.md` 의 파일 역할표는 파일을 추가·삭제했을 때 함께 갱신한다.

---

## 8. 개발 흐름 요약도

![TDD + Harness 개발 흐름](paper_images/tdd-harness-wordpress-workflow.png)

원본은 `paper_images/tdd-harness-wordpress-workflow.svg` 이고, PNG 는 거기서
렌더링한 결과다. 두 파일 모두 `paper_images/` 에 있으며 **Git 추적 대상이 아니다.**

다이어그램을 고칠 때는 **SVG 를 수정한 뒤 PNG 를 다시 렌더링**한다.
이 환경에는 GD·ImageMagick·Inkscape 가 없으므로 Node 로 변환한다:

```bash
npm install @resvg/resvg-js
```

```bash
node -e "const{Resvg}=require('@resvg/resvg-js'),fs=require('fs');const s='paper_images/tdd-harness-wordpress-workflow';fs.writeFileSync(s+'.png',new Resvg(fs.readFileSync(s+'.svg','utf8'),{background:'#f8fafc',fitTo:{mode:'width',value:1740},font:{loadSystemFonts:true,defaultFontFamily:'Malgun Gothic'}}).render().asPng())"
```

> ⚠️ Windows 의 `convert` 는 ImageMagick 이 아니라 **디스크 파일시스템 변환
> 도구(`C:\Windows\system32\convert.exe`)** 다. 이미지 변환 용도로 절대 실행하지 말 것.
