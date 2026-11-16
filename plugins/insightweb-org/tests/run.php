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
iw_check( array() === insightweb_tabs(), 'tab set is empty in the skeleton' );
iw_check( is_bool( insightweb_site_kit_active() ) && false === insightweb_site_kit_active(), 'Site Kit absent in the test → false' );

insightweb_admin_menu();
iw_check( 'insightweb' === ( $GLOBALS['iw_menu']['slug'] ?? '' ) && 'insightweb_render_screen' === ( $GLOBALS['iw_menu']['cb'] ?? '' ), 'standalone top-level menu registered on slug insightweb' );

ob_start();
insightweb_render_screen();
$html = (string) ob_get_clean();
iw_check( false !== strpos( $html, 'No insights modules are active yet.' ), 'empty-module screen renders' );
iw_check( false !== strpos( $html, 'Site Kit by Google' ), 'Site Kit connection prompt shown when absent' );

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

exit( $GLOBALS['iw_fail'] );
