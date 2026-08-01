<?php
// admin/api-chat-detail.php
require_once __DIR__ . '/../includes/admin-check.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($_GET['id'])) exit('ID không hợp lệ');

$db = new Database();
$conn = $db->getConnection();
$id = (int)$_GET['id'];

$stmtMsgs = $conn->prepare("SELECT * FROM chat_messages WHERE conversation_id = :id ORDER BY id ASC");
$stmtMsgs->execute([':id' => $id]);
$msgs = $stmtMsgs->fetchAll(PDO::FETCH_ASSOC);

if (empty($msgs)) {
    echo '<div class="alert alert-warning">Không có tin nhắn nào trong hội thoại này.</div>';
    exit;
}

echo '<div class="chat-history-container">';
foreach ($msgs as $m) {
    if ($m['sender'] === 'user') {
        echo '<div class="d-flex mb-3 justify-content-end">';
        echo '<div class="bg-primary text-white p-3 rounded shadow-sm text-end" style="max-width: 80%;">' . htmlspecialchars($m['message']) . '</div>';
        echo '<div class="ms-2"><i class="bi bi-person-circle fs-3 text-secondary"></i></div>';
        echo '</div>';
    } elseif ($m['sender'] === 'assistant') {
        $safeMessage = htmlspecialchars($m['message'], ENT_QUOTES, 'UTF-8');
        $htmlMsg = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $safeMessage);
        $htmlMsg = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $htmlMsg);
        $htmlMsg = nl2br($htmlMsg);
        
        echo '<div class="d-flex mb-3">';
        echo '<div class="me-2"><i class="bi bi-robot fs-3 text-success"></i></div>';
        echo '<div class="bg-white p-3 rounded shadow-sm border" style="max-width: 80%;">' . $htmlMsg . '</div>';
        echo '</div>';
    }
}
echo '</div>';
