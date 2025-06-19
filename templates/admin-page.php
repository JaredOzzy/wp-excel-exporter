<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get data
$templates = WEE_Templates::get_templates();
$available_columns = WEE_Templates::get_available_columns();
$custom_meta_keys = WEE_Templates::get_order_meta_keys(100);
?>

<div class="wrap wee-wrap">
    <div class="wee-header">
        <h1><?php _e('Export Orders', 'wordpress-excel-export'); ?></h1>
        <p><?php _e('Select a template and date range to export WooCommerce order data to CSV.', 'wordpress-excel-export'); ?></p>
        <div class="wee-header-actions">
            <a href="<?php echo admin_url('admin.php?page=wee-templates'); ?>" class="button button-secondary"><?php _e('Manage Templates', 'wordpress-excel-export'); ?></a>
        </div>
    </div>

    <div class="wee-container">
        <!-- Quick Template Selection -->
        <div class="wee-section">
            <h2><?php _e('Available Templates', 'wordpress-excel-export'); ?></h2>
            <p><?php _e('Choose from your saved export templates', 'wordpress-excel-export'); ?></p>
            

            <div class="wee-templates-grid">
                <?php if (!empty($templates)) : ?>
                    <?php foreach ($templates as $template) : ?>
                        <div class="wee-template-card">
                            <div class="wee-template-header">
                                <h3 class="wee-template-name"><?php echo esc_html($template['name']); ?></h3>
                                <div class="wee-template-actions">
                                    <button class="button button-primary wee-use-template-btn" data-template-id="<?php echo esc_attr($template['id']); ?>"><?php _e('Select Template', 'wordpress-excel-export'); ?></button>
                                    <a href="<?php echo admin_url('admin.php?page=wee-templates'); ?>" class="button"><?php _e('Manage', 'wordpress-excel-export'); ?></a>
                                </div>
                            </div>
                            <div class="wee-template-info">
                                <p><?php printf(__('Created on %s', 'wordpress-excel-export'), date_i18n(get_option('date_format'), strtotime($template['created_at']))); ?></p>
                                <?php if (!empty($template['filters'])) : 
                                    $filters = is_array($template['filters']) ? $template['filters'] : json_decode($template['filters'], true);
                                    $filter_summary = array();
                                    
                                    if (!empty($filters['product_search'])) {
                                        $filter_summary[] = __('Product Search', 'wordpress-excel-export');
                                    }
                                    if (!empty($filters['product_categories'])) {
                                        $filter_summary[] = __('Categories', 'wordpress-excel-export');
                                    }
                                    if (!empty($filters['order_status'])) {
                                        $filter_summary[] = __('Order Status', 'wordpress-excel-export');
                                    }
                                    if (!empty($filters['payment_method'])) {
                                        $filter_summary[] = __('Payment Method', 'wordpress-excel-export');
                                    }
                                    if (!empty($filters['order_total_min']) || !empty($filters['order_total_max'])) {
                                        $filter_summary[] = __('Order Total', 'wordpress-excel-export');
                                    }
                                    if (!empty($filters['custom_meta_key'])) {
                                        $filter_summary[] = __('Custom Meta', 'wordpress-excel-export');
                                    }
                                    
                                    if (!empty($filter_summary)) : ?>
                                        <p class="wee-filter-summary"><strong><?php _e('Filters:', 'wordpress-excel-export'); ?></strong> <?php echo esc_html(implode(', ', $filter_summary)); ?></p>
                                    <?php endif;
                                endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="wee-no-templates">
                        <h3><?php _e('No Templates Found', 'wordpress-excel-export'); ?></h3>
                        <p><?php _e('You need to create export templates before you can export orders.', 'wordpress-excel-export'); ?></p>
                        <a href="<?php echo admin_url('admin.php?page=wee-templates'); ?>" class="button button-primary"><?php _e('Create Your First Template', 'wordpress-excel-export'); ?></a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Export Section -->
        <div class="wee-section">
            <form id="wee-export-form" method="POST">
                <input type="hidden" name="action" value="wee_export_data">
                <?php wp_nonce_field('wee_nonce', 'nonce'); ?>
                <div class="wee-export-container">
                    <div class="wee-export-card">
                        <div class="wee-card-header">
                            <h3><?php _e('Quick Export', 'wordpress-excel-export'); ?></h3>
                        </div>
                        <div class="wee-form-grid">
                            <div class="wee-form-group">
                                <label for="export-template"><?php _e('Template', 'wordpress-excel-export'); ?></label>
                                <select id="export-template" name="template_id" required>
                                    <option value=""><?php _e('-- Select Template --', 'wordpress-excel-export'); ?></option>
                                    <?php foreach ($templates as $template) : ?>
                                        <option value="<?php echo esc_attr($template['id']); ?>"><?php echo esc_html($template['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="wee-form-group wee-full-width">
                                <label><?php _e('Date Range', 'wordpress-excel-export'); ?></label>
                                <div class="wee-date-group">
                                    <input type="date" name="date_from">
                                    <span>&mdash;</span>
                                    <input type="date" name="date_to">
                                </div>
                            </div>
                        </div>

                        <div class="wee-export-actions">
                            <input type="hidden" name="export_format" value="csv">
                            <button type="submit" class="wee-export-btn button button-primary">
                                <?php _e('Export to CSV', 'wordpress-excel-export'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>