<?php
session_start();
include '../includes/db-config.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$message = '';
$error = '';

// Get player ID from URL
$player_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($player_id == 0) {
    header("Location: view-players.php");
    exit();
}

// Fetch player data
$player_query = $conn->query("SELECT p.*, c.category_name FROM players p 
                              JOIN categories c ON p.category_id = c.id 
                              WHERE p.id = $player_id");
$player = $player_query->fetch_assoc();

if (!$player) {
    header("Location: view-players.php");
    exit();
}

// Create upload directory if it doesn't exist
$upload_dir = '../uploads/players/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Function to upload player image
function uploadPlayerImage($file, $player_name, $upload_dir) {
    if ($file['error'] !== 0) {
        return '';
    }

    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        return false;
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        return false;
    }

    $clean_name = preg_replace('/[^a-zA-Z0-9]/', '_', $player_name);
    $new_filename = time() . '_' . $clean_name . '.' . $ext;
    $upload_path = $upload_dir . $new_filename;

    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        chmod($upload_path, 0644);
        return 'uploads/players/' . $new_filename;
    }

    return false;
}

// Fetch categories
$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $player_name = $conn->real_escape_string($_POST['player_name']);
    $age = intval($_POST['age']);
    $playing_role = $conn->real_escape_string($_POST['playing_role']);
    $category_id = intval($_POST['category_id']);
    $base_price = floatval($_POST['base_price']);
    $player_image = $player['player_image']; // Keep existing image by default
    
    // Validate age based on category
    $cat_query = $conn->query("SELECT * FROM categories WHERE id = $category_id");
    $category = $cat_query->fetch_assoc();
    
    if($age < $category['min_age'] || $age > $category['max_age']) {
        $error = "Age must be between " . $category['min_age'] . " and " . $category['max_age'] . " for " . $category['category_name'] . " category!";
    } else {
        // Handle new image upload
        if (!empty($_FILES['player_image']['name'])) {
            $upload = uploadPlayerImage($_FILES['player_image'], $player_name, $upload_dir);
            if ($upload === false) {
                $error = "Invalid image file. Allowed: jpg, jpeg, png, gif (max 2MB)";
            } else {
                // Delete old image if exists
                if (!empty($player['player_image']) && file_exists('../' . $player['player_image'])) {
                    unlink('../' . $player['player_image']);
                }
                $player_image = $upload;
            }
        }
        
        if (empty($error)) {
            $stmt = $conn->prepare("UPDATE players SET 
                                   player_name = ?,
                                   age = ?,
                                   playing_role = ?,
                                   player_image = ?,
                                   category_id = ?,
                                   base_price = ?
                                   WHERE id = ?");
            $stmt->bind_param("sissidi", $player_name, $age, $playing_role, $player_image, $category_id, $base_price, $player_id);
            
            if ($stmt->execute()) {
                $message = "Player updated successfully!";
                // Refresh player data
                $player_query = $conn->query("SELECT p.*, c.category_name FROM players p 
                                              JOIN categories c ON p.category_id = c.id 
                                              WHERE p.id = $player_id");
                $player = $player_query->fetch_assoc();
            } else {
                $error = "Error: " . $conn->error;
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Player - Cricket Auction</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: #2c3e50;
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #34495e;
        }

        .sidebar-header h2 {
            font-size: 24px;
            color: #ecf0f1;
        }

        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
        }

        .sidebar-menu li {
            padding: 12px 20px;
            transition: background 0.3s;
        }

        .sidebar-menu li:hover {
            background: #34495e;
        }

        .sidebar-menu li.active {
            background: #3498db;
        }

        .sidebar-menu a {
            color: white;
            text-decoration: none;
            display: block;
        }

        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
        }

        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }

        .header {
            background: white;
            border-radius: 15px;
            padding: 25px 30px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .header-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }

        .header h1 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 5px;
        }

        .header p {
            color: #7f8c8d;
            font-size: 14px;
        }

        .content-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }

        .form-group input[readonly] {
            background: #f8f9fa;
            cursor: not-allowed;
        }

        .image-upload-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 30px;
            flex-wrap: wrap;
        }

        .current-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            border: 4px solid #3498db;
            box-shadow: 0 10px 30px rgba(52,152,219,0.3);
        }

        .current-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .current-image i {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f7ff;
            color: #3498db;
            font-size: 50px;
        }

        .upload-controls {
            flex: 1;
        }

        .file-input-wrapper {
            position: relative;
            display: inline-block;
        }

        .file-input-wrapper input[type="file"] {
            position: absolute;
            left: -9999px;
        }

        .file-input-wrapper label {
            display: inline-block;
            padding: 12px 25px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }

        .file-input-wrapper label:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102,126,234,0.6);
        }

        .file-name {
            margin-left: 15px;
            color: #7f8c8d;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .btn {
            padding: 14px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102,126,234,0.6);
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #ecf0f1;
        }

        .info-text {
            font-size: 13px;
            color: #7f8c8d;
            margin-top: 5px;
            display: block;
        }

        .age-range-hint {
            background: #e8f0fe;
            color: #2c3e50;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 13px;
            margin-top: 8px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Cricket Auction</h2>
                <p>Welcome, <?php echo $_SESSION['admin_username'] ?? 'Admin'; ?></p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="add-category.php"><i class="fas fa-tags"></i> Categories</a></li>
                <li><a href="add-player.php"><i class="fas fa-user-plus"></i> Add Player</a></li>
                <li class="active"><a href="view-players.php"><i class="fas fa-users"></i> View Players</a></li>
                <li><a href="manage-teams.php"><i class="fas fa-users-cog"></i> Manage Teams</a></li>
                <li ><a href="auction-control_1.php"><i class="fas fa-gavel"></i> Live Auction (Simple)</a></li>
                <li ><a href="auction-control.php"><i class="fas fa-gavel"></i> Live Auction (Advanced)</a></li>
                <li style="border-top: 1px solid #34495e; margin-top: 20px;">
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <div class="header-icon">
                    <i class="fas fa-user-edit"></i>
                </div>
                <div>
                    <h1>Edit Player</h1>
                    <p>Update player information and details</p>
                </div>
            </div>

            <?php if($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="content-section">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="image-upload-section">
                        <div class="current-image">
                            <?php if(!empty($player['player_image'])): ?>
                                <img src="../<?php echo $player['player_image']; ?>" alt="<?php echo $player['player_name']; ?>">
                            <?php else: ?>
                                <i class="fas fa-user-circle"></i>
                            <?php endif; ?>
                        </div>
                        <div class="upload-controls">
                            <div class="file-input-wrapper">
                                <input type="file" name="player_image" id="player_image" accept="image/*">
                                <label for="player_image"><i class="fas fa-camera"></i> Change Photo</label>
                                <span class="file-name" id="file_name">No file chosen</span>
                            </div>
                            <span class="info-text">Allowed: JPG, JPEG, PNG, GIF (Max 2MB)</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-hashtag"></i> Registration Number</label>
                            <input type="text" value="<?php echo $player['registration_no']; ?>" readonly>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Player Name</label>
                            <input type="text" name="player_name" required 
                                   value="<?php echo htmlspecialchars($player['player_name']); ?>"
                                   placeholder="Enter player full name">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-calendar-alt"></i> Age</label>
                            <input type="number" id="age" name="age" required 
                                   value="<?php echo $player['age']; ?>"
                                   placeholder="Enter player age" min="5" max="50">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-baseball-ball"></i> Playing Role</label>
                            <select id="playing_role" name="playing_role" required>
                                <option value="">Select Role</option>
                                <option value="Batsman" <?php echo ($player['playing_role'] == 'Batsman') ? 'selected' : ''; ?>>Batsman</option>
                                <option value="Bowler" <?php echo ($player['playing_role'] == 'Bowler') ? 'selected' : ''; ?>>Bowler</option>
                                <option value="All-rounder" <?php echo ($player['playing_role'] == 'All-rounder') ? 'selected' : ''; ?>>All-rounder</option>
                                <option value="Wicket-keeper" <?php echo ($player['playing_role'] == 'Wicket-keeper') ? 'selected' : ''; ?>>Wicket-keeper</option>
                                <option value="Wicket-keeper Batsman" <?php echo ($player['playing_role'] == 'Wicket-keeper Batsman') ? 'selected' : ''; ?>>Wicket-keeper Batsman</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Category</label>
                            <select id="category_id" name="category_id" required onchange="updateAgeRange()">
                                <option value="">Select Category</option>
                                <?php 
                                $categories = $conn->query("SELECT * FROM categories ORDER BY category_name");
                                while($category = $categories->fetch_assoc()) {
                                    $selected = ($player['category_id'] == $category['id']) ? 'selected' : '';
                                    $badge = ($category['category_name'] == 'Under 15') ? '👶' : '👨';
                                    echo "<option value='" . $category['id'] . "' $selected data-min='" . $category['min_age'] . "' data-max='" . $category['max_age'] . "'>" . $badge . " " . $category['category_name'] . " (" . $category['min_age'] . "-" . $category['max_age'] . " years)</option>";
                                }
                                ?>
                            </select>
                            <span id="ageRangeHint" class="age-range-hint"></span>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-rupee-sign"></i> Base Price (₹)</label>
                            <input type="number" name="base_price" required 
                                   value="<?php echo $player['base_price']; ?>"
                                   placeholder="Enter base price" min="1000" step="1000">
                        </div>
                    </div>

                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Player
                        </button>
                        <a href="view-players.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function updateAgeRange() {
        const select = document.getElementById('category_id');
        const selected = select.options[select.selectedIndex];
        const ageInput = document.getElementById('age');
        const hint = document.getElementById('ageRangeHint');
        
        if(selected.value) {
            const minAge = selected.dataset.min;
            const maxAge = selected.dataset.max;
            ageInput.min = minAge;
            ageInput.max = maxAge;
            hint.innerHTML = `👋 Age should be between ${minAge} and ${maxAge} years for this category`;
            
            // Validate current age
            if(ageInput.value < minAge || ageInput.value > maxAge) {
                ageInput.style.borderColor = '#e74c3c';
            } else {
                ageInput.style.borderColor = '#e0e0e0';
            }
        }
    }

    // Image preview
    document.getElementById('player_image').addEventListener('change', function(e) {
        const fileName = e.target.files[0]?.name || 'No file chosen';
        document.getElementById('file_name').textContent = fileName;
        
        if (e.target.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.querySelector('.current-image');
                preview.innerHTML = '<img src="' + e.target.result + '">';
            }
            reader.readAsDataURL(e.target.files[0]);
        }
    });

    // Run on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateAgeRange();
    });

    // Auto-hide alerts
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.style.display = 'none', 500);
        });
    }, 5000);
    </script>
</body>
</html>