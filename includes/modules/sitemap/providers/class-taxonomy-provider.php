<?php
/**
 * Taxonomy sitemap provider. Generic — works for category, post_tag, any CT.
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\Sitemap\Providers;

defined( 'ABSPATH' ) || exit;

final class Taxonomy_Provider {

	public function __construct(
		private string $taxonomy,
		private int $limit = 1000,
	) {}

	/**
	 * @return list<array{loc: string, lastmod: string, changefreq: string, priority: float}>
	 */
	public function urls(): array {
		$terms = get_terms(
			[
				'taxonomy'   => $this->taxonomy,
				'hide_empty' => true,
				'number'     => $this->limit,
			]
		);

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return [];
		}

		$urls = [];
		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}
			$link = get_term_link( $term );
			if ( is_wp_error( $link ) ) {
				continue;
			}

			$urls[] = [
				'loc'        => (string) $link,
				'lastmod'    => $this->term_lastmod( $term ),
				'changefreq' => 'weekly',
				'priority'   => 0.5,
			];
		}

		return $urls;
	}

	public function lastmod(): string {
		// Most-recent post in any term of this taxonomy.
		$query = new \WP_Query(
			[
				'post_type'              => 'any',
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'tax_query'              => [
					[ 'taxonomy' => $this->taxonomy, 'operator' => 'EXISTS' ],
				],
			]
		);
		if ( $query->posts !== [] ) {
			return (string) mysql2date( 'c', $query->posts[0]->post_modified_gmt, false );
		}
		return (string) gmdate( 'c' );
	}

	private function term_lastmod( \WP_Term $term ): string {
		// Latest post in this specific term.
		$q = new \WP_Query(
			[
				'post_type'              => 'any',
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'tax_query'              => [
					[
						'taxonomy' => $this->taxonomy,
						'field'    => 'term_id',
						'terms'    => $term->term_id,
					],
				],
			]
		);
		if ( $q->posts !== [] ) {
			return (string) mysql2date( 'c', $q->posts[0]->post_modified_gmt, false );
		}
		return (string) gmdate( 'c' );
	}
}
