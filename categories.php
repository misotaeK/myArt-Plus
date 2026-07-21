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

require_once "categories_config.php";

$categories_data = [];
foreach ($art_categories as $cat_name) {
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM artworks a JOIN users u ON a.user_id = u.id WHERE a.category = ?");
    mysqli_stmt_bind_param($stmt, "s", $cat_name);
    mysqli_stmt_execute($stmt);
    $count = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];

    $thumb_stmt = mysqli_prepare($conn, "SELECT a.image_path FROM artworks a JOIN users u ON a.user_id = u.id WHERE a.category = ? ORDER BY a.created_at DESC LIMIT 1");
    mysqli_stmt_bind_param($thumb_stmt, "s", $cat_name);
    mysqli_stmt_execute($thumb_stmt);
    $thumb_row = mysqli_fetch_assoc(mysqli_stmt_get_result($thumb_stmt));

    $categories_data[] = [
        'name' => $cat_name,
        'label' => $t[$art_category_lang_key[$cat_name]] ?? $cat_name,
        'count' => $count,
        'thumb' => $thumb_row ? $thumb_row['image_path'] : null,
    ];
}
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">

<head>
    <meta charset="UTF-8">
    <title>myArt+ | <?php echo $t['categories_title']; ?></title>
    <link rel="stylesheet" href="style.css?v=27">
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
        }

        .main-wrapper {
            background: transparent;
        }

        .category-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            padding: 15px;
        }

        .category-tile {
            position: relative;
            height: 110px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            display: block;
            text-decoration: none;
            background-color: var(--thumb-bg);
            background-size: cover;
            background-position: center;
        }

        .category-tile:hover .category-tile-overlay {
            background: rgba(0, 0, 0, 0.75);
        }

        .category-tile-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            color: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            transition: background 0.15s ease;
        }

        .category-tile-name {
            font-weight: bold;
            font-size: 12px;
        }

        .category-tile-count {
            font-size: 10px;
            color: #ddd;
            margin-top: 2px;
        }

        @media (max-width: 700px) {
            .category-grid {
                grid-template-columns: repeat(2, 1fr);
            }
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
                <?php $current_page = 'categories'; include 'navbar.php'; ?>
            </td>
        </tr>

        <tr>
            <td valign="top" style="padding: 10px 8px;">
                <div class="box">
                    <div class="header-blue"><?php echo $t['categories_title']; ?></div>
                    <div style="padding: 8px 10px 0 10px; font-size: 11px; color: var(--footer-text);"><?php echo $t['categories_intro']; ?></div>

                    <div class="category-grid">
                        <?php foreach ($categories_data as $cat): ?>
                            <a href="browse.php?category=<?php echo urlencode($cat['name']); ?>" class="category-tile" <?php echo $cat['thumb'] ? 'style="background-image:url(\'' . htmlspecialchars($cat['thumb']) . '\');"' : ''; ?>>
                                <div class="category-tile-overlay">
                                    <div class="category-tile-name"><?php echo htmlspecialchars($cat['label']); ?></div>
                                    <div class="category-tile-count"><?php echo $cat['count'] > 0 ? $cat['count'] . ' ' . ($cat['count'] === 1 ? $t['artwork_count_label_singular'] : $t['artworks_count_label']) : $t['category_empty']; ?></div>
                                </div>
                            </a>
                        <?php endforeach; ?>
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

    <?php if ($is_logged_in): ?>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <?php include 'chat_widget.php'; ?>
    <?php endif; ?>

</body>

</html>
