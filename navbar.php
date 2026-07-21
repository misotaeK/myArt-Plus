<?php
// Paylaşılan üst menü. Beklenenler: $t (dil dizisi) ve config.php zaten yüklenmiş olmalı.
// $current_page host sayfa tarafından include'dan önce ayarlanabilir (örn. 'browse', 'forum', 'events'...)
// böylece ilgili menü öğesi kalın/aktif gösterilir.
if (!isset($current_page)) $current_page = '';
$nb_is_logged_in = isset($_SESSION['user_id']);
$nb_is_admin = $nb_is_logged_in && ($_SESSION['role'] ?? '') === 'admin';
?>
<div class="navbar">
    <?php if ($nb_is_logged_in): ?>
        <a href="home.php" class="<?php echo $current_page === 'home' ? 'active' : ''; ?>"><?php echo $t['home']; ?></a><span class="sep">|</span>
    <?php else: ?>
        <a href="index.php" class="<?php echo $current_page === 'home' ? 'active' : ''; ?>"><?php echo $t['home']; ?></a><span class="sep">|</span>
    <?php endif; ?>
    <a href="browse.php" class="<?php echo $current_page === 'browse' ? 'active' : ''; ?>"><?php echo $t['browse']; ?></a><span class="sep">|</span>
    <a href="forum.php" class="<?php echo $current_page === 'forum' ? 'active' : ''; ?>"><?php echo $t['forum']; ?></a><span class="sep">|</span>
    <a href="groups.php" class="<?php echo $current_page === 'group' ? 'active' : ''; ?>"><?php echo $t['group']; ?></a><span class="sep">|</span>
    <a href="exchange.php" class="<?php echo $current_page === 'exchange' ? 'active' : ''; ?>"><?php echo $t['exchange']; ?></a><span class="sep">|</span>
    <a href="events.php" class="<?php echo $current_page === 'events' ? 'active' : ''; ?>"><?php echo $t['events']; ?></a><span class="sep">|</span>
    <?php if (!$nb_is_logged_in): ?>
        <a href="signup.php" class="<?php echo $current_page === 'signup' ? 'active' : ''; ?>"><?php echo $t['signup']; ?></a><span class="sep">|</span>
        <a href="supportus.php" class="<?php echo $current_page === 'support' ? 'active' : ''; ?>"><?php echo $t['support']; ?></a>
    <?php else: ?>
        <a href="myprofile.php" class="<?php echo $current_page === 'my_profile' ? 'active' : ''; ?>"><?php echo $t['my_profile']; ?></a><span class="sep">|</span>
        <?php if ($nb_is_admin): ?>
            <a href="admin.php" class="<?php echo $current_page === 'admin' ? 'active' : ''; ?>"><?php echo $t['admin_panel']; ?></a><span class="sep">|</span>
        <?php endif; ?>
        <a href="logout.php"><?php echo $t['logout']; ?></a>
    <?php endif; ?>
</div>
