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
use SEOForKorean\Modules\ContentAnalyzer\Keyword_Matcher;

defined( 'ABSPATH' ) || exit;

final class Title_Checks {

	/**
	 * @param array<string, mixed> $ctx
	 * @return list<array{id: string, label: string, status: string, message: string, weight: int}>
	 */
	public static function run( array $ctx, Keyword_Matcher $matcher ): array {
		return [
			self::title_length( $ctx ),
			self::title_keyword_position( $ctx, $matcher ),
			self::title_starts_with_keyword( $ctx ),
		];
	}

	/** @param array<string, mixed> $ctx */
	private static function title_starts_with_keyword( array $ctx ): array {
		$kw    = (string) $ctx['focus_keyword'];
		$title = (string) $ctx['title'];
		if ( $kw === '' || $title === '' ) {
			return Helpers::result( 'title_starts_with_keyword', '제목 시작 키워드', 'na', '', 5 );
		}
		if ( str_starts_with( mb_strtolower( $title ), mb_strtolower( $kw ) ) ) {
			return Helpers::result( 'title_starts_with_keyword', '제목 시작 키워드', 'pass', '제목이 키워드로 시작합니다.', 5 );
		}
		return Helpers::result( 'title_starts_with_keyword', '제목 시작 키워드', 'warning', '제목을 키워드로 시작하면 SEO에 더 효과적입니다.', 5 );
	}

	/** @param array<string, mixed> $ctx */
	private static function title_keyword_position( array $ctx, Keyword_Matcher $matcher ): array {
		$kw    = (string) $ctx['focus_keyword'];
		$title = (string) $ctx['title'];
		if ( $kw === '' || (int) $ctx['title_length'] === 0 ) {
			return Helpers::result( 'title_keyword_position', '제목 내 키워드 위치', 'na', '', 5 );
		}
		$matches = $matcher->find( $title, $kw );
		if ( $matches['count'] === 0 ) {
			return Helpers::result( 'title_keyword_position', '제목 내 키워드 위치', 'fail', '제목에 키워드가 없습니다.', 5 );
		}
		// Find first occurrence position (in characters, not bytes).
		$first_match = (string) ( $matches['matches'][0] ?? $kw );
		$byte_pos    = mb_strpos( $title, $first_match );
		if ( $byte_pos === false ) {
			$byte_pos = 0;
		}
		$percent = (int) round( $byte_pos / mb_strlen( $title ) * 100 );
		if ( $percent <= 30 ) {
			return Helpers::result( 'title_keyword_position', '제목 내 키워드 위치', 'pass', "키워드가 제목 앞부분 ({$percent}%)에 있습니다.", 5 );
		}
		return Helpers::result( 'title_keyword_position', '제목 내 키워드 위치', 'warning', "키워드가 제목의 {$percent}% 위치에 있습니다. 앞쪽으로 옮겨보세요.", 5 );
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
