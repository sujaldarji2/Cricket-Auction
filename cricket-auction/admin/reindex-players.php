<?php
session_start();
include '../includes/db-config.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Function to reindex registration numbers
function reindexRegistrationNumbers($conn) {
    // Get all players ordered by ID
    $players = $conn->query("SELECT id FROM players ORDER BY id ASC");
    $new_reg_no = 101;
    $updated = 0;
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        while($player = $players->fetch_assoc()) {
            $new_reg = 'PLR' . $new_reg_no;
            $conn->query("UPDATE players SET registration_no = '$new_reg' WHERE id = " . $player['id']);
            $new_reg_no++;
            $updated++;
        }
        $conn->commit();
        return $updated;
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

// Perform reindex
$updated = reindexRegistrationNumbers($conn);

if ($updated !== false) {
    header("Location: view-players.php?msg=reindexed&count=" . $updated);
} else {
    header("Location: view-players.php?error=reindex_failed");
}
exit();
?>