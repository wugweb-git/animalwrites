/* Kits Core: analytics events (analytics.php: window.kitsPage; GTM's dataLayer, GA4 through gtag while Site Kit places it).
   Moved from the Kits theme's kit.js on 18 Nov 2026; loads before it. window.kitsTrack is the one way to send an event. */
/* Menu clicks: one analytics event per click on a header, menu or footer link (GA4 through gtag, or GTM's dataLayer). */
(function () {
	document.addEventListener('click', function (e) {
		var a = e.target.closest && e.target.closest('a[href]');
		if (!a) return;
		var zone = a.closest('[data-menu]');
		var area = zone ? zone.getAttribute('data-menu')
			: a.closest('.kit-menu-secondary') ? 'secondary'
			: a.closest('.wp-block-navigation') && a.closest('header, .kit-site-header, .kit-site-header') ? 'primary' : '';
		if (!area) return;
		var data = { menu_area: area, link_text: (a.textContent || a.getAttribute('aria-label') || '').trim().slice(0, 100), link_url: a.getAttribute('href') };
		(window.dataLayer = window.dataLayer || []).push(Object.assign({ event: 'menu_click' }, data)); // for GTM
		if ((window.kitsPage || {}).gtag && typeof window.gtag === 'function') window.gtag('event', 'menu_click', data); // GA4 through Site Kit, until GA4 runs inside GTM
	}, { capture: true });
})();

/* Analytics events (owner, 9 Oct 2026). Also button_click on every button. Every event goes to the dataLayer (GTM). While GA4 is still placed directly by
   Site Kit (window.kitsPage.gtag), the same event also goes to gtag, so GA4 receives it once either way.
   Events: form_submit_success, form_error, search, pin_search, filter_apply, sort_change, outbound_click, call_click,
   whatsapp_click, email_click, pdf_download, share_click, claim_step, card_click, 404_view, scroll_depth.
   17 Oct 2026: share intent links count as share_click (never whatsapp_click); whatsapp_click only for a chat with a
   number; call/whatsapp/email clicks on a page about one listing carry listing_id and listing_name; card_click counts
   positions among cards only and carries card_id, card_name. Ad slots: the module after this one. */
(function () {
	var page = window.kitsPage || {};
	// params.ecommerce (GA4 items events: view_item, view_item_list, select_item): the previous one is cleared first, as GA4
	// in GTM reads the latest ecommerce object; gtag gets it flattened.
	function track(name, params) {
		params = params || {};
		params.page_type = page.page_type || '';
		var dl = window.dataLayer = window.dataLayer || [];
		if (params.ecommerce) dl.push({ ecommerce: null });
		dl.push(Object.assign({ event: name }, params));
		if (page.gtag && typeof window.gtag === 'function') {
			var g = Object.assign({}, params);
			if (g.ecommerce) { Object.assign(g, g.ecommerce); delete g.ecommerce; }
			window.gtag('event', name, g);
		}
		// Downloads also go to Clarity as a custom event (owner, 12 Nov 2026), so recordings filter on our own pdf_download;
		// the stub queues it until Clarity's script loads, and there is no stub where Clarity is off.
		if (name === 'pdf_download' && typeof window.clarity === 'function') window.clarity('event', name);
	}
	window.kitsTrack = track;
	function formName(form) {
		form = form && form.closest && form.closest('form') || form;
		var named = form && form.closest && form.closest('[data-kit-form]'); // a form from a form plugin, named by its wrapper
		if (named) return named.dataset.kitForm;
		var a = form && form.querySelector('input[name=action]');
		return a ? a.value.replace(/^aw_/, '') : (form && form.id || '');
	}
	document.addEventListener('kit:form-done', function (e) { track('form_submit_success', { form: formName(e.target) }); });
	document.addEventListener('kit:form-error', function (e) { track('form_error', { form: formName(e.target), where: e.detail.where, fields: e.detail.fields }); });
	// Fluent Forms (AJAX): its own success and failure events, on document, with the form in detail.form
	document.addEventListener('fluentform_submission_success', function (e) {
		var res = e.detail && e.detail.response && e.detail.response.data && e.detail.response.data.result;
		if (res && res.redirectUrl && res.redirectUrl.indexOf('/thank-you/') > -1) { try { sessionStorage.setItem('kit-sent', '1'); return; } catch (x) {} } // the thank-you page counts it
		track('form_submit_success', { form: formName(e.detail && e.detail.form) });
	});
	document.addEventListener('fluentform_submission_failed', function (e) { track('form_error', { form: formName(e.detail && e.detail.form), where: 'server', fields: '' }); });
	// Fluent Forms' own check in the browser (a jQuery event): the fields it stopped on
	if (window.jQuery) jQuery(document).on('fluentform_validation_failed', function (e, d) { var f = d && d.form && d.form[0]; track('form_error', { form: formName(f), where: 'browser', fields: d && d.response ? Object.keys(d.response).length : '' }); });
	document.addEventListener('kit:step', function (e) { if (e.target.closest('.aw-claim-form, #claim')) track('claim_step', { step: e.detail.step }); });
	// filter_apply: one event per filter in use (filter, value), plus the whole query (filters) and the hub
	function filters(params, hub) {
		var all = params.toString().slice(0, 200), n = 0;
		params.forEach(function (v, k) {
			if (!v || /^(aw_filter|sort|paged|action|_wpnonce)$/.test(k)) return;
			n++;
			track('filter_apply', { filter: k === 'q' ? 'search' : k, value: v.slice(0, 100), filters: all, hub: hub });
		});
		if (!n) track('filter_apply', { filter: '(none)', value: '', filters: all, hub: hub });
	}
	document.addEventListener('kit:filter', function (e) { filters(new URLSearchParams(e.detail.params), page.section || ''); });

	// The thank-you page ([data-kit-thanks="<form>"]): every form sent from this site that goes there counts here
	// (once per visit to that URL; kit:form-done above counts only forms that show their message in place).
	var qs = new URLSearchParams(location.search);
	var thanks = document.querySelector('[data-kit-thanks]');
	if (thanks && thanks.dataset.kitThanks) {
		var seen = true; // a thank-you URL opened without sending a form here (bookmark, shared link, crawler) is not a send
		try {
			seen = sessionStorage.getItem('kit-sent') !== '1' || sessionStorage.getItem('kit-thanks:' + location.search) === '1';
			sessionStorage.removeItem('kit-sent');
			sessionStorage.setItem('kit-thanks:' + location.search, '1');
			Object.keys(sessionStorage).forEach(function (k) { if (k.indexOf('kit-form:') === 0) sessionStorage.removeItem(k); }); // sent: forget what was typed
		} catch (x) {}
		if (!seen) track('form_submit_success', { form: thanks.dataset.kitThanks });
	}

	// Search results and the PIN code search
	if (page.page_type === 'search' && qs.get('q')) {
		var c = document.querySelector('.aw-search-count');
		track('search', { term: qs.get('q').slice(0, 100), results: c ? parseInt(c.textContent.replace(/[^\d]/g, ''), 10) || 0 : 0 });
	}
	document.addEventListener('submit', function (e) {
		var q = e.target.querySelector && e.target.querySelector('input[name=q]');
		if (q && /^\s*[1-9]\d{5}\s*$/.test(q.value)) track('pin_search', { pin: q.value.trim() });
		if (e.target.matches && e.target.matches('.aw-dir-search, .aw-ed-search')) filters(new URLSearchParams(new FormData(e.target)), 'directory'); // the Directory search, also the homepage's
	}, true);

	// Every button (and link styled as a button): button_click with its label (GTM, owner 9 Oct 2026). A block that sits
	// just above the footer and belongs to it (data-area="footer", the newsletter band) counts as the footer.
	document.addEventListener('click', function (e) {
		var b = e.target.closest && e.target.closest('button, [role=button], .kit-btn, .wp-block-button__link, .wp-element-button, summary.kit-btn, input[type=submit]');
		if (!b || b.closest('.wp-block-post-template li a:not(.kit-btn)')) return;
		var label = (b.getAttribute('aria-label') || b.textContent || b.value || '').replace(/\s+/g, ' ').trim().slice(0, 80);
		track('button_click', { label: label, href: (b.getAttribute('href') || '').slice(0, 200), area: b.closest('header') ? 'header' : b.closest('footer, [data-area=footer]') ? 'footer' : b.closest('form') ? 'form' : 'page' });
	}, true);

	// The network of a share intent URL (WhatsApp/Telegram text share, X, Facebook, LinkedIn, Pinterest, Reddit), or ''.
	function shareIntent(href) {
		if (/(^|\/\/|\.)wa\.me\/\?text=/i.test(href) || /whatsapp\.com\/send\?(?!(.*&)?phone=\d)/i.test(href) || /^whatsapp:\/\/send/i.test(href)) return 'whatsapp';
		if (/(twitter|x)\.com\/intent\//i.test(href)) return 'x';
		if (/facebook\.com\/sharer/i.test(href)) return 'facebook';
		if (/linkedin\.com\/(sharing|shareArticle)/i.test(href)) return 'linkedin';
		if (/t\.me\/share/i.test(href)) return 'telegram';
		if (/pinterest\.[a-z.]+\/pin\/create/i.test(href)) return 'pinterest';
		if (/reddit\.com\/submit/i.test(href)) return 'reddit';
		return '';
	}

	// Clicks
	document.addEventListener('click', function (e) {
		if (e.target.closest && e.target.closest('.kit-share-native')) { track('share_click', { network: 'native' }); return; } // the phone's share sheet
		var a = e.target.closest && e.target.closest('a[href]');
		if (!a) return;
		var href = a.getAttribute('href') || '';
		if (a.closest('.kit-dd--sort, .kit-segment') || a.classList.contains('kit-dd-opt') && a.closest('[data-kit-sort], .kit-sort')) { track('sort_change', { value: a.textContent.trim().slice(0, 60) }); return; }
		// share buttons first: their WhatsApp and email buttons are shares, not contact clicks
		var share = a.closest('[data-kit-share]'); // a link marked data-kit-share="network" is a share too
		if (share) { track('share_click', { network: a.dataset.kitShare || (a.className.match(/\b(whatsapp|facebook|twitter|linkedin|email|pinterest|reddit|telegram)\b/) || [, 'other'])[1] }); return; }
		// a share link outside a share row (a "share on WhatsApp/X/Facebook/LinkedIn" intent URL) is a share too, not a contact
		var intent = shareIntent(href);
		if (intent) { track('share_click', { network: intent }); return; }
		// contact clicks on an item page about one organisation (a Directory profile) carry it: listing_id, listing_name
		var who = page.listing_id && a.closest('main') && !a.closest('.wp-block-post-template') ? { listing_id: page.listing_id, listing_name: page.listing_name || '' } : {};
		if (/^tel:/i.test(href)) { track('call_click', Object.assign({ number: href.slice(4) }, who)); return; }
		// WhatsApp contact = a chat with a number (wa.me/<number>, api.whatsapp.com/send?phone=); a group invite
		// (chat.whatsapp.com) or a channel is not a contact and counts as an outbound click below.
		if (/(^|\/\/|\.)wa\.me\/\+?\d/i.test(href) || /whatsapp\.com\/send\?(.*&)?phone=\d/i.test(href)) { track('whatsapp_click', who); return; }
		if (/^mailto:/i.test(href)) { track('email_click', who); return; }
		if (/\.pdf(\?|#|$)/i.test(href) || a.hasAttribute('download')) { track('pdf_download', { item: document.title.split(' – ')[0].slice(0, 100), issuer: page.issuer || '' }); }
		var li = a.closest('.wp-block-post-template > li');
		if (li) {
			// position among the cards; the card's post ID and title
			var cards = Array.prototype.slice.call(li.parentNode.children);
			var title = li.querySelector('.wp-block-post-title, h2, h3');
			track('card_click', { list: page.section || page.page_type || '', position: cards.indexOf(li) + 1, card_id: ((li.className.match(/\bpost-(\d+)\b/) || [])[1] || ''), card_name: title ? title.textContent.replace(/\s+/g, ' ').trim().slice(0, 100) : '' });
		}
		try {
			var u = new URL(href, location.href);
			if (/^https?:$/.test(u.protocol) && u.host !== location.host) track('outbound_click', { domain: u.host.replace(/^www\./, '') });
		} catch (x) {}
	}, true);

	// 404
	if (document.body.classList.contains('error404')) track('404_view', { path: location.pathname.slice(0, 200) });

	// Scroll depth: 25, 50, 75, 90 % (once each)
	var marks = [25, 50, 75, 90], hit = {}, tick = false;
	window.addEventListener('scroll', function () {
		if (tick) return; tick = true;
		requestAnimationFrame(function () {
			tick = false;
			var h = document.documentElement.scrollHeight - window.innerHeight;
			if (h < 200) return;
			var pct = window.scrollY / h * 100;
			marks.forEach(function (m) { if (pct >= m && !hit[m]) { hit[m] = 1; track('scroll_depth', { percent: m }); } });
		});
	}, { passive: true });
})();
