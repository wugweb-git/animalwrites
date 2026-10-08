/* Animal Writes interactions specific to Animal Writes. Generic behaviours live in Kits (kit.js). */
(function () {
	'use strict';
	/* Directory search: District stays off until a State is chosen, then lists that state's districts; it resets
	   when the State changes. Without JavaScript the server-rendered list works as is. */
	document.querySelectorAll('.aw-dir-search').forEach(function (f) {
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


	/* Contact: the message placeholder follows the chosen reason. */
	document.querySelectorAll('.aw-contact-form').forEach(function (f) {
		var sel = f.querySelector('[name=about]'), msg = f.querySelector('[name=message]');
		if (!sel || !msg) return;
		sel.addEventListener('change', function () { msg.placeholder = sel.selectedOptions[0].dataset.placeholder || ''; });
	});
})();
