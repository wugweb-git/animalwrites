<?php
/**
 * The keyword table and its rules (wp_aw_si_keywords and wp_aw_si_kw_map, option aw_kw_db), loaded once. InsightWeb's
 * Search Insights and SourceWeb's Keywords, policy and workers share them; RankWeb reads them when they are there.
 * Moved from Kits Core on 18 Nov 2026 with the same table, option and function names.
 *
 * Reached from insightweb.php, from the bootstrap (when Animal Writes Core or RankWeb's bridge loads Search Insights
 * without the InsightWeb plugin) and from SourceWeb's main file. The guard stops a second load from another path.
 *
 * @package insightweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'INSIGHTWEB_KEYWORDS_LOADED' ) ) {
	return;
}
define( 'INSIGHTWEB_KEYWORDS_LOADED', true );

require_once __DIR__ . '/keyword-model.php';
require_once __DIR__ . '/keyword-table.php';
