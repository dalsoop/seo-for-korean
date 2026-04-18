<?php
/**
 * PHPUnit bootstrap — sets up just enough WP shimming to test pure PHP
 * classes (Content_Analyzer, Helper) without loading WordPress core.
 *
 * Tests that need WP runtime should live in a separate `tests/integration/`
 * suite with WP_UnitTestCase + a real test install. We don't have that yet —
 * unit tests come first.
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

if ( ! defined( 'SFK_PATH' ) ) {
	define( 'SFK_PATH', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'SFK_VERSION' ) ) {
	define( 'SFK_VERSION', '0.0.0-test' );
}

// Minimum WP function shims used by the units under test.
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( string $string, bool $remove_breaks = false ): string {
		$string = (string) preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $string );
		$string = strip_tags( $string );
		if ( $remove_breaks ) {
			$string = (string) preg_replace( '/[\r\n\t ]+/', ' ', $string );
		}
		return trim( $string );
	}
}

// Plugin's own autoloader, copied verbatim so tests stay in sync with prod.
spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'SEOForKorean\\';
		if ( strpos( $class, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$class_nm = array_pop( $parts );

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
