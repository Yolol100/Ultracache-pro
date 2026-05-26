<?php
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped -- this helper echoes pre-escaped attribute fragments and static disabled attributes.

final class UCP_Admin_Fields {
    protected static function normalize_args($key, $settings, $help_or_args) {
        $args = is_array($help_or_args) ? $help_or_args : array('help' => $help_or_args);
        $logic = class_exists('UCP_Admin_Field_Logic') ? UCP_Admin_Field_Logic::get($key, $settings) : array();
        $args = wp_parse_args($args, $logic);
        $args['help'] = isset($args['help']) ? (string) $args['help'] : '';
        $args['warnings'] = isset($args['warnings']) && is_array($args['warnings']) ? array_values(array_unique(array_filter(array_map('strval', $args['warnings'])))) : array();
        $args['disabled_reasons'] = isset($args['disabled_reasons']) && is_array($args['disabled_reasons']) ? array_values(array_unique(array_filter(array_map('strval', $args['disabled_reasons'])))) : array();
        $args['disabled'] = !empty($args['disabled']);
        $args['hide_when_disabled'] = !empty($args['hide_when_disabled']);
        $args['parent'] = isset($args['parent']) ? (string) $args['parent'] : '';
        $args['disabled_if'] = isset($args['disabled_if']) && is_array($args['disabled_if']) ? array_values($args['disabled_if']) : array();
        return $args;
    }

    protected static function wrapper_attrs($key, $args) {
        $attrs = array(
            'class' => 'ucp-field-surface' . (!empty($args['disabled']) ? ' is-disabled' : ''),
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
        <label class="ucp-field ucp-field--checkbox ucp-checkbox<?php echo !empty($args['disabled']) ? ' is-disabled' : ''; ?>"<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wrapper attributes are escaped before output.
            echo $wrapper_attrs; ?>>
            <input type="hidden" data-ucp-control="1" name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[<?php echo esc_attr($key); ?>]" value="0"<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static disabled attribute.
            echo $disabled_attr; ?>>
            <input type="checkbox" <?php echo class_exists('UCP_Options') && UCP_Options::get('accessibility_mode') ? '' : 'role="switch"'; ?> data-ucp-control="1" data-ucp-primary-control="1" name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[<?php echo esc_attr($key); ?>]" value="1" aria-label="<?php echo esc_attr($label); ?>" <?php checked(!empty($settings[$key])); ?><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static disabled attribute.
            echo $disabled_attr; ?>>
            <span class="ucp-checkbox__text">
                <span class="ucp-checkbox__label"><strong><?php echo esc_html($label); ?></strong></span>
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
        $is_sensitive = class_exists('UCP_Options') && method_exists('UCP_Options', 'is_sensitive_key') && UCP_Options::is_sensitive_key($key);
        $field_value = isset($settings[$key]) ? $settings[$key] : '';
        if ($is_sensitive && class_exists('UCP_Options') && method_exists('UCP_Options', 'mask_secret_value')) {
            $field_value = UCP_Options::mask_secret_value($field_value);
        }
        ?>
        <label class="ucp-field ucp-field--text<?php echo !empty($args['disabled']) ? ' is-disabled' : ''; ?>"<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wrapper attributes are escaped before output.
            echo $wrapper_attrs; ?>>
            <span><?php echo esc_html($label); ?></span>
            <input type="<?php echo $is_sensitive ? 'password' : 'text'; ?>" autocomplete="<?php echo $is_sensitive ? 'new-password' : 'off'; ?>" data-ucp-control="1" data-ucp-primary-control="1" name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($field_value); ?>" aria-label="<?php echo esc_attr($label); ?>"<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static disabled attribute.
            echo $disabled_attr; ?>>
            <?php if ($args['help']) : ?><small><?php echo esc_html($args['help']); ?></small><?php endif; ?>
            <?php self::render_messages($args); ?>
        </label>
        <?php
    }

    public static function number($key, $label, $settings, $min = 0, $max = 999999, $help = '') {
        $args = self::normalize_args($key, $settings, $help);
        $wrapper_attrs = self::wrapper_attrs($key, $args);
        $disabled_attr = !empty($args['disabled']) ? ' disabled' : '';
        ?>
        <label class="ucp-field ucp-field--number<?php echo !empty($args['disabled']) ? ' is-disabled' : ''; ?>"<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wrapper attributes are escaped before output.
            echo $wrapper_attrs; ?>>
            <span><?php echo esc_html($label); ?></span>
            <input type="number" data-ucp-control="1" data-ucp-primary-control="1" min="<?php echo esc_attr((string) $min); ?>" max="<?php echo esc_attr((string) $max); ?>" name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr((string) (isset($settings[$key]) ? $settings[$key] : '')); ?>" aria-label="<?php echo esc_attr($label); ?>"<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static disabled attribute.
            echo $disabled_attr; ?>>
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
        <label class="ucp-field ucp-field--textarea<?php echo !empty($args['disabled']) ? ' is-disabled' : ''; ?>"<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wrapper attributes are escaped before output.
            echo $wrapper_attrs; ?>>
            <span><?php echo esc_html($label); ?></span>
            <textarea rows="5" data-ucp-control="1" data-ucp-primary-control="1" name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[<?php echo esc_attr($key); ?>]" aria-label="<?php echo esc_attr($label); ?>"<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static disabled attribute.
            echo $disabled_attr; ?>><?php echo esc_textarea(isset($settings[$key]) ? $settings[$key] : ''); ?></textarea>
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
        <label class="ucp-field ucp-field--select<?php echo !empty($args['disabled']) ? ' is-disabled' : ''; ?>"<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wrapper attributes are escaped before output.
            echo $wrapper_attrs; ?>>
            <span><?php echo esc_html($label); ?></span>
            <select data-ucp-control="1" data-ucp-primary-control="1" name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[<?php echo esc_attr($key); ?>]" aria-label="<?php echo esc_attr($label); ?>"<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static disabled attribute.
            echo $disabled_attr; ?>>
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
