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
$is_admin = ($_SESSION['role'] ?? '') === 'admin';
$initial_thread_id = isset($_GET['thread']) ? (int)$_GET['thread'] : 0;
$initial_board_id = isset($_GET['board']) ? (int)$_GET['board'] : 0;

$boards_result = mysqli_query($conn, "SELECT * FROM forum_boards ORDER BY sort_order ASC");
$boards = [];
while ($b = mysqli_fetch_assoc($boards_result)) {
    $boards[] = $b;
}
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">

<head>
    <meta charset="UTF-8">
    <title>myArt+ | <?php echo $t['forum']; ?></title>
    <link rel="stylesheet" href="style.css?v=27">
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
        }

        .main-wrapper {
            background: transparent;
        }

        .forum-topbar {
            padding: 10px 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }

        .forum-topbar input[type="text"] {
            flex-grow: 1;
            min-width: 160px;
            font-size: 11px;
            padding: 6px;
            border: 1px solid var(--border-color);
            background: var(--bg-color);
            color: var(--text-color);
        }

        .board-pill {
            font-size: 11px;
            font-weight: bold;
            padding: 5px 14px;
            border: 1px solid var(--nav-border);
            border-radius: 0;
            text-decoration: none;
            color: var(--footer-text);
            background: var(--thumb-bg);
            cursor: pointer;
            white-space: nowrap;
        }

        .board-pill.active {
            background: var(--link-color);
            color: #fff;
            border-color: var(--link-color);
        }

        .forum-thread-head {
            display: grid;
            grid-template-columns: 1fr 70px 70px 150px;
            background: var(--thumb-bg);
            font-size: 10px;
            font-weight: bold;
            color: var(--footer-text);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 8px 12px;
            border-bottom: 1px solid var(--nav-border);
        }

        .forum-thread-head span.col-center {
            text-align: center;
        }

        .forum-thread-row {
            display: grid;
            grid-template-columns: 1fr 70px 70px 150px;
            padding: 10px 12px;
            border-bottom: 1px solid var(--footer-border);
            align-items: center;
            cursor: pointer;
        }

        .forum-thread-row:hover {
            background: var(--ad-bg);
        }

        .forum-thread-row-title-cell {
            display: flex;
            align-items: center;
            gap: 6px;
            overflow: hidden;
        }

        .forum-board-tag {
            font-size: 9px;
            font-weight: bold;
            padding: 2px 6px;
            color: #333;
            flex-shrink: 0;
            white-space: nowrap;
        }

        .forum-thread-row-title {
            font-size: 12px;
            color: var(--link-color);
            font-weight: bold;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .forum-thread-row-replies,
        .forum-thread-row-views {
            text-align: center;
            font-size: 11px;
            color: var(--footer-text);
        }

        .forum-thread-row-lastpost {
            font-size: 10px;
            color: var(--footer-text);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .forum-thread-list {
            max-height: 560px;
            overflow-y: auto;
        }

        .thread-status-badge {
            font-size: 9px;
            padding: 1px 5px;
            border-radius: 3px;
            color: #fff;
            font-weight: bold;
        }

        .thread-status-badge.pinned {
            background: #ff9900;
        }

        .thread-status-badge.locked {
            background: #888;
        }

        .forum-view-header {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-color);
            font-size: 13px;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .forum-back-link {
            font-size: 11px;
            font-weight: normal;
            color: var(--link-color);
            text-decoration: none;
        }

        .forum-view-actions {
            font-size: 10px;
            font-weight: normal;
        }

        .forum-view-actions a {
            color: var(--link-color);
            text-decoration: none;
            margin-left: 6px;
        }

        .forum-messages {
            padding: 12px;
            overflow-y: auto;
            max-height: 460px;
            background: var(--bg-color);
        }

        .forum-msg-row {
            display: flex;
            gap: 8px;
            margin-bottom: 14px;
        }

        .forum-msg-avatar {
            width: 36px;
            height: 36px;
            border: 1px solid var(--border-color);
            border-radius: 50%;
            flex-shrink: 0;
        }

        .forum-msg-body {
            max-width: 75%;
        }

        .forum-msg-author {
            font-size: 10px;
            font-weight: bold;
            color: var(--link-color);
            margin-bottom: 3px;
        }

        .forum-msg-bubble {
            background: var(--box-bg);
            border: 1px dotted var(--link-color);
            border-radius: 12px;
            padding: 8px 12px;
            font-size: 12px;
            line-height: 1.4;
            white-space: pre-wrap;
            position: relative;
        }

        .forum-msg-row.mine {
            flex-direction: row-reverse;
        }

        .forum-msg-row.mine .forum-msg-body {
            text-align: right;
        }

        .forum-msg-row.mine .forum-msg-bubble {
            background: var(--ad-bg);
        }

        .forum-msg-time {
            font-size: 9px;
            color: var(--footer-text);
            margin-top: 3px;
        }

        .forum-msg-delete {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 10px;
            color: var(--footer-text);
        }

        .forum-msg-delete:hover {
            color: #cc0000;
        }

        .forum-reply-box {
            border-top: 1px solid var(--border-color);
            padding: 8px;
            display: flex;
            gap: 6px;
        }

        .forum-reply-box input {
            flex-grow: 1;
            font-size: 12px;
            padding: 7px;
            border: 1px solid var(--border-color);
            background: var(--bg-color);
            color: var(--text-color);
        }

        .forum-empty-state {
            display: flex;
            align-items: center;
            justify-content: center;
            color: gray;
            font-size: 12px;
            text-align: center;
            padding: 40px 20px;
        }

        @media (max-width: 700px) {

            .forum-thread-head,
            .forum-thread-row {
                grid-template-columns: 1fr 50px 50px;
            }

            .forum-thread-row-lastpost {
                display: none;
            }

            .forum-thread-head span:last-child {
                display: none;
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
                <?php $current_page = 'forum'; include 'navbar.php'; ?>
            </td>
        </tr>

        <tr>
            <td valign="top" style="padding: 10px 8px;">
                <div class="box">
                    <div class="header-blue" style="display:flex; justify-content:space-between; align-items:center;">
                        <span><?php echo $t['forum']; ?></span>
                        <?php if ($is_logged_in): ?>
                            <a href="#" onclick="showNewThreadForm(); return false;" style="color:#7a0044; font-size:18px; font-weight:bold; text-decoration:none; line-height:1;" title="<?php echo htmlspecialchars($t['new_thread']); ?>" aria-label="<?php echo htmlspecialchars($t['new_thread']); ?>">+</a>
                        <?php endif; ?>
                    </div>

                    <div class="forum-topbar">
                        <input type="text" id="forumSearch" placeholder="<?php echo htmlspecialchars($t['search_threads_placeholder']); ?>">
                        <span class="board-pill active" data-board-id="0" onclick="selectBoard(0, this)"><?php echo $t['all_boards']; ?></span>
                        <?php foreach ($boards as $board): ?>
                            <span class="board-pill" data-board-id="<?php echo $board['id']; ?>" onclick="selectBoard(<?php echo $board['id']; ?>, this)"><?php echo htmlspecialchars($board['name']); ?></span>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($is_logged_in): ?>
                        <div id="newThreadForm" style="display:none; padding:10px; border-bottom:1px solid var(--border-color); background:var(--thumb-bg);">
                            <select id="newThreadBoard" class="form-input">
                                <?php foreach ($boards as $board): ?>
                                    <option value="<?php echo $board['id']; ?>"><?php echo htmlspecialchars($board['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" id="newThreadTitle" class="form-input" placeholder="<?php echo htmlspecialchars($t['thread_title_placeholder']); ?>" maxlength="150">
                            <textarea id="newThreadMessage" class="form-input" rows="3" placeholder="<?php echo htmlspecialchars($t['thread_message_placeholder']); ?>"></textarea>
                            <button type="button" class="form-btn" onclick="submitNewThread()"><?php echo $t['post_thread']; ?></button>
                        </div>
                    <?php endif; ?>

                    <div id="forumListView">
                        <div class="forum-thread-head">
                            <span><?php echo $t['thread_col_label']; ?></span>
                            <span class="col-center"><?php echo $t['replies_label']; ?></span>
                            <span class="col-center"><?php echo $t['views_label']; ?></span>
                            <span><?php echo $t['last_post_label']; ?></span>
                        </div>
                        <div class="forum-thread-list" id="forumThreadPanel">
                            <!-- AJAX ile doldurulacak -->
                        </div>
                    </div>
                    <div id="forumDetailView" style="display:none;">
                        <div class="forum-empty-state" id="forumEmptyState"><?php echo $t['select_thread_prompt']; ?></div>
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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        const FORUM_I18N = {
            pinned: <?php echo json_encode($t['pinned']); ?>,
            locked: <?php echo json_encode($t['locked']); ?>,
            byArtist: <?php echo json_encode($t['by_artist']); ?>,
            viewsLabel: <?php echo json_encode($t['views_label']); ?>,
            repliesLabel: <?php echo json_encode($t['replies_label']); ?>,
            opBadge: <?php echo json_encode($t['op_badge']); ?>,
            pinThread: <?php echo json_encode($t['pin_thread']); ?>,
            unpinThread: <?php echo json_encode($t['unpin_thread']); ?>,
            lockThread: <?php echo json_encode($t['lock_thread']); ?>,
            unlockThread: <?php echo json_encode($t['unlock_thread']); ?>,
            deleteThread: <?php echo json_encode($t['delete_thread']); ?>,
            confirmDeleteThread: <?php echo json_encode($t['confirm_delete_thread']); ?>,
            confirmDeleteMessage: <?php echo json_encode($t['confirm_delete_message']); ?>,
            noThreadsYet: <?php echo json_encode($t['no_threads_yet']); ?>,
            threadLockedNotice: <?php echo json_encode($t['thread_locked_notice']); ?>,
            replyPlaceholder: <?php echo json_encode($t['reply_placeholder']); ?>,
            selectThreadPrompt: <?php echo json_encode($t['select_thread_prompt']); ?>,
            postReply: <?php echo json_encode($t['post_reply']); ?>,
            deleteLabel: <?php echo json_encode($t['delete']); ?>,
            backToThreads: <?php echo json_encode($t['back_to_threads']); ?>
        };
        const FORUM_IS_LOGGED_IN = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
        const FORUM_IS_ADMIN = <?php echo $is_admin ? 'true' : 'false'; ?>;
        let currentBoardId = <?php echo $initial_board_id; ?>;
        let currentThreadId = <?php echo $initial_thread_id; ?>;
        let currentThreadPage = 1;
        let searchDebounce;

        function paginatePageNumbers(current, total, delta) {
            delta = delta || 1;
            let items = [];
            let prev = 0;
            for (let i = 1; i <= total; i++) {
                if (i === 1 || i === total || (i >= current - delta && i <= current + delta)) {
                    if (prev && i - prev > 1) items.push('...');
                    items.push(i);
                    prev = i;
                }
            }
            return items;
        }

        function buildPaginationHtml(current, total) {
            if (total <= 1) return '';
            let prevPage = Math.max(1, current - 1);
            let nextPage = Math.min(total, current + 1);
            let html = '<div class="pagination-bar">';
            html += `<a href="#" class="pg-nav ${current <= 1 ? 'disabled' : ''}" onclick="goToThreadPage(${prevPage}); return false;">&lt;</a>`;
            paginatePageNumbers(current, total).forEach(function(p) {
                if (p === '...') {
                    html += '<span class="pg-ellipsis">&hellip;</span>';
                } else if (p === current) {
                    html += `<span class="pg-current">${p}</span>`;
                } else {
                    html += `<a href="#" onclick="goToThreadPage(${p}); return false;">${p}</a>`;
                }
            });
            html += `<a href="#" class="pg-nav ${current >= total ? 'disabled' : ''}" onclick="goToThreadPage(${nextPage}); return false;">&gt;</a>`;
            html += '</div>';
            return html;
        }

        function goToThreadPage(p) {
            currentThreadPage = p;
            loadThreadList();
        }

        const BOARD_TAG_COLORS = ['#c8c8c8', '#ffb3d9', '#c8f7c8', '#fff59e', '#b3e0ff', '#e0c8ff'];

        function escapeHtml(str) {
            return $('<div>').text(str == null ? '' : str).html();
        }

        function timeAgo(dateStr) {
            let then = new Date(dateStr.replace(' ', 'T'));
            let diffMs = Date.now() - then.getTime();
            let mins = Math.floor(diffMs / 60000);
            if (mins < 1) return 'just now';
            if (mins < 60) return mins + 'm ago';
            let hours = Math.floor(mins / 60);
            if (hours < 24) return hours + 'h ago';
            let days = Math.floor(hours / 24);
            return days + 'd ago';
        }

        function selectBoard(boardId, el) {
            currentBoardId = boardId;
            currentThreadPage = 1;
            $('.board-pill').removeClass('active');
            $(el).addClass('active');
            loadThreadList();
        }

        function loadThreadList() {
            $.post('forum_api.php', {
                action: 'get_threads',
                q: $('#forumSearch').val(),
                board_id: currentBoardId,
                page: currentThreadPage
            }, function(data) {
                let threads = data.threads;
                let html = '';
                if (threads.length === 0) {
                    html = `<div style="text-align:center; padding:20px; color:gray; font-size:11px;">${escapeHtml(FORUM_I18N.noThreadsYet)}</div>`;
                }
                threads.forEach(function(th) {
                    let pinBadge = th.is_pinned == 1 ? `<span class="thread-status-badge pinned">${escapeHtml(FORUM_I18N.pinned)}</span> ` : '';
                    let lockBadge = th.is_locked == 1 ? `<span class="thread-status-badge locked">${escapeHtml(FORUM_I18N.locked)}</span> ` : '';
                    let tagColor = BOARD_TAG_COLORS[th.board_id % BOARD_TAG_COLORS.length];
                    let lastPoster = th.last_poster || th.username;
                    html += `
                        <div class="forum-thread-row" data-thread-id="${th.id}" onclick="openThread(${th.id})">
                            <div class="forum-thread-row-title-cell">
                                <span class="forum-board-tag" style="background:${tagColor};">${escapeHtml(th.board_name)}</span>
                                <span class="forum-thread-row-title">${pinBadge}${lockBadge}${escapeHtml(th.title)}</span>
                            </div>
                            <div class="forum-thread-row-replies">${th.reply_count}</div>
                            <div class="forum-thread-row-views">${th.views}</div>
                            <div class="forum-thread-row-lastpost">${escapeHtml(lastPoster)} &middot; ${timeAgo(th.last_activity_at)}</div>
                        </div>
                    `;
                });
                html += buildPaginationHtml(data.page, data.total_pages);
                $('#forumThreadPanel').html(html);
            }, 'json');
        }

        function openThread(threadId) {
            currentThreadId = threadId;

            $.post('forum_api.php', {
                action: 'get_thread',
                thread_id: threadId
            }, function(data) {
                if (data.error) return;
                renderThread(data.thread, data.posts);
                $('#forumListView').hide();
                $('#forumDetailView').show();
            }, 'json');
        }

        function backToList() {
            currentThreadId = 0;
            $('#forumDetailView').hide();
            $('#forumListView').show();
            loadThreadList();
        }

        function renderThread(thread, posts) {
            let manageLinks = '';
            if (FORUM_IS_ADMIN) {
                let pinLabel = thread.is_pinned == 1 ? FORUM_I18N.unpinThread : FORUM_I18N.pinThread;
                let lockLabel = thread.is_locked == 1 ? FORUM_I18N.unlockThread : FORUM_I18N.lockThread;
                manageLinks += `<a href="#" onclick="togglePin(${thread.id}); return false;">${escapeHtml(pinLabel)}</a>`;
                manageLinks += `<a href="#" onclick="toggleLock(${thread.id}); return false;">${escapeHtml(lockLabel)}</a>`;
            }
            if (FORUM_IS_LOGGED_IN && thread.can_manage) {
                manageLinks += `<a href="#" onclick="deleteThread(${thread.id}); return false;">${escapeHtml(FORUM_I18N.deleteThread)}</a>`;
            }

            let headerPinBadge = thread.is_pinned == 1 ? `<span class="thread-status-badge pinned">${escapeHtml(FORUM_I18N.pinned)}</span> ` : '';
            let headerLockBadge = thread.is_locked == 1 ? `<span class="thread-status-badge locked">${escapeHtml(FORUM_I18N.locked)}</span> ` : '';

            let html = `
                <div class="forum-view-header">
                    <span><a href="#" class="forum-back-link" onclick="backToList(); return false;">&larr; ${escapeHtml(FORUM_I18N.backToThreads)}</a> &middot; ${headerPinBadge}${headerLockBadge}${escapeHtml(thread.title)}</span>
                    <span class="forum-view-actions">${thread.views} ${escapeHtml(FORUM_I18N.viewsLabel)} ${manageLinks}</span>
                </div>
                <div class="forum-messages" id="forumMessages"></div>
            `;

            if (thread.is_locked == 1) {
                html += `<div style="padding:10px; text-align:center; font-size:11px; color:gray;">${escapeHtml(FORUM_I18N.threadLockedNotice)}</div>`;
            } else if (FORUM_IS_LOGGED_IN) {
                html += `
                    <div class="forum-reply-box">
                        <input type="text" id="forumReplyInput" placeholder="${escapeHtml(FORUM_I18N.replyPlaceholder)}">
                        <button type="button" class="form-btn" onclick="sendReply(${thread.id})">${escapeHtml(FORUM_I18N.postReply)}</button>
                    </div>
                `;
            }

            $('#forumDetailView').html(html);
            renderPosts(posts);

            $('#forumReplyInput').on('keypress', function(e) {
                if (e.which === 13) sendReply(thread.id);
            });
        }

        function renderPosts(posts) {
            let html = '';
            posts.forEach(function(p, index) {
                let mineClass = p.is_mine ? 'mine' : '';
                let opBadge = index === 0 ? `<span style="font-size:9px; background:var(--ad-border); color:#fff; padding:1px 5px; border-radius:3px; margin-left:4px;">${escapeHtml(FORUM_I18N.opBadge)}</span>` : '';
                let deleteBtn = p.can_delete ? `<button type="button" class="forum-msg-delete" onclick="deletePost(${p.thread_id}, ${p.id})">[${escapeHtml(FORUM_I18N.deleteLabel)}]</button>` : '';

                html += `
                    <div class="forum-msg-row ${mineClass}">
                        <a href="profile.php?id=${p.user_id}"><img class="forum-msg-avatar" src="images/${escapeHtml(p.profile_pic || 'default_avatar.gif')}"></a>
                        <div class="forum-msg-body">
                            <div class="forum-msg-author"><a href="profile.php?id=${p.user_id}" style="color:var(--link-color);">${escapeHtml(p.username)}</a>${opBadge}</div>
                            <div class="forum-msg-bubble">${escapeHtml(p.message)}</div>
                            <div class="forum-msg-time">${timeAgo(p.created_at)} ${deleteBtn}</div>
                        </div>
                    </div>
                `;
            });
            $('#forumMessages').html(html);
            $('#forumMessages').scrollTop($('#forumMessages')[0].scrollHeight);
        }

        function sendReply(threadId) {
            let message = $('#forumReplyInput').val();
            if (!message || !message.trim()) return;
            $.post('forum_api.php', {
                action: 'reply',
                thread_id: threadId,
                message: message
            }, function(res) {
                if (res.success) {
                    $('#forumReplyInput').val('');
                    openThread(threadId);
                }
            }, 'json');
        }

        function deletePost(threadId, postId) {
            if (!confirm(FORUM_I18N.confirmDeleteMessage)) return;
            $.post('forum_api.php', {
                action: 'delete_post',
                thread_id: threadId,
                post_id: postId
            }, function(res) {
                if (res.thread_deleted) {
                    backToList();
                } else {
                    openThread(threadId);
                }
            }, 'json');
        }

        function togglePin(threadId) {
            $.post('forum_api.php', {
                action: 'toggle_pin',
                thread_id: threadId
            }, function() {
                openThread(threadId);
            }, 'json');
        }

        function toggleLock(threadId) {
            $.post('forum_api.php', {
                action: 'toggle_lock',
                thread_id: threadId
            }, function() {
                openThread(threadId);
            }, 'json');
        }

        function deleteThread(threadId) {
            if (!confirm(FORUM_I18N.confirmDeleteThread)) return;
            $.post('forum_api.php', {
                action: 'delete_thread',
                thread_id: threadId
            }, function(res) {
                if (res.success) {
                    backToList();
                }
            }, 'json');
        }

        function showNewThreadForm() {
            $('#newThreadForm').slideToggle('fast');
        }

        function submitNewThread() {
            let title = $('#newThreadTitle').val().trim();
            let message = $('#newThreadMessage').val().trim();
            let boardId = $('#newThreadBoard').val();
            if (!title || !message) return;

            $.post('forum_api.php', {
                action: 'new_thread',
                board_id: boardId,
                title: title,
                message: message
            }, function(res) {
                if (res.success) {
                    $('#newThreadTitle').val('');
                    $('#newThreadMessage').val('');
                    $('#newThreadForm').slideUp('fast');
                    openThread(res.thread_id);
                }
            }, 'json');
        }

        $('#forumSearch').on('input', function() {
            clearTimeout(searchDebounce);
            currentThreadPage = 1;
            searchDebounce = setTimeout(loadThreadList, 300);
        });

        $(document).ready(function() {
            if (currentBoardId > 0) {
                $('.board-pill').removeClass('active');
                $(`.board-pill[data-board-id="${currentBoardId}"]`).addClass('active');
            }
            loadThreadList();
            if (currentThreadId > 0) {
                openThread(currentThreadId);
            }
        });
    </script>

    <?php if ($is_logged_in): ?>
        <?php include 'chat_widget.php'; ?>
    <?php endif; ?>

</body>

</html>
