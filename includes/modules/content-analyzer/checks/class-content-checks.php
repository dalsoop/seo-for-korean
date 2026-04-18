<?php
/**
 * Content body structure. Length + heading count.
 * Future: H3 hierarchy, list usage, table presence.
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\ContentAnalyzer\Checks;

use SEOForKorean\Modules\ContentAnalyzer\Helpers;

defined( 'ABSPATH' ) || exit;

final class Content_Checks {

	/**
	 * @param array<string, mixed> $ctx
	 * @return list<array{id: string, label: string, status: string, message: string, weight: int}>
	 */
	public static function run( array $ctx ): array {
		return [ self::content_length( $ctx ), self::h2_count( $ctx ) ];
	}

	/** @param array<string, mixed> $ctx */
	private static function content_length( array $ctx ): array {
		$len = (int) $ctx['content_length'];
		if ( $len < 100 ) {
			return Helpers::result( 'content_length', '본문 길이', 'fail', "본문이 너무 짧습니다 ({$len}자). 최소 600자 권장.", 10 );
		}
		if ( $len < 300 ) {
			return Helpers::result( 'content_length', '본문 길이', 'fail', "본문이 짧습니다 ({$len}자). 600자 이상 권장.", 10 );
		}
		if ( $len < 600 ) {
			return Helpers::result( 'content_length', '본문 길이', 'warning', "본문이 다소 짧습니다 ({$len}자). 600자 이상 권장.", 10 );
		}
		return Helpers::result( 'content_length', '본문 길이', 'pass', "본문 길이가 충분합니다 ({$len}자).", 10 );
	}

	/** @param array<string, mixed> $ctx */
	private static function h2_count( array $ctx ): array {
		$count = preg_match_all( '/<h2\b[^>]*>/i', (string) $ctx['content_html'] );
		$count = is_int( $count ) ? $count : 0;
		if ( $count === 0 ) {
			return Helpers::result( 'h2_count', 'H2 헤딩', 'warning', 'H2 헤딩이 없습니다. 글이 길다면 2개 이상 추가하세요.', 5 );
		}
		if ( $count === 1 ) {
			return Helpers::result( 'h2_count', 'H2 헤딩', 'warning', "H2 헤딩이 {$count}개 있습니다. 본문이 길면 더 추가하세요.", 5 );
		}
		return Helpers::result( 'h2_count', 'H2 헤딩', 'pass', "H2 헤딩이 {$count}개로 적절합니다.", 5 );
	}
}
