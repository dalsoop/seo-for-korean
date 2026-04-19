<?php
/**
 * Event schema. Auto-detected from event-style markers in content.
 *
 * Detection (any fires): '일시:', '장소:', '행사:', '이벤트', 'Date:', 'Venue:'.
 *
 * Extraction:
 *   - startDate   '일시: 2026-05-15 14:00' → ISO 8601
 *   - location    '장소: 서울 강남구 ...' → Place.name
 *   - description from post excerpt
 *   - image       featured image
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\Schema\Schemas;

defined( 'ABSPATH' ) || exit;

final class Event_Schema {

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
			'@type' => 'Event',
			'@id'   => (string) get_permalink( $post ) . '#event',
			'name'  => (string) get_the_title( $post ),
		];

		$date = self::extract_date( $post->post_content );
		if ( $date !== '' ) {
			$obj['startDate'] = $date;
		}

		$location = self::extract_location( $post->post_content );
		if ( $location !== '' ) {
			$obj['location'] = [
				'@type' => 'Place',
				'name'  => $location,
			];
		}

		if ( has_excerpt( $post ) ) {
			$obj['description'] = (string) get_the_excerpt( $post );
		}

		if ( has_post_thumbnail( $post ) ) {
			$url = get_the_post_thumbnail_url( $post, 'full' );
			if ( $url ) {
				$obj['image'] = (string) $url;
			}
		}

		return $obj;
	}

	private static function detect( string $content ): bool {
		return preg_match(
			'/(?:일시|장소|행사|이벤트|Date|Venue|Event)\s*[:：]/u',
			$content
		) === 1;
	}

	private static function extract_date( string $content ): string {
		// '일시: 2026-05-15' or '날짜: 2026.05.15 14:00'
		if ( preg_match(
			'/(?:일시|날짜|date)\s*[:：]?\s*(\d{4}[-.\/]\d{1,2}[-.\/]\d{1,2})(?:\s+(\d{1,2}:\d{2}))?/iu',
			$content,
			$m
		) === 1 ) {
			$date = (string) preg_replace( '/[.\/]/', '-', (string) $m[1] );
			if ( ! empty( $m[2] ) ) {
				return $date . 'T' . $m[2];
			}
			return $date;
		}
		return '';
	}

	private static function extract_location( string $content ): string {
		if ( preg_match( '/(?:장소|venue|location)\s*[:：]?\s*([^\n\r<]+)/iu', $content, $m ) === 1 ) {
			$loc = trim( wp_strip_all_tags( (string) $m[1] ) );
			$loc = (string) preg_replace( '/\s+/u', ' ', $loc );
			return $loc;
		}
		return '';
	}
}
