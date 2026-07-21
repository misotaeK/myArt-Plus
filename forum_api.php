<?php
session_start();
require_once "config.php";

// Konu/gönderi okuma misafirlere de açık; sadece yazma/yönetim işlemleri giriş ister.
$is_logged_in = isset($_SESSION['user_id']);
$current_lang = in_array($_SESSION['lang'] ?? '', ['tr', 'en']) ? $_SESSION['lang'] : 'en';
require_once "lang/" . $current_lang . ".php";

$user_id = $is_logged_in ? (int)$_SESSION['user_id'] : 0;
$is_admin = $is_logged_in && ($_SESSION['role'] ?? '') === 'admin';
$action = $_POST['action'] ?? '';

$write_actions = ['reply', 'delete_post', 'toggle_pin', 'toggle_lock', 'delete_thread', 'new_thread'];
if (in_array($action, $write_actions) && !$is_logged_in) {
    exit('Unauthorized');
}

if ($action === 'get_threads') {
    $q = trim($_POST['q'] ?? '');
    $board_id = (int)($_POST['board_id'] ?? 0);
    $page = max(1, (int)($_POST['page'] ?? 1));
    $per_page = 15;
    $offset = ($page - 1) * $per_page;

    $where = [];
    $params = [];
    $types = '';
    if ($q !== '') {
        $where[] = "t.title LIKE ?";
        $params[] = "%$q%";
        $types .= 's';
    }
    if ($board_id > 0) {
        $where[] = "t.board_id = ?";
        $params[] = $board_id;
        $types .= 'i';
    }
    $where_sql = count($where) > 0 ? "WHERE " . implode(' AND ', $where) : '';

    $count_sql = "SELECT COUNT(*) as total FROM forum_threads t $where_sql";
    $count_stmt = mysqli_prepare($conn, $count_sql);
    if ($types !== '') mysqli_stmt_bind_param($count_stmt, $types, ...$params);
    mysqli_stmt_execute($count_stmt);
    $total = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'];
    $total_pages = max(1, ceil($total / $per_page));

    $sql = "SELECT t.id, t.title, t.is_pinned, t.is_locked, t.views, t.last_activity_at, t.user_id, t.board_id,
            b.name AS board_name, b.icon AS board_icon,
            u.username, u.profile_pic,
            (SELECT COUNT(*) FROM forum_posts p WHERE p.thread_id = t.id) as post_count,
            (SELECT u2.username FROM forum_posts p2 JOIN users u2 ON p2.user_id = u2.id WHERE p2.thread_id = t.id ORDER BY p2.created_at DESC LIMIT 1) as last_poster
            FROM forum_threads t
            JOIN forum_boards b ON t.board_id = b.id
            JOIN users u ON t.user_id = u.id
            $where_sql
            ORDER BY t.is_pinned DESC, t.last_activity_at DESC
            LIMIT ? OFFSET ?";
    $list_params = $params;
    $list_params[] = $per_page;
    $list_params[] = $offset;
    $list_types = $types . 'ii';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $list_types, ...$list_params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $threads = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['reply_count'] = max(0, (int)$row['post_count'] - 1);
        $threads[] = $row;
    }
    echo json_encode(['threads' => $threads, 'page' => $page, 'total_pages' => $total_pages]);
    exit();
}

if ($action === 'get_thread') {
    $thread_id = (int)$_POST['thread_id'];

    $t_stmt = mysqli_prepare($conn, "SELECT t.*, b.name AS board_name, b.id AS board_id FROM forum_threads t JOIN forum_boards b ON t.board_id = b.id WHERE t.id = ?");
    mysqli_stmt_bind_param($t_stmt, "i", $thread_id);
    mysqli_stmt_execute($t_stmt);
    $thread = mysqli_fetch_assoc(mysqli_stmt_get_result($t_stmt));

    if (!$thread) {
        echo json_encode(['error' => 'not_found']);
        exit();
    }

    mysqli_query($conn, "UPDATE forum_threads SET views = views + 1 WHERE id = " . $thread_id);

    $p_stmt = mysqli_prepare($conn, "SELECT p.*, u.username, u.profile_pic FROM forum_posts p JOIN users u ON p.user_id = u.id WHERE p.thread_id = ? ORDER BY p.created_at ASC");
    mysqli_stmt_bind_param($p_stmt, "i", $thread_id);
    mysqli_stmt_execute($p_stmt);
    $p_result = mysqli_stmt_get_result($p_stmt);
    $posts = [];
    while ($row = mysqli_fetch_assoc($p_result)) {
        $row['is_mine'] = (int)$row['user_id'] === $user_id;
        $row['can_delete'] = $row['is_mine'] || $is_admin;
        $posts[] = $row;
    }

    $thread['can_manage'] = $is_admin || (int)$thread['user_id'] === $user_id;
    echo json_encode(['thread' => $thread, 'posts' => $posts]);
    exit();
}

if ($action === 'reply') {
    $thread_id = (int)$_POST['thread_id'];
    $message = trim($_POST['message'] ?? '');

    $thread = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id, title, is_locked FROM forum_threads WHERE id = " . $thread_id));
    if (!$thread || $thread['is_locked'] || $message === '') {
        echo json_encode(['success' => false]);
        exit();
    }

    $stmt = mysqli_prepare($conn, "INSERT INTO forum_posts (thread_id, user_id, message) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "iis", $thread_id, $user_id, $message);
    mysqli_stmt_execute($stmt);

    mysqli_query($conn, "UPDATE forum_threads SET last_activity_at = NOW() WHERE id = " . $thread_id);

    if ((int)$thread['user_id'] !== $user_id) {
        $replier = mysqli_fetch_assoc(mysqli_query($conn, "SELECT username FROM users WHERE id = $user_id"));
        $notif_msg = str_replace([':user', ':title'], [$replier['username'], $thread['title']], $t['notif_thread_reply']);
        add_notification($conn, $thread['user_id'], 'thread_reply', $notif_msg, 'forum.php?thread=' . $thread_id, $user_id);
    }

    echo json_encode(['success' => true]);
    exit();
}

if ($action === 'delete_post') {
    $thread_id = (int)$_POST['thread_id'];
    $post_id = (int)$_POST['post_id'];

    $post = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id FROM forum_posts WHERE id = $post_id AND thread_id = $thread_id"));
    if (!$post || (!$is_admin && (int)$post['user_id'] !== $user_id)) {
        echo json_encode(['success' => false]);
        exit();
    }

    $first_post = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM forum_posts WHERE thread_id = $thread_id ORDER BY created_at ASC LIMIT 1"));
    if ($first_post && (int)$first_post['id'] === $post_id) {
        mysqli_query($conn, "DELETE FROM forum_threads WHERE id = " . $thread_id);
        echo json_encode(['success' => true, 'thread_deleted' => true]);
        exit();
    }

    mysqli_query($conn, "DELETE FROM forum_posts WHERE id = " . $post_id);
    echo json_encode(['success' => true, 'thread_deleted' => false]);
    exit();
}

if ($action === 'toggle_pin' && $is_admin) {
    $thread_id = (int)$_POST['thread_id'];
    mysqli_query($conn, "UPDATE forum_threads SET is_pinned = 1 - is_pinned WHERE id = " . $thread_id);
    echo json_encode(['success' => true]);
    exit();
}

if ($action === 'toggle_lock' && $is_admin) {
    $thread_id = (int)$_POST['thread_id'];
    mysqli_query($conn, "UPDATE forum_threads SET is_locked = 1 - is_locked WHERE id = " . $thread_id);
    echo json_encode(['success' => true]);
    exit();
}

if ($action === 'delete_thread') {
    $thread_id = (int)$_POST['thread_id'];
    $thread = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id FROM forum_threads WHERE id = " . $thread_id));
    if ($thread && ($is_admin || (int)$thread['user_id'] === $user_id)) {
        mysqli_query($conn, "DELETE FROM forum_threads WHERE id = " . $thread_id);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit();
}

if ($action === 'new_thread') {
    $board_id = (int)$_POST['board_id'];
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');

    $board = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM forum_boards WHERE id = " . $board_id));
    if (!$board || $title === '' || $message === '') {
        echo json_encode(['success' => false]);
        exit();
    }

    $stmt = mysqli_prepare($conn, "INSERT INTO forum_threads (board_id, user_id, title) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "iis", $board_id, $user_id, $title);
    mysqli_stmt_execute($stmt);
    $new_thread_id = mysqli_insert_id($conn);

    $post_stmt = mysqli_prepare($conn, "INSERT INTO forum_posts (thread_id, user_id, message) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($post_stmt, "iis", $new_thread_id, $user_id, $message);
    mysqli_stmt_execute($post_stmt);

    echo json_encode(['success' => true, 'thread_id' => $new_thread_id]);
    exit();
}
