<?php
/**
 * Link analysis. Internal vs outbound counts (cached on Ctx).
 * Future: broken-link detection, dofollow ratio, link diversity.
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\ContentAnalyzer\Checks;

use SEOForKorean\Modules\ContentAnalyzer\Helpers;

defined( 'ABSPATH' ) || exit;

final class Links_Checks {

	/**
	 * @param array<string, mixed> $ctx
	 * @return list<array{id: string, label: string, status: string, message: string, weight: int}>
	 */
	public static function run( array $ctx ): array {
		return [ self::internal_links( $ctx ), self::outbound_links( $ctx ) ];
	}

	/** @param array<string, mixed> $ctx */
	private static function internal_links( array $ctx ): array {
		$n = (int) ( $ctx['link_counts']['internal'] ?? 0 );
		if ( $n === 0 ) {
			return Helpers::result( 'internal_links', '내부 링크', 'warning', '내부 링크가 없습니다. 관련 글로 1개 이상 링크하세요.', 5 );
		}
		return Helpers::result( 'internal_links', '내부 링크', 'pass', "내부 링크 {$n}개.", 5 );
	}

	/** @param array<string, mixed> $ctx */
	private static function outbound_links( array $ctx ): array {
		$n = (int) ( $ctx['link_counts']['outbound'] ?? 0 );
		if ( $n === 0 ) {
			return Helpers::result( 'outbound_links', '외부 링크', 'warning', '외부 링크가 없습니다. 권위 있는 출처로 1개 이상 링크하면 신뢰도가 올라갑니다.', 5 );
		}
		return Helpers::result( 'outbound_links', '외부 링크', 'pass', "외부 링크 {$n}개.", 5 );
	}
}
