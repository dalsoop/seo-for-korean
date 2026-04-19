<?php
/**
 * Settings UI module — admin menu page + REST surface backing the
 * React-based settings app.
 *
 * Page lives under WordPress Settings → SEO for Korean. The PHP side
 * just registers the menu, enqueues the React bundle, and exposes
 * GET/PUT /seo-for-korean/v1/settings. Everything else is in
 * src/settings-page.js.
 *
 * Permission: `manage_options` for both admin page and REST endpoints —
 * SEO settings affect site-wide output, so editors don't get to touch them.
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\SettingsUI;

use SEOForKorean\Helper;

defined( 'ABSPATH' ) || exit;

final class Settings_UI_Module {

	private const MENU_SLUG = 'seo-for-korean';

	public function boot(): void {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'sfk/rest_init', [ $this, 'register_routes' ] );
	}

	public function register_menu(): void {
		add_options_page(
			__( 'SEO for Korean', 'seo-for-korean' ),
			__( 'SEO for Korean', 'seo-for-korean' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render_page' ]
		);
	}

	public function render_page(): void {
		echo '<div class="wrap">';
		echo '<div id="sfk-settings-root">';
		echo '<p>' . esc_html__( 'Loading…', 'seo-for-korean' ) . '</p>';
		echo '</div>';
		echo '</div>';
	}

	public function enqueue_assets( string $hook ): void {
		if ( $hook !== 'settings_page_' . self::MENU_SLUG ) {
			return;
		}

		$asset_file = SFK_PATH . 'build/settings-page.asset.php';
		$deps       = [ 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ];
		$version    = SFK_VERSION;

		if ( file_exists( $asset_file ) ) {
			$asset   = require $asset_file;
			$deps    = $asset['dependencies'] ?? $deps;
			$version = $asset['version'] ?? $version;
		}

		wp_enqueue_script(
			'seo-for-korean-settings',
			SFK_URL . 'build/settings-page.js',
			$deps,
			$version,
			true
		);
		wp_enqueue_style( 'wp-components' );
		wp_set_script_translations(
			'seo-for-korean-settings',
			'seo-for-korean',
			SFK_PATH . 'languages'
		);
	}

	public function register_routes(): void {
		register_rest_route(
			'seo-for-korean/v1',
			'/settings',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'handle_get' ],
					'permission_callback' => static fn (): bool => Helper::has_cap( 'manage_options' ),
				],
				[
					'methods'             => 'PUT',
					'callback'            => [ $this, 'handle_put' ],
					'permission_callback' => static fn (): bool => Helper::has_cap( 'manage_options' ),
				],
			]
		);
	}

	public function handle_get( \WP_REST_Request $request ): \WP_REST_Response {
		$settings = (array) get_option( 'sfk_settings', [] );
		return new \WP_REST_Response( $settings, 200 );
	}

	public function handle_put( \WP_REST_Request $request ): \WP_REST_Response {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			return new \WP_REST_Response( [ 'error' => 'invalid body' ], 400 );
		}
		// Replace whole settings — the admin UI always sends the full state
		// it just rendered, so no merging surprises.
		update_option( 'sfk_settings', $body );
		// Modules toggled may have added rewrite rules.
		update_option( 'sfk_needs_rewrite_flush', '1' );
		return new \WP_REST_Response( [ 'ok' => true, 'settings' => $body ], 200 );
	}
}
