/* Kits Core: share and copy link (share.php: the share bar, the page Share button, the phone's share sheet).
   Moved from the Kits theme's kit.js on 18 Nov 2026; loads before it. */
(function () {
	'use strict';

	/* Copy a link: one way for every Copy link and for the page Share button where the browser has no share sheet (one
	   share kit, owner, 13 Nov 2026). The clipboard; else the browser's copy command; else the link is shown in a field
	   beside the button, selected, for the person to copy. Never silent. Resolves true when the link was copied. */
	function copyText(text) {
		function legacy() {
			var ta = document.createElement('textarea'), ok = false;
			ta.value = text;
			ta.setAttribute('readonly', '');
			ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0;';
			document.body.appendChild(ta);
			ta.select();
			try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
			document.body.removeChild(ta);
			return ok;
		}
		if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text).then(function () { return true; }, legacy);
		return Promise.resolve(legacy());
	}
	function copyLink(text, b) {
		return copyText(text).then(function (ok) {
			if (ok) return true;
			var row = b.closest('.kit-share') || b.parentNode, f = row.parentNode.querySelector('.kit-share-field');
			if (!f) {
				f = document.createElement('input');
				f.type = 'text';
				f.readOnly = true;
				f.className = 'kit-share-field';
				f.style.cssText = 'display:block;width:100%;max-width:30rem;margin-top:var(--kit-space-3,.75rem);';
				f.setAttribute('aria-label', b.getAttribute('aria-label') || 'Link');
				row.insertAdjacentElement('afterend', f);
			}
			f.value = text;
			f.focus();
			f.select();
			return false;
		});
	}
	window.kitShareCopy = copyLink; // the campaign letter tool's Copy link uses it too (campaign.js)
	/* A copy done: the button's words (and a Copy link button's icon) turn to "Link copied" for 2 s, and the bar's status
	   line says it, so screen readers hear it. */
	function copied(b) {
		var ico = b.querySelector('.kit-ico--link'), name = b.querySelector('.kit-share-name'), own = b.classList.contains('kit-share-copy');
		var label = b.getAttribute('aria-label'), title = b.title, text = name ? name.textContent : '';
		var live = b.parentNode.querySelector('[role=status]');
		if (ico) ico.classList.replace('kit-ico--link', 'kit-ico--check');
		if (name) name.textContent = b.dataset.done;
		if (own) { b.setAttribute('aria-label', b.dataset.done); b.title = b.dataset.done; }
		if (live) live.textContent = b.dataset.done;
		setTimeout(function () {
			if (ico) ico.classList.replace('kit-ico--check', 'kit-ico--link');
			if (name) name.textContent = text;
			if (own) { b.setAttribute('aria-label', label); b.title = title; }
			if (live) live.textContent = '';
		}, 2000);
	}

	/* Copy link (share bar). */
	document.addEventListener('click', function (e) {
		var b = e.target.closest('.kit-share-copy');
		if (!b || !b.dataset.url) return;
		copyLink(b.dataset.url, b).then(function (ok) { if (ok) copied(b); });
	});

	/* The phone's own share sheet (one share payload per page, kits_share_payload, 13 Nov 2026): the Share button shows
	   only where the browser has navigator.share. It hands over the title, the line with its hashtags, the link and the
	   page's 1080x1080 share picture as a file where the browser can share files (else without it). The picture is
	   fetched once when the row nears the screen, because a phone opens the sheet only straight from a tap. */
	var natives = navigator.share ? document.querySelectorAll('.kit-share-native') : [];
	var sharePics = {};
	function sharePic(u) {
		if (!u || sharePics[u] !== undefined || !window.File || !navigator.canShare) return;
		sharePics[u] = null;
		fetch(u, { credentials: 'omit' }).then(function (r) { return r.ok ? r.blob() : null; }).then(function (bl) {
			if (!bl) return;
			var name = (u.split('/').pop() || 'share').split('?')[0], type = /^image\//.test(bl.type) ? bl.type : 'image/jpeg';
			var ext = ({ 'image/png': 'png', 'image/jpeg': 'jpg', 'image/webp': 'webp', 'image/avif': 'avif' })[type];
			if (ext) name = name.replace(/\.[a-z0-9]+$/i, '') + '.' + ext; // an image service may send another format: the name follows the real type, or apps refuse the file
			var f = new File([bl], name, { type: type });
			if (navigator.canShare({ files: [f] })) sharePics[u] = f;
		}).catch(function () {});
	}
	Array.prototype.forEach.call(natives, function (b) {
		b.hidden = false;
		b.closest('.kit-share').classList.add('has-native');
		if (!b.dataset.image) return;
		if ('IntersectionObserver' in window) {
			var io = new IntersectionObserver(function (es) { if (es[0].isIntersecting) { sharePic(b.dataset.image); io.disconnect(); } }, { rootMargin: '600px 0px' });
			io.observe(b);
		} else sharePic(b.dataset.image);
	});
	document.addEventListener('click', function (e) {
		var b = e.target.closest('.kit-share-native');
		if (!b) return;
		/* A page Share section's one button (data-fallback="copy") where the browser has no share sheet, or the sheet
		   failed: it copies the link, and its words turn to "Link copied" for 2 s. */
		var fallback = function () { if (b.dataset.fallback === 'copy') copyLink(b.dataset.url, b).then(function (ok) { if (ok) copied(b); }); };
		if (!navigator.share) { fallback(); return; }
		var f = b.dataset.image && sharePics[b.dataset.image];
		var plain = { title: b.dataset.title, text: b.dataset.text, url: b.dataset.url };
		// With a picture most apps drop a separate link, so the link also goes in the text.
		navigator.share(f ? { files: [f], title: b.dataset.title, text: b.dataset.text + '\n' + b.dataset.url } : plain).catch(function (err) {
			if (!err || err.name === 'AbortError') return; // closed by the person
			if (f) navigator.share(plain).catch(function (err2) { if (err2 && err2.name !== 'AbortError') fallback(); }); // the app refused the picture: the link alone
			else fallback();
		});
	});

	/* Share bar names: only at lg, and only when the whole row still fits on one line (never two rows). */
	var shares = document.querySelectorAll('.kit-share');
	if (shares.length) {
		var lg = window.matchMedia('(min-width: 1200px)');
		var fit = function () {
			Array.prototype.forEach.call(shares, function (s) {
				s.classList.remove('is-labelled');
				if (!lg.matches) return;
				s.classList.add('is-labelled');
				if (s.scrollWidth > s.clientWidth + 1) s.classList.remove('is-labelled');
			});
		};
		fit();
		var fitT;
		window.addEventListener('resize', function () { clearTimeout(fitT); fitT = setTimeout(fit, 150); }, { passive: true });
	}
})();
