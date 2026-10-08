/* Animal Writes interactions specific to Animal Writes. Generic behaviours live in Kits (kit.js). */
(function () {
	'use strict';
	/* Directory search: District stays off until a State is chosen, then lists that state's districts; it resets
	   when the State changes. Without JavaScript the server-rendered list works as is. */
	document.querySelectorAll('.aw-dir-search, form.kit-form').forEach(function (f) {
		var st = f.querySelector('[name=state]'), di = f.querySelector('[name=district]');
		if (!st || !di) return;
		var first = di.options[0];
		function sync(reset) {
			var v = st.value;
			di.querySelectorAll('optgroup').forEach(function (g) {
				var show = !!v && g.dataset.state === v;
				g.hidden = !show; g.disabled = !show;
			});
			if (reset) di.value = '';
			di.disabled = !v;
			first.textContent = v ? first.dataset.all : first.dataset.none;
		}
		st.addEventListener('change', function () { sync(true); });
		sync(false);
	});


	/* PIN code (forms with State and District): six digits fill State and District from India Post, matched to the
	   LGD list; if the PIN is not found, the visitor chooses them. */
	document.querySelectorAll('input[data-pin-lookup]').forEach(function (pin) {
		var f = pin.form, st = f && f.querySelector('select[name=state]'), di = f && f.querySelector('select[name=district]');
		if (!st) return;
		var msg = document.getElementById(pin.id + '-error'), last = '';
		pin.addEventListener('input', function () {
			var v = pin.value.replace(/\D/g, '');
			if (v.length !== 6 || v === last) return;
			last = v;
			fetch(f.action.replace('admin-post.php', 'admin-ajax.php') + '?action=aw_pin&pin=' + v, { credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (r) {
					if (!r.success) { if (msg) msg.textContent = r.data; return; }
					if (msg) msg.textContent = '';
					st.value = r.data.state_slug; st.dispatchEvent(new Event('change', { bubbles: true }));
					if (di && r.data.district_slug) { di.value = r.data.district_slug; di.dispatchEvent(new Event('change', { bubbles: true })); }
				})
				.catch(function () { if (msg) msg.textContent = 'Couldn\'t find this PIN. Choose your state and district instead.'; });
		});
	});

	/* Contact: the message placeholder follows the chosen reason. */
	document.querySelectorAll('.aw-contact-form').forEach(function (f) {
		var sel = f.querySelector('[name=about]'), msg = f.querySelector('[name=message]');
		if (!sel || !msg) return;
		sel.addEventListener('change', function () { msg.placeholder = sel.selectedOptions[0].dataset.placeholder || ''; });
	});
})();
