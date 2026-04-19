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
		return [
			self::internal_links( $ctx ),
			self::outbound_links( $ctx ),
			self::nofollow_outbound( $ctx ),
		];
	}

	/** @param array<string, mixed> $ctx */
	private static function nofollow_outbound( array $ctx ): array {
		$outbound_total = (int) ( $ctx['link_counts']['outbound'] ?? 0 );
		if ( $outbound_total === 0 ) {
			return Helpers::result( 'nofollow_outbound', '외부 링크 nofollow', 'na', '외부 링크가 없습니다.', 5 );
		}

		$total    = 0;
		$nofollow = 0;
		if ( preg_match_all( '/<a\s+([^>]*?)>/is', (string) $ctx['content_html'], $matches ) ) {
			foreach ( $matches[1] as $attrs ) {
				if ( preg_match( '/href\s*=\s*"([^"]+)"/i', (string) $attrs, $href_m ) !== 1 ) {
					continue;
				}
				$href = trim( (string) $href_m[1] );
				$is_outbound = str_starts_with( $href, 'http://' )
					|| str_starts_with( $href, 'https://' )
					|| str_starts_with( $href, '//' );
				if ( ! $is_outbound ) {
					continue;
				}
				++$total;
				$attrs_l = strtolower( (string) $attrs );
				if ( str_contains( $attrs_l, 'nofollow' )
					|| str_contains( $attrs_l, 'ugc' )
					|| str_contains( $attrs_l, 'sponsored' ) ) {
					++$nofollow;
				}
			}
		}

		if ( $total === 0 ) {
			return Helpers::result( 'nofollow_outbound', '외부 링크 nofollow', 'na', '외부 링크가 없습니다.', 5 );
		}
		$ratio = (int) round( $nofollow / $total * 100 );
		if ( $ratio === 0 ) {
			return Helpers::result( 'nofollow_outbound', '외부 링크 nofollow', 'pass', "외부 링크 {$total}개 모두 dofollow (추천 의미가 살아있음).", 5 );
		}
		if ( $ratio > 80 ) {
			return Helpers::result( 'nofollow_outbound', '외부 링크 nofollow', 'warning', "외부 링크 nofollow 비율 {$ratio}%로 높음. 너무 보수적이면 신뢰도 신호가 약화됩니다.", 5 );
		}
		return Helpers::result( 'nofollow_outbound', '외부 링크 nofollow', 'pass', "외부 링크 nofollow 비율 {$ratio}% ({$nofollow}/{$total}).", 5 );
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
