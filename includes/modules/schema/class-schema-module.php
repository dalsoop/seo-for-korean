<?php
/**
 * Schema module — emits Schema.org structured data as JSON-LD on every page.
 *
 * Strategy mirrors RankMath/Yoast: collect every applicable schema type into a
 * single @graph wrapper output once in <head>. Each schema "provider" is a
 * static class under schemas/ that decides whether it applies to the current
 * request and builds its own object.
 *
 * Why a single @graph instead of one <script> per type:
 *   - Search engines parse it as one structured document
 *   - @id cross-references work (Article -> Person, Article -> Organization)
 *   - One round of escaping, one chance for theme/plugin output buffering bugs
 *
 * Korean angle: Naver also reads schema.org since ~2020 — Article/NewsArticle/
 * BreadcrumbList all surface in their search results. This isn't Google-only.
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\Schema;

use SEOForKorean\Modules\Schema\Schemas\Article_Schema;
use SEOForKorean\Modules\Schema\Schemas\Breadcrumb_Schema;
use SEOForKorean\Modules\Schema\Schemas\Event_Schema;
use SEOForKorean\Modules\Schema\Schemas\FAQ_Schema;
use SEOForKorean\Modules\Schema\Schemas\HowTo_Schema;
use SEOForKorean\Modules\Schema\Schemas\Organization_Schema;
use SEOForKorean\Modules\Schema\Schemas\Person_Schema;
use SEOForKorean\Modules\Schema\Schemas\Recipe_Schema;
use SEOForKorean\Modules\Schema\Schemas\Review_Schema;
use SEOForKorean\Modules\Schema\Schemas\Video_Schema;
use SEOForKorean\Modules\Schema\Schemas\Website_Schema;

defined( 'ABSPATH' ) || exit;

final class Schema_Module {

	public function boot(): void {
		add_action( 'wp_head', [ $this, 'emit' ], 6 );
	}

	public function emit(): void {
		/**
		 * Schema providers. Each class needs static `applies(): bool` and
		 * `build(): array` methods. Order matters only for human readability —
		 * search engines consume the whole graph as a set.
		 *
		 * @param array<int, class-string> $providers
		 */
		$providers = (array) apply_filters(
			'sfk/schema/providers',
			[
				Website_Schema::class,
				Organization_Schema::class,
				Person_Schema::class,
				Article_Schema::class,
				Breadcrumb_Schema::class,
				FAQ_Schema::class,
				HowTo_Schema::class,
				Review_Schema::class,
				Recipe_Schema::class,
				Event_Schema::class,
				Video_Schema::class,
			]
		);

		$graph = [];
		foreach ( $providers as $provider ) {
			if ( ! is_string( $provider ) || ! class_exists( $provider ) ) {
				continue;
			}
			if ( ! $provider::applies() ) {
				continue;
			}
			$obj = $provider::build();
			if ( ! is_array( $obj ) || $obj === [] ) {
				continue;
			}
			$graph[] = $obj;
		}

		if ( $graph === [] ) {
			return;
		}

		$payload = [
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		];

		// HTML entities make sense inside HTML attributes (og:title="…"),
		// but inside a JSON-LD payload they're noise — search engines see
		// '&#8220;R&#038;D&#8221;' instead of '"R&D"'. WP's title/excerpt
		// helpers return HTML-encoded text for safe page output, so we
		// decode at the JSON boundary.
		$payload = self::decode_html_entities( $payload );

		printf(
			"<script type=\"application/ld+json\">%s</script>\n",
			wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		);
	}

	/**
	 * Recursively decode HTML entities in every string value of a payload.
	 * Numeric values, booleans, arrays / objects: structure preserved.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	private static function decode_html_entities( $value ) {
		if ( is_string( $value ) ) {
			return html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}
		if ( is_array( $value ) ) {
			return array_map( [ self::class, 'decode_html_entities' ], $value );
		}
		return $value;
	}
}
