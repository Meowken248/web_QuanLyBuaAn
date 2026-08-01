<?php
// admin/chat-logs.php
require_once __DIR__ . '/../includes/admin-check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$database = new Database();
$conn = $database->getConnection();

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
        redirect('/admin/chat-logs.php');
    }

    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
        $_SESSION['error'] = 'Hội thoại không hợp lệ.';
        redirect('/admin/chat-logs.php');
    }

    $stmt = $conn->prepare("DELETE FROM chat_conversations WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $_SESSION['success'] = $stmt->rowCount() > 0
        ? 'Đã xóa hội thoại thành công.'
        : 'Hội thoại không tồn tại hoặc đã được xóa.';
    redirect('/admin/chat-logs.php');
}

$stmtTotal = $conn->query("SELECT COUNT(*) FROM chat_conversations");
$total_chats = $stmtTotal->fetchColumn();
$total_pages = ceil($total_chats / $limit);

$stmt = $conn->prepare("
    SELECT c.*, u.full_name, u.email 
    FROM chat_conversations c 
    JOIN users u ON c.user_id = u.id 
    ORDER BY c.updated_at DESC 
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$chats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$current_page = 'chat-logs.php';
$page_title = 'Lịch sử Trò chuyện AI';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-3 col-lg-2">
            <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
        </div>
        
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold mb-0">Quản lý Lịch sử Chatbot AI</h2>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Người dùng</th>
                                    <th>Chủ đề cuộc trò chuyện</th>
                                    <th>Cập nhật lúc</th>
                                    <th>Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($chats as $chat): ?>
                                <tr>
                                    <td>#<?php echo $chat['id']; ?></td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($chat['full_name']); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($chat['email']); ?></div>
                                    </td>
                                    <td>
                                        <span class="text-truncate d-inline-block" style="max-width: 300px;" title="<?php echo htmlspecialchars($chat['title']); ?>">
                                            <?php echo htmlspecialchars($chat['title']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($chat['updated_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info text-white" onclick="viewChat(<?php echo $chat['id']; ?>)">Xem</button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa hội thoại này không?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo (int)$chat['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Xóa</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($chats)): ?>
                                    <tr><td colspan="5" class="text-center py-4 text-muted">Chưa có dữ liệu trò chuyện nào.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">Trước</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>">Tiếp</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal xem chat -->
<div class="modal fade" id="chatModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Chi tiết cuộc trò chuyện</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light" id="chatContent">
                <div class="text-center"><div class="spinner-border text-primary" role="status"></div></div>
            </div>
        </div>
    </div>
</div>

<script>
function viewChat(id) {
    var modal = new bootstrap.Modal(document.getElementById('chatModal'));
    modal.show();
    
    // We can fetch via an api or just write a small inline block since this is admin
    fetch('<?php echo BASE_URL; ?>/admin/api-chat-detail.php?id=' + id)
        .then(response => response.text())
        .then(html => {
            document.getElementById('chatContent').innerHTML = html;
        })
        .catch(err => {
            document.getElementById('chatContent').innerHTML = '<div class="alert alert-danger">Lỗi tải dữ liệu.</div>';
        });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
