jQuery(document).ready(function($) {
    'use strict';

    // Initialize plugin when document is ready
    $(document).ready(function() {
        
        // Initialize plugin components
        
        // Initialize other components
        initProductSearch();
        initExportFunctionality();
        initTemplateManagement();
        initTemplateFilters();
        initColumnOrdering();
        initCustomColumnNames();
        initCombineFields();
        
        // Initialize section states after all components are loaded
        setTimeout(function() {
            initializeSectionStates();
            updateSelectedColumnsList();
            updateSelectedFieldsPreview();
        }, 100);
        
        // Also call it after a longer delay to ensure everything is loaded
        setTimeout(function() {
            updateSelectedFieldsPreview();
        }, 500);
        
        // Add a global checkbox change listener as a fallback
        $(document).on('change', 'input[type="checkbox"]', function() {
            const $checkbox = $(this);
            // Only trigger if it's a column checkbox
            if ($checkbox.attr('name') === 'columns[]' || $checkbox.closest('.wee-column-grid').length > 0) {
                setTimeout(function() {
                    updateSelectedFieldsPreview();
                }, 100);
            }
        });
    });
    
    /**
     * Initialize combine fields functionality
     */
    function initCombineFields() {
        // Test if the preview element exists - only initialize on templates page
        const $preview = $('#selected-fields-preview');
        if ($preview.length === 0) {
            return;
        }
        
        // Handle separator selection change
        $('#combined-field-separator').on('change', function() {
            const $this = $(this);
            const $customGroup = $('#custom-separator-group');
            
            if ($this.val() === 'custom') {
                $customGroup.show();
            } else {
                $customGroup.hide();
            }
        });
        
        // Handle select all / deselect all combine fields
        $('#select-all-combine-fields').on('click', function() {
            $('#selected-fields-preview .wee-combine-field-selector').prop('checked', true);
        });
        
        $('#deselect-all-combine-fields').on('click', function() {
            $('#selected-fields-preview .wee-combine-field-selector').prop('checked', false);
        });
        
        // Handle add combined field button
        $('#add-combined-field').on('click', function() {
            addCombinedField();
        });
        
        // Handle clear all combined fields button
        $('#clear-combined-fields').on('click', function() {
            clearAllCombinedFields();
        });
        
        // Update selected fields preview when columns change
        $(document).on('change', 'input[type="checkbox"][name="columns[]"]', function() {
            setTimeout(function() {
                updateSelectedFieldsPreview();
            }, 100);
        });
        
        // Also listen for any checkbox changes in the column grid
        $(document).on('change', '.wee-column-grid input[type="checkbox"]', function() {
            setTimeout(function() {
                updateSelectedFieldsPreview();
            }, 100);
        });
        
        // Hook into the existing column update function
        const originalUpdateSelectedColumnsList = window.updateSelectedColumnsList;
        if (originalUpdateSelectedColumnsList) {
            window.updateSelectedColumnsList = function() {
                originalUpdateSelectedColumnsList();
                setTimeout(function() {
                    updateSelectedFieldsPreview();
                }, 50);
            };
        }
        
        // Test checkbox detection
        const $allCheckboxes = $('.wee-column-grid input[type="checkbox"]');
        const $checkedCheckboxes = $('.wee-column-grid input[type="checkbox"]:checked');
        
        // Initial update
        updateSelectedFieldsPreview();
        
        // Make the function globally available for manual triggering
        window.updateSelectedFieldsPreview = updateSelectedFieldsPreview;
        
        // Add a debug function
        window.debugFieldSelection = function() {
            
            // Show details of checked checkboxes
            $('input[type="checkbox"][name="columns[]"]:checked').each(function(i, checkbox) {
                const $cb = $(checkbox);
                const $item = $cb.closest('.wee-column-item');
                // Debug info for checked checkboxes
            });
            
            updateSelectedFieldsPreview();
        };
        
        // Add a test button to the page
        if ($('#selected-fields-preview').length > 0) {
            const $testButton = $('<button type="button" id="wee-test-preview" style="margin: 10px; padding: 5px 10px; background: #0073aa; color: white; border: none; border-radius: 3px;">Test Preview Update</button>');
            $('#selected-fields-preview').before($testButton);
            
            $testButton.on('click', function() {
                updateSelectedFieldsPreview();
            });
        }
    }
    
    /**
     * Update the selected fields preview
     */
    function updateSelectedFieldsPreview() {
        const $preview = $('#selected-fields-preview');
        const $selectedCheckboxes = $('.wee-column-grid input[type="checkbox"]:checked');
        
        // Use the more reliable selector
        const $allPossibleCheckboxes = $('input[type="checkbox"][name="columns[]"]');
        const $allPossibleChecked = $('input[type="checkbox"][name="columns[]"]:checked');
        const $workingCheckboxes = $allPossibleChecked.length > 0 ? $allPossibleChecked : $selectedCheckboxes;
        
        if ($workingCheckboxes.length === 0) {
            $preview.html(`
                <div class="wee-no-fields-message">
                    <span class="dashicons dashicons-warning"></span>
                    <p>Select fields from the column selection above first. They will appear here for you to choose which ones to combine.</p>
                </div>
            `);
            $preview.removeClass('has-fields');
            // Hide select/deselect all buttons
            $('.wee-combine-field-actions').hide();
            return;
        }
        
        let fieldsHtml = '<div class="wee-selected-fields-list">';
        $workingCheckboxes.each(function() {
            const $checkbox = $(this);
            const $columnItem = $checkbox.closest('.wee-column-item');
            const fieldName = $columnItem.find('.wee-checkbox span').text();
            const fieldKey = $checkbox.val();
            
            fieldsHtml += `
                <div class="wee-selected-field-item" data-field-key="${fieldKey}">
                    <label class="wee-combine-field-checkbox">
                        <input type="checkbox" class="wee-combine-field-selector" value="${fieldKey}" checked>
                    <span class="dashicons dashicons-yes-alt"></span>
                    <span class="field-name">${fieldName}</span>
                    </label>
                </div>
            `;
        });
        fieldsHtml += '</div>';
        
        $preview.html(fieldsHtml);
        $preview.addClass('has-fields');
        // Show select/deselect all buttons
        $('.wee-combine-field-actions').show();
    }
    
    /**
     * Add a new combined field
     */
    function addCombinedField() {
        const fieldName = $('#combined-field-name').val().trim();
        const separator = $('#combined-field-separator').val();
        const customSeparator = $('#custom-separator').val().trim();
        
        // Get CHECKED fields from the preview
        const selectedFieldKeys = [];
        $('#selected-fields-preview .wee-combine-field-selector:checked').each(function() {
            selectedFieldKeys.push($(this).val());
        });
        
        // Validation
        if (!fieldName) {
            showNotice('Please enter a field name', 'error');
                    return;
                }
                
        if (selectedFieldKeys.length < 2) {
            showNotice('Please check at least 2 fields to combine', 'error');
                    return;
                }
                
        // Get full field info for selected fields
        const selectedFields = [];
        selectedFieldKeys.forEach(function(fieldKey) {
            const $checkbox = $('.wee-column-grid input[value="' + fieldKey + '"]');
            const $columnItem = $checkbox.closest('.wee-column-item');
            const fieldName = $columnItem.find('.wee-checkbox span').text();
            
            selectedFields.push({
                key: fieldKey,
                name: fieldName
            });
        });
        
        // Determine separator
        const finalSeparator = separator === 'custom' ? customSeparator : separator;
        
        // Create combined field object
        const combinedField = {
            id: 'combined_' + Date.now(),
            name: fieldName,
            separator: finalSeparator,
            fields: selectedFields
        };
        
        // Add to list
        addCombinedFieldToList(combinedField);
        
        // Clear form
        $('#combined-field-name').val('');
        $('#combined-field-separator').val(' ');
        $('#custom-separator').val('');
        $('#custom-separator-group').hide();
        
        // Uncheck all combine field selectors
        $('#selected-fields-preview .wee-combine-field-selector').prop('checked', false);
        
        showNotice('Combined field added successfully!', 'success');
    }
    
    /**
     * Add combined field to the list
     */
    function addCombinedFieldToList(combinedField) {
        const $list = $('#combined-fields-list');
        
        // Store field data as a data attribute for easy retrieval when saving
        const fieldKeys = combinedField.fields.map(f => f.key || f.value).join(',');
        const fieldNames = combinedField.fields.map(f => f.name).join(', ');
        
        // Encode separator for data attribute (spaces get stripped by HTML)
        const encodedSeparator = combinedField.separator === ' ' ? '___SPACE___' : combinedField.separator;
        
        const fieldHtml = `
            <div class="wee-combined-field-item" data-field-id="${combinedField.id}" 
                 data-field-keys="${fieldKeys}"
                 data-separator="${encodedSeparator}"
                 data-field-name="${combinedField.name}">
                <div class="wee-combined-field-header">
                    <div class="wee-field-info">
                        <h4>${combinedField.name}</h4>
                        <p>Combines: ${fieldNames}</p>
                    </div>
                    <button type="button" class="wee-remove-combined-field" data-field-id="${combinedField.id}">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="wee-combined-field-details">
                    <p><strong>Separator:</strong> "${combinedField.separator === ' ' ? 'Space' : (combinedField.separator || 'None')}"</p>
                    <p class="wee-field-keys" style="display:none;">${JSON.stringify(combinedField.fields)}</p>
                </div>
            </div>
        `;
        
        $list.append(fieldHtml);
        
        // Handle remove button
        $list.find(`[data-field-id="${combinedField.id}"] .wee-remove-combined-field`).on('click', function() {
            const $item = $(this).closest('.wee-combined-field-item');
            const fieldId = $item.data('field-id');
            
            // Remove from column ordering list
            removeCombinedFieldFromOrdering(fieldId);
            
            // Show individual fields again
            showIndividualFields(fieldId);
            
            // Remove the combined field item
            $item.fadeOut(300, function() {
                $(this).remove();
            });
        });
        
        // Add to column ordering list
        addCombinedFieldToOrdering(combinedField);
        
        // Hide individual fields that are part of this combined field
        hideIndividualFields(combinedField.fields);
    }
    
    /**
     * Clear all combined fields
     */
    function clearAllCombinedFields() {
        if (!confirm('Are you sure you want to clear all combined fields?')) {
            return;
        }
        
        $('#combined-fields-list').empty();
        showNotice('All combined fields cleared', 'success');
    }
    
    /**
     * Initialize export functionality
     */
    function initExportFunctionality() {
        // Only initialize on export page
        if ($('#wee-export-form').length === 0) {
            return;
        }
        
        // Handle export form submission
        $('#wee-export-form').on('submit', function(e) {
            e.preventDefault();
            exportOrders();
        });
        
        // Handle template selection buttons
        $(document).on('click', '.wee-use-template-btn', function(e) {
            e.preventDefault();
            const templateId = $(this).data('template-id');
            $('#export-template').val(templateId);
        });
    }
    
    /**
     * Export orders function
     */
    function exportOrders() {
        const $form = $('#wee-export-form');
        const templateId = $form.find('#export-template').val();
        const dateFrom = $form.find('input[name="date_from"]').val();
        const dateTo = $form.find('input[name="date_to"]').val();
        const exportFormat = $form.find('input[name="export_format"]').val();
        
        // Validation
        if (!templateId) {
            showNotice('Please select a template', 'error');
            return;
        }
        
        if (!dateFrom || !dateTo) {
            showNotice('Please select a date range', 'error');
            return;
        }
        
        // Show loading state
        const $exportBtn = $form.find('.wee-export-btn');
        const originalText = $exportBtn.text();
        $exportBtn.prop('disabled', true).text('Exporting...');
        
        // Prepare form data - template filters will be applied automatically on the backend
        const formData = new FormData();
        formData.append('action', 'wee_export_data');
        formData.append('nonce', $('#nonce').val());
        formData.append('template_id', templateId);
        formData.append('date_from', dateFrom);
        formData.append('date_to', dateTo);
        formData.append('export_format', exportFormat);
        
        // Create a hidden iframe for file download
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        document.body.appendChild(iframe);
        
        // Create form for iframe submission
        const iframeForm = document.createElement('form');
        iframeForm.method = 'POST';
        iframeForm.action = ajaxurl;
        iframeForm.target = iframe.name = 'export_iframe_' + Date.now();
        
        // Add form data
        for (let [key, value] of formData.entries()) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = value;
            iframeForm.appendChild(input);
        }
        
        // Handle iframe load event
        iframe.onload = function() {
            $exportBtn.prop('disabled', false).text(originalText);
            showNotice('Export completed successfully!', 'success');
            
            // Clean up
            setTimeout(function() {
                document.body.removeChild(iframe);
            }, 1000);
        };
        
        // Submit form
        document.body.appendChild(iframeForm);
        iframeForm.submit();
        document.body.removeChild(iframeForm);
    }
    
    /**
     * Initialize template management functionality
     */
    function initTemplateManagement() {
        // Only initialize on templates page
        if ($('#wee-create-template-form').length === 0) {
            return;
        }
        
        // Template form submission
        $('#wee-create-template-form').on('submit', function(e) {
            e.preventDefault();
            saveTemplate();
        });
        
        // Template action buttons
        $(document).on('click', '.wee-edit-template-btn', function(e) {
            e.preventDefault();
            const templateId = $(this).data('template-id');
            openEditModal(templateId);
        });
        
        $(document).on('click', '.wee-duplicate-template-btn', function(e) {
            e.preventDefault();
            const templateId = $(this).data('template-id');
            const templateName = $(this).data('template-name');
            duplicateTemplate(templateId, templateName);
        });
        
        $(document).on('click', '.wee-delete-template-btn', function(e) {
            e.preventDefault();
            const templateId = $(this).data('template-id');
            const templateName = $(this).data('template-name');
            deleteTemplate(templateId, templateName);
        });
        
        // Edit modal handlers
        $(document).on('click', '.wee-modal-close, .wee-modal-cancel', function(e) {
            e.preventDefault();
            closeEditModal();
        });
        
        $(document).on('click', '.wee-modal-overlay', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
        
        // Edit modal form submission
        $('#wee-edit-template-form').on('submit', function(e) {
            e.preventDefault();
            saveEditedTemplate();
        });
        
        // Edit modal column checkbox change - update counter
        $(document).on('change', '#wee-edit-columns-container input[type="checkbox"]', function() {
            updateEditModalColumnCount();
        });
        
        // Edit modal section toggles
        $(document).on('click', '#wee-edit-columns-container .wee-section-toggle', function(e) {
            console.log('WEE DEBUG: Toggle button clicked in edit modal');
            e.preventDefault();
            e.stopPropagation();
            
            const $toggle = $(this);
            const $content = $toggle.closest('.wee-column-section').find('.wee-column-section-content');
            const isCollapsed = $content.hasClass('wee-collapsed');
            
            console.log('WEE DEBUG: Current state - collapsed:', isCollapsed);
            
            if (isCollapsed) {
                $content.removeClass('wee-collapsed');
                $toggle.addClass('wee-expanded');
                $toggle.find('.wee-toggle-icon').text('−');
                console.log('WEE DEBUG: Expanded section');
            } else {
                $content.addClass('wee-collapsed');
                $toggle.removeClass('wee-expanded');
                $toggle.find('.wee-toggle-icon').text('+');
                console.log('WEE DEBUG: Collapsed section');
            }
        });
        
        // Edit modal expand/collapse all
        $('#wee-edit-expand-all-sections').on('click', function(e) {
            e.preventDefault();
            $('#wee-edit-columns-container .wee-column-section-content').removeClass('wee-collapsed');
            $('#wee-edit-columns-container .wee-section-toggle').addClass('wee-expanded').find('.wee-toggle-icon').text('−');
        });
        
        $('#wee-edit-collapse-all-sections').on('click', function(e) {
            e.preventDefault();
            $('#wee-edit-columns-container .wee-column-section-content').addClass('wee-collapsed');
            $('#wee-edit-columns-container .wee-section-toggle').removeClass('wee-expanded').find('.wee-toggle-icon').text('+');
        });
        
        // Edit modal select/deselect all
        $(document).on('click', '#wee-edit-columns-container .wee-select-all', function(e) {
            e.preventDefault();
            const section = $(this).data('section');
            $(`#wee-edit-columns-container .wee-column-section[data-section="${section}"] input[type="checkbox"]`).prop('checked', true);
            updateEditModalColumnCount();
            updateEditModalColumnOrderingList();
        });
        
        $(document).on('click', '#wee-edit-columns-container .wee-deselect-all', function(e) {
            e.preventDefault();
            const section = $(this).data('section');
            $(`#wee-edit-columns-container .wee-column-section[data-section="${section}"] input[type="checkbox"]`).prop('checked', false);
            updateEditModalColumnCount();
            updateEditModalColumnOrderingList();
        });
        
        // Edit modal checkbox change handler
        $(document).on('change', '#wee-edit-columns-container input[type="checkbox"]', function() {
            updateEditModalColumnCount();
            updateEditModalColumnOrderingList();
        });
        
        // Initialize sortable for edit modal column ordering
        if (typeof $.fn.sortable !== 'undefined') {
            $('#wee-edit-selected-columns-list').sortable({
                handle: '.wee-column-drag-handle',
                placeholder: 'wee-sortable-placeholder',
                axis: 'y',
                cursor: 'move',
                tolerance: 'pointer'
            });
        }
        
        // Edit modal column search functionality
        let editSearchTimeout;
        $('#wee-edit-column-search').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            console.log('WEE DEBUG: Edit modal search:', searchTerm);
            
            clearTimeout(editSearchTimeout);
            editSearchTimeout = setTimeout(function() {
                performEditModalColumnSearch(searchTerm);
            }, 300);
        });
        
        // Edit modal combined fields - handle separator selection
        $('#edit-combined-field-separator').on('change', function() {
            const $customGroup = $('#edit-custom-separator-group');
            if ($(this).val() === 'custom') {
                $customGroup.show();
            } else {
                $customGroup.hide();
            }
        });
        
        // Edit modal combined fields - handle add button
        $('#edit-add-combined-field').on('click', function() {
            addEditModalCombinedField();
        });
        
        // Edit modal combined fields - handle clear all button
        $('#edit-clear-combined-fields').on('click', function() {
            if (confirm('Are you sure you want to clear all combined fields?')) {
                clearEditModalCombinedFields();
            }
        });
        
        // Edit modal combined fields - handle select/deselect all
        $('#edit-select-all-combine-fields').on('click', function() {
            $('#edit-selected-fields-preview .wee-combine-field-selector').prop('checked', true);
        });
        
        $('#edit-deselect-all-combine-fields').on('click', function() {
            $('#edit-selected-fields-preview .wee-combine-field-selector').prop('checked', false);
        });
        
        // Update edit modal fields preview when columns change
        $(document).on('change', '#wee-edit-columns-container input[type="checkbox"]', function() {
            updateEditModalSelectedFieldsPreview();
        });
    }
    
    /**
     * Save template
     */
    function saveTemplate() {
        const $form = $('#wee-create-template-form');
        const formData = new FormData();
        
        // Check if we're editing
        const editingTemplateId = $form.data('editing-template-id');
        const isEditing = editingTemplateId && editingTemplateId > 0;
        console.log('WEE DEBUG: ========== SAVE TEMPLATE START ==========');
        console.log('WEE DEBUG: Saving template - isEditing:', isEditing, 'templateId:', editingTemplateId);
        console.log('WEE DEBUG: Button text:', $('.wee-save-template-btn').text());
        
        // Verify the editing ID is still set
        if (!editingTemplateId && $('.wee-save-template-btn').text() === 'Update Template') {
            console.error('WEE ERROR: Button says "Update Template" but no editing ID found!');
            showNotice('Error: Template ID lost. Please try editing again.', 'error');
            return;
        }
        
        // Get selected columns
        const selectedColumns = [];
        console.log('WEE DEBUG: Collecting checked checkboxes...');
        console.log('WEE DEBUG: Total checkboxes:', $('.wee-column-grid input[type="checkbox"]').length);
        console.log('WEE DEBUG: Checked checkboxes:', $('.wee-column-grid input[type="checkbox"]:checked').length);
        $('.wee-column-grid input[type="checkbox"]:checked').each(function() {
            const columnKey = $(this).val();
            const isHidden = $(this).closest('.wee-column-item').hasClass('wee-hidden-by-combined');
            console.log('WEE DEBUG: Checkbox:', columnKey, 'hidden by combined:', isHidden);
            selectedColumns.push(columnKey);
        });
        console.log('WEE DEBUG: Selected columns to save:', selectedColumns);
        
        // CRITICAL DEBUG: Check if checkboxes are actually in checked state
        if (selectedColumns.length === 0) {
            console.error('WEE ERROR: NO COLUMNS COLLECTED! Investigating...');
            console.log('WEE DEBUG: Total checkboxes in DOM:', $('.wee-column-grid input[type="checkbox"]').length);
            console.log('WEE DEBUG: Checked checkboxes:', $('.wee-column-grid input[type="checkbox"]:checked').length);
            console.log('WEE DEBUG: All checkboxes:', $('.wee-column-grid input[type="checkbox"]').map(function() {
                return $(this).val() + ':' + ($(this).is(':checked') ? 'CHECKED' : 'UNCHECKED');
            }).get().slice(0, 10).join(', '));
            
            // Try alternative selector
            const altSelected = $('input[name="columns[]"]:checked');
            console.log('WEE DEBUG: Alternative selector found:', altSelected.length, 'checked');
            
            if (altSelected.length > 0) {
                console.log('WEE WARNING: Using alternative selector to collect columns!');
                altSelected.each(function() {
                    selectedColumns.push($(this).val());
                });
            }
        }
        
        // Get column visibility from the ordering list
        const columnVisibility = {};
        $('#wee-selected-columns-list .wee-selected-column-item').each(function() {
            const $item = $(this);
            const columnKey = $item.data('column');
            const isVisible = $item.find('.wee-show-column').is(':checked');
            columnVisibility[columnKey] = isVisible;
        });
        
        // Get combined fields
        const combinedFields = [];
        console.log('WEE DEBUG: Looking for combined fields in ordering list...');
        console.log('WEE DEBUG: Found combined field items:', $('#wee-selected-columns-list .wee-selected-column-item[data-type="combined"]').length);
        $('#wee-selected-columns-list .wee-selected-column-item[data-type="combined"]').each(function() {
            const $item = $(this);
            const fieldId = $item.data('column');
            const fieldKeys = $item.data('field-keys');
            let separator = $item.data('separator');
            const isVisible = $item.find('.wee-show-column').is(':checked');
            
            // Decode separator (spaces are encoded as ___SPACE___)
            if (separator === '___SPACE___') {
                separator = ' ';
            }
            
            console.log('WEE DEBUG: Processing combined field:', fieldId, fieldKeys, separator);
            
            // Find the original combined field data
            const $originalField = $(`.wee-combined-field-item[data-field-id="${fieldId}"]`);
            if ($originalField.length > 0) {
                const fieldName = $originalField.data('field-name');
                const fieldsJson = $originalField.find('.wee-field-keys').text();
                
                try {
                    const fields = JSON.parse(fieldsJson);
                    combinedFields.push({
                        id: fieldId,
                        name: fieldName,
                        separator: separator,
                        fields: fields,
                        visible: isVisible
                    });
                } catch (e) {
                    console.error('WEE: Error parsing combined field data:', e);
                }
            }
        });
        
        // Get custom column names
        const columnNames = {};
        $('.wee-custom-column-name').each(function() {
            const $input = $(this);
            if (!$input.prop('disabled') && $input.val().trim()) {
                const columnKey = $input.closest('.wee-column-item').data('column-key');
                columnNames[columnKey] = $input.val().trim();
            }
        });
        
        // Get template filters - collect all filter values
        const templateFilters = {};
        
        // Order status (multi-select)
        const orderStatus = $('#template-order-status').val();
        if (orderStatus && orderStatus.length > 0) {
            templateFilters.order_status = orderStatus;
        }
        
        // Payment method
        const paymentMethod = $('#template-payment-method').val();
        if (paymentMethod) {
            templateFilters.payment_method = paymentMethod;
        }
        
        // Order totals
        const orderTotalMin = $('#template-order-total-min').val();
        if (orderTotalMin) {
            templateFilters.order_total_min = parseFloat(orderTotalMin);
        }
        
        const orderTotalMax = $('#template-order-total-max').val();
        if (orderTotalMax) {
            templateFilters.order_total_max = parseFloat(orderTotalMax);
        }
        
        // Product categories (multi-select)
        const productCategories = $('#template-product-categories').val();
        if (productCategories && productCategories.length > 0) {
            templateFilters.product_categories = productCategories;
        }
        
        // Product search (from hidden field with JSON)
        const productSearch = $('#template-product-search-value').val();
        if (productSearch) {
            templateFilters.product_search = productSearch;
        }
        
        // Custom meta filters
        const customMetaKey = $('#template-custom-meta-key').val();
        if (customMetaKey) {
            templateFilters.custom_meta_key = customMetaKey;

            const customMetaOperator = $('#template-custom-meta-operator').val();
            if (customMetaOperator) {
                templateFilters.custom_meta_operator = customMetaOperator;
            }

            const customMetaValue = $('#template-custom-meta-value').val();
            if (customMetaValue) {
                templateFilters.custom_meta_value = customMetaValue;
            }
        }

        // TGF submission type filter
        const tgfSubmissionKey = $('#template-tgf-submission-key').val();
        if (tgfSubmissionKey) {
            templateFilters.tgf_submission_key = tgfSubmissionKey;
        }

        // TGF grading options contains filter
        const tgfGradingContains = $('#template-tgf-grading-contains').val();
        if (tgfGradingContains) {
            templateFilters.tgf_grading_contains = tgfGradingContains;
        }

        // TGF service level contains filter
        const tgfServiceLevelContains = $('#template-tgf-service-level-contains').val();
        if (tgfServiceLevelContains) {
            templateFilters.tgf_service_level_contains = tgfServiceLevelContains;
        }

        console.log('WEE DEBUG: Template filters collected:', templateFilters);
        
        console.log('WEE DEBUG: Total combined fields collected:', combinedFields.length);
        console.log('WEE DEBUG: Combined fields data:', combinedFields);
        
        // Combined fields are now handled above
        
        // Prepare form data
        const action = isEditing ? 'wee_edit_template' : 'wee_add_template';
        console.log('WEE DEBUG: Using action:', action);
        formData.append('action', action);
        formData.append('nonce', $('#nonce').val());
        formData.append('template_name', $form.find('#template-name').val());
        formData.append('template_description', $form.find('#template-description').val());
        formData.append('columns', JSON.stringify(selectedColumns));
        formData.append('column_names', JSON.stringify(columnNames));
        formData.append('template_filters', JSON.stringify(templateFilters));
        formData.append('combined_fields', JSON.stringify(combinedFields));
        console.log('WEE DEBUG: Sending combined_fields JSON:', JSON.stringify(combinedFields));
        formData.append('column_visibility', JSON.stringify(columnVisibility));
        
        // Add template ID if editing
        if (isEditing) {
            formData.append('template_id', editingTemplateId);
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('WEE DEBUG: Save response:', response);
                if (response.success) {
                    const message = isEditing ? 'Template updated successfully!' : 'Template saved successfully!';
                    showNotice(message, 'success');
                    
                    // Clear the form and reset button text
                    clearTemplateForm();
                    $('.wee-save-template-btn').text('Save Template');
                    $form.removeData('editing-template-id');
                    
                    // Refresh to show the updated template
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    // Handle error message properly
                    let errorMessage = 'Failed to save template';
                    if (response.data) {
                        if (typeof response.data === 'string') {
                            errorMessage = response.data;
                        } else if (response.data.message) {
                            errorMessage = response.data.message;
                        } else if (typeof response.data === 'object') {
                            errorMessage = JSON.stringify(response.data);
                        }
                    }
                    console.error('WEE: Save failed:', errorMessage, response);
                    showNotice(errorMessage, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('WEE: Template save AJAX error:', {xhr, status, error});
                console.error('WEE: Response text:', xhr.responseText);
                showNotice('An error occurred while saving the template: ' + error, 'error');
            }
        });
    }
    
    /**
     * Load template for editing
     */
    function loadTemplate(templateId) {
        
        // Set the editing template ID on the form
        $('#wee-create-template-form').data('editing-template-id', templateId);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wee_get_template',
                template_id: templateId,
                nonce: $('#nonce').val()
            },
            success: function(response) {
                console.log('WEE DEBUG: Load template response:', response);
                if (response.success) {
                    console.log('WEE DEBUG: Template data columns:', response.data.columns);
                    console.log('WEE DEBUG: Template data filters:', response.data.filters);
                    console.log('WEE DEBUG: Template data combined_fields:', response.data.combined_fields);
                    populateTemplateForm(response.data);
                } else {
                    showNotice(response.data || 'Failed to load template', 'error');
                }
            },
            error: function() {
                showNotice('An error occurred while loading the template', 'error');
            }
        });
    }
    
    /**
     * Duplicate template
     */
    function duplicateTemplate(templateId, templateName) {
        
        if (!confirm(`Are you sure you want to duplicate "${templateName}"?`)) {
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wee_duplicate_template',
                template_id: templateId,
                nonce: $('#nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    showNotice('Template duplicated successfully!', 'success');
                    // Refresh the page to show the new template
                    location.reload();
                } else {
                    showNotice(response.data || 'Failed to duplicate template', 'error');
                }
            },
            error: function() {
                showNotice('An error occurred while duplicating the template', 'error');
            }
        });
    }
    
    /**
     * Delete template
     */
    function deleteTemplate(templateId, templateName) {
        // console.log('WEE: Deleting template:', templateId);
        
        if (!confirm(`Are you sure you want to delete "${templateName}"? This action cannot be undone.`)) {
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wee_delete_template',
                template_id: templateId,
                nonce: $('#nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    showNotice('Template deleted successfully!', 'success');
                    // Remove the template card from the DOM
                    $(`.wee-template-card:has([data-template-id="${templateId}"])`).fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    showNotice(response.data || 'Failed to delete template', 'error');
                }
            },
            error: function() {
                showNotice('An error occurred while deleting the template', 'error');
            }
        });
    }
    
    /**
     * Open edit modal
     */
    function openEditModal(templateId) {
        console.log('WEE DEBUG: Opening edit modal for template:', templateId);
        
        // Load the template data
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wee_get_template',
                template_id: templateId,
                nonce: $('#nonce').val()
            },
            success: function(response) {
                if (response.success && response.data) {
                    const template = response.data;
                    console.log('WEE DEBUG: Template loaded for edit:', template);
                    
                    // Populate the MODAL form (not the main form)
                    populateEditModalForm(template);
                    
                    // Show the modal
                    $('#wee-edit-modal').fadeIn(200);
                    $('body').css('overflow', 'hidden');
                } else {
                    showNotice('Failed to load template', 'error');
                }
            },
            error: function() {
                showNotice('An error occurred while loading the template', 'error');
            }
        });
    }
    
    /**
     * Populate edit modal form with template data
     */
    function populateEditModalForm(template) {
        console.log('WEE DEBUG: Populating edit modal form:', template);
        
        // Set template ID
        $('#edit-template-id').val(template.id);
        
        // Set template name and description
        $('#edit-template-name').val(template.name);
        $('#edit-template-description').val(template.description || '');
        
        // Parse columns if it's a string
        let columns = template.columns;
        if (typeof columns === 'string') {
            try {
                columns = JSON.parse(columns);
            } catch (e) {
                console.error('WEE: Error parsing columns:', e);
                columns = [];
            }
        }
        
        // Clear all checkboxes in modal first
        $('#wee-edit-columns-container input[type="checkbox"]').prop('checked', false);
        
        // Select columns in modal
        if (columns && Array.isArray(columns)) {
            console.log('WEE DEBUG: Selecting', columns.length, 'columns in modal');
            columns.forEach(function(column) {
                const $checkbox = $(`#wee-edit-columns-container input[value="${column}"]`);
                if ($checkbox.length > 0) {
                    $checkbox.prop('checked', true);
                    // Enable custom name input for checked columns
                    const $columnItem = $checkbox.closest('.wee-column-item');
                    const $customNameInput = $columnItem.find('.wee-custom-column-name');
                    $customNameInput.prop('disabled', false);
                } else {
                    console.warn('WEE DEBUG: Checkbox not found for column:', column);
                }
            });
        }
        
        // Populate custom column names if available
        if (template.column_names) {
            let columnNames = template.column_names;
            if (typeof columnNames === 'string') {
                try {
                    columnNames = JSON.parse(columnNames);
                } catch (e) {
                    console.error('WEE: Error parsing column_names:', e);
                    columnNames = {};
                }
            }
            
            if (columnNames && typeof columnNames === 'object') {
                console.log('WEE DEBUG: Populating custom column names:', columnNames);
                Object.keys(columnNames).forEach(function(columnKey) {
                    const customName = columnNames[columnKey];
                    if (customName) {
                        const $input = $(`#wee-edit-columns-container input[name="edit_column_names[${columnKey}]"]`);
                        if ($input.length > 0) {
                            $input.val(customName);
                        }
                    }
                });
            }
        }
        
        // Load column_order if available (includes both columns and combined field IDs)
        let columnOrder = [];
        if (template.column_order) {
            if (typeof template.column_order === 'string') {
                try {
                    columnOrder = JSON.parse(template.column_order);
                } catch (e) {
                    console.error('WEE: Error parsing column_order:', e);
                    columnOrder = [];
                }
            } else if (Array.isArray(template.column_order)) {
                columnOrder = template.column_order;
            }
        }
        
        // Update column counter
        updateEditModalColumnCount();
        // Don't update ordering list yet - wait until after combined fields are loaded
        
        // Parse filters if it's a string
        let filters = template.filters;
        if (typeof filters === 'string') {
            try {
                filters = JSON.parse(filters);
            } catch (e) {
                console.error('WEE: Error parsing filters:', e);
                filters = {};
            }
        }
        
        // Set filters in modal
        if (filters && typeof filters === 'object') {
            console.log('WEE DEBUG: Populating filters in modal:', filters);
            
            // Order status
            if (filters.order_status) {
                const values = Array.isArray(filters.order_status) ? filters.order_status : [filters.order_status];
                $('#edit-template-order-status').val(values);
            }
            
            // Payment method
            if (filters.payment_method) {
                $('#edit-template-payment-method').val(filters.payment_method);
            }
            
            // Product categories
            if (filters.product_categories) {
                const values = Array.isArray(filters.product_categories) ? filters.product_categories : [filters.product_categories];
                $('#edit-template-product-categories').val(values.map(String));
            }
            
            // Product search
            if (filters.product_search) {
                $('#edit-template-product-search-value').val(filters.product_search);
                // Trigger display of selected products
                displayEditModalSelectedProducts(filters.product_search);
            }

            // TGF submission type
            if (filters.tgf_submission_key) {
                $('#edit-template-tgf-submission-key').val(filters.tgf_submission_key);
            }

            // TGF grading contains
            if (filters.tgf_grading_contains) {
                $('#edit-template-tgf-grading-contains').val(filters.tgf_grading_contains);
            }

            // TGF service level contains
            if (filters.tgf_service_level_contains) {
                $('#edit-template-tgf-service-level-contains').val(filters.tgf_service_level_contains);
            }
        }
        
        // Load combined fields
        if (template.combined_fields) {
            let combinedFields = template.combined_fields;
            if (typeof combinedFields === 'string') {
                try {
                    combinedFields = JSON.parse(combinedFields);
                } catch (e) {
                    console.error('WEE: Error parsing combined_fields:', e);
                    combinedFields = [];
                }
            }
            
            if (Array.isArray(combinedFields) && combinedFields.length > 0) {
                console.log('WEE DEBUG: Loading combined fields:', combinedFields);
                
                // Clear any existing combined fields
                $('#edit-combined-fields-list').empty();
                
                // Add each combined field
                combinedFields.forEach(function(field) {
                    let fields = [];
                    
                    // Handle both formats: field_keys (old) and fields (new)
                    if (field.fields && Array.isArray(field.fields)) {
                        // New format: already has fields array with objects
                        fields = field.fields.map(function(f) {
                            return {
                                key: f.key || f.value,
                                name: f.name
                            };
                        });
                    } else if (field.field_keys && Array.isArray(field.field_keys)) {
                        // Old format: has field_keys array of strings
                        field.field_keys.forEach(function(fieldKey) {
                            const $checkbox = $(`#wee-edit-columns-container input[value="${fieldKey}"]`);
                            const $columnItem = $checkbox.closest('.wee-column-item');
                            const fieldName = $columnItem.find('.wee-checkbox span').text();
                            
                            fields.push({
                                key: fieldKey,
                                name: fieldName
                            });
                        });
                    } else {
                        console.error('WEE: Combined field has no fields or field_keys:', field);
                        return;
                    }
                    
                    const combinedField = {
                        id: field.id,
                        name: field.name,
                        separator: field.separator,
                        fields: fields
                    };
                    
                    addEditModalCombinedFieldToList(combinedField);
                });
            }
        }
        
        // Update the fields preview for combining
        updateEditModalSelectedFieldsPreview();
        
        // Update ordering list AFTER combined fields are loaded - use column_order if available
        const orderToUse = columnOrder.length > 0 ? columnOrder : columns;
        console.log('WEE DEBUG: Updating ordering list with order:', orderToUse);
        updateEditModalColumnOrderingList(orderToUse);
        
        // Load column visibility settings
        if (template.column_visibility) {
            let columnVisibility = template.column_visibility;
            if (typeof columnVisibility === 'string') {
                try {
                    columnVisibility = JSON.parse(columnVisibility);
                } catch (e) {
                    console.error('WEE: Error parsing column_visibility:', e);
                    columnVisibility = {};
                }
            }
            
            if (columnVisibility && typeof columnVisibility === 'object') {
                console.log('WEE DEBUG: Applying column visibility:', columnVisibility);
                
                // Wait a bit for the ordering list to be fully rendered
                setTimeout(function() {
                    Object.keys(columnVisibility).forEach(function(columnKey) {
                        const isVisible = columnVisibility[columnKey];
                        const $toggle = $(`#wee-edit-selected-columns-list .wee-selected-column-item[data-column="${columnKey}"] .wee-show-column`);
                        
                        if ($toggle.length > 0) {
                            $toggle.prop('checked', isVisible);
                            const $text = $toggle.siblings('.wee-toggle-text');
                            $text.text(isVisible ? 'Show' : 'Hide');
                            
                            // Toggle the hidden class on the list item
                            const $item = $toggle.closest('.wee-selected-column-item');
                            $item.toggleClass('wee-hidden-column', !isVisible);
                        }
                    });
                    
                    // Also apply visibility for combined fields
                    if (template.combined_fields) {
                        let combinedFields = template.combined_fields;
                        if (typeof combinedFields === 'string') {
                            combinedFields = JSON.parse(combinedFields);
                        }
                        
                        if (Array.isArray(combinedFields)) {
                            combinedFields.forEach(function(field) {
                                if (typeof field.visible !== 'undefined') {
                                    const $toggle = $(`#wee-edit-selected-columns-list .wee-selected-column-item[data-column="${field.id}"] .wee-show-column`);
                                    if ($toggle.length > 0) {
                                        $toggle.prop('checked', field.visible);
                                        const $text = $toggle.siblings('.wee-toggle-text');
                                        $text.text(field.visible ? 'Show' : 'Hide');
                                        
                                        const $item = $toggle.closest('.wee-selected-column-item');
                                        $item.toggleClass('wee-hidden-column', !field.visible);
                                    }
                                }
                            });
                        }
                    }
                }, 100);
            }
        }
        
        console.log('WEE DEBUG: Edit modal form populated successfully');
    }
    
    /**
     * Update edit modal column counter
     */
    function updateEditModalColumnCount() {
        const count = $('#wee-edit-columns-container input[type="checkbox"]:checked').length;
        $('#edit-selected-count').text(count);
    }
    
    /**
     * Update edit modal column ordering list
     * @param {Array} orderedColumns - Optional array of column keys in specific order
     */
    function updateEditModalColumnOrderingList(orderedColumns) {
        const $list = $('#wee-edit-selected-columns-list');
        const $orderingSection = $('#wee-edit-column-ordering-section');
        
        // Get all checked checkboxes
        const checkedBoxes = $('#wee-edit-columns-container input[type="checkbox"]:checked');
        
        // Get all combined fields
        const combinedFields = [];
        $('#edit-combined-fields-list .wee-combined-field-item').each(function() {
            const $item = $(this);
            combinedFields.push({
                id: $item.data('field-id'),
                name: $item.find('.wee-field-name strong').text()
            });
        });
        
        if (checkedBoxes.length === 0 && combinedFields.length === 0) {
            $orderingSection.hide();
            return;
        }
        
        // Show ordering section
        $orderingSection.show();
        
        // Clear existing list (but preserve sortable functionality)
        $list.empty();
        
        // Create a map of column data for quick lookup
        const columnData = {};
        checkedBoxes.each(function() {
            const $checkbox = $(this);
            const $columnItem = $checkbox.closest('.wee-column-item');
            const columnKey = $checkbox.val();
            const columnLabel = $checkbox.next('span').text();
            const $customNameInput = $columnItem.find('.wee-custom-column-name');
            const customName = $customNameInput.val() || '';
            
            columnData[columnKey] = {
                label: columnLabel,
                customName: customName,
                type: 'regular'
            };
        });
        
        // Add combined fields to the data map
        combinedFields.forEach(function(field) {
            columnData[field.id] = {
                label: field.name,
                customName: '',
                type: 'combined'
            };
        });
        
        // If we have a specific order, use it; otherwise use the checked boxes order plus combined fields
        let columnsToRender;
        if (orderedColumns && Array.isArray(orderedColumns) && orderedColumns.length > 0) {
            columnsToRender = orderedColumns;
        } else {
            columnsToRender = Array.from(checkedBoxes).map(cb => $(cb).val());
            // Add combined field IDs
            combinedFields.forEach(function(field) {
                columnsToRender.push(field.id);
            });
        }
        
        columnsToRender.forEach(function(columnKey) {
            if (columnData[columnKey]) {
                const data = columnData[columnKey];
                const customNameDisplay = data.customName ? `<br><small class="wee-custom-name">Custom name: ${data.customName}</small>` : '';
                const combinedBadge = data.type === 'combined' ? '<br><small class="wee-combined-badge">Combined Field</small>' : '';
                
                const $listItem = $(`
                    <li class="wee-selected-column-item" data-column="${columnKey}" data-type="${data.type}">
                        <div class="wee-column-drag-handle">
                            <span class="dashicons dashicons-menu"></span>
                        </div>
                        <div class="wee-column-info">
                            <span class="wee-column-label">
                                <strong>${data.label}</strong>
                                ${customNameDisplay}
                                ${combinedBadge}
                            </span>
                            <div class="wee-column-actions">
                                <label class="wee-show-hide-toggle">
                                    <input type="checkbox" class="wee-show-column" checked>
                                    <span class="wee-toggle-text">Show</span>
                                </label>
                            </div>
                        </div>
                    </li>
                `);
                
                $list.append($listItem);
            }
        });
    }
    
    /**
     * Perform column search in edit modal
     */
    function performEditModalColumnSearch(searchTerm) {
        // Search within edit modal only
        $('#wee-edit-columns-container .wee-column-item').each(function() {
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
            $('#wee-edit-columns-container .wee-column-section').each(function() {
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
        updateEditModalSectionCounts(searchTerm);
    }
    
    /**
     * Update section counts in edit modal during search
     */
    function updateEditModalSectionCounts(searchTerm) {
        $('#wee-edit-columns-container .wee-column-section').each(function() {
            const $section = $(this);
            const $countSpan = $section.find('.wee-section-count');
            const totalColumns = $section.find('.wee-column-item').length;
            const visibleColumns = $section.find('.wee-column-item:not(.wee-search-hidden)').length;
            
            if (searchTerm !== '' && visibleColumns !== totalColumns) {
                $countSpan.text(`(${visibleColumns}/${totalColumns} columns)`);
                if (visibleColumns === 0) {
                    $countSpan.css('color', '#dc3232');
                } else {
                    $countSpan.css('color', '#2271b1');
                }
            } else {
                $countSpan.text(`(${totalColumns} columns)`);
                $countSpan.css('color', '');
            }
        });
    }
    
    /**
     * Update selected fields preview in edit modal for combining
     */
    function updateEditModalSelectedFieldsPreview() {
        const $preview = $('#edit-selected-fields-preview');
        const $combinedFieldsActions = $('.wee-edit-combine-fields-section .wee-combine-field-actions');
        
        // Get all checked columns
        const checkedColumns = $('#wee-edit-columns-container input[type="checkbox"]:checked');
        
        if (checkedColumns.length === 0) {
            $preview.html('<div class="wee-no-fields-message"><span class="dashicons dashicons-warning"></span><p>Select fields from the column selection above first. They will appear here for you to choose which ones to combine.</p></div>');
            $combinedFieldsActions.hide();
            return;
        }
        
        $preview.empty();
        $combinedFieldsActions.show();
        
        checkedColumns.each(function() {
            const $checkbox = $(this);
            const $columnItem = $checkbox.closest('.wee-column-item');
            const columnKey = $checkbox.val();
            const columnLabel = $columnItem.find('.wee-checkbox span').text();
            
            const $fieldItem = $(`
                <div class="wee-combine-field-item">
                    <label>
                        <input type="checkbox" class="wee-combine-field-selector" value="${columnKey}">
                        <span>${columnLabel}</span>
                    </label>
                </div>
            `);
            
            $preview.append($fieldItem);
        });
    }
    
    /**
     * Add a new combined field in edit modal
     */
    function addEditModalCombinedField() {
        const fieldName = $('#edit-combined-field-name').val().trim();
        const separator = $('#edit-combined-field-separator').val();
        const customSeparator = $('#edit-custom-separator').val().trim();
        
        // Get CHECKED fields from the preview
        const selectedFieldKeys = [];
        $('#edit-selected-fields-preview .wee-combine-field-selector:checked').each(function() {
            selectedFieldKeys.push($(this).val());
        });
        
        // Validation
        if (!fieldName) {
            alert('Please enter a field name');
            return;
        }
        
        if (selectedFieldKeys.length < 2) {
            alert('Please check at least 2 fields to combine');
            return;
        }
        
        // Get full field info for selected fields
        const selectedFields = [];
        selectedFieldKeys.forEach(function(fieldKey) {
            const $checkbox = $('#wee-edit-columns-container input[value="' + fieldKey + '"]');
            const $columnItem = $checkbox.closest('.wee-column-item');
            const fieldName = $columnItem.find('.wee-checkbox span').text();
            
            selectedFields.push({
                key: fieldKey,
                name: fieldName
            });
        });
        
        // Determine separator
        const finalSeparator = separator === 'custom' ? customSeparator : separator;
        
        // Create combined field object
        const combinedField = {
            id: 'combined_' + Date.now(),
            name: fieldName,
            separator: finalSeparator,
            fields: selectedFields
        };
        
        // Add to list
        addEditModalCombinedFieldToList(combinedField);
        
        // Clear form
        $('#edit-combined-field-name').val('');
        $('#edit-combined-field-separator').val(' ');
        $('#edit-custom-separator').val('');
        $('#edit-custom-separator-group').hide();
        
        // Uncheck all combine field selectors
        $('#edit-selected-fields-preview .wee-combine-field-selector').prop('checked', false);
        
        // Update the column ordering list to include all fields including the new combined field
        updateEditModalColumnOrderingList();
    }
    
    /**
     * Add combined field to edit modal list
     */
    function addEditModalCombinedFieldToList(combinedField) {
        const $list = $('#edit-combined-fields-list');
        
        // Build field keys list
        const fieldKeys = combinedField.fields.map(f => f.key);
        const fieldsList = combinedField.fields.map(f => f.name).join(', ');
        
        // Handle separator - ensure we have a valid value
        const separator = combinedField.separator !== undefined && combinedField.separator !== null ? combinedField.separator : ' ';
        
        // Display separator
        let separatorDisplay = 'Space'; // Default
        if (separator === ' ') {
            separatorDisplay = 'Space';
        } else if (separator === ', ') {
            separatorDisplay = 'Comma';
        } else if (separator === ' - ') {
            separatorDisplay = 'Dash';
        } else if (separator === ' | ') {
            separatorDisplay = 'Pipe';
        } else if (separator === '\n' || separator === '\\n') {
            separatorDisplay = 'New Line';
        } else if (separator) {
            separatorDisplay = separator; // Custom separator
        }
        
        console.log('WEE DEBUG: Adding combined field to list:', {
            name: combinedField.name,
            separator: separator,
            separatorDisplay: separatorDisplay
        });
        
        const $fieldItem = $(`
            <div class="wee-combined-field-item" data-field-id="${combinedField.id}" data-field-keys='${JSON.stringify(fieldKeys)}' data-separator="${separator}">
                <div class="wee-combined-field-header">
                    <span class="wee-field-icon"><span class="dashicons dashicons-admin-tools"></span></span>
                    <span class="wee-field-name"><strong>${combinedField.name}</strong></span>
                    <span class="wee-field-separator">[${separatorDisplay}]</span>
                    <button type="button" class="wee-remove-combined-field" data-field-id="${combinedField.id}" title="Remove combined field">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="wee-combined-field-content">
                    <div class="wee-field-keys">${fieldsList}</div>
                </div>
            </div>
        `);
        
        $list.append($fieldItem);
        
        // Add remove handler
        $fieldItem.find('.wee-remove-combined-field').on('click', function() {
            const fieldId = $(this).data('field-id');
            removeEditModalCombinedField(fieldId);
        });
    }
    
    /**
     * Remove combined field from edit modal
     */
    function removeEditModalCombinedField(fieldId) {
        // Remove from combined fields list
        $(`#edit-combined-fields-list .wee-combined-field-item[data-field-id="${fieldId}"]`).remove();
        
        // Refresh the ordering list to remove the combined field
        updateEditModalColumnOrderingList();
    }
    
    /**
     * Clear all combined fields in edit modal
     */
    function clearEditModalCombinedFields() {
        $('#edit-combined-fields-list').empty();
        
        // Clear form
        $('#edit-combined-field-name').val('');
        $('#edit-combined-field-separator').val(' ');
        $('#edit-custom-separator').val('');
        $('#edit-custom-separator-group').hide();
        $('#edit-selected-fields-preview .wee-combine-field-selector').prop('checked', false);
        
        // Refresh the ordering list to remove all combined fields
        updateEditModalColumnOrderingList();
    }
    
    /**
     * Display selected products in edit modal
     */
    function displayEditModalSelectedProducts(productSearchValue) {
        if (!productSearchValue) {
            $('#edit-template-selected-products').empty();
            return;
        }
        
        try {
            const selectedProducts = JSON.parse(productSearchValue);
            if (!Array.isArray(selectedProducts) || selectedProducts.length === 0) {
                return;
            }
            
            let html = `
                <div style="background: #f0f8ff; border: 1px solid #0066cc; border-radius: 6px; padding: 10px;">
                    <strong style="color: #0066cc;">Selected Products:</strong>
                    <div class="wee-selected-products-list" style="margin-top: 8px;">
            `;
            
            selectedProducts.forEach((product, index) => {
                html += `
                    <div class="wee-selected-product-tag" style="display: inline-block; background: white; border: 1px solid #0066cc; border-radius: 4px; padding: 4px 8px; margin: 2px; font-size: 13px;">
                        <span style="color: #0066cc; font-weight: 500;">${product.name}</span>
                        ${product.sku ? `<span style="color: #666; font-size: 12px;"> (${product.sku})</span>` : ''}
                    </div>
                `;
            });
            
            html += '</div></div>';
            $('#edit-template-selected-products').html(html);
        } catch (e) {
            console.error('WEE: Error displaying selected products:', e);
        }
    }
    
    /**
     * Close edit modal
     */
    function closeEditModal() {
        $('#wee-edit-modal').fadeOut(200);
        $('body').css('overflow', '');
        
        // Clear the modal form
        clearEditModalForm();
    }
    
    /**
     * Clear edit modal form
     */
    function clearEditModalForm() {
        $('#edit-template-id').val('');
        $('#edit-template-name').val('');
        $('#edit-template-description').val('');
        $('#wee-edit-columns-container input[type="checkbox"]').prop('checked', false);
        
        // Clear and disable all custom column name inputs
        $('#wee-edit-columns-container .wee-custom-column-name').val('').prop('disabled', true);
        
        $('#edit-template-order-status').val([]);
        $('#edit-template-payment-method').val('');
        $('#edit-template-product-categories').val([]);
        $('#edit-template-product-search').val('');
        $('#edit-template-product-search-value').val('');
        $('#edit-template-selected-products').empty();
        
        // Clear combined fields
        $('#edit-combined-fields-list').empty();
        $('#edit-combined-field-name').val('');
        $('#edit-combined-field-separator').val(' ');
        $('#edit-custom-separator').val('');
        $('#edit-custom-separator-group').hide();
        $('#edit-selected-fields-preview').html('<div class="wee-no-fields-message"><span class="dashicons dashicons-warning"></span><p>Select fields from the column selection above first. They will appear here for you to choose which ones to combine.</p></div>');
        
        // Clear ordering list
        $('#wee-edit-selected-columns-list').empty();
        $('#wee-edit-column-ordering-section').hide();
        
        updateEditModalColumnCount();
    }
    
    /**
     * Save edited template from modal
     */
    function saveEditedTemplate() {
        const templateId = $('#edit-template-id').val();
        const templateName = $('#edit-template-name').val().trim();
        const templateDescription = $('#edit-template-description').val().trim();
        
        if (!templateName) {
            showNotice('Please enter a template name', 'error');
            return;
        }
        
        if (!templateId) {
            showNotice('No template ID found', 'error');
            return;
        }
        
        console.log('WEE DEBUG: ========== SAVE EDITED TEMPLATE FROM MODAL ==========');
        console.log('WEE DEBUG: Template ID:', templateId);
        console.log('WEE DEBUG: Template Name:', templateName);
        
        // Collect data from the MODAL form
        const formData = new FormData();
        
        // Get selected columns from MODAL form
        const selectedColumns = [];
        console.log('WEE DEBUG: Collecting checkboxes from edit modal...');
        const $modalCheckboxes = $('#wee-edit-columns-container input[type="checkbox"]');
        const $modalChecked = $('#wee-edit-columns-container input[type="checkbox"]:checked');
        console.log('WEE DEBUG: Total modal checkboxes:', $modalCheckboxes.length);
        console.log('WEE DEBUG: Checked modal checkboxes:', $modalChecked.length);
        
        $modalChecked.each(function() {
            const columnKey = $(this).val();
            selectedColumns.push(columnKey);
            console.log('WEE DEBUG: Column:', columnKey);
        });
        console.log('WEE DEBUG: Selected columns to save:', selectedColumns);
        
        // Get column order from the ordering list (includes both regular columns and combined fields)
        const columnOrder = [];
        $('#wee-edit-selected-columns-list .wee-selected-column-item').each(function() {
            const columnKey = $(this).data('column');
            if (columnKey) {
                columnOrder.push(columnKey);
            }
        });
        console.log('WEE DEBUG: Column order from edit modal:', columnOrder);
        
        // Get template filters from MODAL form
        const templateFilters = {};
        
        const orderStatus = $('#edit-template-order-status').val();
        if (orderStatus && orderStatus.length > 0) {
            templateFilters.order_status = orderStatus;
        }
        
        const paymentMethod = $('#edit-template-payment-method').val();
        if (paymentMethod) {
            templateFilters.payment_method = paymentMethod;
        }
        
        const productCategories = $('#edit-template-product-categories').val();
        if (productCategories && productCategories.length > 0) {
            templateFilters.product_categories = productCategories;
        }
        
        const productSearch = $('#edit-template-product-search-value').val();
        if (productSearch) {
            templateFilters.product_search = productSearch;
        }

        // TGF submission type filter
        const tgfSubmissionKey = $('#edit-template-tgf-submission-key').val();
        if (tgfSubmissionKey) {
            templateFilters.tgf_submission_key = tgfSubmissionKey;
        }

        // TGF grading options contains filter
        const tgfGradingContainsEdit = $('#edit-template-tgf-grading-contains').val();
        if (tgfGradingContainsEdit) {
            templateFilters.tgf_grading_contains = tgfGradingContainsEdit;
        }

        // TGF service level contains filter
        const tgfServiceLevelContainsEdit = $('#edit-template-tgf-service-level-contains').val();
        if (tgfServiceLevelContainsEdit) {
            templateFilters.tgf_service_level_contains = tgfServiceLevelContainsEdit;
        }

        console.log('WEE DEBUG: Template filters collected from modal:', templateFilters);
        
        // Collect custom column names from the edit modal
        const columnNames = {};
        $('#wee-edit-columns-container .wee-custom-column-name:not(:disabled)').each(function() {
            const $input = $(this);
            const name = $input.attr('name');
            const value = $input.val();
            
            if (name && value) {
                // Extract column key from name attribute (edit_column_names[column_key])
                const match = name.match(/edit_column_names\[([^\]]+)\]/);
                if (match) {
                    const columnKey = match[1];
                    columnNames[columnKey] = value;
                    console.log('WEE DEBUG: Custom column name:', columnKey, '=', value);
                }
            }
        });
        console.log('WEE DEBUG: All custom column names:', columnNames);
        
        // Collect column visibility from the ordering list
        const columnVisibility = {};
        $('#wee-edit-selected-columns-list .wee-selected-column-item[data-type="regular"]').each(function() {
            const columnKey = $(this).data('column');
            const isVisible = $(this).find('.wee-show-column').is(':checked');
            columnVisibility[columnKey] = isVisible;
        });
        console.log('WEE DEBUG: Column visibility:', columnVisibility);
        
        // Collect combined fields
        const combinedFields = [];
        const $combinedFieldItems = $('#edit-combined-fields-list .wee-combined-field-item');
        console.log('WEE DEBUG: Found combined field items in edit modal:', $combinedFieldItems.length);
        
        $combinedFieldItems.each(function() {
            const $item = $(this);
            const fieldId = $item.data('field-id');
            let fieldKeys = $item.data('field-keys');
            let separator = $item.data('separator');
            const fieldName = $item.find('.wee-field-name strong').text();
            const isVisible = $(`#wee-edit-selected-columns-list .wee-selected-column-item[data-column="${fieldId}"] .wee-show-column`).is(':checked');
            
            console.log('WEE DEBUG: Processing combined field item:', {
                fieldId: fieldId,
                fieldKeys: fieldKeys,
                fieldKeysType: typeof fieldKeys,
                fieldName: fieldName,
                separator: separator
            });
            
            // Parse field keys if it's a string
            if (typeof fieldKeys === 'string') {
                try {
                    fieldKeys = JSON.parse(fieldKeys);
                    console.log('WEE DEBUG: Parsed fieldKeys from string:', fieldKeys);
                } catch (e) {
                    console.error('WEE DEBUG: Error parsing field keys:', e);
                    fieldKeys = [];
                }
            }
            
            // Handle newline separator
            if (separator === '\\n') {
                separator = '\n';
            }
            
            // Convert field keys to full field objects (matching server expected format)
            const fields = [];
            if (Array.isArray(fieldKeys)) {
                console.log('WEE DEBUG: Converting', fieldKeys.length, 'field keys to field objects');
                fieldKeys.forEach(function(fieldKey) {
                    const $checkbox = $(`#wee-edit-columns-container input[value="${fieldKey}"]`);
                    const $columnItem = $checkbox.closest('.wee-column-item');
                    const columnLabel = $columnItem.find('.wee-checkbox span').text();
                    
                    fields.push({
                        key: fieldKey,
                        name: columnLabel,
                        value: fieldKey
                    });
                });
            } else {
                console.error('WEE DEBUG: fieldKeys is not an array:', fieldKeys);
            }
            
            if (fields.length > 0) {
                combinedFields.push({
                    id: fieldId,
                    name: fieldName,
                    separator: separator,
                    fields: fields,
                    visible: isVisible
                });
                console.log('WEE DEBUG: Added combined field to save data:', fieldName);
            } else {
                console.warn('WEE DEBUG: Skipping combined field with no fields:', fieldName);
            }
        });
        console.log('WEE DEBUG: Total combined fields to save:', combinedFields.length);
        console.log('WEE DEBUG: Combined fields data:', combinedFields);
        
        // Prepare form data
        formData.append('action', 'wee_edit_template');
        formData.append('nonce', $('#edit_nonce').val());
        formData.append('template_id', templateId);
        formData.append('template_name', templateName);
        formData.append('template_description', templateDescription);
        formData.append('columns', JSON.stringify(selectedColumns));
        formData.append('column_order', JSON.stringify(columnOrder));
        formData.append('column_names', JSON.stringify(columnNames));
        formData.append('column_visibility', JSON.stringify(columnVisibility));
        formData.append('template_filters', JSON.stringify(templateFilters));
        formData.append('combined_fields', JSON.stringify(combinedFields));
        
        console.log('WEE DEBUG: Sending update for template:', templateId);
        console.log('WEE DEBUG: Sending column order:', JSON.stringify(columnOrder));
        console.log('WEE DEBUG: Sending combined_fields JSON:', JSON.stringify(combinedFields));
        
        // Submit via AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('WEE DEBUG: Save response:', response);
                if (response.success) {
                    showNotice('Template updated successfully!', 'success');
                    
                    // Close modal and refresh page to show updated template
                    closeEditModal();
                    setTimeout(function() {
                        location.reload();
                    }, 500);
                } else {
                    showNotice(response.data || 'Failed to update template', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('WEE: AJAX error:', error);
                showNotice('An error occurred while updating the template', 'error');
            }
        });
    }
    
    /**
     * Populate template form with data
     */
    function populateTemplateForm(template) {
        console.log('WEE DEBUG: Populating template form:', template);
        
        // Preserve editing state BEFORE clearing
        const editingTemplateId = $('#wee-create-template-form').data('editing-template-id');
        console.log('WEE DEBUG: Preserved editing template ID:', editingTemplateId);
        
        // Clear form (this will remove the editing-template-id)
        clearTemplateForm();
        
        // Restore editing state AFTER clearing
        if (editingTemplateId) {
            $('#wee-create-template-form').data('editing-template-id', editingTemplateId);
            console.log('WEE DEBUG: Restored editing template ID:', editingTemplateId);
        }
        
        // Also store the template ID from the template object itself as a backup
        if (template.id) {
            $('#wee-create-template-form').data('editing-template-id', template.id);
            console.log('WEE DEBUG: Set editing template ID from template object:', template.id);
        }
        
        // Set template name
        $('#template-name').val(template.name);
        $('#template-description').val(template.description || '');
        
        // Parse columns if it's a string
        let columns = template.columns;
        if (typeof columns === 'string') {
            try {
                columns = JSON.parse(columns);
            } catch (e) {
                console.error('WEE: Error parsing columns:', e);
                columns = [];
            }
        }
        
        // Select columns
        if (columns && Array.isArray(columns)) {
            console.log('WEE DEBUG: Selecting columns:', columns);
            console.log('WEE DEBUG: Number of columns to select:', columns.length);
            
            let successCount = 0;
            let failCount = 0;
            
            columns.forEach(function(column) {
                const $checkbox = $(`.wee-column-grid input[value="${column}"]`);
                if ($checkbox.length > 0) {
                    const wasChecked = $checkbox.is(':checked');
                    $checkbox.prop('checked', true);
                    const isNowChecked = $checkbox.is(':checked');
                    
                    if (isNowChecked) {
                        successCount++;
                    } else {
                        console.error('WEE ERROR: Failed to check checkbox for:', column);
                        failCount++;
                    }
                    
                    // Enable custom name input for selected columns
                    const $columnItem = $checkbox.closest('.wee-column-item');
                    const $customNameInput = $columnItem.find('.wee-custom-column-name');
                    $customNameInput.prop('disabled', false);
                    
                    console.log('WEE DEBUG: Checkbox', column, '- Was:', wasChecked, 'Now:', isNowChecked);
                } else {
                    console.warn('WEE DEBUG: Checkbox not found for column:', column);
                    failCount++;
                }
            });
            
            // Log how many checkboxes are now checked
            const checkedCount = $('.wee-column-grid input[type="checkbox"]:checked').length;
            console.log('WEE DEBUG: After selecting, total checked:', checkedCount);
            console.log('WEE DEBUG: Success:', successCount, 'Failed:', failCount);
            
            // Verify checkboxes are actually checked after a delay
            setTimeout(function() {
                const checkedCountAfterDelay = $('.wee-column-grid input[type="checkbox"]:checked').length;
                console.log('WEE DEBUG: After 100ms delay, total checked:', checkedCountAfterDelay);
                if (checkedCountAfterDelay === 0 && columns.length > 0) {
                    console.error('WEE ERROR: All checkboxes became unchecked after delay! Something is clearing them!');
                }
            }, 100);
            
            // Update the selected fields preview after columns are selected
            setTimeout(function() {
                updateSelectedFieldsPreview();
            }, 50);
        }
        
        // Parse column names if it's a string
        let columnNames = template.column_names;
        if (typeof columnNames === 'string') {
            try {
                columnNames = JSON.parse(columnNames);
            } catch (e) {
                console.error('WEE: Error parsing column names:', e);
                columnNames = {};
            }
        }
        
        // Set column names
        if (columnNames && typeof columnNames === 'object') {
            Object.keys(columnNames).forEach(function(columnKey) {
                const $input = $(`input[name="column_names[${columnKey}]"]`);
                if ($input.length > 0) {
                    $input.val(columnNames[columnKey]);
                }
            });
        }
        
        // Parse filters if it's a string
        let filters = template.filters;
        if (typeof filters === 'string') {
            try {
                filters = JSON.parse(filters);
            } catch (e) {
                console.error('WEE: Error parsing filters:', e);
                filters = {};
            }
        }
        
        // Set filters
        if (filters && typeof filters === 'object') {
            console.log('WEE DEBUG: Populating filters:', filters);
            
            // Handle product search specially (it has a different structure)
            if (filters.product_search) {
                console.log('WEE DEBUG: Setting product_search:', filters.product_search);
                const $hiddenInput = $('#template-product-search-value');
                if ($hiddenInput.length > 0) {
                    $hiddenInput.val(filters.product_search);
                    console.log('WEE DEBUG: Hidden input value set to:', $hiddenInput.val());
                    
                    // Trigger reinitialization of product display
                    setTimeout(function() {
                        console.log('WEE DEBUG: Triggering products-loaded event');
                        const event = new CustomEvent('products-loaded', { 
                            detail: { products: filters.product_search } 
                        });
                        document.dispatchEvent(event);
                        console.log('WEE DEBUG: products-loaded event dispatched');
                    }, 300);
                } else {
                    console.error('WEE DEBUG: Hidden input #template-product-search-value not found');
                }
            }
            
            // Handle order status
            if (filters.order_status) {
                console.log('WEE DEBUG: Setting order_status:', filters.order_status);
                const $orderStatus = $('#template-order-status');
                if ($orderStatus.length > 0) {
                    const values = Array.isArray(filters.order_status) ? filters.order_status : [filters.order_status];
                    $orderStatus.val(values);
                }
            }
            
            // Handle payment method
            if (filters.payment_method) {
                console.log('WEE DEBUG: Setting payment_method:', filters.payment_method);
                $('#template-payment-method').val(filters.payment_method);
            }
            
            // Handle order totals
            if (filters.order_total_min) {
                console.log('WEE DEBUG: Setting order_total_min:', filters.order_total_min);
                $('#template-order-total-min').val(filters.order_total_min);
            }
            if (filters.order_total_max) {
                console.log('WEE DEBUG: Setting order_total_max:', filters.order_total_max);
                $('#template-order-total-max').val(filters.order_total_max);
            }
            
            // Handle product categories
            if (filters.product_categories) {
                console.log('WEE DEBUG: Setting product_categories:', filters.product_categories);
                const $categories = $('#template-product-categories');
                if ($categories.length > 0) {
                    const values = Array.isArray(filters.product_categories) ? filters.product_categories : [filters.product_categories];
                    $categories.val(values.map(String)); // Convert to strings for select matching
                }
            }
            
            // Handle custom meta fields
            if (filters.custom_meta_key) {
                console.log('WEE DEBUG: Setting custom_meta_key:', filters.custom_meta_key);
                $('#template-custom-meta-key').val(filters.custom_meta_key);
            }
            if (filters.custom_meta_operator) {
                console.log('WEE DEBUG: Setting custom_meta_operator:', filters.custom_meta_operator);
                $('#template-custom-meta-operator').val(filters.custom_meta_operator);
            }
            if (filters.custom_meta_value) {
                console.log('WEE DEBUG: Setting custom_meta_value:', filters.custom_meta_value);
                $('#template-custom-meta-value').val(filters.custom_meta_value);
            }
        }
        
        // Parse combined fields if it's a string
        let combinedFields = template.combined_fields;
        console.log('WEE DEBUG: Raw combined_fields from template:', combinedFields);
        if (typeof combinedFields === 'string') {
            try {
                combinedFields = JSON.parse(combinedFields);
                console.log('WEE DEBUG: Parsed combined_fields:', combinedFields);
            } catch (e) {
                console.error('WEE: Error parsing combined fields:', e);
                combinedFields = [];
            }
        }
        
        // Load combined fields if they exist
        if (combinedFields && Array.isArray(combinedFields) && combinedFields.length > 0) {
            console.log('WEE DEBUG: Loading combined fields:', combinedFields);
            loadExistingCombinedFields(combinedFields);
        } else {
            console.log('WEE DEBUG: No combined fields to load - combinedFields:', combinedFields);
        }
        
        // Load column visibility if it exists
        let columnVisibility = template.column_visibility;
        // console.log('WEE: Raw column visibility from template:', columnVisibility);
        if (typeof columnVisibility === 'string') {
            try {
                columnVisibility = JSON.parse(columnVisibility);
                // console.log('WEE: Parsed column visibility:', columnVisibility);
            } catch (e) {
                console.error('WEE: Error parsing column visibility:', e);
                columnVisibility = {};
            }
        }
        
        if (!columnVisibility || Object.keys(columnVisibility).length === 0) {
            // console.log('WEE: No column visibility data found in template');
        }
        
        // Update column ordering and counts after combined fields are loaded
        setTimeout(function() {
            // console.log('WEE: Updating selected columns list after combined fields loaded');
            // console.log('WEE: Combined field items found:', $('.wee-combined-field-item').length);
            updateSelectedColumnsList();
            
            // Apply column visibility after the list is updated
            if (columnVisibility && typeof columnVisibility === 'object') {
                applyColumnVisibility(columnVisibility);
            }
        }, 200); // Increased timeout to ensure combined fields are loaded
        
        // console.log('WEE: Template form populated successfully');
    }
    
    /**
     * Clear template form
     */
    function clearTemplateForm() {
        $('#template-name').val('');
        $('#template-description').val('');
        $('.wee-column-grid input[type="checkbox"]').prop('checked', false);
        $('.wee-custom-column-name').prop('disabled', true).val('');
        
        // Clear filter inputs
        $('.wee-filter-input').each(function() {
            const $input = $(this);
            if ($input.is('select')) {
                if ($input.prop('multiple')) {
                    $input.val([]);
                } else {
                    $input.val($input.find('option:first').val());
                }
            } else if ($input.attr('type') !== 'hidden') {
                $input.val('');
            }
        });
        
        // Clear product search specifically
        $('#template-product-search').val('');
        $('#template-product-search-value').val('');
        $('#template-selected-products').empty();
        
        $('#combined-fields-list').empty();
        
        // Clear editing state
        $('#wee-create-template-form').removeData('editing-template-id');
        
        updateSelectedColumnsList();
    }
    
    /**
     * Add combined field to column ordering list
     */
    function addCombinedFieldToOrdering(combinedField) {
        // This function is called from addCombinedFieldToList
        // Update the selected columns list to include the new combined field
        // console.log('WEE: Combined field added to ordering:', combinedField.name);
        updateSelectedColumnsList();
    }
    
    /**
     * Remove combined field from column ordering list
     */
    function removeCombinedFieldFromOrdering(fieldId) {
        $(`#wee-selected-columns-list .wee-selected-column-item[data-column="${fieldId}"]`).remove();
        // console.log('WEE: Combined field removed from ordering:', fieldId);
    }
    
    /**
     * Hide individual fields that are part of a combined field
     */
    function hideIndividualFields(fields) {
        fields.forEach(function(field) {
            const fieldKey = field.key || field.value;
            $(`.wee-column-item input[value="${fieldKey}"]`).closest('.wee-column-item').addClass('wee-hidden-by-combined');
        });
    }
    
    /**
     * Show individual fields when a combined field is removed
     */
    function showIndividualFields(fieldId) {
        // Find the combined field to get its field keys
        const $combinedField = $(`.wee-combined-field-item[data-field-id="${fieldId}"]`);
        if ($combinedField.length > 0) {
            const fieldKeys = $combinedField.data('field-keys').split(',');
            fieldKeys.forEach(function(fieldKey) {
                $(`.wee-column-item input[value="${fieldKey}"]`).closest('.wee-column-item').removeClass('wee-hidden-by-combined');
            });
        }
    }
    
    /**
     * Apply column visibility settings
     */
    function applyColumnVisibility(columnVisibility) {
        // console.log('WEE: Applying column visibility:', columnVisibility);
        
        // Apply visibility to regular columns
        Object.keys(columnVisibility).forEach(function(columnKey) {
            const isVisible = columnVisibility[columnKey];
            const $orderingItem = $(`#wee-selected-columns-list .wee-selected-column-item[data-column="${columnKey}"][data-type="regular"]`);
            
            if ($orderingItem.length > 0) {
                const $toggle = $orderingItem.find('.wee-show-column');
                const $text = $orderingItem.find('.wee-toggle-text');
                
                $toggle.prop('checked', isVisible);
                $text.text(isVisible ? 'Show' : 'Hide');
                $orderingItem.toggleClass('wee-hidden-column', !isVisible);
            }
        });
        
        // Apply visibility to combined fields
        $('.wee-combined-field-item').each(function() {
            const $item = $(this);
            const fieldId = $item.data('field-id');
            const $orderingItem = $(`#wee-selected-columns-list .wee-selected-column-item[data-column="${fieldId}"][data-type="combined"]`);
            
            if ($orderingItem.length > 0) {
                // Check if this combined field has visibility data
                const combinedFieldData = $item.find('.wee-field-keys').text();
                try {
                    const fields = JSON.parse(combinedFieldData);
                    if (fields && fields.length > 0) {
                        // For now, assume combined fields are visible unless explicitly hidden
                        // This could be enhanced to store individual combined field visibility
                        const isVisible = true; // Default to visible
                        const $toggle = $orderingItem.find('.wee-show-column');
                        const $text = $orderingItem.find('.wee-toggle-text');
                        
                        $toggle.prop('checked', isVisible);
                        $text.text(isVisible ? 'Show' : 'Hide');
                        $orderingItem.toggleClass('wee-hidden-column', !isVisible);
                    }
                } catch (e) {
                    console.error('WEE: Error parsing combined field data for visibility:', e);
                }
            }
        });
    }
    
    /**
     * Load existing combined fields
     */
    function loadExistingCombinedFields(combinedFields) {
        // console.log('WEE: Loading existing combined fields:', combinedFields);
        
        if (!combinedFields || combinedFields.length === 0) {
            // console.log('WEE: No combined fields to load');
            return;
        }
        
        combinedFields.forEach(function(field) {
            // Normalize field structure - handle different formats
            const normalizedField = {
                id: field.id || 'combined_' + Date.now(),
                name: field.name,
                separator: field.separator || ' ',
                fields: []
            };
            
            // Handle fields array - it might be in different formats
            if (field.fields && Array.isArray(field.fields)) {
                normalizedField.fields = field.fields.map(function(f) {
                    // If it's just a string (field name), try to find the key
                    if (typeof f === 'string') {
                        // Try to find the checkbox by label text
                        const $matchingCheckbox = $('.wee-column-grid .wee-checkbox span').filter(function() {
                            return $(this).text().trim() === f.trim();
                        }).closest('.wee-column-item').find('input[type="checkbox"]');
                        
                        return {
                            key: $matchingCheckbox.val() || f,
                            name: f,
                            value: $matchingCheckbox.val() || f
                        };
                    }
                    // If it has a key or value property, use it
                    const fieldKey = f.key || f.value || f.name;
                    let fieldName = f.name;
                    
                    // If no name, look it up from the checkbox label
                    if (!fieldName) {
                        const $checkbox = $(`.wee-column-grid input[value="${fieldKey}"]`);
                        if ($checkbox.length > 0) {
                            fieldName = $checkbox.closest('.wee-column-item').find('.wee-checkbox span').text().trim();
                        }
                    }
                    
                    return {
                        key: fieldKey,
                        name: fieldName || fieldKey,
                        value: fieldKey
                    };
                });
            }
            
            // console.log('WEE: Adding combined field:', normalizedField);
            addCombinedFieldToList(normalizedField);
        });
    }
    
    /**
     * Show notice to user
     */
    function showNotice(message, type) {
        const $notice = $(`
            <div class="notice notice-${type} is-dismissible">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                </button>
            </div>
        `);
        
        $('.wrap').prepend($notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
        
        // Handle manual dismiss
        $notice.find('.notice-dismiss').on('click', function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        });
    }
    
    /**
     * Initialize template filters functionality
     */
    function initTemplateFilters() {
        // console.log('WEE: Initializing template filters...');
        // Template filters functionality can be added here if needed
    }
    
    /**
     * Initialize column ordering functionality
     */
    function initColumnOrdering() {
        // console.log('WEE: Initializing column ordering...');
        
        // Only initialize on templates page
        if ($('.wee-column-grid').length === 0) {
            // console.log('WEE: Column grid not found - skipping column ordering initialization (not on templates page)');
            return;
        }
        
        // Update selected columns list when checkboxes change
        $(document).on('change', '.wee-column-grid input[type="checkbox"]', function() {
            updateSelectedColumnsList();
        });
        
        // Initial update
        updateSelectedColumnsList();
    }
    
    /**
     * Initialize custom column names functionality
     */
    function initCustomColumnNames() {
        // console.log('WEE: Initializing custom column names...');
        
        // Only initialize on templates page
        if ($('.wee-custom-column-name').length === 0) {
            // console.log('WEE: Custom column name inputs not found - skipping custom column names initialization (not on templates page)');
            return;
        }
        
        // Enable/disable custom name input based on checkbox state
        $(document).on('change', '.wee-column-grid input[type="checkbox"]', function() {
            const $checkbox = $(this);
            const $columnItem = $checkbox.closest('.wee-column-item');
            const $customNameInput = $columnItem.find('.wee-custom-column-name');
            
            if ($checkbox.is(':checked')) {
                $customNameInput.prop('disabled', false);
                        } else {
                $customNameInput.prop('disabled', true);
                        }
                    });
                }
    
    /**
     * Update selected columns list for ordering
     */
    function updateSelectedColumnsList() {
        // console.log('WEE: Updating selected columns list...');
        const count = $('.wee-column-item input[type="checkbox"]:checked').length;
        // console.log('WEE: Found', count, 'selected columns');
        
        const $selectedList = $('#wee-selected-columns-list');
        // console.log('WEE: Selected list element found:', $selectedList.length > 0);
        
        if ($selectedList.length === 0) {
            // console.log('WEE: Selected columns list element not found');
            return;
        }
        
        $selectedList.empty();
        
        // Add regular selected columns
        $('.wee-column-item input[type="checkbox"]:checked').each(function() {
            const $checkbox = $(this);
            const $columnItem = $checkbox.closest('.wee-column-item');
            const columnKey = $checkbox.val();
            const columnLabel = $columnItem.find('.wee-checkbox span').text();
            const $customNameInput = $columnItem.find('.wee-custom-column-name');
            const customName = $customNameInput.val() || '';
            
            // console.log('WEE: Processing column:', columnKey, 'Label:', columnLabel);
            
            const $listItem = $(`
                <li class="wee-selected-column-item" data-column="${columnKey}" data-type="regular">
                    <div class="wee-column-drag-handle">
                        <span class="dashicons dashicons-menu"></span>
                    </div>
                    <div class="wee-column-info">
                        <span class="wee-column-label">
                            <strong>${columnLabel}</strong>
                            ${customName ? `<br><small class="wee-custom-name">Custom name: ${customName}</small>` : ''}
                        </span>
                        <div class="wee-column-actions">
                            <label class="wee-show-hide-toggle">
                                <input type="checkbox" class="wee-show-column" checked>
                                <span class="wee-toggle-text">Show</span>
                            </label>
                        <button type="button" class="wee-remove-column-btn" data-column="${columnKey}" title="Remove column">
                            <span class="wee-remove-icon">×</span>
                        </button>
                        </div>
                    </div>
                </li>
            `);
            
            $selectedList.append($listItem);
        });
        
        // Add combined fields
        // console.log('WEE: Processing combined fields for ordering list...');
        // console.log('WEE: Found combined field items:', $('#combined-fields-list .wee-combined-field-item').length);
        $('#combined-fields-list .wee-combined-field-item').each(function() {
            const $item = $(this);
            const fieldId = $item.data('field-id');
            const fieldName = $item.data('field-name');
            const fieldKeys = $item.data('field-keys');
            let separator = $item.data('separator');
            
            // Decode separator if it was encoded
            if (separator === '___SPACE___') {
                separator = ' ';
            }
            
            // console.log('WEE: Processing combined field:', fieldId, fieldName, fieldKeys);
            
            // Encode separator for data attribute (for the ordering list)
            const encodedSeparator = separator === ' ' ? '___SPACE___' : separator;
            
            const $listItem = $(`
                <li class="wee-selected-column-item wee-combined-field-item" data-column="${fieldId}" data-type="combined" data-field-keys="${fieldKeys}" data-separator="${encodedSeparator}">
                    <div class="wee-column-drag-handle">
                        <span class="dashicons dashicons-menu"></span>
                    </div>
                    <div class="wee-column-info">
                        <span class="wee-column-label">
                            <strong>${fieldName}</strong>
                            <br><small class="wee-combined-info">Combined Field (${fieldKeys.split(',').length} fields)</small>
                        </span>
                        <div class="wee-column-actions">
                            <label class="wee-show-hide-toggle">
                                <input type="checkbox" class="wee-show-column" checked>
                                <span class="wee-toggle-text">Show</span>
                            </label>
                            <button type="button" class="wee-remove-column-btn" data-column="${fieldId}" title="Remove combined field">
                                <span class="wee-remove-icon">×</span>
                            </button>
                        </div>
                    </div>
                </li>
            `);
            
            $selectedList.append($listItem);
        });
        
        // Handle remove column buttons
        $selectedList.find('.wee-remove-column-btn').off('click').on('click', function() {
            const columnKey = $(this).data('column');
            const $listItem = $(this).closest('.wee-selected-column-item');
            const type = $listItem.data('type');
            
            if (type === 'combined') {
                // Remove combined field
                removeCombinedFieldFromOrdering(columnKey);
                showIndividualFields(columnKey);
                $(`.wee-combined-field-item[data-field-id="${columnKey}"]`).remove();
            } else {
                // Remove regular column
            $(`.wee-column-item input[value="${columnKey}"]`).prop('checked', false).trigger('change');
            }
        });
        
        // Handle show/hide toggles
        $selectedList.find('.wee-show-column').off('change').on('change', function() {
            const $toggle = $(this);
            const $text = $toggle.siblings('.wee-toggle-text');
            const isChecked = $toggle.is(':checked');
            
            $text.text(isChecked ? 'Show' : 'Hide');
            $toggle.closest('.wee-selected-column-item').toggleClass('wee-hidden-column', !isChecked);
        });
        
        // Make the list sortable
        if (typeof $selectedList.sortable === 'function') {
            $selectedList.sortable({
                handle: '.wee-column-drag-handle',
                placeholder: 'wee-column-placeholder',
                update: function() {
                    // console.log('WEE: Column order updated');
                }
            });
        }
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

        function searchProducts(query) {
        $.ajax({
                url: ajaxurl,
            type: 'POST',
                data: {
                    action: 'wee_search_products',
                    query: query,
                    nonce: $('#wee_nonce').val()
                },
            success: function(response) {
                if (response.success) {
                        products = response.data;
                        showDropdown(products);
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
                $dropdown.html('<div class="wee-no-results">No products found</div>');
            } else {
                products.forEach((product, index) => {
                    const $item = $(`
                        <div class="wee-product-item" data-index="${index}">
                        <div class="wee-product-name">${product.name}</div>
                            <div class="wee-product-sku">SKU: ${product.sku || 'N/A'}</div>
                    </div>
                    `);
                    $dropdown.append($item);
                });
            }
            
            $dropdown.removeClass('wee-hidden');
            selectedIndex = -1;
        }

        function hideDropdown() {
            $dropdown.addClass('wee-hidden');
            selectedIndex = -1;
        }

        $productSearch.on('keydown', function(e) {
            if (!$dropdown.hasClass('wee-hidden')) {
                switch(e.key) {
                    case 'ArrowDown':
                    e.preventDefault();
                        selectedIndex = Math.min(selectedIndex + 1, products.length - 1);
                        updateSelection();
                    break;
                    case 'ArrowUp':
                    e.preventDefault();
                    selectedIndex = Math.max(selectedIndex - 1, -1);
                        updateSelection();
                    break;
                    case 'Enter':
                    e.preventDefault();
                    if (selectedIndex >= 0 && products[selectedIndex]) {
                            selectProduct(products[selectedIndex]);
                    }
                    break;
                    case 'Escape':
                        hideDropdown();
                    break;
            }
            }
        });

        function updateSelection() {
            $dropdown.find('.wee-product-item').removeClass('selected');
            if (selectedIndex >= 0) {
                $dropdown.find(`[data-index="${selectedIndex}"]`).addClass('selected');
            }
        }

        function selectProduct(product) {
            $productSearch.val(product.name);
            hideDropdown();
            
            // Trigger change event for any listeners
            $productSearch.trigger('change');
        }

        // Hide dropdown when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#product-search, .wee-product-dropdown').length) {
                hideDropdown();
            }
        });

        // Handle product item clicks
        $dropdown.on('click', '.wee-product-item', function() {
            const index = $(this).data('index');
            if (products[index]) {
                selectProduct(products[index]);
            }
        });
    }











    function initializeSectionStates() {
        // console.log('WEE: Initializing section states...');
        
        $('.wee-column-section').each(function() {
            const $section = $(this);
            const section = $section.data('section');
            const $content = $(`.wee-column-section-content[data-section="${section}"]`);
            const $toggleIcon = $section.find('.wee-section-toggle .wee-toggle-icon');
            
            if ($content.hasClass('wee-collapsed')) {
                $toggleIcon.text('+');
            } else {
                $toggleIcon.text('−');
            }
        });
    }




    function addNewCombinedField() {
        // console.log('WEE: Adding new combined field...');
        
        // Clone the template
        const $template = $('#wee-combined-field-template');
        
        if ($template.length === 0) {
            console.error('WEE: Combined field template not found!');
            return;
        }
        
        // Create new combined field
        const $newField = $template.clone();
        const fieldId = 'wee-combined-field-' + Date.now();
        
        $newField.attr('id', fieldId);
        $newField.removeClass('wee-hidden wee-combined-field-template');
        $newField.addClass('wee-combined-field');
        
        // Add to container
        const $container = $('#wee-combine-fields-container');
        if ($container.length === 0) {
            console.error('WEE: Combine fields container not found!');
            return;
        }
        
        $container.append($newField);
        
        // Setup event handlers
        setupCombinedFieldEventHandlers($newField);
        
        // Populate available fields
        populateAvailableFieldsForCombinedField($newField);
        
        // console.log('WEE: New combined field added with ID:', fieldId);
    }

    function setupCombinedFieldEventHandlers($field) {
        // console.log('WEE: Setting up event handlers for combined field:', $field.attr('id'));
        
        // Name input handler
        $field.find('.wee-combined-field-name').on('input', function() {
            updateCombinedFieldSaveButton($field);
        });
        
        // Field selection handler
        $field.find('.wee-field-selection-grid input[type="checkbox"]').on('change', function() {
            updateCombinedFieldSaveButton($field);
        });
        
        // Save button handler
        $field.find('.wee-save-combined-field').on('click', function() {
            saveCombinedField($field);
        });
        
        // Remove button handler
        $field.find('.wee-remove-combined-field').on('click', function() {
            removeCombinedField($field);
        });
    }

    function populateAvailableFieldsForCombinedField($field) {
        // console.log('WEE: Populating available fields for combined field...');
        
        const $fieldGrid = $field.find('.wee-field-selection-grid');
        $fieldGrid.empty();
        
        // Get currently selected fields
        const selectedFields = getSelectedFields();
        
        if (selectedFields.length === 0) {
            $fieldGrid.html('<div class="wee-no-fields"><p>No fields selected. Please select some columns first.</p></div>');
            return;
        }
        
        // Add checkboxes for each selected field
        selectedFields.forEach(function(field) {
            const $fieldItem = $(`
                <div class="wee-field-item">
                    <label class="wee-field-checkbox">
                        <input type="checkbox" value="${field.value}" data-field-name="${field.label}">
                        <span>${field.label}</span>
                    </label>
                </div>
            `);
            $fieldGrid.append($fieldItem);
        });
        
        // console.log('WEE: Populated', selectedFields.length, 'fields for combined field');
    }

    function updateAvailableFieldsForCombinedFields() {
        // console.log('WEE: Updating available fields for all combined fields...');
        
        $('.wee-combined-field').each(function() {
            populateAvailableFieldsForCombinedField($(this));
        });
    }

    function getSelectedFields() {
        const selectedFields = [];
        
        $('.wee-column-grid input[type="checkbox"]:checked').each(function() {
            const $checkbox = $(this);
            const value = $checkbox.val();
            const $columnItem = $checkbox.closest('.wee-column-item');
            const $customNameInput = $columnItem.find('.wee-custom-column-name');
            
            // Use custom name if available and not disabled, otherwise use default label
            let label;
            if (!$customNameInput.prop('disabled') && $customNameInput.val().trim()) {
                label = $customNameInput.val().trim();
            } else {
                label = $columnItem.find('.wee-checkbox span').text();
            }
            
            selectedFields.push({
                value: value,
                label: label
            });
        });
        
        return selectedFields;
    }

    function updateCombinedFieldSaveButton($field) {
        const hasName = $field.find('.wee-combined-field-name').val().trim() !== '';
        const hasFields = $field.find('.wee-field-selection-grid input[type="checkbox"]:checked').length > 0;
        
        const $saveButton = $field.find('.wee-save-combined-field');
        $saveButton.prop('disabled', !(hasName && hasFields));
    }

    function saveCombinedField($field) {
        const fieldName = $field.find('.wee-combined-field-name').val().trim();
        const separator = $field.find('.wee-combined-field-separator').val() || ' ';
        const selectedFields = [];
        
        $field.find('.wee-field-selection-grid input[type="checkbox"]:checked').each(function() {
            selectedFields.push({
                value: $(this).val(),
                label: $(this).data('field-name')
            });
        });
        
        if (fieldName === '' || selectedFields.length === 0) {
            showNotice('Please enter a field name and select at least one field to combine.', 'error');
            return;
        }
        
        // Store the combined field data
        const combinedField = {
            name: fieldName,
            fields: selectedFields,
            separator: separator,
            id: $field.attr('id')
        };
        
        // Add to global combined fields array
        if (!window.combinedFields) {
            window.combinedFields = [];
        }
        window.combinedFields.push(combinedField);
        
        // Update the save button
        const $saveButton = $field.find('.wee-save-combined-field');
        $saveButton.text('Saved').addClass('saved').prop('disabled', true);
        
        // Add to column ordering
        addCombinedFieldToOrdering(combinedField);
        
        // Hide individual fields from main column list
        hideCombinedFieldsFromOrdering(selectedFields);
        
        showNotice('Combined field saved successfully!', 'success');
        
        // console.log('WEE: Combined field saved:', combinedField);
    }



    function hideCombinedFieldsFromOrdering(fieldKeys) {
        fieldKeys.forEach(function(fieldKey) {
            $(`.wee-column-item input[value="${fieldKey.value}"]`).closest('.wee-column-item').hide();
        });
    }

    function removeCombinedField($field) {
        const fieldId = $field.attr('id');
        
        // Remove from global array
        if (window.combinedFields) {
            window.combinedFields = window.combinedFields.filter(function(field) {
                return field.id !== fieldId;
            });
        }
        
        // Remove from column ordering
        const fieldName = $field.find('.wee-combined-field-name').val();
        if (fieldName) {
            const combinedFieldKey = 'combined_' + fieldName.toLowerCase().replace(/\s+/g, '_');
            $(`.wee-column-item input[value="${combinedFieldKey}"]`).closest('.wee-column-item').remove();
        }
        
        // Show individual fields again
        $field.find('.wee-field-selection-grid input[type="checkbox"]:checked').each(function() {
            $(`.wee-column-item input[value="${$(this).val()}"]`).closest('.wee-column-item').show();
        });
        
        // Remove the field
        $field.remove();
        
        // Update column ordering
        if (typeof window.updateSelectedColumnsList === 'function') {
        window.updateSelectedColumnsList();
        }
        
        showNotice('Combined field removed successfully!', 'success');
    }



}); 