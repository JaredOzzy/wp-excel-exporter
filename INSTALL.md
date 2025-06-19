# Quick Installation Guide

## Prerequisites
- WordPress 5.0+
- WooCommerce 3.0+
- PHP 7.4+
- MySQL 5.6+

## Installation Steps

### 1. Download and Upload
1. Download the plugin files
2. Upload the `wordpress-excel-export` folder to `/wp-content/plugins/`
3. Ensure all files are uploaded correctly

### 2. Activate Plugin
1. Go to WordPress Admin → Plugins
2. Find "WordPress Excel Export"
3. Click "Activate"

### 3. Verify Installation
1. Check that "Excel Export" appears in your admin menu
2. Navigate to Excel Export → Create your first template
3. Test the export functionality

## First Time Setup

### Create Your First Template
1. Go to Excel Export in your admin menu
2. Enter a template name (e.g., "Basic Order Export")
3. Select the columns you want to export from the categorized sections
4. For Custom Fields, check the box and enter a custom column name
5. Click "Save Template"

### Test Export
1. Select your saved template
2. Set a date range (optional)
3. Add product filter (optional)
4. Click "Export to Excel"

## Custom Fields Support

The plugin automatically detects custom order meta fields from your recent orders, including:
- YITH Custom Order Items fields
- WooCommerce Custom Fields
- Any other plugin that adds order meta

For each custom field:
1. Check the box to include it in your export
2. Enter a custom column name (e.g., "PSA Card Information" for `wpapo-addon-8-1`)
3. The plugin will use your custom name in the export header

## Troubleshooting

### Plugin Not Appearing
- Check file permissions (755 for folders, 644 for files)
- Verify all files uploaded correctly
- Check WordPress error logs

### Export Fails
- Ensure WooCommerce is active
- Check uploads directory permissions
- Verify PHP memory limits

### No Orders Found
- Confirm WooCommerce has orders
- Check date range settings
- Verify product filter terms

### Custom Fields Not Showing
- Ensure you have recent orders with custom meta
- Check if meta keys start with underscore (protected)
- Verify plugin compatibility

## Support
For additional help, check the main README.md file or create an issue on GitHub. 