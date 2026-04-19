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
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
		add_action( 'init', [ $this, 'register_modules' ], 5 );
		add_action( 'init', [ $this, 'maybe_flush_rewrite' ], 9999 );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
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
		// Defaults seed, capability registration, db migrations.
		add_option(
			'sfk_settings',
			[
				'version'         => SFK_VERSION,
				'enabled_modules' => [ 'content-analyzer', 'head-meta', 'schema', 'naver-meta', 'naver-sitemap' ],
			]
		);
		update_option( 'sfk_needs_rewrite_flush', '1' );
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	public function modules(): Modules {
		return $this->modules;
	}
}
