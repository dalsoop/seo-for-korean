<?php
/**
 * 404 monitor — logs every 404 hit so admins can see what URLs are
 * leaking link equity. Pairs with the Redirections module: V2 admin UI
 * will turn the top entries into one-click redirect suggestions.
 *
 * Storage shape (sfk_settings.404_log):
 *   path => [count, first, last, referer]
 *
 * Bounded at 50 entries with LRU eviction so the option doesn't grow
 * unbounded under bot scan storms. The cap is high enough to capture
 * the 10-20 paths that matter for any normal-sized site.
 *
 * Performance: writes the option on every 404. For typical sites that's
 * a few writes per day; for sites with heavy bot 404 noise (open
 * directories getting probed for /wp-admin/, /.env, etc) it could be
 * thousands. The IGNORE_PATTERNS list filters the worst offenders so
 * the log stays useful instead of full of crawler garbage.
 *
 * Public API:
 *   Monitor_404_Module::get_log()    // for V2 admin UI / sidebar
 *   Monitor_404_Module::clear_log()
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\Monitor_404;

use SEOForKorean\Helper;

defined( 'ABSPATH' ) || exit;

final class Monitor_404_Module {

	private const MAX_ENTRIES = 50;

	/** Skip these — they're bot probes, not real 404s worth fixing. */
	private const IGNORE_PATTERNS = [
		'/^\/wp-admin/i',
		'/^\/wp-login/i',
		'/^\/xmlrpc\.php/i',
		'/^\/wp-content\/plugins\//i',
		'/\.env$/i',
		'/\.git/i',
		'/\.aspx?$/i',
		'/\.php$/i',  // most PHP probes are scanners looking for backdoors
		'/\/\.well-known\//i',
	];

	public function boot(): void {
		// After Redirections (priority 1) — by now WP knows it's a 404.
		add_action( 'template_redirect', [ $this, 'maybe_log' ], 99 );
	}

	public function maybe_log(): void {
		if ( ! is_404() ) {
			return;
		}
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		$uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) )
			: '';
		if ( $uri === '' ) {
			return;
		}

		$parsed = wp_parse_url( $uri );
		$path   = (string) ( $parsed['path'] ?? '/' );
		if ( $path === '/' || $path === '' ) {
			return;
		}

		// Filter out scanner noise.
		foreach ( self::IGNORE_PATTERNS as $pattern ) {
			if ( @preg_match( $pattern, $path ) === 1 ) {
				return;
			}
		}

		// Allow filter to ignore additional paths.
		if ( ! (bool) apply_filters( 'sfk/404_monitor/should_log', true, $path ) ) {
			return;
		}

		$log = (array) Helper::get_settings( '404_log', [] );

		if ( isset( $log[ $path ] ) ) {
			$log[ $path ]['count'] = (int) ( $log[ $path ]['count'] ?? 0 ) + 1;
			$log[ $path ]['last']  = time();
		} else {
			// Cap the log — evict the entry that hasn't been hit in the longest.
			if ( count( $log ) >= self::MAX_ENTRIES ) {
				$log = self::evict_oldest( $log );
			}
			$log[ $path ] = [
				'count'   => 1,
				'first'   => time(),
				'last'    => time(),
				'referer' => isset( $_SERVER['HTTP_REFERER'] )
					? esc_url_raw( wp_unslash( (string) $_SERVER['HTTP_REFERER'] ) )
					: '',
			];
		}

		Helper::update_settings( '404_log', $log );
	}

	/**
	 * @param array<string, array<string, mixed>> $log
	 * @return array<string, array<string, mixed>>
	 */
	private static function evict_oldest( array $log ): array {
		$oldest_key  = null;
		$oldest_time = PHP_INT_MAX;
		foreach ( $log as $key => $entry ) {
			$last = (int) ( $entry['last'] ?? 0 );
			if ( $last < $oldest_time ) {
				$oldest_time = $last;
				$oldest_key  = $key;
			}
		}
		if ( $oldest_key !== null ) {
			unset( $log[ $oldest_key ] );
		}
		return $log;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_log(): array {
		$log = (array) Helper::get_settings( '404_log', [] );
		// Sort by count desc for usefulness.
		uasort(
			$log,
			static fn ( $a, $b ): int => ( (int) ( $b['count'] ?? 0 ) ) <=> ( (int) ( $a['count'] ?? 0 ) )
		);
		return $log;
	}

	public static function clear_log(): void {
		Helper::update_settings( '404_log', [] );
	}
}
