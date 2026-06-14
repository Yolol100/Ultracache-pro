# UltraCache Pro Runtime Smoke Tests

Run these checks after installation and before enabling aggressive optimization on production.

1. Activate the plugin and confirm no PHP fatal errors or admin notices.
2. Open the UltraCache admin dashboard and save settings once.
3. Run the built-in UltraCache runtime tests from Tools/Site Health.
4. Purge cache and confirm the cache directory is writable.
5. Start preload and confirm the queue does not create a large failed backlog.
6. Deactivate and reactivate the plugin; confirm cron schedules are not duplicated.
7. Disable the plugin and confirm the site remains reachable.
8. Test with WooCommerce and Elementor disabled if the site does not use them.
9. Test with WooCommerce and Elementor enabled if the site uses them.
10. Keep a rollback copy of the previous plugin zip and cache/drop-in state.

## Pass criteria

- No PHP fatals.
- Site Health shows no critical UltraCache items.
- REST mutations are blocked for low-privilege roles.
- Cache purge/preload works without growing failed jobs.
- Frontend pages remain visually correct on mobile and desktop.
