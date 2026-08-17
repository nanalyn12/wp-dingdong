<?php
/**
 * Plugin Name: Dingdong LMS
 * Description: 한국인을 위한 AI 기반 중국어·중국문화 교육 플랫폼
 * Version: 2.5.0
 * Author: Dingdong
 * Text Domain: dingdong-lms
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 플러그인 헤더의 Version 과 반드시 동일하게 유지할 것.
// (헤더는 WordPress 가 표시·업데이트 판단에, 이 상수는 에셋 캐시버스팅에 쓴다)
define( 'DD_LMS_VERSION', '2.5.0' );
define( 'DD_LMS_PATH', plugin_dir_path( __FILE__ ) );
define( 'DD_LMS_URL', plugin_dir_url( __FILE__ ) );

// DD_Env 는 자격증명 해석기이므로 이를 사용하는 모든 클래스보다 먼저 로드한다.
require_once DD_LMS_PATH . 'includes/class-dd-env.php';

// 로컬 개발용 .env 오버라이드 (파일이 없으면 아무 일도 하지 않음 → 배포 사이트 영향 없음).
// 이미 정의된 상수/환경변수는 덮어쓰지 않는다.
DD_Env::load_dotenv();

require_once DD_LMS_PATH . 'includes/class-dd-loader.php';
require_once DD_LMS_PATH . 'includes/class-dd-post-types.php';
require_once DD_LMS_PATH . 'includes/class-dd-api-key.php';
require_once DD_LMS_PATH . 'includes/class-dd-chinese.php';
require_once DD_LMS_PATH . 'includes/class-dd-gemini.php';
require_once DD_LMS_PATH . 'includes/class-dd-course-generator.php';
require_once DD_LMS_PATH . 'includes/class-dd-image-generator.php';
require_once DD_LMS_PATH . 'includes/class-dd-youtube-search.php';
require_once DD_LMS_PATH . 'includes/class-dd-thumbnail.php';
require_once DD_LMS_PATH . 'includes/class-dd-public-access.php';
require_once DD_LMS_PATH . 'includes/class-dd-rest-api.php';
require_once DD_LMS_PATH . 'includes/class-dd-story-generator.php';
require_once DD_LMS_PATH . 'includes/class-dd-newsletter-generator.php';
require_once DD_LMS_PATH . 'includes/class-dd-youtube-subtitles.php';
require_once DD_LMS_PATH . 'includes/class-dd-song-course-generator.php';
require_once DD_LMS_PATH . 'includes/class-dd-backup.php';
require_once DD_LMS_PATH . 'includes/class-dd-setup.php';

function dd_lms_activate() {
    DD_Post_Types::register();
    DD_Public_Access::add_rewrite_rules();
    flush_rewrite_rules();
    update_option( 'dd_lms_version', DD_LMS_VERSION );
    DD_Setup::activate();
}
register_activation_hook( __FILE__, 'dd_lms_activate' );

function dd_lms_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'dd_lms_deactivate' );

DD_Loader::init();
