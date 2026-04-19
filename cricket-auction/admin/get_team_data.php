<?php
session_start();
include '../includes/db-config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$id = intval($_GET['id']);
$result = $conn->query("SELECT * FROM teams WHERE id = $id");
$team = $result->fetch_assoc();

// Fix logo path if needed
if (!empty($team['logo_url'])) {
    // Ensure we only store the correct relative path
    $team['logo_url'] = 'uploads/team_logos/' . basename($team['logo_url']);
}

header('Content-Type: application/json');
echo json_encode($team);
?>