<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Grading Exports page — one export card per TGF service + collectable type combination.
 * Templates are auto-created on first load and re-used thereafter.
 */

// Default column layout for all grading templates
$wee_grading_default_columns = array(
    'tgf_line_number',
    'customer_name',
    'order_edit_link',
    'tgf_service_level',
    'tgf_extras',
    'tgf_item_name',
    'tgf_item_set',
    'tgf_item_year',
    'tgf_item_number',
    'tgf_item_description',
    'shipping_address',
    'order_customer_note',
);

/**
 * Get or create a WEE template for a given service + collectable type combination.
 * Template ID is cached in a WP option so it's only created once.
 */
function wee_get_or_create_grading_template($service_product_id, $collectable_type_id, $template_name) {
    $option_key = 'wee_grading_tpl_' . intval($service_product_id) . '_' . intval($collectable_type_id);
    $template_id = get_option($option_key, 0);

    // Verify cached template still exists
    if ($template_id) {
        $tpl = WEE_Templates::get_template($template_id);
        if (!$tpl) {
            $template_id = 0;
            delete_option($option_key);
        }
    }

    if (!$template_id) {
        $filters = array();

        // Filter by WC product ID (= tgf_service_id)
        if ($service_product_id > 0) {
            $filters['product_search'] = json_encode(array(array('id' => $service_product_id)));
        }

        // Filter by TGF collectable type
        if ($collectable_type_id > 0) {
            $filters['tgf_collectable_type_id'] = $collectable_type_id;
        }

        global $wee_grading_default_columns;
        $result = WEE_Templates::save_template(
            $template_name,
            $wee_grading_default_columns,
            array(),   // custom_fields
            $filters,
            array(),   // column_names
            array(),   // field_groups
            array(),   // combined_fields
            array()    // column_visibility
        );

        if (!empty($result['success']) && !empty($result['template_id'])) {
            $template_id = $result['template_id'];
            update_option($option_key, $template_id);
        }
    }

    return $template_id;
}

// Check if TGF is active by looking for the tgf_service_types table
global $wpdb;
$tgf_table = $wpdb->prefix . 'tgf_service_types';
$tgf_active = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tgf_table)) === $tgf_table;

// Load service + collectable type combinations from TGF
$grading_exports = array();

if ($tgf_active) {
    $rows = $wpdb->get_results("
        SELECT
            st.service_product_id,
            p.post_title AS service_name,
            st.collectable_type_id,
            ct.name AS type_name,
            ct.slug AS type_slug,
            st.sort_order
        FROM {$wpdb->prefix}tgf_service_types st
        INNER JOIN {$wpdb->posts} p ON p.ID = st.service_product_id AND p.post_type = 'product' AND p.post_status != 'trash'
        INNER JOIN {$wpdb->prefix}tgf_collectable_types ct ON ct.id = st.collectable_type_id AND ct.active = 1
        ORDER BY p.post_title ASC, st.sort_order ASC, ct.name ASC
    ", ARRAY_A);

    foreach ($rows as $row) {
        $label = $row['service_name'] . ' — ' . $row['type_name'];
        $template_id = wee_get_or_create_grading_template(
            intval($row['service_product_id']),
            intval($row['collectable_type_id']),
            $label
        );

        $grading_exports[] = array(
            'label'              => $label,
            'service_name'       => $row['service_name'],
            'type_name'          => $row['type_name'],
            'service_product_id' => intval($row['service_product_id']),
            'collectable_type_id'=> intval($row['collectable_type_id']),
            'template_id'        => $template_id,
        );
    }
}
?>

<div class="wrap wee-wrap">
    <div class="wee-header">
        <h1><?php _e('Grading Exports', 'wordpress-excel-export'); ?></h1>
        <p><?php _e('Export grading submissions by service and collectable type. Each card exports one CSV row per item.', 'wordpress-excel-export'); ?></p>
        <div class="wee-header-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=wee-templates')); ?>" class="button button-secondary">
                <?php _e('Manage Templates', 'wordpress-excel-export'); ?>
            </a>
        </div>
    </div>

    <div class="wee-container">
        <?php if (!$tgf_active) : ?>
            <div class="notice notice-warning">
                <p><?php _e('TCG Grading Flow plugin does not appear to be active. Please activate it to use grading exports.', 'wordpress-excel-export'); ?></p>
            </div>
        <?php elseif (empty($grading_exports)) : ?>
            <div class="notice notice-info">
                <p><?php _e('No grading service types configured yet. Set up services and collectable types in the TCG Grading Flow plugin settings.', 'wordpress-excel-export'); ?></p>
            </div>
        <?php else : ?>
            <div class="wee-grading-grid">
                <?php foreach ($grading_exports as $export) : ?>
                    <?php if (!$export['template_id']) continue; ?>
                    <div class="wee-grading-card">
                        <div class="wee-grading-card-header">
                            <h3><?php echo esc_html($export['service_name']); ?></h3>
                            <span class="wee-grading-type-badge"><?php echo esc_html($export['type_name']); ?></span>
                        </div>
                        <form class="wee-grading-export-form" method="POST">
                            <?php wp_nonce_field('wee_nonce', 'nonce'); ?>
                            <input type="hidden" name="action" value="wee_export_data">
                            <input type="hidden" name="template_id" value="<?php echo esc_attr($export['template_id']); ?>">
                            <input type="hidden" name="export_format" value="csv">
                            <div class="wee-grading-date-row">
                                <div class="wee-form-group">
                                    <label><?php _e('From', 'wordpress-excel-export'); ?></label>
                                    <input type="date" name="date_from" class="wee-grading-date-from">
                                </div>
                                <div class="wee-form-group">
                                    <label><?php _e('To', 'wordpress-excel-export'); ?></label>
                                    <input type="date" name="date_to" class="wee-grading-date-to">
                                </div>
                            </div>
                            <div class="wee-grading-actions">
                                <button type="submit" class="button button-primary wee-grading-export-btn">
                                    <?php _e('Export CSV', 'wordpress-excel-export'); ?>
                                </button>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=wee-templates&edit=' . $export['template_id'])); ?>"
                                   class="button button-secondary" title="<?php esc_attr_e('Edit template columns', 'wordpress-excel-export'); ?>">
                                    <?php _e('Edit Template', 'wordpress-excel-export'); ?>
                                </a>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.wee-grading-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
    margin-top: 20px;
}
.wee-grading-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
}
.wee-grading-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
    flex-wrap: wrap;
    gap: 8px;
}
.wee-grading-card-header h3 {
    margin: 0;
    font-size: 15px;
    color: #1d2327;
}
.wee-grading-type-badge {
    background: #2271b1;
    color: #fff;
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 20px;
    white-space: nowrap;
}
.wee-grading-date-row {
    display: flex;
    gap: 12px;
    margin-bottom: 14px;
}
.wee-grading-date-row .wee-form-group {
    flex: 1;
}
.wee-grading-date-row label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 4px;
    color: #50575e;
}
.wee-grading-date-row input[type="date"] {
    width: 100%;
}
.wee-grading-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}
</style>

<script>
jQuery(function($) {
    $('.wee-grading-export-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('.wee-grading-export-btn');
        var originalText = $btn.text();
        $btn.prop('disabled', true).text('<?php echo esc_js(__('Exporting…', 'wordpress-excel-export')); ?>');

        $.ajax({
            url: wee_ajax.ajax_url,
            type: 'POST',
            data: $form.serialize(),
            xhrFields: { responseType: 'blob' },
            success: function(data, status, xhr) {
                var cd = xhr.getResponseHeader('Content-Disposition') || '';
                var match = cd.match(/filename="([^"]+)"/);
                var filename = match ? match[1] : 'grading-export.csv';
                var url = window.URL.createObjectURL(data);
                var a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                a.remove();
            },
            error: function(xhr) {
                try {
                    var reader = new FileReader();
                    reader.onload = function() {
                        var json = JSON.parse(reader.result);
                        alert(json.data && json.data.message ? json.data.message : '<?php echo esc_js(__('Export failed. Check the date range and try again.', 'wordpress-excel-export')); ?>');
                    };
                    reader.readAsText(xhr.responseType === 'blob' ? xhr.response : new Blob([xhr.responseText]));
                } catch (err) {
                    alert('<?php echo esc_js(__('Export failed. Check the date range and try again.', 'wordpress-excel-export')); ?>');
                }
            },
            complete: function() {
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });
});
</script>
