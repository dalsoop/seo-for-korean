<?php
/**
 * WebSite schema. Site-wide; emitted on every page.
 * Includes a SearchAction so Google/Naver can render a search box in SERPs.
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\Schema\Schemas;

defined( 'ABSPATH' ) || exit;

final class Website_Schema {

	public static function applies(): bool {
		return true;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function build(): array {
		$home = home_url( '/' );

		return [
			'@type'           => 'WebSite',
			'@id'             => $home . '#website',
			'url'             => $home,
			'name'            => get_bloginfo( 'name' ),
			'description'     => get_bloginfo( 'description' ),
			'inLanguage'      => str_replace( '_', '-', (string) get_locale() ),
			'publisher'       => [ '@id' => $home . '#organization' ],
			'potentialAction' => [
				[
					'@type'       => 'SearchAction',
					'target'      => [
						'@type'       => 'EntryPoint',
						'urlTemplate' => $home . '?s={search_term_string}',
					],
					'query-input' => 'required name=search_term_string',
				],
			],
		];
	}
}
