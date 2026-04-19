<?php
session_start();
include '../includes/db-config.php';
requireLogin();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $category_name = mysqli_real_escape_string($conn, $_POST['category_name']);
    
    // Set age ranges based on category
    if($category_name == 'Under 15') {
        $min_age = 5;
        $max_age = 15;
    } else {
        $min_age = 16;
        $max_age = 50;
    }
    
    // Check if category already exists
    $check = mysqli_query($conn, "SELECT * FROM categories WHERE category_name = '$category_name'");
    if(mysqli_num_rows($check) > 0) {
        $error = "Category already exists!";
    } else {
        $query = "INSERT INTO categories (category_name, min_age, max_age) VALUES ('$category_name', $min_age, $max_age)";
        
        if (mysqli_query($conn, $query)) {
            $message = "Category added successfully!";
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Category - Cricket Auction</title>
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
                <li>
                    <a href="index.php"><i class="fas fa-dashboard"></i> Dashboard</a>
                </li>
                <li class="active">
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
                <h1>Add Category</h1>
            </div>

            <div class="content-section">
                <?php if($message): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <?php if($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px;">
                    <!-- Under 15 Card -->
                    <div style="background: linear-gradient(135deg, #3498db, #2980b9); padding: 25px; border-radius: 8px; color: white; text-align: center;">
                        <i class="fas fa-child" style="font-size: 48px; margin-bottom: 15px;"></i>
                        <h3 style="font-size: 24px; margin-bottom: 10px;">Under 15</h3>
                        <p style="margin-bottom: 15px;">Age: 5 - 15 years</p>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="category_name" value="Under 15">
                            <button type="submit" class="btn" style="background: white; color: #2980b9; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;">
                                <i class="fas fa-plus"></i> Add This Category
                            </button>
                        </form>
                    </div>

                    <!-- Above 16 Card -->
                    <div style="background: linear-gradient(135deg, #e74c3c, #c0392b); padding: 25px; border-radius: 8px; color: white; text-align: center;">
                        <i class="fas fa-male" style="font-size: 48px; margin-bottom: 15px;"></i>
                        <h3 style="font-size: 24px; margin-bottom: 10px;">Above 16</h3>
                        <p style="margin-bottom: 15px;">Age: 16 - 50 years</p>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="category_name" value="Above 16">
                            <button type="submit" class="btn" style="background: white; color: #c0392b; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;">
                                <i class="fas fa-plus"></i> Add This Category
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Display Existing Categories -->
                <div style="margin-top: 20px;">
                    <h2 style="color: #2c3e50; margin-bottom: 20px;">Current Categories</h2>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Category Name</th>
                                    <th>Age Range</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $categories = mysqli_query($conn, "SELECT * FROM categories");
                                while($category = mysqli_fetch_assoc($categories)) {
                                    $badge_class = ($category['category_name'] == 'Under 15') ? 'badge-child' : 'badge-men';
                                    echo "<tr>";
                                    echo "<td>" . $category['id'] . "</td>";
                                    echo "<td>" . $category['category_name'] . "</td>";
                                    echo "<td>" . $category['min_age'] . " - " . $category['max_age'] . " years</td>";
                                    echo "<td><span class='badge " . $badge_class . "'>Active</span></td>";
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/script.js"></script>
</body>
</html>