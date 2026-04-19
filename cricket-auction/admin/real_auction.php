<?php
session_start();
include '../includes/db-config.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Initialize captain session if not set
if (!isset($_SESSION['captain_ids'])) {
    $_SESSION['captain_ids'] = [];
}

// Handle captain selection
if (isset($_POST['save_captains'])) {
    $_SESSION['captain_ids'] = isset($_POST['captains']) ? $_POST['captains'] : [];
    header("Location: auction-control.php");
    exit();
}

// Handle clear captains
if (isset($_GET['clear_captains'])) {
    $_SESSION['captain_ids'] = [];
    header("Location: auction-control.php");
    exit();
}

// Create upload directory for player images if it doesn't exist
$player_upload_dir = '../uploads/players/';
if (!is_dir($player_upload_dir)) {
    mkdir($player_upload_dir, 0777, true);
}

// Create auction status table if it doesn't exist
$create_table = "CREATE TABLE IF NOT EXISTS auction_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    status ENUM('pending', 'sold', 'unsold') DEFAULT 'pending',
    sold_price DECIMAL(10,2) NULL,
    sold_to VARCHAR(100) NULL,
    team_id INT NULL,
    bid_history TEXT NULL,
    auction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL,
    UNIQUE KEY unique_player (player_id)
)";
$conn->query($create_table);

// Create auction purchases table if it doesn't exist
$create_purchases = "CREATE TABLE IF NOT EXISTS auction_purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    team_id INT NOT NULL,
    purchase_price DECIMAL(10,2) NOT NULL,
    purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
)";
$conn->query($create_purchases);

// Handle AJAX requests
if(isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        if($_POST['action'] == 'get_current_player') {
            $category = isset($_POST['category']) ? $_POST['category'] : 'all';
            $random = isset($_POST['random']) ? $_POST['random'] : 'false';
            
            // Build captain exclusion condition
            $captain_condition = "";
            if (!empty($_SESSION['captain_ids'])) {
                $captain_ids = implode(',', array_map('intval', $_SESSION['captain_ids']));
                $captain_condition = " AND p.id NOT IN ($captain_ids)";
            }
            
            $query = "SELECT p.*, c.category_name, c.min_age, c.max_age,
                      COALESCE(a.status, 'pending') as auction_status,
                      a.sold_price, a.sold_to, a.team_id, a.bid_history
                      FROM players p 
                      JOIN categories c ON p.category_id = c.id 
                      LEFT JOIN auction_status a ON p.id = a.player_id
                      WHERE COALESCE(a.status, 'pending') = 'pending'
                      $captain_condition";
            
            if($category != 'all') {
                $query .= " AND c.id = " . intval($category);
            }
            
            if($random == 'true') {
                $query .= " ORDER BY RAND() LIMIT 1";
            } else {
                $query .= " ORDER BY p.id ASC LIMIT 1";
            }
            
            $result = $conn->query($query);
            
            if(!$result) {
                throw new Exception("Database query failed: " . $conn->error);
            }
            
            if($result && $result->num_rows > 0) {
                $player = $result->fetch_assoc();
                
                // Initialize bid tracking
                $player['current_bid'] = floatval($player['base_price']);
                $player['current_team'] = null;
                $player['current_team_id'] = null;
                $player['bid_history'] = [];
                
                if(!empty($player['bid_history'])) {
                    $history = json_decode($player['bid_history'], true);
                    if(is_array($history) && !empty($history)) {
                        $player['bid_history'] = $history;
                        $last_bid = end($history);
                        $player['current_bid'] = floatval($last_bid['bid'] ?? $player['base_price']);
                        $player['current_team'] = $last_bid['team'] ?? null;
                        $player['current_team_id'] = $last_bid['team_id'] ?? null;
                    }
                }
                
                // Set player image URL
                $player_image = '';
                if (!empty($player['player_image'])) {
                    $player_image = 'uploads/players/' . basename($player['player_image']);
                }
                
                $response = [
                    'success' => true,
                    'player' => [
                        'id' => intval($player['id']),
                        'registration_no' => $player['registration_no'],
                        'player_name' => $player['player_name'],
                        'age' => intval($player['age']),
                        'playing_role' => $player['playing_role'],
                        'base_price' => floatval($player['base_price']),
                        'category_id' => intval($player['category_id']),
                        'category_name' => $player['category_name'],
                        'auction_status' => $player['auction_status'] ?? 'pending',
                        'current_bid' => $player['current_bid'],
                        'current_team' => $player['current_team'],
                        'current_team_id' => $player['current_team_id'],
                        'bid_history' => $player['bid_history'],
                        'player_image' => $player_image
                    ]
                ];
                
                echo json_encode($response);
                exit();
            } else {
                echo json_encode([
                    'success' => false, 
                    'message' => 'No more players in this category'
                ]);
                exit();
            }
        }
        
        // Replace your existing place_bid section with this:
if($_POST['action'] == 'place_bid') {
    $player_id = intval($_POST['player_id']);
    $team_name = $conn->real_escape_string($_POST['team']);
    $team_id = intval($_POST['team_id']);
    $bid_amount = floatval($_POST['bid_amount']);
    $increment = floatval($_POST['increment']);
    
    // Check team's current player count before allowing bid
    $team_check = $conn->query("SELECT players_bought, max_players FROM teams WHERE id = $team_id");
    $team_data = $team_check->fetch_assoc();
    
    if ($team_data['players_bought'] >= $team_data['max_players']) {
        echo json_encode([
            'success' => false,
            'message' => 'This team has already reached its maximum player limit!'
        ]);
        exit();
    }
    
    // Get current bid history
    $result = $conn->query("SELECT * FROM auction_status WHERE player_id = $player_id");
    $player = $result->fetch_assoc();
    
    $bid_history = [];
    if($player && $player['bid_history']) {
        $bid_history = json_decode($player['bid_history'], true);
        if(!is_array($bid_history)) {
            $bid_history = [];
        }
    }
    
    // Add new bid
    $bid_history[] = [
        'team' => $team_name,
        'team_id' => $team_id,
        'bid' => $bid_amount,
        'increment' => $increment,
        'time' => date('Y-m-d H:i:s')
    ];
    
    $bid_history_json = $conn->real_escape_string(json_encode($bid_history));
    
    if($player) {
        $query = "UPDATE auction_status SET bid_history = '$bid_history_json' WHERE player_id = $player_id";
    } else {
        $query = "INSERT INTO auction_status (player_id, status, bid_history) VALUES ($player_id, 'pending', '$bid_history_json')";
    }
    
    if($conn->query($query)) {
        echo json_encode([
            'success' => true,
            'message' => 'Bid placed successfully',
            'current_bid' => $bid_amount,
            'current_team' => $team_name,
            'current_team_id' => $team_id,
            'bid_history' => $bid_history
        ]);
    } else {
        throw new Exception($conn->error);
    }
    exit();
}

// Replace your existing mark_sold section with this:
if($_POST['action'] == 'mark_sold') {
    $player_id = intval($_POST['player_id']);
    $sold_price = floatval($_POST['sold_price']);
    $sold_to = $conn->real_escape_string($_POST['sold_to']);
    $team_id = intval($_POST['team_id']);
    
    // Double-check team player limit before finalizing sale
    $team_check = $conn->query("SELECT players_bought, max_players FROM teams WHERE id = $team_id");
    $team_data = $team_check->fetch_assoc();
    
    if ($team_data['players_bought'] >= $team_data['max_players']) {
        echo json_encode([
            'success' => false,
            'message' => 'Cannot sell: Team has already reached maximum player limit!'
        ]);
        exit();
    }
    
    // Get bid history
    $result = $conn->query("SELECT bid_history FROM auction_status WHERE player_id = $player_id");
    $player = $result->fetch_assoc();
    $bid_history = $player['bid_history'] ?? '[]';
    
    // Get current team purse before update
    $team_query = $conn->query("SELECT total_purse, spent_amount FROM teams WHERE id = $team_id");
    $team_data = $team_query->fetch_assoc();
    $new_spent = $team_data['spent_amount'] + $sold_price;
    $new_remaining = $team_data['total_purse'] - $new_spent;
    
    // Update team stats (deduct from remaining purse)
    $conn->query("UPDATE teams SET 
                  spent_amount = spent_amount + $sold_price, 
                  players_bought = players_bought + 1 
                  WHERE id = $team_id");
    
    // Record purchase
    $conn->query("INSERT INTO auction_purchases (player_id, team_id, purchase_price) 
                  VALUES ($player_id, $team_id, $sold_price)");
    
    // Update auction status
    $query = "UPDATE auction_status SET 
              status = 'sold', 
              sold_price = $sold_price, 
              sold_to = '$sold_to',
              team_id = $team_id,
              bid_history = '$bid_history'
              WHERE player_id = $player_id";
    
    if($conn->query($query)) {
        // Get updated team data to return
        $updated_team = $conn->query("SELECT players_bought, spent_amount FROM teams WHERE id = $team_id")->fetch_assoc();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Player marked as sold',
            'team_id' => $team_id,
            'new_remaining' => $new_remaining,
            'players_bought' => $updated_team['players_bought']
        ]);
    } else {
        throw new Exception($conn->error);
    }
    exit();
}
        
        if($_POST['action'] == 'mark_sold') {
            $player_id = intval($_POST['player_id']);
            $sold_price = floatval($_POST['sold_price']);
            $sold_to = $conn->real_escape_string($_POST['sold_to']);
            $team_id = intval($_POST['team_id']);
            
            // Get bid history
            $result = $conn->query("SELECT bid_history FROM auction_status WHERE player_id = $player_id");
            $player = $result->fetch_assoc();
            $bid_history = $player['bid_history'] ?? '[]';
            
            // Get current team purse before update
            $team_query = $conn->query("SELECT total_purse, spent_amount FROM teams WHERE id = $team_id");
            $team_data = $team_query->fetch_assoc();
            $new_spent = $team_data['spent_amount'] + $sold_price;
            $new_remaining = $team_data['total_purse'] - $new_spent;
            
            // Update team stats (deduct from remaining purse)
            $conn->query("UPDATE teams SET 
                          spent_amount = spent_amount + $sold_price, 
                          players_bought = players_bought + 1 
                          WHERE id = $team_id");
            
            // Record purchase
            $conn->query("INSERT INTO auction_purchases (player_id, team_id, purchase_price) 
                          VALUES ($player_id, $team_id, $sold_price)");
            
            // Update auction status
            $query = "UPDATE auction_status SET 
                      status = 'sold', 
                      sold_price = $sold_price, 
                      sold_to = '$sold_to',
                      team_id = $team_id,
                      bid_history = '$bid_history'
                      WHERE player_id = $player_id";
            
            if($conn->query($query)) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Player marked as sold',
                    'team_id' => $team_id,
                    'new_remaining' => $new_remaining
                ]);
            } else {
                throw new Exception($conn->error);
            }
            exit();
        }
        
        if($_POST['action'] == 'mark_unsold') {
            $player_id = intval($_POST['player_id']);
            
            // First, get the current auction status to find team and price
            $result = $conn->query("SELECT team_id, sold_price FROM auction_status WHERE player_id = $player_id");
            $player_data = $result->fetch_assoc();
            
            // If player was sold to a team, revert those team stats
            if($player_data && $player_data['team_id'] && $player_data['sold_price']) {
                $team_id = $player_data['team_id'];
                $sold_price = $player_data['sold_price'];
                
                // Revert team stats (subtract from spent_amount and players_bought)
                $conn->query("UPDATE teams SET 
                              spent_amount = spent_amount - $sold_price, 
                              players_bought = players_bought - 1 
                              WHERE id = $team_id");
                
                // Delete the purchase record
                $conn->query("DELETE FROM auction_purchases WHERE player_id = $player_id");
            }
            
            // Now update auction status to unsold with NULL values
            $query = "UPDATE auction_status SET 
                      status = 'unsold', 
                      sold_price = NULL,
                      sold_to = NULL,
                      team_id = NULL
                      WHERE player_id = $player_id";
            
            if($conn->query($query)) {
                echo json_encode(['success' => true, 'message' => 'Player marked as unsold']);
            } else {
                throw new Exception($conn->error);
            }
            exit();
        }
        
        if($_POST['action'] == 'reset_auction') {
            $confirm = isset($_POST['confirm']) ? $_POST['confirm'] : false;
            
            if($confirm === 'true') {
                // Reset auction status and purchases
                $conn->query("TRUNCATE TABLE auction_status");
                $conn->query("TRUNCATE TABLE auction_purchases");
                
                // Reset all teams to original purse (spent_amount = 0, players_bought = 0)
                $conn->query("UPDATE teams SET spent_amount = 0, players_bought = 0");
                
                // Clear captain session as well
                $_SESSION['captain_ids'] = [];
                
                echo json_encode(['success' => true, 'message' => 'Auction has been reset. All teams have their original purse back!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Confirmation required']);
            }
            exit();
        }
        
        if($_POST['action'] == 'get_stats') {
            $total = $conn->query("SELECT COUNT(*) as count FROM players")->fetch_assoc()['count'];
            $pending = $conn->query("SELECT COUNT(*) as count FROM players p 
                                     LEFT JOIN auction_status a ON p.id = a.player_id 
                                     WHERE COALESCE(a.status, 'pending') = 'pending'")->fetch_assoc()['count'];
            $sold = $conn->query("SELECT COUNT(*) as count FROM auction_status WHERE status = 'sold'")->fetch_assoc()['count'];
            $unsold = $conn->query("SELECT COUNT(*) as count FROM auction_status WHERE status = 'unsold'")->fetch_assoc()['count'];
            $total_value = $conn->query("SELECT SUM(sold_price) as total FROM auction_status WHERE status = 'sold'")->fetch_assoc()['total'];
            
            echo json_encode([
                'success' => true,
                'stats' => [
                    'total' => intval($total),
                    'pending' => intval($pending),
                    'sold' => intval($sold),
                    'unsold' => intval($unsold),
                    'total_value' => floatval($total_value ?: 0)
                ]
            ]);
            exit();
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit();
    }
}

// Fetch categories
$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");

// Fetch ALL players for captain selection (including those who are captains)
$captain_players_query = "SELECT p.*, c.category_name 
                          FROM players p 
                          JOIN categories c ON p.category_id = c.id 
                          ORDER BY p.player_name";
$captain_players = $conn->query($captain_players_query);

// Fetch teams - REMOVED ALL CAPTAIN INFO
$teams_query = "SELECT id, team_name, team_short_code, team_color, logo_url, 
                total_purse, spent_amount, players_bought, max_players
                FROM teams WHERE is_active = 1 OR is_active IS NULL ORDER BY team_name";
$teams_result = $conn->query($teams_query);
$teams_array = [];

if($teams_result) {
    while($team = $teams_result->fetch_assoc()) {
        // Fix logo path for display
        $logo_display = '';
        if (!empty($team['logo_url'])) {
            $logo_filename = basename($team['logo_url']);
            $logo_display = 'uploads/team_logos/' . $logo_filename;
        }
        
        $teams_array[] = [
            'id' => intval($team['id']),
            'name' => $team['team_name'],
            'short' => $team['team_short_code'],
            'color' => $team['team_color'],
            'logo' => $logo_display,
            'total_purse' => floatval($team['total_purse']),
            'spent' => floatval($team['spent_amount']),
            'remaining' => floatval($team['total_purse'] - $team['spent_amount']),
            'players_bought' => intval($team['players_bought']),
            'max_players' => intval($team['max_players'])
        ];
    }
}

// Get auction progress
$progress_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN COALESCE(a.status, 'pending') = 'sold' THEN 1 ELSE 0 END) as sold,
    SUM(CASE WHEN COALESCE(a.status, 'pending') = 'unsold' THEN 1 ELSE 0 END) as unsold,
    SUM(CASE WHEN COALESCE(a.status, 'pending') = 'pending' THEN 1 ELSE 0 END) as pending
    FROM players p
    LEFT JOIN auction_status a ON p.id = a.player_id";

$progress_result = $conn->query($progress_query);
$progress = $progress_result ? $progress_result->fetch_assoc() : ['total' => 0, 'sold' => 0, 'unsold' => 0, 'pending' => 0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Auction - Cricket Auction</title>
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
            background: #f0f2f5;
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

        /* Captain Modal Styles */
        .captain-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: <?php echo empty($_SESSION['captain_ids']) ? 'flex' : 'none'; ?>;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .captain-modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .captain-modal h2 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .captain-modal p {
            color: #7f8c8d;
            margin-bottom: 20px;
        }

        .captain-list {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }

        .captain-item {
            display: flex;
            align-items: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .captain-item:hover {
            background: #e8f0fe;
        }

        .captain-item input[type="checkbox"] {
            margin-right: 10px;
            width: 18px;
            height: 18px;
        }

        .captain-item label {
            cursor: pointer;
            flex: 1;
        }

        .captain-category {
            font-size: 11px;
            color: #7f8c8d;
        }

        .selected-captains-info {
            margin: 15px 0;
            padding: 15px;
            background: #e8f0fe;
            border-radius: 8px;
        }

        .selected-captains-info h4 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .selected-captains-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .selected-captain-tag {
            background: #3498db;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .selected-captain-tag i {
            cursor: pointer;
            font-size: 12px;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .save-btn {
            padding: 12px 25px;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }

        .clear-btn {
            padding: 12px 25px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }

        .auction-container {
            display: flex;
            gap: 20px;
            max-width: 1600px;
            margin: 0 auto;
        }

        /* Left Panel - Stats */
        .left-panel {
            width: 280px;
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 20px;
        }

        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
        }

        .stats-card h3 {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .stats-card .value {
            font-size: 36px;
            font-weight: bold;
        }

        .progress-item {
            margin-bottom: 20px;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            color: #2c3e50;
        }

        .progress-bar {
            height: 10px;
            background: #ecf0f1;
            border-radius: 5px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #27ae60, #f39c12, #e74c3c);
            transition: width 0.3s;
        }

        .category-filters {
            margin-top: 20px;
        }

        .category-btn {
            width: 100%;
            padding: 12px;
            margin-bottom: 8px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-align: left;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .category-btn.all {
            background: #3498db;
            color: white;
        }

        .category-btn.child {
            background: #3498db20;
            color: #2c3e50;
            border: 1px solid #3498db;
        }

        .category-btn.men {
            background: #e74c3c20;
            color: #2c3e50;
            border: 1px solid #e74c3c;
        }

        .category-btn.active {
            transform: scale(1.02);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            font-weight: bold;
        }

        .category-btn .count {
            background: rgba(0,0,0,0.1);
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 12px;
        }

        .random-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 15px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .random-toggle label {
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #2c3e50;
        }

        .random-toggle input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .captain-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }

        .captain-info h4 {
            color: #2c3e50;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .captain-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-bottom: 10px;
        }

        .captain-tag {
            background: #f39c12;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
        }

        .edit-captain-btn {
            width: 100%;
            padding: 8px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
        }

        .reset-btn {
            width: 100%;
            padding: 12px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            margin-top: 10px;
            transition: all 0.3s;
        }

        .reset-btn:hover {
            background: #c0392b;
        }

        /* Center Panel - Player Info */
        .center-panel {
            flex: 1;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            min-height: 700px;
        }

        .player-header {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
        }

        .player-status {
            position: absolute;
            top: 0;
            right: 0;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: bold;
        }

        .status-pending {
            background: #f39c12;
            color: white;
        }

        .status-sold {
            background: #27ae60;
            color: white;
        }

        .status-unsold {
            background: #e74c3c;
            color: white;
        }

        .player-image-container {
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
            border-radius: 50%;
            overflow: hidden;
            border: 4px solid #3498db;
            box-shadow: 0 10px 30px rgba(52,152,219,0.3);
        }

        .player-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .default-player-icon {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f7ff;
            color: #3498db;
            font-size: 60px;
        }

        .player-name {
            font-size: 42px;
            color: #2c3e50;
            margin-bottom: 5px;
            font-weight: 700;
        }

        .player-reg {
            font-size: 18px;
            color: #7f8c8d;
            letter-spacing: 1px;
        }

        .player-details-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
        }

        .detail-item {
            text-align: center;
        }

        .detail-label {
            color: #7f8c8d;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .detail-value {
            font-size: 20px;
            font-weight: bold;
            color: #2c3e50;
        }

        .price-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            margin: 20px 0;
        }

        .price-label {
            font-size: 14px;
            opacity: 0.9;
        }

        .current-price {
            font-size: 56px;
            font-weight: bold;
            line-height: 1.2;
        }

        .current-bidder {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
            font-size: 18px;
        }

        .bidder-logo {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
        }

        .bid-history {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            max-height: 200px;
            overflow-y: auto;
        }

        .bid-history h3 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .bid-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px;
            border-bottom: 1px solid #ecf0f1;
        }

        .bid-item img {
            width: 20px;
            height: 20px;
            border-radius: 50%;
        }

        .bid-team {
            flex: 1;
            font-weight: 500;
        }

        .bid-amount {
            color: #27ae60;
            font-weight: bold;
        }

        .bid-time {
            color: #95a5a6;
            font-size: 11px;
        }

        /* Right Panel - Auction Controls */
        .right-panel {
            width: 320px;
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 20px;
        }

        .keyboard-hint {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 15px;
            font-size: 12px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid #e0e0e0;
        }

        .keyboard-hint i {
            color: #3498db;
            font-size: 16px;
        }

        .keyboard-hint .keys {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .keyboard-hint .key {
            background: #2c3e50;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-family: monospace;
        }

        .increment-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 16px;
            color: #2c3e50;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .increment-buttons {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }

        .increment-btn {
            padding: 12px;
            border: 2px solid #3498db;
            border-radius: 8px;
            background: white;
            color: #3498db;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .increment-btn:hover,
        .increment-btn.active {
            background: #3498db;
            color: white;
        }

        .manual-increment {
            display: flex;
            gap: 8px;
        }

        .manual-increment input {
            flex: 1;
            padding: 12px;
            border: 2px solid #3498db;
            border-radius: 8px;
            font-size: 14px;
        }

        .apply-btn {
            padding: 12px 20px;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }

        .selected-increment {
            text-align: center;
            margin-top: 10px;
            padding: 8px;
            background: #e8f0fe;
            border-radius: 20px;
            font-size: 14px;
        }

        .teams-list {
            max-height: 350px;
            overflow-y: auto;
            margin: 20px 0;
            padding-right: 5px;
        }

        .team-item {
            margin-bottom: 8px;
            position: relative;
        }

        .team-radio {
            display: none;
        }

        .team-label {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: #f8f9fa;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .team-radio:checked + .team-label {
            border-color: #3498db;
            background: #e3f2fd;
            transform: scale(1.02);
        }

        .team-label img {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
        }

        .team-info {
            flex: 1;
        }

        .team-name {
            font-weight: 600;
            font-size: 14px;
        }

        .team-purse {
            font-size: 11px;
            color: #27ae60;
        }

        .team-shortcut {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: #3498db;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .team-item:hover .team-shortcut {
            opacity: 1;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .action-btn {
            flex: 1;
            padding: 15px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }

        .sold-btn {
            background: #27ae60;
            color: white;
        }

        .unsold-btn {
            background: #e74c3c;
            color: white;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .action-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .action-btn .shortcut-hint {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #2c3e50;
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: bold;
            border: 2px solid white;
        }

        .sold-btn .shortcut-hint {
            background: #27ae60;
        }

        .unsold-btn .shortcut-hint {
            background: #e74c3c;
        }

        .loading, .no-player {
            text-align: center;
            padding: 100px 20px;
            color: #7f8c8d;
        }

        .loading i {
            font-size: 50px;
            color: #3498db;
            margin-bottom: 20px;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
        }

        .refresh-btn {
            background: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
            cursor: pointer;
            width: 100%;
            font-weight: 600;
        }

        .shortcuts-info {
            margin-top: 15px;
            padding: 10px;
            background: #e8f0fe;
            border-radius: 8px;
            font-size: 12px;
        }

        .shortcuts-info span {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            margin: 2px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- Captain Selection Modal -->
    <div class="captain-modal" id="captainModal">
        <div class="captain-modal-content">
            <h2><i class="fas fa-crown" style="color: #f39c12;"></i> Select Team Captains</h2>
            <p>Please select players who are team captains. They will be excluded from the auction.</p>
            
            <form method="POST" action="" id="captainForm">
                <div class="captain-list">
                    <?php 
                    if($captain_players && $captain_players->num_rows > 0) {
                        while($player = $captain_players->fetch_assoc()): 
                    ?>
                    <div class="captain-item">
                        <input type="checkbox" 
                               name="captains[]" 
                               value="<?php echo $player['id']; ?>" 
                               id="captain_<?php echo $player['id']; ?>"
                               <?php echo in_array($player['id'], $_SESSION['captain_ids']) ? 'checked' : ''; ?>>
                        <label for="captain_<?php echo $player['id']; ?>">
                            <strong><?php echo $player['player_name']; ?></strong>
                            <div class="captain-category"><?php echo $player['category_name']; ?> - <?php echo $player['playing_role']; ?></div>
                        </label>
                    </div>
                    <?php 
                        endwhile;
                    } 
                    ?>
                </div>
                
                <div class="modal-buttons">
                    <button type="submit" name="save_captains" class="save-btn">Save Captains & Start Auction</button>
                    <button type="button" class="clear-btn" onclick="clearAllCaptains()">Clear All</button>
                </div>
            </form>
        </div>
    </div>

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
                <li><a href="auction-control_1.php"><i class="fas fa-gavel"></i> Live Auction (Simple)</a></li>
                <li class="active"><a href="auction-control.php"><i class="fas fa-gavel"></i> Live Auction (Advanced)</a></li>
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
            <div class="auction-container">
                <!-- Left Panel - Stats -->
                <div class="left-panel">
                    <div class="stats-card">
                        <h3>Total Players</h3>
                        <div class="value" id="totalPlayers"><?php echo $progress['total'] ?? 0; ?></div>
                    </div>
                    
                    <!-- Captain Info -->
                    <?php if(!empty($_SESSION['captain_ids'])): ?>
                    <div class="captain-info">
                        <h4><i class="fas fa-crown" style="color: #f39c12;"></i> Selected Captains (<?php echo count($_SESSION['captain_ids']); ?>)</h4>
                        <div class="captain-tags">
                            <?php 
                            $captain_names = [];
                            if(!empty($_SESSION['captain_ids'])) {
                                $ids = implode(',', $_SESSION['captain_ids']);
                                $names_query = $conn->query("SELECT player_name FROM players WHERE id IN ($ids)");
                                while($name = $names_query->fetch_assoc()) {
                                    echo '<span class="captain-tag">' . $name['player_name'] . '</span>';
                                }
                            }
                            ?>
                        </div>
                        <a href="?clear_captains=1" class="edit-captain-btn" onclick="return confirm('Clear all captains?')">
                            <i class="fas fa-edit"></i> Edit Captains
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <div class="progress-item">
                        <div class="progress-header">
                            <span>Pending</span>
                            <span id="pendingPlayers"><?php echo $progress['pending'] ?? 0; ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $progress['total'] > 0 ? ($progress['pending']/$progress['total']*100) : 0; ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="progress-item">
                        <div class="progress-header">
                            <span>Sold</span>
                            <span id="soldPlayers"><?php echo $progress['sold'] ?? 0; ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $progress['total'] > 0 ? ($progress['sold']/$progress['total']*100) : 0; ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="progress-item">
                        <div class="progress-header">
                            <span>Unsold</span>
                            <span id="unsoldPlayers"><?php echo $progress['unsold'] ?? 0; ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $progress['total'] > 0 ? ($progress['unsold']/$progress['total']*100) : 0; ?>%"></div>
                        </div>
                    </div>

                    <div class="category-filters">
                        <button class="category-btn all active" onclick="filterCategory('all')">
                            <span>All Players</span>
                            <span class="count"><?php echo $progress['total'] ?? 0; ?></span>
                        </button>
                        
                        <?php 
                        $categories->data_seek(0); // Reset pointer
                        if($categories && $categories->num_rows > 0) {
                            while($cat = $categories->fetch_assoc()): 
                                $btnClass = ($cat['category_name'] == 'Under 15') ? 'child' : 'men';
                                $cat_count = $conn->query("SELECT COUNT(*) as count FROM players WHERE category_id = ".$cat['id'])->fetch_assoc()['count'];
                        ?>
                            <button class="category-btn <?php echo $btnClass; ?>" 
                                    onclick="filterCategory(<?php echo $cat['id']; ?>)">
                                <span><?php echo $cat['category_name']; ?></span>
                                <span class="count"><?php echo $cat_count; ?></span>
                            </button>
                        <?php 
                            endwhile;
                        } 
                        ?>
                    </div>

                    <div class="random-toggle">
                        <label>
                            <input type="checkbox" id="randomMode" onchange="toggleRandomMode()">
                            <i class="fas fa-random"></i> Random Player Mode
                        </label>
                    </div>

                    <button class="reset-btn" onclick="resetAuction()">
                        <i class="fas fa-redo-alt"></i> Reset Auction
                    </button>
                    
                    <button class="refresh-btn" onclick="refreshPlayer()">
                        <i class="fas fa-sync-alt"></i> Next Player
                    </button>

                    <div class="shortcuts-info">
                        <strong><i class="fas fa-keyboard"></i> Keyboard Shortcuts:</strong><br>
                        <span>1-<?php echo count($teams_array); ?></span> Select Team<br>
                        <span>S</span> Mark as SOLD<br>
                        <span>U</span> Mark as UNSOLD<br>
                        <span>Space</span> Place Bid (with selected team)
                    </div>
                </div>

                <!-- Center Panel - Player Info -->
                <div class="center-panel">
                    <!-- Error Display -->
                    <div id="errorDisplay" class="error-message" style="display: none;"></div>

                    <!-- Loading Indicator -->
                    <div id="loadingIndicator" class="loading" style="display: none;">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Loading players...</p>
                    </div>

                    <!-- Player Card -->
                    <div id="playerCard" style="display: none;">
                        <div class="player-header">
                            <div class="player-status status-pending" id="statusBadge">
                                <i class="fas fa-clock"></i> Pending
                            </div>
                            <div class="player-image-container" id="playerImageContainer">
                                <div class="default-player-icon" id="defaultPlayerIcon">
                                    <i class="fas fa-user-circle"></i>
                                </div>
                                <img src="" alt="Player" class="player-image" id="playerImage" style="display: none;">
                            </div>
                            <h1 class="player-name" id="playerName"></h1>
                            <div class="player-reg" id="playerRegNo"></div>
                        </div>

                        <div class="player-details-grid">
                            <div class="detail-item">
                                <div class="detail-label">Age</div>
                                <div class="detail-value" id="playerAge"></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Category</div>
                                <div class="detail-value" id="playerCategory"></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Role</div>
                                <div class="detail-value" id="playerRole"></div>
                            </div>
                        </div>

                        <div class="price-box">
                            <div class="price-label">Current Bid</div>
                            <div class="current-price" id="currentBid"></div>
                            <div class="current-bidder" id="currentBidder"></div>
                        </div>

                        <div class="bid-history">
                            <h3><i class="fas fa-history"></i> Bid History</h3>
                            <div id="bidHistoryList">
                                <p style="text-align: center; color: #95a5a6;">No bids placed yet</p>
                            </div>
                        </div>
                    </div>

                    <!-- No Player Message -->
                    <div id="noPlayer" class="no-player" style="display: none;">
                        <i class="fas fa-trophy"></i>
                        <h3>Auction Complete!</h3>
                        <p>All players have been processed.</p>
                    </div>
                </div>

                <!-- Right Panel - Auction Controls -->
                <div class="right-panel">
                    <div class="keyboard-hint">
                        <i class="fas fa-keyboard"></i>
                        <div class="keys">
                            <span class="key">1-<?php echo count($teams_array); ?></span> Team
                            <span class="key">S</span> Sold
                            <span class="key">U</span> Unsold
                            <span class="key">Space</span> Bid
                        </div>
                    </div>

                    <div class="increment-section">
                        <div class="section-title">Bid Increment</div>
                        
                        <!-- Increment Buttons -->
                        <div id="incrementButtons">
                            <div class="increment-buttons">
                                <button class="increment-btn" onclick="setIncrement(2000)">+ ₹2,000</button>
                                <button class="increment-btn" onclick="setIncrement(5000)">+ ₹5,000</button>
                                <button class="increment-btn" onclick="setIncrement(10000)">+ ₹10,000</button>
                                <button class="increment-btn" onclick="setIncrement(25000)">+ ₹25,000</button>
                            </div>
                        </div>
                        
                        <!-- Manual Increment -->
                        <div class="manual-increment">
                            <input type="number" id="manualIncrement" placeholder="Custom amount" min="1000" step="1000">
                            <button class="apply-btn" onclick="setManualIncrement()">Apply</button>
                        </div>
                        
                        <div class="selected-increment">
                            <span id="selectedIncrement">₹2,000</span>
                        </div>
                    </div>

                    <div class="section-title">Select Team <small style="color: #7f8c8d;">(Press 1-<?php echo count($teams_array); ?>)</small></div>
                    <div class="teams-list" id="teamsList">
                        <?php foreach($teams_array as $index => $team): ?>
                        <div class="team-item">
                            <input type="radio" name="team" id="team<?php echo $team['id']; ?>" 
                                   value="<?php echo $team['name']; ?>" 
                                   data-id="<?php echo $team['id']; ?>"
                                   data-remaining="<?php echo $team['remaining']; ?>"
                                   data-players="<?php echo $team['players_bought']; ?>"
                                   data-max="<?php echo $team['max_players']; ?>"
                                   class="team-radio">
                            <label for="team<?php echo $team['id']; ?>" class="team-label">
                                <?php if(!empty($team['logo'])): ?>
                                    <img src="../<?php echo $team['logo']; ?>" alt="<?php echo $team['name']; ?>">
                                <?php else: ?>
                                    <i class="fas fa-shield-alt" style="font-size: 30px; color: <?php echo $team['color']; ?>;"></i>
                                <?php endif; ?>
                                <div class="team-info">
                                    <div class="team-name"><?php echo $team['name']; ?></div>
                                    <!-- In the teams list section, update the team-purse display -->
                                <div class="team-purse">
                                     ₹<?php echo number_format($team['remaining']); ?> left 
                                    (<?php echo $team['players_bought']; ?>/<?php echo $team['max_players']; ?> players)
                                </div>
                                </div>
                            </label>
                            <div class="team-shortcut"><?php echo $index + 1; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="action-buttons">
                        <button class="action-btn sold-btn" onclick="markSold()" id="soldBtn">
                            <i class="fas fa-check-circle"></i> SOLD
                            <span class="shortcut-hint">S</span>
                        </button>
                        <button class="action-btn unsold-btn" onclick="markUnsold()" id="unsoldBtn">
                            <i class="fas fa-times-circle"></i> UNSOLD
                            <span class="shortcut-hint">U</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
// Team data from PHP
const teams = <?php echo json_encode($teams_array); ?>;
let currentPlayer = null;
let currentCategory = 'all';
let currentIncrement = 2000;
let bidHistory = [];
let randomMode = false;

// Load initial player
document.addEventListener('DOMContentLoaded', function() {
    loadNextPlayer();
    updateStats();
    
    // Add event listener for team selection
    document.querySelectorAll('input[name="team"]').forEach(radio => {
        radio.addEventListener('change', function() {
            if(this.checked && currentPlayer) {
                placeBid();
            }
        });
    });
});

// Clear all captains function
function clearAllCaptains() {
    document.querySelectorAll('input[name="captains[]"]').forEach(cb => {
        cb.checked = false;
    });
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Prevent default for these keys
    if (e.key === ' ' || e.key === 'Space' || e.key === 's' || e.key === 'S' || e.key === 'u' || e.key === 'U' || (e.key >= '1' && e.key <= '9')) {
        e.preventDefault();
    }
    
    // Number keys for team selection (1-8)
    if (e.key >= '1' && e.key <= '8') {
        const index = parseInt(e.key) - 1;
        const teamRadios = document.querySelectorAll('input[name="team"]');
        if (teamRadios[index] && !teamRadios[index].disabled) {
            teamRadios[index].checked = true;
            // Auto place bid if a player is active
            if (currentPlayer) {
                placeBid();
            }
        }
    }
    
    // S key for Sold
    if (e.key === 's' || e.key === 'S') {
        if (currentPlayer && !document.getElementById('soldBtn').disabled) {
            markSold();
        }
    }
    
    // U key for Unsold
    if (e.key === 'u' || e.key === 'U') {
        if (currentPlayer && !document.getElementById('unsoldBtn').disabled) {
            markUnsold();
        }
    }
    
    // Space bar for placing bid with selected team
    if (e.key === ' ' || e.key === 'Space') {
        if (currentPlayer) {
            const selectedTeam = document.querySelector('input[name="team"]:checked');
            if (selectedTeam && !selectedTeam.disabled) {
                placeBid();
            }
        }
    }
});

function toggleRandomMode() {
    randomMode = document.getElementById('randomMode').checked;
    loadNextPlayer();
}

function refreshPlayer() {
    loadNextPlayer();
}

function filterCategory(categoryId) {
    currentCategory = categoryId;
    
    document.querySelectorAll('.category-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    loadNextPlayer();
}

function loadNextPlayer() {
    document.getElementById('loadingIndicator').style.display = 'block';
    document.getElementById('playerCard').style.display = 'none';
    document.getElementById('noPlayer').style.display = 'none';
    document.getElementById('errorDisplay').style.display = 'none';
    
    fetch('auction-control.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_current_player&category=' + currentCategory + '&random=' + randomMode
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('loadingIndicator').style.display = 'none';
        
        if(data.success && data.player) {
            currentPlayer = data.player;
            displayPlayer(data.player);
            document.getElementById('playerCard').style.display = 'block';
        } else {
            document.getElementById('noPlayer').style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('loadingIndicator').style.display = 'none';
        document.getElementById('errorDisplay').style.display = 'block';
        document.getElementById('errorDisplay').textContent = 'Error loading player: ' + error.message;
    });
}

function displayPlayer(player) {
    document.getElementById('playerName').textContent = player.player_name;
    document.getElementById('playerRegNo').textContent = player.registration_no;
    document.getElementById('playerAge').textContent = player.age + ' years';
    document.getElementById('playerCategory').textContent = player.category_name;
    document.getElementById('playerRole').textContent = player.playing_role;
    
    // Handle player image
    const playerImage = document.getElementById('playerImage');
    const defaultIcon = document.getElementById('defaultPlayerIcon');
    
    if(player.player_image) {
        playerImage.src = '../' + player.player_image;
        playerImage.style.display = 'block';
        defaultIcon.style.display = 'none';
    } else {
        playerImage.style.display = 'none';
        defaultIcon.style.display = 'flex';
    }
    
    // Set current bid
    document.getElementById('currentBid').textContent = '₹' + player.current_bid.toLocaleString('en-IN');
    
    // Show current bidder
    const bidderDiv = document.getElementById('currentBidder');
    if(player.current_team) {
        const team = teams.find(t => t.name === player.current_team);
        if(team && team.logo) {
            bidderDiv.innerHTML = `
                <img src="../${team.logo}" class="bidder-logo">
                <span>${player.current_team}</span>
            `;
        } else {
            bidderDiv.innerHTML = `<span>${player.current_team}</span>`;
        }
    } else {
        bidderDiv.innerHTML = '';
    }
    
    // Display bid history
    if(player.bid_history && player.bid_history.length > 0) {
        bidHistory = player.bid_history;
        displayBidHistory();
    } else {
        bidHistory = [];
        document.getElementById('bidHistoryList').innerHTML = '<p style="text-align: center; color: #95a5a6;">No bids placed yet</p>';
    }
    
    // Update status badge
    const badge = document.getElementById('statusBadge');
    if(player.auction_status === 'sold') {
        badge.className = 'player-status status-sold';
        badge.innerHTML = '<i class="fas fa-check-circle"></i> Sold';
        disableButtons(true);
    } else if(player.auction_status === 'unsold') {
        badge.className = 'player-status status-unsold';
        badge.innerHTML = '<i class="fas fa-times-circle"></i> Unsold';
        disableButtons(true);
    } else {
        badge.className = 'player-status status-pending';
        badge.innerHTML = '<i class="fas fa-clock"></i> Pending';
        disableButtons(false);
    }
}

function disableButtons(disabled) {
    document.getElementById('soldBtn').disabled = disabled;
    document.getElementById('unsoldBtn').disabled = disabled;
    document.querySelectorAll('.team-radio').forEach(radio => {
        radio.disabled = disabled;
    });
}

function setIncrement(amount) {
    currentIncrement = amount;
    document.getElementById('selectedIncrement').textContent = '₹' + amount.toLocaleString('en-IN');
    
    document.querySelectorAll('.increment-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
}

function setManualIncrement() {
    const manualAmount = document.getElementById('manualIncrement').value;
    if(manualAmount && manualAmount >= 1000) {
        currentIncrement = parseInt(manualAmount);
        document.getElementById('selectedIncrement').textContent = '₹' + currentIncrement.toLocaleString('en-IN');
        
        document.querySelectorAll('.increment-btn').forEach(btn => {
            btn.classList.remove('active');
        });
    } else {
        alert('Please enter a valid amount (minimum ₹1000)');
    }
}

function placeBid() {
    if(!currentPlayer) return;
    
    const selectedTeam = document.querySelector('input[name="team"]:checked');
    if(!selectedTeam) return;
    
    const teamId = selectedTeam.dataset.id;
    const teamName = selectedTeam.value;
    const remainingPurse = parseFloat(selectedTeam.dataset.remaining);
    const playersBought = parseInt(selectedTeam.dataset.players);
    const maxPlayers = parseInt(selectedTeam.dataset.max);
    
    const newBid = currentPlayer.current_bid + currentIncrement;
    
    // Check if team has enough purse
    if(newBid > remainingPurse) {
        alert(`⚠️ ${teamName} does not have enough purse!\nRemaining: ₹${remainingPurse.toLocaleString()}\nBid Amount: ₹${newBid.toLocaleString()}`);
        selectedTeam.checked = false;
        return;
    }
    
    // Check if team has reached max players
    if(playersBought >= maxPlayers) {
        alert(`⚠️ ${teamName} has already reached maximum player limit (${maxPlayers})!`);
        selectedTeam.checked = false;
        return;
    }
    
    fetch('auction-control.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=place_bid&player_id=' + currentPlayer.id + 
              '&team=' + encodeURIComponent(teamName) + 
              '&team_id=' + teamId +
              '&bid_amount=' + newBid + 
              '&increment=' + currentIncrement
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            document.getElementById('currentBid').textContent = '₹' + newBid.toLocaleString('en-IN');
            
            const team = teams.find(t => t.id == teamId);
            if(team && team.logo) {
                document.getElementById('currentBidder').innerHTML = `
                    <img src="../${team.logo}" class="bidder-logo">
                    <span>${teamName}</span>
                `;
            } else {
                document.getElementById('currentBidder').innerHTML = `<span>${teamName}</span>`;
            }
            
            bidHistory = data.bid_history;
            displayBidHistory();
            
            currentPlayer.current_bid = newBid;
            currentPlayer.current_team = teamName;
            currentPlayer.current_team_id = teamId;
            
            // Deselect team for next bid
            selectedTeam.checked = false;
        } else {
            alert('Error placing bid: ' + data.message);
        }
    });
}

function markSold() {
    if(!currentPlayer) return;
    
    let finalBid = currentPlayer.current_bid;
    let lastBidder = currentPlayer.current_team || 'Unknown';
    let teamId = currentPlayer.current_team_id || null;
    
    if(bidHistory.length > 0) {
        const last = bidHistory[bidHistory.length - 1];
        finalBid = last.bid;
        lastBidder = last.team;
        teamId = last.team_id;
    }
    
    if(!teamId) {
        alert('No valid team found for this bid!');
        return;
    }
    
    if(!confirm(`Mark player as SOLD to ${lastBidder} for ₹${finalBid.toLocaleString('en-IN')}?`)) return;
    
    fetch('auction-control.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=mark_sold&player_id=' + currentPlayer.id + 
              '&sold_price=' + finalBid + 
              '&sold_to=' + encodeURIComponent(lastBidder) +
              '&team_id=' + teamId
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            // Update the team's remaining purse and player count in the UI
            const teamRadio = document.querySelector(`input[data-id="${teamId}"]`);
            if(teamRadio) {
                const newRemaining = parseFloat(teamRadio.dataset.remaining) - finalBid;
                const newPlayersBought = data.players_bought;
                
                teamRadio.dataset.remaining = newRemaining;
                teamRadio.dataset.players = newPlayersBought;
                
                const teamLabel = teamRadio.nextElementSibling;
                const purseSpan = teamLabel.querySelector('.team-purse');
                purseSpan.textContent = '₹' + newRemaining.toLocaleString() + ' left (' + newPlayersBought + '/' + teamRadio.dataset.max + ' players)';
            }
            
            updateStats();
            loadNextPlayer();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function markUnsold() {
    if(!currentPlayer) return;
    
    if(!confirm('Are you sure you want to mark this player as UNSOLD?')) return;
    
    fetch('auction-control.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=mark_unsold&player_id=' + currentPlayer.id
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            // Update the UI to reflect unsold status
            document.getElementById('statusBadge').className = 'player-status status-unsold';
            document.getElementById('statusBadge').innerHTML = '<i class="fas fa-times-circle"></i> Unsold';
            
            // Disable buttons for unsold player
            disableButtons(true);
            
            // If there was a team associated, we need to refresh team data
            updateStats();
            
            // Load next player after a short delay
            setTimeout(() => {
                loadNextPlayer();
            }, 1500);
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function displayBidHistory() {
    let html = '';
    bidHistory.slice().reverse().forEach((bid) => {
        const date = new Date(bid.time);
        const timeStr = date.toLocaleTimeString();
        const team = teams.find(t => t.name === bid.team);
        
        html += `
            <div class="bid-item">
                ${team && team.logo ? `<img src="../${team.logo}" alt="${bid.team}">` : ''}
                <span class="bid-team">${bid.team}</span>
                <span class="bid-amount">₹${bid.bid.toLocaleString('en-IN')}</span>
                <span class="bid-time">${timeStr}</span>
            </div>
        `;
    });
    
    document.getElementById('bidHistoryList').innerHTML = html || '<p style="text-align: center; color: #95a5a6;">No bids placed yet</p>';
}

function resetAuction() {
    if(!confirm('⚠️ WARNING: This will reset ALL auction progress, restore all team purses, AND clear all captains! Are you sure?')) return;
    
    fetch('auction-control.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=reset_auction&confirm=true'
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            // Refresh the page to show updated team purses
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function updateStats() {
    fetch('auction-control.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_stats'
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            document.getElementById('totalPlayers').textContent = data.stats.total;
            document.getElementById('pendingPlayers').textContent = data.stats.pending;
            document.getElementById('soldPlayers').textContent = data.stats.sold;
            document.getElementById('unsoldPlayers').textContent = data.stats.unsold;
        }
    });
}
</script>
</body>
</html>