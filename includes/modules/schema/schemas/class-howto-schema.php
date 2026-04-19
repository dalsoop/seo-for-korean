<?php
/**
 * HowTo schema. Auto-detected from the first ordered list with 3+ items.
 *
 * Conservative detector — only emits when the post genuinely looks like
 * step-by-step instructions, not when it happens to have a numbered list
 * for some other reason. The 3-item floor catches recipes, tutorials,
 * setup guides while skipping casual lists.
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\Schema\Schemas;

defined( 'ABSPATH' ) || exit;

final class HowTo_Schema {

	private const MIN_STEPS = 3;

	public static function applies(): bool {
		if ( ! is_singular() ) {
			return false;
		}
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}
		return self::extract_steps( $post->post_content ) !== [];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function build(): array {
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return [];
		}

		$steps = self::extract_steps( $post->post_content );
		if ( count( $steps ) < self::MIN_STEPS ) {
			return [];
		}

		$entities = [];
		foreach ( $steps as $i => $text ) {
			$entities[] = [
				'@type'    => 'HowToStep',
				'position' => $i + 1,
				'name'     => mb_substr( $text, 0, 110 ),
				'text'     => $text,
			];
		}

		$obj = [
			'@type' => 'HowTo',
			'@id'   => (string) get_permalink( $post ) . '#howto',
			'name'  => (string) get_the_title( $post ),
			'step'  => $entities,
		];

		// Optional total time estimate based on step count (rough heuristic).
		// Skipped when we have no signal — no fake numbers.

		return $obj;
	}

	/**
	 * @return list<string>
	 */
	private static function extract_steps( string $html ): array {
		// First <ol> only — multiple ordered lists are usually unrelated.
		if ( preg_match( '/<ol\b[^>]*>(.*?)<\/ol>/is', $html, $m ) !== 1 ) {
			return [];
		}
		preg_match_all( '/<li\b[^>]*>(.*?)<\/li>/is', (string) $m[1], $items );
		$raw = (array) ( $items[1] ?? [] );

		$steps = [];
		foreach ( $raw as $item ) {
			$text = trim( wp_strip_all_tags( (string) $item ) );
			$text = (string) preg_replace( '/\s+/u', ' ', $text );
			if ( $text !== '' ) {
				$steps[] = $text;
			}
		}

		if ( count( $steps ) < self::MIN_STEPS ) {
			return [];
		}
		return $steps;
	}
}
