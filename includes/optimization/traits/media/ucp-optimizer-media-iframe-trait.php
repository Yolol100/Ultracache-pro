<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Optimizer_Media_Iframe_Trait {
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
                $play_label = esc_attr__('YouTube-video afspelen', 'ultracache-pro');
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
