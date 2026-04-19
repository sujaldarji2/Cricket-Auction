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

$upload_dir = '../uploads/team_logos/';

// Create upload directory with absolute path
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Fix directory permissions
chmod($upload_dir, 0777);

function uploadLogo($file, $team_name, $upload_dir)
{
    if ($file['error'] !== 0) {
        return false;
    }

    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        return false;
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        return false;
    }

    // Clean team name for filename
    $clean_name = preg_replace('/[^a-zA-Z0-9]/', '_', $team_name);
    $new_filename = time() . "_" . $clean_name . "." . $ext;
    $upload_path = $upload_dir . $new_filename;

    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        chmod($upload_path, 0644);
        // Return correct relative path from project root
        return 'uploads/team_logos/' . $new_filename;
    }

    return false;
}

if ($_SERVER['REQUEST_METHOD'] == "POST") {

    $action = $_POST['action'];

    if ($action == "add_team") {

        $team_name = $_POST['team_name'];
        $short_code = $_POST['short_code'];
        $team_color = $_POST['team_color'];
        $total_purse = $_POST['total_purse'];
        $max_players = $_POST['max_players'];
        $captain_id = !empty($_POST['captain_id']) ? intval($_POST['captain_id']) : NULL;
        $logo_url = "";

        if (!empty($_FILES['team_logo']['name'])) {
            $upload = uploadLogo($_FILES['team_logo'], $team_name, $upload_dir);
            if ($upload === false) {
                $error = "Invalid logo file.";
            } else {
                $logo_url = $upload;
            }
        }

        if (empty($error)) {
            $stmt = $conn->prepare("INSERT INTO teams 
            (team_name, team_short_code, team_color, total_purse, max_players, logo_url, captain_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");

            $stmt->bind_param(
                "sssdisi",
                $team_name,
                $short_code,
                $team_color,
                $total_purse,
                $max_players,
                $logo_url,
                $captain_id
            );

            if ($stmt->execute()) {
                $message = "Team added successfully";
            } else {
                $error = "Database error: " . $conn->error;
            }
            $stmt->close();
        }
    }

    if ($action == "update_team") {

        $team_id = $_POST['team_id'];
        $team_name = $_POST['team_name'];
        $short_code = $_POST['short_code'];
        $team_color = $_POST['team_color'];
        $total_purse = $_POST['total_purse'];
        $max_players = $_POST['max_players'];
        $captain_id = !empty($_POST['captain_id']) ? intval($_POST['captain_id']) : NULL;
        $logo_url = $_POST['existing_logo'];

        if (!empty($_FILES['team_logo']['name'])) {
            $upload = uploadLogo($_FILES['team_logo'], $team_name, $upload_dir);
            if ($upload !== false) {
                // Delete old logo if exists
                if ($logo_url && file_exists("../" . $logo_url)) {
                    unlink("../" . $logo_url);
                }
                $logo_url = $upload;
            }
        }

        $stmt = $conn->prepare("UPDATE teams SET 
        team_name=?, 
        team_short_code=?, 
        team_color=?, 
        total_purse=?, 
        max_players=?, 
        logo_url=?,
        captain_id=?
        WHERE id=?");

        $stmt->bind_param(
            "sssdisii",
            $team_name,
            $short_code,
            $team_color,
            $total_purse,
            $max_players,
            $logo_url,
            $captain_id,
            $team_id
        );

        if ($stmt->execute()) {
            $message = "Team updated successfully";
        } else {
            $error = "Update failed: " . $conn->error;
        }
        $stmt->close();
    }

    if ($action == "delete_team") {

        $team_id = $_POST['team_id'];

        $result = $conn->query("SELECT logo_url FROM teams WHERE id=$team_id");
        $row = $result->fetch_assoc();

        if ($row && $row['logo_url'] && file_exists("../" . $row['logo_url'])) {
            unlink("../" . $row['logo_url']);
        }

        $stmt = $conn->prepare("DELETE FROM teams WHERE id=?");
        $stmt->bind_param("i", $team_id);

        if ($stmt->execute()) {
            $message = "Team deleted successfully";
        } else {
            $error = "Delete failed: " . $conn->error;
        }
        $stmt->close();
    }

    if ($action == "reset_team") {

        $team_id = $_POST['team_id'];

        $stmt = $conn->prepare("UPDATE teams 
        SET spent_amount=0, players_bought=0, captain_id=NULL
        WHERE id=?");

        $stmt->bind_param("i", $team_id);

        if ($stmt->execute()) {
            $conn->query("DELETE FROM auction_purchases WHERE team_id=$team_id");
            $message = "Team reset successfully";
        } else {
            $error = "Reset failed: " . $conn->error;
        }
        $stmt->close();
    }

    if ($action == "update_purse") {

        $team_id = $_POST['team_id'];
        $total_purse = $_POST['total_purse'];

        $stmt = $conn->prepare("UPDATE teams SET total_purse=? WHERE id=?");
        $stmt->bind_param("di", $total_purse, $team_id);

        if ($stmt->execute()) {
            $message = "Purse updated";
        } else {
            $error = "Update failed: " . $conn->error;
        }
        $stmt->close();
    }

    if ($action == "update_captain") {

        $team_id = $_POST['team_id'];
        $captain_id = !empty($_POST['captain_id']) ? intval($_POST['captain_id']) : NULL;

        $stmt = $conn->prepare("UPDATE teams SET captain_id=? WHERE id=?");
        $stmt->bind_param("ii", $captain_id, $team_id);

        if ($stmt->execute()) {
            $message = "Captain updated successfully";
        } else {
            $error = "Update failed: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fix existing logo URLs in database
$conn->query("UPDATE teams SET logo_url = REPLACE(logo_url, 'http://localhost/cricket-auction/', '') WHERE logo_url LIKE 'http%'");
$conn->query("UPDATE teams SET logo_url = REPLACE(logo_url, '/admin/', '/') WHERE logo_url LIKE '%/admin/%'");
$conn->query("UPDATE teams SET logo_url = CONCAT('uploads/team_logos/', SUBSTRING_INDEX(logo_url, '/', -1)) WHERE logo_url NOT LIKE 'uploads/%' AND logo_url != '' AND logo_url IS NOT NULL");

// Add captain_id column if it doesn't exist
$check_column = $conn->query("SHOW COLUMNS FROM teams LIKE 'captain_id'");
if($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE teams ADD COLUMN captain_id INT NULL AFTER max_players");
}

$teams = $conn->query("SELECT t.*, p.player_name as captain_name, p.player_image as captain_image 
                      FROM teams t 
                      LEFT JOIN players p ON t.captain_id = p.id
                      ORDER BY t.team_name");

$summary = $conn->query("
SELECT
SUM(total_purse) as total_purse,
SUM(spent_amount) as total_spent,
SUM(players_bought) as total_players_bought
FROM teams
")->fetch_assoc();

if (!$summary) {
    $summary = ['total_purse' => 0, 'total_spent' => 0, 'total_players_bought' => 0];
}

// Get all players for captain selection
$players = $conn->query("SELECT id, player_name, registration_no FROM players ORDER BY player_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Teams - Cricket Auction</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .teams-container {
            padding: 20px;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .summary-card h3 {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        
        .summary-card .value {
            font-size: 32px;
            font-weight: bold;
        }
        
        .add-team-form {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .team-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            border-top: 4px solid transparent;
        }
        
        .team-card:hover {
            transform: translateY(-5px);
        }
        
        .team-header {
            padding: 20px;
            color: white;
            position: relative;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .team-logo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 3px solid rgba(255,255,255,0.3);
        }
        
        .team-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .team-logo i {
            font-size: 30px;
            color: #2c3e50;
        }
        
        .team-info {
            flex: 1;
        }
        
        .team-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .team-short {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .captain-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            font-size: 11px;
            margin-top: 5px;
        }
        
        .captain-badge i {
            font-size: 12px;
        }
        
        .team-stats {
            padding: 20px;
            background: #f8f9fa;
        }
        
        .stat-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .stat-label {
            color: #7f8c8d;
        }
        
        .stat-value {
            font-weight: bold;
            color: #2c3e50;
        }
        
        .captain-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: #e3f2fd;
            border-radius: 8px;
            margin: 10px 0;
        }
        
        .captain-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #3498db;
        }
        
        .default-captain-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: #3498db;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        
        .captain-details {
            flex: 1;
        }
        
        .captain-name {
            font-weight: bold;
            color: #2c3e50;
            font-size: 14px;
        }
        
        .captain-label {
            font-size: 11px;
            color: #7f8c8d;
        }
        
        .purse-bar {
            height: 10px;
            background: #ecf0f1;
            border-radius: 5px;
            margin: 15px 0;
            overflow: hidden;
        }
        
        .purse-fill {
            height: 100%;
            background: linear-gradient(90deg, #27ae60, #f39c12, #e74c3c);
            transition: width 0.3s;
        }
        
        .team-actions {
            display: flex;
            gap: 10px;
            padding: 15px 20px;
            background: white;
            border-top: 1px solid #ecf0f1;
        }
        
        .action-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .edit-btn {
            background: #3498db;
            color: white;
        }
        
        .captain-btn {
            background: #9b59b6;
            color: white;
        }
        
        .reset-btn {
            background: #f39c12;
            color: white;
        }
        
        .delete-btn {
            background: #e74c3c;
            color: white;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(0,0,0,0.2);
        }
        
        .purse-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-good {
            background: #d4edda;
            color: #155724;
        }
        
        .status-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .captain-select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .captain-select:focus {
            outline: none;
            border-color: #9b59b6;
        }
        
        .color-preview {
            width: 30px;
            height: 30px;
            border-radius: 5px;
            display: inline-block;
            margin-left: 10px;
            vertical-align: middle;
        }
        
        .logo-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 3px solid #3498db;
            margin: 10px auto;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
        }
        
        .logo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .logo-preview i {
            font-size: 50px;
            color: #bdc3c7;
        }
        
        .file-input-wrapper {
            position: relative;
            margin-bottom: 15px;
        }
        
        .file-input-wrapper input[type="file"] {
            position: absolute;
            left: -9999px;
        }
        
        .file-input-wrapper label {
            display: inline-block;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .file-input-wrapper label:hover {
            background: #2980b9;
        }
        
        .file-name {
            margin-left: 10px;
            color: #7f8c8d;
        }
        
        .no-captain {
            color: #95a5a6;
            font-style: italic;
            font-size: 13px;
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
                <li><a href="view-players.php"><i class="fas fa-users"></i> View Players</a></li>
                <li class="active"><a href="manage-teams.php"><i class="fas fa-users-cog"></i> Manage Teams</a></li>
                <li ><a href="auction-control_1.php"><i class="fas fa-gavel"></i> Live Auction (Simple)</a></li>
                <li ><a href="auction-control.php"><i class="fas fa-gavel"></i> Live Auction (Advanced)</a></li>
                <li><a href="player-status.php"><i class="fas fa-sync-alt"></i> Player Status</a></li>
                <li><a href="team-players.php"><i class="fas fa-users-between-lines"></i> Team Players</a></li>
                <li><a href="highest-bids.php"><i class="fas fa-chart-line"></i> Highest Bids</a></li>
                <li style="border-top: 1px solid #34495e; margin-top: 20px;">
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="teams-container">
                <!-- Header -->
                <div class="header">
                    <h1><i class="fas fa-users-cog"></i> Manage Teams</h1>
                    <p>Add and manage teams, their purse amounts, and assign captains</p>
                </div>

                <?php if($message): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <?php if($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Summary Cards -->
                <div class="summary-cards">
                    <div class="summary-card">
                        <h3>Total Purse Across Teams</h3>
                        <div class="value">₹<?php echo number_format($summary['total_purse'] ?? 0, 2); ?></div>
                    </div>
                    <div class="summary-card" style="background: linear-gradient(135deg, #27ae60, #229954);">
                        <h3>Total Spent</h3>
                        <div class="value">₹<?php echo number_format($summary['total_spent'] ?? 0, 2); ?></div>
                    </div>
                    <div class="summary-card" style="background: linear-gradient(135deg, #e74c3c, #c0392b);">
                        <h3>Remaining Purse</h3>
                        <div class="value">₹<?php echo number_format(($summary['total_purse'] ?? 0) - ($summary['total_spent'] ?? 0), 2); ?></div>
                    </div>
                    <div class="summary-card" style="background: linear-gradient(135deg, #3498db, #2980b9);">
                        <h3>Total Players Bought</h3>
                        <div class="value"><?php echo $summary['total_players_bought'] ?? 0; ?></div>
                    </div>
                </div>

                <!-- Add Team Form -->
                <div class="add-team-form">
                    <h2 style="margin-bottom: 20px; color: #2c3e50;">Add New Team</h2>
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_team">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Team Name</label>
                                <input type="text" name="team_name" required placeholder="e.g., Mumbai Indians">
                            </div>
                            <div class="form-group">
                                <label>Short Code</label>
                                <input type="text" name="short_code" required placeholder="e.g., MI" maxlength="5">
                            </div>
                            <div class="form-group">
                                <label>Team Color</label>
                                <input type="color" name="team_color" value="#3498db" style="height: 42px;">
                            </div>
                            <div class="form-group">
                                <label>Total Purse (₹)</label>
                                <input type="number" name="total_purse" required value="10000000" step="100000">
                            </div>
                            <div class="form-group">
                                <label>Max Players</label>
                                <input type="number" name="max_players" required value="15" min="1" max="25">
                            </div>
                            <div class="form-group">
                                <label>Team Logo</label>
                                <div class="file-input-wrapper">
                                    <input type="file" name="team_logo" id="team_logo" accept="image/*">
                                    <label for="team_logo"><i class="fas fa-upload"></i> Choose Logo</label>
                                    <span class="file-name" id="file_name">No file chosen</span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Captain</label>
                                <select name="captain_id" class="captain-select">
                                    <option value="">-- Select Captain --</option>
                                    <?php 
                                    if($players && $players->num_rows > 0) {
                                        while($player = $players->fetch_assoc()) {
                                            echo "<option value='" . $player['id'] . "'>" . $player['player_name'] . " (" . $player['registration_no'] . ")</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary" style="margin-top: 24px;">
                                    <i class="fas fa-plus"></i> Add Team
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Teams Grid -->
                <h2 style="margin: 30px 0 20px; color: #2c3e50;">Teams</h2>
                <div class="team-grid">
                    <?php if($teams && $teams->num_rows > 0): ?>
                        <?php while($team = $teams->fetch_assoc()): 
                            $remaining = $team['total_purse'] - $team['spent_amount'];
                            $spent_percentage = ($team['total_purse'] > 0) ? ($team['spent_amount'] / $team['total_purse'] * 100) : 0;
                            
                            if($remaining > $team['total_purse'] * 0.5) {
                                $status_class = 'status-good';
                                $status_text = 'Good Balance';
                            } elseif($remaining > $team['total_purse'] * 0.2) {
                                $status_class = 'status-warning';
                                $status_text = 'Moderate Balance';
                            } else {
                                $status_class = 'status-danger';
                                $status_text = 'Low Balance';
                            }
                            
                            // Fix logo path for display
                            $logo_display = '';
                            if (!empty($team['logo_url'])) {
                                $logo_filename = basename($team['logo_url']);
                                $logo_display = 'uploads/team_logos/' . $logo_filename;
                            }
                        ?>
                            <div class="team-card">
                                <div class="team-header" style="background: <?php echo $team['team_color']; ?>;">
                                    <div class="team-logo">
                                        <?php if(!empty($logo_display)): ?>
                                            <img src="../<?php echo $logo_display; ?>" alt="<?php echo $team['team_name']; ?>">
                                        <?php else: ?>
                                            <i class="fas fa-shield-alt"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="team-info">
                                        <div class="team-name"><?php echo $team['team_name']; ?></div>
                                        <div class="team-short"><?php echo $team['team_short_code']; ?></div>
                                        <?php if(!empty($team['captain_name'])): ?>
                                            <div class="captain-badge">
                                                <i class="fas fa-crown"></i> C: <?php echo $team['captain_name']; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="team-stats">
                                    <?php if(!empty($team['captain_name'])): ?>
                                    <div class="captain-info">
                                        <?php if(!empty($team['captain_image'])): ?>
                                            <img src="../<?php echo $team['captain_image']; ?>" class="captain-avatar">
                                        <?php else: ?>
                                            <div class="default-captain-avatar">
                                                <i class="fas fa-user-circle"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="captain-details">
                                            <div class="captain-name"><?php echo $team['captain_name']; ?></div>
                                            <div class="captain-label"><i class="fas fa-crown" style="color: #f39c12;"></i> Team Captain</div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="stat-row">
                                        <span class="stat-label">Total Purse:</span>
                                        <span class="stat-value">₹<?php echo number_format($team['total_purse'], 2); ?></span>
                                    </div>
                                    <div class="stat-row">
                                        <span class="stat-label">Spent:</span>
                                        <span class="stat-value">₹<?php echo number_format($team['spent_amount'], 2); ?></span>
                                    </div>
                                    <div class="stat-row">
                                        <span class="stat-label">Remaining:</span>
                                        <span class="stat-value">₹<?php echo number_format($remaining, 2); ?></span>
                                    </div>
                                    <div class="stat-row">
                                        <span class="stat-label">Players Bought:</span>
                                        <span class="stat-value"><?php echo $team['players_bought']; ?>/<?php echo $team['max_players']; ?></span>
                                    </div>
                                    
                                    <div class="purse-bar">
                                        <div class="purse-fill" style="width: <?php echo $spent_percentage; ?>%;"></div>
                                    </div>
                                    
                                    <div style="text-align: center;">
                                        <span class="purse-status <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="team-actions">
                                    <button class="action-btn edit-btn" onclick="editTeam(<?php echo $team['id']; ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="action-btn captain-btn" onclick="editCaptain(<?php echo $team['id']; ?>, '<?php echo $team['team_name']; ?>', <?php echo $team['captain_id'] ?? 'null'; ?>)">
                                        <i class="fas fa-crown"></i> Captain
                                    </button>
                                    <button class="action-btn reset-btn" onclick="resetTeam(<?php echo $team['id']; ?>, '<?php echo $team['team_name']; ?>')">
                                        <i class="fas fa-redo-alt"></i> Reset
                                    </button>
                                    <button class="action-btn delete-btn" onclick="deleteTeam(<?php echo $team['id']; ?>, '<?php echo $team['team_name']; ?>')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="grid-column: 1/-1; text-align: center; padding: 50px; background: white; border-radius: 15px;">
                            <i class="fas fa-users-slash" style="font-size: 60px; color: #bdc3c7;"></i>
                            <h3 style="margin: 20px 0; color: #7f8c8d;">No Teams Found</h3>
                            <p>Add your first team using the form above.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Team Purchases Summary -->
                <div style="margin-top: 40px;">
                    <h2 style="color: #2c3e50; margin-bottom: 20px;">Recent Purchases</h2>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Team</th>
                                    <th>Player</th>
                                    <th>Purchase Price</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $purchases = $conn->query("
                                    SELECT p.player_name, p.registration_no, t.team_name, t.team_color, t.logo_url,
                                           ap.purchase_price, ap.purchase_date
                                    FROM auction_purchases ap
                                    JOIN players p ON ap.player_id = p.id
                                    JOIN teams t ON ap.team_id = t.id
                                    ORDER BY ap.purchase_date DESC
                                    LIMIT 10
                                ");
                                
                                if($purchases && $purchases->num_rows > 0):
                                    while($purchase = $purchases->fetch_assoc()):
                                        $logo_display = '';
                                        if (!empty($purchase['logo_url'])) {
                                            $logo_filename = basename($purchase['logo_url']);
                                            $logo_display = 'uploads/team_logos/' . $logo_filename;
                                        }
                                ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <?php if(!empty($logo_display)): ?>
                                                    <img src="../<?php echo $logo_display; ?>" 
                                                         style="width: 30px; height: 30px; border-radius: 50%; object-fit: cover;">
                                                <?php endif; ?>
                                                <span style="color: <?php echo $purchase['team_color']; ?>; font-weight: bold;">
                                                    <?php echo $purchase['team_name']; ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td><?php echo $purchase['player_name']; ?> (<?php echo $purchase['registration_no']; ?>)</td>
                                        <td>₹<?php echo number_format($purchase['purchase_price'], 2); ?></td>
                                        <td><?php echo date('d M Y h:i A', strtotime($purchase['purchase_date'])); ?></td>
                                    </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; padding: 30px;">
                                            <i class="fas fa-shopping-cart" style="font-size: 40px; color: #bdc3c7; margin-bottom: 10px;"></i>
                                            <br>No purchases yet
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Team Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 20px;">Edit Team</h3>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_team">
                <input type="hidden" name="team_id" id="edit_team_id">
                <input type="hidden" name="existing_logo" id="existing_logo">
                
                <div class="logo-preview" id="logo_preview">
                    <i class="fas fa-shield-alt"></i>
                </div>
                
                <div class="form-group">
                    <label>Team Name</label>
                    <input type="text" name="team_name" id="edit_team_name" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Short Code</label>
                    <input type="text" name="short_code" id="edit_short_code" required class="form-control" maxlength="5">
                </div>
                
                <div class="form-group">
                    <label>Team Color</label>
                    <input type="color" name="team_color" id="edit_team_color" class="form-control" style="height: 42px;">
                </div>
                
                <div class="form-group">
                    <label>Total Purse (₹)</label>
                    <input type="number" name="total_purse" id="edit_total_purse" required step="100000" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Max Players</label>
                    <input type="number" name="max_players" id="edit_max_players" required min="1" max="25" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Captain</label>
                    <select name="captain_id" id="edit_captain_id" class="captain-select">
                        <option value="">-- No Captain --</option>
                        <?php 
                        $players->data_seek(0);
                        while($player = $players->fetch_assoc()) {
                            echo "<option value='" . $player['id'] . "'>" . $player['player_name'] . " (" . $player['registration_no'] . ")</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Team Logo</label>
                    <div class="file-input-wrapper">
                        <input type="file" name="team_logo" id="edit_team_logo" accept="image/*">
                        <label for="edit_team_logo"><i class="fas fa-upload"></i> Change Logo</label>
                        <span class="file-name" id="edit_file_name">No file chosen</span>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">Update Team</button>
                    <button type="button" class="btn btn-danger" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Captain Modal -->
    <div id="captainModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 20px; color: #9b59b6;"><i class="fas fa-crown"></i> Assign Team Captain</h3>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_captain">
                <input type="hidden" name="team_id" id="captain_team_id">
                
                <p style="margin-bottom: 15px;">Select captain for <strong id="captain_team_name"></strong></p>
                
                <select name="captain_id" id="captain_select" class="captain-select">
                    <option value="">-- Remove Captain --</option>
                    <?php 
                    $players->data_seek(0);
                    while($player = $players->fetch_assoc()) {
                        echo "<option value='" . $player['id'] . "'>" . $player['player_name'] . " (" . $player['registration_no'] . ")</option>";
                    }
                    ?>
                </select>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="submit" class="btn" style="background: #9b59b6; color: white;">Update Captain</button>
                    <button type="button" class="btn btn-danger" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset Team Modal -->
    <div id="resetModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 20px; color: #f39c12;">Reset Team Stats</h3>
            <p style="margin-bottom: 20px;">Are you sure you want to reset stats for <span id="reset_team_name"></span>?</p>
            <p style="color: #e74c3c; margin-bottom: 20px;">This will clear all purchases and remove captain for this team!</p>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="reset_team">
                <input type="hidden" name="team_id" id="reset_team_id">
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="submit" class="btn btn-warning">Yes, Reset</button>
                    <button type="button" class="btn btn-primary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Team Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 20px; color: #e74c3c;">Delete Team</h3>
            <p style="margin-bottom: 20px;">Are you sure you want to delete <span id="delete_team_name"></span>?</p>
            <p style="color: #e74c3c; margin-bottom: 20px;">This action cannot be undone!</p>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete_team">
                <input type="hidden" name="team_id" id="delete_team_id">
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="submit" class="btn btn-danger">Yes, Delete</button>
                    <button type="button" class="btn btn-primary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function editTeam(id) {
        fetch('get_team_data.php?id=' + id)
            .then(response => response.json())
            .then(data => {
                document.getElementById('edit_team_id').value = data.id;
                document.getElementById('edit_team_name').value = data.team_name;
                document.getElementById('edit_short_code').value = data.team_short_code;
                document.getElementById('edit_team_color').value = data.team_color;
                document.getElementById('edit_total_purse').value = data.total_purse;
                document.getElementById('edit_max_players').value = data.max_players;
                document.getElementById('edit_captain_id').value = data.captain_id || '';
                document.getElementById('existing_logo').value = data.logo_url || '';
                
                // Update logo preview
                const preview = document.getElementById('logo_preview');
                if(data.logo_url) {
                    const filename = data.logo_url.split('/').pop();
                    preview.innerHTML = '<img src="../uploads/team_logos/' + filename + '" style="width: 100%; height: 100%; object-fit: cover;">';
                } else {
                    preview.innerHTML = '<i class="fas fa-shield-alt"></i>';
                }
                
                document.getElementById('editModal').classList.add('active');
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading team data');
            });
    }
    
    function editCaptain(id, name, currentCaptain) {
        document.getElementById('captain_team_id').value = id;
        document.getElementById('captain_team_name').textContent = name;
        document.getElementById('captain_select').value = currentCaptain || '';
        document.getElementById('captainModal').classList.add('active');
    }
    
    function resetTeam(id, name) {
        document.getElementById('reset_team_id').value = id;
        document.getElementById('reset_team_name').textContent = name;
        document.getElementById('resetModal').classList.add('active');
    }
    
    function deleteTeam(id, name) {
        document.getElementById('delete_team_id').value = id;
        document.getElementById('delete_team_name').textContent = name;
        document.getElementById('deleteModal').classList.add('active');
    }
    
    function closeModal() {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.classList.remove('active');
        });
    }
    
    // File input display
    document.getElementById('team_logo')?.addEventListener('change', function(e) {
        const fileName = e.target.files[0]?.name || 'No file chosen';
        document.getElementById('file_name').textContent = fileName;
    });
    
    document.getElementById('edit_team_logo')?.addEventListener('change', function(e) {
        const fileName = e.target.files[0]?.name || 'No file chosen';
        document.getElementById('edit_file_name').textContent = fileName;
        
        if (e.target.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('logo_preview').innerHTML = 
                    '<img src="' + e.target.result + '" style="width: 100%; height: 100%; object-fit: cover;">';
            }
            reader.readAsDataURL(e.target.files[0]);
        }
    });
    
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            closeModal();
        }
    }
    
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