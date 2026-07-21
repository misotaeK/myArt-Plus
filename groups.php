<?php
session_start();
require_once "config.php";

// --- DİL VE TEMA SİSTEMİ ---
if (!isset($_SESSION['lang'])) $_SESSION['lang'] = 'en';
if (isset($_GET['lang']) && in_array($_GET['lang'], ['tr', 'en'])) $_SESSION['lang'] = $_GET['lang'];
$current_lang = $_SESSION['lang'];
require_once "lang/" . $current_lang . ".php";

if (!isset($_SESSION['theme'])) $_SESSION['theme'] = 'light';
if (isset($_GET['theme']) && in_array($_GET['theme'], ['light', 'dark'])) $_SESSION['theme'] = $_GET['theme'];
$current_theme = $_SESSION['theme'];

$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? $_SESSION['user_id'] : 0;

// groups tablosunda banner_image kolonu yoksa ekle (eski kurulumlarla uyumluluk için)
$banner_col_check = mysqli_query($conn, "SHOW COLUMNS FROM `groups` LIKE 'banner_image'");
if ($banner_col_check && mysqli_num_rows($banner_col_check) === 0) {
    mysqli_query($conn, "ALTER TABLE `groups` ADD COLUMN banner_image VARCHAR(255) DEFAULT NULL");
}

$group_tag_colors = [
    'Art' => '#ec008c',
    'Anime' => '#8e44ad',
    'Retro' => '#888888',
    'Gaming' => '#00b8a9',
    'Music' => '#3498db',
];

function relative_time_ago($datetime) {
    if (!$datetime) return '';
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return floor($diff / 86400) . 'd ago';
}

// --- YENİ GRUP KURMA İŞLEMİ ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_group']) && $is_logged_in) {
    $g_name = trim($_POST['group_name']);
    $g_desc = trim($_POST['group_desc']);
    $g_tag = trim($_POST['group_tag']);

    // Grubu oluştur
    $stmt = mysqli_prepare($conn, "INSERT INTO `groups` (creator_id, name, description, tag) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "isss", $user_id, $g_name, $g_desc, $g_tag);
    mysqli_stmt_execute($stmt);

    $new_group_id = mysqli_insert_id($conn);

    // Kuran kişiyi otomatik olarak gruba onaylı üye olarak ekle
    $stmt2 = mysqli_prepare($conn, "INSERT INTO group_members (group_id, user_id, status) VALUES (?, ?, 'approved')");
    mysqli_stmt_bind_param($stmt2, "ii", $new_group_id, $user_id);
    mysqli_stmt_execute($stmt2);

    // Grup lideri (kurucu) için bildirim oluştur
    $notif_msg = str_replace(':name', $g_name, isset($t['notif_group_created']) ? $t['notif_group_created'] : 'Your group ":name" has been created and is awaiting admin approval.');
    add_notification($conn, $user_id, 'group_created', $notif_msg, 'groups.php');

    header("Location: groups.php");
    exit();
}

// --- GRUP BANNER GÜNCELLEME (Sadece lider, sadece Community Groups tablosunda gösterilir) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_banner']) && $is_logged_in) {
    $banner_group_id = (int)($_POST['group_id'] ?? 0);
    $owner_check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT creator_id FROM `groups` WHERE id = '$banner_group_id'"));
    if ($owner_check && (int)$owner_check['creator_id'] === $user_id) {
        if (isset($_FILES['banner']) && $_FILES['banner']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['banner']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $target_dir = "uploads/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
                $new_filename = "banner_" . $banner_group_id . "_" . time() . "." . $ext;
                $target_file = $target_dir . $new_filename;
                if (move_uploaded_file($_FILES['banner']['tmp_name'], $target_file)) {
                    $stmt = mysqli_prepare($conn, "UPDATE `groups` SET banner_image = ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "si", $target_file, $banner_group_id);
                    mysqli_stmt_execute($stmt);
                }
            }
        }
    }
    header("Location: groups.php");
    exit();
}

// --- GRUBU SİLME İŞLEMİ (Sadece kurucu silebilir) ---
if (isset($_GET['delete_group']) && $is_logged_in) {
    $group_id = (int)$_GET['delete_group'];
    $owner_check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT creator_id FROM `groups` WHERE id = '$group_id'"));
    if ($owner_check && (int)$owner_check['creator_id'] === $user_id) {
        mysqli_query($conn, "DELETE FROM group_messages WHERE group_id = '$group_id'");
        mysqli_query($conn, "DELETE FROM group_members WHERE group_id = '$group_id'");
        mysqli_query($conn, "DELETE FROM `groups` WHERE id = '$group_id'");
    }
    header("Location: groups.php");
    exit();
}

// --- GRUBA KATILMA TALEBİ (Lider onayı gerekiyor, Maks 10 onaylı üye) ---
if (isset($_GET['join']) && $is_logged_in) {
    $group_id = (int)$_GET['join'];

    $count_check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM group_members WHERE group_id = '$group_id' AND status = 'approved'"));
    $group_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT creator_id, name FROM `groups` WHERE id = '$group_id'"));

    if ($group_row && $count_check['total'] < 10) {
        $stmt2 = mysqli_prepare($conn, "INSERT IGNORE INTO group_members (group_id, user_id, status) VALUES (?, ?, 'pending')");
        mysqli_stmt_bind_param($stmt2, "ii", $group_id, $user_id);
        mysqli_stmt_execute($stmt2);

        if (mysqli_stmt_affected_rows($stmt2) > 0) {
            $requester = mysqli_fetch_assoc(mysqli_query($conn, "SELECT username FROM users WHERE id = '$user_id'"));
            $notif_msg = str_replace([':user', ':name'], [$requester['username'], $group_row['name']], isset($t['notif_join_request']) ? $t['notif_join_request'] : ':user wants to join your group ":name".');
            add_notification($conn, $group_row['creator_id'], 'join_request', $notif_msg, 'groups.php', $user_id);
        }
    }
    header("Location: groups.php");
    exit();
}

// --- KATILMA TALEBİNİ ONAYLAMA/REDDETME (Sadece grup lideri) ---
if ((isset($_GET['approve_member']) || isset($_GET['reject_member'])) && $is_logged_in) {
    $target_group = (int)($_GET['approve_member'] ?? $_GET['reject_member']);
    $target_user = (int)($_GET['member_id'] ?? 0);

    $owner_check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT creator_id, name FROM `groups` WHERE id = '$target_group'"));
    if ($owner_check && (int)$owner_check['creator_id'] === $user_id) {
        if (isset($_GET['approve_member'])) {
            $stmt2 = mysqli_prepare($conn, "UPDATE group_members SET status = 'approved' WHERE group_id = ? AND user_id = ?");
            mysqli_stmt_bind_param($stmt2, "ii", $target_group, $target_user);
            mysqli_stmt_execute($stmt2);
            $notif_msg = str_replace(':name', $owner_check['name'], isset($t['notif_join_approved']) ? $t['notif_join_approved'] : 'Your request to join ":name" was accepted!');
            add_notification($conn, $target_user, 'join_approved', $notif_msg, 'group_chat.php?id=' . $target_group);
        } else {
            $stmt2 = mysqli_prepare($conn, "DELETE FROM group_members WHERE group_id = ? AND user_id = ? AND status = 'pending'");
            mysqli_stmt_bind_param($stmt2, "ii", $target_group, $target_user);
            mysqli_stmt_execute($stmt2);
            $notif_msg = str_replace(':name', $owner_check['name'], isset($t['notif_join_rejected']) ? $t['notif_join_rejected'] : 'Your request to join ":name" was declined.');
            add_notification($conn, $target_user, 'join_rejected', $notif_msg, 'groups.php');
        }
    }
    header("Location: groups.php");
    exit();
}

// --- GRUPTAN AYRILMA / TALEBİ İPTAL ETME ---
if (isset($_GET['leave']) && $is_logged_in) {
    $group_id = (int)$_GET['leave'];
    $leaving_group = mysqli_fetch_assoc(mysqli_query($conn, "SELECT creator_id, name FROM `groups` WHERE id = '$group_id'"));

    mysqli_query($conn, "DELETE FROM group_members WHERE group_id = '$group_id' AND user_id = '$user_id'");

    // Ayrılan kişi grup lideriyse, liderliği bir sonraki en eski onaylı üyeye devret.
    // Başka üye kalmadıysa artık sahipsiz kalan grubu tamamen sil.
    if ($leaving_group && (int)$leaving_group['creator_id'] === $user_id) {
        $next_leader = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id FROM group_members WHERE group_id = '$group_id' AND status = 'approved' ORDER BY joined_at ASC LIMIT 1"));
        if ($next_leader) {
            $new_leader_id = (int)$next_leader['user_id'];
            $stmt = mysqli_prepare($conn, "UPDATE `groups` SET creator_id = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ii", $new_leader_id, $group_id);
            mysqli_stmt_execute($stmt);

            $notif_msg = str_replace(':name', $leaving_group['name'], isset($t['notif_promoted_leader']) ? $t['notif_promoted_leader'] : 'The leader of ":name" left, so you are now the group leader.');
            add_notification($conn, $new_leader_id, 'promoted_leader', $notif_msg, 'group_chat.php?id=' . $group_id);
        } else {
            mysqli_query($conn, "DELETE FROM group_messages WHERE group_id = '$group_id'");
            mysqli_query($conn, "DELETE FROM `groups` WHERE id = '$group_id'");
        }
    }

    header("Location: groups.php");
    exit();
}

// --- GRUPLARI LİSTELEME (FİLTRELEME, ARAMA VE DİNAMİK KİŞİ SAYIMI) ---
$filter_tag = isset($_GET['tag']) ? $_GET['tag'] : '';
$search_q = isset($_GET['q']) ? trim($_GET['q']) : '';

function paginate_page_numbers($current, $total, $delta = 1) {
    $items = [];
    $prev = 0;
    for ($i = 1; $i <= $total; $i++) {
        if ($i == 1 || $i == $total || ($i >= $current - $delta && $i <= $current + $delta)) {
            if ($prev && $i - $prev > 1) $items[] = '...';
            $items[] = $i;
            $prev = $i;
        }
    }
    return $items;
}

function groups_url($overrides = []) {
    $params = array_merge($_GET, $overrides);
    foreach ($params as $k => $v) {
        if ($v === '' || $v === null) unset($params[$k]);
    }
    return 'groups.php?' . http_build_query($params);
}

$gpage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$gper_page = 6;
$goffset = ($gpage - 1) * $gper_page;

$where_sql = "WHERE (g.status = 'approved' OR g.creator_id = '$user_id')";
if (!empty($filter_tag)) {
    $where_sql .= " AND g.tag = '" . mysqli_real_escape_string($conn, $filter_tag) . "'";
}
if ($search_q !== '') {
    $where_sql .= " AND g.name LIKE '%" . mysqli_real_escape_string($conn, $search_q) . "%'";
}

$g_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM `groups` g $where_sql"));
$groups_total_pages = max(1, ceil(((int)$g_count['total']) / $gper_page));

// İç içe SQL ile grubu, gruptaki onaylı kişi sayısını, üye avatarlarını ve son aktiviteyi tek seferde çekiyoruz
$sql = "SELECT g.*,
        (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id AND gm.status = 'approved') as member_count,
        (SELECT COUNT(*) FROM group_members gm2 WHERE gm2.group_id = g.id AND gm2.user_id = '$user_id' AND gm2.status = 'approved') as is_member,
        (SELECT COUNT(*) FROM group_members gm3 WHERE gm3.group_id = g.id AND gm3.user_id = '$user_id' AND gm3.status = 'pending') as is_pending,
        (SELECT COUNT(*) FROM group_members gm4 WHERE gm4.group_id = g.id AND gm4.status = 'pending') as pending_request_count,
        (SELECT GROUP_CONCAT(u2.profile_pic ORDER BY gm5.joined_at ASC SEPARATOR '||') FROM group_members gm5 JOIN users u2 ON gm5.user_id = u2.id WHERE gm5.group_id = g.id AND gm5.status = 'approved') as member_avatars,
        (SELECT MAX(created_at) FROM group_messages WHERE group_id = g.id) as last_message_at
        FROM `groups` g
        $where_sql
        ORDER BY g.created_at DESC
        LIMIT $gper_page OFFSET $goffset";

$groups_result = mysqli_query($conn, $sql);

// Kullanıcının liderlik ettiği gruplardaki bekleyen katılma talepleri
$my_join_requests = [];
if ($is_logged_in) {
    $req_sql = "SELECT gm.group_id, gm.user_id, g.name AS group_name, u.username, u.profile_pic
                FROM group_members gm
                JOIN `groups` g ON gm.group_id = g.id
                JOIN users u ON gm.user_id = u.id
                WHERE g.creator_id = ? AND gm.status = 'pending'
                ORDER BY gm.joined_at ASC";
    $req_stmt = mysqli_prepare($conn, $req_sql);
    mysqli_stmt_bind_param($req_stmt, "i", $user_id);
    mysqli_stmt_execute($req_stmt);
    $req_result = mysqli_stmt_get_result($req_stmt);
    while ($rq = mysqli_fetch_assoc($req_result)) {
        $my_join_requests[] = $rq;
    }
}

// Arkadaşlarımın üye olduğu gruplar (bir arkadaş birden fazla grupta olabilir -> her biri ayrı satır)
$friend_groups = [];
if ($is_logged_in) {
    $fg_sql = "SELECT DISTINCT u.id AS friend_id, u.username, u.profile_pic, g.id AS group_id, g.name AS group_name
                FROM friendships f
                JOIN users u ON u.id = (CASE WHEN f.requester_id = ? THEN f.addressee_id ELSE f.requester_id END)
                JOIN group_members gm ON gm.user_id = u.id AND gm.status = 'approved'
                JOIN `groups` g ON g.id = gm.group_id AND g.status = 'approved'
                WHERE f.status = 'accepted' AND (f.requester_id = ? OR f.addressee_id = ?)
                ORDER BY u.username ASC
                LIMIT 10";
    $fg_stmt = mysqli_prepare($conn, $fg_sql);
    mysqli_stmt_bind_param($fg_stmt, "iii", $user_id, $user_id, $user_id);
    mysqli_stmt_execute($fg_stmt);
    $fg_result = mysqli_stmt_get_result($fg_stmt);
    while ($fg = mysqli_fetch_assoc($fg_result)) {
        $friend_groups[] = $fg;
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">

<head>
    <meta charset="UTF-8">
    <title>myArt+ | Groups</title>
    <link rel="stylesheet" href="style.css?v=27">
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
        }

        .main-wrapper {
            background: transparent;
        }

        table {
            border-collapse: collapse;
        }

        .tag-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 12px !important;
        }

        .tag-list a {
            display: inline-flex;
            align-items: center;
            padding: 6px 14px;
            color: var(--text-color);
            text-decoration: none;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: var(--box-bg);
            border: 1px solid var(--border-color);
            border-radius: 0;
        }

        .tag-list a:hover,
        .tag-list a.active {
            background: var(--link-color);
            color: #fff;
            border-color: var(--link-color);
            text-decoration: none;
        }

        .group-search-bar {
            display: flex;
            gap: 6px;
            padding: 12px 15px 0 15px;
        }

        .group-search-bar input {
            flex-grow: 1;
            font-size: 11px;
            padding: 7px;
            border: 1px solid var(--border-color);
            background: var(--bg-color);
            color: var(--text-color);
        }

        .groups-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            padding: 15px;
        }

        @media (max-width: 700px) {
            .groups-grid {
                grid-template-columns: 1fr;
            }
        }

        .group-card {
            position: relative;
            border: 1px dotted var(--link-color);
            box-shadow: 2px 2px 0 var(--shadow-color);
            background: var(--box-bg);
            display: flex;
            flex-direction: column;
        }

        .group-card.completed {
            opacity: 0.65;
        }

        .group-cover {
            height: 54px;
            border-bottom: 1px solid var(--footer-border);
            position: relative;
            flex-shrink: 0;
        }

        .group-cover-ribbon {
            position: absolute;
            top: 8px;
            left: 8px;
            color: #fff;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.3px;
            padding: 3px 9px;
        }

        .group-card-body {
            padding: 10px 12px 12px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .group-name {
            font-weight: bold;
            font-size: 15px;
            color: var(--link-color);
            text-decoration: none;
        }

        .new-badge {
            display: inline-block;
            font-size: 9px;
            font-weight: bold;
            background: var(--header-bg);
            color: var(--link-color);
            border: 1px solid var(--link-color);
            padding: 2px 6px;
            margin-left: 6px;
            vertical-align: middle;
        }

        .group-desc {
            font-size: 11px;
            color: var(--footer-text);
            margin: 5px 0 8px;
        }

        .group-avatars {
            display: flex;
            margin-bottom: 8px;
        }

        .group-avatar {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: var(--thumb-bg);
            border: 2px solid var(--box-bg);
            object-fit: cover;
            margin-right: -6px;
        }

        .group-progress-track {
            height: 6px;
            background: var(--thumb-bg);
            border: 1px solid var(--footer-border);
            margin-bottom: 6px;
            overflow: hidden;
        }

        .group-progress-fill {
            height: 100%;
            background: repeating-linear-gradient(45deg, var(--link-color), var(--link-color) 5px, var(--header-bg) 5px, var(--header-bg) 10px);
        }

        .group-stats-row {
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: var(--footer-text);
            margin-bottom: 10px;
        }

        .member-full {
            color: red;
        }

        .member-open {
            color: green;
        }

        .group-card-footer {
            margin-top: auto;
        }

        .group-cta-card {
            border: 2px dashed var(--nav-border);
            background: transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 90px;
            cursor: pointer;
            color: var(--footer-text);
            font-size: 12px;
            font-weight: bold;
        }

        .group-cta-card:hover {
            border-color: var(--link-color);
            color: var(--link-color);
        }

        .friend-groups-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .friend-groups-table th {
            text-align: left;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--footer-text);
            background: var(--thumb-bg);
            padding: 6px 10px;
            border-bottom: 1px solid var(--footer-border);
        }

        .friend-groups-table td {
            padding: 6px 10px;
            border-bottom: 1px dashed var(--border-color);
            vertical-align: middle;
        }

        .friend-groups-table tr:last-child td {
            border-bottom: none;
        }

        .friend-groups-who {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .friend-groups-avatar {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid var(--border-color);
            background: var(--thumb-bg);
            flex-shrink: 0;
        }
    </style>
</head>

<body class="<?php echo $current_theme; ?>">
<div class="marquee-wrap"><div class="marquee-text">★ WELCOME TO MYART+ ★ SHARE YOUR ART WITH THE WORLD ★ JOIN THE FORUM ★ NEW EVENTS POSTED WEEKLY ★</div></div>

    <table width="960" border="0" cellpadding="0" cellspacing="0" align="center" class="main-wrapper" style="margin: 0 auto;">

        <tr height="35">
            <td>
                <table width="100%" border="0" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="50%" align="left" valign="bottom"><a href="index.php"><img src="logo.png" alt="Site Logosu" border="0" class="site-logo"></a></td>
                        <td width="50%" align="right" valign="top" class="top-controls" style="padding-top: 5px; font-size:12px;">
                            <?php echo $t['theme']; ?>
                            <?php if ($current_theme == 'light'): ?><span class="active"><?php echo $t['light']; ?></span> | <a href="?theme=dark"><?php echo $t['dark']; ?></a><?php else: ?><a href="?theme=light"><?php echo $t['light']; ?></a> | <span class="active"><?php echo $t['dark']; ?></span><?php endif; ?> &nbsp;&nbsp;&nbsp;
                            <?php echo $t['lang_label']; ?>
                            <?php if ($current_lang == 'en'): ?><a href="?lang=tr">TR</a> | <span class="active">EN</span><?php else: ?><span class="active">TR</span> | <a href="?lang=en">EN</a><?php endif; ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr height="30">
            <td>
                <?php $current_page = 'group'; include 'navbar.php'; ?>
            </td>
        </tr>

        <tr>
            <td valign="top" style="padding: 10px 8px;">
                <table width="100%" border="0" cellpadding="6" cellspacing="0">
                    <tr>
                        <td width="30%" valign="top">

                            <div class="box">
                                <div class="header-blue" style="display:flex; justify-content:space-between; align-items:center;">
                                    <span><?php echo $t['filter_by_tags']; ?></span>
                                    <a href="#" id="categoriesToggleLink" onclick="toggleCategories(); return false;" style="color:#7a0044; font-size:14px; text-decoration:none;" aria-label="<?php echo htmlspecialchars(!empty($filter_tag) ? $t['hide_categories'] : $t['show_categories']); ?>">&#9662;</a>
                                </div>
                                <div class="tag-list" id="tagListPanel" style="padding: 5px; display:<?php echo !empty($filter_tag) ? 'flex' : 'none'; ?>;">
                                    <a href="groups.php" class="<?php echo empty($filter_tag) ? 'active' : ''; ?>"><?php echo $t['all_groups']; ?></a>
                                    <a href="groups.php?tag=Art" class="<?php echo ($filter_tag == 'Art') ? 'active' : ''; ?>"><?php echo $t['tag_art']; ?></a>
                                    <a href="groups.php?tag=Anime" class="<?php echo ($filter_tag == 'Anime') ? 'active' : ''; ?>"><?php echo $t['tag_anime']; ?></a>
                                    <a href="groups.php?tag=Retro" class="<?php echo ($filter_tag == 'Retro') ? 'active' : ''; ?>"><?php echo $t['tag_retro']; ?></a>
                                    <a href="groups.php?tag=Gaming" class="<?php echo ($filter_tag == 'Gaming') ? 'active' : ''; ?>"><?php echo $t['tag_gaming']; ?></a>
                                    <a href="groups.php?tag=Music" class="<?php echo ($filter_tag == 'Music') ? 'active' : ''; ?>"><?php echo $t['tag_music']; ?></a>
                                </div>
                            </div>

                            <?php if (count($friend_groups) > 0): ?>
                                <div class="box" style="margin-top: 15px;">
                                    <div class="header-blue"><?php echo $t['friend_groups']; ?></div>
                                    <table class="friend-groups-table">
                                        <thead>
                                            <tr>
                                                <th><?php echo $t['friend_label']; ?></th>
                                                <th><?php echo $t['group_label']; ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($friend_groups as $fg): ?>
                                                <tr>
                                                    <td>
                                                        <div class="friend-groups-who">
                                                            <img src="images/<?php echo htmlspecialchars($fg['profile_pic'] ?: 'default_avatar.gif'); ?>" class="friend-groups-avatar" alt="">
                                                            <a href="profile.php?id=<?php echo $fg['friend_id']; ?>"><?php echo htmlspecialchars($fg['username']); ?></a>
                                                        </div>
                                                    </td>
                                                    <td><a href="groups.php?q=<?php echo urlencode($fg['group_name']); ?>"><?php echo htmlspecialchars($fg['group_name']); ?></a></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>

                            <?php if ($is_logged_in && count($my_join_requests) > 0): ?>
                                <div class="box" style="margin-top: 15px;">
                                    <div class="header-blue" style="background-color: #ff9900; color:#fff;"><?php echo $t['pending_join_requests']; ?></div>
                                    <div style="padding: 10px;">
                                        <?php foreach ($my_join_requests as $req): ?>
                                            <div style="display:flex; align-items:center; justify-content:space-between; padding:5px 0; border-bottom:1px dashed var(--border-color); font-size:11px;">
                                                <span>
                                                    <img src="images/<?php echo htmlspecialchars($req['profile_pic'] ?: 'default_avatar.gif'); ?>" style="width:20px; height:20px; vertical-align:middle; border:1px solid var(--border-color); margin-right:4px;">
                                                    <a href="profile.php?id=<?php echo $req['user_id']; ?>"><?php echo htmlspecialchars($req['username']); ?></a>
                                                    <?php echo $t['wants_to_join']; ?> <strong><?php echo htmlspecialchars($req['group_name']); ?></strong>
                                                </span>
                                                <span>
                                                    <a href="?approve_member=<?php echo $req['group_id']; ?>&member_id=<?php echo $req['user_id']; ?>" class="action-link action-activate"><?php echo $t['approve']; ?></a>
                                                    <a href="?reject_member=<?php echo $req['group_id']; ?>&member_id=<?php echo $req['user_id']; ?>" class="action-link action-delete"><?php echo $t['reject']; ?></a>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!$is_logged_in): ?>
                                <div class="box" style="margin-top: 15px; text-align: center; padding: 15px;">
                                    <span style="font-size: 11px; color: gray;"><?php echo $t['must_login_groups']; ?></span><br>
                                    <a href="index.php" style="font-size: 11px; color: var(--link-color); font-weight: bold;"><?php echo $t['login_here']; ?></a>
                                </div>
                            <?php endif; ?>

                        </td>

                        <td width="70%" valign="top">
                            <div class="box">
                                <div class="header-blue"><?php echo $t['community_groups']; ?></div>

                                <form method="GET" action="groups.php" class="group-search-bar">
                                    <?php if (!empty($filter_tag)): ?><input type="hidden" name="tag" value="<?php echo htmlspecialchars($filter_tag); ?>"><?php endif; ?>
                                    <input type="text" name="q" value="<?php echo htmlspecialchars($search_q); ?>" placeholder="<?php echo htmlspecialchars($t['search_groups_placeholder']); ?>">
                                    <button type="submit" class="form-btn"><?php echo $t['search_btn']; ?></button>
                                </form>

                                <div class="groups-grid">

                                    <?php if (mysqli_num_rows($groups_result) > 0): ?>
                                        <?php while ($grp = mysqli_fetch_assoc($groups_result)):
                                            $tag_color = $group_tag_colors[$grp['tag']] ?? '#888888';
                                            $is_new = !empty($grp['created_at']) && (time() - strtotime($grp['created_at'])) < 172800;
                                            $avatars = !empty($grp['member_avatars']) ? array_slice(explode('||', $grp['member_avatars']), 0, 4) : [];
                                            $last_active = relative_time_ago($grp['last_message_at'] ?: $grp['created_at']);
                                            $fill_pct = min(100, round(((int)$grp['member_count'] / 10) * 100));
                                        ?>
                                            <div class="group-card <?php echo $grp['status'] !== 'approved' ? 'completed' : ''; ?>">
                                                <?php if (!empty($grp['banner_image'])): ?>
                                                    <div class="group-cover" style="background-image:url('<?php echo htmlspecialchars($grp['banner_image']); ?>'); background-size:cover; background-position:center;">
                                                        <div class="group-cover-ribbon" style="background:<?php echo $tag_color; ?>;"><?php echo htmlspecialchars($grp['tag']); ?></div>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="group-cover" style="background: repeating-linear-gradient(45deg, <?php echo $tag_color; ?>4D, <?php echo $tag_color; ?>4D 10px, var(--box-bg) 10px, var(--box-bg) 20px);">
                                                        <div class="group-cover-ribbon" style="background:<?php echo $tag_color; ?>;"><?php echo htmlspecialchars($grp['tag']); ?></div>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="group-card-body">
                                                    <?php if ($grp['status'] == 'pending'): ?>
                                                        <span class="badge-pending"><?php echo $t['pending_approval']; ?></span>
                                                    <?php elseif ($grp['status'] == 'rejected'): ?>
                                                        <span class="badge-rejected"><?php echo $t['rejected']; ?></span>
                                                    <?php endif; ?>

                                                    <div>
                                                        <?php if ($grp['status'] == 'approved' && $grp['is_member'] > 0): ?>
                                                            <a href="group_chat.php?id=<?php echo $grp['id']; ?>" class="group-name"><?php echo htmlspecialchars($grp['name']); ?></a>
                                                        <?php else: ?>
                                                            <span class="group-name"><?php echo htmlspecialchars($grp['name']); ?></span>
                                                        <?php endif; ?>
                                                        <?php if ($is_new): ?><span class="new-badge"><?php echo $t['new_badge_label']; ?></span><?php endif; ?>
                                                    </div>
                                                    <div class="group-desc"><?php echo nl2br(htmlspecialchars($grp['description'])); ?></div>

                                                    <?php if (count($avatars) > 0): ?>
                                                        <div class="group-avatars">
                                                            <?php foreach ($avatars as $av): ?>
                                                                <img class="group-avatar" src="images/<?php echo htmlspecialchars($av ?: 'default_avatar.gif'); ?>">
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <div class="group-progress-track">
                                                        <div class="group-progress-fill" style="width:<?php echo $fill_pct; ?>%;"></div>
                                                    </div>

                                                    <div class="group-stats-row">
                                                        <span>
                                                            <?php echo $t['members_label']; ?>
                                                            <?php if ($grp['member_count'] >= 10): ?>
                                                                <span class="member-full"><?php echo $grp['member_count']; ?>/10 (<?php echo $t['full']; ?>)</span>
                                                            <?php else: ?>
                                                                <span class="member-open"><?php echo $grp['member_count']; ?>/10</span>
                                                            <?php endif; ?>
                                                        </span>
                                                        <span><?php echo $t['active_label']; ?> <?php echo $last_active; ?></span>
                                                    </div>

                                                    <?php if ($grp['creator_id'] == $user_id && $grp['pending_request_count'] > 0): ?>
                                                        <div style="margin-bottom:8px;"><span class="badge-pending"><?php echo $grp['pending_request_count']; ?> <?php echo $t['pending_join_requests']; ?></span></div>
                                                    <?php endif; ?>

                                                    <div class="group-card-footer">
                                                        <?php if ($is_logged_in): ?>
                                                            <?php if ($grp['is_member'] > 0 && $grp['status'] == 'approved'): ?>
                                                                <a href="group_chat.php?id=<?php echo $grp['id']; ?>" class="form-btn" style="text-decoration:none; display:block; text-align:center; margin-bottom:6px;"><?php echo $t['open_chat']; ?></a>
                                                            <?php endif; ?>
                                                            <?php if ($grp['is_member'] > 0): ?>
                                                                <a href="?leave=<?php echo $grp['id']; ?>" class="form-btn btn-leave" style="text-decoration:none; display:block; text-align:center;"><?php echo $t['leave_group']; ?></a>
                                                            <?php elseif ($grp['is_pending'] > 0): ?>
                                                                <span style="font-size: 11px; color: #ff9900; font-weight: bold; display:block; margin-bottom:4px; text-align:center;"><?php echo $t['request_pending']; ?></span>
                                                                <a href="?leave=<?php echo $grp['id']; ?>" class="form-btn btn-leave" style="text-decoration:none; display:block; text-align:center;"><?php echo $t['cancel_request']; ?></a>
                                                            <?php elseif ($grp['status'] == 'approved' && $grp['member_count'] < 10): ?>
                                                                <a href="?join=<?php echo $grp['id']; ?>" class="form-btn btn-join" style="text-decoration:none; display:block; text-align:center;"><?php echo $t['join_group']; ?></a>
                                                            <?php elseif ($grp['status'] == 'approved'): ?>
                                                                <span style="font-size: 11px; color: red; font-weight: bold;"><?php echo $t['group_full']; ?></span>
                                                            <?php endif; ?>
                                                            <?php if ($grp['creator_id'] == $user_id): ?>
                                                                <a href="#" onclick="$('#bannerForm<?php echo $grp['id']; ?>').slideToggle('fast'); return false;" class="form-btn" style="text-decoration:none; display:block; text-align:center; margin-top:6px;"><?php echo $t['change_banner']; ?></a>
                                                                <form method="POST" action="groups.php" enctype="multipart/form-data" id="bannerForm<?php echo $grp['id']; ?>" style="display:none; margin-top:6px;">
                                                                    <input type="hidden" name="group_id" value="<?php echo $grp['id']; ?>">
                                                                    <input type="file" name="banner" accept=".jpg,.jpeg,.png,.gif" required style="width:100%; font-size:10px; margin-bottom:5px; box-sizing:border-box;">
                                                                    <button type="submit" name="update_banner" class="form-btn" style="width:100%;"><?php echo $t['upload_banner_btn']; ?></button>
                                                                    <div class="size-hint" style="text-align:center;"><?php echo $t['group_banner_size_hint']; ?></div>
                                                                </form>
                                                                <a href="?delete_group=<?php echo $grp['id']; ?>" class="form-btn" style="background-color:#cc0000; text-decoration:none; display:block; text-align:center; margin-top:6px;" onclick="return confirm('<?php echo htmlspecialchars($t['confirm_delete_group']); ?>');"><?php echo $t['delete_group']; ?></a>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>

                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <div style="grid-column: 1 / -1; text-align: center; padding: 20px; font-size: 12px; color: gray;">
                                            <?php echo $t['no_groups_found']; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($is_logged_in): ?>
                                        <div class="group-cta-card" onclick="$('#createGroupBox').slideToggle('fast');">+ <?php echo $t['create_new_group']; ?></div>
                                    <?php endif; ?>

                                </div>

                                <?php if ($groups_total_pages > 1): ?>
                                    <div class="pagination-bar">
                                        <a href="<?php echo groups_url(['page' => max(1, $gpage - 1)]); ?>" class="pg-nav <?php echo $gpage <= 1 ? 'disabled' : ''; ?>">&lt;</a>
                                        <?php foreach (paginate_page_numbers($gpage, $groups_total_pages) as $p): ?>
                                            <?php if ($p === '...'): ?>
                                                <span class="pg-ellipsis">&hellip;</span>
                                            <?php elseif ($p == $gpage): ?>
                                                <span class="pg-current"><?php echo $p; ?></span>
                                            <?php else: ?>
                                                <a href="<?php echo groups_url(['page' => $p]); ?>"><?php echo $p; ?></a>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                        <a href="<?php echo groups_url(['page' => min($groups_total_pages, $gpage + 1)]); ?>" class="pg-nav <?php echo $gpage >= $groups_total_pages ? 'disabled' : ''; ?>">&gt;</a>
                                    </div>
                                <?php endif; ?>

                                <?php if ($is_logged_in): ?>
                                    <div class="box" id="createGroupBox" style="display:none; margin:0 15px 15px;">
                                        <div class="header-blue"><?php echo $t['create_new_group']; ?></div>
                                        <div style="padding: 10px;">
                                            <form method="POST" action="groups.php">
                                                <label style="font-size: 11px; font-weight: bold; color: var(--text-color);"><?php echo $t['group_name_label']; ?></label>
                                                <input type="text" name="group_name" required class="form-input">

                                                <label style="font-size: 11px; font-weight: bold; color: var(--text-color);"><?php echo $t['tag_label']; ?></label>
                                                <select name="group_tag" class="form-input">
                                                    <option value="Art"><?php echo $t['tag_art']; ?></option>
                                                    <option value="Anime"><?php echo $t['tag_anime']; ?></option>
                                                    <option value="Retro"><?php echo $t['tag_retro']; ?></option>
                                                    <option value="Gaming"><?php echo $t['tag_gaming']; ?></option>
                                                    <option value="Music"><?php echo $t['tag_music']; ?></option>
                                                </select>

                                                <label style="font-size: 11px; font-weight: bold; color: var(--text-color);"><?php echo $t['description_label']; ?></label>
                                                <textarea name="group_desc" rows="3" required class="form-input"></textarea>

                                                <button type="submit" name="create_group" class="form-btn"><?php echo $t['create_group_btn']; ?></button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr>
            <td valign="bottom" style="padding-top: 10px;">
                <div class="footer" style="text-align:center; padding:10px; font-size:12px;">
                    <a href="qa.php"><?php echo $t['qa']; ?></a> | <a href="privacy.php"><?php echo $t['privacy']; ?></a> | <a href="help.php"><?php echo $t['help']; ?></a> | <a href="terms.php"><?php echo $t['terms']; ?></a>
                    <div class="footer-copy" style="margin-top:5px; color:gray;">© <?php echo date("Y"); ?> myArt+ | <?php echo $t['all_rights_reserved']; ?></div>
                </div>
            </td>
        </tr>
    </table>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        const CATEGORIES_SHOW_LABEL = <?php echo json_encode($t['show_categories']); ?>;
        const CATEGORIES_HIDE_LABEL = <?php echo json_encode($t['hide_categories']); ?>;

        function toggleCategories() {
            const panel = $('#tagListPanel');
            const link = $('#categoriesToggleLink');
            const opening = !panel.is(':visible');
            panel.slideToggle('fast');
            link.attr('aria-label', opening ? CATEGORIES_HIDE_LABEL : CATEGORIES_SHOW_LABEL);
        }
    </script>
    <?php if ($is_logged_in): ?>
        <?php include 'chat_widget.php'; ?>
    <?php endif; ?>

</body>

</html>
