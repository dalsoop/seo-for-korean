<?php
/**
 * BreadcrumbList schema. Walks up the post hierarchy and emits the path.
 *
 * Pattern:
 *   Home → Category → Post              (singular post)
 *   Home → Page                          (singular page, no parents)
 *   Home → Parent Page → Page            (singular page with hierarchy)
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\Schema\Schemas;

defined( 'ABSPATH' ) || exit;

final class Breadcrumb_Schema {

	public static function applies(): bool {
		return is_singular();
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function build(): array {
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return [];
		}

		$items = [];
		$pos   = 1;

		$items[] = self::item( $pos++, __( '홈', 'seo-for-korean' ), home_url( '/' ) );

		// For posts: append primary category if available.
		if ( $post->post_type === 'post' ) {
			$cats = get_the_category( $post->ID );
			if ( is_array( $cats ) && $cats !== [] && $cats[0] instanceof \WP_Term ) {
				$items[] = self::item( $pos++, $cats[0]->name, (string) get_category_link( $cats[0] ) );
			}
		}

		// For pages: walk parent ancestors top-down.
		if ( $post->post_type === 'page' && (int) $post->post_parent > 0 ) {
			$ancestors = array_reverse( (array) get_post_ancestors( $post ) );
			foreach ( $ancestors as $ancestor_id ) {
				$ancestor = get_post( $ancestor_id );
				if ( $ancestor instanceof \WP_Post ) {
					$items[] = self::item( $pos++, get_the_title( $ancestor ), (string) get_permalink( $ancestor ) );
				}
			}
		}

		// Current item — by Google's spec, the last item should NOT have a URL.
		$items[] = [
			'@type'    => 'ListItem',
			'position' => $pos,
			'name'     => get_the_title( $post ),
		];

		return [
			'@type'           => 'BreadcrumbList',
			'@id'             => get_permalink( $post ) . '#breadcrumb',
			'itemListElement' => $items,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function item( int $position, string $name, string $url ): array {
		return [
			'@type'    => 'ListItem',
			'position' => $position,
			'name'     => $name,
			'item'     => $url,
		];
	}
}
