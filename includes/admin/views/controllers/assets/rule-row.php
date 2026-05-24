<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
        <div class="ucp-rule-row" data-rule-row>
            <input type="hidden" name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[asset_rules][<?php echo esc_attr((string) $index); ?>][id]" value="<?php echo esc_attr($rule['id']); ?>">
            <select name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[asset_rules][<?php echo esc_attr((string) $index); ?>][scope]">
                <?php foreach (array('url_contains' => __('URL bevat', 'ultracache-pro'), 'path_contains' => __('Pad bevat', 'ultracache-pro'), 'request_type' => __('Type pagina', 'ultracache-pro'), 'post_type' => __('Post type', 'ultracache-pro'), 'archive' => __('Archief', 'ultracache-pro'), 'device' => __('Device', 'ultracache-pro'), 'logged_in' => __('Ingelogd', 'ultracache-pro'), 'logged_out' => __('Uitgelogd', 'ultracache-pro'), 'regex' => __('Regex', 'ultracache-pro'), 'front_page' => __('Homepage', 'ultracache-pro'), 'singular' => __('Singular', 'ultracache-pro'), '404' => __('404', 'ultracache-pro')) as $scope => $label) : ?>
                    <option value="<?php echo esc_attr($scope); ?>" <?php selected($rule['scope'], $scope); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[asset_rules][<?php echo esc_attr((string) $index); ?>][value]" value="<?php echo esc_attr($rule['value']); ?>" placeholder="/afrekenen/" aria-label="<?php esc_attr_e('Waarde voor de regel', 'ultracache-pro'); ?>">
            <select name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[asset_rules][<?php echo esc_attr((string) $index); ?>][action]">
                <?php foreach (array('disable_cache' => __('Cache uitzetten', 'ultracache-pro'), 'disable_delay_js' => __('Later laden uitzetten', 'ultracache-pro'), 'disable_css_optimization' => __('CSS-optimalisatie uitzetten', 'ultracache-pro'), 'disable_js_optimization' => __('Scriptoptimalisatie uitzetten', 'ultracache-pro'), 'disable_speculation' => __('Opwarmen van links uitzetten', 'ultracache-pro'), 'exclude_remote_css' => __('Cloud CSS overslaan', 'ultracache-pro')) as $action => $label) : ?>
                    <option value="<?php echo esc_attr($action); ?>" <?php selected($rule['action'], $action); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <label class="ucp-inline-check ucp-inline-check--toggle"><input type="checkbox" role="switch" name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[asset_rules][<?php echo esc_attr((string) $index); ?>][enabled]" value="1" <?php checked(!empty($rule['enabled'])); ?>><span class="screen-reader-text"><?php esc_html_e('Actief', 'ultracache-pro'); ?></span></label>
            <div class="ucp-rule-actions"><button type="button" class="button ucp-sort-button ucp-move-up" aria-label="<?php esc_attr_e('Regel omhoog', 'ultracache-pro'); ?>" title="<?php esc_attr_e('Regel omhoog', 'ultracache-pro'); ?>">↑</button><button type="button" class="button ucp-sort-button ucp-move-down" aria-label="<?php esc_attr_e('Regel omlaag', 'ultracache-pro'); ?>" title="<?php esc_attr_e('Regel omlaag', 'ultracache-pro'); ?>">↓</button><button type="button" class="button button-secondary ucp-delete-rule" aria-label="<?php esc_attr_e('Regel verwijderen', 'ultracache-pro'); ?>"><?php esc_html_e('Weg', 'ultracache-pro'); ?></button></div>
        </div>
