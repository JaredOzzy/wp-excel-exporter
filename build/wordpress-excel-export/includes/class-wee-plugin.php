<?php
/**
 * Main Plugin Class
 */
class WEE_Plugin {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        }
        
        // AJAX hooks
        add_action('wp_ajax_wee_save_template', array($this, 'ajax_save_template'));
        add_action('wp_ajax_wee_add_template', array($this, 'ajax_add_template'));
        add_action('wp_ajax_wee_edit_template', array($this, 'ajax_edit_template'));
        add_action('wp_ajax_wee_get_template', array($this, 'ajax_get_template'));
        add_action('wp_ajax_wee_delete_template', array($this, 'ajax_delete_template'));
        add_action('wp_ajax_wee_export_data', array($this, 'ajax_export_data'));
        add_action('wp_ajax_wee_get_products', array($this, 'ajax_get_products'));
        add_action('wp_ajax_wee_search_products', array($this, 'ajax_search_products'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Excel Export', 'wordpress-excel-export'),
            __('Excel Export', 'wordpress-excel-export'),
            'manage_options',
            'wordpress-excel-export',
            array($this, 'admin_page'),
            'dashicons-media-spreadsheet',
            30
        );
        
        add_submenu_page(
            'wordpress-excel-export',
            __('Export Orders', 'wordpress-excel-export'),
            __('Export Orders', 'wordpress-excel-export'),
            'manage_options',
            'wordpress-excel-export',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'wordpress-excel-export',
            __('Manage Templates', 'wordpress-excel-export'),
            __('Manage Templates', 'wordpress-excel-export'),
            'manage_options',
            'wee-templates',
            array($this, 'templates_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Debug the current hook
        error_log('WEE Current Hook: ' . $hook);
        error_log('WEE Plugin URL: ' . WEE_PLUGIN_URL);
        
        // Check if we're on our plugin pages
        if (strpos($hook, 'wordpress-excel-export') === false && strpos($hook, 'wee-templates') === false) {
            error_log('WEE Not on plugin page, hook: ' . $hook);
            return;
        }
        
        error_log('WEE Enqueueing scripts and styles');
        
        // Enqueue our admin styles first
        wp_enqueue_style(
            'wee-admin-css',
            WEE_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WEE_PLUGIN_VERSION
        );
        
        // Enqueue admin script
        wp_enqueue_script(
            'wee-admin-js',
            WEE_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'jquery-ui-sortable'),
            WEE_PLUGIN_VERSION,
            true
        );
        
        // Check if styles were enqueued
        error_log('WEE CSS Enqueued: ' . (wp_style_is('wee-admin-css', 'enqueued') ? 'Yes' : 'No'));
        
        // Localize script for AJAX
        wp_localize_script('wee-admin-js', 'wee_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wee_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this template?', 'wordpress-excel-export'),
                'exporting' => __('Exporting...', 'wordpress-excel-export'),
                'export_complete' => __('Export completed!', 'wordpress-excel-export'),
                'error' => __('An error occurred.', 'wordpress-excel-export')
            )
        ));
    }
    
    /**
     * Admin page callback
     */
    public function admin_page() {
        include WEE_PLUGIN_PATH . 'templates/admin-page.php';
    }
    
    /**
     * Templates page callback
     */
    public function templates_page() {
        include WEE_PLUGIN_PATH . 'templates/templates-page.php';
    }
    
    /**
     * AJAX: Save template
     */
    public function ajax_save_template() {
        check_ajax_referer('wee_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'wordpress-excel-export'));
        }
        
        $template_name = sanitize_text_field($_POST['template_name']);
        $columns = array_map('sanitize_text_field', $_POST['columns'] ?? array());
        $custom_fields = array();
        if (!empty($_POST['custom_fields']) && is_array($_POST['custom_fields'])) {
            foreach ($_POST['custom_fields'] as $meta_key => $field) {
                if (!empty($field['enabled'])) {
                    $custom_fields[$meta_key] = array(
                        'column_name' => sanitize_text_field($field['column_name']),
                        'meta_key' => sanitize_text_field($meta_key)
                    );
                }
            }
        }
        $result = WEE_Templates::save_template($template_name, $columns, $custom_fields);
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Delete template
     */
    public function ajax_delete_template() {
        check_ajax_referer('wee_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'wordpress-excel-export'));
        }
        
        $template_id = intval($_POST['template_id']);
        $result = WEE_Templates::delete_template($template_id);
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Export data
     */
    public function ajax_export_data() {
        check_ajax_referer('wee_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'wordpress-excel-export'));
        }
        
        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
        $export_format = isset($_POST['export_format']) ? sanitize_text_field($_POST['export_format']) : 'xlsx';
        
        // Validate export format
        if (!in_array($export_format, ['xlsx', 'csv'])) {
            $export_format = 'xlsx'; // Default to Excel
        }
        
        // Start with template filters if a template is selected
        $filters = array();
        if ($template_id > 0) {
            $template = WEE_Templates::get_template($template_id);
            if ($template && !empty($template['filters'])) {
                $filters = $template['filters'];
                error_log('WEE Export: Loaded template filters: ' . print_r($filters, true));
            }
        }
        
        // Override with any explicit form filters (these take precedence over template filters)
        // Product filters
        if (!empty($_POST['product_search'])) {
            $filters['product_search'] = sanitize_text_field($_POST['product_search']);
        }
        
        if (!empty($_POST['product_categories']) && is_array($_POST['product_categories'])) {
            $filters['product_categories'] = array_map('intval', $_POST['product_categories']);
        }
        
        if (!empty($_POST['product_status'])) {
            $filters['product_status'] = sanitize_text_field($_POST['product_status']);
        }
        
        if (!empty($_POST['product_type'])) {
            $filters['product_type'] = sanitize_text_field($_POST['product_type']);
        }
        
        // Order filters
        if (!empty($_POST['order_status']) && is_array($_POST['order_status'])) {
            $filters['order_status'] = array_map('sanitize_text_field', $_POST['order_status']);
        }
        
        if (!empty($_POST['payment_method'])) {
            $filters['payment_method'] = sanitize_text_field($_POST['payment_method']);
        }
        
        if (!empty($_POST['order_total_min'])) {
            $filters['order_total_min'] = floatval($_POST['order_total_min']);
        }
        
        if (!empty($_POST['order_total_max'])) {
            $filters['order_total_max'] = floatval($_POST['order_total_max']);
        }
        
        // Custom meta filters
        if (!empty($_POST['custom_meta_key'])) {
            $filters['custom_meta_key'] = sanitize_text_field($_POST['custom_meta_key']);
        }
        
        if (!empty($_POST['custom_meta_value'])) {
            $filters['custom_meta_value'] = sanitize_text_field($_POST['custom_meta_value']);
        }
        
        if (!empty($_POST['custom_meta_operator'])) {
            $filters['custom_meta_operator'] = sanitize_text_field($_POST['custom_meta_operator']);
        }
        
        error_log('WEE Export: Final filters being passed to export: ' . print_r($filters, true));
        
        try {
            $export = new WEE_Export();
            $result = $export->export_orders($template_id, $date_from, $date_to, $filters, $export_format);

            // If export was successful and a file URL was returned, output the file directly
            if (!empty($result['file_url'])) {
            $upload_dir = wp_upload_dir();
            $file_url   = $result['file_url'];

            // Convert file URL back to absolute path on the server
            $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $file_url);

            if (file_exists($file_path)) {
                $filename = !empty($result['file_name']) ? $result['file_name'] : basename($file_path);

                // Set appropriate headers for download
                $mime_type = ($export_format === 'csv') ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                header('Content-Type: ' . $mime_type);
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . filesize($file_path));
                header('Pragma: public');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Expires: 0');

                // Read the file and output its contents
                readfile($file_path);

                // Optionally delete the generated file after sending
                // unlink($file_path);

                exit; // End the AJAX request after sending the file
            }

                // If file does not exist, return an error
                wp_send_json_error(array(
                    'message' => __('Export file not found.', 'wordpress-excel-export')
                ));
            } else {
                // If no file URL was returned, there was an issue with file generation
                wp_send_json_error(array(
                    'message' => __('Failed to generate export file.', 'wordpress-excel-export')
                ));
            }
        } catch (Exception $e) {
            // Catch any exceptions and return a proper error message
            error_log('WEE Export Exception: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => sprintf(__('Export failed: %s', 'wordpress-excel-export'), $e->getMessage())
            ));
        }
    }
    
    /**
     * AJAX: Get products for filter
     */
    public function ajax_get_products() {
        check_ajax_referer('wee_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'wordpress-excel-export'));
        }
        
        $products = WEE_Export::get_products();
        wp_send_json_success($products);
    }
    
    /**
     * AJAX: Search products
     */
    public function ajax_search_products() {
        check_ajax_referer('wee_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'wordpress-excel-export'));
        }
        
        $query = sanitize_text_field($_POST['query']);
        
        if (empty($query) || strlen($query) < 2) {
            wp_send_json_success(array());
        }
        
        $products = WEE_Export::search_products($query);
        wp_send_json_success($products);
    }

    /**
     * AJAX: Get template
     */
    public function ajax_get_template() {
        check_ajax_referer('wee_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'wordpress-excel-export'));
        }
        
        $template_id = intval($_POST['template_id']);
        $template = WEE_Templates::get_template($template_id);
        
        if ($template) {
            wp_send_json_success($template);
        } else {
            wp_send_json_error(array(
                'message' => __('Template not found.', 'wordpress-excel-export')
            ));
        }
    }

    /**
     * AJAX: Add template
     */
    public function ajax_add_template() {
        check_ajax_referer('wee_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'wordpress-excel-export'));
        }
        
        $template_name = sanitize_text_field($_POST['template_name']);
        $template_description = sanitize_text_field($_POST['template_description']);
        $columns = array_map('sanitize_text_field', $_POST['columns'] ?? array());
        
        // Handle column ordering
        if (!empty($_POST['column_order'])) {
            $column_order = json_decode(sanitize_text_field($_POST['column_order']), true);
            if (is_array($column_order)) {
                // Reorder columns according to the specified order
                $ordered_columns = array();
                foreach ($column_order as $column_key) {
                    if (in_array($column_key, $columns)) {
                        $ordered_columns[] = $column_key;
                    }
                }
                // Add any remaining columns that weren't in the order (shouldn't happen normally)
                foreach ($columns as $column) {
                    if (!in_array($column, $ordered_columns)) {
                        $ordered_columns[] = $column;
                    }
                }
                $columns = $ordered_columns;
            }
        }
        
        // Handle custom column names
        $column_names = array();
        if (!empty($_POST['column_names']) && is_array($_POST['column_names'])) {
            foreach ($_POST['column_names'] as $column_key => $custom_name) {
                $column_names[sanitize_text_field($column_key)] = sanitize_text_field($custom_name);
            }
        }
        
        $custom_fields = array();
        $filters = array();
        
        if (!empty($_POST['custom_fields']) && is_array($_POST['custom_fields'])) {
            foreach ($_POST['custom_fields'] as $meta_key => $field) {
                if (!empty($field['enabled'])) {
                    $custom_fields[$meta_key] = array(
                        'column_name' => sanitize_text_field($field['column_name']),
                        'meta_key' => sanitize_text_field($meta_key)
                    );
                }
            }
        }
        
        // Handle template filters
        if (!empty($_POST['template_filters']) && is_array($_POST['template_filters'])) {
            $template_filters = $_POST['template_filters'];
            
            if (!empty($template_filters['product_search'])) {
                $filters['product_search'] = sanitize_text_field($template_filters['product_search']);
            }
            
            if (!empty($template_filters['product_categories']) && is_array($template_filters['product_categories'])) {
                $filters['product_categories'] = array_map('intval', $template_filters['product_categories']);
            }
            
            if (!empty($template_filters['order_status']) && is_array($template_filters['order_status'])) {
                $filters['order_status'] = array_map('sanitize_text_field', $template_filters['order_status']);
            }
            
            if (!empty($template_filters['payment_method'])) {
                $filters['payment_method'] = sanitize_text_field($template_filters['payment_method']);
            }
            
            if (!empty($template_filters['order_total_min'])) {
                $filters['order_total_min'] = floatval($template_filters['order_total_min']);
            }
            
            if (!empty($template_filters['order_total_max'])) {
                $filters['order_total_max'] = floatval($template_filters['order_total_max']);
            }
            
            if (!empty($template_filters['custom_meta_key'])) {
                $filters['custom_meta_key'] = sanitize_text_field($template_filters['custom_meta_key']);
                
                if (!empty($template_filters['custom_meta_value'])) {
                    $filters['custom_meta_value'] = sanitize_text_field($template_filters['custom_meta_value']);
                }
                
                if (!empty($template_filters['custom_meta_operator'])) {
                    $filters['custom_meta_operator'] = sanitize_text_field($template_filters['custom_meta_operator']);
                }
            }
        }
        
        $result = WEE_Templates::save_template($template_name, $columns, $custom_fields, $filters, $column_names);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Edit template
     */
    public function ajax_edit_template() {
        check_ajax_referer('wee_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'wordpress-excel-export'));
        }
        
        $template_id = intval($_POST['template_id']);
        $template_name = sanitize_text_field($_POST['template_name']);
        $template_description = sanitize_text_field($_POST['template_description']);
        $columns = array_map('sanitize_text_field', $_POST['columns'] ?? array());
        
        // Handle column ordering
        if (!empty($_POST['column_order'])) {
            $column_order = json_decode(sanitize_text_field($_POST['column_order']), true);
            if (is_array($column_order)) {
                // Reorder columns according to the specified order
                $ordered_columns = array();
                foreach ($column_order as $column_key) {
                    if (in_array($column_key, $columns)) {
                        $ordered_columns[] = $column_key;
                    }
                }
                // Add any remaining columns that weren't in the order (shouldn't happen normally)
                foreach ($columns as $column) {
                    if (!in_array($column, $ordered_columns)) {
                        $ordered_columns[] = $column;
                    }
                }
                $columns = $ordered_columns;
            }
        }
        
        // Handle custom column names
        $column_names = array();
        if (!empty($_POST['column_names']) && is_array($_POST['column_names'])) {
            foreach ($_POST['column_names'] as $column_key => $custom_name) {
                $column_names[sanitize_text_field($column_key)] = sanitize_text_field($custom_name);
            }
        }
        
        $custom_fields = array();
        $filters = array();
        
        if (!empty($_POST['custom_fields']) && is_array($_POST['custom_fields'])) {
            foreach ($_POST['custom_fields'] as $meta_key => $field) {
                if (!empty($field['enabled'])) {
                    $custom_fields[$meta_key] = array(
                        'column_name' => sanitize_text_field($field['column_name']),
                        'meta_key' => sanitize_text_field($meta_key)
                    );
                }
            }
        }
        
        // Handle template filters (same logic as add template)
        if (!empty($_POST['template_filters']) && is_array($_POST['template_filters'])) {
            $template_filters = $_POST['template_filters'];
            
            if (!empty($template_filters['product_search'])) {
                $filters['product_search'] = sanitize_text_field($template_filters['product_search']);
            }
            
            if (!empty($template_filters['product_categories']) && is_array($template_filters['product_categories'])) {
                $filters['product_categories'] = array_map('intval', $template_filters['product_categories']);
            }
            
            if (!empty($template_filters['order_status']) && is_array($template_filters['order_status'])) {
                $filters['order_status'] = array_map('sanitize_text_field', $template_filters['order_status']);
            }
            
            if (!empty($template_filters['payment_method'])) {
                $filters['payment_method'] = sanitize_text_field($template_filters['payment_method']);
            }
            
            if (!empty($template_filters['order_total_min'])) {
                $filters['order_total_min'] = floatval($template_filters['order_total_min']);
            }
            
            if (!empty($template_filters['order_total_max'])) {
                $filters['order_total_max'] = floatval($template_filters['order_total_max']);
            }
            
            if (!empty($template_filters['custom_meta_key'])) {
                $filters['custom_meta_key'] = sanitize_text_field($template_filters['custom_meta_key']);
                
                if (!empty($template_filters['custom_meta_value'])) {
                    $filters['custom_meta_value'] = sanitize_text_field($template_filters['custom_meta_value']);
                }
                
                if (!empty($template_filters['custom_meta_operator'])) {
                    $filters['custom_meta_operator'] = sanitize_text_field($template_filters['custom_meta_operator']);
                }
            }
        }
        
        $result = WEE_Templates::update_template($template_id, $template_name, $columns, $custom_fields, $filters, $column_names);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    public static function activate() {
        // Create database tables
        WEE_Templates::create_tables();
        
        // Optimize database indexes for better performance
        self::optimize_database_indexes();
    }
    
    /**
     * Optimize database indexes for better export performance
     */
    public static function optimize_database_indexes() {
        global $wpdb;
        
        // Check and create indexes that will speed up our queries
        $indexes_to_create = array(
            // Posts table indexes for order queries
            array(
                'table' => $wpdb->posts,
                'index' => 'wee_post_type_date',
                'columns' => '(post_type, post_date)',
                'query' => "CREATE INDEX wee_post_type_date ON {$wpdb->posts} (post_type, post_date)"
            ),
            array(
                'table' => $wpdb->posts,
                'index' => 'wee_post_type_status',
                'columns' => '(post_type, post_status)',
                'query' => "CREATE INDEX wee_post_type_status ON {$wpdb->posts} (post_type, post_status)"
            ),
            
            // Postmeta indexes for faster meta queries
            array(
                'table' => $wpdb->postmeta,
                'index' => 'wee_meta_key_value',
                'columns' => '(meta_key, meta_value(50))',
                'query' => "CREATE INDEX wee_meta_key_value ON {$wpdb->postmeta} (meta_key, meta_value(50))"
            ),
            array(
                'table' => $wpdb->postmeta,
                'index' => 'wee_post_id_key',
                'columns' => '(post_id, meta_key)',
                'query' => "CREATE INDEX wee_post_id_key ON {$wpdb->postmeta} (post_id, meta_key)"
            ),
            
            // WooCommerce order items indexes
            array(
                'table' => $wpdb->prefix . 'woocommerce_order_items',
                'index' => 'wee_order_id_type',
                'columns' => '(order_id, order_item_type)',
                'query' => "CREATE INDEX wee_order_id_type ON {$wpdb->prefix}woocommerce_order_items (order_id, order_item_type)"
            ),
            
            // WooCommerce order item meta indexes
            array(
                'table' => $wpdb->prefix . 'woocommerce_order_itemmeta',
                'index' => 'wee_item_meta_key_value',
                'columns' => '(meta_key, meta_value(50))',
                'query' => "CREATE INDEX wee_item_meta_key_value ON {$wpdb->prefix}woocommerce_order_itemmeta (meta_key, meta_value(50))"
            ),
            array(
                'table' => $wpdb->prefix . 'woocommerce_order_itemmeta',
                'index' => 'wee_item_id_key',
                'columns' => '(order_item_id, meta_key)',
                'query' => "CREATE INDEX wee_item_id_key ON {$wpdb->prefix}woocommerce_order_itemmeta (order_item_id, meta_key)"
            )
        );
        
        foreach ($indexes_to_create as $index_info) {
            // Check if table exists first
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $index_info['table']));
            if (!$table_exists) {
                continue; // Skip if table doesn't exist
            }
            
            // Check if index already exists
            $index_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                WHERE table_schema = %s 
                AND table_name = %s 
                AND index_name = %s",
                DB_NAME,
                $index_info['table'],
                $index_info['index']
            ));
            
            // Create index if it doesn't exist
            if (!$index_exists) {
                $result = $wpdb->query($index_info['query']);
                if ($result === false) {
                    error_log('WEE: Failed to create index ' . $index_info['index'] . ' on table ' . $index_info['table'] . '. Error: ' . $wpdb->last_error);
                } else {
                    error_log('WEE: Successfully created index ' . $index_info['index'] . ' on table ' . $index_info['table']);
                }
            }
        }
        
        // Update option to track optimization
        update_option('wee_db_optimized', time());
    }
    
    /**
     * Check if database optimization is needed (run monthly)
     */
    public static function maybe_optimize_database() {
        $last_optimized = get_option('wee_db_optimized', 0);
        $one_month_ago = time() - (30 * 24 * 60 * 60);
        
        if ($last_optimized < $one_month_ago) {
            self::optimize_database_indexes();
        }
    }

    /**
     * Initialize the plugin
     */
    public static function init() {
        $plugin = new self();
        
        // Add performance optimization notice
        add_action('admin_notices', array($plugin, 'show_performance_notice'));
    }
    
    /**
     * Show performance optimization notice
     */
    public function show_performance_notice() {
        // Only show on our plugin pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'wordpress-excel-export') === false) {
            return;
        }
        
        // Check if optimization was recently run
        $last_optimized = get_option('wee_db_optimized', 0);
        if ($last_optimized && (time() - $last_optimized) < 86400) { // Less than 24 hours ago
            $metrics = WEE_Export::get_performance_metrics();
            
            if (!empty($metrics['recent_exports'])) {
                $latest = $metrics['recent_exports'][0];
                ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <strong><?php _e('WordPress Excel Export - Optimized!', 'wordpress-excel-export'); ?></strong><br>
                        <?php 
                        printf(
                            __('Database indexes optimized for faster exports. Latest export: %d orders in %s seconds (avg: %s seconds per order).', 'wordpress-excel-export'),
                            $latest['orders_count'],
                            $latest['execution_time'],
                            $latest['avg_time_per_order']
                        );
                        ?>
                    </p>
                </div>
                <?php
            } else {
                ?>
                <div class="notice notice-info is-dismissible">
                    <p>
                        <strong><?php _e('WordPress Excel Export - Ready!', 'wordpress-excel-export'); ?></strong><br>
                        <?php _e('Database optimized for fast exports. Your exports will now run significantly faster with batch processing and proper indexing.', 'wordpress-excel-export'); ?>
                    </p>
                </div>
                <?php
            }
        }
    }
} 