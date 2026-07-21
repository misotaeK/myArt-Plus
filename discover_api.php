<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['user_id'])) exit('Unauthorized');

$user_id = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'get_artists') {
    $q = trim($_POST['q'] ?? '');
    $offset = max(0, (int)($_POST['offset'] ?? 0));
    $per_page = 4;

    $where = "WHERE account_status = 'active' AND id != ?";
    $params = [$user_id];
    $types = 'i';
    if ($q !== '') {
        $where .= " AND username LIKE ?";
        $params[] = "%$q%";
        $types .= 's';
    }

    $count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM users $where");
    mysqli_stmt_bind_param($count_stmt, $types, ...$params);
    mysqli_stmt_execute($count_stmt);
    $total = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'];

    if ($offset >= $total && $total > 0) {
        $offset = 0;
    }

    $sql = "SELECT id, username, profile_pic, comm_status FROM users $where ORDER BY username ASC LIMIT $per_page OFFSET $offset";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $artists = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $artists[] = $row;
    }

    echo json_encode(['artists' => $artists, 'total' => $total, 'offset' => $offset]);
    exit();
}
