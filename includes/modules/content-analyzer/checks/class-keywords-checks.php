<?php
/**
 * Focus-keyword distribution checks. The biggest domain — anything that
 * asks "is the keyword here?" lives here.
 *
 * Only check class that needs the Keyword_Matcher (everyone else ignores
 * morphology entirely).
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\ContentAnalyzer\Checks;

use SEOForKorean\Modules\ContentAnalyzer\Helpers;
use SEOForKorean\Modules\ContentAnalyzer\Keyword_Matcher;

defined( 'ABSPATH' ) || exit;

final class Keywords_Checks {

	/**
	 * @param array<string, mixed> $ctx
	 * @return list<array{id: string, label: string, status: string, message: string, weight: int}>
	 */
	public static function run( array $ctx, Keyword_Matcher $matcher ): array {
		return [
			self::focus_keyword_present( $ctx ),
			self::focus_keyword_in_title( $ctx, $matcher ),
			self::focus_keyword_in_first_paragraph( $ctx, $matcher ),
			self::focus_keyword_in_content( $ctx, $matcher ),
			self::keyword_density( $ctx, $matcher ),
			self::keyword_in_meta_description( $ctx, $matcher ),
			self::keyword_in_h2( $ctx, $matcher ),
			self::keyword_in_slug( $ctx ),
		];
	}

	/** @param array<string, mixed> $ctx */
	private static function focus_keyword_present( array $ctx ): array {
		if ( (string) $ctx['focus_keyword'] === '' ) {
			return Helpers::result( 'focus_keyword_present', '포커스 키워드 설정', 'fail', '포커스 키워드를 입력해 주세요.', 5 );
		}
		return Helpers::result( 'focus_keyword_present', '포커스 키워드 설정', 'pass', "포커스 키워드: {$ctx['focus_keyword']}", 5 );
	}

	/** @param array<string, mixed> $ctx */
	private static function focus_keyword_in_title( array $ctx, Keyword_Matcher $m ): array {
		$kw = (string) $ctx['focus_keyword'];
		if ( $kw === '' ) {
			return Helpers::result( 'focus_keyword_in_title', '제목에 포커스 키워드', 'na', '', 10 );
		}
		if ( $m->find( (string) $ctx['title'], $kw )['count'] > 0 ) {
			return Helpers::result( 'focus_keyword_in_title', '제목에 포커스 키워드', 'pass', '제목에 포커스 키워드가 포함되어 있습니다.', 10 );
		}
		return Helpers::result( 'focus_keyword_in_title', '제목에 포커스 키워드', 'fail', '제목에 포커스 키워드가 없습니다.', 10 );
	}

	/** @param array<string, mixed> $ctx */
	private static function focus_keyword_in_first_paragraph( array $ctx, Keyword_Matcher $m ): array {
		$kw = (string) $ctx['focus_keyword'];
		if ( $kw === '' ) {
			return Helpers::result( 'focus_keyword_in_first_paragraph', '첫 단락에 포커스 키워드', 'na', '', 10 );
		}
		$first = mb_substr( (string) $ctx['content_text'], 0, 200 );
		if ( $m->find( $first, $kw )['count'] > 0 ) {
			return Helpers::result( 'focus_keyword_in_first_paragraph', '첫 단락에 포커스 키워드', 'pass', '첫 단락에 포커스 키워드가 등장합니다.', 10 );
		}
		return Helpers::result( 'focus_keyword_in_first_paragraph', '첫 단락에 포커스 키워드', 'warning', '첫 200자 안에 포커스 키워드가 없습니다.', 10 );
	}

	/** @param array<string, mixed> $ctx */
	private static function focus_keyword_in_content( array $ctx, Keyword_Matcher $m ): array {
		$kw = (string) $ctx['focus_keyword'];
		if ( $kw === '' ) {
			return Helpers::result( 'focus_keyword_in_content', '본문에 포커스 키워드', 'na', '', 10 );
		}
		$count = $m->find( (string) $ctx['content_text'], $kw )['count'];
		if ( $count === 0 ) {
			return Helpers::result( 'focus_keyword_in_content', '본문에 포커스 키워드', 'fail', '본문에 포커스 키워드가 없습니다.', 10 );
		}
		if ( $count >= 2 ) {
			return Helpers::result( 'focus_keyword_in_content', '본문에 포커스 키워드', 'pass', "본문에 포커스 키워드가 {$count}회 등장합니다.", 10 );
		}
		return Helpers::result( 'focus_keyword_in_content', '본문에 포커스 키워드', 'warning', '본문에 포커스 키워드가 1회만 등장합니다.', 10 );
	}

	/** @param array<string, mixed> $ctx */
	private static function keyword_density( array $ctx, Keyword_Matcher $m ): array {
		$kw = (string) $ctx['focus_keyword'];
		if ( $kw === '' ) {
			return Helpers::result( 'keyword_density', '키워드 밀도', 'na', '', 5 );
		}
		$len = (int) $ctx['content_length'];
		if ( $len === 0 ) {
			return Helpers::result( 'keyword_density', '키워드 밀도', 'na', '본문이 비어 있습니다.', 5 );
		}
		$count    = $m->find( (string) $ctx['content_text'], $kw )['count'];
		$kw_chars = mb_strlen( $kw );
		$density  = $count * $kw_chars / $len * 100.0;
		$d        = number_format( $density, 2 );
		if ( $count === 0 ) {
			return Helpers::result( 'keyword_density', '키워드 밀도', 'fail', '본문에 키워드가 없습니다.', 5 );
		}
		if ( $density > 4.0 ) {
			return Helpers::result( 'keyword_density', '키워드 밀도', 'fail', "키워드 밀도가 너무 높습니다 ({$d}%). 키워드 스터핑으로 보일 수 있습니다.", 5 );
		}
		if ( $density > 2.5 ) {
			return Helpers::result( 'keyword_density', '키워드 밀도', 'warning', "키워드 밀도가 다소 높습니다 ({$d}%). 0.5~2.5% 권장.", 5 );
		}
		if ( $density >= 0.5 ) {
			return Helpers::result( 'keyword_density', '키워드 밀도', 'pass', "키워드 밀도가 적절합니다 ({$d}%).", 5 );
		}
		return Helpers::result( 'keyword_density', '키워드 밀도', 'warning', "키워드 밀도가 낮습니다 ({$d}%). 0.5~2.5% 권장.", 5 );
	}

	/** @param array<string, mixed> $ctx */
	private static function keyword_in_meta_description( array $ctx, Keyword_Matcher $m ): array {
		$kw = (string) $ctx['focus_keyword'];
		if ( $kw === '' ) {
			return Helpers::result( 'keyword_in_meta_description', '메타 설명에 키워드', 'na', '', 5 );
		}
		if ( (int) $ctx['meta_description_length'] === 0 ) {
			return Helpers::result( 'keyword_in_meta_description', '메타 설명에 키워드', 'warning', '메타 설명이 비어 있습니다.', 5 );
		}
		if ( $m->find( (string) $ctx['meta_description'], $kw )['count'] > 0 ) {
			return Helpers::result( 'keyword_in_meta_description', '메타 설명에 키워드', 'pass', '메타 설명에 키워드가 포함되어 있습니다.', 5 );
		}
		return Helpers::result( 'keyword_in_meta_description', '메타 설명에 키워드', 'warning', '메타 설명에 키워드가 없습니다.', 5 );
	}

	/** @param array<string, mixed> $ctx */
	private static function keyword_in_h2( array $ctx, Keyword_Matcher $m ): array {
		$kw = (string) $ctx['focus_keyword'];
		if ( $kw === '' ) {
			return Helpers::result( 'keyword_in_h2', 'H2에 키워드', 'na', '', 5 );
		}
		preg_match_all( '/<h2\b[^>]*>(.*?)<\/h2>/is', (string) $ctx['content_html'], $matches );
		$inners = (array) ( $matches[1] ?? [] );
		if ( $inners === [] ) {
			return Helpers::result( 'keyword_in_h2', 'H2에 키워드', 'na', 'H2 헤딩이 없습니다.', 5 );
		}
		$with_kw = 0;
		foreach ( $inners as $inner ) {
			$plain = Helpers::strip_html( (string) $inner );
			if ( $m->find( $plain, $kw )['count'] > 0 ) {
				++$with_kw;
			}
		}
		if ( $with_kw > 0 ) {
			return Helpers::result( 'keyword_in_h2', 'H2에 키워드', 'pass', "{$with_kw}개 H2에 키워드가 포함되어 있습니다.", 5 );
		}
		return Helpers::result( 'keyword_in_h2', 'H2에 키워드', 'warning', '어떤 H2에도 키워드가 없습니다.', 5 );
	}

	/** @param array<string, mixed> $ctx */
	private static function keyword_in_slug( array $ctx ): array {
		$kw = (string) $ctx['focus_keyword'];
		if ( $kw === '' ) {
			return Helpers::result( 'keyword_in_slug', '슬러그에 키워드', 'na', '', 5 );
		}
		$slug = (string) $ctx['slug'];
		if ( $slug === '' ) {
			return Helpers::result( 'keyword_in_slug', '슬러그에 키워드', 'warning', '슬러그가 비어 있습니다.', 5 );
		}
		if ( preg_match( '/[^\x00-\x7F]/u', $kw ) === 1 ) {
			return Helpers::result( 'keyword_in_slug', '슬러그에 키워드', 'na', '한국어 키워드는 영문 슬러그와 직접 비교가 어렵습니다.', 5 );
		}
		if ( str_contains( strtolower( $slug ), strtolower( $kw ) ) ) {
			return Helpers::result( 'keyword_in_slug', '슬러그에 키워드', 'pass', '슬러그에 키워드가 포함되어 있습니다.', 5 );
		}
		return Helpers::result( 'keyword_in_slug', '슬러그에 키워드', 'warning', '슬러그에 키워드가 포함되어 있지 않습니다.', 5 );
	}
}
