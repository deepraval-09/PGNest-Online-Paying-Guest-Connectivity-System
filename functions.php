<?php
function optimizeAndMoveImage($file, $subfolder_name) {
    // 1. CLEANUP FOLDER NAME (Remove slashes)
    $subfolder_name = trim($subfolder_name, "/\\");
    
    // 2. GET ABSOLUTE PATH (The Magic Fix)
    // This converts "uploads" to "C:\xampp\htdocs\pgnest\uploads\"
    $target_dir = __DIR__ . DIRECTORY_SEPARATOR . $subfolder_name . DIRECTORY_SEPARATOR;

    // 3. AUTO-CREATE FOLDER (If missing)
    if (!is_dir($target_dir)) {
        if (!mkdir($target_dir, 0777, true)) {
            return "Error: Could not create folder at: $target_dir"; 
        }
    }

    // 4. CHECK FOR PHP UPLOAD ERRORS
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return "Error: File is too big (Limit is usually 2MB).";
            case UPLOAD_ERR_NO_FILE:
                return "Error: No file was uploaded.";
            default:
                return "Error: Unknown upload error (Code " . $file['error'] . ")";
        }
    }

    // 5. VALIDATE EXTENSION
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowed)) { 
        return "Error: Invalid file type. Only JPG, PNG, WEBP allowed."; 
    }

    // 6. GENERATE UNIQUE NAME
    $new_name = uniqid('img_', true) . '.' . $ext;
    $destination = $target_dir . $new_name;

    // 7. MOVE FILE
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return $new_name; // Success! Return just the filename
    }
    
    return "Error: Failed to move file to: $destination";
}
?>