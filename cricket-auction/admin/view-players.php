<?php
session_start();
include '../includes/db-config.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Function to reindex registration numbers (ensures NO GAPS)
function reindexRegistrationNumbers($conn) {
    // Get all players ordered by ID
    $players = $conn->query("SELECT id FROM players ORDER BY id ASC");
    $new_reg_no = 101;
    $updated_count = 0;
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        while($player = $players->fetch_assoc()) {
            $new_reg = 'PLR' . $new_reg_no;
            $conn->query("UPDATE players SET registration_no = '$new_reg' WHERE id = " . $player['id']);
            $new_reg_no++;
            $updated_count++;
        }
        $conn->commit();
        return $updated_count;
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

// Handle delete request - AUTOMATIC REINDEX AFTER DELETE
if(isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Get player image to delete
    $result = $conn->query("SELECT player_image FROM players WHERE id = $id");
    $player = $result->fetch_assoc();
    if ($player && $player['player_image'] && file_exists('../' . $player['player_image'])) {
        unlink('../' . $player['player_image']);
    }
    
    // Delete the player
    $conn->query("DELETE FROM players WHERE id = $id");
    
    // AUTOMATICALLY REINDEX AFTER DELETION (NO GAPS)
    $count = reindexRegistrationNumbers($conn);
    
    header("Location: view-players.php?msg=deleted&reindexed=1&count=" . $count);
    exit();
}

// Handle manual reindex request (optional, but kept for safety)
if(isset($_GET['reindex']) && $_GET['reindex'] == 'now') {
    $count = reindexRegistrationNumbers($conn);
    if($count !== false) {
        header("Location: view-players.php?msg=reindexed&count=" . $count);
    } else {
        header("Location: view-players.php?error=reindex_failed");
    }
    exit();
}

// Search and filter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';

$where = "WHERE 1=1";
if($search) {
    $search = $conn->real_escape_string($search);
    $where .= " AND (p.player_name LIKE '%$search%' OR p.registration_no LIKE '%$search%')";
}
if($category_filter) {
    $category_filter = intval($category_filter);
    $where .= " AND p.category_id = $category_filter";
}

// Order by registration number numerically to see the sequence
$query = "SELECT p.*, c.category_name 
          FROM players p 
          JOIN categories c ON p.category_id = c.id 
          $where 
          ORDER BY CAST(SUBSTRING(p.registration_no, 4) AS UNSIGNED) ASC";
$players = $conn->query($query);

// Fetch categories for filter
$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");

// Get counts for stats
$total_players = $conn->query("SELECT COUNT(*) as count FROM players")->fetch_assoc()['count'];
$under_15 = $conn->query("SELECT COUNT(*) as count FROM players p JOIN categories c ON p.category_id = c.id WHERE c.category_name = 'Under 15'")->fetch_assoc()['count'];
$above_16 = $conn->query("SELECT COUNT(*) as count FROM players p JOIN categories c ON p.category_id = c.id WHERE c.category_name = 'Above 16'")->fetch_assoc()['count'];
$total_value = $conn->query("SELECT SUM(base_price) as total FROM players")->fetch_assoc()['total'];

// Check for gaps in registration numbers (for information only)
$check_gaps = $conn->query("SELECT registration_no FROM players ORDER BY CAST(SUBSTRING(registration_no, 4) AS UNSIGNED) ASC");
$expected = 101;
$has_gaps = false;
$gap_list = [];

while($row = $check_gaps->fetch_assoc()) {
    $current = intval(substr($row['registration_no'], 3));
    if($current != $expected) {
        $has_gaps = true;
        $gap_list[] = $expected;
    }
    $expected = $current + 1;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Players - Cricket Auction</title>
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
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .header-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 30px;
            box-shadow: 0 10px 20px rgba(102,126,234,0.3);
        }

        .header h1 {
            color: #2c3e50;
            font-size: 32px;
            margin-bottom: 5px;
            font-weight: 700;
        }

        .header p {
            color: #7f8c8d;
            font-size: 15px;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }

        .stat-icon.total {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .stat-icon.under15 {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
        }

        .stat-icon.above16 {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }

        .stat-icon.value {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
        }

        .stat-info h3 {
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-info .value {
            font-size: 32px;
            font-weight: 700;
            color: #2c3e50;
            line-height: 1.2;
        }

        /* Search Section */
        .search-section {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .filter-item {
            position: relative;
        }

        .filter-item label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-item input,
        .filter-item select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s;
            background: white;
        }

        .filter-item input:focus,
        .filter-item select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(102,126,234,0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102,126,234,0.5);
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background: #229954;
        }

        .btn-info {
            background: #3498db;
            color: white;
        }

        .btn-info:hover {
            background: #2980b9;
        }

        /* Table */
        .table-container {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table thead th {
            background: #f8f9fa;
            padding: 15px;
            font-size: 13px;
            font-weight: 600;
            color: #2c3e50;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e0e0e0;
        }

        table tbody td {
            padding: 15px;
            border-bottom: 1px solid #ecf0f1;
            color: #2c3e50;
        }

        table tbody tr:hover {
            background: #f8f9fa;
        }

        .player-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .player-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #667eea;
        }

        .default-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            border: 3px solid rgba(255,255,255,0.5);
        }

        .badge {
            padding: 6px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-child {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
        }

        .badge-men {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }

        .role-icon {
            margin-right: 5px;
            color: #667eea;
        }

        .price {
            font-weight: 700;
            color: #27ae60;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            width: 35px;
            height: 35px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }

        .action-btn.edit {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        }

        .action-btn.delete {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 80px;
            color: #bdc3c7;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #2c3e50;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
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

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .reindex-bar {
            background: white;
            border-radius: 15px;
            padding: 15px 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .reindex-bar .info {
            color: #2c3e50;
            font-size: 14px;
        }

        .reindex-bar .info i {
            color: #27ae60;
            margin-right: 8px;
        }

        .reindex-badge {
            background: #27ae60;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .reindex-badge i {
            font-size: 12px;
        }

        .sequence-info {
            background: #e8f0fe;
            border-radius: 12px;
            padding: 10px 15px;
            margin-bottom: 15px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sequence-info i {
            color: #3498db;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .admin-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                position: relative;
                height: auto;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            table {
                font-size: 13px;
            }
            
            .player-cell {
                flex-direction: column;
                text-align: center;
            }
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
            <!-- Header -->
            <div class="header">
                <div class="header-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <h1>View Players</h1>
                    <p>Manage and view all registered players</p>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon total">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Players</h3>
                        <div class="value"><?php echo $total_players; ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon under15">
                        <i class="fas fa-child"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Under 15</h3>
                        <div class="value"><?php echo $under_15; ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon above16">
                        <i class="fas fa-male"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Above 16</h3>
                        <div class="value"><?php echo $above_16; ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon value">
                        <i class="fas fa-rupee-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Value</h3>
                        <div class="value">₹<?php echo number_format($total_value ?? 0, 2); ?></div>
                    </div>
                </div>
            </div>

            <?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> 
                    Player deleted successfully! 
                    <?php if(isset($_GET['reindexed']) && $_GET['reindexed'] == 1): ?>
                        Registration numbers have been automatically rearranged to remove gaps.
                        <strong><?php echo $_GET['count']; ?> players updated.</strong>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if(isset($_GET['msg']) && $_GET['msg'] == 'reindexed'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> 
                    Registration numbers rearranged successfully! 
                    <strong><?php echo $_GET['count']; ?> players updated.</strong>
                    All numbers are now sequential from PLR101 to PLR<?php echo 100 + $total_players; ?>.
                </div>
            <?php endif; ?>

            <!-- Sequence Information -->
            <div class="sequence-info">
                <i class="fas fa-info-circle"></i>
                <span>
                    <strong>Registration Number Sequence:</strong> 
                    Players are numbered sequentially from PLR101 to PLR<?php echo 100 + $total_players; ?>.
                    When you delete a player, numbers are automatically rearranged to maintain sequential order with NO GAPS.
                </span>
                <span class="reindex-badge">
                    <i class="fas fa-sync-alt"></i> Auto-Reindex Active
                </span>
            </div>

            <!-- Reindex Bar (Optional Manual Reindex) -->
            <div class="reindex-bar">
                <div class="info">
                    <i class="fas fa-check-circle" style="color: #27ae60;"></i>
                    <strong>Current Range:</strong> PLR101 to PLR<?php echo 100 + $total_players; ?> 
                    (<?php echo $total_players; ?> players)
                </div>
                <a href="?reindex=now" class="btn btn-info" onclick="return confirm('Manually rearrange all registration numbers?\n\nThis will reset all numbers to PLR101, PLR102, etc. in order of player ID.')">
                    <i class="fas fa-sync-alt"></i> Manual Reindex
                </a>
            </div>

            <!-- Search Section -->
            <div class="search-section">
                <form method="GET" action="">
                    <div class="filter-grid">
                        <div class="filter-item">
                            <label><i class="fas fa-search"></i> Search</label>
                            <input type="text" name="search" placeholder="Search by name or registration no..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="filter-item">
                            <label><i class="fas fa-filter"></i> Category</label>
                            <select name="category">
                                <option value="">All Categories</option>
                                <?php 
                                $categories = $conn->query("SELECT * FROM categories ORDER BY category_name");
                                while($category = $categories->fetch_assoc()) {
                                    $selected = ($category_filter == $category['id']) ? 'selected' : '';
                                    echo "<option value='" . $category['id'] . "' $selected>" . 
                                         $category['category_name'] . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                            <a href="view-players.php" class="btn btn-danger">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Players Table -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Player</th>
                            <th>Reg No.</th>
                            <th>Age</th>
                            <th>Role</th>
                            <th>Category</th>
                            <th>Base Price</th>
                            <th>Added On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($players && $players->num_rows > 0): ?>
                            <?php 
                            $expected = 101;
                            $sequence_perfect = true;
                            while($player = $players->fetch_assoc()): 
                                $current_num = intval(substr($player['registration_no'], 3));
                                if($current_num != $expected) {
                                    $sequence_perfect = false;
                                }
                                $expected++;
                            ?>
                                <tr>
                                    <td>
                                        <div class="player-cell">
                                            <?php if(!empty($player['player_image'])): ?>
                                                <img src="../<?php echo $player['player_image']; ?>" 
                                                     class="player-avatar" 
                                                     alt="<?php echo $player['player_name']; ?>">
                                            <?php else: ?>
                                                <div class="default-avatar">
                                                    <i class="fas fa-user-circle"></i>
                                                </div>
                                            <?php endif; ?>
                                            <span style="font-weight: 600;"><?php echo htmlspecialchars($player['player_name']); ?></span>
                                        </div>
                                    </td>
                                    <td><strong><?php echo $player['registration_no']; ?></strong></td>
                                    <td><?php echo $player['age']; ?> years</td>
                                    <td>
                                        <?php
                                        $role_icons = [
                                            'Batsman' => 'fa-baseball-bat-ball',
                                            'Bowler' => 'fa-baseball',
                                            'All-rounder' => 'fa-star',
                                            'Wicket-keeper' => 'fa-gloves',
                                            'Wicket-keeper Batsman' => 'fa-mask'
                                        ];
                                        $icon = $role_icons[$player['playing_role']] ?? 'fa-user';
                                        ?>
                                        <i class="fas <?php echo $icon; ?> role-icon"></i>
                                        <?php echo $player['playing_role']; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo ($player['category_name'] == 'Under 15') ? 'badge-child' : 'badge-men'; ?>">
                                            <?php echo $player['category_name']; ?>
                                        </span>
                                    </td>
                                    <td class="price">₹<?php echo number_format($player['base_price'], 2); ?></td>
                                    <td><?php echo date('d M Y', strtotime($player['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="edit-player.php?id=<?php echo $player['id']; ?>" class="action-btn edit" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?delete=<?php echo $player['id']; ?>" class="action-btn delete" title="Delete"
                                               onclick="return confirm('Are you sure you want to delete <?php echo addslashes($player['player_name']); ?>?\n\nAfter deletion, registration numbers will be AUTOMATICALLY rearranged to remove gaps and maintain sequential order.')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            
                            <!-- Show sequence status at bottom of table -->
                            <tr style="background: #f8f9fa;">
                                <td colspan="8" style="padding: 10px 15px; text-align: right;">
                                    <?php if($sequence_perfect): ?>
                                        <span style="color: #27ae60;">
                                            <i class="fas fa-check-circle"></i> 
                                            Sequence is perfect: PLR101 to PLR<?php echo 100 + $total_players; ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #e74c3c;">
                                            <i class="fas fa-exclamation-triangle"></i> 
                                            Sequence has gaps. <a href="?reindex=now" style="color: #e74c3c; text-decoration: underline;">Click here to fix</a>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <i class="fas fa-users-slash"></i>
                                        <h3>No Players Found</h3>
                                        <p><?php echo $search ? 'No players match your search criteria.' : 'Get started by adding your first player.'; ?></p>
                                        <a href="add-player.php" class="btn btn-success">
                                            <i class="fas fa-plus-circle"></i> Add New Player
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    // Auto-hide alerts after 5 seconds
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