<?php
/**
 * Main Plugin Class
 */
class WEE_Plugin {
    
    public function __construct() {
        // Initialize templates class
        WEE_Templates::init();

        // Auto-create the six grading templates if they don't exist yet
        $this->ensure_grading_templates();

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
        add_action('wp_ajax_wee_duplicate_template', array($this, 'ajax_duplicate_template'));
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
     * AJAX: Duplicate template
     */
    public function ajax_duplicate_template() {
        check_ajax_referer('wee_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'wordpress-excel-export'));
        }
        
        $template_id = intval($_POST['template_id']);
        $result = WEE_Templates::duplicate_template($template_id);
        
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
        
        error_log('WEE: Template retrieved for AJAX: ' . print_r($template, true));
        
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
        
        // Handle columns - decode JSON if it's a string
        $columns = array();
        if (!empty($_POST['columns'])) {
            if (is_string($_POST['columns'])) {
                $columns_data = json_decode(stripslashes($_POST['columns']), true);
                if (is_array($columns_data)) {
                    $columns = array_map('sanitize_text_field', $columns_data);
                }
            } elseif (is_array($_POST['columns'])) {
                $columns = array_map('sanitize_text_field', $_POST['columns']);
            }
        }
        
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
        if (!empty($_POST['column_names'])) {
            if (is_string($_POST['column_names'])) {
                $column_names_data = json_decode(stripslashes($_POST['column_names']), true);
                if (is_array($column_names_data)) {
                    foreach ($column_names_data as $column_key => $custom_name) {
                        $column_names[sanitize_text_field($column_key)] = sanitize_text_field($custom_name);
                    }
                }
            } elseif (is_array($_POST['column_names'])) {
                foreach ($_POST['column_names'] as $column_key => $custom_name) {
                    $column_names[sanitize_text_field($column_key)] = sanitize_text_field($custom_name);
                }
            }
        }
        
        $custom_fields = array();
        $filters = array();
        $field_groups = array();
        $combined_fields = array();
        
        // Handle field groups
        if (!empty($_POST['field_groups'])) {
            $field_groups_data = json_decode(sanitize_text_field($_POST['field_groups']), true);
            if (is_array($field_groups_data)) {
                foreach ($field_groups_data as $group) {
                    if (!empty($group['name']) && !empty($group['fields'])) {
                        $field_groups[] = array(
                            'name' => sanitize_text_field($group['name']),
                            'fields' => array_map('sanitize_text_field', $group['fields'])
                        );
                    }
                }
            }
        }
        
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
        
        // Handle combined fields - use the safe pattern from the checklist
        $raw_combined = isset($_POST['combined_fields']) ? wp_unslash($_POST['combined_fields']) : '';
        error_log('WEE DEBUG: Raw combined_fields POST data in ajax_edit_template: ' . $raw_combined);
        
        $combined_fields = array();
        if (!empty($raw_combined)) {
            // Accept both JSON string and array (defensive)
            if (is_string($raw_combined) && $raw_combined !== '') {
                $decoded = json_decode($raw_combined, true);
            } elseif (is_array($raw_combined)) {
                $decoded = $raw_combined;
            } else {
                $decoded = array();
            }
            
            error_log('WEE DEBUG: Decoded combined_fields data in ajax_edit_template: ' . print_r($decoded, true));
            
            // Validate/sanitize STRUCTURE, not the JSON string itself
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    // expected shape: { id, name, fields[], separator, visible }
                    $id = isset($item['id']) ? sanitize_text_field($item['id']) : '';
                    $name = isset($item['name']) ? sanitize_text_field($item['name']) : '';
                    $fields_in = isset($item['fields']) ? (array) $item['fields'] : array();
                    
                    // Sanitize fields array - each field is an object with key, name, value
                    $fields = array();
                    foreach ($fields_in as $field) {
                        if (is_array($field)) {
                            $fields[] = array(
                                'key' => isset($field['key']) ? sanitize_text_field($field['key']) : '',
                                'name' => isset($field['name']) ? sanitize_text_field($field['name']) : '',
                                'value' => isset($field['value']) ? sanitize_text_field($field['value']) : (isset($field['key']) ? sanitize_text_field($field['key']) : '')
                            );
                        }
                    }
                    
                    $separator = isset($item['separator']) ? sanitize_text_field($item['separator']) : ' ';
                    $visible = isset($item['visible']) ? (bool) $item['visible'] : true;
                    
                    if ($name && !empty($fields)) {
                        $combined_fields[] = array(
                            'id' => $id,
                            'name' => $name,
                            'fields' => $fields,
                            'separator' => $separator,
                            'visible' => $visible
                        );
                    }
                }
            }
                error_log('WEE DEBUG: Final combined_fields array in ajax_edit_template: ' . print_r($combined_fields, true));
        }
        
        // Handle column visibility
        $column_visibility = array();
        if (!empty($_POST['column_visibility'])) {
            // Don't sanitize JSON data as it can corrupt the structure
            $column_visibility_data = json_decode($_POST['column_visibility'], true);
            if (is_array($column_visibility_data)) {
                $column_visibility = $column_visibility_data;
            }
        }
        
        // Handle template filters
        $filters = array();
        $template_filters = array();
        if (!empty($_POST['template_filters'])) {
            if (is_string($_POST['template_filters'])) {
                $template_filters = json_decode(stripslashes($_POST['template_filters']), true);
                if (!is_array($template_filters)) {
                    $template_filters = array();
                }
            } elseif (is_array($_POST['template_filters'])) {
                $template_filters = $_POST['template_filters'];
            }
        }
        
        error_log('WEE DEBUG: Template filters received in ajax_add_template: ' . print_r($template_filters, true));
        
        if (!empty($template_filters) && is_array($template_filters)) {
            if (!empty($template_filters['product_search'])) {
                // Product search can be a JSON array of IDs, don't sanitize it as text
                $filters['product_search'] = $template_filters['product_search'];
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

            if (!empty($template_filters['tgf_submission_key'])) {
                $filters['tgf_submission_key'] = sanitize_text_field($template_filters['tgf_submission_key']);
            }

            if (!empty($template_filters['tgf_grading_contains'])) {
                $filters['tgf_grading_contains'] = sanitize_text_field($template_filters['tgf_grading_contains']);
            }

            if (!empty($template_filters['tgf_service_level_contains'])) {
                $filters['tgf_service_level_contains'] = sanitize_text_field($template_filters['tgf_service_level_contains']);
            }
        }

        // Debug logging
        error_log('WEE: Template save attempt - Name: ' . $template_name . ', Columns: ' . print_r($columns, true));
        error_log('WEE DEBUG: Filters being passed to save_template: ' . print_r($filters, true));
        
        $result = WEE_Templates::save_template($template_name, $columns, $custom_fields, $filters, $column_names, $field_groups, $combined_fields, $column_visibility);
        
        error_log('WEE: Template save result: ' . print_r($result, true));
        
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
        
        // Handle columns - decode JSON if it's a string
        $columns = array();
        error_log('WEE DEBUG SERVER: Checking for columns in $_POST...');
        error_log('WEE DEBUG SERVER: isset($_POST[columns]): ' . (isset($_POST['columns']) ? 'YES' : 'NO'));
        error_log('WEE DEBUG SERVER: empty($_POST[columns]): ' . (empty($_POST['columns']) ? 'YES' : 'NO'));
        
        if (!empty($_POST['columns'])) {
            error_log('WEE DEBUG SERVER: Raw columns data: ' . print_r($_POST['columns'], true));
            error_log('WEE DEBUG SERVER: Type: ' . gettype($_POST['columns']));
            if (is_string($_POST['columns'])) {
                error_log('WEE DEBUG SERVER: Decoding JSON string...');
                // Use stripslashes to remove WordPress-added slashes before decoding
                $clean_json = stripslashes($_POST['columns']);
                error_log('WEE DEBUG SERVER: After stripslashes: ' . $clean_json);
                $columns_data = json_decode($clean_json, true);
                error_log('WEE DEBUG SERVER: Decoded columns data: ' . print_r($columns_data, true));
                if (is_array($columns_data)) {
                    $columns = array_map('sanitize_text_field', $columns_data);
                    error_log('WEE DEBUG SERVER: Sanitized columns: ' . print_r($columns, true));
                } else {
                    error_log('WEE DEBUG SERVER: ERROR - columns_data is not an array after decode. JSON error: ' . json_last_error_msg());
                }
            } elseif (is_array($_POST['columns'])) {
                error_log('WEE DEBUG SERVER: columns is already an array');
                $columns = array_map('sanitize_text_field', $_POST['columns']);
            } else {
                error_log('WEE DEBUG SERVER: ERROR - columns is neither string nor array: ' . gettype($_POST['columns']));
            }
        } else {
            error_log('WEE DEBUG SERVER: ERROR - columns is empty or not set in POST data!');
            error_log('WEE DEBUG SERVER: All POST keys: ' . print_r(array_keys($_POST), true));
        }
        error_log('WEE DEBUG SERVER: Final columns array for saving: ' . print_r($columns, true));
        error_log('WEE DEBUG SERVER: Final columns count: ' . count($columns));
        
        // Handle column ordering - keep the full order including combined field IDs
        $column_order = array();
        if (!empty($_POST['column_order'])) {
            // Use stripslashes before json_decode
            $column_order_data = json_decode(stripslashes($_POST['column_order']), true);
            if (is_array($column_order_data)) {
                // Save the complete order (includes both regular columns and combined field IDs)
                $column_order = array_map('sanitize_text_field', $column_order_data);
                
                // Also reorder the regular columns array for backwards compatibility
                $ordered_columns = array();
                foreach ($column_order_data as $column_key) {
                    $column_key = sanitize_text_field($column_key);
                    // Only add if it's a regular column (not a combined field ID)
                    if (in_array($column_key, $columns)) {
                        $ordered_columns[] = $column_key;
                    }
                }
                // Add any remaining columns that weren't in the order
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
        if (!empty($_POST['column_names'])) {
            if (is_string($_POST['column_names'])) {
                $column_names_data = json_decode(stripslashes($_POST['column_names']), true);
                if (is_array($column_names_data)) {
                    foreach ($column_names_data as $column_key => $custom_name) {
                        $column_names[sanitize_text_field($column_key)] = sanitize_text_field($custom_name);
                    }
                }
            } elseif (is_array($_POST['column_names'])) {
                foreach ($_POST['column_names'] as $column_key => $custom_name) {
                    $column_names[sanitize_text_field($column_key)] = sanitize_text_field($custom_name);
                }
            }
        }
        
        $custom_fields = array();
        $filters = array();
        $field_groups = array();
        $combined_fields = array();
        
        // Handle field groups
        if (!empty($_POST['field_groups'])) {
            $field_groups_data = json_decode(sanitize_text_field($_POST['field_groups']), true);
            if (is_array($field_groups_data)) {
                foreach ($field_groups_data as $group) {
                    if (!empty($group['name']) && !empty($group['fields'])) {
                        $field_groups[] = array(
                            'name' => sanitize_text_field($group['name']),
                            'fields' => array_map('sanitize_text_field', $group['fields'])
                        );
                    }
                }
            }
        }
        
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
        
        // Handle combined fields - use the safe pattern from the checklist
        $raw_combined = isset($_POST['combined_fields']) ? wp_unslash($_POST['combined_fields']) : '';
        error_log('WEE DEBUG: Raw combined_fields POST data in ajax_edit_template: ' . $raw_combined);
        
        $combined_fields = array();
        if (!empty($raw_combined)) {
            // Accept both JSON string and array (defensive)
            if (is_string($raw_combined) && $raw_combined !== '') {
                $decoded = json_decode($raw_combined, true);
            } elseif (is_array($raw_combined)) {
                $decoded = $raw_combined;
            } else {
                $decoded = array();
            }
            
            error_log('WEE DEBUG: Decoded combined_fields data in ajax_edit_template: ' . print_r($decoded, true));
            
            // Validate/sanitize STRUCTURE, not the JSON string itself
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    // expected shape: { id, name, fields[], separator, visible }
                    $id = isset($item['id']) ? sanitize_text_field($item['id']) : '';
                    $name = isset($item['name']) ? sanitize_text_field($item['name']) : '';
                    $fields_in = isset($item['fields']) ? (array) $item['fields'] : array();
                    
                    // Sanitize fields array - each field is an object with key, name, value
                    $fields = array();
                    foreach ($fields_in as $field) {
                        if (is_array($field)) {
                            $fields[] = array(
                                'key' => isset($field['key']) ? sanitize_text_field($field['key']) : '',
                                'name' => isset($field['name']) ? sanitize_text_field($field['name']) : '',
                                'value' => isset($field['value']) ? sanitize_text_field($field['value']) : (isset($field['key']) ? sanitize_text_field($field['key']) : '')
                            );
                        }
                    }
                    
                    $separator = isset($item['separator']) ? sanitize_text_field($item['separator']) : ' ';
                    $visible = isset($item['visible']) ? (bool) $item['visible'] : true;
                    
                    if ($name && !empty($fields)) {
                        $combined_fields[] = array(
                            'id' => $id,
                            'name' => $name,
                            'fields' => $fields,
                            'separator' => $separator,
                            'visible' => $visible
                        );
                    }
                }
            }
                error_log('WEE DEBUG: Final combined_fields array in ajax_edit_template: ' . print_r($combined_fields, true));
        }
        
        // Handle column visibility
        $column_visibility = array();
        if (!empty($_POST['column_visibility'])) {
            // Use stripslashes before json_decode
            $column_visibility_data = json_decode(stripslashes($_POST['column_visibility']), true);
            if (is_array($column_visibility_data)) {
                $column_visibility = $column_visibility_data;
            }
        }
        
        // Handle template filters (same logic as add template)
        $template_filters = array();
        if (!empty($_POST['template_filters'])) {
            if (is_string($_POST['template_filters'])) {
                // Use stripslashes before json_decode
                $template_filters = json_decode(stripslashes($_POST['template_filters']), true);
                if (!is_array($template_filters)) {
                    $template_filters = array();
                }
            } elseif (is_array($_POST['template_filters'])) {
                $template_filters = $_POST['template_filters'];
            }
        }
        
        error_log('WEE DEBUG SERVER: Template filters received in ajax_edit_template: ' . print_r($template_filters, true));
        error_log('WEE DEBUG SERVER: ========================================');
        error_log('WEE DEBUG SERVER: ABOUT TO CALL update_template() WITH:');
        error_log('WEE DEBUG SERVER: - template_id: ' . $template_id);
        error_log('WEE DEBUG SERVER: - template_name: ' . $template_name);
        error_log('WEE DEBUG SERVER: - columns count: ' . count($columns));
        error_log('WEE DEBUG SERVER: - columns array: ' . print_r($columns, true));
        error_log('WEE DEBUG SERVER: - filters: ' . print_r($filters, true));
        error_log('WEE DEBUG SERVER: ========================================');
        
        if (!empty($template_filters)) {
            
            if (!empty($template_filters['product_search'])) {
                // Product search can be a JSON array of IDs, don't sanitize it as text
                $filters['product_search'] = $template_filters['product_search'];
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

            if (!empty($template_filters['tgf_submission_key'])) {
                $filters['tgf_submission_key'] = sanitize_text_field($template_filters['tgf_submission_key']);
            }

            if (!empty($template_filters['tgf_grading_contains'])) {
                $filters['tgf_grading_contains'] = sanitize_text_field($template_filters['tgf_grading_contains']);
            }

            if (!empty($template_filters['tgf_service_level_contains'])) {
                $filters['tgf_service_level_contains'] = sanitize_text_field($template_filters['tgf_service_level_contains']);
            }
        }

        error_log('WEE DEBUG: Filters being passed to update_template: ' . print_r($filters, true));
        error_log('WEE DEBUG: Combined fields being passed to update_template: ' . print_r($combined_fields, true));
        error_log('WEE DEBUG: Columns being passed to update_template: ' . print_r($columns, true));
        error_log('WEE DEBUG: Column order being passed to update_template: ' . print_r($column_order, true));
        error_log('WEE DEBUG: Template ID: ' . $template_id);
        error_log('WEE DEBUG: Template name: ' . $template_name);
        
        $result = WEE_Templates::update_template($template_id, $template_name, $columns, $custom_fields, $filters, $column_names, $field_groups, $combined_fields, $template_description, $column_visibility, $column_order);
        
        error_log('WEE DEBUG: Update result: ' . print_r($result, true));
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            error_log('WEE ERROR: Update failed with message: ' . (isset($result['message']) ? $result['message'] : 'No message'));
            wp_send_json_error($result);
        }
    }

    /**
     * Auto-create the six grading templates if they don't already exist.
     * Template IDs are cached in WP options so creation only happens once.
     */
    public function ensure_grading_templates() {
        $default_columns = array(
            'tgf_line_number',
            'customer_name',
            'order_edit_link',
            'tgf_service_level',
            'tgf_extras',
            'tgf_extras_card_type',
            'tgf_extras_card_extras',
            'tgf_extras_signatures',
            'tgf_extras_comic_extras',
            'tgf_extras_bgs_subgrades',
            'tgf_extras_tag_score',
            'tgf_item_name',
            'tgf_item_set',
            'tgf_item_year',
            'tgf_item_number',
            'tgf_item_description',
            'shipping_address',
            'order_customer_note',
        );

        $grading_templates = array(
            'CGC Cards'  => 'cgc_service_level',
            'CGC Comics' => 'cgc_comic_service',
            'PSA Cards'  => 'psa_service_level',
            'BGS Cards'  => 'bgs_service_level',
            'AGS Cards'  => 'ags_service_level',
            'TAG Cards'  => 'tag_service_level',
        );

        foreach ($grading_templates as $name => $submission_key) {
            $option_key  = 'wee_grading_tpl_' . sanitize_key($name);
            $template_id = get_option($option_key, 0);

            if ($template_id) {
                $tpl = WEE_Templates::get_template($template_id);
                if ($tpl) {
                    // Migrate: append any new default columns not yet in this template
                    $existing_columns = is_array($tpl['columns']) ? $tpl['columns'] : array();
                    $missing_columns  = array_diff($default_columns, $existing_columns);

                    if (!empty($missing_columns)) {
                        WEE_Templates::update_template(
                            $template_id,
                            $tpl['name'],
                            array_merge($existing_columns, array_values($missing_columns)),
                            isset($tpl['custom_fields']) && is_array($tpl['custom_fields']) ? $tpl['custom_fields'] : array(),
                            isset($tpl['filters'])      && is_array($tpl['filters'])      ? $tpl['filters']      : array(),
                            isset($tpl['column_names']) && is_array($tpl['column_names']) ? $tpl['column_names'] : array(),
                            isset($tpl['field_groups']) && is_array($tpl['field_groups']) ? $tpl['field_groups'] : array(),
                            isset($tpl['combined_fields']) && is_array($tpl['combined_fields']) ? $tpl['combined_fields'] : array(),
                            isset($tpl['description']) ? $tpl['description'] : '',
                            isset($tpl['column_visibility']) && is_array($tpl['column_visibility']) ? $tpl['column_visibility'] : array(),
                            isset($tpl['column_order'])      && is_array($tpl['column_order'])      ? $tpl['column_order']      : array()
                        );
                    }
                    continue;
                }
                delete_option($option_key);
            }

            $result = WEE_Templates::save_template(
                $name,
                $default_columns,
                array(),  // custom_fields
                array('tgf_submission_key' => $submission_key),
                array(),  // column_names
                array(),  // field_groups
                array(),  // combined_fields
                array()   // column_visibility
            );

            if (!empty($result['success']) && !empty($result['template_id'])) {
                update_option($option_key, $result['template_id']);
            }
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