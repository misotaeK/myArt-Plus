<?php
session_start();

// Veritabanı bağlantısı
require_once "config.php"; // db.php yerine config.php kullanıyorduk, projendeki isme göre ayarlarsın.

// --- GÜVENLİK KONTROLÜ: Sadece Adminler Girebilir ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // Admin değilse ana sayfaya yolla
    header("Location: index.php");
    exit();
}

// --- DİL VE TEMA SİSTEMİ ---
if (!isset($_SESSION['lang'])) $_SESSION['lang'] = 'en';
if (isset($_GET['lang']) && in_array($_GET['lang'], ['tr', 'en'])) $_SESSION['lang'] = $_GET['lang'];
$current_lang = $_SESSION['lang'];
require_once "lang/" . $current_lang . ".php";

if (!isset($_SESSION['theme'])) $_SESSION['theme'] = 'light';
if (isset($_GET['theme']) && in_array($_GET['theme'], ['light', 'dark'])) $_SESSION['theme'] = $_GET['theme'];
$current_theme = $_SESSION['theme'];

// ==========================================
// 0. ŞİKAYET İNCELEME
// ==========================================
if (isset($_GET['review_report'])) {
    $report_id = (int)$_GET['review_report'];
    $stmt = mysqli_prepare($conn, "UPDATE reports SET status = 'reviewed' WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $report_id);
    mysqli_stmt_execute($stmt);
    header("Location: admin.php");
    exit();
}

// ==========================================
// 1. KULLANICI CRUD OPERASYONLARI
// ==========================================
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $target_id = (int)$_GET['id'];

    // Adminin kendini silmesini veya dondurmasını engelliyoruz
    if ($target_id !== $_SESSION['user_id']) {
        // Durum Güncelleme İşlemleri (Update)
        if (in_array($action, ['active', 'suspended', 'banned'])) {
            $stmt = mysqli_prepare($conn, "UPDATE users SET account_status = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $action, $target_id);
            mysqli_stmt_execute($stmt);
        }
        // Kullanıcı Silme İşlemi (Delete)
        elseif ($action === 'delete') {
            $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $target_id);
            mysqli_stmt_execute($stmt);
        }
    }
    header("Location: admin.php");
    exit();
}

// ==========================================
// 2. YENİ KURULAN GRUPLARI ONAYLAMA
// ==========================================
if (isset($_GET['approve_group'])) {
    $g_id = (int)$_GET['approve_group'];
    mysqli_query($conn, "UPDATE `groups` SET status = 'approved' WHERE id = '$g_id'");
    $g_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT creator_id, name FROM `groups` WHERE id = '$g_id'"));
    if ($g_row) {
        $notif_msg = str_replace(':name', $g_row['name'], isset($t['notif_group_approved']) ? $t['notif_group_approved'] : 'Your group ":name" has been approved and is now public!');
        add_notification($conn, $g_row['creator_id'], 'group_approved', $notif_msg, 'groups.php');
    }
    header("Location: admin.php?msg=approved");
    exit();
}

if (isset($_GET['reject_group'])) {
    $g_id = (int)$_GET['reject_group'];
    mysqli_query($conn, "UPDATE `groups` SET status = 'rejected' WHERE id = '$g_id'");
    $g_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT creator_id, name FROM `groups` WHERE id = '$g_id'"));
    if ($g_row) {
        $notif_msg = str_replace(':name', $g_row['name'], isset($t['notif_group_rejected']) ? $t['notif_group_rejected'] : 'Your group ":name" was rejected by an admin.');
        add_notification($conn, $g_row['creator_id'], 'group_rejected', $notif_msg, 'groups.php');
    }
    header("Location: admin.php?msg=rejected");
    exit();
}

// --- KULLANICI ARAMA VE FİLTRELEME ---
$search_q = isset($_GET['q']) ? trim($_GET['q']) : '';
$filter_gender = isset($_GET['gender']) ? $_GET['gender'] : '';
$filter_role = isset($_GET['role']) ? $_GET['role'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

$where = [];
$params = [];
$types = '';

if ($search_q !== '') {
    $where[] = "(username LIKE ? OR email LIKE ?)";
    $like = "%$search_q%";
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}
if (in_array($filter_gender, ['m', 'f', 'nb', 'unspecified'])) {
    $where[] = "gender = ?";
    $params[] = $filter_gender;
    $types .= 's';
}
if (in_array($filter_role, ['user', 'admin'])) {
    $where[] = "role = ?";
    $params[] = $filter_role;
    $types .= 's';
}
if (in_array($filter_status, ['active', 'suspended', 'banned'])) {
    $where[] = "account_status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

$users_sql = "SELECT id, username, email, gender, role, account_status, created_at FROM users";
if (count($where) > 0) {
    $users_sql .= " WHERE " . implode(' AND ', $where);
}
$users_sql .= " ORDER BY id DESC";

$users_stmt = mysqli_prepare($conn, $users_sql);
if ($types !== '') {
    mysqli_stmt_bind_param($users_stmt, $types, ...$params);
}
mysqli_stmt_execute($users_stmt);
$users_query = mysqli_stmt_get_result($users_stmt);

$pending_groups = mysqli_query($conn, "SELECT g.*, u.username FROM `groups` g JOIN users u ON g.creator_id = u.id WHERE g.status = 'pending' ORDER BY g.created_at ASC");
$open_reports = mysqli_query($conn, "SELECT r.*, ru.username AS reported_username, rep.username AS reporter_username FROM reports r JOIN users ru ON r.reported_user_id = ru.id JOIN users rep ON r.reporter_id = rep.id WHERE r.status = 'open' ORDER BY r.created_at DESC");
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">

<head>
    <meta charset="UTF-8">
    <title>My Cool Space - Admin Panel</title>
    <link rel="stylesheet" href="style.css?v=27">
    <style>
        /* Admin paneli tablosu için 2000'ler tarzı basit CSS */
        .admin-filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 12px;
            align-items: center;
        }

        .admin-filter-input {
            font-size: 11px;
            padding: 5px;
            border: 1px solid var(--border-color);
            background: var(--bg-color);
            color: var(--text-color);
        }

        .admin-filter-bar input.admin-filter-input {
            flex-grow: 1;
            min-width: 160px;
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            color: var(--text-color);
        }

        .admin-table th {
            background-color: var(--thumb-bg);
            padding: 8px;
            border: 1px solid var(--border-color);
            text-align: left;
        }

        .admin-table td {
            padding: 8px;
            border: 1px solid var(--border-color);
        }

        .action-link {
            font-weight: bold;
            padding: 2px 5px;
            border: 1px solid var(--border-color);
            font-size: 10px;
            background: var(--box-bg);
            text-decoration: none;
            display: inline-block;
            margin: 1px;
        }

        .action-link:hover {
            text-decoration: underline;
        }

        .action-suspend {
            color: #cc6600;
        }

        .action-ban {
            color: #cc0000;
        }

        .action-activate {
            color: #008800;
        }

        .action-delete {
            color: #ffffff;
            background-color: #cc0000;
        }

        .status-badge {
            font-weight: bold;
            padding: 2px 4px;
            border-radius: 3px;
        }
    </style>
</head>

<body class="<?php echo $current_theme; ?>">
<div class="marquee-wrap"><div class="marquee-text">★ WELCOME TO MYART+ ★ SHARE YOUR ART WITH THE WORLD ★ JOIN THE FORUM ★ NEW EVENTS POSTED WEEKLY ★</div></div>

    <table width="960" border="0" cellpadding="0" cellspacing="0" align="center" class="main-wrapper">

        <tr height="35">
            <td>
                <table width="100%" border="0" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="50%" align="left" valign="bottom">
                            <a href="index.php">
                                <img src="logo.png" alt="Site Logosu" border="0" class="site-logo">
                            </a>
                        </td>
                        <td width="50%" align="right" valign="top" class="top-controls" style="padding-top: 5px;">
                            <?php echo $t['theme']; ?>
                            <?php if ($current_theme == 'light'): ?>
                                <span class="active"><?php echo $t['light']; ?></span> | <a href="?theme=dark"><?php echo $t['dark']; ?></a>
                            <?php else: ?>
                                <a href="?theme=light"><?php echo $t['light']; ?></a> | <span class="active"><?php echo $t['dark']; ?></span>
                            <?php endif; ?>
                            &nbsp;&nbsp;&nbsp;
                            <?php echo $t['lang_label']; ?>
                            <?php if ($current_lang == 'en'): ?><a href="?lang=tr">TR</a> | <span class="active">EN</span><?php else: ?><span class="active">TR</span> | <a href="?lang=en">EN</a><?php endif; ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr height="30">
            <td>
                <?php $current_page = 'admin'; include 'navbar.php'; ?>
            </td>
        </tr>

        <tr>
            <td valign="top" style="padding: 10px 8px;">

                <!-- KULLANICI YÖNETİMİ KUTUSU -->
                <div class="box">
                    <div class="header-blue"><?php echo $t['user_management']; ?></div>
                    <div style="padding: 15px; overflow-x: auto;">

                        <form method="GET" action="admin.php" class="admin-filter-bar">
                            <input type="text" name="q" value="<?php echo htmlspecialchars($search_q); ?>" placeholder="<?php echo htmlspecialchars($t['search_placeholder']); ?>" class="admin-filter-input">
                            <select name="gender" class="admin-filter-input">
                                <option value=""><?php echo $t['filter_all_genders']; ?></option>
                                <option value="m" <?php echo $filter_gender === 'm' ? 'selected' : ''; ?>><?php echo $t['gender_male']; ?></option>
                                <option value="f" <?php echo $filter_gender === 'f' ? 'selected' : ''; ?>><?php echo $t['gender_female']; ?></option>
                                <option value="nb" <?php echo $filter_gender === 'nb' ? 'selected' : ''; ?>><?php echo $t['gender_other']; ?></option>
                                <option value="unspecified" <?php echo $filter_gender === 'unspecified' ? 'selected' : ''; ?>><?php echo $t['gender_unspecified']; ?></option>
                            </select>
                            <select name="role" class="admin-filter-input">
                                <option value=""><?php echo $t['filter_all_roles']; ?></option>
                                <option value="user" <?php echo $filter_role === 'user' ? 'selected' : ''; ?>>USER</option>
                                <option value="admin" <?php echo $filter_role === 'admin' ? 'selected' : ''; ?>>ADMIN</option>
                            </select>
                            <select name="status" class="admin-filter-input">
                                <option value=""><?php echo $t['filter_all_statuses']; ?></option>
                                <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>><?php echo $t['active']; ?></option>
                                <option value="suspended" <?php echo $filter_status === 'suspended' ? 'selected' : ''; ?>><?php echo $t['suspended']; ?></option>
                                <option value="banned" <?php echo $filter_status === 'banned' ? 'selected' : ''; ?>><?php echo $t['banned']; ?></option>
                            </select>
                            <button type="submit" class="form-btn"><?php echo $t['search_btn']; ?></button>
                            <?php if ($search_q !== '' || $filter_gender !== '' || $filter_role !== '' || $filter_status !== ''): ?>
                                <a href="admin.php" class="form-btn" style="background:#888; text-decoration:none; display:inline-block;"><?php echo $t['clear_filters']; ?></a>
                            <?php endif; ?>
                        </form>

                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th><?php echo $t['id_col']; ?></th>
                                    <th><?php echo $t['username_col']; ?></th>
                                    <th><?php echo $t['email_col']; ?></th>
                                    <th><?php echo $t['gender_col']; ?></th>
                                    <th><?php echo $t['role_col']; ?></th>
                                    <th><?php echo $t['status_col']; ?></th>
                                    <th><?php echo $t['date_joined_col']; ?></th>
                                    <th><?php echo $t['actions_col']; ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($users_query) === 0): ?>
                                    <tr>
                                        <td colspan="8" align="center" style="padding: 20px; color: gray;"><?php echo $t['no_users_found']; ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php while ($row = mysqli_fetch_assoc($users_query)): ?>
                                    <tr>
                                        <td>#<?php echo $row['id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($row['username']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                                        <td>
                                            <?php
                                            $gender_labels = ['m' => $t['gender_male'], 'f' => $t['gender_female'], 'nb' => $t['gender_other'], 'unspecified' => $t['gender_unspecified']];
                                            echo $gender_labels[$row['gender']] ?? htmlspecialchars($row['gender']);
                                            ?>
                                        </td>
                                        <td><?php echo strtoupper($row['role']); ?></td>
                                        <td>
                                            <?php
                                            if ($row['account_status'] == 'active') echo "<span class='status-badge' style='color:green;'>" . $t['active'] . "</span>";
                                            elseif ($row['account_status'] == 'suspended') echo "<span class='status-badge' style='color:orange;'>" . $t['suspended'] . "</span>";
                                            elseif ($row['account_status'] == 'banned') echo "<span class='status-badge' style='color:red;'>" . $t['banned'] . "</span>";
                                            ?>
                                        </td>
                                        <td><?php echo date("d.m.Y", strtotime($row['created_at'])); ?></td>
                                        <td>
                                            <?php if ($row['id'] !== $_SESSION['user_id']): ?>
                                                <?php if ($row['account_status'] !== 'active'): ?>
                                                    <a href="?action=active&id=<?php echo $row['id']; ?>" class="action-link action-activate"><?php echo $t['reactivate']; ?></a>
                                                <?php else: ?>
                                                    <a href="?action=suspended&id=<?php echo $row['id']; ?>" class="action-link action-suspend" onclick="return confirm('<?php echo htmlspecialchars($t['confirm_suspend']); ?>');"><?php echo $t['suspend']; ?></a>
                                                <?php endif; ?>
                                                <a href="?action=banned&id=<?php echo $row['id']; ?>" class="action-link action-ban" onclick="return confirm('<?php echo htmlspecialchars($t['confirm_ban']); ?>');"><?php echo $t['ban']; ?></a>
                                                <a href="?action=delete&id=<?php echo $row['id']; ?>" class="action-link action-delete" onclick="return confirm('<?php echo htmlspecialchars($t['confirm_delete_user']); ?>');"><?php echo $t['delete']; ?></a>
                                            <?php else: ?>
                                                <span style="color: gray; font-style: italic;"><?php echo $t['you_admin']; ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- GRUP YÖNETİMİ KUTUSU (YENİ EKLENEN KISIM) -->
                <div class="box" style="margin-top: 15px;">
                    <div class="header-blue" style="background-color: #ff00ff; color: #fff;"><?php echo $t['group_management']; ?></div>
                    <div style="padding: 15px; overflow-x: auto;">
                        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'approved'): ?>
                            <div style="color: green; font-weight: bold; margin-bottom: 10px;"><?php echo $t['group_approved_msg']; ?></div>
                        <?php elseif (isset($_GET['msg']) && $_GET['msg'] == 'rejected'): ?>
                            <div style="color: red; font-weight: bold; margin-bottom: 10px;"><?php echo $t['group_rejected_msg']; ?></div>
                        <?php endif; ?>

                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th><?php echo $t['group_col']; ?></th>
                                    <th><?php echo $t['creator_col']; ?></th>
                                    <th><?php echo $t['tag_col']; ?></th>
                                    <th><?php echo $t['description_col']; ?></th>
                                    <th><?php echo $t['actions_col']; ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($pending_groups) > 0): ?>
                                    <?php while ($g_row = mysqli_fetch_assoc($pending_groups)): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($g_row['name']); ?></strong></td>
                                            <td><a href="profile.php?id=<?php echo $g_row['creator_id']; ?>" style="color: var(--link-color); text-decoration:none;"><?php echo htmlspecialchars($g_row['username']); ?></a></td>
                                            <td><?php echo htmlspecialchars($g_row['tag']); ?></td>
                                            <td><?php echo htmlspecialchars($g_row['description']); ?></td>
                                            <td>
                                                <a href="?approve_group=<?php echo $g_row['id']; ?>" class="action-link action-activate"><?php echo $t['approve']; ?></a>
                                                <a href="?reject_group=<?php echo $g_row['id']; ?>" class="action-link action-delete" onclick="return confirm('<?php echo htmlspecialchars($t['confirm_reject_group']); ?>');"><?php echo $t['reject']; ?></a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" align="center" style="padding: 20px; color: gray;"><?php echo $t['no_pending_groups']; ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ŞİKAYET YÖNETİMİ KUTUSU -->
                <div class="box" style="margin-top: 15px;">
                    <div class="header-blue" style="background-color: #cc0000; color: #fff;"><?php echo $t['reports_management']; ?></div>
                    <div style="padding: 15px; overflow-x: auto;">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th><?php echo $t['reported_by_col']; ?></th>
                                    <th><?php echo $t['username_col']; ?></th>
                                    <th><?php echo $t['report_reason_col']; ?></th>
                                    <th><?php echo $t['date_joined_col']; ?></th>
                                    <th><?php echo $t['actions_col']; ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($open_reports) > 0): ?>
                                    <?php while ($r_row = mysqli_fetch_assoc($open_reports)): ?>
                                        <tr>
                                            <td><a href="profile.php?id=<?php echo $r_row['reporter_id']; ?>" style="color: var(--link-color); text-decoration:none;"><?php echo htmlspecialchars($r_row['reporter_username']); ?></a></td>
                                            <td><a href="profile.php?id=<?php echo $r_row['reported_user_id']; ?>" style="color: var(--link-color); text-decoration:none;"><?php echo htmlspecialchars($r_row['reported_username']); ?></a></td>
                                            <td><?php echo htmlspecialchars($r_row['reason']); ?></td>
                                            <td><?php echo date("d.m.Y", strtotime($r_row['created_at'])); ?></td>
                                            <td>
                                                <a href="?review_report=<?php echo $r_row['id']; ?>" class="action-link action-activate"><?php echo $t['mark_reviewed']; ?></a>
                                                <a href="?action=suspended&id=<?php echo $r_row['reported_user_id']; ?>" class="action-link action-suspend" onclick="return confirm('<?php echo htmlspecialchars($t['confirm_suspend']); ?>');"><?php echo $t['suspend']; ?></a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" align="center" style="padding: 20px; color: gray;"><?php echo $t['no_reports']; ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </td>
        </tr>

        <tr>
            <td valign="bottom" style="padding-top: 10px;">
                <div class="footer">
                    <div class="footer-copy">© <?php echo date("Y"); ?> myArt+ | <?php echo $t['admin_control_panel']; ?></div>
                </div>
            </td>
        </tr>
    </table>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <?php include 'chat_widget.php'; ?>
</body>

</html>