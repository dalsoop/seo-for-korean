<?php
/**
 * Post-type sitemap provider. Generic — works for post, page, any CPT.
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\Sitemap\Providers;

defined( 'ABSPATH' ) || exit;

final class Post_Type_Provider {

	public function __construct(
		private string $post_type,
		private int $limit = 1000,
	) {}

	/**
	 * @return list<array{loc: string, lastmod: string, changefreq: string, priority: float, image?: string}>
	 */
	public function urls(): array {
		$query = new \WP_Query(
			[
				'post_type'              => $this->post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => $this->limit,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'has_password'           => false,
			]
		);

		$urls = [];
		foreach ( $query->posts as $post ) {
			$entry = [
				'loc'        => (string) get_permalink( $post ),
				'lastmod'    => (string) mysql2date( 'c', $post->post_modified_gmt, false ),
				'changefreq' => $this->post_type === 'post' ? 'weekly' : 'monthly',
				'priority'   => $this->post_type === 'page' ? 0.6 : 0.8,
			];

			if ( has_post_thumbnail( $post ) ) {
				$img = get_the_post_thumbnail_url( $post, 'full' );
				if ( $img ) {
					$entry['image'] = (string) $img;
				}
			}

			$urls[] = $entry;
		}

		return $urls;
	}

	public function lastmod(): string {
		$query = new \WP_Query(
			[
				'post_type'              => $this->post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		if ( $query->posts !== [] ) {
			$post = $query->posts[0];
			return (string) mysql2date( 'c', $post->post_modified_gmt, false );
		}
		return (string) gmdate( 'c' );
	}
}
