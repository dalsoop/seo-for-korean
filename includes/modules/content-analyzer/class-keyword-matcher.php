<?php
/**
 * Keyword matcher — particle-aware Korean keyword search with optional
 * morphology gateway routing.
 *
 * Extracted from Content_Analyzer so check classes can match without
 * carrying analyzer state. Keyword_Checks injects this; everyone else
 * ignores it.
 *
 * @package SEOForKorean
 */

declare( strict_types=1 );

namespace SEOForKorean\Modules\ContentAnalyzer;

use SEOForKorean\Morphology\Morphology_Client;

defined( 'ABSPATH' ) || exit;

final class Keyword_Matcher {

	private const PARTICLES = '을|를|이|가|은|는|에|에서|의|와|과|도|만|보다|에게|께|로|으로|로서|으로서|로써|으로써|만큼|처럼|같이|마저|조차|이나|나|이라도|라도|이라고|라고|이라며|라며';

	public function __construct( private ?Morphology_Client $morphology = null ) {}

	/**
	 * Count keyword occurrences in $text. Tries the morphology gateway when
	 * available; falls back to in-PHP regex on failure or absence.
	 *
	 * @return array{count: int, matches: list<string>}
	 */
	public function find( string $text, string $keyword ): array {
		if ( $keyword === '' || $text === '' ) {
			return [ 'count' => 0, 'matches' => [] ];
		}

		if ( $this->morphology !== null && $this->morphology->is_available() ) {
			$remote = $this->morphology->keyword_contains( $text, $keyword );
			if ( $remote !== null ) {
				return $remote;
			}
		}

		$regex = self::regex( $keyword );
		if ( $regex === '' ) {
			return [ 'count' => 0, 'matches' => [] ];
		}
		$count = preg_match_all( $regex, $text, $matches );
		$count = is_int( $count ) ? $count : 0;
		return [
			'count'   => $count,
			'matches' => array_values( array_map( 'strval', $matches[0] ?? [] ) ),
		];
	}

	private static function regex( string $keyword ): string {
		if ( $keyword === '' ) {
			return '';
		}
		return '/' . preg_quote( $keyword, '/' ) . '(?:' . self::PARTICLES . ')?/u';
	}
}
