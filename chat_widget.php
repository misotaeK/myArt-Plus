<?php
// Paylaşılan sohbet widget'ı. Sadece giriş yapmış kullanıcı sayfalarına dahil edilir.
// Beklenenler: $t (dil dizisi) ve aktif bir oturum ($_SESSION['user_id']) zaten yüklenmiş olmalı.
// jQuery (tam sürüm, slim değil) bu dosya include edilmeden ÖNCE sayfada yüklenmiş olmalı.
?>
<div class="chat-sidebar" id="chatSidebar">
    <div class="chat-sidebar-header" onclick="toggleChatSidebar()">
        <span><?php echo $t['chat']; ?> <span id="friendRequestBadge" class="chat-badge" style="display:none;">0</span><span id="unreadMsgBadge" class="chat-badge" style="display:none;">0</span></span>
    </div>
    <div class="chat-sidebar-body" id="chatUserList">
        <!-- AJAX ile doldurulacak: bekleyen istekler + arkadaş listesi -->
    </div>
</div>

<div class="chat-box" id="chatBox">
    <div class="chat-box-header">
        <span id="chatBoxName">User Name</span>
        <div>
            <a href="#" onclick="closeChatBox()">—</a>
            <a href="#" onclick="closeChatBox()">X</a>
        </div>
    </div>
    <div class="chat-box-history-link">
        <a href="#" onclick="clearChatHistory(); return false;" style="text-decoration:none; color:var(--link-color);"><?php echo $t['clear_chat_history']; ?></a>
    </div>
    <div class="chat-box-body" id="chatBoxBody">
        <!-- Mesajlar buraya gelecek -->
    </div>
    <div class="chat-box-input">
        <button type="button" class="attach-btn" id="dmAttachBtn" onclick="$('#dmFileInput').click();" title="<?php echo htmlspecialchars($t['attach_file']); ?>">+</button>
        <input type="file" id="dmFileInput" style="display:none;">
        <input type="text" id="chatInput" placeholder="<?php echo $t['type_message']; ?>">
        <input type="hidden" id="activeChatUserId" value="0">
    </div>
</div>

<script>
    let chatUpdateInterval;
    let pendingShareText = '';
    const CHAT_I18N = {
        friendList: <?php echo json_encode($t['friend_list']); ?>,
        friendRequests: <?php echo json_encode($t['friend_requests_label']); ?>,
        noFriendsYet: <?php echo json_encode($t['no_friends_yet']); ?>,
        accept: <?php echo json_encode($t['accept']); ?>,
        decline: <?php echo json_encode($t['decline']); ?>,
        confirmDeleteMessage: <?php echo json_encode($t['confirm_delete_message']); ?>,
        confirmClearHistory: <?php echo json_encode($t['confirm_delete_message']); ?>,
        noMessagesYet: <?php echo json_encode($t['no_messages_yet']); ?>,
        deleteLabel: <?php echo json_encode($t['delete']); ?>,
        fileTooLarge: <?php echo json_encode($t['file_too_large']); ?>,
        fileTypeNotAllowed: <?php echo json_encode($t['file_type_not_allowed']); ?>
    };
    const CHAT_CURRENT_USER_ID = <?php echo (int) $_SESSION['user_id']; ?>;
    const MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024;

    function escapeHtml(str) {
        return $('<div>').text(str == null ? '' : str).html();
    }

    function toggleChatSidebar() {
        $('#chatUserList').slideToggle('fast');
        if ($('#chatUserList').is(':visible')) {
            loadChatSidebar();
        }
    }

    function loadChatSidebar() {
        $.post('chat_api.php', {
            action: 'get_pending_requests'
        }, function(requests) {
            let html = '';

            if (requests.length > 0) {
                html += '<div style="padding: 5px; color: gray; font-weight: bold; background:#f2f2f2;">' + escapeHtml(CHAT_I18N.friendRequests) + ' (' + requests.length + ')</div>';
                requests.forEach(function(req) {
                    html += `
                        <div class="chat-user-item friend-request-item">
                            <img src="images/${escapeHtml(req.profile_pic || 'default_avatar.gif')}">
                            <span>${escapeHtml(req.username)}</span>
                            <button type="button" class="friend-req-btn accept" onclick="respondFriendRequest(${req.id}, true)">${escapeHtml(CHAT_I18N.accept)}</button>
                            <button type="button" class="friend-req-btn decline" onclick="respondFriendRequest(${req.id}, false)">${escapeHtml(CHAT_I18N.decline)}</button>
                        </div>
                    `;
                });
            }

            $('#friendRequestBadge').text(requests.length).toggle(requests.length > 0);

            $.post('chat_api.php', {
                action: 'get_users'
            }, function(users) {
                html += '<div style="padding: 5px; color: gray; font-weight: bold; background:#f2f2f2;">' + escapeHtml(CHAT_I18N.friendList) + '</div>';

                if (users.length > 0) {
                    users.forEach(function(user) {
                        html += `
                            <div class="chat-user-item" onclick="openChatBox(${user.id}, '${escapeHtml(user.username).replace(/'/g, "\\'")}')">
                                <img src="images/${escapeHtml(user.profile_pic || 'default_avatar.gif')}">
                                <span>${escapeHtml(user.username)}</span>
                            </div>
                        `;
                    });
                } else {
                    html += `<div style="padding:10px; text-align:center; color:gray; font-size:10px;">${escapeHtml(CHAT_I18N.noFriendsYet)}</div>`;
                }

                $('#chatUserList').html(html);
            }, 'json');
        }, 'json');
    }

    function loadUnreadCount() {
        $.post('chat_api.php', {
            action: 'get_unread_count'
        }, function(res) {
            $('#unreadMsgBadge').text(res.total).toggle(res.total > 0);
        }, 'json');
    }

    function respondFriendRequest(fromId, accept) {
        $.post('chat_api.php', {
            action: 'respond_friend_request',
            from_id: fromId,
            accept: accept ? '1' : '0'
        }, function() {
            loadChatSidebar();
        });
    }

    function openChatBox(userId, username) {
        $('#activeChatUserId').val(userId);
        $('#chatBoxName').text(username);
        $('#chatBox').show();
        if (pendingShareText) {
            $('#chatInput').val(pendingShareText);
            pendingShareText = '';
        }
        $('#chatInput').focus();
        loadMessages();

        clearInterval(chatUpdateInterval);
        chatUpdateInterval = setInterval(loadMessages, 3000);
    }

    function closeChatBox() {
        $('#chatBox').hide();
        clearInterval(chatUpdateInterval);
    }

    function deleteMessage(messageId) {
        if (!confirm(CHAT_I18N.confirmDeleteMessage)) return;
        $.post('chat_api.php', {
            action: 'delete_message',
            message_id: messageId
        }, function() {
            loadMessages();
        });
    }

    function uploadDmFile(file) {
        let userId = $('#activeChatUserId').val();
        if (!file || userId == 0) return;
        if (file.size > MAX_ATTACHMENT_BYTES) {
            alert(CHAT_I18N.fileTooLarge);
            return;
        }

        let fd = new FormData();
        fd.append('action', 'send_file');
        fd.append('receiver_id', userId);
        fd.append('attachment', file);

        let $btn = $('#dmAttachBtn');
        $btn.prop('disabled', true);
        $.ajax({
            url: 'chat_api.php',
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function(res) {
                res = res.trim();
                if (res === 'success') {
                    loadMessages();
                } else if (res === 'invalid_type') {
                    alert(CHAT_I18N.fileTypeNotAllowed);
                } else if (res === 'too_large') {
                    alert(CHAT_I18N.fileTooLarge);
                }
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    }

    $(document).on('change', '#dmFileInput', function() {
        uploadDmFile(this.files[0]);
        this.value = '';
    });

    function clearChatHistory() {
        let userId = $('#activeChatUserId').val();
        if (userId == 0) return;
        if (!confirm(CHAT_I18N.confirmClearHistory)) return;
        $.post('chat_api.php', {
            action: 'clear_history',
            chat_with: userId
        }, function() {
            loadMessages();
        });
    }

    function loadMessages() {
        let userId = $('#activeChatUserId').val();
        if (userId == 0) return;

        $.post('chat_api.php', {
            action: 'get_messages',
            chat_with: userId
        }, function(messages) {
            let html = '';

            if (messages.length === 0) {
                html = `<div style="text-align:center; color:gray; padding:15px 0;">${escapeHtml(CHAT_I18N.noMessagesYet)}</div>`;
            }

            messages.forEach(function(msg) {
                let isMe = msg.sender_id == CHAT_CURRENT_USER_ID;
                let senderName = isMe ? "Me" : $('#chatBoxName').text();
                let msgClass = isMe ? "me" : "them";
                let deleteBtn = isMe ? `<button type="button" class="msg-delete-btn" onclick="deleteMessage(${msg.id})" title="Delete">[${escapeHtml(CHAT_I18N.deleteLabel)}]</button>` : '';
                let bodyContent = '';
                if (msg.message) bodyContent += `<div>${escapeHtml(msg.message)}</div>`;
                if (msg.attachment) bodyContent += `<a href="${escapeHtml(msg.attachment)}" target="_blank" rel="noopener" class="chat-attachment">📎 ${escapeHtml(msg.attachment_name || 'file')}</a>`;

                html += `
                    <div class="chat-message ${msgClass}">
                        <div class="chat-message-sender">${escapeHtml(senderName)}</div>
                        <div class="msg-row-wrap">
                            ${bodyContent}
                            ${deleteBtn}
                        </div>
                    </div>
                `;
            });

            $('#chatBoxBody').html(html);
            $('#chatBoxBody').scrollTop($('#chatBoxBody')[0].scrollHeight);
            loadUnreadCount();
        }, 'json');
    }

    $(document).on('keypress', '#chatInput', function(e) {
        if (e.which == 13) {
            let message = $(this).val();
            let receiverId = $('#activeChatUserId').val();

            if (message.trim() != '') {
                $.post('chat_api.php', {
                    action: 'send_message',
                    receiver_id: receiverId,
                    message: message
                }, function(res) {
                    if (res.trim() == 'success') {
                        $('#chatInput').val('');
                        loadMessages();
                    }
                });
            }
        }
    });

    $(document).ready(function() {
        loadChatSidebar();
        loadUnreadCount();
        setInterval(loadChatSidebar, 15000);
        setInterval(loadUnreadCount, 5000);

        // Profilden "Send Message" / "Instant Message" ile gelindiyse sohbeti otomatik aç
        const params = new URLSearchParams(window.location.search);
        const chatWith = params.get('chat_with');
        const chatName = params.get('chat_name');
        const shareProfile = params.get('share_profile');

        if (chatWith && chatName) {
            openChatBox(parseInt(chatWith, 10), chatName);
        } else if (shareProfile) {
            pendingShareText = window.location.origin + window.location.pathname.replace(/[^\/]+$/, '') + 'profile.php?id=' + shareProfile;
            $('#chatUserList').slideDown('fast');
            loadChatSidebar();
        }
    });
</script>
