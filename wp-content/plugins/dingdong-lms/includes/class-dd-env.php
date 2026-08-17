<?php
/**
 * DD_Env — 자격증명 해석기 (개발용 오버라이드 계층).
 *
 * ── 설계 원칙 ────────────────────────────────────────────────
 * 이 플러그인은 zip 으로 배포되어, 설치한 사이트의 관리자가 본인 API 키를
 * 관리자 화면에서 등록하는 것이 "정상 경로"다(AGENTS.md 규칙 8).
 * 따라서 환경변수는 그 경로를 대체하지 않고 **덮어쓰기만** 한다.
 *
 *   해석 우선순위:  PHP 상수  →  환경변수  →  wp_options (호출부에서 폴백)
 *
 * 이렇게 하면
 *   - 로컬 개발자는 .env / wp-config.php 상수로 키를 주입해 DB 없이 작업하고,
 *   - 배포 사이트 사용자는 아무 설정 없이 기존 관리자 화면을 그대로 쓴다.
 *
 * ⚠️ Playground(PHP-WASM)에서는 getenv() 가 제한적일 수 있으므로,
 *    가장 확실한 주입 경로는 wp-config.php 의 define() 이다.
 *
 * @since 2.5.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DD_Env {

	/** .env 를 이미 로드했는지 (중복 로드 방지). */
	private static $loaded = false;

	/**
	 * 자격증명 값을 해석한다.
	 *
	 * @param string $name 상수/환경변수 이름 (예: 'DD_GEMINI_API_KEY').
	 * @return string 값이 없으면 빈 문자열.
	 */
	public static function get( $name ) {
		// 1순위: PHP 상수 (wp-config.php 의 define — Playground 에서도 항상 동작).
		if ( defined( $name ) ) {
			$value = constant( $name );
			if ( is_string( $value ) && trim( $value ) !== '' ) {
				return trim( $value );
			}
		}

		// 2순위: 환경변수.
		$value = getenv( $name );
		if ( is_string( $value ) && trim( $value ) !== '' ) {
			return trim( $value );
		}

		// 3순위: 일부 SAPI 는 $_ENV / $_SERVER 에만 채워 넣는다.
		foreach ( array( $_ENV, $_SERVER ) as $bag ) {
			if ( isset( $bag[ $name ] ) && is_string( $bag[ $name ] ) && trim( $bag[ $name ] ) !== '' ) {
				return trim( $bag[ $name ] );
			}
		}

		// 없음 → 호출부가 wp_options 로 폴백한다.
		return '';
	}

	/**
	 * 오버라이드 값이 존재하는지.
	 *
	 * @param string $name
	 * @return bool
	 */
	public static function has( $name ) {
		return self::get( $name ) !== '';
	}

	/**
	 * .env 파일 내용을 파싱한다 (순수 함수 — 파일 I/O 없음).
	 *
	 * 지원: KEY=VALUE, export 접두사, # 주석, 빈 줄, 따옴표 감싼 값, CRLF.
	 * 미지원(의도적): 변수 보간(${VAR}), 여러 줄 값 — 자격증명 저장에 불필요하고
	 *                 파서를 복잡하게 만들어 오동작 위험만 키운다.
	 *
	 * @param string $contents
	 * @return array<string,string>
	 */
	public static function parse_dotenv( $contents ) {
		$out = array();

		if ( ! is_string( $contents ) || $contents === '' ) {
			return $out;
		}

		$lines = preg_split( '/\r\n|\r|\n/', $contents );
		if ( ! is_array( $lines ) ) {
			return $out;
		}

		foreach ( $lines as $line ) {
			$line = trim( $line );

			// 빈 줄 / 주석.
			if ( $line === '' || strpos( $line, '#' ) === 0 ) {
				continue;
			}

			// `export KEY=VALUE` 형태 허용.
			if ( strpos( $line, 'export ' ) === 0 ) {
				$line = trim( substr( $line, 7 ) );
			}

			// `=` 가 없으면 KEY=VALUE 형식이 아니므로 건너뛴다.
			$pos = strpos( $line, '=' );
			if ( $pos === false ) {
				continue;
			}

			$key   = trim( substr( $line, 0, $pos ) );
			$value = trim( substr( $line, $pos + 1 ) ); // 값 안의 '=' 는 보존됨.

			if ( $key === '' ) {
				continue;
			}

			// 값을 감싼 따옴표 한 겹 제거.
			$len = strlen( $value );
			if ( $len >= 2 ) {
				$first = $value[0];
				$last  = $value[ $len - 1 ];
				if ( ( $first === '"' && $last === '"' ) || ( $first === "'" && $last === "'" ) ) {
					$value = substr( $value, 1, -1 );
				}
			}

			$out[ $key ] = $value;
		}

		return $out;
	}

	/**
	 * 프로젝트 루트의 .env 를 읽어 환경에 주입한다 (로컬 개발 전용).
	 *
	 * - 파일이 없으면 조용히 무시한다 → 배포 사이트에 영향 없음.
	 * - **이미 정의된 상수/환경변수는 덮어쓰지 않는다** (실서버 설정 우선).
	 *
	 * @param string|null $path 기본값: WordPress 설치 루트의 .env
	 * @return int 주입된 변수 개수.
	 */
	public static function load_dotenv( $path = null ) {
		if ( self::$loaded ) {
			return 0;
		}
		self::$loaded = true;

		if ( $path === null ) {
			$path = ABSPATH . '.env';
		}

		if ( ! is_readable( $path ) ) {
			return 0;
		}

		$contents = file_get_contents( $path );
		if ( $contents === false ) {
			return 0;
		}

		$injected = 0;
		foreach ( self::parse_dotenv( $contents ) as $key => $value ) {
			// 이미 설정된 값은 존중한다.
			if ( defined( $key ) || getenv( $key ) !== false ) {
				continue;
			}
			putenv( $key . '=' . $value );
			$_ENV[ $key ] = $value;
			++$injected;
		}

		return $injected;
	}

	/**
	 * 자격증명을 로그에 남길 때 쓰는 마스킹.
	 *
	 * 앞뒤 4글자만 남기고 가운데는 **고정 길이** 별표로 대체한다.
	 * 별표 개수를 고정하는 이유: 마스킹 결과에서 원래 키 길이가 유추되지 않도록.
	 *
	 * @param string $value
	 * @return string
	 */
	public static function mask( $value ) {
		if ( ! is_string( $value ) || $value === '' ) {
			return '';
		}
		if ( strlen( $value ) <= 8 ) {
			return str_repeat( '*', 8 );
		}
		return substr( $value, 0, 4 ) . str_repeat( '*', 8 ) . substr( $value, -4 );
	}
}
