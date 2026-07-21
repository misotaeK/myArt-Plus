<?php
session_start();

// 1. Veritabanı bağlantısını çağırıyoruz
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

// --- KAYIT OLMA (SIGN UP) İŞLEMİ MOTORU (MySQLi Versiyonu) ---
$register_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    $gender = isset($_POST['gender']) ? $_POST['gender'] : 'unspecified';
    $terms = isset($_POST['terms']) ? true : false;

    if (empty($username) || empty($email) || empty($password)) {
        $register_message = "<div style='color: red; margin-bottom: 10px; font-weight: bold;'>Lütfen tüm alanları doldurun!</div>";
    } elseif ($password !== $password_confirm) {
        $register_message = "<div style='color: red; margin-bottom: 10px; font-weight: bold;'>Şifreler birbiriyle uyuşmuyor!</div>";
    } elseif (!$terms) {
        $register_message = "<div style='color: red; margin-bottom: 10px; font-weight: bold;'>Şartlar ve Koşulları kabul etmelisiniz!</div>";
    } else {
        // Kullanıcı adı veya e-posta zaten var mı kontrol et (MySQLi prepare statement)
        $check_stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? OR email = ?");
        mysqli_stmt_bind_param($check_stmt, "ss", $username, $email);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);

        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            $register_message = "<div style='color: red; margin-bottom: 10px; font-weight: bold;'>Bu kullanıcı adı veya e-posta zaten kullanılıyor.</div>";
        } else {
            // Şifreyi kriptola ve kaydet
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_stmt = mysqli_prepare($conn, "INSERT INTO users (username, email, password, gender) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($insert_stmt, "ssss", $username, $email, $hashed_password, $gender);

            if (mysqli_stmt_execute($insert_stmt)) {
                // KAYIT BAŞARILI: Oturumu başlat ve ana sayfaya gönder
                $_SESSION['user_id'] = mysqli_insert_id($conn);
                $_SESSION['username'] = $username;
                $_SESSION['role'] = 'user';

                header("Location: index.php"); // Direkt ana sayfaya atar
                exit();
            } else {
                $register_message = "<div style='color: red; margin-bottom: 10px; font-weight: bold;'>Kayıt sırasında bir hata oluştu.</div>";
            }
        }
        // İfadeleri kapatıyoruz
        if (isset($check_stmt)) mysqli_stmt_close($check_stmt);
        if (isset($insert_stmt)) mysqli_stmt_close($insert_stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">

<head>
    <meta charset="UTF-8">
    <title>Sign Up</title>
    <link rel="stylesheet" href="style.css?v=27">
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
                            Theme :
                            <?php if ($current_theme == 'light'): ?>
                                <span class="active">Acik</span> | <a href="?theme=dark">Koyu</a>
                            <?php else: ?>
                                <a href="?theme=light">Acik</a> | <span class="active">Koyu</span>
                            <?php endif; ?>
                            &nbsp;&nbsp;&nbsp;
                            Dil:
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
                <div class="navbar">
                    <a href="home.php"><?php echo isset($t['home']) ? $t['home'] : 'Home'; ?></a> |
                    <a href="browse.php"><?php echo isset($t['browse']) ? $t['browse'] : 'Browse'; ?></a> |
                    <a href="forum.php"><?php echo isset($t['forum']) ? $t['forum'] : 'Forum'; ?></a> |
                    <a href="groups.php"><?php echo isset($t['group']) ? $t['group'] : 'Group'; ?></a> |
                    <a href="categories.php"><?php echo isset($t['categories']) ? $t['categories'] : 'Categories'; ?></a> |
                    <a href="signup.php"><?php echo isset($t['signup']) ? $t['signup'] : 'Sign Up'; ?></a> |
                    <a href="supportus.php"><?php echo isset($t['support']) ? $t['support'] : 'Support Us'; ?></a>
                </div>
            </td>
        </tr>

        <tr>
            <td valign="top" style="padding: 10px 8px;">
                <table width="100%" border="0" cellpadding="4" cellspacing="0">
                    <tr>

                        <td width="40%" valign="top">
                            <div class="box">
                                <div class="header-blue">Join The Community!</div>
                                <div class="events-text" style="height: 145px;">
                                    <p style="color: var(--link-color); font-size: 14px;">Why Sign Up?</p>
                                    <ul style="padding-left: 15px; color: var(--text-color);">
                                        <li>Create your own custom profile</li>
                                        <li>Connect with cool artists</li>
                                        <li>Join exclusive forums & groups</li>
                                        <li>Leave comments on friends' pages</li>
                                    </ul>
                                    <br>
                                    <div style="text-align: center; color: var(--footer-text); font-style: italic;">
                                        "It's the best place on the web!"
                                    </div>
                                </div>
                            </div>
                        </td>

                        <td width="60%" valign="top">
                            <div class="box">
                                <div class="header-blue">Create Your Account</div>
                                <div class="login-area" style="height: 250px; padding: 20px;">

                                    <?php if (!empty($register_message)) echo $register_message; ?>

                                    <form action="#" method="POST">
                                        <table width="100%" border="0" cellpadding="5" style="font-size: 12px; color: var(--text-color);">
                                            <tr>
                                                <td align="right" width="35%"><strong>Username:</strong></td>
                                                <td><input type="text" name="username" class="login-input" style="width: 200px;"></td>
                                            </tr>
                                            <tr>
                                                <td align="right"><strong>E-Mail:</strong></td>
                                                <td><input type="text" name="email" class="login-input" style="width: 200px;"></td>
                                            </tr>
                                            <tr>
                                                <td align="right"><strong>Password:</strong></td>
                                                <td><input type="password" name="password" class="login-input" style="width: 200px;"></td>
                                            </tr>
                                            <tr>
                                                <td align="right"><strong>Confirm Password:</strong></td>
                                                <td><input type="password" name="password_confirm" class="login-input" style="width: 200px;"></td>
                                            </tr>
                                            <tr>
                                                <td align="right"><strong>Gender:</strong></td>
                                                <td>
                                                    <input type="radio" name="gender" value="m" id="gm"> <label for="gm">Male</label> &nbsp;
                                                    <input type="radio" name="gender" value="f" id="gf"> <label for="gf">Female</label>
                                                    <input type="radio" name="gender" value="nb" id="gnb"> <label for="gnb">Whatever I want :3</label>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td></td>
                                                <td style="padding-top: 10px;">
                                                    <input type="checkbox" name="terms" id="terms">
                                                    <label for="terms">I agree to the <a href="#">Terms & Conditions</a></label>
                                                </td>
                                            </tr>
                                            <tr height="40">
                                                <td></td>
                                                <td valign="bottom">
                                                    <input type="submit" value="SIGN UP NOW!" class="btn-signup" style="padding: 6px 12px; font-size: 12px;">
                                                </td>
                                            </tr>
                                        </table>
                                    </form>
                                </div>
                            </div>
                        </td>

                    </tr>
                </table>
            </td>
        </tr>

        <tr>
            <td valign="bottom" style="padding-top: 10px;">
                <div class="footer">
                    <a href="qa.php">Q&A</a> |
                    <a href="privacy.php"><?php echo isset($t['privacy']) ? $t['privacy'] : 'Privacy'; ?></a> |
                    <a href="help.php"><?php echo isset($t['help']) ? $t['help'] : 'Help'; ?></a> |
                    <a href="terms.php"><?php echo isset($t['terms']) ? $t['terms'] : 'Terms and Conditions'; ?></a>
                    <div class="footer-copy">© <?php echo date("Y"); ?> myArt+ | All Rights Reserved.</div>
                </div>
            </td>
        </tr>
    </table>

</body>

</html>