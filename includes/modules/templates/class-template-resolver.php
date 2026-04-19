<?php
/**
 * Template_Resolver — substitutes %vars% in title/description templates.
 *
 * Pure logic. Same surface as RankMath/Yoast variables — users coming from
 * those plugins don't need to relearn syntax.
 *
 * Supported variables:
 *   %title%          post title
 *   %sitename%       blog name
 *   %sitedesc%       blog tagline
 *   %separator%      separator char (default '|', configurable)
 *   %excerpt%        post excerpt or trimmed content
 *   %category%       primary category name
 *   %tag%            first tag name
 *   %date%           post published date (site format)
 *   %modified%       post modified date
 *   %author%         author display name
 *   %focuskw%        focus keyword from sidebar input
 *   %searchphrase%   for search archives
 *
 * Unknown variables pass through unchanged so templates with typos don't
 * silently disappear from output (you'll see %typo% rendered).
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\Templates;

use SEOForKorean\Helper;

defined( 'ABSPATH' ) || exit;

final class Template_Resolver {

	public function resolve( string $template, ?\WP_Post $post = null ): string {
		if ( $template === '' ) {
			return '';
		}

		$vars = $this->variables( $post );

		$out = (string) preg_replace_callback(
			'/%([a-z_]+)%/i',
			static function ( array $m ) use ( $vars ) {
				$key = strtolower( $m[1] );
				return array_key_exists( $key, $vars ) ? $vars[ $key ] : $m[0];
			},
			$template
		);

		// Collapse the double-spaces that show up when a variable resolves to ''.
		return trim( (string) preg_replace( '/\s+/', ' ', $out ) );
	}

	/**
	 * @return array<string, string>
	 */
	private function variables( ?\WP_Post $post ): array {
		$vars = [
			'sitename'     => (string) get_bloginfo( 'name' ),
			'sitedesc'     => (string) get_bloginfo( 'description' ),
			'separator'    => (string) Helper::get_settings( 'templates.separator', '|' ),
			'searchphrase' => function_exists( 'is_search' ) && is_search() ? (string) get_search_query() : '',
			'title'        => '',
			'excerpt'      => '',
			'category'     => '',
			'tag'          => '',
			'date'         => '',
			'modified'     => '',
			'author'       => '',
			'focuskw'      => '',
		];

		if ( $post instanceof \WP_Post ) {
			$vars['title']    = (string) get_the_title( $post );
			$vars['excerpt']  = has_excerpt( $post )
				? (string) get_the_excerpt( $post )
				: (string) wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '…' );
			$vars['date']     = (string) get_the_date( '', $post );
			$vars['modified'] = (string) get_the_modified_date( '', $post );

			$author = get_userdata( (int) $post->post_author );
			if ( $author ) {
				$vars['author'] = (string) ( $author->display_name ?: $author->user_login );
			}

			$cats = get_the_category( $post->ID );
			if ( is_array( $cats ) && $cats !== [] && $cats[0] instanceof \WP_Term ) {
				$vars['category'] = $cats[0]->name;
			}

			$tags = get_the_tags( $post->ID );
			if ( is_array( $tags ) && $tags !== [] && $tags[0] instanceof \WP_Term ) {
				$vars['tag'] = $tags[0]->name;
			}

			$vars['focuskw'] = (string) get_post_meta( $post->ID, 'sfk_focus_keyword', true );
		}

		return $vars;
	}
}
