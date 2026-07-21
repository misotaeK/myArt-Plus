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

$submitted = false;
$dev_reset_link = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $submitted = true;

    if ($email !== '') {
        $stmt = mysqli_prepare($conn, "SELECT id, username FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        // Kullanıcı bulunamasa bile aynı mesajı gösteriyoruz (e-posta sızıntısını önlemek için)
        if ($user) {
            $token = bin2hex(random_bytes(32));

            // PHP ve MySQL saat dilimleri farklı olabileceğinden (bu sunucuda öyle),
            // süre hesaplamasını PHP'nin date()'i yerine MySQL'in kendi saatiyle yapıyoruz.
            $ins = mysqli_prepare($conn, "INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, NOW() + INTERVAL 1 HOUR)");
            mysqli_stmt_bind_param($ins, "is", $user['id'], $token);
            mysqli_stmt_execute($ins);

            $reset_link = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/reset_password.php?token=' . $token;

            $subject = $t['reset_email_subject'];
            $body = str_replace([':user', ':link'], [$user['username'], $reset_link], $t['reset_email_body']);
            $headers = "Content-Type: text/plain; charset=UTF-8\r\nFrom: no-reply@myartplus.local\r\n";

            $mail_sent = @mail($email, $subject, $body, $headers);

            if (!$mail_sent && defined('DEV_SHOW_RESET_LINK') && DEV_SHOW_RESET_LINK) {
                $dev_reset_link = $reset_link;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">

<head>
    <meta charset="UTF-8">
    <title>myArt+ | <?php echo $t['forgot_password_title']; ?></title>
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

        .dev-reset-link {
            margin-top: 12px;
            padding: 8px;
            background: var(--ad-bg);
            border: 1px dashed var(--ad-border);
            font-size: 10px;
            word-break: break-all;
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

        <tr>
            <td valign="top">
                <div class="box auth-box">
                    <div class="header-blue"><?php echo $t['forgot_password_title']; ?></div>
                    <div style="padding: 15px; font-size: 12px;">
                        <?php if ($submitted): ?>
                            <p><?php echo $t['reset_email_sent_generic']; ?></p>
                            <?php if ($dev_reset_link): ?>
                                <div class="dev-reset-link">
                                    <strong><?php echo $t['dev_mode_notice']; ?></strong><br>
                                    <a href="<?php echo htmlspecialchars($dev_reset_link); ?>"><?php echo htmlspecialchars($dev_reset_link); ?></a>
                                </div>
                            <?php endif; ?>
                            <p style="margin-top:15px;"><a href="index.php" style="color:var(--link-color); font-weight:bold;"><?php echo $t["back_to_login"]; ?></a></p>
                        <?php else: ?>
                            <p style="color:var(--footer-text);"><?php echo $t['enter_email_prompt']; ?></p>
                            <form method="POST" action="forgot_password.php">
                                <input type="email" name="email" required class="form-input" placeholder="<?php echo htmlspecialchars($t['email']); ?>">
                                <button type="submit" class="form-btn"><?php echo $t['send_reset_link']; ?></button>
                            </form>
                            <p style="margin-top:10px;"><a href="index.php" style="color:var(--link-color);"><?php echo $t["back_to_login"]; ?></a></p>
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
