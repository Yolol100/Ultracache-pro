# WooCommerce Checkout Runtime Proof

Status: runtime proof required for WooCommerce / checkout safety 15/15.

## Score gate

WooCommerce / checkout safety may be scored 15/15 only when every critical checkout test below passes on staging with the relevant UltraCache features enabled.

## Feature state to record

| Feature | State | Notes |
|---|---|---|
| Page cache | Pending | |
| REST cache | Pending | |
| Delay JS | Pending | |
| Used CSS | Pending | |
| Critical CSS | Pending | |
| JS minify | Pending | |
| CSS minify | Pending | |
| Lazyload | Pending | |
| WooCommerce exclusions | Pending | |

## Required checkout tests

| Test | Required result | Actual result | Evidence | Status |
|---|---|---|---|---|
| Product page loads | No layout/console errors | Not executed | Staging required | Pending |
| Add to cart | Cart receives product | Not executed | Staging required | Pending |
| Cart page | Not publicly cached | Not executed | Staging required | Pending |
| Change quantity | Totals update correctly | Not executed | Staging required | Pending |
| Apply coupon | Coupon/totals update | Not executed | Staging required | Pending |
| Remove coupon | Totals update | Not executed | Staging required | Pending |
| Checkout loads | Form, totals, shipping visible | Not executed | Staging required | Pending |
| Shipping method | Can select/update | Not executed | Staging required | Pending |
| Payment method | Can select in sandbox/test mode | Not executed | Staging required | Pending |
| Place test order | Test order completes | Not executed | Staging required | Pending |
| Order confirmation | Correct order page shown | Not executed | Staging required | Pending |
| Order-pay URL | Not cached and functional | Not executed | Staging required | Pending |
| My account | Not cached and personal data private | Not executed | Staging required | Pending |
| Logged-out flow | Works | Not executed | Staging required | Pending |
| Logged-in flow | Works | Not executed | Staging required | Pending |
| Mobile checkout | Works at 390px | Not executed | Staging required | Pending |
| wc-ajax endpoints | Work without cache breakage | Not executed | Staging required | Pending |
| wc-cart-fragments | Works or is safely excluded | Not executed | Staging required | Pending |
| Payment scripts with Delay JS | Not delayed/broken | Not executed | Staging required | Pending |
| Checkout with Used CSS/Critical CSS | No visual breakage | Not executed | Staging required | Pending |

## Privacy/cache checks

| Check | Required result | Actual result | Status |
|---|---|---|---|
| No order data in public cache | Confirmed on filesystem/server | Not executed | Pending |
| No customer/session data in public cache | Confirmed on filesystem/server | Not executed | Pending |
| WooCommerce cookies bypass cache | Confirmed via headers/behavior | Not executed | Pending |

## Conclusion

Current score gate result: 14/15 remains until staging proof is filled and passed.
