<?php
/**
 * Templates Management Page
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get available columns and templates
$available_columns = WEE_Templates::get_available_columns();
$templates = WEE_Templates::get_templates();

// Debug: Show discovered custom fields (temporary)
if (current_user_can('manage_options') && isset($_GET['debug_fields'])) {
    global $wpdb;
    
    echo '<div class="notice notice-info" style="padding: 20px; margin: 20px 0; color: #333; background: #fff; border-left: 4px solid #0073aa;">';
    echo '<h3 style="color: #0073aa;">🔍 Debug: Custom Fields Analysis</h3>';
    
    // Get custom fields
    $custom_fields = WEE_Templates::get_custom_meta_columns(500);
    echo '<h4 style="color: #333;">📊 Discovered Custom Fields (' . count($custom_fields) . ' total)</h4>';
    
    if (!empty($custom_fields)) {
        echo '<div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9; color: #333;">';
        echo '<ul style="margin: 0;">';
        $count = 0;
        $ywapo_count = 0;
        foreach ($custom_fields as $key => $label) {
            if ($count >= 30) { // Show more fields to see YWAPO ones
                echo '<li><em>... and ' . (count($custom_fields) - 30) . ' more fields</em></li>';
                break;
            }
            $meta_key = str_replace('meta_', '', $key);
            $is_ywapo = strpos($meta_key, 'ywapo-addon-') === 0;
            if ($is_ywapo) {
                $ywapo_count++;
                echo '<li style="background: #e7f3ff; padding: 2px 4px; margin: 1px 0;"><strong style="color: #0073aa;">🎯 ' . esc_html($meta_key) . '</strong>: ' . esc_html($label) . '</li>';
            } else {
                echo '<li><strong>' . esc_html($meta_key) . '</strong>: ' . esc_html($label) . '</li>';
            }
            $count++;
        }
        echo '</ul>';
        if ($ywapo_count > 0) {
            echo '<p style="margin-top: 10px; color: #0073aa;"><strong>🎯 Found ' . $ywapo_count . ' YWAPO fields (highlighted in blue)</strong></p>';
        } else {
            echo '<p style="margin-top: 10px; color: #d63638;"><strong>❌ No YWAPO fields found in custom columns list</strong></p>';
        }
        echo '</div>';
    } else {
        echo '<p style="color: #d63638;"><strong>❌ No custom fields found!</strong></p>';
    }
    
    // Check YWAPO integration
    echo '<h4 style="color: #333;">🎯 YWAPO Integration Status</h4>';
    $yith_table = $wpdb->prefix . 'yith_wapo_addons';
    echo '<p><strong>Looking for table:</strong> ' . esc_html($yith_table) . '</p>';
    echo '<p><strong>WordPress prefix:</strong> ' . esc_html($wpdb->prefix) . '</p>';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$yith_table'") == $yith_table;
    
    if ($table_exists) {
        $addon_count = $wpdb->get_var("SELECT COUNT(*) FROM {$yith_table}");
        echo '<p style="color: #00a32a;">✅ YITH WAPO table found with <strong>' . $addon_count . '</strong> addons</p>';
        
        // Show a few sample addons
        $sample_addons = $wpdb->get_results("SELECT id, label, type FROM {$yith_table} ORDER BY id LIMIT 5");
        if (!empty($sample_addons)) {
            echo '<div style="border: 1px solid #ddd; padding: 10px; background: #f9f9f9; color: #333;">';
            echo '<strong>Sample addons:</strong><br>';
            foreach ($sample_addons as $addon) {
                echo '• ' . esc_html($addon->label) . ' (ID: ' . $addon->id . ')<br>';
            }
            if ($addon_count > 5) {
                echo '<em>... and ' . ($addon_count - 5) . ' more</em>';
            }
            echo '</div>';
        }
        
        // Let's check how YWAPO actually stores order data
        echo '<h5 style="color: #333;">🔍 YWAPO Order Data Investigation</h5>';
        
        // Check for YWAPO-related meta keys in actual orders
        $order_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_order' ORDER BY ID DESC LIMIT 50");
        if (!empty($order_ids)) {
            $placeholders = implode(',', array_fill(0, count($order_ids), '%d'));
            
            // Search for any YWAPO-related meta keys
            $ywapo_meta = $wpdb->get_results($wpdb->prepare(
                "SELECT DISTINCT meta_key, COUNT(*) as count 
                FROM {$wpdb->postmeta} 
                WHERE post_id IN ($placeholders) 
                AND (meta_key LIKE '%ywapo%' OR meta_key LIKE '%yith%' OR meta_key LIKE '%wapo%' OR meta_key LIKE '%addon%')
                GROUP BY meta_key 
                ORDER BY count DESC, meta_key",
                ...$order_ids
            ));
            
            if (!empty($ywapo_meta)) {
                echo '<div style="border: 1px solid #00a32a; padding: 10px; background: #f0f8f0; color: #333;">';
                echo '<strong>✅ Found YWAPO-related meta keys in orders:</strong><br>';
                foreach ($ywapo_meta as $meta) {
                    echo '• <strong>' . esc_html($meta->meta_key) . '</strong> (' . $meta->count . ' orders)<br>';
                }
                echo '</div>';
            } else {
                echo '<div style="border: 1px solid #ff9800; padding: 10px; background: #fff8e1; color: #333;">';
                echo '<strong>⚠️ No YWAPO meta keys found in recent orders</strong><br>';
                echo 'This suggests either:<br>';
                echo '• No orders have YWAPO add-ons selected<br>';
                echo '• YWAPO stores data differently (maybe in order items or separate tables)<br>';
                echo '</div>';
                
                // Check WooCommerce order items for YWAPO data
                echo '<h6>Checking WooCommerce Order Items...</h6>';
                $item_meta = $wpdb->get_results($wpdb->prepare(
                    "SELECT DISTINCT meta_key, COUNT(*) as count 
                    FROM {$wpdb->prefix}woocommerce_order_itemmeta oim
                    JOIN {$wpdb->prefix}woocommerce_order_items oi ON oim.order_item_id = oi.order_item_id
                    WHERE oi.order_id IN ($placeholders)
                    AND (meta_key LIKE '%ywapo%' OR meta_key LIKE '%yith%' OR meta_key LIKE '%wapo%' OR meta_key LIKE '%addon%')
                    GROUP BY meta_key 
                    ORDER BY count DESC, meta_key",
                    ...$order_ids
                ));
                
                if (!empty($item_meta)) {
                    echo '<div style="border: 1px solid #00a32a; padding: 10px; background: #f0f8f0; color: #333;">';
                    echo '<strong>✅ Found YWAPO data in order items:</strong><br>';
                    foreach ($item_meta as $meta) {
                        echo '• <strong>' . esc_html($meta->meta_key) . '</strong> (' . $meta->count . ' items)<br>';
                    }
                    echo '</div>';
                } else {
                    echo '<div style="border: 1px solid #d63638; padding: 10px; background: #fff0f0; color: #333;">';
                    echo '<strong>❌ No YWAPO data found in order items either</strong><br>';
                    echo 'YWAPO might use a completely different storage method.';
                    echo '</div>';
                }
            }
        }
    } else {
        echo '<p style="color: #d63638;">❌ YITH WAPO table not found</p>';
    }

    
    echo '</div>'; // Close debug div
}
?>

<div class="wrap wee-wrap">
    <div class="wee-header">
        <h1><?php _e('Manage Export Templates', 'wordpress-excel-export'); ?></h1>
        <p><?php _e('Create and manage reusable export templates with custom columns and filters.', 'wordpress-excel-export'); ?></p>
    </div>

    <!-- Template Creation Form -->
    <div class="wee-section">
        <div class="wee-card">
            <div class="wee-card-header">
                <h2><?php _e('Create New Template', 'wordpress-excel-export'); ?></h2>
                <p class="wee-card-subtitle"><?php _e('Define columns and filters for reusable export configurations', 'wordpress-excel-export'); ?></p>
            </div>
            
            <form id="wee-create-template-form" method="post">
                <?php wp_nonce_field('wee_nonce', 'nonce'); ?>
                
                <div class="wee-form-content">
                    <div class="wee-form-grid">
                        <div class="wee-form-group wee-full-width">
                            <label for="template-name"><?php _e('Template Name', 'wordpress-excel-export'); ?></label>
                            <input type="text" id="template-name" name="template_name" required placeholder="<?php _e('Enter template name...', 'wordpress-excel-export'); ?>">
                        </div>
                        
                        <div class="wee-form-group wee-full-width">
                            <label for="template-description"><?php _e('Description (Optional)', 'wordpress-excel-export'); ?></label>
                            <textarea id="template-description" name="template_description" rows="2" placeholder="<?php _e('Brief description of this template...', 'wordpress-excel-export'); ?>"></textarea>
                        </div>
                    </div>

                    <!-- Column Selection -->
                    <div class="wee-form-group wee-full-width">
                        <label><?php _e('Select Columns', 'wordpress-excel-export'); ?></label>
                        
                        <!-- Column Search -->
                        <div class="wee-column-search-container">
                            <input type="text" id="wee-column-search" placeholder="<?php _e('Search columns...', 'wordpress-excel-export'); ?>">
                            <div class="wee-search-actions">
                                <button type="button" class="button" id="wee-expand-all-sections"><?php _e('Expand All', 'wordpress-excel-export'); ?></button>
                                <button type="button" class="button" id="wee-collapse-all-sections"><?php _e('Collapse All', 'wordpress-excel-export'); ?></button>
                            </div>
                        </div>
                        
                        <div class="wee-columns-container">
                            <?php foreach ($available_columns as $group_key => $group) : ?>
                                <div class="wee-column-section" data-section="<?php echo esc_attr($group_key); ?>">
                                    <div class="wee-column-section-header">
                                        <button type="button" class="wee-section-toggle" data-section="<?php echo esc_attr($group_key); ?>">
                                            <span class="wee-toggle-icon">+</span>
                                            <span class="wee-section-title"><?php echo esc_html($group['label']); ?></span>
                                            <span class="wee-section-count">(<?php echo count($group['columns']); ?> columns)</span>
                                        </button>
                                    </div>
                                    <div class="wee-column-section-content wee-collapsed" data-section="<?php echo esc_attr($group_key); ?>">
                                        <div class="wee-column-actions">
                                            <button type="button" class="wee-select-all" data-section="<?php echo esc_attr($group_key); ?>"><?php _e('Select All', 'wordpress-excel-export'); ?></button>
                                            <button type="button" class="wee-deselect-all" data-section="<?php echo esc_attr($group_key); ?>"><?php _e('Deselect All', 'wordpress-excel-export'); ?></button>
                                        </div>
                                        <div class="wee-column-grid">
                                            <?php foreach ($group['columns'] as $column_key => $column_label) : ?>
                                                <div class="wee-column-item" data-column-name="<?php echo esc_attr(strtolower($column_label)); ?>" data-column-key="<?php echo esc_attr($column_key); ?>">
                                                    <label class="wee-checkbox">
                                                        <input type="checkbox" name="columns[]" value="<?php echo esc_attr($column_key); ?>">
                                                        <span><?php echo esc_html($column_label); ?></span>
                                                    </label>
                                                    <div class="wee-column-name-input">
                                                        <input type="text" 
                                                               name="column_names[<?php echo esc_attr($column_key); ?>]" 
                                                               placeholder="<?php _e('Custom column name...', 'wordpress-excel-export'); ?>"
                                                               class="wee-custom-column-name"
                                                               data-default="<?php echo esc_attr($column_label); ?>"
                                                               disabled>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Selected Columns Order Section -->
                    <div class="wee-form-group wee-full-width wee-selected-columns-section">
                        <label><?php _e('Column Order', 'wordpress-excel-export'); ?></label>
                        <p class="description"><?php _e('Drag and drop to reorder your selected columns. The order here will be the order in your export.', 'wordpress-excel-export'); ?></p>
                        <ul id="wee-selected-columns-list" class="wee-sortable-list">
                            <!-- Selected columns will be populated here by JavaScript -->
                        </ul>
                        <input type="hidden" id="wee-column-order" name="column_order" value="">
                    </div>
                    
                    <!-- Template Filters Toggle -->
                    <div class="wee-form-group wee-full-width">
                        <div class="wee-template-filters-toggle">
                            <button type="button" id="wee-toggle-template-filters" class="button button-secondary wee-collapsed">
                                <span class="wee-toggle-icon">+</span>
                                <span><?php _e('Add Template Filters (Optional)', 'wordpress-excel-export'); ?></span>
                            </button>
                        </div>
                    </div>
                    
                    <div class="wee-template-filters wee-collapsed" id="wee-template-filters-content">
                        <div class="wee-template-filters-header">
                            <h4><?php _e('Template Filters', 'wordpress-excel-export'); ?></h4>
                            <p class="description"><?php _e('Configure default filters for this template. These will be applied automatically when using this template.', 'wordpress-excel-export'); ?></p>
                        </div>
                        <div class="wee-form-grid">
                            <!-- Product Filters -->
                            <div class="wee-form-group wee-full-width">
                                <h5><?php _e('Product Filters', 'wordpress-excel-export'); ?></h5>
                            </div>
                            
                            <div class="wee-form-group">
                                <label for="template-product-search"><?php _e('Product Search', 'wordpress-excel-export'); ?></label>
                                <div class="wee-product-search-container">
                                    <input type="text" id="template-product-search" name="template_filters[product_search]" placeholder="<?php _e('Search by product name, SKU, or ID...', 'wordpress-excel-export'); ?>">
                                    <div class="wee-product-dropdown wee-hidden" id="template-product-dropdown"></div>
                                </div>
                            </div>
                            
                            <div class="wee-form-group">
                                <label for="template-product-categories"><?php _e('Product Categories', 'wordpress-excel-export'); ?></label>
                                <select id="template-product-categories" name="template_filters[product_categories][]" multiple>
                                    <?php 
                                    $product_categories = get_terms(array(
                                        'taxonomy' => 'product_cat',
                                        'hide_empty' => false,
                                    ));
                                    if (!is_wp_error($product_categories)) :
                                        foreach ($product_categories as $category) : ?>
                                            <option value="<?php echo esc_attr($category->term_id); ?>"><?php echo esc_html($category->name); ?></option>
                                        <?php endforeach;
                                    endif; ?>
                                </select>
                            </div>
                            
                            <!-- Order Filters -->
                            <div class="wee-form-group wee-full-width">
                                <h5><?php _e('Order Filters', 'wordpress-excel-export'); ?></h5>
                            </div>
                            
                            <div class="wee-form-group">
                                <label for="template-order-status"><?php _e('Order Status', 'wordpress-excel-export'); ?></label>
                                <select id="template-order-status" name="template_filters[order_status][]" multiple>
                                    <?php
                                    $order_statuses = wc_get_order_statuses();
                                    foreach ($order_statuses as $status_key => $status_name) : ?>
                                        <option value="<?php echo esc_attr($status_key); ?>"><?php echo esc_html($status_name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="wee-form-group">
                                <label for="template-payment-method"><?php _e('Payment Method', 'wordpress-excel-export'); ?></label>
                                <select id="template-payment-method" name="template_filters[payment_method]">
                                    <option value=""><?php _e('All Payment Methods', 'wordpress-excel-export'); ?></option>
                                    <?php
                                    $payment_gateways = WC()->payment_gateways->payment_gateways();
                                    foreach ($payment_gateways as $gateway) :
                                        if ($gateway->enabled === 'yes') : ?>
                                            <option value="<?php echo esc_attr($gateway->id); ?>"><?php echo esc_html($gateway->get_title()); ?></option>
                                        <?php endif;
                                    endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="wee-form-group">
                                <label for="template-order-total-min"><?php _e('Order Total (Min)', 'wordpress-excel-export'); ?></label>
                                <input type="number" id="template-order-total-min" name="template_filters[order_total_min]" step="0.01" min="0">
                            </div>
                            
                            <div class="wee-form-group">
                                <label for="template-order-total-max"><?php _e('Order Total (Max)', 'wordpress-excel-export'); ?></label>
                                <input type="number" id="template-order-total-max" name="template_filters[order_total_max]" step="0.01" min="0">
                            </div>
                            
                            <!-- Custom Meta Filters -->
                            <div class="wee-form-group wee-full-width">
                                <h5><?php _e('Custom Meta Field Filter', 'wordpress-excel-export'); ?></h5>
                            </div>
                            
                            <div class="wee-form-group">
                                <label for="template-custom-meta-key"><?php _e('Meta Key', 'wordpress-excel-export'); ?></label>
                                <input type="text" id="template-custom-meta-key" name="template_filters[custom_meta_key]" placeholder="<?php _e('Enter meta key...', 'wordpress-excel-export'); ?>">
                            </div>
                            
                            <div class="wee-form-group">
                                <label for="template-custom-meta-operator"><?php _e('Operator', 'wordpress-excel-export'); ?></label>
                                <select id="template-custom-meta-operator" name="template_filters[custom_meta_operator]">
                                    <option value="="><?php _e('Equals', 'wordpress-excel-export'); ?></option>
                                    <option value="!="><?php _e('Not Equals', 'wordpress-excel-export'); ?></option>
                                    <option value="LIKE"><?php _e('Contains', 'wordpress-excel-export'); ?></option>
                                    <option value="NOT LIKE"><?php _e('Does Not Contain', 'wordpress-excel-export'); ?></option>
                                    <option value=">"><?php _e('Greater Than', 'wordpress-excel-export'); ?></option>
                                    <option value=">="><?php _e('Greater Than or Equal', 'wordpress-excel-export'); ?></option>
                                    <option value="<"><?php _e('Less Than', 'wordpress-excel-export'); ?></option>
                                    <option value="<="><?php _e('Less Than or Equal', 'wordpress-excel-export'); ?></option>
                                </select>
                            </div>
                            
                            <div class="wee-form-group">
                                <label for="template-custom-meta-value"><?php _e('Meta Value', 'wordpress-excel-export'); ?></label>
                                <input type="text" id="template-custom-meta-value" name="template_filters[custom_meta_value]" placeholder="<?php _e('Enter meta value...', 'wordpress-excel-export'); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="wee-form-actions">
                    <div class="wee-columns-selected">
                        <span id="selected-count">0</span> <?php _e('columns selected', 'wordpress-excel-export'); ?>
                    </div>
                    <button type="submit" class="button button-primary wee-save-template-btn"><?php _e('Save Template', 'wordpress-excel-export'); ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Existing Templates -->
    <div class="wee-section">
        <div class="wee-card">
            <div class="wee-card-header">
                <h2><?php _e('Saved Templates', 'wordpress-excel-export'); ?></h2>
                <p class="wee-card-subtitle"><?php _e('Manage your existing export templates', 'wordpress-excel-export'); ?></p>
            </div>
            
            <div class="wee-templates-grid">
                <?php if (!empty($templates)) : ?>
                    <?php foreach ($templates as $template) : ?>
                        <div class="wee-template-card">
                            <div class="wee-template-header">
                                <h3 class="wee-template-name"><?php echo esc_html($template['name']); ?></h3>
                                <div class="wee-template-actions">
                                    <a href="<?php echo admin_url('admin.php?page=wordpress-excel-export&template=' . $template['id']); ?>" class="button button-primary"><?php _e('Use for Export', 'wordpress-excel-export'); ?></a>
                                    <button class="button wee-edit-template-btn" data-template-id="<?php echo esc_attr($template['id']); ?>"><?php _e('Edit', 'wordpress-excel-export'); ?></button>
                                    <button class="button button-danger wee-delete-template-btn" data-template-id="<?php echo esc_attr($template['id']); ?>" data-template-name="<?php echo esc_attr($template['name']); ?>"><?php _e('Delete', 'wordpress-excel-export'); ?></button>
                                </div>
                            </div>
                            <div class="wee-template-info">
                                <p><?php printf(__('Created on %s', 'wordpress-excel-export'), date_i18n(get_option('date_format'), strtotime($template['created_at']))); ?></p>
                                <p><?php printf(__('%d columns configured', 'wordpress-excel-export'), count($template['columns'])); ?></p>
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
                        <h3><?php _e('No Templates Created Yet', 'wordpress-excel-export'); ?></h3>
                        <p><?php _e('Create your first template above to get started with streamlined order exports.', 'wordpress-excel-export'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Update selected columns counter
    function updateSelectedCount() {
        const count = $('.wee-column-grid input[type="checkbox"]:checked').length;
        $('#selected-count').text(count);
    }
    
    // Make this function available globally so admin.js can hook into it
    window.updateSelectedCount = updateSelectedCount;

    // Initialize counter
    updateSelectedCount();

    // Initialize collapsible sections and search
    initColumnSectionsAndSearch();

    // Update counter on checkbox change
    $('.wee-column-grid').on('change', 'input[type="checkbox"]', function() {
        updateSelectedCount();
    });

    // Select/Deselect all functionality
    $('.wee-select-all').on('click', function() {
        const section = $(this).data('section');
        const $section = $('.wee-column-section[data-section="' + section + '"]');
        $section.find('.wee-column-item:not(.wee-search-hidden) input[type="checkbox"]').prop('checked', true);
        updateSelectedCount();
    });

    $('.wee-deselect-all').on('click', function() {
        const section = $(this).data('section');
        const $section = $('.wee-column-section[data-section="' + section + '"]');
        $section.find('.wee-column-item:not(.wee-search-hidden) input[type="checkbox"]').prop('checked', false);
        updateSelectedCount();
    });

    // Initialize collapsible sections and search functionality
    function initColumnSectionsAndSearch() {
        // Section toggle functionality
        $('.wee-section-toggle').on('click', function() {
            const $toggle = $(this);
            const $content = $toggle.closest('.wee-column-section').find('.wee-column-section-content');
            const isCollapsed = $content.hasClass('wee-collapsed');
            
            if (isCollapsed) {
                $content.removeClass('wee-collapsed');
                $toggle.addClass('wee-expanded');
                $toggle.find('.wee-toggle-icon').text('−');
            } else {
                $content.addClass('wee-collapsed');
                $toggle.removeClass('wee-expanded');
                $toggle.find('.wee-toggle-icon').text('+');
            }
        });

        // Expand all sections
        $('#wee-expand-all-sections').on('click', function() {
            $('.wee-column-section-content').removeClass('wee-collapsed');
            $('.wee-section-toggle').addClass('wee-expanded');
            $('.wee-section-toggle .wee-toggle-icon').text('−');
        });

        // Collapse all sections
        $('#wee-collapse-all-sections').on('click', function() {
            $('.wee-column-section-content').addClass('wee-collapsed');
            $('.wee-section-toggle').removeClass('wee-expanded');
            $('.wee-section-toggle .wee-toggle-icon').text('+');
        });

        // Column search functionality
        let searchTimeout;
        $('#wee-column-search').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                performColumnSearch(searchTerm);
            }, 300);
        });

        function performColumnSearch(searchTerm) {
            $('.wee-column-item').each(function() {
                const $columnItem = $(this);
                const columnName = $columnItem.data('column-name') || '';
                const columnKey = $columnItem.data('column-key') || '';
                const labelText = $columnItem.find('.wee-checkbox span').text().toLowerCase();
                
                const matches = searchTerm === '' || 
                               columnName.includes(searchTerm) || 
                               columnKey.includes(searchTerm) || 
                               labelText.includes(searchTerm);
                
                if (matches) {
                    $columnItem.removeClass('wee-search-hidden');
                    if (searchTerm !== '') {
                        $columnItem.addClass('wee-search-match');
                    } else {
                        $columnItem.removeClass('wee-search-match');
                    }
                } else {
                    $columnItem.addClass('wee-search-hidden');
                    $columnItem.removeClass('wee-search-match');
                }
            });

            // Auto-expand sections that have matches when searching
            if (searchTerm !== '') {
                $('.wee-column-section').each(function() {
                    const $section = $(this);
                    const hasVisibleItems = $section.find('.wee-column-item:not(.wee-search-hidden)').length > 0;
                    
                    if (hasVisibleItems) {
                        const $content = $section.find('.wee-column-section-content');
                        const $toggle = $section.find('.wee-section-toggle');
                        
                        $content.removeClass('wee-collapsed');
                        $toggle.addClass('wee-expanded');
                        $toggle.find('.wee-toggle-icon').text('−');
                    }
                });
            }

            // Update section counts to show visible/total
            updateSectionCounts(searchTerm);
        }

        function updateSectionCounts(searchTerm) {
            $('.wee-column-section').each(function() {
                const $section = $(this);
                const $countSpan = $section.find('.wee-section-count');
                const totalColumns = $section.find('.wee-column-item').length;
                const visibleColumns = $section.find('.wee-column-item:not(.wee-search-hidden)').length;
                
                if (searchTerm !== '' && visibleColumns !== totalColumns) {
                    $countSpan.text(`(${visibleColumns}/${totalColumns} columns)`);
                    if (visibleColumns === 0) {
                        $countSpan.css('color', '#dc3232');
                    } else {
                        $countSpan.css('color', '#00a32a');
                    }
                } else {
                    $countSpan.text(`(${totalColumns} columns)`);
                    $countSpan.css('color', '#646970');
                }
            });
        }
    }
});
</script> 