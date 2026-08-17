<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Cache_Purge_Lifecycle_Trait {
    public function purge_on_global_change() {
        $hook = function_exists('current_filter') ? sanitize_key((string) current_filter()) : 'global_change';
        $comment_hooks = array('comment_post', 'edit_comment', 'wp_set_comment_status');
        if (in_array($hook, $comment_hooks, true)) {
            $enabled = UCP_Options::get('purge_on_comment');
        } elseif ('switch_theme' === $hook) {
            $enabled = UCP_Options::get('purge_on_theme_switch');
        } else {
            $enabled = UCP_Options::get('purge_on_global_change');
        }
        if (!$enabled) {
            return;
        }
        if ($this->already_purged_full_event('global_' . $hook)) {
            return;
        }
        UCP_Diagnostics::record('cache', 'Purged full cache after global site change', array('hook' => $hook));
        $this->purge_all();
    }

    public function purge_on_extension_change($item = '', $success = true) {
        $hook = function_exists('current_filter') ? (string) current_filter() : '';
        if ('deleted_plugin' === $hook && false === $success) {
            return;
        }
        if (!$this->should_purge_on_lifecycle_change()) {
            return;
        }
        $this->purge_and_preload_after_lifecycle_change(
            'extension_change',
            array(
                'hook' => $hook,
                'item' => is_scalar($item) ? (string) $item : '',
            )
        );
    }

    protected function should_purge_on_lifecycle_change() {
        $enabled = (bool) UCP_Options::get('purge_on_extension_change');
        return (bool) apply_filters('ucp_always_purge_on_lifecycle_change', $enabled);
    }

    public function purge_and_preload_after_lifecycle_change($context = 'lifecycle_change', $extra = array()) {
        $extra = is_array($extra) ? $extra : array();
        $context = sanitize_key((string) $context);
        if ('' === $context) {
            $context = 'lifecycle_change';
        }

        UCP_Diagnostics::record('cache', 'Purged full cache after WordPress lifecycle change', array_merge($extra, array(
            'context' => $context,
        )));
        $this->purge_all();
        $this->schedule_preload_after_lifecycle_change($context, $extra);
    }

    protected function schedule_preload_after_lifecycle_change($context = 'lifecycle_change', $extra = array()) {
        if (!class_exists('UCP_Preload') || !UCP_Options::get('enable_cache') || !UCP_Options::get('enable_preload')) {
            return;
        }

        $context = sanitize_key((string) $context);
        $extra = is_array($extra) ? $extra : array();

        if (UCP_Options::get('enable_preload_queue') && class_exists('UCP_Jobs')) {
            $delay = absint(apply_filters('ucp_lifecycle_preload_seed_delay', 10, $context, $extra));
            $delay = max(5, $delay);
            $event_args = array($context, $extra);
            $scheduled = (bool) wp_next_scheduled('ucp_lifecycle_preload_seed_event', $event_args);
            if (!$scheduled) {
                $scheduled = false !== wp_schedule_single_event(time() + $delay, 'ucp_lifecycle_preload_seed_event', $event_args);
            }
            UCP_Diagnostics::record('cache', $scheduled
                ? 'Scheduled cache warmup queue after WordPress lifecycle change'
                : 'Failed to schedule cache warmup queue after WordPress lifecycle change', array_merge($extra, array(
                'context' => $context,
                'delay'   => $delay,
            )));
            return;
        }

        $delay = absint(apply_filters('ucp_lifecycle_preload_delay', 30, $context, $extra));
        $delay = max(5, $delay);
        $scheduled = (bool) wp_next_scheduled('ucp_preload_event');
        if (!$scheduled) {
            $scheduled = false !== wp_schedule_single_event(time() + $delay, 'ucp_preload_event');
        }
        UCP_Diagnostics::record('cache', $scheduled
            ? 'Scheduled cache warmup after WordPress lifecycle change'
            : 'Failed to schedule cache warmup after WordPress lifecycle change', array_merge($extra, array(
            'context' => $context,
            'delay'   => $delay,
        )));
    }

    public function run_lifecycle_preload_seed($context = 'lifecycle_change', $extra = array()) {
        if (!class_exists('UCP_Preload') || !class_exists('UCP_Jobs') || !UCP_Options::get('enable_cache') || !UCP_Options::get('enable_preload') || !UCP_Options::get('enable_preload_queue')) {
            return;
        }

        $context = sanitize_key((string) $context);
        $extra = is_array($extra) ? $extra : array();
        $preload = UCP_Helpers::new_without_constructor('UCP_Preload');
        $queued = $preload->seed_preload_queue();
        UCP_Diagnostics::record('cache', 'Queued cache warmup in background after WordPress lifecycle change', array_merge($extra, array(
            'context' => $context,
            'queued'  => absint($queued),
        )));
    }

    public function purge_on_upgrader_process_complete($upgrader, $hook_extra) {
        $hook_extra = is_array($hook_extra) ? $hook_extra : array();
        $type = isset($hook_extra['type']) ? (string) $hook_extra['type'] : '';
        $action = isset($hook_extra['action']) ? (string) $hook_extra['action'] : '';

        if ('core' === $type) {
            $this->purge_on_core_updated();
            return;
        }

        if (!$this->should_purge_on_lifecycle_change()) {
            return;
        }

        $purge_types = apply_filters('ucp_upgrader_purge_types', array('plugin', 'theme', 'translation'));
        $purge_actions = apply_filters('ucp_upgrader_purge_actions', array('install', 'update', 'delete'));
        if (in_array($type, (array) $purge_types, true) && ('' === $action || in_array($action, (array) $purge_actions, true))) {
            $this->purge_and_preload_after_lifecycle_change('upgrader_process', array(
                'type'   => $type,
                'action' => $action,
            ));
        }
    }

    public function purge_on_core_updated() {
        if (!UCP_Options::get('purge_on_core_update')) {
            return;
        }
        UCP_Diagnostics::record('cache', 'Purged full cache after WordPress core update');
        $this->purge_all();
    }
}
