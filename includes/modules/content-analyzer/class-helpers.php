<?php
/**
 * Stateless utilities shared across check classes.
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\ContentAnalyzer;

defined( 'ABSPATH' ) || exit;

final class Helpers {

	public static function strip_html( string $html ): string {
		$text = function_exists( 'wp_strip_all_tags' )
			? wp_strip_all_tags( $html )
			: trim( strip_tags( $html ) );
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * @return array{internal: int, outbound: int}
	 */
	public static function count_links( string $html ): array {
		$internal = 0;
		$outbound = 0;
		if ( preg_match_all( '/<a\s+[^>]*?href\s*=\s*"([^"]+)"/is', $html, $m ) ) {
			foreach ( $m[1] as $href ) {
				$href = trim( $href );
				if ( str_starts_with( $href, 'http://' )
					|| str_starts_with( $href, 'https://' )
					|| str_starts_with( $href, '//' )
				) {
					++$outbound;
				} elseif ( $href !== ''
					&& ! str_starts_with( $href, '#' )
					&& ! str_starts_with( $href, 'javascript:' )
					&& ! str_starts_with( $href, 'mailto:' )
					&& ! str_starts_with( $href, 'tel:' )
				) {
					++$internal;
				}
			}
		}
		return [ 'internal' => $internal, 'outbound' => $outbound ];
	}

	/**
	 * @return array{id: string, label: string, status: string, message: string, weight: int}
	 */
	public static function result( string $id, string $label, string $status, string $message, int $weight ): array {
		return [
			'id'      => $id,
			'label'   => $label,
			'status'  => $status,
			'message' => $message,
			'weight'  => $weight,
		];
	}
}
