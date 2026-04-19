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
use SEOForKorean\Modules\Schema\Schemas\Organization_Schema;
use SEOForKorean\Modules\Schema\Schemas\Person_Schema;
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

		printf(
			"<script type=\"application/ld+json\">%s</script>\n",
			wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		);
	}
}
