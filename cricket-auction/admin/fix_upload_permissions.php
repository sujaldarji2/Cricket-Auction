<?php
session_start();
include '../includes/db-config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$message = '';
$error = '';

// Get absolute path
$base_dir = dirname(__DIR__); // This goes up one level from admin folder
$upload_dir_abs = $base_dir . '/uploads/team_logos/';
$upload_dir_rel = '../uploads/team_logos/';

echo "<h2>🔧 Upload Test Page</h2>";

// Show path information
echo "<h3>📁 Path Information:</h3>";
echo "Base directory: " . $base_dir . "<br>";
echo "Absolute upload path: " . $upload_dir_abs . "<br>";
echo "Relative upload path: " . $upload_dir_rel . "<br>";

// Check and create directory
if (!file_exists($upload_dir_abs)) {
    echo "Creating directory: " . $upload_dir_abs . "<br>";
    if (mkdir($upload_dir_abs, 0777, true)) {
        echo "✅ Directory created successfully<br>";
    } else {
        echo "❌ Failed to create directory<br>";
    }
}

// Check directory permissions
if (file_exists($upload_dir_abs)) {
    echo "<h3>🔐 Permission Check:</h3>";
    $perms = fileperms($upload_dir_abs);
    $perm_string = substr(sprintf('%o', $perms), -4);
    echo "Directory permissions: " . $perm_string . "<br>";
    
    if (is_writable($upload_dir_abs)) {
        echo "✅ Directory is writable<br>";
    } else {
        echo "❌ Directory is NOT writable<br>";
        
        // Try to fix permissions
        echo "Attempting to fix permissions...<br>";
        chmod($upload_dir_abs, 0777);
        
        if (is_writable($upload_dir_abs)) {
            echo "✅ Permissions fixed! Directory is now writable<br>";
        } else {
            echo "❌ Could not fix permissions automatically.<br>";
            echo "Please run this command in terminal:<br>";
            echo "<pre style='background: #f4f4f4; padding: 10px;'>";
            echo "sudo chmod -R 777 " . $upload_dir_abs . "<br>";
            echo "sudo chown -R daemon:daemon " . $upload_dir_abs;
            echo "</pre>";
        }
    }
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['test_image'])) {
    echo "<h3>📤 Upload Debug Info:</h3>";
    
    $file = $_FILES['test_image'];
    
    echo "File name: " . htmlspecialchars($file['name']) . "<br>";
    echo "File size: " . $file['size'] . " bytes (" . round($file['size']/1024/1024, 2) . " MB)<br>";
    echo "File type: " . $file['type'] . "<br>";
    echo "Temp file: " . $file['tmp_name'] . "<br>";
    echo "Error code: " . $file['error'] . "<br>";
    
    if ($file['error'] == 0) {
        // Clean filename
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $clean_filename = time() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', pathinfo($file['name'], PATHINFO_FILENAME)) . '.' . $file_extension;
        
        $destination_abs = $upload_dir_abs . $clean_filename;
        $destination_rel = '../uploads/team_logos/' . $clean_filename;
        
        echo "Clean filename: " . $clean_filename . "<br>";
        echo "Absolute destination: " . $destination_abs . "<br>";
        echo "Relative destination: " . $destination_rel . "<br>";
        echo "Destination directory writable? " . (is_writable($upload_dir_abs) ? '✅ Yes' : '❌ No') . "<br>";
        
        if (is_writable($upload_dir_abs)) {
            if (move_uploaded_file($file['tmp_name'], $destination_abs)) {
                echo "<span style='color: green; font-weight: bold;'>✅ File uploaded successfully!</span><br>";
                chmod($destination_abs, 0644);
                echo "<img src='" . $destination_rel . "' style='max-width: 200px; margin-top: 10px; border: 1px solid #ddd;'><br>";
                echo "File saved to: " . $destination_abs . "<br>";
            } else {
                echo "<span style='color: red; font-weight: bold;'>❌ Failed to move file!</span><br>";
                $error_info = error_get_last();
                echo "Error: " . ($error_info['message'] ?? 'Unknown error') . "<br>";
            }
        } else {
            echo "<span style='color: red; font-weight: bold;'>❌ Cannot upload: Directory not writable</span><br>";
        }
    } else {
        $upload_errors = array(
            1 => "File exceeds upload_max_filesize",
            2 => "File exceeds MAX_FILE_SIZE",
            3 => "File partially uploaded",
            4 => "No file uploaded",
            6 => "Missing temporary folder",
            7 => "Failed to write file",
            8 => "PHP extension stopped upload"
        );
        echo "<span style='color: red; font-weight: bold;'>❌ Upload error: " . ($upload_errors[$file['error']] ?? "Unknown error") . "</span><br>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Upload - Cricket Auction</title>
    <style>
        body { 
            font-family: 'Segoe UI', Arial, sans-serif; 
            padding: 30px; 
            background: #f5f5f5;
            line-height: 1.6;
        }
        .container { 
            max-width: 800px; 
            margin: 0 auto; 
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        h2, h3 { 
            color: #2c3e50; 
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        .btn { 
            padding: 12px 30px; 
            background: #3498db; 
            color: white; 
            border: none; 
            border-radius: 5px;
            cursor: pointer; 
            font-size: 16px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #2980b9;
        }
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin: 20px 0;
        }
        pre {
            background: #2c3e50;
            color: white;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .success { color: #27ae60; }
        .error { color: #e74c3c; }
        .warning { color: #f39c12; }
    </style>
</head>
<body>
    <div class="container">
        <h2>📤 Test File Upload</h2>
        
        <div class="info-box">
            <strong>System Info:</strong><br>
            PHP Version: <?php echo phpversion(); ?><br>
            Server: <?php echo $_SERVER['SERVER_SOFTWARE']; ?><br>
            Document Root: <?php echo $_SERVER['DOCUMENT_ROOT']; ?>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <div style="margin: 20px 0;">
                <label for="test_image" style="display: block; margin-bottom: 10px; font-weight: bold;">
                    Choose an image to upload:
                </label>
                <input type="file" name="test_image" id="test_image" accept="image/*" required 
                       style="padding: 10px; border: 1px solid #ddd; border-radius: 5px; width: 100%;">
            </div>
            <button type="submit" class="btn">Upload Test File</button>
        </form>
        
        <div style="margin-top: 30px;">
            <a href="manage-teams.php" style="color: #3498db;">← Back to Manage Teams</a>
        </div>
    </div>
</body>
</html>