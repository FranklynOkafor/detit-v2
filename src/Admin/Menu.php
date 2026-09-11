<?php

namespace DetIt\Admin;

if (! defined('ABSPATH')) {
    exit;
}

class Menu
{
    private const CAPABILITY = 'manage_woocommerce';

    private const MENU_SLUG = 'detit';

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'registerMenu'], 20);
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            'woocommerce',
            __('DetIt V2 Development', 'detit-product-content-generator-for-woocommerce'),
            __('DetIt', 'detit-product-content-generator-for-woocommerce'),
            self::CAPABILITY,
            self::MENU_SLUG,
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            wp_die(
                esc_html__(
                    'You do not have permission to access this page.',
                    'detit-product-content-generator-for-woocommerce'
                ),
                esc_html__(
                    'Access denied',
                    'detit-product-content-generator-for-woocommerce'
                ),
                ['response' => 403]
            );
        }
?>

        <div class="wrap">
            <h1>
                <?php
                echo esc_html__(
                    'DetIt V2 Development',
                    'detit-product-content-generator-for-woocommerce'
                );
                ?>
            </h1>

            <p>
                <?php
                echo esc_html__(
                    'The DetIt admin screen is working.',
                    'detit-product-content-generator-for-woocommerce'
                );
                ?>
            </p>
        </div>

<?php
    }
}
