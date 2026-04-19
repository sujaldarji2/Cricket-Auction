<?php
session_start();
include '../includes/db-config.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get all sold players with their bid history and calculate increase percentage
$query = "SELECT p.*, c.category_name, 
          a.sold_price, a.team_id, a.bid_history,
          t.team_name, t.team_color, t.logo_url,
          (a.sold_price - p.base_price) as price_increase,
          ROUND(((a.sold_price - p.base_price) / NULLIF(p.base_price, 0)) * 100, 2) as increase_percentage
          FROM players p 
          JOIN categories c ON p.category_id = c.id 
          JOIN auction_status a ON p.id = a.player_id 
          LEFT JOIN teams t ON a.team_id = t.id
          WHERE a.status = 'sold'
          ORDER BY increase_percentage DESC, price_increase DESC";

$result = $conn->query($query);

if (!$result) {
    die("Query failed: " . $conn->error . "<br>Query: " . $query);
}

$players = $result;

// Get statistics - Fixed: base_price comes from players table
$stats_query = "SELECT 
                COUNT(*) as total_sold,
                COALESCE(AVG(a.sold_price), 0) as avg_price,
                COALESCE(MAX(a.sold_price), 0) as max_price,
                COALESCE(SUM(a.sold_price), 0) as total_value,
                COALESCE(AVG(a.sold_price - p.base_price), 0) as avg_increase,
                COALESCE(MAX(a.sold_price - p.base_price), 0) as max_increase
                FROM auction_status a
                JOIN players p ON a.player_id = p.id
                WHERE a.status = 'sold'";
$stats_result = $conn->query($stats_query);
$stats = $stats_result ? $stats_result->fetch_assoc() : ['total_sold' => 0, 'avg_price' => 0, 'max_price' => 0, 'total_value' => 0, 'avg_increase' => 0, 'max_increase' => 0];

// Get categories for filter
$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Highest Bids - Cricket Auction</title>
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
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            text-align: center;
            border-bottom: 4px solid transparent;
        }

        .stat-card.primary {
            border-bottom-color: #3498db;
        }

        .stat-card.success {
            border-bottom-color: #27ae60;
        }

        .stat-card.warning {
            border-bottom-color: #f39c12;
        }

        .stat-card.danger {
            border-bottom-color: #e74c3c;
        }

        .stat-icon {
            font-size: 32px;
            margin-bottom: 10px;
            color: #7f8c8d;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .stat-value {
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

        .filter-select {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            min-width: 150px;
            background: white;
        }

        .rank-badge {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
        }

        .rank-1 {
            background: gold;
            color: #2c3e50;
        }

        .rank-2 {
            background: silver;
            color: #2c3e50;
        }

        .rank-3 {
            background: #cd7f32;
            color: white;
        }

        .bids-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        table th {
            padding: 15px;
            text-align: left;
            font-size: 13px;
            text-transform: uppercase;
        }

        table td {
            padding: 15px;
            border-bottom: 1px solid #ecf0f1;
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
            font-size: 18px;
        }

        .player-info {
            flex: 1;
        }

        .player-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 3px;
        }

        .player-reg {
            font-size: 11px;
            color: #7f8c8d;
        }

        .team-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            background: #f8f9fa;
            border-radius: 20px;
            font-size: 12px;
        }

        .team-logo {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            object-fit: cover;
        }

        .base-price {
            color: #7f8c8d;
            text-decoration: line-through;
            font-size: 12px;
        }

        .sold-price {
            color: #27ae60;
            font-size: 16px;
            font-weight: bold;
        }

        .increase-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }

        .increase-high {
            background: #27ae60;
            color: white;
        }

        .increase-medium {
            background: #f39c12;
            color: white;
        }

        .increase-low {
            background: #e74c3c;
            color: white;
        }

        .percentage-bar {
            width: 100px;
            height: 6px;
            background: #ecf0f1;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 5px;
        }

        .percentage-fill {
            height: 100%;
            background: linear-gradient(90deg, #27ae60, #f39c12);
            transition: width 0.3s;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 60px;
            color: #bdc3c7;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #2c3e50;
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
                <li><a href="player-status.php"><i class="fas fa-sync-alt"></i> Player Status</a></li>
                <li><a href="team-players.php"><i class="fas fa-users-between-lines"></i> Team Players</a></li>
                <li class="active"><a href="highest-bids.php"><i class="fas fa-chart-line"></i> Highest Bids</a></li>
                <li style="border-top: 1px solid #34495e; margin-top: 20px;">
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <div class="header-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div>
                    <h1>Highest Bids</h1>
                    <p>Players ranked by price increase percentage</p>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="stat-label">Total Sold</div>
                    <div class="stat-value"><?php echo $stats['total_sold'] ?? 0; ?></div>
                </div>
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-rupee-sign"></i>
                    </div>
                    <div class="stat-label">Total Value</div>
                    <div class="stat-value">₹<?php echo number_format($stats['total_value'] ?? 0, 2); ?></div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="stat-label">Avg Price</div>
                    <div class="stat-value">₹<?php echo number_format($stats['avg_price'] ?? 0, 2); ?></div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-icon">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <div class="stat-label">Max Increase</div>
                    <div class="stat-value">₹<?php echo number_format($stats['max_increase'] ?? 0, 2); ?></div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <select id="categoryFilter" class="filter-select">
                    <option value="all">All Categories</option>
                    <?php
                    if($categories && $categories->num_rows > 0) {
                        while($cat = $categories->fetch_assoc()) {
                            echo "<option value='" . $cat['id'] . "'>" . $cat['category_name'] . "</option>";
                        }
                    }
                    ?>
                </select>
                <select id="sortFilter" class="filter-select">
                    <option value="percentage">Sort by % Increase</option>
                    <option value="amount">Sort by Amount Increase</option>
                    <option value="sold">Sort by Sold Price</option>
                </select>
                <input type="text" id="searchInput" class="filter-select" placeholder="Search player..." style="flex: 1;">
            </div>

            <!-- Bids Table -->
            <div class="bids-table">
                <table id="bidsTable">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Player</th>
                            <th>Category/Role</th>
                            <th>Team</th>
                            <th>Base Price</th>
                            <th>Sold Price</th>
                            <th>Increase</th>
                            <th>% Increase</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if($players && $players->num_rows > 0):
                            $rank = 1;
                            while($player = $players->fetch_assoc()): 
                                $increase_class = 'increase-low';
                                if($player['increase_percentage'] > 100) $increase_class = 'increase-high';
                                else if($player['increase_percentage'] > 50) $increase_class = 'increase-medium';
                        ?>
                        <tr data-category="<?php echo $player['category_id']; ?>"
                            data-name="<?php echo strtolower($player['player_name']); ?>"
                            data-percentage="<?php echo $player['increase_percentage']; ?>"
                            data-amount="<?php echo $player['price_increase']; ?>"
                            data-sold="<?php echo $player['sold_price']; ?>">
                            <td>
                                <div class="rank-badge" style="background: <?php echo $rank == 1 ? 'gold' : ($rank == 2 ? 'silver' : ($rank == 3 ? '#cd7f32' : '#ecf0f1')); ?>;">
                                    <?php echo $rank; ?>
                                </div>
                            </td>
                            <td>
                                <div class="player-cell">
                                    <?php if(!empty($player['player_image'])): ?>
                                        <img src="../<?php echo $player['player_image']; ?>" class="player-avatar">
                                    <?php else: ?>
                                        <div class="default-avatar">
                                            <i class="fas fa-user-circle"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="player-info">
                                        <div class="player-name"><?php echo htmlspecialchars($player['player_name']); ?></div>
                                        <div class="player-reg"><?php echo htmlspecialchars($player['registration_no']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($player['category_name']); ?></div>
                                <small><?php echo htmlspecialchars($player['playing_role']); ?></small>
                            </td>
                            <td>
                                <?php if(!empty($player['team_name'])): ?>
                                <div class="team-badge">
                                    <?php if(!empty($player['logo_url'])): ?>
                                        <img src="../<?php echo $player['logo_url']; ?>" class="team-logo">
                                    <?php endif; ?>
                                    <span style="color: <?php echo $player['team_color'] ?? '#333'; ?>;">
                                        <?php echo htmlspecialchars($player['team_name']); ?>
                                    </span>
                                </div>
                                <?php else: ?>
                                    <span class="team-badge">Not Assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="base-price">₹<?php echo number_format($player['base_price'], 2); ?></span>
                            </td>
                            <td>
                                <span class="sold-price">₹<?php echo number_format($player['sold_price'], 2); ?></span>
                            </td>
                            <td>
                                <span class="sold-price">+₹<?php echo number_format($player['price_increase'], 2); ?></span>
                            </td>
                            <td>
                                <span class="increase-badge <?php echo $increase_class; ?>">
                                    <?php echo number_format($player['increase_percentage'], 2); ?>%
                                </span>
                                <div class="percentage-bar">
                                    <div class="percentage-fill" style="width: <?php echo min($player['increase_percentage'], 300); ?>%;"></div>
                                </div>
                            </td>
                        </tr>
                        <?php 
                                $rank++;
                            endwhile; 
                        else:
                        ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fas fa-chart-line"></i>
                                    <h3>No Sold Players Yet</h3>
                                    <p>Players who are sold in the auction will appear here.</p>
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
    // Check if table exists before adding event listeners
    document.addEventListener('DOMContentLoaded', function() {
        const categoryFilter = document.getElementById('categoryFilter');
        const sortFilter = document.getElementById('sortFilter');
        const searchInput = document.getElementById('searchInput');
        
        if(categoryFilter) categoryFilter.addEventListener('change', filterTable);
        if(sortFilter) sortFilter.addEventListener('change', sortTable);
        if(searchInput) searchInput.addEventListener('keyup', filterTable);
    });

    function filterTable() {
        const category = document.getElementById('categoryFilter').value;
        const search = document.getElementById('searchInput').value.toLowerCase();
        const rows = document.querySelectorAll('#bidsTable tbody tr');
        
        let visibleCount = 0;
        
        rows.forEach(row => {
            // Skip empty state row
            if(row.querySelector('td[colspan]')) return;
            
            const rowCategory = row.dataset.category;
            const rowName = row.dataset.name;
            
            const matchesCategory = category === 'all' || rowCategory == category;
            const matchesSearch = !search || (rowName && rowName.includes(search));
            
            if(matchesCategory && matchesSearch) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Show empty message if no rows visible
        const tbody = document.querySelector('#bidsTable tbody');
        const emptyRow = tbody.querySelector('tr td[colspan]')?.parentNode;
        
        if(visibleCount === 0) {
            // Check if empty row already exists
            if(!emptyRow) {
                const newEmptyRow = document.createElement('tr');
                newEmptyRow.innerHTML = '<td colspan="8"><div class="empty-state"><i class="fas fa-search"></i><h3>No Results Found</h3><p>Try adjusting your filters</p></div></td>';
                tbody.appendChild(newEmptyRow);
            }
        } else if(emptyRow) {
            emptyRow.remove();
        }
        
        // Re-rank visible rows
        rankVisibleRows();
    }

    function sortTable() {
        const sortBy = document.getElementById('sortFilter').value;
        const tbody = document.querySelector('#bidsTable tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        // Filter out empty state row
        const dataRows = rows.filter(row => !row.querySelector('td[colspan]'));
        
        dataRows.sort((a, b) => {
            const aVal = parseFloat(a.dataset[sortBy]) || 0;
            const bVal = parseFloat(b.dataset[sortBy]) || 0;
            return bVal - aVal;
        });
        
        // Remove all rows
        tbody.innerHTML = '';
        
        // Add sorted data rows
        dataRows.forEach(row => tbody.appendChild(row));
        
        // Re-rank after sorting
        rankVisibleRows();
    }

    function rankVisibleRows() {
        const visibleRows = Array.from(document.querySelectorAll('#bidsTable tbody tr:not([style*="display: none"])')).filter(row => !row.querySelector('td[colspan]'));
        
        visibleRows.forEach((row, index) => {
            const rankCell = row.cells[0];
            const rankDiv = rankCell.querySelector('.rank-badge');
            const rank = index + 1;
            
            rankDiv.textContent = rank;
            rankDiv.style.background = rank === 1 ? 'gold' : (rank === 2 ? 'silver' : (rank === 3 ? '#cd7f32' : '#ecf0f1'));
        });
    }
    </script>
</body>
</html>