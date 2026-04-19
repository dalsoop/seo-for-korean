<?php
/**
 * Redirections module — pattern-based URL redirects.
 *
 * Hooks template_redirect at priority 1 so we run before WordPress
 * decides to 404. Three match types per rule:
 *
 *   exact   — '/old-page' → '/new-page'
 *   prefix  — '/blog/2020' → '/archive/2020' (suffix preserved:
 *              /blog/2020/post → /archive/2020/post)
 *   regex   — '^/category-(\d+)$' → '/cat/$1'  (preg_replace style)
 *
 * Statuses supported: 301 (default), 302, 307, 410 (gone — emits the
 * status with no Location header).
 *
 * Storage: WP option sfk_settings.redirects (array of rule arrays).
 * V1 has no admin UI — power users add rules via filter or code:
 *
 *   add_filter('sfk/redirects/rules', function($rules) {
 *       $rules[] = [
 *           'from' => '/old-url',
 *           'to'   => '/new-url',
 *           'type' => 'exact',
 *           'status' => 301,
 *       ];
 *       return $rules;
 *   });
 *
 * V2 adds a settings page + 404 monitor that auto-suggests rules.
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\Redirections;

use SEOForKorean\Helper;

defined( 'ABSPATH' ) || exit;

final class Redirections_Module {

	private const ALLOWED_STATUS = [ 301, 302, 307, 308, 410 ];

	public function boot(): void {
		// Run before WP's main query so we don't waste cycles loading
		// content we're about to redirect away from.
		add_action( 'template_redirect', [ $this, 'maybe_redirect' ], 1 );
	}

	public function maybe_redirect(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) )
			: '/';
		$parsed = wp_parse_url( $request_uri );
		$path   = (string) ( $parsed['path'] ?? '/' );

		foreach ( $this->rules() as $rule ) {
			if ( empty( $rule['enabled'] ) && isset( $rule['enabled'] ) ) {
				continue;
			}
			$status = (int) ( $rule['status'] ?? 301 );
			if ( ! in_array( $status, self::ALLOWED_STATUS, true ) ) {
				$status = 301;
			}

			// 410 Gone — no target, just emit the status and stop.
			if ( $status === 410 && $this->matches( $rule, $path ) ) {
				status_header( 410 );
				nocache_headers();
				exit;
			}

			$target = $this->resolve( $rule, $path );
			if ( $target === null ) {
				continue;
			}

			// Append original query string when no override.
			if ( ! empty( $parsed['query'] ) && ! str_contains( $target, '?' ) ) {
				$target .= '?' . $parsed['query'];
			}

			wp_safe_redirect( $target, $status, 'SEO for Korean' );
			exit;
		}
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function rules(): array {
		$stored = (array) Helper::get_settings( 'redirects', [] );

		/**
		 * Filter the active redirect rule set. Code-defined rules are
		 * appended after option-stored ones.
		 */
		return (array) apply_filters( 'sfk/redirects/rules', $stored );
	}

	/**
	 * @param array<string, mixed> $rule
	 */
	private function matches( array $rule, string $path ): bool {
		return $this->resolve( $rule, $path ) !== null
			|| ( ( $rule['status'] ?? 0 ) === 410
				&& $this->raw_matches( $rule, $path ) );
	}

	/**
	 * @param array<string, mixed> $rule
	 */
	private function raw_matches( array $rule, string $path ): bool {
		$from = (string) ( $rule['from'] ?? '' );
		$type = (string) ( $rule['type'] ?? 'exact' );
		if ( $from === '' ) {
			return false;
		}

		switch ( $type ) {
			case 'exact':
				return $path === $from;
			case 'prefix':
				return str_starts_with( $path, $from );
			case 'regex':
				$pattern = '#' . str_replace( '#', '\\#', $from ) . '#';
				return @preg_match( $pattern, $path ) === 1;
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $rule
	 */
	private function resolve( array $rule, string $path ): ?string {
		$from = (string) ( $rule['from'] ?? '' );
		$to   = (string) ( $rule['to'] ?? '' );
		$type = (string) ( $rule['type'] ?? 'exact' );

		if ( $from === '' || $to === '' ) {
			return null;
		}

		switch ( $type ) {
			case 'exact':
				return $path === $from ? $to : null;

			case 'prefix':
				if ( str_starts_with( $path, $from ) ) {
					return $to . substr( $path, strlen( $from ) );
				}
				return null;

			case 'regex':
				$pattern = '#' . str_replace( '#', '\\#', $from ) . '#';
				if ( @preg_match( $pattern, $path ) !== 1 ) {
					return null;
				}
				$result = @preg_replace( $pattern, $to, $path );
				return is_string( $result ) ? $result : null;
		}

		return null;
	}
}
