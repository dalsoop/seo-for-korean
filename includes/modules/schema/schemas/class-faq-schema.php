<?php
/**
 * FAQPage schema. Auto-detected from heading-then-paragraph patterns.
 *
 * Heuristics that flag a heading as a question:
 *   - Ends with '?' or '？'
 *   - Starts with 'Q:', 'Q.', '질문:', '질문.'
 *
 * For each matched question, the immediately following <p> becomes the
 * answer. Skipped silently on posts without any matches — no false-
 * positive markup.
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\Schema\Schemas;

defined( 'ABSPATH' ) || exit;

final class FAQ_Schema {

	public static function applies(): bool {
		if ( ! is_singular() ) {
			return false;
		}
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}
		return self::extract( $post->post_content ) !== [];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function build(): array {
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return [];
		}

		$faqs = self::extract( $post->post_content );
		if ( $faqs === [] ) {
			return [];
		}

		$entities = [];
		foreach ( $faqs as $faq ) {
			$entities[] = [
				'@type'          => 'Question',
				'name'           => $faq['question'],
				'acceptedAnswer' => [
					'@type' => 'Answer',
					'text'  => $faq['answer'],
				],
			];
		}

		return [
			'@type'      => 'FAQPage',
			'@id'        => (string) get_permalink( $post ) . '#faq',
			'mainEntity' => $entities,
		];
	}

	/**
	 * @return list<array{question: string, answer: string}>
	 */
	private static function extract( string $html ): array {
		$faqs = [];
		preg_match_all(
			'/<h([2-4])[^>]*>(.*?)<\/h\1>\s*<p[^>]*>(.*?)<\/p>/is',
			$html,
			$matches,
			PREG_SET_ORDER
		);
		foreach ( $matches as $m ) {
			$question = trim( wp_strip_all_tags( (string) $m[2] ) );
			$answer   = trim( wp_strip_all_tags( (string) $m[3] ) );
			if ( $question === '' || $answer === '' ) {
				continue;
			}
			if ( self::is_question( $question ) ) {
				$faqs[] = [ 'question' => $question, 'answer' => $answer ];
			}
		}
		return $faqs;
	}

	private static function is_question( string $heading ): bool {
		$t = trim( $heading );
		if ( str_ends_with( $t, '?' ) || str_ends_with( $t, '？' ) ) {
			return true;
		}
		if ( preg_match( '/^(Q\s*[\.:]\s*|질문\s*[\.:]\s*|문의\s*[\.:]\s*)/u', $t ) === 1 ) {
			return true;
		}
		return false;
	}
}
