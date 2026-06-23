<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Optimizer_Scripts_Trait {
    public function native_script_strategy($tag, $handle, $src) {
        if ($this->should_skip_frontend_optimizations() || $this->html_context_is_sensitive() || is_admin() || !UCP_Options::get('enable_native_script_strategy') || !UCP_Options::get('defer_all_js') || UCP_Options::get('enable_delay_js')) {
            return $tag;
        }
        $configured = UCP_Helpers::normalize_multiline(UCP_Options::get('native_script_handles', ''));
        if (!empty($configured) && !in_array($handle, $configured, true)) {
            return $tag;
        }
        if ($this->script_has_no_delay_marker($tag)) {
            return $tag;
        }
        $excluded = $this->script_optimization_exclusions();
        foreach ($excluded as $fragment) {
            if (false !== stripos((string) $handle, (string) $fragment) || false !== stripos((string) $src, (string) $fragment)) {
                return $tag;
            }
        }
        if (false !== stripos($tag, ' type="module"')) {
            return $tag;
        }
        if (false === strpos($tag, ' defer')) {
            $tag = str_replace(' src', ' defer src', $tag);
            UCP_Diagnostics::record('scripts', 'Applied native defer strategy', array('handle' => $handle));
        }
        return $tag;
    }

    public function defer_scripts_fallback($tag, $handle, $src) {
        if ($this->should_skip_frontend_optimizations() || $this->html_context_is_sensitive() || is_admin() || !UCP_Options::get('enable_defer_js_fallback') || !UCP_Options::get('defer_all_js') || UCP_Options::get('enable_delay_js')) {
            return $tag;
        }
        if ($this->script_has_no_delay_marker($tag)) {
            return $tag;
        }
        $excluded = $this->script_optimization_exclusions();
        foreach ($excluded as $fragment) {
            if (false !== stripos((string) $handle, (string) $fragment) || false !== stripos((string) $src, (string) $fragment)) {
                return $tag;
            }
        }
        if (false === strpos($tag, ' defer')) {
            $tag = str_replace(' src', ' defer src', $tag);
        }
        return $tag;
    }

    private function script_optimization_exclusions() {
        $manual = UCP_Helpers::normalize_multiline(UCP_Options::get('js_exclusions', ''));
        $manual = apply_filters('ucp_js_exclusions', $manual);
        $delay = UCP_Helpers::normalize_multiline(UCP_Options::get('delay_js_exclusions', ''));
        $delay = apply_filters('ucp_delay_js_exclusions', $delay);
        return array_values(array_unique(array_filter(array_merge((array) $manual, (array) $delay), 'strlen')));
    }

    private function delay_js_in_html($html) {
        if ($this->should_skip_markup_optimizations($html)) {
            return $html;
        }
        $delay_mode = UCP_Options::get('delay_js_mode', 'specified');
        $specified = UCP_Helpers::normalize_multiline(UCP_Options::get('delay_js_specified_scripts', ''));
        $excluded = apply_filters('ucp_delay_js_exclusions', UCP_Helpers::normalize_multiline(UCP_Options::get('delay_js_exclusions', '')));
        $hard_excluded = $this->hard_delay_js_exclusions($excluded, $html);
        $soft_excluded = $this->soft_delay_js_exclusions($excluded);
        $safe_mode = (bool) UCP_Options::get('delay_js_safe_mode');
        $forced_delay_hints = class_exists('UCP_PageSpeed_Browser_Scan') ? UCP_PageSpeed_Browser_Scan::delay_script_hints_for_current_request() : array();
        $delayed = 0;
        $forced_delayed = 0;
        $delayed_handles = array();
        $delayed_preload_urls = array();
        $protected_blocks = array();
        $html = $this->mask_delay_js_protected_blocks($html, $protected_blocks);
        $html = UCP_Helpers::safe_preg_replace_callback('#<script\b([^>]*)>(.*?)</script>#is', function ($matches) use ($hard_excluded, $soft_excluded, $safe_mode, $delay_mode, $specified, $forced_delay_hints, &$delayed, &$forced_delayed, &$delayed_handles, &$delayed_preload_urls) {
            $attrs = $matches[1];
            $body = $matches[2];
            if (!$this->is_delayable_script_type($attrs) || $this->script_has_no_delay_marker($attrs, $body) || false !== stripos($attrs, 'type="module"') || false !== stripos($attrs, "type='module'") || false !== stripos($attrs, 'importmap') || false !== stripos($attrs, 'nomodule') || preg_match('/\bdata-cfasync\s*=\s*(["\']?)false\1/i', $attrs)) {
                return $matches[0];
            }
            $script_src_for_hint = '';
            if (preg_match("/\\ssrc=[\"\x27]([^\"\x27]+)[\"\x27]/i", $attrs, $hint_src_match)) {
                $script_src_for_hint = html_entity_decode($hint_src_match[1], ENT_QUOTES);
            }
            $forced_by_browser_scan = $this->script_matches_browser_delay_hint($attrs, $body, $script_src_for_hint, $forced_delay_hints);
            foreach ($hard_excluded as $rule) {
                if ('' !== $rule && (false !== stripos($attrs, $rule) || false !== stripos($body, $rule) || ('' !== $script_src_for_hint && false !== stripos($script_src_for_hint, $rule)))) {
                    return $matches[0];
                }
            }
            foreach ($soft_excluded as $rule) {
                if (!$forced_by_browser_scan && '' !== $rule && (false !== stripos($attrs, $rule) || false !== stripos($body, $rule) || ('' !== $script_src_for_hint && false !== stripos($script_src_for_hint, $rule)))) {
                    return $matches[0];
                }
            }
            if ('specified' === $delay_mode && !$forced_by_browser_scan) {
                $matched = false;
                foreach ($specified as $rule) {
                    if ('' !== $rule && (false !== stripos($attrs, $rule) || false !== stripos($body, $rule))) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) {
                    return $matches[0];
                }
            }
            if (preg_match("/\\ssrc=[\"\x27]([^\"\x27]+)[\"\x27]/i", $attrs, $src_match)) {
                $script_src = html_entity_decode($src_match[1], ENT_QUOTES);
                if ($safe_mode && !$forced_by_browser_scan && UCP_Helpers::is_local_url($script_src)) {
                    return $matches[0];
                }
                $delayed++;
                $delayed_handles[] = $this->describe_delayed_script($attrs, $script_src);
                if ($forced_by_browser_scan) {
                    $forced_delayed++;
                }
                if (UCP_Options::get('enable_delay_js_preload_delayed_scripts') && $this->should_preload_delayed_script($script_src)) {
                    $delayed_preload_urls[] = esc_url_raw($script_src);
                }
                // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- rewrites already-enqueued script tags in buffered front-end HTML for delay loading.
                $bucket = $this->delay_bucket_for_script($attrs, '', $script_src);
                return '<script type="text/ucpdelayed" data-ucp-bucket="' . esc_attr($bucket) . '" data-ucp-src="' . esc_url($script_src) . '"' . $this->prepare_delay_placeholder_attrs($attrs, true) . '></script>';
            }
            if ($safe_mode && !$forced_by_browser_scan) {
                return $matches[0];
            }
            if ('' !== trim($body)) {
                $delayed++;
                $delayed_handles[] = $this->describe_delayed_script($attrs, 'inline');
                if ($forced_by_browser_scan) {
                    $forced_delayed++;
                }
                $bucket = $this->delay_bucket_for_script($attrs, $body, 'inline');
                return '<script type="text/ucpdelayed-inline" data-ucp-bucket="' . esc_attr($bucket) . '"' . $this->prepare_delay_placeholder_attrs($attrs, false) . '>' . $body . '</script>';
            }
            return $matches[0];
        }, $html);

        $html = $this->restore_delay_js_protected_blocks($html, $protected_blocks);
        if ($delayed < 1) {
            return $html;
        }
        UCP_Diagnostics::record('scripts', 'Delayed scripts in HTML', array('count' => $delayed, 'safe_mode' => $safe_mode ? 1 : 0, 'browser_scan_forced' => $forced_delayed, 'preloaded' => count(array_unique($delayed_preload_urls)), 'delayed' => array_slice(array_values(array_unique($delayed_handles)), 0, 25)));
        if (!empty($delayed_preload_urls) && UCP_Options::get('enable_delay_js_preload_delayed_scripts')) {
            $html = $this->inject_delayed_script_preloads($html, $delayed_preload_urls);
        }
        $timeout = max(1, absint(UCP_Options::get('delay_js_timeout', 4)) * 1000);
        $loader = $this->inline_script_tag($this->delay_loader_script($timeout, (bool) UCP_Options::get('delay_js_disable_click_delay')), array('id' => 'ucp-delay-loader'));
        $count = 0;
        $html = UCP_Helpers::safe_preg_replace_callback('#</body>#i', static function () use ($loader) {
            return $loader . '</body>';
        }, $html, 1, $count);
        if (!$count) {
            $html .= $loader;
        }
        return $html;
    }


    private function should_preload_delayed_script($src) {
        $src = trim((string) $src);
        if ('' === $src || 0 === strpos($src, 'data:') || 0 === strpos($src, 'blob:')) {
            return false;
        }
        if (UCP_Helpers::is_local_url($src)) {
            return true;
        }
        return (bool) wp_http_validate_url($src);
    }

    private function inject_delayed_script_preloads($html, $urls) {
        $urls = array_values(array_unique(array_filter((array) $urls, 'strlen')));
        $limit = max(0, min(12, absint(apply_filters('ucp_delay_js_preload_limit', 8))));
        if ($limit < 1 || empty($urls)) {
            return $html;
        }
        $links = '';
        foreach (array_slice($urls, 0, $limit) as $url) {
            $links .= $this->delayed_script_preload_link($url);
        }
        if ('' === $links) {
            return $html;
        }
        $count = 0;
        $html = UCP_Helpers::safe_preg_replace_callback('#</head>#i', static function () use ($links) {
            return $links . '</head>';
        }, (string) $html, 1, $count);
        if (!$count) {
            $html = $links . (string) $html;
        }
        return $html;
    }

    private function delayed_script_preload_link($url) {
        $url = esc_url($url);
        if ('' === $url) {
            return '';
        }
        $crossorigin = UCP_Helpers::is_local_url($url) ? '' : ' crossorigin';
        return '<link rel="preload" as="script" href="' . $url . '" data-ucp="delayed-script-preload"' . $crossorigin . '>
';
    }

    private function delay_bucket_for_script($attrs, $body = '', $src = '') {
        $haystack = strtolower((string) $attrs . ' ' . (string) $src . ' ' . substr((string) $body, 0, 500));
        foreach (array('jquery', 'wp-i18n', 'wp-hooks', 'wp-polyfill', 'woocommerce', 'wc-checkout', 'wc-cart-fragments', 'stripe', 'paypal', 'mollie', 'klarna', 'adyen', 'ideal', 'cookie', 'consent', 'complianz', 'cookiebot', 'recaptcha', 'grecaptcha', 'turnstile', 'hcaptcha') as $critical) {
            if (false !== strpos($haystack, $critical)) {
                return 'normal';
            }
        }
        foreach (array('gtag', 'google-analytics', 'googletagmanager', 'facebook', 'fbq', 'hotjar', 'clarity', 'adsbygoogle') as $interaction) {
            if (false !== strpos($haystack, $interaction)) {
                return 'interaction';
            }
        }
        return 'idle';
    }

    private function script_has_no_delay_marker($attrs, $body = '') {
        $haystack = strtolower((string) $attrs . ' ' . substr((string) $body, 0, 500));
        foreach (array('nowprocket', 'data-ucp-no-delay', 'data-no-defer', 'noucpdelay', 'data-no-delay', 'data-no-optimize', 'application/ld+json', 'application/json', 'text/template', 'text/html') as $marker) {
            if (false !== strpos($haystack, strtolower($marker))) {
                return true;
            }
        }
        return false;
    }

    private function describe_delayed_script($attrs, $src = '') {
        if (preg_match('/\bid\s*=\s*(["\'])([^"\']+)\1/i', (string) $attrs, $m)) {
            return 'id:' . sanitize_key($m[2]);
        }
        if (preg_match('/\bdata-wp-strategy\s*=\s*(["\'])([^"\']+)\1/i', (string) $attrs, $m)) {
            return 'wp-strategy:' . sanitize_key($m[2]);
        }
        $src = (string) $src;
        if ('' !== $src && 'inline' !== $src) {
            $path = wp_parse_url($src, PHP_URL_PATH);
            return 'src:' . sanitize_text_field(basename((string) $path));
        }
        return 'inline';
    }

    private function is_delayable_script_type($attrs) {
        if (!preg_match('/\btype\s*=\s*(["\'])([^"\']+)\1/i', (string) $attrs, $type_match)) {
            return true;
        }
        $type = strtolower(trim((string) $type_match[2]));
        return in_array($type, array('', 'text/javascript', 'application/javascript', 'application/ecmascript', 'text/ecmascript'), true);
    }

    private function script_matches_browser_delay_hint($attrs, $body, $script_src, $hints) {
        foreach ((array) $hints as $hint) {
            $hint = trim((string) $hint);
            if ('' === $hint) {
                continue;
            }
            if ('' !== $script_src && false !== stripos($script_src, $hint)) {
                return true;
            }
            if (false !== stripos((string) $attrs, $hint) || false !== stripos((string) $body, $hint)) {
                return true;
            }
        }
        return false;
    }

    private function runtime_delay_js_exclusions($excluded, $html = '') {
        return $this->hard_delay_js_exclusions($excluded, $html);
    }

    private function hard_delay_js_exclusions($excluded, $html = '') {
        $excluded = array_values(array_filter((array) $excluded, 'strlen'));

        $protected = array(
            // WordPress/WooCommerce essentials.
            'jquery', 'jquery-core', 'jquery-migrate', 'wp-hooks', 'wp-i18n', 'wp-polyfill', 'wp-util', 'wp-interactivity',
            'wc-cart-fragments', 'wc-checkout', 'woocommerce', 'add-to-cart-variation', 'single-product.min.js', 'js-cookie',
            'wc-blocks', 'wc-block-cart', 'wc-block-checkout', 'wc-price-format', 'cartflows', 'surecart',

            // Payments and checkout widgets.
            'stripe', 'js.stripe.com', 'stripe-elements', 'paypal', 'paypal.com/sdk/js', 'paypal-buttons', 'ppcp',
            'mollie', 'klarna', 'afterpay', 'adyen', 'ideal', 'apple-pay', 'google-pay', 'wcpay', 'woocommerce-payments',
            'amazon-pay', 'clearpay', 'braintree', 'razorpay', 'squareup', 'paddle', 'authorize.net',

            // Forms, captcha and consent layers.
            'contact-form-7', 'wpcf7', 'gravityforms', 'gform', 'wpforms', 'fluentform', 'ninja-forms', 'forminator',
            'recaptcha', 'grecaptcha', 'hcaptcha', 'h-captcha', 'turnstile', 'cf-turnstile', 'challenges.cloudflare.com',
            'cookiebot', 'complianz', 'cmplz', 'cookieyes', 'borlabs', 'CookieConsent', 'OneTrust', 'optanon', 'usercentrics', 'iubenda',

            // Builders and interactive UI libraries that frequently own above-the-fold behaviour.
            'elementor-frontend', 'elementor-pro-frontend', 'frontend-modules', 'elements-handlers', 'bricks', 'bricks.min.js',
            'Divi/js/scripts.min.js', 'et-builder', 'fl-builder', 'oxygen', 'breakdance', 'vc_frontend_js',
            'swiper', 'slick', 'flickity', 'splide', 'revslider', 'smart-slider', 'photoswipe', 'jquery.zoom.min.js',
        );

        return array_values(array_unique(array_filter(array_merge($excluded, $protected), 'strlen')));
    }

    private function soft_delay_js_exclusions($excluded) {
        $soft = array('gtm', 'googletagmanager', 'gtag', 'google-analytics', 'fbevents', 'facebook', 'hotjar', 'clarity', 'adsbygoogle', 'doubleclick');
        return array_values(array_unique(array_filter(apply_filters('ucp_delay_js_soft_exclusions', $soft, $excluded), 'strlen')));
    }

    private function mask_delay_js_protected_blocks($html, &$protected) {
        $pattern = '#<(svg|template|xmp|noscript|textarea|pre)\b[^>]*>.*?</\1>#is';
        $masked = preg_replace_callback($pattern, function ($matches) use (&$protected) {
            $token = '%%UCP_DELAY_PROTECTED_' . count($protected) . '%%';
            $protected[$token] = $matches[0];
            return $token;
        }, (string) $html);
        return is_string($masked) ? $masked : $html;
    }

    private function restore_delay_js_protected_blocks($html, $protected) {
        return empty($protected) ? $html : strtr((string) $html, $protected);
    }

    private function prepare_delay_placeholder_attrs($attrs, $has_src) {
        $attrs = preg_replace("/\stype=(\"|')[^\"']+\1/i", '', $attrs);
        $attrs = preg_replace("/\\s(?:async|defer)(?:=(?:\"[^\"]*\"|'[^']*'|[^\\s>]+))?/i", '', $attrs);
        if ($has_src) {
            $attrs = preg_replace("/\ssrc=(\"|')[^\"']+\1/i", '', $attrs);
        }
        return $attrs;
    }

    private function delay_loader_script($timeout, $disable_click_delay = false) {
        $timeout = max(500, absint($timeout));
        $disable = $disable_click_delay ? 'true' : 'false';
        return "(function(){var d=document,w=window,loaded=false,events=[],disableClick=" . $disable . ";var names=['keydown','keyup','mousedown','mouseup','mousemove','mouseover','mouseout','mouseenter','mouseleave','pointerdown','pointerup','pointermove','pointerover','touchstart','touchmove','touchend','touchcancel','wheel','mousewheel','scroll','input','submit','focus','blur','contextmenu'];if(!disableClick)names.push('click','dblclick');function save(e){if(events.length<32)events.push({t:e.type,x:e.clientX||0,y:e.clientY||0,k:e.key||''});run('interaction')}function opts(n){return{once:true,capture:true,passive:!(n==='input'||n==='submit'||/^key/.test(n))}}function on(){names.forEach(function(n){w.addEventListener(n,save,opts(n))})}function off(){names.forEach(function(n){w.removeEventListener(n,save,true)})}function replay(){setTimeout(function(){events.forEach(function(e){try{var el=d.elementFromPoint(e.x,e.y)||d.body,ev;if(/^key/.test(e.t)){ev=new KeyboardEvent(e.t,{bubbles:true,cancelable:true,key:e.k})}else if(/^pointer/.test(e.t)&&typeof PointerEvent==='function'){ev=new PointerEvent(e.t,{bubbles:true,cancelable:true,clientX:e.x,clientY:e.y})}else if(/^(mouse|click|dblclick|contextmenu)/.test(e.t)&&typeof MouseEvent==='function'){ev=new MouseEvent(e.t,{bubbles:true,cancelable:true,clientX:e.x,clientY:e.y})}else if(/^(wheel|mousewheel)/.test(e.t)&&typeof WheelEvent==='function'){ev=new WheelEvent(e.t,{bubbles:true,cancelable:true,clientX:e.x,clientY:e.y})}else if(/^touch/.test(e.t)&&typeof TouchEvent==='function'){ev=new TouchEvent(e.t,{bubbles:true,cancelable:true})}else{ev=new Event(e.t,{bubbles:true,cancelable:true})}el.dispatchEvent(ev)}catch(x){}});w.dispatchEvent(new Event('ucp-allScriptsLoaded'));},40)}function create(n,done){var s=d.createElement('script'),a=n.attributes,i;for(i=0;i<a.length;i++){var k=a[i].name;if(k.indexOf('data-ucp')!==0&&k!=='type')s.setAttribute(k,a[i].value)}if(n.getAttribute('nonce'))s.setAttribute('nonce',n.getAttribute('nonce'));if(n.getAttribute('data-ucp-src')){s.src=n.getAttribute('data-ucp-src');s.onload=s.onerror=done;d.head.appendChild(s)}else{s.text=n.textContent;d.head.appendChild(s);done()}}function collect(bucket){return [].slice.call(d.querySelectorAll('script[type=\"text/ucpdelayed\"],script[type=\"text/ucpdelayed-inline\"]')).filter(function(n){return !n.__ucpLoaded&&(!bucket||n.getAttribute('data-ucp-bucket')===bucket)})}function loadList(q,done){var i=0;function next(){if(i>=q.length){done&&done();return}q[i].__ucpLoaded=1;create(q[i++],next)}next()}function run(reason){if(loaded)return;loaded=true;off();loadList(collect('normal'),function(){loadList(collect('idle'),function(){loadList(collect('interaction'),replay)})})}function idle(){(w.requestIdleCallback||function(cb){setTimeout(cb,1)})(function(){if(!loaded){loadList(collect('normal'),function(){loadList(collect('idle'),function(){})})}}, {timeout:1500})}on();setTimeout(idle," . $timeout . ");setTimeout(function(){run('timeout')}," . max($timeout * 2, 8000) . ");w.addEventListener('pageshow',function(e){if(e.persisted)setTimeout(function(){run('bfcache')},0)});})();";
    }

    private function heartbeat_context() {
        if (is_admin()) {
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
            $is_editor = $screen && !empty($screen->base) && false !== strpos((string) $screen->base, 'post');
            return $is_editor ? 'editor' : 'backend';
        }
        return 'frontend';
    }

    private function heartbeat_behavior_for_context($context = null) {
        $context = $context ? $context : $this->heartbeat_context();
        $key = 'heartbeat_' . $context . '_behavior';
        $behavior = UCP_Options::get($key, 'reduce');
        return in_array($behavior, array('keep', 'reduce', 'disable'), true) ? $behavior : 'reduce';
    }

    public function maybe_disable_heartbeat_script() {
        if (!UCP_Options::get('enable_heartbeat_control')) {
            return;
        }
        if ('disable' === $this->heartbeat_behavior_for_context()) {
            wp_deregister_script('heartbeat');
        }
    }

    public function heartbeat_settings($settings) {
        if (!UCP_Options::get('enable_heartbeat_control')) {
            return $settings;
        }

        $context = $this->heartbeat_context();
        $behavior = $this->heartbeat_behavior_for_context($context);
        if ('keep' === $behavior || 'disable' === $behavior) {
            return $settings;
        }

        $interval = absint(UCP_Options::get('heartbeat_frequency', 60));
        if ('editor' === $context) {
            $interval = absint(UCP_Options::get('heartbeat_editor_frequency', $interval));
        } elseif ('backend' === $context) {
            $interval = absint(UCP_Options::get('heartbeat_backend_frequency', $interval));
        } else {
            $interval = absint(UCP_Options::get('heartbeat_frontend_frequency', $interval));
        }

        $settings['interval'] = max(15, $interval);
        return $settings;
    }

}
