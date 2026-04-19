<?php
/**
 * Review schema. Auto-detects review-style posts and emits a Review entity.
 *
 * Detection heuristics — any one trigger:
 *   - Heading or first-line marker: '리뷰:', '평가:', 'Review:'
 *   - Inline rating: '별점: 4.5', '평점 4', '점수: 8/10'
 *   - Star markers: '★★★★☆' or '⭐⭐⭐⭐'
 *
 * Rating extraction (for reviewRating field):
 *   1. Numeric '별점/평점/점수: N' or 'N/M' patterns → use N (normalized to /5)
 *   2. Count of ★/⭐ characters (1-5) → use as rating value
 *   3. None → omit reviewRating, still emit the Review marker
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\Schema\Schemas;

defined( 'ABSPATH' ) || exit;

final class Review_Schema {

	public static function applies(): bool {
		if ( ! is_singular() ) {
			return false;
		}
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}
		return self::detect( $post->post_content );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function build(): array {
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return [];
		}

		$obj = [
			'@type'         => 'Review',
			'@id'           => (string) get_permalink( $post ) . '#review',
			'itemReviewed'  => [
				'@type' => 'Thing',
				'name'  => (string) get_the_title( $post ),
			],
			'author'        => [ '@id' => Person_Schema::author_id_url( (int) $post->post_author ) ],
			'datePublished' => (string) mysql2date( 'c', $post->post_date_gmt, false ),
		];

		$rating = self::extract_rating( $post->post_content );
		if ( $rating !== null ) {
			$obj['reviewRating'] = [
				'@type'       => 'Rating',
				'ratingValue' => $rating,
				'bestRating'  => 5,
				'worstRating' => 1,
			];
		}

		return $obj;
	}

	private static function detect( string $content ): bool {
		if ( preg_match( '/(?:^|\s)(리뷰|평가|Review)\s*[:：]/u', $content ) === 1 ) {
			return true;
		}
		if ( preg_match( '/(?:별점|평점|점수)\s*[:：]?\s*\d/u', $content ) === 1 ) {
			return true;
		}
		// 3 or more stars in a row signals a rating display
		if ( preg_match( '/[★⭐]{3,}/u', $content ) === 1 ) {
			return true;
		}
		return false;
	}

	private static function extract_rating( string $content ): ?float {
		// '별점: 4.5' or '평점 4' or '점수: 8/10'
		if ( preg_match( '/(?:별점|평점|점수)\s*[:：]?\s*(\d+(?:\.\d+)?)\s*(?:\/\s*(\d+(?:\.\d+)?))?/u', $content, $m ) === 1 ) {
			$value = (float) $m[1];
			$max   = isset( $m[2] ) ? (float) $m[2] : 0.0;
			if ( $max > 0 && $max !== 5.0 ) {
				$value = $value / $max * 5.0;
			}
			return self::clamp_rating( $value );
		}

		$stars = preg_match_all( '/[★⭐]/u', $content );
		if ( is_int( $stars ) && $stars >= 1 && $stars <= 5 ) {
			return (float) $stars;
		}

		return null;
	}

	private static function clamp_rating( float $v ): ?float {
		if ( $v <= 0 || $v > 5 ) {
			return null;
		}
		return round( $v, 1 );
	}
}
