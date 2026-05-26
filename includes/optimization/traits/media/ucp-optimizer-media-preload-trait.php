<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Optimizer_Media_Preload_Trait {
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

        if ('' !== (string) $this->ucp_lcp_candidate_src) {
            $this->ucp_preload_image_urls[] = $this->ucp_lcp_candidate_src;
            $this->ucp_background_preloaded = true;
            if (class_exists('UCP_Diagnostics')) {
                UCP_Diagnostics::record('lcp', 'Preloaded selected measured LCP candidate.', array(
                    'url' => $this->ucp_lcp_candidate_src,
                    'background' => $this->ucp_lcp_candidate_is_background ? 1 : 0,
                ));
            }
        }
        return $html;
    }

    private function detect_lcp_image_candidate($html) {
        if (!is_string($html) || '' !== (string) $this->ucp_lcp_candidate_src) {
            return;
        }
        if ($this->select_measured_lcp_candidate()) {
            return;
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

    private function select_measured_lcp_candidate() {
        if ('' !== (string) $this->ucp_lcp_candidate_src) {
            return true;
        }
        if (class_exists('UCP_CWV')) {
            $current_url = $this->current_lcp_lookup_url();
            $device = $this->current_lcp_lookup_device();
            $row = UCP_CWV::lcp_hint_for_url($current_url, $device);
            if (!empty($row['lcp_url'])) {
                $url = $this->normalize_lcp_image_candidate_url($row['lcp_url']);
                if ($url) {
                    $this->ucp_lcp_candidate_src = esc_url_raw($url);
                    $element = !empty($row['lcp_element_json']) ? json_decode((string) $row['lcp_element_json'], true) : array();
                    $this->ucp_lcp_candidate_is_background = is_array($element) && !empty($element['background']);
                    if (class_exists('UCP_Diagnostics')) {
                        UCP_Diagnostics::record('lcp', 'Selected measured per-URL LCP candidate.', array('url' => $this->ucp_lcp_candidate_src, 'device' => $device));
                    }
                    return true;
                }
            }
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
                    return true;
                }
            }
        }
        return false;
    }

    private function current_lcp_lookup_url() {
        $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : wp_parse_url(home_url('/'), PHP_URL_HOST);
        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '/';
        $scheme = is_ssl() ? 'https' : 'http';
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $forwarded_proto = strtolower(sanitize_key(wp_unslash($_SERVER['HTTP_X_FORWARDED_PROTO'])));
            if (in_array($forwarded_proto, array('http', 'https'), true)) {
                $scheme = $forwarded_proto;
            }
        }
        $parts = wp_parse_url($scheme . '://' . $host . $request_uri);
        if (empty($parts['host'])) {
            return home_url('/');
        }
        $path = isset($parts['path']) && '' !== $parts['path'] ? (string) $parts['path'] : '/';
        return esc_url_raw($scheme . '://' . $parts['host'] . $path);
    }

    private function current_lcp_lookup_device() {
        if (function_exists('wp_is_mobile') && wp_is_mobile()) {
            return 'mobile';
        }
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))) : '';
        if (false !== strpos($ua, 'mobile') || false !== strpos($ua, 'android') || false !== strpos($ua, 'iphone')) {
            return 'mobile';
        }
        return 'desktop';
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
}
