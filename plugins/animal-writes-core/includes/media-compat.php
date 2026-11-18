<?php
/**
 * Old names of the media functions that moved out of this plugin on 18 Nov 2026 (Track F of its retirement):
 * card images, category photos, the no-repeat thumbnail rule and the media tools to Kits Core; Media Library autofill
 * and columns to RankWeb; publisher images and the CC0 fetch to SourceWeb; the Library document viewer to Kits Core and
 * the government PDF mirror to SourceWeb. Every caller in this repository uses the
 * new names. These one-line wrappers only cover code on other open branches that still calls an old name; they go
 * with this plugin. Each forwards to the new function when that plugin is loaded, else returns an empty value.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'aw_item_image' ) ) {
	/**
	 * Old name of kits_item_image().
	 *
	 * @param mixed ...$args Same arguments.
	 * @return mixed
	 */
	function aw_item_image( ...$args ) {
		return function_exists( 'kits_item_image' ) ? kits_item_image( ...$args ) : '';
	}
}

if ( ! function_exists( 'aw_item_image_id' ) ) {
	/**
	 * Old name of kits_item_image_id().
	 *
	 * @param mixed ...$args Same arguments.
	 * @return mixed
	 */
	function aw_item_image_id( ...$args ) {
		return function_exists( 'kits_item_image_id' ) ? kits_item_image_id( ...$args ) : 0;
	}
}

if ( ! function_exists( 'aw_category_card_id' ) ) {
	/**
	 * Old name of kits_category_card_id().
	 *
	 * @param mixed ...$args Same arguments.
	 * @return mixed
	 */
	function aw_category_card_id( ...$args ) {
		return function_exists( 'kits_category_card_id' ) ? kits_category_card_id( ...$args ) : 0;
	}
}

if ( ! function_exists( 'aw_is_ingested' ) ) {
	/**
	 * Old name of kits_is_ingested().
	 *
	 * @param mixed ...$args Same arguments.
	 * @return mixed
	 */
	function aw_is_ingested( ...$args ) {
		return function_exists( 'kits_is_ingested' ) ? kits_is_ingested( ...$args ) : false;
	}
}

if ( ! function_exists( 'aw_placard_html' ) ) {
	/**
	 * Old name of kits_placard_html().
	 *
	 * @param mixed ...$args Same arguments.
	 * @return mixed
	 */
	function aw_placard_html( ...$args ) {
		return function_exists( 'kits_placard_html' ) ? kits_placard_html( ...$args ) : '';
	}
}

if ( ! function_exists( 'aw_category_image_id' ) ) {
	/**
	 * Old name of kits_category_image_id().
	 *
	 * @param mixed ...$args Same arguments.
	 * @return mixed
	 */
	function aw_category_image_id( ...$args ) {
		return function_exists( 'kits_category_image_id' ) ? kits_category_image_id( ...$args ) : 0;
	}
}

if ( ! function_exists( 'aw_category_banner_html' ) ) {
	/**
	 * Old name of kits_category_banner_html().
	 *
	 * @param mixed ...$args Same arguments.
	 * @return mixed
	 */
	function aw_category_banner_html( ...$args ) {
		return function_exists( 'kits_category_banner_html' ) ? kits_category_banner_html( ...$args ) : '';
	}
}

if ( ! function_exists( 'aw_thumb_hash' ) ) {
	/**
	 * Old name of kits_thumb_hash().
	 *
	 * @param mixed ...$args Same arguments.
	 * @return mixed
	 */
	function aw_thumb_hash( ...$args ) {
		return function_exists( 'kits_thumb_hash' ) ? kits_thumb_hash( ...$args ) : '';
	}
}

if ( ! function_exists( 'aw_thumb_rebuild' ) ) {
	/**
	 * Old name of kits_thumb_rebuild().
	 *
	 * @param mixed ...$args Same arguments.
	 * @return mixed
	 */
	function aw_thumb_rebuild( ...$args ) {
		return function_exists( 'kits_thumb_rebuild' ) ? kits_thumb_rebuild( ...$args ) : array();
	}
}

if ( ! function_exists( 'aw_thumb_for' ) ) {
	/**
	 * Old name of kits_thumb_for().
	 *
	 * @param mixed ...$args Same arguments.
	 * @return mixed
	 */
	function aw_thumb_for( ...$args ) {
		return function_exists( 'kits_thumb_for' ) ? kits_thumb_for( ...$args ) : 0;
	}
}

if ( ! function_exists( 'aw_thumb_duplicates' ) ) {
	/**
	 * Old name of kits_thumb_duplicates().
	 *
	 * @param mixed ...$args Same arguments.
	 * @return mixed
	 */
	function aw_thumb_duplicates( ...$args ) {
		return function_exists( 'kits_thumb_duplicates' ) ? kits_thumb_duplicates( ...$args ) : array();
	}
}

if ( ! function_exists( 'aw_publisher_image' ) ) {
	/**
	 * Old name of sourceweb_publisher_image().
	 *
	 * @param mixed ...$args Same arguments.
	 * @return mixed
	 */
	function aw_publisher_image( ...$args ) {
		return function_exists( 'sourceweb_publisher_image' ) ? sourceweb_publisher_image( ...$args ) : '';
	}
}

if ( ! function_exists( 'aw_source_download' ) ) {
	/**
	 * Old name of sourceweb_source_download().
	 *
	 * @param mixed ...$args Same arguments.
	 * @return mixed
	 */
	function aw_source_download( ...$args ) {
		return function_exists( 'sourceweb_source_download' ) ? sourceweb_source_download( ...$args ) : new WP_Error( 'sourceweb_missing', 'SourceWeb is not loaded.' );
	}
}

if ( ! function_exists( 'aw_media_usage' ) ) {
	/**
	 * Old name of rankweb_media_usage().
	 *
	 * @param mixed ...$args Same arguments.
	 * @return mixed
	 */
	function aw_media_usage( ...$args ) {
		return function_exists( 'rankweb_media_usage' ) ? rankweb_media_usage( ...$args ) : array();
	}
}

if ( ! function_exists( 'aw_doc_preview_html' ) ) {
	/**
	 * Old name of kits_doc_preview_html().
	 *
	 * @param mixed ...$args Same arguments.
	 * @return mixed
	 */
	function aw_doc_preview_html( ...$args ) {
		return function_exists( 'kits_doc_preview_html' ) ? kits_doc_preview_html( ...$args ) : '';
	}
}

if ( ! function_exists( 'aw_doc_hostable' ) ) {
	/**
	 * Old name of sourceweb_doc_hostable().
	 *
	 * @param mixed ...$args Same arguments.
	 * @return mixed
	 */
	function aw_doc_hostable( ...$args ) {
		return function_exists( 'sourceweb_doc_hostable' ) ? sourceweb_doc_hostable( ...$args ) : false;
	}
}

if ( ! function_exists( 'aw_doc_refetch' ) ) {
	/**
	 * Old name of sourceweb_doc_refetch().
	 *
	 * @param mixed ...$args Same arguments.
	 * @return mixed
	 */
	function aw_doc_refetch( ...$args ) {
		return function_exists( 'sourceweb_doc_refetch' ) ? sourceweb_doc_refetch( ...$args ) : 0;
	}
}

if ( ! function_exists( 'aw_mirror_gov_pdf' ) ) {
	/**
	 * Old name of sourceweb_mirror_gov_pdf().
	 *
	 * @param mixed ...$args Same arguments.
	 * @return mixed
	 */
	function aw_mirror_gov_pdf( ...$args ) {
		return function_exists( 'sourceweb_mirror_gov_pdf' ) ? sourceweb_mirror_gov_pdf( ...$args ) : 0;
	}
}
