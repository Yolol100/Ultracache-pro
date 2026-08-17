<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Quality_Suite_Dashboard_Trait {
    public static function schedule_post_update_check($upgrader = null, $hook_extra = array()) {
        $hook_extra = is_array($hook_extra) ? $hook_extra : array();
        $action = isset($hook_extra['action']) ? sanitize_key((string) $hook_extra['action']) : '';
        $type = isset($hook_extra['type']) ? sanitize_key((string) $hook_extra['type']) : '';
        if ('update' !== $action || !in_array($type, array('plugin', 'theme', 'core'), true)) {
            return;
        }

        $items = array();
        foreach (array('plugins', 'themes') as $key) {
            if (!empty($hook_extra[$key]) && is_array($hook_extra[$key])) {
                $items = array_merge($items, array_map('sanitize_text_field', $hook_extra[$key]));
            }
        }
        if (!empty($hook_extra['plugin']) && is_scalar($hook_extra['plugin'])) {
            $items[] = sanitize_text_field((string) $hook_extra['plugin']);
        }
        if (!empty($hook_extra['theme']) && is_scalar($hook_extra['theme'])) {
            $items[] = sanitize_text_field((string) $hook_extra['theme']);
        }

        update_option(self::POST_UPDATE_CONTEXT_OPTION, array(
            'type' => $type,
            'items' => array_values(array_unique(array_filter($items))),
            'scheduled_at' => gmdate('c'),
        ), false);

        if (!wp_next_scheduled(self::POST_UPDATE_CHECK_HOOK)) {
            wp_schedule_single_event(time() + 90, self::POST_UPDATE_CHECK_HOOK);
        }
    }

    public static function run_scheduled_website_check() {
        $context = get_option(self::POST_UPDATE_CONTEXT_OPTION, array());
        $report = self::run_website_check('post_update');
        if (is_array($report)) {
            $report['update_context'] = is_array($context) ? $context : array();
            update_option(self::WEBSITE_CHECK_OPTION, $report, false);
        }
        delete_option(self::POST_UPDATE_CONTEXT_OPTION);
        return $report;
    }

    public static function rest_website_check() {
        $report = self::run_website_check('manual');
        return self::action_success(
            !empty($report['state']) && 'good' === $report['state']
                ? __('Websitecontrole geslaagd.', 'ultracache-pro')
                : __('Websitecontrole afgerond. Bekijk de aandachtspunten.', 'ultracache-pro'),
            array('websiteCheck' => $report)
        );
    }

    public static function run_website_check($context = 'manual') {
        do_action('ucp_operation_heartbeat');
        $health = class_exists('UCP_Health') ? UCP_Health::run_checks() : array();
        do_action('ucp_operation_heartbeat');
        $runtime = self::run_runtime_cache_test();
        do_action('ucp_operation_heartbeat');
        $conflicts = self::detect_conflicts();
        $release = self::release_checklist();
        $operational = self::operational_status($runtime, true);
        $plan = self::conflict_resolution_plan($conflicts);
        do_action('ucp_operation_heartbeat');
        $checks = self::website_check_items($operational, $health, $plan);
        $failed = 0;
        $warnings = 0;
        foreach ($checks as $check) {
            if ('failed' === $check['state']) {
                $failed++;
            } elseif ('warning' === $check['state']) {
                $warnings++;
            }
        }

        $report = array(
            'generatedAt' => gmdate('c'),
            'context' => sanitize_key((string) $context),
            'state' => $failed > 0 ? 'failed' : ($warnings > 0 ? 'warning' : 'good'),
            'passed' => count($checks) - $failed - $warnings,
            'warnings' => $warnings,
            'failed' => $failed,
            'total' => count($checks),
            'checks' => $checks,
            'operational' => $operational,
            'conflictPlan' => $plan,
            'healthSnapshot' => is_array($health) ? $health : array(),
            'releaseChecklist' => $release,
        );
        update_option(self::WEBSITE_CHECK_OPTION, $report, false);
        UCP_Logger::log('notice', 'health', 'website_check_completed', __('Gecombineerde websitecontrole uitgevoerd.', 'ultracache-pro'), array(
            'context' => sanitize_key((string) $context),
            'state' => $report['state'],
            'failed' => $failed,
            'warnings' => $warnings,
        ));
        return $report;
    }

    protected static function website_check_items($operational, $health, $plan) {
        $page = isset($operational['pageCache']) ? $operational['pageCache'] : array();
        $compression = isset($operational['compression']) ? $operational['compression'] : array();
        $dropin = isset($operational['dropin']) ? $operational['dropin'] : array();
        $object = isset($operational['objectCache']) ? $operational['objectCache'] : array();
        $preload = isset($operational['preload']) ? $operational['preload'] : array();
        $jobs = isset($operational['jobs']) ? $operational['jobs'] : array();
        $health = is_array($health) ? $health : array();

        $page_state = empty($page['configured']) ? 'warning' : (!empty($page['working']) ? 'good' : 'failed');
        $dropin_finalizing = !empty($dropin['finalizing']);
        $dropin_state = empty($page['configured']) || !empty($dropin['ready']) ? 'good' : ($dropin_finalizing ? 'warning' : 'failed');
        $compression_state = 'good';
        $object_state = empty($object['configured']) ? 'good' : (!empty($object['reachable']) ? 'good' : 'warning');
        $preload_state = empty($preload['enabled']) || !empty($preload['lastCompleted']) ? 'good' : 'warning';
        $jobs_state = empty($jobs['failed']) && empty($jobs['staleRunning']) ? 'good' : 'failed';
        $conflict_state = empty($plan['items']) ? 'good' : 'warning';
        $storage_state = !empty($health['cache_dir_writable']) ? 'good' : 'failed';

        return array(
            self::website_check_item('cache-storage', __('Tijdelijke bestanden', 'ultracache-pro'), $storage_state, !empty($health['cache_dir_writable']) ? __('In orde', 'ultracache-pro') : __('Controle nodig', 'ultracache-pro'), !empty($health['cache_dir_writable']) ? __('UltraCache kan tijdelijke snelheidsbestanden opslaan.', 'ultracache-pro') : __('De map voor tijdelijke snelheidsbestanden is niet schrijfbaar. Laat de hostingprovider de maprechten controleren.', 'ultracache-pro'), ''),
            self::website_check_item('page-cache', __('Pagina’s versnellen', 'ultracache-pro'), $page_state, empty($page['configured']) ? __('Uit', 'ultracache-pro') : (!empty($page['working']) ? __('Werkt', 'ultracache-pro') : __('Controle nodig', 'ultracache-pro')), empty($page['configured']) ? __('De versnelling van openbare pagina’s staat uit.', 'ultracache-pro') : (!empty($page['working']) ? __('Een versnelde pagina is succesvol gemeten.', 'ultracache-pro') : __('De website reageert, maar de versnelling is nog niet bevestigd. Voer de controle nogmaals uit of herstel de cachekoppeling.', 'ultracache-pro')), !empty($page['configured']) ? 'repair-cache-files' : ''),
            self::website_check_item('dropin', __('Verbinding met WordPress', 'ultracache-pro'), $dropin_state, !empty($dropin['ready']) ? __('In orde', 'ultracache-pro') : ($dropin_finalizing ? __('Wordt afgerond', 'ultracache-pro') : __('Herstel nodig', 'ultracache-pro')), !empty($dropin['ready']) ? __('WordPress en UltraCache zijn correct gekoppeld.', 'ultracache-pro') : ($dropin_finalizing ? __('UltraCache controleert de koppeling automatisch.', 'ultracache-pro') : __('De koppeling is niet compleet en kan veilig worden hersteld.', 'ultracache-pro')), $dropin_finalizing ? '' : 'repair-cache-files'),
            self::website_check_item('compression', __('Snellere overdracht', 'ultracache-pro'), $compression_state, !empty($compression['actual']) ? __('Werkt', 'ultracache-pro') : __('Niet gemeten', 'ultracache-pro'), !empty($compression['actual']) ? __('De server verstuurt de website gecomprimeerd.', 'ultracache-pro') : __('De server gaf geen meetbaar compressiesignaal terug. Dit kan ook door hosting of een proxy worden geregeld.', 'ultracache-pro'), ''),
            self::website_check_item('object-cache', __('Extra versnelling', 'ultracache-pro'), $object_state, !empty($object['reachable']) ? __('Werkt', 'ultracache-pro') : (empty($object['configured']) ? __('Niet gebruikt', 'ultracache-pro') : __('Controle nodig', 'ultracache-pro')), empty($object['configured']) ? __('Deze extra versnelling is optioneel en staat niet aan.', 'ultracache-pro') : (!empty($object['reachable']) ? __('De extra databaseversnelling reageert.', 'ultracache-pro') : __('De ingestelde databaseversnelling reageert niet. Laat Redis, APCu of de bestaande koppeling controleren.', 'ultracache-pro')), 'refresh-object-cache-status'),
            self::website_check_item('preload', __('Pagina’s voorbereiden', 'ultracache-pro'), $preload_state, !empty($preload['lastCompleted']) ? __('Voltooid', 'ultracache-pro') : (empty($preload['enabled']) ? __('Uit', 'ultracache-pro') : __('Nog niet voltooid', 'ultracache-pro')), empty($preload['enabled']) ? __('Automatisch voorbereiden staat uit.', 'ultracache-pro') : (!empty($preload['lastCompleted']) ? __('Een openbare pagina is recent succesvol voorbereid.', 'ultracache-pro') : __('Start het voorbereiden zodat belangrijke pagina’s direct snel beschikbaar zijn.', 'ultracache-pro')), !empty($preload['enabled']) ? 'preload' : ''),
            self::website_check_item('jobs', __('Automatische verwerking', 'ultracache-pro'), $jobs_state, 'good' === $jobs_state ? __('In orde', 'ultracache-pro') : sprintf(
                /* translators: %d: number of failed jobs. */
                _n('%d probleem', '%d problemen', absint(isset($jobs['failed']) ? $jobs['failed'] : 0), 'ultracache-pro'),
                absint(isset($jobs['failed']) ? $jobs['failed'] : 0)
            ), 'good' === $jobs_state ? __('Alle automatische taken zijn normaal verwerkt.', 'ultracache-pro') : __('Een of meer automatische taken zijn niet afgerond. Probeer ze opnieuw en controleer daarna de website.', 'ultracache-pro'), 'retry-failed-jobs'),
            self::website_check_item('conflicts', __('Dubbele versnelling', 'ultracache-pro'), $conflict_state, empty($plan['items']) ? __('Geen overlap', 'ultracache-pro') : sprintf(
                /* translators: %d: number of detected conflicts. */
                _n('%d overlap', '%d overlappingen', count($plan['items']), 'ultracache-pro'),
                count($plan['items'])
            ), empty($plan['items']) ? __('Geen bekende dubbele cache- of snelheidsfuncties gevonden.', 'ultracache-pro') : __('Dezelfde functie wordt door meerdere plugins geregeld. Kies per functie één plugin; UltraCache kan alleen eigen dubbele functies uitschakelen.', 'ultracache-pro'), !empty($plan['canApply']) ? 'apply-conflict-resolution' : ''),
        );
    }

    protected static function website_check_item($key, $label, $state, $value, $detail, $action) {
        return array(
            'key' => sanitize_key((string) $key),
            'label' => $label,
            'state' => in_array($state, array('good', 'warning', 'failed'), true) ? $state : 'warning',
            'value' => sanitize_text_field((string) $value),
            'detail' => sanitize_text_field((string) $detail),
            'action' => sanitize_key((string) $action),
        );
    }

    public static function operational_status($runtime = null, $force_object_cache = false) {
        $settings = UCP_Options::get_all();
        $runtime = is_array($runtime) ? $runtime : get_option(self::RUNTIME_OPTION, array());
        $headers = isset($runtime['home']['second']['headers']) && is_array($runtime['home']['second']['headers']) ? $runtime['home']['second']['headers'] : array();
        $encoding = isset($headers['content-encoding']) ? strtolower(trim((string) $headers['content-encoding'])) : '';
        $cache_signal = self::cache_signal_from_runtime($runtime);
        $object_status = class_exists('UCP_Object_Cache') ? UCP_Object_Cache::status((bool) $force_object_cache) : array();
        $queue = class_exists('UCP_Jobs') ? UCP_Jobs::get_summary() : array();
        $preload_summary = class_exists('UCP_Preload') && method_exists('UCP_Preload', 'preload_status_summary') ? UCP_Preload::preload_status_summary(50) : array();
        $last_preload = '';
        foreach ((array) (isset($preload_summary['recent']) ? $preload_summary['recent'] : array()) as $row) {
            if (is_array($row) && 'cached' === (isset($row['status']) ? $row['status'] : '') && !empty($row['updated_at'])) {
                $last_preload = sanitize_text_field((string) $row['updated_at']);
                break;
            }
        }

        $dropin_owner = class_exists('UCP_Helpers') && file_exists(WP_CONTENT_DIR . '/advanced-cache.php')
            ? UCP_Helpers::detect_advanced_cache_owner(UCP_Helpers::read_file_head(WP_CONTENT_DIR . '/advanced-cache.php', 64 * KB_IN_BYTES))
            : '';
        $dropin_ready = UCP_Helpers::has_valid_wp_cache_constant()
            && file_exists(WP_CONTENT_DIR . '/advanced-cache.php')
            && class_exists('UCP_Helpers')
            && file_exists(UCP_Helpers::dropin_config_path())
            && ('UltraCache Pro' === $dropin_owner || 'ultracache-pro' === sanitize_key($dropin_owner) || UCP_Helpers::is_own_advanced_cache(UCP_Helpers::read_file_head(WP_CONTENT_DIR . '/advanced-cache.php', 64 * KB_IN_BYTES)));
        $dropin_setup = get_option('ucp_advanced_cache_auto_status', array());
        $dropin_setup_state = is_array($dropin_setup) ? sanitize_key((string) (isset($dropin_setup['status']) ? $dropin_setup['status'] : '')) : '';
        $dropin_finalizing = in_array($dropin_setup_state, array('finalizing', 'verification_pending'), true);

        return array(
            'generatedAt' => gmdate('c'),
            'pageCache' => array(
                'configured' => !empty($settings['enable_cache']),
                'working' => 'HIT' === $cache_signal || 'MISS' === $cache_signal,
                'signal' => $cache_signal,
                'testedAt' => isset($runtime['generated_at']) ? sanitize_text_field((string) $runtime['generated_at']) : '',
            ),
            'compression' => array(
                'configured' => !empty($settings['enable_brotli_precompression']) || !empty($settings['enable_gzip_precompression']),
                'actual' => in_array($encoding, array('br', 'gzip', 'deflate'), true),
                'encoding' => $encoding,
                'brotliSupported' => function_exists('brotli_compress'),
                'gzipSupported' => function_exists('gzencode'),
            ),
            'dropin' => array(
                'ready' => (bool) $dropin_ready,
                'wpCache' => UCP_Helpers::has_valid_wp_cache_constant(),
                'advancedCache' => file_exists(WP_CONTENT_DIR . '/advanced-cache.php'),
                'config' => class_exists('UCP_Helpers') && file_exists(UCP_Helpers::dropin_config_path()),
                'owner' => sanitize_text_field((string) $dropin_owner),
                'setupState' => $dropin_setup_state,
                'finalizing' => $dropin_finalizing,
            ),
            'objectCache' => array(
                'configured' => !empty($settings['enable_redis_object_cache']) || !empty($settings['enable_apcu_object_cache']) || !empty($object_status['dropin']) || !empty($object_status['enabled']),
                'reachable' => !empty($object_status['enabled']) || !empty($object_status['redis_connected']) || !empty($object_status['apcu_available']),
                'backend' => !empty($object_status['dropin_owner']) ? sanitize_key((string) $object_status['dropin_owner']) : sanitize_key((string) (isset($object_status['recommended_backend']) ? $object_status['recommended_backend'] : '')),
                'detail' => $object_status,
            ),
            'preload' => array(
                'enabled' => !empty($settings['enable_preload']),
                'lastCompleted' => $last_preload,
                'summary' => $preload_summary,
            ),
            'purge' => array(
                'lastAt' => sanitize_text_field((string) get_option('ucp_last_purge_at', '')),
            ),
            'jobs' => array(
                'failed' => absint(isset($queue['failed']) ? $queue['failed'] : 0),
                'pending' => absint(isset($queue['pending']) ? $queue['pending'] : 0),
                'running' => absint(isset($queue['running']) ? $queue['running'] : 0),
                'retrying' => absint(isset($queue['retrying']) ? $queue['retrying'] : 0),
                'staleRunning' => absint(isset($queue['staleRunning']) ? $queue['staleRunning'] : 0),
            ),
            'capabilities' => self::server_capabilities($object_status),
        );
    }

    protected static function cache_signal_from_runtime($runtime) {
        $headers = isset($runtime['home']['second']['headers']) && is_array($runtime['home']['second']['headers']) ? $runtime['home']['second']['headers'] : array();
        $joined = strtolower(UCP_Helpers::safe_json_encode_or($headers, '{}'));
        if (false !== strpos($joined, 'bypass')) {
            return 'BYPASS';
        }
        if (false !== strpos($joined, 'hit') || 'hit_or_cached_signal' === (isset($runtime['home']['result']) ? $runtime['home']['result'] : '')) {
            return 'HIT';
        }
        if (false !== strpos($joined, 'miss')) {
            return 'MISS';
        }
        if (!empty($runtime['home']['first']['ok']) && !empty($runtime['home']['second']['ok'])) {
            return 'BYPASS';
        }
        return __('Niet getest', 'ultracache-pro');
    }

    public static function server_capabilities($object_status = null) {
        $object_status = is_array($object_status) ? $object_status : (class_exists('UCP_Object_Cache') ? UCP_Object_Cache::status(false) : array());
        $server = isset($_SERVER['SERVER_SOFTWARE']) ? strtolower(sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE']))) : '';
        $htaccess = trailingslashit(ABSPATH) . '.htaccess';
        $supports_htaccess = false !== strpos($server, 'apache') || false !== strpos($server, 'litespeed');
        $htaccess_writable = wp_is_writable(ABSPATH) && (!file_exists($htaccess) || wp_is_writable($htaccess));
        $image_support = class_exists('UCP_Image_Optimizer') ? UCP_Image_Optimizer::server_support() : array();

        return array(
            'brotliPrecompression' => function_exists('brotli_compress'),
            'gzipPrecompression' => function_exists('gzencode'),
            'zipArchive' => class_exists('ZipArchive'),
            'redis' => !empty($object_status['redis']),
            'redisReachable' => !empty($object_status['redis_connected']),
            'apcu' => !empty($object_status['apcu_available']),
            'webpGeneration' => !empty($image_support['webp']),
            'avifGeneration' => !empty($image_support['avif']),
            'wpContentWritable' => wp_is_writable(WP_CONTENT_DIR),
            'cacheDirectoryWritable' => wp_is_writable(UCP_CACHE_DIR),
            'wpConfigWritable' => class_exists('UCP_Helpers') && UCP_Helpers::can_manage_wp_config(),
            'dropinWritable' => wp_is_writable(WP_CONTENT_DIR),
            'browserCacheRuleWrites' => $supports_htaccess && $htaccess_writable,
        );
    }

    public static function conflict_resolution_plan($conflicts = null) {
        $conflicts = is_array($conflicts) ? $conflicts : self::detect_conflicts();
        $settings = UCP_Options::get_all();
        $items = array();
        $changes = array();
        foreach ($conflicts as $conflict) {
            if (!is_array($conflict)) {
                continue;
            }
            $slug = sanitize_text_field((string) (isset($conflict['slug']) ? $conflict['slug'] : ''));
            $features = isset($conflict['features']) && is_array($conflict['features']) ? array_values(array_unique(array_map('sanitize_key', $conflict['features']))) : array();
            $item_changes = array();
            foreach ($features as $feature) {
                foreach (self::safe_conflict_changes($feature) as $setting_key => $definition) {
                    if (!array_key_exists($setting_key, $settings) || (string) $settings[$setting_key] === (string) $definition['value']) {
                        continue;
                    }
                    $change = array(
                        'key' => $setting_key,
                        'label' => $definition['label'],
                        'current' => is_scalar($settings[$setting_key]) ? $settings[$setting_key] : '',
                        'next' => $definition['value'],
                        'feature' => $feature,
                    );
                    $item_changes[$setting_key] = $change;
                    $changes[$setting_key] = $change;
                }
            }
            $items[] = array(
                'id' => substr(hash('sha256', (string) (isset($conflict['type']) ? $conflict['type'] : '') . ':' . $slug), 0, 16),
                'type' => sanitize_key((string) (isset($conflict['type']) ? $conflict['type'] : 'plugin')),
                'slug' => $slug,
                'label' => sanitize_text_field((string) (isset($conflict['label']) ? $conflict['label'] : $slug)),
                'owner' => sanitize_text_field((string) (isset($conflict['owner']) ? $conflict['owner'] : (isset($conflict['label']) ? $conflict['label'] : $slug))),
                'features' => $features,
                'featureLabels' => array_map(array(__CLASS__, 'conflict_feature_label'), $features),
                'recommendation' => sanitize_text_field((string) (isset($conflict['recommendation']) ? $conflict['recommendation'] : '')),
                'changes' => array_values($item_changes),
                'automatic' => !empty($item_changes),
            );
        }

        return array(
            'generatedAt' => gmdate('c'),
            'items' => array_values($items),
            'recommendedChanges' => array_values($changes),
            'canApply' => !empty($changes),
        );
    }

    protected static function safe_conflict_changes($feature) {
        $map = array(
            'page_cache' => array(
                'enable_cache' => array('value' => 0, 'label' => __('UltraCache paginacache', 'ultracache-pro')),
                'enable_preload' => array('value' => 0, 'label' => __('UltraCache vooraf opbouwen', 'ultracache-pro')),
                'enable_preload_queue' => array('value' => 0, 'label' => __('UltraCache preloadwachtrij', 'ultracache-pro')),
            ),
            'critical_css' => array(
                'enable_critical_css' => array('value' => 0, 'label' => __('Critical CSS', 'ultracache-pro')),
                'enable_used_css' => array('value' => 0, 'label' => __('Used CSS', 'ultracache-pro')),
                'enable_used_css_delivery' => array('value' => 0, 'label' => __('Used CSS levering', 'ultracache-pro')),
            ),
            'delay_js' => array(
                'enable_delay_js' => array('value' => 0, 'label' => __('JavaScript vertragen', 'ultracache-pro')),
            ),
            'lazyload' => array(
                'enable_lazy_images' => array('value' => 0, 'label' => __('Afbeeldingen lazyloaden', 'ultracache-pro')),
                'enable_lazy_iframes' => array('value' => 0, 'label' => __('Iframes lazyloaden', 'ultracache-pro')),
                'enable_lazy_youtube_preview' => array('value' => 0, 'label' => __('YouTube-voorbeelden uitstellen', 'ultracache-pro')),
            ),
            'font_optimization' => array(
                'enable_local_google_fonts' => array('value' => 0, 'label' => __('Google Fonts lokaal opslaan', 'ultracache-pro')),
            ),
            'image_optimization' => array(
                'enable_image_optimization' => array('value' => 0, 'label' => __('Afbeeldingsoptimalisatie', 'ultracache-pro')),
            ),
            'object_cache_overlap' => array(
                'enable_object_cache_support' => array('value' => 0, 'label' => __('UltraCache object-cacheondersteuning', 'ultracache-pro')),
                'enable_redis_object_cache' => array('value' => 0, 'label' => __('UltraCache Redis-objectcache', 'ultracache-pro')),
                'enable_apcu_object_cache' => array('value' => 0, 'label' => __('UltraCache APCu-objectcache', 'ultracache-pro')),
            ),
        );
        return isset($map[$feature]) ? $map[$feature] : array();
    }

    public static function conflict_feature_label($feature) {
        if (!is_scalar($feature) && null !== $feature) {
            $feature = '';
        }
        $labels = array(
            'page_cache' => __('Paginacache', 'ultracache-pro'),
            'critical_css' => __('CSS-levering', 'ultracache-pro'),
            'delay_js' => __('JavaScript vertragen', 'ultracache-pro'),
            'lazyload' => __('Lazyload', 'ultracache-pro'),
            'font_optimization' => __('Lettertypen', 'ultracache-pro'),
            'cdn_edge_cache' => __('CDN/edge-cache', 'ultracache-pro'),
            'asset_unload' => __('Assets uitschakelen', 'ultracache-pro'),
            'image_optimization' => __('Afbeeldingsoptimalisatie', 'ultracache-pro'),
            'object_cache_overlap' => __('Object cache', 'ultracache-pro'),
            'css_js_rewrite' => __('CSS/JavaScript herschrijven', 'ultracache-pro'),
        );
        return isset($labels[$feature]) ? $labels[$feature] : ucwords(str_replace('_', ' ', sanitize_key((string) $feature)));
    }

    public static function rest_apply_conflict_resolution($request = null) {
        $confirmed = $request instanceof WP_REST_Request ? rest_sanitize_boolean($request->get_param('confirmed')) : false;
        if (!$confirmed) {
            return new WP_Error('ucp_conflict_resolution_confirmation_required', __('Bevestig eerst de voorgestelde veilige wijzigingen.', 'ultracache-pro'), array('status' => 400));
        }
        $plan = self::conflict_resolution_plan();
        if (empty($plan['recommendedChanges'])) {
            return self::action_success(__('Er zijn geen veilige UltraCache-wijzigingen nodig.', 'ultracache-pro'), array('conflictPlan' => $plan));
        }
        $settings = UCP_Options::get_all();
        $before_ids = wp_list_pluck(UCP_Options::settings_snapshots(), 'id');
        $changed = array();
        foreach ($plan['recommendedChanges'] as $change) {
            $key = sanitize_key((string) $change['key']);
            if ('' === $key || !array_key_exists($key, $settings)) {
                continue;
            }
            $settings[$key] = $change['next'];
            $changed[] = $key;
        }
        if (empty($changed) || !UCP_Options::update($settings)) {
            return new WP_Error('ucp_conflict_resolution_failed', __('De conflictinstellingen konden niet veilig worden aangepast.', 'ultracache-pro'), array('status' => 500));
        }
        $snapshot_id = self::new_snapshot_id($before_ids);
        UCP_Logger::log('notice', 'compat', 'conflict_resolution_applied', __('Veilige UltraCache-overlapinstellingen zijn uitgeschakeld.', 'ultracache-pro'), array('keys' => $changed));
        return self::action_success(__('Veilige overlapinstellingen zijn uitgeschakeld.', 'ultracache-pro'), array(
            'changedKeys' => array_values($changed),
            'snapshotId' => $snapshot_id,
            'conflictPlan' => self::conflict_resolution_plan(),
            'settings' => UCP_Options::redact_sensitive_settings(UCP_Options::get_all()),
        ));
    }

    protected static function new_snapshot_id($before_ids) {
        return UCP_Options::newest_snapshot_id($before_ids);
    }

    public static function support_mode_status() {
        $until = (int) get_option(self::DEBUG_UNTIL_OPTION, 0);
        return array(
            'active' => $until > time(),
            'until' => $until,
            'supportReportEndpoint' => '/support-report',
            'logPackageUrl' => class_exists('UCP_Log_Package') ? UCP_Log_Package::download_url() : '',
            'logPackageAvailable' => class_exists('ZipArchive'),
        );
    }

    public static function rest_disable_support_mode() {
        if (!self::disable_support_mode('manual')) {
            return new WP_Error('ucp_support_mode_disable_failed', __('Supportmodus kon niet veilig worden gestopt.', 'ultracache-pro'), array('status' => 500));
        }
        return self::action_success(__('Supportmodus is gestopt en eerdere diagnostiekinstellingen zijn hersteld.', 'ultracache-pro'), array('supportMode' => self::support_mode_status()));
    }

    public static function disable_support_mode($reason = 'manual') {
        if (!is_scalar($reason) && null !== $reason) {
            $reason = 'manual';
        }
        $previous = get_option(self::SUPPORT_PREVIOUS_OPTION, array());
        $settings = UCP_Options::get_all();
        $keys = self::support_setting_keys();
        if (is_array($previous) && !empty($previous['settings']) && is_array($previous['settings'])) {
            foreach ($keys as $key) {
                if (array_key_exists($key, $previous['settings'])) {
                    $settings[$key] = $previous['settings'][$key];
                }
            }
        } else {
            $settings['enable_runtime_debug_headers'] = 0;
        }
        $saved = UCP_Options::update($settings);
        if (!$saved) {
            return false;
        }
        delete_option(self::DEBUG_UNTIL_OPTION);
        delete_option(self::SUPPORT_PREVIOUS_OPTION);
        UCP_Logger::log('notice', 'diagnostics', 'support_mode_disabled', __('Supportmodus is gestopt.', 'ultracache-pro'), array('reason' => sanitize_key((string) $reason)));
        return true;
    }

    public static function support_setting_keys() {
        return array('enable_logs', 'enable_diagnostics', 'enable_admin_queue_runner', 'enable_health_checks', 'enable_runtime_debug_headers');
    }
}
