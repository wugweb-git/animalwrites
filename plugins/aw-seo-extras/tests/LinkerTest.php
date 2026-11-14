<?php
/**
 * PHPUnit tests for the auto-linker (includes/class-aw-linker.php): sample HTML in, expected HTML out. No WordPress.
 *
 * With PHPUnit:    phpunit plugins/aw-seo-extras/tests/LinkerTest.php
 * Without PHPUnit: php plugins/aw-seo-extras/tests/phpunit-lite.php (the Deploy check runs this; it loads a small
 *                  stand-in for PHPUnit\Framework\TestCase and runs every test* method here).
 *
 * @package aw-seo-extras
 */

// phpcs:disable WordPress.Files.FileName, WordPress.NamingConventions.ValidFunctionName, Squiz.Commenting.FunctionComment.Missing -- PHPUnit naming.

if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) {
	exit;
}

require_once dirname( __DIR__ ) . '/includes/class-aw-linker.php';

/** The linker. */
final class LinkerTest extends PHPUnit\Framework\TestCase {

	/** Addresses of the sample targets. */
	const URLS = array(
		1  => 'https://example.org/one/',
		2  => 'https://example.org/stray-dogs/',
		3  => 'https://example.org/dogs/',
		4  => 'https://example.org/cruelty-act/',
		5  => 'https://example.org/gaushala/',
		10 => 'https://example.org/self/',
	);

	/**
	 * An index from keyword => target ids (best first).
	 *
	 * @param array $map Keyword => target id or ids.
	 */
	private function index( array $map ): array {
		$out = array();
		foreach ( $map as $kw => $ids ) {
			foreach ( (array) $ids as $p => $id ) {
				$out[ AW_Linker::normalize( $kw ) ][] = array( $id, 100 - $p, $kw );
			}
		}
		return $out;
	}

	private function run_linker( string $html, array $map, array $opt = array() ): array {
		return AW_Linker::link( $html, $this->index( $map ), fn( int $id ) => self::URLS[ $id ] ?? '', $opt + array( 'home' => 'https://example.org' ) );
	}

	private function a( int $id, string $text ): string {
		return '<a href="' . self::URLS[ $id ] . '" class="aw-auto-link">' . $text . '</a>';
	}

	public function test_links_a_keyword_in_a_paragraph(): void {
		$r = $this->run_linker( '<p>Help stray dogs in winter.</p>', array( 'stray dogs' => 2 ) );
		$this->assertSame( '<p>Help ' . $this->a( 2, 'stray dogs' ) . ' in winter.</p>', $r['html'] );
		$this->assertCount( 1, $r['links'] );
		$this->assertSame( 'stray dogs', $r['links'][0]['anchor'] );
	}

	public function test_case_insensitive_keeps_the_text_as_written(): void {
		$r = $this->run_linker( '<p>STRAY Dogs need food.</p>', array( 'stray dogs' => 2 ) );
		$this->assertSame( '<p>' . $this->a( 2, 'STRAY Dogs' ) . ' need food.</p>', $r['html'] );
	}

	public function test_longest_keyword_first(): void {
		$r = $this->run_linker(
			'<p>Feed stray dogs daily.</p>',
			array(
				'dogs'       => 3,
				'stray dogs' => 2,
			)
		);
		$this->assertSame( '<p>Feed ' . $this->a( 2, 'stray dogs' ) . ' daily.</p>', $r['html'] );
	}

	public function test_whole_words_only(): void {
		$html = '<p>Hotdogs and dogsled races; underdogs.</p>';
		$r    = $this->run_linker( $html, array( 'dogs' => 3 ) );
		$this->assertSame( $html, $r['html'] );
		$this->assertSame( array(), $r['links'] );
	}

	public function test_words_split_by_punctuation_are_not_a_keyword(): void {
		$html = '<p>Stray, dogs.</p>';
		$this->assertSame( $html, $this->run_linker( $html, array( 'stray dogs' => 2 ) )['html'] );
	}

	public function test_never_inside_skipped_elements(): void {
		$html = '<h2>Stray dogs</h2><a href="/x/">stray dogs</a><code>stray dogs</code><pre>stray dogs</pre>'
			. '<button>stray dogs</button><figure><img src="a.jpg" alt=""><figcaption>stray dogs</figcaption></figure>'
			. '<script>var a = "stray dogs";</script><style>/* stray dogs */</style>';
		$r    = $this->run_linker( $html, array( 'stray dogs' => 2 ) );
		$this->assertSame( array(), $r['links'] );
		$this->assertSame( $html, $r['html'] );
	}

	public function test_links_after_a_skipped_heading(): void {
		$r = $this->run_linker( '<h3>Stray dogs</h3><p>About stray dogs.</p>', array( 'stray dogs' => 2 ) );
		$this->assertSame( '<h3>Stray dogs</h3><p>About ' . $this->a( 2, 'stray dogs' ) . '.</p>', $r['html'] );
	}

	public function test_only_the_first_appearance(): void {
		$r = $this->run_linker( '<p>Dogs here.</p><p>Dogs there.</p>', array( 'dogs' => 3 ), array( 'max_per_target' => 3 ) );
		$this->assertSame( '<p>' . $this->a( 3, 'Dogs' ) . ' here.</p><p>Dogs there.</p>', $r['html'] );
	}

	public function test_every_appearance_when_first_only_is_off(): void {
		$r = $this->run_linker(
			'<p>Dogs here.</p><p>Dogs there.</p>',
			array( 'dogs' => 3 ),
			array(
				'max_per_target' => 3,
				'first_only'     => false,
			)
		);
		$this->assertSame( '<p>' . $this->a( 3, 'Dogs' ) . ' here.</p><p>' . $this->a( 3, 'Dogs' ) . ' there.</p>', $r['html'] );
	}

	public function test_most_links_per_item(): void {
		$r = $this->run_linker(
			'<p>One, stray dogs, gaushala and cruelty act.</p>',
			array(
				'one'         => 1,
				'stray dogs'  => 2,
				'gaushala'    => 5,
				'cruelty act' => 4,
			),
			array( 'max_links' => 2 )
		);
		$this->assertSame( '<p>' . $this->a( 1, 'One' ) . ', ' . $this->a( 2, 'stray dogs' ) . ', gaushala and cruelty act.</p>', $r['html'] );
	}

	public function test_most_links_per_target(): void {
		$r = $this->run_linker(
			'<p>Dogs and a gaushala; canines too.</p>',
			array(
				'dogs'    => 3,
				'canines' => 3,
			)
		);
		$this->assertSame( '<p>' . $this->a( 3, 'Dogs' ) . ' and a gaushala; canines too.</p>', $r['html'] );
	}

	public function test_never_links_an_item_to_itself(): void {
		$html = '<p>The self page.</p>';
		$this->assertSame( $html, $this->run_linker( $html, array( 'self' => 10 ), array( 'self_id' => 10 ) )['html'] );
	}

	public function test_the_next_target_when_the_first_is_itself(): void {
		$r = $this->run_linker( '<p>Dogs.</p>', array( 'dogs' => array( 10, 3 ) ), array( 'self_id' => 10 ) );
		$this->assertSame( '<p>' . $this->a( 3, 'Dogs' ) . '.</p>', $r['html'] );
	}

	public function test_skips_a_target_already_linked_by_hand(): void {
		$html = '<p>See <a href="https://www.example.org/dogs">our page</a>. Dogs matter.</p>';
		$r    = $this->run_linker( $html, array( 'dogs' => 3 ) );
		$this->assertSame( $html, $r['html'] );
		$this->assertSame( 'our page', $r['manual'][0]['anchor'] );
	}

	public function test_relative_hand_link_counts_too(): void {
		$html = '<p><a href="/dogs/">x</a> dogs</p>';
		$this->assertSame( $html, $this->run_linker( $html, array( 'dogs' => 3 ) )['html'] );
	}

	public function test_excluded_target(): void {
		$html = '<p>Dogs.</p>';
		$this->assertSame( $html, $this->run_linker( $html, array( 'dogs' => 3 ), array( 'exclude' => array( 3 ) ) )['html'] );
	}

	public function test_unicode_hindi_and_accents(): void {
		$r = $this->run_linker(
			'<p>गौशाला में सेवा। Café dogs.</p>',
			array(
				'गौशाला' => 5,
				'café'   => 1,
			)
		);
		$this->assertSame( '<p>' . $this->a( 5, 'गौशाला' ) . ' में सेवा। ' . $this->a( 1, 'Café' ) . ' dogs.</p>', $r['html'] );
	}

	public function test_hindi_word_is_whole(): void {
		$html = '<p>गौशालाएँ</p>';
		$this->assertSame( $html, $this->run_linker( $html, array( 'गौशाला' => 5 ) )['html'] );
	}

	public function test_inline_markup_around_text(): void {
		$r = $this->run_linker( '<p><strong>Note:</strong> stray dogs <em>and</em> cats.</p>', array( 'stray dogs' => 2 ) );
		$this->assertSame( '<p><strong>Note:</strong> ' . $this->a( 2, 'stray dogs' ) . ' <em>and</em> cats.</p>', $r['html'] );
	}

	public function test_no_link_leaves_html_byte_for_byte(): void {
		$html = "<!-- wp:paragraph -->\n<p>Nothing&nbsp;here &amp; there<br/></p>\n<!-- /wp:paragraph -->";
		$this->assertSame( $html, $this->run_linker( $html, array( 'dogs' => 3 ) )['html'] );
	}

	public function test_block_comments_kept_when_linking(): void {
		$r = $this->run_linker( "<!-- wp:paragraph -->\n<p>Dogs &amp; cats</p>\n<!-- /wp:paragraph -->", array( 'dogs' => 3 ) );
		$this->assertSame( "<!-- wp:paragraph -->\n<p>" . $this->a( 3, 'Dogs' ) . " &amp; cats</p>\n<!-- /wp:paragraph -->", $r['html'] );
	}

	public function test_empty_index_and_zero_limit(): void {
		$html = '<p>Dogs.</p>';
		$this->assertSame( $html, $this->run_linker( $html, array() )['html'] );
		$this->assertSame( $html, $this->run_linker( $html, array( 'dogs' => 3 ), array( 'max_links' => 0 ) )['html'] );
	}

	public function test_target_without_address_is_skipped(): void {
		$html = '<p>Unknown thing.</p>';
		$this->assertSame( $html, $this->run_linker( $html, array( 'unknown' => 99 ) )['html'] );
	}

	public function test_item_the_parser_would_change_is_left_alone(): void {
		$svg = '<svg viewBox="0 0 1 1"><path d="M0 0"/></svg><p>Help stray dogs.</p>';
		$r   = $this->run_linker( $svg, array( 'stray dogs' => 2 ) );
		$this->assertSame( $svg, $r['html'] );
		$this->assertSame( array(), $r['links'] );
		$nest = '<p>Help stray dogs <div>here</div></p>';
		$this->assertSame( $nest, $this->run_linker( $nest, array( 'stray dogs' => 2 ) )['html'] );
	}

	public function test_figure_and_void_tags_still_link(): void {
		$html = '<figure><img src="a.jpg" alt="x"/><figcaption>Cap</figcaption></figure><p>Stray dogs<br/>sleep.</p><ul><li>One</li></ul>';
		$r    = $this->run_linker( $html, array( 'stray dogs' => 2 ) );
		$this->assertCount( 1, $r['links'] );
	}

	public function test_normalize(): void {
		$this->assertSame( 'stray dogs', AW_Linker::normalize( '  Stray   DOGS ' ) );
		$this->assertSame( 'prevention of cruelty to animals act 1960', AW_Linker::normalize( 'Prevention of Cruelty to Animals Act, 1960' ) );
		$this->assertSame( '', AW_Linker::normalize( ' -- ' ) );
	}

	public function test_url_key(): void {
		$this->assertSame( 'example.org/dogs', AW_Linker::url_key( 'https://www.Example.org/dogs/?a=1#b' ) );
		$this->assertSame( 'example.org/dogs', AW_Linker::url_key( '/dogs/', 'https://example.org' ) );
		$this->assertSame( '', AW_Linker::url_key( '#top' ) );
	}
}
