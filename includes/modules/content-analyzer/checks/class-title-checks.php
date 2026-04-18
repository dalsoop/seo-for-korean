<?php
/**
 * Title-only checks. Length today; future: keyword position, numbers,
 * starts-with-keyword, power words.
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\ContentAnalyzer\Checks;

use SEOForKorean\Modules\ContentAnalyzer\Helpers;

defined( 'ABSPATH' ) || exit;

final class Title_Checks {

	/**
	 * @param array<string, mixed> $ctx
	 * @return list<array{id: string, label: string, status: string, message: string, weight: int}>
	 */
	public static function run( array $ctx ): array {
		return [ self::title_length( $ctx ) ];
	}

	/** @param array<string, mixed> $ctx */
	private static function title_length( array $ctx ): array {
		$len = (int) $ctx['title_length'];
		if ( $len === 0 ) {
			return Helpers::result( 'title_length', '제목 길이', 'fail', '제목이 비어 있습니다.', 10 );
		}
		if ( $len < 15 ) {
			return Helpers::result( 'title_length', '제목 길이', 'fail', "제목이 너무 짧습니다 ({$len}자). 최소 15자 권장.", 10 );
		}
		if ( $len > 70 ) {
			return Helpers::result( 'title_length', '제목 길이', 'warning', "제목이 너무 깁니다 ({$len}자). 검색 결과에서 잘릴 수 있습니다.", 10 );
		}
		if ( $len < 30 || $len > 60 ) {
			return Helpers::result( 'title_length', '제목 길이', 'warning', "제목 길이가 이상적이지 않습니다 ({$len}자). 30~60자 권장.", 10 );
		}
		return Helpers::result( 'title_length', '제목 길이', 'pass', "제목 길이가 적절합니다 ({$len}자).", 10 );
	}
}
