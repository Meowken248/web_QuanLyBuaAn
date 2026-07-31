<?php
// foods.php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/models/FoodModel.php';

$foodModel = new FoodModel();
$categories = $foodModel->getCategories();

// Xử lý query params
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 12;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$category_id = !empty($_GET['category']) ? (int)$_GET['category'] : null;

$foods = $foodModel->getFoods($limit, $offset, $search, $category_id);
$total_foods = $foodModel->getTotalFoods($search, $category_id);
$total_pages = ceil($total_foods / $limit);

$page_title = 'Thư viện món ăn';
require_once __DIR__ . '/includes/header.php';
?>

<div class="bg-success text-white py-4 mb-4">
    <div class="container text-center">
        <h2 class="fw-bold">Thư viện món ăn</h2>
        <p class="mb-0">Khám phá hàng nghìn món ăn Việt Nam với đầy đủ thông tin dinh dưỡng.</p>
    </div>
</div>

<div class="container mb-5">
    <!-- Bộ lọc và tìm kiếm -->
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body">
            <form method="GET" action="foods.php" class="row g-3">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control" placeholder="Tìm kiếm món ăn..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-4">
                    <select name="category" class="form-select">
                        <option value="">Tất cả danh mục</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo ($category_id == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-success w-100"><i class="bi bi-search me-2"></i>Tìm kiếm</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Danh sách món ăn -->
    <?php if (empty($foods)): ?>
        <div class="text-center py-5">
            <i class="bi bi-basket text-muted" style="font-size: 4rem;"></i>
            <h4 class="mt-3 text-muted">Không tìm thấy món ăn nào</h4>
            <p>Hãy thử thay đổi từ khóa hoặc danh mục tìm kiếm.</p>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-3 row-cols-lg-4 g-4 mb-4">
            <?php foreach ($foods as $food): ?>
                <div class="col">
                    <div class="card h-100 shadow-sm card-hover border-0">
                        <!-- Hình ảnh mặc định nếu không có ảnh -->
                        <?php 
                        $img_src = !empty($food['image']) ? BASE_URL . '/assets/uploads/foods/' . htmlspecialchars($food['image']) : 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80';
                        ?>
                        <img src="<?php echo $img_src; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($food['name']); ?>" style="height: 180px; object-fit: cover;">
                        
                        <div class="card-body">
                            <span class="badge bg-light text-success border border-success mb-2"><?php echo htmlspecialchars($food['category_name'] ?? 'Chưa phân loại'); ?></span>
                            <h5 class="card-title fw-bold text-truncate" title="<?php echo htmlspecialchars($food['name']); ?>">
                                <?php echo htmlspecialchars($food['name']); ?>
                            </h5>
                            
                            <div class="d-flex justify-content-between mt-3 text-muted small">
                                <div><i class="bi bi-fire text-danger me-1"></i><?php echo $food['calories']; ?> kcal</div>
                                <div><i class="bi bi-egg text-warning me-1"></i><?php echo $food['protein']; ?>g P</div>
                            </div>
                        </div>
                        <div class="card-footer bg-white border-top-0 pt-0">
                            <!-- Nút mở trang chi tiết -->
                            <a href="<?php echo BASE_URL; ?>/food-detail.php?id=<?php echo $food['id']; ?>" class="btn btn-outline-success btn-sm w-100 fw-bold">
                                Xem chi tiết
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Phân trang -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_id; ?>">Trước</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_id; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_id; ?>">Sau</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
