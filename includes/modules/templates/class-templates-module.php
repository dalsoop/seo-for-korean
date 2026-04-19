<?php
/**
 * Templates module — site-wide title/description templates with %variables%.
 *
 * Filters `pre_get_document_title` so every page (post, archive, search,
 * 404, home) gets a consistent, configurable title without touching the
 * theme. Per-post override via the `sfk_seo_title` post meta.
 *
 * Description templates are read by the head-meta module; this class owns
 * the resolver and the option layout.
 *
 * Defaults are seeded on activation. Users override per context via
 * `sfk_settings['templates'][context]['title'|'description']`.
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\Templates;

use SEOForKorean\Helper;

defined( 'ABSPATH' ) || exit;

final class Templates_Module {

	public function boot(): void {
		add_action( 'init', [ $this, 'register_post_meta' ] );
		add_filter( 'pre_get_document_title', [ $this, 'filter_title' ], 15 );
	}

	public function register_post_meta(): void {
		\register_post_meta(
			'',
			'sfk_seo_title',
			[
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'string',
				'auth_callback' => static fn (): bool => Helper::has_cap( 'edit_posts' ),
			]
		);
	}

	public function filter_title( string $title ): string {
		$post = ( function_exists( 'is_singular' ) && is_singular() )
			? ( get_queried_object() instanceof \WP_Post ? get_queried_object() : null )
			: null;

		$resolver = new Template_Resolver();

		// Per-post override wins.
		if ( $post instanceof \WP_Post ) {
			$override = (string) get_post_meta( $post->ID, 'sfk_seo_title', true );
			if ( $override !== '' ) {
				$resolved = $resolver->resolve( $override, $post );
				return $resolved !== '' ? $resolved : $title;
			}
		}

		$template = (string) Helper::get_settings( 'templates.' . $this->context() . '.title', '' );
		if ( $template === '' ) {
			return $title;
		}

		$resolved = $resolver->resolve( $template, $post );
		return $resolved !== '' ? $resolved : $title;
	}

	private function context(): string {
		if ( function_exists( 'is_search' ) && is_search() ) {
			return 'search';
		}
		if ( function_exists( 'is_404' ) && is_404() ) {
			return 'notfound';
		}
		if ( function_exists( 'is_front_page' ) && is_front_page() ) {
			return 'home';
		}
		if ( function_exists( 'is_home' ) && is_home() ) {
			return 'home';
		}
		if ( function_exists( 'is_category' ) && is_category() ) {
			return 'category';
		}
		if ( function_exists( 'is_tag' ) && is_tag() ) {
			return 'tag';
		}
		if ( function_exists( 'is_singular' ) && is_singular( 'page' ) ) {
			return 'page';
		}
		return 'single';
	}

	/**
	 * Default templates — seeded into sfk_settings on plugin activation.
	 * Korean-friendly: separator default '|', includes 검색 / 404 templates.
	 *
	 * @return array<string, array{title: string, description: string}>
	 */
	public static function defaults(): array {
		return [
			'home'     => [
				'title'       => '%sitename% %separator% %sitedesc%',
				'description' => '%sitedesc%',
			],
			'single'   => [
				'title'       => '%title% %separator% %sitename%',
				'description' => '%excerpt%',
			],
			'page'     => [
				'title'       => '%title% %separator% %sitename%',
				'description' => '%excerpt%',
			],
			'category' => [
				'title'       => '%category% %separator% %sitename%',
				'description' => '%category% 관련 글 모음',
			],
			'tag'      => [
				'title'       => '%tag% %separator% %sitename%',
				'description' => '%tag% 태그가 달린 글 모음',
			],
			'search'   => [
				'title'       => '%searchphrase% 검색 결과 %separator% %sitename%',
				'description' => '%searchphrase% 검색 결과',
			],
			'notfound' => [
				'title'       => '페이지를 찾을 수 없습니다 %separator% %sitename%',
				'description' => '요청하신 페이지를 찾을 수 없습니다.',
			],
		];
	}
}
