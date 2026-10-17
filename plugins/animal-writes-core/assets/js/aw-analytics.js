/* Animal Writes analytics (owner's audit, 17 Oct 2026). Events go through kitsTrack (kit.js) to the dataLayer (GTM ->
 * GA4); window.kitsPage (kits_page_context, kits-config.php) says what the page is. docs/analytics/ga4-setup.md lists
 * every event, its parameters and the GA4 settings.
 *
 * Directory
 * - view_item (profile), view_item_list (a list of listings: on load, or when a related list scrolls into view),
 *   select_item (a listing card clicked): GA4 items with item_id, item_name, item_category (type), item_category2
 *   (state), item_category3 (district), index, item_list_id, item_list_name.
 * - listing_action (action, area, network): every action on a profile: call, whatsapp, email, website, directions,
 *   social, donate, volunteer, claim, report, suggest_update, share, type_link, place_link, breadcrumb; a greyed-out
 *   button that was tapped: call_missing, whatsapp_missing, email_missing, website_missing, directions_missing.
 * - generate_lead (lead_source: call | whatsapp | email): a visitor contacting a listed organisation.
 * - no_results (hub, term, filters): a hub list that came back empty.
 *   Every Directory event carries listing_id, listing_name, listing_type, state, district where they apply.
 * Campaigns: campaign_view on every campaign page; campaign_action (action, ...) for every link on it, FAQ answers
 *   opened, and the home band (promo_click). campaign.js adds the letter tool's actions. Always campaign_slug and
 *   campaign_title (not campaign_id / campaign_name: GA4 reads those two as traffic-source fields).
 * Money: sponsored_view / sponsored_click (sponsored blocks and sponsored pages), donate_click. Ad slots: kit.js.
 * Named-entity links in summaries: select_content (content_type entity_law | entity_organisation | entity_place).
 */
(function () {
	'use strict';
	var page = window.kitsPage || {};
	function track(name, params) {
		if (typeof window.kitsTrack === 'function') { window.kitsTrack(name, params); return; }
		var dl = window.dataLayer = window.dataLayer || [];
		if (params.ecommerce) dl.push({ ecommerce: null });
		dl.push(Object.assign({ event: name, page_type: page.page_type || '' }, params));
	}
	function text(el) { return el ? el.textContent.replace(/\s+/g, ' ').trim().slice(0, 100) : ''; }
	function ready(fn) { if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn); else fn(); }
	function domain(href) { try { var u = new URL(href, location.href); return u.host === location.host ? '' : u.host.replace(/^www\./, ''); } catch (x) { return ''; } }

	/* ---------- Directory ---------- */
	var PER_PAGE = 24; // Directory list page size (hubs.php AW_HUBS aw_listing)
	var isProfile = page.page_type === 'directory' && !!page.listing_id;
	var isDirList = page.section === 'directory' && (page.page_type === 'hub' || page.page_type === 'directory_place');
	var qs = new URLSearchParams(location.search);
	var paged = +((location.pathname.match(/\/page\/(\d+)\/?$/) || [])[1] || 1);

	// The main Directory list: an id by kind of page, a name a person can read.
	function dirList() {
		var kind = page.district ? 'district' : page.state ? 'state' : 'all';
		var id = 'directory_' + kind + (page.listing_type ? '_type' : '') + (qs.get('q') ? '_search' : '');
		var where = page.district ? page.district + ', ' + page.state : page.state || 'India';
		return { id: id, name: 'Directory: ' + where + (page.listing_type ? ' – ' + page.listing_type : '') + (qs.get('q') ? ' – search' : '') };
	}
	// One listing card as a GA4 item.
	function cardItem(li, index, list) {
		var a = li.querySelector('.aw-org-name a, .wp-block-post-title a');
		var meta = text(li.querySelector('.aw-org-meta')).split(',').map(function (s) { return s.trim(); });
		var it = {
			item_id: (li.className.match(/\bpost-(\d+)\b/) || [])[1] || '',
			item_name: text(a),
			item_category: text(li.querySelector('.aw-org-tags .kit-tag')),
			item_category2: meta.length > 1 ? meta[meta.length - 1] : page.state || '',
			item_category3: meta.length > 1 ? meta.slice(0, -1).join(', ') : page.district || '',
			index: index
		};
		if (list) { it.item_list_id = list.id; it.item_list_name = list.name; }
		return it;
	}
	function cards(ul) { return Array.prototype.filter.call(ul.children, function (li) { return li.matches('li.wp-block-post'); }); }
	// Which list a card list is: the page's own Directory list, a "More organisations in …" list, or another list.
	function listOf(ul) {
		var rel = ul.closest('.aw-related');
		if (rel) return { id: 'related_listings', name: text(rel.querySelector('.aw-related-head, h2')) || 'Related listings' };
		if (isDirList && ul.closest('#main')) return dirList();
		var h = ul.closest('section') && ul.closest('section').querySelector('h2');
		return { id: 'listings_' + (page.page_type || 'page'), name: text(h) || 'Listings' };
	}
	function offset(ul) { return isDirList && !ul.closest('.aw-related') ? (paged - 1) * PER_PAGE : 0; }
	function viewList(ul) {
		var list = listOf(ul), off = offset(ul), items = cards(ul).map(function (li, i) { return cardItem(li, off + i + 1, list); });
		if (!items.length) return;
		var status = document.querySelector('.aw-dir-results-head [role=status]');
		track('view_item_list', { item_list_id: list.id, item_list_name: list.name, results: status ? parseInt(status.textContent.replace(/[^\d]/g, ''), 10) || items.length : items.length, listing_type: page.listing_type || undefined, state: page.state || undefined, district: page.district || undefined, ecommerce: { item_list_id: list.id, item_list_name: list.name, items: items } });
	}

	// Profile actions: data-act on the buttons and links (bindings.php, listing-profile.php), else the kind of link.
	var ACT = { phone: 'call', whatsapp: 'whatsapp', email: 'email', website: 'website', maps_url: 'directions', instagram: 'social', facebook: 'social', x: 'social', youtube: 'social', donate_url: 'donate', volunteer_url: 'volunteer' };
	function listingParams(extra) {
		return Object.assign({ listing_id: page.listing_id || '', listing_name: page.listing_name || '', listing_type: page.listing_type || '', state: page.state || '', district: page.district || '' }, extra);
	}
	function profileAction(el) {
		var act = el.getAttribute('data-act'), href = el.getAttribute('href') || '';
		var area = el.closest('.aw-profile-actions, .aw-profile-actions-na') ? 'actions' : el.closest('.aw-org-details') ? 'details' : el.closest('.aw-listing-help') ? 'help' : el.closest('.aw-profile-head') ? 'head' : el.closest('.scriptlesssocialsharing, [data-kit-share], .kit-share') ? 'share' : 'page';
		var action = '', network;
		if (act) {
			action = ACT[act] || act;
			if (action === 'social') network = act;
			if (el.getAttribute('aria-disabled') === 'true') action += '_missing';
		} else if (el.closest('.aw-claim-link') || /\/claim\/\?listing=/.test(href)) action = 'claim';
		else if (el.closest('.aw-report-link')) action = 'report';
		else if (el.closest('.aw-org-fact-note, .aw-profile-actions-na')) action = 'suggest_update';
		else if (area === 'share') { action = 'share'; network = el.getAttribute('data-kit-share') || (el.className.match(/\b(whatsapp|facebook|twitter|linkedin|email|telegram)\b/) || [, 'other'])[1]; }
		else if (el.closest('.taxonomy-post_tag')) action = 'type_link';
		else if (el.closest('.taxonomy-aw_place')) action = 'place_link';
		else if (el.closest('.kit-crumbs')) action = 'breadcrumb';
		else if (/^tel:/i.test(href)) action = 'call';
		else if (/^mailto:/i.test(href)) action = 'email';
		return action ? { action: action, area: area, network: network } : null;
	}

	ready(function () {
		if (isProfile) {
			var it = { item_id: page.listing_id, item_name: page.listing_name || '', item_category: page.listing_type || '', item_category2: page.state || '', item_category3: page.district || '' };
			var contacts = document.querySelectorAll('.aw-profile-actions a[data-act]').length;
			track('view_item', listingParams({ contacts: contacts, verified: document.querySelector('.kit-trust.is-unverified') ? 'no' : 'yes', ecommerce: { items: [it] } }));
		}
		// Lists of listings: the page's own list at once; other lists (related) when half of one is on screen.
		document.querySelectorAll('ul.aw-orgs').forEach(function (ul) {
			if (!cards(ul).length) return;
			if (!ul.closest('.aw-related') && isDirList) { viewList(ul); return; }
			if (!('IntersectionObserver' in window)) return;
			var io = new IntersectionObserver(function (en) { if (en[0].isIntersecting) { io.disconnect(); viewList(ul); } }, { threshold: 0.3 });
			io.observe(ul);
		});
		// An empty hub list (filters or a search that found nothing).
		var empty = document.querySelector('#main .wp-block-query .kit-empty');
		if (empty && page.page_type !== 'search') {
			var f = new URLSearchParams(location.search);
			['aw_filter', 'paged'].forEach(function (k) { f.delete(k); });
			track('no_results', { hub: page.section || '', term: (qs.get('q') || '').slice(0, 100), filters: f.toString().slice(0, 200), listing_type: page.listing_type || undefined, state: page.state || undefined, district: page.district || undefined });
		}
	});

	document.addEventListener('click', function (e) {
		var el = e.target.closest && e.target.closest('a[href], [data-act]');
		if (!el) return;
		// A listing card in any list: select_item.
		var li = el.closest('ul.aw-orgs > li.wp-block-post');
		if (li) {
			var ul = li.parentNode, list = listOf(ul);
			var item = cardItem(li, offset(ul) + cards(ul).indexOf(li) + 1, list);
			track('select_item', { item_list_id: list.id, item_list_name: list.name, ecommerce: { item_list_id: list.id, item_list_name: list.name, items: [item] } });
			return;
		}
		if (!isProfile || !el.closest('main') || el.closest('.aw-related')) return;
		var pa = profileAction(el);
		if (!pa) return;
		track('listing_action', listingParams({ action: pa.action, area: pa.area, network: pa.network }));
		if (/^(call|whatsapp|email)$/.test(pa.action)) track('generate_lead', listingParams({ lead_source: pa.action }));
	}, true);

	/* ---------- Campaigns ---------- */
	// Every campaign_action push sets the same keys (unused ones undefined), so a value from an earlier push never
	// carries over in GTM's data layer.
	function campaign(action, extra, slug, title) {
		track('campaign_action', Object.assign({ action: action, campaign_slug: slug || page.campaign_slug || '', campaign_title: title || page.campaign_title || '', link_text: undefined, domain: undefined, network: undefined, route: undefined, rule: undefined, ground: undefined, on: undefined, source: undefined }, extra || {}));
	}
	window.awCampaignTrack = campaign; // campaign.js
	function campaignLink(a) {
		var href = a.getAttribute('href') || '', d = domain(href), p = '';
		try { p = new URL(href, location.href).pathname; } catch (x) {}
		var share = a.closest('[data-kit-share], .aw-camp-share, .scriptlesssocialsharing');
		if (share) return { action: 'share', network: a.getAttribute('data-kit-share') || (a.className.match(/\b(whatsapp|facebook|twitter|linkedin|email|telegram)\b/) || [, ''])[1] || d.split('.')[0] };
		if (/^#/.test(href)) return { action: 'section_jump' };
		if (/^tel:/i.test(href)) return { action: 'call' };
		if (/^mailto:/i.test(href)) return { action: 'email' };
		if (/(^|\.)chat\.whatsapp\.com$/.test(d)) return { action: 'whatsapp_group', domain: d };
		if (/(^|\.)(instagram\.com|facebook\.com|x\.com|twitter\.com|youtube\.com|linkedin\.com)$/.test(d)) return { action: 'social_follow', domain: d };
		if (/\.pdf(\?|#|$)/i.test(href)) return { action: 'document_open', domain: d || undefined };
		if (d) return { action: 'outbound', domain: d };
		if (/^\/library\//.test(p)) return { action: 'document_open' };
		if (/^\/resources\//.test(p)) return { action: 'guide_open' };
		if (/^\/news\//.test(p)) return { action: 'news_open' };
		if (/^\/media\//.test(p)) return { action: 'coverage_open' };
		if (/^\/volunteer\//.test(p)) return { action: 'volunteer_click' };
		if (/^\/contact\//.test(p)) return { action: 'contact_click' };
		if (/^\/campaigns\//.test(p)) return { action: 'campaign_link' };
		return { action: 'internal_link' };
	}
	if (page.campaign_slug) {
		ready(function () { track('campaign_view', { campaign_slug: page.campaign_slug, campaign_title: page.campaign_title || '', campaign_status: page.campaign_status || undefined }); });
		document.addEventListener('click', function (e) {
			var a = e.target.closest && e.target.closest('a[href]');
			if (!a || !a.closest('main') || a.closest('.aw-letter, .kit-crumbs')) return; // the letter tool: campaign.js
			var c = campaignLink(a);
			campaign(c.action, { link_text: text(a) || a.getAttribute('aria-label') || '', domain: c.domain, network: c.network });
		}, true);
		// FAQ answers opened by the visitor (a summary clicked while closed; Rank Math's FAQ buttons, kit.js). Not the
		// toggle event: details opened by script at load (folded sections) would count too.
		document.addEventListener('click', function (e) {
			var s = e.target.closest && e.target.closest('main details > summary');
			if (s && !s.parentNode.open && !s.closest('.kit-filter-sheet, .aw-dir-more')) campaign('faq_open', { link_text: text(s) });
		}, true);
		document.addEventListener('click', function (e) {
			var b = e.target.closest && e.target.closest('.rank-math-question button[aria-expanded]');
			if (b && b.getAttribute('aria-expanded') === 'true') campaign('faq_open', { link_text: text(b) });
		});
	}
	// The home band (and any campaign promo elsewhere): data-campaign-slug on the band (campaign-tools.php).
	document.addEventListener('click', function (e) {
		var a = e.target.closest && e.target.closest('[data-campaign-slug] a[href]');
		if (!a || page.campaign_slug) return;
		var band = a.closest('[data-campaign-slug]');
		campaign('promo_click', { link_text: text(a) }, band.getAttribute('data-campaign-slug'), band.getAttribute('data-campaign-title'));
	}, true);

	/* ---------- Money: sponsored blocks and pages, donate links ---------- */
	function sponsored(el) { return { sponsor: el.getAttribute('data-sponsor') || 'sponsored', sponsored_type: el.getAttribute('data-sponsored-type') || '', item: (el.getAttribute('data-sponsored-item') || '').slice(0, 100) }; }
	ready(function () {
		if (page.sponsor) track('sponsored_view', { sponsor: page.sponsor, sponsored_type: page.page_type === 'directory' ? 'listing' : 'page', item: document.title.split(' – ')[0].slice(0, 100) });
		var blocks = document.querySelectorAll('[data-sponsored-type]');
		if (!blocks.length || !('IntersectionObserver' in window)) return;
		Array.prototype.forEach.call(blocks, function (el) {
			var timer;
			var io = new IntersectionObserver(function (en) {
				if (en[0].intersectionRatio >= 0.5) {
					if (!timer) timer = setTimeout(function () { io.disconnect(); track('sponsored_view', sponsored(el)); }, 1000);
				} else { clearTimeout(timer); timer = null; }
			}, { threshold: [0, 0.5] });
			io.observe(el);
		});
	});
	document.addEventListener('click', function (e) {
		var a = e.target.closest && e.target.closest('a[href]');
		if (!a) return;
		var box = a.closest('[data-sponsored-type]');
		if (box) track('sponsored_click', Object.assign(sponsored(box), { domain: domain(a.href) || undefined }));
		var href = a.getAttribute('href') || '';
		if (a.getAttribute('data-act') === 'donate_url' || /^donate\b/i.test(text(a)) || /donat|razorpay\.me|rzp\.io|paypal\.(com|me)|instamojo|ketto\.org|milaap\.org|givedo|^upi:/i.test(href)) {
			track('donate_click', { domain: domain(href) || undefined, link_text: text(a), listing_id: page.listing_id || undefined, listing_name: page.listing_name || undefined });
		}
	}, true);

	/* ---------- Named entities in a summary (entity-links.php) ---------- */
	document.addEventListener('click', function (e) {
		var a = e.target.closest && e.target.closest('a[data-entity]');
		if (!a) return;
		var p = '';
		try { p = new URL(a.href).pathname; } catch (x) {}
		track('select_content', { content_type: 'entity_' + a.getAttribute('data-entity'), content_id: p.slice(0, 100), link_text: text(a) });
	}, true);
})();
