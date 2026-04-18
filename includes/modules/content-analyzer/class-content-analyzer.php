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

use SEOForKorean\Morphology\Morphology_Client;

defined( 'ABSPATH' ) || exit;

final class Content_Analyzer {

	/** Common Korean particles appended after nouns. Naive — V2 uses morphology. */
	private const PARTICLES = '을|를|이|가|은|는|에|에서|의|와|과|도|만|보다|에게|께|로|으로|로서|으로서|로써|으로써|만큼|처럼|같이|마저|조차|이나|나|이라도|라도|이라고|라고|이라며|라며';

	public function __construct( private ?Morphology_Client $morphology = null ) {}

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
			$this->check_keyword_density( $ctx ),
			$this->check_keyword_in_meta_description( $ctx ),
			$this->check_keyword_in_h2( $ctx ),
			$this->check_keyword_in_slug( $ctx ),
			$this->check_content_length( $ctx ),
			$this->check_h2_count( $ctx ),
			$this->check_image_alt_coverage( $ctx ),
			$this->check_slug_quality( $ctx ),
			$this->check_internal_links( $ctx ),
			$this->check_outbound_links( $ctx ),
			$this->check_paragraph_length( $ctx ),
			$this->check_sentence_length( $ctx ),
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
			'link_counts'             => $this->count_links( $content_html ),
		];
	}

	/**
	 * @return array{internal: int, outbound: int}
	 */
	private function count_links( string $html ): array {
		$internal = 0;
		$outbound = 0;
		if ( preg_match_all( '/<a\s+[^>]*?href\s*=\s*"([^"]+)"/is', $html, $m ) ) {
			foreach ( $m[1] as $href ) {
				$href = trim( $href );
				if ( str_starts_with( $href, 'http://' ) || str_starts_with( $href, 'https://' ) || str_starts_with( $href, '//' ) ) {
					++$outbound;
				} elseif ( $href !== ''
					&& ! str_starts_with( $href, '#' )
					&& ! str_starts_with( $href, 'javascript:' )
					&& ! str_starts_with( $href, 'mailto:' )
					&& ! str_starts_with( $href, 'tel:' )
				) {
					++$internal;
				}
			}
		}
		return [ 'internal' => $internal, 'outbound' => $outbound ];
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

	/**
	 * Count keyword occurrences in $text. Tries the morphology gateway when
	 * available; falls back to in-PHP regex on failure or absence. Both paths
	 * agree for V1 (same algorithm) — the gateway hop exists so V2 morphology
	 * lands without changing the analyzer.
	 *
	 * @return array{count: int, matches: list<string>}
	 */
	private function find_keyword( string $text, string $keyword ): array {
		if ( $keyword === '' || $text === '' ) {
			return [ 'count' => 0, 'matches' => [] ];
		}

		if ( $this->morphology !== null && $this->morphology->is_available() ) {
			$remote = $this->morphology->keyword_contains( $text, $keyword );
			if ( $remote !== null ) {
				return $remote;
			}
		}

		$regex = $this->keyword_regex( $keyword );
		if ( $regex === '' ) {
			return [ 'count' => 0, 'matches' => [] ];
		}
		$count   = preg_match_all( $regex, $text, $matches );
		$count   = is_int( $count ) ? $count : 0;
		$matches = $matches[0] ?? [];

		return [
			'count'   => $count,
			'matches' => array_values( array_map( 'strval', $matches ) ),
		];
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
		$found = $this->find_keyword( (string) $ctx['title'], $kw );
		if ( $found['count'] > 0 ) {
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
		$found = $this->find_keyword( $first, $kw );
		if ( $found['count'] > 0 ) {
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
		$count = $this->find_keyword( (string) $ctx['content_text'], $kw )['count'];
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
	/* New checks: keyword distribution                                      */
	/* -------------------------------------------------------------------- */

	/** @param array<string, mixed> $ctx */
	private function check_keyword_density( array $ctx ): array {
		$kw = (string) $ctx['focus_keyword'];
		if ( $kw === '' ) {
			return $this->result( 'keyword_density', '키워드 밀도', 'na', '', 5 );
		}
		$len = (int) $ctx['content_length'];
		if ( $len === 0 ) {
			return $this->result( 'keyword_density', '키워드 밀도', 'na', '본문이 비어 있습니다.', 5 );
		}
		$count    = $this->find_keyword( (string) $ctx['content_text'], $kw )['count'];
		$kw_chars = mb_strlen( $kw );
		$density  = $count * $kw_chars / $len * 100.0;
		$d        = number_format( $density, 2 );
		if ( $count === 0 ) {
			return $this->result( 'keyword_density', '키워드 밀도', 'fail', '본문에 키워드가 없습니다.', 5 );
		}
		if ( $density > 4.0 ) {
			return $this->result( 'keyword_density', '키워드 밀도', 'fail', "키워드 밀도가 너무 높습니다 ({$d}%). 키워드 스터핑으로 보일 수 있습니다.", 5 );
		}
		if ( $density > 2.5 ) {
			return $this->result( 'keyword_density', '키워드 밀도', 'warning', "키워드 밀도가 다소 높습니다 ({$d}%). 0.5~2.5% 권장.", 5 );
		}
		if ( $density >= 0.5 ) {
			return $this->result( 'keyword_density', '키워드 밀도', 'pass', "키워드 밀도가 적절합니다 ({$d}%).", 5 );
		}
		return $this->result( 'keyword_density', '키워드 밀도', 'warning', "키워드 밀도가 낮습니다 ({$d}%). 0.5~2.5% 권장.", 5 );
	}

	/** @param array<string, mixed> $ctx */
	private function check_keyword_in_meta_description( array $ctx ): array {
		$kw = (string) $ctx['focus_keyword'];
		if ( $kw === '' ) {
			return $this->result( 'keyword_in_meta_description', '메타 설명에 키워드', 'na', '', 5 );
		}
		if ( (int) $ctx['meta_description_length'] === 0 ) {
			return $this->result( 'keyword_in_meta_description', '메타 설명에 키워드', 'warning', '메타 설명이 비어 있습니다.', 5 );
		}
		if ( $this->find_keyword( (string) $ctx['meta_description'], $kw )['count'] > 0 ) {
			return $this->result( 'keyword_in_meta_description', '메타 설명에 키워드', 'pass', '메타 설명에 키워드가 포함되어 있습니다.', 5 );
		}
		return $this->result( 'keyword_in_meta_description', '메타 설명에 키워드', 'warning', '메타 설명에 키워드가 없습니다.', 5 );
	}

	/** @param array<string, mixed> $ctx */
	private function check_keyword_in_h2( array $ctx ): array {
		$kw = (string) $ctx['focus_keyword'];
		if ( $kw === '' ) {
			return $this->result( 'keyword_in_h2', 'H2에 키워드', 'na', '', 5 );
		}
		preg_match_all( '/<h2\b[^>]*>(.*?)<\/h2>/is', (string) $ctx['content_html'], $m );
		$inners = (array) ( $m[1] ?? [] );
		if ( $inners === [] ) {
			return $this->result( 'keyword_in_h2', 'H2에 키워드', 'na', 'H2 헤딩이 없습니다.', 5 );
		}
		$with_kw = 0;
		foreach ( $inners as $inner ) {
			$plain = $this->strip_html( (string) $inner );
			if ( $this->find_keyword( $plain, $kw )['count'] > 0 ) {
				++$with_kw;
			}
		}
		if ( $with_kw > 0 ) {
			return $this->result( 'keyword_in_h2', 'H2에 키워드', 'pass', "{$with_kw}개 H2에 키워드가 포함되어 있습니다.", 5 );
		}
		return $this->result( 'keyword_in_h2', 'H2에 키워드', 'warning', '어떤 H2에도 키워드가 없습니다.', 5 );
	}

	/** @param array<string, mixed> $ctx */
	private function check_keyword_in_slug( array $ctx ): array {
		$kw = (string) $ctx['focus_keyword'];
		if ( $kw === '' ) {
			return $this->result( 'keyword_in_slug', '슬러그에 키워드', 'na', '', 5 );
		}
		$slug = (string) $ctx['slug'];
		if ( $slug === '' ) {
			return $this->result( 'keyword_in_slug', '슬러그에 키워드', 'warning', '슬러그가 비어 있습니다.', 5 );
		}
		if ( preg_match( '/[^\x00-\x7F]/u', $kw ) === 1 ) {
			return $this->result( 'keyword_in_slug', '슬러그에 키워드', 'na', '한국어 키워드는 영문 슬러그와 직접 비교가 어렵습니다.', 5 );
		}
		if ( str_contains( strtolower( $slug ), strtolower( $kw ) ) ) {
			return $this->result( 'keyword_in_slug', '슬러그에 키워드', 'pass', '슬러그에 키워드가 포함되어 있습니다.', 5 );
		}
		return $this->result( 'keyword_in_slug', '슬러그에 키워드', 'warning', '슬러그에 키워드가 포함되어 있지 않습니다.', 5 );
	}

	/* -------------------------------------------------------------------- */
	/* New checks: links                                                     */
	/* -------------------------------------------------------------------- */

	/** @param array<string, mixed> $ctx */
	private function check_internal_links( array $ctx ): array {
		$n = (int) ( $ctx['link_counts']['internal'] ?? 0 );
		if ( $n === 0 ) {
			return $this->result( 'internal_links', '내부 링크', 'warning', '내부 링크가 없습니다. 관련 글로 1개 이상 링크하세요.', 5 );
		}
		return $this->result( 'internal_links', '내부 링크', 'pass', "내부 링크 {$n}개.", 5 );
	}

	/** @param array<string, mixed> $ctx */
	private function check_outbound_links( array $ctx ): array {
		$n = (int) ( $ctx['link_counts']['outbound'] ?? 0 );
		if ( $n === 0 ) {
			return $this->result( 'outbound_links', '외부 링크', 'warning', '외부 링크가 없습니다. 권위 있는 출처로 1개 이상 링크하면 신뢰도가 올라갑니다.', 5 );
		}
		return $this->result( 'outbound_links', '외부 링크', 'pass', "외부 링크 {$n}개.", 5 );
	}

	/* -------------------------------------------------------------------- */
	/* New checks: readability                                               */
	/* -------------------------------------------------------------------- */

	/** @param array<string, mixed> $ctx */
	private function check_paragraph_length( array $ctx ): array {
		preg_match_all( '/<p\b[^>]*>(.*?)<\/p>/is', (string) $ctx['content_html'], $m );
		$lengths = [];
		foreach ( (array) ( $m[1] ?? [] ) as $inner ) {
			$plain = $this->strip_html( (string) $inner );
			$len   = mb_strlen( $plain );
			if ( $len > 0 ) {
				$lengths[] = $len;
			}
		}
		if ( $lengths === [] ) {
			return $this->result( 'paragraph_length', '문단 길이', 'na', '문단이 없습니다.', 5 );
		}
		$max      = max( $lengths );
		$too_long = count( array_filter( $lengths, static fn ( $l ) => $l > 500 ) );
		if ( $too_long > 0 ) {
			return $this->result( 'paragraph_length', '문단 길이', 'warning', "{$too_long}개 문단이 500자보다 깁니다 (최대 {$max}자). 가독성을 위해 분할하세요.", 5 );
		}
		return $this->result( 'paragraph_length', '문단 길이', 'pass', "문단 길이가 적절합니다 (최대 {$max}자).", 5 );
	}

	/** @param array<string, mixed> $ctx */
	private function check_sentence_length( array $ctx ): array {
		if ( (int) $ctx['content_length'] === 0 ) {
			return $this->result( 'sentence_length', '문장 길이', 'na', '', 5 );
		}
		$parts = preg_split( '/[.!?。?]+\s*/u', (string) $ctx['content_text'] );
		$parts = is_array( $parts ) ? $parts : [];
		$sentences = array_values( array_filter( array_map( 'trim', $parts ), static fn ( $s ) => $s !== '' ) );
		if ( $sentences === [] ) {
			return $this->result( 'sentence_length', '문장 길이', 'na', '', 5 );
		}
		$lengths = array_map( 'mb_strlen', $sentences );
		$avg     = (int) ( array_sum( $lengths ) / count( $lengths ) );
		$over    = count( array_filter( $lengths, static fn ( $l ) => $l > 80 ) );
		$total   = count( $sentences );
		if ( $over > intdiv( $total, 4 ) && $over > 0 ) {
			return $this->result( 'sentence_length', '문장 길이', 'warning', "긴 문장이 많습니다 ({$over}/{$total} 문장이 80자 초과). 평균 {$avg}자.", 5 );
		}
		return $this->result( 'sentence_length', '문장 길이', 'pass', "문장 길이가 적절합니다 (평균 {$avg}자, 총 {$total} 문장).", 5 );
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
