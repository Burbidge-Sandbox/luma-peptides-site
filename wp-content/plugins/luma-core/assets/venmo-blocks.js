/* Registers the Venmo (manual) method with the block checkout. No build step. */
(function () {
	var reg = window.wc && window.wc.wcBlocksRegistry, set = window.wc && window.wc.wcSettings, el = window.wp && window.wp.element;
	if (!reg || !set || !el) return;
	var h = el.createElement, dec = (window.wp.htmlEntities && window.wp.htmlEntities.decodeEntities) || function (s) { return s; };
	var s = set.getSetting('luma_venmo_data', {});
	var title = dec(s.title || 'Venmo');
	var Content = function () { return h('div', { className: 'luma-venmo-desc' }, dec(s.description || '')); };
	var Label = function (props) {
		var L = props && props.components && props.components.PaymentMethodLabel;
		return L ? h(L, { text: title }) : h('span', null, title);
	};
	reg.registerPaymentMethod({
		name: 'luma_venmo',
		label: h(Label, null),
		content: h(Content, null),
		edit: h(Content, null),
		canMakePayment: function () { return true; },
		ariaLabel: title,
		supports: { features: s.supports || ['products'] }
	});
})();
