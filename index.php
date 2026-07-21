<?php
session_start();
require_once "config.php";

// --- DİL SİSTEMİ ---
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}
if (isset($_GET['lang']) && in_array($_GET['lang'], ['tr', 'en'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
$current_lang = $_SESSION['lang'];
require_once "lang/" . $current_lang . ".php";

// --- TEMA SİSTEMİ ---
if (!isset($_SESSION['theme'])) {
    $_SESSION['theme'] = 'light';
}
if (isset($_GET['theme']) && in_array($_GET['theme'], ['light', 'dark'])) {
    $_SESSION['theme'] = $_GET['theme'];
}
$current_theme = $_SESSION['theme'];

// --- GİRİŞ KONTROLÜ ---
if (isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit();
}

// --- RASTGELE SANATÇILAR ---
$random_artists = [];
$ra_result = mysqli_query($conn, "SELECT id, username, profile_pic, comm_status FROM users WHERE account_status = 'active' ORDER BY RAND() LIMIT 12");
if ($ra_result) {
    while ($ra_row = mysqli_fetch_assoc($ra_result)) {
        $random_artists[] = $ra_row;
    }
}

// --- HATIRLANAN E-POSTA (Remember Me) ---
$remembered_email = isset($_COOKIE['remember_email']) ? htmlspecialchars($_COOKIE['remember_email']) : '';

// --- SON FORUM KONULARI (Misafirler de görebilir) ---
$recent_threads = [];
$rt_result = mysqli_query($conn, "SELECT t.id, t.title, t.last_activity_at, u.username,
    (SELECT COUNT(*) FROM forum_posts p WHERE p.thread_id = t.id) as post_count
    FROM forum_threads t JOIN users u ON t.user_id = u.id
    ORDER BY t.last_activity_at DESC LIMIT 5");
if ($rt_result) {
    while ($rt_row = mysqli_fetch_assoc($rt_result)) {
        $recent_threads[] = $rt_row;
    }
}

// --- YAKLAŞAN ETKİNLİKLER (Misafirler de görebilir) ---
$upcoming_events = [];
$ev_result = mysqli_query($conn, "SELECT e.id, e.title, e.event_date, e.poster_image, u.username
    FROM events e JOIN users u ON e.creator_id = u.id
    WHERE e.event_date >= NOW() ORDER BY e.event_date ASC LIMIT 5");
if ($ev_result) {
    while ($ev_row = mysqli_fetch_assoc($ev_result)) {
        $upcoming_events[] = $ev_row;
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">

<head>
    <meta charset="UTF-8">
    <title>My Cool Space - <?php echo $t['home']; ?></title>
    <link rel="stylesheet" href="style.css?v=27">
</head>

<body class="<?php echo $current_theme; ?>">
    <div class="marquee-wrap">
        <div class="marquee-text">★ WELCOME TO MYART+ ★ SHARE YOUR ART WITH THE WORLD ★ JOIN THE FORUM ★ NEW EVENTS POSTED WEEKLY ★</div>
    </div>

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
                            <?php if ($current_lang == 'en'): ?>
                                <a href="?lang=tr">TR</a> | <span class="active">EN</span>
                            <?php else: ?>
                                <span class="active">TR</span> | <a href="?lang=en">EN</a>
                            <?php endif; ?>
                        </td>

                    </tr>
                </table>
            </td>
        </tr>

        <tr height="30">
            <td>
                <?php $current_page = 'home';
                include 'navbar.php'; ?>
            </td>
        </tr>

        <tr>
            <td valign="top">
                <table width="100%" border="0" cellpadding="4" cellspacing="0">
                    <tr>

                        <td width="70%" valign="top">

                            <div class="box hero-box">
                                <div class="hero-inner">
                                    <div>
                                        <p class="hero-title">Get More From Your Art!</p>
                                        <p class="hero-text">Upload sketches, join events, and talk shop in the forum — myArt+ is your corner of the web for making stuff.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="box box-artists">
                                <div class="header-blue"><?php echo $t['random_artists']; ?></div>
                                <div style="padding: 6px;">

                                    <table width="100%" border="0" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td width="5%" align="center" valign="middle">
                                                <input type="button" value="&lt;" class="nav-btn" onclick="location.reload();">
                                            </td>
                                            <td width="90%">
                                                <table width="100%" border="0" cellpadding="4" cellspacing="0" style="font-size: 11px; text-align: center;">
                                                    <?php if (count($random_artists) > 0): ?>
                                                        <?php foreach (array_chunk($random_artists, 4) as $artist_row): ?>
                                                            <tr>
                                                                <?php foreach ($artist_row as $artist): ?>
                                                                    <td width="25%">
                                                                        <div class="artist-thumb" style="background-image:url('images/<?php echo htmlspecialchars($artist['profile_pic'] ?: 'default_avatar.gif'); ?>'); background-size:cover; background-position:center;"></div>
                                                                        <a href="profile.php?id=<?php echo $artist['id']; ?>"><?php echo htmlspecialchars($artist['username']); ?></a>
                                                                    </td>
                                                                <?php endforeach; ?>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="4" style="padding: 15px; color: gray;">No artists have joined yet. Be the first!</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </table>
                                            </td>
                                            <td width="5%" align="center" valign="middle">
                                                <input type="button" value="&gt;" class="nav-btn" onclick="location.reload();">
                                            </td>
                                        </tr>
                                    </table>

                                </div>
                            </div>

                            <div class="box box-events">
                                <div class="header-blue"><?php echo $t['events']; ?></div>
                                <div class="events-text" style="<?php echo count($upcoming_events) > 0 ? 'padding: 8px 10px;' : 'display:flex; align-items:center; justify-content:center; color:gray;'; ?>">
                                    <?php if (count($upcoming_events) > 0): ?>
                                        <?php foreach ($upcoming_events as $ev): ?>
                                            <div style="display:flex; justify-content:space-between; align-items:center; padding:4px 0; border-bottom:1px dashed var(--border-color); font-size:11px;">
                                                <a href="event.php?id=<?php echo $ev['id']; ?>" style="color:var(--link-color); font-weight:bold; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:60%;"><?php echo htmlspecialchars($ev['title']); ?></a>
                                                <span style="color:gray;"><?php echo date('d.m.Y H:i', strtotime($ev['event_date'])); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <?php echo $t['no_events']; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </td>

                        <td width="42%" valign="top">

                            <div class="box box-login">
                                <div class="header-blue"><?php echo $t['member_login']; ?></div>
                                <div class="login-area">
                                    <?php if (isset($_GET['error'])): ?>
                                        <div style="color: #cc0000; font-size: 10px; font-weight: bold; margin-bottom: 5px;">
                                            <?php echo $_GET['error'] === 'suspended' ? $t['login_error_suspended'] : $t['login_error_invalid']; ?>
                                        </div>
                                    <?php endif; ?>
                                    <form action="login.php" method="POST">
                                        <table width="100%" border="0" cellpadding="2" style="font-size: 11px;">
                                            <tr>
                                                <td align="right" width="35%"><?php echo $t['email']; ?></td>
                                                <td><input type="text" name="email" class="login-input" value="<?php echo $remembered_email; ?>"></td>
                                            </tr>
                                            <tr>
                                                <td align="right"><?php echo $t['password']; ?></td>
                                                <td><input type="password" name="password" class="login-input"></td>
                                            </tr>
                                            <tr>
                                                <td></td>
                                                <td>
                                                    <input type="checkbox" name="remember" id="rem" <?php echo $remembered_email ? 'checked' : ''; ?>>
                                                    <label for="rem"><?php echo $t['remember']; ?></label>
                                                    &nbsp;|&nbsp;<a href="forgot_password.php" style="font-size:11px;"><?php echo $t['forgot_password_link']; ?></a>
                                                </td>
                                            </tr>
                                            <tr height="30">
                                                <td></td>
                                                <td>
                                                    <input type="submit" value="<?php echo $t['login_btn']; ?>" class="btn-login">
                                                    <input type="button" value="<?php echo $t['signup_btn']; ?>" class="btn-signup" onclick="window.location.href='signup.php';">
                                                </td>
                                            </tr>
                                        </table>
                                    </form>
                                </div>
                            </div>

                            <div class="box box-news">
                                <div class="header-blue"><?php echo $t['site_news_title']; ?></div>
                                <div class="site-news-area">
                                    <div class="site-news-item">&raquo; <?php echo $t['site_news_item1']; ?></div>
                                    <div class="site-news-item">&raquo; <?php echo $t['site_news_item2']; ?></div>
                                    <div class="site-news-item">&raquo; <?php echo $t['site_news_item3']; ?></div>
                                </div>
                            </div>

                            <div class="box box-ads">
                                <div class="header-blue"><?php echo $t['sponsored_ads']; ?></div>
                                <div class="ad-area">
                                    <div class="ad-box">
                                        <strong>[<?php echo $t['ad_label']; ?> #1]</strong><br>
                                        <?php echo $t['ad_1']; ?>
                                    </div>
                                    <div class="ad-box">
                                        <strong>[<?php echo $t['ad_label']; ?> #2]</strong><br>
                                        <?php echo $t['ad_2']; ?>
                                    </div>
                                    <div class="ad-box">
                                        <strong>[<?php echo $t['ad_label']; ?> #3]</strong><br>
                                        <?php echo $t['ad_3']; ?>
                                    </div>
                                </div>
                            </div>

                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr>
            <td valign="top" style="padding: 4px 4px;">
                <div class="box">
                    <div class="header-blue" style="display:flex; justify-content:space-between; align-items:center;">
                        <span><?php echo $t['latest_forum_posts']; ?></span>
                        <a href="forum.php" style="color:#7a0044; font-size:10px; text-decoration:underline;"><?php echo $t['view_forum']; ?></a>
                    </div>
                    <div style="padding: 8px 10px;">
                        <?php if (count($recent_threads) > 0): ?>
                            <?php foreach ($recent_threads as $rt): ?>
                                <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px dashed var(--border-color); font-size:11px;">
                                    <a href="forum.php?thread=<?php echo $rt['id']; ?>" style="color:var(--link-color); font-weight:bold; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:70%;"><?php echo htmlspecialchars($rt['title']); ?></a>
                                    <span style="color:gray;"><?php echo $t['by_artist']; ?> <?php echo htmlspecialchars($rt['username']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align:center; color:gray; padding:10px 0; font-size:11px;"><?php echo $t['no_threads_yet']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </td>
        </tr>

        <tr>
            <td valign="bottom" style="padding-top: 10px;">
                <div class="footer">
                    <a href="qa.php"><?php echo $t['qa']; ?></a> |
                    <a href="privacy.php"><?php echo $t['privacy']; ?></a> |
                    <a href="help.php"><?php echo $t['help']; ?></a> |
                    <a href="terms.php"><?php echo $t['terms']; ?></a>
                    <div class="footer-copy">© <?php echo date("Y"); ?> myArt+ | <?php echo $t['all_rights_reserved']; ?></div>
                </div>
            </td>
        </tr>
    </table>

</body>

</html>