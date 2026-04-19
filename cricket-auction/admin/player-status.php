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

// Handle status toggle
if(isset($_GET['toggle']) && isset($_GET['player_id'])) {
    $player_id = intval($_GET['player_id']);
    $new_status = $_GET['toggle'];
    
    if($new_status == 'sold' || $new_status == 'unsold') {
        // Get player and team info
        $player_query = $conn->query("SELECT * FROM auction_status WHERE player_id = $player_id");
        $player_data = $player_query->fetch_assoc();
        
        if($new_status == 'sold') {
            // If marking as sold, we need team info
            if(isset($_GET['team_id']) && $_GET['team_id'] > 0) {
                $team_id = intval($_GET['team_id']);
                $sold_price = floatval($_GET['price'] ?? 0);
                
                // Update team stats
                $conn->query("UPDATE teams SET 
                              spent_amount = spent_amount + $sold_price, 
                              players_bought = players_bought + 1 
                              WHERE id = $team_id");
                
                // Record purchase
                $conn->query("INSERT INTO auction_purchases (player_id, team_id, purchase_price) 
                              VALUES ($player_id, $team_id, $sold_price)");
                
                $conn->query("UPDATE auction_status SET 
                              status = 'sold', 
                              sold_price = $sold_price,
                              team_id = $team_id
                              WHERE player_id = $player_id");
                
                $message = "Player marked as SOLD successfully!";
            } else {
                $error = "Team ID required for sold status";
            }
        } else {
            // Mark as unsold - remove from team purchases
            if($player_data && $player_data['team_id']) {
                $team_id = $player_data['team_id'];
                $sold_price = $player_data['sold_price'];
                
                // Revert team stats
                $conn->query("UPDATE teams SET 
                              spent_amount = spent_amount - $sold_price, 
                              players_bought = players_bought - 1 
                              WHERE id = $team_id");
                
                // Delete purchase record
                $conn->query("DELETE FROM auction_purchases WHERE player_id = $player_id");
            }
            
            $conn->query("UPDATE auction_status SET 
                          status = 'unsold', 
                          sold_price = NULL,
                          team_id = NULL,
                          sold_to = NULL
                          WHERE player_id = $player_id");
            
            $message = "Player marked as UNSOLD successfully!";
        }
    }
    header("Location: player-status.php?msg=" . urlencode($message));
    exit();
}

// Handle price update
if(isset($_POST['action']) && $_POST['action'] == 'update_price') {
    $player_id = intval($_POST['player_id']);
    $new_price = floatval($_POST['new_price']);
    $team_id = intval($_POST['team_id']);
    
    // Get current sold price
    $result = $conn->query("SELECT sold_price FROM auction_status WHERE player_id = $player_id");
    $current = $result->fetch_assoc();
    $old_price = $current['sold_price'];
    
    // Update team stats (adjust spent amount)
    $price_difference = $new_price - $old_price;
    $conn->query("UPDATE teams SET spent_amount = spent_amount + $price_difference WHERE id = $team_id");
    
    // Update purchase record
    $conn->query("UPDATE auction_purchases SET purchase_price = $new_price WHERE player_id = $player_id");
    
    // Update auction status
    $conn->query("UPDATE auction_status SET sold_price = $new_price WHERE player_id = $player_id");
    
    $message = "Sold price updated successfully!";
    header("Location: player-status.php?msg=" . urlencode($message));
    exit();
}

// Get all players with auction status
$query = "SELECT p.*, c.category_name, 
          COALESCE(a.status, 'pending') as auction_status,
          a.sold_price, a.team_id, a.bid_history,
          t.team_name, t.team_color, t.logo_url
          FROM players p 
          JOIN categories c ON p.category_id = c.id 
          LEFT JOIN auction_status a ON p.id = a.player_id
          LEFT JOIN teams t ON a.team_id = t.id
          WHERE a.status IN ('sold', 'unsold')
          ORDER BY 
          CASE a.status 
            WHEN 'sold' THEN 1 
            WHEN 'unsold' THEN 2 
            ELSE 3 
          END,
          a.updated_at DESC";

$players = $conn->query($query);

// Get all teams for dropdown
$teams = $conn->query("SELECT * FROM teams ORDER BY team_name");

// Calculate price increase percentage
function getPriceIncrease($start, $end) {
    if($start == 0) return 0;
    return round((($end - $start) / $start) * 100, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Player Status Management - Cricket Auction</title>
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
        }

        .header h1 {
            color: #2c3e50;
            font-size: 32px;
            margin-bottom: 5px;
        }

        .header p {
            color: #7f8c8d;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-icon.sold {
            background: #27ae60;
            color: white;
        }

        .stat-icon.unsold {
            background: #e74c3c;
            color: white;
        }

        .stat-icon.value {
            background: #f39c12;
            color: white;
        }

        .stat-info h3 {
            color: #7f8c8d;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .stat-info .value {
            font-size: 28px;
            font-weight: bold;
            color: #2c3e50;
        }

        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-select {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            min-width: 200px;
        }

        .search-input {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            width: 250px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-warning {
            background: #f39c12;
            color: white;
        }

        .players-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 20px;
        }

        .player-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            border-left: 5px solid transparent;
        }

        .player-card.sold {
            border-left-color: #27ae60;
        }

        .player-card.unsold {
            border-left-color: #e74c3c;
        }

        .player-card:hover {
            transform: translateY(-5px);
        }

        .player-header {
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            background: #f8f9fa;
        }

        .player-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
            box-shadow: 0 5px 10px rgba(0,0,0,0.1);
        }

        .default-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .player-info {
            flex: 1;
        }

        .player-name {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 3px;
        }

        .player-reg {
            font-size: 12px;
            color: #7f8c8d;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }

        .status-sold {
            background: #27ae60;
            color: white;
        }

        .status-unsold {
            background: #e74c3c;
            color: white;
        }

        .player-details {
            padding: 15px 20px;
            border-bottom: 1px solid #ecf0f1;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .detail-label {
            color: #7f8c8d;
        }

        .detail-value {
            font-weight: 600;
            color: #2c3e50;
        }

        .price-increase {
            color: #27ae60;
            font-weight: bold;
        }

        .team-info {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .team-logo {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
        }

        .player-actions {
            padding: 15px 20px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .action-btn.sold {
            background: #27ae60;
            color: white;
        }

        .action-btn.unsold {
            background: #e74c3c;
            color: white;
        }

        .action-btn.edit-price {
            background: #f39c12;
            color: white;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(0,0,0,0.1);
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
            border-radius: 15px;
            padding: 30px;
            max-width: 400px;
            width: 90%;
        }

        .modal-content h3 {
            margin-bottom: 20px;
            color: #2c3e50;
        }

        .modal-content select,
        .modal-content input {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
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

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }

        .page-btn {
            padding: 8px 15px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 5px;
            cursor: pointer;
        }

        .page-btn.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
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
                <li><a href="manage-teams.php"><i class="fas fa-users-cog"></i> Manage Teams</a></li>
                <li ><a href="auction-control_1.php"><i class="fas fa-gavel"></i> Live Auction (Simple)</a></li>
                <li ><a href="auction-control.php"><i class="fas fa-gavel"></i> Live Auction (Advanced)</a></li>
                <li class="active"><a href="player-status.php"><i class="fas fa-sync-alt"></i> Player Status</a></li>
                <li><a href="team-players.php"><i class="fas fa-users-between-lines"></i> Team Players</a></li>
                <li><a href="highest-bids.php"><i class="fas fa-chart-line"></i> Highest Bids</a></li>
                <li style="border-top: 1px solid #34495e; margin-top: 20px;">
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <div class="header-icon">
                    <i class="fas fa-sync-alt"></i>
                </div>
                <div>
                    <h1>Player Status Management</h1>
                    <p>Toggle players between Sold and Unsold status</p>
                </div>
            </div>

            <?php if(isset($_GET['msg'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['msg']); ?>
                </div>
            <?php endif; ?>

            <?php if($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <?php
            $sold_count = $conn->query("SELECT COUNT(*) as count FROM auction_status WHERE status = 'sold'")->fetch_assoc()['count'];
            $unsold_count = $conn->query("SELECT COUNT(*) as count FROM auction_status WHERE status = 'unsold'")->fetch_assoc()['count'];
            $total_value = $conn->query("SELECT SUM(sold_price) as total FROM auction_status WHERE status = 'sold'")->fetch_assoc()['total'];
            ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon sold">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Sold Players</h3>
                        <div class="value"><?php echo $sold_count; ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon unsold">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Unsold Players</h3>
                        <div class="value"><?php echo $unsold_count; ?></div>
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

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-group">
                    <input type="text" id="searchInput" class="search-input" placeholder="Search player...">
                    <select id="statusFilter" class="filter-select">
                        <option value="all">All Status</option>
                        <option value="sold">Sold</option>
                        <option value="unsold">Unsold</option>
                    </select>
                    <select id="categoryFilter" class="filter-select">
                        <option value="all">All Categories</option>
                        <?php
                        $cats = $conn->query("SELECT * FROM categories");
                        while($cat = $cats->fetch_assoc()) {
                            echo "<option value='" . $cat['id'] . "'>" . $cat['category_name'] . "</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <!-- Players Grid -->
            <div class="players-grid" id="playersGrid">
                <?php 
                if($players && $players->num_rows > 0):
                    while($player = $players->fetch_assoc()): 
                        $increase = getPriceIncrease($player['base_price'], $player['sold_price']);
                ?>
                <div class="player-card <?php echo $player['auction_status']; ?>" 
                     data-name="<?php echo strtolower($player['player_name']); ?>"
                     data-status="<?php echo $player['auction_status']; ?>"
                     data-category="<?php echo $player['category_id']; ?>">
                    <div class="player-header">
                        <?php if(!empty($player['player_image'])): ?>
                            <img src="../<?php echo $player['player_image']; ?>" class="player-avatar">
                        <?php else: ?>
                            <div class="default-avatar">
                                <i class="fas fa-user-circle"></i>
                            </div>
                        <?php endif; ?>
                        <div class="player-info">
                            <div class="player-name"><?php echo $player['player_name']; ?></div>
                            <div class="player-reg"><?php echo $player['registration_no']; ?></div>
                        </div>
                        <span class="status-badge status-<?php echo $player['auction_status']; ?>">
                            <?php echo strtoupper($player['auction_status']); ?>
                        </span>
                    </div>
                    
                    <div class="player-details">
                        <div class="detail-row">
                            <span class="detail-label">Age/Role:</span>
                            <span class="detail-value"><?php echo $player['age']; ?> yrs | <?php echo $player['playing_role']; ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Category:</span>
                            <span class="detail-value"><?php echo $player['category_name']; ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Base Price:</span>
                            <span class="detail-value">₹<?php echo number_format($player['base_price'], 2); ?></span>
                        </div>
                        <?php if($player['auction_status'] == 'sold'): ?>
                        <div class="detail-row">
                            <span class="detail-label">Sold Price:</span>
                            <span class="detail-value price-increase" id="price-<?php echo $player['id']; ?>">
                                ₹<?php echo number_format($player['sold_price'], 2); ?>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Increase:</span>
                            <span class="detail-value price-increase">+<?php echo $increase; ?>%</span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if($player['team_name']): ?>
                        <div class="team-info">
                            <?php if(!empty($player['logo_url'])): ?>
                                <img src="../<?php echo $player['logo_url']; ?>" class="team-logo">
                            <?php endif; ?>
                            <span style="color: <?php echo $player['team_color']; ?>; font-weight: bold;">
                                <?php echo $player['team_name']; ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="player-actions">
                        <?php if($player['auction_status'] == 'sold'): ?>
                            <button class="action-btn edit-price" onclick="showEditPriceModal(<?php echo $player['id']; ?>, '<?php echo $player['player_name']; ?>', <?php echo $player['sold_price']; ?>, <?php echo $player['team_id']; ?>)">
                                <i class="fas fa-edit"></i> Edit Price
                            </button>
                            <a href="?toggle=unsold&player_id=<?php echo $player['id']; ?>" 
                               class="action-btn unsold"
                               onclick="return confirm('Mark this player as UNSOLD? This will revert team stats.')">
                                <i class="fas fa-times-circle"></i> Unsold
                            </a>
                        <?php else: ?>
                            <button class="action-btn sold" onclick="showSoldModal(<?php echo $player['id']; ?>, '<?php echo $player['player_name']; ?>', <?php echo $player['base_price']; ?>)">
                                <i class="fas fa-check-circle"></i> Mark Sold
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php 
                    endwhile;
                else: 
                ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 50px;">
                    <i class="fas fa-users-slash" style="font-size: 60px; color: #bdc3c7;"></i>
                    <h3 style="margin: 20px 0; color: #7f8c8d;">No processed players found</h3>
                </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <div class="pagination" id="pagination"></div>
        </div>
    </div>

    <!-- Sold Modal -->
    <div id="soldModal" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-check-circle" style="color: #27ae60;"></i> Mark Player as Sold</h3>
            <form method="GET" action="">
                <input type="hidden" name="toggle" value="sold">
                <input type="hidden" name="player_id" id="modal_player_id">
                
                <div style="margin-bottom: 15px;">
                    <strong id="modal_player_name"></strong>
                </div>
                
                <select name="team_id" id="modal_team_id" required>
                    <option value="">Select Team</option>
                    <?php 
                    $teams = $conn->query("SELECT * FROM teams ORDER BY team_name");
                    while($team = $teams->fetch_assoc()):
                    ?>
                    <option value="<?php echo $team['id']; ?>" data-remaining="<?php echo $team['total_purse'] - $team['spent_amount']; ?>">
                        <?php echo $team['team_name']; ?> (₹<?php echo number_format($team['total_purse'] - $team['spent_amount']); ?> left)
                    </option>
                    <?php endwhile; ?>
                </select>
                
                <input type="number" name="price" id="modal_price" placeholder="Sold Price (₹)" min="1000" step="1000" required>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="submit" class="btn btn-success">Confirm Sold</button>
                    <button type="button" class="btn btn-danger" onclick="closeModal('soldModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Price Modal -->
    <div id="editPriceModal" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-edit" style="color: #f39c12;"></i> Edit Sold Price</h3>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_price">
                <input type="hidden" name="player_id" id="edit_player_id">
                <input type="hidden" name="team_id" id="edit_team_id">
                
                <div style="margin-bottom: 15px;">
                    <strong id="edit_player_name"></strong>
                </div>
                
                <div style="background: #f8f9fa; padding: 10px; border-radius: 8px; margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span>Current Price:</span>
                        <span id="current_price_display" style="font-weight: bold;">₹0</span>
                    </div>
                </div>
                
                <input type="number" name="new_price" id="edit_price" placeholder="New Sold Price (₹)" min="1000" step="1000" required>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="submit" class="btn btn-warning">Update Price</button>
                    <button type="button" class="btn btn-danger" onclick="closeModal('editPriceModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Modal functions
    function showSoldModal(id, name, basePrice) {
        document.getElementById('modal_player_id').value = id;
        document.getElementById('modal_player_name').textContent = name;
        document.getElementById('modal_price').placeholder = `Min: ₹${basePrice.toLocaleString()}`;
        document.getElementById('modal_price').min = basePrice;
        document.getElementById('soldModal').classList.add('active');
    }

    function showEditPriceModal(id, name, currentPrice, teamId) {
        document.getElementById('edit_player_id').value = id;
        document.getElementById('edit_team_id').value = teamId;
        document.getElementById('edit_player_name').textContent = name;
        document.getElementById('current_price_display').textContent = '₹' + currentPrice.toLocaleString();
        document.getElementById('edit_price').value = currentPrice;
        document.getElementById('edit_price').min = 1000;
        document.getElementById('editPriceModal').classList.add('active');
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('active');
    }

    // Filter functionality
    document.getElementById('searchInput').addEventListener('keyup', filterPlayers);
    document.getElementById('statusFilter').addEventListener('change', filterPlayers);
    document.getElementById('categoryFilter').addEventListener('change', filterPlayers);

    function filterPlayers() {
        const search = document.getElementById('searchInput').value.toLowerCase();
        const status = document.getElementById('statusFilter').value;
        const category = document.getElementById('categoryFilter').value;
        
        const cards = document.querySelectorAll('.player-card');
        let visibleCount = 0;
        
        cards.forEach(card => {
            const name = card.dataset.name;
            const cardStatus = card.dataset.status;
            const cardCategory = card.dataset.category;
            
            const matchesSearch = name.includes(search);
            const matchesStatus = status === 'all' || cardStatus === status;
            const matchesCategory = category === 'all' || cardCategory == category;
            
            if(matchesSearch && matchesStatus && matchesCategory) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.classList.remove('active');
        }
    }

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