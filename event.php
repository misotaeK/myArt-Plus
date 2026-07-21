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

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS event_rsvps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_rsvp (event_id, user_id)
)");

// events tablosunda location kolonu yoksa ekle (eski kurulumlarla uyumluluk için)
$loc_col_check = mysqli_query($conn, "SHOW COLUMNS FROM events LIKE 'location'");
if ($loc_col_check && mysqli_num_rows($loc_col_check) === 0) {
    mysqli_query($conn, "ALTER TABLE events ADD COLUMN location VARCHAR(255) DEFAULT NULL");
}

$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (isset($_GET['rsvp']) && $is_logged_in) {
    $rsvp_ev_id = (int)$_GET['rsvp'];
    $existing = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM event_rsvps WHERE event_id = $rsvp_ev_id AND user_id = $user_id"));
    if ($existing) {
        mysqli_query($conn, "DELETE FROM event_rsvps WHERE event_id = $rsvp_ev_id AND user_id = $user_id");
    } else {
        $stmt2 = mysqli_prepare($conn, "INSERT INTO event_rsvps (event_id, user_id) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt2, "ii", $rsvp_ev_id, $user_id);
        mysqli_stmt_execute($stmt2);
    }
    header("Location: event.php?id=" . $event_id);
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT e.*, u.username, u.profile_pic,
    (SELECT COUNT(*) FROM event_rsvps r2 WHERE r2.event_id = e.id AND r2.user_id = ?) as im_going
    FROM events e JOIN users u ON e.creator_id = u.id WHERE e.id = ?");
mysqli_stmt_bind_param($stmt, "ii", $user_id, $event_id);
mysqli_stmt_execute($stmt);
$event = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// --- ETKİNLİK YORUMLARI ---
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS event_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_event_comment']) && $is_logged_in) {
    $comment_msg = trim($_POST['comment_message'] ?? '');
    if ($comment_msg !== '' && $event) {
        $stmt2 = mysqli_prepare($conn, "INSERT INTO event_comments (event_id, user_id, message) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt2, "iis", $event_id, $user_id, $comment_msg);
        mysqli_stmt_execute($stmt2);

        if ((int)$event['creator_id'] !== $user_id) {
            $commenter_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT username FROM users WHERE id = $user_id"));
            $notif_msg = str_replace([':user', ':title'], [$commenter_row['username'], $event['title']], isset($t['notif_new_event_comment']) ? $t['notif_new_event_comment'] : ':user commented on your event ":title".');
            add_notification($conn, (int)$event['creator_id'], 'new_event_comment', $notif_msg, 'event.php?id=' . $event_id, $user_id);
        }
    }
    header("Location: event.php?id=" . $event_id);
    exit();
}

if (isset($_GET['delete_event_comment']) && $is_logged_in) {
    $comment_id = (int)$_GET['delete_event_comment'];
    $c_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id FROM event_comments WHERE id = $comment_id AND event_id = $event_id"));
    if ($c_row && ((int)$c_row['user_id'] === $user_id || (isset($event['creator_id']) && (int)$event['creator_id'] === $user_id))) {
        mysqli_query($conn, "DELETE FROM event_comments WHERE id = $comment_id");
    }
    header("Location: event.php?id=" . $event_id);
    exit();
}

// Yorumlar için sayfalama
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

function event_url($event_id, $overrides = []) {
    $params = array_merge(['id' => $event_id], $overrides);
    return 'event.php?' . http_build_query($params);
}

$cpage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$cper_page = 10;
$coffset = ($cpage - 1) * $cper_page;

$event_comments = [];
$comment_total_pages = 1;
if ($event) {
    $ec_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM event_comments WHERE event_id = " . (int)$event_id));
    $comment_total_pages = max(1, ceil(((int)$ec_count['total']) / $cper_page));

    $ec_stmt = mysqli_prepare($conn, "SELECT c.*, u.username, u.profile_pic FROM event_comments c JOIN users u ON c.user_id = u.id WHERE c.event_id = ? ORDER BY c.created_at ASC LIMIT ? OFFSET ?");
    mysqli_stmt_bind_param($ec_stmt, "iii", $event_id, $cper_page, $coffset);
    mysqli_stmt_execute($ec_stmt);
    $ec_result = mysqli_stmt_get_result($ec_stmt);
    while ($ec = mysqli_fetch_assoc($ec_result)) {
        $event_comments[] = $ec;
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">

<head>
    <meta charset="UTF-8">
    <title>myArt+ | <?php echo $event ? htmlspecialchars($event['title']) : $t['event_not_found']; ?></title>
    <link rel="stylesheet" href="style.css?v=27">
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
        }

        .main-wrapper {
            background: transparent;
        }

        .event-poster {
            width: 100%;
            max-height: 420px;
            object-fit: cover;
            border-bottom: 1px solid var(--border-color);
            display: block;
        }

        .event-detail-body {
            padding: 15px;
        }

        .event-detail-meta {
            font-size: 11px;
            color: var(--footer-text);
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }

        .event-detail-desc {
            font-size: 13px;
            color: var(--text-color);
            white-space: pre-wrap;
            line-height: 1.5;
        }

        .event-rsvp-btn {
            flex-shrink: 0;
            padding: 6px 14px;
            font-size: 10px;
            font-weight: bold;
            text-decoration: none;
            text-align: center;
            background: var(--header-bg);
            color: #7a6400;
            border: 1px solid #e6d200;
        }

        .event-rsvp-btn.going {
            background: #2a9d2a;
            color: #fff;
            border-color: #1c5c33;
        }

        .event-location-text {
            font-size: 12px;
            color: var(--text-color);
            margin-bottom: 10px;
        }

        .event-comments-list {
            max-height: 420px;
            overflow-y: auto;
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
                        <td width="50%" align="left" valign="bottom"><a href="index.php"><img src="logo.png" alt="Logo" border="0" class="site-logo"></a></td>
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
                <?php $current_page = 'events'; include 'navbar.php'; ?>
            </td>
        </tr>
        <tr>
            <td valign="top" style="padding: 10px 8px;">
                <div class="box" id="eventDetailBox">
                    <?php if (!$event): ?>
                        <div class="header-blue"><?php echo $t['event_not_found']; ?></div>
                        <div style="padding:15px; font-size:12px;"><a href="events.php"><?php echo $t['back_to_events']; ?></a></div>
                    <?php else: ?>
                        <div class="header-blue" style="display:flex; justify-content:space-between; align-items:center;">
                            <span><?php echo htmlspecialchars($event['title']); ?></span>
                            <a href="events.php" style="color:#7a0044; font-size:11px; text-decoration:underline;"><?php echo $t['back_to_events']; ?></a>
                        </div>
                        <?php if ($event['poster_image']): ?>
                            <img src="<?php echo htmlspecialchars($event['poster_image']); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>" class="event-poster">
                        <?php endif; ?>
                        <div class="event-detail-body">
                            <div class="event-detail-meta">
                                <span>
                                    <?php echo date('d.m.Y H:i', strtotime($event['event_date'])); ?>
                                    &middot; <?php echo $t['event_created_by']; ?>
                                    <a href="profile.php?id=<?php echo (int)$event['creator_id']; ?>" style="color:var(--link-color); font-weight:bold;"><?php echo htmlspecialchars($event['username']); ?></a>
                                </span>
                                <?php if ($is_logged_in): ?>
                                    <a href="?id=<?php echo $event['id']; ?>&rsvp=<?php echo $event['id']; ?>" class="event-rsvp-btn <?php echo (int)$event['im_going'] > 0 ? 'going' : ''; ?>">
                                        <?php echo (int)$event['im_going'] > 0 ? $t['rsvp_going'] : $t['rsvp_label']; ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="event-detail-desc"><?php echo htmlspecialchars($event['description']); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php if ($event && !empty($event['location'])): ?>
        <tr>
            <td valign="top" style="padding: 0 8px;">
                <div class="box">
                    <div class="header-blue">📍 <?php echo $t['location_label']; ?></div>
                    <div style="padding: 12px;">
                        <div class="event-location-text"><?php echo htmlspecialchars($event['location']); ?></div>
                    </div>
                </div>
            </td>
        </tr>
        <?php endif; ?>
        <?php if ($event): ?>
        <tr>
            <td valign="top" style="padding: 0 8px;">
                <div class="box">
                    <div class="header-blue"><?php echo $t['event_comments_title']; ?></div>
                    <div style="padding: 10px; font-size: 11px;">
                        <?php if ($is_logged_in): ?>
                            <form method="POST" action="event.php?id=<?php echo $event_id; ?>" class="comment-input-wrapper">
                                <div class="comment-input-area">
                                    <img src="images/<?php echo isset($_SESSION['profile_pic']) ? htmlspecialchars($_SESSION['profile_pic']) : 'default_avatar.gif'; ?>" alt="You">
                                    <input type="text" name="comment_message" placeholder="<?php echo htmlspecialchars($t['add_comment']); ?>" maxlength="500" required>
                                    <button type="submit" name="add_event_comment" class="form-btn" style="margin-left:6px;"><?php echo $t['send']; ?></button>
                                </div>
                            </form>
                        <?php endif; ?>

                        <div class="event-comments-list">
                        <?php if (count($event_comments) > 0): ?>
                            <?php foreach ($event_comments as $cmt): ?>
                                <table width="100%" border="0" cellpadding="4" cellspacing="0" style="border-bottom: 1px dashed var(--border-color); margin-bottom: 8px;">
                                    <tr>
                                        <td width="50" valign="top" align="center">
                                            <img src="images/<?php echo htmlspecialchars($cmt['profile_pic'] ?: 'default_avatar.gif'); ?>" style="width:36px; height:36px; border-radius:50%; background-color: var(--thumb-bg); border: 1px solid var(--thumb-border); object-fit:cover;">
                                        </td>
                                        <td valign="top">
                                            <a href="profile.php?id=<?php echo $cmt['user_id']; ?>" style="font-weight:bold;"><?php echo htmlspecialchars($cmt['username']); ?></a>
                                            <span style="color: gray; margin-left:4px;"><?php echo date("d.m.Y H:i", strtotime($cmt['created_at'])); ?></span>
                                            <div style="margin-top:3px;"><?php echo nl2br(htmlspecialchars($cmt['message'])); ?></div>
                                            <?php if ($is_logged_in && ((int)$cmt['user_id'] === $user_id || (int)$event['creator_id'] === $user_id)): ?>
                                                <a href="?id=<?php echo $event_id; ?>&delete_event_comment=<?php echo $cmt['id']; ?>" class="comment-delete-btn">[<?php echo $t['delete']; ?>]</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            <?php endforeach; ?>
                            <?php if ($comment_total_pages > 1): ?>
                                <div class="pagination-bar">
                                    <a href="<?php echo event_url($event_id, ['page' => max(1, $cpage - 1)]); ?>" class="pg-nav <?php echo $cpage <= 1 ? 'disabled' : ''; ?>">&lt;</a>
                                    <?php foreach (paginate_page_numbers($cpage, $comment_total_pages) as $p): ?>
                                        <?php if ($p === '...'): ?>
                                            <span class="pg-ellipsis">&hellip;</span>
                                        <?php elseif ($p == $cpage): ?>
                                            <span class="pg-current"><?php echo $p; ?></span>
                                        <?php else: ?>
                                            <a href="<?php echo event_url($event_id, ['page' => $p]); ?>"><?php echo $p; ?></a>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    <a href="<?php echo event_url($event_id, ['page' => min($comment_total_pages, $cpage + 1)]); ?>" class="pg-nav <?php echo $cpage >= $comment_total_pages ? 'disabled' : ''; ?>">&gt;</a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div style="text-align:center; color:gray; padding:15px 0;"><?php echo $t['no_comments_yet']; ?></div>
                        <?php endif; ?>
                        </div>
                    </div>
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

    <script>
        (function() {
            var eventBox = document.getElementById('eventDetailBox');
            var commentsList = document.querySelector('.event-comments-list');
            if (!eventBox || !commentsList) return;
            window.addEventListener('load', function() {
                var h = eventBox.getBoundingClientRect().height;
                if (h > 0) commentsList.style.maxHeight = h + 'px';
            });
        })();
    </script>

    <?php if ($is_logged_in): ?>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <?php include 'chat_widget.php'; ?>
    <?php endif; ?>
</body>

</html>
