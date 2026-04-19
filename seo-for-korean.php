<?php
/**
 * Plugin Name:       SEO for Korean
 * Plugin URI:        https://github.com/dalsoop/seo-for-korean
 * Description:       Korean WordPress SEO. Naver-aware, AI-assisted, GPL.
 * Version:           0.3.2
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

define( 'SFK_VERSION', '0.3.2' );
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

// Built-in PSR-4-ish autoloader. Maps `SEOForKorean\Foo\Bar_Baz`
// → `includes/foo/class-bar-baz.php`. Composer's autoloader, if present, takes
// precedence (registered first) — this is the fallback so the plugin works
// when shipped without `vendor/`.
if ( file_exists( SFK_PATH . 'vendor/autoload.php' ) ) {
	require_once SFK_PATH . 'vendor/autoload.php';
}

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'SEOForKorean\\';
		if ( strpos( $class, $prefix ) !== 0 ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$class_nm = array_pop( $parts );

		// PascalCase or Snake_Case → kebab-case for filesystem paths.
		$kebab = static function ( string $s ): string {
			$s = (string) preg_replace( '/(?<!^)([A-Z])/', '-$1', $s );
			$s = str_replace( '_', '-', $s );
			$s = (string) preg_replace( '/-+/', '-', $s );
			return strtolower( $s );
		};

		$dir  = $parts === [] ? '' : implode( '/', array_map( $kebab, $parts ) ) . '/';
		$file = SFK_PATH . 'includes/' . $dir . 'class-' . $kebab( $class_nm ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

\SEOForKorean\Plugin::instance()->boot();

register_activation_hook( __FILE__, [ \SEOForKorean\Plugin::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \SEOForKorean\Plugin::class, 'deactivate' ] );
