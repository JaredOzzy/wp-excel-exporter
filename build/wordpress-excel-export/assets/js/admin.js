jQuery(document).ready(function($) {
    'use strict';

    // Initialize the plugin
    initWEE();

    function initWEE() {
        initProductSearch();
        initAdvancedFilters();
        initExportForm();
        initTemplateManagement();
        initTemplateFilters();
        initColumnOrdering();
        initCustomColumnNames();
    }

    // Product Search with Autocomplete
    function initProductSearch() {
        const $productSearch = $('#product-search');
        const $dropdown = $('<div class="wee-product-dropdown wee-hidden"></div>');
        
        $productSearch.after($dropdown);
        
        let searchTimeout;
        let selectedIndex = -1;
        let products = [];

        $productSearch.on('input', function() {
            const query = $(this).val().trim();
            
            clearTimeout(searchTimeout);
            
            if (query.length < 2) {
                hideDropdown();
                return;
            }

            searchTimeout = setTimeout(() => {
                searchProducts(query);
            }, 300);
        });

        $productSearch.on('keydown', function(e) {
            const $items = $dropdown.find('.wee-product-item');
            
            switch(e.keyCode) {
                case 40: // Down arrow
                    e.preventDefault();
                    selectedIndex = Math.min(selectedIndex + 1, $items.length - 1);
                    updateSelection();
                    break;
                case 38: // Up arrow
                    e.preventDefault();
                    selectedIndex = Math.max(selectedIndex - 1, -1);
                    updateSelection();
                    break;
                case 13: // Enter
                    e.preventDefault();
                    if (selectedIndex >= 0 && products[selectedIndex]) {
                        selectProduct(products[selectedIndex]);
                    }
                    break;
                case 27: // Escape
                    hideDropdown();
                    break;
            }
        });

        $productSearch.on('focus', function() {
            if ($(this).val().trim().length >= 2) {
                showDropdown();
            }
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('.wee-product-search-container').length) {
                hideDropdown();
            }
        });

        function searchProducts(query) {
        $.ajax({
            url: wee_ajax.ajax_url,
            type: 'POST',
                data: {
                    action: 'wee_search_products',
                    query: query,
                    nonce: wee_ajax.nonce
                },
            success: function(response) {
                if (response.success) {
                        products = response.data;
                        displayProducts(products);
                    }
                }
            });
        }

        function displayProducts(products) {
            if (products.length === 0) {
                $dropdown.html('<div class="wee-product-item wee-no-results">No products found</div>');
            } else {
                const html = products.map(product => `
                    <div class="wee-product-item" data-product-id="${product.id}">
                        <div class="wee-product-name">${product.name}</div>
                        <div class="wee-product-meta">ID: ${product.id} | SKU: ${product.sku || 'N/A'}</div>
                    </div>
                `).join('');
                $dropdown.html(html);
            }
            showDropdown();
        }

        function showDropdown() {
            $dropdown.removeClass('wee-hidden');
        }

        function hideDropdown() {
            $dropdown.addClass('wee-hidden');
            selectedIndex = -1;
        }

        function updateSelection() {
            $dropdown.find('.wee-product-item').removeClass('wee-selected');
            if (selectedIndex >= 0) {
                $dropdown.find('.wee-product-item').eq(selectedIndex).addClass('wee-selected');
            }
        }

        function selectProduct(product) {
            $productSearch.val(product.name);
            $productSearch.attr('data-product-id', product.id);
            hideDropdown();
        }

        $dropdown.on('click', '.wee-product-item', function() {
            const index = $(this).index();
            if (products[index]) {
                selectProduct(products[index]);
            }
        });
    }

    // Advanced Filters Toggle (now for template filters)
    function initAdvancedFilters() {
        // No longer needed since filters are part of templates now
        // This function is kept for compatibility but does nothing
    }

    // Template Filters Toggle and Product Search
    function initTemplateFilters() {
        // Toggle for template filters
        const $toggleBtn = $('#wee-toggle-template-filters');
        const $filtersContent = $('#wee-template-filters-content');
        const $toggleIcon = $toggleBtn.find('.wee-toggle-icon');
        const $toggleText = $toggleBtn.find('span:not(.wee-toggle-icon)');

        $toggleBtn.on('click', function() {
            const isCollapsed = $filtersContent.hasClass('wee-collapsed');
            
            if (isCollapsed) {
                $filtersContent.removeClass('wee-collapsed').slideDown(300);
                $toggleBtn.removeClass('wee-collapsed');
                $toggleIcon.text('−');
                $toggleText.text('Hide Template Filters');
            } else {
                $filtersContent.addClass('wee-collapsed').slideUp(300);
                $toggleBtn.addClass('wee-collapsed');
                $toggleIcon.text('+');
                $toggleText.text('Add Template Filters (Optional)');
            }
        });

        // Product Search with Autocomplete for Templates
        initTemplateProductSearch();
    }

    function initTemplateProductSearch() {
        const $productSearch = $('#template-product-search');
        const $dropdown = $('#template-product-dropdown');
        
        if (!$productSearch.length || !$dropdown.length) return;
        
        let searchTimeout;
        let selectedIndex = -1;
        let products = [];

        $productSearch.on('input', function() {
            const query = $(this).val().trim();
            
            clearTimeout(searchTimeout);
            
            if (query.length < 2) {
                hideTemplateDropdown();
                return;
            }

            searchTimeout = setTimeout(() => {
                searchTemplateProducts(query);
            }, 300);
        });

        $productSearch.on('keydown', function(e) {
            const $items = $dropdown.find('.wee-product-item');
            
            switch(e.keyCode) {
                case 40: // Down arrow
                    e.preventDefault();
                    selectedIndex = Math.min(selectedIndex + 1, $items.length - 1);
                    updateTemplateSelection();
                    break;
                case 38: // Up arrow
                    e.preventDefault();
                    selectedIndex = Math.max(selectedIndex - 1, -1);
                    updateTemplateSelection();
                    break;
                case 13: // Enter
                    e.preventDefault();
                    if (selectedIndex >= 0 && products[selectedIndex]) {
                        selectTemplateProduct(products[selectedIndex]);
                    }
                    break;
                case 27: // Escape
                    hideTemplateDropdown();
                    break;
            }
        });

        $productSearch.on('focus', function() {
            if ($(this).val().trim().length >= 2) {
                showTemplateDropdown();
            }
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('.wee-product-search-container').length) {
                hideTemplateDropdown();
            }
        });

        function searchTemplateProducts(query) {
            $.ajax({
                url: wee_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wee_search_products',
                    query: query,
                    nonce: wee_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        products = response.data;
                        displayTemplateProducts(products);
                    }
                }
            });
        }

        function displayTemplateProducts(products) {
            if (products.length === 0) {
                $dropdown.html('<div class="wee-product-item wee-no-results">No products found</div>');
            } else {
                const html = products.map(product => `
                    <div class="wee-product-item" data-product-id="${product.id}">
                        <div class="wee-product-name">${product.name}</div>
                        <div class="wee-product-meta">ID: ${product.id} | SKU: ${product.sku || 'N/A'}</div>
                    </div>
                `).join('');
                $dropdown.html(html);
            }
            showTemplateDropdown();
        }

        function showTemplateDropdown() {
            $dropdown.removeClass('wee-hidden');
        }

        function hideTemplateDropdown() {
            $dropdown.addClass('wee-hidden');
            selectedIndex = -1;
        }

        function updateTemplateSelection() {
            $dropdown.find('.wee-product-item').removeClass('wee-selected');
            if (selectedIndex >= 0) {
                $dropdown.find('.wee-product-item').eq(selectedIndex).addClass('wee-selected');
            }
        }

        function selectTemplateProduct(product) {
            $productSearch.val(product.name);
            $productSearch.attr('data-product-id', product.id);
            hideTemplateDropdown();
        }

        $dropdown.on('click', '.wee-product-item', function() {
            const index = $(this).index();
            if (products[index]) {
                selectTemplateProduct(products[index]);
            }
        });
    }

    // Export Form Handling
    function initExportForm() {
        const $form = $('#wee-export-form');
        const $exportBtn = $form.find('.wee-export-btn');

        $form.on('submit', function(e) {
            e.preventDefault();
            
            const $submitBtn = $exportBtn;
            const originalText = $submitBtn.text();
            
            // Create FormData directly from the form element
            const allFormData = new FormData(this);
            
            // Add loading state
            $submitBtn.prop('disabled', true).text('Exporting...');
            $form.addClass('wee-loading');
        
        $.ajax({
            url: wee_ajax.ajax_url,
            type: 'POST',
                data: allFormData,
                processData: false,
                contentType: false,
                xhrFields: {
                    responseType: 'blob'
                },
                success: function(response, status, xhr) {
                    // Check if response is actually an error
                    if (response.type && response.type.includes('application/json')) {
                        // This is an error response
                        const reader = new FileReader();
                        reader.onload = function() {
                            try {
                                const errorData = JSON.parse(reader.result);
                                if (errorData && errorData.data && errorData.data.message) {
                                    showNotice('Error: ' + errorData.data.message, 'error');
                                } else if (errorData && errorData.data) {
                                    const message = typeof errorData.data === 'string' ? errorData.data : 'An unknown error occurred.';
                                    showNotice('Error: ' + message, 'error');
                                } else {
                                    showNotice('Export failed: Could not parse error response.', 'error');
                                }
                            } catch (e) {
                                showNotice('Export failed: An unknown server error occurred.', 'error');
                            }
                        };
                        reader.onerror = function() {
                            showNotice('Export failed: Could not read error response.', 'error');
                        };
                        reader.readAsText(response);
                    } else {
                        // This is a successful file download
                        const filename = xhr.getResponseHeader('Content-Disposition');
                        let downloadFilename = 'export.xlsx';
                        
                        if (filename) {
                            const matches = filename.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
                            if (matches && matches[1]) {
                                downloadFilename = matches[1].replace(/['"]/g, '');
                            }
                        }
                        
                        // Create download link
                        const url = window.URL.createObjectURL(response);
                        const a = document.createElement('a');
                        a.style.display = 'none';
                        a.href = url;
                        a.download = downloadFilename;
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        document.body.removeChild(a);
                        
                        showNotice('Export completed successfully!', 'success');
                    }
                },
                error: function(xhr) {
                    // The response from the server is in xhr.response, which is a Blob because of `responseType: 'blob'`.
                    if (xhr.response) {
                        const reader = new FileReader();
                        reader.onload = function() {
                            try {
                                const errorData = JSON.parse(reader.result);
                                if (errorData && errorData.data && errorData.data.message) {
                                    showNotice('Error: ' + errorData.data.message, 'error');
                                } else if (errorData && errorData.data) {
                                    const message = typeof errorData.data === 'string' ? errorData.data : 'An unknown error occurred.';
                                    showNotice('Error: ' + message, 'error');
                                } else {
                                    showNotice('Export failed: Could not parse error response.', 'error');
                                }
                            } catch (e) {
                                showNotice('Export failed: An unknown server error occurred.', 'error');
                            }
                        };
                        reader.onerror = function() {
                            showNotice('Export failed: Could not read error response.', 'error');
                        };
                        reader.readAsText(xhr.response);
                } else {
                        showNotice('Export failed: An unknown server error occurred.', 'error');
                    }
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).text(originalText);
                    $form.removeClass('wee-loading');
                }
            });
        });
    }

    // Template Management
    function initTemplateManagement() {
        // Handle the submission of the "Create New Template" form
        $('#wee-create-template-form').on('submit', function(e) {
            e.preventDefault();
            const action = $(this).attr('data-action') || 'add';
            saveTemplate($(this), action);
        });

        // Show the modal for adding a new template
        $('.wee-add-template-btn').on('click', function() {
            $('#wee-add-template-modal').show();
        });

        // Handle the click event for using a template (auto-fill export form)
        $('.wee-use-template-btn').on('click', function() {
            const templateId = $(this).data('template-id');
            
            // Set the template in the export form
            $('#export-template').val(templateId);
            
            // Scroll to the export section
            $('html, body').animate({
                scrollTop: $('#wee-export-form').offset().top - 50
            }, 500);
            
            // Optional: Show a success message
            showNotice('Template selected for export!', 'success');
        });

        // Handle the click event for editing a template
        $('.wee-edit-template-btn').on('click', function() {
            const templateId = $(this).data('template-id');
            editTemplate(templateId);
        });

        // Handle the click event for deleting a template
        $('.wee-delete-template-btn').on('click', function() {
            const templateId = $(this).data('template-id');
            const templateName = $(this).data('template-name');
            
            if (confirm(`Are you sure you want to delete the template "${templateName}"?`)) {
                deleteTemplate(templateId);
            }
        });
    }

    // Helper Functions
    function saveTemplate($form, action) {
        const formData = new FormData($form[0]);
        formData.append('action', action === 'add' ? 'wee_add_template' : 'wee_edit_template');
        formData.append('nonce', wee_ajax.nonce);
        
        // Add template ID for edit action
        if (action === 'edit') {
            const templateId = $form.attr('data-template-id');
            if (templateId) {
                formData.append('template_id', templateId);
            }
        }
        
        // Add loading state
        const $submitBtn = $form.find('.wee-save-template-btn');
        const originalText = $submitBtn.text();
        $submitBtn.prop('disabled', true).text(action === 'add' ? 'Creating...' : 'Updating...');
        
        $.ajax({
            url: wee_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showNotice(response.data.message, 'success');
                    
                    // Reset form after successful save
                    if (action === 'add') {
                        resetTemplateForm($form);
                    }
                    
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotice(response.data.message, 'error');
                }
            },
            error: function() {
                showNotice('Error saving template', 'error');
            },
            complete: function() {
                $submitBtn.prop('disabled', false).text(originalText);
            }
        });
    }

    function resetTemplateForm($form) {
        // Reset form to create mode
        $form.removeAttr('data-action data-template-id');
        $form.find('h2').text('Create New Template');
        $form.find('.wee-save-template-btn').text('Save Template');
        
        // Clear form fields
        $form[0].reset();
        
        // Hide filters section
        const $filtersContent = $('#wee-template-filters-content');
        const $toggleBtn = $('#wee-toggle-template-filters');
        
        if (!$filtersContent.hasClass('wee-collapsed')) {
            $toggleBtn.click(); // This will toggle the section closed
        }
        
        // Update counter
        updateSelectedCount();
    }

    function editTemplate(templateId) {
        // Get template data
        $.ajax({
            url: wee_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wee_get_template',
                template_id: templateId,
                nonce: wee_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    populateTemplateForm(response.data, templateId);
                    // Scroll to the form
                    $('html, body').animate({
                        scrollTop: $('#wee-create-template-form').offset().top - 50
                    }, 500);
                    showNotice('Template loaded for editing', 'success');
                } else {
                    showNotice('Error loading template: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showNotice('Error loading template', 'error');
            }
        });
    }

    function populateTemplateForm(template, templateId) {
        const $form = $('#wee-create-template-form');
        
        // Update form action to edit
        $form.attr('data-action', 'edit');
        $form.attr('data-template-id', templateId);
        
        // Update form title and button text
        $form.find('h2').text('Edit Template');
        $form.find('.wee-save-template-btn').text('Update Template');
        
        // Populate basic fields
        $form.find('#template-name').val(template.name);
        $form.find('#template-description').val(template.description || '');
        
        // Clear all checkboxes first
        $form.find('input[type="checkbox"]').prop('checked', false);
        
        // Check selected columns (in the stored order)
        if (template.columns && Array.isArray(template.columns)) {
            template.columns.forEach(function(column) {
                const $checkbox = $form.find('input[value="' + column + '"]');
                $checkbox.prop('checked', true);
                
                // Handle custom column name if it exists
                if (template.column_names && template.column_names[column]) {
                    const $columnItem = $checkbox.closest('.wee-column-item');
                    const $customNameInput = $columnItem.find('.wee-custom-column-name');
                    $customNameInput.prop('disabled', false);
                    $customNameInput.val(template.column_names[column]);
                } else {
                    // Enable input and set default value
                    const $columnItem = $checkbox.closest('.wee-column-item');
                    const $customNameInput = $columnItem.find('.wee-custom-column-name');
                    $customNameInput.prop('disabled', false);
                    if (!$customNameInput.val()) {
                        $customNameInput.val($customNameInput.data('default'));
                    }
                }
                
                // Auto-expand the section containing this checkbox
                const $section = $checkbox.closest('.wee-column-section');
                if ($section.length) {
                    const $content = $section.find('.wee-column-section-content');
                    const $toggle = $section.find('.wee-section-toggle');
                    
                    $content.removeClass('wee-collapsed');
                    $toggle.addClass('wee-expanded');
                    $toggle.find('.wee-toggle-icon').text('−');
                }
            });
            
            // Set the column order for editing
            $('#wee-column-order').val(JSON.stringify(template.columns));
        }
        
        // Populate filters if they exist
        if (template.filters) {
            populateTemplateFilters(template.filters);
        }
        
        // Update selected count
        updateSelectedCount();
        
        // Scroll to top of form
        setTimeout(function() {
            $('html, body').animate({
                scrollTop: $form.offset().top - 100
            }, 500);
        }, 100);
    }

    function populateTemplateFilters(filters) {
        // Show filters section if we have filters
        if (Object.keys(filters).length > 0) {
            const $filtersContent = $('#wee-template-filters-content');
            const $toggleBtn = $('#wee-toggle-template-filters');
            
            if ($filtersContent.hasClass('wee-collapsed')) {
                $toggleBtn.click(); // This will toggle the section open
            }
        }
        
        // Populate individual filter fields
        if (filters.product_search) {
            $('#template-product-search').val(filters.product_search);
        }
        
        if (filters.product_categories && Array.isArray(filters.product_categories)) {
            $('#template-product-categories').val(filters.product_categories);
        }
        
        if (filters.order_status && Array.isArray(filters.order_status)) {
            $('#template-order-status').val(filters.order_status);
        }
        
        if (filters.payment_method) {
            $('#template-payment-method').val(filters.payment_method);
        }
        
        if (filters.order_total_min) {
            $('#template-order-total-min').val(filters.order_total_min);
        }
        
        if (filters.order_total_max) {
            $('#template-order-total-max').val(filters.order_total_max);
        }
        
        if (filters.custom_meta_key) {
            $('#template-custom-meta-key').val(filters.custom_meta_key);
        }
        
        if (filters.custom_meta_operator) {
            $('#template-custom-meta-operator').val(filters.custom_meta_operator);
        }
        
        if (filters.custom_meta_value) {
            $('#template-custom-meta-value').val(filters.custom_meta_value);
        }
    }

    function deleteTemplate(templateId) {
            $.ajax({
                url: wee_ajax.ajax_url,
                type: 'POST',
                data: {
                action: 'wee_delete_template',
                template_id: templateId,
                    nonce: wee_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                    showNotice(response.data.message, 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotice(response.data.message, 'error');
                }
            }
        });
    }

    function showNotice(message, type) {
        // Ensure the toast container exists
        let $container = $('#wee-toast-container');
        if (!$container.length) {
            $container = $('<div id="wee-toast-container"></div>');
            $('body').append($container);
        }

        const $notice = $(`<div class="wee-notice wee-${type}">${message}</div>`);
        $container.append($notice);

        // Animate in
        setTimeout(function() {
            $notice.addClass('wee-show');
        }, 100); // Small delay to allow element to be added to DOM first

        // Animate out and remove after 5 seconds
        setTimeout(function() {
            $notice.removeClass('wee-show');
            $notice.on('transitionend', function() {
                $(this).remove();
            });
        }, 5000);
    }

    // Column Ordering Functionality
    function initColumnOrdering() {
        const $selectedColumnsSection = $('.wee-selected-columns-section');
        const $selectedColumnsList = $('#wee-selected-columns-list');
        const $columnOrderInput = $('#wee-column-order');
        
        // Initialize sortable list
        if ($selectedColumnsList.length && typeof jQuery.ui !== 'undefined' && jQuery.ui.sortable) {
            $selectedColumnsList.sortable({
                handle: '.wee-drag-handle',
                placeholder: 'wee-sortable-placeholder',
                update: function() {
                    updateColumnOrder();
                }
            });
        }

        // Track selected columns
        function updateSelectedColumnsList() {
            const selectedColumns = [];
            const columnLabels = {};
            
            // Collect all column labels (using custom names if available)
            $('.wee-column-item').each(function() {
                const $columnItem = $(this);
                const $checkbox = $columnItem.find('input[type="checkbox"]');
                const value = $checkbox.val();
                const $customNameInput = $columnItem.find('.wee-custom-column-name');
                
                // Use custom name if available and not disabled, otherwise use default label
                let label;
                if (!$customNameInput.prop('disabled') && $customNameInput.val().trim()) {
                    label = $customNameInput.val().trim();
                } else {
                    label = $columnItem.find('.wee-checkbox span').text();
                }
                columnLabels[value] = label;
            });
            
            // Get checked columns
            const checkedColumns = [];
            $('.wee-column-grid input[type="checkbox"]:checked').each(function() {
                checkedColumns.push($(this).val());
            });
            
            // Check if we have a stored order
            const storedOrder = $columnOrderInput.val();
            let orderedColumns = [];
            
            if (storedOrder) {
                try {
                    const parsedOrder = JSON.parse(storedOrder);
                    if (Array.isArray(parsedOrder)) {
                        // Use stored order for checked columns
                        parsedOrder.forEach(function(columnValue) {
                            if (checkedColumns.includes(columnValue)) {
                                orderedColumns.push(columnValue);
                            }
                        });
                        // Add any newly checked columns that weren't in the stored order
                        checkedColumns.forEach(function(columnValue) {
                            if (!orderedColumns.includes(columnValue)) {
                                orderedColumns.push(columnValue);
                            }
                        });
                    }
                } catch (e) {
                    // Invalid JSON, use current order
                    orderedColumns = checkedColumns;
                }
            } else {
                orderedColumns = checkedColumns;
            }
            
            // Build selectedColumns array in proper order
            orderedColumns.forEach(function(columnValue) {
                selectedColumns.push({
                    value: columnValue,
                    label: columnLabels[columnValue]
                });
            });
            
            // Clear the list
            $selectedColumnsList.empty();
            
            if (selectedColumns.length === 0) {
                $selectedColumnsSection.hide();
                $columnOrderInput.val('');
                return;
            }
            
            // Show the section
            $selectedColumnsSection.show();
            
            // Add columns to sortable list
            selectedColumns.forEach(function(column) {
                const $item = $(`
                    <li class="wee-sortable-item" data-column="${column.value}">
                        <span class="wee-drag-handle">⋮⋮</span>
                        <span class="wee-column-name">${column.label}</span>
                        <button type="button" class="wee-remove-column" data-column="${column.value}">×</button>
                    </li>
                `);
                $selectedColumnsList.append($item);
            });
            
            updateColumnOrder();
        }
        
        function updateColumnOrder() {
            const order = [];
            $selectedColumnsList.find('.wee-sortable-item').each(function() {
                order.push($(this).data('column'));
            });
            $columnOrderInput.val(JSON.stringify(order));
        }
        
        // Listen for checkbox changes
        $(document).on('change', '.wee-column-grid input[type="checkbox"]', function() {
            updateSelectedColumnsList();
        });
        
        // Listen for remove button clicks
        $(document).on('click', '.wee-remove-column', function() {
            const columnValue = $(this).data('column');
            // Uncheck the corresponding checkbox
            $('.wee-column-grid input[value="' + columnValue + '"]').prop('checked', false);
            updateSelectedColumnsList();
            updateSelectedCount();
        });
        
        // Initialize on page load
        updateSelectedColumnsList();
        
        // Hook into the existing updateSelectedCount function if it exists
        setTimeout(function() {
            const existingUpdateCount = window.updateSelectedCount;
            if (existingUpdateCount) {
                window.updateSelectedCount = function() {
                    existingUpdateCount();
                    updateSelectedColumnsList();
                };
            }
        }, 100);
    }

    // Custom Column Names functionality
    function initCustomColumnNames() {
        // Enable/disable custom name inputs based on checkbox state
        $(document).on('change', '.wee-column-item input[type="checkbox"]', function() {
            const $checkbox = $(this);
            const $columnItem = $checkbox.closest('.wee-column-item');
            const $customNameInput = $columnItem.find('.wee-custom-column-name');
            
            if ($checkbox.is(':checked')) {
                $customNameInput.prop('disabled', false);
                $customNameInput.focus();
                // Set default value if empty
                if (!$customNameInput.val()) {
                    $customNameInput.val($customNameInput.data('default'));
                }
            } else {
                $customNameInput.prop('disabled', true);
                $customNameInput.val('');
            }
        });

        // Prevent disabled inputs from being submitted
        $('form').on('submit', function() {
            $(this).find('.wee-custom-column-name:disabled').prop('name', '');
        });

        // Update search functionality to work with new structure
        function updateSearchFunctionality() {
            const originalPerformSearch = window.performColumnSearch;
            if (typeof originalPerformSearch === 'function') {
                window.performColumnSearch = function(searchTerm) {
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
                                
                                if ($content.hasClass('wee-collapsed')) {
                                    $content.removeClass('wee-collapsed');
                                    $toggle.addClass('wee-expanded');
                                    $toggle.find('.wee-toggle-icon').text('−');
                                }
                            }
                        });
                    }

                    // Update section counters
                    $('.wee-column-section').each(function() {
                        const $section = $(this);
                        const totalItems = $section.find('.wee-column-item').length;
                        const visibleItems = $section.find('.wee-column-item:not(.wee-search-hidden)').length;
                        const $counter = $section.find('.wee-section-count');
                        
                        if (searchTerm !== '' && visibleItems !== totalItems) {
                            $counter.text(`(${visibleItems}/${totalItems} columns)`);
                        } else {
                            $counter.text(`(${totalItems} columns)`);
                        }
                    });
                };
            }
        }

        // Update search functionality after a short delay to ensure DOM is ready
        setTimeout(updateSearchFunctionality, 100);
        
        // Update the global updateSelectedCount function to work with new structure
        setTimeout(function() {
            if (typeof window.updateSelectedCount === 'function') {
                const originalUpdateCount = window.updateSelectedCount;
                window.updateSelectedCount = function() {
                    const count = $('.wee-column-item input[type="checkbox"]:checked').length;
                    $('#selected-count').text(count);
                };
            }
        }, 100);
    }
}); 