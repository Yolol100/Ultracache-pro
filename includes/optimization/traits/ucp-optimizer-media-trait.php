<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Optimizer_Media_Trait {
    // From includes/optimization/traits/media/ucp-optimizer-media-preload-trait.php
    private function reset_media_scan_state() {
        $this->ucp_lcp_image_seen = false;
        $this->ucp_seen_images = 0;
        $this->ucp_preloaded_images = 0;
        $this->ucp_preload_image_urls = array();
        $this->ucp_preload_image_entries = array();
        $this->ucp_background_preloaded = false;
        $this->ucp_lcp_candidate_src = '';
        $this->ucp_lcp_candidate_is_background = false;
    }

    private function inject_preload_image_links($html) {
        if (!is_string($html)) {
            return $html;
        }

        $entries = array();
        foreach ((array) $this->ucp_preload_image_entries as $entry) {
            if (empty($entry['href'])) {
                continue;
            }
            $entries[] = $entry;
        }
        foreach ((array) $this->ucp_preload_image_urls as $url) {
            if (empty($url)) {
                continue;
            }
            $entries[] = array('href' => $url);
        }

        if (empty($entries)) {
            return $html;
        }

        $seen = array();
        $links = '';
        foreach ($entries as $entry) {
            $href = html_entity_decode((string) $entry['href'], ENT_QUOTES);
            if ('' === $href || isset($seen[$href]) || 0 === stripos($href, 'data:')) {
                continue;
            }
            $seen[$href] = true;
            $link = '<link rel="preload" as="image" fetchpriority="high" href="' . esc_url($href) . '"';
            if (!empty($entry['imagesrcset'])) {
                $link .= ' imagesrcset="' . esc_attr($entry['imagesrcset']) . '"';
            }
            if (!empty($entry['imagesizes'])) {
                $link .= ' imagesizes="' . esc_attr($entry['imagesizes']) . '"';
            }
            $link .= '>' . "\n";
            $links .= $link;
        }
        if ('' === $links) {
            return $html;
        }
        $count = 0;
        $html = preg_replace('#</head>#i', $links . '</head>', $html, 1, $count);
        return $count ? $html : $links . $html;
    }

    private function collect_background_lcp_preloads($html) {
        if (!is_string($html) || $this->ucp_background_preloaded || absint(UCP_Options::get('preload_critical_images', 0)) < 1) {
            return $html;
        }

        if ('' === (string) $this->ucp_lcp_candidate_src) {
            $this->detect_lcp_image_candidate($html);
        }

        if ($this->ucp_lcp_candidate_is_background && '' !== (string) $this->ucp_lcp_candidate_src) {
            $this->ucp_preload_image_urls[] = $this->ucp_lcp_candidate_src;
            $this->ucp_background_preloaded = true;
            if (class_exists('UCP_Diagnostics')) {
                UCP_Diagnostics::record('lcp', 'Preloaded selected background LCP candidate.', array('url' => $this->ucp_lcp_candidate_src));
            }
        }
        return $html;
    }

    private function detect_lcp_image_candidate($html) {
        if (!is_string($html) || '' !== (string) $this->ucp_lcp_candidate_src) {
            return;
        }
        if (class_exists('UCP_PageSpeed_Browser_Scan')) {
            $hint = UCP_PageSpeed_Browser_Scan::lcp_hint_for_current_request();
            if (!empty($hint['url'])) {
                $url = $this->normalize_lcp_image_candidate_url($hint['url']);
                if ($url) {
                    $this->ucp_lcp_candidate_src = esc_url_raw($url);
                    $this->ucp_lcp_candidate_is_background = !empty($hint['background']);
                    if (class_exists('UCP_Diagnostics')) {
                        UCP_Diagnostics::record('lcp', 'Selected browser-rendered LCP candidate.', array(
                            'url' => $this->ucp_lcp_candidate_src,
                            'background' => $this->ucp_lcp_candidate_is_background ? 1 : 0,
                            'score' => isset($hint['score']) ? absint($hint['score']) : 0,
                        ));
                    }
                    return;
                }
            }
        }

        $scan = substr($html, 0, 220000);
        $best = array('score' => 0, 'url' => '', 'background' => false);

        if (preg_match_all('/<img\b([^>]*)>/i', $scan, $img_matches, PREG_OFFSET_CAPTURE)) {
            foreach ($img_matches[1] as $img) {
                $attrs = (string) $img[0];
                $offset = (int) $img[1];
                if (!preg_match('/\bsrc=["\']([^"\']+)["\']/i', $attrs, $src_match)) {
                    continue;
                }
                $raw_candidate_src = $src_match[1];
                if (preg_match('/\bsrcset=["\']([^"\']+)["\']/i', $attrs, $srcset_match)) {
                    $srcset_candidate = $this->best_lcp_srcset_candidate($srcset_match[1]);
                    if ('' !== $srcset_candidate) {
                        $raw_candidate_src = $srcset_candidate;
                    }
                }
                $url = $this->normalize_lcp_image_candidate_url($raw_candidate_src);
                if (!$url || $this->lcp_candidate_is_low_value($url, $attrs)) {
                    continue;
                }
                $score = $this->score_lcp_candidate($url, $attrs, $offset, false);
                if ($score > $best['score']) {
                    $best = array('score' => $score, 'url' => $url, 'background' => false);
                }
            }
        }

        if (preg_match_all('/<([a-z0-9]+)\b([^>]*)>/i', $scan, $tag_matches, PREG_OFFSET_CAPTURE)) {
            foreach ($tag_matches[2] as $tag) {
                $attrs = (string) $tag[0];
                $offset = (int) $tag[1];
                if (false === stripos($attrs, 'background') && false === stripos($attrs, 'url(') && false === stripos($attrs, 'data-settings')) {
                    continue;
                }
                $urls = array();
                if (preg_match_all('/url\(([^)]+)\)/i', $attrs, $bg_matches)) {
                    $urls = array_merge($urls, $bg_matches[1]);
                }
                if (preg_match('/\bdata-settings=["\']([^"\']+)["\']/i', $attrs, $settings_match)) {
                    $decoded = html_entity_decode($settings_match[1], ENT_QUOTES);
                    $decoded = str_replace('\\/', '/', $decoded);
                    if (preg_match_all('#https?://[^"\'\s<>]+\.(?:jpe?g|png|webp|avif)(?:\?[^"\'\s<>]*)?#i', $decoded, $url_matches)) {
                        $urls = array_merge($urls, $url_matches[0]);
                    }
                    if (preg_match_all('#\/[^"\'\s<>]+\.(?:jpe?g|png|webp|avif)(?:\?[^"\'\s<>]*)?#i', $decoded, $local_matches)) {
                        $urls = array_merge($urls, $local_matches[0]);
                    }
                }
                foreach ($urls as $raw_url) {
                    $url = $this->normalize_lcp_image_candidate_url($raw_url);
                    if (!$url || $this->lcp_candidate_is_low_value($url, $attrs)) {
                        continue;
                    }
                    $score = $this->score_lcp_candidate($url, $attrs, $offset, true);
                    if ($score > $best['score']) {
                        $best = array('score' => $score, 'url' => $url, 'background' => true);
                    }
                }
            }
        }

        if (!empty($best['url'])) {
            $this->ucp_lcp_candidate_src = esc_url_raw($best['url']);
            $this->ucp_lcp_candidate_is_background = !empty($best['background']);
            if (class_exists('UCP_Diagnostics')) {
                UCP_Diagnostics::record('lcp', 'Selected LCP candidate.', array(
                    'url' => $this->ucp_lcp_candidate_src,
                    'background' => $this->ucp_lcp_candidate_is_background ? 1 : 0,
                    'score' => (int) $best['score'],
                ));
            }
        }
    }

    private function score_lcp_candidate($url, $attrs, $offset, $background) {
        $attrs = strtolower((string) $attrs);
        $url_l = strtolower((string) $url);
        $score = max(20, 420 - min(250, (int) floor($offset / 850)));
        if ($background) {
            $score += 35;
        }
        foreach (array('hero', 'lcp', 'above-fold', 'banner', 'cover', 'elementor-top-section', 'wp-block-cover', 'swiper-slide-active', 'splide__slide is-active') as $needle) {
            if (false !== strpos($attrs, $needle)) {
                $score += 70;
            }
        }
        if (preg_match('/\bwidth=["\']?(\d+)/i', $attrs, $w) && preg_match('/\bheight=["\']?(\d+)/i', $attrs, $h)) {
            $area = max(0, (int) $w[1]) * max(0, (int) $h[1]);
            $score += min(160, (int) floor($area / 4500));
        }
        if (preg_match('/-(\d{3,4})x(\d{3,4})\.(?:jpe?g|png|webp|avif)/i', $url_l, $m)) {
            $score += min(110, (int) floor(((int) $m[1] * (int) $m[2]) / 7000));
        }
        if (preg_match('/\.(webp|avif)(\?|$)/i', $url_l)) {
            $score += 10;
        }
        if ($this->lcp_candidate_is_low_value($url_l, $attrs)) {
            $score -= 220;
        }
        return $score;
    }

    private function lcp_candidate_is_low_value($url, $attrs) {
        $haystack = strtolower((string) $url . ' ' . (string) $attrs);
        foreach (array('logo', 'site-logo', 'custom-logo', 'icon', 'avatar', 'badge', 'placeholder', 'spinner', 'loader', 'thumb', 'thumbnail', 'woocommerce-placeholder', '1x1', 'pixel') as $needle) {
            if (false !== strpos($haystack, $needle)) {
                return true;
            }
        }
        return (bool) preg_match('/\.(svg|gif)(\?|$)/i', $haystack);
    }

    private function normalize_lcp_image_candidate_url($raw_url) {
        $url = trim(html_entity_decode((string) $raw_url, ENT_QUOTES), " \t\n\r\0\x0B'\"");
        if ('' === $url || 0 === stripos($url, 'data:')) {
            return '';
        }
        if (0 === strpos($url, '//')) {
            $url = (is_ssl() ? 'https:' : 'http:') . $url;
        }
        if (0 === strpos($url, '/')) {
            $url = home_url($url);
        }
        if (!preg_match('/\.(jpe?g|png|webp|avif)(\?|$)/i', $url)) {
            return '';
        }
        if (!UCP_Helpers::is_local_url($url)) {
            return '';
        }
        $modern = $this->modern_variant_url_for_image_url($url);
        if ($modern) {
            $url = $modern;
        }
        return esc_url_raw($url);
    }

    private function best_lcp_srcset_candidate($srcset) {
        $srcset = html_entity_decode((string) $srcset, ENT_QUOTES);
        $candidates = array();
        foreach (explode(',', $srcset) as $candidate) {
            $candidate = trim($candidate);
            if ('' === $candidate) {
                continue;
            }
            $parts = preg_split('/\s+/', $candidate);
            $url = isset($parts[0]) ? trim((string) $parts[0]) : '';
            if ('' === $url) {
                continue;
            }
            $width = 0;
            $density = 0.0;
            foreach ($parts as $part) {
                if (preg_match('/^(\d+)w$/', $part, $m)) {
                    $width = (int) $m[1];
                    break;
                }
                if (preg_match('/^(\d+(?:\.\d+)?)x$/', $part, $m)) {
                    $density = (float) $m[1];
                }
            }
            $candidates[] = array(
                'url' => $url,
                'width' => $width,
                'density' => $density,
            );
        }
        if (empty($candidates)) {
            return '';
        }

        // WP Rocket/FlyingPress-style critical image handling should not blindly preload the largest desktop srcset
        // candidate for a mobile PageSpeed run. Pick the smallest candidate that covers the likely viewport instead.
        $target = $this->lcp_srcset_target_width();
        $best = null;
        foreach ($candidates as $candidate) {
            $width = (int) $candidate['width'];
            if ($width > 0 && $width >= $target && (null === $best || $width < (int) $best['width'])) {
                $best = $candidate;
            }
        }
        if (null !== $best) {
            return $best['url'];
        }

        // Density-only srcsets are already browser-picked; use the first resource as the preload href and preserve
        // the full imagesrcset/imagesizes attributes on the preload tag elsewhere.
        foreach ($candidates as $candidate) {
            if (empty($candidate['width']) && !empty($candidate['density'])) {
                return $candidate['url'];
            }
        }

        usort($candidates, function ($a, $b) {
            return (int) $b['width'] <=> (int) $a['width'];
        });
        return isset($candidates[0]['url']) ? $candidates[0]['url'] : '';
    }

    private function lcp_srcset_target_width() {
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))) : '';
        $is_mobile = false;
        if (function_exists('wp_is_mobile') && wp_is_mobile()) {
            $is_mobile = true;
        }
        if (false !== strpos($ua, 'mobile') || false !== strpos($ua, 'android') || false !== strpos($ua, 'iphone') || false !== strpos($ua, 'pagespeed')) {
            $is_mobile = true;
        }
        $target = $is_mobile ? 960 : 1440;
        return (int) apply_filters('ucp_lcp_srcset_target_width', $target, $is_mobile);
    }

    private function maybe_rewrite_image_attrs_to_modern_variant($attrs) {
        if (empty(UCP_Options::get('enable_image_optimization')) && empty(UCP_Options::get('enable_webp_generation')) && empty(UCP_Options::get('enable_avif_generation'))) {
            return $attrs;
        }
        if (!preg_match('/\bsrc=["\']([^"\']+)["\']/i', $attrs, $src_match)) {
            return $attrs;
        }
        $src = html_entity_decode($src_match[1], ENT_QUOTES);
        $modern = $this->modern_variant_url_for_image_url($src);
        if (!$modern || $modern === $src) {
            return $attrs;
        }
        $attrs = preg_replace('/\ssrc=["\'][^"\']+["\']/i', ' src="' . esc_url($modern) . '"', $attrs, 1);
        if (preg_match('/\bsrcset\s*=/i', $attrs)) {
            $attrs = preg_replace('/\ssrcset=["\'][^"\']+["\']/i', '', $attrs, 1);
        }
        return $attrs;
    }

    private function modern_variant_url_for_image_url($url) {
        $url = esc_url_raw((string) $url);
        if ('' === $url || !UCP_Helpers::is_local_url($url)) {
            return '';
        }
        $uploads = wp_get_upload_dir();
        if (empty($uploads['baseurl']) || empty($uploads['basedir']) || 0 !== strpos($url, $uploads['baseurl'])) {
            return '';
        }
        $path = $uploads['basedir'] . substr($url, strlen($uploads['baseurl']));
        if (!preg_match('/\.(jpe?g|png)$/i', $path) || !is_file($path)) {
            return '';
        }
        $accept = isset($_SERVER['HTTP_ACCEPT']) ? strtolower(sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT']))) : '';
        $prefer_avif = !empty(UCP_Options::get('enable_avif_generation')) && false !== strpos($accept, 'image/avif');
        $candidates = $prefer_avif ? array('avif', 'webp') : array('webp', 'avif');
        foreach ($candidates as $ext) {
            if ('avif' === $ext && empty(UCP_Options::get('enable_avif_generation'))) {
                continue;
            }
            if ('webp' === $ext && empty(UCP_Options::get('enable_webp_generation'))) {
                continue;
            }
            $variant_path = preg_replace('/\.(jpe?g|png)$/i', '.' . $ext, $path);
            if ($variant_path && is_file($variant_path)) {
                return str_replace($uploads['basedir'], $uploads['baseurl'], $variant_path);
            }
        }
        return '';
    }

    // From includes/optimization/traits/media/ucp-optimizer-media-image-trait.php
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
        $this->ucp_seen_images++;
        $exclude_leading = absint(UCP_Options::get('lazyload_exclude_leading_images', 1));
        $is_leading = $exclude_leading > 0 && $this->ucp_seen_images <= $exclude_leading;
        $is_excluded = $this->media_matches_lazyload_exclusion($class_name) || $this->image_matches_parent_exclusion($class_name);

        if ($is_leading && !$this->ucp_lcp_image_seen) {
            $this->ucp_lcp_image_seen = true;
            $attr['fetchpriority'] = 'high';
            $attr['loading'] = 'eager';
        } elseif (!$is_excluded && UCP_Options::get('enable_lazy_images') && empty($attr['loading'])) {
            $attr['loading'] = 'lazy';
        }
        if (!$is_leading && !empty($attr['loading']) && 'lazy' === $attr['loading'] && empty($attr['fetchpriority'])) {
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
            $html = preg_replace_callback('/<img\b([^>]*)>/i', array($this, 'optimize_image_loading_attribute'), $html);
        }
        if (UCP_Options::get('enable_lazyload_background_images') || UCP_Options::get('enable_image_optimization') || UCP_Options::get('enable_webp_generation') || UCP_Options::get('enable_avif_generation')) {
            $html = $this->rewrite_background_image_urls_to_modern_variants($html);
        }
        if (UCP_Options::get('enable_lazy_iframes') || UCP_Options::get('enable_lazy_youtube_preview')) {
            $html = preg_replace_callback('/<iframe\b([^>]*)>(.*?)<\/iframe>/is', array($this, 'optimize_iframe_loading_attribute'), $html);
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

    private function force_lcp_image_attrs($attrs) {
        $attrs = (string) $attrs;
        if (preg_match("/\\bfetchpriority\\s*=/i", $attrs)) {
            $attrs = preg_replace("/\\sfetchpriority=[\"\047][^\"\047]*[\"\047]/i", " fetchpriority=\"high\"", $attrs, 1);
        } else {
            $attrs .= " fetchpriority=\"high\"";
        }
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
        $url = esc_url_raw((string) $url);
        if ($url === $candidate) {
            return true;
        }
        $modern = $this->modern_variant_url_for_image_url($candidate);
        if ($modern && $modern === $url) {
            return true;
        }
        $a = preg_replace('/\.(jpe?g|png|webp|avif)(\?.*)?$/i', '', (string) wp_parse_url($url, PHP_URL_PATH));
        $b = preg_replace('/\.(jpe?g|png|webp|avif)(\?.*)?$/i', '', (string) wp_parse_url($candidate, PHP_URL_PATH));
        return '' !== $a && $a === $b;
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
        if (!UCP_Options::get('enable_add_image_dimensions')) {
            return $tag;
        }
        if (!preg_match('/\bsrc=["\']([^"\']+)["\']/i', $attrs, $src_match)) {
            return $tag;
        }
        $has_width = preg_match('/\bwidth\s*=/i', $attrs);
        $has_height = preg_match('/\bheight\s*=/i', $attrs);
        if ($has_width && $has_height) {
            return $tag;
        }
        $src = html_entity_decode($src_match[1], ENT_QUOTES);
        if (0 === strpos($src, 'data:') || false !== stripos($src, '.svg')) {
            return $tag;
        }
        $uploads = wp_get_upload_dir();
        if (empty($uploads['baseurl']) || empty($uploads['basedir']) || 0 !== strpos($src, $uploads['baseurl'])) {
            return $tag;
        }
        $path = $uploads['basedir'] . substr($src, strlen($uploads['baseurl']));
        if (!is_file($path)) {
            return $tag;
        }
        $size = @getimagesize($path);
        if (empty($size[0]) || empty($size[1])) {
            return $tag;
        }
        $insert = '';
        if (!$has_width) {
            $insert .= ' width="' . absint($size[0]) . '"';
        }
        if (!$has_height) {
            $insert .= ' height="' . absint($size[1]) . '"';
        }
        return $insert ? preg_replace('/<img\b/i', '<img' . $insert, $tag, 1) : $tag;
    }

    // From includes/optimization/traits/media/ucp-optimizer-media-iframe-trait.php
    private function optimize_iframe_loading_attribute($matches) {
        $attrs = isset($matches[1]) ? (string) $matches[1] : '';
        $body = isset($matches[2]) ? (string) $matches[2] : '';
        $original = '<iframe' . $attrs . '>' . $body . '</iframe>';

        if ($this->media_matches_lazyload_exclusion($attrs . ' ' . $body) || $this->image_matches_parent_exclusion($attrs)) {
            return $original;
        }

        if (UCP_Options::get('enable_lazy_iframes') && !preg_match('/\bloading\s*=/i', $attrs)) {
            $attrs .= ' loading="lazy"';
        }

        if (UCP_Options::get('enable_lazy_youtube_preview') && preg_match('/\bsrc=["\']([^"\']+)["\']/i', $attrs, $src_match)) {
            $src = html_entity_decode($src_match[1], ENT_QUOTES);
            $video_id = $this->extract_youtube_video_id($src);
            if ($video_id) {
                if (!preg_match('/\bwidth\s*=/i', $attrs)) {
                    $attrs .= ' width="560"';
                }
                if (!preg_match('/\bheight\s*=/i', $attrs)) {
                    $attrs .= ' height="315"';
                }
                $thumb = 'https://i.ytimg.com/vi/' . rawurlencode($video_id) . '/hqdefault.jpg';
                $play_label = esc_attr__('Play YouTube video', 'ultracache-pro');
                $srcdoc = '<style>*{padding:0;margin:0;overflow:hidden}html,body{height:100%}body{background:#000 url(' . esc_url($thumb) . ') center/cover no-repeat}a{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;text-decoration:none}a:before{content:"";width:68px;height:48px;border-radius:14px;background:rgba(0,0,0,.75)}a:after{content:"";position:absolute;border-style:solid;border-width:12px 0 12px 19px;border-color:transparent transparent transparent #fff;margin-left:5px}</style><a href="' . esc_url($src) . '" aria-label="' . $play_label . '"></a>';
                if (!preg_match('/\bsrcdoc\s*=/i', $attrs)) {
                    $attrs .= ' srcdoc="' . esc_attr($srcdoc) . '"';
                }
            }
        }

        return '<iframe' . $attrs . '>' . $body . '</iframe>';
    }

    private function extract_youtube_video_id($src) {
        $host = strtolower((string) wp_parse_url($src, PHP_URL_HOST));
        if (false === strpos($host, 'youtube.com') && false === strpos($host, 'youtu.be')) {
            return '';
        }
        $path = trim((string) wp_parse_url($src, PHP_URL_PATH), '/');
        if (false !== strpos($host, 'youtu.be')) {
            return sanitize_text_field(strtok($path, '/'));
        }
        if (preg_match('#(?:embed|shorts)/([A-Za-z0-9_-]{6,})#', $path, $m)) {
            return sanitize_text_field($m[1]);
        }
        parse_str((string) wp_parse_url($src, PHP_URL_QUERY), $query);
        return !empty($query['v']) ? sanitize_text_field($query['v']) : '';
    }
}
