<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Read-only frontend asset inspector for administrators.
 *
 * The inspector inventories scripts and styles, explains dependency relationships,
 * highlights sensitive handles and generates rules for the existing conditional
 * unload engine. It deliberately exposes no write endpoint.
 */
class UCP_Asset_Inspector {
    public function __construct() {
        if (!is_admin()) {
            add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'), 99998);
            add_action('wp_footer', array($this, 'maybe_render'), 99999);
        }
    }

    /**
     * Load inspector assets only for an authorized inspection request.
     *
     * @return void
     */
    public function enqueue_assets() {
        if (!$this->should_show()) {
            return;
        }

        $style_rel = 'assets/frontend/css/ucp-asset-inspector' . (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min') . '.css';
        $script_rel = 'assets/frontend/js/ucp-asset-inspector' . (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min') . '.js';
        $style_path = UCP_PATH . $style_rel;
        $script_path = UCP_PATH . $script_rel;

        wp_enqueue_style(
            'ucp-asset-inspector',
            UCP_URL . $style_rel,
            array(),
            is_readable($style_path) ? (string) filemtime($style_path) : UCP_VERSION
        );
        wp_enqueue_script(
            'ucp-asset-inspector',
            UCP_URL . $script_rel,
            array(),
            is_readable($script_path) ? (string) filemtime($script_path) : UCP_VERSION,
            true
        );
        if (function_exists('wp_script_add_data')) {
            wp_script_add_data('ucp-asset-inspector', 'strategy', 'defer');
        }
        wp_localize_script(
            'ucp-asset-inspector',
            'ucpAssetInspectorL10n',
            array(
                'selectFirst'       => __('Selecteer eerst minimaal één asset.', 'ultracache-pro'),
                'rulesCopied'       => __('Regels gekopieerd.', 'ultracache-pro'),
                'copyFailed'        => __('Kopiëren is niet gelukt. Selecteer de tekst en kopieer deze handmatig.', 'ultracache-pro'),
                'visible'           => __('zichtbaar', 'ultracache-pro'),
                'candidatesSelected'=> __('Zichtbare kandidaten geselecteerd. Test de regels eerst in testmodus.', 'ultracache-pro'),
                'selectionCleared'  => __('Selectie gewist.', 'ultracache-pro'),
            )
        );
    }

    /**
     * Render the inspector after WordPress has resolved queued dependencies.
     *
     * @return void
     */
    public function maybe_render() {
        if (!$this->should_show()) {
            return;
        }

        global $wp_scripts, $wp_styles;
        $scripts = $this->collect($wp_scripts, 'script');
        $styles  = $this->collect($wp_styles, 'style');
        $path    = $this->current_path();

        echo $this->render_overlay($path, $scripts, $styles); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled below with per-field escaping.
    }

    /**
     * Determine whether this request may expose the diagnostic inventory.
     *
     * @return bool
     */
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
     * Collect queued and printed handles with dependency and ownership metadata.
     *
     * @param mixed  $registry WP_Scripts|WP_Styles|null.
     * @param string $kind     script|style.
     * @return array<int,array<string,mixed>>
     */
    private function collect($registry, $kind) {
        if (!is_object($registry) || empty($registry->registered)) {
            return array();
        }

        $handles = array();
        foreach (array('done', 'queue', 'to_do') as $property) {
            if (!empty($registry->{$property}) && is_array($registry->{$property})) {
                $handles = array_merge($handles, $registry->{$property});
            }
        }
        $handles = array_values(array_unique(array_map('strval', $handles)));

        $dependents = array();
        foreach ((array) $registry->registered as $registered_handle => $dependency) {
            $deps = isset($dependency->deps) && is_array($dependency->deps) ? $dependency->deps : array();
            foreach ($deps as $required_handle) {
                $required_handle = (string) $required_handle;
                if ('' === $required_handle) {
                    continue;
                }
                if (!isset($dependents[$required_handle])) {
                    $dependents[$required_handle] = array();
                }
                $dependents[$required_handle][] = (string) $registered_handle;
            }
        }

        $out = array();
        foreach ($handles as $handle) {
            if ('' === $handle || !isset($registry->registered[$handle])) {
                continue;
            }

            $dependency = $registry->registered[$handle];
            $src = isset($dependency->src) ? (string) $dependency->src : '';
            $deps = isset($dependency->deps) && is_array($dependency->deps) ? array_values(array_unique(array_map('strval', $dependency->deps))) : array();
            $required_by = isset($dependents[$handle]) ? array_values(array_unique($dependents[$handle])) : array();
            sort($deps);
            sort($required_by);
            $provider = $this->provider_for($src);
            $risk = $this->risk_for($handle, $src, $deps, $required_by, $provider);

            $out[] = array(
                'handle'       => $handle,
                'src'          => $src,
                'display_src'  => $this->shorten_src($src),
                'deps'         => $deps,
                'required_by'  => $required_by,
                'provider'     => $provider['label'],
                'provider_key' => $provider['key'],
                'risk'         => $risk['level'],
                'risk_label'   => $risk['label'],
                'risk_reason'  => $risk['reason'],
                'kind'         => 'style' === $kind ? 'style' : 'script',
                'inline'       => '' === $src,
            );
        }

        usort($out, static function ($left, $right) {
            $provider_compare = strcasecmp((string) $left['provider'], (string) $right['provider']);
            return 0 !== $provider_compare ? $provider_compare : strcasecmp((string) $left['handle'], (string) $right['handle']);
        });

        return $out;
    }

    /**
     * Resolve a human-readable owner from an asset URL.
     *
     * @param string $src Asset source.
     * @return array{key:string,label:string}
     */
    private function provider_for($src) {
        $src = html_entity_decode((string) $src, ENT_QUOTES, 'UTF-8');
        if ('' === $src) {
            return array('key' => 'inline', 'label' => __('Inline of alias', 'ultracache-pro'));
        }

        $path = (string) wp_parse_url($src, PHP_URL_PATH);
        $host = (string) wp_parse_url($src, PHP_URL_HOST);
        $site_host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
        if ('' !== $host && '' !== $site_host && 0 !== strcasecmp($host, $site_host)) {
            return array(
                'key' => 'external',
                'label' => sprintf(
                    /* translators: %s: external host. */
                    __('Extern: %s', 'ultracache-pro'),
                    $host
                ),
            );
        }

        if (preg_match('#/(?:wp-content/)?plugins/([^/]+)/#i', $path, $matches)) {
            return array(
                'key' => 'plugin',
                'label' => sprintf(
                    /* translators: %s: plugin directory. */
                    __('Plugin: %s', 'ultracache-pro'),
                    sanitize_key($matches[1])
                ),
            );
        }
        if (preg_match('#/(?:wp-content/)?themes/([^/]+)/#i', $path, $matches)) {
            return array(
                'key' => 'theme',
                'label' => sprintf(
                    /* translators: %s: theme directory. */
                    __('Thema: %s', 'ultracache-pro'),
                    sanitize_key($matches[1])
                ),
            );
        }
        if (false !== strpos($path, '/wp-includes/') || false !== strpos($path, '/wp-admin/')) {
            return array('key' => 'core', 'label' => __('WordPress Core', 'ultracache-pro'));
        }

        return array('key' => 'site', 'label' => __('Website', 'ultracache-pro'));
    }

    /**
     * Classify unloading risk without mutating any settings.
     *
     * @param string $handle      Handle.
     * @param string $src         Source URL.
     * @param array  $deps        Dependencies.
     * @param array  $required_by Dependents.
     * @param array  $provider    Provider metadata.
     * @return array{level:string,label:string,reason:string}
     */
    private function risk_for($handle, $src, array $deps, array $required_by, array $provider) {
        $haystack = strtolower($handle . ' ' . $src);
        $protected_patterns = array(
            'jquery', 'wp-api-fetch', 'wp-components', 'wp-data', 'wp-dom-ready', 'wp-element', 'wp-hooks',
            'wp-i18n', 'wp-interactivity', 'wp-polyfill', 'woocommerce', 'wc-', 'wc_', 'checkout', 'cart',
            'payment', 'stripe', 'paypal', 'mollie', 'klarna', 'adyen', 'consent', 'cookiebot', 'complianz',
            'borlabs', 'captcha', 'recaptcha', 'turnstile', 'nonce', 'heartbeat',
        );
        foreach ($protected_patterns as $pattern) {
            if (false !== strpos($haystack, $pattern)) {
                return array(
                    'level' => 'protected',
                    'label' => __('Beschermd', 'ultracache-pro'),
                    'reason' => __('Dit asset lijkt onderdeel van WordPress Core, checkout, betaling, toestemming of beveiliging. Niet automatisch uitschakelen.', 'ultracache-pro'),
                );
            }
        }

        if ('core' === $provider['key']) {
            return array(
                'level' => 'review',
                'label' => __('Controleren', 'ultracache-pro'),
                'reason' => __('WordPress Core-asset. Controleer de volledige pagina en beheerflow voordat je dit uitschakelt.', 'ultracache-pro'),
            );
        }
        if ('' === $src) {
            return array(
                'level' => 'review',
                'label' => __('Controleren', 'ultracache-pro'),
                'reason' => __('Inline asset of alias zonder eigen bronbestand. Het handle kan andere assets bundelen.', 'ultracache-pro'),
            );
        }
        if (!empty($deps) || !empty($required_by)) {
            return array(
                'level' => 'review',
                'label' => __('Controleren', 'ultracache-pro'),
                'reason' => __('Dit asset heeft afhankelijkheden of wordt door andere assets vereist.', 'ultracache-pro'),
            );
        }
        if ('external' === $provider['key']) {
            return array(
                'level' => 'review',
                'label' => __('Controleren', 'ultracache-pro'),
                'reason' => __('Extern asset. Controleer tracking, consent en functionele afhankelijkheden.', 'ultracache-pro'),
            );
        }

        return array(
            'level' => 'candidate',
            'label' => __('Kandidaat', 'ultracache-pro'),
            'reason' => __('Geen directe afhankelijkheid of beschermde flow gedetecteerd. Test alsnog op staging.', 'ultracache-pro'),
        );
    }

    /**
     * Current request path without query or fragment.
     *
     * @return string
     */
    private function current_path() {
        $path = wp_parse_url(UCP_Helpers::current_full_url(), PHP_URL_PATH);
        return is_string($path) && '' !== $path ? $path : '/';
    }

    /**
     * Render the dependency-aware read-only overlay.
     *
     * @param string $path    Request path.
     * @param array  $scripts Script inventory.
     * @param array  $styles  Style inventory.
     * @return string
     */
    private function render_overlay($path, array $scripts, array $styles) {
        $settings_url = admin_url('admin.php?page=ultracache-pro&tab=advanced');
        $html  = '<section id="ucp-asset-inspector" class="ucp-asset-inspector" role="dialog" aria-modal="false" aria-labelledby="ucp-asset-inspector-title" data-path="' . esc_attr($path) . '">';
        $html .= '<header class="ucp-asset-inspector__header">';
        $html .= '<div><p class="ucp-asset-inspector__eyebrow">' . esc_html__('Veilige assetanalyse', 'ultracache-pro') . '</p>';
        $html .= '<h2 id="ucp-asset-inspector-title">' . esc_html__('UltraCache Asset Inspector', 'ultracache-pro') . '</h2>';
        $html .= '<p class="ucp-asset-inspector__intro">' . sprintf(
            /* translators: %s: current path. */
            esc_html__('Selecteer alleen aantoonbaar overbodige assets op %s. Beschermde handles worden niet automatisch selecteerbaar gemaakt.', 'ultracache-pro'),
            '<code>' . esc_html($path) . '</code>'
        ) . '</p></div>';
        $html .= '<button type="button" class="ucp-asset-inspector__close" data-ucp-inspector-close aria-label="' . esc_attr__('Asset Inspector sluiten', 'ultracache-pro') . '">&times;</button>';
        $html .= '</header>';
        $html .= '<div class="ucp-asset-inspector__toolbar">';
        $html .= '<label class="ucp-asset-inspector__search"><span>' . esc_html__('Zoeken', 'ultracache-pro') . '</span><input type="search" data-ucp-inspector-search placeholder="' . esc_attr__('Handle, bron of eigenaar', 'ultracache-pro') . '"></label>';
        $html .= '<label><span>' . esc_html__('Risico', 'ultracache-pro') . '</span><select data-ucp-inspector-risk><option value="all">' . esc_html__('Alles', 'ultracache-pro') . '</option><option value="candidate">' . esc_html__('Kandidaten', 'ultracache-pro') . '</option><option value="review">' . esc_html__('Controleren', 'ultracache-pro') . '</option><option value="protected">' . esc_html__('Beschermd', 'ultracache-pro') . '</option></select></label>';
        $html .= '<div class="ucp-asset-inspector__toolbar-actions"><button type="button" data-ucp-inspector-select-candidates>' . esc_html__('Selecteer zichtbare kandidaten', 'ultracache-pro') . '</button><button type="button" data-ucp-inspector-clear>' . esc_html__('Selectie wissen', 'ultracache-pro') . '</button></div>';
        $html .= '</div>';
        $html .= '<div class="ucp-asset-inspector__notice" role="note">' . esc_html__('Deze tool schrijft niets naar de database. De gegenereerde regels moeten bewust in de bestaande Asset Manager worden geplaatst en daarna in testmodus worden gecontroleerd.', 'ultracache-pro') . '</div>';
        $html .= '<div class="ucp-asset-inspector__columns">';
        $html .= $this->render_group('script', __('Scripts', 'ultracache-pro'), $scripts, $path);
        $html .= $this->render_group('style', __('Styles', 'ultracache-pro'), $styles, $path);
        $html .= '</div>';
        $html .= '<footer class="ucp-asset-inspector__footer"><span data-ucp-inspector-status aria-live="polite"></span><a href="' . esc_url($settings_url) . '">' . esc_html__('Asset Manager openen', 'ultracache-pro') . '</a></footer>';
        $html .= '</section>';
        return $html;
    }

    /**
     * Render one inventory group.
     *
     * @param string $kind  script|style.
     * @param string $label Group label.
     * @param array  $items Inventory items.
     * @param string $path  Current request path.
     * @return string
     */
    private function render_group($kind, $label, array $items, $path) {
        $html  = '<section class="ucp-asset-inspector__group" data-ucp-inspector-group="' . esc_attr($kind) . '">';
        $html .= '<div class="ucp-asset-inspector__group-header"><h3>' . esc_html($label) . ' <span>(' . absint(count($items)) . ')</span></h3><span data-ucp-inspector-visible-count></span></div>';
        if (empty($items)) {
            return $html . '<p>' . esc_html__('Geen handles gevonden.', 'ultracache-pro') . '</p></section>';
        }

        $html .= '<div class="ucp-asset-inspector__table-wrap"><table><thead><tr>';
        $html .= '<th scope="col"><span class="screen-reader-text">' . esc_html__('Selecteren', 'ultracache-pro') . '</span></th>';
        $html .= '<th scope="col">' . esc_html__('Handle en eigenaar', 'ultracache-pro') . '</th>';
        $html .= '<th scope="col">' . esc_html__('Bron', 'ultracache-pro') . '</th>';
        $html .= '<th scope="col">' . esc_html__('Afhankelijkheden', 'ultracache-pro') . '</th>';
        $html .= '<th scope="col">' . esc_html__('Beoordeling', 'ultracache-pro') . '</th>';
        $html .= '</tr></thead><tbody>';
        foreach ($items as $item) {
            $search_text = implode(' ', array(
                $item['handle'], $item['provider'], $item['display_src'],
                implode(' ', $item['deps']), implode(' ', $item['required_by']), $item['risk_label'],
            ));
            $disabled = 'protected' === $item['risk'];
            $html .= '<tr data-ucp-inspector-row data-risk="' . esc_attr($item['risk']) . '" data-search="' . esc_attr(strtolower($search_text)) . '">';
            $html .= '<td><input type="checkbox" data-ucp-inspector-select data-kind="' . esc_attr($kind) . '" data-handle="' . esc_attr($item['handle']) . '"' . disabled($disabled, true, false) . ' aria-label="' . esc_attr(sprintf(
                /* translators: %s: asset handle. */
                __('%s selecteren', 'ultracache-pro'),
                $item['handle']
            )) . '"></td>';
            $html .= '<td><code>' . esc_html($item['handle']) . '</code><span class="ucp-asset-inspector__provider">' . esc_html($item['provider']) . '</span></td>';
            $html .= '<td class="ucp-asset-inspector__source">' . ($item['inline'] ? '<em>' . esc_html__('Inline of alias', 'ultracache-pro') . '</em>' : esc_html($item['display_src'])) . '</td>';
            $html .= '<td>' . $this->dependency_summary($item['deps'], $item['required_by']) . '</td>';
            $html .= '<td><span class="ucp-asset-inspector__risk is-' . esc_attr($item['risk']) . '">' . esc_html($item['risk_label']) . '</span><span class="ucp-asset-inspector__reason">' . esc_html($item['risk_reason']) . '</span></td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div>';
        $html .= '<div class="ucp-asset-inspector__rules"><label for="ucp-asset-inspector-' . esc_attr($kind) . '-rules">' . sprintf(
            /* translators: %s: scripts or styles. */
            esc_html__('Geselecteerde %s-regels', 'ultracache-pro'),
            esc_html(strtolower($label))
        ) . '</label><textarea id="ucp-asset-inspector-' . esc_attr($kind) . '-rules" readonly data-ucp-inspector-rules data-kind="' . esc_attr($kind) . '" data-path="' . esc_attr($path) . '"></textarea><button type="button" data-ucp-inspector-copy data-kind="' . esc_attr($kind) . '" hidden disabled aria-hidden="true">' . esc_html__('Regels kopiëren', 'ultracache-pro') . '</button></div>';
        $html .= '</section>';
        return $html;
    }

    /**
     * Human-readable dependency details.
     *
     * @param array $deps        Dependencies.
     * @param array $required_by Dependents.
     * @return string
     */
    private function dependency_summary(array $deps, array $required_by) {
        if (empty($deps) && empty($required_by)) {
            return '<span class="ucp-asset-inspector__muted">' . esc_html__('Geen directe relaties', 'ultracache-pro') . '</span>';
        }

        $parts = array();
        if (!empty($deps)) {
            $parts[] = '<span><strong>' . esc_html__('Vereist:', 'ultracache-pro') . '</strong> ' . esc_html(implode(', ', $deps)) . '</span>';
        }
        if (!empty($required_by)) {
            $parts[] = '<span><strong>' . esc_html__('Nodig voor:', 'ultracache-pro') . '</strong> ' . esc_html(implode(', ', $required_by)) . '</span>';
        }
        return '<span class="ucp-asset-inspector__dependencies">' . implode('', $parts) . '</span>';
    }

    /**
     * Shorten an asset URL to a readable path.
     *
     * @param string $src Source URL.
     * @return string
     */
    private function shorten_src($src) {
        $src = html_entity_decode((string) $src, ENT_QUOTES, 'UTF-8');
        if ('' === $src) {
            return '';
        }
        $path = wp_parse_url($src, PHP_URL_PATH);
        return is_string($path) && '' !== $path ? $path : $src;
    }
}
