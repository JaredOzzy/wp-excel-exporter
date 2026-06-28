<?php
/**
 * Templates Management Class
 */
class WEE_Templates {
    
    private static $table_name;
    
    public static function init() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'wee_templates';
    }
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wee_templates';
        $charset_collate = $wpdb->get_charset_collate();
        
        error_log('WEE DEBUG: Creating/updating table: ' . $table_name);
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text NULL,
            columns longtext NOT NULL,
            custom_fields longtext NULL,
            filters longtext NULL,
            column_names longtext NULL,
            field_groups longtext NULL,
            combined_fields longtext NULL,
            column_visibility longtext NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Diagnostic: Check if combined_fields column exists
        self::diagnose_database_structure();
    }
    
    /**
     * Diagnostic function to check database structure
     */
    private static function diagnose_database_structure() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wee_templates';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        error_log('WEE DEBUG: Table exists: ' . ($table_exists ? 'YES' : 'NO'));
        
        if ($table_exists) {
            // Get all columns
            $columns = $wpdb->get_col("DESC $table_name");
            error_log('WEE DEBUG: Table columns: ' . print_r($columns, true));
            
            // Check specifically for combined_fields
            $has_combined_fields = in_array('combined_fields', $columns);
            error_log('WEE DEBUG: Has combined_fields column: ' . ($has_combined_fields ? 'YES' : 'NO'));
            
            // If missing, try to add it
            if (!$has_combined_fields) {
                error_log('WEE DEBUG: Adding missing combined_fields column...');
                $result = $wpdb->query("ALTER TABLE $table_name ADD combined_fields LONGTEXT NULL");
                error_log('WEE DEBUG: Add column result: ' . $result);
                error_log('WEE DEBUG: Last error: ' . $wpdb->last_error);
            }
        }
    }
    
    /**
     * Save template
     */
    public static function save_template($name, $columns, $custom_fields = array(), $filters = array(), $column_names = array(), $field_groups = array(), $combined_fields = array(), $column_visibility = array()) {
        global $wpdb;
        
        error_log('WEE DEBUG: save_template called with combined_fields: ' . print_r($combined_fields, true));
        
        self::init();
        
        $data = array(
            'name' => $name,
            'columns' => json_encode($columns),
            'custom_fields' => json_encode($custom_fields),
            'filters' => json_encode($filters),
            'column_names' => json_encode($column_names),
            'field_groups' => json_encode($field_groups),
            'combined_fields' => json_encode($combined_fields),
            'column_visibility' => json_encode($column_visibility)
        );
        
        error_log('WEE DEBUG: Data being saved to database: ' . print_r($data, true));
        error_log('WEE DEBUG: Combined fields JSON: ' . $data['combined_fields']);
        
        // Add custom_fields column if not exists
        $table_fields = $wpdb->get_col("DESC " . self::$table_name, 0);
        if (!in_array('custom_fields', $table_fields)) {
            $wpdb->query("ALTER TABLE " . self::$table_name . " ADD custom_fields LONGTEXT NULL");
        }
        
        // Add filters column if not exists
        if (!in_array('filters', $table_fields)) {
            $wpdb->query("ALTER TABLE " . self::$table_name . " ADD filters LONGTEXT NULL");
        }
        
        // Add column_names column if not exists
        if (!in_array('column_names', $table_fields)) {
            $wpdb->query("ALTER TABLE " . self::$table_name . " ADD column_names LONGTEXT NULL");
        }
        
        // Add field_groups column if not exists
        if (!in_array('field_groups', $table_fields)) {
            $wpdb->query("ALTER TABLE " . self::$table_name . " ADD field_groups LONGTEXT NULL");
        }
        
        // Add combined_fields column if not exists
        error_log('WEE DEBUG: Checking if combined_fields column exists. Table fields: ' . print_r($table_fields, true));
        if (!in_array('combined_fields', $table_fields)) {
            error_log('WEE DEBUG: combined_fields column does not exist, adding it...');
            $wpdb->query("ALTER TABLE " . self::$table_name . " ADD combined_fields LONGTEXT NULL");
        } else {
            error_log('WEE DEBUG: combined_fields column already exists');
        }
        
        // Add column_visibility column if not exists
        if (!in_array('column_visibility', $table_fields)) {
            $wpdb->query("ALTER TABLE " . self::$table_name . " ADD column_visibility LONGTEXT NULL");
        }
        
        error_log('WEE DEBUG: About to insert data into database. Table: ' . self::$table_name);
        error_log('WEE DEBUG: Insert data: ' . print_r($data, true));
        $result = $wpdb->insert(self::$table_name, $data);
        error_log('WEE DEBUG: Database insert result: ' . print_r($result, true));
        error_log('WEE DEBUG: Last error: ' . $wpdb->last_error);
        
        if ($result === false) {
            return array(
                'success' => false,
                'message' => __('Failed to save template.', 'wordpress-excel-export')
            );
        }
        
        return array(
            'success' => true,
            'message' => __('Template saved successfully.', 'wordpress-excel-export'),
            'template_id' => $wpdb->insert_id
        );
    }
    
    /**
     * Get all templates
     */
    public static function get_templates() {
        global $wpdb;
        
        self::init();
        
        $templates = $wpdb->get_results(
            "SELECT * FROM " . self::$table_name . " ORDER BY created_at DESC",
            ARRAY_A
        );
        
        foreach ($templates as &$template) {
            $template['columns'] = json_decode($template['columns'], true);
            if (!is_array($template['columns'])) {
                $template['columns'] = array();
            }
            
            $template['custom_fields'] = isset($template['custom_fields']) ? json_decode($template['custom_fields'], true) : array();
            if (!is_array($template['custom_fields'])) {
                $template['custom_fields'] = array();
            }
            
            $template['filters'] = isset($template['filters']) ? json_decode($template['filters'], true) : array();
            if (!is_array($template['filters'])) {
                $template['filters'] = array();
            }
            
            $template['column_names'] = isset($template['column_names']) ? json_decode($template['column_names'], true) : array();
            if (!is_array($template['column_names'])) {
                $template['column_names'] = array();
            }
            
            $template['field_groups'] = isset($template['field_groups']) ? json_decode($template['field_groups'], true) : array();
            if (!is_array($template['field_groups'])) {
                $template['field_groups'] = array();
            }
            
            $template['combined_fields'] = isset($template['combined_fields']) ? json_decode($template['combined_fields'], true) : array();
            if (!is_array($template['combined_fields'])) {
                $template['combined_fields'] = array();
            }
            
            $template['column_visibility'] = isset($template['column_visibility']) ? json_decode($template['column_visibility'], true) : array();
            if (!is_array($template['column_visibility'])) {
                $template['column_visibility'] = array();
            }

        }
        
        return $templates;
    }
    
    /**
     * Get template by ID
     */
    public static function get_template($id) {
        global $wpdb;
        
        self::init();
        
        $template = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . self::$table_name . " WHERE id = %d",
                $id
            ),
            ARRAY_A
        );
        
        if ($template) {
            error_log('WEE DEBUG: Raw template from database: ' . print_r($template, true));
            
            $template['columns'] = json_decode($template['columns'], true);
            if (!is_array($template['columns'])) {
                $template['columns'] = array();
            }
            
            $template['custom_fields'] = isset($template['custom_fields']) ? json_decode($template['custom_fields'], true) : array();
            if (!is_array($template['custom_fields'])) {
                $template['custom_fields'] = array();
            }
            
            error_log('WEE DEBUG: Raw filters JSON: ' . $template['filters']);
            $template['filters'] = isset($template['filters']) ? json_decode($template['filters'], true) : array();
            if (!is_array($template['filters'])) {
                $template['filters'] = array();
            }
            error_log('WEE DEBUG: Decoded filters: ' . print_r($template['filters'], true));
            
            $template['column_names'] = isset($template['column_names']) ? json_decode($template['column_names'], true) : array();
            if (!is_array($template['column_names'])) {
                $template['column_names'] = array();
            }
            
            $template['field_groups'] = isset($template['field_groups']) ? json_decode($template['field_groups'], true) : array();
            if (!is_array($template['field_groups'])) {
                $template['field_groups'] = array();
            }
            
            error_log('WEE DEBUG: Raw combined_fields JSON from DB: ' . (isset($template['combined_fields']) ? $template['combined_fields'] : 'NOT SET'));
            $template['combined_fields'] = isset($template['combined_fields']) ? json_decode($template['combined_fields'], true) : array();
            if (!is_array($template['combined_fields'])) {
                $template['combined_fields'] = array();
            }
            error_log('WEE DEBUG: Decoded combined_fields from DB: ' . print_r($template['combined_fields'], true));
            
            $template['column_visibility'] = isset($template['column_visibility']) ? json_decode($template['column_visibility'], true) : array();
            if (!is_array($template['column_visibility'])) {
                $template['column_visibility'] = array();
            }
            
        }
        
        return $template;
    }
    
    
    /**
     * Delete template
     */
    public static function delete_template($id) {
        global $wpdb;
        
        self::init();
        
        $result = $wpdb->delete(
            self::$table_name,
            array('id' => $id),
            array('%d')
        );
        
        if ($result === false) {
            return array(
                'success' => false,
                'message' => __('Failed to delete template.', 'wordpress-excel-export')
            );
        }
        
        return array(
            'success' => true,
            'message' => __('Template deleted successfully.', 'wordpress-excel-export')
        );
    }
    
    /**
     * Duplicate template
     */
    public static function duplicate_template($id) {
        global $wpdb;
        
        self::init();
        
        // Get the original template
        $original_template = self::get_template($id);
        
        if (!$original_template) {
            return array(
                'success' => false,
                'message' => __('Template not found.', 'wordpress-excel-export')
            );
        }
        
        // Create a new name for the duplicate
        $original_name = $original_template['name'];
        $duplicate_name = $original_name . ' (Copy)';
        
        // Check if the duplicate name already exists, if so, add a number
        $counter = 1;
        while (self::template_name_exists($duplicate_name)) {
            $duplicate_name = $original_name . ' (Copy ' . $counter . ')';
            $counter++;
        }
        
        // Prepare the data for the new template
        $data = array(
            'name' => $duplicate_name,
            'description' => isset($original_template['description']) ? $original_template['description'] : '',
            'columns' => json_encode($original_template['columns']),
            'custom_fields' => json_encode(isset($original_template['custom_fields']) ? $original_template['custom_fields'] : array()),
            'filters' => json_encode(isset($original_template['filters']) ? $original_template['filters'] : array()),
            'column_names' => json_encode(isset($original_template['column_names']) ? $original_template['column_names'] : array()),
            'field_groups' => json_encode(isset($original_template['field_groups']) ? $original_template['field_groups'] : array()),
            'combined_fields' => json_encode(isset($original_template['combined_fields']) ? $original_template['combined_fields'] : array()),
            'column_visibility' => json_encode(isset($original_template['column_visibility']) ? $original_template['column_visibility'] : array()),
        );
        
        // Insert the duplicate template
        $result = $wpdb->insert(self::$table_name, $data);
        
        if ($result === false) {
            return array(
                'success' => false,
                'message' => __('Failed to duplicate template.', 'wordpress-excel-export')
            );
        }
        
        return array(
            'success' => true,
            'message' => __('Template duplicated successfully.', 'wordpress-excel-export'),
            'template_id' => $wpdb->insert_id,
            'template_name' => $duplicate_name
        );
    }
    
    /**
     * Check if a template name already exists
     */
    private static function template_name_exists($name) {
        global $wpdb;
        
        self::init();
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::$table_name . " WHERE name = %s",
            $name
        ));
        
        return $count > 0;
    }
    
    /**
     * Get all unique order meta keys from recent orders (default: 100)
     */
    public static function get_order_meta_keys($limit = 100) {
        global $wpdb;
        $meta_keys = array();
        // Get recent order IDs
        $order_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_order' ORDER BY ID DESC LIMIT %d",
            $limit
        ));
        if (empty($order_ids)) {
            return $meta_keys;
        }
        // Get all meta keys for these orders
        $placeholders = implode(',', array_fill(0, count($order_ids), '%d'));
        $query = $wpdb->prepare(
            "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE post_id IN ($placeholders)",
            ...$order_ids
        );
        $results = $wpdb->get_col($query);
        // Exclude protected/meta keys that are not custom fields
        foreach ($results as $key) {
            if (strpos($key, '_') !== 0) { // Only show non-protected meta by default
                $meta_keys[] = $key;
            }
            // Optionally, allow all keys (including those starting with _)
            // $meta_keys[] = $key;
        }
        return $meta_keys;
    }
    
    /**
     * Get available columns for templates
     */
    public static function get_available_columns() {
        $columns = array(
            'order_information' => array(
                'label' => __('Order Information', 'wordpress-excel-export'),
                'columns' => array(
                    'order_id' => __('Order ID', 'wordpress-excel-export'),
                    'order_edit_link' => __('Link to Order Edit', 'wordpress-excel-export'),
                    'order_date' => __('Order Date', 'wordpress-excel-export'),
                    'order_status' => __('Order Status', 'wordpress-excel-export'),
                    'order_total' => __('Order Total', 'wordpress-excel-export'),
                    'order_subtotal' => __('Order Subtotal', 'wordpress-excel-export'),
                    'order_tax_total' => __('Order Tax Total', 'wordpress-excel-export'),
                    'order_shipping_total' => __('Order Shipping Total', 'wordpress-excel-export'),
                    'order_discount_total' => __('Order Discount Total', 'wordpress-excel-export'),
                    'order_refund_total' => __('Order Refund Total', 'wordpress-excel-export'),
                    'order_currency' => __('Order Currency', 'wordpress-excel-export'),
                    'order_payment_method' => __('Payment Method', 'wordpress-excel-export'),
                    'order_payment_method_title' => __('Payment Method Title', 'wordpress-excel-export'),
                    'order_shipping_method' => __('Shipping Method', 'wordpress-excel-export'),
                    'order_shipping_method_title' => __('Shipping Method Title', 'wordpress-excel-export'),
                    'order_coupon_codes' => __('Order Coupon Codes', 'wordpress-excel-export'),
                    'order_notes' => __('Order Notes', 'wordpress-excel-export'),
                    'order_meta' => __('Order Meta Data', 'wordpress-excel-export'),
                    'order_items_count' => __('Order Items Count', 'wordpress-excel-export'),
                    'order_dimensions' => __('Order Dimensions', 'wordpress-excel-export'),
                    'order_created_via' => __('Order Created Via', 'wordpress-excel-export'),
                    'order_customer_note' => __('Order Customer Note', 'wordpress-excel-export'),
                    'order_transaction_id' => __('Order Transaction ID', 'wordpress-excel-export'),
                    'order_ip_address' => __('Customer IP Address', 'wordpress-excel-export'),
                    'order_user_agent' => __('User Agent', 'wordpress-excel-export'),
                    'order_date_paid' => __('Date Paid', 'wordpress-excel-export'),
                    'order_date_completed' => __('Date Completed', 'wordpress-excel-export'),
                    'order_date_modified' => __('Date Modified', 'wordpress-excel-export')
                )
            ),
            'customer_information' => array(
                'label' => __('Customer Information', 'wordpress-excel-export'),
                'columns' => array(
                    'customer_id' => __('Customer ID', 'wordpress-excel-export'),
                    'customer_name' => __('Customer Name', 'wordpress-excel-export'),
                    'customer_first_name' => __('Customer First Name', 'wordpress-excel-export'),
                    'customer_last_name' => __('Customer Last Name', 'wordpress-excel-export'),
                    'customer_email' => __('Customer Email', 'wordpress-excel-export'),
                    'customer_phone' => __('Customer Phone', 'wordpress-excel-export'),
                    'customer_username' => __('Customer Username', 'wordpress-excel-export'),
                    'customer_registration_date' => __('Customer Registration Date', 'wordpress-excel-export'),
                    'customer_total_orders' => __('Customer Total Orders', 'wordpress-excel-export'),
                    'customer_total_spent' => __('Customer Total Spent', 'wordpress-excel-export'),
                    'customer_role' => __('Customer Role', 'wordpress-excel-export'),
                    'customer_meta' => __('Customer Meta Data', 'wordpress-excel-export')
                )
            ),
            'billing_information' => array(
                'label' => __('Billing Information', 'wordpress-excel-export'),
                'columns' => array(
                    'billing_first_name' => __('Billing First Name', 'wordpress-excel-export'),
                    'billing_last_name' => __('Billing Last Name', 'wordpress-excel-export'),
                    'billing_company' => __('Billing Company', 'wordpress-excel-export'),
                    'billing_address_1' => __('Billing Address Line 1', 'wordpress-excel-export'),
                    'billing_address_2' => __('Billing Address Line 2', 'wordpress-excel-export'),
                    'billing_city' => __('Billing City', 'wordpress-excel-export'),
                    'billing_state' => __('Billing State', 'wordpress-excel-export'),
                    'billing_postcode' => __('Billing Postcode', 'wordpress-excel-export'),
                    'billing_country' => __('Billing Country', 'wordpress-excel-export'),
                    'billing_email' => __('Billing Email', 'wordpress-excel-export'),
                    'billing_phone' => __('Billing Phone', 'wordpress-excel-export'),
                    'billing_address' => __('Billing Address (Full)', 'wordpress-excel-export')
                )
            ),
            'shipping_information' => array(
                'label' => __('Shipping Information', 'wordpress-excel-export'),
                'columns' => array(
                    'shipping_first_name' => __('Shipping First Name', 'wordpress-excel-export'),
                    'shipping_last_name' => __('Shipping Last Name', 'wordpress-excel-export'),
                    'shipping_company' => __('Shipping Company', 'wordpress-excel-export'),
                    'shipping_address_1' => __('Shipping Address Line 1', 'wordpress-excel-export'),
                    'shipping_address_2' => __('Shipping Address Line 2', 'wordpress-excel-export'),
                    'shipping_city' => __('Shipping City', 'wordpress-excel-export'),
                    'shipping_state' => __('Shipping State', 'wordpress-excel-export'),
                    'shipping_postcode' => __('Shipping Postcode', 'wordpress-excel-export'),
                    'shipping_country' => __('Shipping Country', 'wordpress-excel-export'),
                    'shipping_address' => __('Shipping Address (Full)', 'wordpress-excel-export')
                )
            ),
            'product_information' => array(
                'label' => __('Product Information', 'wordpress-excel-export'),
                'columns' => array(
                    'product_id' => __('Product ID', 'wordpress-excel-export'),
                    'product_name' => __('Product Name', 'wordpress-excel-export'),
                    'product_sku' => __('Product SKU', 'wordpress-excel-export'),
                    'product_quantity' => __('Product Quantity', 'wordpress-excel-export'),
                    'product_price' => __('Product Price', 'wordpress-excel-export'),
                    'product_total' => __('Product Total', 'wordpress-excel-export'),
                    'product_subtotal' => __('Product Subtotal', 'wordpress-excel-export'),
                    'product_tax' => __('Product Tax', 'wordpress-excel-export'),
                    'product_tax_class' => __('Product Tax Class', 'wordpress-excel-export'),
                    'product_variation_id' => __('Product Variation ID', 'wordpress-excel-export'),
                    'product_variation_attributes' => __('Product Variation Attributes', 'wordpress-excel-export'),
                    'product_categories' => __('Product Categories', 'wordpress-excel-export'),
                    'product_tags' => __('Product Tags', 'wordpress-excel-export'),
                    'product_weight' => __('Product Weight', 'wordpress-excel-export'),
                    'product_dimensions' => __('Product Dimensions', 'wordpress-excel-export'),
                    'product_meta' => __('Product Meta Data', 'wordpress-excel-export'),
                    'product_image_url' => __('Product Image URL', 'wordpress-excel-export'),
                    'product_stock_status' => __('Product Stock Status', 'wordpress-excel-export'),
                    'product_stock_quantity' => __('Product Stock Quantity', 'wordpress-excel-export'),
                    'product_type' => __('Product Type', 'wordpress-excel-export'),
                    'product_status' => __('Product Status', 'wordpress-excel-export'),
                    'product_regular_price' => __('Product Regular Price', 'wordpress-excel-export'),
                    'product_sale_price' => __('Product Sale Price', 'wordpress-excel-export'),
                    'product_cost' => __('Product Cost', 'wordpress-excel-export'),
                    'product_margin' => __('Product Margin', 'wordpress-excel-export'),
                    'product_margin_percentage' => __('Product Margin Percentage', 'wordpress-excel-export')
                )
            ),
            'line_item_information' => array(
                'label' => __('Line Item Information', 'wordpress-excel-export'),
                'columns' => array(
                    'line_item_id' => __('Line Item ID', 'wordpress-excel-export'),
                    'line_item_name' => __('Line Item Name', 'wordpress-excel-export'),
                    'line_item_quantity' => __('Line Item Quantity', 'wordpress-excel-export'),
                    'line_item_total' => __('Line Item Total', 'wordpress-excel-export'),
                    'line_item_subtotal' => __('Line Item Subtotal', 'wordpress-excel-export'),
                    'line_item_tax' => __('Line Item Tax', 'wordpress-excel-export'),
                    'line_item_tax_class' => __('Line Item Tax Class', 'wordpress-excel-export'),
                    'line_item_meta' => __('Line Item Meta Data', 'wordpress-excel-export'),
                    'line_item_sku' => __('Line Item SKU', 'wordpress-excel-export'),
                    'line_item_variation_id' => __('Line Item Variation ID', 'wordpress-excel-export'),
                    'line_item_variation_attributes' => __('Line Item Variation Attributes', 'wordpress-excel-export')
                )
            ),
            'tax_information' => array(
                'label' => __('Tax Information', 'wordpress-excel-export'),
                'columns' => array(
                    'tax_total' => __('Tax Total', 'wordpress-excel-export'),
                    'tax_subtotal' => __('Tax Subtotal', 'wordpress-excel-export'),
                    'tax_rate_id' => __('Tax Rate ID', 'wordpress-excel-export'),
                    'tax_rate_code' => __('Tax Rate Code', 'wordpress-excel-export'),
                    'tax_rate_name' => __('Tax Rate Name', 'wordpress-excel-export'),
                    'tax_rate_percent' => __('Tax Rate Percent', 'wordpress-excel-export'),
                    'tax_rate_compound' => __('Tax Rate Compound', 'wordpress-excel-export'),
                    'tax_rate_shipping' => __('Tax Rate Shipping', 'wordpress-excel-export'),
                    'tax_rate_order' => __('Tax Rate Order', 'wordpress-excel-export'),
                    'tax_rate_class' => __('Tax Rate Class', 'wordpress-excel-export')
                )
            ),
            'coupon_information' => array(
                'label' => __('Coupon Information', 'wordpress-excel-export'),
                'columns' => array(
                    'coupon_code' => __('Coupon Code', 'wordpress-excel-export'),
                    'coupon_amount' => __('Coupon Amount', 'wordpress-excel-export'),
                    'coupon_type' => __('Coupon Type', 'wordpress-excel-export'),
                    'coupon_description' => __('Coupon Description', 'wordpress-excel-export'),
                    'coupon_date_expires' => __('Coupon Expiry Date', 'wordpress-excel-export'),
                    'coupon_usage_count' => __('Coupon Usage Count', 'wordpress-excel-export'),
                    'coupon_individual_use' => __('Coupon Individual Use', 'wordpress-excel-export'),
                    'coupon_product_ids' => __('Coupon Product IDs', 'wordpress-excel-export'),
                    'coupon_excluded_product_ids' => __('Coupon Excluded Product IDs', 'wordpress-excel-export'),
                    'coupon_product_categories' => __('Coupon Product Categories', 'wordpress-excel-export'),
                    'coupon_excluded_product_categories' => __('Coupon Excluded Product Categories', 'wordpress-excel-export'),
                    'coupon_exclude_sale_items' => __('Coupon Exclude Sale Items', 'wordpress-excel-export'),
                    'coupon_minimum_amount' => __('Coupon Minimum Amount', 'wordpress-excel-export'),
                    'coupon_maximum_amount' => __('Coupon Maximum Amount', 'wordpress-excel-export'),
                    'coupon_email_restrictions' => __('Coupon Email Restrictions', 'wordpress-excel-export')
                )
            ),
            'shipping_information_detailed' => array(
                'label' => __('Shipping Information (Detailed)', 'wordpress-excel-export'),
                'columns' => array(
                    'shipping_method_id' => __('Shipping Method ID', 'wordpress-excel-export'),
                    'shipping_method_title' => __('Shipping Method Title', 'wordpress-excel-export'),
                    'shipping_method_cost' => __('Shipping Method Cost', 'wordpress-excel-export'),
                    'shipping_method_taxes' => __('Shipping Method Taxes', 'wordpress-excel-export'),
                    'shipping_method_total' => __('Shipping Method Total', 'wordpress-excel-export'),
                    'shipping_packages' => __('Shipping Packages', 'wordpress-excel-export'),
                    'shipping_weight' => __('Shipping Weight', 'wordpress-excel-export'),
                    'shipping_dimensions' => __('Shipping Dimensions', 'wordpress-excel-export'),
                    'shipping_zone' => __('Shipping Zone', 'wordpress-excel-export'),
                    'shipping_zone_id' => __('Shipping Zone ID', 'wordpress-excel-export')
                )
            ),
            'payment_information' => array(
                'label' => __('Payment Information', 'wordpress-excel-export'),
                'columns' => array(
                    'payment_method_id' => __('Payment Method ID', 'wordpress-excel-export'),
                    'payment_method_title' => __('Payment Method Title', 'wordpress-excel-export'),
                    'payment_method_description' => __('Payment Method Description', 'wordpress-excel-export'),
                    'payment_method_instructions' => __('Payment Method Instructions', 'wordpress-excel-export'),
                    'payment_method_icon' => __('Payment Method Icon', 'wordpress-excel-export'),
                    'payment_method_supports' => __('Payment Method Supports', 'wordpress-excel-export'),
                    'payment_method_enabled' => __('Payment Method Enabled', 'wordpress-excel-export'),
                    'payment_method_settings' => __('Payment Method Settings', 'wordpress-excel-export'),
                    'payment_method_meta' => __('Payment Method Meta Data', 'wordpress-excel-export')
                )
            ),
            'grading_information' => array(
                'label' => __('Grading Information (TGF)', 'wordpress-excel-export'),
                'columns' => array(
                    'tgf_line_number'          => __('Line Number', 'wordpress-excel-export'),
                    'tgf_service_level'        => __('Service Level', 'wordpress-excel-export'),
                    'tgf_extras'               => __('Extras (combined)', 'wordpress-excel-export'),
                    'tgf_extras_card_type'     => __('Card Type', 'wordpress-excel-export'),
                    'tgf_extras_card_extras'   => __('Card Extras', 'wordpress-excel-export'),
                    'tgf_extras_signatures'    => __('Signatures / Sketches', 'wordpress-excel-export'),
                    'tgf_extras_comic_extras'  => __('Comic Extras', 'wordpress-excel-export'),
                    'tgf_extras_bgs_subgrades' => __('BGS Sub-grades', 'wordpress-excel-export'),
                    'tgf_extras_tag_score'     => __('TAG Score', 'wordpress-excel-export'),
                    'tgf_item_name'            => __('Item Name', 'wordpress-excel-export'),
                    'tgf_item_set'             => __('Item Set / Publisher', 'wordpress-excel-export'),
                    'tgf_item_year'            => __('Year', 'wordpress-excel-export'),
                    'tgf_item_number'          => __('Item Number', 'wordpress-excel-export'),
                    'tgf_item_description'     => __('Description', 'wordpress-excel-export'),
                    'tgf_collectable_type'     => __('Collectable Type', 'wordpress-excel-export'),
                )
            )
        );
        
        // Add dynamic "Other" section with custom meta fields
        $other_columns = self::get_custom_meta_columns();
        if (!empty($other_columns)) {
            $columns['other'] = array(
                'label' => __('Other / Custom Fields', 'wordpress-excel-export'),
                'columns' => $other_columns
            );
            
            // Debug: Log the other section creation
            if (isset($_GET['debug_fields']) && current_user_can('manage_options')) {
                error_log('WEE DEBUG: Created "Other" section with ' . count($other_columns) . ' columns');
                error_log('WEE DEBUG: Sample other columns: ' . print_r(array_slice($other_columns, 0, 5, true), true));
            }
        } else {
            // Debug: Log why no other section was created
            if (isset($_GET['debug_fields']) && current_user_can('manage_options')) {
                error_log('WEE DEBUG: No "Other" section created - other_columns is empty');
            }
        }
        

        
        return $columns;
    }
    
    /**
     * Get custom meta fields that don't fit in other categories (optimized)
     */
    public static function get_custom_meta_columns($limit = 500) {
        global $wpdb;
        
        // Cache the results to avoid repeated database queries
        $cache_key = 'wee_custom_meta_columns_' . $limit;
        $cached_result = wp_cache_get($cache_key, 'wee_templates');
        
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        // Get meta keys from recent orders with optimized query
        $order_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = 'shop_order' 
            AND post_status NOT IN ('auto-draft', 'trash') 
            ORDER BY ID DESC LIMIT %d",
            $limit
        ));
        
        if (empty($order_ids)) {
            $empty_result = array();
            wp_cache_set($cache_key, $empty_result, 'wee_templates', 300); // Cache for 5 minutes
            return $empty_result;
        }
        
        // Use single optimized query to get both order and item meta keys
        $placeholders = implode(',', array_fill(0, count($order_ids), '%d'));
        
        // Combined query for better performance
        $combined_query = $wpdb->prepare(
            "(SELECT DISTINCT meta_key, 'order' as source 
             FROM {$wpdb->postmeta} 
             WHERE post_id IN ($placeholders) 
             AND meta_key NOT LIKE '_wp_%' 
             AND meta_key NOT LIKE '_oembed_%')
            UNION
            (SELECT DISTINCT oim.meta_key, 'item' as source
             FROM {$wpdb->prefix}woocommerce_order_itemmeta oim
             JOIN {$wpdb->prefix}woocommerce_order_items oi ON oim.order_item_id = oi.order_item_id
             WHERE oi.order_id IN ($placeholders)
             AND oim.meta_key NOT LIKE '_wp_%')
            ORDER BY meta_key ASC",
            array_merge($order_ids, $order_ids)
        );
        
        $meta_results = $wpdb->get_results($combined_query);
        $meta_keys = array_unique(wp_list_pluck($meta_results, 'meta_key'));
        
        // Store YWAPO addons separately to add them directly to the final result
        $ywapo_addons = array();
        
        // Exclude WooCommerce internal fields and essential system fields
        $excluded_keys = array(
            // WooCommerce internal fields (already covered by proper getters)
            '_billing_first_name', '_billing_last_name', '_billing_company', '_billing_address_1', 
            '_billing_address_2', '_billing_city', '_billing_state', '_billing_postcode', 
            '_billing_country', '_billing_email', '_billing_phone',
            '_shipping_first_name', '_shipping_last_name', '_shipping_company', '_shipping_address_1', 
            '_shipping_address_2', '_shipping_city', '_shipping_state', '_shipping_postcode', '_shipping_country',
            '_shipping_phone', '_date_completed', '_transaction_id', '_date_paid',
            '_customer_user_agent', '_customer_ip_address', '_cart_hash',
            '_order_key', '_customer_user', '_payment_method', '_payment_method_title',
            '_order_shipping', '_order_shipping_tax', '_order_tax', '_order_total',
            '_order_currency', '_created_via', '_order_version',
            
            // Essential WordPress fields
            '_edit_lock', '_edit_last'
        );
        
        // Minimal exclusion patterns - only the most obvious system stuff
        $excluded_patterns = array(
            '/^_wp_(?!cf_|custom_|meta_)/', // WordPress internal (but allow custom patterns)
            '/^_oembed_/',               // WordPress oEmbed cache
            '/^_thumbnail_id$/',         // Featured image
        );
        
        $custom_columns = array();
        
        // Add YWAPO addons FIRST with optimized query
        $yith_table = $wpdb->prefix . 'yith_wapo_addons';
        
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $yith_table)) == $yith_table) {
            // First, check what columns exist in the table
            $columns = $wpdb->get_col("DESCRIBE {$yith_table}");
            
            // Build the SELECT query based on available columns
            $select_fields = array('id');
            if (in_array('label', $columns)) {
                $select_fields[] = 'label';
            }
            if (in_array('name', $columns)) {
                $select_fields[] = 'name';
            }
            if (in_array('title', $columns)) {
                $select_fields[] = 'title';
            }
            if (in_array('type', $columns)) {
                $select_fields[] = 'type';
            }
            
            $select_clause = implode(', ', $select_fields);
            $yith_addons = $wpdb->get_results("SELECT {$select_clause} FROM {$yith_table} ORDER BY id", ARRAY_A);
            
            error_log('WEE DEBUG: Found ' . count($yith_addons) . ' YWAPO addons in database');
            error_log('WEE DEBUG: YWAPO addons: ' . print_r($yith_addons, true));
            
            // Add ALL YITH addon fields directly to custom_columns at the beginning
            foreach ($yith_addons as $addon) {
                $meta_key = 'ywapo-addon-' . $addon['id'];
                
                // Try different label fields in order of preference
                $label = '';
                if (!empty($addon['label'])) {
                    $label = $addon['label'];
                } elseif (!empty($addon['title'])) {
                    $label = $addon['title'];
                } elseif (!empty($addon['name'])) {
                    $label = $addon['name'];
                }
                
                if (empty($label)) {
                    $label = self::create_label_from_meta_key($meta_key);
                } else {
                    $label .= ' (YWAPO)';
                }
                
                $custom_columns['meta_' . $meta_key] = $label;
            }
        }
        
        // Process remaining meta keys efficiently with batch queries
        $filtered_keys = array();
        foreach ($meta_keys as $meta_key) {
            if (empty($meta_key) || in_array($meta_key, $excluded_keys)) {
                continue;
            }
            
            // Skip basic exclusion patterns
            $skip = false;
            foreach ($excluded_patterns as $pattern) {
                if (preg_match($pattern, $meta_key)) {
                    $skip = true;
                    break;
                }
            }
            if (!$skip) {
                $filtered_keys[] = $meta_key;
            }
        }
        
        // Batch check for meta values to reduce database queries
        if (!empty($filtered_keys)) {
            $keys_with_values = self::get_meta_keys_with_values($order_ids, $filtered_keys);
            
            foreach ($filtered_keys as $meta_key) {
                // For YWAPO fields, always include them (they're defined in the addon table)
                if (strpos($meta_key, 'ywapo-addon-') === 0 || in_array($meta_key, $keys_with_values)) {
                    $label = self::create_label_from_meta_key($meta_key);
                    $custom_columns['meta_' . $meta_key] = $label;
                }
            }
        }
        
        // Cache the result for 5 minutes to improve performance
        wp_cache_set($cache_key, $custom_columns, 'wee_templates', 300);
        
        return $custom_columns;
    }
    
    /**
     * Get available custom fields for grouping
     * Returns a list of custom fields that can be grouped together
     */
    public static function get_groupable_custom_fields() {
        $custom_columns = self::get_custom_meta_columns();
        $groupable_fields = array();
        
        foreach ($custom_columns as $column_key => $column_label) {
            $meta_key = str_replace('meta_', '', $column_key);
            
            // Skip WooCommerce internal fields
            $wc_internal_fields = array(
                '_shipping_phone', '_date_completed', '_transaction_id', '_date_paid',
                '_customer_user_agent', '_customer_ip_address', '_cart_hash',
                '_billing_first_name', '_billing_last_name', '_billing_company',
                '_billing_address_1', '_billing_address_2', '_billing_city',
                '_billing_state', '_billing_postcode', '_billing_country',
                '_billing_email', '_billing_phone', '_shipping_first_name',
                '_shipping_last_name', '_shipping_company', '_shipping_address_1',
                '_shipping_address_2', '_shipping_city', '_shipping_state',
                '_shipping_postcode', '_shipping_country', '_order_key',
                '_customer_user', '_payment_method', '_payment_method_title',
                '_order_shipping', '_order_shipping_tax', '_order_tax',
                '_order_total', '_order_currency', '_created_via', '_order_version'
            );
            
            if (!in_array($meta_key, $wc_internal_fields)) {
                $groupable_fields[$column_key] = array(
                    'meta_key' => $meta_key,
                    'label' => $column_label,
                    'type' => self::get_field_type($meta_key)
                );
            }
        }
        
        return $groupable_fields;
    }
    
    /**
     * Determine the type of a custom field based on its meta key
     */
    private static function get_field_type($meta_key) {
        // YWAPO fields
        if (strpos($meta_key, 'ywapo-addon-') === 0) {
            return 'ywapo';
        }
        
        // Plugin-specific patterns
        if (strpos($meta_key, 'plugin_') === 0) {
            return 'plugin';
        }
        
        // Generic patterns
        if (strpos($meta_key, '_custom') !== false) {
            return 'custom';
        }
        
        if (strpos($meta_key, '_extra') !== false) {
            return 'extra';
        }
        
        if (strpos($meta_key, '_addon') !== false) {
            return 'addon';
        }
        
        return 'other';
    }
    
    /**
     * Efficiently check which meta keys have values using batch queries
     */
    private static function get_meta_keys_with_values($order_ids, $meta_keys) {
        global $wpdb;
        
        if (empty($order_ids) || empty($meta_keys)) {
            return array();
        }
        
        $placeholders_orders = implode(',', array_fill(0, count($order_ids), '%d'));
        $placeholders_keys = implode(',', array_fill(0, count($meta_keys), '%s'));
        
        // Single query to check both order meta and item meta
        $query = $wpdb->prepare(
            "(SELECT DISTINCT meta_key 
             FROM {$wpdb->postmeta} 
             WHERE post_id IN ($placeholders_orders) 
             AND meta_key IN ($placeholders_keys)
             AND meta_value != '' 
             AND meta_value IS NOT NULL)
            UNION
            (SELECT DISTINCT oim.meta_key 
             FROM {$wpdb->prefix}woocommerce_order_itemmeta oim
             JOIN {$wpdb->prefix}woocommerce_order_items oi ON oim.order_item_id = oi.order_item_id
             WHERE oi.order_id IN ($placeholders_orders) 
             AND oim.meta_key IN ($placeholders_keys)
             AND oim.meta_value != '' 
             AND oim.meta_value IS NOT NULL)",
            array_merge($order_ids, $meta_keys, $order_ids, $meta_keys)
        );
        
        return $wpdb->get_col($query);
    }
    
    /**
     * Create a readable label from a meta key
     */
    private static function create_label_from_meta_key($meta_key) {
        global $wpdb;
        
        // Remove leading underscore
        $label = ltrim($meta_key, '_');
        
        // Special handling for YWAPO addon fields - get actual label from database
        if (preg_match('/^ywapo-addon-(\d+)$/', $meta_key, $matches)) {
            $addon_id = $matches[1];
            $yith_table = $wpdb->prefix . 'yith_wapo_addons';
            
            if ($wpdb->get_var("SHOW TABLES LIKE '$yith_table'") == $yith_table) {
                // Check what columns exist first
                $columns = $wpdb->get_col("DESCRIBE {$yith_table}");
                $label_field = 'id'; // fallback
                
                if (in_array('label', $columns)) {
                    $label_field = 'label';
                } elseif (in_array('title', $columns)) {
                    $label_field = 'title';
                } elseif (in_array('name', $columns)) {
                    $label_field = 'name';
                }
                
                $addon_label = $wpdb->get_var($wpdb->prepare(
                    "SELECT {$label_field} FROM {$yith_table} WHERE id = %d",
                    $addon_id
                ));
                
                if ($addon_label && $label_field !== 'id') {
                    return $addon_label . ' (YWAPO)';
                }
            }
            
            // Fallback if database lookup fails
            $label = 'YWAPO Add-on ' . $addon_id . ' (Custom)';
            return $label;
        }
        
        // Special handling for YWAPO addon fields with old pattern
        if (preg_match('/^ywapo-addon-(\d+)-(\d+)$/', $meta_key, $matches)) {
            $label = 'YWAPO Add-on ' . $matches[1] . '-' . $matches[2] . ' (Custom)';
            return $label;
        }
        
        // Special handling for other known patterns
        if (strpos($label, 'ywapo') === 0) {
            $label = 'YITH ' . ucwords(str_replace(array('ywapo', '_', '-'), array('', ' ', ' '), $label));
        } elseif (strpos($label, 'yith') === 0) {
            $label = 'YITH ' . ucwords(str_replace(array('yith', '_', '-'), array('', ' ', ' '), $label));
        } else {
            // Replace underscores and hyphens with spaces
            $label = str_replace(array('_', '-'), ' ', $label);
            
            // Convert to title case
            $label = ucwords($label);
        }
        
        // Add "(Custom)" suffix to distinguish from built-in fields
        if (strpos($label, '(Custom)') === false) {
            $label .= ' (Custom)';
        }
        
        return $label;
    }
    
    /**
     * Update an existing template
     */
    public static function update_template($template_id, $name, $columns = array(), $custom_fields = array(), $filters = array(), $column_names = array(), $field_groups = array(), $combined_fields = array(), $description = '', $column_visibility = array(), $column_order = array()) {
        global $wpdb;
        
        error_log('WEE DEBUG: update_template called with combined_fields: ' . print_r($combined_fields, true));
        
        self::init();
        
        // Check if template exists
        $existing_template = self::get_template($template_id);
        if (!$existing_template) {
            return array('success' => false, 'message' => 'Template not found');
        }
        
        // Prepare data for update
        $data = array(
            'name' => sanitize_text_field($name),
            'description' => sanitize_textarea_field($description),
            'columns' => is_array($columns) ? json_encode($columns) : $columns,
            'custom_fields' => is_array($custom_fields) ? json_encode($custom_fields) : $custom_fields,
            'filters' => is_array($filters) ? json_encode($filters) : $filters,
            'column_names' => is_array($column_names) ? json_encode($column_names) : $column_names,
            'field_groups' => is_array($field_groups) ? json_encode($field_groups) : $field_groups,
            'combined_fields' => is_array($combined_fields) ? json_encode($combined_fields) : $combined_fields,
            'column_visibility' => is_array($column_visibility) ? json_encode($column_visibility) : $column_visibility,
            'column_order' => is_array($column_order) ? json_encode($column_order) : $column_order,
            'updated_at' => current_time('mysql')
        );
        
        error_log('WEE DEBUG: Update data being prepared: ' . print_r($data, true));
        error_log('WEE DEBUG: Combined fields JSON for update: ' . $data['combined_fields']);
        error_log('WEE DEBUG: Column order JSON for update: ' . $data['column_order']);
        
        // Check if combined_fields column exists, if not add it
        $table_name = self::$table_name;
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'combined_fields'");
        
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN combined_fields TEXT AFTER field_groups");
        }
        
        // Check if column_visibility column exists, if not add it
        $visibility_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'column_visibility'");
        
        if (empty($visibility_exists)) {
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN column_visibility TEXT AFTER combined_fields");
        }
        
        // Check if column_order column exists, if not add it
        $order_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'column_order'");
        
        if (empty($order_exists)) {
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN column_order TEXT AFTER column_visibility");
        }
        
        // Update the template
        error_log('WEE DEBUG: About to update template in database. Table: ' . $table_name . ', Template ID: ' . $template_id);
        $result = $wpdb->update(
            $table_name,
            $data,
            array('id' => $template_id),
            array(
                '%s', // name
                '%s', // description
                '%s', // columns
                '%s', // custom_fields
                '%s', // filters
                '%s', // column_names
                '%s', // field_groups
                '%s', // combined_fields
                '%s', // column_visibility
                '%s'  // updated_at
            ),
            array('%d') // where id
        );
        
        error_log('WEE DEBUG: Database update result: ' . print_r($result, true));
        error_log('WEE DEBUG: Last error: ' . $wpdb->last_error);
        
        if ($result !== false) {
            return array('success' => true, 'message' => 'Template updated successfully', 'template_id' => $template_id);
        } else {
            return array('success' => false, 'message' => 'Failed to update template: ' . $wpdb->last_error);
        }
    }
} 