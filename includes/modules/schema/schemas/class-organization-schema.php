<?php
/**
 * Organization schema. Site-wide publisher entity.
 * Linked from Article.publisher and WebSite.publisher.
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\Schema\Schemas;

use SEOForKorean\Helper;

defined( 'ABSPATH' ) || exit;

final class Organization_Schema {

	public static function applies(): bool {
		return true;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function build(): array {
		$home = home_url( '/' );

		$logo = (string) Helper::get_settings( 'schema.organization_logo', '' );
		if ( $logo === '' ) {
			$logo = (string) get_site_icon_url( 512 );
		}

		$obj = [
			'@type' => 'Organization',
			'@id'   => $home . '#organization',
			'name'  => get_bloginfo( 'name' ),
			'url'   => $home,
		];

		if ( $logo !== '' ) {
			$obj['logo'] = [
				'@type' => 'ImageObject',
				'url'   => $logo,
			];
		}

		// Optional sameAs: array of social profile URLs (LinkedIn, Twitter, etc.).
		$same_as = (array) Helper::get_settings( 'schema.organization_same_as', [] );
		$same_as = array_values( array_filter( array_map( 'strval', $same_as ), 'wp_http_validate_url' ) );
		if ( $same_as !== [] ) {
			$obj['sameAs'] = $same_as;
		}

		return $obj;
	}
}
