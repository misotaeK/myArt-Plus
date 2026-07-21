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

// RSVP takibi için tablo (yoksa oluştur)
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS event_rsvps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_rsvp (event_id, user_id)
)");

// --- RSVP AÇ/KAPAT (Toggle) ---
if (isset($_GET['rsvp']) && $is_logged_in) {
    $rsvp_ev_id = (int)$_GET['rsvp'];
    $existing = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM event_rsvps WHERE event_id = $rsvp_ev_id AND user_id = $user_id"));
    if ($existing) {
        mysqli_query($conn, "DELETE FROM event_rsvps WHERE event_id = $rsvp_ev_id AND user_id = $user_id");
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO event_rsvps (event_id, user_id) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, "ii", $rsvp_ev_id, $user_id);
        mysqli_stmt_execute($stmt);
    }
    header("Location: events.php");
    exit();
}

// Aynı sorgu mantığı forum.php / home.php'deki "Yaklaşan Etkinlikler" kutusuyla aynı, + katılım durumu
$upcoming_events = [];
$up_res = mysqli_query($conn, "SELECT e.id, e.title, e.description, e.event_date, e.poster_image, u.username,
    (SELECT COUNT(*) FROM event_rsvps r2 WHERE r2.event_id = e.id AND r2.user_id = $user_id) as im_going
    FROM events e JOIN users u ON e.creator_id = u.id
    WHERE e.event_date >= NOW() ORDER BY e.event_date ASC");
if ($up_res) {
    while ($row = mysqli_fetch_assoc($up_res)) {
        $upcoming_events[] = $row;
    }
}

$past_events = [];
$past_res = mysqli_query($conn, "SELECT e.id, e.title, e.description, e.event_date, e.poster_image, u.username,
    (SELECT COUNT(*) FROM event_rsvps r2 WHERE r2.event_id = e.id AND r2.user_id = $user_id) as im_going
    FROM events e JOIN users u ON e.creator_id = u.id
    WHERE e.event_date < NOW() ORDER BY e.event_date DESC LIMIT 12");
if ($past_res) {
    while ($row = mysqli_fetch_assoc($past_res)) {
        $past_events[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">

<head>
    <meta charset="UTF-8">
    <title>myArt+ | <?php echo $t['events']; ?></title>
    <link rel="stylesheet" href="style.css?v=27">
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
        }

        .main-wrapper {
            background: transparent;
        }

        .events-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding: 15px;
        }

        .events-list.upcoming-scroll {
            max-height: 576px;
            box-sizing: border-box;
            overflow-y: auto;
        }

        .event-row {
            border: 1px dotted var(--link-color);
            background: var(--box-bg);
            box-shadow: 2px 2px 0 var(--shadow-color);
            display: flex;
            gap: 14px;
            padding: 14px;
            align-items: center;
            text-decoration: none;
        }

        .event-row-date {
            background: #76e2da;
            color: #0b3d3a;
            text-align: center;
            padding: 8px 10px;
            flex-shrink: 0;
            min-width: 46px;
        }

        .event-row-day {
            font-family: 'Baloo 2', cursive;
            font-weight: 800;
            font-size: 24px;
            line-height: 1;
        }

        .event-row-month {
            font-size: 10px;
            letter-spacing: 1px;
        }

        .event-row-body {
            flex: 1;
            min-width: 0;
        }

        .event-row-title {
            font-weight: bold;
            font-size: 14px;
            color: var(--text-color);
        }

        .event-row-desc {
            font-size: 11px;
            color: var(--footer-text);
            margin: 4px 0;
        }

        .event-row-meta {
            font-size: 10px;
            color: var(--footer-text);
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

        .events-section-title {
            padding: 10px 15px 0 15px;
            font-size: 12px;
            font-weight: bold;
            color: var(--footer-text);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .event-row.past {
            opacity: 0.7;
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
                <?php $current_page = 'events'; include 'navbar.php'; ?>
            </td>
        </tr>

        <tr>
            <td valign="top" style="padding: 10px 8px;">
                <div class="box">
                    <div class="header-blue" style="display:flex; justify-content:space-between; align-items:center;">
                        <span><?php echo $t['upcoming_events']; ?></span>
                        <?php if ($is_logged_in): ?>
                            <a href="add_event.php" style="color:#7a0044; font-size:18px; font-weight:bold; text-decoration:none; line-height:1;" title="<?php echo htmlspecialchars($t['add_event']); ?>" aria-label="<?php echo htmlspecialchars($t['add_event']); ?>">+</a>
                        <?php endif; ?>
                    </div>

                    <?php if (count($upcoming_events) > 0): ?>
                        <div class="events-list upcoming-scroll">
                            <?php foreach ($upcoming_events as $ev):
                                $ev_ts = strtotime($ev['event_date']);
                                $going = (int)$ev['im_going'] > 0;
                            ?>
                                <div class="event-row">
                                    <a href="event.php?id=<?php echo $ev['id']; ?>" style="display:flex; gap:14px; align-items:center; flex:1; min-width:0; text-decoration:none;">
                                        <div class="event-row-date">
                                            <div class="event-row-day"><?php echo date('d', $ev_ts); ?></div>
                                            <div class="event-row-month"><?php echo strtoupper(date('M', $ev_ts)); ?></div>
                                        </div>
                                        <div class="event-row-body">
                                            <div class="event-row-title"><?php echo htmlspecialchars($ev['title']); ?></div>
                                            <?php if (!empty($ev['description'])): ?>
                                                <div class="event-row-desc"><?php echo htmlspecialchars(mb_strimwidth($ev['description'], 0, 120, '...')); ?></div>
                                            <?php endif; ?>
                                            <div class="event-row-meta"><?php echo $t['event_created_by']; ?> <?php echo htmlspecialchars($ev['username']); ?></div>
                                        </div>
                                    </a>
                                    <?php if ($is_logged_in): ?>
                                        <a href="?rsvp=<?php echo $ev['id']; ?>" class="event-rsvp-btn <?php echo $going ? 'going' : ''; ?>">
                                            <?php echo $going ? $t['rsvp_going'] : $t['rsvp_label']; ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align:center; color:gray; padding: 30px 10px;"><?php echo $t['no_events']; ?></div>
                    <?php endif; ?>

                    <?php if (count($past_events) > 0): ?>
                        <div class="events-section-title"><?php echo $t['past_events']; ?></div>
                        <div class="events-list">
                            <?php foreach ($past_events as $ev):
                                $ev_ts = strtotime($ev['event_date']);
                            ?>
                                <a href="event.php?id=<?php echo $ev['id']; ?>" class="event-row past">
                                    <div class="event-row-date">
                                        <div class="event-row-day"><?php echo date('d', $ev_ts); ?></div>
                                        <div class="event-row-month"><?php echo strtoupper(date('M', $ev_ts)); ?></div>
                                    </div>
                                    <div class="event-row-body">
                                        <div class="event-row-title"><?php echo htmlspecialchars($ev['title']); ?></div>
                                        <div class="event-row-meta"><?php echo $t['event_created_by']; ?> <?php echo htmlspecialchars($ev['username']); ?></div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
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

    <?php if ($is_logged_in): ?>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <?php include 'chat_widget.php'; ?>
    <?php endif; ?>
</body>

</html>
