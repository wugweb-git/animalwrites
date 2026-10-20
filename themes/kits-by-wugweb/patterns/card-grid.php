<?php
/**
 * Title: Card grid — latest
 * Slug: kits-by-wugweb/card-grid
 * Categories: kits-lists
 */
?>
<!-- wp:query {"queryId":10,"query":{"perPage":6,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":false},"align":"wide"} -->
<div class="wp-block-query alignwide"><!-- wp:post-template {"layout":{"type":"grid","columnCount":3,"minimumColumnWidth":"18rem"}} -->
<?php echo kits_card_markup( 'block-md' ); ?>
<!-- /wp:post-template -->

<!-- wp:query-no-results -->
<!-- wp:pattern {"slug":"kits-by-wugweb/empty-state"} /-->
<!-- /wp:query-no-results --></div>
<!-- /wp:query -->
