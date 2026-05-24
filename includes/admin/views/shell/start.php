<?php
if (!defined('ABSPATH')) { exit; }
?>
        <div class="wrap ucp-wrap ucp-wpr-shell ucp-modern-admin">
            <div class="ucp-workspace ucp-modern-shell">
                <header class="ucp-modern-topbar" aria-label="<?php esc_attr_e('UltraCache beheer', 'ultracache-pro'); ?>">
                    <div class="ucp-modern-brand" aria-label="<?php esc_attr_e('UltraCache Pro', 'ultracache-pro'); ?>">
                        <span class="ucp-modern-brand__mark" aria-hidden="true"><span class="dashicons dashicons-performance"></span></span>
                        <span class="ucp-modern-brand__copy">
                            <strong><?php esc_html_e('UltraCache Pro', 'ultracache-pro'); ?></strong>
                            <small><?php echo defined('UCP_VERSION') ? esc_html(UCP_VERSION) : ''; ?></small>
                        </span>
                    </div>
                    <?php if (!empty($actions)) : ?>
                        <div class="ucp-modern-actions">
                            <?php foreach ($actions as $action) : ?>
                                <a class="<?php echo esc_attr($action['class']); ?>" href="<?php echo esc_url($action['url']); ?>"><?php echo esc_html($action['label']); ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <nav class="ucp-admin-nav" aria-label="<?php esc_attr_e('UltraCache onderdelen', 'ultracache-pro'); ?>">
                        <?php foreach ($visible_tabs as $key => $tab_data) : ?>
                            <a class="ucp-admin-nav__item <?php echo $tab === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url($admin->tab_url_public($key)); ?>" <?php echo $tab === $key ? 'aria-current="page"' : ''; ?>>
                                <span class="dashicons <?php echo esc_attr($tab_data['icon']); ?>" aria-hidden="true"></span>
                                <span class="ucp-admin-nav__text">
                                    <strong class="ucp-admin-nav__label"><?php echo esc_html($tab_data['label']); ?></strong>
                                    <?php if (!empty($tab_data['meta'])) : ?><small class="ucp-admin-nav__meta"><?php echo esc_html($tab_data['meta']); ?></small><?php endif; ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </header>
                <main class="ucp-workspace__main">
<?php
