<?php
if (!defined('ABSPATH')) {
    exit;
}

return array(
    array(
        'id' => 'woocommerce-safe-core',
        'version' => '1.0.0',
        'signature' => array('plugin' => 'woocommerce/woocommerce.php', 'class' => 'WooCommerce'),
        'applies_to' => array('cache', 'delay_js', 'rest_cache', 'fragment_cache'),
        'affected_feature' => 'woocommerce_safety',
        'exclusions' => array('cart', 'checkout', 'my-account', 'order-pay', 'add-payment-method', 'order-received', 'wc-api', 'add-to-cart=', 'wc-cart-fragments', 'woocommerce'),
        'risk_tags' => array('may_break_checkout', 'staging_first'),
        'message' => 'WooCommerce checkout, cart and account flows must stay excluded from public caching and aggressive JavaScript delay.',
        'source' => 'bundled',
        'changelog' => 'Initial bundled WooCommerce safety rule.',
        'enabled' => true,
        'provenance' => 'bundled',
    ),
    array(
        'id' => 'elementor-delay-js-safe-list',
        'version' => '1.0.0',
        'signature' => array('plugin' => 'elementor/elementor.php', 'class' => 'Elementor\\Plugin'),
        'applies_to' => array('delay_js', 'critical_css'),
        'affected_feature' => 'builder_assets',
        'exclusions' => array('elementor', 'elementor-frontend', 'swiper', 'webpack.runtime', 'frontend-modules'),
        'risk_tags' => array('may_affect_layout', 'staging_first'),
        'message' => 'Elementor frontend scripts often need conservative Delay JS exclusions.',
        'source' => 'bundled',
        'changelog' => 'Initial Elementor safety rule.',
        'enabled' => true,
        'provenance' => 'bundled',
    ),
    array(
        'id' => 'forms-delay-js-safe-list',
        'version' => '1.0.0',
        'signature' => array('plugins' => array('gravityforms/gravityforms.php', 'contact-form-7/wp-contact-form-7.php', 'wpforms-lite/wpforms.php', 'fluentform/fluentform.php')),
        'applies_to' => array('delay_js'),
        'affected_feature' => 'forms',
        'exclusions' => array('gform', 'gravityforms', 'contact-form-7', 'wpcf7', 'wpforms', 'fluentform'),
        'risk_tags' => array('may_affect_forms', 'staging_first'),
        'message' => 'Form validation and AJAX submit scripts should be tested before delaying.',
        'source' => 'bundled',
        'changelog' => 'Initial forms compatibility rule.',
        'enabled' => true,
        'provenance' => 'bundled',
    ),
);
