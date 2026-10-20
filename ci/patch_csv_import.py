#!/usr/bin/env python3
"""Patch only the Daily POS source reader to accept safe CSV alongside XLSX."""

def patch_importer(source: str) -> str:
    old = "private function uploaded_file( $key = 'file' ) {"
    new = "private function uploaded_file( $key = 'file', $allow_csv = false ) {"
    if source.count(old) != 1:
        raise ValueError('Expected exactly one uploaded_file() declaration.')
    source = source.replace(old, new, 1)
    old = """\t\tif ( empty( $upload ) ) {
\t\t\tthrow new Exception( 'Please select an .xlsx file.' );
\t\t}"""
    new = """\t\tif ( empty( $upload ) ) {
\t\t\tthrow new Exception( $allow_csv ? 'Please select a .csv or .xlsx file.' : 'Please select an .xlsx file.' );
\t\t}"""
    if source.count(old) != 1:
        raise ValueError('Could not find unique empty-upload validation anchor.')
    source = source.replace(old, new, 1)
    old = """\t\t$file_name_input = isset( $upload['name'] ) && is_string( $upload['name'] ) ? wp_unslash( $upload['name'] ) : '';
\t\t$name            = sanitize_file_name( $file_name_input );
\t\tif ( strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) !== 'xlsx' ) {
\t\t\tthrow new Exception( 'Only XLSX files are accepted.' );
\t\t}"""
    new = """\t\t$file_name_input = isset( $upload['name'] ) && is_string( $upload['name'] ) ? wp_unslash( $upload['name'] ) : '';
\t\t$name            = sanitize_file_name( $file_name_input );
\t\t$extension       = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
\t\tif ( 'xlsx' !== $extension && ( ! $allow_csv || 'csv' !== $extension ) ) {
\t\t\tthrow new Exception( $allow_csv ? 'Only CSV or XLSX files are accepted for Daily POS imports.' : 'Only XLSX files are accepted.' );
\t\t}"""
    if source.count(old) != 1:
        raise ValueError('Could not find unique extension-validation anchor.')
    source = source.replace(old, new, 1)
    old = """\t\treturn array(
\t\t\t'path'       => $path,
\t\t\t'name'       => $name,
\t\t\t'size_bytes' => $size,
\t\t);"""
    new = """\t\treturn array(
\t\t\t'path'       => $path,
\t\t\t'name'       => $name,
\t\t\t'size_bytes' => $size,
\t\t\t'extension'  => $extension,
\t\t);"""
    if source.count(old) != 1:
        raise ValueError('Could not find unique uploaded-file return anchor.')
    source = source.replace(old, new, 1)
    # Make upload failure messages neutral within this helper without affecting
    # Product Master policy or any other XLSX-only operation.
    start = source.index("private function uploaded_file( $key = 'file', $allow_csv = false ) {")
    end = source.index("\n\t/**\n\t * Normalizes a string for stable import matching.", start)
    method = source[start:end]
    for old_message, new_message in [
        ('The XLSX upload exceeds the server upload limit.', 'The import upload exceeds the server upload limit.'),
        ('The XLSX upload exceeds the form upload limit.', 'The import upload exceeds the form upload limit.'),
        ('The XLSX upload was incomplete. Please upload the file again.', 'The import upload was incomplete. Please upload the file again.'),
        ('The XLSX upload failed.', 'The import upload failed.'),
        ('The uploaded XLSX file could not be read.', 'The uploaded import file could not be read.'),
        ('The XLSX upload is too large. Maximum allowed size is 8 MB.', 'The import file is too large. Maximum allowed size is 8 MB.'),
        ('The uploaded XLSX file is empty.', 'The uploaded import file is empty.'),
    ]:
        method = method.replace(old_message, new_message)
    source = source[:start] + method + source[end:]
    old = "$file                    = $this->uploaded_file( 'file' );"
    if source.count(old) != 1:
        raise ValueError('Could not find unique daily POS upload call.')
    source = source.replace(old, "$file                    = $this->uploaded_file( 'file', true );", 1)
    old = "$xlsx     = new Stockland_XLSX( $file['path'] );"
    new = "$xlsx     = ( 'csv' === ( $file['extension'] ?? '' ) ) ? new Stockland_CSV( $file['path'] ) : new Stockland_XLSX( $file['path'] );"
    if source.count(old) != 1:
        raise ValueError('Could not find unique daily POS parser selection anchor.')
    source = source.replace(old, new, 1)
    source = source.replace('Daily POS Excel data', 'Daily POS CSV/Excel data')
    return source

def patch_entrypoint(source: str) -> str:
    anchor = "require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-xlsx.php';"
    addition = anchor + "\nrequire_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-csv.php';"
    if source.count(anchor) != 1:
        raise ValueError('Could not find the unique XLSX reader include in the plugin entrypoint.')
    if "includes/class-stockland-csv.php" in source:
        raise ValueError('CSV reader include is already present; review the baseline before rebuilding.')
    return source.replace(anchor, addition, 1)
