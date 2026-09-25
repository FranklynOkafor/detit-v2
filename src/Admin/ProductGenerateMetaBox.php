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
                data-product-id="<?php echo esc_attr((string) $post->ID); ?>">
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
            hidden>
            <div
                class="detit-modal__backdrop"
                data-detit-close="true"></div>

            <div
                class="detit-modal__dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="detit-generator-title">
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
                                    ?>">
                        &times;
                    </button>

                </div>

                <div id="detit-generation-controls">
                    <div class="detit-generation-feedback">

                        <div
                            id="detit-generation-error"
                            class="detit-message detit-message--error"
                            role="alert"
                            aria-live="assertive"
                            hidden></div>

                        <div
                            id="detit-generation-status"
                            class="detit-message detit-message--success"
                            role="status"
                            aria-live="polite"
                            hidden></div>

                    </div>

                    <div class="detit-modal__body">

                        <fieldset class="detit-field-group">

                            <legend>
                                <?php
                                esc_html_e(
                                    'What should DetIt generate?',
                                    'detit-product-content-generator-for-woocommerce'
                                );
                                ?>
                            </legend>

                            <label>
                                <input
                                    type="checkbox"
                                    class="detit-output-field"
                                    value="title">
                                <?php
                                esc_html_e(
                                    'Product title',
                                    'detit-product-content-generator-for-woocommerce'
                                );
                                ?>
                            </label>

                            <label>
                                <input
                                    type="checkbox"
                                    class="detit-output-field"
                                    value="short_description"
                                    checked>
                                <?php
                                esc_html_e(
                                    'Short description',
                                    'detit-product-content-generator-for-woocommerce'
                                );
                                ?>
                            </label>

                            <label>
                                <input
                                    type="checkbox"
                                    class="detit-output-field"
                                    value="description"
                                    checked>
                                <?php
                                esc_html_e(
                                    'Description',
                                    'detit-product-content-generator-for-woocommerce'
                                );
                                ?>
                            </label>

                            <label>
                                <input
                                    type="checkbox"
                                    class="detit-output-field"
                                    value="meta_description"
                                    checked>
                                <?php
                                esc_html_e(
                                    'Meta description',
                                    'detit-product-content-generator-for-woocommerce'
                                );
                                ?>
                            </label>

                            <label>
                                <input
                                    type="checkbox"
                                    class="detit-output-field"
                                    value="tags"
                                    checked>
                                <?php
                                esc_html_e(
                                    'Tags',
                                    'detit-product-content-generator-for-woocommerce'
                                );
                                ?>
                            </label>

                        </fieldset>

                        <div class="detit-form-field">

                            <label for="detit-template">
                                <?php
                                esc_html_e(
                                    'Template',
                                    'detit-product-content-generator-for-woocommerce'
                                );
                                ?>
                            </label>

                            <select id="detit-template">
                                <option value="general">
                                    <?php esc_html_e(
                                        'General',
                                        'detit-product-content-generator-for-woocommerce'
                                    ); ?>
                                </option>

                                <option value="fashion">
                                    <?php esc_html_e(
                                        'Fashion',
                                        'detit-product-content-generator-for-woocommerce'
                                    ); ?>
                                </option>

                                <option value="electronics">
                                    <?php esc_html_e(
                                        'Electronics',
                                        'detit-product-content-generator-for-woocommerce'
                                    ); ?>
                                </option>
                            </select>

                        </div>

                        <div class="detit-form-field">

                            <label for="detit-language">
                                <?php
                                esc_html_e(
                                    'Language',
                                    'detit-product-content-generator-for-woocommerce'
                                );
                                ?>
                            </label>

                            <input
                                type="text"
                                id="detit-language"
                                placeholder="<?php
                                                echo esc_attr__(
                                                    'e.g. English (Nigeria)',
                                                    'detit-product-content-generator-for-woocommerce'
                                                );
                                                ?>">

                        </div>

                        <div class="detit-form-field">

                            <label for="detit-tone">
                                <?php
                                esc_html_e(
                                    'Tone',
                                    'detit-product-content-generator-for-woocommerce'
                                );
                                ?>
                            </label>

                            <input
                                type="text"
                                id="detit-tone"
                                placeholder="<?php
                                                echo esc_attr__(
                                                    'e.g. Professional',
                                                    'detit-product-content-generator-for-woocommerce'
                                                );
                                                ?>">

                        </div>

                        <div class="detit-form-field">

                            <label for="detit-instructions">
                                <?php
                                esc_html_e(
                                    'Additional instructions',
                                    'detit-product-content-generator-for-woocommerce'
                                );
                                ?>
                            </label>

                            <textarea
                                id="detit-instructions"
                                rows="4"
                                placeholder="<?php
                                                echo esc_attr__(
                                                    'Add any extra instructions for this generation.',
                                                    'detit-product-content-generator-for-woocommerce'
                                                );
                                                ?>"></textarea>

                        </div>



                    </div>

                    <div class="detit-modal__footer">

                        <button
                            type="button"
                            class="button"
                            data-detit-close="true">
                            <?php
                            esc_html_e(
                                'Cancel',
                                'detit-product-content-generator-for-woocommerce'
                            );
                            ?>
                        </button>

                        <button
                            type="button"
                            class="button button-primary"
                            id="detit-prepare-generation">
                            <?php
                            esc_html_e(
                                'Generate',
                                'detit-product-content-generator-for-woocommerce'
                            );
                            ?>
                        </button>

                    </div>

                </div>

            </div>
        </div>
<?php
    }
}
