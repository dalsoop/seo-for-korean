<?php
/**
 * Article schema (BlogPosting / NewsArticle / Article auto-pick).
 *
 * Type selection:
 *   - NewsArticle if any category contains '뉴스' or 'news'
 *   - Article for pages
 *   - BlogPosting for everything else
 *
 * Override via post meta `sfk_schema_type`.
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\Schema\Schemas;

defined( 'ABSPATH' ) || exit;

final class Article_Schema {

	public static function applies(): bool {
		return is_singular() && get_queried_object() instanceof \WP_Post;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function build(): array {
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return [];
		}

		$home    = home_url( '/' );
		$url     = (string) get_permalink( $post );
		$type    = self::resolve_type( $post );
		$obj     = [
			'@type'            => $type,
			'@id'              => $url . '#article',
			'mainEntityOfPage' => [ '@id' => $url . '#webpage' ],
			'headline'         => self::headline( $post ),
			'datePublished'    => mysql2date( 'c', $post->post_date_gmt, false ),
			'dateModified'     => mysql2date( 'c', $post->post_modified_gmt, false ),
			'author'           => [ '@id' => Person_Schema::author_id_url( (int) $post->post_author ) ],
			'publisher'        => [ '@id' => $home . '#organization' ],
			'inLanguage'       => str_replace( '_', '-', (string) get_locale() ),
		];

		$desc = self::description( $post );
		if ( $desc !== '' ) {
			$obj['description'] = $desc;
		}

		$image = self::image( $post );
		if ( $image !== [] ) {
			$obj['image'] = $image;
		}

		$categories = self::categories( $post );
		if ( $categories !== [] ) {
			$obj['articleSection'] = $categories;
		}

		$tags = self::tags( $post );
		if ( $tags !== [] ) {
			$obj['keywords'] = implode( ', ', $tags );
		}

		$word_count = self::word_count( $post );
		if ( $word_count > 0 ) {
			$obj['wordCount'] = $word_count;
		}

		return $obj;
	}

	private static function resolve_type( \WP_Post $post ): string {
		$override = (string) get_post_meta( $post->ID, 'sfk_schema_type', true );
		if ( $override !== '' ) {
			return $override;
		}

		if ( $post->post_type === 'page' ) {
			return 'Article';
		}

		$cats = get_the_category( $post->ID );
		if ( is_array( $cats ) ) {
			foreach ( $cats as $cat ) {
				if ( ! $cat instanceof \WP_Term ) {
					continue;
				}
				$slug = strtolower( $cat->slug );
				$name = strtolower( $cat->name );
				if ( str_contains( $slug, 'news' ) || str_contains( $name, '뉴스' ) || str_contains( $name, 'news' ) ) {
					return 'NewsArticle';
				}
			}
		}

		return 'BlogPosting';
	}

	private static function headline( \WP_Post $post ): string {
		$title = (string) get_the_title( $post );
		// Schema.org headline guidance is 110 chars max for clean display.
		return mb_substr( $title, 0, 110 );
	}

	private static function description( \WP_Post $post ): string {
		$desc = (string) get_post_meta( $post->ID, 'sfk_meta_description', true );
		if ( $desc === '' && has_excerpt( $post ) ) {
			$desc = (string) get_the_excerpt( $post );
		}
		if ( $desc === '' ) {
			$desc = (string) wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '…' );
		}
		return trim( $desc );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function image( \WP_Post $post ): array {
		if ( ! has_post_thumbnail( $post ) ) {
			return [];
		}
		$id  = (int) get_post_thumbnail_id( $post );
		$src = wp_get_attachment_image_src( $id, 'full' );
		if ( ! is_array( $src ) ) {
			return [];
		}
		[ $url, $w, $h ] = $src;
		return [
			'@type'  => 'ImageObject',
			'url'    => (string) $url,
			'width'  => (int) $w,
			'height' => (int) $h,
		];
	}

	/**
	 * @return list<string>
	 */
	private static function categories( \WP_Post $post ): array {
		$cats = get_the_category( $post->ID );
		if ( ! is_array( $cats ) ) {
			return [];
		}
		$names = [];
		foreach ( $cats as $cat ) {
			if ( $cat instanceof \WP_Term ) {
				$names[] = $cat->name;
			}
		}
		return $names;
	}

	/**
	 * @return list<string>
	 */
	private static function tags( \WP_Post $post ): array {
		$tags = get_the_tags( $post->ID );
		if ( ! is_array( $tags ) ) {
			return [];
		}
		$names = [];
		foreach ( $tags as $tag ) {
			if ( $tag instanceof \WP_Term ) {
				$names[] = $tag->name;
			}
		}
		return $names;
	}

	private static function word_count( \WP_Post $post ): int {
		$text = wp_strip_all_tags( (string) $post->post_content );
		return mb_strlen( $text ) > 0 ? str_word_count( $text ) : 0;
	}
}
