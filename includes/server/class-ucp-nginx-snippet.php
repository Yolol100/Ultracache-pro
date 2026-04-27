<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Nginx_Snippet {
    public static function snippet() {
        return "# UltraCache Pro Nginx example snippet (copy manually; plugin never writes Nginx config)\n" .
            "set \$ucp_skip_cache 0;\n" .
            "if (\$request_method != GET) { set \$ucp_skip_cache 1; }\n" .
            "if (\$query_string != '') { set \$ucp_skip_cache 1; }\n" .
            "if (\$http_cookie ~* 'wordpress_logged_in_|wp_woocommerce_session_|woocommerce_items_in_cart|woocommerce_cart_hash|comment_author_|wp-postpass_') { set \$ucp_skip_cache 1; }\n" .
            "if (\$request_uri ~* '/(cart|checkout|my-account|order-pay|add-payment-method|order-received|wc-api|wp-json)') { set \$ucp_skip_cache 1; }\n" .
            "# Coordinate this with your host's FastCGI/static cache rules.\n";
    }
}
