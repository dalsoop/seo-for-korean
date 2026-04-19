<?php
/**
 * Unit tests for the Content_Analyzer engine.
 *
 * Pure PHP, no WP runtime required. Anchors the scoring logic so future
 * threshold tweaks don't silently regress.
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Tests\Unit\Modules\ContentAnalyzer;

use PHPUnit\Framework\TestCase;
use SEOForKorean\Modules\ContentAnalyzer\Content_Analyzer;
use SEOForKorean\Morphology\Morphology_Client;

final class Content_Analyzer_Test extends TestCase {

	private Content_Analyzer $analyzer;

	protected function setUp(): void {
		// No morphology client — exercises the in-PHP regex path.
		$this->analyzer = new Content_Analyzer();
	}

	public function test_empty_post_yields_low_score(): void {
		$result = $this->analyzer->analyze( [] );
		self::assertLessThanOrEqual( 30, $result['score'], 'empty post should be poor' );
		self::assertSame( 'poor', $result['grade'] );
		self::assertCount( 24, $result['checks'] );
	}

	public function test_well_formed_korean_post_scores_at_least_good(): void {
		$content = '<h1>워드프레스 입문</h1>'
			. '<p>워드프레스는 가장 널리 쓰이는 콘텐츠 관리 시스템입니다. '
			. '워드프레스를 사용하면 누구나 쉽게 블로그나 웹사이트를 만들 수 있습니다. '
			. '오픈소스이며 무료로 사용할 수 있고 한국어 지원도 잘 되어 있습니다. '
			. '본 글에서는 워드프레스를 처음 접하는 분들을 위해 설치부터 '
			. '운영까지 단계별로 자세히 안내합니다.</p>'
			. '<h2>워드프레스의 장점</h2>'
			. '<p>오픈소스이며 자유롭게 커스터마이징할 수 있습니다. '
			. '수많은 테마와 플러그인이 있어 확장성이 매우 뛰어납니다. '
			. '한국어 자료도 풍부해서 학습 곡선이 완만합니다. '
			. '워드프레스의 활발한 커뮤니티가 큰 강점입니다.</p>'
			. '<h2>설치 방법</h2>'
			. '<p>호스팅 업체에서 원클릭 설치를 제공하거나 wordpress.org에서 직접 받을 수 있습니다. '
			. '워드프레스를 처음 다루는 분들은 원클릭 설치가 무난합니다. '
			. '설치 후에는 기본 테마를 활성화하고 필요한 플러그인을 추가합니다.</p>'
			. '<img src="dashboard.jpg" alt="워드프레스 대시보드" />'
			. '<p>대시보드에서 글 작성, 미디어 업로드, 댓글 관리 등 모든 작업을 할 수 있습니다. '
			. '워드프레스는 이 단순함과 확장성을 동시에 제공한다는 점에서 매력적입니다.</p>';

		$result = $this->analyzer->analyze(
			[
				'title'            => '워드프레스 입문 가이드: 한국어 블로그를 시작하는 가장 쉬운 방법',
				'content'          => $content,
				'slug'             => 'wordpress-guide-korean-blog',
				'focus_keyword'    => '워드프레스',
				'meta_description' => '워드프레스로 한국어 블로그를 처음 만드는 분들을 위한 입문 가이드. 호스팅 선택부터 테마 적용, 플러그인 설치까지 단계별로 자세히 설명합니다.',
			]
		);

		self::assertGreaterThanOrEqual( 65, $result['score'], 'well-formed post should be at least good' );
		self::assertContains( $result['grade'], [ 'good', 'great' ] );
	}

	public function test_score_includes_all_active_checks_in_grade(): void {
		$result = $this->analyzer->analyze( [ 'title' => '제목' ] );
		self::assertArrayHasKey( 'grade', $result );
		self::assertContains( $result['grade'], [ 'poor', 'needs_work', 'good', 'great' ] );
	}

	/* ----- Title length ----- */

	public function test_title_too_short_fails(): void {
		$check = $this->find_check( $this->analyzer->analyze( [ 'title' => '짧음' ] ), 'title_length' );
		self::assertSame( 'fail', $check['status'] );
	}

	public function test_title_in_ideal_range_passes(): void {
		$title = str_repeat( '가나다라마', 8 ); // 40 chars
		$check = $this->find_check( $this->analyzer->analyze( [ 'title' => $title ] ), 'title_length' );
		self::assertSame( 'pass', $check['status'] );
	}

	public function test_title_too_long_warns(): void {
		$title = str_repeat( '가', 80 );
		$check = $this->find_check( $this->analyzer->analyze( [ 'title' => $title ] ), 'title_length' );
		self::assertSame( 'warning', $check['status'] );
	}

	/* ----- Meta description ----- */

	public function test_empty_meta_description_warns(): void {
		$check = $this->find_check( $this->analyzer->analyze( [] ), 'meta_description_length' );
		self::assertSame( 'warning', $check['status'] );
	}

	public function test_ideal_meta_description_passes(): void {
		$desc  = str_repeat( '가나다라마', 20 ); // 100 chars
		$check = $this->find_check( $this->analyzer->analyze( [ 'meta_description' => $desc ] ), 'meta_description_length' );
		self::assertSame( 'pass', $check['status'] );
	}

	/* ----- Korean particle matching ----- */

	public function test_focus_keyword_matches_through_korean_particles(): void {
		$content = '<p>워드프레스를 사용하면 좋습니다. 워드프레스의 장점은 많습니다. 워드프레스가 제일 인기있습니다.</p>';
		$result  = $this->analyzer->analyze(
			[
				'title'         => '워드프레스 가이드',
				'content'       => $content,
				'focus_keyword' => '워드프레스',
			]
		);
		$check = $this->find_check( $result, 'focus_keyword_in_content' );
		self::assertSame( 'pass', $check['status'] );
		self::assertStringContainsString( '3회', $check['message'] );
	}

	public function test_focus_keyword_in_title_matches_with_particle(): void {
		$result = $this->analyzer->analyze(
			[
				'title'         => '워드프레스를 시작하는 방법',
				'focus_keyword' => '워드프레스',
			]
		);
		$check = $this->find_check( $result, 'focus_keyword_in_title' );
		self::assertSame( 'pass', $check['status'] );
	}

	public function test_focus_keyword_absent_from_title_fails(): void {
		$result = $this->analyzer->analyze(
			[
				'title'         => 'WordPress 가이드',
				'focus_keyword' => '드루팔',
			]
		);
		$check = $this->find_check( $result, 'focus_keyword_in_title' );
		self::assertSame( 'fail', $check['status'] );
	}

	public function test_focus_keyword_check_marked_na_when_keyword_empty(): void {
		$result = $this->analyzer->analyze( [ 'title' => '제목입니다 적당한 길이로' ] );
		$check  = $this->find_check( $result, 'focus_keyword_in_title' );
		self::assertSame( 'na', $check['status'] );
	}

	/* ----- Content length ----- */

	public function test_short_content_fails(): void {
		$result = $this->analyzer->analyze( [ 'content' => '<p>짧음</p>' ] );
		$check  = $this->find_check( $result, 'content_length' );
		self::assertSame( 'fail', $check['status'] );
	}

	public function test_adequate_content_passes(): void {
		$body   = str_repeat( '한국어 본문 충분합니다. ', 100 );
		$result = $this->analyzer->analyze( [ 'content' => "<p>{$body}</p>" ] );
		$check  = $this->find_check( $result, 'content_length' );
		self::assertSame( 'pass', $check['status'] );
	}

	/* ----- H2 count ----- */

	public function test_no_h2_warns(): void {
		$result = $this->analyzer->analyze( [ 'content' => '<p>본문만 있음</p>' ] );
		$check  = $this->find_check( $result, 'h2_count' );
		self::assertSame( 'warning', $check['status'] );
	}

	public function test_two_h2_passes(): void {
		$result = $this->analyzer->analyze(
			[ 'content' => '<h2>첫 헤딩</h2><p>본문</p><h2>둘째 헤딩</h2>' ]
		);
		$check = $this->find_check( $result, 'h2_count' );
		self::assertSame( 'pass', $check['status'] );
	}

	/* ----- Image alt coverage ----- */

	public function test_no_images_marked_na(): void {
		$result = $this->analyzer->analyze( [ 'content' => '<p>텍스트만</p>' ] );
		$check  = $this->find_check( $result, 'image_alt_coverage' );
		self::assertSame( 'na', $check['status'] );
	}

	public function test_image_without_alt_warns(): void {
		$result = $this->analyzer->analyze( [ 'content' => '<img src="x.jpg" />' ] );
		$check  = $this->find_check( $result, 'image_alt_coverage' );
		self::assertSame( 'warning', $check['status'] );
	}

	public function test_all_images_with_alt_pass(): void {
		$result = $this->analyzer->analyze(
			[ 'content' => '<img src="x.jpg" alt="설명" /><img src="y.jpg" alt="다른 설명" />' ]
		);
		$check = $this->find_check( $result, 'image_alt_coverage' );
		self::assertSame( 'pass', $check['status'] );
	}

	/* ----- Slug ----- */

	public function test_korean_slug_warns(): void {
		$result = $this->analyzer->analyze( [ 'slug' => '한국어-슬러그' ] );
		$check  = $this->find_check( $result, 'slug_quality' );
		self::assertSame( 'warning', $check['status'] );
	}

	public function test_ascii_slug_passes(): void {
		$result = $this->analyzer->analyze( [ 'slug' => 'wordpress-guide' ] );
		$check  = $this->find_check( $result, 'slug_quality' );
		self::assertSame( 'pass', $check['status'] );
	}

	/* ----- Morphology gateway integration ----- */

	public function test_morphology_client_overrides_local_count(): void {
		$stub = new class() extends Morphology_Client {
			public function is_available(): bool {
				return true;
			}
			public function keyword_contains( string $text, string $keyword ): ?array {
				// Pretend the gateway found 7 matches regardless of input.
				return [ 'count' => 7, 'matches' => array_fill( 0, 7, $keyword ) ];
			}
		};

		$analyzer = new Content_Analyzer( $stub );
		$result   = $analyzer->analyze(
			[
				'title'         => '워드프레스 가이드',
				'content'       => '<p>본문에 키워드 없음</p>',
				'focus_keyword' => '워드프레스',
			]
		);
		$check = $this->find_check( $result, 'focus_keyword_in_content' );
		self::assertSame( 'pass', $check['status'] );
		self::assertStringContainsString( '7회', $check['message'] );
	}

	public function test_morphology_client_null_response_falls_back_to_regex(): void {
		$stub = new class() extends Morphology_Client {
			public function is_available(): bool {
				return true;
			}
			public function keyword_contains( string $text, string $keyword ): ?array {
				// Simulate gateway error mid-call (network timeout, 500, etc).
				return null;
			}
		};

		$analyzer = new Content_Analyzer( $stub );
		$result   = $analyzer->analyze(
			[
				'title'         => '워드프레스 가이드',
				'content'       => '<p>워드프레스를 사용합니다. 워드프레스의 장점.</p>',
				'focus_keyword' => '워드프레스',
			]
		);
		$check = $this->find_check( $result, 'focus_keyword_in_content' );
		// Local regex still finds the two particle-suffixed occurrences.
		self::assertSame( 'pass', $check['status'] );
		self::assertStringContainsString( '2회', $check['message'] );
	}

	public function test_unavailable_morphology_client_skips_gateway_silently(): void {
		$stub = new class() extends Morphology_Client {
			public function is_available(): bool {
				return false;
			}
			public function keyword_contains( string $text, string $keyword ): ?array {
				self::fail( 'keyword_contains should not be called when client is unavailable' );
			}
		};

		$analyzer = new Content_Analyzer( $stub );
		$result   = $analyzer->analyze(
			[
				'title'         => '워드프레스 가이드',
				'content'       => '<p>워드프레스를 씁니다.</p>',
				'focus_keyword' => '워드프레스',
			]
		);
		$check = $this->find_check( $result, 'focus_keyword_in_title' );
		self::assertSame( 'pass', $check['status'] );
	}

	/* ----- Keyword distribution (new) ----- */

	public function test_keyword_density_excess_fails(): void {
		$content = '<p>' . str_repeat( '워드프레스 ', 20 ) . '</p>';
		$result  = $this->analyzer->analyze( [ 'content' => $content, 'focus_keyword' => '워드프레스' ] );
		$check   = $this->find_check( $result, 'keyword_density' );
		self::assertSame( 'fail', $check['status'] );
	}

	public function test_keyword_density_zero_fails_when_keyword_set(): void {
		$result = $this->analyzer->analyze(
			[
				'content'       => '<p>키워드가 전혀 없는 본문입니다. 매우 다양한 글이 적절히 들어 있습니다.</p>',
				'focus_keyword' => '워드프레스',
			]
		);
		$check = $this->find_check( $result, 'keyword_density' );
		self::assertSame( 'fail', $check['status'] );
	}

	public function test_keyword_in_meta_description_pass(): void {
		$result = $this->analyzer->analyze(
			[
				'focus_keyword'    => '워드프레스',
				'meta_description' => '이 글은 워드프레스 입문자를 위한 가이드입니다.',
			]
		);
		$check = $this->find_check( $result, 'keyword_in_meta_description' );
		self::assertSame( 'pass', $check['status'] );
	}

	public function test_keyword_in_h2_pass(): void {
		$result = $this->analyzer->analyze(
			[
				'content'       => '<h2>워드프레스 입문</h2><p>본문</p><h2>설치 방법</h2>',
				'focus_keyword' => '워드프레스',
			]
		);
		$check = $this->find_check( $result, 'keyword_in_h2' );
		self::assertSame( 'pass', $check['status'] );
	}

	public function test_keyword_in_slug_na_for_korean_keyword(): void {
		$result = $this->analyzer->analyze(
			[
				'slug'          => 'wordpress-guide',
				'focus_keyword' => '워드프레스',
			]
		);
		$check = $this->find_check( $result, 'keyword_in_slug' );
		self::assertSame( 'na', $check['status'] );
	}

	public function test_keyword_in_slug_pass_for_ascii(): void {
		$result = $this->analyzer->analyze(
			[
				'slug'          => 'wordpress-guide',
				'focus_keyword' => 'wordpress',
			]
		);
		$check = $this->find_check( $result, 'keyword_in_slug' );
		self::assertSame( 'pass', $check['status'] );
	}

	/* ----- Links (new) ----- */

	public function test_internal_and_outbound_links_counted(): void {
		$html   = '<p><a href="/about">about</a> <a href="https://example.com">ext</a> <a href="#anchor">a</a></p>';
		$result = $this->analyzer->analyze( [ 'content' => $html ] );
		$i      = $this->find_check( $result, 'internal_links' );
		$o      = $this->find_check( $result, 'outbound_links' );
		self::assertSame( 'pass', $i['status'] );
		self::assertStringContainsString( '1개', $i['message'] );
		self::assertSame( 'pass', $o['status'] );
		self::assertStringContainsString( '1개', $o['message'] );
	}

	public function test_no_links_warns_both(): void {
		$result = $this->analyzer->analyze( [ 'content' => '<p>링크 없음</p>' ] );
		self::assertSame( 'warning', $this->find_check( $result, 'internal_links' )['status'] );
		self::assertSame( 'warning', $this->find_check( $result, 'outbound_links' )['status'] );
	}

	/* ----- Readability (new) ----- */

	public function test_long_paragraph_warns(): void {
		$long   = str_repeat( '가', 600 );
		$result = $this->analyzer->analyze( [ 'content' => "<p>{$long}</p>" ] );
		$check  = $this->find_check( $result, 'paragraph_length' );
		self::assertSame( 'warning', $check['status'] );
	}

	public function test_short_paragraphs_pass(): void {
		$result = $this->analyzer->analyze( [ 'content' => '<p>짧은 문단입니다.</p><p>또 다른 짧은 문단.</p>' ] );
		$check  = $this->find_check( $result, 'paragraph_length' );
		self::assertSame( 'pass', $check['status'] );
	}

	/* ----- Korean readability (new — distinctive) ----- */

	public function test_ending_consistency_haeyo_passes(): void {
		$body   = str_repeat(
			'워드프레스는 정말 편리해요. 누구나 쉽게 쓸 수 있어요. 한국어 자료도 풍부해요. '
			. '설치도 어렵지 않아요. 처음 시작하기 좋아요. 그래서 추천해요. ',
			3
		);
		$result = $this->analyzer->analyze( [ 'content' => "<p>{$body}</p>" ] );
		$check  = $this->find_check( $result, 'ending_consistency' );
		self::assertSame( 'pass', $check['status'] );
		self::assertStringContainsString( '해요체', $check['message'] );
	}

	public function test_ending_consistency_mixed_fails(): void {
		$body   = str_repeat(
			'워드프레스는 편리해요. 누구나 쓸 수 있습니다. 한국어 자료도 풍부합니다. '
			. '설치는 쉬워요. 처음에는 좋아요. 추천합니다. 정말 좋습니다. ',
			3
		);
		$result = $this->analyzer->analyze( [ 'content' => "<p>{$body}</p>" ] );
		$check  = $this->find_check( $result, 'ending_consistency' );
		self::assertContains( $check['status'], [ 'warning', 'fail' ] );
	}

	public function test_transition_words_pass(): void {
		$body   = str_repeat(
			'워드프레스는 매우 인기있는 시스템입니다. 따라서 많은 사람들이 사용합니다. '
			. '그러나 처음에는 어려울 수 있습니다. 그래서 가이드가 필요합니다. '
			. '또한 플러그인이 풍부합니다. 즉 확장성이 좋습니다. ',
			2
		);
		$result = $this->analyzer->analyze( [ 'content' => "<p>{$body}</p>" ] );
		$check  = $this->find_check( $result, 'transition_words' );
		self::assertSame( 'pass', $check['status'] );
	}

	/* ----- Helpers ----- */

	/**
	 * @param array{checks: array<int, array{id: string, status: string, message: string}>} $result
	 * @return array{id: string, status: string, message: string}
	 */
	private function find_check( array $result, string $id ): array {
		foreach ( $result['checks'] as $check ) {
			if ( $check['id'] === $id ) {
				return $check;
			}
		}
		self::fail( "Check {$id} not found in result." );
	}
}
