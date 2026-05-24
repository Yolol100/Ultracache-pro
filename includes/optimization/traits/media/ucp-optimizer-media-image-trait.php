<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Optimizer_Media_Image_Trait {
    public function optimize_wp_attachment_image_attributes($attr, $attachment, $size) {
        if (!is_array($attr) || $this->should_skip_frontend_optimizations() || is_admin()) {
            return $attr;
        }
        if ($this->html_context_is_sensitive() || $this->asset_manager_flag('disable_lazyload')) {
            return $attr;
        }
        if (!UCP_Options::get('enable_lazy_images') && !UCP_Options::get('preload_critical_images')) {
            return $attr;
        }

        $class_name = isset($attr['class']) ? (string) $attr['class'] : '';
        $this->select_measured_lcp_candidate();
        $has_measured_lcp = '' !== (string) $this->ucp_lcp_candidate_src;
        $this->ucp_seen_images++;
        $exclude_leading = absint(UCP_Options::get('lazyload_exclude_leading_images', 1));
        $is_leading = $exclude_leading > 0 && $this->ucp_seen_images <= $exclude_leading;
        $is_excluded = $this->media_matches_lazyload_exclusion($class_name) || $this->image_matches_parent_exclusion($class_name);
        $is_lcp_candidate = !empty($attr['src']) && $this->image_url_matches_lcp_candidate((string) $attr['src']);
        if (!$is_lcp_candidate && !empty($attr['srcset'])) {
            $is_lcp_candidate = $this->srcset_matches_lcp_candidate((string) $attr['srcset']);
        }

        if (($is_lcp_candidate || (!$has_measured_lcp && $is_leading)) && !$this->ucp_lcp_image_seen) {
            $this->ucp_lcp_image_seen = true;
            $attr['fetchpriority'] = 'high';
            $attr['loading'] = 'eager';
        } elseif (!$is_excluded && UCP_Options::get('enable_lazy_images') && empty($attr['loading'])) {
            $attr['loading'] = 'lazy';
        }
        if (!$is_lcp_candidate && !$is_leading && !empty($attr['loading']) && 'lazy' === $attr['loading'] && empty($attr['fetchpriority'])) {
            $attr['fetchpriority'] = 'low';
        }

        if (empty($attr['decoding'])) {
            $attr['decoding'] = 'async';
        }

        return $attr;
    }
    public function lazyload_content($content) {
        return $this->lazyload_html_fragment($content);
    }

    public function lazyload_html_fragment($html) {
        if (!is_string($html) || '' === trim($html)) {
            return $html;
        }
        if ($this->should_skip_markup_optimizations($html)) {
            return $html;
        }
        if ($this->asset_manager_flag('disable_lazyload')) {
            return $html;
        }
        if (UCP_Options::get('enable_lazy_images') || UCP_Options::get('enable_add_image_dimensions') || UCP_Options::get('preload_critical_images')) {
            $this->detect_lcp_image_candidate($html);
            $candidate = preg_replace_callback('/<img\b([^>]*)>/i', array($this, 'optimize_image_loading_attribute'), $html);
            $html = is_string($candidate) ? $candidate : $html;
        }
        if (UCP_Options::get('enable_lazyload_background_images') || UCP_Options::get('enable_image_optimization') || UCP_Options::get('enable_webp_generation') || UCP_Options::get('enable_avif_generation')) {
            $html = $this->rewrite_background_image_urls_to_modern_variants($html);
        }
        if (UCP_Options::get('enable_lazy_iframes') || UCP_Options::get('enable_lazy_youtube_preview')) {
            $candidate = preg_replace_callback('/<iframe\b([^>]*)>(.*?)<\/iframe>/is', array($this, 'optimize_iframe_loading_attribute'), $html);
            $html = is_string($candidate) ? $candidate : $html;
        }
        return $html;
    }

    private function optimize_image_loading_attribute($matches) {
        $attrs = isset($matches[1]) ? (string) $matches[1] : '';
        $attrs = $this->maybe_rewrite_image_attrs_to_modern_variant($attrs);
        $this->ucp_seen_images++;

        $exclude_leading = absint(UCP_Options::get('lazyload_exclude_leading_images', 1));
        $is_leading = $exclude_leading > 0 && $this->ucp_seen_images <= $exclude_leading;
        $preload_count = absint(UCP_Options::get('preload_critical_images', 0));
        $is_excluded = $this->media_matches_lazyload_exclusion($attrs) || $this->image_matches_parent_exclusion($attrs);

        $current_src = '';
        if (preg_match('/\bsrc=["\']([^"\']+)["\']/i', $attrs, $current_src_match)) {
            $current_src = html_entity_decode($current_src_match[1], ENT_QUOTES);
        }
        $is_lcp_candidate = $current_src && $this->image_url_matches_lcp_candidate($current_src);
        if (!$is_lcp_candidate && preg_match('/\bsrcset=["\']([^"\']+)["\']/i', $attrs, $srcset_match)) {
            $is_lcp_candidate = $this->srcset_matches_lcp_candidate($srcset_match[1]);
        }
        if (($is_lcp_candidate || ($is_leading && !$this->ucp_lcp_image_seen && '' === (string) $this->ucp_lcp_candidate_src)) && !$this->ucp_lcp_image_seen) {
            $this->ucp_lcp_image_seen = true;
            $attrs = $this->force_lcp_image_attrs($attrs);
        }

        if (!preg_match('/\bdecoding\s*=/i', $attrs)) {
            $attrs .= ' decoding="async"';
        }

        if ($is_excluded) {
            return $this->maybe_add_missing_image_dimensions('<img' . $attrs . '>', $attrs);
        }

        $allow_preload = $preload_count > 0 && $this->ucp_preloaded_images < $preload_count;
        if ($allow_preload && !$is_lcp_candidate && '' !== (string) $this->ucp_lcp_candidate_src) {
            $allow_preload = false;
        }
        if ($allow_preload && preg_match('/\bsrc=["\']([^"\']+)["\']/i', $attrs, $src_match)) {
            $this->ucp_preloaded_images++;
            $entry = array('href' => html_entity_decode($src_match[1], ENT_QUOTES));
            if (preg_match('/\bsrcset=["\']([^"\']+)["\']/i', $attrs, $srcset_match)) {
                $entry['imagesrcset'] = html_entity_decode($srcset_match[1], ENT_QUOTES);
            }
            if (preg_match('/\bsizes=["\']([^"\']+)["\']/i', $attrs, $sizes_match)) {
                $entry['imagesizes'] = html_entity_decode($sizes_match[1], ENT_QUOTES);
            }
            $this->ucp_preload_image_entries[] = $entry;
        }


        if (!UCP_Options::get('enable_lazy_images') || $is_leading || preg_match('/\bloading\s*=/i', $attrs)) {
            return $this->maybe_add_missing_image_dimensions('<img' . $attrs . '>', $attrs);
        }

        $lazy_attrs = ' loading="lazy"' . $attrs;
        $lazy_attrs = $this->add_low_priority_if_lazy($lazy_attrs);
        if (UCP_Options::get('enable_lazyload_fade_in') && false === stripos($lazy_attrs, 'ucp-lazy-fade')) {
            $lazy_attrs = $this->append_class_attribute($lazy_attrs, 'ucp-lazy-fade');
        }
        return $this->maybe_add_missing_image_dimensions('<img' . $lazy_attrs . '>', $attrs);
    }

    private function add_responsive_image_attrs($attrs) {
        $attrs = (string) $attrs;
        if (!preg_match('/\bsrc=["\']([^"\']+)["\']/i', $attrs, $src_match)) {
            return $attrs;
        }
        $src = html_entity_decode($src_match[1], ENT_QUOTES);
        if ($this->image_src_is_unsafe_for_responsive_rewrite($src, $attrs)) {
            return $this->apply_device_fetchpriority_rules($attrs);
        }
        $attachment_id = function_exists('attachment_url_to_postid') ? absint(attachment_url_to_postid($src)) : 0;
        if ($attachment_id > 0) {
            if (!preg_match('/\bsrcset\s*=/i', $attrs) && function_exists('wp_get_attachment_image_srcset')) {
                $srcset = wp_get_attachment_image_srcset($attachment_id, 'full');
                if ($srcset) {
                    $attrs .= ' srcset="' . esc_attr($srcset) . '"';
                }
            }
            if (!preg_match('/\bsizes\s*=/i', $attrs) && preg_match('/\bloading=["\']lazy["\']/i', $attrs)) {
                $attrs .= ' sizes="auto, (max-width: 768px) 100vw, 768px"';
            }
        }
        if (!preg_match('/\b(width|height)\s*=/i', $attrs)) {
            $dims = $this->image_dimensions_for_url($src, $attachment_id);
            if (!empty($dims['width']) && !preg_match('/\bwidth\s*=/i', $attrs)) {
                $attrs .= ' width="' . absint($dims['width']) . '"';
            }
            if (!empty($dims['height']) && !preg_match('/\bheight\s*=/i', $attrs)) {
                $attrs .= ' height="' . absint($dims['height']) . '"';
            }
        }
        return $this->apply_device_fetchpriority_rules($attrs);
    }

    private function image_src_is_unsafe_for_responsive_rewrite($src, $attrs) {
        $src = trim((string) $src);
        $path = strtolower((string) wp_parse_url($src, PHP_URL_PATH));
        if ('' === $src || 0 === strpos($src, 'data:') || false !== stripos($src, 'base64') || preg_match('/\.svg(?:$|\?)/i', $path)) {
            return true;
        }
        if (!UCP_Helpers::is_local_url($src) && !preg_match('/\b(width|height)\s*=/i', (string) $attrs)) {
            return true;
        }
        foreach (array('pixel', 'tracking', 'placeholder', 'spacer', 'blank.gif') as $needle) {
            if (false !== stripos($src . ' ' . $attrs, $needle)) {
                return true;
            }
        }
        return false;
    }

    private function image_dimensions_for_url($url, $attachment_id = 0) {
        $cache_key = 'ucp_img_dims_' . md5((string) $url);
        $cached = get_transient($cache_key);
        if (is_array($cached) && !empty($cached['width']) && !empty($cached['height'])) {
            return $cached;
        }
        $dims = array('width' => 0, 'height' => 0);
        if ($attachment_id > 0) {
            $meta = wp_get_attachment_metadata($attachment_id);
            if (is_array($meta) && !empty($meta['width']) && !empty($meta['height'])) {
                $dims = array('width' => absint($meta['width']), 'height' => absint($meta['height']));
            }
        }
        if (empty($dims['width']) && UCP_Helpers::is_local_url($url)) {
            $path = UCP_Helpers::local_path_from_url($url);
            if ($path && is_file($path) && function_exists('getimagesize')) {
                $size = @getimagesize($path);
                if (is_array($size) && !empty($size[0]) && !empty($size[1])) {
                    $dims = array('width' => absint($size[0]), 'height' => absint($size[1]));
                }
            }
        }
        if (!empty($dims['width']) && !empty($dims['height'])) {
            set_transient($cache_key, $dims, WEEK_IN_SECONDS);
        }
        return $dims;
    }

    private function apply_device_fetchpriority_rules($attrs) {
        $rules = UCP_Helpers::normalize_multiline(UCP_Options::get('fetchpriority_rules', ''));
        if (empty($rules)) {
            return $attrs;
        }
        $device = (function_exists('wp_is_mobile') && wp_is_mobile()) ? 'mobile' : 'desktop';
        $context = $this->fetchpriority_context();
        foreach ($rules as $rule) {
            $parts = array_map('trim', explode('|', (string) $rule));
            $selector = isset($parts[0]) ? sanitize_text_field($parts[0]) : '';
            $rule_device = isset($parts[1]) ? sanitize_key($parts[1]) : 'all';
            $rule_context = isset($parts[2]) ? sanitize_key($parts[2]) : 'all';
            $priority = isset($parts[3]) ? sanitize_key($parts[3]) : (isset($parts[2]) && in_array(sanitize_key($parts[2]), array('high','low','auto'), true) ? sanitize_key($parts[2]) : 'high');
            if (in_array($rule_context, array('high','low','auto'), true)) {
                $rule_context = 'all';
            }
            if ('' === $selector || !in_array($priority, array('high', 'low', 'auto'), true)) {
                continue;
            }
            if (!in_array($rule_device, array('all', $device), true) || !in_array($rule_context, array('all', $context), true)) {
                continue;
            }
            if ('high' === $priority && ($this->ucp_fetchpriority_high_assigned || preg_match('/\bloading=["\']lazy["\']/i', $attrs))) {
                continue;
            }
            if (!$this->image_attrs_match_simple_selector($attrs, $selector)) {
                continue;
            }
            if ('high' === $priority) {
                $this->ucp_fetchpriority_high_assigned = true;
            }
            if (preg_match('/\bfetchpriority\s*=/i', $attrs)) {
                return preg_replace('/\sfetchpriority=["\'][^"\']*["\']/i', ' fetchpriority="' . esc_attr($priority) . '"', $attrs, 1);
            }
            return $attrs . ' fetchpriority="' . esc_attr($priority) . '"';
        }
        return $attrs;
    }

    private function fetchpriority_context() {
        if (function_exists('is_product') && is_product()) {
            return 'product';
        }
        if (function_exists('is_cart') && is_cart()) {
            return 'cart';
        }
        if (function_exists('is_checkout') && is_checkout()) {
            return 'checkout';
        }
        if (function_exists('is_front_page') && is_front_page()) {
            return 'front_page';
        }
        if (function_exists('is_singular') && is_singular()) {
            return 'singular';
        }
        if (function_exists('is_archive') && is_archive()) {
            return 'archive';
        }
        return 'all';
    }

    private function image_attrs_match_simple_selector($attrs, $selector) {
        $selector = trim((string) $selector);
        if ('' === $selector) {
            return false;
        }
        if (0 === strpos($selector, '.')) {
            $class = preg_quote(substr($selector, 1), '/');
            return (bool) preg_match('/\bclass=["\'][^"\']*\b' . $class . '\b/i', $attrs);
        }
        if (0 === strpos($selector, '#')) {
            $id = preg_quote(substr($selector, 1), '/');
            return (bool) preg_match('/\bid=["\']' . $id . '["\']/i', $attrs);
        }
        return false !== stripos($attrs, $selector);
    }

    private function force_lcp_image_attrs($attrs) {
        $attrs = (string) $attrs;
        if (preg_match("/\\bfetchpriority\\s*=/i", $attrs)) {
            $attrs = preg_replace("/\\sfetchpriority=[\"\047][^\"\047]*[\"\047]/i", " fetchpriority=\"high\"", $attrs, 1);
        } else {
            $attrs .= " fetchpriority=\"high\"";
        }
        $this->ucp_fetchpriority_high_assigned = true;
        if (preg_match("/\\bloading\\s*=/i", $attrs)) {
            $attrs = preg_replace("/\\sloading=[\"\047][^\"\047]*[\"\047]/i", " loading=\"eager\"", $attrs, 1);
        } else {
            $attrs .= " loading=\"eager\"";
        }
        if (!preg_match("/\\bdecoding\\s*=/i", $attrs)) {
            $attrs .= " decoding=\"async\"";
        }
        return $attrs;
    }

    private function add_low_priority_if_lazy($attrs) {
        if (!preg_match("/\\bloading=[\"\047]lazy[\"\047]/i", (string) $attrs) || preg_match("/\\bfetchpriority\\s*=/i", (string) $attrs)) {
            return $attrs;
        }
        return (string) $attrs . " fetchpriority=\"low\"";
    }

    private function image_url_matches_lcp_candidate($url) {
        $candidate = (string) $this->ucp_lcp_candidate_src;
        if ('' === $candidate || '' === (string) $url) {
            return false;
        }
        $url = $this->normalize_lcp_image_candidate_url($url);
        if ('' === $url) {
            return false;
        }
        if ($url === $candidate) {
            return true;
        }
        $modern = $this->modern_variant_url_for_image_url($candidate);
        if ($modern && $modern === $url) {
            return true;
        }
        $a = preg_replace('/\-(?:\d{2,5})x(?:\d{2,5})(?=\.(?:jpe?g|png|webp|avif)$)/i', '', (string) wp_parse_url($url, PHP_URL_PATH));
        $b = preg_replace('/\-(?:\d{2,5})x(?:\d{2,5})(?=\.(?:jpe?g|png|webp|avif)$)/i', '', (string) wp_parse_url($candidate, PHP_URL_PATH));
        $a = preg_replace('/\.(jpe?g|png|webp|avif)(\?.*)?$/i', '', $a);
        $b = preg_replace('/\.(jpe?g|png|webp|avif)(\?.*)?$/i', '', $b);
        return '' !== $a && $a === $b;
    }

    private function srcset_matches_lcp_candidate($srcset) {
        foreach (explode(',', html_entity_decode((string) $srcset, ENT_QUOTES)) as $candidate) {
            $parts = preg_split('/\s+/', trim((string) $candidate));
            $url = isset($parts[0]) ? $parts[0] : '';
            if ('' !== $url && $this->image_url_matches_lcp_candidate($url)) {
                return true;
            }
        }
        return false;
    }

    private function rewrite_background_image_urls_to_modern_variants($html) {
        if (!is_string($html) || false === stripos($html, 'url(')) {
            return $html;
        }
        return preg_replace_callback('/url\(([^)]+)\)/i', function ($matches) {
            $raw = trim(html_entity_decode((string) $matches[1], ENT_QUOTES), " \t\n\r\0\x0B'\"");
            $url = $this->normalize_lcp_image_candidate_url($raw);
            if (!$url) {
                return $matches[0];
            }
            $modern = $this->modern_variant_url_for_image_url($url);
            if (!$modern || $modern === $url) {
                return $matches[0];
            }
            if (class_exists('UCP_Diagnostics')) {
                UCP_Diagnostics::record('images', 'Rewrote background image to modern variant.', array('from' => $url, 'to' => $modern));
            }
            return 'url(' . esc_url($modern) . ')';
        }, $html);
    }

    private function media_matches_lazyload_exclusion($subject) {
        $rules = UCP_Helpers::normalize_multiline(UCP_Options::get('lazyload_exclusions', ''));
        foreach ($rules as $rule) {
            $needle = trim((string) $rule);
            if ('' !== $needle && false !== stripos((string) $subject, $needle)) {
                return true;
            }
        }
        return false;
    }

    private function image_matches_parent_exclusion($attrs) {
        $rules = UCP_Helpers::normalize_multiline(UCP_Options::get('lazyload_parent_exclusions', ''));
        foreach ($rules as $rule) {
            $needle = ltrim(trim((string) $rule), '.#');
            if ('' !== $needle && false !== stripos($attrs, $needle)) {
                return true;
            }
        }
        return false;
    }

    private function maybe_add_missing_image_dimensions($tag, $attrs) {
        $attrs = $this->add_responsive_image_attrs($attrs);
        if (!UCP_Options::get('enable_add_image_dimensions')) {
            return '<img' . $attrs . '>';
        }
        if (!preg_match('/\bsrc=["\']([^"\']+)["\']/i', $attrs, $src_match)) {
            return '<img' . $attrs . '>';
        }
        $has_width = preg_match('/\bwidth\s*=/i', $attrs);
        $has_height = preg_match('/\bheight\s*=/i', $attrs);
        if ($has_width && $has_height) {
            return '<img' . $attrs . '>';
        }
        $src = html_entity_decode($src_match[1], ENT_QUOTES);
        if (0 === strpos($src, 'data:') || false !== stripos($src, '.svg')) {
            return '<img' . $attrs . '>';
        }
        $dims = $this->image_dimensions_for_url($src, function_exists('attachment_url_to_postid') ? absint(attachment_url_to_postid($src)) : 0);
        if (empty($dims['width']) || empty($dims['height'])) {
            return '<img' . $attrs . '>';
        }
        if (!$has_width) {
            $attrs .= ' width="' . absint($dims['width']) . '"';
        }
        if (!$has_height) {
            $attrs .= ' height="' . absint($dims['height']) . '"';
        }
        return '<img' . $attrs . '>';
    }
}
