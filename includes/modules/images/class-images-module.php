<?php
/**
 * Images module — fills missing alt text on every image WP renders.
 *
 * Why only alt, and not lazy-loading / width-height / decoding=async:
 * WordPress core already handles those (since 5.5, 6.0, 6.1 respectively).
 * Adding our own would step on toes. The actual gap is alt text — core
 * just outputs alt="" when the attachment's `_wp_attachment_image_alt`
 * meta is empty, which kills both SEO and accessibility.
 *
 * Resolution chain (first non-filename, non-empty wins):
 *   1. Attachment post title (if it doesn't look like a default filename)
 *   2. Cleaned filename — separators → spaces, date stamps removed
 *   3. Parent post title — only if attached to a post
 *
 * Filename heuristic catches: IMG_1234, DSC_5678, screenshot 2026-04-19,
 * 스크린샷 2026-04-19, photo-001, etc. These shouldn't become alt text
 * because they tell crawlers literally nothing about the image.
 *
 * Filter to opt out per-attachment:
 *   add_filter('sfk/images/auto_alt', fn($enabled, $att) => $att->ID !== 99);
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\Images;

defined( 'ABSPATH' ) || exit;

final class Images_Module {

	public function boot(): void {
		add_filter( 'wp_get_attachment_image_attributes', [ $this, 'fill_alt' ], 10, 2 );
	}

	/**
	 * @param array<string, mixed> $attr
	 * @return array<string, mixed>
	 */
	public function fill_alt( array $attr, \WP_Post $attachment ): array {
		/**
		 * Allow disabling auto-alt per attachment. Returning false leaves
		 * the existing (possibly empty) alt untouched.
		 *
		 * @param bool     $enabled
		 * @param \WP_Post $attachment
		 */
		if ( ! (bool) apply_filters( 'sfk/images/auto_alt', true, $attachment ) ) {
			return $attr;
		}

		$existing = trim( (string) ( $attr['alt'] ?? '' ) );
		if ( $existing !== '' ) {
			return $attr;
		}

		$generated = $this->generate( $attachment );
		if ( $generated !== '' ) {
			$attr['alt'] = $generated;
		}
		return $attr;
	}

	private function generate( \WP_Post $att ): string {
		$title = trim( (string) $att->post_title );
		if ( $title !== '' && ! $this->looks_like_filename( $title ) ) {
			return $title;
		}

		$file = (string) get_attached_file( $att->ID );
		if ( $file !== '' ) {
			$basename = pathinfo( $file, PATHINFO_FILENAME );
			$cleaned  = $this->clean_filename( $basename );
			if ( $cleaned !== '' && ! $this->looks_like_filename( $cleaned ) ) {
				return $cleaned;
			}
		}

		if ( (int) $att->post_parent > 0 ) {
			$parent_title = (string) get_the_title( (int) $att->post_parent );
			if ( $parent_title !== '' ) {
				return $parent_title;
			}
		}

		return '';
	}

	private function looks_like_filename( string $s ): bool {
		// Camera/phone/screenshot defaults — same in English and Korean.
		if ( preg_match( '/^(img|dsc|dscn|photo|image|screenshot|스크린샷|화면\s?캡처|화면\s?캡쳐)[\s_-]*\d/iu', $s ) === 1 ) {
			return true;
		}
		// Bare date-stamps like "2026-04-19" or "2026 04 19".
		if ( preg_match( '/^\d{4}[\s_-]?\d{2}[\s_-]?\d{2}/', $s ) === 1 ) {
			return true;
		}
		// Long hex/uuid blobs.
		if ( preg_match( '/^[a-f0-9]{16,}$/i', $s ) === 1 ) {
			return true;
		}
		return false;
	}

	private function clean_filename( string $name ): string {
		// Separators → space; collapse whitespace.
		$out = (string) preg_replace( '/[_\-\.]+/', ' ', $name );
		// Strip embedded date stamps (2026-04-19 or 20260419).
		$out = (string) preg_replace( '/\b\d{4}[\s_-]?\d{2}[\s_-]?\d{2}\b/', '', $out );
		// Strip trailing -1, -2, copy, sized variants like -1024x768.
		$out = (string) preg_replace( '/\s\d+x\d+\b/', '', $out );
		$out = (string) preg_replace( '/\s\d+\s*$/', '', $out );
		$out = (string) preg_replace( '/\s+/', ' ', $out );
		return trim( $out );
	}
}
