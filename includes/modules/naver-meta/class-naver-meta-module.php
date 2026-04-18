<?php
/**
 * Naver Meta module — emits Naver Search Advisor verification + Korean
 * social-share OG hints in <head>.
 *
 * Configure via filter (overrides DB option, useful for theme functions.php):
 *
 *   add_filter( 'sfk/naver_meta/site_verification',
 *       static fn (): string => 'YOUR_NAVER_VERIFICATION_CODE' );
 *
 * Or via the plugin settings option:
 *
 *   sfk_settings['naver_meta']['site_verification'] = '...';
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\NaverMeta;

use SEOForKorean\Helper;

defined( 'ABSPATH' ) || exit;

final class Naver_Meta_Module {

	public function boot(): void {
		add_action( 'wp_head', [ $this, 'render_head_meta' ], 1 );
	}

	public function render_head_meta(): void {
		$verification = (string) apply_filters(
			'sfk/naver_meta/site_verification',
			(string) Helper::get_settings( 'naver_meta.site_verification', '' )
		);

		if ( $verification !== '' ) {
			printf(
				"<meta name=\"naver-site-verification\" content=\"%s\" />\n",
				esc_attr( $verification )
			);
		}

		// KakaoTalk shares prefer images >= 300x300. If the post has a thumbnail
		// smaller than that, emit a hint comment so theme/SEO devs notice.
		if ( ! is_singular() || ! has_post_thumbnail() ) {
			return;
		}

		$thumb_id = (int) get_post_thumbnail_id();
		$meta     = wp_get_attachment_metadata( $thumb_id );

		if (
			is_array( $meta )
			&& isset( $meta['width'], $meta['height'] )
			&& ( $meta['width'] < 300 || $meta['height'] < 300 )
		) {
			printf(
				"<!-- sfk: featured image is %dx%d — KakaoTalk recommends 300x300+ -->\n",
				(int) $meta['width'],
				(int) $meta['height']
			);
		}
	}
}
