<?php

declare(strict_types=1);

namespace DetIt\Admin;

use DetIt\Catalog\BatchCatalogAuditor;
use DetIt\Catalog\ProductCatalogAuditor;
use DetIt\Storage\ContentHealthDashboardRepository;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Stage 62: native WordPress/WooCommerce Content Health dashboard.
 *
 * GET requests only read results; explicit, nonce-protected admin-post
 * actions trigger bounded scans that write to DetIt's audit index only.
 */
final class ContentHealthPage
{
    public const SLUG = 'detit-content-health';

    private const TD = 'detit-product-content-generator-for-woocommerce';

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'registerMenu'], 30);
        add_action('admin_post_detit_health_scan_batch', [$this, 'scanBatch']);
        add_action('admin_post_detit_health_scan_product', [$this, 'scanProduct']);
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            'woocommerce',
            __('DetIt Content Health', self::TD),
            __('DetIt Content Health', self::TD),
            'manage_woocommerce',
            self::SLUG,
            [$this, 'renderPage']
        );
    }

    public function scanBatch(): void
    {
        $this->requirePermission();
        check_admin_referer('detit_health_scan_batch');

        $rawCursor = isset($_POST['after_product_id'])
            ? wp_unslash($_POST['after_product_id'])
            : '0';
        $cursor = is_scalar($rawCursor)
            ? filter_var((string) $rawCursor, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 0],
            ])
            : false;

        if ($cursor === false) {
            $this->redirect(['detit_error' => 'invalid_cursor']);
        }

        $result = (new BatchCatalogAuditor())->scan(10, (int) $cursor);
        if (is_wp_error($result)) {
            $this->redirect(['detit_error' => sanitize_key($result->get_error_code())]);
        }

        $this->redirect([
            'detit_completed' => $result['completed'],
            'detit_failed' => $result['failed'],
            'detit_next' => $result['has_more'] ? $result['next_after_id'] : 0,
            'detit_more' => $result['has_more'] ? 1 : 0,
        ]);
    }

    public function scanProduct(): void
    {
        $this->requirePermission();
        $id = isset($_POST['product_id'])
            ? absint(wp_unslash($_POST['product_id']))
            : 0;

        check_admin_referer('detit_health_scan_product_' . $id);

        if ($id <= 0 || ! current_user_can('edit_post', $id)) {
            $this->redirect(['detit_error' => 'invalid_product_or_permission']);
        }

        $result = (new ProductCatalogAuditor())->audit($id);
        if (is_wp_error($result)) {
            $this->redirect(['detit_error' => sanitize_key($result->get_error_code())]);
        }

        $this->redirect(['detit_product_scanned' => $id]);
    }

    public function renderPage(): void
    {
        $this->requirePermission();

        $filter = isset($_GET['audit_filter']) && is_string($_GET['audit_filter'])
            ? sanitize_key(wp_unslash($_GET['audit_filter']))
            : 'all';
        $page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;

        $data = (new ContentHealthDashboardRepository())->browse($filter, $page);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('DetIt Content Health', self::TD); ?></h1>
            <p><?php esc_html_e(
                'A catalog completeness audit, not an SEO performance rating. Audits never change product content or use AI credits.',
                self::TD
            ); ?></p>

            <?php $this->renderNotice(); ?>

            <?php if (is_wp_error($data)) : ?>
                <div class="notice notice-error"><p><?php echo esc_html($data->get_error_message()); ?></p></div>
            <?php else : ?>
                <p>
                    <?php
                    printf(
                        esc_html__(
                            'Products: %1$d | Scanned: %2$d | Not scanned: %3$d | Below 70: %4$d',
                            self::TD
                        ),
                        (int) $data['totals']['products'],
                        (int) $data['totals']['scanned'],
                        (int) $data['totals']['unscanned'],
                        (int) $data['totals']['needs_attention']
                    );
                    ?>
                </p>

                <div style="display:flex; flex-wrap:wrap; align-items:center; gap:12px; margin:16px 0;">
                    <?php foreach ([
                        'all' => __('All products', self::TD),
                        'scanned' => __('Scanned', self::TD),
                        'unscanned' => __('Not scanned', self::TD),
                        'needs_attention' => __('Below 70', self::TD),
                    ] as $value => $label) : ?>
                        <a href="<?php echo esc_url($this->url(['audit_filter' => $value])); ?>"
                           <?php echo $data['filter'] === $value ? 'aria-current="page"' : ''; ?>>
                            <?php echo esc_html($label); ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div style="display:flex; flex-wrap:wrap; gap:12px; align-items:center; margin:16px 0;">
                    <?php $this->batchForm(0, __('Scan first 10 products', self::TD)); ?>
                    <?php
                    $more = isset($_GET['detit_more']) && absint($_GET['detit_more']) === 1;
                    $next = isset($_GET['detit_next']) ? absint($_GET['detit_next']) : 0;
                    if ($more && $next > 0) {
                        $this->batchForm($next, __('Continue: next 10', self::TD));
                    }
                    ?>
                    <span class="description"><?php esc_html_e(
                        'Each click scans at most 10 products; updates only DetIt audit records.',
                        self::TD
                    ); ?></span>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead><tr>
                        <th scope="col"><?php esc_html_e('Product', self::TD); ?></th>
                        <th scope="col"><?php esc_html_e('Score', self::TD); ?></th>
                        <th scope="col"><?php esc_html_e('Audit status', self::TD); ?></th>
                        <th scope="col"><?php esc_html_e('Issues', self::TD); ?></th>
                        <th scope="col"><?php esc_html_e('Last scan', self::TD); ?></th>
                        <th scope="col"><?php esc_html_e('Action', self::TD); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php if ($data['products'] === []) : ?>
                        <tr><td colspan="6"><?php esc_html_e('No matching accessible products.', self::TD); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($data['products'] as $product) : ?>
                            <tr>
                                <td>
                                    <?php
                                    $edit = get_edit_post_link($product['product_id'], '');
                                    $title = $product['title'] !== ''
                                        ? $product['title']
                                        : __('(Untitled product)', self::TD);
                                    ?>
                                    <?php if ($edit) : ?>
                                        <a href="<?php echo esc_url($edit); ?>"><?php echo esc_html($title); ?></a>
                                    <?php else : ?>
                                        <?php echo esc_html($title); ?>
                                    <?php endif; ?>
                                    <small>#<?php echo (int) $product['product_id']; ?></small>
                                </td>
                                <td><?php
                                    echo $product['scanned']
                                        ? esc_html((string) $product['score'] . '/100')
                                        : '&mdash;';
                                ?></td>
                                <td><?php
                                    echo esc_html(
                                        ! $product['scanned']
                                            ? __('Not scanned', self::TD)
                                            : ($product['stale']
                                                ? __('Needs rescan', self::TD)
                                                : __('Current', self::TD))
                                    );
                                ?></td>
                                <td>
                                    <?php
                                    if (! $product['scanned']) {
                                        echo '&mdash;';
                                    } elseif ($product['issues'] === []) {
                                        esc_html_e('None detected', self::TD);
                                    } else {
                                        $labels = [];
                                        foreach ($product['issues'] as $issue) {
                                            if (! is_array($issue)) {
                                                continue;
                                            }
                                            $labels[] = (string) ($issue['message'] ?? $issue['code'] ?? '');
                                        }
                                        $labels = array_values(array_filter(
                                            $labels,
                                            static fn (string $label): bool => $label !== ''
                                        ));

                                        if ($labels === []) {
                                            echo '&mdash;';
                                        } else {
                                            echo '<ul style="margin:0; padding-left:18px; list-style:disc;">';

                                            foreach ($labels as $label) {
                                                echo '<li style="margin-bottom:6px;">'
                                                    . esc_html($label)
                                                    . '</li>';
                                            }

                                            echo '</ul>';
                                        }
                                    }
                                    ?>
                                </td>
                                <td><?php
                                    echo $product['last_scanned_at']
                                        ? esc_html(get_date_from_gmt($product['last_scanned_at'], 'Y-m-d H:i'))
                                        : '&mdash;';
                                ?></td>
                                <td><?php $this->productForm($product['product_id']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>

                <p>
                    <?php
                    printf(
                        esc_html__('Page %1$d of %2$d · %3$d matching products', self::TD),
                        (int) $data['page'],
                        (int) $data['pages'],
                        (int) $data['matched']
                    );
                    ?>
                    <?php if ($data['page'] > 1) : ?>
                        <a href="<?php echo esc_url($this->url([
                            'audit_filter' => $data['filter'],
                            'paged' => $data['page'] - 1,
                        ])); ?>"><?php esc_html_e('Previous', self::TD); ?></a>
                    <?php endif; ?>
                    <?php if ($data['page'] < $data['pages']) : ?>
                        <a href="<?php echo esc_url($this->url([
                            'audit_filter' => $data['filter'],
                            'paged' => $data['page'] + 1,
                        ])); ?>"><?php esc_html_e('Next', self::TD); ?></a>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function batchForm(int $cursor, string $label): void
    {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="detit_health_scan_batch">
            <input type="hidden" name="after_product_id" value="<?php echo (int) $cursor; ?>">
            <?php wp_nonce_field('detit_health_scan_batch'); ?>
            <button type="submit" class="button button-primary"><?php echo esc_html($label); ?></button>
        </form>
        <?php
    }

    private function productForm(int $productId): void
    {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="detit_health_scan_product">
            <input type="hidden" name="product_id" value="<?php echo (int) $productId; ?>">
            <?php wp_nonce_field('detit_health_scan_product_' . $productId); ?>
            <button type="submit" class="button"><?php esc_html_e('Rescan', self::TD); ?></button>
        </form>
        <?php
    }

    private function renderNotice(): void
    {
        if (isset($_GET['detit_error'])) {
            echo '<div class="notice notice-error"><p>';
            esc_html_e('Audit failed. Check permissions and your database connection.', self::TD);
            echo '</p></div>';
            return;
        }

        if (isset($_GET['detit_product_scanned'])) {
            echo '<div class="notice notice-success"><p>';
            esc_html_e('Product audit saved.', self::TD);
            echo '</p></div>';
            return;
        }

        if (isset($_GET['detit_completed'])) {
            $completed = absint($_GET['detit_completed']);
            $failed = isset($_GET['detit_failed']) ? absint($_GET['detit_failed']) : 0;
            echo '<div class="notice notice-info"><p>';
            printf(
                esc_html__('Batch complete: %1$d audited, %2$d failed.', self::TD),
                $completed,
                $failed
            );
            echo '</p></div>';
        }
    }

    private function requirePermission(): void
    {
        if (! is_user_logged_in() || ! current_user_can('manage_woocommerce')) {
            wp_die(
                esc_html__('You cannot manage DetIt catalog audits.', self::TD),
                esc_html__('Access denied', self::TD),
                ['response' => 403]
            );
        }
    }

    private function url(array $args = []): string
    {
        return add_query_arg(
            array_merge(['page' => self::SLUG], $args),
            admin_url('admin.php')
        );
    }

    private function redirect(array $args): void
    {
        wp_safe_redirect($this->url($args));
        exit;
    }
}
