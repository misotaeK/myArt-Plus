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

// --- YORUM EKLEME İŞLEMİ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment']) && isset($_SESSION['user_id'])) {
    $target_profile = (int)$_POST['profile_id'];
    $comment_msg = trim($_POST['comment_message']);
    if ($comment_msg !== '' && $target_profile > 0) {
        $stmt = mysqli_prepare($conn, "INSERT INTO comments (profile_user_id, commenter_id, message) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iis", $target_profile, $_SESSION['user_id'], $comment_msg);
        mysqli_stmt_execute($stmt);

        if ($target_profile !== (int)$_SESSION['user_id']) {
            $commenter_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT username FROM users WHERE id = " . (int)$_SESSION['user_id']));
            $notif_msg = str_replace(':user', $commenter_row['username'], isset($t['notif_new_comment']) ? $t['notif_new_comment'] : ':user commented on your profile.');
            add_notification($conn, $target_profile, 'new_comment', $notif_msg, 'profile.php?id=' . $target_profile, (int)$_SESSION['user_id']);
        }
    }
    header("Location: profile.php?id=" . $target_profile);
    exit();
}

// --- YORUM SİLME İŞLEMİ (Yorumu yazan veya profil sahibi silebilir) ---
if (isset($_GET['delete_comment']) && isset($_SESSION['user_id'])) {
    $comment_id = (int)$_GET['delete_comment'];
    $stmt = mysqli_prepare($conn, "SELECT profile_user_id, commenter_id FROM comments WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $comment_id);
    mysqli_stmt_execute($stmt);
    $c_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if ($c_row && ((int)$c_row['commenter_id'] === (int)$_SESSION['user_id'] || (int)$c_row['profile_user_id'] === (int)$_SESSION['user_id'])) {
        $del_stmt = mysqli_prepare($conn, "DELETE FROM comments WHERE id = ?");
        mysqli_stmt_bind_param($del_stmt, "i", $comment_id);
        mysqli_stmt_execute($del_stmt);
    }
    $redirect_id = isset($_GET['id']) ? (int)$_GET['id'] : ($c_row['profile_user_id'] ?? 0);
    header("Location: profile.php?id=" . $redirect_id);
    exit();
}

// --- PROFİL VERİSİNİ ÇEKME ---
$profile_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0);

if ($profile_id === 0) {
    header("Location: index.php");
    exit();
}

// Kendi profiline bakıyorsa, düzenlenebilir kendi profil sayfasına yönlendir
if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $profile_id) {
    header("Location: myprofile.php");
    exit();
}

$me = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

// --- ARKADAŞLIK İSTEĞİ GÖNDERME ---
if (isset($_GET['send_friend_request']) && $me > 0) {
    $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO friendships (requester_id, addressee_id, status) VALUES (?, ?, 'pending')");
    mysqli_stmt_bind_param($stmt, "ii", $me, $profile_id);
    mysqli_stmt_execute($stmt);
    if (mysqli_stmt_affected_rows($stmt) > 0) {
        $me_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT username FROM users WHERE id = $me"));
        $notif_msg = str_replace(':user', $me_row['username'], $t['notif_friend_request']);
        add_notification($conn, $profile_id, 'friend_request', $notif_msg, 'profile.php?id=' . $me, $me);
    }
    header("Location: profile.php?id=" . $profile_id);
    exit();
}

// --- ARKADAŞLIK İSTEĞİNİ KABUL/RED ---
if ((isset($_GET['accept_friend']) || isset($_GET['reject_friend'])) && $me > 0) {
    $from_id = (int)($_GET['accept_friend'] ?? $_GET['reject_friend']);
    if (isset($_GET['accept_friend'])) {
        $stmt = mysqli_prepare($conn, "UPDATE friendships SET status = 'accepted' WHERE requester_id = ? AND addressee_id = ? AND status = 'pending'");
        mysqli_stmt_bind_param($stmt, "ii", $from_id, $me);
        mysqli_stmt_execute($stmt);
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            $me_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT username FROM users WHERE id = $me"));
            $notif_msg = str_replace(':user', $me_row['username'], $t['notif_friend_accepted']);
            add_notification($conn, $from_id, 'friend_accepted', $notif_msg, 'profile.php?id=' . $me, $me);
        }
    } else {
        $stmt = mysqli_prepare($conn, "DELETE FROM friendships WHERE requester_id = ? AND addressee_id = ? AND status = 'pending'");
        mysqli_stmt_bind_param($stmt, "ii", $from_id, $me);
        mysqli_stmt_execute($stmt);
    }
    header("Location: profile.php?id=" . $profile_id);
    exit();
}

// --- ARKADAŞLIKTAN ÇIKARMA / İSTEĞİ İPTAL ETME ---
if (isset($_GET['remove_friend']) && $me > 0) {
    $stmt = mysqli_prepare($conn, "DELETE FROM friendships WHERE (requester_id = ? AND addressee_id = ?) OR (requester_id = ? AND addressee_id = ?)");
    mysqli_stmt_bind_param($stmt, "iiii", $me, $profile_id, $profile_id, $me);
    mysqli_stmt_execute($stmt);
    header("Location: profile.php?id=" . $profile_id);
    exit();
}

// --- FAVORİLERE EKLEME/ÇIKARMA (TOGGLE) ---
if (isset($_GET['toggle_favorite']) && $me > 0) {
    $check = mysqli_prepare($conn, "SELECT id FROM favorites WHERE user_id = ? AND favorite_user_id = ?");
    mysqli_stmt_bind_param($check, "ii", $me, $profile_id);
    mysqli_stmt_execute($check);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($check))) {
        $stmt = mysqli_prepare($conn, "DELETE FROM favorites WHERE user_id = ? AND favorite_user_id = ?");
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO favorites (user_id, favorite_user_id) VALUES (?, ?)");
    }
    mysqli_stmt_bind_param($stmt, "ii", $me, $profile_id);
    mysqli_stmt_execute($stmt);
    header("Location: profile.php?id=" . $profile_id);
    exit();
}

// --- ENGELLEME/ENGEL KALDIRMA (TOGGLE) ---
if (isset($_GET['toggle_block']) && $me > 0) {
    $check = mysqli_prepare($conn, "SELECT id FROM blocks WHERE user_id = ? AND blocked_user_id = ?");
    mysqli_stmt_bind_param($check, "ii", $me, $profile_id);
    mysqli_stmt_execute($check);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($check))) {
        $stmt = mysqli_prepare($conn, "DELETE FROM blocks WHERE user_id = ? AND blocked_user_id = ?");
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO blocks (user_id, blocked_user_id) VALUES (?, ?)");
    }
    mysqli_stmt_bind_param($stmt, "ii", $me, $profile_id);
    mysqli_stmt_execute($stmt);
    header("Location: profile.php?id=" . $profile_id);
    exit();
}

// --- KULLANICIYI ŞİKAYET ETME ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report']) && $me > 0) {
    $reason = trim($_POST['report_reason']);
    if ($reason !== '') {
        $stmt = mysqli_prepare($conn, "INSERT INTO reports (reporter_id, reported_user_id, reason) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iis", $me, $profile_id, $reason);
        mysqli_stmt_execute($stmt);
    }
    header("Location: profile.php?id=" . $profile_id . "&reported=1");
    exit();
}

// --- BİR GRUBA DAVET ETME (Sadece lideri olduğun gruplar) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invite_to_group']) && $me > 0) {
    $target_group = (int)$_POST['group_id'];
    $owner_check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT creator_id, name FROM `groups` WHERE id = '$target_group'"));
    if ($owner_check && (int)$owner_check['creator_id'] === $me) {
        $count_check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM group_members WHERE group_id = '$target_group' AND status = 'approved'"));
        if ($count_check['total'] < 10) {
            $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO group_members (group_id, user_id, status) VALUES (?, ?, 'approved')");
            mysqli_stmt_bind_param($stmt, "ii", $target_group, $profile_id);
            mysqli_stmt_execute($stmt);
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                $notif_msg = str_replace(':name', $owner_check['name'], $t['notif_added_to_group']);
                add_notification($conn, $profile_id, 'added_to_group', $notif_msg, 'group_chat.php?id=' . $target_group);
            }
        }
    }
    header("Location: profile.php?id=" . $profile_id);
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $profile_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    $p_username = htmlspecialchars($row['username']);
    $p_gender = $row['gender'];
    $p_bio = htmlspecialchars($row['bio']);

    if (empty($p_bio)) {
        $p_bio = isset($t['no_bio']) ? $t['no_bio'] : "This user hasn't written a bio yet...";
    }

    $p_pic = !empty($row['profile_pic']) ? htmlspecialchars($row['profile_pic']) : 'default_avatar.gif';
    $p_joined = date("M d, Y", strtotime($row['created_at']));
    $p_tw = htmlspecialchars($row['twitter'] ?? '');
    $p_ig = htmlspecialchars($row['instagram'] ?? '');
    $p_c_status = htmlspecialchars($row['comm_status'] ?? 'OPEN');
    $p_c_t1 = htmlspecialchars($row['comm_title1'] ?? 'Sketch (Bust)');
    $p_c_s1 = htmlspecialchars($row['comm_sketch'] ?? '$15');
    $p_c_t2 = htmlspecialchars($row['comm_title2'] ?? 'Lineart (Half)');
    $p_c_s2 = htmlspecialchars($row['comm_lineart'] ?? '$25');
    $p_c_t3 = htmlspecialchars($row['comm_title3'] ?? 'Full Color');
    $p_c_s3 = htmlspecialchars($row['comm_full'] ?? '$50+');
    $p_comm_sheet_img = !empty($row['commission_sheet_image']) ? htmlspecialchars($row['commission_sheet_image']) : '';

    $profile_artworks = [];
    $art_q = mysqli_query($conn, "SELECT * FROM artworks WHERE user_id = '$profile_id' ORDER BY created_at DESC LIMIT 20");
    while ($a = mysqli_fetch_assoc($art_q)) {
        $profile_artworks[] = $a;
    }

    // Profil yorumları için sayfalama
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

    function profile_url($profile_id, $overrides = []) {
        $params = array_merge(['id' => $profile_id], $overrides);
        return 'profile.php?' . http_build_query($params);
    }

    $cpage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $cper_page = 10;
    $coffset = ($cpage - 1) * $cper_page;

    $pc_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM comments WHERE profile_user_id = '$profile_id'"));
    $comment_total_pages = max(1, ceil(((int)$pc_count['total']) / $cper_page));

    $profile_comments = [];
    $c_stmt = mysqli_prepare($conn, "SELECT c.*, u.username, u.profile_pic FROM comments c JOIN users u ON c.commenter_id = u.id WHERE c.profile_user_id = ? ORDER BY c.created_at DESC LIMIT ? OFFSET ?");
    mysqli_stmt_bind_param($c_stmt, "iii", $profile_id, $cper_page, $coffset);
    mysqli_stmt_execute($c_stmt);
    $c_result = mysqli_stmt_get_result($c_stmt);
    while ($c = mysqli_fetch_assoc($c_result)) {
        $profile_comments[] = $c;
    }

    // --- SOSYAL DURUM HESAPLAMALARI (Contacting paneli için) ---
    $friendship_status = 'none'; // none | pending_sent | pending_received | accepted
    $is_favorited = false;
    $i_blocked_them = false;
    $they_blocked_me = false;
    $my_led_groups = [];

    if ($me > 0) {
        $fr = mysqli_fetch_assoc(mysqli_query($conn, "SELECT requester_id, status FROM friendships WHERE (requester_id = $me AND addressee_id = $profile_id) OR (requester_id = $profile_id AND addressee_id = $me)"));
        if ($fr) {
            if ($fr['status'] === 'accepted') {
                $friendship_status = 'accepted';
            } elseif ((int)$fr['requester_id'] === $me) {
                $friendship_status = 'pending_sent';
            } else {
                $friendship_status = 'pending_received';
            }
        }

        $fav = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM favorites WHERE user_id = $me AND favorite_user_id = $profile_id"));
        $is_favorited = (bool)$fav;

        $blk1 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM blocks WHERE user_id = $me AND blocked_user_id = $profile_id"));
        $i_blocked_them = (bool)$blk1;

        $blk2 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM blocks WHERE user_id = $profile_id AND blocked_user_id = $me"));
        $they_blocked_me = (bool)$blk2;

        $led_res = mysqli_query($conn, "SELECT id, name FROM `groups` WHERE creator_id = $me AND status = 'approved' AND id NOT IN (SELECT group_id FROM group_members WHERE user_id = $profile_id AND status = 'approved')");
        while ($lg = mysqli_fetch_assoc($led_res)) {
            $my_led_groups[] = $lg;
        }
    }
} else {
    die("<div style='text-align:center; padding: 50px; font-family: Arial;'><h1>User Not Found!</h1><a href='index.php'>Go Back</a></div>");
}
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">

<head>
    <meta charset="UTF-8">
    <title>My Cool Space - <?php echo $p_username; ?>'s Profile</title>

    <!-- BOOTSTRAP 4 CSS (Sadece Galeri ve Modal İçin) -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">

    <link rel="stylesheet" href="style.css?v=27">
    <style>
        /* Sitenin Orijinal Yapısını Koruyan Özel CSS */
        body {
            font-family: Arial, Helvetica, sans-serif;
        }

        .main-wrapper {
            background: transparent;
        }

        .profile-name {
            font-size: 22px;
            font-weight: bold;
            color: var(--link-color);
            margin: 0 0 10px 0;
            font-family: 'Comic Sans MS', cursive, sans-serif;
        }

        .profile-pic {
            display: block;
            border: 2px solid var(--border-color);
            width: 170px;
            height: 200px;
            object-fit: cover;
            background-color: var(--thumb-bg);
            margin-bottom: 10px;
        }

        .online-status {
            color: #008800;
            font-weight: bold;
            font-size: 14px;
            margin-top: 5px;
            display: inline-block;
        }

        body.dark .online-status {
            color: #00ff00;
        }

        .contact-table td {
            padding: 4px;
            font-size: 11px;
        }

        .contact-table a {
            color: var(--text-color);
            font-weight: normal;
            text-decoration: none;
        }

        .contact-table a:hover {
            text-decoration: underline;
            color: var(--link-hover);
        }

        .commission-sheet {
            background-color: var(--thumb-bg);
            border: 1px dashed var(--border-color);
            padding: 8px;
            margin-top: 10px;
            text-align: center;
        }

        .commission-sheet-thumb {
            display: block;
            width: 100%;
            max-width: 220px;
            height: 140px;
            object-fit: cover;
            margin: 8px auto 0 auto;
            border: 1px solid var(--border-color);
            cursor: zoom-in;
        }

        /* Bootstrap ezmelerini düzeltmek için (Tablo yapısı bozulmasın diye) */
        table {
            border-collapse: collapse;
        }

        .carousel-item img {
            object-fit: contain;
            width: 100%;
            height: 100%;
        }

        .art-credit-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.7);
            color: #fff;
            font-size: 11px;
            padding: 6px 10px;
            text-align: center;
        }
    </style>
</head>

<body class="<?php echo $current_theme; ?>">
<div class="marquee-wrap"><div class="marquee-text">★ WELCOME TO MYART+ ★ SHARE YOUR ART WITH THE WORLD ★ JOIN THE FORUM ★ NEW EVENTS POSTED WEEKLY ★</div></div>

    <!-- ANA YAPI (960px Y2K Tablosu) -->
    <table width="960" border="0" cellpadding="0" cellspacing="0" align="center" class="main-wrapper" style="margin: 0 auto;">

        <tr height="35">
            <td>
                <table width="100%" border="0" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="50%" align="left" valign="bottom">
                            <a href="index.php"><img src="logo.png" alt="Site Logosu" border="0" class="site-logo"></a>
                        </td>
                        <td width="50%" align="right" valign="top" class="top-controls" style="padding-top: 5px; font-size:12px;">
                            <?php echo $t['theme']; ?>
                            <?php if ($current_theme == 'light'): ?>
                                <span class="active"><?php echo $t['light']; ?></span> | <a href="?id=<?php echo $profile_id; ?>&theme=dark"><?php echo $t['dark']; ?></a>
                            <?php else: ?>
                                <a href="?id=<?php echo $profile_id; ?>&theme=light"><?php echo $t['light']; ?></a> | <span class="active"><?php echo $t['dark']; ?></span>
                            <?php endif; ?>
                            &nbsp;&nbsp;&nbsp;
                            <?php echo $t['lang_label']; ?>
                            <?php if ($current_lang == 'en'): ?>
                                <a href="?id=<?php echo $profile_id; ?>&lang=tr">TR</a> | <span class="active">EN</span>
                            <?php else: ?>
                                <span class="active">TR</span> | <a href="?id=<?php echo $profile_id; ?>&lang=en">EN</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr height="30">
            <td>
                <?php $current_page = 'profile'; include 'navbar.php'; ?>
            </td>
        </tr>

        <tr>
            <td valign="top" style="padding: 10px 8px;">
                <table width="100%" border="0" cellpadding="6" cellspacing="0">
                    <tr>
                        <!-- ================= SOL KOLON ================= -->
                        <td width="35%" valign="top">
                            <div class="profile-name"><?php echo $p_username; ?></div>

                            <img src="images/<?php echo $p_pic; ?>" alt="<?php echo $p_username; ?>" class="profile-pic">

                            <div style="font-size: 12px; margin-bottom: 15px;">
                                <?php if ($p_gender != 'unspecified'): ?>
                                    <?php echo ucfirst($p_gender); ?> <br>
                                <?php endif; ?>
                                <span style="color: gray;"><?php echo $t['member_since']; ?> <?php echo $p_joined; ?></span>
                                <?php if ($p_tw !== '' || $p_ig !== ''): ?>
                                    <div class="social-links">
                                        <?php if ($p_tw !== ''): ?>
                                            <a href="https://twitter.com/<?php echo ltrim($p_tw, '@'); ?>" target="_blank" rel="noopener noreferrer"><?php echo $t['twitter_prefix']; ?> @<?php echo ltrim($p_tw, '@'); ?></a>
                                        <?php endif; ?>
                                        <?php if ($p_ig !== ''): ?>
                                            <a href="https://instagram.com/<?php echo ltrim($p_ig, '@'); ?>" target="_blank" rel="noopener noreferrer"><?php echo $t['instagram_prefix']; ?> @<?php echo ltrim($p_ig, '@'); ?></a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- İletişim Kutusu -->
                            <?php if ($me === 0): ?>
                                <div class="box">
                                    <div class="header-blue" style="background-color: var(--ad-border); color: #fff;"><?php echo $t['contacting']; ?> <?php echo $p_username; ?></div>
                                    <div style="padding: 10px; font-size: 11px; text-align: center; color: gray;">
                                        <?php echo $t['must_login_groups']; ?> <a href="index.php" style="color: var(--link-color); font-weight: bold;"><?php echo $t['login_here']; ?></a>
                                    </div>
                                </div>
                            <?php elseif ($they_blocked_me): ?>
                                <div class="box">
                                    <div class="header-blue" style="background-color: var(--ad-border); color: #fff;"><?php echo $t['contacting']; ?> <?php echo $p_username; ?></div>
                                    <div style="padding: 10px; font-size: 11px; text-align: center; color: gray;"><?php echo $t['cannot_interact']; ?></div>
                                </div>
                            <?php else: ?>
                                <div class="box">
                                    <div class="header-blue" style="background-color: var(--ad-border); color: #fff;"><?php echo $t['contacting']; ?> <?php echo $p_username; ?></div>
                                    <div style="padding: 5px;">
                                        <?php if (isset($_GET['reported'])): ?>
                                            <div style="color: green; font-size: 11px; text-align: center; padding: 5px;"><?php echo $t['report_sent']; ?></div>
                                        <?php endif; ?>

                                        <?php if ($i_blocked_them): ?>
                                            <div style="padding: 8px; text-align: center;">
                                                <span style="font-size: 11px; color: gray; display:block; margin-bottom:6px;"><?php echo $t['you_blocked_user']; ?></span>
                                                <a href="?id=<?php echo $profile_id; ?>&toggle_block=1" style="font-size: 11px; font-weight: bold;">🚫 <?php echo $t['unblock_user']; ?></a>
                                            </div>
                                        <?php else: ?>
                                            <table width="100%" border="0" cellpadding="0" cellspacing="0" class="contact-table">
                                                <tr>
                                                    <td width="50%">
                                                        <?php if ($friendship_status === 'accepted'): ?>
                                                            ✅ <span><?php echo $t['friends_label']; ?></span>
                                                        <?php elseif ($friendship_status === 'pending_sent'): ?>
                                                            ⏳ <span style="color:gray;"><?php echo $t['request_sent']; ?></span>
                                                        <?php elseif ($friendship_status === 'pending_received'): ?>
                                                            ✔ <a href="?id=<?php echo $profile_id; ?>&accept_friend=<?php echo $profile_id; ?>"><?php echo $t['accept_friend']; ?></a>
                                                        <?php else: ?>
                                                            ➕ <a href="?id=<?php echo $profile_id; ?>&send_friend_request=1"><?php echo $t['add_to_friends']; ?></a>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td width="50%"><?php echo $is_favorited ? '⭐' : '☆'; ?> <a href="?id=<?php echo $profile_id; ?>&toggle_favorite=1"><?php echo $is_favorited ? $t['favorited'] : $t['add_to_favorites']; ?></a></td>
                                                </tr>
                                                <tr>
                                                    <td>✉ <a href="home.php?chat_with=<?php echo $profile_id; ?>&chat_name=<?php echo urlencode($p_username); ?>"><?php echo $t['send_message']; ?></a></td>
                                                    <td>➡ <a href="home.php?share_profile=<?php echo $profile_id; ?>&chat_name=<?php echo urlencode($p_username); ?>"><?php echo $t['forward_to_friend']; ?></a></td>
                                                </tr>
                                                <tr>
                                                    <td colspan="2">🚫 <a href="?id=<?php echo $profile_id; ?>&toggle_block=1"><?php echo $t['block_user']; ?></a></td>
                                                </tr>
                                                <tr>
                                                    <td colspan="2">
                                                        <details>
                                                            <summary style="cursor:pointer;">👥 <?php echo $t['add_to_group']; ?></summary>
                                                            <?php if (count($my_led_groups) > 0): ?>
                                                                <form method="POST" action="profile.php?id=<?php echo $profile_id; ?>" style="padding:6px 0;">
                                                                    <select name="group_id" class="form-input" style="font-size:10px;">
                                                                        <?php foreach ($my_led_groups as $lg): ?>
                                                                            <option value="<?php echo $lg['id']; ?>"><?php echo htmlspecialchars($lg['name']); ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                    <button type="submit" name="invite_to_group" class="form-btn" style="font-size:10px; margin-top:4px;"><?php echo $t['add_to_group']; ?></button>
                                                                </form>
                                                            <?php else: ?>
                                                                <div style="font-size:10px; color:gray; padding:4px 0;"><?php echo $t['no_groups_to_invite']; ?></div>
                                                            <?php endif; ?>
                                                        </details>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td colspan="2">
                                                        <details>
                                                            <summary style="cursor:pointer; color:#cc0000;">🚩 <?php echo $t['report_user']; ?></summary>
                                                            <form method="POST" action="profile.php?id=<?php echo $profile_id; ?>" style="padding:6px 0;">
                                                                <textarea name="report_reason" required maxlength="255" rows="2" placeholder="<?php echo htmlspecialchars($t['report_reason_placeholder']); ?>" class="form-input" style="font-size:10px;"></textarea>
                                                                <button type="submit" name="submit_report" class="form-btn" style="background-color:#cc0000; font-size:10px; margin-top:4px;"><?php echo $t['submit_report']; ?></button>
                                                            </form>
                                                        </details>
                                                    </td>
                                                </tr>
                                            </table>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- ABOUT ME VE COMMISSION SHEET (Dil Destekli) -->
                            <div class="box" style="margin-top: 15px;">
                                <div class="header-blue"><?php echo $p_username; ?>'s <?php echo $t['blurbs']; ?></div>
                                <div style="padding: 10px; font-size: 12px; line-height: 1.5;">
                                    <strong style="color: var(--link-color);"><?php echo $t['about_me']; ?></strong><br>
                                    <?php echo nl2br($p_bio); ?>

                                    <!-- Commission Sheet -->
                                    <div class="commission-sheet">
                                        <strong style="color: var(--link-color); font-size: 14px;"><?php echo $t['commission_info']; ?></strong><br>
                                        <span style="color: <?php echo ($p_c_status == 'OPEN') ? 'green' : 'red'; ?>; font-weight: bold;"><?php echo $t['comm_status_label']; ?> <?php echo $p_c_status; ?></span><br><br>
                                        <table width="100%" border="0" cellpadding="2" cellspacing="0" style="font-size: 11px; text-align: left;">
                                            <tr>
                                                <td><?php echo $p_c_t1; ?></td>
                                                <td align="right"><?php echo $p_c_s1; ?></td>
                                            </tr>
                                            <tr>
                                                <td><?php echo $p_c_t2; ?></td>
                                                <td align="right"><?php echo $p_c_s2; ?></td>
                                            </tr>
                                            <tr>
                                                <td><?php echo $p_c_t3; ?></td>
                                                <td align="right"><?php echo $p_c_s3; ?></td>
                                            </tr>
                                        </table>
                                        <?php if ($p_comm_sheet_img !== ''): ?>
                                            <img src="<?php echo $p_comm_sheet_img; ?>" alt="<?php echo $t['commission_sheet_label']; ?>" class="commission-sheet-thumb" onclick="enlargeImage(this.src)">
                                        <?php endif; ?>
                                        <div style="margin-top: 8px; font-style: italic; font-size: 10px;">
                                            <?php echo $t['payment_note']; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </td>

                        <!-- ================= SAĞ KOLON ================= -->
                        <td width="65%" valign="top">

                            <!-- LATEST ARTWORKS (BOOTSTRAP CAROUSEL) -->
                            <div class="box">
                                <div class="header-blue"><?php echo $p_username; ?>'s <?php echo $t['latest_artworks']; ?></div>

                                <div style="padding: 10px; background-color: var(--box-bg);">

                                    <div id="artworkCarousel" class="carousel slide" data-ride="carousel" style="border: 1px solid var(--border-color); background-color: #000;">
                                        <?php if (count($profile_artworks) > 0): ?>
                                            <ol class="carousel-indicators">
                                                <?php foreach ($profile_artworks as $index => $art): ?>
                                                    <li data-target="#artworkCarousel" data-slide-to="<?php echo $index; ?>" class="<?php echo $index == 0 ? 'active' : ''; ?>"></li>
                                                <?php endforeach; ?>
                                            </ol>

                                            <div class="carousel-inner" style="min-height: 400px;">
                                                <?php foreach ($profile_artworks as $index => $art): ?>
                                                    <div class="carousel-item <?php echo $index == 0 ? 'active' : ''; ?>" style="height: 400px;">
                                                        <img class="d-block mx-auto" src="<?php echo htmlspecialchars($art['image_path']); ?>" style="cursor: zoom-in;" onclick="enlargeImage(this.src)">
                                                        <?php if (empty($art['is_original'] ?? 1)): ?>
                                                            <div class="art-credit-overlay"><?php echo $t['credit_prefix']; ?> <strong><?php echo htmlspecialchars($art['credit_artist']); ?></strong> <?php echo $t['credit_on']; ?> <strong><?php echo htmlspecialchars($art['credit_platform']); ?></strong></div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>

                                            <a class="carousel-control-prev" href="#artworkCarousel" role="button" data-slide="prev">
                                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                                <span class="sr-only">Previous</span>
                                            </a>
                                            <a class="carousel-control-next" href="#artworkCarousel" role="button" data-slide="next">
                                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                                <span class="sr-only">Next</span>
                                            </a>
                                        <?php else: ?>
                                            <div style="height: 400px; display:flex; align-items:center; justify-content:center; color:gray;"><?php echo $t['no_artworks']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- KAYDIRILABİLİR YORUMLAR KUTUSU -->
                            <div class="box" style="margin-top: 15px;">
                                <div class="header-blue"><?php echo $p_username; ?>'s <?php echo $t['friends_comments']; ?></div>

                                <div style="padding: 10px; font-size: 11px; position: relative;">

                                    <?php if (isset($_SESSION['user_id'])): ?>
                                        <form method="POST" action="profile.php?id=<?php echo $profile_id; ?>" class="comment-input-wrapper">
                                            <input type="hidden" name="profile_id" value="<?php echo $profile_id; ?>">
                                            <div class="comment-input-area">
                                                <img src="images/<?php echo isset($_SESSION['profile_pic']) ? htmlspecialchars($_SESSION['profile_pic']) : 'default_avatar.gif'; ?>" alt="You">
                                                <input type="text" name="comment_message" placeholder="<?php echo $t['add_comment']; ?>" maxlength="500" required>
                                                <button type="submit" name="add_comment" class="form-btn" style="margin-left:6px;"><?php echo $t['send']; ?></button>
                                            </div>
                                        </form>
                                    <?php endif; ?>

                                    <div style="max-height: 250px; overflow-y: auto; padding-right: 5px;">
                                        <?php if (count($profile_comments) > 0): ?>
                                            <?php foreach ($profile_comments as $cmt): ?>
                                                <table width="100%" border="0" cellpadding="4" cellspacing="0" style="border-bottom: 1px dashed var(--border-color); margin-bottom: 8px;">
                                                    <tr>
                                                        <td width="80" valign="top" align="center">
                                                            <img src="images/<?php echo htmlspecialchars($cmt['profile_pic'] ?: 'default_avatar.gif'); ?>" style="width:60px; height:60px; background-color: var(--thumb-bg); border: 1px solid var(--thumb-border); object-fit:cover;">
                                                            <a href="profile.php?id=<?php echo $cmt['commenter_id']; ?>"><?php echo htmlspecialchars($cmt['username']); ?></a>
                                                        </td>
                                                        <td valign="top">
                                                            <div style="color: gray; margin-bottom: 5px;"><?php echo date("d.m.Y H:i", strtotime($cmt['created_at'])); ?></div>
                                                            <?php echo nl2br(htmlspecialchars($cmt['message'])); ?><br>
                                                            <?php if (isset($_SESSION['user_id']) && ((int)$_SESSION['user_id'] === (int)$cmt['commenter_id'] || (int)$_SESSION['user_id'] === $profile_id)): ?>
                                                                <a href="?id=<?php echo $profile_id; ?>&delete_comment=<?php echo $cmt['id']; ?>" class="comment-delete-btn">[<?php echo $t['delete']; ?>]</a>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                </table>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div style="text-align:center; color:gray; padding:15px 0;"><?php echo $t['no_comments_yet']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($comment_total_pages > 1): ?>
                                        <div class="pagination-bar">
                                            <a href="<?php echo profile_url($profile_id, ['page' => max(1, $cpage - 1)]); ?>" class="pg-nav <?php echo $cpage <= 1 ? 'disabled' : ''; ?>">&lt;</a>
                                            <?php foreach (paginate_page_numbers($cpage, $comment_total_pages) as $p): ?>
                                                <?php if ($p === '...'): ?>
                                                    <span class="pg-ellipsis">&hellip;</span>
                                                <?php elseif ($p == $cpage): ?>
                                                    <span class="pg-current"><?php echo $p; ?></span>
                                                <?php else: ?>
                                                    <a href="<?php echo profile_url($profile_id, ['page' => $p]); ?>"><?php echo $p; ?></a>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            <a href="<?php echo profile_url($profile_id, ['page' => min($comment_total_pages, $cpage + 1)]); ?>" class="pg-nav <?php echo $cpage >= $comment_total_pages ? 'disabled' : ''; ?>">&gt;</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr>
            <td valign="bottom" style="padding-top: 10px;">
                <div class="footer" style="text-align:center; padding:10px; font-size:12px;">
                    <a href="qa.php"><?php echo $t['qa']; ?></a> |
                    <a href="privacy.php"><?php echo $t['privacy']; ?></a> |
                    <a href="help.php"><?php echo $t['help']; ?></a> |
                    <a href="terms.php"><?php echo $t['terms']; ?></a>
                    <div class="footer-copy" style="margin-top:5px; color:gray;">© <?php echo date("Y"); ?> myArt+ | <?php echo $t['all_rights_reserved']; ?></div>
                </div>
            </td>
        </tr>
    </table>

    <!-- ================= BOOTSTRAP SCRIPTS & MODAL ================= -->

    <!-- Modal (Büyütülen Fotoğrafın Çıktığı Ekran) -->
    <div class="modal fade" id="artModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content" style="background: transparent; border: none;">
                <div class="modal-body text-center">
                    <img id="modalEnlargedImage" src="" class="img-fluid" style="border: 4px solid var(--header-blue, #ff00ff); border-radius: 10px; max-height: 85vh;">
                    <br>
                    <button type="button" class="btn btn-dark mt-3" data-dismiss="modal" style="border: 1px solid #ff00ff; font-weight:bold;">X Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap & jQuery Kütüphaneleri -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>

    <!-- Resim Büyütme Fonksiyonu -->
    <script>
        function enlargeImage(src) {
            document.getElementById('modalEnlargedImage').src = src;
            $('#artModal').modal('show');
        }
    </script>

    <?php if ($me > 0): ?>
        <?php include 'chat_widget.php'; ?>
    <?php endif; ?>

</body>

</html>