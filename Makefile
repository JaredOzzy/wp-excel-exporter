# WordPress Excel Export Plugin Makefile
# Use: make package

# Plugin details
PLUGIN_NAME = wordpress-excel-export
PLUGIN_VERSION = 1.0.1
PACKAGE_NAME = $(PLUGIN_NAME)-$(PLUGIN_VERSION).zip

# Directories
PLUGIN_DIR = .
BUILD_DIR = build

# Files to include in the package
INCLUDE_FILES = \
	wordpress-excel-export.php \
	includes/ \
	templates/ \
	assets/ \
	languages/ \
	uninstall.php \
	README.md \
	INSTALL.md

# Files to exclude from the package
EXCLUDE_FILES = \
	.git/ \
	.gitignore \
	Makefile \
	build/ \
	*.log \
	*.tmp \
	.DS_Store \
	Thumbs.db

# Default target
.PHONY: all
all: clean package

# Clean build directory
.PHONY: clean
clean:
	@echo "Cleaning build directory..."
	@rm -rf $(BUILD_DIR)
	@echo "Clean complete."

# Create package for WordPress admin installation
.PHONY: package
package: clean
	@echo "Creating WordPress plugin package..."
	@mkdir -p $(BUILD_DIR)/$(PLUGIN_NAME)
	
	# Copy files to build directory
	@echo "Copying plugin files..."
	@for file in $(INCLUDE_FILES); do \
		if [ -e "$(PLUGIN_DIR)/$$file" ]; then \
			if [ -d "$(PLUGIN_DIR)/$$file" ]; then \
				cp -r "$(PLUGIN_DIR)/$$file" "$(BUILD_DIR)/$(PLUGIN_NAME)/"; \
			else \
				cp "$(PLUGIN_DIR)/$$file" "$(BUILD_DIR)/$(PLUGIN_NAME)/"; \
			fi \
		fi \
	done
	
	# Create zip file with proper forward slash separators
	@echo "Creating zip package..."
	@python create_zip.py "$(BUILD_DIR)/$(PLUGIN_NAME)" "$(PACKAGE_NAME)"
	
	@echo "Package created: $(PACKAGE_NAME)"
	@echo "You can now upload this file to WordPress Admin -> Plugins -> Add New -> Upload Plugin"

# Create package with version from plugin file
.PHONY: package-version
package-version: clean
	@echo "Extracting version from plugin file..."
	@$(eval PLUGIN_VERSION := $(shell grep "Version:" wordpress-excel-export.php | cut -d' ' -f4))
	@echo "Detected version: $(PLUGIN_VERSION)"
	@$(MAKE) package PLUGIN_VERSION=$(PLUGIN_VERSION)

# Install plugin to local WordPress (if WP_CLI is available)
.PHONY: install
install: package
	@if command -v wp >/dev/null 2>&1; then \
		echo "Installing plugin via WP-CLI..."; \
		wp plugin install $(PACKAGE_NAME) --activate; \
	else \
		echo "WP-CLI not found. Please install manually via WordPress admin."; \
		echo "Upload: $(PACKAGE_NAME)"; \
	fi

# Development package (includes development files)
.PHONY: package-dev
package-dev: clean
	@echo "Creating development package..."
	@mkdir -p $(BUILD_DIR)/$(PLUGIN_NAME)
	
	# Copy all files except build
	@echo "Copying all files..."
	@rsync -av --exclude='build/' --exclude='.git/' $(PLUGIN_DIR)/ $(BUILD_DIR)/$(PLUGIN_NAME)/
	
	# Create zip file with proper forward slash separators
	@echo "Creating development zip package..."
	@python create_zip.py "$(BUILD_DIR)/$(PLUGIN_NAME)" "$(PLUGIN_NAME)-dev-$(PLUGIN_VERSION).zip"
	
	@echo "Development package created: $(PLUGIN_NAME)-dev-$(PLUGIN_VERSION).zip"

# Show package info
.PHONY: info
info:
	@echo "Plugin Name: $(PLUGIN_NAME)"
	@echo "Version: $(PLUGIN_VERSION)"
	@echo "Package: $(PACKAGE_NAME)"
	@echo ""
	@echo "Available targets:"
	@echo "  make package        - Create production package"
	@echo "  make package-dev    - Create development package"
	@echo "  make package-version - Create package with version from plugin file"
	@echo "  make install        - Install via WP-CLI (if available)"
	@echo "  make clean          - Clean build directory"
	@echo "  make info           - Show this information"

# Help target
.PHONY: help
help: info

# Check if required files exist
.PHONY: check
check:
	@echo "Checking required files..."
	@for file in $(INCLUDE_FILES); do \
		if [ ! -e "$(PLUGIN_DIR)/$$file" ]; then \
			echo "WARNING: Required file/directory not found: $$file"; \
		else \
			echo "✓ Found: $$file"; \
		fi \
	done
	@echo "Check complete."

# Validate plugin structure
.PHONY: validate
validate: check
	@echo "Validating plugin structure..."
	@if [ ! -f "$(PLUGIN_DIR)/wordpress-excel-export.php" ]; then \
		echo "ERROR: Main plugin file not found!"; \
		exit 1; \
	fi
	@if [ ! -d "$(PLUGIN_DIR)/includes" ]; then \
		echo "ERROR: Includes directory not found!"; \
		exit 1; \
	fi
	@if [ ! -d "$(PLUGIN_DIR)/templates" ]; then \
		echo "ERROR: Templates directory not found!"; \
		exit 1; \
	fi
	@if [ ! -d "$(PLUGIN_DIR)/assets" ]; then \
		echo "ERROR: Assets directory not found!"; \
		exit 1; \
	fi
	@echo "✓ Plugin structure validation passed."

# Create release package (validates first)
.PHONY: release
release: validate package
	@echo "Release package created: $(PACKAGE_NAME)"
	@echo "Ready for distribution!" 