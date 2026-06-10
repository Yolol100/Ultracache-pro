# UltraCache Pro Release Smoke Checks

Run these checks before shipping a production ZIP.

## Static checks

1. PHP lint all `*.php` files.
2. Node syntax check all shipped `*.js` files.
3. Verify every classmap path in `ultracache-pro.php` exists.
4. Verify every privileged REST route has a `permission_callback`.
5. Verify `admin-post.php` handlers use capability and nonce checks.
6. Verify the runtime ZIP does not contain build, tmp, log, source-map or package-manager files.

## WordPress runtime checks

1. Install and activate on a clean staging site.
2. Deactivate and reactivate.
3. Uninstall with cleanup disabled and enabled.
4. Test admin REST actions as admin, editor, subscriber and logged-out user.
5. Test purge, preload, database cleanup, import/export and support report.
6. Test `advanced-cache.php`, object-cache drop-ins and read-only filesystem behavior.
7. Test WooCommerce cart, checkout, order-pay, account and coupons when WooCommerce is active.
8. Test queue status, last run, failed jobs and manual runner actions.
