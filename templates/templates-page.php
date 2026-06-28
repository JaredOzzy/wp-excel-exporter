<?php
/**
 * Templates Management Page
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Clear cache if requested
if (isset($_GET['clear_cache'])) {
    wp_cache_delete('wee_custom_meta_columns_500', 'wee_templates');
    wp_cache_delete('wee_custom_meta_columns_1000', 'wee_templates');
    wp_cache_delete('wee_custom_meta_columns_100', 'wee_templates');
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
        <div class="wee-header-content">
            <h1><?php _e('Export Templates', 'wordpress-excel-export'); ?></h1>
            <p class="wee-header-description"><?php _e('Create and manage reusable export templates with custom columns and filters.', 'wordpress-excel-export'); ?></p>
        </div>
        <div class="wee-header-actions">
            <a href="<?php echo admin_url('admin.php?page=wordpress-excel-export'); ?>" class="button button-secondary">
                <span class="dashicons dashicons-arrow-left-alt2"></span>
                <?php _e('Back to Export', 'wordpress-excel-export'); ?>
            </a>
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
                                    <button class="button wee-duplicate-template-btn" data-template-id="<?php echo esc_attr($template['id']); ?>" data-template-name="<?php echo esc_attr($template['name']); ?>"><?php _e('Duplicate', 'wordpress-excel-export'); ?></button>
                                    <button class="button button-danger wee-delete-template-btn" data-template-id="<?php echo esc_attr($template['id']); ?>" data-template-name="<?php echo esc_attr($template['name']); ?>"><?php _e('Delete', 'wordpress-excel-export'); ?></button>
                                </div>
                            </div>
                            <div class="wee-template-info">
                                <p><?php printf(__('Created on %s', 'wordpress-excel-export'), date_i18n(get_option('date_format'), strtotime($template['created_at']))); ?></p>
                                <p><?php printf(__('%d columns configured', 'wordpress-excel-export'), is_array($template['columns']) ? count($template['columns']) : 0); ?></p>
                                <?php if (!empty($template['filters'])) : 
                                    $filters = is_array($template['filters']) ? $template['filters'] : (is_string($template['filters']) ? json_decode($template['filters'], true) : array());
                                    if (!is_array($filters)) {
                                        $filters = array();
                                    }
                                    $filter_details = array();
                                    
                                    // Product Search - show actual product names
                                    if (!empty($filters['product_search'])) {
                                        $product_ids = array();
                                        if (is_string($filters['product_search'])) {
                                            $decoded = json_decode($filters['product_search'], true);
                                            if (is_array($decoded)) {
                                                $product_ids = $decoded;
                                            }
                                        } elseif (is_array($filters['product_search'])) {
                                            $product_ids = $filters['product_search'];
                                        }
                                        
                                        if (!empty($product_ids)) {
                                            $product_names = array();
                                            foreach ($product_ids as $product_id) {
                                                $product = wc_get_product($product_id);
                                                if ($product) {
                                                    $product_names[] = $product->get_name();
                                                }
                                            }
                                            if (!empty($product_names)) {
                                                $filter_details[] = '<strong>' . __('Products:', 'wordpress-excel-export') . '</strong> ' . esc_html(implode(', ', $product_names));
                                            }
                                        }
                                    }
                                    
                                    // Product Categories
                                    if (!empty($filters['product_categories']) && is_array($filters['product_categories'])) {
                                        $cat_names = array();
                                        foreach ($filters['product_categories'] as $cat_id) {
                                            $term = get_term($cat_id, 'product_cat');
                                            if ($term && !is_wp_error($term)) {
                                                $cat_names[] = $term->name;
                                            }
                                        }
                                        if (!empty($cat_names)) {
                                            $filter_details[] = '<strong>' . __('Categories:', 'wordpress-excel-export') . '</strong> ' . esc_html(implode(', ', $cat_names));
                                        }
                                    }
                                    
                                    // Order Status
                                    if (!empty($filters['order_status']) && is_array($filters['order_status'])) {
                                        $status_names = array();
                                        $all_statuses = wc_get_order_statuses();
                                        foreach ($filters['order_status'] as $status) {
                                            if (isset($all_statuses[$status])) {
                                                $status_names[] = $all_statuses[$status];
                                            }
                                        }
                                        if (!empty($status_names)) {
                                            $filter_details[] = '<strong>' . __('Status:', 'wordpress-excel-export') . '</strong> ' . esc_html(implode(', ', $status_names));
                                        }
                                    }
                                    
                                    // Payment Method
                                    if (!empty($filters['payment_method'])) {
                                        $gateway = WC()->payment_gateways->payment_gateways()[$filters['payment_method']] ?? null;
                                        $payment_name = $gateway ? $gateway->get_title() : $filters['payment_method'];
                                        $filter_details[] = '<strong>' . __('Payment:', 'wordpress-excel-export') . '</strong> ' . esc_html($payment_name);
                                    }
                                    
                                    // Order Total Range
                                    if (!empty($filters['order_total_min']) || !empty($filters['order_total_max'])) {
                                        $min = !empty($filters['order_total_min']) ? wc_price($filters['order_total_min']) : __('any', 'wordpress-excel-export');
                                        $max = !empty($filters['order_total_max']) ? wc_price($filters['order_total_max']) : __('any', 'wordpress-excel-export');
                                        $filter_details[] = '<strong>' . __('Total:', 'wordpress-excel-export') . '</strong> ' . $min . ' - ' . $max;
                                    }
                                    
                                    // TGF Submission Type
                                    if (!empty($filters['tgf_submission_key'])) {
                                        $submission_labels = array(
                                            'cgc_service_level' => 'CGC Cards',
                                            'cgc_comic_service' => 'CGC Comics',
                                            'psa_service_level' => 'PSA Cards',
                                            'bgs_service_level' => 'BGS Cards',
                                            'ags_service_level' => 'AGS Cards',
                                            'tag_service_level' => 'TAG Cards',
                                        );
                                        $label = $submission_labels[$filters['tgf_submission_key']] ?? $filters['tgf_submission_key'];
                                        $filter_details[] = '<strong>' . __('Grading Type:', 'wordpress-excel-export') . '</strong> ' . esc_html($label);
                                    }

                                    // TGF Grading Contains
                                    if (!empty($filters['tgf_grading_contains'])) {
                                        $filter_details[] = '<strong>' . __('Grading Contains:', 'wordpress-excel-export') . '</strong> ' . esc_html($filters['tgf_grading_contains']);
                                    }

                                    // TGF Service Level Contains
                                    if (!empty($filters['tgf_service_level_contains'])) {
                                        $filter_details[] = '<strong>' . __('Service Level:', 'wordpress-excel-export') . '</strong> ' . esc_html($filters['tgf_service_level_contains']);
                                    }

                                    // Custom Meta
                                    if (!empty($filters['custom_meta_key'])) {
                                        $meta_text = esc_html($filters['custom_meta_key']);
                                        if (!empty($filters['custom_meta_value'])) {
                                            $operator = !empty($filters['custom_meta_operator']) ? $filters['custom_meta_operator'] : '=';
                                            $meta_text .= ' ' . $operator . ' ' . esc_html($filters['custom_meta_value']);
                                        }
                                        $filter_details[] = '<strong>' . __('Meta:', 'wordpress-excel-export') . '</strong> ' . $meta_text;
                                    }
                                    
                                    if (!empty($filter_details)) : ?>
                                        <div class="wee-filter-details" style="margin-top: 10px; padding: 10px; background: #f0f6fc; border-left: 3px solid #2271b1; border-radius: 3px;">
                                            <p style="margin: 0; font-size: 13px; line-height: 1.6;">
                                                <span class="dashicons dashicons-filter" style="color: #2271b1; font-size: 14px; margin-right: 5px;"></span>
                                                <?php echo implode('<br>', $filter_details); ?>
                                            </p>
                                        </div>
                                    <?php endif;
                                endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="wee-no-templates">
                        <h3><?php _e('No Templates Created Yet', 'wordpress-excel-export'); ?></h3>
                        <p><?php _e('Create your first template below to get started with streamlined order exports.', 'wordpress-excel-export'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Template Creation Form -->
    <div class="wee-main-content">
        <div class="wee-card wee-template-form-card">
            <div class="wee-card-header">
                <div class="wee-card-header-content">
                    <h2><?php _e('Create New Template', 'wordpress-excel-export'); ?></h2>
                    <p class="wee-card-subtitle"><?php _e('Define columns and filters for reusable export configurations', 'wordpress-excel-export'); ?></p>
                </div>
                <div class="wee-card-header-icon">
                    <span class="dashicons dashicons-admin-tools"></span>
                </div>
            </div>
            
            <form id="wee-create-template-form" method="post">
                <?php wp_nonce_field('wee_nonce', 'nonce'); ?>
                
                <div class="wee-form-content">
                    <!-- Template Basic Info -->
                    <div class="wee-form-section">
                        <div class="wee-form-section-header">
                            <h3><?php _e('Template Information', 'wordpress-excel-export'); ?></h3>
                            <p class="wee-form-section-description"><?php _e('Give your template a name and description', 'wordpress-excel-export'); ?></p>
                        </div>
                        <div class="wee-form-grid">
                            <div class="wee-form-group">
                                <label for="template-name"><?php _e('Template Name', 'wordpress-excel-export'); ?> <span class="required">*</span></label>
                                <input type="text" id="template-name" name="template_name" required placeholder="<?php _e('e.g., Customer Orders Export', 'wordpress-excel-export'); ?>">
                            </div>
                            
                            <div class="wee-form-group">
                                <label for="template-description"><?php _e('Description', 'wordpress-excel-export'); ?></label>
                                <textarea id="template-description" name="template_description" rows="2" placeholder="<?php _e('Brief description of this template...', 'wordpress-excel-export'); ?>"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Order Filters Section -->
                    <div class="wee-form-section">
                        <div class="wee-form-section-header">
                            <h3><?php _e('Order Filters', 'wordpress-excel-export'); ?></h3>
                            <p class="wee-form-section-description"><?php _e('Set default filters for this template (optional - can be overridden during export)', 'wordpress-excel-export'); ?></p>
                        </div>
                        
                        <div class="wee-form-grid">
                            <!-- Order Status Filter -->
                            <div class="wee-form-group">
                                <label for="template-order-status">
                                    <span class="dashicons dashicons-admin-post" style="color: #2271b1;"></span>
                                    <?php _e('Order Status', 'wordpress-excel-export'); ?>
                                </label>
                                <select id="template-order-status" name="template_filters[order_status][]" multiple size="4" class="wee-filter-input">
                                    <?php
                                    $order_statuses = wc_get_order_statuses();
                                    foreach ($order_statuses as $status_key => $status_name) : ?>
                                        <option value="<?php echo esc_attr($status_key); ?>"><?php echo esc_html($status_name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php _e('Hold Ctrl/Cmd to select multiple statuses', 'wordpress-excel-export'); ?></p>
                            </div>
                            
                            <!-- Payment Method Filter -->
                            <div class="wee-form-group">
                                <label for="template-payment-method">
                                    <span class="dashicons dashicons-money-alt" style="color: #46b450;"></span>
                                    <?php _e('Payment Method', 'wordpress-excel-export'); ?>
                                </label>
                                <select id="template-payment-method" name="template_filters[payment_method]" class="wee-filter-input">
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
                            
                            <!-- Order Total Range -->
                            <div class="wee-form-group">
                                <label for="template-order-total-min">
                                    <span class="dashicons dashicons-chart-line" style="color: #f0b849;"></span>
                                    <?php _e('Min Order Total', 'wordpress-excel-export'); ?>
                                </label>
                                <input type="number" id="template-order-total-min" name="template_filters[order_total_min]" step="0.01" min="0" placeholder="0.00" class="wee-filter-input">
                            </div>
                            
                            <div class="wee-form-group">
                                <label for="template-order-total-max">
                                    <span class="dashicons dashicons-chart-line" style="color: #f0b849;"></span>
                                    <?php _e('Max Order Total', 'wordpress-excel-export'); ?>
                                </label>
                                <input type="number" id="template-order-total-max" name="template_filters[order_total_max]" step="0.01" min="0" placeholder="0.00" class="wee-filter-input">
                            </div>
                            
                            <!-- Product Categories Filter -->
                            <div class="wee-form-group">
                                <label for="template-product-categories">
                                    <span class="dashicons dashicons-category" style="color: #826eb4;"></span>
                                    <?php _e('Product Categories', 'wordpress-excel-export'); ?>
                                </label>
                                <select id="template-product-categories" name="template_filters[product_categories][]" multiple size="4" class="wee-filter-input">
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
                                <p class="description"><?php _e('Hold Ctrl/Cmd to select multiple categories', 'wordpress-excel-export'); ?></p>
                            </div>
                            
                            <!-- Product Search Filter -->
                            <div class="wee-form-group">
                                <label for="template-product-search">
                                    <span class="dashicons dashicons-search" style="color: #d63638;"></span>
                                    <?php _e('Product Search', 'wordpress-excel-export'); ?>
                                </label>
                                <div class="wee-product-search-container" style="position: relative;">
                                    <input type="text" id="template-product-search" placeholder="<?php _e('Type to search products...', 'wordpress-excel-export'); ?>" class="wee-filter-input" autocomplete="off">
                                    <div class="wee-product-dropdown wee-hidden"></div>
                                    <input type="hidden" id="template-product-search-value" name="template_filters[product_search]" value="">
                                </div>
                                <div id="template-selected-products" class="wee-selected-products" style="margin-top: 10px;">
                                    <!-- Selected products will appear here -->
                                </div>
                                <p class="description"><?php _e('Filter orders containing specific products. Type to search and select from the dropdown.', 'wordpress-excel-export'); ?></p>
                            </div>
                        </div>
                        
                        <!-- TGF Grading Submission Type Filter -->
                        <div class="wee-form-group">
                            <label for="template-tgf-submission-key">
                                <span class="dashicons dashicons-awards" style="color: #e65c00;"></span>
                                <?php _e('Grading Submission Type', 'wordpress-excel-export'); ?>
                            </label>
                            <select id="template-tgf-submission-key" class="wee-filter-input">
                                <option value=""><?php _e('All Submission Types', 'wordpress-excel-export'); ?></option>
                                <option value="cgc_service_level"><?php _e('CGC Cards', 'wordpress-excel-export'); ?></option>
                                <option value="cgc_comic_service"><?php _e('CGC Comics', 'wordpress-excel-export'); ?></option>
                                <option value="psa_service_level"><?php _e('PSA Cards', 'wordpress-excel-export'); ?></option>
                                <option value="bgs_service_level"><?php _e('BGS Cards', 'wordpress-excel-export'); ?></option>
                                <option value="ags_service_level"><?php _e('AGS Cards', 'wordpress-excel-export'); ?></option>
                                <option value="tag_service_level"><?php _e('TAG Cards', 'wordpress-excel-export'); ?></option>
                            </select>
                            <p class="description"><?php _e('Filter to orders of a specific grading service type.', 'wordpress-excel-export'); ?></p>
                        </div>

                        <!-- TGF Grading Options Contains Filter -->
                        <div class="wee-form-group">
                            <label for="template-tgf-grading-contains">
                                <span class="dashicons dashicons-filter" style="color: #e65c00;"></span>
                                <?php _e('Grading Options Contains', 'wordpress-excel-export'); ?>
                            </label>
                            <input type="text" id="template-tgf-grading-contains" class="wee-filter-input" placeholder="<?php esc_attr_e('e.g. Signature Authentication', 'wordpress-excel-export'); ?>">
                            <p class="description"><?php _e('Filter to items where any grading option value contains this text (card type, extras, signatures, etc.).', 'wordpress-excel-export'); ?></p>
                        </div>

                        <!-- TGF Service Level Filter -->
                        <div class="wee-form-group">
                            <label for="template-tgf-service-level-contains">
                                <span class="dashicons dashicons-star-filled" style="color: #e65c00;"></span>
                                <?php _e('Service Level Contains', 'wordpress-excel-export'); ?>
                            </label>
                            <input type="text" id="template-tgf-service-level-contains" class="wee-filter-input" placeholder="<?php esc_attr_e('e.g. Basic Tier Card Grading', 'wordpress-excel-export'); ?>">
                            <p class="description"><?php _e('Filter to items with a specific service level (spaces and capitalisation are handled automatically).', 'wordpress-excel-export'); ?></p>
                        </div>

                        <!-- Custom Meta Filter (Full Width) -->
                        <div class="wee-form-grid" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e1e5e9;">
                            <div class="wee-form-group wee-full-width">
                                <h4 style="margin: 0 0 10px 0; color: #1d2327;">
                                    <span class="dashicons dashicons-admin-generic" style="color: #00a0d2;"></span>
                                    <?php _e('Custom Meta Field Filter', 'wordpress-excel-export'); ?>
                                </h4>
                                <p class="description" style="margin-bottom: 15px;"><?php _e('Filter orders based on custom meta field values', 'wordpress-excel-export'); ?></p>
                            </div>
                            
                            <div class="wee-form-group">
                                <label for="template-custom-meta-key"><?php _e('Meta Key', 'wordpress-excel-export'); ?></label>
                                <input type="text" id="template-custom-meta-key" name="template_filters[custom_meta_key]" placeholder="<?php _e('Enter meta key...', 'wordpress-excel-export'); ?>" class="wee-filter-input">
                            </div>
                            
                            <div class="wee-form-group">
                                <label for="template-custom-meta-operator"><?php _e('Comparison', 'wordpress-excel-export'); ?></label>
                                <select id="template-custom-meta-operator" name="template_filters[custom_meta_operator]" class="wee-filter-input">
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
                                <input type="text" id="template-custom-meta-value" name="template_filters[custom_meta_value]" placeholder="<?php _e('Enter meta value...', 'wordpress-excel-export'); ?>" class="wee-filter-input">
                            </div>
                        </div>
                    </div>

                    <!-- Column Selection -->
                    <div class="wee-form-section">
                        <div class="wee-form-section-header">
                            <h3><?php _e('Select Columns', 'wordpress-excel-export'); ?></h3>
                            <p class="wee-form-section-description"><?php _e('Choose which columns to include in your export', 'wordpress-excel-export'); ?></p>
                        </div>
                        
                        <!-- Column Search -->
                        <div class="wee-column-search-container">
                            <div class="wee-search-input-wrapper">
                                <span class="dashicons dashicons-search"></span>
                                <input type="text" id="wee-column-search" placeholder="<?php _e('Search columns...', 'wordpress-excel-export'); ?>">
                            </div>
                            <div class="wee-search-actions">
                                <button type="button" class="button button-secondary" id="wee-expand-all-sections">
                                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                                    <?php _e('Expand All', 'wordpress-excel-export'); ?>
                                </button>
                                <button type="button" class="button button-secondary" id="wee-collapse-all-sections">
                                    <span class="dashicons dashicons-arrow-up-alt2"></span>
                                    <?php _e('Collapse All', 'wordpress-excel-export'); ?>
                                </button>
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

                <!-- Combine Selected Fields Section -->
                <div class="wee-section wee-combine-fields-section">
                    <div class="wee-card">
                        <div class="wee-card-header wee-combine-header">
                            <div class="wee-header-icon">
                                <span class="dashicons dashicons-admin-tools"></span>
                            </div>
                            <div class="wee-header-content">
                                <h2><?php _e('Combine Selected Fields', 'wordpress-excel-export'); ?></h2>
                                <p class="wee-card-subtitle"><?php _e('Create custom combined fields from your selected columns', 'wordpress-excel-export'); ?></p>
                            </div>
                        </div>
                        
                        <div class="wee-combine-fields-content">
                            <div class="wee-combine-fields-intro">
                                <div class="wee-info-box">
                                    <span class="dashicons dashicons-info-outline"></span>
                                    <p><?php _e('Select multiple columns above, then create a combined field that will replace them in your export.', 'wordpress-excel-export'); ?></p>
                                </div>
                            </div>
                            
                            <div class="wee-combine-fields-form">
                                <div class="wee-form-row">
                                    <div class="wee-form-group wee-form-group-large">
                                        <label for="combined-field-name"><?php _e('Combined Field Name', 'wordpress-excel-export'); ?></label>
                                        <input type="text" id="combined-field-name" name="combined_field_name" placeholder="<?php _e('e.g., Full Name, Complete Address', 'wordpress-excel-export'); ?>" class="wee-input-large">
                                    </div>
                                </div>
                                
                                <div class="wee-form-row">
                                    <div class="wee-form-group wee-form-group-medium">
                                        <label for="combined-field-separator"><?php _e('Separator', 'wordpress-excel-export'); ?></label>
                                        <select id="combined-field-separator" name="combined_field_separator" class="wee-select-medium">
                                            <option value=" "><?php _e('Space ( )', 'wordpress-excel-export'); ?></option>
                                            <option value=", "><?php _e('Comma (, )', 'wordpress-excel-export'); ?></option>
                                            <option value=" - "><?php _e('Dash ( - )', 'wordpress-excel-export'); ?></option>
                                            <option value=" | "><?php _e('Pipe ( | )', 'wordpress-excel-export'); ?></option>
                                            <option value="\n"><?php _e('New Line', 'wordpress-excel-export'); ?></option>
                                            <option value="custom"><?php _e('Custom...', 'wordpress-excel-export'); ?></option>
                                        </select>
                                    </div>
                                    
                                    <div class="wee-form-group wee-form-group-small" id="custom-separator-group" style="display: none;">
                                        <label for="custom-separator"><?php _e('Custom Separator', 'wordpress-excel-export'); ?></label>
                                        <input type="text" id="custom-separator" name="custom_separator" placeholder="<?php _e('Enter custom separator', 'wordpress-excel-export'); ?>" class="wee-input-small">
                                    </div>
                                </div>
                                
                                <div class="wee-form-row">
                                    <div class="wee-form-group wee-form-group-full">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                            <label style="margin: 0;"><?php _e('Select Fields to Combine', 'wordpress-excel-export'); ?></label>
                                            <div class="wee-combine-field-actions" style="display: none;">
                                                <button type="button" id="select-all-combine-fields" class="button button-small" style="margin-left: 5px;">
                                                    <?php _e('Select All', 'wordpress-excel-export'); ?>
                                                </button>
                                                <button type="button" id="deselect-all-combine-fields" class="button button-small" style="margin-left: 5px;">
                                                    <?php _e('Deselect All', 'wordpress-excel-export'); ?>
                                                </button>
                                            </div>
                                        </div>
                                        <p class="description" style="margin-bottom: 10px;">
                                            <?php _e('Check the fields below that you want to combine into this new field. Only your selected columns are shown here.', 'wordpress-excel-export'); ?>
                                        </p>
                                        <div id="selected-fields-preview" class="wee-selected-fields-preview">
                                            <div class="wee-no-fields-message">
                                                <span class="dashicons dashicons-warning"></span>
                                                <p><?php _e('Select fields from the column selection above first. They will appear here for you to choose which ones to combine.', 'wordpress-excel-export'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="wee-form-actions wee-combine-actions">
                                    <button type="button" id="add-combined-field" class="button button-primary wee-add-combined-btn">
                                        <span class="dashicons dashicons-plus-alt2"></span>
                                        <?php _e('Add Combined Field', 'wordpress-excel-export'); ?>
                                    </button>
                                    <button type="button" id="clear-combined-fields" class="button wee-clear-combined-btn">
                                        <span class="dashicons dashicons-trash"></span>
                                        <?php _e('Clear All', 'wordpress-excel-export'); ?>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="wee-combined-fields-list" id="combined-fields-list">
                                <!-- Combined fields will be added here dynamically -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Column Ordering Section -->
                <div class="wee-section wee-column-ordering-section">
                    <div class="wee-card">
                        <div class="wee-card-header">
                            <div class="wee-header-icon">
                                <span class="dashicons dashicons-sort"></span>
                            </div>
                            <div class="wee-header-content">
                                <h2><?php _e('Column Ordering', 'wordpress-excel-export'); ?></h2>
                                <p class="wee-card-subtitle"><?php _e('Drag and drop to reorder your selected columns', 'wordpress-excel-export'); ?></p>
                            </div>
                        </div>
                        
                        <div class="wee-column-ordering-content">
                            <div id="wee-selected-columns-list" class="wee-selected-columns-list">
                                <!-- Selected columns will be populated here -->
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
</div>

<!-- Edit Template Modal -->
<div id="wee-edit-modal" class="wee-modal" style="display: none;">
    <div class="wee-modal-overlay"></div>
    <div class="wee-modal-content wee-modal-large">
        <div class="wee-modal-header">
            <h2><?php _e('Edit Template', 'wordpress-excel-export'); ?></h2>
            <button class="wee-modal-close" aria-label="<?php _e('Close', 'wordpress-excel-export'); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="wee-modal-body">
            <form id="wee-edit-template-form" method="post">
                <?php wp_nonce_field('wee_nonce', 'edit_nonce'); ?>
                <input type="hidden" id="edit-template-id" name="template_id" value="">
                
                <div class="wee-form-content">
                    <!-- Template Basic Info -->
                    <div class="wee-form-section">
                        <div class="wee-form-section-header">
                            <h3><?php _e('Template Information', 'wordpress-excel-export'); ?></h3>
                        </div>
                        <div class="wee-form-grid">
                            <div class="wee-form-group">
                                <label for="edit-template-name"><?php _e('Template Name', 'wordpress-excel-export'); ?> <span class="required">*</span></label>
                                <input type="text" id="edit-template-name" name="template_name" required>
                            </div>
                            
                            <div class="wee-form-group">
                                <label for="edit-template-description"><?php _e('Description', 'wordpress-excel-export'); ?></label>
                                <textarea id="edit-template-description" name="template_description" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Filters Section -->
                    <div class="wee-form-section">
                        <div class="wee-form-section-header">
                            <h3><?php _e('Order Filters', 'wordpress-excel-export'); ?></h3>
                            <p class="wee-form-section-description"><?php _e('Set default filters for this template', 'wordpress-excel-export'); ?></p>
                        </div>
                        
                        <div class="wee-form-grid">
                            <!-- Order Status Filter -->
                            <div class="wee-form-group">
                                <label for="edit-template-order-status">
                                    <span class="dashicons dashicons-admin-post" style="color: #2271b1;"></span>
                                    <?php _e('Order Status', 'wordpress-excel-export'); ?>
                                </label>
                                <select id="edit-template-order-status" name="edit_template_filters[order_status][]" multiple size="4" class="wee-filter-input">
                                    <?php
                                    $order_statuses = wc_get_order_statuses();
                                    foreach ($order_statuses as $status_key => $status_name) : ?>
                                        <option value="<?php echo esc_attr($status_key); ?>"><?php echo esc_html($status_name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Payment Method Filter -->
                            <div class="wee-form-group">
                                <label for="edit-template-payment-method">
                                    <span class="dashicons dashicons-money-alt" style="color: #46b450;"></span>
                                    <?php _e('Payment Method', 'wordpress-excel-export'); ?>
                                </label>
                                <select id="edit-template-payment-method" name="edit_template_filters[payment_method]" class="wee-filter-input">
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
                            
                            <!-- Product Categories Filter -->
                            <div class="wee-form-group">
                                <label for="edit-template-product-categories">
                                    <span class="dashicons dashicons-category" style="color: #826eb4;"></span>
                                    <?php _e('Product Categories', 'wordpress-excel-export'); ?>
                                </label>
                                <select id="edit-template-product-categories" name="edit_template_filters[product_categories][]" multiple size="4" class="wee-filter-input">
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
                            
                            <!-- Product Search Filter -->
                            <div class="wee-form-group">
                                <label for="edit-template-product-search">
                                    <span class="dashicons dashicons-search" style="color: #d63638;"></span>
                                    <?php _e('Product Search', 'wordpress-excel-export'); ?>
                                </label>
                                <div class="wee-product-search-container" style="position: relative;">
                                    <input type="text" id="edit-template-product-search" placeholder="<?php _e('Type to search products...', 'wordpress-excel-export'); ?>" class="wee-filter-input" autocomplete="off">
                                    <div class="wee-product-dropdown wee-hidden"></div>
                                    <input type="hidden" id="edit-template-product-search-value" name="edit_template_filters[product_search]" value="">
                                </div>
                                <div id="edit-template-selected-products" class="wee-selected-products" style="margin-top: 10px;"></div>
                            </div>

                            <!-- TGF Grading Submission Type Filter -->
                            <div class="wee-form-group">
                                <label for="edit-template-tgf-submission-key">
                                    <span class="dashicons dashicons-awards" style="color: #e65c00;"></span>
                                    <?php _e('Grading Submission Type', 'wordpress-excel-export'); ?>
                                </label>
                                <select id="edit-template-tgf-submission-key" class="wee-filter-input">
                                    <option value=""><?php _e('All Submission Types', 'wordpress-excel-export'); ?></option>
                                    <option value="cgc_service_level"><?php _e('CGC Cards', 'wordpress-excel-export'); ?></option>
                                    <option value="cgc_comic_service"><?php _e('CGC Comics', 'wordpress-excel-export'); ?></option>
                                    <option value="psa_service_level"><?php _e('PSA Cards', 'wordpress-excel-export'); ?></option>
                                    <option value="bgs_service_level"><?php _e('BGS Cards', 'wordpress-excel-export'); ?></option>
                                    <option value="ags_service_level"><?php _e('AGS Cards', 'wordpress-excel-export'); ?></option>
                                    <option value="tag_service_level"><?php _e('TAG Cards', 'wordpress-excel-export'); ?></option>
                                </select>
                            </div>
                        </div>

                        <!-- TGF Grading Options Contains Filter -->
                        <div class="wee-form-group">
                            <label for="edit-template-tgf-grading-contains">
                                <span class="dashicons dashicons-filter" style="color: #e65c00;"></span>
                                <?php _e('Grading Options Contains', 'wordpress-excel-export'); ?>
                            </label>
                            <input type="text" id="edit-template-tgf-grading-contains" class="wee-filter-input" placeholder="<?php esc_attr_e('e.g. Signature Authentication', 'wordpress-excel-export'); ?>">
                            <p class="description"><?php _e('Filter to items where any grading option value contains this text (card type, extras, signatures, etc.).', 'wordpress-excel-export'); ?></p>
                        </div>

                        <!-- TGF Service Level Filter -->
                        <div class="wee-form-group">
                            <label for="edit-template-tgf-service-level-contains">
                                <span class="dashicons dashicons-star-filled" style="color: #e65c00;"></span>
                                <?php _e('Service Level Contains', 'wordpress-excel-export'); ?>
                            </label>
                            <input type="text" id="edit-template-tgf-service-level-contains" class="wee-filter-input" placeholder="<?php esc_attr_e('e.g. Basic Tier Card Grading', 'wordpress-excel-export'); ?>">
                            <p class="description"><?php _e('Filter to items with a specific service level (spaces and capitalisation are handled automatically).', 'wordpress-excel-export'); ?></p>
                        </div>
                    </div>

                    <!-- Column Selection -->
                    <div class="wee-form-section">
                        <div class="wee-form-section-header">
                            <h3><?php _e('Select Columns', 'wordpress-excel-export'); ?></h3>
                            <p class="wee-form-section-description"><?php _e('Choose which columns to include in your export', 'wordpress-excel-export'); ?></p>
                        </div>
                        
                        <!-- Column Search -->
                        <div class="wee-column-search-container">
                            <div class="wee-search-input-wrapper">
                                <span class="dashicons dashicons-search"></span>
                                <input type="text" id="wee-edit-column-search" placeholder="<?php _e('Search columns...', 'wordpress-excel-export'); ?>">
                            </div>
                            <div class="wee-search-actions">
                                <button type="button" class="button button-secondary" id="wee-edit-expand-all-sections">
                                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                                    <?php _e('Expand All', 'wordpress-excel-export'); ?>
                                </button>
                                <button type="button" class="button button-secondary" id="wee-edit-collapse-all-sections">
                                    <span class="dashicons dashicons-arrow-up-alt2"></span>
                                    <?php _e('Collapse All', 'wordpress-excel-export'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <div class="wee-columns-container" id="wee-edit-columns-container">
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
                                                        <input type="checkbox" name="edit_columns[]" value="<?php echo esc_attr($column_key); ?>">
                                                        <span><?php echo esc_html($column_label); ?></span>
                                                    </label>
                                                    <div class="wee-column-name-input">
                                                        <input type="text" 
                                                               name="edit_column_names[<?php echo esc_attr($column_key); ?>]" 
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
                        
                        <div class="wee-columns-selected" style="margin-top: 15px; padding: 10px; background: #f0f6fc; border-left: 3px solid #2271b1;">
                            <strong><span id="edit-selected-count">0</span> <?php _e('columns selected', 'wordpress-excel-export'); ?></strong>
                        </div>
                    </div>

                    <!-- Column Ordering Section -->
                    <div class="wee-form-section" id="wee-edit-column-ordering-section" style="display: none;">
                        <div class="wee-form-section-header">
                            <h3>
                                <span class="dashicons dashicons-sort" style="color: #2271b1;"></span>
                                <?php _e('Column Order', 'wordpress-excel-export'); ?>
                            </h3>
                            <p class="wee-form-section-description"><?php _e('Drag and drop to reorder your columns', 'wordpress-excel-export'); ?></p>
                        </div>
                        
                        <div id="wee-edit-selected-columns-list" class="wee-selected-columns-list">
                            <!-- Selected columns will appear here dynamically -->
                        </div>
                    </div>
                    
                    <!-- Combined Fields Section -->
                    <div class="wee-form-section wee-edit-combine-fields-section">
                        <div class="wee-form-section-header">
                            <h3>
                                <span class="dashicons dashicons-admin-tools" style="color: #2271b1;"></span>
                                <?php _e('Combine Selected Fields', 'wordpress-excel-export'); ?>
                            </h3>
                            <p class="wee-form-section-description"><?php _e('Create custom combined fields from your selected columns', 'wordpress-excel-export'); ?></p>
                        </div>
                        
                        <div class="wee-combine-fields-content">
                            <div class="wee-combine-fields-intro">
                                <div class="wee-info-box">
                                    <span class="dashicons dashicons-info-outline"></span>
                                    <p><?php _e('Select multiple columns above, then create a combined field that will replace them in your export.', 'wordpress-excel-export'); ?></p>
                                </div>
                            </div>
                            
                            <div class="wee-combine-fields-form">
                                <div class="wee-form-row">
                                    <div class="wee-form-group wee-form-group-large">
                                        <label for="edit-combined-field-name"><?php _e('Combined Field Name', 'wordpress-excel-export'); ?></label>
                                        <input type="text" id="edit-combined-field-name" name="edit_combined_field_name" placeholder="<?php _e('e.g., Full Name, Complete Address', 'wordpress-excel-export'); ?>" class="wee-input-large">
                                    </div>
                                </div>
                                
                                <div class="wee-form-row">
                                    <div class="wee-form-group wee-form-group-medium">
                                        <label for="edit-combined-field-separator"><?php _e('Separator', 'wordpress-excel-export'); ?></label>
                                        <select id="edit-combined-field-separator" name="edit_combined_field_separator" class="wee-select-medium">
                                            <option value=" "><?php _e('Space ( )', 'wordpress-excel-export'); ?></option>
                                            <option value=", "><?php _e('Comma (, )', 'wordpress-excel-export'); ?></option>
                                            <option value=" - "><?php _e('Dash ( - )', 'wordpress-excel-export'); ?></option>
                                            <option value=" | "><?php _e('Pipe ( | )', 'wordpress-excel-export'); ?></option>
                                            <option value="\n"><?php _e('New Line', 'wordpress-excel-export'); ?></option>
                                            <option value="custom"><?php _e('Custom...', 'wordpress-excel-export'); ?></option>
                                        </select>
                                    </div>
                                    
                                    <div class="wee-form-group wee-form-group-small" id="edit-custom-separator-group" style="display: none;">
                                        <label for="edit-custom-separator"><?php _e('Custom Separator', 'wordpress-excel-export'); ?></label>
                                        <input type="text" id="edit-custom-separator" name="edit_custom_separator" placeholder="<?php _e('Enter custom separator', 'wordpress-excel-export'); ?>" class="wee-input-small">
                                    </div>
                                </div>
                                
                                <div class="wee-form-row">
                                    <div class="wee-form-group wee-form-group-full">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                            <label style="margin: 0;"><?php _e('Select Fields to Combine', 'wordpress-excel-export'); ?></label>
                                            <div class="wee-combine-field-actions" style="display: none;">
                                                <button type="button" id="edit-select-all-combine-fields" class="button button-small" style="margin-left: 5px;">
                                                    <?php _e('Select All', 'wordpress-excel-export'); ?>
                                                </button>
                                                <button type="button" id="edit-deselect-all-combine-fields" class="button button-small" style="margin-left: 5px;">
                                                    <?php _e('Deselect All', 'wordpress-excel-export'); ?>
                                                </button>
                                            </div>
                                        </div>
                                        <p class="description" style="margin-bottom: 10px;">
                                            <?php _e('Check the fields below that you want to combine into this new field. Only your selected columns are shown here.', 'wordpress-excel-export'); ?>
                                        </p>
                                        <div id="edit-selected-fields-preview" class="wee-selected-fields-preview">
                                            <div class="wee-no-fields-message">
                                                <span class="dashicons dashicons-warning"></span>
                                                <p><?php _e('Select fields from the column selection above first. They will appear here for you to choose which ones to combine.', 'wordpress-excel-export'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="wee-form-actions wee-combine-actions">
                                    <button type="button" id="edit-add-combined-field" class="button button-primary wee-add-combined-btn">
                                        <span class="dashicons dashicons-plus-alt2"></span>
                                        <?php _e('Add Combined Field', 'wordpress-excel-export'); ?>
                                    </button>
                                    <button type="button" id="edit-clear-combined-fields" class="button wee-clear-combined-btn">
                                        <span class="dashicons dashicons-trash"></span>
                                        <?php _e('Clear All', 'wordpress-excel-export'); ?>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="wee-combined-fields-list" id="edit-combined-fields-list">
                                <!-- Combined fields will be added here dynamically -->
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="wee-modal-actions">
                    <button type="button" class="button wee-modal-cancel"><?php _e('Cancel', 'wordpress-excel-export'); ?></button>
                    <button type="submit" class="button button-primary"><?php _e('Update Template', 'wordpress-excel-export'); ?></button>
                </div>
            </form>
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
        // Section toggle functionality - only for main form, NOT edit modal
        $('.wee-columns-container:not(#wee-edit-columns-container) .wee-section-toggle').on('click', function() {
            const $toggle = $(this);
            const $content = $toggle.closest('.wee-column-section').find('.wee-column-section-content');
            const isCollapsed = $content.hasClass('wee-collapsed');
            
            console.log('WEE DEBUG: Main form toggle clicked, collapsed:', isCollapsed);
            
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

        // Expand all sections - only for main form
        $('#wee-expand-all-sections').on('click', function() {
            $('.wee-columns-container:not(#wee-edit-columns-container) .wee-column-section-content').removeClass('wee-collapsed');
            $('.wee-columns-container:not(#wee-edit-columns-container) .wee-section-toggle').addClass('wee-expanded');
            $('.wee-columns-container:not(#wee-edit-columns-container) .wee-section-toggle .wee-toggle-icon').text('−');
        });

        // Collapse all sections - only for main form
        $('#wee-collapse-all-sections').on('click', function() {
            $('.wee-columns-container:not(#wee-edit-columns-container) .wee-column-section-content').addClass('wee-collapsed');
            $('.wee-columns-container:not(#wee-edit-columns-container) .wee-section-toggle').removeClass('wee-expanded');
            $('.wee-columns-container:not(#wee-edit-columns-container) .wee-section-toggle .wee-toggle-icon').text('+');
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
            // Search only in main form, not edit modal
            $('.wee-columns-container:not(#wee-edit-columns-container) .wee-column-item').each(function() {
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

            // Auto-expand sections that have matches when searching - only in main form
            if (searchTerm !== '') {
                $('.wee-columns-container:not(#wee-edit-columns-container) .wee-column-section').each(function() {
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
            // Update only main form section counts
            $('.wee-columns-container:not(#wee-edit-columns-container) .wee-column-section').each(function() {
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
    
    // Initialize template product search with autocomplete
    initTemplateProductSearch();
    initEditTemplateProductSearch();
    
    function initTemplateProductSearch() {
        setupProductSearch('#template-product-search', '#template-product-search-value', '#template-selected-products');
    }
    
    function initEditTemplateProductSearch() {
        setupProductSearch('#edit-template-product-search', '#edit-template-product-search-value', '#edit-template-selected-products');
    }
    
    function setupProductSearch(searchInputSelector, hiddenInputSelector, selectedContainerSelector) {
        const $productSearch = $(searchInputSelector);
        if ($productSearch.length === 0) {
            console.warn('Product search input not found:', searchInputSelector);
            return;
        }
        
        const $dropdown = $productSearch.siblings('.wee-product-dropdown');
        const $hiddenInput = $(hiddenInputSelector);
        const $selectedProducts = $(selectedContainerSelector);
        const containerSelector = $productSearch.closest('.wee-product-search-container');
        
        let searchTimeout;
        let selectedProducts = [];
        
        console.log('Setting up product search for:', searchInputSelector);
        
        // Function to reload selected products from hidden input
        function reloadSelectedProducts() {
            if ($hiddenInput.val()) {
                try {
                    selectedProducts = JSON.parse($hiddenInput.val());
                    renderSelectedProducts();
                } catch (e) {
                    console.error('Error parsing product search data:', e);
                    selectedProducts = [];
                }
            }
        }
        
        // Load existing selected products if editing
        reloadSelectedProducts();
        
        // Listen for custom event when template is loaded
        document.addEventListener('products-loaded', function(e) {
            console.log('Products loaded event fired, reloading products');
            reloadSelectedProducts();
        });
        
        $productSearch.on('input', function() {
            const query = $(this).val().trim();
            console.log('Product search input:', query);
            
            clearTimeout(searchTimeout);
            
            if (query.length < 2) {
                hideDropdown();
                return;
            }
            
            searchTimeout = setTimeout(() => {
                searchProducts(query);
            }, 300);
        });
        
        function searchProducts(query) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wee_search_products',
                    query: query,
                    nonce: $('input[name="nonce"]').val()
                },
                success: function(response) {
                    if (response.success && response.data) {
                        showDropdown(response.data);
                    } else {
                        hideDropdown();
                    }
                },
                error: function() {
                    hideDropdown();
                }
            });
        }
        
        function showDropdown(products) {
            $dropdown.empty();
            
            if (products.length === 0) {
                $dropdown.html('<div class="wee-no-results" style="padding: 10px; text-align: center; color: #666;">No products found</div>');
            } else {
                products.forEach((product) => {
                    const isSelected = selectedProducts.some(p => p.id === product.id);
                    const $item = $(`
                        <div class="wee-product-item ${isSelected ? 'wee-product-selected' : ''}" data-product-id="${product.id}" data-product-name="${product.name}" data-product-sku="${product.sku || ''}">
                            <div class="wee-product-name">${product.name}</div>
                            <div class="wee-product-sku">SKU: ${product.sku || 'N/A'} ${isSelected ? '<span style="color: #46b450; font-weight: bold;">✓ Selected</span>' : ''}</div>
                        </div>
                    `);
                    $dropdown.append($item);
                });
            }
            
            $dropdown.removeClass('wee-hidden');
        }
        
        function hideDropdown() {
            $dropdown.addClass('wee-hidden');
        }
        
        // Handle product selection
        $dropdown.on('click', '.wee-product-item', function() {
            const productId = $(this).data('product-id');
            const productName = $(this).data('product-name');
            const productSku = $(this).data('product-sku');
            
            // Check if already selected
            if (!selectedProducts.some(p => p.id === productId)) {
                selectedProducts.push({
                    id: productId,
                    name: productName,
                    sku: productSku
                });
                
                updateHiddenField();
                renderSelectedProducts();
            }
            
            $productSearch.val('');
            hideDropdown();
        });
        
        function updateHiddenField() {
            $hiddenInput.val(JSON.stringify(selectedProducts));
        }
        
        function renderSelectedProducts() {
            if (selectedProducts.length === 0) {
                $selectedProducts.empty();
                return;
            }
            
            $selectedProducts.html(`
                <div style="background: #f0f8ff; border: 1px solid #0066cc; border-radius: 6px; padding: 10px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <strong style="color: #0066cc;">Selected Products:</strong>
                        <button type="button" class="button button-small wee-clear-all-products" style="padding: 2px 8px; height: auto;">Clear All</button>
                    </div>
                    <div class="wee-selected-products-list"></div>
                </div>
            `);
            
            const $list = $selectedProducts.find('.wee-selected-products-list');
            
            selectedProducts.forEach((product, index) => {
                const $productTag = $(`
                    <div class="wee-selected-product-tag" style="display: inline-block; background: white; border: 1px solid #0066cc; border-radius: 4px; padding: 4px 8px; margin: 2px; font-size: 13px;">
                        <span style="color: #0066cc; font-weight: 500;">${product.name}</span>
                        ${product.sku ? `<span style="color: #666; font-size: 12px;"> (${product.sku})</span>` : ''}
                        <button type="button" class="wee-remove-product" data-index="${index}" style="background: none; border: none; color: #d63638; cursor: pointer; margin-left: 5px; padding: 0; font-weight: bold;">×</button>
                    </div>
                `);
                $list.append($productTag);
            });
        }
        
        // Handle remove product
        $selectedProducts.on('click', '.wee-remove-product', function() {
            const index = $(this).data('index');
            selectedProducts.splice(index, 1);
            updateHiddenField();
            renderSelectedProducts();
        });
        
        // Handle clear all products
        $selectedProducts.on('click', '.wee-clear-all-products', function() {
            selectedProducts = [];
            updateHiddenField();
            renderSelectedProducts();
        });
        
        // Hide dropdown when clicking outside - use specific container check
        $(document).on('click', function(e) {
            const $target = $(e.target);
            const $container = $productSearch.closest('.wee-product-search-container');
            if ($container.length && !$target.closest($container).length) {
                hideDropdown();
            }
        });
    }
});
</script> 