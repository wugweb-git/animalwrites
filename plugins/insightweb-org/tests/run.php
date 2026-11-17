<?php
/**
 * InsightWeb (public edition) I1 smoke test — no WordPress. Confirms the skeleton loads, defines its constants,
 * registers a standalone menu, and renders the empty-module screen with the Site Kit prompt. Runs in the Deploy check
 * and locally: php plugins/insightweb-org/tests/run.php
 *
 * @package InsightWeb
 */

if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) {
	exit;
}

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput, Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment, Universal.Files.SeparateFunctionsFromOO, WordPress.NamingConventions.PrefixAllGlobals, Generic.CodeAnalysis.UnusedFunctionParameter, WordPress.Files.FileName, WordPress.WP.GlobalVariablesOverride -- a command-line test runner with WordPress stubs (stub params are used via compact(); the $wpdb stub stands in for the global).

define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAY_A', 'ARRAY_A' );

$GLOBALS['iw_menu'] = array();

/** Stub. */
function plugin_dir_path( $file ) {
	return dirname( $file ) . '/';
}
/** Stub. */
function add_action() {}
/** Stub: default passthrough. */
function apply_filters( $hook, $value ) {
	return $value;
}
/** Stub. */
function current_user_can() {
	return true;
}
/** Stub: record the registered menu. */
function add_menu_page( $page_title, $menu_title, $cap, $slug, $cb, $icon = '' ) {
	$GLOBALS['iw_menu'] = compact( 'page_title', 'menu_title', 'cap', 'slug', 'cb', 'icon' );
}
/** Stub. */
function __( $s ) {
	return $s;
}
/** Stub. */
function esc_html__( $s ) {
	return $s;
}
/** Stub. */
function esc_html( $s ) {
	return $s;
}
/** Stub: options in memory. */
function get_option( $name, $fallback = false ) {
	return array_key_exists( $name, $GLOBALS['iw_opts'] ) ? $GLOBALS['iw_opts'][ $name ] : $fallback;
}
/** Stub. */
function update_option( $name, $value ) {
	$GLOBALS['iw_opts'][ $name ] = $value;
	return true;
}
/** Stub. */
function current_time( $type ) {
	return '2026-11-17 10:00:00';
}
/** Stub. */
function add_filter() {}
/** Stub: front-end context, so the WP_List_Table file is not loaded in the test. */
function is_admin() {
	return false;
}
/** Stub. */
function esc_attr( $s ) {
	return $s;
}
/** Stub. */
function esc_attr__( $s ) {
	return $s;
}
/** Stub. */
function esc_url( $s ) {
	return $s;
}
/** Stub: append the args as a query string so URLs are inspectable. */
function add_query_arg( $args, $url = '' ) {
	return $url . ( false === strpos( (string) $url, '?' ) ? '?' : '&' ) . http_build_query( (array) $args );
}
/** Stub. */
function admin_url( $path = '' ) {
	return 'http://example.test/wp-admin/' . $path;
}
/** Stub. */
function number_format_i18n( $n, $decimals = 0 ) {
	return number_format( (float) $n, (int) $decimals );
}

/** Minimal in-memory $wpdb for the keyword-table round-trip (ignores SQL text, dispatches on the known shapes). */
class IW_WPDB {
	public $prefix    = 'wp_';
	public $insert_id = 0;
	/**
	 * In-memory rows by id.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public $rows  = array();
	private $next = 1;
	public function get_charset_collate() {
		return '';
	}
	public function prepare( $query, ...$args ) {
		return array( $query, $args );
	}
	public function get_var( $prepared ) {
		list( $query, $args ) = $prepared;
		if ( false !== strpos( $query, 'WHERE norm' ) ) {
			foreach ( $this->rows as $r ) {
				if ( $r['norm'] === $args[1] ) {
					return $r['id'];
				}
			}
		}
		return null;
	}
	public function get_row( $prepared, $output = null ) {
		list( $query, $args ) = $prepared;
		if ( false !== strpos( $query, 'WHERE id' ) ) {
			return $this->rows[ (int) $args[1] ] ?? null;
		}
		return null;
	}
	public function insert( $table, $row ) {
		$id                = $this->next++;
		$row['id']         = $id;
		$this->rows[ $id ] = $row;
		$this->insert_id   = $id;
		return 1;
	}
	public function update( $table, $set, $where ) {
		$id = (int) ( $where['id'] ?? 0 );
		if ( ! isset( $this->rows[ $id ] ) ) {
			return false;
		}
		foreach ( $set as $k => $v ) {
			$this->rows[ $id ][ $k ] = $v;
		}
		return 1;
	}
}

$GLOBALS['iw_opts'] = array();
$GLOBALS['wpdb']    = new IW_WPDB();

require dirname( __DIR__ ) . '/insightweb.php';

$GLOBALS['iw_fail'] = 0;

/** One check. */
function iw_check( bool $ok, string $what ): void {
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $what . "\n";
	if ( ! $ok ) {
		$GLOBALS['iw_fail'] = 1;
	}
}

iw_check( defined( 'INSIGHTWEB_VERSION' ) && '0.1.0' === INSIGHTWEB_VERSION, 'version constant is 0.1.0' );
iw_check( defined( 'INSIGHTWEB_FILE' ) && defined( 'INSIGHTWEB_DIR' ), 'file and dir constants defined' );
iw_check( 'manage_options' === insightweb_cap(), 'capability maps to manage_options' );
$tabs = insightweb_tabs();
iw_check( isset( $tabs['overview'], $tabs['keywords'] ), 'tab set has Overview and Keywords' );
iw_check( is_bool( insightweb_site_kit_active() ) && false === insightweb_site_kit_active(), 'Site Kit absent in the test → false' );

insightweb_admin_menu();
iw_check( 'insightweb' === ( $GLOBALS['iw_menu']['slug'] ?? '' ) && 'insightweb_render_screen' === ( $GLOBALS['iw_menu']['cb'] ?? '' ), 'standalone top-level menu registered on slug insightweb' );

ob_start();
insightweb_render_screen();
$html = (string) ob_get_clean();
iw_check( false !== strpos( $html, 'nav-tab' ) && false !== strpos( $html, 'Overview' ) && false !== strpos( $html, 'Keywords' ), 'tabbed screen renders the Overview and Keywords tabs' );
iw_check( false !== strpos( $html, 'Site Kit by Google' ), 'Site Kit connection prompt shown when absent' );
iw_check( false !== strpos( $html, 'Setting up' ), 'Overview shows the setting-up state before the tables exist' );

// Admin data layer (query.php): views, roles, intents, effective-priority SQL.
$views = insightweb_view_sql();
iw_check( isset( $views['p1'], $views['gap'], $views['quick'], $views['notranked'] ) && ! isset( $views['entity'] ), 'view SQL has the public views and drops the entity view' );
iw_check( false !== strpos( insightweb_eff_sql(), 'priority_override' ), 'effective-priority SQL prefers the override' );
iw_check( count( insightweb_roles() ) === 4 && count( insightweb_intents() ) === 4, 'four roles and four intents' );
iw_check( false !== strpos( insightweb_url( 'keywords' ), 'page=insightweb' ) && false !== strpos( insightweb_url( 'keywords' ), 'tab=keywords' ), 'tab URL carries page and tab' );

// Keyword table.
iw_check( 'dog bite' === insightweb_kw_norm( '  Dog BITE! ' ), 'norm lower-cases, strips punctuation, collapses space' );
$sql = insightweb_kw_schema_sql( 'wp_insightweb_keywords', '' );
iw_check( false !== strpos( $sql, 'CREATE TABLE wp_insightweb_keywords' ) && false !== strpos( $sql, 'quick_win' ) && false !== strpos( $sql, 'impr28' ), 'schema has the keywords table and the search-insights columns' );
iw_check( false === strpos( $sql, 'variants' ) && false === strpos( $sql, 'feed_id' ) && false === strpos( $sql, 'src_note' ), 'schema drops the sourcing columns (decoupled)' );
iw_check( false === insightweb_kw_ready(), 'table not ready before install' );
$GLOBALS['iw_opts'][ INSIGHTWEB_KW_DB_OPTION ] = INSIGHTWEB_KW_DB_VERSION;
iw_check( true === insightweb_kw_ready(), 'table ready once the version option is set' );
$kw = insightweb_kw_create( 'Dog bite' );
iw_check( 1 === $kw && 1 === insightweb_kw_id_by_norm( 'dog bite' ), 'create returns an id; id_by_norm finds it' );
iw_check( 1 === insightweb_kw_create( 'dog  bite' ), 'create dedupes on the normal form' );
$row = insightweb_kw_get( $kw );
iw_check( is_array( $row ) && 'dog bite' === $row['norm'] && (int) $row['group_id'] === $kw, 'get returns the record; a new record is its own group' );
iw_check(
	insightweb_kw_set_figures(
		$kw,
		array(
			'impr28'    => 50,
			'quick_win' => 1,
		)
	) && 50 === (int) insightweb_kw_get( $kw )['impr28'],
	'set_figures writes the daily figures'
);

// Decision engine (compute.php).
iw_check( 'P3' === insightweb_priority( null, 999, 2.0, true ), 'position in the top 3 is always P3' );
iw_check( 'P1' === insightweb_priority( null, 80, 6.0, false ), 'high demand, ranking 4-20 → P1' );
iw_check( 'P1' === insightweb_priority( null, 80, null, true ), 'high demand with an owning page → P1' );
iw_check( 'P2' === insightweb_priority( null, 80, null, false ), 'high demand, no page, not ranking → P2 (gap)' );
iw_check( 'P3' === insightweb_priority( 5, 10, null, false ), 'low demand → P3' );
iw_check( insightweb_is_question( 'how to report animal cruelty' ) && insightweb_is_question( 'what is rabies?' ), 'question phrases detected' );
iw_check( ! insightweb_is_question( 'animal ambulance mumbai' ), 'a plain phrase is not a question' );
iw_check( insightweb_is_news( 'high court verdict' ) && insightweb_is_news( 'dog attack incident 2024' ), 'news phrases detected' );
iw_check( ! insightweb_is_news( 'animal birth control rules 2023' ), 'a law of a year is not news' );
iw_check( insightweb_quick_win( 12.0, 20 ) && ! insightweb_quick_win( 5.0, 999 ) && ! insightweb_quick_win( 12.0, 3 ), 'quick win: position 8-20 with enough impressions' );
iw_check( 'faq-answer' === insightweb_gap_action( 'how do I adopt' ) && 'news-explainer' === insightweb_gap_action( 'court case 2024' ) && 'new-guide' === insightweb_gap_action( 'animal shelter delhi' ), 'gap action picks the fitting page type' );

$d = insightweb_decide( null, 80, 6.0, null, array( '/a/', '/b/' ), true, array( '/a/', 60 ), array( 'main' => array( '/a/', '/b/' ) ) );
iw_check( 'conflict' === $d['status'] && 'fix-conflict' === $d['action'] && true === $d['conflict'], 'two owning pages → conflict' );
$d = insightweb_decide( null, 80, null, null, array(), false, array( '', 0 ), array( 'label' => 'how to report cruelty' ) );
iw_check( 'gap' === $d['status'] && 'faq-answer' === $d['action'], 'high-demand question with no page → gap + FAQ answer' );
$d = insightweb_decide( null, 40, 12.0, null, array( '/guide/' ), true, array( '/guide/', 40 ), array() );
iw_check( 'has-page' === $d['status'] && 'guide-update' === $d['action'], 'a quick win with an owning page → update the page' );

// Observation tables (schema.php).
$pages = insightweb_pages_schema_sql( 'wp_insightweb_kw_pages', '' );
$obs   = insightweb_obs_schema_sql( 'wp_insightweb_obs', '' );
$ris   = insightweb_rising_schema_sql( 'wp_insightweb_rising', '' );
$lnk   = insightweb_links_schema_sql( 'wp_insightweb_links', '' );
iw_check( false !== strpos( $pages, 'CREATE TABLE wp_insightweb_kw_pages' ) && false !== strpos( $pages, 'UNIQUE KEY kw_page_role' ), 'kw_pages schema' );
iw_check( false !== strpos( $obs, 'CREATE TABLE wp_insightweb_obs' ) && false !== strpos( $obs, 'UNIQUE KEY obs' ) && false !== strpos( $obs, 'clicks' ) && false !== strpos( $obs, 'impr' ) && false !== strpos( $obs, 'pos ' ), 'obs schema with the GSC metric columns' );
iw_check( false !== strpos( $ris, 'CREATE TABLE wp_insightweb_rising' ) && false !== strpos( $ris, 'growth' ), 'rising schema' );
iw_check( false !== strpos( $lnk, 'CREATE TABLE wp_insightweb_links' ) && false !== strpos( $lnk, 'UNIQUE KEY link_key' ), 'links schema' );
iw_check( false === insightweb_ready(), 'observation tables not ready before install' );
$GLOBALS['iw_opts'][ INSIGHTWEB_DB_OPTION ] = INSIGHTWEB_DB_VERSION;
iw_check( true === insightweb_ready(), 'observation tables ready once the version option is set' );

// Daily recompute (recompute.php).
iw_check( ! insightweb_compute_changed( null, null ) && insightweb_compute_changed( null, 5 ) && insightweb_compute_changed( 5, null ), 'change detection: null vs value' );
iw_check( ! insightweb_compute_changed( '6.4', 6.43 ) && insightweb_compute_changed( '6.4', 7.0 ), 'change detection: floats within 0.05 are equal' );
iw_check( insightweb_compute_changed( 'P1', 'P2' ) && ! insightweb_compute_changed( 'P1', 'P1' ), 'change detection: strings' );

$set = insightweb_compute_row( 80, 0, null, null, null, null, array(), false, array( '', 0 ), array( 'label' => 'how to report cruelty' ) );
iw_check( 'gap' === $set['status'] && 'faq-answer' === $set['action'] && 80 === $set['impr28'] && 0 === $set['quick_win'] && '' === $set['page'], 'row shaper: a high-demand question gap' );
$set = insightweb_compute_row( 40, 3, 12.0, 13.0, null, null, array( '/guide/' ), true, array( '/guide/', 40 ), array( 'label' => 'dog adoption guide' ) );
iw_check( 'has-page' === $set['status'] && 'guide-update' === $set['action'] && 1 === $set['quick_win'] && '/guide/' === $set['page'] && 12.0 === $set['pos28'], 'row shaper: a quick win with an owning page' );

exit( $GLOBALS['iw_fail'] );
