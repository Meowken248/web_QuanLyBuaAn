<?php
// user/notifications.php
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$database = new Database();
$conn = $database->getConnection();
$user_id = (int)$_SESSION['user_id'];
$is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

$filter = $_GET['filter'] ?? 'all';
if (!in_array($filter, ['all', 'unread', 'read'])) {
    $filter = 'all';
}

// Xử lý các thao tác POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('Lỗi CSRF token');
    }
    
    if ($_POST['action'] === 'mark_all_read') {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $user_id]);
        $_SESSION['success'] = 'Đã đánh dấu tất cả thông báo là đã đọc.';
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':id' => (int)$_POST['id'], ':user_id' => $user_id]);
        $_SESSION['success'] = 'Đã xóa thông báo.';
    } elseif ($_POST['action'] === 'delete_all_read') {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = :user_id AND is_read = 1");
        $stmt->execute([':user_id' => $user_id]);
        $_SESSION['success'] = 'Đã xóa tất cả thông báo đã đọc.';
    } elseif ($_POST['action'] === 'delete_all') {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $user_id]);
        $_SESSION['success'] = 'Đã xóa toàn bộ thông báo.';
    }
    redirect('/user/notifications.php' . ($filter !== 'all' ? '?filter=' . $filter : ''));
}

// Nếu có tham số ?read=id trên URL
if (isset($_GET['read'])) {
    $id = (int)$_GET['read'];
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $id, ':user_id' => $user_id]);
    redirect('/user/notifications.php' . ($filter !== 'all' ? '?filter=' . $filter : ''));
}

// Thống kê số lượng theo tab
$stmtAll = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id");
$stmtAll->execute([':user_id' => $user_id]);
$count_all = (int)$stmtAll->fetchColumn();

$stmtUnread = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0");
$stmtUnread->execute([':user_id' => $user_id]);
$count_unread = (int)$stmtUnread->fetchColumn();

$stmtRead = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 1");
$stmtRead->execute([':user_id' => $user_id]);
$count_read = (int)$stmtRead->fetchColumn();

// Lấy danh sách thông báo theo filter (phân trang)
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$whereSql = "WHERE user_id = :user_id";
if ($filter === 'unread') {
    $whereSql .= " AND is_read = 0";
    $total_notifs = $count_unread;
} elseif ($filter === 'read') {
    $whereSql .= " AND is_read = 1";
    $total_notifs = $count_read;
} else {
    $total_notifs = $count_all;
}

$total_pages = $total_notifs > 0 ? ceil($total_notifs / $limit) : 1;

$stmt = $conn->prepare("SELECT * FROM notifications {$whereSql} ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Thông báo của bạn';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-bell-fill text-primary me-2"></i>Thông báo của bạn</h2>
            <p class="text-muted mb-0">Quản lý và cập nhật thông báo hệ thống và tin nhắn hỗ trợ</p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($count_unread > 0): ?>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-check-all me-1"></i>Đánh dấu đã đọc tất cả
                    </button>
                </form>
            <?php endif; ?>
            <?php if ($count_all > 0): ?>
                <form method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa tất cả thông báo?');">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="delete_all">
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-trash me-1"></i>Xóa tất cả
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Tabs Bộ lọc -->
    <ul class="nav nav-pills mb-3 border-bottom pb-2">
        <li class="nav-item">
            <a class="nav-link rounded-pill <?php echo $filter === 'all' ? 'active bg-success text-white' : 'text-dark'; ?>" href="?filter=all">
                Tất cả <span class="badge <?php echo $filter === 'all' ? 'bg-light text-success' : 'bg-secondary'; ?> ms-1"><?php echo $count_all; ?></span>
            </a>
        </li>
        <li class="nav-item ms-2">
            <a class="nav-link rounded-pill <?php echo $filter === 'unread' ? 'active bg-success text-white' : 'text-dark'; ?>" href="?filter=unread">
                Chưa đọc <?php if ($count_unread > 0): ?><span class="badge bg-danger ms-1"><?php echo $count_unread; ?></span><?php endif; ?>
            </a>
        </li>
        <li class="nav-item ms-2">
            <a class="nav-link rounded-pill <?php echo $filter === 'read' ? 'active bg-success text-white' : 'text-dark'; ?>" href="?filter=read">
                Đã đọc <span class="badge bg-light text-dark border ms-1"><?php echo $count_read; ?></span>
            </a>
        </li>
    </ul>

    <div class="card glass-card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <?php if (count($notifications) > 0): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($notifications as $n): ?>
                        <?php 
                            $clean_msg = preg_replace('/^(\[UID:\d+\]|\[MID:\d+\])\s*/', '', $n['message']);
                            $chat_user_id = null;
                            if (preg_match('/\[UID:(\d+)\]/', $n['message'], $matches)) {
                                $chat_user_id = (int)$matches[1];
                            }
                            $contact_msg_id = null;
                            if (preg_match('/\[MID:(\d+)\]/', $n['message'], $mMatches)) {
                                $contact_msg_id = (int)$mMatches[1];
                            }
                            $is_chat_notif = str_starts_with($n['title'], '💬 Tin nhắn') || str_starts_with($n['title'], '💬 Phản hồi');
                            $is_contact_notif = str_starts_with($n['title'], '📩 Thư liên hệ') || str_contains($n['title'], 'liên hệ');
                        ?>
                        <div id="notif-<?php echo $n['id']; ?>" class="list-group-item py-3 <?php echo $n['is_read'] ? '' : 'notif-unread-glass'; ?>" style="border-bottom: 1px solid rgba(0,0,0,0.05);">
                            <div class="d-flex w-100 justify-content-between align-items-start mb-1">
                                <h5 class="mb-1 fw-bold text-dark">
                                    <?php 
                                        $icon = 'bi-info-circle text-info';
                                        if ($n['type'] == 'success') $icon = 'bi-check-circle text-success';
                                        if ($n['type'] == 'warning') $icon = 'bi-exclamation-triangle text-warning';
                                        if ($n['type'] == 'danger') $icon = 'bi-x-circle text-danger';
                                        if ($is_chat_notif) $icon = 'bi-chat-dots-fill text-success';
                                        if ($is_contact_notif) $icon = 'bi-envelope-fill text-primary';
                                    ?>
                                    <i class="bi <?php echo $icon; ?> me-2"></i>
                                    <?php echo htmlspecialchars($n['title']); ?>
                                </h5>
                                <small class="text-muted"><i class="bi bi-clock me-1"></i><?php echo date('d/m/Y H:i', strtotime($n['created_at'])); ?></small>
                            </div>
                            <p class="mb-2 ms-4 text-secondary" style="font-size: 0.95rem;"><?php echo nl2br(htmlspecialchars($clean_msg)); ?></p>
                            <div class="d-flex justify-content-end align-items-center gap-2 mt-2">
                                <?php if ($is_admin && $chat_user_id): ?>
                                    <a href="<?php echo BASE_URL; ?>/admin/support-chats.php?user_id=<?php echo $chat_user_id; ?>" class="btn btn-sm btn-success">
                                        <i class="bi bi-chat-dots me-1"></i>Mở hội thoại
                                    </a>
                                <?php elseif ($is_admin && $contact_msg_id): ?>
                                    <a href="<?php echo BASE_URL; ?>/admin/contact-message-view.php?id=<?php echo $contact_msg_id; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-envelope-open me-1"></i>Xem thư liên hệ
                                    </a>
                                <?php elseif (!$is_admin && str_starts_with($n['title'], '💬 Phản hồi')): ?>
                                    <button type="button" class="btn btn-sm btn-success" onclick="var w=document.getElementById('chatbot-window'); if(w){w.classList.remove('d-none');} var at=document.getElementById('admin-tab'); if(at){at.click();}">
                                        <i class="bi bi-chat-dots me-1"></i>Mở chat hỗ trợ
                                    </button>
                                <?php endif; ?>

                                <?php if (!$n['is_read']): ?>
                                    <a href="?read=<?php echo $n['id']; ?>&filter=<?php echo $filter; ?>" class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-check2 me-1"></i>Đánh dấu đã đọc
                                    </a>
                                <?php endif; ?>

                                <form method="POST" class="d-inline" onsubmit="return confirm('Xóa thông báo này?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $n['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa thông báo"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-bell-slash fs-1 d-block mb-3 text-secondary opacity-50"></i>
                    <h5>Không có thông báo nào<?php echo $filter === 'unread' ? ' chưa đọc' : ($filter === 'read' ? ' đã đọc' : ''); ?>.</h5>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Phân trang -->
    <?php if ($total_pages > 1): ?>
        <nav class="mt-4" aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&filter=<?php echo $filter; ?>">Trước</a>
                </li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&filter=<?php echo $filter; ?>">Tiếp</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
