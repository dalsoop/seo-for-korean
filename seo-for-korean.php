<?php
/**
 * Plugin Name:       SEO for Korean
 * Plugin URI:        https://github.com/dalsoop/seo-for-korean
 * Description:       Korean WordPress SEO. Naver-aware, AI-assisted, GPL.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Dalsoop
 * Author URI:        https://github.com/dalsoop
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       seo-for-korean
 * Domain Path:       /languages
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'SFK_VERSION', '0.1.0' );
define( 'SFK_FILE', __FILE__ );
define( 'SFK_PATH', plugin_dir_path( __FILE__ ) );
define( 'SFK_URL', plugin_dir_url( __FILE__ ) );
define( 'SFK_BASENAME', plugin_basename( __FILE__ ) );
define( 'SFK_MIN_PHP', '8.0' );
define( 'SFK_MIN_WP', '6.0' );

// Bail early if PHP version is too low — register a notice instead of fataling.
if ( version_compare( PHP_VERSION, SFK_MIN_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: required PHP version, 2: current PHP version */
						__( 'SEO for Korean requires PHP %1$s or higher. You are running %2$s.', 'seo-for-korean' ),
						SFK_MIN_PHP,
						PHP_VERSION
					)
				)
			);
		}
	);
	return;
}

if ( file_exists( SFK_PATH . 'vendor/autoload.php' ) ) {
	require_once SFK_PATH . 'vendor/autoload.php';
}

require_once SFK_PATH . 'includes/class-helper.php';
require_once SFK_PATH . 'includes/class-modules.php';
require_once SFK_PATH . 'includes/class-plugin.php';

\SEOForKorean\Plugin::instance()->boot();

register_activation_hook( __FILE__, [ \SEOForKorean\Plugin::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \SEOForKorean\Plugin::class, 'deactivate' ] );
