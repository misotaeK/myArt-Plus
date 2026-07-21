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
$group_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Grup kontrolü ve üyelik kontrolü
$stmt = mysqli_prepare($conn, "SELECT * FROM `groups` WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $group_id);
mysqli_stmt_execute($stmt);
$group = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$group) {
    header("Location: groups.php");
    exit();
}

$check = mysqli_query($conn, "SELECT 1 FROM group_members WHERE group_id = $group_id AND user_id = $user_id AND status = 'approved'");
if (mysqli_num_rows($check) == 0) {
    header("Location: groups.php"); // Onaylı üye değilse at
    exit();
}
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">

<head>
    <meta charset="UTF-8">
    <title>Group Chat - <?php echo htmlspecialchars($group['name']); ?></title>
    <link rel="stylesheet" href="style.css?v=27">
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
        }

        .main-wrapper {
            background: transparent;
        }

        .msg-row .msg-delete-btn {
            margin-left: auto;
        }

        .chat-container {
            border: 1px solid var(--border-color);
            background: var(--box-bg);
            height: 400px;
            display: flex;
            flex-direction: column;
        }

        .chat-messages {
            flex-grow: 1;
            padding: 10px 10px 10px 40px;
            overflow-y: auto;
            background-color: #fffdf5;
            background-image:
                repeating-linear-gradient(to bottom, transparent, transparent 35px, #ffd9ec 35px, #ffd9ec 36px),
                linear-gradient(to right, transparent 27px, #ff9ec8 27px, #ff9ec8 28px, transparent 28px, transparent 31px, #ff9ec8 31px, #ff9ec8 32px, transparent 32px);
            background-position: 0 -4px;
            border-bottom: 1px solid var(--border-color);
        }

        .msg-row {
            margin-bottom: 8px;
            font-size: 12px;
            display: flex;
            align-items: flex-start;
        }

        .msg-pic {
            width: 30px;
            height: 30px;
            margin-right: 8px;
            border: 1px solid var(--border-color);
        }

        .msg-author {
            font-weight: bold;
            color: var(--link-color);
            margin-bottom: 2px;
        }

        .msg-bubble {
            background: var(--thumb-bg);
            padding: 5px 8px;
            border: 1px dashed var(--border-color);
            display: inline-block;
            color: var(--text-color);
        }

        .chat-input-area {
            padding: 10px;
            background: var(--box-bg);
            display: flex;
        }

        .chat-input-area input {
            flex-grow: 1;
            padding: 8px;
            border: 1px solid var(--border-color);
            background: var(--bg-color);
            color: var(--text-color);
            outline: none;
        }

        .chat-input-area button {
            padding: 8px 15px;
            margin-left: 5px;
            background: var(--ad-border);
            color: #fff;
            border: none;
            font-weight: bold;
            cursor: pointer;
        }

        .member-list {
            padding: 6px;
            max-height: 400px;
            overflow-y: auto;
        }

        .member-item {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 4px;
            border-bottom: 1px dashed var(--border-color);
            font-size: 11px;
        }

        .member-item img {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid var(--thumb-border);
            background: var(--thumb-bg);
            flex-shrink: 0;
        }

        .member-name {
            flex-grow: 1;
            color: var(--link-color);
            font-weight: bold;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .member-leader-badge {
            font-size: 8px;
            background: var(--header-bg);
            color: var(--header-text);
            padding: 1px 5px;
            margin-left: 3px;
            border-radius: 3px;
            font-weight: bold;
            text-transform: uppercase;
            flex-shrink: 0;
        }

        .kick-btn {
            font-size: 9px;
            color: #cc0000;
            background: none;
            border: 1px solid #cc0000;
            padding: 2px 5px;
            cursor: pointer;
            flex-shrink: 0;
        }

        .kick-btn:hover {
            background: #cc0000;
            color: #fff;
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
                        <td width="50%" align="left" valign="bottom"><a href="index.php"><img src="logo.png" alt="Logo" border="0" class="site-logo"></a></td>
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
                <?php $current_page = 'group'; include 'navbar.php'; ?>
            </td>
        </tr>
        <tr>
            <td valign="top" style="padding: 20px 0;">
                <table width="100%" border="0" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="70%" valign="top" style="padding-right: 10px;">
                            <div class="box">
                                <div class="header-blue" style="font-size:14px;">
                                    <span><?php echo htmlspecialchars($group['name'] ?? ''); ?></span>
                                </div>
                                <div class="chat-container">
                                    <div class="chat-messages" id="chatArea">
                                        <!-- Mesajlar Buraya Yüklenecek -->
                                    </div>
                                    <div class="chat-input-area">
                                        <button type="button" class="attach-btn" id="attachBtn" onclick="$('#groupFileInput').click();" title="<?php echo htmlspecialchars($t['attach_file']); ?>">+</button>
                                        <input type="file" id="groupFileInput" style="display:none;">
                                        <input type="text" id="msgInput" placeholder="<?php echo $t['type_a_message']; ?>" autocomplete="off">
                                        <button onclick="sendMessage()"><?php echo $t['send']; ?></button>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td width="30%" valign="top">
                            <div class="box">
                                <div class="header-blue"><?php echo $t['group_members_title']; ?> (<span id="memberCount">0</span>)</div>
                                <div class="member-list" id="memberList">
                                    <!-- AJAX ile doldurulacak -->
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        const groupId = <?php echo $group_id; ?>;
        const chatArea = document.getElementById('chatArea');
        const CONFIRM_DELETE_MSG = <?php echo json_encode($t['confirm_delete_message']); ?>;
        const NO_MESSAGES_YET = <?php echo json_encode($t['no_messages_yet']); ?>;
        const DELETE_LABEL = <?php echo json_encode($t['delete']); ?>;
        const KICK_LABEL = <?php echo json_encode($t['kick_member']); ?>;
        const LEADER_LABEL = <?php echo json_encode($t['leader_badge']); ?>;
        const CONFIRM_KICK_MSG = <?php echo json_encode($t['confirm_kick_member']); ?>;
        const FILE_TOO_LARGE_MSG = <?php echo json_encode($t['file_too_large']); ?>;
        const FILE_TYPE_NOT_ALLOWED_MSG = <?php echo json_encode($t['file_type_not_allowed']); ?>;
        const MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024;

        function escapeHtml(str) {
            return $('<div>').text(str == null ? '' : str).html();
        }

        function deleteGroupMessage(messageId) {
            if (!confirm(CONFIRM_DELETE_MSG)) return;
            $.post('group_api.php', {
                action: 'delete',
                message_id: messageId
            }, function() {
                loadGroupMessages();
            });
        }

        function loadGroupMessages() {
            $.post('group_api.php', {
                action: 'get',
                group_id: groupId
            }, function(data) {
                let msgs = JSON.parse(data);
                let html = '';

                if (msgs.length === 0) {
                    chatArea.innerHTML = `<div style="text-align:center; color:gray; padding:15px 0;">${escapeHtml(NO_MESSAGES_YET)}</div>`;
                    return;
                }

                msgs.forEach(m => {
                    let pic = m.profile_pic ? m.profile_pic : 'default_avatar.gif';
                    let deleteBtn = m.is_mine ? `<button type="button" class="msg-delete-btn" onclick="deleteGroupMessage(${m.id})" title="Delete">[${escapeHtml(DELETE_LABEL)}]</button>` : '';
                    let bubbleContent = '';
                    if (m.message) bubbleContent += escapeHtml(m.message);
                    if (m.attachment) bubbleContent += `<a href="${escapeHtml(m.attachment)}" target="_blank" rel="noopener" class="chat-attachment">📎 ${escapeHtml(m.attachment_name || 'file')}</a>`;
                    html += `
                        <div class="msg-row">
                            <img src="images/${escapeHtml(pic)}" class="msg-pic">
                            <div style="flex-grow:1;">
                                <div class="msg-author">${escapeHtml(m.username)} <span style="font-size:9px; color:gray; font-weight:normal;">${escapeHtml(m.created_at)}</span></div>
                                <div class="msg-row-wrap">
                                    <div class="msg-bubble">${bubbleContent}</div>
                                    ${deleteBtn}
                                </div>
                            </div>
                        </div>
                    `;
                });
                chatArea.innerHTML = html;
            });
        }

        function uploadGroupFile(file) {
            if (!file) return;
            if (file.size > MAX_ATTACHMENT_BYTES) {
                alert(FILE_TOO_LARGE_MSG);
                return;
            }
            let fd = new FormData();
            fd.append('action', 'send_file');
            fd.append('group_id', groupId);
            fd.append('attachment', file);

            let $btn = $('#attachBtn');
            $btn.prop('disabled', true);
            $.ajax({
                url: 'group_api.php',
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                success: function(res) {
                    if (res.trim() === 'success') {
                        loadGroupMessages();
                        setTimeout(() => {
                            chatArea.scrollTop = chatArea.scrollHeight;
                        }, 200);
                    } else if (res.trim() === 'invalid_type') {
                        alert(FILE_TYPE_NOT_ALLOWED_MSG);
                    } else if (res.trim() === 'too_large') {
                        alert(FILE_TOO_LARGE_MSG);
                    }
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        }

        function loadGroupMembers() {
            $.post('group_api.php', {
                action: 'get_members',
                group_id: groupId
            }, function(res) {
                let html = '';
                res.members.forEach(function(mem) {
                    let pic = mem.profile_pic ? mem.profile_pic : 'default_avatar.gif';
                    let isLeaderMember = mem.id == res.leader_id;
                    let leaderBadge = isLeaderMember ? `<span class="member-leader-badge">${escapeHtml(LEADER_LABEL)}</span>` : '';
                    let kickBtn = (res.is_leader && !isLeaderMember) ? `<button type="button" class="kick-btn" onclick="kickMember(${mem.id}, '${escapeHtml(mem.username).replace(/'/g, "\\'")}')">${escapeHtml(KICK_LABEL)}</button>` : '';
                    html += `
                        <div class="member-item">
                            <img src="images/${escapeHtml(pic)}">
                            <span class="member-name">${escapeHtml(mem.username)}</span>
                            ${leaderBadge}
                            ${kickBtn}
                        </div>
                    `;
                });
                $('#memberList').html(html);
                $('#memberCount').text(res.members.length);
            }, 'json');
        }

        function kickMember(memberId, username) {
            if (!confirm(CONFIRM_KICK_MSG.replace(':user', username))) return;
            $.post('group_api.php', {
                action: 'kick',
                group_id: groupId,
                member_id: memberId
            }, function() {
                loadGroupMembers();
            });
        }

        $('#groupFileInput').on('change', function() {
            uploadGroupFile(this.files[0]);
            this.value = '';
        });

        function sendMessage() {
            let msg = $('#msgInput').val();
            if (msg.trim() !== '') {
                $.post('group_api.php', {
                    action: 'send',
                    group_id: groupId,
                    message: msg
                }, function(res) {
                    if (res.trim() === 'success') {
                        $('#msgInput').val('');
                        loadGroupMessages();
                        setTimeout(() => {
                            chatArea.scrollTop = chatArea.scrollHeight;
                        }, 200);
                    }
                });
            }
        }

        $('#msgInput').on('keypress', function(e) {
            if (e.which == 13) sendMessage();
        });

        $(document).ready(function() {
            loadGroupMessages();
            loadGroupMembers();
            setTimeout(() => {
                chatArea.scrollTop = chatArea.scrollHeight;
            }, 500);
            setInterval(loadGroupMessages, 2000); // 2 saniyede bir güncelle
            setInterval(loadGroupMembers, 10000); // 10 saniyede bir üye listesini güncelle
        });
    </script>

    <?php include 'chat_widget.php'; ?>
</body>

</html>