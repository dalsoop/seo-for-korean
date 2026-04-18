<?php
/**
 * Meta description checks. Length only; future: keyword position, sentiment.
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\ContentAnalyzer\Checks;

use SEOForKorean\Modules\ContentAnalyzer\Helpers;

defined( 'ABSPATH' ) || exit;

final class Meta_Checks {

	/**
	 * @param array<string, mixed> $ctx
	 * @return list<array{id: string, label: string, status: string, message: string, weight: int}>
	 */
	public static function run( array $ctx ): array {
		return [ self::meta_description_length( $ctx ) ];
	}

	/** @param array<string, mixed> $ctx */
	private static function meta_description_length( array $ctx ): array {
		$len = (int) $ctx['meta_description_length'];
		if ( $len === 0 ) {
			return Helpers::result( 'meta_description_length', '메타 설명', 'warning', '메타 설명이 비어 있습니다. 80~155자 권장.', 10 );
		}
		if ( $len < 40 ) {
			return Helpers::result( 'meta_description_length', '메타 설명', 'fail', "메타 설명이 너무 짧습니다 ({$len}자).", 10 );
		}
		if ( $len > 200 ) {
			return Helpers::result( 'meta_description_length', '메타 설명', 'warning', "메타 설명이 너무 깁니다 ({$len}자). 검색 결과에서 잘립니다.", 10 );
		}
		if ( $len < 80 || $len > 155 ) {
			return Helpers::result( 'meta_description_length', '메타 설명', 'warning', "메타 설명 길이가 이상적이지 않습니다 ({$len}자). 80~155자 권장.", 10 );
		}
		return Helpers::result( 'meta_description_length', '메타 설명', 'pass', "메타 설명이 적절합니다 ({$len}자).", 10 );
	}
}
