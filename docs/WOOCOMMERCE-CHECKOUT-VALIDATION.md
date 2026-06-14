# UltraCache Pro WooCommerce Checkout Validation

Run this checklist on a WooCommerce staging copy before production rollout.

## Required flow

1. Open a product page as logged-out visitor.
2. Add product to cart.
3. Change cart quantity.
4. Apply and remove a coupon.
5. Open checkout.
6. Change shipping country/postcode if available.
7. Select each active payment method.
8. Start payment flow up to the external gateway boundary or test-mode confirmation.
9. Open order-pay URL for a pending order.
10. Open my-account as logged-in customer.
11. Repeat on mobile viewport.
12. Repeat once with Delay JS, Used CSS, Critical CSS and lazyload enabled if those features will be used.

## Pass criteria

- Cart count and totals update correctly.
- No customer/session/order data appears in cached HTML.
- Checkout scripts and gateway scripts run without console errors.
- Order-pay and my-account are not cached as public pages.
- `wc-cart-fragments`, checkout and payment scripts are excluded from risky JS delay/combine behavior.
