/**
 * UltraCache Pro — Script Manager editor panel.
 *
 * Native Gutenberg document-settings panel (no build step) that lists the
 * scripts and styles actually loaded on this page, grouped by their source
 * plugin/theme, and lets the editor disable any handle for this page only.
 * Selection is saved as normal post meta (_ucp_sm_disabled) on post save.
 */
(function (wp) {
	'use strict';

	if (!wp || !wp.plugins || !wp.element || !wp.components || !wp.data) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var registerPlugin = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel =
		(wp.editor && wp.editor.PluginDocumentSettingPanel) ||
		(wp.editPost && wp.editPost.PluginDocumentSettingPanel);
	var PanelBody = wp.components.PanelBody;
	var ToggleControl = wp.components.ToggleControl;
	var Spinner = wp.components.Spinner;
	var Notice = wp.components.Notice;
	var ExternalLink = wp.components.ExternalLink;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var apiFetch = wp.apiFetch;
	var __ = (wp.i18n && wp.i18n.__) || function (s) { return s; };
	var sprintf = (wp.i18n && wp.i18n.sprintf) || function (s) { return s; };

	if (!PluginDocumentSettingPanel) {
		return; // Classic editor / unsupported screen.
	}

	var META_KEY = '_ucp_sm_disabled';

	function normalizeDisabled(meta) {
		var d = (meta && meta[META_KEY]) || {};
		return {
			scripts: Array.isArray(d.scripts) ? d.scripts.slice() : [],
			styles: Array.isArray(d.styles) ? d.styles.slice() : []
		};
	}

	function groupBySource(map) {
		var groups = {};
		Object.keys(map || {}).forEach(function (handle) {
			var source = (map[handle] && map[handle].source) || __('Overig', 'ultracache-pro');
			if (!groups[source]) {
				groups[source] = [];
			}
			groups[source].push(handle);
		});
		return groups;
	}

	function Panel() {
		var postId = useSelect(function (select) {
			return select('core/editor').getCurrentPostId();
		}, []);
		var meta = useSelect(function (select) {
			return select('core/editor').getEditedPostAttribute('meta') || {};
		}, []);
		var editPost = useDispatch('core/editor').editPost;

		var stateInventory = useState({ scripts: {}, styles: {}, updated_at: 0 });
		var inventory = stateInventory[0];
		var setInventory = stateInventory[1];
		var stateLoading = useState(true);
		var loading = stateLoading[0];
		var setLoading = stateLoading[1];
		var statePreview = useState('');
		var previewUrl = statePreview[0];
		var setPreviewUrl = statePreview[1];

		useEffect(function () {
			if (!postId || !window.UCPScriptManager) {
				setLoading(false);
				return;
			}
			setLoading(true);
			apiFetch({ path: 'ultracache-pro/v1/script-manager/' + postId })
				.then(function (res) {
					if (res && res.inventory) {
						setInventory(res.inventory);
					}
					if (res && res.preview_url) {
						setPreviewUrl(res.preview_url);
					}
					setLoading(false);
				})
				.catch(function () {
					setLoading(false);
				});
		}, [postId]);

		var disabled = normalizeDisabled(meta);

		function isDisabled(kind, handle) {
			return disabled[kind].indexOf(handle) !== -1;
		}

		function toggleHandle(kind, handle, nextLoaded) {
			var next = normalizeDisabled(meta);
			var idx = next[kind].indexOf(handle);
			if (nextLoaded && idx !== -1) {
				next[kind].splice(idx, 1);
			} else if (!nextLoaded && idx === -1) {
				next[kind].push(handle);
			}
			var newMeta = {};
			newMeta[META_KEY] = next;
			editPost({ meta: newMeta });
		}

		function renderGroupedKind(kind, label) {
			var map = inventory[kind] || {};
			var handles = Object.keys(map);
			if (!handles.length) {
				return el('p', { className: 'ucp-sm-empty' }, sprintf(__('Geen %s gedetecteerd op deze pagina.', 'ultracache-pro'), label));
			}
			var groups = groupBySource(map);
			return Object.keys(groups).sort().map(function (source) {
				return el(
					PanelBody,
					{ key: kind + '-' + source, title: source + ' (' + groups[source].length + ')', initialOpen: false },
					groups[source].sort().map(function (handle) {
						return el(ToggleControl, {
							key: kind + '-' + handle,
							label: handle,
							checked: !isDisabled(kind, handle),
							help: map[handle].src ? map[handle].src : __('Inline / dynamisch', 'ultracache-pro'),
							onChange: function (val) { toggleHandle(kind, handle, val); }
						});
					})
				);
			});
		}

		var hasInventory = inventory &&
			((inventory.scripts && Object.keys(inventory.scripts).length) ||
			 (inventory.styles && Object.keys(inventory.styles).length));

		return el(
			PluginDocumentSettingPanel,
			{ name: 'ucp-script-manager', title: __('UltraCache — Scripts', 'ultracache-pro'), className: 'ucp-script-manager-panel' },
			el('p', { className: 'ucp-sm-intro' }, __('Schakel afzonderlijke scripts of stijlen uit voor alleen deze pagina. Uit = niet laden.', 'ultracache-pro')),
			loading
				? el('div', { style: { padding: '8px 0' } }, el(Spinner, null))
				: !hasInventory
					? el(
						Fragment,
						null,
						el(Notice, { status: 'info', isDismissible: false },
							__('Nog geen assets gemeten. Bekijk deze pagina eenmaal als beheerder op de voorkant; UltraCache legt dan de geladen handles vast.', 'ultracache-pro')),
						previewUrl ? el(ExternalLink, { href: previewUrl }, __('Open de voorkant van deze pagina', 'ultracache-pro')) : null
					)
					: el(
						Fragment,
						null,
						el('strong', { className: 'ucp-sm-heading' }, __('Scripts', 'ultracache-pro')),
						renderGroupedKind('scripts', __('scripts', 'ultracache-pro')),
						el('strong', { className: 'ucp-sm-heading' }, __('Stijlen', 'ultracache-pro')),
						renderGroupedKind('styles', __('stijlen', 'ultracache-pro'))
					)
		);
	}

	registerPlugin('ucp-script-manager', { render: Panel });
})(window.wp);
