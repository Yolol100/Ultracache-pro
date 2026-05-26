<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Admin_Notices_Render_Trait {
    public function hide_third_party_notices() {
        $page = /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin routing/filter parameter. */ isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ('ultracache-pro' !== $page) {
            return;
        }

        // UltraCache uses its own toast system on the React admin screen, so suppress WordPress admin notices there.
        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
    }

    public function render_admin_notices() {
        $page = /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin routing/filter parameter. */ isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ('ultracache-pro' !== $page) {
            return;
        }
        return;

        $map = array(
            'preset'          => __('Instellingen aangezet.', 'ultracache-pro'),
            'seeded'          => __('Testtaken gemaakt.', 'ultracache-pro'),
            'jobs'            => __('Taken gestart.', 'ultracache-pro'),
            'import'          => __('Bestand verwerkt.', 'ultracache-pro'),
            'health'          => __('Controle bijgewerkt.', 'ultracache-pro'),
            'onboarding'      => __('Instelhulp is klaar.', 'ultracache-pro'),
            'maintenance'     => __('Onderhoud is klaar.', 'ultracache-pro'),
            'purged'          => __('Cache is geleegd.', 'ultracache-pro'),
            'preloaded'       => __('Cache is opgewarmd.', 'ultracache-pro'),
            'preload_queued'  => __('Pagina\'s zijn toegevoegd om op te warmen.', 'ultracache-pro'),
            'easy_mode'       => __('Snelle hulp is aangepast.', 'ultracache-pro'),
            'auto_compat'     => __('Automatische hulp is toegepast.', 'ultracache-pro'),
            'heartbeat_defaults' => __('Veilige Heartbeat instellingen zijn toegepast.', 'ultracache-pro'),
            'preload_defaults' => __('Veilige opwarminstellingen zijn toegepast.', 'ultracache-pro'),
            'server_cache_fixed' => __('UltraCache is nu de actieve page-cache drop-in.', 'ultracache-pro'),
            'dropin_checked' => __('Drop-in bestandsrechten opnieuw gecontroleerd.', 'ultracache-pro'),
            'dropin_backup' => __('De vorige advanced-cache.php is veilig als backup bewaard.', 'ultracache-pro'),
            'dropin_auto_installed' => __('UltraCache heeft automatisch de page-cache drop-in geactiveerd omdat er geen actieve andere cacheplugin is gevonden.', 'ultracache-pro'),
            'dropin_auto_blocked' => __('UltraCache heeft de bestaande drop-in niet automatisch vervangen omdat er nog een actieve cacheplugin is gevonden.', 'ultracache-pro'),
            'server_cache_preserved' => __('WP_CACHE is aangezet. De bestaande advanced-cache.php is bewaard als backup en UltraCache is actief gemaakt.', 'ultracache-pro'),
            'html_test_defaults' => __('Veilige HTML-instellingen actief.', 'ultracache-pro'),
            'cloud_sync'      => __('Cloud synchronisatie is uitgevoerd.', 'ultracache-pro'),
            'object_cache_checked' => __('Object cache status is gecontroleerd.', 'ultracache-pro'),
            'images_optimized' => __('Afbeeldingsoptimalisatie is uitgevoerd.', 'ultracache-pro'),
            'runtime'       => __('Systeemtest is uitgevoerd.', 'ultracache-pro'),
        );

        $this->render_flash_notice();

        foreach ($map as $query_key => $message) {
            if (!/* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin routing/filter parameter. */ isset($_GET[$query_key])) {
                continue;
            }

            echo '<div class="notice notice-success is-dismissible ucp-notice"><p>' . esc_html($message) . '</p></div>';
            break;
        }


        $jobs_retry_done = /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin routing/filter parameter. */ isset($_GET['jobs_retry_done']) ? absint(wp_unslash($_GET['jobs_retry_done'])) : 0;
        $jobs_retry_conflicts = /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin routing/filter parameter. */ isset($_GET['jobs_retry_conflicts']) ? absint(wp_unslash($_GET['jobs_retry_conflicts'])) : 0;
        $jobs_retry_missing = /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin routing/filter parameter. */ isset($_GET['jobs_retry_missing']) ? absint(wp_unslash($_GET['jobs_retry_missing'])) : 0;
        $jobs_retry_errors = /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin routing/filter parameter. */ isset($_GET['jobs_retry_errors']) ? absint(wp_unslash($_GET['jobs_retry_errors'])) : 0;

        if ($jobs_retry_done > 0) {
            $message = sprintf(
                /* translators: %d: number of retried jobs. */
                _n('%d taak opnieuw in de wachtrij gezet.', '%d taken opnieuw in de wachtrij gezet.', $jobs_retry_done, 'ultracache-pro'),
                $jobs_retry_done
            );
            echo '<div class="notice notice-success is-dismissible ucp-notice"><p>' . esc_html($message) . '</p></div>';
        }

        if ($jobs_retry_conflicts > 0 || $jobs_retry_missing > 0 || $jobs_retry_errors > 0) {
            $parts = array();
            if ($jobs_retry_conflicts > 0) {
                $parts[] = sprintf(
                /* translators: %d: number of skipped duplicate jobs. */
                    _n('%d taak is overgeslagen omdat er al een actieve dubbele taak bestaat.', '%d taken zijn overgeslagen omdat er al actieve dubbele taken bestaan.', $jobs_retry_conflicts, 'ultracache-pro'),
                    $jobs_retry_conflicts
                );
            }
            if ($jobs_retry_missing > 0) {
                $parts[] = sprintf(
                /* translators: %d: number of missing jobs. */
                    _n('%d taak bestond niet meer.', '%d taken bestonden niet meer.', $jobs_retry_missing, 'ultracache-pro'),
                    $jobs_retry_missing
                );
            }
            if ($jobs_retry_errors > 0) {
                $parts[] = sprintf(
                /* translators: %d: number of jobs that could not be retried. */
                    _n('%d taak kon niet opnieuw worden ingepland.', '%d taken konden niet opnieuw worden ingepland.', $jobs_retry_errors, 'ultracache-pro'),
                    $jobs_retry_errors
                );
            }

            echo '<div class="notice notice-warning ucp-notice"><p>' . esc_html(implode(' ', $parts)) . '</p></div>';
        }


        $warning_items = array();
        $info_items = array();
        $warning_actions = array();
        $check_dropin_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_check_dropin_owner'), 'ucp_check_dropin_owner');
        $fix_dropin_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_fix_server_cache'), 'ucp_fix_server_cache');

        if (!UCP_Helpers::has_valid_wp_cache_constant() && $this->tab_allows(array('overview', 'cache', 'tools'))) {
            $warning_items[] = esc_html__('WP_CACHE staat niet actief in wp-config.php. UltraCache kan daardoor niet vroeg genoeg laden.', 'ultracache-pro');
            $warning_actions[] = array(
                'label' => __('WP_CACHE veilig aanzetten', 'ultracache-pro'),
                'url'   => $fix_dropin_url,
            );
        }

        $conflicts = get_option('ucp_detected_conflicts', array());
        if (!empty($conflicts) && is_array($conflicts) && $this->tab_allows(array('overview', 'cache', 'optimization', 'expert', 'tools'))) {
            $labels = array();
            foreach ($conflicts as $conflict) {
                if (!empty($conflict['label'])) {
                    $labels[] = (string) $conflict['label'];
                }
            }
            if (!empty($labels)) {
                $warning_items[] = sprintf(
                    /* translators: %s: comma separated conflict labels. */
                    __('Mogelijke overlap gevonden: %s. UltraCache neemt automatisch over als er geen actieve andere page-cacheplugin is. Is er wel een actieve cacheplugin, kies dan bewust welke cachelaag actief mag zijn of gebruik de knop om UltraCache alsnog te activeren.', 'ultracache-pro'),
                    esc_html(implode(', ', $labels))
                );
                $warning_actions[] = array(
                    'label' => __('Cachelaag controleren', 'ultracache-pro'),
                    'url'   => $check_dropin_url,
                );
                $warning_actions[] = array(
                    'label' => __('Back-up maken en cache activeren', 'ultracache-pro'),
                    'url'   => $fix_dropin_url,
                );
            }
        }

        if (get_option('ucp_advanced_cache_conflict') && $this->tab_allows(array('overview', 'cache', 'expert', 'tools'))) {
            $warning_items[] = esc_html__('Er staat al een advanced-cache.php van een andere cachelaag. Als er geen andere cacheplugin actief is, neemt UltraCache dit automatisch veilig over. Anders kun je bewust een back-up maken en cache activeren.', 'ultracache-pro');
            $warning_actions[] = array(
                'label' => __('Bestaande drop-in back-uppen en cache activeren', 'ultracache-pro'),
                'url'   => $fix_dropin_url,
            );
        }

        if (class_exists('UCP_Compat') && $this->tab_allows(array('overview', 'optimization', 'expert', 'tools'))) {
            $disabled = UCP_Compat::recommended_disabled_features();
            if (!empty($disabled)) {
                $info_items[] = sprintf(
                    /* translators: %s: comma separated features. */
                    __('Aanbevolen veilige modus door overlap: %s.', 'ultracache-pro'),
                    esc_html(implode(', ', UCP_Compat::feature_labels($disabled)))
                );
            }
        }

        $this->render_group_notice('notice-warning', __('Aandacht vereist', 'ultracache-pro'), $warning_items, $warning_actions);
        $this->render_group_notice('notice-info', __('Context voor deze tab', 'ultracache-pro'), $info_items);
    }
}
