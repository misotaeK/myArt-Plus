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

$is_logged_in = isset($_SESSION['user_id']);
$my_id = $is_logged_in ? (int)$_SESSION['user_id'] : 0;

// --- ARAMA / FİLTRE / SIRALAMA ---
$search_q = isset($_GET['q']) ? trim($_GET['q']) : '';
$categories = $art_categories;
$filter_category = isset($_GET['category']) && in_array($_GET['category'], $categories) ? $_GET['category'] : '';
$sort = isset($_GET['sort']) && in_array($_GET['sort'], ['newest', 'oldest', 'popular']) ? $_GET['sort'] : 'newest';

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where = [];
$params = [];
$types = '';

if ($search_q !== '') {
    $where[] = "(a.title LIKE ? OR u.username LIKE ?)";
    $like = "%$search_q%";
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}
if ($filter_category !== '') {
    $where[] = "a.category = ?";
    $params[] = $filter_category;
    $types .= 's';
}
$where_sql = count($where) > 0 ? "WHERE " . implode(' AND ', $where) : '';

$order_sql = "a.created_at DESC";
if ($sort === 'oldest') $order_sql = "a.created_at ASC";
if ($sort === 'popular') $order_sql = "like_count DESC, a.created_at DESC";

// Toplam sonuç sayısı
$count_sql = "SELECT COUNT(*) as total FROM artworks a JOIN users u ON a.user_id = u.id $where_sql";
$count_stmt = mysqli_prepare($conn, $count_sql);
if ($types !== '') mysqli_stmt_bind_param($count_stmt, $types, ...$params);
mysqli_stmt_execute($count_stmt);
$total_results = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'];
$total_pages = max(1, ceil($total_results / $per_page));

// Sonuçları çek (beğeni sayısı ve benim beğenip beğenmediğimle birlikte)
$list_sql = "SELECT a.*, u.username, u.profile_pic,
             (SELECT COUNT(*) FROM artwork_likes al WHERE al.artwork_id = a.id) as like_count,
             (SELECT COUNT(*) FROM artwork_likes al2 WHERE al2.artwork_id = a.id AND al2.user_id = $my_id) as liked_by_me
             FROM artworks a JOIN users u ON a.user_id = u.id
             $where_sql
             ORDER BY $order_sql
             LIMIT $per_page OFFSET $offset";
$list_stmt = mysqli_prepare($conn, $list_sql);
if ($types !== '') mysqli_stmt_bind_param($list_stmt, $types, ...$params);
mysqli_stmt_execute($list_stmt);
$artworks_result = mysqli_stmt_get_result($list_stmt);
$artworks = [];
while ($row = mysqli_fetch_assoc($artworks_result)) {
    $artworks[] = $row;
}

function browse_url($overrides = []) {
    $params = array_merge($_GET, $overrides);
    foreach ($params as $k => $v) {
        if ($v === '' || $v === null) unset($params[$k]);
    }
    return 'browse.php?' . http_build_query($params);
}

// Sayfa numaralarını, çok sayfa olduğunda "…" ile kısaltarak döndürür (örn. 1 2 3 … 15)
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
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">

<head>
    <meta charset="UTF-8">
    <title>myArt+ | <?php echo $t['browse_title']; ?></title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css?v=27">
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
        }

        .main-wrapper {
            background: transparent;
        }

        .browse-filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
            padding: 10px;
        }

        .browse-filter-bar input,
        .browse-filter-bar select {
            font-size: 11px;
            padding: 6px;
            border: 1px solid var(--border-color);
            background: var(--bg-color);
            color: var(--text-color);
        }

        .browse-filter-bar input[type="text"] {
            flex-grow: 1;
            min-width: 180px;
        }

        .category-nav {
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
            padding: 12px 15px;
            margin-top: 8px;
        }

        .category-nav-all {
            display: inline-block;
            font-size: 11px;
            font-weight: bold;
            color: var(--text-color);
            text-decoration: none;
            margin-bottom: 10px;
            padding: 4px 12px;
            border-radius: 14px;
            background: var(--box-bg);
            border: 1px solid var(--border-color);
        }

        .category-nav-all.active,
        .category-nav-all:hover {
            background: var(--link-color);
            color: #fff;
            border-color: var(--link-color);
            text-decoration: none;
        }

        .category-columns {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }

        .category-column-title {
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--footer-text);
            margin-bottom: 6px;
        }

        .category-column a {
            display: block;
            padding: 3px 10px;
            margin-bottom: 3px;
            font-size: 11px;
            font-weight: normal;
            color: var(--text-color);
            text-decoration: none;
            border-radius: 12px;
            background: var(--box-bg);
            border: 1px solid var(--nav-border);
        }

        .category-column a:hover,
        .category-column a.active {
            background: var(--link-color);
            color: #fff;
            border-color: var(--link-color);
            text-decoration: none;
        }

        @media (max-width: 700px) {
            .category-columns {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .browse-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            padding: 10px;
        }

        .browse-card {
            border: 1px dotted var(--link-color);
            background: var(--box-bg);
            display: flex;
            flex-direction: column;
            box-shadow: 2px 2px 0 var(--shadow-color);
        }

        .browse-card img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            cursor: zoom-in;
            border-bottom: 1px solid var(--border-color);
        }

        .browse-card-body {
            padding: 6px 8px;
            font-size: 11px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .browse-card-title {
            font-weight: bold;
            color: var(--text-color);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .browse-card-cat {
            font-size: 9px;
            color: var(--footer-text);
            margin-bottom: 4px;
        }

        .browse-card-credit {
            font-size: 9px;
            color: #fff;
            background: var(--ad-border);
            display: inline-block;
            padding: 2px 6px;
            margin-bottom: 4px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 100%;
            box-sizing: border-box;
        }

        .browse-card-artist {
            font-size: 10px;
            margin-top: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .browse-card-artist img {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            margin-right: 4px;
            vertical-align: middle;
            border: none;
        }

        .like-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 11px;
            color: var(--footer-text);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-weight: bold;
        }

        .like-btn .heart-icon {
            font-size: 14px;
            line-height: 1;
            color: var(--thumb-border);
            transition: transform 0.15s ease, color 0.15s ease;
            display: inline-block;
        }

        .like-btn.liked .heart-icon {
            color: #ec008c;
            transform: scale(1.15);
        }

        .like-btn:not([disabled]):hover .heart-icon {
            color: #ec008c;
            transform: scale(1.25);
        }

        .like-btn:not([disabled]):active .heart-icon {
            transform: scale(0.9);
        }

        @media (max-width: 700px) {
            .browse-grid {
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
                <?php $current_page = 'browse'; include 'navbar.php'; ?>
            </td>
        </tr>

        <tr>
            <td valign="top" style="padding: 10px 8px;">
                <div class="box">
                    <div class="header-blue"><?php echo $t['browse_title']; ?></div>
                    <div style="padding: 8px 10px 0 10px; font-size: 11px; color: var(--footer-text);"><?php echo $t['browse_intro']; ?></div>

                    <form method="GET" action="browse.php" class="browse-filter-bar">
                        <input type="text" name="q" value="<?php echo htmlspecialchars($search_q); ?>" placeholder="<?php echo htmlspecialchars($t['search_artwork_placeholder']); ?>">
                        <select name="sort" onchange="this.form.submit()">
                            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>><?php echo $t['sort_newest']; ?></option>
                            <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>><?php echo $t['sort_oldest']; ?></option>
                            <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>><?php echo $t['sort_popular']; ?></option>
                        </select>
                        <input type="hidden" name="category" value="<?php echo htmlspecialchars($filter_category); ?>">
                        <button type="submit" class="form-btn"><?php echo $t['search_btn']; ?></button>
                    </form>

                    <div class="category-nav">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <a href="<?php echo browse_url(['category' => '', 'page' => null]); ?>" class="category-nav-all <?php echo $filter_category === '' ? 'active' : ''; ?>" style="margin-bottom:0;"><?php echo $t['all_categories']; ?></a>
                            <a href="#" id="categoriesToggleLink" onclick="toggleCategories(); return false;" style="color:#7a0044; font-size:14px; text-decoration:none;" aria-label="<?php echo htmlspecialchars(!empty($filter_category) ? $t['hide_categories'] : $t['show_categories']); ?>">&#9662;</a>
                        </div>
                        <div class="category-columns" id="categoryColumnsPanel" style="display:<?php echo !empty($filter_category) ? 'grid' : 'none'; ?>;">
                            <?php foreach ($art_category_groups as $group_label_key => $group_cats): ?>
                                <div class="category-column">
                                    <div class="category-column-title"><?php echo htmlspecialchars($t[$group_label_key]); ?></div>
                                    <?php foreach ($group_cats as $cat_name => $lang_key): ?>
                                        <a href="<?php echo browse_url(['category' => $cat_name, 'page' => null]); ?>" class="<?php echo $filter_category === $cat_name ? 'active' : ''; ?>"><?php echo htmlspecialchars($t[$lang_key]); ?></a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if (count($artworks) > 0): ?>
                        <div class="browse-grid">
                            <?php foreach ($artworks as $art): ?>
                                <div class="browse-card">
                                    <img src="<?php echo htmlspecialchars($art['image_path']); ?>" onclick="enlargeImage('<?php echo htmlspecialchars($art['image_path'], ENT_QUOTES); ?>')" alt="<?php echo htmlspecialchars($art['title'] ?: $t['untitled']); ?>">
                                    <div class="browse-card-body">
                                        <div class="browse-card-title"><?php echo htmlspecialchars($art['title'] ?: $t['untitled']); ?></div>
                                        <div class="browse-card-cat"><?php echo htmlspecialchars($t[$art_category_lang_key[$art['category']] ?? ''] ?? $art['category']); ?></div>
                                        <?php if (empty($art['is_original'] ?? 1)): ?>
                                            <div class="browse-card-credit" title="<?php echo htmlspecialchars($t['credit_prefix'] . ' ' . $art['credit_artist'] . ' ' . $t['credit_on'] . ' ' . $art['credit_platform']); ?>"><?php echo $t['credit_prefix']; ?> <?php echo htmlspecialchars($art['credit_artist']); ?> <?php echo $t['credit_on']; ?> <?php echo htmlspecialchars($art['credit_platform']); ?></div>
                                        <?php endif; ?>
                                        <div class="browse-card-artist">
                                            <a href="profile.php?id=<?php echo $art['user_id']; ?>" style="text-decoration:none; color:var(--link-color);">
                                                <img src="images/<?php echo htmlspecialchars($art['profile_pic'] ?: 'default_avatar.gif'); ?>">
                                                <?php echo htmlspecialchars($art['username']); ?>
                                            </a>
                                            <?php if ($is_logged_in): ?>
                                                <button type="button" class="like-btn <?php echo $art['liked_by_me'] > 0 ? 'liked' : ''; ?>" data-artwork-id="<?php echo $art['id']; ?>" onclick="toggleLike(this)" title="<?php echo htmlspecialchars($art['liked_by_me'] > 0 ? $t['unlike'] : $t['like']); ?>">
                                                    <span class="heart-icon">&#10084;</span> <span class="like-count"><?php echo $art['like_count']; ?></span>
                                                </button>
                                            <?php else: ?>
                                                <span class="like-btn" disabled title="<?php echo htmlspecialchars($t['like']); ?>"><span class="heart-icon">&#10084;</span> <?php echo $art['like_count']; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($total_pages > 1): ?>
                            <div class="pagination-bar">
                                <a href="<?php echo browse_url(['page' => max(1, $page - 1)]); ?>" class="pg-nav <?php echo $page <= 1 ? 'disabled' : ''; ?>" aria-label="<?php echo htmlspecialchars($t['prev_page']); ?>">&lt;</a>
                                <?php foreach (paginate_page_numbers($page, $total_pages) as $p): ?>
                                    <?php if ($p === '...'): ?>
                                        <span class="pg-ellipsis">&hellip;</span>
                                    <?php elseif ($p == $page): ?>
                                        <span class="pg-current"><?php echo $p; ?></span>
                                    <?php else: ?>
                                        <a href="<?php echo browse_url(['page' => $p]); ?>"><?php echo $p; ?></a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <a href="<?php echo browse_url(['page' => min($total_pages, $page + 1)]); ?>" class="pg-nav <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" aria-label="<?php echo htmlspecialchars($t['next_page']); ?>">&gt;</a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div style="text-align:center; padding: 40px 10px; color: gray;">
                            <?php echo ($search_q !== '' || $filter_category !== '') ? $t['no_artworks_found'] : $t['upload_first_artwork']; ?>
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

    <!-- Büyütülen Görsel Modalı -->
    <div class="modal fade" id="artModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
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
        const LIKE_LABEL = <?php echo json_encode($t['like']); ?>;
        const UNLIKE_LABEL = <?php echo json_encode($t['unlike']); ?>;
        const CATEGORIES_SHOW_LABEL = <?php echo json_encode($t['show_categories']); ?>;
        const CATEGORIES_HIDE_LABEL = <?php echo json_encode($t['hide_categories']); ?>;

        function toggleCategories() {
            const panel = $('#categoryColumnsPanel');
            const link = $('#categoriesToggleLink');
            const opening = !panel.is(':visible');
            panel.slideToggle('fast');
            link.attr('aria-label', opening ? CATEGORIES_HIDE_LABEL : CATEGORIES_SHOW_LABEL);
        }

        function enlargeImage(src) {
            document.getElementById('modalEnlargedImage').src = src;
            $('#artModal').modal('show');
        }

        function toggleLike(btn) {
            const artworkId = btn.getAttribute('data-artwork-id');
            $.post('artwork_api.php', {
                action: 'toggle_like',
                artwork_id: artworkId
            }, function(res) {
                $(btn).toggleClass('liked', !!res.liked);
                $(btn).attr('title', res.liked ? UNLIKE_LABEL : LIKE_LABEL);
                $(btn).find('.like-count').text(res.count);
            }, 'json');
        }
    </script>

    <?php if ($is_logged_in): ?>
        <?php include 'chat_widget.php'; ?>
    <?php endif; ?>
</body>

</html>
