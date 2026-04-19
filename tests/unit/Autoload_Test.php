<?php
/**
 * Smoke test that every plugin class autoloads cleanly.
 *
 * Catches the class FQCN ↔ filesystem path mismatches the autoloader
 * silently swallows (file_exists() false → no require, class missing
 * fails later somewhere far from the broken file).
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class Autoload_Test extends TestCase {

	/**
	 * Walk every includes/**\/class-*.php, parse out the class declaration,
	 * compose the FQCN from `namespace` + class name, then assert
	 * class_exists() — which forces the autoloader to actually find the
	 * file. Any mismatch fails this test loudly with the offending pair.
	 */
	public function test_every_plugin_class_autoloads(): void {
		$root = SFK_PATH . 'includes';
		$it   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );

		$failures = [];
		$checked  = 0;

		foreach ( $it as $file ) {
			if ( $file->getExtension() !== 'php' ) {
				continue;
			}
			if ( ! str_starts_with( $file->getBasename(), 'class-' ) ) {
				continue;
			}

			$contents = (string) file_get_contents( $file->getPathname() );

			$ns = '';
			if ( preg_match( '/^\s*namespace\s+([^;\s]+)\s*;/m', $contents, $m ) ) {
				$ns = $m[1];
			}

			// Match `final class X`, `abstract class X`, `class X` —
			// only the *declaration* (must be at line start to skip
			// docblock prose like "this class owns…").
			if ( ! preg_match( '/^(?:final|abstract)?\s*class\s+(\w+)/m', $contents, $m ) ) {
				continue;
			}
			$class = $m[1];
			$fqcn  = $ns !== '' ? $ns . '\\' . $class : $class;

			++$checked;

			if ( ! class_exists( $fqcn ) ) {
				$failures[] = sprintf(
					'%s declared in %s did not autoload',
					$fqcn,
					str_replace( SFK_PATH, '', $file->getPathname() )
				);
			}
		}

		self::assertGreaterThan( 0, $checked, 'expected to find at least some classes to check' );
		self::assertSame( [], $failures, "Autoload failures:\n  " . implode( "\n  ", $failures ) );
	}
}
