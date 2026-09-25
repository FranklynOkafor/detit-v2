<?php

namespace DetIt\Admin;

use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

final class ProductGenerateMetaBox
{
    public function registerHooks(): void
    {
        add_action(
            'add_meta_boxes_product',
            [$this, 'registerMetaBox']
        );

        add_action(
            'admin_enqueue_scripts',
            [$this, 'enqueueAssets']
        );
    }

    public function registerMetaBox(WP_Post $post): void
    {
        if (! current_user_can('edit_post', $post->ID)) {
            return;
        }

        // Stage 41 targets existing products only.
        if ('auto-draft' === $post->post_status) {
            return;
        }

        add_meta_box(
            'detit_generate_product',
            __(
                'DetIt',
                'detit-product-content-generator-for-woocommerce'
            ),
            [$this, 'render'],
            'product',
            'side',
            'high'
        );
    }

    public function enqueueAssets(string $hookSuffix): void
    {
        // Existing post/product editing screen only.
        if ('post.php' !== $hookSuffix) {
            return;
        }

        $screen = get_current_screen();

        if (! $screen || 'product' !== $screen->post_type) {
            return;
        }

        $cssFile = DETIT_PATH . 'assets/css/product-generate.css';
        $jsFile  = DETIT_PATH . 'assets/js/product-generate.js';

        if (file_exists($cssFile)) {
            wp_enqueue_style(
                'detit-product-generate',
                DETIT_URL . 'assets/css/product-generate.css',
                [],
                (string) filemtime($cssFile)
            );
        }

        if (file_exists($jsFile)) {
            wp_enqueue_script(
                'detit-product-generate',
                DETIT_URL . 'assets/js/product-generate.js',
                [],
                (string) filemtime($jsFile),
                true
            );
        }
    }

    public function render(WP_Post $post): void
    {
        ?>
        <div class="detit-generate-panel">

            <p>
                <?php
                esc_html_e(
                    'Generate product content with DetIt.',
                    'detit-product-content-generator-for-woocommerce'
                );
                ?>
            </p>

            <button
                type="button"
                class="button button-primary"
                id="detit-open-generator"
                data-product-id="<?php echo esc_attr((string) $post->ID); ?>"
            >
                <?php
                esc_html_e(
                    'Generate with DetIt',
                    'detit-product-content-generator-for-woocommerce'
                );
                ?>
            </button>

        </div>

        <div
            id="detit-generator-modal"
            class="detit-modal"
            hidden
        >
            <div
                class="detit-modal__backdrop"
                data-detit-close="true"
            ></div>

            <div
                class="detit-modal__dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="detit-generator-title"
            >
                <div class="detit-modal__header">

                    <h2 id="detit-generator-title">
                        <?php
                        esc_html_e(
                            'Generate with DetIt',
                            'detit-product-content-generator-for-woocommerce'
                        );
                        ?>
                    </h2>

                    <button
                        type="button"
                        class="detit-modal__close"
                        data-detit-close="true"
                        aria-label="<?php
                        echo esc_attr__(
                            'Close',
                            'detit-product-content-generator-for-woocommerce'
                        );
                        ?>"
                    >
                        &times;
                    </button>

                </div>

                <div class="detit-modal__body">

                    <p>
                        <?php
                        esc_html_e(
                            'Generation options will be added in the next stage.',
                            'detit-product-content-generator-for-woocommerce'
                        );
                        ?>
                    </p>

                </div>
            </div>
        </div>
        <?php
    }
}