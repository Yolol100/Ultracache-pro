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
        if (class_exists('UCP_Helpers') && UCP_Helpers::testing_mode_active()) {
            $expires_at = UCP_Helpers::testing_mode_expires_at();
            $test_mode_title = $expires_at > 0
                ? sprintf(
                    /* translators: %s: local testing mode expiration time. */
                    __('Teststand aan tot %s', 'ultracache-pro'),
                    wp_date(get_option('time_format'), $expires_at)
                )
                : __('Teststand staat aan', 'ultracache-pro');
            $wp_admin_bar->add_node(array(
                'id' => 'ucp-test-mode',
                'parent' => 'ucp-parent',
                'title' => esc_html($test_mode_title),
                'href' => admin_url('admin.php?page=ultracache-pro&tab=tools'),
                'meta' => array('class' => 'ucp-adminbar-testmode'),
            ));
        }
    }

    public function admin_bar_styles() {
        if (!is_admin_bar_showing() || !current_user_can('manage_options') || !UCP_Options::get('enable_admin_bar') || UCP_Options::get('enable_hide_toolbar_menu')) {
            return;
        }

        static $assets_enqueued = false;
        if ($assets_enqueued) {
            return;
        }
        $assets_enqueued = true;

        $version = defined('UCP_VERSION') ? UCP_VERSION : null;
        $rest_url = esc_url_raw(rest_url('ultracache-pro/v1/actions/'));
        $rest_nonce = wp_create_nonce('wp_rest');
        $messages = array(
            'close'             => __('Melding sluiten', 'ultracache-pro'),
            'failed'            => __('Actie mislukt.', 'ultracache-pro'),
            'success'           => __('Actie uitgevoerd.', 'ultracache-pro'),
            'purge_preload'     => __('Cache geleegd en opwarmen gestart.', 'ultracache-pro'),
            'purge_url'         => __('Deze pagina is geleegd.', 'ultracache-pro'),
            'clear_used_css'    => __('Gebruikte CSS is geleegd.', 'ultracache-pro'),
            'clear_priority'    => __('Prioriteitselementen zijn geleegd.', 'ultracache-pro'),
        );
        $css = '#wpadminbar #wp-admin-bar-ucp-parent>.ab-item{display:flex!important;align-items:center;height:32px;line-height:32px;}'
            . '#wpadminbar #wp-admin-bar-ucp-parent>.ab-item .ab-icon.dashicons{font-family:dashicons;display:inline-flex;align-items:center;justify-content:center;top:0;margin:0 6px 0 0;height:32px;line-height:32px;}'
            . '#wpadminbar #wp-admin-bar-ucp-parent>.ab-item .ab-label{display:inline-flex;align-items:center;height:32px;line-height:32px;}'
            . '#wpadminbar .ucp-adminbar-state{display:inline-flex;align-items:center;justify-content:center;height:20px;min-height:20px;margin:0 0 0 8px;padding:0 8px;border-radius:999px;font-size:11px;line-height:20px;font-weight:600;vertical-align:middle;background:rgba(255,255,255,.14);}'
            . '#wpadminbar .ucp-adminbar-state.is-on{background:#1f6f43;color:#fff;}'
            . '#wpadminbar .ucp-adminbar-state.is-off{background:#8a2424;color:#fff;}'
            . '#wpadminbar .ucp-adminbar-testmode>.ab-item{color:#ffdd57;}'
            . '.ucp-adminbar-toast{position:fixed;left:50%;right:auto;bottom:24px;transform:translate(-50%,8px);z-index:999999;display:grid;grid-template-columns:24px minmax(0,1fr) 24px;align-items:center;gap:10px;box-sizing:border-box;width:fit-content;min-width:min(320px,calc(100vw - 48px));max-width:min(440px,calc(100vw - 48px));min-height:48px;padding:10px 12px 10px 14px;overflow:hidden;background:#fff;color:#1d2327;border:1px solid #dfe6ec;border-left:4px solid #00a32a;border-radius:12px;font-size:13px;font-weight:400;line-height:1.45;text-align:left;box-shadow:0 14px 34px rgba(16,24,40,.16),0 2px 8px rgba(16,24,40,.08);opacity:0;animation:ucpAdminbarToastIn .2s ease-out forwards;}'
            . '.ucp-adminbar-toast__icon{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:7px;background:#edfaef;color:#0a7a28;font-size:12px;font-weight:700;}'
            . '.ucp-adminbar-toast__message{min-width:0;white-space:normal;overflow-wrap:break-word;}'
            . '.ucp-adminbar-toast__close{-webkit-appearance:none;appearance:none;display:inline-flex;align-items:center;justify-content:center;width:24px;min-width:24px;height:24px;min-height:24px;margin:0;padding:0;border:1px solid transparent;border-radius:999px;background:transparent;color:#6b7280;box-shadow:none;cursor:pointer;font-size:16px;font-weight:400;line-height:1;}'
            . '.ucp-adminbar-toast__close:hover,.ucp-adminbar-toast__close:focus{border-color:#dcdcde;background:#f6f7f7;color:#1d2327;outline:0;box-shadow:none;}'
            . '.ucp-adminbar-toast__close:focus-visible{outline:2px solid #2271b1;outline-offset:2px;}'
            . '.ucp-adminbar-toast.is-error{border-left-color:#d63638;}'
            . '.ucp-adminbar-toast.is-error .ucp-adminbar-toast__icon{background:#fcf0f1;color:#c62828;}'
            . '@keyframes ucpAdminbarToastIn{from{opacity:0;transform:translate(-50%,8px)}to{opacity:1;transform:translate(-50%,0)}}'
            . 'body.wp-admin .ucp-adminbar-toast{left:calc(160px + ((100vw - 160px) / 2));}'
            . 'body.wp-admin.folded .ucp-adminbar-toast{left:calc(36px + ((100vw - 36px) / 2));}'
            . '@media (max-width:960px) and (min-width:783px){body.wp-admin .ucp-adminbar-toast{left:calc(36px + ((100vw - 36px) / 2));}}'
            . '@media (max-width:782px){.ucp-adminbar-toast,body.wp-admin .ucp-adminbar-toast{left:16px;right:16px;bottom:16px;width:auto;min-width:0;max-width:none;transform:translateY(8px);}@keyframes ucpAdminbarToastIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}}'
            . '@media (prefers-reduced-motion:reduce){.ucp-adminbar-toast{animation:none;opacity:1;transform:translateX(-50%);}}'
            . '@media (prefers-reduced-motion:reduce) and (max-width:782px){.ucp-adminbar-toast,body.wp-admin .ucp-adminbar-toast{transform:none;}}'
            . '#wpadminbar .ucp-adminbar-busy>.ab-item{opacity:.7;pointer-events:none;}';

        wp_register_style('ucp-adminbar-styles', false, array('admin-bar'), $version);
        wp_enqueue_style('ucp-adminbar-styles');
        wp_add_inline_style('ucp-adminbar-styles', $css);

        $javascript = '(function(){'
            . 'var base=' . UCP_Helpers::safe_inline_json(trailingslashit($rest_url), '""') . ',nonce=' . UCP_Helpers::safe_inline_json($rest_nonce, '""') . ',messages=' . UCP_Helpers::safe_inline_json($messages, '{}') . ';'
            . 'function removeToast(node){if(node&&node.parentNode){node.parentNode.removeChild(node);}}'
            . 'function toast(message,isError){var node=document.querySelector(".ucp-adminbar-toast"),icon,messageNode,close;if(!node){node=document.createElement("div");icon=document.createElement("span");messageNode=document.createElement("span");close=document.createElement("button");icon.className="ucp-adminbar-toast__icon";icon.setAttribute("aria-hidden","true");messageNode.className="ucp-adminbar-toast__message";close.type="button";close.className="ucp-adminbar-toast__close";close.setAttribute("aria-label",messages.close);close.textContent="×";close.addEventListener("click",function(){window.clearTimeout(node._ucpTimer);removeToast(node);});node.appendChild(icon);node.appendChild(messageNode);node.appendChild(close);document.body.appendChild(node);}else{icon=node.querySelector(".ucp-adminbar-toast__icon");messageNode=node.querySelector(".ucp-adminbar-toast__message");}node.className="ucp-adminbar-toast"+(isError?" is-error":"");node.setAttribute("role",isError?"alert":"status");node.setAttribute("aria-live",isError?"assertive":"polite");node.setAttribute("aria-atomic","true");if(icon){icon.textContent=isError?"!":"✓";}if(messageNode){messageNode.textContent=String(message||"");}window.clearTimeout(node._ucpTimer);node._ucpTimer=window.setTimeout(function(){removeToast(node);},isError?8000:4600);}'
            . 'function action(slug,data){return window.fetch(base+slug,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","X-WP-Nonce":nonce},body:JSON.stringify(data||{})}).then(function(r){return r.json().catch(function(){return {};}).then(function(json){if(!r.ok||json.code){throw new Error(json.message||messages.failed);}return json;});});}'
            . 'function bind(id,runner){var item=document.getElementById("wp-admin-bar-"+id);if(!item){return;}var link=item.querySelector("a");if(!link){return;}link.addEventListener("click",function(e){e.preventDefault();if(item.classList.contains("ucp-adminbar-busy")){return;}item.classList.add("ucp-adminbar-busy");runner().then(function(message){toast(message||messages.success,false);}).catch(function(err){toast((err&&err.message)?err.message:messages.failed,true);}).then(function(){item.classList.remove("ucp-adminbar-busy");});});}'
            . 'function ready(fn){if(document.readyState!=="loading"){fn();}else{document.addEventListener("DOMContentLoaded",fn);}}'
            . 'ready(function(){bind("ucp-purge-preload",function(){return action("purge-all").then(function(){return action("preload");}).then(function(){return messages.purge_preload;});});'
            . 'bind("ucp-purge-url",function(){return action("purge-url",{url:window.location.href}).then(function(resp){return resp&&resp.message?resp.message:messages.purge_url;});});'
            . 'bind("ucp-clear-used-css",function(){return action("clear-used-css").then(function(resp){return resp&&resp.message?resp.message:messages.clear_used_css;});});'
            . 'bind("ucp-clear-priority-elements",function(){return action("clear-priority-elements").then(function(resp){return resp&&resp.message?resp.message:messages.clear_priority;});});});'
            . '})();';

        wp_register_script(
            'ucp-adminbar-actions',
            false,
            array(),
            $version,
            array('in_footer' => true)
        );
        wp_enqueue_script('ucp-adminbar-actions');
        wp_add_inline_script('ucp-adminbar-actions', $javascript, 'after');
    }

    protected function require_post_admin_action($nonce) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }
        $request_method = UCP_Helpers::request_method();
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
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- require_post_admin_action() verifies the action nonce before this input is read.
        $url = esc_url_raw(UCP_Helpers::request_scalar('url', home_url('/'), 2048));
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
