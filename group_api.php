<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['user_id'])) exit('Unauthorized');

$current_lang = in_array($_SESSION['lang'] ?? '', ['tr', 'en']) ? $_SESSION['lang'] : 'en';
require_once "lang/" . $current_lang . ".php";

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

// group_messages tablosunda attachment kolonları yoksa ekle (eski kurulumlarla uyumluluk için)
$attach_col_check = mysqli_query($conn, "SHOW COLUMNS FROM group_messages LIKE 'attachment'");
if ($attach_col_check && mysqli_num_rows($attach_col_check) === 0) {
    mysqli_query($conn, "ALTER TABLE group_messages ADD COLUMN attachment VARCHAR(255) DEFAULT NULL, ADD COLUMN attachment_name VARCHAR(255) DEFAULT NULL");
}

function is_approved_group_member($conn, $group_id, $user_id) {
    $stmt = mysqli_prepare($conn, "SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ? AND status = 'approved'");
    mysqli_stmt_bind_param($stmt, "ii", $group_id, $user_id);
    mysqli_stmt_execute($stmt);
    return mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0;
}

function is_group_leader($conn, $group_id, $user_id) {
    $stmt = mysqli_prepare($conn, "SELECT 1 FROM `groups` WHERE id = ? AND creator_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $group_id, $user_id);
    mysqli_stmt_execute($stmt);
    return mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0;
}

if ($action == 'send') {
    $group_id = (int)$_POST['group_id'];
    $message = htmlspecialchars(trim($_POST['message']));
    if (!empty($message) && is_approved_group_member($conn, $group_id, $user_id)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO group_messages (group_id, user_id, message) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iis", $group_id, $user_id, $message);
        mysqli_stmt_execute($stmt);
        echo "success";
    }
    exit();
}

if ($action == 'get') {
    $group_id = (int)$_POST['group_id'];
    if (!is_approved_group_member($conn, $group_id, $user_id)) {
        echo json_encode([]);
        exit();
    }
    $stmt = mysqli_prepare($conn, "SELECT m.*, u.username, u.profile_pic FROM group_messages m JOIN users u ON m.user_id = u.id WHERE m.group_id = ? ORDER BY m.created_at ASC");
    mysqli_stmt_bind_param($stmt, "i", $group_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $msgs = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $row['is_mine'] = ((int)$row['user_id'] === (int)$user_id);
        $msgs[] = $row;
    }
    echo json_encode($msgs);
    exit();
}

if ($action == 'delete') {
    $message_id = (int)$_POST['message_id'];
    // Sadece kendi gönderdiği mesajı silebilir
    $stmt = mysqli_prepare($conn, "DELETE FROM group_messages WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $message_id, $user_id);
    mysqli_stmt_execute($stmt);
    echo "success";
    exit();
}

if ($action == 'send_file') {
    $group_id = (int)$_POST['group_id'];
    if (!is_approved_group_member($conn, $group_id, $user_id)) {
        echo "unauthorized";
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
        $stmt = mysqli_prepare($conn, "INSERT INTO group_messages (group_id, user_id, message, attachment, attachment_name) VALUES (?, ?, '', ?, ?)");
        mysqli_stmt_bind_param($stmt, "iiss", $group_id, $user_id, $target_file, $display_name);
        mysqli_stmt_execute($stmt);
        echo "success";
    } else {
        echo "error";
    }
    exit();
}

if ($action == 'get_members') {
    $group_id = (int)$_POST['group_id'];
    if (!is_approved_group_member($conn, $group_id, $user_id)) {
        echo json_encode(['is_leader' => false, 'leader_id' => 0, 'members' => []]);
        exit();
    }

    $group_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT creator_id FROM `groups` WHERE id = " . $group_id));
    $leader_id = $group_row ? (int)$group_row['creator_id'] : 0;

    $stmt = mysqli_prepare($conn, "SELECT u.id, u.username, u.profile_pic FROM group_members gm JOIN users u ON gm.user_id = u.id WHERE gm.group_id = ? AND gm.status = 'approved' ORDER BY gm.joined_at ASC");
    mysqli_stmt_bind_param($stmt, "i", $group_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $members = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $members[] = $row;
    }
    echo json_encode([
        'is_leader' => ($leader_id === (int)$user_id),
        'leader_id' => $leader_id,
        'members' => $members
    ]);
    exit();
}

if ($action == 'kick') {
    $group_id = (int)$_POST['group_id'];
    $member_id = (int)$_POST['member_id'];

    if (!is_group_leader($conn, $group_id, $user_id)) {
        echo "unauthorized";
        exit();
    }
    if ($member_id === (int)$user_id) {
        echo "cannot_kick_self";
        exit();
    }

    $stmt = mysqli_prepare($conn, "DELETE FROM group_members WHERE group_id = ? AND user_id = ? AND status = 'approved'");
    mysqli_stmt_bind_param($stmt, "ii", $group_id, $member_id);
    mysqli_stmt_execute($stmt);

    if (mysqli_stmt_affected_rows($stmt) > 0) {
        $group_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM `groups` WHERE id = " . $group_id));
        $notif_msg = str_replace(':name', $group_row['name'] ?? '', isset($t['notif_kicked_from_group']) ? $t['notif_kicked_from_group'] : 'You were removed from the group ":name" by the leader.');
        add_notification($conn, $member_id, 'kicked_from_group', $notif_msg, 'groups.php');
    }
    echo "success";
    exit();
}
