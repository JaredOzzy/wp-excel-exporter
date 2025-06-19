#!/usr/bin/env python3
"""
Create WordPress plugin zip file with proper forward slash separators.
Usage: python create_zip.py <source_dir> <zip_file>
"""

import zipfile
import os
import sys

def create_plugin_zip(source_dir, zip_file):
    """Create a zip file with forward slash separators."""
    with zipfile.ZipFile(zip_file, 'w', zipfile.ZIP_DEFLATED) as z:
        for root, dirs, files in os.walk(source_dir):
            for file in files:
                # Get the full path of the file
                file_path = os.path.join(root, file)
                
                # Calculate the archive name (relative to source_dir)
                archive_name = os.path.relpath(file_path, os.path.dirname(source_dir))
                
                # Ensure forward slashes in the archive name
                archive_name = archive_name.replace('\\', '/')
                
                # Add file to zip
                z.write(file_path, archive_name)

if __name__ == "__main__":
    if len(sys.argv) != 3:
        print("Usage: python create_zip.py <source_dir> <zip_file>")
        sys.exit(1)
    
    source_dir = sys.argv[1]
    zip_file = sys.argv[2]
    
    if not os.path.exists(source_dir):
        print(f"Error: Source directory '{source_dir}' does not exist.")
        sys.exit(1)
    
    create_plugin_zip(source_dir, zip_file)
    print(f"Created zip file: {zip_file}") 