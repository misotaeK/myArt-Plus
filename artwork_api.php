<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['user_id'])) exit('Unauthorized');
$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action == 'toggle_like') {
    $artwork_id = (int)$_POST['artwork_id'];

    $check = mysqli_prepare($conn, "SELECT id FROM artwork_likes WHERE artwork_id = ? AND user_id = ?");
    mysqli_stmt_bind_param($check, "ii", $artwork_id, $user_id);
    mysqli_stmt_execute($check);
    $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($check));

    if ($existing) {
        $stmt = mysqli_prepare($conn, "DELETE FROM artwork_likes WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $existing['id']);
        mysqli_stmt_execute($stmt);
        $liked = false;
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO artwork_likes (artwork_id, user_id) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, "ii", $artwork_id, $user_id);
        mysqli_stmt_execute($stmt);
        $liked = true;
    }

    $count_res = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM artwork_likes WHERE artwork_id = ?");
    mysqli_stmt_bind_param($count_res, "i", $artwork_id);
    mysqli_stmt_execute($count_res);
    $count_row = mysqli_fetch_assoc(mysqli_stmt_get_result($count_res));

    echo json_encode(['liked' => $liked, 'count' => (int)$count_row['total']]);
    exit();
}
