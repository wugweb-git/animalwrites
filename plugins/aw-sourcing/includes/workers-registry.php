<?php
/**
 * Settings → Sourcing → Workers: the workflow registry (no functions, so the Deploy check can load it alone).
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * The workflows: an exact copy of docs/workers.json in wugweb-git/animal-writes-workers. That repository's test fails when a
 * workflow's name, schedule or timeout changes without that file; this repository's Deploy check fails when this copy
 * differs from it (tools/ci/workers_registry_check.php). interval_min: the longest gap between two scheduled runs. runnow: inputs
 * sent by Run now (none: the workflow's own defaults). late_after_h: hours without a report before the workflow is late
 * (null: never late; owner, 24 Oct 2026). Crons are UTC, as in the workflows.
 */
const AW_WORKERS = array(
	'sourcing.yml'             => array(
		'name'         => 'Sourcing workers',
		'what'         => 'Finds News, Library and press items (Google News, feeds), checks relevance, summarises with AI and sends them to the site',
		'crons'        => array( '17 */6 * * *', '47 2-23/6 * * *' ),
		'interval_min' => 210,
		'timeout_min'  => 45,
		'runnow'       => array( 'dry_run' => 'false' ),
		'late_after_h' => 12,
	),
	'summary-rewrite.yml'      => array(
		'name'         => 'Summaries: rewrite those that break the current rules',
		'what'         => 'Rewrites summaries that break Settings → Sourcing → Summary rules; starts its next run while items remain',
		'crons'        => array( '7,37 * * * *' ),
		'interval_min' => 30,
		'timeout_min'  => 50,
		'late_after_h' => 12,
	),
	'sources-sync.yml'         => array(
		'name'         => 'Sources sync',
		'what'         => 'Copies the site\'s source lists into sources.yaml (the workers\' fallback) when Workers use these lists is on',
		'crons'        => array( '23 * * * *' ),
		'interval_min' => 60,
		'timeout_min'  => 5,
		'late_after_h' => 12,
	),
	'tests.yml'                => array(
		'name'         => 'Tests',
		'what'         => 'Runs the workers\' tests on every push and after Sources sync; a failure opens an issue (sourcing is not blocked)',
		'crons'        => array(),
		'after'        => 'Sources sync',
		'interval_min' => 60,
		'timeout_min'  => 15,
		'late_after_h' => null,
	),
	'focus-keywords.yml'       => array(
		'name'         => 'Focus keywords (Rank Math)',
		'what'         => 'Gives items without a Rank Math focus keyword one (AI, or the best words of the SEO title)',
		'crons'        => array( '30 22 * * *' ),
		'interval_min' => 1440,
		'timeout_min'  => 90,
		'late_after_h' => 36,
	),
	'backup-offsite.yml'       => array(
		'name'         => 'Site backup copy',
		'what'         => 'Copies the site\'s daily database and code backup to a private GitHub release (kept 14 days)',
		'crons'        => array( '30 23 * * *' ),
		'interval_min' => 1440,
		'timeout_min'  => 360,
		'late_after_h' => 36,
	),
	'seo-scores.yml'           => array(
		'name'         => 'SEO scores (Rank Math)',
		'what'         => 'Works out Rank Math SEO scores with Rank Math\'s own analyser for items that have a keyword but no score',
		'crons'        => array( '45 23 * * *' ),
		'interval_min' => 1440,
		'timeout_min'  => 30,
		'late_after_h' => 36,
	),
	'directory-enrichment.yml' => array(
		'name'         => 'Directory enrichment',
		'what'         => 'Finds missing contact details for Directory listings (Google Places free quota, OSM, Wikidata, websites) for review',
		'crons'        => array(),
		'after'        => 'SEO scores (Rank Math)',
		'interval_min' => 1440,
		'timeout_min'  => 170,
		'late_after_h' => 36,
	),
	'research.yml'             => array(
		'name'         => 'Research (India, Europe PMC and Crossref)',
		'what'         => 'Finds new Indian animal-welfare papers (Europe PMC, Crossref) for the Library',
		'crons'        => array( '40 3 * * 1' ),
		'interval_min' => 10080,
		'timeout_min'  => 120,
		'runnow'       => array( 'dry_run' => 'false' ),
		'late_after_h' => 216,
	),
	'wp-cron.yml'              => array(
		'name'         => 'WordPress cron',
		'what'         => 'Backup trigger for the site\'s scheduled tasks (Hostinger\'s server cron is the main one)',
		'crons'        => array( '*/15 * * * *' ),
		'interval_min' => 15,
		'timeout_min'  => 3,
		'late_after_h' => null,
	),
	'keepalive.yml'            => array(
		'name'         => 'Keepalive',
		'what'         => 'Switches scheduled workflows back on and commits when the repository has been quiet for 50 days',
		'crons'        => array( '41 3 1 * *' ),
		'interval_min' => 44640,
		'timeout_min'  => 5,
		'late_after_h' => 840,
	),
	'backfill.yml'             => array(
		'name'         => 'Backfill (News + Library from 1 Nov 2025)',
		'what'         => 'One-off: older News and Library items, sent as drafts',
		'crons'        => array(),
		'timeout_min'  => 300,
		'late_after_h' => null,
	),
	'submit.yml'               => array(
		'name'         => 'Submit URLs (News found by hand)',
		'what'         => 'Sends News URLs found by hand through the same checks and summary as sourcing',
		'crons'        => array(),
		'timeout_min'  => 30,
		'needs_input'  => true,
		'late_after_h' => null,
	),
	'press-gnews.yml'          => array(
		'name'         => 'Press search (one-off, Google News and Google Search)',
		'what'         => 'One-off: press coverage of Animal Writes through Google Search',
		'crons'        => array(),
		'timeout_min'  => 60,
		'late_after_h' => null,
	),
	'relevance-existing.yml'   => array(
		'name'         => 'Relevance check of existing items',
		'what'         => 'One-off: checks published items against the relevance rules and stores a verdict',
		'crons'        => array(),
		'timeout_min'  => 120,
		'late_after_h' => null,
	),
	'news-title-case.yml'      => array(
		'name'         => 'News and press titles: sentence case (dry run)',
		'what'         => 'Dry run: a CSV of titles in sentence case; writes nothing to the site',
		'crons'        => array(),
		'timeout_min'  => 60,
		'late_after_h' => null,
	),
	'same-story-fold.yml'      => array(
		'name'         => 'Same story: fold duplicates into the first story',
		'what'         => 'Folds a duplicate story into the first one (pairs given by hand)',
		'crons'        => array(),
		'timeout_min'  => 30,
		'needs_input'  => true,
		'late_after_h' => null,
	),
);
