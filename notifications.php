<?php
// in file: htdocs/notifications.php
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/core/database.php';

require_login();

$user_id = get_current_user_id();

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

$page_title = 'All Notifications';
include __DIR__ . '/app/templates/header.php';
?>

<h1>All Notifications</h1>

<ul class="notification-list-page">
    <?php if (empty($notifications)): ?>
        <li>You have no notifications.</li>
    <?php else: ?>
        <?php foreach ($notifications as $notif): ?>
            <?php 
                $link = $notif['request_id'] ? '/requests/view.php?id=' . $notif['request_id'] : '#';
                $icon_class = $notif['is_read'] ? 'fa-bell' : 'fa-bell unread';
            ?>
            <li class="<?= $notif['is_read'] ? '' : 'unread' ?>" data-notification-id="<?= $notif['id'] ?>">
                <a href="<?= $link ?>" class="notification-link">
                    <i class="fas <?= $icon_class ?> notification-icon"></i>
                    <div class="notification-content">
                        <div class="message"><?= htmlspecialchars($notif['message']) ?></div>
                        <span class="time"><?= date('M d, Y, g:i a', strtotime($notif['created_at'])) ?></span>
                    </div>
                </a>
            </li>
        <?php endforeach; ?>
    <?php endif; ?>
</ul>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.notification-list-page li').forEach(item => {
        item.addEventListener('click', function() {
            const notificationId = this.dataset.notificationId;
            if (notificationId) {
                // Mark notification as read via API
                fetch('/api/mark_notifications_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ notification_ids: [notificationId] })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Notification marked as read:', notificationId);
                        // Optionally update UI immediately
                        this.classList.remove('unread');
                        this.querySelector('.notification-icon').classList.remove('unread');
                    } else {
                        console.error('Failed to mark notification as read:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error marking notification as read:', error);
                });
            }

            // Navigate to the link
            const link = this.querySelector('.notification-link');
            if (link && link.href && link.href !== '#') {
                window.location.href = link.href;
            }
        });
    });
});
</script>

<?php include __DIR__ . '/app/templates/footer.php'; ?>