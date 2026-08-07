(function () {
	'use strict';

	var config = window.OpenLinguaShortcodes;
	if (!config || !config.endpoint) return;

	var attributes = ['aria-label', 'alt', 'placeholder', 'title'];
	var cache = new Map();
	var timers = new WeakMap();
	var applying = false;

	function normalized(value) {
		return String(value || '').replace(/\s+/g, ' ').trim();
	}

	function isText(value) {
		return value.length > 0 && value.length <= 1000 && /[A-Za-zÀ-ÖØ-öø-ÿ]/.test(value);
	}

	function collect(root) {
		var entries = [];
		var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
			acceptNode: function (node) {
				var parent = node.parentElement;
				if (!parent || parent.closest('script,style,code,pre,noscript,svg')) return NodeFilter.FILTER_REJECT;
				return isText(normalized(node.nodeValue)) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
			}
		});
		var node;
		while ((node = walker.nextNode())) {
			entries.push({ target: node, kind: 'element-text', text: normalized(node.nodeValue), attribute: '' });
		}
		root.querySelectorAll('*').forEach(function (element) {
			attributes.forEach(function (attribute) {
				var value = normalized(element.getAttribute(attribute));
				if (isText(value)) entries.push({ target: element, kind: 'attribute-' + attribute, text: value, attribute: attribute });
			});
		});
		return entries;
	}

	function apply(entry, value) {
		if (!value || value === entry.text) return;
		applying = true;
		if (entry.attribute) entry.target.setAttribute(entry.attribute, value);
		else entry.target.nodeValue = entry.target.nodeValue.replace(entry.text, value);
		applying = false;
	}

	function scan(root) {
		var shortcode = root.getAttribute('data-openlingua-shortcode');
		var entries = collect(root);
		var pending = [];
		entries.forEach(function (entry) {
			var key = shortcode + '|' + entry.kind + '|' + entry.text;
			if (cache.has(key)) apply(entry, cache.get(key));
			else pending.push({ entry: entry, key: key });
		});
		if (!pending.length) return;

		fetch(config.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: Object.assign({ 'Content-Type': 'application/json' }, config.nonce ? { 'X-WP-Nonce': config.nonce } : {}),
			body: JSON.stringify({ shortcode: shortcode, language: config.language, entries: pending.map(function (item) { return { kind: item.entry.kind, text: item.entry.text }; }) })
		}).then(function (response) { return response.ok ? response.json() : Promise.reject(); }).then(function (data) {
			(data.translations || []).forEach(function (translation, index) {
				var item = pending[index];
				if (!item) return;
				cache.set(item.key, translation);
				apply(item.entry, translation);
			});
		}).catch(function () {});
	}

	function schedule(root) {
		clearTimeout(timers.get(root));
		timers.set(root, setTimeout(function () { scan(root); }, 80));
	}

	function watch(root) {
		schedule(root);
		new MutationObserver(function () { if (!applying) schedule(root); }).observe(root, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: attributes });
	}

	function start() {
		document.querySelectorAll('[data-openlingua-shortcode]').forEach(watch);
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
	else start();
}());
