<?php
/**
 * DingDong LMS — WordPress 함수 스텁.
 *
 * 플러그인의 순수 로직(간체 변환, 마크다운 렌더링, 키 해석 등)을
 * WordPress 부팅 없이 테스트하기 위한 최소 구현이다.
 *
 * ⚠️ 이 스텁은 "테스트 대상 코드가 실제로 사용하는 함수"만 담는다.
 *    WordPress를 재구현하려는 목적이 아니다. 필요한 함수가 생기면 여기에 추가한다.
 *    WordPress Core 와의 실제 상호작용(WP_Query, wpdb, 훅 등)은 이 계층에서
 *    검증할 수 없으므로, 그런 코드는 Studio 사이트에서 수동 확인해야 한다.
 */

/* =============================================================
   인메모리 옵션 저장소
   ============================================================= */

$GLOBALS['dd_test_options'] = array();

/** 테스트 간 격리를 위해 옵션 저장소를 초기화한다. */
function dd_test_reset_options() {
	$GLOBALS['dd_test_options'] = array();
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['dd_test_options'] )
			? $GLOBALS['dd_test_options'][ $name ]
			: $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value ) {
		$GLOBALS['dd_test_options'][ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	function add_option( $name, $value ) {
		if ( array_key_exists( $name, $GLOBALS['dd_test_options'] ) ) {
			return false;
		}
		$GLOBALS['dd_test_options'][ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $name ) {
		unset( $GLOBALS['dd_test_options'][ $name ] );
		return true;
	}
}

/* =============================================================
   새니타이즈 / 이스케이프
   ============================================================= */

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		$str = (string) $str;
		$str = strip_tags( $str );
		$str = preg_replace( '/[\r\n\t]+/', ' ', $str );
		return trim( $str );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		$url = trim( (string) $url );
		if ( $url === '' ) {
			return '';
		}
		// 허용 스킴만 통과 (WordPress 의 esc_url 을 크게 단순화한 형태).
		if ( ! preg_match( '#^(https?:|mailto:|/|\#)#i', $url ) ) {
			return '';
		}
		return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	/**
	 * WordPress sanitize_title 의 근사 구현.
	 * 앵커 ID 생성에만 쓰이므로 "소문자 + 안전문자만 + 하이픈" 수준이면 충분하다.
	 * CJK 는 WordPress 처럼 퍼센트 인코딩한다.
	 */
	function sanitize_title( $title, $fallback = '', $context = 'save' ) {
		$title = strtolower( trim( (string) $title ) );
		$title = strip_tags( $title );
		// ASCII 영숫자/하이픈 외의 문자는 퍼센트 인코딩 (WordPress 동작과 유사).
		$title = preg_replace_callback(
			'/[^a-z0-9\-_]/u',
			function ( $m ) {
				if ( $m[0] === ' ' ) {
					return '-';
				}
				return rawurlencode( $m[0] );
			},
			$title
		);
		$title = preg_replace( '/-+/', '-', $title );
		$title = trim( $title, '-' );
		return $title === '' ? $fallback : $title;
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	/**
	 * WordPress wp_kses_post 의 근사 구현.
	 *
	 * 본문에서 흔히 쓰는 태그만 남기고 나머지(특히 <script>)는 제거한다.
	 * 정확한 kses 는 아니지만, "허용 태그는 보존하고 스크립트는 없앤다"는
	 * 성질을 검증하는 데는 충분하다. 실제 필터링은 WordPress 가 담당한다.
	 */
	function wp_kses_post( $data ) {
		$allowed = '<a><b><strong><i><em><u><s><br><p><span><div><ul><ol><li>'
			. '<h1><h2><h3><h4><h5><h6><blockquote><code><pre><img><table><thead>'
			. '<tbody><tr><th><td><hr><small><sup><sub><figure><figcaption>';
		return strip_tags( (string) $data, $allowed );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}

if ( ! function_exists( 'wp_upload_dir' ) ) {
	/** 경로만 필요한 코드를 위한 최소 구현. 실제 업로드 처리는 검증하지 않는다. */
	function wp_upload_dir( $time = null, $create_dir = true, $refresh_cache = false ) {
		return array(
			'basedir' => sys_get_temp_dir() . '/dd-test-uploads',
			'baseurl' => 'http://example.test/wp-content/uploads',
			'error'   => false,
		);
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $string, $remove_breaks = false ) {
		$string = strip_tags( (string) $string );
		if ( $remove_breaks ) {
			$string = preg_replace( '/[\r\n\t ]+/', ' ', $string );
		}
		return trim( $string );
	}
}

/* =============================================================
   WP_Error
   ============================================================= */

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $errors = array();
		public $error_data = array();

		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( $code === '' ) {
				return;
			}
			$this->errors[ $code ][] = $message;
			if ( $data !== '' ) {
				$this->error_data[ $code ] = $data;
			}
		}

		public function get_error_code() {
			$codes = array_keys( $this->errors );
			return empty( $codes ) ? '' : $codes[0];
		}

		public function get_error_message( $code = '' ) {
			if ( $code === '' ) {
				$code = $this->get_error_code();
			}
			return isset( $this->errors[ $code ][0] ) ? $this->errors[ $code ][0] : '';
		}

		public function get_error_data( $code = '' ) {
			if ( $code === '' ) {
				$code = $this->get_error_code();
			}
			return isset( $this->error_data[ $code ] ) ? $this->error_data[ $code ] : null;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

/* =============================================================
   훅 시스템 (호출만 흡수하는 무동작 스텁)
   ============================================================= */

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) {
		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook ) {
		return null;
	}
}

/* =============================================================
   기타 유틸
   ============================================================= */

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options | JSON_UNESCAPED_UNICODE, $depth );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}
