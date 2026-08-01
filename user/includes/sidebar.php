<?php
$sidebar_page = basename($_SERVER['PHP_SELF'] ?? '');
$sidebar_active = match ($sidebar_page) {
    'dashboard.php' => 'dashboard',
    'profile.php' => 'profile',
    'meals.php', 'add-meal.php' => 'meals',
    'weight-logs.php' => 'weight',
    'chatbot.php', 'chat-history.php' => 'chatbot',
    default => '',
};

$sidebar_items = [
    ['key' => 'dashboard', 'url' => '/user/dashboard.php', 'icon' => 'bi-speedometer2', 'label' => 'Dashboard'],
    ['key' => 'profile', 'url' => '/user/profile.php', 'icon' => 'bi-person-circle', 'label' => 'Hồ sơ sức khỏe'],
    ['key' => 'meals', 'url' => '/user/meals.php', 'icon' => 'bi-journal-text', 'label' => 'Nhật ký bữa ăn'],
    ['key' => 'weight', 'url' => '/user/weight-logs.php', 'icon' => 'bi-graph-up', 'label' => 'Cân nặng'],
    ['key' => 'chatbot', 'url' => '/user/chatbot.php', 'icon' => 'bi-robot', 'label' => 'Trợ lý dinh dưỡng'],
];
?>
<aside class="user-global-sidebar" aria-label="Điều hướng chức năng người dùng">
    <nav class="list-group user-sidebar-nav" aria-label="Chức năng sức khỏe">
        <?php foreach ($sidebar_items as $sidebar_item): ?>
            <?php $is_active = $sidebar_active === $sidebar_item['key']; ?>
            <a href="<?php echo BASE_URL . $sidebar_item['url']; ?>"
               class="list-group-item list-group-item-action<?php echo $is_active ? ' active' : ''; ?>"
               <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
                <i class="bi <?php echo $sidebar_item['icon']; ?>" aria-hidden="true"></i>
                <span><?php echo htmlspecialchars($sidebar_item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <section class="user-sidebar-support" aria-labelledby="user-sidebar-support-title">
        <div class="small text-muted mb-2">Cần hỗ trợ?</div>
        <h2 class="h6 fw-bold mb-3" id="user-sidebar-support-title">Hỏi trợ lý về số liệu dinh dưỡng.</h2>
        <a href="<?php echo BASE_URL; ?>/user/chatbot.php" class="btn btn-success w-100">Hỏi trợ lý</a>
    </section>
</aside>