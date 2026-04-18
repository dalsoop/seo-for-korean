<?php
/**
 * Content Analyzer — runs SEO checks against a post and returns a score
 * with per-check pass/warning/fail diagnostics.
 *
 * Pure logic, no WP hook side-effects. The module class is the REST surface;
 * this class is the engine. Designed to be unit-testable without WP loaded
 * (only depends on `wp_strip_all_tags` and `WP_HTML_Tag_Processor`, which
 * the REST entrypoint guarantees are present).
 *
 * Korean-aware:
 *   - Length thresholds in 글자 (mb_strlen), not English words.
 *   - Focus keyword matching tolerates common particles (을, 를, 이, 가, …)
 *     so "워드프레스" matches "워드프레스를". This is naive — V2 will defer
 *     to a real morphology service (lindera/mecab-ko) for accurate matching.
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\ContentAnalyzer;

defined( 'ABSPATH' ) || exit;

final class Content_Analyzer {

	/** Common Korean particles appended after nouns. Naive — V2 uses morphology. */
	private const PARTICLES = '을|를|이|가|은|는|에|에서|의|와|과|도|만|보다|에게|께|로|으로|로서|으로서|로써|으로써|만큼|처럼|같이|마저|조차|이나|나|이라도|라도|이라고|라고|이라며|라며';

	/**
	 * @param array<string, mixed> $input
	 * @return array{score: int, grade: string, checks: array<int, array{id: string, label: string, status: string, message: string, weight: int}>}
	 */
	public function analyze( array $input ): array {
		$ctx = $this->normalize( $input );

		$checks = [
			$this->check_title_length( $ctx ),
			$this->check_meta_description_length( $ctx ),
			$this->check_focus_keyword_present( $ctx ),
			$this->check_focus_keyword_in_title( $ctx ),
			$this->check_focus_keyword_in_first_paragraph( $ctx ),
			$this->check_focus_keyword_in_content( $ctx ),
			$this->check_content_length( $ctx ),
			$this->check_h2_count( $ctx ),
			$this->check_image_alt_coverage( $ctx ),
			$this->check_slug_quality( $ctx ),
		];

		$score = $this->compute_score( $checks );

		return [
			'score'  => $score,
			'grade'  => $this->grade( $score ),
			'checks' => $checks,
		];
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array{title: string, title_length: int, content_html: string, content_text: string, content_length: int, slug: string, focus_keyword: string, meta_description: string, meta_description_length: int}
	 */
	private function normalize( array $input ): array {
		$title         = trim( (string) ( $input['title'] ?? '' ) );
		$content_html  = (string) ( $input['content'] ?? '' );
		$content_text  = $this->strip_html( $content_html );
		$meta_desc     = trim( (string) ( $input['meta_description'] ?? '' ) );
		$focus_keyword = trim( (string) ( $input['focus_keyword'] ?? '' ) );
		$slug          = trim( (string) ( $input['slug'] ?? '' ) );

		return [
			'title'                   => $title,
			'title_length'            => mb_strlen( $title ),
			'content_html'            => $content_html,
			'content_text'            => $content_text,
			'content_length'          => mb_strlen( $content_text ),
			'slug'                    => $slug,
			'focus_keyword'           => $focus_keyword,
			'meta_description'        => $meta_desc,
			'meta_description_length' => mb_strlen( $meta_desc ),
		];
	}

	private function strip_html( string $html ): string {
		$text = function_exists( 'wp_strip_all_tags' )
			? wp_strip_all_tags( $html )
			: trim( strip_tags( $html ) );
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Build a regex that matches the focus keyword optionally followed by a
	 * Korean particle. Returns empty string if keyword is empty.
	 */
	private function keyword_regex( string $keyword ): string {
		if ( $keyword === '' ) {
			return '';
		}
		return '/' . preg_quote( $keyword, '/' ) . '(?:' . self::PARTICLES . ')?/u';
	}

	/* -------------------------------------------------------------------- */
	/* Individual checks                                                     */
	/* -------------------------------------------------------------------- */

	/** @param array<string, mixed> $ctx */
	private function check_title_length( array $ctx ): array {
		$len = (int) $ctx['title_length'];
		if ( $len === 0 ) {
			return $this->result( 'title_length', '제목 길이', 'fail', '제목이 비어 있습니다.', 10 );
		}
		if ( $len < 15 ) {
			return $this->result( 'title_length', '제목 길이', 'fail', "제목이 너무 짧습니다 ({$len}자). 최소 15자 권장.", 10 );
		}
		if ( $len > 70 ) {
			return $this->result( 'title_length', '제목 길이', 'warning', "제목이 너무 깁니다 ({$len}자). 검색 결과에서 잘릴 수 있습니다.", 10 );
		}
		if ( $len < 30 || $len > 60 ) {
			return $this->result( 'title_length', '제목 길이', 'warning', "제목 길이가 이상적이지 않습니다 ({$len}자). 30~60자 권장.", 10 );
		}
		return $this->result( 'title_length', '제목 길이', 'pass', "제목 길이가 적절합니다 ({$len}자).", 10 );
	}

	/** @param array<string, mixed> $ctx */
	private function check_meta_description_length( array $ctx ): array {
		$len = (int) $ctx['meta_description_length'];
		if ( $len === 0 ) {
			return $this->result( 'meta_description_length', '메타 설명', 'warning', '메타 설명이 비어 있습니다. 80~155자 권장.', 10 );
		}
		if ( $len < 40 ) {
			return $this->result( 'meta_description_length', '메타 설명', 'fail', "메타 설명이 너무 짧습니다 ({$len}자).", 10 );
		}
		if ( $len > 200 ) {
			return $this->result( 'meta_description_length', '메타 설명', 'warning', "메타 설명이 너무 깁니다 ({$len}자). 검색 결과에서 잘립니다.", 10 );
		}
		if ( $len < 80 || $len > 155 ) {
			return $this->result( 'meta_description_length', '메타 설명', 'warning', "메타 설명 길이가 이상적이지 않습니다 ({$len}자). 80~155자 권장.", 10 );
		}
		return $this->result( 'meta_description_length', '메타 설명', 'pass', "메타 설명이 적절합니다 ({$len}자).", 10 );
	}

	/** @param array<string, mixed> $ctx */
	private function check_focus_keyword_present( array $ctx ): array {
		if ( $ctx['focus_keyword'] === '' ) {
			return $this->result( 'focus_keyword_present', '포커스 키워드 설정', 'fail', '포커스 키워드를 입력해 주세요.', 5 );
		}
		return $this->result( 'focus_keyword_present', '포커스 키워드 설정', 'pass', "포커스 키워드: {$ctx['focus_keyword']}", 5 );
	}

	/** @param array<string, mixed> $ctx */
	private function check_focus_keyword_in_title( array $ctx ): array {
		$kw = (string) $ctx['focus_keyword'];
		if ( $kw === '' ) {
			return $this->result( 'focus_keyword_in_title', '제목에 포커스 키워드', 'na', '', 10 );
		}
		$regex = $this->keyword_regex( $kw );
		if ( $regex !== '' && preg_match( $regex, (string) $ctx['title'] ) === 1 ) {
			return $this->result( 'focus_keyword_in_title', '제목에 포커스 키워드', 'pass', '제목에 포커스 키워드가 포함되어 있습니다.', 10 );
		}
		return $this->result( 'focus_keyword_in_title', '제목에 포커스 키워드', 'fail', '제목에 포커스 키워드가 없습니다.', 10 );
	}

	/** @param array<string, mixed> $ctx */
	private function check_focus_keyword_in_first_paragraph( array $ctx ): array {
		$kw = (string) $ctx['focus_keyword'];
		if ( $kw === '' ) {
			return $this->result( 'focus_keyword_in_first_paragraph', '첫 단락에 포커스 키워드', 'na', '', 10 );
		}
		$first = mb_substr( (string) $ctx['content_text'], 0, 200 );
		$regex = $this->keyword_regex( $kw );
		if ( $regex !== '' && preg_match( $regex, $first ) === 1 ) {
			return $this->result( 'focus_keyword_in_first_paragraph', '첫 단락에 포커스 키워드', 'pass', '첫 단락에 포커스 키워드가 등장합니다.', 10 );
		}
		return $this->result( 'focus_keyword_in_first_paragraph', '첫 단락에 포커스 키워드', 'warning', '첫 200자 안에 포커스 키워드가 없습니다.', 10 );
	}

	/** @param array<string, mixed> $ctx */
	private function check_focus_keyword_in_content( array $ctx ): array {
		$kw = (string) $ctx['focus_keyword'];
		if ( $kw === '' ) {
			return $this->result( 'focus_keyword_in_content', '본문에 포커스 키워드', 'na', '', 10 );
		}
		$regex = $this->keyword_regex( $kw );
		if ( $regex === '' ) {
			return $this->result( 'focus_keyword_in_content', '본문에 포커스 키워드', 'na', '', 10 );
		}
		$count = preg_match_all( $regex, (string) $ctx['content_text'] ) ?: 0;
		if ( $count === 0 ) {
			return $this->result( 'focus_keyword_in_content', '본문에 포커스 키워드', 'fail', '본문에 포커스 키워드가 없습니다.', 10 );
		}
		if ( $count >= 2 ) {
			return $this->result( 'focus_keyword_in_content', '본문에 포커스 키워드', 'pass', "본문에 포커스 키워드가 {$count}회 등장합니다.", 10 );
		}
		return $this->result( 'focus_keyword_in_content', '본문에 포커스 키워드', 'warning', '본문에 포커스 키워드가 1회만 등장합니다.', 10 );
	}

	/** @param array<string, mixed> $ctx */
	private function check_content_length( array $ctx ): array {
		$len = (int) $ctx['content_length'];
		if ( $len < 100 ) {
			return $this->result( 'content_length', '본문 길이', 'fail', "본문이 너무 짧습니다 ({$len}자). 최소 600자 권장.", 10 );
		}
		if ( $len < 300 ) {
			return $this->result( 'content_length', '본문 길이', 'fail', "본문이 짧습니다 ({$len}자). 600자 이상 권장.", 10 );
		}
		if ( $len < 600 ) {
			return $this->result( 'content_length', '본문 길이', 'warning', "본문이 다소 짧습니다 ({$len}자). 600자 이상 권장.", 10 );
		}
		return $this->result( 'content_length', '본문 길이', 'pass', "본문 길이가 충분합니다 ({$len}자).", 10 );
	}

	/** @param array<string, mixed> $ctx */
	private function check_h2_count( array $ctx ): array {
		$count = preg_match_all( '/<h2\b[^>]*>/i', (string) $ctx['content_html'] ) ?: 0;
		if ( $count === 0 ) {
			return $this->result( 'h2_count', 'H2 헤딩', 'warning', 'H2 헤딩이 없습니다. 글이 길다면 2개 이상 추가하세요.', 5 );
		}
		if ( $count === 1 ) {
			return $this->result( 'h2_count', 'H2 헤딩', 'warning', "H2 헤딩이 {$count}개 있습니다. 본문이 길면 더 추가하세요.", 5 );
		}
		return $this->result( 'h2_count', 'H2 헤딩', 'pass', "H2 헤딩이 {$count}개로 적절합니다.", 5 );
	}

	/** @param array<string, mixed> $ctx */
	private function check_image_alt_coverage( array $ctx ): array {
		$html  = (string) $ctx['content_html'];
		$total = preg_match_all( '/<img\b[^>]*>/i', $html, $imgs ) ?: 0;
		if ( $total === 0 ) {
			return $this->result( 'image_alt_coverage', '이미지 alt', 'na', '본문에 이미지가 없습니다.', 5 );
		}
		$with_alt = 0;
		foreach ( (array) ( $imgs[0] ?? [] ) as $tag ) {
			if ( preg_match( '/\balt\s*=\s*"[^"]+"/i', (string) $tag ) === 1 ) {
				++$with_alt;
			}
		}
		if ( $with_alt === $total ) {
			return $this->result( 'image_alt_coverage', '이미지 alt', 'pass', "모든 이미지({$total}개)에 alt 속성이 있습니다.", 5 );
		}
		$missing = $total - $with_alt;
		return $this->result( 'image_alt_coverage', '이미지 alt', 'warning', "{$missing}개 이미지에 alt 속성이 없습니다 (총 {$total}개).", 5 );
	}

	/** @param array<string, mixed> $ctx */
	private function check_slug_quality( array $ctx ): array {
		$slug = (string) $ctx['slug'];
		if ( $slug === '' ) {
			return $this->result( 'slug_quality', '슬러그', 'warning', '슬러그가 비어 있습니다.', 5 );
		}
		if ( preg_match( '/[^\x00-\x7F]/u', $slug ) === 1 ) {
			return $this->result( 'slug_quality', '슬러그', 'warning', '슬러그에 비-ASCII 문자가 포함되어 있습니다. URL 가독성을 위해 영문 hyphen 권장.', 5 );
		}
		if ( strlen( $slug ) > 75 ) {
			return $this->result( 'slug_quality', '슬러그', 'warning', "슬러그가 너무 깁니다 ({$slug}). 75자 이하 권장.", 5 );
		}
		return $this->result( 'slug_quality', '슬러그', 'pass', '슬러그가 적절합니다.', 5 );
	}

	/* -------------------------------------------------------------------- */
	/* Helpers                                                                */
	/* -------------------------------------------------------------------- */

	/**
	 * @return array{id: string, label: string, status: string, message: string, weight: int}
	 */
	private function result( string $id, string $label, string $status, string $message, int $weight ): array {
		return [
			'id'      => $id,
			'label'   => $label,
			'status'  => $status,
			'message' => $message,
			'weight'  => $weight,
		];
	}

	/**
	 * @param array<int, array{status: string, weight: int}> $checks
	 */
	private function compute_score( array $checks ): int {
		$total  = 0;
		$earned = 0.0;
		foreach ( $checks as $c ) {
			if ( $c['status'] === 'na' ) {
				continue;
			}
			$total += $c['weight'];
			if ( $c['status'] === 'pass' ) {
				$earned += (float) $c['weight'];
			} elseif ( $c['status'] === 'warning' ) {
				$earned += (float) $c['weight'] * 0.5;
			}
		}
		return $total > 0 ? (int) round( $earned / $total * 100 ) : 0;
	}

	private function grade( int $score ): string {
		if ( $score >= 85 ) {
			return 'great';
		}
		if ( $score >= 65 ) {
			return 'good';
		}
		if ( $score >= 40 ) {
			return 'needs_work';
		}
		return 'poor';
	}
}
