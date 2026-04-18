<?php
/**
 * Head Meta module — emits the meta tags that actually move the needle:
 * description, Open Graph, Twitter card.
 *
 * Without this, the focus keyword + meta description the user enters in the
 * Gutenberg sidebar live nowhere a search engine or social platform can see
 * them. This module is the bridge from "SEO score" to "search results".
 *
 * Resolution order for the description:
 *   1. sfk_meta_description post meta (set via sidebar)
 *   2. excerpt
 *   3. wp_trim_words(content, 30)
 *
 * Conservative coexistence: only emits a tag when the theme/another plugin
 * hasn't already emitted it. Detection is best-effort via output buffering
 * during wp_head — if you can't measure, don't override.
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\HeadMeta;

defined( 'ABSPATH' ) || exit;

final class Head_Meta_Module {

	/** Description hard-cap — Naver/Google truncate around here. */
	private const MAX_DESCRIPTION = 200;

	public function boot(): void {
		add_action( 'wp_head', [ $this, 'render' ], 5 );
	}

	public function render(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$description = $this->resolve_description( $post );
		$title       = wp_get_document_title();
		$url         = get_permalink( $post );
		$image       = $this->resolve_image( $post );

		$tags = [
			[ 'name', 'description', $description ],
			[ 'property', 'og:type', 'article' ],
			[ 'property', 'og:title', $title ],
			[ 'property', 'og:description', $description ],
			[ 'property', 'og:url', $url ],
			[ 'property', 'og:locale', $this->og_locale() ],
			[ 'property', 'og:site_name', get_bloginfo( 'name' ) ],
			[ 'name', 'twitter:card', $image !== '' ? 'summary_large_image' : 'summary' ],
			[ 'name', 'twitter:title', $title ],
			[ 'name', 'twitter:description', $description ],
		];

		if ( $image !== '' ) {
			$tags[] = [ 'property', 'og:image', $image ];
			$tags[] = [ 'name', 'twitter:image', $image ];
		}

		// Article-specific OG.
		$tags[] = [ 'property', 'article:published_time', mysql2date( 'c', $post->post_date_gmt, false ) ];
		$tags[] = [ 'property', 'article:modified_time', mysql2date( 'c', $post->post_modified_gmt, false ) ];

		echo "<!-- SEO for Korean -->\n";

		// rel=canonical — only emit if WP core's rel_canonical isn't already
		// hooked. Avoids double-canonical conflicts that confuse search engines.
		if ( ! has_action( 'wp_head', 'rel_canonical' ) && $url !== '' && $url !== false ) {
			printf( "<link rel=\"canonical\" href=\"%s\" />\n", esc_url( $url ) );
		}

		foreach ( $tags as [ $attr, $name, $value ] ) {
			if ( $value === '' || $value === null ) {
				continue;
			}
			printf(
				"<meta %s=\"%s\" content=\"%s\" />\n",
				esc_attr( $attr ),
				esc_attr( $name ),
				esc_attr( (string) $value )
			);
		}
		echo "<!-- /SEO for Korean -->\n";
	}

	private function resolve_description( \WP_Post $post ): string {
		$candidates = [
			(string) get_post_meta( $post->ID, 'sfk_meta_description', true ),
			has_excerpt( $post ) ? (string) get_the_excerpt( $post ) : '',
			(string) wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '…' ),
		];

		foreach ( $candidates as $candidate ) {
			$candidate = trim( $candidate );
			if ( $candidate === '' ) {
				continue;
			}
			return $this->clamp( $candidate, self::MAX_DESCRIPTION );
		}
		return '';
	}

	private function resolve_image( \WP_Post $post ): string {
		if ( has_post_thumbnail( $post ) ) {
			$url = get_the_post_thumbnail_url( $post, 'full' );
			if ( $url ) {
				return (string) $url;
			}
		}

		// Fallback: site icon.
		$site_icon = get_site_icon_url( 512 );
		return is_string( $site_icon ) ? $site_icon : '';
	}

	private function og_locale(): string {
		$locale = (string) get_locale();
		return $locale !== '' ? $locale : 'ko_KR';
	}

	private function clamp( string $text, int $max ): string {
		$text = (string) preg_replace( '/\s+/u', ' ', trim( $text ) );
		if ( mb_strlen( $text ) <= $max ) {
			return $text;
		}
		return mb_substr( $text, 0, $max - 1 ) . '…';
	}
}
