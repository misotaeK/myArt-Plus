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

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
$user_id = $_SESSION['user_id'];

// --- SON ETKİNLİKLER (Gerçek bildirimler) ---
$notifications = [];
$notif_res = mysqli_query($conn, "SELECT n.*, u.username AS related_username FROM notifications n LEFT JOIN users u ON n.related_user_id = u.id WHERE n.user_id = '$user_id' ORDER BY n.created_at DESC LIMIT 8");
if ($notif_res) {
    while ($n = mysqli_fetch_assoc($notif_res)) {
        $notifications[] = $n;
    }
}

// Bildirim metnindeki aktör kullanıcı adını profiline bağlantı veren güvenli HTML'e çevirir
function render_notification_message($message, $related_user_id, $related_username) {
    if ($related_user_id && $related_username && strpos($message, $related_username) !== false) {
        $parts = explode($related_username, $message, 2);
        return htmlspecialchars($parts[0])
            . '<a href="profile.php?id=' . (int)$related_user_id . '" style="color:var(--link-color); font-weight:bold;">' . htmlspecialchars($related_username) . '</a>'
            . htmlspecialchars($parts[1] ?? '');
    }
    return htmlspecialchars($message);
}
// Görüntülendikten sonra okunmuş olarak işaretle
mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = '$user_id' AND is_read = 0");

// --- YAKLAŞAN ETKİNLİKLER ---
$upcoming_events = [];
$ev_result = mysqli_query($conn, "SELECT e.id, e.title, e.event_date, e.poster_image, u.username
    FROM events e JOIN users u ON e.creator_id = u.id
    WHERE e.event_date >= NOW() ORDER BY e.event_date ASC LIMIT 6");
if ($ev_result) {
    while ($ev_row = mysqli_fetch_assoc($ev_result)) {
        $upcoming_events[] = $ev_row;
    }
}

// --- EN YENİ ESERLER (Tüm kullanıcılardan) ---
$new_artworks = [];
$art_res = mysqli_query($conn, "SELECT a.*, u.username FROM artworks a JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 5");
if ($art_res) {
    while ($a = mysqli_fetch_assoc($art_res)) {
        $new_artworks[] = $a;
    }
}

// --- SANATÇILAR (Arama varsa isimle, yoksa rastgele) ---
$artist_q = isset($_GET['artist_q']) ? trim($_GET['artist_q']) : '';
$random_artists = [];
if ($artist_q !== '') {
    $ra_res = mysqli_prepare($conn, "SELECT id, username, profile_pic, comm_status FROM users WHERE account_status = 'active' AND id != ? AND username LIKE ? ORDER BY username ASC LIMIT 4");
    $like = "%$artist_q%";
    mysqli_stmt_bind_param($ra_res, "is", $user_id, $like);
} else {
    $ra_res = mysqli_prepare($conn, "SELECT id, username, profile_pic, comm_status FROM users WHERE account_status = 'active' AND id != ? ORDER BY RAND() LIMIT 4");
    mysqli_stmt_bind_param($ra_res, "i", $user_id);
}
mysqli_stmt_execute($ra_res);
$ra_result = mysqli_stmt_get_result($ra_res);
while ($ra = mysqli_fetch_assoc($ra_result)) {
    $random_artists[] = $ra;
}

$events = [];
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">

<head>
    <meta charset="UTF-8">
    <title>My Cool Space - Home</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css?v=27">
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
        }

        .main-wrapper {
            background: transparent;
        }

        .art-grid-5 {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
        }

        .art-grid-item {
            border: 1px solid var(--border-color);
            background: var(--thumb-bg);
            padding: 5px;
            text-align: center;
        }

        .art-grid-item img {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border: 1px solid var(--thumb-border);
        }

        .notification-item {
            font-size: 11px;
            border-bottom: 1px dashed var(--border-color);
            padding: 8px 0;
            display: flex;
            align-items: center;
        }

        .notification-item img {
            width: 25px;
            height: 25px;
            margin-right: 8px;
            border-radius: 2px;
        }

        .artist-carousel {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 10px;
        }

        .artist-card {
            display: inline-block;
            width: 48%;
            margin-bottom: 10px;
            text-align: center;
            font-size: 11px;
        }

        .artist-card img {
            width: 60px;
            height: 60px;
            border: 1px solid var(--border-color);
        }

        .artist-search-bar {
            display: flex;
            gap: 4px;
            align-items: center;
            padding: 8px 10px 0 10px;
        }

        .artist-search-bar input {
            flex-grow: 1;
            font-size: 11px;
            padding: 5px;
            border: 1px solid var(--border-color);
            background: var(--bg-color);
            color: var(--text-color);
        }

        .artist-search-bar button {
            font-size: 11px;
            padding: 5px 8px;
            border: 1px solid var(--border-color);
            background: var(--ad-border);
            color: #fff;
            cursor: pointer;
        }

        .artist-search-clear {
            font-size: 12px;
            color: var(--footer-text);
            text-decoration: none;
            padding: 0 3px;
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
                <?php $current_page = 'home'; include 'navbar.php'; ?>
            </td>
        </tr>

        <tr>
            <td valign="top" style="padding: 10px 8px;">
                <table width="100%" border="0" cellpadding="8" cellspacing="0">
                    <tr>
                        <td width="28%" valign="top">

                            <div class="box mb-3">
                                <div class="header-blue"><?php echo $t['recent_activity']; ?></div>
                                <div style="padding: 10px; min-height: 200px; max-height: 350px; box-sizing: border-box; overflow-y: auto;">
                                    <?php if (count($notifications) > 0): ?>
                                        <?php foreach ($notifications as $n): ?>
                                            <div class="notification-item <?php echo $n['is_read'] ? '' : 'unread'; ?>">
                                                <div>
                                                    <?php echo render_notification_message($n['message'], $n['related_user_id'], $n['related_username']); ?>
                                                    <span class="notification-time"><?php echo date("d.m.Y H:i", strtotime($n['created_at'])); ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div style="font-size: 11px; color: gray; text-align: center; padding: 15px 0;"><?php echo $t['no_activity']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="box">
                                <div class="header-blue"><?php echo $t['sponsored']; ?></div>
                                <div style="padding: 10px; text-align:center;">
                                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200'%3E%3Crect width='200' height='200' fill='%23fff0f8'/%3E%3Ctext x='50%25' y='50%25' font-family='Arial' font-size='16' fill='%23ec008c' text-anchor='middle' dominant-baseline='middle'%3EADVERTISEMENT%3C/text%3E%3C/svg%3E" style="max-width: 100%; border: 1px solid var(--border-color);">
                                    <div style="font-size:10px; color:gray; margin-top:5px;"><?php echo $t['support_sponsors']; ?></div>
                                </div>
                            </div>
                        </td>

                        <td width="72%" valign="top">

                            <div class="box hero-box">
                                <div class="hero-inner">
                                    <div>
                                        <p class="hero-title">Get More From Your Art!</p>
                                        <p class="hero-text">Upload sketches, join events, and talk shop in the forum — myArt+ is your corner of the web for making stuff.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="box mb-3">
                                <div class="header-blue" style="display:flex; justify-content:space-between; align-items:center;">
                                    <span><?php echo $t['newest_artworks']; ?></span>
                                    <a href="browse.php" style="color:#7a0044; font-size:10px; text-decoration:underline;"><?php echo $t['view_all']; ?></a>
                                </div>
                                <div style="padding: 15px; background-color: var(--box-bg);">
                                    <?php if (count($new_artworks) > 0): ?>
                                        <div class="art-grid-5">
                                            <?php foreach ($new_artworks as $art): ?>
                                                <div class="art-grid-item">
                                                    <a href="profile.php?id=<?php echo $art['user_id']; ?>">
                                                        <img src="<?php echo htmlspecialchars($art['image_path']); ?>" alt="Artwork">
                                                    </a>
                                                    <div style="font-size: 9px; color: var(--link-color); margin-top: 5px; overflow:hidden; white-space:nowrap;"><?php echo $t['by']; ?> <?php echo htmlspecialchars($art['username']); ?></div>
                                                    <?php if (empty($art['is_original'] ?? 1)): ?>
                                                        <div style="font-size: 8px; color: var(--footer-text); margin-top: 2px; overflow:hidden; white-space:nowrap; text-overflow:ellipsis;" title="<?php echo htmlspecialchars($t['credit_prefix'] . ' ' . $art['credit_artist'] . ' ' . $t['credit_on'] . ' ' . $art['credit_platform']); ?>"><?php echo $t['credit_prefix']; ?> <?php echo htmlspecialchars($art['credit_artist']); ?> <?php echo $t['credit_on']; ?> <?php echo htmlspecialchars($art['credit_platform']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="text-align:center; color:gray; padding: 20px 0;"><?php echo $t['no_artworks']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <table width="100%" border="0" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td width="48%" valign="top">
                                        <div class="box">
                                            <div class="header-blue"><?php echo $t['discover_artists']; ?></div>
                                            <form method="GET" action="home.php" class="artist-search-bar">
                                                <input type="text" name="artist_q" value="<?php echo htmlspecialchars($artist_q); ?>" placeholder="<?php echo htmlspecialchars($t['search_artist_placeholder']); ?>">
                                                <button type="submit"><?php echo $t['search_btn']; ?></button>
                                                <?php if ($artist_q !== ''): ?>
                                                    <a href="home.php" class="artist-search-clear"><?php echo $t['clear_filters']; ?></a>
                                                <?php endif; ?>
                                            </form>
                                            <div class="artist-carousel">
                                                <input type="button" value="&lt;" class="nav-btn" onclick="shiftArtists(-1)">
                                                <div id="discoverArtistsGrid" style="flex-grow:1;">
                                                    <?php if (count($random_artists) > 0): ?>
                                                        <?php foreach ($random_artists as $artist): ?>
                                                            <div class="artist-card">
                                                                <a href="profile.php?id=<?php echo $artist['id']; ?>">
                                                                    <img src="images/<?php echo htmlspecialchars($artist['profile_pic'] ?: 'default_avatar.gif'); ?>"><br>
                                                                    <strong><?php echo htmlspecialchars($artist['username']); ?></strong>
                                                                </a>
                                                                <div style="color: <?php echo ($artist['comm_status'] == 'OPEN') ? 'green' : 'red'; ?>;"><?php echo htmlspecialchars($artist['comm_status'] ?: 'OPEN'); ?></div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <div style="text-align:center; color:gray; padding: 10px 0; font-size: 11px;"><?php echo $artist_q !== '' ? $t['no_artists_found'] : $t['no_other_artists']; ?></div>
                                                    <?php endif; ?>
                                                </div>
                                                <input type="button" value="&gt;" class="nav-btn" onclick="shiftArtists(1)">
                                            </div>
                                        </div>
                                    </td>

                                    <td width="4%"></td>

                                    <td width="48%" valign="top">
                                        <div class="box">
                                            <div class="header-blue" style="display:flex; justify-content:space-between; align-items:center;">
                                                <span><?php echo $t['upcoming_events']; ?></span>
                                                <a href="add_event.php" style="color:#7a0044; font-size:16px; font-weight:bold; text-decoration:none; line-height:1;" title="<?php echo htmlspecialchars($t['add_event']); ?>" aria-label="<?php echo htmlspecialchars($t['add_event']); ?>">+</a>
                                            </div>
                                            <div style="padding: 10px; font-size: 12px; min-height: 200px; max-height: 220px; box-sizing: border-box; overflow-y: auto;">
                                                <?php if (count($upcoming_events) > 0): ?>
                                                    <?php foreach ($upcoming_events as $ev): ?>
                                                        <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px dashed var(--border-color); font-size:11px;">
                                                            <a href="event.php?id=<?php echo $ev['id']; ?>" style="color:var(--link-color); font-weight:bold; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:60%;"><?php echo htmlspecialchars($ev['title']); ?></a>
                                                            <span style="color:gray;"><?php echo date('d.m.Y H:i', strtotime($ev['event_date'])); ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <div style="text-align:center; color:gray; padding:80px 0; font-size:11px;"><?php echo $t['no_events']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </table>

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

    <!-- DİKKAT: AJAX için tam jQuery sürümü kullanılmalı, slim değil! -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>

    <script>
        let discoverOffset = 0;
        const DISCOVER_Q = <?php echo json_encode($artist_q); ?>;
        const DISCOVER_NO_RESULTS = <?php echo json_encode($artist_q !== '' ? $t['no_artists_found'] : $t['no_other_artists']); ?>;

        function shiftArtists(direction) {
            discoverOffset = Math.max(0, discoverOffset + (direction * 4));
            $.post('discover_api.php', {
                action: 'get_artists',
                q: DISCOVER_Q,
                offset: discoverOffset
            }, function(data) {
                discoverOffset = data.offset;
                let html = '';
                if (data.artists.length > 0) {
                    data.artists.forEach(function(a) {
                        let statusColor = a.comm_status === 'OPEN' ? 'green' : 'red';
                        html += `
                            <div class="artist-card">
                                <a href="profile.php?id=${a.id}">
                                    <img src="images/${escapeHtml(a.profile_pic || 'default_avatar.gif')}"><br>
                                    <strong>${escapeHtml(a.username)}</strong>
                                </a>
                                <div style="color: ${statusColor};">${escapeHtml(a.comm_status || 'OPEN')}</div>
                            </div>
                        `;
                    });
                } else {
                    html = `<div style="text-align:center; color:gray; padding: 10px 0; font-size: 11px;">${DISCOVER_NO_RESULTS}</div>`;
                }
                $('#discoverArtistsGrid').html(html);
            }, 'json');
        }
    </script>

    <?php include 'chat_widget.php'; ?>
</body>

</html>