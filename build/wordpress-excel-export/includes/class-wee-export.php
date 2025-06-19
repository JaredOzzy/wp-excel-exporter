<?php
/**
 * Export Class
 */
class WEE_Export {
    
    /**
     * Export orders to Excel/CSV with performance monitoring
     */
    public function export_orders($template_id, $date_from, $date_to, $filters = array(), $format = 'xlsx') {
        // Start performance monitoring
        $start_time = microtime(true);
        $start_memory = memory_get_usage();
        
        try {
            // Get template
            $template = WEE_Templates::get_template($template_id);
            if (!$template) {
                throw new Exception('Template not found');
            }
            
            // Merge template filters with passed filters
            $template_filters = array();
            if (!empty($template['filters'])) {
                if (is_string($template['filters'])) {
                    $template_filters = json_decode($template['filters'], true);
                    if (!is_array($template_filters)) {
                        $template_filters = array();
                    }
                } elseif (is_array($template['filters'])) {
                    $template_filters = $template['filters'];
                }
            }
            $filters = array_merge($template_filters, $filters);
            
            // Get orders data with optimized queries
            $orders = $this->get_orders_data($date_from, $date_to, $filters);
            
            if (empty($orders)) {
                throw new Exception('No orders found for the specified date range and filters. Please adjust your search criteria and try again.');
            }
            
            // Generate file
            $file_url = $this->generate_file($orders, $template, $format);
            
            if (!$file_url) {
                throw new Exception('Failed to generate export file');
            }
            
            // Log performance metrics
            $end_time = microtime(true);
            $end_memory = memory_get_usage();
            $execution_time = round($end_time - $start_time, 2);
            $memory_used = round(($end_memory - $start_memory) / 1024 / 1024, 2);
            $orders_count = count($orders);
            
            error_log("WEE Export Performance: {$orders_count} orders exported in {$execution_time}s using {$memory_used}MB memory");
            
            // Store performance metrics for admin dashboard
            $this->store_performance_metrics($orders_count, $execution_time, $memory_used);
            
            // Return success result with file information
            return array(
                'success' => true,
                'file_url' => $file_url,
                'file_name' => $template['name'] . '_' . date('Y-m-d_H-i-s') . '.csv',
                'orders_count' => $orders_count,
                'execution_time' => $execution_time,
                'memory_used' => $memory_used,
                'message' => sprintf(__('Export completed successfully. %d orders exported in %s seconds.', 'wordpress-excel-export'), $orders_count, $execution_time)
            );
            
        } catch (Exception $e) {
            error_log('WEE Export Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Store performance metrics for monitoring
     */
    private function store_performance_metrics($orders_count, $execution_time, $memory_used) {
        $metrics = get_option('wee_performance_metrics', array());
        
        // Keep only last 10 exports for monitoring
        if (count($metrics) >= 10) {
            array_shift($metrics);
        }
        
        $metrics[] = array(
            'timestamp' => current_time('mysql'),
            'orders_count' => $orders_count,
            'execution_time' => $execution_time,
            'memory_used' => $memory_used,
            'avg_time_per_order' => $orders_count > 0 ? round($execution_time / $orders_count, 4) : 0
        );
        
        update_option('wee_performance_metrics', $metrics);
    }
    
    /**
     * Get performance metrics for admin display
     */
    public static function get_performance_metrics() {
        $metrics = get_option('wee_performance_metrics', array());
        
        if (empty($metrics)) {
            return array(
                'avg_execution_time' => 0,
                'avg_memory_usage' => 0,
                'avg_orders_per_export' => 0,
                'recent_exports' => array()
            );
        }
        
        $total_time = array_sum(wp_list_pluck($metrics, 'execution_time'));
        $total_memory = array_sum(wp_list_pluck($metrics, 'memory_used'));
        $total_orders = array_sum(wp_list_pluck($metrics, 'orders_count'));
        $count = count($metrics);
        
        return array(
            'avg_execution_time' => round($total_time / $count, 2),
            'avg_memory_usage' => round($total_memory / $count, 2),
            'avg_orders_per_export' => round($total_orders / $count, 0),
            'recent_exports' => array_reverse($metrics) // Most recent first
        );
    }
    
    /**
     * Get orders data with optimized queries
     */
    private function get_orders_data($date_from, $date_to, $filters = array()) {
        global $wpdb;
        
        // Build optimized query with proper indexes
        $joins = array();
        $where_conditions = array();
        $where_values = array();
        
        // Date range filter with index optimization
        if ($date_from) {
            $where_conditions[] = "p.post_date >= %s";
            $where_values[] = $date_from . ' 00:00:00';
        }
        if ($date_to) {
            $where_conditions[] = "p.post_date <= %s";
            $where_values[] = $date_to . ' 23:59:59';
        }
        
        // Order status filter with optimized IN clause
        if (!empty($filters['order_status'])) {
            $status_placeholders = implode(',', array_fill(0, count($filters['order_status']), '%s'));
            $where_conditions[] = "p.post_status IN ($status_placeholders)";
            $where_values = array_merge($where_values, $filters['order_status']);
        }
        
        // Product search optimization - use single JOIN instead of multiple queries
        if (!empty($filters['product_search'])) {
            $joins['product_search'] = "
                LEFT JOIN {$wpdb->prefix}woocommerce_order_items oi_prod ON p.ID = oi_prod.order_id AND oi_prod.order_item_type = 'line_item'
                LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_prod ON oi_prod.order_item_id = oim_prod.order_item_id AND oim_prod.meta_key = '_product_id'
                LEFT JOIN {$wpdb->posts} prod ON CAST(oim_prod.meta_value AS UNSIGNED) = prod.ID
            ";
            
            $search_term = '%' . $wpdb->esc_like($filters['product_search']) . '%';
            
            // Check if search term is numeric for product ID search
            if (is_numeric($filters['product_search'])) {
                $where_conditions[] = "(prod.post_title LIKE %s OR prod.ID = %d OR oi_prod.order_item_name LIKE %s)";
                $where_values[] = $search_term;
                $where_values[] = intval($filters['product_search']); // Numeric ID match
                $where_values[] = $search_term;
            } else {
                // For non-numeric searches, skip the ID comparison
                $where_conditions[] = "(prod.post_title LIKE %s OR oi_prod.order_item_name LIKE %s)";
                $where_values[] = $search_term;
                $where_values[] = $search_term;
            }
        }
        
        // Category filter optimization
        if (!empty($filters['product_categories'])) {
            $cat_placeholders = implode(',', array_fill(0, count($filters['product_categories']), '%d'));
            $joins['categories'] = "
                LEFT JOIN {$wpdb->prefix}woocommerce_order_items oi_cat ON p.ID = oi_cat.order_id AND oi_cat.order_item_type = 'line_item'
                LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_cat ON oi_cat.order_item_id = oim_cat.order_item_id AND oim_cat.meta_key = '_product_id'
                LEFT JOIN {$wpdb->term_relationships} tr ON CAST(oim_cat.meta_value AS UNSIGNED) = tr.object_id
                LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'product_cat'
            ";
            $where_conditions[] = "tt.term_id IN ($cat_placeholders)";
            $where_values = array_merge($where_values, $filters['product_categories']);
        }
        
        // Payment method filter
        if (!empty($filters['payment_method'])) {
            $joins['payment'] = "LEFT JOIN {$wpdb->postmeta} pm_payment ON p.ID = pm_payment.post_id AND pm_payment.meta_key = '_payment_method'";
            $where_conditions[] = "pm_payment.meta_value = %s";
            $where_values[] = $filters['payment_method'];
        }
        
        // Order total range filter with numeric optimization
        if (!empty($filters['order_total_min']) || !empty($filters['order_total_max'])) {
            $joins['total'] = "LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'";
            
            if (!empty($filters['order_total_min'])) {
                $where_conditions[] = "CAST(pm_total.meta_value AS DECIMAL(10,2)) >= %f";
                $where_values[] = floatval($filters['order_total_min']);
            }
            if (!empty($filters['order_total_max'])) {
                $where_conditions[] = "CAST(pm_total.meta_value AS DECIMAL(10,2)) <= %f";
                $where_values[] = floatval($filters['order_total_max']);
            }
        }
        
        // Custom meta filter
        if (!empty($filters['custom_meta_key']) && !empty($filters['custom_meta_value'])) {
            $joins['custom'] = "LEFT JOIN {$wpdb->postmeta} pm_custom ON p.ID = pm_custom.post_id AND pm_custom.meta_key = %s";
            $where_values[] = $filters['custom_meta_key'];
            
            $operator = !empty($filters['custom_meta_operator']) ? $filters['custom_meta_operator'] : '=';
            
            switch ($operator) {
                case 'LIKE':
                case 'NOT LIKE':
                    $where_conditions[] = "pm_custom.meta_value $operator %s";
                    $where_values[] = '%' . $wpdb->esc_like($filters['custom_meta_value']) . '%';
                    break;
                case '>':
                case '>=':
                case '<':
                case '<=':
                    $where_conditions[] = "CAST(pm_custom.meta_value AS DECIMAL(10,2)) $operator %f";
                    $where_values[] = floatval($filters['custom_meta_value']);
                    break;
                default:
                    $where_conditions[] = "pm_custom.meta_value $operator %s";
                    $where_values[] = $filters['custom_meta_value'];
                    break;
            }
        }
        
        // Ensure post_type condition with index optimization
        $where_conditions[] = "p.post_type = 'shop_order'";
        
        // Build optimized query
        $joins_clause = !empty($joins) ? ' ' . implode(' ', array_values($joins)) : '';
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        // Use covering index query - only get what we need
        $query = "
            SELECT DISTINCT p.ID as order_id
            FROM {$wpdb->posts} p
            $joins_clause
            $where_clause
            ORDER BY p.post_date DESC
        ";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        error_log('WEE Export: Optimized SQL query: ' . $query);
        
        $order_ids = $wpdb->get_col($query);
        
        if ($wpdb->last_error) {
            error_log('WEE Export: SQL Error: ' . $wpdb->last_error);
            return array();
        }
        
        error_log('WEE Export: Found ' . count($order_ids) . ' orders');
        
        // Batch process orders for better memory management
        return $this->get_orders_batch($order_ids);
    }
    
    /**
     * Process orders in batches for better performance and memory management
     */
    private function get_orders_batch($order_ids, $batch_size = 50) {
        $detailed_orders = array();
        $batches = array_chunk($order_ids, $batch_size);
        
        foreach ($batches as $batch) {
            // Preload all order objects in batch
            $orders = array();
            foreach ($batch as $order_id) {
                $order = wc_get_order($order_id);
                if ($order) {
                    $orders[$order_id] = $order;
                }
            }
            
            // Preload all meta data in single queries
            $this->preload_order_meta($batch);
            $this->preload_order_item_meta($batch);
            
            // Process each order with preloaded data
            foreach ($orders as $order_id => $order) {
                $order_data = $this->get_order_details_optimized($order);
                if ($order_data) {
                    $detailed_orders[] = $order_data;
                }
            }
            
            // Clear memory
            unset($orders);
            gc_collect_cycles();
        }
        
        return $detailed_orders;
    }
    
    /**
     * Preload order meta data to reduce database queries
     */
    private function preload_order_meta($order_ids) {
        global $wpdb;
        
        if (empty($order_ids)) return;
        
        $placeholders = implode(',', array_fill(0, count($order_ids), '%d'));
        $query = $wpdb->prepare(
            "SELECT post_id, meta_key, meta_value 
            FROM {$wpdb->postmeta} 
            WHERE post_id IN ($placeholders)",
            ...$order_ids
        );
        
        $meta_data = $wpdb->get_results($query);
        
        // Cache meta data for quick access
        foreach ($meta_data as $meta) {
            wp_cache_set("order_meta_{$meta->post_id}_{$meta->meta_key}", $meta->meta_value, 'wee_export');
        }
    }
    
    /**
     * Preload order item meta data for YWAPO and other custom fields
     */
    private function preload_order_item_meta($order_ids) {
        global $wpdb;
        
        if (empty($order_ids)) return;
        
        $placeholders = implode(',', array_fill(0, count($order_ids), '%d'));
        $query = $wpdb->prepare(
            "SELECT oi.order_id, oim.meta_key, oim.meta_value 
            FROM {$wpdb->prefix}woocommerce_order_items oi
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
            WHERE oi.order_id IN ($placeholders)
            AND (oim.meta_key LIKE 'ywapo%' OR oim.meta_key LIKE '_ywapo%')",
            ...$order_ids
        );
        
        $item_meta_data = $wpdb->get_results($query);
        
        // Cache item meta data grouped by order
        $grouped_meta = array();
        foreach ($item_meta_data as $meta) {
            if (!isset($grouped_meta[$meta->order_id])) {
                $grouped_meta[$meta->order_id] = array();
            }
            if (!isset($grouped_meta[$meta->order_id][$meta->meta_key])) {
                $grouped_meta[$meta->order_id][$meta->meta_key] = array();
            }
            $grouped_meta[$meta->order_id][$meta->meta_key][] = $meta->meta_value;
        }
        
        // Cache grouped data
        foreach ($grouped_meta as $order_id => $meta_data) {
            wp_cache_set("order_item_meta_{$order_id}", $meta_data, 'wee_export');
        }
    }
    
    /**
     * Optimized order details retrieval using preloaded data
     */
    private function get_order_details_optimized($order) {
        if (!$order) return false;
        
        $order_id = $order->get_id();
        
        // Use WooCommerce's built-in methods which are already optimized
        $order_data = array(
            'order_id' => $order_id,
            'order_edit_link' => admin_url('post.php?post=' . $order_id . '&action=edit'),
            'order_date' => $order->get_date_created() ? $order->get_date_created()->format('Y-m-d H:i:s') : '',
            'order_status' => $order->get_status(),
            'order_total' => $order->get_total(),
            'order_subtotal' => $order->get_subtotal(),
            'order_tax_total' => $order->get_total_tax(),
            'order_shipping_total' => $order->get_shipping_total(),
            'order_discount_total' => $order->get_discount_total(),
            'order_currency' => $order->get_currency(),
            'order_payment_method' => $order->get_payment_method(),
            'order_payment_method_title' => $order->get_payment_method_title(),
            'order_items_count' => $order->get_item_count(),
            'order_created_via' => $order->get_created_via(),
            'order_customer_note' => $order->get_customer_note(),
            'order_transaction_id' => $order->get_transaction_id(),
            'order_ip_address' => $order->get_customer_ip_address(),
            'order_user_agent' => $order->get_customer_user_agent(),
            'order_date_paid' => $order->get_date_paid() ? $order->get_date_paid()->format('Y-m-d H:i:s') : '',
            'order_date_completed' => $order->get_date_completed() ? $order->get_date_completed()->format('Y-m-d H:i:s') : '',
            'order_date_modified' => $order->get_date_modified() ? $order->get_date_modified()->format('Y-m-d H:i:s') : '',
        );
        
        // Customer information
        $order_data['customer_id'] = $order->get_customer_id();
        $order_data['customer_name'] = $order->get_formatted_billing_full_name();
        $order_data['customer_first_name'] = $order->get_billing_first_name();
        $order_data['customer_last_name'] = $order->get_billing_last_name();
        $order_data['customer_email'] = $order->get_billing_email();
        $order_data['customer_phone'] = $order->get_billing_phone();
        
        // Billing information
        $order_data['billing_first_name'] = $order->get_billing_first_name();
        $order_data['billing_last_name'] = $order->get_billing_last_name();
        $order_data['billing_company'] = $order->get_billing_company();
        $order_data['billing_address_1'] = $order->get_billing_address_1();
        $order_data['billing_address_2'] = $order->get_billing_address_2();
        $order_data['billing_city'] = $order->get_billing_city();
        $order_data['billing_state'] = $order->get_billing_state();
        $order_data['billing_postcode'] = $order->get_billing_postcode();
        $order_data['billing_country'] = $order->get_billing_country();
        $order_data['billing_email'] = $order->get_billing_email();
        $order_data['billing_phone'] = $order->get_billing_phone();
        $order_data['billing_address'] = $order->get_formatted_billing_address();
        
        // Shipping information
        $order_data['shipping_first_name'] = $order->get_shipping_first_name();
        $order_data['shipping_last_name'] = $order->get_shipping_last_name();
        $order_data['shipping_company'] = $order->get_shipping_company();
        $order_data['shipping_address_1'] = $order->get_shipping_address_1();
        $order_data['shipping_address_2'] = $order->get_shipping_address_2();
        $order_data['shipping_city'] = $order->get_shipping_city();
        $order_data['shipping_state'] = $order->get_shipping_state();
        $order_data['shipping_postcode'] = $order->get_shipping_postcode();
        $order_data['shipping_country'] = $order->get_shipping_country();
        $order_data['shipping_address'] = $order->get_formatted_shipping_address();
        
        // Add custom meta fields using preloaded data
        $order_data = $this->add_custom_meta_fields_optimized($order_data, $order);
        
        return $order_data;
    }
    
    /**
     * Optimized custom meta fields retrieval using cached data
     */
    private function add_custom_meta_fields_optimized($order_data, $order) {
        $custom_columns = WEE_Templates::get_custom_meta_columns();
        $order_id = $order->get_id();
        
        // WooCommerce internal fields that should use proper getters instead of get_meta()
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
        
        // Get preloaded item meta data
        $item_meta_cache = wp_cache_get("order_item_meta_{$order_id}", 'wee_export');
        
        foreach ($custom_columns as $column_key => $column_label) {
            $meta_key = str_replace('meta_', '', $column_key);
            $meta_value = '';
            
            // Skip WooCommerce internal fields to avoid warnings
            if (in_array($meta_key, $wc_internal_fields)) {
                continue;
            }
            
            // Check if this is a YWAPO field (use cached data)
            if (strpos($meta_key, 'ywapo-addon-') === 0 || strpos($meta_key, '_ywapo') === 0) {
                if ($item_meta_cache && isset($item_meta_cache[$meta_key])) {
                    $meta_value = implode(' | ', array_unique($item_meta_cache[$meta_key]));
                }
            } else {
                // Try cached order meta first
                $cached_value = wp_cache_get("order_meta_{$order_id}_{$meta_key}", 'wee_export');
                if ($cached_value !== false) {
                    $meta_value = $cached_value;
                } else {
                    // For non-internal fields, use get_meta() safely
                    $meta_value = $order->get_meta($meta_key);
                }
            }
            
            // Convert complex values to string
            if (is_array($meta_value) || is_object($meta_value)) {
                $meta_value = json_encode($meta_value);
            }
            
            $order_data[$column_key] = $meta_value ? $meta_value : '';
        }
        
        return $order_data;
    }
    
    /**
     * Get order notes
     */
    private function get_order_notes($order_id) {
        $notes = wc_get_order_notes(array('order_id' => $order_id));
        $note_texts = array();
        foreach ($notes as $note) {
            $note_texts[] = $note->content;
        }
        return implode(' | ', $note_texts);
    }
    
    /**
     * Get order meta
     */
    private function get_order_meta($order) {
        $meta_data = $order->get_meta_data();
        $meta_array = array();
        foreach ($meta_data as $meta) {
            $meta_array[] = $meta->key . ': ' . $meta->value;
        }
        return implode(' | ', $meta_array);
    }
    
    /**
     * Get order dimensions
     */
    private function get_order_dimensions($order) {
        $dimensions = array(
            'length' => 0,
            'width'  => 0,
            'height' => 0
        );
        
        // Sum up dimensions from all line items
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && $product->has_dimensions()) {
                $dimensions['length'] += $product->get_length() * $item->get_quantity();
                $dimensions['width']  += $product->get_width() * $item->get_quantity();
                $dimensions['height'] += $product->get_height() * $item->get_quantity();
            }
        }
        
        // Return as a formatted string: L x W x H
        return sprintf('%s x %s x %s',
            $dimensions['length'] . ' cm',
            $dimensions['width'] . ' cm',
            $dimensions['height'] . ' cm'
        );
    }
    
    /**
     * Get customer meta
     */
    private function get_customer_meta($customer_id) {
        $meta = get_user_meta($customer_id);
        $meta_array = array();
        foreach ($meta as $key => $values) {
            if (!in_array($key, array('first_name', 'last_name', 'nickname', 'description'))) {
                $meta_array[] = $key . ': ' . implode(', ', $values);
            }
        }
        return implode(' | ', $meta_array);
    }
    
    /**
     * Get variation attributes as a string
     */
    private function get_variation_attributes($item) {
        $formatted_attributes = array();
        
        foreach ( $item->get_meta_data() as $meta ) {
            // Skip hidden meta based on leading underscore
            if (strpos($meta->key, '_') === 0) {
                continue;
            }

            // Create a human-readable label from the key. Not perfect, but compatible.
            $label = str_replace('pa_', '', $meta->key);
            $label = ucwords(str_replace('_', ' ', $label));

            $formatted_attributes[] = $label . ': ' . $meta->value;
        }
        
        return implode('; ', $formatted_attributes);
    }
    
    /**
     * Get product categories
     */
    private function get_product_categories($product) {
        $categories = get_the_terms($product->get_id(), 'product_cat');
        if ($categories && !is_wp_error($categories)) {
            $category_names = array();
            foreach ($categories as $category) {
                $category_names[] = $category->name;
            }
            return implode(', ', $category_names);
        }
        return '';
    }
    
    /**
     * Get product tags
     */
    private function get_product_tags($product) {
        $tags = get_the_terms($product->get_id(), 'product_tag');
        if ($tags && !is_wp_error($tags)) {
            $tag_names = array();
            foreach ($tags as $tag) {
                $tag_names[] = $tag->name;
            }
            return implode(', ', $tag_names);
        }
        return '';
    }
    
    /**
     * Get product dimensions as a string
     */
    private function get_product_dimensions($product) {
        if ($product && $product->has_dimensions()) {
            $length = $product->get_length() ?: '0';
            $width = $product->get_width() ?: '0';
            $height = $product->get_height() ?: '0';
            return $length . 'x' . $width . 'x' . $height;
        }
        return '';
    }
    
    /**
     * Get product meta
     */
    private function get_product_meta($product) {
        $meta_data = $product->get_meta_data();
        $meta_array = array();
        foreach ($meta_data as $meta) {
            $value = is_scalar($meta->value) ? $meta->value : (is_array($meta->value) || is_object($meta->value) ? json_encode($meta->value) : '');
            $meta_array[] = $meta->key . ': ' . $value;
        }
        return implode(' | ', $meta_array);
    }
    
    /**
     * Calculate product margin
     */
    private function calculate_product_margin($product) {
        $cost = $product->get_meta('_cost');
        $price = $product->get_price();
        if ($cost && $price) {
            return $price - $cost;
        }
        return '';
    }
    
    /**
     * Calculate product margin percentage
     */
    private function calculate_product_margin_percentage($product) {
        $cost = $product->get_meta('_cost');
        $price = $product->get_price();
        if ($cost && $price && $cost > 0) {
            return round((($price - $cost) / $cost) * 100, 2) . '%';
        }
        return '';
    }
    
    /**
     * Format address
     */
    private function format_address($address) {
        if (empty($address)) {
            return '';
        }
        
        $parts = array();
        if (!empty($address['address_1'])) $parts[] = $address['address_1'];
        if (!empty($address['address_2'])) $parts[] = $address['address_2'];
        if (!empty($address['city'])) $parts[] = $address['city'];
        if (!empty($address['state'])) $parts[] = $address['state'];
        if (!empty($address['postcode'])) $parts[] = $address['postcode'];
        if (!empty($address['country'])) $parts[] = $address['country'];
        
        return implode(', ', $parts);
    }
    
    /**
     * Generate file (Excel or CSV) with proper formatting
     */
    private function generate_file($orders, $template, $format = 'xlsx') {
        // Flatten orders array (in case of multiple products per order)
        $flattened_orders = array();
        foreach ($orders as $order) {
            if (is_array($order) && isset($order[0])) {
                $flattened_orders = array_merge($flattened_orders, $order);
            } else {
                $flattened_orders[] = $order;
            }
        }
        
        // Create uploads directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $wee_dir = $upload_dir['basedir'] . '/wee-exports/';
        if (!file_exists($wee_dir)) {
            wp_mkdir_p($wee_dir);
        }
        
        // Generate filename with template name and date
        $template_name = sanitize_file_name($template['name']);
        $date_suffix = date('Y-m-d_H-i-s');
        $filename = $template_name . '_' . $date_suffix . '.csv';
        $filepath = $wee_dir . $filename;
        
        // Prepare headers and data
        $headers = array();
        $data = array();
        $column_keys = $template['columns'];
        $available_columns = WEE_Templates::get_available_columns();
        
        // Build headers - use custom names if available
        $custom_column_names = array();
        if (!empty($template['column_names'])) {
            if (is_string($template['column_names'])) {
                $custom_column_names = json_decode($template['column_names'], true);
                if (!is_array($custom_column_names)) {
                    $custom_column_names = array();
                }
            } elseif (is_array($template['column_names'])) {
                $custom_column_names = $template['column_names'];
            }
        }
        
        foreach ($column_keys as $column_key) {
            // Use custom name if available
            if (!empty($custom_column_names[$column_key])) {
                $headers[] = $custom_column_names[$column_key];
            } else {
                // Fallback to default column name
                $found = false;
                foreach ($available_columns as $cat) {
                    if (isset($cat['columns'][$column_key])) {
                        $headers[] = $cat['columns'][$column_key];
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $headers[] = $column_key; // Ultimate fallback
                }
            }
        }
        
        // Add custom fields headers
        if (!empty($template['custom_fields'])) {
            foreach ($template['custom_fields'] as $custom) {
                $headers[] = $custom['column_name'];
            }
        }
        
        // Add headers to data array
        $data[] = $headers;
        
        // Build data rows
        foreach ($flattened_orders as $order) {
            $row = array();
            
            // Standard columns
            foreach ($column_keys as $column_key) {
                $value = isset($order[$column_key]) ? $order[$column_key] : '';
                
                // Handle HYPERLINK formulas for CSV - just extract the display text/URL
                if ($column_key === 'order_edit_link' && !empty($value) && strpos($value, '=HYPERLINK(') === 0) {
                    // Extract URL from HYPERLINK formula for CSV
                    if (preg_match('/=HYPERLINK\("([^"]+)","([^"]+)"\)/', $value, $matches)) {
                        $url = $matches[1];
                        $display_text = $matches[2];
                        $value = $url; // Use the actual URL for CSV
                    } else {
                        // Fallback - just use the display text
                        $value = preg_replace('/=HYPERLINK\("[^"]+","([^"]+)"\)/', '$1', $value);
                    }
                }
                
                $row[] = $value;
            }
            
            // Custom fields
            if (!empty($template['custom_fields'])) {
                $order_id = isset($order['order_id']) ? $order['order_id'] : null;
                foreach ($template['custom_fields'] as $custom) {
                    $meta_value = '';
                    if ($order_id && !empty($custom['meta_key'])) {
                        $meta_value = get_post_meta($order_id, $custom['meta_key'], true);
                    }
                    $row[] = $meta_value;
                }
            }
            
            $data[] = $row;
        }
        
        // Generate file - always use CSV for reliability
        return $this->create_csv_file($data, $filepath, $template['name']);
    }
    
    /**
     * Get upload directory
     */
    private function get_upload_dir() {
        return wp_upload_dir();
    }
    
    /**
     * Get products for filter dropdown
     */
    public static function get_products() {
        if (!class_exists('WC_Product')) {
            return array();
        }
        
        $products = wc_get_products(array(
            'limit' => -1,
            'status' => 'publish'
        ));
        
        $product_list = array();
        foreach ($products as $product) {
            $product_list[] = array(
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'sku' => $product->get_sku()
            );
        }
        
        return $product_list;
    }
    
    /**
     * Search products
     * 
     * @param string $query Search query
     * @return array Array of matching products
     */
    public static function search_products($query) {
        global $wpdb;
        
        // Search by ID if query is numeric
        if (is_numeric($query)) {
            $product = wc_get_product($query);
            if ($product) {
                return array(array(
                    'id' => $product->get_id(),
                    'name' => $product->get_name(),
                    'sku' => $product->get_sku()
                ));
            }
        }
        
        // Search by SKU
        $sku_query = $wpdb->prepare("
            SELECT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_sku' 
            AND meta_value LIKE %s
        ", '%' . $wpdb->esc_like($query) . '%');
        
        // Search products by title, SKU, or ID
        $products_query = $wpdb->prepare("
            SELECT ID, post_title 
            FROM {$wpdb->posts} p
            WHERE (
                p.ID IN ($sku_query)
                OR p.post_title LIKE %s
                OR p.ID = %s
            )
            AND p.post_type IN ('product', 'product_variation')
            AND p.post_status IN ('publish', 'private')
            LIMIT 10
        ", '%' . $wpdb->esc_like($query) . '%', $query);
        
        $results = $wpdb->get_results($products_query);
        
        if (empty($results)) {
            return array();
        }
        
        $products = array();
        foreach ($results as $result) {
            $product = wc_get_product($result->ID);
            if ($product) {
                $products[] = array(
                    'id' => $product->get_id(),
                    'name' => $product->get_name(),
                    'sku' => $product->get_sku()
                );
            }
        }
        
        return $products;
    }
    
    /**
     * Generate order edit link
     */
    private function generate_order_edit_link($order_id) {
        $admin_url = admin_url('post.php?post=' . $order_id . '&action=edit');
        return '=HYPERLINK("' . $admin_url . '","' . $order_id . '")';
    }
    
    /**
     * Create CSV file
     */
    private function create_csv_file($data, $filepath, $template_name) {
        // Create CSV file
        $file = fopen($filepath, 'w');
        if (!$file) {
            return false;
        }
        
        // Write data
        foreach ($data as $row) {
            fputcsv($file, $row);
        }
        
        fclose($file);
        
        return str_replace($this->get_upload_dir()['basedir'], $this->get_upload_dir()['baseurl'], $filepath);
    }
} 