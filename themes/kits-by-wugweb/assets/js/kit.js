/* Kits by Wugweb interactions. No dependencies. Respects prefers-reduced-motion.
 * Each behaviour answers to .kit-* and to the older .aw-* names. Share, forms and analytics events are Kits Core's
 * (plugins/kits-core/assets/js), which load before this file when Kits Core is active. */
(function () {
	'use strict';
	var doc = document.documentElement;

	/* Smooth in-page scrolling (kit.css html.kit-smooth) from the first pointer or key press on, never for the jump to
	   a #fragment while the page loads: an animated load-time jump lands short when content above it settles. */
	var smooth = function () { doc.classList.add('kit-smooth'); };
	['pointerdown', 'keydown'].forEach(function (t) { window.addEventListener(t, smooth, { once: true, passive: true, capture: true }); });

	/* 2. Sticky help bar: hide while scrolling down, show on scroll up or near the top. */
	var bar = document.querySelector('.kit-sticky-help, .kit-sticky-help');
	if (bar) {
		var lastY = window.scrollY;
		window.addEventListener('scroll', function () {
			var y = window.scrollY;
			if (Math.abs(y - lastY) < 8) return; // ignore jitter
			bar.classList.toggle('is-hidden', y > lastY && y > 240);
			lastY = y;
		}, { passive: true });
		bar.addEventListener('focusin', function () { bar.classList.remove('is-hidden'); });
	}

	/* Share and copy link, and form behaviour: Kits Core (assets/js/share.js, forms.js), since 18 Nov 2026. */
})();

/* Header search (every width): the icon opens a field across the whole header row; the menu and contact step aside.
   Esc, the close button or a click outside closes it and puts focus back on the icon. Without JavaScript the icon is a
   link to the search page. */
(function () {
	document.querySelectorAll('[data-search-toggle]').forEach(function (btn) {
		var form = document.getElementById(btn.getAttribute('aria-controls'));
		var header = btn.closest('.kit-site-header');
		if (!form || !header) return;
		var input = form.querySelector('input');
		var close = form.querySelector('.kit-hsearch-close');
		if (!close) {
			close = document.createElement('button');
			close.type = 'button';
			close.className = 'kit-hsearch-close';
			close.setAttribute('aria-label', 'Close search');
			close.innerHTML = '<span class="kit-ico kit-ico--x" aria-hidden="true"></span>';
			form.appendChild(close);
		}
		function set(open, back) {
			form.hidden = !open;
			header.classList.toggle('is-searching', open);
			btn.setAttribute('aria-expanded', open ? 'true' : 'false');
			if (open) input.focus(); else if (back) btn.focus();
		}
		btn.addEventListener('click', function (e) { e.preventDefault(); set(form.hidden); });
		close.addEventListener('click', function () { set(false, true); });
		form.addEventListener('keydown', function (e) { if (e.key === 'Escape') set(false, true); });
		document.addEventListener('click', function (e) { if (!form.hidden && !form.contains(e.target) && e.target !== btn && !btn.contains(e.target)) set(false); });
	});
})();

/* Sticky nav: the announcement bar scrolls away, the nav stays at the top (its height is measured, as it can wrap). */
(function () {
	var bar = document.querySelector('.kit-announce');
	function measure() { document.documentElement.style.setProperty('--kit-announce-h', (bar ? bar.offsetHeight : 0) + 'px'); }
	measure();
	window.addEventListener('resize', measure, { passive: true });
})();

/* Mobile menu: the page behind does not move (position: fixed, scroll restored — iOS ignores overflow: hidden), and the
   close button sits exactly where the open button was. */
(function () {
	var html = document.documentElement, y = 0;
	var openBtn = document.querySelector('.kit-site-header .wp-block-navigation__responsive-container-open');
	new MutationObserver(function () {
		var open = html.classList.contains('has-modal-open');
		if (open && !html.classList.contains('kit-scroll-locked')) {
			y = window.scrollY;
			if (openBtn) {
				var r = openBtn.getBoundingClientRect();
				html.style.setProperty('--kit-toggle-top', r.top + 'px');
				html.style.setProperty('--kit-toggle-left', r.left + 'px');
			}
			document.body.style.top = -y + 'px';
			html.classList.add('kit-scroll-locked');
		} else if (!open && html.classList.contains('kit-scroll-locked')) {
			html.classList.remove('kit-scroll-locked');
			document.body.style.top = '';
			window.scrollTo(0, y);
		}
	}).observe(html, { attributes: true, attributeFilter: ['class'] });
})();

/* Page scroll flow: main lists its sections in order; each top-level section gets data-scroll-index 1…n. */
(function () {
	var main = document.getElementById('main');
	if (!main) return;
	var root = main.querySelector(':scope > .wp-block-post-content') || main;
	var ids = [];
	Array.prototype.forEach.call(root.querySelectorAll(':scope > section, :scope > .alignfull, :scope > header, :scope > .wp-block-group'), function (s, i) {
		s.setAttribute('data-scroll-index', i + 1);
		if (s.id) ids.push(s.id);
	});
	main.setAttribute('data-scroll-flow', ids.join(' '));
})();

/* Filters (kits_filter_bar): below 768px the filters fold into a "Filters" sheet (focus kept inside, Esc closes). */
(function () {
	var phone = window.matchMedia('(max-width: 767px)');
	document.querySelectorAll('.kit-filter-sheet').forEach(function (sheet) {
		var summary = sheet.querySelector('summary');
		function layout() {
			sheet.classList.toggle('is-sheet', phone.matches);
			if (phone.matches) sheet.removeAttribute('open'); else sheet.setAttribute('open', '');
		}
		layout();
		phone.addEventListener('change', layout);
		sheet.addEventListener('toggle', function () {
			if (!sheet.classList.contains('is-sheet')) return;
			if (sheet.open) {
				var first = sheet.querySelector('.kit-filters summary, .kit-filters a, .kit-filters button, .kit-filters input');
				if (first) first.focus();
			}
		});
		// The page behind stays still while the sheet is open; a tap on the dimmed page or a swipe down closes it.
		sheet.addEventListener('toggle', function () {
			var on = sheet.classList.contains('is-sheet') && sheet.open;
			document.documentElement.classList.toggle('kit-sheet-open', on);
		});
		sheet.addEventListener('click', function (e) {
			if (sheet.classList.contains('is-sheet') && sheet.open && e.target === sheet) { sheet.open = false; summary.focus(); }
		});
		var panel = sheet.querySelector('.kit-filters'), y0 = null;
		if (panel) {
			panel.addEventListener('touchstart', function (e) { y0 = panel.scrollTop <= 0 ? e.touches[0].clientY : null; }, { passive: true });
			panel.addEventListener('touchend', function (e) {
				if (y0 !== null && e.changedTouches[0].clientY - y0 > 80 && sheet.open) { sheet.open = false; summary.focus(); }
				y0 = null;
			}, { passive: true });
		}
		sheet.addEventListener('keydown', function (e) {
			if (!sheet.classList.contains('is-sheet') || !sheet.open) return;
			if (e.key === 'Escape') { sheet.open = false; summary.focus(); return; }
			if (e.key !== 'Tab') return;
			var f = Array.prototype.filter.call(sheet.querySelectorAll('.kit-filters summary, .kit-filters a, .kit-filters button, .kit-filters input'), function (el) { return el.offsetParent !== null; });
			if (!f.length) return;
			if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
			else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
		});
	});
})();

/* External links: every link to another site that has words gets an arrow (CSS) and a screen-reader note; icon-only links,
   buttons (they carry their own icon) and links that already show an arrow are left as they are. */
(function () {
	function mark(root) {
		root.querySelectorAll('a[href^="http"]:not(.kit-link-ext)').forEach(function (a) {
			if (a.host === location.host || !a.textContent.trim() || a.querySelector('.kit-ico--arrow-up-right') || a.closest('.wp-block-navigation, .kit-share, .kit-socials') || a.matches('.kit-btn, .wp-block-button__link')) {
				return;
			}
			a.classList.add('kit-link-ext');
			var sr = document.createElement('span');
			sr.className = 'screen-reader-text';
			sr.textContent = ' (external site)';
			a.appendChild(sr);
		});
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { mark(document); });
	} else {
		mark(document);
	}
})();

/* Folded sections (data-kit-fold): open on wide screens, folded at or below the width given (default 1199px), unless
   data-kit-keep-open (e.g. filters in use); open without JavaScript. A link to the fold, to a block around it or to
   something in it opens it. */
(function () {
	function targeted(d) {
		var t = null;
		try { t = location.hash.length > 1 && document.getElementById(decodeURIComponent(location.hash.slice(1))); } catch (e) { t = null; }
		return !!t && (t.contains(d) || d.contains(t));
	}
	document.querySelectorAll('details[data-kit-fold]').forEach(function (d) {
		var mq = window.matchMedia('(max-width: ' + (parseInt(d.getAttribute('data-kit-fold'), 10) || 1199) + 'px)');
		function apply() { d.open = !mq.matches || d.hasAttribute('data-kit-keep-open') || targeted(d); }
		apply();
		if (mq.addEventListener) mq.addEventListener('change', apply);
		window.addEventListener('hashchange', function () { if (targeted(d)) d.open = true; });
	});
})();

/* Filter bar: ticking a filter applies it at once, except in the phone sheet, where "Apply" sends them together.
   Several values of one filter go as category=a,b. (Opening and closing the dropdowns: kits/dropdown, Interactivity API.) */
(function () {
	function go(form) {
		var params = new URLSearchParams(), lists = {};
		Array.prototype.forEach.call(form.elements, function (el) {
			if (!el.name || el.disabled) return;
			if (el.type === 'checkbox') {
				if (!el.checked) return;
				var n = el.name.replace(/\[\]$/, '');
				(lists[n] = lists[n] || []).push(el.value);
			} else if (el.type === 'radio') { // one choice (e.g. Sort); an empty value is the default and stays out of the URL
				if (el.checked && el.value !== '') params.set(el.name, el.value);
			} else if (el.type === 'hidden') {
				params.set(el.name, el.value);
			}
		});
		Object.keys(lists).forEach(function (n) { params.set(n, lists[n].join(',')); });
		var status = form.parentNode.querySelector('[data-kit-filter-status]');
		if (status) status.textContent = 'Loading results';
		var q = params.toString();
		form.dispatchEvent(new CustomEvent('kit:filter', { bubbles: true, detail: { params: q } }));
		window.location.href = form.getAttribute('action').split('?')[0] + (q ? '?' + q : '');
	}
	document.querySelectorAll('form[data-kit-filterbar]').forEach(function (form) {
		form.addEventListener('submit', function (e) { e.preventDefault(); go(form); });
		form.addEventListener('change', function (e) {
			if (e.target.classList.contains('kit-dd-check') && !e.target.closest('.is-sheet, .kit-dd--menu')) go(form); // the Filters menu waits for Apply
		});
	});
})();

/* Motion: scroll-in (transform and opacity only; nothing under prefers-reduced-motion). The browser's own animations
   (element.animate), no library (owner, 2 Nov 2026: GSAP removed).
   The first two groups (page header or hero, first content section) are shown at once, never faded in: they hold the
   page's largest text or picture, and hiding them until this script ran delayed LCP by 1-2 s on phones (owner, 20 Oct
   2026). Later sections rise in as they scroll into view. Elements can opt in with data-reveal. */
(function () {
	if (!('IntersectionObserver' in window) || !Element.prototype.animate || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
	var main = document.getElementById('main');
	if (!main || main.classList.contains('kit-static')) return; // a template can opt out: its sections are always visible
	var root = main.querySelector(':scope > .wp-block-post-content') || main;
	var sections = Array.prototype.slice.call(root.querySelectorAll(':scope > section, :scope > header, :scope > .alignfull, [data-reveal]'));
	var ease = 'cubic-bezier(.2, .9, .3, 1.2)'; // a light spring (was GSAP back.out(1.2))
	sections.forEach(function (s, i) {
		if (i < 2) return; // above the fold: shown as rendered
		s.style.opacity = '0';
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (e) {
				if (e.isIntersecting) {
					io.disconnect();
					s.style.opacity = '';
					s.animate([{ opacity: 0, transform: 'translateY(24px)' }, { opacity: 1, transform: 'none' }], { duration: 600, easing: ease });
				} else if (e.boundingClientRect.bottom < 0) { // jumped past (anchor link, fast scroll): show it, no animation
					io.disconnect();
					s.style.opacity = '';
				}
			});
		}, { rootMargin: '0px' });
		io.observe(s);
	});
	// Print shows every section, whether or not it has scrolled into view.
	window.addEventListener('beforeprint', function () {
		sections.forEach(function (s) { s.getAnimations().forEach(function (a) { a.cancel(); }); s.style.opacity = ''; });
	});
})();


/* Header hides while you scroll down and comes back as soon as you scroll up (owner, 9 Oct 2026). It stays while the
   menu or the search is open, near the top of the page, or when focus is inside it. */
(function () {
	var bar = document.querySelector('header.wp-block-template-part');
	if (!bar || !bar.querySelector('.kit-site-header')) return;
	var last = window.scrollY, ticking = false;
	function busy() {
		return document.documentElement.classList.contains('has-modal-open') || bar.querySelector('.is-searching, .is-menu-open') || bar.contains(document.activeElement) && document.activeElement.matches(':focus-visible'); // keyboard users keep it
	}
	function update() {
		var y = window.scrollY, h = bar.offsetHeight;
		if (y <= h || busy()) bar.classList.remove('kit-header-hidden');
		else if (y > last + 4) bar.classList.add('kit-header-hidden');
		else if (y < last - 4) bar.classList.remove('kit-header-hidden');
		last = y; ticking = false;
	}
	window.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(update); } }, { passive: true });
	bar.addEventListener('focusin', function (e) { if (e.target.matches(':focus-visible')) bar.classList.remove('kit-header-hidden'); });
	// Back/forward cache: a page restored after a menu tap keeps focus on the menu button and can show its focus ring
	// with no keyboard use; drop that stale focus when the page is shown again.
	window.addEventListener('pageshow', function (e) { var a = document.activeElement; if (e.persisted && a && bar.contains(a)) a.blur(); });
})();


/* Item page sidebar: sticky under the header at lg only when it is shorter than the screen (a taller one scrolls
   with the page, so nothing in it is out of reach). */
(function () {
	var side = document.querySelector('.kit-with-sidebar > .kit-sidebar');
	if (!side) return;
	function check() {
		var top = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--kit-header-height')) || 75;
		side.classList.toggle('is-sticky', side.offsetHeight > 0 && side.offsetHeight + top + 48 < window.innerHeight);
	}
	check();
	window.addEventListener('resize', check, { passive: true });
	if (window.ResizeObserver) new ResizeObserver(check).observe(side);
})();

/* Rank Math FAQ: every answer shows, with no "+" to open or close it (owner, 13 Nov 2026: no expand and collapse on any
   page). The question stays a heading and the FAQ schema is untouched. */

/* FAQ (kit-faq): every answer shows, with no "+" (owner, 13 Nov 2026: no expand and collapse on any page). The server
   draws each question as a button (its FAQ schema reads it); here the button gives way to its words, so the question is
   a plain heading over its answer. */
(function () {
	document.querySelectorAll('.kit-faq-btn').forEach(function (b) {
		var a = document.getElementById(b.getAttribute('aria-controls'));
		if (a) a.hidden = false;
		var q = b.parentNode;
		while (b.firstChild) q.insertBefore(b.firstChild, b);
		q.removeChild(b);
	});
})();

/* No folds (owner, 13 Nov 2026: "remove all + sign to expand collapse from all campaigns and all pages"): every fold that
   drew a "+" (the Details block, claim and card folds) stays open and its summary no longer closes it. Folds marked with
   a chevron (footer menus, filters) keep working. */
(function () {
	var SEL = 'details.wp-block-details, .kit-claim details';
	document.querySelectorAll(SEL).forEach(function (d) { d.open = true; });
	document.addEventListener('click', function (e) {
		var s = e.target.closest && e.target.closest('summary');
		if (s && s.parentNode && s.parentNode.matches && s.parentNode.matches(SEL)) { e.preventDefault(); s.parentNode.open = true; }
	});
})();

/* Card titles and summaries (kit-card states): cut at a whole word with "…" when they run past their lines (the CSS
   line clamp alone can cut inside a word). The full title stays the link's accessible name. Chips that do not fit on
   the one row are hidden from screen readers too. Runs again when a card changes width. */
(function () {
	var els = document.querySelectorAll('.kit-card[class*="kit-card--"] .kit-card-title a, .kit-card[class*="kit-card--"] .kit-card-sum .wp-block-post-excerpt__excerpt');
	if (!els.length) return;
	var textNode = function (el) { for (var n = el.firstChild; n; n = n.nextSibling) if (n.nodeType === 3 && n.nodeValue.trim()) return n; return null; };
	var fit = function (el) {
		var t = textNode(el);
		if (!t) return;
		if (el.dataset.full == null) el.dataset.full = t.nodeValue;
		var full = el.dataset.full, words = full.trim().split(/\s+/);
		t.nodeValue = full;
		if (el.scrollHeight <= el.clientHeight + 1) return;
		if (el.tagName === 'A' && !el.hasAttribute('aria-label')) el.setAttribute('aria-label', full.trim());
		var lo = 1, hi = words.length - 1, best = 1;
		while (lo <= hi) {
			var mid = (lo + hi) >> 1;
			t.nodeValue = words.slice(0, mid).join(' ').replace(/[,;:–—-]$/, '') + '… ';
			if (el.scrollHeight <= el.clientHeight + 1) { best = mid; lo = mid + 1; } else hi = mid - 1;
		}
		t.nodeValue = words.slice(0, best).join(' ').replace(/[,;:–—-]$/, '') + '… ';
	};
	var chips = function (card) {
		card.querySelectorAll('.kit-card-chips').forEach(function (row) {
			var top = row.getBoundingClientRect().top;
			Array.prototype.forEach.call(row.children, function (c) { c.toggleAttribute('aria-hidden', c.getBoundingClientRect().top - top > 4); });
		});
	};
	var run = function () { els.forEach(fit); document.querySelectorAll('.kit-card[class*="kit-card--"]').forEach(chips); };
	run();
	if (document.fonts && document.fonts.ready) document.fonts.ready.then(run);
	var w = window.innerWidth, timer;
	window.addEventListener('resize', function () { if (window.innerWidth === w) return; w = window.innerWidth; clearTimeout(timer); timer = setTimeout(run, 150); });
})();
