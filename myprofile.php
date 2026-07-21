<?php
session_start();
require_once "config.php";
require_once "categories_config.php";

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

// artworks tablosunda özgünlük kolonları yoksa ekle (eski kurulumlarla uyumluluk için)
$orig_col_check = mysqli_query($conn, "SHOW COLUMNS FROM artworks LIKE 'is_original'");
if ($orig_col_check && mysqli_num_rows($orig_col_check) === 0) {
    mysqli_query($conn, "ALTER TABLE artworks
        ADD COLUMN is_original TINYINT(1) NOT NULL DEFAULT 1,
        ADD COLUMN credit_artist VARCHAR(150) DEFAULT NULL,
        ADD COLUMN credit_platform VARCHAR(150) DEFAULT NULL");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $n = trim($_POST['username']);
        $b = trim($_POST['bio']);

        // Mevcut resmi varsayılan olarak ayarla
        $p = isset($_SESSION['profile_pic']) ? $_SESSION['profile_pic'] : 'default_avatar.gif';

        // Yeni dosya yüklendiyse işle
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['profile_pic']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($ext, $allowed)) {
                $target_dir = "images/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
                $new_filename = "pfp_" . time() . "_" . basename($filename);
                $target_file = $target_dir . $new_filename;

                if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
                    $p = $new_filename; // Veritabanına sadece dosya adını kaydet
                }
            }
        }

        $stmt = mysqli_prepare($conn, "UPDATE users SET username = ?, bio = ?, profile_pic = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "sssi", $n, $b, $p, $user_id);
        mysqli_stmt_execute($stmt);

        $_SESSION['profile_pic'] = $p;
        $_SESSION['username'] = $n;
    } elseif (isset($_POST['update_socials'])) {
        $tw = trim($_POST['twitter']);
        $ig = trim($_POST['instagram']);
        $stmt = mysqli_prepare($conn, "UPDATE users SET twitter = ?, instagram = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ssi", $tw, $ig, $user_id);
        mysqli_stmt_execute($stmt);
    } elseif (isset($_POST['update_comm'])) {
        $sheet_stmt = mysqli_prepare($conn, "SELECT commission_sheet_image FROM users WHERE id = ?");
        mysqli_stmt_bind_param($sheet_stmt, "i", $user_id);
        mysqli_stmt_execute($sheet_stmt);
        $sheet_row = mysqli_fetch_assoc(mysqli_stmt_get_result($sheet_stmt));
        $sheet_img = $sheet_row['commission_sheet_image'] ?? null;

        if (isset($_FILES['commission_sheet']) && $_FILES['commission_sheet']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['commission_sheet']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $target_dir = "uploads/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
                $target_file = $target_dir . "comm_" . time() . "_" . basename($filename);
                if (move_uploaded_file($_FILES['commission_sheet']['tmp_name'], $target_file)) {
                    $sheet_img = $target_file;
                }
            }
        }

        $stmt = mysqli_prepare($conn, "UPDATE users SET comm_status = ?, comm_title1 = ?, comm_sketch = ?, comm_title2 = ?, comm_lineart = ?, comm_title3 = ?, comm_full = ?, commission_sheet_image = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ssssssssi", $_POST['comm_status'], $_POST['comm_title1'], $_POST['comm_sketch'], $_POST['comm_title2'], $_POST['comm_lineart'], $_POST['comm_title3'], $_POST['comm_full'], $sheet_img, $user_id);
        mysqli_stmt_execute($stmt);
    } elseif (isset($_POST['add_comment'])) {
        $comment_msg = trim($_POST['comment_message']);
        if ($comment_msg !== '') {
            $stmt = mysqli_prepare($conn, "INSERT INTO comments (profile_user_id, commenter_id, message) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "iis", $user_id, $user_id, $comment_msg);
            mysqli_stmt_execute($stmt);
        }
    } elseif (isset($_FILES['art_file']) && $_FILES['art_file']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['art_file']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $count_q = mysqli_query($conn, "SELECT COUNT(id) as total FROM artworks WHERE user_id = '$user_id'");
        $c_row = mysqli_fetch_assoc($count_q);

        $is_original = (isset($_POST['is_original']) && $_POST['is_original'] === '0') ? 0 : 1;
        $credit_artist = trim($_POST['credit_artist'] ?? '');
        $credit_platform = trim($_POST['credit_platform'] ?? '');
        $credit_ok = $is_original || ($credit_artist !== '' && $credit_platform !== '');

        if ($c_row['total'] < 20 && in_array($ext, $allowed) && $credit_ok) {
            $target_dir = "uploads/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $target_file = $target_dir . time() . "_" . basename($filename);
            if (move_uploaded_file($_FILES["art_file"]["tmp_name"], $target_file)) {
                $art_title = trim($_POST['art_title'] ?? '');
                $art_category = in_array($_POST['art_category'] ?? '', $art_categories) ? $_POST['art_category'] : 'Other';
                if ($is_original) {
                    $credit_artist = null;
                    $credit_platform = null;
                }
                $stmt = mysqli_prepare($conn, "INSERT INTO artworks (user_id, image_path, title, category, is_original, credit_artist, credit_platform) VALUES (?, ?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "isssiss", $user_id, $target_file, $art_title, $art_category, $is_original, $credit_artist, $credit_platform);
                mysqli_stmt_execute($stmt);
            }
        }
    }
    header("Location: myprofile.php");
    exit();
}

if (isset($_GET['delete_art'])) {
    $art_id = (int)$_GET['delete_art'];
    $stmt = mysqli_prepare($conn, "SELECT image_path FROM artworks WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $art_id, $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($r = mysqli_fetch_assoc($res)) {
        if (file_exists($r['image_path'])) unlink($r['image_path']);
        $del_stmt = mysqli_prepare($conn, "DELETE FROM artworks WHERE id = ?");
        mysqli_stmt_bind_param($del_stmt, "i", $art_id);
        mysqli_stmt_execute($del_stmt);
    }
    header("Location: myprofile.php");
    exit();
}

if (isset($_GET['delete_comment'])) {
    $comment_id = (int)$_GET['delete_comment'];
    // Kendi profilinde: yorumu yazan ya da profil sahibi (sen) silebilir
    $del_stmt = mysqli_prepare($conn, "DELETE FROM comments WHERE id = ? AND (commenter_id = ? OR profile_user_id = ?)");
    mysqli_stmt_bind_param($del_stmt, "iii", $comment_id, $user_id, $user_id);
    mysqli_stmt_execute($del_stmt);
    header("Location: myprofile.php");
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$p_username = htmlspecialchars($row['username']);
$p_gender = $row['gender'];
$p_bio = htmlspecialchars($row['bio']);
if (empty($p_bio)) $p_bio = isset($t['no_bio']) ? $t['no_bio'] : "Welcome to my profile! I haven't written a bio yet...";
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

$artworks = [];
$art_q = mysqli_query($conn, "SELECT * FROM artworks WHERE user_id = '$user_id' ORDER BY created_at DESC LIMIT 20");
while ($a = mysqli_fetch_assoc($art_q)) {
    $artworks[] = $a;
}

$profile_comments = [];
$c_stmt = mysqli_prepare($conn, "SELECT c.*, u.username, u.profile_pic FROM comments c JOIN users u ON c.commenter_id = u.id WHERE c.profile_user_id = ? ORDER BY c.created_at DESC");
mysqli_stmt_bind_param($c_stmt, "i", $user_id);
mysqli_stmt_execute($c_stmt);
$c_result = mysqli_stmt_get_result($c_stmt);
while ($c = mysqli_fetch_assoc($c_result)) {
    $profile_comments[] = $c;
}
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">

<head>
    <meta charset="UTF-8">
    <title>My Cool Space - My Profile</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css?v=27">
    <style>
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
                <?php $current_page = 'my_profile'; include 'navbar.php'; ?>
            </td>
        </tr>
        <tr>
            <td valign="top" style="padding: 10px 8px;">
                <table width="100%" border="0" cellpadding="6" cellspacing="0">
                    <tr>
                        <td width="35%" valign="top">
                            <div class="profile-name"><?php echo $p_username; ?></div>
                            <img src="images/<?php echo $p_pic; ?>" alt="<?php echo $p_username; ?>" class="profile-pic">
                            <div style="font-size: 12px; margin-bottom: 15px;">
                                <?php if ($p_gender != 'unspecified') echo ucfirst($p_gender) . "<br>"; ?>
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
                            <div class="box">
                                <div class="header-blue" style="background-color: #333; color: #fff;"><?php echo $t['manage_profile']; ?></div>
                                <div style="padding: 10px; font-size: 12px; line-height: 2;">
                                    <a href="#" data-toggle="modal" data-target="#editProfileModal" style="color: var(--link-color); text-decoration: none; font-weight: bold;"><?php echo $t['edit_profile']; ?></a><br>
                                    <a href="#" data-toggle="modal" data-target="#editArtModal" style="color: var(--link-color); text-decoration: none; font-weight: bold;"><?php echo $t['manage_artworks']; ?></a><br>
                                    <a href="#" data-toggle="modal" data-target="#editSocialsModal" style="color: var(--link-color); text-decoration: none; font-weight: bold;"><?php echo $t['edit_contact']; ?></a><br>
                                    <a href="#" data-toggle="modal" data-target="#editCommModal" style="color: var(--link-color); text-decoration: none; font-weight: bold;"><?php echo $t['edit_commission']; ?></a>
                                </div>
                            </div>
                            <div class="box" style="margin-top: 15px;">
                                <div class="header-blue"><?php echo $p_username; ?>'s <?php echo $t['blurbs']; ?></div>
                                <div style="padding: 10px; font-size: 12px; line-height: 1.5;">
                                    <strong style="color: var(--link-color);"><?php echo $t['about_me']; ?></strong><br>
                                    <?php echo nl2br($p_bio); ?>
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
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td width="65%" valign="top">
                            <div class="box">
                                <div class="header-blue"><?php echo $p_username; ?>'s <?php echo $t['latest_artworks']; ?></div>
                                <div style="padding: 10px; background-color: var(--box-bg);">
                                    <div id="artworkCarousel" class="carousel slide" data-ride="carousel" style="border: 1px solid var(--border-color); background-color: #000;">
                                        <?php if (count($artworks) > 0): ?>
                                            <ol class="carousel-indicators">
                                                <?php foreach ($artworks as $index => $art): ?>
                                                    <li data-target="#artworkCarousel" data-slide-to="<?php echo $index; ?>" class="<?php echo $index == 0 ? 'active' : ''; ?>"></li>
                                                <?php endforeach; ?>
                                            </ol>
                                            <div class="carousel-inner" style="min-height: 400px;">
                                                <?php foreach ($artworks as $index => $art): ?>
                                                    <div class="carousel-item <?php echo $index == 0 ? 'active' : ''; ?>" style="height: 400px;">
                                                        <img class="d-block mx-auto" src="<?php echo $art['image_path']; ?>" style="cursor: zoom-in;" onclick="enlargeImage(this.src)">
                                                        <?php if (empty($art['is_original'] ?? 1)): ?>
                                                            <div class="art-credit-overlay"><?php echo $t['credit_prefix']; ?> <strong><?php echo htmlspecialchars($art['credit_artist']); ?></strong> <?php echo $t['credit_on']; ?> <strong><?php echo htmlspecialchars($art['credit_platform']); ?></strong></div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <a class="carousel-control-prev" href="#artworkCarousel" role="button" data-slide="prev"><span class="carousel-control-prev-icon" aria-hidden="true"></span></a>
                                            <a class="carousel-control-next" href="#artworkCarousel" role="button" data-slide="next"><span class="carousel-control-next-icon" aria-hidden="true"></span></a>
                                        <?php else: ?>
                                            <div style="height: 400px; display:flex; align-items:center; justify-content:center; color:gray;"><?php echo $t['no_artworks']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="text-align: right; padding-top: 8px;">
                                        <a href="#" data-toggle="modal" data-target="#editArtModal" style="color: var(--link-color); font-weight: bold; font-size: 11px;">[+] <?php echo $t['manage_artworks']; ?></a>
                                    </div>
                                </div>
                            </div>
                            <div class="box" style="margin-top: 15px;">
                                <div class="header-blue"><?php echo $t['profile_comments']; ?></div>
                                <div style="padding: 10px; font-size: 11px; position: relative;">
                                    <form method="POST" class="comment-input-wrapper">
                                        <div class="comment-input-area">
                                            <img src="images/<?php echo $p_pic; ?>" alt="You">
                                            <input type="text" name="comment_message" placeholder="<?php echo $t['add_comment']; ?>" maxlength="500" required>
                                            <button type="submit" name="add_comment" class="form-btn" style="margin-left:6px;"><?php echo $t['send']; ?></button>
                                        </div>
                                    </form>
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
                                                            <a href="?delete_comment=<?php echo $cmt['id']; ?>" class="comment-delete-btn">[<?php echo $t['delete']; ?>]</a>
                                                        </td>
                                                    </tr>
                                                </table>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div style="text-align:center; color:gray; padding:15px 0;"><?php echo $t['no_comments_yet']; ?></div>
                                        <?php endif; ?>
                                    </div>
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
                    <a href="qa.php"><?php echo $t['qa']; ?></a> | <a href="privacy.php"><?php echo $t['privacy']; ?></a> | <a href="help.php"><?php echo $t['help']; ?></a> | <a href="terms.php"><?php echo $t['terms']; ?></a>
                    <div class="footer-copy" style="margin-top:5px; color:gray;">© <?php echo date("Y"); ?> myArt+ | <?php echo $t['all_rights_reserved']; ?></div>
                </div>
            </td>
        </tr>
    </table>

    <!-- MODALS -->
    <div class="modal fade" id="editProfileModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered text-dark">
            <div class="modal-content" style="border: 2px solid var(--border-color); border-radius: 0; background: var(--box-bg); color: var(--text-color);">
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-header" style="background: var(--ad-border); color: #fff; border-radius:0;">
                        <h5 class="modal-title" style="font-size: 16px; font-weight:bold;"><?php echo $t['edit_profile']; ?></h5>
                        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body" style="font-size:12px;">
                        <label><strong><?php echo $t['nickname']; ?></strong></label>
                        <input type="text" name="username" value="<?php echo $p_username; ?>" class="form-control form-control-sm mb-3" style="background: var(--bg-color); color: var(--text-color); border: 1px solid var(--border-color);">

                        <label><strong><?php echo $t['profile_pic_label']; ?></strong></label>
                        <input type="file" name="profile_pic" accept=".jpg, .jpeg, .png, .gif" class="form-control-file mb-3" style="background: var(--bg-color); color: var(--text-color);">

                        <label><strong><?php echo $t['bio_label']; ?></strong></label>
                        <textarea name="bio" class="form-control form-control-sm" rows="5" style="background: var(--bg-color); color: var(--text-color); border: 1px solid var(--border-color);"><?php echo str_replace("<br />", "", $p_bio); ?></textarea>
                    </div>
                    <div class="modal-footer" style="border-top: 1px dashed var(--border-color);">
                        <button type="submit" name="update_profile" class="btn btn-primary btn-sm" style="background: var(--ad-border); border:none;"><?php echo $t['save_changes']; ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editArtModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered text-dark">
            <div class="modal-content" style="border: 2px solid var(--border-color); border-radius: 0; background: var(--box-bg); color: var(--text-color);">
                <div class="modal-header" style="background: var(--ad-border); color: #fff; border-radius:0;">
                    <h5 class="modal-title" style="font-size: 16px; font-weight:bold;"><?php echo $t['manage_artworks']; ?></h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body" style="font-size:12px;">
                    <form method="POST" enctype="multipart/form-data">
                        <label><strong><?php echo $t['art_title_label']; ?></strong></label>
                        <input type="text" name="art_title" maxlength="150" class="form-control form-control-sm mb-2" style="background: var(--bg-color); color: var(--text-color); border: 1px solid var(--border-color);">
                        <label><strong><?php echo $t['art_category_label']; ?></strong></label>
                        <select name="art_category" class="form-control form-control-sm mb-2" style="background: var(--bg-color); color: var(--text-color); border: 1px solid var(--border-color);">
                            <?php foreach ($art_category_groups as $group_label_key => $group_cats): ?>
                                <optgroup label="<?php echo htmlspecialchars($t[$group_label_key]); ?>">
                                    <?php foreach ($group_cats as $cat_name => $lang_key): ?>
                                        <option value="<?php echo htmlspecialchars($cat_name); ?>" <?php echo $cat_name === 'Other' ? 'selected' : ''; ?>><?php echo htmlspecialchars($t[$lang_key]); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>

                        <label><strong><?php echo $t['is_original_label']; ?></strong></label>
                        <div class="mb-2">
                            <label style="font-weight:normal; margin-right:14px;">
                                <input type="radio" name="is_original" value="1" checked onchange="toggleCreditFields()"> <?php echo $t['original_yes']; ?>
                            </label>
                            <label style="font-weight:normal;">
                                <input type="radio" name="is_original" value="0" onchange="toggleCreditFields()"> <?php echo $t['original_no']; ?>
                            </label>
                        </div>
                        <div id="creditFieldsRow" style="display:none; border-left: 2px solid var(--ad-border); padding-left: 8px; margin-bottom: 8px;">
                            <label><strong><?php echo $t['credit_artist_label']; ?></strong></label>
                            <input type="text" name="credit_artist" id="creditArtistInput" maxlength="150" placeholder="<?php echo htmlspecialchars($t['credit_artist_placeholder']); ?>" class="form-control form-control-sm mb-2" style="background: var(--bg-color); color: var(--text-color); border: 1px solid var(--border-color);">
                            <label><strong><?php echo $t['credit_platform_label']; ?></strong></label>
                            <input type="text" name="credit_platform" id="creditPlatformInput" maxlength="150" placeholder="<?php echo htmlspecialchars($t['credit_platform_placeholder']); ?>" class="form-control form-control-sm mb-2" style="background: var(--bg-color); color: var(--text-color); border: 1px solid var(--border-color);">
                        </div>

                        <label><strong><?php echo $t['upload_new_art']; ?></strong></label>
                        <input type="file" name="art_file" class="form-control-file mb-2">
                        <button type="submit" class="btn btn-success btn-sm mb-3"><?php echo $t['upload']; ?></button>
                    </form>
                    <hr style="border-top: 1px dashed var(--border-color);">
                    <div style="max-height: 200px; overflow-y: auto;">
                        <?php foreach ($artworks as $art): ?>
                            <div style="display:flex; justify-content:space-between; margin-bottom:5px; border-bottom: 1px solid #333; padding-bottom:5px;">
                                <span><img src="<?php echo $art['image_path']; ?>" height="30"> <?php echo htmlspecialchars($art['title'] ?: basename($art['image_path'])); ?> <span style="color:gray; font-size:10px;">(<?php echo htmlspecialchars($t[$art_category_lang_key[$art['category']] ?? ''] ?? $art['category']); ?>)</span> <?php if (empty($art['is_original'] ?? 1)): ?><span style="color:#fff; background:var(--ad-border); font-size:9px; font-weight:bold; padding:1px 5px; border-radius:2px;"><?php echo $t['not_original_badge']; ?></span><?php endif; ?></span>
                                <a href="?delete_art=<?php echo $art['id']; ?>" class="comment-delete-btn">[<?php echo $t['delete']; ?>]</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editSocialsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered text-dark">
            <div class="modal-content" style="border: 2px solid var(--border-color); border-radius: 0; background: var(--box-bg); color: var(--text-color);">
                <form method="POST">
                    <div class="modal-header" style="background: var(--ad-border); color: #fff; border-radius:0;">
                        <h5 class="modal-title" style="font-size: 16px; font-weight:bold;"><?php echo $t['edit_contact']; ?></h5>
                        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body" style="font-size:12px;">
                        <label><strong><?php echo $t['twitter_label']; ?></strong></label>
                        <input type="text" name="twitter" value="<?php echo $p_tw; ?>" class="form-control form-control-sm mb-3" style="background: var(--bg-color); color: var(--text-color); border: 1px solid var(--border-color);">
                        <label><strong><?php echo $t['instagram_label']; ?></strong></label>
                        <input type="text" name="instagram" value="<?php echo $p_ig; ?>" class="form-control form-control-sm mb-3" style="background: var(--bg-color); color: var(--text-color); border: 1px solid var(--border-color);">
                    </div>
                    <div class="modal-footer" style="border-top: 1px dashed var(--border-color);">
                        <button type="submit" name="update_socials" class="btn btn-primary btn-sm" style="background: var(--ad-border); border:none;"><?php echo $t['save_socials']; ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editCommModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered text-dark">
            <div class="modal-content" style="border: 2px solid var(--border-color); border-radius: 0; background: var(--box-bg); color: var(--text-color);">
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-header" style="background: var(--ad-border); color: #fff; border-radius:0;">
                        <h5 class="modal-title" style="font-size: 16px; font-weight:bold;"><?php echo $t['edit_commission']; ?></h5>
                        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body" style="font-size:12px;">
                        <label><strong><?php echo $t['comm_status_label']; ?></strong></label>
                        <select name="comm_status" class="form-control form-control-sm mb-3" style="background: var(--bg-color); color: var(--text-color); border: 1px solid var(--border-color);">
                            <option value="OPEN" <?php if ($p_c_status == 'OPEN') echo 'selected'; ?>>OPEN</option>
                            <option value="CLOSED" <?php if ($p_c_status == 'CLOSED') echo 'selected'; ?>>CLOSED</option>
                        </select>
                        <div class="row">
                            <div class="col-6"><label><strong><?php echo $t['item_name']; ?></strong></label><input type="text" name="comm_title1" value="<?php echo $p_c_t1; ?>" class="form-control form-control-sm mb-2" style="background: var(--bg-color); color: var(--text-color); border: 1px solid var(--border-color);"></div>
                            <div class="col-6"><label><strong><?php echo $t['price']; ?></strong></label><input type="text" name="comm_sketch" value="<?php echo $p_c_s1; ?>" class="form-control form-control-sm mb-2" style="background: var(--bg-color); color: var(--text-color); border: 1px solid var(--border-color);"></div>
                        </div>
                        <div class="row">
                            <div class="col-6"><label><strong><?php echo $t['item_name']; ?></strong></label><input type="text" name="comm_title2" value="<?php echo $p_c_t2; ?>" class="form-control form-control-sm mb-2" style="background: var(--bg-color); color: var(--text-color); border: 1px solid var(--border-color);"></div>
                            <div class="col-6"><label><strong><?php echo $t['price']; ?></strong></label><input type="text" name="comm_lineart" value="<?php echo $p_c_s2; ?>" class="form-control form-control-sm mb-2" style="background: var(--bg-color); color: var(--text-color); border: 1px solid var(--border-color);"></div>
                        </div>
                        <div class="row">
                            <div class="col-6"><label><strong><?php echo $t['item_name']; ?></strong></label><input type="text" name="comm_title3" value="<?php echo $p_c_t3; ?>" class="form-control form-control-sm mb-2" style="background: var(--bg-color); color: var(--text-color); border: 1px solid var(--border-color);"></div>
                            <div class="col-6"><label><strong><?php echo $t['price']; ?></strong></label><input type="text" name="comm_full" value="<?php echo $p_c_s3; ?>" class="form-control form-control-sm mb-2" style="background: var(--bg-color); color: var(--text-color); border: 1px solid var(--border-color);"></div>
                        </div>
                        <label><strong><?php echo $t['commission_sheet_label']; ?></strong></label>
                        <input type="file" name="commission_sheet" accept=".jpg, .jpeg, .png, .gif" class="form-control-file mb-2" style="background: var(--bg-color); color: var(--text-color);">
                        <?php if ($p_comm_sheet_img !== ''): ?>
                            <img src="<?php echo $p_comm_sheet_img; ?>" alt="" style="width:80px; height:60px; object-fit:cover; border:1px solid var(--border-color); display:block;">
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer" style="border-top: 1px dashed var(--border-color);">
                        <button type="submit" name="update_comm" class="btn btn-primary btn-sm" style="background: var(--ad-border); border:none;"><?php echo $t['save_commission']; ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="artModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="background: transparent; border: none;">
                <div class="modal-body text-center">
                    <img id="modalEnlargedImage" src="" class="img-fluid" style="border: 4px solid var(--link-color); border-radius: 10px; max-height: 85vh;">
                    <br>
                    <button type="button" class="btn btn-dark mt-3" data-dismiss="modal" style="border: 1px solid var(--link-color); font-weight:bold;">X <?php echo $t['close']; ?></button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
    <script>
        function enlargeImage(src) {
            document.getElementById('modalEnlargedImage').src = src;
            $('#artModal').modal('show');
        }

        function toggleCreditFields() {
            const notOriginal = document.querySelector('input[name="is_original"]:checked').value === '0';
            document.getElementById('creditFieldsRow').style.display = notOriginal ? 'block' : 'none';
            document.getElementById('creditArtistInput').required = notOriginal;
            document.getElementById('creditPlatformInput').required = notOriginal;
        }
    </script>

    <?php include 'chat_widget.php'; ?>
</body>

</html>