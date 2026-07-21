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

if (isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit();
}

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$error = '';
$success = false;

function get_valid_reset($conn, $token) {
    if ($token === '') return null;
    $stmt = mysqli_prepare($conn, "SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW()");
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

$reset_row = get_valid_reset($conn, $token);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset_row) {
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if (strlen($password) < 6) {
        $error = $t['password_too_short'];
    } elseif ($password !== $password_confirm) {
        $error = $t['passwords_no_match'];
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $upd = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
        mysqli_stmt_bind_param($upd, "si", $hashed, $reset_row['user_id']);
        mysqli_stmt_execute($upd);

        // Bu tokeni ve kullanıcının diğer bekleyen tüm sıfırlama taleplerini geçersiz kıl
        mysqli_query($conn, "UPDATE password_resets SET used = 1 WHERE user_id = " . (int)$reset_row['user_id']);

        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">

<head>
    <meta charset="UTF-8">
    <title>myArt+ | <?php echo $t['reset_password_title']; ?></title>
    <link rel="stylesheet" href="style.css?v=27">
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
        }

        .main-wrapper {
            background: transparent;
        }

        .auth-box {
            max-width: 360px;
            margin: 30px auto;
            padding: 20px;
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
                            <?php if ($current_theme == 'light'): ?><span class="active"><?php echo $t['light']; ?></span> | <a href="?theme=dark&token=<?php echo urlencode($token); ?>"><?php echo $t['dark']; ?></a><?php else: ?><a href="?theme=light&token=<?php echo urlencode($token); ?>"><?php echo $t['light']; ?></a> | <span class="active"><?php echo $t['dark']; ?></span><?php endif; ?> &nbsp;&nbsp;&nbsp;
                            <?php echo $t['lang_label']; ?>
                            <?php if ($current_lang == 'en'): ?><a href="?lang=tr&token=<?php echo urlencode($token); ?>">TR</a> | <span class="active">EN</span><?php else: ?><span class="active">TR</span> | <a href="?lang=en&token=<?php echo urlencode($token); ?>">EN</a><?php endif; ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr>
            <td valign="top">
                <div class="box auth-box">
                    <div class="header-blue"><?php echo $t['reset_password_title']; ?></div>
                    <div style="padding: 15px; font-size: 12px;">
                        <?php if ($success): ?>
                            <p style="color:green; font-weight:bold;"><?php echo $t['password_reset_success']; ?></p>
                            <p><a href="index.php" style="color:var(--link-color); font-weight:bold;"><?php echo $t['login_here']; ?></a></p>
                        <?php elseif (!$reset_row): ?>
                            <p style="color:#cc0000;"><?php echo $t['invalid_or_expired_token']; ?></p>
                            <p><a href="forgot_password.php" style="color:var(--link-color); font-weight:bold;"><?php echo $t['forgot_password_title']; ?></a></p>
                        <?php else: ?>
                            <?php if ($error !== ''): ?>
                                <div style="color:#cc0000; font-weight:bold; margin-bottom:10px;"><?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>
                            <form method="POST" action="reset_password.php">
                                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                                <label style="font-size:11px; font-weight:bold;"><?php echo $t['new_password_label']; ?></label>
                                <input type="password" name="password" required minlength="6" class="form-input">
                                <label style="font-size:11px; font-weight:bold;"><?php echo $t['confirm_new_password_label']; ?></label>
                                <input type="password" name="password_confirm" required minlength="6" class="form-input">
                                <button type="submit" class="form-btn"><?php echo $t['reset_password_btn']; ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
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

</body>

</html>
