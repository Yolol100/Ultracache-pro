# UltraCache Pro Compatibility Matrix

| Surface | Required check | Pass condition |
|---|---|---|
| WordPress Core | Activate on supported WordPress version | No fatal errors or missing callbacks |
| PHP | Run on declared minimum PHP and current PHP | No syntax/deprecation blockers |
| WooCommerce absent | Load plugin without WooCommerce | No WooCommerce class/function fatal |
| WooCommerce present | Cart/checkout/order-pay/my-account test | No cached customer/session/payment output |
| Elementor absent | Load plugin without Elementor | No Elementor constant/class fatal |
| Elementor present | Builder page with Delay JS/Used CSS/Critical CSS | No broken layout or missing interactive JS |
| Composer vendor absent | Load fallback minifiers | No fatal error; Site Health shows recommended fallback status |
| Object cache absent | Page cache only | No object-cache fatal |
| Object cache present | Enable/disable object cache on staging | Rollback works and existing drop-ins are respected |
| Multisite | Network/site-level smoke test | No unintended network-wide setting changes |

This matrix documents proof targets. Runtime pass/fail must be recorded per site.
