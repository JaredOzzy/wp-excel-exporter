# WordPress Excel Export Plugin

A powerful WordPress plugin that allows you to export WooCommerce order data to Excel/CSV format with customizable templates and advanced filtering options.

## Features

### 🎯 Core Features
- **Customizable Export Templates**: Create and save templates with specific column selections
- **Date Range Filtering**: Export orders from specific date ranges
- **Product Filtering**: Filter exports by product name or SKU
- **Multiple Data Fields**: Export 150+ different order and customer data fields
- **CSV Export**: Generate downloadable CSV files compatible with Excel
- **Template Management**: Save, reuse, and delete export templates
- **Dynamic Custom Fields**: Automatically detect and include custom order meta fields

### 📊 Available Export Fields
- **Order Information**: ID, Date, Status, Total, Subtotal, Tax, Shipping, Discount, Refund totals
- **Customer Information**: ID, Name, Email, Phone, Username, Registration date, Total orders/spent
- **Billing Information**: Complete billing address breakdown
- **Shipping Information**: Complete shipping address breakdown
- **Product Information**: ID, Name, SKU, Quantity, Price, Categories, Tags, Dimensions, Weight
- **Line Item Information**: Line item specific data, variation details
- **Tax Information**: Tax totals, rates, codes, classes
- **Coupon Information**: Codes, amounts, types, restrictions, expiry dates
- **Custom Fields**: Dynamically detected order meta fields (including plugin fields like YITH Custom Order Items)

### 🎨 User Interface
- Modern, responsive admin interface
- Intuitive template creation with categorized columns
- Collapsible category sections for easy navigation
- Select All/Deselect All functionality per category
- Real-time column selection counter
- Loading indicators and progress feedback
- Mobile-friendly design

## Requirements

- WordPress 5.0 or higher
- WooCommerce 3.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## Installation

### Method 1: Manual Installation
1. Download the plugin files
2. Upload the `wordpress-excel-export` folder to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to 'Excel Export' in your WordPress admin menu

### Method 2: WordPress Admin Upload
1. Go to WordPress Admin → Plugins → Add New
2. Click 'Upload Plugin'
3. Choose the plugin zip file
4. Click 'Install Now' and then 'Activate'

## Usage

### Creating Export Templates

1. **Navigate to Excel Export**: Go to WordPress Admin → Excel Export
2. **Create New Template**: 
   - Enter a template name
   - Select columns from organized categories (Order Info, Customer Info, Products, etc.)
   - For Custom Fields, check the box and enter a custom column name
   - Click 'Save Template'

### Exporting Data

1. **Select Template**: Choose a saved template from the dropdown
2. **Set Date Range**: Optionally set start and end dates
3. **Add Product Filter**: Optionally filter by product name or SKU
4. **Export**: Click 'Export to Excel' to generate and download the file

### Managing Templates

- **Use Template**: Click 'Use Template' to quickly select it for export
- **Delete Template**: Click 'Delete' to remove unwanted templates
- **View Details**: See template creation date and column count

### Custom Fields Support

The plugin automatically scans your recent orders (last 100 by default) to detect all custom meta fields, including those added by plugins like:
- YITH Custom Order Items
- WooCommerce Custom Fields
- Any other plugin that adds order meta

For each custom field found, you can:
- Select whether to include it in the export
- Specify a custom column name for the export header
- The plugin will automatically map the meta key to your custom column name

## File Structure

```
wordpress-excel-export/
├── wordpress-excel-export.php      # Main plugin file
├── includes/
│   ├── class-wee-plugin.php        # Main plugin class
│   ├── class-wee-templates.php     # Template management
│   └── class-wee-export.php        # Export functionality
├── templates/
│   └── admin-page.php              # Admin interface template
├── assets/
│   ├── js/
│   │   └── admin.js                # JavaScript functionality
│   └── css/
│       └── admin.css               # Admin styles
├── languages/
│   └── wordpress-excel-export.pot  # Translation template
├── uninstall.php                   # Cleanup script
├── README.md                       # This file
└── INSTALL.md                      # Quick install guide
```

## Database Tables

The plugin creates one custom table:
- `wp_wee_templates`: Stores export templates with column configurations and custom field mappings

## Hooks and Filters

### Actions
- `wee_before_export`: Fired before export starts
- `wee_after_export`: Fired after export completes
- `wee_template_saved`: Fired when a template is saved
- `wee_template_deleted`: Fired when a template is deleted

### Filters
- `wee_available_columns`: Modify available export columns
- `wee_export_data`: Modify export data before file generation
- `wee_export_filename`: Customize export filename
- `wee_custom_meta_keys`: Modify which meta keys are detected as custom fields

## Customization

### Adding Custom Columns

```php
add_filter('wee_available_columns', 'add_custom_columns');

function add_custom_columns($columns) {
    $columns['custom_field'] = 'Custom Field Label';
    return $columns;
}
```

### Modifying Export Data

```php
add_filter('wee_export_data', 'modify_export_data');

function modify_export_data($order_data) {
    // Add custom logic here
    return $order_data;
}
```

### Customizing Meta Key Detection

```php
add_filter('wee_custom_meta_keys', 'modify_meta_keys');

function modify_meta_keys($meta_keys) {
    // Add or remove meta keys from detection
    return $meta_keys;
}
```

## Troubleshooting

### Common Issues

1. **No Orders Found**
   - Ensure WooCommerce is active and has orders
   - Check date range settings
   - Verify product filter terms

2. **Export Fails**
   - Check file permissions in uploads directory
   - Ensure sufficient server memory
   - Verify PHP execution time limits

3. **Template Not Saving**
   - Check database permissions
   - Verify nonce validation
   - Check for JavaScript errors

4. **Custom Fields Not Appearing**
   - Ensure you have recent orders with custom meta
   - Check if meta keys start with underscore (protected)
   - Verify plugin compatibility

### Debug Mode

Enable WordPress debug mode to see detailed error messages:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Security

- All user inputs are sanitized and validated
- Nonce verification for all AJAX requests
- Capability checks for admin functions
- SQL prepared statements to prevent injection
- Meta key validation for custom fields

## Performance

- Efficient database queries with proper indexing
- Scans only recent orders for meta key detection (configurable)
- Memory-optimized data processing
- Caching for template data
- Pagination for large datasets (future enhancement)

## Support

For support and feature requests:
- Create an issue on GitHub
- Check the troubleshooting section
- Review WordPress error logs

## Changelog

### Version 1.0.0
- Initial release
- Basic template management
- CSV export functionality
- Date and product filtering
- Responsive admin interface
- Categorized column selection
- Dynamic custom fields detection
- Custom column naming for meta fields

## License

This plugin is licensed under the GPL v2 or later.

## Contributing

Contributions are welcome! Please:
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## Credits

Developed for WordPress and WooCommerce communities. 