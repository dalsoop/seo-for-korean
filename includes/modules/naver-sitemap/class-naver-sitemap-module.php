<?php
/**
 * Naver Sitemap module — exposes /sitemap-naver.xml in sitemap.org format
 * with image:image extension. Submit the URL inside Naver Search Advisor.
 *
 * Why we ship our own (vs. relying on WP core's /wp-sitemap.xml):
 *   - Includes <image:image> for every featured image (core sitemap omits it).
 *   - Stable, Naver-recognizable URL convention.
 *   - Filter hooks scoped to Naver, so customizing one search engine's feed
 *     doesn't affect Google.
 *
 * Filters:
 *   sfk/naver_sitemap/post_types  (array, default ['post', 'page'])
 *   sfk/naver_sitemap/max_urls    (int,   default 1000)
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\NaverSitemap;

defined( 'ABSPATH' ) || exit;

final class Naver_Sitemap_Module {

	private const QUERY_VAR = 'sfk_sitemap';
	private const REWRITE   = '^sitemap-naver\.xml$';

	public function boot(): void {
		add_action( 'init', [ $this, 'add_rewrite' ] );
		add_filter( 'query_vars', [ $this, 'add_query_var' ] );
		add_action( 'template_redirect', [ $this, 'maybe_render' ] );
	}

	public function add_rewrite(): void {
		add_rewrite_rule( self::REWRITE, 'index.php?' . self::QUERY_VAR . '=naver', 'top' );
	}

	/**
	 * @param array<int, string> $vars
	 * @return array<int, string>
	 */
	public function add_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public function maybe_render(): void {
		if ( get_query_var( self::QUERY_VAR ) !== 'naver' ) {
			return;
		}

		nocache_headers();
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow', true );

		echo $this->build_xml(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	private function build_xml(): string {
		$post_types = (array) apply_filters( 'sfk/naver_sitemap/post_types', [ 'post', 'page' ] );
		$max_urls   = (int) apply_filters( 'sfk/naver_sitemap/max_urls', 1000 );

		$query = new \WP_Query(
			[
				'post_type'              => $post_types,
				'post_status'            => 'publish',
				'posts_per_page'         => $max_urls,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'has_password'           => false,
			]
		);

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
		$xml .= ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

		// Site root entry.
		$xml .= "\t<url>\n";
		$xml .= "\t\t<loc>" . esc_url( home_url( '/' ) ) . "</loc>\n";
		$xml .= "\t\t<changefreq>daily</changefreq>\n";
		$xml .= "\t\t<priority>1.0</priority>\n";
		$xml .= "\t</url>\n";

		foreach ( $query->posts as $post ) {
			$xml .= "\t<url>\n";
			$xml .= "\t\t<loc>" . esc_url( get_permalink( $post ) ) . "</loc>\n";
			$xml .= "\t\t<lastmod>" . esc_html( mysql2date( 'c', $post->post_modified_gmt, false ) ) . "</lastmod>\n";
			$xml .= "\t\t<changefreq>weekly</changefreq>\n";
			$xml .= "\t\t<priority>0.8</priority>\n";

			$image_url = has_post_thumbnail( $post ) ? get_the_post_thumbnail_url( $post, 'full' ) : '';
			if ( $image_url ) {
				$xml .= "\t\t<image:image>\n";
				$xml .= "\t\t\t<image:loc>" . esc_url( $image_url ) . "</image:loc>\n";
				$xml .= "\t\t</image:image>\n";
			}

			$xml .= "\t</url>\n";
		}

		$xml .= '</urlset>';
		return $xml;
	}
}
