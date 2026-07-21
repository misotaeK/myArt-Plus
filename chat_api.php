<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized');
}

$current_lang = in_array($_SESSION['lang'] ?? '', ['tr', 'en']) ? $_SESSION['lang'] : 'en';
require_once "lang/" . $current_lang . ".php";

$user_id = $_SESSION['user_id'];
$action = isset($_POST['action']) ? $_POST['action'] : '';

// messages tablosunda attachment kolonları yoksa ekle (eski kurulumlarla uyumluluk için)
$attach_col_check = mysqli_query($conn, "SHOW COLUMNS FROM messages LIKE 'attachment'");
if ($attach_col_check && mysqli_num_rows($attach_col_check) === 0) {
    mysqli_query($conn, "ALTER TABLE messages ADD COLUMN attachment VARCHAR(255) DEFAULT NULL, ADD COLUMN attachment_name VARCHAR(255) DEFAULT NULL");
}

// messages tablosunda is_read kolonu yoksa ekle (eski kurulumlarla uyumluluk için)
$read_col_check = mysqli_query($conn, "SHOW COLUMNS FROM messages LIKE 'is_read'");
if ($read_col_check && mysqli_num_rows($read_col_check) === 0) {
    mysqli_query($conn, "ALTER TABLE messages ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0");
}

function is_friend($conn, $user_id, $other_id) {
    $stmt = mysqli_prepare($conn, "SELECT 1 FROM friendships WHERE status = 'accepted' AND ((requester_id = ? AND addressee_id = ?) OR (requester_id = ? AND addressee_id = ?))");
    mysqli_stmt_bind_param($stmt, "iiii", $user_id, $other_id, $other_id, $user_id);
    mysqli_stmt_execute($stmt);
    return mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0;
}

if ($action == 'get_users') {
    // Sadece kabul edilmiş arkadaşları getir (chat artık sadece arkadaşlar arasında)
    $stmt = mysqli_prepare($conn, "SELECT u.id, u.username, u.profile_pic
        FROM users u
        JOIN friendships f ON (
            (f.requester_id = ? AND f.addressee_id = u.id) OR
            (f.addressee_id = ? AND f.requester_id = u.id)
        )
        WHERE f.status = 'accepted'
        ORDER BY u.username ASC");
    mysqli_stmt_bind_param($stmt, "ii", $user_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $users = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
    echo json_encode($users);
    exit();
}

if ($action == 'get_pending_requests') {
    // Bana gelen bekleyen arkadaşlık istekleri
    $stmt = mysqli_prepare($conn, "SELECT u.id, u.username, u.profile_pic
        FROM friendships f JOIN users u ON f.requester_id = u.id
        WHERE f.addressee_id = ? AND f.status = 'pending'
        ORDER BY f.created_at DESC");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $requests = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $requests[] = $row;
    }
    echo json_encode($requests);
    exit();
}

if ($action == 'respond_friend_request') {
    $from_id = (int)$_POST['from_id'];
    $accept = $_POST['accept'] === '1';

    if ($accept) {
        $stmt = mysqli_prepare($conn, "UPDATE friendships SET status = 'accepted' WHERE requester_id = ? AND addressee_id = ? AND status = 'pending'");
        mysqli_stmt_bind_param($stmt, "ii", $from_id, $user_id);
        mysqli_stmt_execute($stmt);
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            $me_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT username FROM users WHERE id = " . (int)$user_id));
            $notif_msg = str_replace(':user', $me_row['username'], $t['notif_friend_accepted'] ?? ':user accepted your friend request!');
            add_notification($conn, $from_id, 'friend_accepted', $notif_msg, 'profile.php?id=' . $user_id, $user_id);
        }
    } else {
        $stmt = mysqli_prepare($conn, "DELETE FROM friendships WHERE requester_id = ? AND addressee_id = ? AND status = 'pending'");
        mysqli_stmt_bind_param($stmt, "ii", $from_id, $user_id);
        mysqli_stmt_execute($stmt);
    }
    echo "success";
    exit();
}

if ($action == 'get_messages') {
    $chat_with = (int)$_POST['chat_with'];
    if (!is_friend($conn, $user_id, $chat_with)) {
        echo json_encode([]);
        exit();
    }
    $stmt = mysqli_prepare($conn, "SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC");
    mysqli_stmt_bind_param($stmt, "iiii", $user_id, $chat_with, $chat_with, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $messages = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $messages[] = $row;
    }

    // Bu konuşmayı görüntülemek, karşı taraftan gelen okunmamış mesajları okunmuş yapar
    $mark_read = mysqli_prepare($conn, "UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
    mysqli_stmt_bind_param($mark_read, "ii", $chat_with, $user_id);
    mysqli_stmt_execute($mark_read);

    echo json_encode($messages);
    exit();
}

if ($action == 'get_unread_count') {
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND is_read = 0");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    echo json_encode(['total' => (int)$row['total']]);
    exit();
}

if ($action == 'send_message') {
    $receiver_id = (int)$_POST['receiver_id'];
    $message = htmlspecialchars(trim($_POST['message']));

    $block_check = mysqli_prepare($conn, "SELECT 1 FROM blocks WHERE (user_id = ? AND blocked_user_id = ?) OR (user_id = ? AND blocked_user_id = ?)");
    mysqli_stmt_bind_param($block_check, "iiii", $user_id, $receiver_id, $receiver_id, $user_id);
    mysqli_stmt_execute($block_check);
    $is_blocked = mysqli_num_rows(mysqli_stmt_get_result($block_check)) > 0;

    if (!empty($message) && !$is_blocked && is_friend($conn, $user_id, $receiver_id)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iis", $user_id, $receiver_id, $message);
        mysqli_stmt_execute($stmt);
        echo "success";
    } else {
        echo "blocked";
    }
    exit();
}

if ($action == 'delete_message') {
    $message_id = (int)$_POST['message_id'];
    // Sadece kendi gönderdiği mesajı silebilir
    $stmt = mysqli_prepare($conn, "DELETE FROM messages WHERE id = ? AND sender_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $message_id, $user_id);
    mysqli_stmt_execute($stmt);
    echo "success";
    exit();
}

if ($action == 'send_file') {
    $receiver_id = (int)$_POST['receiver_id'];

    $block_check = mysqli_prepare($conn, "SELECT 1 FROM blocks WHERE (user_id = ? AND blocked_user_id = ?) OR (user_id = ? AND blocked_user_id = ?)");
    mysqli_stmt_bind_param($block_check, "iiii", $user_id, $receiver_id, $receiver_id, $user_id);
    mysqli_stmt_execute($block_check);
    $is_blocked = mysqli_num_rows(mysqli_stmt_get_result($block_check)) > 0;

    if ($is_blocked || !is_friend($conn, $user_id, $receiver_id)) {
        echo "blocked";
        exit();
    }
    if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] != 0) {
        echo "error";
        exit();
    }
    if ($_FILES['attachment']['size'] > 10 * 1024 * 1024) {
        echo "too_large";
        exit();
    }

    $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip', 'rar', 'png', 'jpg', 'jpeg', 'gif'];
    $orig_name = trim($_FILES['attachment']['name']);
    $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        echo "invalid_type";
        exit();
    }

    $target_dir = "uploads/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
    $safe_base = preg_replace('/[^A-Za-z0-9_\-.]/', '_', basename($orig_name));
    $new_filename = "attach_" . time() . "_" . $user_id . "_" . $safe_base;
    $target_file = $target_dir . $new_filename;

    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_file)) {
        $display_name = htmlspecialchars($orig_name);
        $stmt = mysqli_prepare($conn, "INSERT INTO messages (sender_id, receiver_id, message, attachment, attachment_name) VALUES (?, ?, '', ?, ?)");
        mysqli_stmt_bind_param($stmt, "iiss", $user_id, $receiver_id, $target_file, $display_name);
        mysqli_stmt_execute($stmt);
        echo "success";
    } else {
        echo "error";
    }
    exit();
}

if ($action == 'clear_history') {
    $chat_with = (int)$_POST['chat_with'];
    $stmt = mysqli_prepare($conn, "DELETE FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
    mysqli_stmt_bind_param($stmt, "iiii", $user_id, $chat_with, $chat_with, $user_id);
    mysqli_stmt_execute($stmt);
    echo "success";
    exit();
}
