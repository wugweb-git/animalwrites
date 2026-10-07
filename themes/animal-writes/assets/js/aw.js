/* Animal Writes interactions specific to Animal Writes. Generic behaviours live in Kits (kit.js). */
(function () {
	'use strict';
	/* Directory filter: districts follow the chosen state. */
	document.querySelectorAll('.aw-dir-filter').forEach(function (f) {
		var st = f.querySelector('[name=state]'), di = f.querySelector('[name=district]');
		if (!st || !di) return;
		function sync() {
			var v = st.value;
			di.querySelectorAll('optgroup').forEach(function (g) {
				var show = !v || g.dataset.state === v;
				g.hidden = !show; g.disabled = !show;
			});
			var sel = di.selectedOptions[0];
			if (sel && sel.parentNode.disabled) di.value = '';
			di.disabled = !v;
		}
		st.addEventListener('change', sync);
		sync();
	});

})();
