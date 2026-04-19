<?php
/**
 * Sitemap module — comprehensive XML sitemap suite.
 *
 * Replaces WP core's /wp-sitemap.xml with our own at /sitemap.xml plus a
 * handful of sub-sitemaps:
 *
 *   /sitemap.xml             — index, lists every sub-sitemap
 *   /sitemap-posts.xml       — published posts (with <image:image>)
 *   /sitemap-pages.xml       — published pages
 *   /sitemap-categories.xml  — category archives
 *   /sitemap-tags.xml        — tag archives
 *
 * Submit /sitemap.xml to both Google Search Console and Naver
 * 서치어드바이저 — both engines parse the index and crawl every linked file.
 *
 * Future: pagination at >1000 URLs per file, news sitemap variant,
 * video sitemap, custom post type / custom taxonomy auto-discovery.
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\Sitemap;

use SEOForKorean\Modules\Sitemap\Providers\Post_Type_Provider;
use SEOForKorean\Modules\Sitemap\Providers\Taxonomy_Provider;

defined( 'ABSPATH' ) || exit;

final class Sitemap_Module {

	private const QUERY_VAR = 'sfk_sitemap';
	private const REWRITE   = '^sitemap(?:-([a-z0-9_-]+))?\.xml$';
	private const MAX_URLS  = 1000;

	public function boot(): void {
		// Replace WP core's /wp-sitemap.xml so Google sees a single source of truth.
		add_filter( 'wp_sitemaps_enabled', '__return_false' );

		add_action( 'init', [ $this, 'add_rewrite' ] );
		add_filter( 'query_vars', [ $this, 'add_query_var' ] );
		// Run before canonical redirect (default priority 10) so we don't
		// get bumped to /sitemap.xml/ for a URL that intentionally has no
		// trailing slash.
		add_action( 'template_redirect', [ $this, 'maybe_render' ], 1 );
		add_filter( 'redirect_canonical', [ $this, 'short_circuit_canonical' ], 10, 2 );
	}

	/**
	 * Tell WP not to canonical-redirect any URL that's about to render
	 * one of our sitemaps. Without this, /sitemap.xml gets bumped to
	 * /sitemap.xml/ (search engines grumble).
	 *
	 * @param string|false $redirect_url The redirect WP wanted to do.
	 * @return string|false The same value, or false to cancel.
	 */
	public function short_circuit_canonical( $redirect_url, string $requested_url ) {
		$slug = get_query_var( self::QUERY_VAR, null );
		if ( $slug !== null && $slug !== false ) {
			return false;
		}
		return $redirect_url;
	}

	public function add_rewrite(): void {
		add_rewrite_rule( self::REWRITE, 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
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
		// `get_query_var` returns '' when the rewrite captured no slug
		// (i.e. the bare /sitemap.xml index request).
		$slug = get_query_var( self::QUERY_VAR, null );
		if ( $slug === null || $slug === false ) {
			return;
		}

		nocache_headers();
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow', true );

		$slug = (string) $slug;
		if ( $slug === '' ) {
			echo $this->render_index(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			exit;
		}

		$providers = $this->providers();
		if ( ! isset( $providers[ $slug ] ) ) {
			status_header( 404 );
			exit;
		}

		echo $this->render_urlset( $providers[ $slug ]['urls']() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * @return array<string, array{lastmod: callable, urls: callable}>
	 */
	private function providers(): array {
		$post_provider = new Post_Type_Provider( 'post', self::MAX_URLS );
		$page_provider = new Post_Type_Provider( 'page', self::MAX_URLS );
		$cat_provider  = new Taxonomy_Provider( 'category', self::MAX_URLS );
		$tag_provider  = new Taxonomy_Provider( 'post_tag', self::MAX_URLS );

		$providers = [
			'posts'      => [
				'lastmod' => [ $post_provider, 'lastmod' ],
				'urls'    => [ $post_provider, 'urls' ],
			],
			'pages'      => [
				'lastmod' => [ $page_provider, 'lastmod' ],
				'urls'    => [ $page_provider, 'urls' ],
			],
			'categories' => [
				'lastmod' => [ $cat_provider, 'lastmod' ],
				'urls'    => [ $cat_provider, 'urls' ],
			],
			'tags'       => [
				'lastmod' => [ $tag_provider, 'lastmod' ],
				'urls'    => [ $tag_provider, 'urls' ],
			],
		];

		/**
		 * Filter sub-sitemap providers. Each entry is keyed by the URL slug
		 * (e.g., 'posts' → /sitemap-posts.xml) and contains 'lastmod' and
		 * 'urls' callables.
		 */
		return (array) apply_filters( 'sfk/sitemap/providers', $providers );
	}

	private function render_index(): string {
		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( $this->providers() as $slug => $provider ) {
			$xml .= "\t<sitemap>\n";
			$xml .= "\t\t<loc>" . esc_url( home_url( "/sitemap-{$slug}.xml" ) ) . "</loc>\n";
			$xml .= "\t\t<lastmod>" . esc_html( (string) call_user_func( $provider['lastmod'] ) ) . "</lastmod>\n";
			$xml .= "\t</sitemap>\n";
		}

		$xml .= '</sitemapindex>';
		return $xml;
	}

	/**
	 * @param list<array{loc: string, lastmod: string, changefreq: string, priority: float, image?: string}> $urls
	 */
	private function render_urlset( array $urls ): string {
		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
		$xml .= ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

		foreach ( $urls as $url ) {
			$xml .= "\t<url>\n";
			$xml .= "\t\t<loc>" . esc_url( $url['loc'] ) . "</loc>\n";
			$xml .= "\t\t<lastmod>" . esc_html( $url['lastmod'] ) . "</lastmod>\n";
			$xml .= "\t\t<changefreq>" . esc_html( $url['changefreq'] ) . "</changefreq>\n";
			$xml .= "\t\t<priority>" . esc_html( (string) $url['priority'] ) . "</priority>\n";

			if ( ! empty( $url['image'] ) ) {
				$xml .= "\t\t<image:image>\n";
				$xml .= "\t\t\t<image:loc>" . esc_url( (string) $url['image'] ) . "</image:loc>\n";
				$xml .= "\t\t</image:image>\n";
			}

			$xml .= "\t</url>\n";
		}

		$xml .= '</urlset>';
		return $xml;
	}
}
