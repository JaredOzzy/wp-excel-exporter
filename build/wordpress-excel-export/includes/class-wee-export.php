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
            error_log('WEE Export: Template filters: ' . print_r($template_filters, true));
            error_log('WEE Export: Passed filters: ' . print_r($filters, true));
            $filters = array_merge($template_filters, $filters);
            
            // Clean up filters - remove empty values
            $filters = array_filter($filters, function($value) {
                if (is_array($value)) {
                    return !empty($value);
                }
                return $value !== '' && $value !== null;
            });
            
            error_log('WEE Export: Merged filters (cleaned): ' . print_r($filters, true));
            
            // Debug product search specifically
            if (!empty($filters['product_search'])) {
                error_log('WEE Export: Product search filter detected: ' . $filters['product_search']);
                error_log('WEE Export: Product search type: ' . gettype($filters['product_search']));
            } else {
                error_log('WEE Export: No product search filter found in merged filters');
            }
            
            // Get orders data with optimized queries
            $template_columns = is_array($template['columns']) ? $template['columns'] : array();
            $orders = $this->get_orders_data($date_from, $date_to, $filters, $template_columns);
            
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
    private function get_orders_data($date_from, $date_to, $filters = array(), $template_columns = array()) {
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
        
        // Product search optimization - only apply at order level if we're not doing line item export
        if (!empty($filters['product_search']) && !$this->template_has_line_item_columns($template_columns)) {
            // Check if product_search is a JSON array of product objects or a search string
            $product_ids = array();
            if (is_string($filters['product_search'])) {
                $decoded = json_decode($filters['product_search'], true);
                if (is_array($decoded) && !empty($decoded)) {
                    // It's a JSON array of product objects like [{"id":146,"name":"PSA Grading","sku":""}]
                    // Extract the IDs from each object
                    foreach ($decoded as $product) {
                        if (is_array($product) && isset($product['id'])) {
                            $product_ids[] = intval($product['id']);
                        }
                    }
                    error_log('WEE Export: Product IDs filter extracted: ' . print_r($product_ids, true));
                }
            }
            
            if (!empty($product_ids)) {
                // Filter by specific product IDs
                $joins['product_search'] = "
                    INNER JOIN {$wpdb->prefix}woocommerce_order_items oi_prod ON p.ID = oi_prod.order_id AND oi_prod.order_item_type = 'line_item'
                    INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_prod ON oi_prod.order_item_id = oim_prod.order_item_id AND oim_prod.meta_key = '_product_id'
                ";
                
                $id_placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
                $where_conditions[] = "CAST(oim_prod.meta_value AS UNSIGNED) IN ($id_placeholders)";
                $where_values = array_merge($where_values, $product_ids);
            } else {
                // Fall back to text search (legacy behavior)
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
        }
        
        // Category filter optimization - only apply at order level if we're not doing line item export
        if (!empty($filters['product_categories']) && !$this->template_has_line_item_columns($template_columns)) {
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

        // TGF submission type filter — matches orders where tgf_grading_options contains the given key
        if (!empty($filters['tgf_submission_key'])) {
            $joins['tgf_submission'] = "
                INNER JOIN {$wpdb->prefix}woocommerce_order_items oi_tgf ON p.ID = oi_tgf.order_id AND oi_tgf.order_item_type = 'line_item'
                INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_tgf ON oi_tgf.order_item_id = oim_tgf.order_item_id
                    AND oim_tgf.meta_key = 'tgf_grading_options'
            ";
            $where_conditions[] = "oim_tgf.meta_value LIKE %s";
            $where_values[] = '%' . $wpdb->esc_like('"' . $filters['tgf_submission_key'] . '"') . '%';
        }

        // TGF grading options text search — LIKE match anywhere in the JSON value
        if (!empty($filters['tgf_grading_contains'])) {
            if (!isset($joins['tgf_submission'])) {
                $joins['tgf_submission'] = "
                    INNER JOIN {$wpdb->prefix}woocommerce_order_items oi_tgf ON p.ID = oi_tgf.order_id AND oi_tgf.order_item_type = 'line_item'
                    INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_tgf ON oi_tgf.order_item_id = oim_tgf.order_item_id
                        AND oim_tgf.meta_key = 'tgf_grading_options'
                ";
            }
            $where_conditions[] = "oim_tgf.meta_value LIKE %s";
            $where_values[] = '%' . $wpdb->esc_like($filters['tgf_grading_contains']) . '%';
        }

        // TGF service level value filter — convert human input to snake_case for JSON match
        if (!empty($filters['tgf_service_level_contains'])) {
            if (!isset($joins['tgf_submission'])) {
                $joins['tgf_submission'] = "
                    INNER JOIN {$wpdb->prefix}woocommerce_order_items oi_tgf ON p.ID = oi_tgf.order_id AND oi_tgf.order_item_type = 'line_item'
                    INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_tgf ON oi_tgf.order_item_id = oim_tgf.order_item_id
                        AND oim_tgf.meta_key = 'tgf_grading_options'
                ";
            }
            $service_search = strtolower( str_replace( ' ', '_', $filters['tgf_service_level_contains'] ) );
            $where_conditions[] = "oim_tgf.meta_value LIKE %s";
            $where_values[] = '%' . $wpdb->esc_like( $service_search ) . '%';
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
        
        // For line item exports, we need to include orders that have line items
        if ($this->template_has_line_item_columns($template_columns)) {
            $joins_clause .= " LEFT JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id AND oi.order_item_type = 'line_item'";
            $where_clause = str_replace('WHERE ', 'WHERE oi.order_item_id IS NOT NULL AND ', $where_clause);
        }
        
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
        
        error_log('WEE Export: Found ' . count($order_ids) . ' order IDs matching query');
        if (count($order_ids) > 0) {
            error_log('WEE Export: First 10 order IDs: ' . implode(', ', array_slice($order_ids, 0, 10)) . (count($order_ids) > 10 ? '...' : ''));
        }
        
        if (empty($order_ids)) {
            error_log('WEE Export: ========================================');
            error_log('WEE Export: NO ORDERS FOUND!');
            error_log('WEE Export: Date from: ' . $date_from . ', Date to: ' . $date_to);
            error_log('WEE Export: Active filters: ' . print_r($filters, true));
            error_log('WEE Export: SQL Query executed: ' . $query);
            error_log('WEE Export: ========================================');
            
            // Try a simple query without filters to see if there are ANY orders in date range
            $simple_query = $wpdb->prepare("
                SELECT COUNT(*) FROM {$wpdb->posts} 
                WHERE post_type = 'shop_order' 
                AND post_date >= %s 
                AND post_date <= %s
            ", $date_from . ' 00:00:00', $date_to . ' 23:59:59');
            $total_in_range = $wpdb->get_var($simple_query);
            error_log('WEE Export: Total orders in date range (no filters): ' . $total_in_range);
        }
        
        // Batch process orders for better memory management
        return $this->get_orders_batch($order_ids, 50, $template_columns, $filters);
    }
    
    /**
     * Process orders in batches for better performance and memory management
     */
    private function get_orders_batch($order_ids, $batch_size = 50, $template_columns = array(), $filters = array()) {
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
                $order_data = $this->get_order_details_optimized($order, $template_columns, $filters);
                if ($order_data) {
                    // Handle both single row and multiple rows (line item exports)
                    if (is_array($order_data) && isset($order_data[0]) && is_array($order_data[0])) {
                        // Multiple rows returned (line item export)
                        $detailed_orders = array_merge($detailed_orders, $order_data);
                    } else {
                        // Single row returned (order export)
                        $detailed_orders[] = $order_data;
                    }
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
            "SELECT oi.order_id, oi.order_item_id, oim.meta_key, oim.meta_value 
            FROM {$wpdb->prefix}woocommerce_order_items oi
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
            WHERE oi.order_id IN ($placeholders)
            AND (oim.meta_key LIKE 'ywapo%' OR oim.meta_key LIKE '_ywapo%' OR oim.meta_key LIKE 'tgf_%')",
            ...$order_ids
        );
        
        $item_meta_data = $wpdb->get_results($query);
        
        // Cache item meta data grouped by order and line item
        $grouped_meta = array();
        foreach ($item_meta_data as $meta) {
            if (!isset($grouped_meta[$meta->order_id])) {
                $grouped_meta[$meta->order_id] = array();
            }
            if (!isset($grouped_meta[$meta->order_id][$meta->order_item_id])) {
                $grouped_meta[$meta->order_id][$meta->order_item_id] = array();
            }
            if (!isset($grouped_meta[$meta->order_id][$meta->order_item_id][$meta->meta_key])) {
                $grouped_meta[$meta->order_id][$meta->order_item_id][$meta->meta_key] = array();
            }
            $grouped_meta[$meta->order_id][$meta->order_item_id][$meta->meta_key][] = $meta->meta_value;
        }
        
        // Cache grouped data
        foreach ($grouped_meta as $order_id => $line_items) {
            wp_cache_set("order_item_meta_{$order_id}", $line_items, 'wee_export');
        }
    }
    
    /**
     * Check if template includes line item columns
     */
    private function template_has_line_item_columns($template_columns) {
        $line_item_columns = array(
            'line_item_id', 'line_item_name', 'line_item_quantity', 'line_item_total',
            'line_item_subtotal', 'line_item_tax', 'line_item_tax_class', 'line_item_meta',
            'line_item_sku', 'line_item_variation_id', 'line_item_variation_attributes',
            // TGF grading columns always trigger per-line-item export
            'tgf_line_number', 'tgf_service_level', 'tgf_extras',
            'tgf_extras_card_type', 'tgf_extras_card_extras', 'tgf_extras_signatures',
            'tgf_extras_comic_extras', 'tgf_extras_bgs_subgrades', 'tgf_extras_tag_score',
            'tgf_item_name', 'tgf_item_set', 'tgf_item_year', 'tgf_item_number',
            'tgf_item_description', 'tgf_collectable_type'
        );

        return !empty(array_intersect($template_columns, $line_item_columns));
    }
    
    /**
     * Optimized order details retrieval using preloaded data
     * Now supports both order-level and line-item-level exports
     */
    private function get_order_details_optimized($order, $template_columns = array(), $filters = array()) {
        if (!$order) return false;
        
        $order_id = $order->get_id();
        
        // Check if we need line item level data
        $include_line_items = $this->template_has_line_item_columns($template_columns);
        
        // Get base order data
        $base_order_data = $this->get_base_order_data($order);
        
        if (!$include_line_items) {
            // Original behavior - return single order row
            return $this->add_custom_meta_fields_optimized($base_order_data, $order);
        }
        
        // New behavior - return one row per line item
        $line_item_rows = array();
        $items = $order->get_items('line_item');
        
        if (empty($items)) {
            // No line items, return order data as single row
            return array($this->add_custom_meta_fields_optimized($base_order_data, $order));
        }
        
        foreach ($items as $item_id => $item) {
            // Check if this line item matches the product filter
            if (!$this->line_item_matches_filter($item, $filters)) {
                continue; // Skip this line item if it doesn't match
            }
            
            // Get quantity for this line item
            $quantity = $item->get_quantity();
            
            // Create a row for each quantity
            for ($i = 0; $i < $quantity; $i++) {
                // Clone base order data for each line item instance
                $item_row = $base_order_data;
                
                // Add line item specific data
                $item_row = $this->add_line_item_data($item_row, $item, $item_id);
                
                // Adjust for individual unit (quantity = 1)
                $item_row['line_item_quantity'] = 1;
                
                // Divide totals by quantity to get per-unit price
                if ($quantity > 1) {
                    $item_row['line_item_total'] = $item->get_total() / $quantity;
                    $item_row['line_item_subtotal'] = $item->get_subtotal() / $quantity;
                    $item_row['line_item_tax'] = $item->get_total_tax() / $quantity;
                }
                
                // Add custom meta fields with line item context
                $item_row = $this->add_custom_meta_fields_optimized($item_row, $order, $item);
                
                $line_item_rows[] = $item_row;
            }
        }
        
        return $line_item_rows;
    }
    
    /**
     * Check if a line item matches the product filter criteria
     */
    private function line_item_matches_filter($item, $filters = array()) {
        // If no filters that require item-level checking are set, include all line items
        if (empty($filters['product_search']) && empty($filters['product_categories']) && empty($filters['tgf_submission_key']) && empty($filters['tgf_grading_contains']) && empty($filters['tgf_service_level_contains'])) {
            return true;
        }
        
        $product = $item->get_product();
        if (!$product) {
            return false; // Skip items without products
        }
        
        // Check product search filter
        if (!empty($filters['product_search'])) {
            $product_id = $product->get_id();
            
            // Check if product_search is a JSON array of product objects
            $product_ids = array();
            if (is_string($filters['product_search'])) {
                $decoded = json_decode($filters['product_search'], true);
                if (is_array($decoded) && !empty($decoded)) {
                    // Extract the IDs from each product object
                    foreach ($decoded as $product_data) {
                        if (is_array($product_data) && isset($product_data['id'])) {
                            $product_ids[] = intval($product_data['id']);
                        }
                    }
                }
            }
            
            if (!empty($product_ids)) {
                // Check if this product ID is in the array
                if (!in_array($product_id, $product_ids)) {
                    return false;
                }
            } else {
                // Fall back to text search (legacy behavior)
                $search_term = strtolower($filters['product_search']);
                $product_name = strtolower($product->get_name());
                $product_sku = strtolower($product->get_sku());
                $item_name = strtolower($item->get_name());
                
                // Check if search term is numeric for product ID search
                if (is_numeric($filters['product_search'])) {
                    $search_id = intval($filters['product_search']);
                    if ($product_id != $search_id) {
                        return false;
                    }
                } else {
                    // For text search, check name and SKU
                    if (strpos($product_name, $search_term) === false && 
                        strpos($product_sku, $search_term) === false && 
                        strpos($item_name, $search_term) === false) {
                        return false;
                    }
                }
            }
        }
        
        // Check product categories filter
        if (!empty($filters['product_categories'])) {
            $product_categories = get_the_terms($product->get_id(), 'product_cat');
            if ($product_categories && !is_wp_error($product_categories)) {
                $product_category_ids = wp_list_pluck($product_categories, 'term_id');
                $filter_category_ids = array_map('intval', $filters['product_categories']);
                
                // Check if any of the product's categories match the filter categories
                if (array_intersect($product_category_ids, $filter_category_ids)) {
                    return true;
                }
            }
            
            // If we have a category filter and this item doesn't match, exclude it
            return false;
        }
        
        // TGF grading option filters — fetch meta once for all checks
        if (!empty($filters['tgf_submission_key']) || !empty($filters['tgf_grading_contains']) || !empty($filters['tgf_service_level_contains'])) {
            $raw_grading = $item->get_meta('tgf_grading_options');

            if (!empty($filters['tgf_submission_key'])) {
                if (!$raw_grading || strpos($raw_grading, '"' . $filters['tgf_submission_key'] . '"') === false) {
                    return false;
                }
            }

            if (!empty($filters['tgf_grading_contains'])) {
                if (!$raw_grading || stripos($raw_grading, $filters['tgf_grading_contains']) === false) {
                    return false;
                }
            }

            if (!empty($filters['tgf_service_level_contains'])) {
                $service_search = strtolower( str_replace( ' ', '_', $filters['tgf_service_level_contains'] ) );
                if (!$raw_grading || stripos($raw_grading, $service_search) === false) {
                    return false;
                }
            }
        }

        return true; // Default to include if no specific filters are set
    }
    
    /**
     * Get base order data (without line item specifics)
     */
    private function get_base_order_data($order) {
        $order_id = $order->get_id();
        
        // Use WooCommerce's built-in methods which are already optimized
        $order_data = array(
            'order_id' => $order_id,
            'order_edit_link' => $this->generate_order_edit_link($order_id),
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
        $order_data['billing_address'] = str_replace( array( '<br/>', '<br />', '<br>' ), ', ', $order->get_formatted_billing_address() );
        
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
        $order_data['shipping_address'] = str_replace( array( '<br/>', '<br />', '<br>' ), ', ', $order->get_formatted_shipping_address() );
        
        return $order_data;
    }
    
    /**
     * Add line item specific data to the row
     */
    private function add_line_item_data($row_data, $item, $item_id) {
        $product = $item->get_product();
        
        // Line item basic information
        $row_data['line_item_id'] = $item_id;
        $row_data['line_item_name'] = $item->get_name();
        $row_data['line_item_quantity'] = $item->get_quantity();
        $row_data['line_item_total'] = $item->get_total();
        $row_data['line_item_subtotal'] = $item->get_subtotal();
        $row_data['line_item_tax'] = $item->get_total_tax();
        $row_data['line_item_tax_class'] = $item->get_tax_class();
        
        // Product specific data
        if ($product) {
            $row_data['line_item_sku'] = $product->get_sku();
            $row_data['line_item_variation_id'] = $item->get_variation_id();
            
            // Variation attributes
            if ($item->get_variation_id()) {
                $variation_attributes = array();
                $variation_data = $item->get_meta_data();
                foreach ($variation_data as $meta) {
                    $key = $meta->get_data()['key'];
                    $value = $meta->get_data()['value'];
                    if (strpos($key, 'pa_') === 0 || strpos($key, 'attribute_') === 0) {
                        $variation_attributes[] = $key . ': ' . $value;
                    }
                }
                $row_data['line_item_variation_attributes'] = implode(' | ', $variation_attributes);
            } else {
                $row_data['line_item_variation_attributes'] = '';
            }
        } else {
            $row_data['line_item_sku'] = '';
            $row_data['line_item_variation_id'] = '';
            $row_data['line_item_variation_attributes'] = '';
        }
        
        // Line item meta data
        $meta_data = array();
        $item_meta = $item->get_meta_data();
        foreach ($item_meta as $meta) {
            $meta_data[] = $meta->key . ': ' . $meta->value;
        }
        $row_data['line_item_meta'] = implode(' | ', $meta_data);

        // --- TGF Grading Data ---
        $order_id = method_exists($item, 'get_order_id') ? $item->get_order_id() : 0;
        $tgf_cache = wp_cache_get("order_item_meta_{$order_id}", 'wee_export');
        $get_tgf = function($key) use ($tgf_cache, $item_id) {
            return ($tgf_cache && isset($tgf_cache[$item_id][$key][0])) ? $tgf_cache[$item_id][$key][0] : '';
        };

        // Parse tgf_card_data JSON
        $card_data = array();
        $raw_card = $get_tgf('tgf_card_data');
        if ($raw_card) {
            $decoded = json_decode($raw_card, true);
            if (is_array($decoded)) {
                $card_data = $decoded;
            }
        }

        // Parse tgf_grading_options JSON
        $grading_options = array();
        $raw_grading = $get_tgf('tgf_grading_options');
        if ($raw_grading) {
            $decoded = json_decode($raw_grading, true);
            if (is_array($decoded)) {
                $grading_options = $decoded;
            }
        }

        // Comic books use comic_title key; everything else is a card/collectible
        if (isset($card_data['comic_title'])) {
            $row_data['tgf_item_name']        = $card_data['comic_title'] ?? '';
            $row_data['tgf_item_set']         = $card_data['comic_publisher'] ?? '';
            $row_data['tgf_item_year']        = $card_data['comic_year'] ?? '';
            $row_data['tgf_item_number']      = $card_data['comic_issue'] ?? '';
            $row_data['tgf_item_description'] = $card_data['comic_desc'] ?? '';
        } else {
            $row_data['tgf_item_name']        = $card_data['name'] ?? $card_data['card_name'] ?? '';
            $row_data['tgf_item_set']         = $card_data['set_name'] ?? '';
            $row_data['tgf_item_year']        = $card_data['card_year'] ?? '';
            $row_data['tgf_item_number']      = $card_data['number'] ?? $card_data['card_number'] ?? '';
            $desc_parts = array_filter(array($card_data['variant'] ?? '', $card_data['rarity'] ?? '', $card_data['notes'] ?? ''));
            $row_data['tgf_item_description'] = implode(' | ', $desc_parts);
        }

        $row_data['tgf_collectable_type'] = $get_tgf('tgf_collectable_name');

        // Extract service level using the known service-level keys; everything else goes to extras
        $service_level_keys = array('cgc_service_level', 'cgc_comic_service', 'psa_service_level', 'bgs_service_level', 'ags_service_level', 'tag_service_level');
        $humanize = function($str) {
            if (is_array($str)) {
                $str = implode(', ', array_filter(array_map('strval', $str)));
            }
            return ucwords(str_replace(array('_', '-'), ' ', (string)$str));
        };

        $service_level = '';
        $extras_parts  = array();
        foreach ($grading_options as $key => $val) {
            if ($val === '' || $val === null || (is_array($val) && empty($val))) {
                continue;
            }
            if (in_array($key, $service_level_keys)) {
                $service_level = $humanize($val);
            } else {
                $extras_parts[] = $humanize($key) . ': ' . $humanize($val);
            }
        }

        $row_data['tgf_service_level'] = $service_level;
        $row_data['tgf_extras']        = implode(', ', $extras_parts);

        // Individual extras columns — each maps a known grading-options key to its own column
        $extras_key_map = array(
            'card_type'            => 'tgf_extras_card_type',
            'card_extras'          => 'tgf_extras_card_extras',
            'signatures_sketches'  => 'tgf_extras_signatures',
            'modern_comic_extras'  => 'tgf_extras_comic_extras',
            'vintage_comic_extras' => 'tgf_extras_comic_extras',
            'bgs_sub_grades'       => 'tgf_extras_bgs_subgrades',
            'tag_score'            => 'tgf_extras_tag_score',
        );
        // Initialise all to empty
        foreach (array_unique(array_values($extras_key_map)) as $col) {
            $row_data[$col] = '';
        }
        foreach ($grading_options as $key => $val) {
            if (isset($extras_key_map[$key]) && $val !== '' && $val !== null && !(is_array($val) && empty($val))) {
                $row_data[$extras_key_map[$key]] = $humanize($val);
            }
        }

        // Line number placeholder — filled by generate_file() counter
        $row_data['tgf_line_number'] = '';

        return $row_data;
    }
    
    /**
     * Optimized custom meta fields retrieval using cached data
     */
    private function add_custom_meta_fields_optimized($order_data, $order, $item = null) {
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
        
        // Get field groups to determine which fields to skip
        $grouped_field_keys = array();
        $template = $this->template;
        if (!empty($template['field_groups'])) {
            $field_groups = is_string($template['field_groups']) ? json_decode($template['field_groups'], true) : $template['field_groups'];
            if (is_array($field_groups)) {
                foreach ($field_groups as $group) {
                    if (!empty($group['fields']) && is_array($group['fields'])) {
                        $grouped_field_keys = array_merge($grouped_field_keys, $group['fields']);
                    }
                }
            }
        }
        
        foreach ($custom_columns as $column_key => $column_label) {
            // Skip if this field is part of a group
            if (in_array($column_key, $grouped_field_keys)) {
                continue;
            }
            
            $meta_key = str_replace('meta_', '', $column_key);
            $meta_value = $this->get_meta_value($meta_key, $order, $item, $item_meta_cache, $wc_internal_fields);
            
            $order_data[$column_key] = $meta_value ? $meta_value : '';
        }
        

        
        return $order_data;
    }
    
    /**
     * Helper method to get meta value for a specific field
     */
    private function get_meta_value($meta_key, $order, $item = null, $item_meta_cache = null, $wc_internal_fields = array()) {
        $meta_value = '';
        
        // Skip WooCommerce internal fields to avoid warnings
        if (in_array($meta_key, $wc_internal_fields)) {
            return '';
        }
        
        $order_id = $order->get_id();
        
        // Check if this is a YWAPO field (use cached data)
        if (strpos($meta_key, 'ywapo-addon-') === 0 || strpos($meta_key, '_ywapo') === 0) {
            if ($item && $item_meta_cache) {
                $item_id = $item->get_id();
                // Get line item specific meta data
                if (isset($item_meta_cache[$item_id][$meta_key])) {
                    $meta_value = implode(' | ', array_unique($item_meta_cache[$item_id][$meta_key]));
                }
            } else {
                // Fallback for order-level export - concatenate all line item values
                if ($item_meta_cache) {
                    $all_values = array();
                    foreach ($item_meta_cache as $line_item_id => $line_item_meta) {
                        if (isset($line_item_meta[$meta_key])) {
                            $all_values = array_merge($all_values, $line_item_meta[$meta_key]);
                        }
                    }
                    $meta_value = implode(' | ', array_unique($all_values));
                }
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
        
        return $meta_value ? $meta_value : '';
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
        
        // Use column_order if available (includes both columns and combined field IDs), otherwise fall back to columns
        $column_order = array();
        if (!empty($template['column_order'])) {
            if (is_string($template['column_order'])) {
                $column_order = json_decode($template['column_order'], true);
                if (!is_array($column_order)) {
                    $column_order = array();
                }
            } elseif (is_array($template['column_order'])) {
                $column_order = $template['column_order'];
            }
        }
        
        // If no column_order, fall back to columns
        $column_keys = !empty($column_order) ? $column_order : $template['columns'];

        // Assign sequential line numbers for tgf_line_number column
        if (is_array($column_keys) && in_array('tgf_line_number', $column_keys)) {
            $line_num = 1;
            foreach ($flattened_orders as &$order_row) {
                $order_row['tgf_line_number'] = $line_num++;
            }
            unset($order_row);
        }

        $available_columns = WEE_Templates::get_available_columns();
        
        error_log('WEE Export: Using column order: ' . print_r($column_keys, true));
        
        // Initialize field groups and combined fields early
        $field_groups = array();
        if (!empty($template['field_groups'])) {
            if (is_string($template['field_groups'])) {
                $field_groups = json_decode($template['field_groups'], true);
                if (!is_array($field_groups)) {
                    $field_groups = array();
                }
            } elseif (is_array($template['field_groups'])) {
                $field_groups = $template['field_groups'];
            }
        }
        
        $combined_fields = array();
        if (!empty($template['combined_fields'])) {
            if (is_string($template['combined_fields'])) {
                $combined_fields = json_decode($template['combined_fields'], true);
                if (!is_array($combined_fields)) {
                    $combined_fields = array();
                }
            } elseif (is_array($template['combined_fields'])) {
                $combined_fields = $template['combined_fields'];
            }
            error_log('WEE Export: Combined fields loaded: ' . print_r($combined_fields, true));
        }
        
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
        
        // Load column visibility settings
        $column_visibility = array();
        if (!empty($template['column_visibility'])) {
            if (is_string($template['column_visibility'])) {
                $column_visibility = json_decode($template['column_visibility'], true);
                if (!is_array($column_visibility)) {
                    $column_visibility = array();
                }
            } elseif (is_array($template['column_visibility'])) {
                $column_visibility = $template['column_visibility'];
            }
            error_log('WEE Export: Column visibility loaded: ' . print_r($column_visibility, true));
        }
        
        foreach ($column_keys as $column_key) {
            // Check if this is a combined field ID
            $is_combined_field = strpos($column_key, 'combined_') === 0;
            
            if ($is_combined_field) {
                // Find and add the combined field header
                foreach ($combined_fields as $combined_field) {
                    if ($combined_field['id'] === $column_key) {
                        // Check visibility
                        if (isset($combined_field['visible']) && $combined_field['visible'] === false) {
                            error_log('WEE Export: Skipping hidden combined field: ' . $combined_field['name']);
                            break;
                        }
                        
                        if (!empty($combined_field['name'])) {
                            $headers[] = $combined_field['name'];
                        }
                        break;
                    }
                }
                continue;
            }
            
            // Handle regular column
            // Skip individual fields that are part of field groups
            $skip_field = false;
            foreach ($field_groups as $group) {
                if (!empty($group['fields']) && in_array($column_key, $group['fields'])) {
                    $skip_field = true;
                    break;
                }
            }
            
            // Skip individual fields that are part of combined fields
            if (!$skip_field) {
                foreach ($combined_fields as $combined_field) {
                    if (!empty($combined_field['fields'])) {
                        foreach ($combined_field['fields'] as $field) {
                            if (isset($field['value']) && $field['value'] === $column_key) {
                                $skip_field = true;
                                break 2;
                            }
                        }
                    }
                }
            }
            
            if ($skip_field) {
                continue;
            }
            
            // Check column visibility - skip if set to hidden (false)
            if (isset($column_visibility[$column_key]) && $column_visibility[$column_key] === false) {
                error_log('WEE Export: Skipping hidden column: ' . $column_key);
                continue;
            }
            
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
        
        // Add custom fields headers (only if not using column_order, since they're not in it)
        if (empty($column_order) && !empty($template['custom_fields'])) {
            foreach ($template['custom_fields'] as $custom) {
                $headers[] = $custom['column_name'];
            }
        }
        
        // Add field groups headers (only if not using column_order, since they're not in it)
        if (empty($column_order)) {
            foreach ($field_groups as $group) {
                if (!empty($group['name'])) {
                    $headers[] = $group['name'];
                }
            }
        }
        $data[] = $headers;
        
        // Build data rows
        $first_order = true;
        foreach ($flattened_orders as $order) {
            if ($first_order) {
                error_log('WEE Export: Available order fields for combined fields: ' . print_r(array_keys($order), true));
                $first_order = false;
            }
            $row = array();
            
                    // Process columns in the order specified
        foreach ($column_keys as $column_key) {
            // Check if this is a combined field ID
            $is_combined_field = strpos($column_key, 'combined_') === 0;
            
            if ($is_combined_field) {
                // Find and add the combined field data
                foreach ($combined_fields as $combined_field) {
                    if ($combined_field['id'] === $column_key) {
                        // Check visibility
                        if (isset($combined_field['visible']) && $combined_field['visible'] === false) {
                            break;
                        }
                        
                        if (!empty($combined_field['fields'])) {
                            $combined_values = array();
                            error_log('WEE Export: Processing combined field in row: ' . $combined_field['name']);
                            error_log('WEE Export: Combined field data: ' . print_r($combined_field, true));
                            
                            foreach ($combined_field['fields'] as $field) {
                                if (isset($field['value'])) {
                                    $value = isset($order[$field['value']]) ? $order[$field['value']] : '';
                                    
                                    // Clean meta fields (remove duplicate label before colon)
                                    if (strpos($field['value'], 'meta_') === 0) {
                                        $value = $this->clean_field_value($value);
                                    }
                                    
                                    error_log('WEE Export: Field ' . $field['value'] . ' = "' . $value . '"');
                                    // Include even if empty - user might want to see empty values separated
                                    $combined_values[] = $value;
                                }
                            }
                            
                            $separator = isset($combined_field['separator']) ? $combined_field['separator'] : ' ';
                            error_log('WEE Export: Separator: "' . $separator . '"');
                            error_log('WEE Export: Values to combine: ' . print_r($combined_values, true));
                            
                            $result = implode($separator, $combined_values);
                            error_log('WEE Export: Combined result: "' . $result . '"');
                            $row[] = $result;
                        } else {
                            $row[] = '';
                        }
                        break;
                    }
                }
                continue;
            }
            
            // Handle regular column
            // Skip individual fields that are part of field groups
            $skip_field = false;
            foreach ($field_groups as $group) {
                if (!empty($group['fields']) && in_array($column_key, $group['fields'])) {
                    $skip_field = true;
                    break;
                }
            }
            
            // Skip individual fields that are part of combined fields
            if (!$skip_field) {
                foreach ($combined_fields as $combined_field) {
                    if (!empty($combined_field['fields'])) {
                        foreach ($combined_field['fields'] as $field) {
                            if (isset($field['value']) && $field['value'] === $column_key) {
                                $skip_field = true;
                                break 2;
                            }
                        }
                    }
                }
            }
            
            if ($skip_field) {
                continue;
            }
            
            // Check column visibility - skip if set to hidden (false)
            if (isset($column_visibility[$column_key]) && $column_visibility[$column_key] === false) {
                continue;
            }
            
            $value = isset($order[$column_key]) ? $order[$column_key] : '';
                
                // Keep HYPERLINK formulas intact - Excel will interpret them when opening the CSV
                // No modification needed - the formula is already in the correct format
                
                // Clean meta fields (remove duplicate label before colon)
                if (strpos($column_key, 'meta_') === 0) {
                    $value = $this->clean_field_value($value);
                }
                
                $row[] = $value;
            }
            
            // Add custom fields data (only if not using column_order)
            if (empty($column_order) && !empty($template['custom_fields'])) {
                foreach ($template['custom_fields'] as $custom) {
                    $meta_key = $custom['meta_key'];
                    $value = isset($order['meta_' . $meta_key]) ? $order['meta_' . $meta_key] : '';
                    // Clean custom field values
                    $value = $this->clean_field_value($value);
                    $row[] = $value;
                }
            }
            
            // Add field groups data (only if not using column_order)
            if (empty($column_order)) {
                foreach ($field_groups as $group) {
                    if (!empty($group['fields'])) {
                        $group_values = array();
                        foreach ($group['fields'] as $field_key) {
                            $meta_key = str_replace('meta_', '', $field_key);
                            $value = isset($order[$field_key]) ? $order[$field_key] : '';
                            if (!empty($value)) {
                                // Clean field group values
                                $value = $this->clean_field_value($value);
                                $group_values[] = $value;
                            }
                        }
                        $row[] = implode(' | ', array_unique($group_values));
                    } else {
                        $row[] = '';
                    }
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
     * Clean field value by removing duplicate label before colon
     * 
     * @param string $value The field value to clean
     * @return string Cleaned value
     */
    private function clean_field_value($value) {
        if (empty($value)) {
            return $value;
        }
        
        // Find the first colon
        $colon_pos = strpos($value, ':');
        
        // If colon found, return everything after it (trimmed)
        if ($colon_pos !== false) {
            return trim(substr($value, $colon_pos + 1));
        }
        
        // No colon found, return original value
        return $value;
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
        return '=HYPERLINK("' . $admin_url . '","#' . $order_id . '")';
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