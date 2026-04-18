<?php
/**
 * Morphology_Client — talks to the SEO for Korean gateway.
 *
 * Optional dependency. When a gateway URL is configured AND reachable, the
 * Content_Analyzer routes Korean keyword matching through it (currently a
 * Rust regex implementation; will become lindera + ko-dic in V2). When the
 * gateway is unset or unreachable, the analyzer falls back to its in-PHP
 * regex — same algorithm, slightly slower, equally correct for V1.
 *
 * Health is cached in a 60s transient so a down gateway doesn't pause the
 * editor on every keystroke.
 *
 * Configure via:
 *   sfk_settings['morphology']['gateway_url']  string  e.g. http://10.0.x.x:8787
 *   sfk_settings['morphology']['timeout']      int     seconds, default 2
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Morphology;

use SEOForKorean\Helper;

defined( 'ABSPATH' ) || exit;

class Morphology_Client {

	private const HEALTH_TRANSIENT = 'sfk_morphology_health';
	private const HEALTH_TTL       = 60;

	public function gateway_url(): string {
		return rtrim( (string) Helper::get_settings( 'morphology.gateway_url', '' ), '/' );
	}

	public function timeout(): int {
		$t = (int) Helper::get_settings( 'morphology.timeout', 2 );
		return $t > 0 ? $t : 2;
	}

	public function is_available(): bool {
		if ( $this->gateway_url() === '' ) {
			return false;
		}

		$cached = get_transient( self::HEALTH_TRANSIENT );
		if ( $cached !== false ) {
			return $cached === '1';
		}

		$response = wp_remote_get(
			$this->gateway_url() . '/health',
			[ 'timeout' => $this->timeout() ]
		);
		$ok = ! is_wp_error( $response )
			&& wp_remote_retrieve_response_code( $response ) === 200;

		set_transient( self::HEALTH_TRANSIENT, $ok ? '1' : '0', self::HEALTH_TTL );
		return $ok;
	}

	/**
	 * Count occurrences of $keyword inside $text using the gateway's
	 * particle-aware matcher. Returns null when the gateway is unconfigured
	 * or the request fails — caller should fall back to local logic.
	 *
	 * @return array{count: int, matches: list<string>}|null
	 */
	public function keyword_contains( string $text, string $keyword ): ?array {
		if ( $this->gateway_url() === '' ) {
			return null;
		}
		if ( trim( $keyword ) === '' ) {
			return [ 'count' => 0, 'matches' => [] ];
		}

		$response = wp_remote_post(
			$this->gateway_url() . '/keyword/contains',
			[
				'timeout' => $this->timeout(),
				'headers' => [ 'content-type' => 'application/json' ],
				'body'    => wp_json_encode( [ 'text' => $text, 'keyword' => $keyword ] ),
			]
		);

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			// Mark gateway unhealthy so subsequent calls in the next minute
			// skip the network roundtrip and fall back immediately.
			set_transient( self::HEALTH_TRANSIENT, '0', self::HEALTH_TTL );
			return null;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || ! isset( $body['count'], $body['matches'] ) ) {
			return null;
		}

		return [
			'count'   => (int) $body['count'],
			'matches' => array_values( array_map( 'strval', (array) $body['matches'] ) ),
		];
	}

	/**
	 * Run a full SEO analysis on the gateway. Returns the gateway's response
	 * verbatim ({score, grade, checks}) — the plugin's REST handler passes
	 * it straight back to the editor.
	 *
	 * Returns null on any failure so the caller can fall back to its
	 * in-PHP Content_Analyzer. Same health-cache writeback as
	 * keyword_contains() — failures pin the gateway as down for 60s.
	 *
	 * @param array<string, string> $input title, content, slug, focus_keyword, meta_description
	 * @return array{score: int, grade: string, checks: array<int, array<string, mixed>>, engine?: string}|null
	 */
	public function analyze_post( array $input ): ?array {
		if ( $this->gateway_url() === '' ) {
			return null;
		}

		$response = wp_remote_post(
			$this->gateway_url() . '/analyze',
			[
				'timeout' => $this->timeout(),
				'headers' => [ 'content-type' => 'application/json' ],
				'body'    => wp_json_encode( $input ),
			]
		);

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			set_transient( self::HEALTH_TRANSIENT, '0', self::HEALTH_TTL );
			return null;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || ! isset( $body['score'], $body['grade'], $body['checks'] ) ) {
			return null;
		}

		return $body;
	}
}
