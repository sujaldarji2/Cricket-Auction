<?php
session_start();
include '../includes/db-config.php';

header('Content-Type: application/json');

$teams_query = "SELECT id, team_name, 
                (total_purse - spent_amount) as remaining,
                players_bought
                FROM teams 
                ORDER BY team_name";
$result = $conn->query($teams_query);

$teams = [];
while($team = $result->fetch_assoc()) {
    $teams[] = [
        'id' => intval($team['id']),
        'remaining' => floatval($team['remaining']),
        'players_bought' => intval($team['players_bought'])
    ];
}

echo json_encode($teams);
?>