<?php
/**
 * Recipe schema. Auto-detected from cooking-content patterns common in
 * Korean food blogs.
 *
 * Detection (any one fires):
 *   - '재료:', '만드는 법:', '조리 시간:', '레시피', 'Ingredients:', 'Instructions:'
 *
 * Extraction (best-effort — Google rewards filled fields with rich
 * results, so we extract what we can find):
 *   - recipeIngredient   <ul> right after a '재료' heading
 *   - recipeInstructions <ol> right after a '만드는 법' heading,
 *                        each <li> becomes a HowToStep
 *   - cookTime           '조리 시간: 30분' → ISO 8601 duration (PT30M)
 *   - recipeYield        '분량: 4인분'
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\Schema\Schemas;

defined( 'ABSPATH' ) || exit;

final class Recipe_Schema {

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

		$home = home_url( '/' );

		$obj = [
			'@type'         => 'Recipe',
			'@id'           => (string) get_permalink( $post ) . '#recipe',
			'name'          => (string) get_the_title( $post ),
			'author'        => [ '@id' => Person_Schema::author_id_url( (int) $post->post_author ) ],
			'publisher'     => [ '@id' => $home . '#organization' ],
			'datePublished' => (string) mysql2date( 'c', $post->post_date_gmt, false ),
		];

		if ( has_post_thumbnail( $post ) ) {
			$url = get_the_post_thumbnail_url( $post, 'full' );
			if ( $url ) {
				$obj['image'] = (string) $url;
			}
		}

		if ( has_excerpt( $post ) ) {
			$obj['description'] = (string) get_the_excerpt( $post );
		}

		$ingredients = self::extract_ingredients( $post->post_content );
		if ( $ingredients !== [] ) {
			$obj['recipeIngredient'] = $ingredients;
		}

		$instructions = self::extract_instructions( $post->post_content );
		if ( $instructions !== [] ) {
			$obj['recipeInstructions'] = $instructions;
		}

		$cook = self::extract_duration( $post->post_content, '/(?:조리\s?시간|cook\s?time)/iu' );
		if ( $cook !== '' ) {
			$obj['cookTime'] = $cook;
		}

		$prep = self::extract_duration( $post->post_content, '/(?:준비\s?시간|prep\s?time)/iu' );
		if ( $prep !== '' ) {
			$obj['prepTime'] = $prep;
		}

		$yield = self::extract_yield( $post->post_content );
		if ( $yield !== '' ) {
			$obj['recipeYield'] = $yield;
		}

		return $obj;
	}

	private static function detect( string $content ): bool {
		return preg_match(
			'/(?:재료|만드는\s?법|조리\s?시간|레시피|Ingredients|Instructions)\s*[:：]/u',
			$content
		) === 1;
	}

	/**
	 * @return list<string>
	 */
	private static function extract_ingredients( string $html ): array {
		// Heading marker → next <ul>
		if ( preg_match(
			'/(?:재료|Ingredients)[^<]*?<\/h[1-6]>\s*<ul[^>]*>(.*?)<\/ul>/is',
			$html,
			$m
		) === 1 ) {
			return self::list_items_to_strings( (string) $m[1] );
		}
		// Inline marker '재료:' followed by <ul>
		if ( preg_match( '/(?:재료|Ingredients)\s*[:：][^<]*<\/p>\s*<ul[^>]*>(.*?)<\/ul>/is', $html, $m ) === 1 ) {
			return self::list_items_to_strings( (string) $m[1] );
		}
		return [];
	}

	/**
	 * @return list<array{@type: string, text: string}>
	 */
	private static function extract_instructions( string $html ): array {
		if ( preg_match(
			'/(?:만드는\s?법|조리\s?방법|Instructions)[^<]*?<\/h[1-6]>\s*<ol[^>]*>(.*?)<\/ol>/is',
			$html,
			$m
		) === 1 ) {
			return self::list_items_to_steps( (string) $m[1] );
		}
		if ( preg_match( '/(?:만드는\s?법|조리\s?방법|Instructions)\s*[:：][^<]*<\/p>\s*<ol[^>]*>(.*?)<\/ol>/is', $html, $m ) === 1 ) {
			return self::list_items_to_steps( (string) $m[1] );
		}
		return [];
	}

	/**
	 * @return list<string>
	 */
	private static function list_items_to_strings( string $list_html ): array {
		preg_match_all( '/<li[^>]*>(.*?)<\/li>/is', $list_html, $items );
		$out = [];
		foreach ( (array) ( $items[1] ?? [] ) as $item ) {
			$text = trim( wp_strip_all_tags( (string) $item ) );
			$text = (string) preg_replace( '/\s+/u', ' ', $text );
			if ( $text !== '' ) {
				$out[] = $text;
			}
		}
		return $out;
	}

	/**
	 * @return list<array{@type: string, text: string}>
	 */
	private static function list_items_to_steps( string $list_html ): array {
		$out = [];
		foreach ( self::list_items_to_strings( $list_html ) as $text ) {
			$out[] = [ '@type' => 'HowToStep', 'text' => $text ];
		}
		return $out;
	}

	private static function extract_duration( string $content, string $label_pattern ): string {
		// '조리 시간: 30분' or '조리시간 1시간'
		$pattern = '/' . substr( $label_pattern, 1, -3 ) . '\s*[:：]?\s*(\d+)\s*(분|시간|min|hour)/iu';
		if ( preg_match( $pattern, $content, $m ) !== 1 ) {
			return '';
		}
		$value = (int) $m[1];
		$unit  = (string) $m[2];
		if ( preg_match( '/시간|hour/iu', $unit ) === 1 ) {
			return "PT{$value}H";
		}
		return "PT{$value}M";
	}

	private static function extract_yield( string $content ): string {
		if ( preg_match( '/(?:분량|servings|yield)\s*[:：]?\s*([^\n\r<]+)/iu', $content, $m ) === 1 ) {
			return trim( wp_strip_all_tags( (string) $m[1] ) );
		}
		return '';
	}
}
