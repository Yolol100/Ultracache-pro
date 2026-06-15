<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Cache_Admin_Bar_Trait {
    public function admin_bar($wp_admin_bar) {
        if (!current_user_can('manage_options') || !UCP_Options::get('enable_admin_bar') || UCP_Options::get('enable_hide_toolbar_menu')) {
            return;
        }

        $cache_on = (bool) UCP_Options::get('enable_cache');
        $status_class = $cache_on ? 'is-on' : 'is-off';
        $status_text  = $cache_on ? __('Klaar', 'ultracache-pro') : __('Cache uit', 'ultracache-pro');
        $show_purge_preload = $cache_on && (bool) UCP_Options::get('enable_preload');
        $show_used_css = (bool) UCP_Options::get('enable_used_css');
        $show_priority_elements = absint(UCP_Options::get('preload_critical_images', 0)) > 0;

        $wp_admin_bar->add_node(array(
            'id' => 'ucp-parent',
            'title' => '<span class="ab-icon dashicons dashicons-performance"></span><span class="ab-label">UltraCache</span><span class="ucp-adminbar-state ' . esc_attr($status_class) . '">' . esc_html($status_text) . '</span>',
            'href' => admin_url('admin.php?page=ultracache-pro'),
            'meta' => array('class' => 'ucp-adminbar-parent'),
        ));

        if ($show_purge_preload) {
            $wp_admin_bar->add_node(array(
                'id' => 'ucp-purge-preload',
                'parent' => 'ucp-parent',
                'title' => __('Cache legen en opwarmen', 'ultracache-pro'),
                'href' => admin_url('admin.php?page=ultracache-pro&tab=tools'),
            ));
        }

        if ($show_used_css) {
            $wp_admin_bar->add_node(array(
                'id' => 'ucp-clear-used-css',
                'parent' => 'ucp-parent',
                'title' => __('Gebruikte CSS legen', 'ultracache-pro'),
                'href' => admin_url('admin.php?page=ultracache-pro&tab=tools'),
            ));
        }

        if ($show_priority_elements) {
            $wp_admin_bar->add_node(array(
                'id' => 'ucp-clear-priority-elements',
                'parent' => 'ucp-parent',
                'title' => __('Priority elements legen', 'ultracache-pro'),
                'href' => admin_url('admin.php?page=ultracache-pro&tab=tools'),
            ));
        }

        if (!is_admin()) {
            $wp_admin_bar->add_node(array(
                'id' => 'ucp-purge-url',
                'parent' => 'ucp-parent',
                'title' => __('Deze pagina legen', 'ultracache-pro'),
                'href' => admin_url('admin.php?page=ultracache-pro&tab=tools'),
            ));
        }

        $wp_admin_bar->add_node(array(
            'id' => 'ucp-open-plugin',
            'parent' => 'ucp-parent',
            'title' => esc_html__('UltraCache openen', 'ultracache-pro'),
            'href' => admin_url('admin.php?page=ultracache-pro'),
        ));
        if (UCP_Options::get('enable_asset_test_mode')) {
            $wp_admin_bar->add_node(array(
                'id' => 'ucp-test-mode',
                'parent' => 'ucp-parent',
                'title' => esc_html__('Teststand staat aan', 'ultracache-pro'),
                'href' => admin_url('admin.php?page=ultracache-pro&tab=tools'),
                'meta' => array('class' => 'ucp-adminbar-testmode'),
            ));
        }
    }

    public function admin_bar_styles() {
        if (!is_admin_bar_showing() || !current_user_can('manage_options') || !UCP_Options::get('enable_admin_bar') || UCP_Options::get('enable_hide_toolbar_menu')) {
            return;
        }
        $rest_url = esc_url_raw(rest_url('ultracache-pro/v1/actions/'));
        $rest_nonce = wp_create_nonce('wp_rest');
        echo '<style id="ucp-adminbar-styles">'
            . '#wpadminbar #wp-admin-bar-ucp-parent>.ab-item{display:flex!important;align-items:center;height:32px;line-height:32px;}'
            . '#wpadminbar #wp-admin-bar-ucp-parent>.ab-item .ab-icon.dashicons{font-family:dashicons;display:inline-flex;align-items:center;justify-content:center;top:0;margin:0 6px 0 0;height:32px;line-height:32px;}'
            . '#wpadminbar #wp-admin-bar-ucp-parent>.ab-item .ab-label{display:inline-flex;align-items:center;height:32px;line-height:32px;}'
            . '#wpadminbar .ucp-adminbar-state{display:inline-flex;align-items:center;justify-content:center;height:20px;min-height:20px;margin:0 0 0 8px;padding:0 8px;border-radius:999px;font-size:11px;line-height:20px;font-weight:600;vertical-align:middle;background:rgba(255,255,255,.14);}'
            . '#wpadminbar .ucp-adminbar-state.is-on{background:#1f6f43;color:#fff;}'
            . '#wpadminbar .ucp-adminbar-state.is-off{background:#8a2424;color:#fff;}'
            . '#wpadminbar .ucp-adminbar-testmode>.ab-item{color:#ffdd57;}'
            . '.ucp-adminbar-toast{position:fixed;left:50%;right:auto;bottom:24px;transform:translateX(-50%);z-index:999999;display:inline-flex;align-items:center;justify-content:center;gap:8px;width:max-content;max-width:min(92vw,420px);min-height:40px;padding:7px 10px;background:#fff;color:#1d2327;border:1px solid #dcdcde;border-left:4px solid #007cba;border-radius:10px;font-size:13px;font-weight:600;line-height:1.3;text-align:left;box-shadow:0 4px 16px rgba(16,24,40,.10);}'
            . 'body.wp-admin .ucp-adminbar-toast{left:calc(160px + ((100vw - 160px) / 2));}'
            . 'body.wp-admin.folded .ucp-adminbar-toast{left:calc(36px + ((100vw - 36px) / 2));}'
            . '@media (max-width:960px) and (min-width:783px){body.wp-admin .ucp-adminbar-toast{left:calc(36px + ((100vw - 36px) / 2));}}'
            . '@media (max-width:782px){body.wp-admin .ucp-adminbar-toast{left:16px;right:16px;width:auto;max-width:none;transform:none;}}'
            . '.ucp-adminbar-toast.is-error{border-left-color:#d63638;}'
            . '#wpadminbar .ucp-adminbar-busy>.ab-item{opacity:.7;pointer-events:none;}'
            . '</style>';
        echo '<script id="ucp-adminbar-actions">(function(){'
            . 'var base=' . wp_json_encode(trailingslashit($rest_url)) . ',nonce=' . wp_json_encode($rest_nonce) . ';'
            . 'function toast(message,isError){var node=document.querySelector(".ucp-adminbar-toast");if(!node){node=document.createElement("div");node.className="ucp-adminbar-toast";document.body.appendChild(node);}node.className="ucp-adminbar-toast"+(isError?" is-error":"");node.textContent=message;window.clearTimeout(node._ucpTimer);node._ucpTimer=window.setTimeout(function(){if(node&&node.parentNode){node.parentNode.removeChild(node);}},4200);}'
            . 'function action(slug,data){return window.fetch(base+slug,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","X-WP-Nonce":nonce},body:JSON.stringify(data||{})}).then(function(r){return r.json().catch(function(){return {};}).then(function(json){if(!r.ok||json.code){throw new Error(json.message||"Actie mislukt.");}return json;});});}'
            . 'function bind(id,runner){var item=document.getElementById("wp-admin-bar-"+id);if(!item){return;}var link=item.querySelector("a");if(!link){return;}link.addEventListener("click",function(e){e.preventDefault();if(item.classList.contains("ucp-adminbar-busy")){return;}item.classList.add("ucp-adminbar-busy");runner().then(function(message){toast(message||"Actie uitgevoerd.",false);}).catch(function(err){toast((err&&err.message)?err.message:"Actie mislukt.",true);}).then(function(){item.classList.remove("ucp-adminbar-busy");});});}'
            . 'function ready(fn){if(document.readyState!=="loading"){fn();}else{document.addEventListener("DOMContentLoaded",fn);}}'
            . 'ready(function(){bind("ucp-purge-preload",function(){return action("purge-all").then(function(){return action("preload");}).then(function(){return "Cache geleegd en opwarmen gestart.";});});'
            . 'bind("ucp-purge-url",function(){return action("purge-url",{url:window.location.href}).then(function(resp){return resp&&resp.message?resp.message:"Deze pagina is geleegd.";});});'
            . 'bind("ucp-clear-used-css",function(){return action("clear-used-css").then(function(resp){return resp&&resp.message?resp.message:"Gebruikte CSS is geleegd.";});});'
            . 'bind("ucp-clear-priority-elements",function(){return action("clear-priority-elements").then(function(resp){return resp&&resp.message?resp.message:"Priority elements zijn geleegd.";});});});'
            . '})();</script>';
    }

    protected function require_post_admin_action($nonce) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }
        $request_method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']))) : '';
        if ('POST' !== $request_method) {
            wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=tools&post_required=1'));
            exit;
        }
        check_admin_referer($nonce);
    }

    public function handle_purge_all() {
        $this->require_post_admin_action('ucp_purge_all');
        $this->purge_all();
        wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=tools&purged=1'));
        exit;
    }

    public function handle_purge_and_preload() {
        $this->require_post_admin_action('ucp_purge_and_preload');
        $this->purge_all();
        do_action('ucp_preload_event');
        wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=tools&purged=1&preloaded=1'));
        exit;
    }

    public function handle_purge_url() {
        $this->require_post_admin_action('ucp_purge_url');
        $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : home_url('/');
        $url = UCP_Helpers::strict_local_url($url, home_url('/'));
        if (!$url) {
            $url = home_url('/');
        }
        $this->purge_url($url);
        $this->queue_preload_url($url);
        wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=tools&purged=1'));
        exit;
    }
}
