<?php
/**
 * Content_Analyzer — orchestrates the per-domain check classes.
 *
 * Stays thin on purpose. Building the analysis context, running every
 * check domain in turn, and computing the final score are this class's
 * only jobs. Each check is owned by a class under
 * `SEOForKorean\Modules\ContentAnalyzer\Checks\*` so adding a new SEO
 * dimension means dropping a method into the right domain (or adding a
 * new domain class) without ever editing this file.
 *
 * Mirrors `src/analyzer/mod.rs` in the gateway repo. The two
 * implementations MUST agree until features make divergence intentional.
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\ContentAnalyzer;

use SEOForKorean\Modules\ContentAnalyzer\Checks\Content_Checks;
use SEOForKorean\Modules\ContentAnalyzer\Checks\Images_Checks;
use SEOForKorean\Modules\ContentAnalyzer\Checks\Keywords_Checks;
use SEOForKorean\Modules\ContentAnalyzer\Checks\Links_Checks;
use SEOForKorean\Modules\ContentAnalyzer\Checks\Meta_Checks;
use SEOForKorean\Modules\ContentAnalyzer\Checks\Readability_Checks;
use SEOForKorean\Modules\ContentAnalyzer\Checks\Slug_Checks;
use SEOForKorean\Modules\ContentAnalyzer\Checks\Title_Checks;
use SEOForKorean\Morphology\Morphology_Client;

defined( 'ABSPATH' ) || exit;

final class Content_Analyzer {

	private Keyword_Matcher $matcher;

	public function __construct( ?Morphology_Client $morphology = null ) {
		$this->matcher = new Keyword_Matcher( $morphology );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array{score: int, grade: string, checks: array<int, array{id: string, label: string, status: string, message: string, weight: int}>}
	 */
	public function analyze( array $input ): array {
		$ctx = $this->normalize( $input );

		$checks = array_merge(
			Title_Checks::run( $ctx, $this->matcher ),
			Meta_Checks::run( $ctx ),
			Keywords_Checks::run( $ctx, $this->matcher ),
			Content_Checks::run( $ctx ),
			Images_Checks::run( $ctx ),
			Slug_Checks::run( $ctx ),
			Links_Checks::run( $ctx ),
			Readability_Checks::run( $ctx ),
		);

		$score = $this->compute_score( $checks );

		return [
			'score'  => $score,
			'grade'  => $this->grade( $score ),
			'checks' => $checks,
		];
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	private function normalize( array $input ): array {
		$title         = trim( (string) ( $input['title'] ?? '' ) );
		$content_html  = (string) ( $input['content'] ?? '' );
		$content_text  = Helpers::strip_html( $content_html );
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
			'link_counts'             => Helpers::count_links( $content_html ),
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
