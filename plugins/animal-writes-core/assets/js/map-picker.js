/* Map picker for the listing's Latitude / Longitude (wp-admin). Works with the SCF fields and with the fallback box. */
(function () {
	'use strict';
	function field(name) {
		return document.querySelector('.acf-field[data-name="' + name + '"] input') || document.getElementById(name);
	}
	function init() {
		var lat = field('aw_lat'), lng = field('aw_lng');
		if (!lat || !lng || !window.L || document.querySelector('.aw-map-picker')) { return; }
		var host = lng.closest('.acf-field') || lng.closest('tr') || lng.parentNode;
		var box = document.createElement('div');
		box.className = 'aw-map-picker';
		box.setAttribute('role', 'application');
		box.setAttribute('aria-label', 'Map: click or drag the pin to set the latitude and longitude');
		var help = document.createElement('p');
		help.className = 'aw-map-picker-help';
		help.textContent = 'Click the map or drag the pin to fill Latitude and Longitude. Typing in the fields moves the pin. The Google Maps link is not changed.';
		var wrap = document.createElement('div');
		wrap.style.width = '100%';
		wrap.style.clear = 'both';
		wrap.appendChild(box);
		wrap.appendChild(help);
		if (host.closest && host.closest('.acf-fields')) {
			wrap.className = 'acf-field';
			wrap.style.padding = '0 12px 12px';
		}
		if ('TR' === host.tagName) {
			var tr = document.createElement('tr'), td = document.createElement('td');
			td.colSpan = 2;
			td.appendChild(wrap);
			tr.appendChild(td);
			host.parentNode.insertBefore(tr, host.nextSibling);
		} else {
			host.parentNode.insertBefore(wrap, host.nextSibling);
		}
		L.Icon.Default.imagePath = (window.awMapPicker && awMapPicker.icons) || '';
		var map = L.map(box, { scrollWheelZoom: false }).setView([22.5, 79], 5);
		L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
			maxZoom: 19,
			attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors'
		}).addTo(map);
		var pin = null;
		function read() {
			var a = parseFloat(lat.value), b = parseFloat(lng.value);
			return (isFinite(a) && isFinite(b) && Math.abs(a) <= 90 && Math.abs(b) <= 180) ? L.latLng(a, b) : null;
		}
		function write(p) {
			lat.value = p.lat.toFixed(6);
			lng.value = p.lng.toFixed(6);
			[lat, lng].forEach(function (el) { el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); });
		}
		function place(p, zoom) {
			if (!pin) {
				pin = L.marker(p, { draggable: true, keyboard: true, title: 'Listing location' }).addTo(map);
				pin.on('dragend', function () { write(pin.getLatLng()); });
			} else {
				pin.setLatLng(p);
			}
			if (zoom) { map.setView(p, Math.max(map.getZoom(), 16)); }
		}
		var start = read();
		if (start) { place(start, true); }
		map.on('click', function (e) { place(e.latlng, false); write(e.latlng); });
		[lat, lng].forEach(function (el) {
			el.addEventListener('input', function () { var p = read(); if (p) { place(p, true); } });
		});
		// The fields sit in a tab: give the map its size when it becomes visible.
		if (window.ResizeObserver) {
			new ResizeObserver(function () { map.invalidateSize(); var p = read(); if (p && pin) { map.setView(p, map.getZoom()); } }).observe(box);
		}
	}
	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
	if (window.acf && acf.addAction) { acf.addAction('ready', init); }
}());
