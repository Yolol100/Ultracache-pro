<?php
if (!defined('ABSPATH')) {
    exit;
}

final class UCP_Admin_Fields {
    protected static function normalize_args($key, $settings, $help_or_args) {
        $args = is_array($help_or_args) ? $help_or_args : array('help' => $help_or_args);
        $logic = class_exists('UCP_Admin_Field_Logic') ? UCP_Admin_Field_Logic::get($key, $settings) : array();
        $args = wp_parse_args($args, $logic);
        $args['help'] = isset($args['help']) ? (string) $args['help'] : '';
        $args['warnings'] = isset($args['warnings']) && is_array($args['warnings']) ? array_values(array_unique(array_filter(array_map('strval', $args['warnings'])))) : array();
        $args['disabled_reasons'] = isset($args['disabled_reasons']) && is_array($args['disabled_reasons']) ? array_values(array_unique(array_filter(array_map('strval', $args['disabled_reasons'])))) : array();
        $args['badges'] = isset($args['badges']) && is_array($args['badges']) ? array_values(array_unique(array_filter(array_map('strval', $args['badges' ])))) : self::default_badges($key);
        $args['disabled'] = !empty($args['disabled']);
        $args['hide_when_disabled'] = !empty($args['hide_when_disabled']);
        $args['parent'] = isset($args['parent']) ? (string) $args['parent'] : '';
        $args['disabled_if'] = isset($args['disabled_if']) && is_array($args['disabled_if']) ? array_values($args['disabled_if']) : array();
        return $args;
    }

    protected static function wrapper_attrs($key, $args) {
        $attrs = array(
            'data-ucp-field-key' => $key,
        );

        if (!empty($args['parent'])) {
            $attrs['data-ucp-parent'] = $args['parent'];
        }
        if (!empty($args['hide_when_disabled'])) {
            $attrs['data-ucp-hide-when-disabled'] = '1';
        }
        if (!empty($args['disabled_if'])) {
            $attrs['data-ucp-disabled-if'] = wp_json_encode($args['disabled_if']);
        }

        $compiled = '';
        foreach ($attrs as $name => $value) {
            if ('' === (string) $value) {
                continue;
            }
            $compiled .= ' ' . $name . '="' . esc_attr((string) $value) . '"';
        }
        return $compiled;
    }

    protected static function default_badges($key) {
        $map = array(
            'enable_delay_js' => array('Staging-first', 'May affect layout'),
            'enable_js_combine' => array('Advanced', 'May affect layout'),
            'enable_css_combine' => array('Advanced', 'May affect layout'),
            'enable_used_css' => array('Staging-first', 'Advanced'),
            'enable_critical_css' => array('Staging-first', 'Advanced'),
            'enable_remote_css_render' => array('Developer-only'),
            'enable_lazy_images' => array('Low risk'),
            'enable_lazy_iframes' => array('Low risk'),
            'enable_cdn_purge' => array('Staging-first'),
            'db_cleanup_optimize_tables' => array('Staging-first'),
            'db_cleanup_all_transients' => array('Advanced'),
            'confirm_page_cache_takeover' => array('Developer-only'),
            'allow_wp_config_write' => array('Developer-only'),
            'allow_dropin_writes' => array('Developer-only'),
            'enable_rest_cache' => array('Developer-only', 'Staging-first'),
            'enable_fragment_cache' => array('Developer-only'),
            'enable_crawler' => array('Staging-first'),
            'enable_cache_vary' => array('Developer-only', 'Staging-first'),
            'vary_mobile_desktop' => array('Staging-first'),
            'serve_mode' => array('Developer-only'),
            'compat_remote_updates_enabled' => array('Advanced'),
        );
        return isset($map[$key]) ? $map[$key] : array();
    }

    protected static function render_badges($args) {
        if (empty($args['badges'])) { return; }
        echo '<span class="ucp-impact-labels">';
        foreach ($args['badges'] as $badge) {
            $slug = sanitize_html_class(strtolower(str_replace(' ', '-', $badge)));
            echo '<span class="ucp-impact-label ucp-impact-label--' . esc_attr($slug) . '">' . esc_html($badge) . '</span>';
        }
        echo '</span>';
    }

    protected static function render_messages($args) {
        if (empty($args['warnings']) && empty($args['disabled_reasons'])) {
            return;
        }
        echo '<span class="ucp-field__messages">';
        foreach ((array) $args['warnings'] as $warning) {
            echo '<span class="ucp-field__warning">' . esc_html($warning) . '</span>';
        }
        foreach ((array) $args['disabled_reasons'] as $reason) {
            echo '<span class="ucp-field__reason">' . esc_html($reason) . '</span>';
        }
        echo '</span>';
    }

    public static function checkbox($key, $label, $settings, $help = '') {
        $args = self::normalize_args($key, $settings, $help);
        $wrapper_attrs = self::wrapper_attrs($key, $args);
        $disabled_attr = !empty($args['disabled']) ? ' disabled' : '';
        ?>
        <label class="ucp-field ucp-field-surface ucp-checkbox<?php echo !empty($args['disabled']) ? ' is-disabled' : ''; ?>"<?php echo $wrapper_attrs; ?>>
            <input type="hidden" data-ucp-control="1" name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[<?php echo esc_attr($key); ?>]" value="0"<?php echo $disabled_attr; ?>>
            <input type="checkbox" role="switch" data-ucp-control="1" data-ucp-primary-control="1" name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[<?php echo esc_attr($key); ?>]" value="1" aria-label="<?php echo esc_attr($label); ?>" <?php checked(!empty($settings[$key])); ?><?php echo $disabled_attr; ?>>
            <span class="ucp-checkbox__text">
                <span class="ucp-checkbox__label"><strong><?php echo esc_html($label); ?></strong><?php self::render_badges($args); ?></span>
                <?php if ($args['help']) : ?><span class="ucp-checkbox__help"><?php echo esc_html($args['help']); ?></span><?php endif; ?>
                <?php self::render_messages($args); ?>
            </span>
        </label>
        <?php
    }

    public static function text($key, $label, $settings, $help = '') {
        $args = self::normalize_args($key, $settings, $help);
        $wrapper_attrs = self::wrapper_attrs($key, $args);
        $disabled_attr = !empty($args['disabled']) ? ' disabled' : '';
        ?>
        <label class="ucp-field ucp-field-surface<?php echo !empty($args['disabled']) ? ' is-disabled' : ''; ?>"<?php echo $wrapper_attrs; ?>>
            <span><?php echo esc_html($label); ?><?php self::render_badges($args); ?></span>
            <input type="text" data-ucp-control="1" data-ucp-primary-control="1" name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr(isset($settings[$key]) ? $settings[$key] : ''); ?>" aria-label="<?php echo esc_attr($label); ?>"<?php echo $disabled_attr; ?>>
            <?php if ($args['help']) : ?><small><?php echo esc_html($args['help']); ?></small><?php endif; ?>
            <?php self::render_messages($args); ?>
        </label>
        <?php
    }

    public static function secret($key, $label, $settings, $help = '') {
        $args = self::normalize_args($key, $settings, $help);
        $wrapper_attrs = self::wrapper_attrs($key, $args);
        $disabled_attr = !empty($args['disabled']) ? ' disabled' : '';
        $has_value = isset($settings[$key]) && '' !== (string) $settings[$key];
        $placeholder = $has_value ? __('Ingesteld - laat leeg om te behouden', 'ultracache-pro') : __('Nog niet ingesteld', 'ultracache-pro');
        ?>
        <label class="ucp-field ucp-field-surface<?php echo !empty($args['disabled']) ? ' is-disabled' : ''; ?>"<?php echo $wrapper_attrs; ?>>
            <span><?php echo esc_html($label); ?><?php self::render_badges($args); ?></span>
            <?php if ($has_value) : ?>
                <input type="hidden" name="ucp_secret_keep[<?php echo esc_attr($key); ?>]" value="1">
            <?php endif; ?>
            <input type="password" data-ucp-control="1" data-ucp-primary-control="1" autocomplete="new-password" name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[<?php echo esc_attr($key); ?>]" value="" placeholder="<?php echo esc_attr($placeholder); ?>" aria-label="<?php echo esc_attr($label); ?>"<?php echo $disabled_attr; ?>>
            <?php if ($args['help']) : ?><small><?php echo esc_html($args['help']); ?> <?php esc_html_e('Deze waarde wordt niet zichtbaar getoond en niet in supportbundles geëxporteerd.', 'ultracache-pro'); ?></small><?php endif; ?>
            <?php self::render_messages($args); ?>
        </label>
        <?php
    }

    public static function number($key, $label, $settings, $min = 0, $max = 999999, $help = '') {
        $args = self::normalize_args($key, $settings, $help);
        $wrapper_attrs = self::wrapper_attrs($key, $args);
        $disabled_attr = !empty($args['disabled']) ? ' disabled' : '';
        ?>
        <label class="ucp-field ucp-field-surface<?php echo !empty($args['disabled']) ? ' is-disabled' : ''; ?>"<?php echo $wrapper_attrs; ?>>
            <span><?php echo esc_html($label); ?><?php self::render_badges($args); ?></span>
            <input type="number" data-ucp-control="1" data-ucp-primary-control="1" min="<?php echo esc_attr((string) $min); ?>" max="<?php echo esc_attr((string) $max); ?>" name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr((string) (isset($settings[$key]) ? $settings[$key] : '')); ?>" aria-label="<?php echo esc_attr($label); ?>"<?php echo $disabled_attr; ?>>
            <?php if ($args['help']) : ?><small><?php echo esc_html($args['help']); ?></small><?php endif; ?>
            <?php self::render_messages($args); ?>
        </label>
        <?php
    }

    public static function textarea($key, $label, $settings, $help = '') {
        $args = self::normalize_args($key, $settings, $help);
        $wrapper_attrs = self::wrapper_attrs($key, $args);
        $disabled_attr = !empty($args['disabled']) ? ' disabled' : '';
        ?>
        <label class="ucp-field ucp-field-surface<?php echo !empty($args['disabled']) ? ' is-disabled' : ''; ?>"<?php echo $wrapper_attrs; ?>>
            <span><?php echo esc_html($label); ?><?php self::render_badges($args); ?></span>
            <textarea rows="5" data-ucp-control="1" data-ucp-primary-control="1" name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[<?php echo esc_attr($key); ?>]" aria-label="<?php echo esc_attr($label); ?>"<?php echo $disabled_attr; ?>><?php echo esc_textarea(isset($settings[$key]) ? $settings[$key] : ''); ?></textarea>
            <?php if ($args['help']) : ?><small><?php echo esc_html($args['help']); ?></small><?php endif; ?>
            <?php self::render_messages($args); ?>
        </label>
        <?php
    }

    public static function select($key, $label, $settings, $options, $help = '') {
        $args = self::normalize_args($key, $settings, $help);
        $wrapper_attrs = self::wrapper_attrs($key, $args);
        $disabled_attr = !empty($args['disabled']) ? ' disabled' : '';
        ?>
        <label class="ucp-field ucp-field-surface<?php echo !empty($args['disabled']) ? ' is-disabled' : ''; ?>"<?php echo $wrapper_attrs; ?>>
            <span><?php echo esc_html($label); ?><?php self::render_badges($args); ?></span>
            <select data-ucp-control="1" data-ucp-primary-control="1" name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[<?php echo esc_attr($key); ?>]" aria-label="<?php echo esc_attr($label); ?>"<?php echo $disabled_attr; ?>>
                <?php foreach ($options as $value => $option_label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected(isset($settings[$key]) ? $settings[$key] : '', $value); ?>><?php echo esc_html($option_label); ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($args['help']) : ?><small><?php echo esc_html($args['help']); ?></small><?php endif; ?>
            <?php self::render_messages($args); ?>
        </label>
        <?php
    }
}
