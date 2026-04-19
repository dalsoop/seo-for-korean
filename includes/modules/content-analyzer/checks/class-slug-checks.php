<?php
/**
 * Slug checks. Quality + length. Future: dash patterns, stop words.
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\ContentAnalyzer\Checks;

use SEOForKorean\Modules\ContentAnalyzer\Helpers;

defined( 'ABSPATH' ) || exit;

final class Slug_Checks {

	/**
	 * @param array<string, mixed> $ctx
	 * @return list<array{id: string, label: string, status: string, message: string, weight: int}>
	 */
	public static function run( array $ctx ): array {
		return [
			self::slug_quality( $ctx ),
			self::slug_uses_dashes( $ctx ),
			self::slug_length( $ctx ),
		];
	}

	/** @param array<string, mixed> $ctx */
	private static function slug_length( array $ctx ): array {
		$slug = (string) $ctx['slug'];
		if ( $slug === '' ) {
			return Helpers::result( 'slug_length', '슬러그 길이', 'na', '', 5 );
		}
		$len = strlen( $slug );
		if ( $len > 75 ) {
			return Helpers::result( 'slug_length', '슬러그 길이', 'fail', "슬러그가 너무 깁니다 ({$len}자). 75자 이하 권장.", 5 );
		}
		if ( $len > 50 ) {
			return Helpers::result( 'slug_length', '슬러그 길이', 'warning', "슬러그가 다소 깁니다 ({$len}자). 50자 이하가 이상적입니다.", 5 );
		}
		if ( $len < 3 ) {
			return Helpers::result( 'slug_length', '슬러그 길이', 'warning', "슬러그가 너무 짧습니다 ({$len}자).", 5 );
		}
		return Helpers::result( 'slug_length', '슬러그 길이', 'pass', "슬러그 길이가 적절합니다 ({$len}자).", 5 );
	}

	/** @param array<string, mixed> $ctx */
	private static function slug_uses_dashes( array $ctx ): array {
		$slug = (string) $ctx['slug'];
		if ( $slug === '' ) {
			return Helpers::result( 'slug_uses_dashes', '슬러그 구분자', 'na', '', 5 );
		}
		if ( str_contains( $slug, '_' ) ) {
			return Helpers::result( 'slug_uses_dashes', '슬러그 구분자', 'warning', '슬러그에 언더스코어(_)가 있습니다. 검색엔진은 하이픈(-)을 권장합니다.', 5 );
		}
		return Helpers::result( 'slug_uses_dashes', '슬러그 구분자', 'pass', '슬러그 구분자가 적절합니다.', 5 );
	}

	/** @param array<string, mixed> $ctx */
	private static function slug_quality( array $ctx ): array {
		$slug = (string) $ctx['slug'];
		if ( $slug === '' ) {
			return Helpers::result( 'slug_quality', '슬러그', 'warning', '슬러그가 비어 있습니다.', 5 );
		}
		if ( preg_match( '/[^\x00-\x7F]/u', $slug ) === 1 ) {
			return Helpers::result( 'slug_quality', '슬러그', 'warning', '슬러그에 비-ASCII 문자가 포함되어 있습니다. URL 가독성을 위해 영문 hyphen 권장.', 5 );
		}
		if ( strlen( $slug ) > 75 ) {
			return Helpers::result( 'slug_quality', '슬러그', 'warning', "슬러그가 너무 깁니다 ({$slug}). 75자 이하 권장.", 5 );
		}
		return Helpers::result( 'slug_quality', '슬러그', 'pass', '슬러그가 적절합니다.', 5 );
	}
}
