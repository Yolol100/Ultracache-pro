<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Notices {
    protected $admin;

    protected function current_tab() {
        return method_exists($this->admin, 'get_current_tab') ? (string) $this->admin->get_current_tab() : 'overview';
    }

    protected function tab_allows($tabs) {
        return in_array($this->current_tab(), (array) $tabs, true);
    }

    protected function render_group_notice($class, $title, $items, $actions = array()) {
        $items = array_values(array_filter((array) $items));
        if (empty($items)) {
            return;
        }

        echo '<div class="ucp-notice ucp-notice--premium ' . esc_attr($class) . '"><p><strong>' . esc_html($title) . '</strong></p><ul class="ucp-notice-list">';
        foreach ($items as $item) {
            echo '<li>' . wp_kses_post($item) . '</li>';
        }
        echo '</ul>';
        if (!empty($actions)) {
            echo '<div class="ucp-notice-actions">';
            foreach ($actions as $action) {
                echo '<a class="ucp-button ucp-button--secondary" href="' . esc_url($action['url']) . '">' . esc_html($action['label']) . '</a>';
            }
            echo '</div>';
        }
        echo '</div>';
    }

    public function __construct($admin) {
        $this->admin = $admin;
    }

    public function hide_third_party_notices() {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ('ultracache-pro' !== $page) {
            return;
        }

    }

    public function render_admin_notices() {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ('ultracache-pro' !== $page) {
            return;
        }

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
            'dropin_checked' => __('Drop-in eigenaar opnieuw gecontroleerd.', 'ultracache-pro'),
            'dropin_backup' => __('De vorige advanced-cache.php is veilig als backup bewaard.', 'ultracache-pro'),
            'dropin_auto_installed' => __('UltraCache heeft automatisch de page-cache drop-in geactiveerd omdat er geen actieve andere cacheplugin is gevonden.', 'ultracache-pro'),
            'dropin_auto_blocked' => __('UltraCache heeft de bestaande drop-in niet automatisch vervangen omdat er nog een actieve cacheplugin is gevonden.', 'ultracache-pro'),
            'server_cache_preserved' => __('WP_CACHE is aangezet. De bestaande advanced-cache.php is bewaard als backup en UltraCache is actief gemaakt.', 'ultracache-pro'),
            'html_test_defaults' => __('Veilige HTML-instellingen actief.', 'ultracache-pro'),
        );

        foreach ($map as $query_key => $message) {
            if (!isset($_GET[$query_key])) {
                continue;
            }

            echo '<div class="notice notice-success is-dismissible ucp-notice"><p>' . esc_html($message) . '</p></div>';
            break;
        }

        if (isset($_GET['wp_cache_failed'])) {
            $message = __('WP_CACHE kon niet automatisch in wp-config.php worden gezet. Voeg deze regel handmatig toe boven "That\'s all, stop editing": define( \'WP_CACHE\', true );', 'ultracache-pro');
            if (isset($_GET['wp_config_unwritable'])) {
                $message = __('WP_CACHE kon niet automatisch worden gezet omdat wp-config.php niet schrijfbaar is. Maak wp-config.php tijdelijk schrijfbaar of voeg handmatig toe: define( \'WP_CACHE\', true );', 'ultracache-pro');
            }
            echo '<div class="notice notice-error is-dismissible ucp-notice"><p>' . esc_html($message) . '</p></div>';
        }

        if (isset($_GET['server_cache_failed'])) {
            echo '<div class="notice notice-error is-dismissible ucp-notice"><p>' . esc_html__('De page-cache drop-in kon niet automatisch worden geplaatst. Controleer schrijfrechten op wp-content/advanced-cache.php en probeer opnieuw.', 'ultracache-pro') . '</p></div>';
        }

        $jobs_retry_done = isset($_GET['jobs_retry_done']) ? absint(wp_unslash($_GET['jobs_retry_done'])) : 0;
        $jobs_retry_conflicts = isset($_GET['jobs_retry_conflicts']) ? absint(wp_unslash($_GET['jobs_retry_conflicts'])) : 0;
        $jobs_retry_missing = isset($_GET['jobs_retry_missing']) ? absint(wp_unslash($_GET['jobs_retry_missing'])) : 0;
        $jobs_retry_errors = isset($_GET['jobs_retry_errors']) ? absint(wp_unslash($_GET['jobs_retry_errors'])) : 0;

        if ($jobs_retry_done > 0) {
            $message = sprintf(
                _n('%d taak opnieuw in de wachtrij gezet.', '%d taken opnieuw in de wachtrij gezet.', $jobs_retry_done, 'ultracache-pro'),
                $jobs_retry_done
            );
            echo '<div class="notice notice-success is-dismissible ucp-notice"><p>' . esc_html($message) . '</p></div>';
        }

        if ($jobs_retry_conflicts > 0 || $jobs_retry_missing > 0 || $jobs_retry_errors > 0) {
            $parts = array();
            if ($jobs_retry_conflicts > 0) {
                $parts[] = sprintf(
                    _n('%d taak is overgeslagen omdat er al een actieve dubbele taak bestaat.', '%d taken zijn overgeslagen omdat er al actieve dubbele taken bestaan.', $jobs_retry_conflicts, 'ultracache-pro'),
                    $jobs_retry_conflicts
                );
            }
            if ($jobs_retry_missing > 0) {
                $parts[] = sprintf(
                    _n('%d taak bestond niet meer.', '%d taken bestonden niet meer.', $jobs_retry_missing, 'ultracache-pro'),
                    $jobs_retry_missing
                );
            }
            if ($jobs_retry_errors > 0) {
                $parts[] = sprintf(
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
        $fix_dropin_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_fix_server_cache&redirect_tab=' . rawurlencode($this->current_tab())), 'ucp_fix_server_cache');

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
                    __('Mogelijke overlap gevonden: %s. UltraCache neemt automatisch over als er geen actieve andere page-cache plugin is. Is er wel een actieve cacheplugin, kies dan bewust één eigenaar of gebruik de knop om UltraCache alsnog te activeren.', 'ultracache-pro'),
                    esc_html(implode(', ', $labels))
                );
                $warning_actions[] = array(
                    'label' => __('Alleen eigenaar controleren', 'ultracache-pro'),
                    'url'   => $check_dropin_url,
                );
                $warning_actions[] = array(
                    'label' => __('Back-up maken en UltraCache activeren', 'ultracache-pro'),
                    'url'   => $fix_dropin_url,
                );
            }
        }

        if (get_option('ucp_advanced_cache_conflict') && $this->tab_allows(array('overview', 'cache', 'expert', 'tools'))) {
            $warning_items[] = esc_html__('Er staat al een advanced-cache.php van een andere cachelaag. Als er geen andere cacheplugin actief is, neemt UltraCache dit automatisch veilig over. Anders kun je bewust een backup maken en UltraCache activeren.', 'ultracache-pro');
            $warning_actions[] = array(
                'label' => __('Bestaande drop-in backuppen en UltraCache activeren', 'ultracache-pro'),
                'url'   => $fix_dropin_url,
            );
        }

        if (class_exists('UCP_Compat') && $this->tab_allows(array('overview', 'optimization', 'expert', 'tools'))) {
            $disabled = UCP_Compat::recommended_disabled_features();
            if (!empty($disabled)) {
                $info_items[] = sprintf(
                    /* translators: %s: comma separated features. */
                    __('Aanbevolen veilige modus door overlap: %s.', 'ultracache-pro'),
                    esc_html(implode(', ', class_exists('UCP_Compat') ? UCP_Compat::feature_labels($disabled) : $disabled))
                );
            }
        }

        $this->render_group_notice('notice-warning', __('Aandacht vereist', 'ultracache-pro'), $warning_items, $warning_actions);
        $this->render_group_notice('notice-info', __('Context voor deze tab', 'ultracache-pro'), $info_items);
    }
}
