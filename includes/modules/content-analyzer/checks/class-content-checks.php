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
		return [
			self::content_length( $ctx ),
			self::h2_count( $ctx ),
			self::subheading_distribution( $ctx ),
			self::has_lists( $ctx ),
			self::headings_hierarchy( $ctx ),
		];
	}

	/** @param array<string, mixed> $ctx */
	private static function headings_hierarchy( array $ctx ): array {
		preg_match_all( '/<h([1-6])\b[^>]*>/i', (string) $ctx['content_html'], $m );
		$levels = array_map( 'intval', (array) ( $m[1] ?? [] ) );
		if ( $levels === [] ) {
			return Helpers::result( 'headings_hierarchy', '헤딩 계층', 'na', '헤딩이 없습니다.', 5 );
		}
		$h1_count = count( array_filter( $levels, static fn ( $l ) => $l === 1 ) );
		if ( $h1_count > 1 ) {
			return Helpers::result( 'headings_hierarchy', '헤딩 계층', 'warning', "본문에 H1이 {$h1_count}개 있습니다. 보통 H1은 글 제목 1개만 사용합니다.", 5 );
		}
		for ( $i = 1; $i < count( $levels ); $i++ ) {
			if ( $levels[ $i ] > $levels[ $i - 1 ] && $levels[ $i ] - $levels[ $i - 1 ] > 1 ) {
				return Helpers::result( 'headings_hierarchy', '헤딩 계층', 'warning', "헤딩 단계가 건너뛰어졌습니다 (H{$levels[$i-1]} → H{$levels[$i]}). 접근성을 위해 단계별 사용 권장.", 5 );
			}
		}
		$total = count( $levels );
		return Helpers::result( 'headings_hierarchy', '헤딩 계층', 'pass', "헤딩 계층이 적절합니다 ({$total}개).", 5 );
	}

	/** @param array<string, mixed> $ctx */
	private static function subheading_distribution( array $ctx ): array {
		$len = (int) $ctx['content_length'];
		if ( $len < 600 ) {
			return Helpers::result( 'subheading_distribution', '헤딩 분포', 'na', '본문이 짧아 평가 생략.', 5 );
		}
		$h2 = preg_match_all( '/<h2\b[^>]*>/i', (string) $ctx['content_html'] );
		$h2 = is_int( $h2 ) ? $h2 : 0;
		if ( $h2 === 0 ) {
			return Helpers::result( 'subheading_distribution', '헤딩 분포', 'warning', '헤딩이 없습니다.', 5 );
		}
		$avg = intdiv( $len, $h2 );
		if ( $avg > 1500 ) {
			return Helpers::result( 'subheading_distribution', '헤딩 분포', 'warning', "H2 사이 본문이 너무 깁니다 (평균 {$avg}자). 헤딩을 더 추가하세요.", 5 );
		}
		return Helpers::result( 'subheading_distribution', '헤딩 분포', 'pass', "헤딩 분포가 적절합니다 (H2 사이 평균 {$avg}자).", 5 );
	}

	/** @param array<string, mixed> $ctx */
	private static function has_lists( array $ctx ): array {
		if ( (int) $ctx['content_length'] < 400 ) {
			return Helpers::result( 'has_lists', '리스트 사용', 'na', '본문이 짧아 평가 생략.', 5 );
		}
		if ( preg_match( '/<(ul|ol)\b/i', (string) $ctx['content_html'] ) === 1 ) {
			return Helpers::result( 'has_lists', '리스트 사용', 'pass', '본문에 리스트가 있습니다.', 5 );
		}
		return Helpers::result( 'has_lists', '리스트 사용', 'warning', '리스트(ul/ol)가 없습니다. 정보 정리에 활용해 보세요.', 5 );
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
