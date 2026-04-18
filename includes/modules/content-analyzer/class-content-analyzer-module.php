<?php
/**
 * Content Analyzer module — exposes POST /seo-for-korean/v1/analyze.
 *
 * The Gutenberg sidebar calls this on every (debounced) edit and renders
 * the score + checklist returned here.
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\ContentAnalyzer;

use SEOForKorean\Helper;
use SEOForKorean\Morphology\Morphology_Client;

defined( 'ABSPATH' ) || exit;

final class Content_Analyzer_Module {

	public function boot(): void {
		add_action( 'init', [ $this, 'register_post_meta' ] );
		add_action( 'sfk/rest_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Expose focus keyword and meta description as REST-visible post meta so
	 * the Gutenberg sidebar can read/write them via `useEntityProp`.
	 *
	 * Registered against an empty post type so the meta is available on every
	 * editable post type (post, page, custom).
	 */
	public function register_post_meta(): void {
		$args = [
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'string',
			'auth_callback' => static fn (): bool => Helper::has_cap( 'edit_posts' ),
		];
		\register_post_meta( '', 'sfk_focus_keyword', $args );
		\register_post_meta( '', 'sfk_meta_description', $args );
	}

	public function register_routes(): void {
		register_rest_route(
			'seo-for-korean/v1',
			'/analyze',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_analyze' ],
				'permission_callback' => static fn (): bool => Helper::has_cap( 'edit_posts' ),
				'args'                => [
					'title'            => [ 'type' => 'string', 'default' => '' ],
					'content'          => [ 'type' => 'string', 'default' => '' ],
					'slug'             => [ 'type' => 'string', 'default' => '' ],
					'focus_keyword'    => [ 'type' => 'string', 'default' => '' ],
					'meta_description' => [ 'type' => 'string', 'default' => '' ],
				],
			]
		);
	}

	public function handle_analyze( \WP_REST_Request $request ): \WP_REST_Response {
		$analyzer = new Content_Analyzer( new Morphology_Client() );
		$result   = $analyzer->analyze(
			[
				'title'            => (string) $request->get_param( 'title' ),
				'content'          => (string) $request->get_param( 'content' ),
				'slug'             => (string) $request->get_param( 'slug' ),
				'focus_keyword'    => (string) $request->get_param( 'focus_keyword' ),
				'meta_description' => (string) $request->get_param( 'meta_description' ),
			]
		);

		return new \WP_REST_Response( $result, 200 );
	}
}
