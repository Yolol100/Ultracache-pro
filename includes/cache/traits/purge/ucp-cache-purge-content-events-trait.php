<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Cache_Purge_Content_Events_Trait {
    /**
     * Prevent repeated purges for the same content event in a single request.
     *
     * WordPress, block editor, Elementor and WooCommerce can trigger multiple
     * save/cache hooks for the same post or term. Debouncing keeps automatic
     * cache clearing reliable without doing duplicate filesystem work.
     *
     * @param string $key Event key.
     * @return bool True when this event was already handled.
     */
    protected function already_purged_content_event($key) {
        static $handled = array();

        $key = sanitize_key((string) $key);
        if ('' === $key) {
            return false;
        }

        if (isset($handled[$key])) {
            return true;
        }

        $handled[$key] = true;
        return false;
    }

    /**
     * Prevent repeated full purges triggered by multiple lifecycle hooks in one request.
     *
     * @param string $context Event context.
     * @return bool True when a full purge for this context already ran.
     */
    protected function already_purged_full_event($context = 'global') {
        static $handled = array();

        $context = sanitize_key((string) $context);
        if ('' === $context) {
            $context = 'global';
        }

        if (isset($handled[$context])) {
            return true;
        }

        $handled[$context] = true;
        return false;
    }

    /**
     * Check whether automatic content purging is enabled.
     *
     * @return bool
     */
    protected function should_auto_purge_content_changes() {
        return (bool) apply_filters('ucp_auto_purge_on_content_change', (bool) UCP_Options::get('purge_on_post_update'));
    }

    /**
     * Purge related cache URLs and queue warmup when targeted purge is enabled.
     *
     * @param array $urls URLs to purge.
     * @return void
     */
    protected function purge_and_queue_related_urls($urls) {
        $urls = $this->normalize_local_url_list($urls);
        if (empty($urls)) {
            $this->purge_all();
            return;
        }

        $this->purge_urls($urls);
        foreach ($urls as $url) {
            $this->queue_preload_url($url);
        }
    }

    /**
     * Purge cache related to a post and queue warmup where possible.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param string  $context Diagnostic context.
     * @return void
     */
    protected function purge_related_cache_for_post($post_id, $post, $context = 'content save') {
        $post_id = absint($post_id);
        if (!$post_id || !$post) {
            return;
        }

        if (UCP_Options::get('enable_targeted_purge')) {
            $urls = array_merge($this->related_urls_for_post($post_id, $post), $this->configured_always_purge_urls());
            if (class_exists('UCP_Cache_Tags') && UCP_Cache_Tags::enabled()) {
                $urls = array_merge($urls, UCP_Cache_Tags::urls_for_post($post_id, $post));
            }
            $urls = $this->normalize_local_url_list($urls);
            if (!empty($urls)) {
                $this->purge_and_queue_related_urls($urls);
                UCP_Diagnostics::record('cache', 'Purged cache after ' . sanitize_text_field((string) $context), array(
                    'post_id'   => $post_id,
                    'post_type' => isset($post->post_type) ? (string) $post->post_type : '',
                ));
                return;
            }
        }

        UCP_Diagnostics::record('cache', 'Purged full cache after ' . sanitize_text_field((string) $context), array(
            'post_id'   => $post_id,
            'post_type' => isset($post->post_type) ? (string) $post->post_type : '',
        ));
        $this->purge_all();
    }
    public function purge_on_save($post_id, $post) {
        $post_id = absint($post_id);
        if (!$post_id || $this->already_purged_content_event('post_' . $post_id)) {
            return;
        }

        if (!$post) {
            $post = get_post($post_id);
        }

        if (!$post || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || 'auto-draft' === $post->post_status) {
            return;
        }
        if (!$this->should_auto_purge_content_changes()) {
            return;
        }
        $this->purge_related_cache_for_post($post_id, $post, 'content save');
    }

    public function purge_on_delete($post_id) {
        $post_id = absint($post_id);
        if ($post_id && $this->already_purged_content_event('post_delete_' . $post_id)) {
            return;
        }
        if (!$this->should_auto_purge_content_changes()) {
            return;
        }
        if (UCP_Options::get('enable_targeted_purge')) {
            $urls = array_merge(array(home_url('/')), $this->configured_always_purge_urls());
            $this->purge_urls($urls);
            foreach ($urls as $url) { $this->queue_preload_url($url); }
            return;
        }
        $this->purge_all();
    }

    /**
     * Catch WordPress object-cache invalidation after editor saves.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @return void
     */
    public function purge_on_clean_post_cache($post_id, $post = null) {
        $post_id = absint($post_id);
        if (!$post_id) {
            return;
        }
        if (!$post) {
            $post = get_post($post_id);
        }
        $this->purge_on_save($post_id, $post);
    }

    /**
     * Purge cache after Elementor saves a document.
     *
     * @param object $document Elementor document instance.
     * @param array  $data     Saved data.
     * @return void
     */
    public function purge_on_elementor_document_save($document = null, $data = array()) {
        $post_id = 0;

        if (is_object($document)) {
            if (method_exists($document, 'get_main_id')) {
                $post_id = absint($document->get_main_id());
            } elseif (method_exists($document, 'get_post_id')) {
                $post_id = absint($document->get_post_id());
            } elseif (isset($document->post) && isset($document->post->ID)) {
                $post_id = absint($document->post->ID);
            }
        } elseif (is_numeric($document)) {
            $post_id = absint($document);
        }

        if ($post_id) {
            $post = get_post($post_id);
            $this->purge_on_save($post_id, $post);
            return;
        }

        if ($this->should_auto_purge_content_changes() && !$this->already_purged_content_event('elementor_document_unknown')) {
            UCP_Diagnostics::record('cache', 'Purged full cache after Elementor document save without post id');
            $this->purge_all();
        }
    }

    /**
     * Purge cache after Elementor editor save.
     *
     * @param int|object $post_id_or_document Post ID or Elementor document.
     * @param array      $editor_data         Editor data.
     * @return void
     */
    public function purge_on_elementor_editor_save($post_id_or_document = 0, $editor_data = array()) {
        if (is_numeric($post_id_or_document)) {
            $post_id = absint($post_id_or_document);
            if ($post_id) {
                $this->purge_on_save($post_id, get_post($post_id));
                return;
            }
        }

        $this->purge_on_elementor_document_save($post_id_or_document, $editor_data);
    }
    public function purge_on_term_change($term_id = 0, $tt_id = 0, $taxonomy = '') {
        $term_id = absint($term_id);
        $taxonomy = sanitize_key((string) $taxonomy);

        if ($term_id && $taxonomy && $this->already_purged_content_event('term_' . $taxonomy . '_' . $term_id)) {
            return;
        }

        if (UCP_Options::get('enable_targeted_purge')) {
            $urls = array_merge(array(home_url('/')), $this->configured_always_purge_urls());
            if ($term_id && $taxonomy) {
                $term = get_term($term_id, $taxonomy);
                if ($term && !is_wp_error($term)) {
                    $link = get_term_link($term);
                    if (!is_wp_error($link)) { $urls[] = $link; }
                }
            }
            $urls = apply_filters('ucp_auto_purge_term_urls', $urls, $term_id, $taxonomy);
            $this->purge_and_queue_related_urls($urls);
            UCP_Diagnostics::record('cache', 'Purged cache after taxonomy term change', array(
                'term_id'  => $term_id,
                'taxonomy' => $taxonomy,
            ));
            return;
        }
        UCP_Diagnostics::record('cache', 'Purged full cache after taxonomy term change', array(
            'term_id'  => $term_id,
            'taxonomy' => $taxonomy,
        ));
        $this->purge_all();
    }

    /**
     * Purge cache after WordPress updates a term through edited_terms.
     *
     * @param int    $term_id  Term ID.
     * @param string $taxonomy Taxonomy slug.
     * @param array  $args     Update arguments.
     * @return void
     */
    public function purge_on_edited_terms($term_id = 0, $taxonomy = '', $args = array()) {
        if (is_numeric($taxonomy) && is_string($args)) {
            $this->purge_on_term_change($term_id, absint($taxonomy), $args);
            return;
        }
        $this->purge_on_term_change($term_id, 0, $taxonomy);
    }

    /**
     * Purge related cache when terms are assigned to an object.
     *
     * This catches product category assignments and quick edits that can happen
     * after the main post save callback has already run.
     *
     * @param int    $object_id Object ID.
     * @param array  $terms     Term IDs or slugs.
     * @param array  $tt_ids    Term taxonomy IDs.
     * @param string $taxonomy  Taxonomy slug.
     * @return void
     */
    public function purge_on_object_terms_change($object_id = 0, $terms = array(), $tt_ids = array(), $taxonomy = '') {
        $object_id = absint($object_id);
        $taxonomy = sanitize_key((string) $taxonomy);
        if (!$object_id || !$taxonomy || $this->already_purged_content_event('object_terms_' . $taxonomy . '_' . $object_id)) {
            return;
        }

        if (!$this->should_auto_purge_content_changes()) {
            return;
        }

        $post = get_post($object_id);
        if ($post) {
            $this->purge_related_cache_for_post($object_id, $post, 'term assignment change');
            return;
        }

        $this->purge_on_term_change(0, 0, $taxonomy);
    }

    public function purge_on_woocommerce_product($product_id) {
        $product_id = absint($product_id);
        if (!$product_id || !UCP_Options::get('enable_woocommerce_rules')) {
            return;
        }

        $post = get_post($product_id);
        if ($post && 'product_variation' === $post->post_type && !empty($post->post_parent)) {
            $product_id = absint($post->post_parent);
            $post = get_post($product_id);
        }

        if ($post) {
            $this->purge_related_cache_for_post($product_id, $post, 'WooCommerce product change');
            $this->purge_woocommerce_listing_urls($product_id);
            return;
        }
        if (!$this->already_purged_full_event('woocommerce_product_unknown')) {
            $this->purge_all();
        }
    }

    public function purge_on_woocommerce_stock_change($product) {
        if (!UCP_Options::get('enable_woocommerce_rules')) {
            return;
        }
        if (is_object($product) && method_exists($product, 'get_parent_id') && $product->get_parent_id()) {
            $this->purge_on_woocommerce_product($product->get_parent_id());
            return;
        }
        if (is_object($product) && method_exists($product, 'get_id')) {
            $this->purge_on_woocommerce_product($product->get_id());
            return;
        }
        if (!$this->already_purged_full_event('woocommerce_stock_unknown')) {
            $this->purge_all();
        }
    }

    /**
     * Purge product and listing pages affected by WooCommerce orders.
     *
     * @param int|WC_Order $order Order ID or order object.
     * @return void
     */
    public function purge_on_woocommerce_order_change($order = 0) {
        if (!UCP_Options::get('enable_woocommerce_rules') || !$this->should_auto_purge_content_changes()) {
            return;
        }

        $order_id = is_object($order) && method_exists($order, 'get_id') ? absint($order->get_id()) : absint($order);
        if ($order_id && $this->already_purged_content_event('woocommerce_order_' . $order_id)) {
            return;
        }

        $product_ids = array();
        if (function_exists('wc_get_order') && $order_id) {
            $wc_order = wc_get_order($order_id);
            if ($wc_order && method_exists($wc_order, 'get_items')) {
                foreach ($wc_order->get_items() as $item) {
                    if (is_object($item) && method_exists($item, 'get_product_id')) {
                        $product_ids[] = absint($item->get_product_id());
                    }
                    if (is_object($item) && method_exists($item, 'get_variation_id') && $item->get_variation_id()) {
                        $variation = get_post(absint($item->get_variation_id()));
                        if ($variation && !empty($variation->post_parent)) {
                            $product_ids[] = absint($variation->post_parent);
                        }
                    }
                }
            }
        }

        $product_ids = array_values(array_unique(array_filter($product_ids)));
        if (!empty($product_ids) && UCP_Options::get('enable_targeted_purge')) {
            $urls = array();
            foreach ($product_ids as $product_id) {
                $post = get_post($product_id);
                if ($post) {
                    $urls = array_merge($urls, $this->related_urls_for_post($product_id, $post));
                }
            }
            $urls = array_merge($urls, $this->woocommerce_listing_urls(), $this->configured_always_purge_urls());
            $this->purge_and_queue_related_urls($urls);
            UCP_Diagnostics::record('cache', 'Purged WooCommerce cache after order change', array('order_id' => $order_id));
            return;
        }

        $this->purge_and_queue_related_urls(array_merge(array(home_url('/')), $this->woocommerce_listing_urls(), $this->configured_always_purge_urls()));
    }

    /**
     * Purge WooCommerce shop/listing URLs after product changes.
     *
     * @param int $product_id Product ID.
     * @return void
     */
    protected function purge_woocommerce_listing_urls($product_id = 0) {
        if (!UCP_Options::get('enable_targeted_purge')) {
            return;
        }
        $urls = $this->woocommerce_listing_urls();
        $product_id = absint($product_id);
        if ($product_id) {
            $post = get_post($product_id);
            if ($post) {
                $urls = array_merge($urls, $this->related_urls_for_post($product_id, $post));
            }
        }
        $urls = array_merge($urls, $this->configured_always_purge_urls());
        $this->purge_and_queue_related_urls($urls);
    }

    /**
     * Return WooCommerce listing pages that commonly reflect product/order changes.
     *
     * @return array
     */
    protected function woocommerce_listing_urls() {
        $urls = array(home_url('/'));
        if (function_exists('wc_get_page_permalink')) {
            $shop_url = wc_get_page_permalink('shop');
            if ($shop_url) {
                $urls[] = $shop_url;
            }
        }
        return $this->normalize_local_url_list(apply_filters('ucp_auto_purge_woocommerce_listing_urls', $urls));
    }
}
