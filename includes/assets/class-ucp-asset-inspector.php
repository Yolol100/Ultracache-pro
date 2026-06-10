<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Front-end asset inspector (Perfmatters-style "Script Manager", read-only).
 *
 * For administrators only, and only when the page URL carries ?ucp-assets, it renders a small
 * overlay listing every script and style handle printed on the current page together with a
 * ready-to-paste conditional-unload rule for each. Those rules use the exact
 * "<path> => handle" syntax that conditional_script_unloads / conditional_style_unloads already
 * honour, so the admin copies what they want into the Asset Manager and the existing (tested)
 * per-page unload engine does the work.
 *
 * Deliberately read-only: it adds no REST/AJAX write endpoint, so it introduces no new attack
 * surface. It never renders for visitors and is gated behind ?ucp-assets, so it can never end up
 * in a cached guest page. The feature flag (enable_asset_inspector) defaults OFF.
 */
class UCP_Asset_Inspector {

    public function __construct() {
        if (!is_admin()) {
            add_action('wp_footer', array($this, 'maybe_render'), 99999);
        }
    }

    public function maybe_render() {
        if (!$this->should_show()) {
            return;
        }

        global $wp_scripts, $wp_styles;
        $scripts = $this->collect($wp_scripts);
        $styles  = $this->collect($wp_styles);
        $path    = $this->current_path();

        echo $this->render_overlay($path, $scripts, $styles); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- assembled below with per-field escaping.
    }

    private function should_show() {
        if (!isset($_GET['ucp-assets'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin diagnostic toggle, no state change.
            return false;
        }
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return false;
        }
        return true;
    }

    /**
     * Collect printed + queued handles from a WP_Dependencies registry.
     *
     * @param mixed $registry WP_Scripts|WP_Styles|null
     * @return array<int,array{handle:string,src:string}>
     */
    private function collect($registry) {
        if (!is_object($registry) || empty($registry->registered)) {
            return array();
        }
        $handles = array();
        if (!empty($registry->done) && is_array($registry->done)) {
            $handles = array_merge($handles, $registry->done);
        }
        if (!empty($registry->queue) && is_array($registry->queue)) {
            $handles = array_merge($handles, $registry->queue);
        }
        $handles = array_values(array_unique($handles));
        sort($handles);

        $out = array();
        foreach ($handles as $handle) {
            $handle = (string) $handle;
            if ('' === $handle || !isset($registry->registered[$handle])) {
                continue;
            }
            $src = isset($registry->registered[$handle]->src) ? (string) $registry->registered[$handle]->src : '';
            $out[] = array('handle' => $handle, 'src' => $src);
        }
        return $out;
    }

    private function current_path() {
        $path = wp_parse_url(UCP_Helpers::current_full_url(), PHP_URL_PATH);
        return is_string($path) && '' !== $path ? $path : '/';
    }

    /**
     * @param string $path
     * @param array<int,array{handle:string,src:string}> $scripts
     * @param array<int,array{handle:string,src:string}> $styles
     * @return string
     */
    private function render_overlay($path, $scripts, $styles) {
        $rules_scripts = $this->rules_block($path, $scripts);
        $rules_styles  = $this->rules_block($path, $styles);

        $css = '#ucp-asset-inspector{position:fixed;left:0;right:0;bottom:0;z-index:2147483646;max-height:46vh;overflow:auto;'
            . 'background:#0b1020;color:#e6e9f2;font:13px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;'
            . 'box-shadow:0 -8px 30px rgba(0,0,0,.45);padding:14px 18px}'
            . '#ucp-asset-inspector h2{margin:0 0 4px;font-size:14px;color:#8fd3ff}'
            . '#ucp-asset-inspector .ucp-asset-inspector-sub{color:#9aa3b8;margin:0 0 10px}'
            . '#ucp-asset-inspector code{background:#1b2440;color:#cfe3ff;padding:1px 5px;border-radius:4px}'
            . '#ucp-asset-inspector .ucp-asset-inspector-cols{display:flex;gap:18px;flex-wrap:wrap}'
            . '#ucp-asset-inspector .ucp-asset-inspector-col{flex:1 1 360px;min-width:280px}'
            . '#ucp-asset-inspector table{width:100%;border-collapse:collapse}'
            . '#ucp-asset-inspector th,#ucp-asset-inspector td{text-align:left;padding:3px 6px;border-bottom:1px solid #1c2540;vertical-align:top}'
            . '#ucp-asset-inspector th{color:#9aa3b8;font-weight:600}'
            . '#ucp-asset-inspector .ucp-asset-inspector-src{color:#8a93ab;word-break:break-all;font-size:11px}'
            . '#ucp-asset-inspector textarea{width:100%;height:84px;background:#11182f;color:#cfe3ff;border:1px solid #243054;'
            . 'border-radius:6px;padding:8px;font:12px/1.4 ui-monospace,SFMono-Regular,Menlo,monospace}'
            . '#ucp-asset-inspector .ucp-asset-inspector-close{position:absolute;top:8px;right:14px;cursor:pointer;color:#9aa3b8;font-size:20px;'
            . 'background:none;border:0}'
            . '#ucp-asset-inspector .ucp-asset-inspector-close:hover{color:#fff}';

        $html  = '<div id="ucp-asset-inspector" role="dialog" aria-label="UltraCache asset inspector">';
        $html .= '<button type="button" class="ucp-asset-inspector-close" onclick="document.getElementById(\'ucp-asset-inspector\').remove()" aria-label="Sluiten">&times;</button>';
        $html .= '<h2>UltraCache &mdash; Asset Inspector</h2>';
        $html .= '<p class="ucp-asset-inspector-sub">Handles op <code>' . esc_html($path) . '</code>. '
            . 'Kopieer regels naar <strong>Asset Manager &rarr; Conditional Script/Style Unloads</strong> '
            . 'om ze enkel op dit pad uit te schakelen. Alleen zichtbaar voor beheerders via '
            . '<code>?ucp-assets</code>.</p>';
        $html .= '<div class="ucp-asset-inspector-cols">';
        $html .= '<div class="ucp-asset-inspector-col"><h2>Scripts (' . count($scripts) . ')</h2>' . $this->table($scripts)
            . '<label>Conditional Script Unloads</label><textarea readonly onclick="this.select()">'
            . esc_textarea($rules_scripts) . '</textarea></div>';
        $html .= '<div class="ucp-asset-inspector-col"><h2>Styles (' . count($styles) . ')</h2>' . $this->table($styles)
            . '<label>Conditional Style Unloads</label><textarea readonly onclick="this.select()">'
            . esc_textarea($rules_styles) . '</textarea></div>';
        $html .= '</div></div>';

        return '<style>' . $css . '</style>' . $html;
    }

    /**
     * @param array<int,array{handle:string,src:string}> $items
     * @return string
     */
    private function table($items) {
        if (empty($items)) {
            return '<p class="ucp-asset-inspector-sub">Geen handles gevonden.</p>';
        }
        $rows = '';
        foreach ($items as $item) {
            $src = $item['src'] ? $this->shorten_src($item['src']) : '<em>inline</em>';
            $rows .= '<tr><td><code>' . esc_html($item['handle']) . '</code></td>'
                . '<td class="ucp-asset-inspector-src">' . wp_kses($src, array('em' => array())) . '</td></tr>';
        }
        return '<table><thead><tr><th>Handle</th><th>Bron</th></tr></thead><tbody>' . $rows . '</tbody></table>';
    }

    private function shorten_src($src) {
        $src = (string) $src;
        $path = wp_parse_url($src, PHP_URL_PATH);
        return esc_html(is_string($path) && '' !== $path ? $path : $src);
    }

    /**
     * Build a copy-paste block of "<path> => handle" rules, one per handle.
     *
     * @param string $path
     * @param array<int,array{handle:string,src:string}> $items
     * @return string
     */
    private function rules_block($path, $items) {
        $lines = array();
        foreach ($items as $item) {
            $lines[] = $path . ' => ' . $item['handle'];
        }
        return implode("\n", $lines);
    }
}
