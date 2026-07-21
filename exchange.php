<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['lang'])) $_SESSION['lang'] = 'en';
if (isset($_GET['lang']) && in_array($_GET['lang'], ['tr', 'en'])) $_SESSION['lang'] = $_GET['lang'];
$current_lang = $_SESSION['lang'];
require_once "lang/" . $current_lang . ".php";

if (!isset($_SESSION['theme'])) $_SESSION['theme'] = 'light';
if (isset($_GET['theme']) && in_array($_GET['theme'], ['light', 'dark'])) $_SESSION['theme'] = $_GET['theme'];
$current_theme = $_SESSION['theme'];

$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? (int)$_SESSION['user_id'] : 0;
$exchange_tags = ['Art', 'Craft', 'Tattoo', 'Writing', 'Music', 'Other'];
$exchange_tag_colors = [
    'Art' => '#ec008c',
    'Craft' => '#ff6b35',
    'Tattoo' => '#00b8a9',
    'Writing' => '#6c5ce7',
    'Music' => '#3498db',
    'Other' => '#888888',
];

// --- YENİ DEĞİŞİM İLANI OLUŞTURMA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_exchange']) && $is_logged_in) {
    $ex_title = trim($_POST['exchange_title'] ?? '');
    $ex_offering = trim($_POST['offering'] ?? '');
    $ex_seeking = trim($_POST['seeking'] ?? '');
    $ex_desc = trim($_POST['exchange_desc'] ?? '');
    $ex_tag = in_array($_POST['exchange_tag'] ?? '', $exchange_tags) ? $_POST['exchange_tag'] : 'Other';

    if ($ex_title !== '' && $ex_offering !== '' && $ex_seeking !== '') {
        $stmt = mysqli_prepare($conn, "INSERT INTO exchanges (user_id, title, description, offering, seeking, tag) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isssss", $user_id, $ex_title, $ex_desc, $ex_offering, $ex_seeking, $ex_tag);
        mysqli_stmt_execute($stmt);
    }
    header("Location: exchange.php");
    exit();
}

// --- TAMAMLANDI OLARAK İŞARETLE / YENİDEN AÇ (Sadece sahibi) ---
if ((isset($_GET['mark_completed']) || isset($_GET['reopen_exchange'])) && $is_logged_in) {
    $ex_id = (int)($_GET['mark_completed'] ?? $_GET['reopen_exchange']);
    $new_status = isset($_GET['mark_completed']) ? 'completed' : 'open';
    $stmt = mysqli_prepare($conn, "UPDATE exchanges SET status = ? WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($stmt, "sii", $new_status, $ex_id, $user_id);
    mysqli_stmt_execute($stmt);
    header("Location: exchange.php");
    exit();
}

// --- İLANI SİLME (Sadece sahibi) ---
if (isset($_GET['delete_exchange']) && $is_logged_in) {
    $ex_id = (int)$_GET['delete_exchange'];
    $stmt = mysqli_prepare($conn, "DELETE FROM exchanges WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $ex_id, $user_id);
    mysqli_stmt_execute($stmt);
    header("Location: exchange.php");
    exit();
}

// --- İLANLARI LİSTELEME ---
$filter_tag = isset($_GET['tag']) && in_array($_GET['tag'], $exchange_tags) ? $_GET['tag'] : '';

$sql = "SELECT e.*, u.username, u.profile_pic FROM exchanges e JOIN users u ON e.user_id = u.id";
if ($filter_tag !== '') {
    $sql .= " WHERE e.tag = '" . mysqli_real_escape_string($conn, $filter_tag) . "'";
}
$sql .= " ORDER BY (e.status = 'open') DESC, e.created_at DESC";
$exchanges_result = mysqli_query($conn, $sql);

// --- BENİM İLANLARIM (Durumlarını hızlıca görebilmek için ayrı bir tablo) ---
$my_exchanges_result = null;
if ($is_logged_in) {
    $my_stmt = mysqli_prepare($conn, "SELECT * FROM exchanges WHERE user_id = ? ORDER BY (status = 'open') DESC, created_at DESC");
    mysqli_stmt_bind_param($my_stmt, "i", $user_id);
    mysqli_stmt_execute($my_stmt);
    $my_exchanges_result = mysqli_stmt_get_result($my_stmt);
}
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">

<head>
    <meta charset="UTF-8">
    <title>myArt+ | <?php echo $t['exchange_title']; ?></title>
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

        .exchange-card {
            position: relative;
            border: 1px dotted var(--link-color);
            box-shadow: 2px 2px 0 var(--shadow-color);
            padding: 18px 14px 12px;
            margin: 18px 0 14px;
            background: var(--box-bg);
        }

        .exchange-card.completed {
            opacity: 0.65;
        }

        .exchange-tag-ribbon {
            position: absolute;
            top: -1px;
            left: -1px;
            color: #fff;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.3px;
            padding: 3px 9px;
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

        .exchange-card-actions {
            position: absolute;
            top: 8px;
            right: 10px;
            display: flex;
            gap: 6px;
        }

        .exchange-card-actions .form-btn {
            padding: 4px 10px;
            font-size: 9px;
        }

        .exchange-title {
            font-weight: bold;
            font-size: 15px;
            color: var(--link-color);
        }

        .exchange-swap-row {
            display: flex;
            align-items: stretch;
            gap: 8px;
            margin: 8px 0;
            font-size: 11px;
        }

        .exchange-swap-box {
            flex: 1;
            padding: 6px 8px;
            border-radius: 4px;
        }

        .exchange-swap-box.offering {
            background: var(--ad-bg);
            border: 1px solid var(--ad-border);
        }

        .exchange-swap-box.seeking {
            background: var(--thumb-bg);
            border: 1px solid var(--border-color);
        }

        .exchange-swap-arrow {
            display: flex;
            align-items: center;
            color: var(--footer-text);
            font-size: 16px;
            flex-shrink: 0;
        }

        .exchange-swap-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--footer-text);
            display: block;
            margin-bottom: 2px;
        }

        .exchange-desc {
            font-size: 11px;
            font-style: italic;
            color: var(--footer-text);
            margin: 5px 0;
        }

        .exchange-card-footer {
            font-size: 11px;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .exchange-avatar {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 1px solid var(--border-color);
            object-fit: cover;
            margin-right: 4px;
        }

        .exchange-status-dot {
            font-size: 9px;
        }

        .exchange-status-open {
            color: green;
            font-weight: bold;
        }

        .exchange-status-completed {
            color: gray;
            font-weight: bold;
        }

        .my-exchanges-table-wrap {
            overflow-x: auto;
            overflow-y: auto;
            max-height: 90px;
        }

        tr.exchange-columns-row {
            display: flex;
            width: 100%;
        }

        tr.exchange-columns-row > td {
            box-sizing: border-box;
        }

        tr.exchange-columns-row > td:last-child {
            display: flex;
            flex-direction: column;
        }

        .exchange-list-box {
            flex: 1 1 auto;
            height: auto;
            min-height: 300px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
        }

        .community-exchanges-list {
            flex: 1;
            min-height: 0;
            box-sizing: border-box;
            overflow-y: auto;
        }

        .my-exchanges-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .my-exchanges-table th {
            background: var(--thumb-bg);
            color: var(--footer-text);
            text-align: left;
            padding: 8px 10px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--footer-border);
            white-space: nowrap;
        }

        .my-exchanges-table td {
            padding: 8px 10px;
            border-bottom: 1px solid var(--footer-border);
            vertical-align: top;
        }

        .my-exchanges-table tbody tr:hover td {
            background: var(--ad-bg);
        }

        .my-ex-tag-chip {
            display: inline-block;
            color: #fff;
            font-size: 9px;
            font-weight: bold;
            padding: 2px 7px;
            white-space: nowrap;
        }

        .my-ex-title-cell {
            font-weight: bold;
            color: var(--link-color);
            max-width: 160px;
        }

        .my-ex-actions {
            white-space: nowrap;
        }

        .my-ex-actions .form-btn {
            padding: 3px 8px;
            font-size: 9px;
            margin-right: 4px;
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
                <?php $current_page = 'exchange'; include 'navbar.php'; ?>
            </td>
        </tr>

        <tr>
            <td valign="top" style="padding: 10px 8px;">
                <table width="100%" border="0" cellpadding="6" cellspacing="0">
                    <tr class="exchange-columns-row">
                        <td width="30%" valign="top">

                            <div class="box">
                                <div class="header-blue"><?php echo $t['filter_by_tags']; ?></div>
                                <div class="tag-list" style="padding: 5px;">
                                    <a href="exchange.php" class="<?php echo $filter_tag === '' ? 'active' : ''; ?>"><?php echo $t['all_exchanges']; ?></a>
                                    <a href="exchange.php?tag=Art" class="<?php echo ($filter_tag == 'Art') ? 'active' : ''; ?>"><?php echo $t['tag_art']; ?></a>
                                    <a href="exchange.php?tag=Craft" class="<?php echo ($filter_tag == 'Craft') ? 'active' : ''; ?>"><?php echo $t['tag_craft']; ?></a>
                                    <a href="exchange.php?tag=Tattoo" class="<?php echo ($filter_tag == 'Tattoo') ? 'active' : ''; ?>"><?php echo $t['tag_tattoo']; ?></a>
                                    <a href="exchange.php?tag=Writing" class="<?php echo ($filter_tag == 'Writing') ? 'active' : ''; ?>"><?php echo $t['tag_writing']; ?></a>
                                    <a href="exchange.php?tag=Music" class="<?php echo ($filter_tag == 'Music') ? 'active' : ''; ?>"><?php echo $t['tag_music']; ?></a>
                                    <a href="exchange.php?tag=Other" class="<?php echo ($filter_tag == 'Other') ? 'active' : ''; ?>"><?php echo $t['tag_other']; ?></a>
                                </div>
                            </div>

                            <?php if ($is_logged_in): ?>
                                <div class="box" style="margin-top: 15px;">
                                    <div class="header-blue"><?php echo $t['post_exchange']; ?></div>
                                    <div style="padding: 10px;">
                                        <form method="POST" action="exchange.php">
                                            <label style="font-size: 11px; font-weight: bold; color: var(--text-color);"><?php echo $t['exchange_title_label']; ?></label>
                                            <input type="text" name="exchange_title" maxlength="150" required class="form-input">

                                            <label style="font-size: 11px; font-weight: bold; color: var(--text-color);"><?php echo $t['tag_label']; ?></label>
                                            <select name="exchange_tag" class="form-input">
                                                <option value="Art"><?php echo $t['tag_art']; ?></option>
                                                <option value="Craft"><?php echo $t['tag_craft']; ?></option>
                                                <option value="Tattoo"><?php echo $t['tag_tattoo']; ?></option>
                                                <option value="Writing"><?php echo $t['tag_writing']; ?></option>
                                                <option value="Music"><?php echo $t['tag_music']; ?></option>
                                                <option value="Other"><?php echo $t['tag_other']; ?></option>
                                            </select>

                                            <label style="font-size: 11px; font-weight: bold; color: var(--text-color);"><?php echo $t['offering_label']; ?></label>
                                            <input type="text" name="offering" maxlength="255" required placeholder="<?php echo htmlspecialchars($t['offering_placeholder']); ?>" class="form-input">

                                            <label style="font-size: 11px; font-weight: bold; color: var(--text-color);"><?php echo $t['seeking_label']; ?></label>
                                            <input type="text" name="seeking" maxlength="255" required placeholder="<?php echo htmlspecialchars($t['seeking_placeholder']); ?>" class="form-input">

                                            <label style="font-size: 11px; font-weight: bold; color: var(--text-color);"><?php echo $t['exchange_desc_label']; ?></label>
                                            <textarea name="exchange_desc" rows="3" class="form-input"></textarea>

                                            <button type="submit" name="post_exchange" class="form-btn"><?php echo $t['post_exchange_btn']; ?></button>
                                        </form>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="box" style="margin-top: 15px; text-align: center; padding: 15px;">
                                    <span style="font-size: 11px; color: gray;"><?php echo $t['must_login_exchange']; ?></span><br>
                                    <a href="index.php" style="font-size: 11px; color: var(--link-color); font-weight: bold;"><?php echo $t['login_here']; ?></a>
                                </div>
                            <?php endif; ?>

                        </td>

                        <td width="70%" valign="top">
                            <div class="box exchange-list-box">
                                <div class="header-blue"><?php echo $t['community_exchanges']; ?></div>
                                <div class="community-exchanges-list" style="padding: 15px;">

                                    <?php if (mysqli_num_rows($exchanges_result) > 0): ?>
                                        <?php while ($ex = mysqli_fetch_assoc($exchanges_result)):
                                            $tag_color = $exchange_tag_colors[$ex['tag']] ?? '#888888';
                                            $is_new = isset($ex['created_at']) && (time() - strtotime($ex['created_at'])) < 172800;
                                        ?>
                                            <div class="exchange-card <?php echo $ex['status'] === 'completed' ? 'completed' : ''; ?>">
                                                <div class="exchange-tag-ribbon" style="background:<?php echo $tag_color; ?>;"><?php echo htmlspecialchars($ex['tag']); ?></div>

                                                <?php if ($is_logged_in && (int)$ex['user_id'] === $user_id): ?>
                                                    <div class="exchange-card-actions">
                                                        <?php if ($ex['status'] === 'open'): ?>
                                                            <a href="?mark_completed=<?php echo $ex['id']; ?>" class="form-btn" style="background-color:green;"><?php echo $t['mark_completed']; ?></a>
                                                        <?php else: ?>
                                                            <a href="?reopen_exchange=<?php echo $ex['id']; ?>" class="form-btn"><?php echo $t['reopen_exchange']; ?></a>
                                                        <?php endif; ?>
                                                        <a href="?delete_exchange=<?php echo $ex['id']; ?>" class="form-btn" style="background-color:#cc0000;" onclick="return confirm('<?php echo htmlspecialchars($t['confirm_delete_exchange']); ?>');"><?php echo $t['delete_exchange']; ?></a>
                                                    </div>
                                                <?php endif; ?>

                                                <span class="exchange-title"><?php echo htmlspecialchars($ex['title']); ?></span>
                                                <?php if ($is_new): ?><span class="new-badge"><?php echo $t['new_badge_label']; ?></span><?php endif; ?>

                                                <div class="exchange-swap-row">
                                                    <div class="exchange-swap-box offering">
                                                        <span class="exchange-swap-label"><?php echo $t['offering_label']; ?></span>
                                                        <?php echo htmlspecialchars($ex['offering']); ?>
                                                    </div>
                                                    <div class="exchange-swap-arrow">&#8644;</div>
                                                    <div class="exchange-swap-box seeking">
                                                        <span class="exchange-swap-label"><?php echo $t['seeking_label']; ?></span>
                                                        <?php echo htmlspecialchars($ex['seeking']); ?>
                                                    </div>
                                                </div>

                                                <?php if ($ex['description'] !== ''): ?>
                                                    <div class="exchange-desc"><?php echo nl2br(htmlspecialchars($ex['description'])); ?></div>
                                                <?php endif; ?>

                                                <div class="exchange-card-footer">
                                                    <img src="images/<?php echo htmlspecialchars($ex['profile_pic'] ?: 'default_avatar.gif'); ?>" class="exchange-avatar">
                                                    <a href="profile.php?id=<?php echo $ex['user_id']; ?>"><?php echo htmlspecialchars($ex['username']); ?></a>
                                                    &middot;
                                                    <span class="exchange-status-dot" style="color:<?php echo $ex['status'] === 'open' ? 'green' : 'gray'; ?>;">&#9679;</span>
                                                    <span class="<?php echo $ex['status'] === 'open' ? 'exchange-status-open' : 'exchange-status-completed'; ?>">
                                                        <?php echo $ex['status'] === 'open' ? $t['exchange_status_open'] : $t['exchange_status_completed']; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <div style="text-align: center; padding: 20px; font-size: 12px; color: gray;">
                                            <?php echo $t['no_exchanges_found']; ?>
                                        </div>
                                    <?php endif; ?>

                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <?php if ($is_logged_in): ?>
        <tr>
            <td valign="top" style="padding: 0 8px;">
                <div class="box">
                    <div class="header-blue" style="background-color: #333333; color: #fff;"><?php echo $t['my_exchange_offers']; ?></div>
                    <?php if (mysqli_num_rows($my_exchanges_result) > 0): ?>
                        <div class="my-exchanges-table-wrap">
                            <table class="my-exchanges-table">
                                <thead>
                                    <tr>
                                        <th><?php echo $t['col_title']; ?></th>
                                        <th><?php echo $t['col_tag']; ?></th>
                                        <th><?php echo $t['col_offering']; ?></th>
                                        <th><?php echo $t['col_seeking']; ?></th>
                                        <th><?php echo $t['col_status']; ?></th>
                                        <th><?php echo $t['col_posted']; ?></th>
                                        <th><?php echo $t['col_actions']; ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($mex = mysqli_fetch_assoc($my_exchanges_result)):
                                        $mex_tag_color = $exchange_tag_colors[$mex['tag']] ?? '#888888';
                                    ?>
                                        <tr>
                                            <td class="my-ex-title-cell"><?php echo htmlspecialchars($mex['title']); ?></td>
                                            <td><span class="my-ex-tag-chip" style="background:<?php echo $mex_tag_color; ?>;"><?php echo htmlspecialchars($mex['tag']); ?></span></td>
                                            <td><?php echo htmlspecialchars($mex['offering']); ?></td>
                                            <td><?php echo htmlspecialchars($mex['seeking']); ?></td>
                                            <td>
                                                <span class="<?php echo $mex['status'] === 'open' ? 'exchange-status-open' : 'exchange-status-completed'; ?>">
                                                    <?php echo $mex['status'] === 'open' ? $t['exchange_status_open'] : $t['exchange_status_completed']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d.m.Y', strtotime($mex['created_at'])); ?></td>
                                            <td class="my-ex-actions">
                                                <?php if ($mex['status'] === 'open'): ?>
                                                    <a href="?mark_completed=<?php echo $mex['id']; ?>" class="form-btn" style="background-color:green;"><?php echo $t['mark_completed']; ?></a>
                                                <?php else: ?>
                                                    <a href="?reopen_exchange=<?php echo $mex['id']; ?>" class="form-btn"><?php echo $t['reopen_exchange']; ?></a>
                                                <?php endif; ?>
                                                <a href="?delete_exchange=<?php echo $mex['id']; ?>" class="form-btn" style="background-color:#cc0000;" onclick="return confirm('<?php echo htmlspecialchars($t['confirm_delete_exchange']); ?>');"><?php echo $t['delete_exchange']; ?></a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 20px; font-size: 12px; color: gray;">
                            <?php echo $t['no_my_exchanges']; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endif; ?>

        <tr>
            <td valign="bottom" style="padding-top: 10px;">
                <div class="footer" style="text-align:center; padding:10px; font-size:12px;">
                    <a href="qa.php"><?php echo $t['qa']; ?></a> | <a href="privacy.php"><?php echo $t['privacy']; ?></a> | <a href="help.php"><?php echo $t['help']; ?></a> | <a href="terms.php"><?php echo $t['terms']; ?></a>
                    <div class="footer-copy" style="margin-top:5px; color:gray;">© <?php echo date("Y"); ?> myArt+ | <?php echo $t['all_rights_reserved']; ?></div>
                </div>
            </td>
        </tr>
    </table>

    <?php if ($is_logged_in): ?>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <?php include 'chat_widget.php'; ?>
    <?php endif; ?>

</body>

</html>
