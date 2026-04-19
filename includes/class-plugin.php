<?php
/**
 * Plugin bootstrap — singleton entrypoint, hook registration, activation.
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static ?self $instance = null;

	private Modules $modules;

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	private function __construct() {
		$this->modules = new Modules();
	}

	public function boot(): void {
		add_action( 'plugins_loaded', [ $this, 'maybe_migrate' ], 5 );
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
		add_action( 'init', [ $this, 'register_modules' ], 5 );
		add_action( 'init', [ $this, 'maybe_flush_rewrite' ], 9999 );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
	}

	/**
	 * Runtime migration safety net.
	 *
	 * Activation hook only fires on first activate (and on full
	 * deactivate/reactivate), but plugin upgrades via WP admin or
	 * `wp plugin install --force` replace the files without re-firing
	 * activation. So sfk_settings.version drifts: still says the value
	 * that was active on first install, even on a freshly-upgraded site.
	 *
	 * Runs once per request; no DB write when versions already match.
	 */
	public function maybe_migrate(): void {
		$settings        = (array) get_option( 'sfk_settings', [] );
		$stored_version  = (string) ( $settings['version'] ?? '0.0.0' );
		if ( version_compare( $stored_version, SFK_VERSION, '>=' ) ) {
			return;
		}
		// Future: branch on $stored_version for actual schema migrations.
		$settings['version'] = SFK_VERSION;
		update_option( 'sfk_settings', $settings );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'seo-for-korean',
			false,
			dirname( SFK_BASENAME ) . '/languages'
		);
	}

	public function register_modules(): void {
		/**
		 * Register modules. Each module is a class implementing a `boot(): void` method.
		 *
		 * Add new modules by appending to this filter.
		 *
		 * @param array<string, string> $modules Map of module slug => fully-qualified class name.
		 */
		$modules = apply_filters(
			'sfk/modules',
			[
				'content-analyzer' => Modules\ContentAnalyzer\Content_Analyzer_Module::class,
				'head-meta'        => Modules\HeadMeta\Head_Meta_Module::class,
				'schema'           => Modules\Schema\Schema_Module::class,
				'sitemap'          => Modules\Sitemap\Sitemap_Module::class,
				'templates'        => Modules\Templates\Templates_Module::class,
				'images'           => Modules\Images\Images_Module::class,
				'redirections'     => Modules\Redirections\Redirections_Module::class,
				'monitor-404'      => Modules\Monitor_404\Monitor_404_Module::class,
				'settings-ui'      => Modules\SettingsUI\Settings_UI_Module::class,
				'naver-meta'       => Modules\NaverMeta\Naver_Meta_Module::class,
				'naver-sitemap'    => Modules\NaverSitemap\Naver_Sitemap_Module::class,
				'example'          => Modules\Example\Example_Module::class,
			]
		);

		$this->modules->register_many( $modules );
		$this->modules->boot_active();
	}

	public function register_rest_routes(): void {
		// Modules with REST routes register them via their own boot() method.
		do_action( 'sfk/rest_init' );
	}

	public function enqueue_editor_assets(): void {
		$asset_file = SFK_PATH . 'build/editor-sidebar.asset.php';
		$deps       = [ 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n' ];
		$version    = SFK_VERSION;

		if ( file_exists( $asset_file ) ) {
			$asset   = require $asset_file;
			$deps    = $asset['dependencies'] ?? $deps;
			$version = $asset['version'] ?? $version;
		}

		wp_enqueue_script(
			'seo-for-korean-editor-sidebar',
			SFK_URL . 'build/editor-sidebar.js',
			$deps,
			$version,
			true
		);

		wp_set_script_translations(
			'seo-for-korean-editor-sidebar',
			'seo-for-korean',
			SFK_PATH . 'languages'
		);
	}

	/**
	 * Modules that add rewrite rules register them on `init`, which fires
	 * AFTER activation. We can't flush during activate() because the rules
	 * aren't registered yet — instead we flag, and {@see maybe_flush_rewrite}
	 * picks it up on the next request.
	 */
	public function maybe_flush_rewrite(): void {
		if ( get_option( 'sfk_needs_rewrite_flush' ) !== '1' ) {
			return;
		}
		flush_rewrite_rules( false );
		delete_option( 'sfk_needs_rewrite_flush' );
	}

	public static function activate(): void {
		$defaults = [
			'version'         => SFK_VERSION,
			'enabled_modules' => [ 'content-analyzer', 'head-meta', 'schema', 'sitemap', 'templates', 'images', 'redirections', 'monitor-404', 'settings-ui', 'naver-meta', 'naver-sitemap' ],
			'templates'       => Modules\Templates\Templates_Module::defaults(),
		];

		// First-install: seed defaults wholesale.
		// Upgrade: keep user-modified keys, fill any new defaults, ALWAYS
		// stamp the live plugin version so future migrations can branch on it.
		$existing = get_option( 'sfk_settings' );
		if ( ! is_array( $existing ) ) {
			update_option( 'sfk_settings', $defaults );
		} else {
			$merged            = $defaults;
			foreach ( $existing as $key => $value ) {
				$merged[ $key ] = $value;
			}
			$merged['version'] = SFK_VERSION;
			update_option( 'sfk_settings', $merged );
		}

		update_option( 'sfk_needs_rewrite_flush', '1' );
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	public function modules(): Modules {
		return $this->modules;
	}
}
