<?php
/**
 * Image checks. Alt coverage today; future: filename keyword, alt keyword,
 * image-to-content ratio, dimension specification.
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\ContentAnalyzer\Checks;

use SEOForKorean\Modules\ContentAnalyzer\Helpers;

defined( 'ABSPATH' ) || exit;

final class Images_Checks {

	/**
	 * @param array<string, mixed> $ctx
	 * @return list<array{id: string, label: string, status: string, message: string, weight: int}>
	 */
	public static function run( array $ctx ): array {
		return [
			self::image_alt_coverage( $ctx ),
			self::image_density( $ctx ),
		];
	}

	/** @param array<string, mixed> $ctx */
	private static function image_density( array $ctx ): array {
		$len = (int) $ctx['content_length'];
		if ( $len < 300 ) {
			return Helpers::result( 'image_density', '이미지 밀도', 'na', '본문이 짧아 평가 생략.', 5 );
		}
		$count = preg_match_all( '/<img\b[^>]*>/i', (string) $ctx['content_html'] );
		$count = is_int( $count ) ? $count : 0;
		$recommended = max( 1, intdiv( $len, 600 ) );
		if ( $count === 0 ) {
			return Helpers::result( 'image_density', '이미지 밀도', 'warning', "이미지가 없습니다. 약 {$recommended}개 권장 ({$len}자 본문).", 5 );
		}
		if ( $count >= $recommended ) {
			return Helpers::result( 'image_density', '이미지 밀도', 'pass', "이미지 {$count}개 (본문 {$len}자에 적절).", 5 );
		}
		return Helpers::result( 'image_density', '이미지 밀도', 'warning', "이미지 {$count}개. 본문 {$len}자에는 약 {$recommended}개 권장.", 5 );
	}

	/** @param array<string, mixed> $ctx */
	private static function image_alt_coverage( array $ctx ): array {
		$html  = (string) $ctx['content_html'];
		$total = preg_match_all( '/<img\b[^>]*>/i', $html, $imgs );
		$total = is_int( $total ) ? $total : 0;
		if ( $total === 0 ) {
			return Helpers::result( 'image_alt_coverage', '이미지 alt', 'na', '본문에 이미지가 없습니다.', 5 );
		}
		$with_alt = 0;
		foreach ( (array) ( $imgs[0] ?? [] ) as $tag ) {
			if ( preg_match( '/\balt\s*=\s*"[^"]+"/i', (string) $tag ) === 1 ) {
				++$with_alt;
			}
		}
		if ( $with_alt === $total ) {
			return Helpers::result( 'image_alt_coverage', '이미지 alt', 'pass', "모든 이미지({$total}개)에 alt 속성이 있습니다.", 5 );
		}
		$missing = $total - $with_alt;
		return Helpers::result( 'image_alt_coverage', '이미지 alt', 'warning', "{$missing}개 이미지에 alt 속성이 없습니다 (총 {$total}개).", 5 );
	}
}
