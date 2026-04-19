<?php
session_start();
include '../includes/db-config.php';
requireLogin(); // This function checks if user is logged in

// Get counts for dashboard
$player_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM players"))['count'];
$under_15_count = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as count FROM players p JOIN categories c ON p.category_id = c.id WHERE c.category_name = 'Under 15'"))['count'];
$above_16_count = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as count FROM players p JOIN categories c ON p.category_id = c.id WHERE c.category_name = 'Above 16'"))['count'];

// Get total base price value
$total_value = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(base_price) as total FROM players"))['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Cricket Auction</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Cricket Auction</h2>
                <p>Welcome, <?php echo $_SESSION['admin_username']; ?></p>
            </div>
            <ul class="sidebar-menu">
                <li class="active">
                    <a href="index.php"><i class="fas fa-dashboard"></i> Dashboard</a>
                </li>
                <li>
                    <a href="add-category.php"><i class="fas fa-tags"></i> Add Category</a>
                </li>
                <li>
                    <a href="add-player.php"><i class="fas fa-user-plus"></i> Add Player</a>
                </li>
                <li>
                    <a href="view-players.php"><i class="fas fa-users"></i> View Players</a>
                </li>
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
            <div class="header">
                <h1>Dashboard</h1>
            </div>

            <div class="content-section">
                <!-- Stats Cards -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <!-- Total Players Card -->
                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 25px; border-radius: 10px; color: white;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <i class="fas fa-users" style="font-size: 48px; opacity: 0.8;"></i>
                            </div>
                            <div style="text-align: right;">
                                <h3 style="font-size: 14px; opacity: 0.9;">TOTAL PLAYERS</h3>
                                <p style="font-size: 36px; font-weight: bold;"><?php echo $player_count; ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Under 15 Card -->
                    <div style="background: linear-gradient(135deg, #3498db, #2980b9); padding: 25px; border-radius: 10px; color: white;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <i class="fas fa-child" style="font-size: 48px; opacity: 0.8;"></i>
                            </div>
                            <div style="text-align: right;">
                                <h3 style="font-size: 14px; opacity: 0.9;">UNDER 15</h3>
                                <p style="font-size: 36px; font-weight: bold;"><?php echo $under_15_count; ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Above 16 Card -->
                    <div style="background: linear-gradient(135deg, #e74c3c, #c0392b); padding: 25px; border-radius: 10px; color: white;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <i class="fas fa-male" style="font-size: 48px; opacity: 0.8;"></i>
                            </div>
                            <div style="text-align: right;">
                                <h3 style="font-size: 14px; opacity: 0.9;">ABOVE 16</h3>
                                <p style="font-size: 36px; font-weight: bold;"><?php echo $above_16_count; ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Total Value Card -->
                    <div style="background: linear-gradient(135deg, #27ae60, #229954); padding: 25px; border-radius: 10px; color: white;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <i class="fas fa-rupee-sign" style="font-size: 48px; opacity: 0.8;"></i>
                            </div>
                            <div style="text-align: right;">
                                <h3 style="font-size: 14px; opacity: 0.9;">TOTAL VALUE</h3>
                                <p style="font-size: 24px; font-weight: bold;">₹<?php echo number_format($total_value ?: 0, 2); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Players -->
                <div style="margin-top: 30px;">
                    <h2 style="color: #2c3e50; margin-bottom: 20px;">Recently Added Players</h2>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Reg No.</th>
                                    <th>Name</th>
                                    <th>Age</th>
                                    <th>Role</th>
                                    <th>Category</th>
                                    <th>Base Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $recent_players = mysqli_query($conn, "
                                    SELECT p.*, c.category_name 
                                    FROM players p 
                                    JOIN categories c ON p.category_id = c.id 
                                    ORDER BY p.created_at DESC 
                                    LIMIT 5
                                ");
                                
                                while($player = mysqli_fetch_assoc($recent_players)) {
                                    $badge_class = ($player['category_name'] == 'Under 15') ? 'badge-child' : 'badge-men';
                                    echo "<tr>";
                                    echo "<td><strong>" . $player['registration_no'] . "</strong></td>";
                                    echo "<td>" . $player['player_name'] . "</td>";
                                    echo "<td>" . $player['age'] . "</td>";
                                    echo "<td>" . $player['playing_role'] . "</td>";
                                    echo "<td><span class='badge " . $badge_class . "'>" . $player['category_name'] . "</span></td>";
                                    echo "<td>₹" . number_format($player['base_price'], 2) . "</td>";
                                    echo "</tr>";
                                }

                                if(mysqli_num_rows($recent_players) == 0) {
                                    echo "<tr><td colspan='6' style='text-align: center;'>No players added yet</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div style="margin-top: 30px; display: flex; gap: 15px;">
                    <a href="add-player.php" class="btn btn-primary" style="text-decoration: none;">
                        <i class="fas fa-plus-circle"></i> Add New Player
                    </a>
                    <a href="view-players.php" class="btn btn-success" style="text-decoration: none;">
                        <i class="fas fa-list"></i> View All Players
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="js/script.js"></script>
</body>
</html>