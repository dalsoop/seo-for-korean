<?php
/**
 * Korean readability heuristics. Paragraph + sentence length today.
 * Future: transition words (그러나/따라서/한편), ending consistency
 * (해요체/합쇼체 mixing), passive voice detection.
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\ContentAnalyzer\Checks;

use SEOForKorean\Modules\ContentAnalyzer\Helpers;

defined( 'ABSPATH' ) || exit;

final class Readability_Checks {

	/**
	 * @param array<string, mixed> $ctx
	 * @return list<array{id: string, label: string, status: string, message: string, weight: int}>
	 */
	public static function run( array $ctx ): array {
		return [ self::paragraph_length( $ctx ), self::sentence_length( $ctx ) ];
	}

	/** @param array<string, mixed> $ctx */
	private static function paragraph_length( array $ctx ): array {
		preg_match_all( '/<p\b[^>]*>(.*?)<\/p>/is', (string) $ctx['content_html'], $matches );
		$lengths = [];
		foreach ( (array) ( $matches[1] ?? [] ) as $inner ) {
			$plain = Helpers::strip_html( (string) $inner );
			$len   = mb_strlen( $plain );
			if ( $len > 0 ) {
				$lengths[] = $len;
			}
		}
		if ( $lengths === [] ) {
			return Helpers::result( 'paragraph_length', '문단 길이', 'na', '문단이 없습니다.', 5 );
		}
		$max      = max( $lengths );
		$too_long = count( array_filter( $lengths, static fn ( $l ) => $l > 500 ) );
		if ( $too_long > 0 ) {
			return Helpers::result( 'paragraph_length', '문단 길이', 'warning', "{$too_long}개 문단이 500자보다 깁니다 (최대 {$max}자). 가독성을 위해 분할하세요.", 5 );
		}
		return Helpers::result( 'paragraph_length', '문단 길이', 'pass', "문단 길이가 적절합니다 (최대 {$max}자).", 5 );
	}

	/** @param array<string, mixed> $ctx */
	private static function sentence_length( array $ctx ): array {
		if ( (int) $ctx['content_length'] === 0 ) {
			return Helpers::result( 'sentence_length', '문장 길이', 'na', '', 5 );
		}
		$parts = preg_split( '/[.!?。?]+\s*/u', (string) $ctx['content_text'] );
		$parts = is_array( $parts ) ? $parts : [];
		$sentences = array_values( array_filter( array_map( 'trim', $parts ), static fn ( $s ) => $s !== '' ) );
		if ( $sentences === [] ) {
			return Helpers::result( 'sentence_length', '문장 길이', 'na', '', 5 );
		}
		$lengths = array_map( 'mb_strlen', $sentences );
		$avg     = (int) ( array_sum( $lengths ) / count( $lengths ) );
		$over    = count( array_filter( $lengths, static fn ( $l ) => $l > 80 ) );
		$total   = count( $sentences );
		if ( $over > intdiv( $total, 4 ) && $over > 0 ) {
			return Helpers::result( 'sentence_length', '문장 길이', 'warning', "긴 문장이 많습니다 ({$over}/{$total} 문장이 80자 초과). 평균 {$avg}자.", 5 );
		}
		return Helpers::result( 'sentence_length', '문장 길이', 'pass', "문장 길이가 적절합니다 (평균 {$avg}자, 총 {$total} 문장).", 5 );
	}
}
