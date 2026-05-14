<?php
if (!defined('ABSPATH')) { exit; }
?>
        <div class="wrap ucp-wrap ucp-wpr-shell">
            <div class="ucp-workspace is-wprocket-style">
                <aside class="ucp-sidebar" aria-label="<?php esc_attr_e('UltraCache navigatie', 'ultracache-pro'); ?>">
                    <div class="ucp-brand-block">
                        <span class="ucp-brand-mark" aria-hidden="true">UC</span>
                        <span class="ucp-brand-copy"><strong><?php esc_html_e('UltraCache', 'ultracache-pro'); ?></strong><span><?php esc_html_e('Superior WordPress Performance', 'ultracache-pro'); ?></span></span>
                    </div>
                    <nav class="ucp-tabs" aria-label="<?php esc_attr_e('UltraCache onderdelen', 'ultracache-pro'); ?>">
                        <?php foreach ($visible_tabs as $key => $tab_data) : ?>
                            <a class="ucp-tab <?php echo $tab === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url($admin->tab_url_public($key)); ?>">
                                <span class="dashicons <?php echo esc_attr($tab_data['icon']); ?>" aria-hidden="true"></span>
                                <span class="ucp-tab__text">
                                    <strong class="ucp-tab__label"><?php echo esc_html($tab_data['label']); ?></strong>
                                    <?php if (!empty($tab_data['meta'])) : ?><small class="ucp-tab__meta"><?php echo esc_html($tab_data['meta']); ?></small><?php endif; ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                    <div class="ucp-sidebar-footer"><?php
                        printf(
                            esc_html(
                                /* translators: %s: plugin version number. */
                                __('versie %s', 'ultracache-pro')
                            ),
                            defined('UCP_VERSION') ? esc_html(UCP_VERSION) : ''
                        );
                    ?></div>
                </aside>
                <main class="ucp-workspace__main">
                    <header class="ucp-hero ucp-hero--workspace<?php echo 'overview' === $tab ? ' is-overview' : ''; ?>">
                        <span class="ucp-hero__icon" aria-hidden="true"><span class="dashicons <?php echo esc_attr(isset($tab_meta['icon']) ? $tab_meta['icon'] : 'dashicons-performance'); ?>"></span></span>
                        <div class="ucp-hero__content"><h1><?php echo esc_html($tab_meta['title']); ?></h1><p><?php echo esc_html($tab_meta['description']); ?></p></div>
                        <?php if (!empty($actions)) : ?><div class="ucp-hero__actions"><?php foreach ($actions as $action) : ?><a class="<?php echo esc_attr($action['class']); ?>" href="<?php echo esc_url($action['url']); ?>"><?php echo esc_html($action['label']); ?></a><?php endforeach; ?></div><?php endif; ?>
                    </header>
<?php
