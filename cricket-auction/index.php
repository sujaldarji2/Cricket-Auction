<?php
session_start();
include 'includes/db-config.php';

// Fetch teams for display
$teams_query = "SELECT id, team_name, team_short_code, team_color, logo_url, 
                total_purse, spent_amount, players_bought, max_players
                FROM teams 
                WHERE is_active = 1 OR is_active IS NULL 
                ORDER BY team_name";
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
            'remaining' => floatval($team['total_purse'] - $team['spent_amount']),
            'spent' => floatval($team['spent_amount']),
            'players_bought' => intval($team['players_bought']),
            'max_players' => intval($team['max_players'])
        ];
    }
}

// Get auction progress for reference (but not displayed)
$progress_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN COALESCE(a.status, 'pending') = 'sold' THEN 1 ELSE 0 END) as sold,
    SUM(CASE WHEN COALESCE(a.status, 'pending') = 'unsold' THEN 1 ELSE 0 END) as unsold
    FROM players p
    LEFT JOIN auction_status a ON p.id = a.player_id";

$progress_result = $conn->query($progress_query);
$progress = $progress_result ? $progress_result->fetch_assoc() : ['total' => 0, 'sold' => 0, 'unsold' => 0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cricket Auction - Live Dashboard</title>
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
            transition: background-color 0.5s ease;
        }

        body.sold-celebration {
            animation: soldFlash 0.5s ease;
        }

        @keyframes soldFlash {
            0% { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
            25% { background: #ff4444; }
            50% { background: #ff8888; }
            75% { background: #ff4444; }
            100% { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        }

        /* Confetti Animation */
        .confetti {
            position: fixed;
            width: 10px;
            height: 10px;
            background-color: #f0f0f0;
            position: absolute;
            top: -10px;
            border-radius: 0%;
            animation: confetti-fall 3s ease-in-out infinite;
            z-index: 9999;
        }

        @keyframes confetti-fall {
            0% { transform: translateY(0vh) rotate(0deg); opacity: 1; }
            100% { transform: translateY(100vh) rotate(720deg); opacity: 0; }
        }

        .sold-overlay {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 120px;
            font-weight: 900;
            color: white;
            text-shadow: 0 0 20px rgba(255,0,0,0.8), 0 0 40px rgba(255,0,0,0.5);
            z-index: 10000;
            animation: soldPop 1s ease-out forwards;
            pointer-events: none;
            text-transform: uppercase;
            letter-spacing: 10px;
        }

        @keyframes soldPop {
            0% { transform: translate(-50%, -50%) scale(0); opacity: 0; }
            50% { transform: translate(-50%, -50%) scale(1.2); opacity: 1; }
            100% { transform: translate(-50%, -50%) scale(1); opacity: 0; }
        }

        .header {
            background: rgba(255,255,255,0.95);
            padding: 15px 30px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(10px);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo i {
            font-size: 40px;
            color: #667eea;
        }

        .logo h1 {
            font-size: 28px;
            color: #2c3e50;
            font-weight: 700;
        }

        .live-badge {
            background: #e74c3c;
            color: white;
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }

        .main-content {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        /* Player Card */
        .player-card {
            background: white;
            border-radius: 30px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .player-card.sold {
            animation: cardSold 1s ease;
        }

        @keyframes cardSold {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); background: #fff0f0; }
            100% { transform: scale(1); }
        }

        .player-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            opacity: 0.1;
            z-index: 0;
        }

        .player-content {
            position: relative;
            z-index: 1;
            display: flex;
            gap: 50px;
            align-items: center;
            flex-wrap: wrap;
        }

        .player-image-section {
            flex: 0 0 300px;
            text-align: center;
        }

        .player-image-container {
            width: 250px;
            height: 250px;
            margin: 0 auto;
            border-radius: 50%;
            overflow: hidden;
            border: 5px solid #667eea;
            box-shadow: 0 10px 30px rgba(102,126,234,0.4);
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
            color: #667eea;
            font-size: 100px;
        }

        .player-details-section {
            flex: 1;
        }

        .player-name {
            font-size: 56px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
            line-height: 1.2;
        }

        .player-reg {
            font-size: 20px;
            color: #7f8c8d;
            margin-bottom: 20px;
            letter-spacing: 2px;
        }

        .player-info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 25px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 15px;
        }

        .info-item {
            text-align: center;
        }

        .info-label {
            color: #7f8c8d;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 22px;
            font-weight: bold;
            color: #2c3e50;
        }

        .price-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 20px;
            text-align: center;
            display: inline-block;
            min-width: 300px;
        }

        .price-label {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 10px;
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
            margin-top: 15px;
            font-size: 20px;
        }

        .bidder-logo {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
        }

        /* Teams Grid */
        .teams-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .team-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }

        .team-card.active-bid {
            transform: scale(1.02);
            box-shadow: 0 10px 30px rgba(39,174,96,0.3);
            border: 2px solid #27ae60;
        }

        .team-header {
            padding: 15px;
            color: white;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .team-logo {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .team-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .team-logo i {
            font-size: 25px;
            color: #2c3e50;
        }

        .team-info {
            flex: 1;
        }

        .team-name {
            font-weight: 600;
            font-size: 18px;
            margin-bottom: 3px;
        }

        .team-short {
            font-size: 12px;
            opacity: 0.9;
        }

        .team-stats {
            padding: 15px;
            background: #f8f9fa;
            display: flex;
            justify-content: space-between;
            font-size: 14px;
        }

        .spent-amount {
            color: #27ae60;
            font-weight: bold;
        }

        .remaining-amount {
            color: #e74c3c;
            font-weight: bold;
        }

        /* Bid History */
        .bid-history {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
        }

        .bid-history h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .bid-list {
            max-height: 200px;
            overflow-y: auto;
        }

        .bid-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            border-bottom: 1px solid #ecf0f1;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .bid-item img {
            width: 25px;
            height: 25px;
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

        .loading {
            text-align: center;
            padding: 100px;
            color: white;
        }

        .no-player {
            text-align: center;
            padding: 100px;
            color: white;
        }

        /* Footer */
        .footer {
            background: rgba(255,255,255,0.9);
            padding: 15px;
            text-align: center;
            margin-top: 50px;
            color: #2c3e50;
            border-radius: 10px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .player-content {
                flex-direction: column;
                text-align: center;
            }
            
            .player-name {
                font-size: 36px;
            }
            
            .player-info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <i class="fas fa-baseball-ball"></i>
            <div>
                <h1>Cricket Auction</h1>
            </div>
        </div>
        <div class="live-badge">
            <i class="fas fa-circle"></i> LIVE AUCTION
        </div>
    </div>

    <div class="main-content">
        <!-- Player Card -->
        <div class="player-card" id="playerCard">
            <div id="loadingIndicator" class="loading" style="display: none;">
                <i class="fas fa-spinner fa-spin fa-3x"></i>
                <p style="margin-top: 20px;">Loading players...</p>
            </div>

            <div id="playerContent" style="display: none;">
                <div class="player-content">
                    <div class="player-image-section">
                        <div class="player-image-container">
                            <div class="default-player-icon" id="defaultPlayerIcon">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <img src="" alt="Player" class="player-image" id="playerImage" style="display: none;">
                        </div>
                    </div>
                    
                    <div class="player-details-section">
                        <div class="player-name" id="playerName">Player Name</div>
                        <div class="player-reg" id="playerRegNo">REG-001</div>
                        
                        <div class="player-info-grid">
                            <div class="info-item">
                                <div class="info-label">Age</div>
                                <div class="info-value" id="playerAge">25</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Role</div>
                                <div class="info-value" id="playerRole">Batsman</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Base Price</div>
                                <div class="info-value" id="playerBasePrice">₹30,000</div>
                            </div>
                        </div>

                        <div class="price-box">
                            <div class="price-label">Current Bid</div>
                            <div class="current-price" id="currentBid">₹30,000</div>
                            <div class="current-bidder" id="currentBidder"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="noPlayer" style="display: none; text-align: center; padding: 50px;">
                <i class="fas fa-hourglass-end" style="font-size: 80px; color: #bdc3c7;"></i>
                <h2 style="color: #7f8c8d; margin-top: 20px;">Auction Complete</h2>
                <p>All players have been processed. Thank you for watching!</p>
            </div>
        </div>

        <!-- Teams Grid -->
        <h2 style="color: white; margin: 30px 0 20px;"><i class="fas fa-users-cog"></i> Teams</h2>
        <div class="teams-grid" id="teamsGrid">
            <?php foreach($teams_array as $team): ?>
            <div class="team-card" id="team-card-<?php echo $team['id']; ?>">
                <div class="team-header" style="background: <?php echo $team['color']; ?>;">
                    <div class="team-logo">
                        <?php if(!empty($team['logo'])): ?>
                            <img src="<?php echo $team['logo']; ?>" alt="<?php echo $team['name']; ?>">
                        <?php else: ?>
                            <i class="fas fa-shield-alt"></i>
                        <?php endif; ?>
                    </div>
                    <div class="team-info">
                        <div class="team-name"><?php echo $team['name']; ?></div>
                        <div class="team-short"><?php echo $team['short']; ?></div>
                    </div>
                </div>
                <div class="team-stats">
                    <span class="spent-amount">₹<span id="team-spent-<?php echo $team['id']; ?>"><?php echo number_format($team['spent']); ?></span> spent</span>
                    <span class="remaining-amount">₹<span id="team-remaining-<?php echo $team['id']; ?>"><?php echo number_format($team['remaining']); ?></span> left</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Bid History -->
        <div class="bid-history">
            <h3><i class="fas fa-history"></i> Bid History</h3>
            <div class="bid-list" id="bidHistoryList">
                <p style="text-align: center; color: #95a5a6;">No bids yet</p>
            </div>
        </div>
    </div>

    <footer class="footer">
        <p><i class="fas fa-baseball-ball"></i> Live Cricket Auction | Updates every 1 second</p>
    </footer>

    <script>
    // Team data from PHP
    const teams = <?php echo json_encode($teams_array); ?>;
    let currentPlayer = null;
    let bidHistory = [];
    let lastPlayerId = null;
    let soldCelebrationTimeout = null;

    // Load initial player
    document.addEventListener('DOMContentLoaded', function() {
        loadLiveAuction();
        // Refresh every 1 second
        setInterval(loadLiveAuction, 1000);
    });

    function loadLiveAuction() {
        fetch('admin/auction-control.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_current_player&category=all&random=false'
        })
        .then(response => response.json())
        .then(data => {
            if(data.success && data.player) {
                // Check if player is sold
                if (data.player.auction_status === 'sold' && lastPlayerId !== data.player.id) {
                    triggerSoldCelebration(data.player);
                }
                
                currentPlayer = data.player;
                displayPlayer(data.player);
                document.getElementById('playerContent').style.display = 'block';
                document.getElementById('noPlayer').style.display = 'none';
                document.getElementById('loadingIndicator').style.display = 'none';
                lastPlayerId = data.player.id;
            } else {
                document.getElementById('playerContent').style.display = 'none';
                document.getElementById('noPlayer').style.display = 'block';
                document.getElementById('loadingIndicator').style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }

    function triggerSoldCelebration(player) {
        // Clear any existing timeout
        if (soldCelebrationTimeout) {
            clearTimeout(soldCelebrationTimeout);
        }

        // Create confetti
        for (let i = 0; i < 100; i++) {
            createConfetti();
        }

        // Add sold overlay
        const overlay = document.createElement('div');
        overlay.className = 'sold-overlay';
        overlay.textContent = 'SOLD';
        document.body.appendChild(overlay);

        // Add flash effect to body
        document.body.classList.add('sold-celebration');

        // Add sold class to player card
        const playerCard = document.getElementById('playerCard');
        playerCard.classList.add('sold');

        // Remove effects after animation
        soldCelebrationTimeout = setTimeout(() => {
            document.body.classList.remove('sold-celebration');
            playerCard.classList.remove('sold');
            overlay.remove();
        }, 3000);
    }

    function createConfetti() {
        const confetti = document.createElement('div');
        confetti.className = 'confetti';
        confetti.style.left = Math.random() * 100 + 'vw';
        confetti.style.backgroundColor = `hsl(${Math.random() * 360}, 100%, 50%)`;
        confetti.style.width = Math.random() * 10 + 5 + 'px';
        confetti.style.height = Math.random() * 10 + 5 + 'px';
        confetti.style.animationDuration = Math.random() * 2 + 2 + 's';
        confetti.style.animationDelay = Math.random() * 2 + 's';
        document.body.appendChild(confetti);

        // Remove confetti after animation
        setTimeout(() => {
            confetti.remove();
        }, 5000);
    }

    function displayPlayer(player) {
        document.getElementById('playerName').textContent = player.player_name;
        document.getElementById('playerRegNo').textContent = player.registration_no;
        document.getElementById('playerAge').textContent = player.age + ' years';
        document.getElementById('playerRole').textContent = player.playing_role;
        document.getElementById('playerBasePrice').textContent = '₹' + player.base_price.toLocaleString('en-IN');
        
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
        
        // Update current bid
        document.getElementById('currentBid').textContent = '₹' + player.current_bid.toLocaleString('en-IN');
        
        // Show current bidder
        const bidderDiv = document.getElementById('currentBidder');
        if(player.current_team) {
            const team = teams.find(t => t.name === player.current_team);
            if(team && team.logo) {
                bidderDiv.innerHTML = `
                    <img src="${team.logo}" class="bidder-logo">
                    <span>${player.current_team}</span>
                `;
            } else {
                bidderDiv.innerHTML = `<span>${player.current_team}</span>`;
            }
        } else {
            bidderDiv.innerHTML = '';
        }
        
        // Update bid history
        if(player.bid_history && player.bid_history.length > 0) {
            bidHistory = player.bid_history;
            displayBidHistory();
        }
        
        // Update team highlights and spent amounts
        updateTeamHighlights(player);
    }

    function updateTeamHighlights(player) {
        // Reset all team highlights
        document.querySelectorAll('.team-card').forEach(card => {
            card.classList.remove('active-bid');
        });
        
        // Highlight current bidding team
        if(player.current_team_id) {
            const activeCard = document.getElementById(`team-card-${player.current_team_id}`);
            if(activeCard) {
                activeCard.classList.add('active-bid');
            }
        }
        
        // Update team spent amounts (this would need another API call for real accuracy)
        // For now, we'll just use the static data
    }

    function displayBidHistory() {
        let html = '';
        bidHistory.slice().reverse().forEach((bid) => {
            const date = new Date(bid.time);
            const timeStr = date.toLocaleTimeString();
            const team = teams.find(t => t.name === bid.team);
            
            html += `
                <div class="bid-item">
                    ${team && team.logo ? `<img src="${team.logo}" alt="${bid.team}">` : ''}
                    <span class="bid-team">${bid.team}</span>
                    <span class="bid-amount">₹${bid.bid.toLocaleString('en-IN')}</span>
                    <span class="bid-time">${timeStr}</span>
                </div>
            `;
        });
        
        document.getElementById('bidHistoryList').innerHTML = html || '<p style="text-align: center; color: #95a5a6;">No bids yet</p>';
    }
    </script>
</body>
</html>