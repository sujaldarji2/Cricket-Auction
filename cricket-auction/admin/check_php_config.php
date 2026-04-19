<?php
echo "<h2>PHP Configuration Check</h2>";

echo "<h3>File Upload Settings:</h3>";
echo "file_uploads: " . (ini_get('file_uploads') ? 'Enabled' : 'Disabled') . "<br>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "max_file_uploads: " . ini_get('max_file_uploads') . "<br>";
echo "upload_tmp_dir: " . (ini_get('upload_tmp_dir') ?: 'System default') . "<br>";

echo "<h3>Directory Permissions:</h3>";
$upload_dir = '../uploads/team_logos/';

if (file_exists($upload_dir)) {
    echo "Upload directory exists: Yes<br>";
    echo "Permissions: " . substr(sprintf('%o', fileperms($upload_dir)), -4) . "<br>";
    echo "Writable: " . (is_writable($upload_dir) ? 'Yes' : 'No') . "<br>";
} else {
    echo "Upload directory does not exist<br>";
}

echo "<h3>Current Script Info:</h3>";
echo "Script path: " . __FILE__ . "<br>";
echo "Document root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Current directory: " . getcwd() . "<br>";
?>