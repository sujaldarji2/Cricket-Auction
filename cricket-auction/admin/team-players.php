<?php
session_start();
include '../includes/db-config.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Get all teams with their stats
$teams_query = "SELECT t.*, 
                COUNT(DISTINCT ap.player_id) as players_bought,
                COALESCE(SUM(ap.purchase_price), 0) as total_spent,
                (t.total_purse - COALESCE(SUM(ap.purchase_price), 0)) as remaining_purse
                FROM teams t
                LEFT JOIN auction_purchases ap ON t.id = ap.team_id
                GROUP BY t.id
                ORDER BY t.team_name";
$teams = $conn->query($teams_query);

// Get all players for each team (for modal)
$team_players_data = [];
$team_ids = [];
$teams_array = [];

if($teams && $teams->num_rows > 0) {
    while($team = $teams->fetch_assoc()) {
        $team_ids[] = $team['id'];
        $teams_array[] = $team;
        
        // Initialize with empty players array
        $team_players_data[$team['id']] = [
            'info' => $team,
            'players' => []
        ];
    }
}

// Fetch players for all teams
if(!empty($team_ids)) {
    $players_query = "SELECT p.*, c.category_name, ap.purchase_price, ap.purchase_date,
                     t.team_name, t.team_color, t.logo_url, t.id as team_id
                     FROM auction_purchases ap
                     JOIN players p ON ap.player_id = p.id
                     JOIN categories c ON p.category_id = c.id
                     JOIN teams t ON ap.team_id = t.id
                     WHERE ap.team_id IN (" . implode(',', $team_ids) . ")
                     ORDER BY t.team_name, ap.purchase_price DESC";
    
    $players_result = $conn->query($players_query);
    
    if($players_result && $players_result->num_rows > 0) {
        while($player = $players_result->fetch_assoc()) {
            $team_players_data[$player['team_id']]['players'][] = $player;
        }
    }
}

// Get overall stats
$overall_stats = [
    'total_teams' => count($teams_array),
    'total_players_sold' => $conn->query("SELECT COUNT(*) as count FROM auction_purchases")->fetch_assoc()['count'],
    'total_amount' => $conn->query("SELECT COALESCE(SUM(purchase_price), 0) as total FROM auction_purchases")->fetch_assoc()['total'],
    'avg_spent' => 0
];

if($overall_stats['total_teams'] > 0) {
    $overall_stats['avg_spent'] = $overall_stats['total_amount'] / $overall_stats['total_teams'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team-wise Players - Cricket Auction</title>
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

        /* Stats Cards */
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

        .stat-icon.teams {
            background: #3498db;
            color: white;
        }

        .stat-icon.players {
            background: #27ae60;
            color: white;
        }

        .stat-icon.amount {
            background: #f39c12;
            color: white;
        }

        .stat-icon.avg {
            background: #9b59b6;
            color: white;
        }

        .stat-info h3 {
            color: #7f8c8d;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .stat-info .value {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }

        /* Teams Grid */
        .teams-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .team-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s;
            cursor: pointer;
            border: 2px solid transparent;
        }

        .team-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.2);
            border-color: #3498db;
        }

        .team-header {
            padding: 20px;
            color: white;
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
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .team-short {
            font-size: 12px;
            opacity: 0.9;
        }

        .team-stats {
            padding: 15px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            text-align: center;
            background: #f8f9fa;
        }

        .stat-item {
            padding: 5px;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 11px;
            margin-bottom: 3px;
        }

        .stat-value {
            font-size: 16px;
            font-weight: bold;
            color: #2c3e50;
        }

        .purse-bar {
            height: 6px;
            background: #ecf0f1;
            margin: 0 15px 15px;
            border-radius: 3px;
            overflow: hidden;
        }

        .purse-fill {
            height: 100%;
            background: linear-gradient(90deg, #27ae60, #f39c12, #e74c3c);
            transition: width 0.3s;
        }

        .view-details {
            padding: 10px 15px;
            background: #f8f9fa;
            text-align: center;
            color: #3498db;
            font-size: 13px;
            font-weight: 600;
            border-top: 1px solid #ecf0f1;
        }

        .view-details i {
            margin-left: 5px;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(5px);
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 1000px;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 25px;
            color: white;
            display: flex;
            align-items: center;
            gap: 20px;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .modal-logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 4px solid rgba(255,255,255,0.3);
        }

        .modal-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .modal-logo i {
            font-size: 40px;
            color: #2c3e50;
        }

        .modal-title {
            flex: 1;
        }

        .modal-title h2 {
            font-size: 28px;
            margin-bottom: 5px;
        }

        .modal-title p {
            opacity: 0.9;
            font-size: 14px;
        }

        .close-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .close-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: rotate(90deg);
        }

        .modal-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            padding: 20px;
            background: #f8f9fa;
        }

        .modal-stat {
            text-align: center;
            padding: 15px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .modal-stat .label {
            color: #7f8c8d;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .modal-stat .value {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }

        .players-table {
            padding: 20px;
        }

        .players-table h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 18px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table thead {
            background: #f8f9fa;
        }

        table th {
            padding: 12px;
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            color: #7f8c8d;
        }

        table td {
            padding: 12px;
            border-bottom: 1px solid #ecf0f1;
        }

        .player-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .player-thumb {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
        }

        .default-thumb {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .price-cell {
            font-weight: bold;
            color: #27ae60;
        }

        .export-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 50px;
            padding: 15px 30px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 5px 20px rgba(52,152,219,0.4);
            transition: all 0.3s;
            z-index: 100;
        }

        .export-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(52,152,219,0.6);
        }

        .empty-state {
            text-align: center;
            padding: 50px;
            color: #95a5a6;
        }

        .empty-state i {
            font-size: 60px;
            margin-bottom: 15px;
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
                <li class="active"><a href="team-players.php"><i class="fas fa-users-between-lines"></i> Team Players</a></li>
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
                    <i class="fas fa-users-between-lines"></i>
                </div>
                <div>
                    <h1>Team-wise Players</h1>
                    <p>Click on any team to view their purchased players</p>
                </div>
            </div>

            <!-- Overall Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon teams">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Teams</h3>
                        <div class="value"><?php echo $overall_stats['total_teams']; ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon players">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Players Sold</h3>
                        <div class="value"><?php echo $overall_stats['total_players_sold']; ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon amount">
                        <i class="fas fa-rupee-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Amount</h3>
                        <div class="value">₹<?php echo number_format($overall_stats['total_amount'], 2); ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon avg">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Avg per Team</h3>
                        <div class="value">₹<?php echo number_format($overall_stats['avg_spent'], 2); ?></div>
                    </div>
                </div>
            </div>

            <!-- Teams Grid -->
            <div class="teams-grid">
                <?php foreach($teams_array as $team): 
                    $players_count = isset($team_players_data[$team['id']]['players']) ? count($team_players_data[$team['id']]['players']) : 0;
                    $spent_percentage = ($team['total_purse'] > 0) ? ($team['total_spent'] / $team['total_purse'] * 100) : 0;
                ?>
                <div class="team-card" onclick="showTeamModal(<?php echo $team['id']; ?>)">
                    <div class="team-header" style="background: <?php echo $team['team_color']; ?>;">
                        <div class="team-logo">
                            <?php if(!empty($team['logo_url'])): ?>
                                <img src="../<?php echo $team['logo_url']; ?>" alt="<?php echo $team['team_name']; ?>">
                            <?php else: ?>
                                <i class="fas fa-shield-alt"></i>
                            <?php endif; ?>
                        </div>
                        <div class="team-info">
                            <div class="team-name"><?php echo $team['team_name']; ?></div>
                            <div class="team-short"><?php echo $team['team_short_code']; ?></div>
                        </div>
                    </div>
                    
                    <div class="team-stats">
                        <div class="stat-item">
                            <div class="stat-label">Players</div>
                            <div class="stat-value"><?php echo $players_count; ?>/<?php echo $team['max_players']; ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Spent</div>
                            <div class="stat-value">₹<?php echo number_format($team['total_spent'] ?? 0, 2); ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Remaining</div>
                            <div class="stat-value">₹<?php echo number_format($team['remaining_purse'] ?? $team['total_purse'], 2); ?></div>
                        </div>
                    </div>

                    <div class="purse-bar">
                        <div class="purse-fill" style="width: <?php echo $spent_percentage; ?>%;"></div>
                    </div>
                    
                    <div class="view-details">
                        <span>Click to view players</span>
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <button class="export-btn" onclick="exportAllTeamsData()">
                <i class="fas fa-download"></i> Export All Teams Data
            </button>
        </div>
    </div>

    <!-- Team Modal -->
    <div id="teamModal" class="modal">
        <div class="modal-content" id="modalContent">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>

    <script>
    // Store team data
    const teamPlayersData = <?php echo json_encode($team_players_data); ?>;

    function showTeamModal(teamId) {
        const team = teamPlayersData[teamId];
        if (!team) return;
        
        const modal = document.getElementById('teamModal');
        const modalContent = document.getElementById('modalContent');
        
        let playersHtml = '';
        
        if (team.players && team.players.length > 0) {
            team.players.forEach(player => {
                playersHtml += `
                    <tr>
                        <td>
                            <div class="player-info">
                                ${player.player_image ? 
                                    `<img src="../${player.player_image}" class="player-thumb">` : 
                                    `<div class="default-thumb"><i class="fas fa-user-circle"></i></div>`
                                }
                                <div>
                                    <strong>${player.player_name}</strong><br>
                                    <small>${player.registration_no}</small>
                                </div>
                            </div>
                        </td>
                        <td>${player.age} yrs</td>
                        <td>${player.playing_role}</td>
                        <td>${player.category_name}</td>
                        <td class="price-cell">₹${Number(player.purchase_price).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
                        <td>${new Date(player.purchase_date).toLocaleDateString()}</td>
                    </tr>
                `;
            });
        } else {
            playersHtml = `
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <i class="fas fa-users-slash"></i>
                            <p>No players purchased yet</p>
                        </div>
                    </td>
                </tr>
            `;
        }
        
        modalContent.innerHTML = `
            <div class="modal-header" style="background: ${team.info.team_color};">
                <div class="modal-logo">
                    ${team.info.logo_url ? 
                        `<img src="../${team.info.logo_url}" alt="${team.info.team_name}">` : 
                        `<i class="fas fa-shield-alt"></i>`
                    }
                </div>
                <div class="modal-title">
                    <h2>${team.info.team_name}</h2>
                    <p>${team.info.team_short_code} • Max Players: ${team.info.max_players}</p>
                </div>
                <button class="close-btn" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-stats">
                <div class="modal-stat">
                    <div class="label">Players Bought</div>
                    <div class="value">${team.players.length}/${team.info.max_players}</div>
                </div>
                <div class="modal-stat">
                    <div class="label">Total Spent</div>
                    <div class="value">₹${Number(team.info.total_spent).toLocaleString('en-IN', {minimumFractionDigits: 2})}</div>
                </div>
                <div class="modal-stat">
                    <div class="label">Remaining Purse</div>
                    <div class="value">₹${Number(team.info.remaining_purse).toLocaleString('en-IN', {minimumFractionDigits: 2})}</div>
                </div>
            </div>
            
            <div class="players-table">
                <h3><i class="fas fa-users"></i> Purchased Players</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Player</th>
                            <th>Age</th>
                            <th>Role</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${playersHtml}
                    </tbody>
                </table>
            </div>
        `;
        
        modal.classList.add('active');
    }

    function closeModal() {
        document.getElementById('teamModal').classList.remove('active');
    }

    function exportAllTeamsData() {
        let csv = "Team,Player Name,Registration No,Age,Role,Category,Purchase Price,Purchase Date\n";
        
        <?php foreach($team_players_data as $team_id => $data): 
            $team = $data['info'];
            foreach($data['players'] as $player): ?>
            csv += "<?php echo $team['team_name']; ?>,<?php echo $player['player_name']; ?>,<?php echo $player['registration_no']; ?>,<?php echo $player['age']; ?>,<?php echo $player['playing_role']; ?>,<?php echo $player['category_name']; ?>,<?php echo $player['purchase_price']; ?>,<?php echo $player['purchase_date']; ?>\n";
        <?php 
            endforeach;
        endforeach; ?>
        
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'all_teams_players.csv';
        a.click();
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('teamModal');
        if (event.target == modal) {
            closeModal();
        }
    }
    </script>
</body>
</html>