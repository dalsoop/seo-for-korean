<?php
/**
 * VideoObject schema. Auto-detected from embedded video iframes/oEmbeds.
 *
 * Supported sources:
 *   - YouTube iframe embed (youtube.com/embed/...)
 *   - YouTube watch URL (auto-embedded by WP oEmbed)
 *   - youtu.be short URL
 *   - Vimeo iframe embed
 *
 * The post becomes a video page in Google's eyes — important for video-
 * heavy blogs and tutorial channels. Required fields per Google docs:
 * name, description, thumbnailUrl, uploadDate. We supply all four when
 * the post has a featured image.
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\Schema\Schemas;

defined( 'ABSPATH' ) || exit;

final class Video_Schema {

	public static function applies(): bool {
		if ( ! is_singular() ) {
			return false;
		}
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}
		return self::detect_video( $post->post_content ) !== '';
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function build(): array {
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return [];
		}

		$embed = self::detect_video( $post->post_content );
		if ( $embed === '' ) {
			return [];
		}

		$obj = [
			'@type'      => 'VideoObject',
			'@id'        => (string) get_permalink( $post ) . '#video',
			'name'       => (string) get_the_title( $post ),
			'embedUrl'   => $embed,
			'uploadDate' => (string) mysql2date( 'c', $post->post_date_gmt, false ),
		];

		if ( has_excerpt( $post ) ) {
			$obj['description'] = (string) get_the_excerpt( $post );
		} else {
			$trimmed = (string) wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '…' );
			if ( $trimmed !== '' ) {
				$obj['description'] = $trimmed;
			}
		}

		// Google requires thumbnailUrl. Featured image first; fall back
		// to YouTube auto-thumbnail when we can derive the video ID.
		$thumb = '';
		if ( has_post_thumbnail( $post ) ) {
			$thumb = (string) get_the_post_thumbnail_url( $post, 'full' );
		}
		if ( $thumb === '' ) {
			$yt_id = self::extract_youtube_id( $embed );
			if ( $yt_id !== '' ) {
				$thumb = "https://i.ytimg.com/vi/{$yt_id}/hqdefault.jpg";
			}
		}
		if ( $thumb !== '' ) {
			$obj['thumbnailUrl'] = $thumb;
		}

		return $obj;
	}

	private static function detect_video( string $html ): string {
		// 1. YouTube iframe embed
		if ( preg_match( '/<iframe[^>]+src="(https?:\/\/(?:www\.)?youtube(?:-nocookie)?\.com\/embed\/[a-zA-Z0-9_-]+)/i', $html, $m ) === 1 ) {
			return (string) $m[1];
		}
		// 2. Vimeo iframe embed
		if ( preg_match( '/<iframe[^>]+src="(https?:\/\/player\.vimeo\.com\/video\/\d+)/i', $html, $m ) === 1 ) {
			return (string) $m[1];
		}
		// 3. YouTube watch URL (oEmbed-converted plain URL)
		if ( preg_match( '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/i', $html, $m ) === 1 ) {
			return "https://www.youtube.com/embed/{$m[1]}";
		}
		// 4. youtu.be short URL
		if ( preg_match( '/youtu\.be\/([a-zA-Z0-9_-]+)/i', $html, $m ) === 1 ) {
			return "https://www.youtube.com/embed/{$m[1]}";
		}
		return '';
	}

	private static function extract_youtube_id( string $embed_url ): string {
		if ( preg_match( '/youtube(?:-nocookie)?\.com\/embed\/([a-zA-Z0-9_-]+)/i', $embed_url, $m ) === 1 ) {
			return (string) $m[1];
		}
		return '';
	}
}
