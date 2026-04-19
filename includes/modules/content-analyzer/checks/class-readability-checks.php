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
	private const TRANSITIONS = '그러나|그렇지만|하지만|반면|한편|따라서|그러므로|그래서|결국|결과적으로|예를\s?들어|구체적으로|말하자면|가령|또한|게다가|더불어|더욱이|즉|다시\s?말해|요컨대|우선|먼저|다음으로|마지막으로|끝으로|물론|사실|참고로|반대로|오히려|특히|즉시';
	private const HAEYO       = '해요|예요|이에요|에요|네요|어요|아요|거예요|이지요|지요|나요|ㄴ가요|는가요';
	private const HAPSYO      = '합니다|입니다|습니다|됩니다|갑니다|옵니다|합니까|입니까|습니까|됩니까|십시오';

	public static function run( array $ctx ): array {
		return [
			self::paragraph_length( $ctx ),
			self::sentence_length( $ctx ),
			self::transition_words( $ctx ),
			self::ending_consistency( $ctx ),
		];
	}

	/** @param array<string, mixed> $ctx */
	private static function transition_words( array $ctx ): array {
		$len = (int) $ctx['content_length'];
		if ( $len < 200 ) {
			return Helpers::result( 'transition_words', '접속어 사용', 'na', '본문이 짧아 평가 생략.', 5 );
		}
		$pattern = '/(?:^|[\s,()\[\]。.!?])(' . self::TRANSITIONS . ')/u';
		$count   = preg_match_all( $pattern, (string) $ctx['content_text'] );
		$count   = is_int( $count ) ? $count : 0;

		$parts     = preg_split( '/[.!?。?]+\s*/u', (string) $ctx['content_text'] ) ?: [];
		$sentences = count( array_filter( array_map( 'trim', $parts ), static fn ( $s ) => $s !== '' ) );
		if ( $sentences === 0 ) {
			return Helpers::result( 'transition_words', '접속어 사용', 'na', '', 5 );
		}
		$ratio = (int) round( $count / $sentences * 100 );
		if ( $count === 0 ) {
			return Helpers::result( 'transition_words', '접속어 사용', 'warning', '접속어(그러나/따라서/즉 등)가 없습니다. 글의 흐름을 매끄럽게 해보세요.', 5 );
		}
		if ( $ratio < 5 ) {
			return Helpers::result( 'transition_words', '접속어 사용', 'warning', "접속어가 적습니다 ({$count}회, 문장의 {$ratio}%). 더 추가하면 가독성이 좋아집니다.", 5 );
		}
		return Helpers::result( 'transition_words', '접속어 사용', 'pass', "접속어를 잘 사용했습니다 ({$count}회, 문장의 {$ratio}%).", 5 );
	}

	/** @param array<string, mixed> $ctx */
	private static function ending_consistency( array $ctx ): array {
		$len = (int) $ctx['content_length'];
		if ( $len < 200 ) {
			return Helpers::result( 'ending_consistency', '어미 일관성', 'na', '본문이 짧아 평가 생략.', 5 );
		}
		$text   = (string) $ctx['content_text'];
		$haeyo  = preg_match_all( '/(?:' . self::HAEYO . ')[\s.!?。]/u', $text );
		$hapsyo = preg_match_all( '/(?:' . self::HAPSYO . ')[\s.!?。]/u', $text );
		$haeyo  = is_int( $haeyo ) ? $haeyo : 0;
		$hapsyo = is_int( $hapsyo ) ? $hapsyo : 0;
		$total  = $haeyo + $hapsyo;
		if ( $total < 3 ) {
			return Helpers::result( 'ending_consistency', '어미 일관성', 'na', '어미가 적어 평가 생략.', 5 );
		}
		$dominant    = max( $haeyo, $hapsyo );
		$minor       = min( $haeyo, $hapsyo );
		$consistency = (int) round( $dominant / $total * 100 );
		$style       = $haeyo >= $hapsyo ? '해요체' : '합쇼체';

		if ( $consistency >= 90 ) {
			return Helpers::result( 'ending_consistency', '어미 일관성', 'pass', "{$style} 일관됨 ({$consistency}%).", 5 );
		}
		if ( $consistency >= 70 ) {
			return Helpers::result( 'ending_consistency', '어미 일관성', 'warning', "{$style} 위주이나 다른 어미 {$minor}회 섞여 있습니다 ({$consistency}%).", 5 );
		}
		return Helpers::result( 'ending_consistency', '어미 일관성', 'fail', "해요체({$haeyo})와 합쇼체({$hapsyo}) 혼용. 일관된 어조 권장.", 5 );
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
