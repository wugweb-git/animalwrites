<?php
/**
 * Title: Find help in your state
 * Slug: animal-writes/help-finder
 * Categories: animal-writes
 * Description: The state finder with the emergency note. Use on any page that should lead people to local help.
 * Inserter: yes
 *
 * @package animal-writes
 */
?>
<!-- wp:group {"align":"full","className":"aw-finder-band","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"backgroundColor":"surface","layout":{"type":"constrained","contentSize":"760px"}} -->
<div class="wp-block-group alignfull aw-finder-band has-surface-background-color has-background" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)"><!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Find help in your state</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Shelters, vets, animal ambulances and rescue groups, sorted by district.</p>
<!-- /wp:paragraph -->
<!-- wp:html -->
<form class="aw-finder" action="/directory/" method="get" onsubmit="var v=this.state.value;if(v){location.href='/directory/'+v+'/';return false;}"><label for="aw-state">State or union territory</label><select id="aw-state" name="state" required><option value="">Choose a state</option><option value="andaman-and-nicobar-islands">Andaman and Nicobar Islands</option><option value="andhra-pradesh">Andhra Pradesh</option><option value="arunachal-pradesh">Arunachal Pradesh</option><option value="assam">Assam</option><option value="bihar">Bihar</option><option value="chandigarh-ut">Chandigarh</option><option value="chhattisgarh">Chhattisgarh</option><option value="delhi">Delhi</option><option value="goa">Goa</option><option value="gujarat">Gujarat</option><option value="haryana">Haryana</option><option value="himachal-pradesh">Himachal Pradesh</option><option value="jammu-and-kashmir">Jammu and Kashmir</option><option value="jharkhand">Jharkhand</option><option value="karnataka">Karnataka</option><option value="kerala">Kerala</option><option value="ladakh">Ladakh</option><option value="lakshadweep">Lakshadweep</option><option value="madhya-pradesh">Madhya Pradesh</option><option value="maharashtra">Maharashtra</option><option value="manipur">Manipur</option><option value="meghalaya">Meghalaya</option><option value="mizoram">Mizoram</option><option value="nagaland">Nagaland</option><option value="odisha">Odisha</option><option value="puducherry-ut">Puducherry</option><option value="punjab">Punjab</option><option value="rajasthan">Rajasthan</option><option value="sikkim">Sikkim</option><option value="tamil-nadu">Tamil Nadu</option><option value="telangana">Telangana</option><option value="the-dadra-and-nagar-haveli-and-daman-and-diu">The Dadra and Nagar Haveli and Daman and Diu</option><option value="tripura">Tripura</option><option value="uttar-pradesh">Uttar Pradesh</option><option value="uttarakhand">Uttarakhand</option><option value="west-bengal">West Bengal</option></select><button type="submit" class="wp-element-button">Show help</button></form>
<!-- /wp:html -->
<!-- wp:paragraph {"className":"aw-emergency","fontSize":"small"} -->
<p class="aw-emergency has-small-font-size">In an emergency, call a rescue group or vet directly. <a href="/knowledge/found-an-injured-animal-what-to-do/">What to do for an injured animal</a> or <a href="/helplines/">official helplines</a>.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
