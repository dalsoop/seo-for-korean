<?php
/**
 * Person schema for the post author. Only emitted on singular post views.
 * Linked from Article.author.
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\Schema\Schemas;

defined( 'ABSPATH' ) || exit;

final class Person_Schema {

	public static function applies(): bool {
		return is_singular() && self::current_author_id() > 0;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function build(): array {
		$user_id = self::current_author_id();
		$user    = get_userdata( $user_id );
		if ( ! $user ) {
			return [];
		}

		$obj = [
			'@type' => 'Person',
			'@id'   => self::author_id_url( $user_id ),
			'name'  => $user->display_name ?: $user->user_login,
			'url'   => get_author_posts_url( $user_id ),
		];

		$avatar = get_avatar_url( $user_id, [ 'size' => 256 ] );
		if ( is_string( $avatar ) && $avatar !== '' ) {
			$obj['image'] = [
				'@type' => 'ImageObject',
				'url'   => $avatar,
			];
		}

		$bio = (string) get_user_meta( $user_id, 'description', true );
		if ( $bio !== '' ) {
			$obj['description'] = $bio;
		}

		return $obj;
	}

	private static function current_author_id(): int {
		$post = get_queried_object();
		if ( $post instanceof \WP_Post ) {
			return (int) $post->post_author;
		}
		return 0;
	}

	public static function author_id_url( int $user_id ): string {
		return home_url( '/' ) . '#person-' . $user_id;
	}
}
