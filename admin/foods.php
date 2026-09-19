<?php
// admin/foods.php
require_once __DIR__ . '/../includes/admin-check.php';
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

require_once __DIR__ . '/../includes/functions.php';

// Tải file mẫu Excel món ăn (đầy đủ các trường: Mô tả ngắn, Nguyên liệu, Cách làm, v.v.)
if (isset($_GET['action']) && $_GET['action'] === 'sample_excel') {
    require_once __DIR__ . '/../includes/SimpleXLSXGen.php';
    $data = [
        ['STT', 'Tên Món Ăn', 'Tên Danh Mục', 'Khẩu Phần', 'Đơn Vị', 'Calories', 'Protein (g)', 'Carbs (g)', 'Fat (g)', 'Chất Xơ (g)', 'Mô Tả Ngắn', 'Nguyên Liệu', 'Cách Làm', 'Trạng Thái'],
        [
            1, 
            'Phở bò Hà Nội', 
            'Món Nước & Canh', 
            1, 
            'bát', 
            450, 
            25, 
            60, 
            12, 
            3, 
            'Phở bò tái nạm truyền thống nước dùng thanh ngọt đậm đà', 
            'Bánh phở, thịt bò tái, nạm bò, hành tây, ngò gai, gừng, hồi, quế, thảo quả, nước hầm xương ống', 
            '1. Chần bánh phở qua nước sôi xếp vào tô.\n2. Cho thịt bò tái và nạm thái mỏng, rắc hành ngò lên trên.\n3. Chan nước dùng sôi sùng sục và ăn kèm rau thơm, chanh ớt.', 
            'Hoạt động'
        ],
        [
            2, 
            'Cơm gà nướng mật ong', 
            'Món Cơm', 
            1, 
            'đĩa', 
            580, 
            35, 
            70, 
            15, 
            2, 
            'Cơm gạo tám dẻo thơm kèm đùi gà ướp sốt mật ong nướng vàng giòn', 
            'Gạo tám, đùi gà góc tư, mật ong nguyên chất, dầu hào, nước tương, tỏi, ớt, dưa leo, cà chua', 
            '1. Ướp gà với mật ong, dầu hào, tỏi băm 30 phút.\n2. Nướng chín vàng hai mặt ở nhiệt độ 180°C.\n3. Ăn kèm cơm nóng, dưa leo và nước sốt.', 
            'Hoạt động'
        ],
        [
            3, 
            'Salad ức gà sốt mè rang', 
            'Món Salad & Healthy', 
            1, 
            'đĩa', 
            320, 
            32, 
            14, 
            8, 
            4, 
            'Salad thanh mát giàu đạm thích hợp cho thực đơn ăn kiêng lành mạnh', 
            'Ức gà phi lê, xà lách romaine, cà chua bi, dưa leo baby, mè rang, sốt mè rang Kewpie', 
            '1. Luộc chín ức gà rồi xé sợi nhỏ vừa ăn.\n2. Rửa sạch rau củ, cà chua bi bổ đôi, dưa leo cắt lát.\n3. Cho tất cả vào tô, rưới sốt mè rang và trộn đều trước khi dùng.', 
            'Hoạt động'
        ]
    ];
    $xlsx = Shuchkin\SimpleXLSXGen::fromArray($data);
    $xlsx->downloadAs("Mau_DanhSach_MonAn.xlsx");
    exit;
}

// Xử lý Import Excel món ăn
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_excel') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('danger', 'Yêu cầu không hợp lệ.');
        redirect('/admin/foods.php');
    }

    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['excel_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext === 'xlsx') {
            require_once __DIR__ . '/../includes/SimpleXLSX.php';
            if ($xlsx = Shuchkin\SimpleXLSX::parse($file['tmp_name'])) {
                $successCount = 0;
                $skipCount = 0;

                // Lấy danh sách danh mục để tra cứu
                $catStmt = $conn->query("SELECT id, name FROM food_categories");
                $catMap = [];
                while ($row = $catStmt->fetch(PDO::FETCH_ASSOC)) {
                    $catMap[mb_strtolower(trim($row['name']), 'UTF-8')] = (int)$row['id'];
                }

                $defaultCatId = !empty($catMap) ? reset($catMap) : null;

                $checkDupStmt = $conn->prepare("SELECT id FROM foods WHERE category_id = :category_id AND LOWER(TRIM(name)) = LOWER(TRIM(:name)) LIMIT 1");
                $insertStmt = $conn->prepare("
                    INSERT INTO foods (name, slug, category_id, serving_size, serving_unit, calories, protein, carbs, fat, fiber, description, ingredients, instructions, status, created_by)
                    VALUES (:name, :slug, :category_id, :serving_size, :serving_unit, :calories, :protein, :carbs, :fat, :fiber, :description, :ingredients, :instructions, :status, :created_by)
                ");

                /** @var array<int, array<int, mixed>> $excelRows */
                $excelRows = (array)$xlsx->rows();

                // Nhận diện cột theo tiêu đề (nếu có)
                $headerMap = [];
                if (isset($excelRows[0])) {
                    foreach ((array)$excelRows[0] as $colIdx => $headerVal) {
                        $cleanHeader = mb_strtolower(trim((string)$headerVal), 'UTF-8');
                        if (str_contains($cleanHeader, 'tên món') || str_contains($cleanHeader, 'ten mon')) {
                            $headerMap['name'] = $colIdx;
                        } elseif (str_contains($cleanHeader, 'danh mục') || str_contains($cleanHeader, 'danh muc')) {
                            $headerMap['category'] = $colIdx;
                        } elseif (str_contains($cleanHeader, 'khẩu phần') || str_contains($cleanHeader, 'khau phan')) {
                            $headerMap['serving_size'] = $colIdx;
                        } elseif (str_contains($cleanHeader, 'đơn vị') || str_contains($cleanHeader, 'don vi')) {
                            $headerMap['serving_unit'] = $colIdx;
                        } elseif (str_contains($cleanHeader, 'calorie') || str_contains($cleanHeader, 'calo')) {
                            $headerMap['calories'] = $colIdx;
                        } elseif (str_contains($cleanHeader, 'protein') || str_contains($cleanHeader, 'đạm')) {
                            $headerMap['protein'] = $colIdx;
                        } elseif (str_contains($cleanHeader, 'carb')) {
                            $headerMap['carbs'] = $colIdx;
                        } elseif (str_contains($cleanHeader, 'fat') || str_contains($cleanHeader, 'béo')) {
                            $headerMap['fat'] = $colIdx;
                        } elseif (str_contains($cleanHeader, 'chất xơ') || str_contains($cleanHeader, 'fiber') || str_contains($cleanHeader, 'chat xo')) {
                            $headerMap['fiber'] = $colIdx;
                        } elseif (str_contains($cleanHeader, 'nguyên liệu') || str_contains($cleanHeader, 'nguyen lieu')) {
                            $headerMap['ingredients'] = $colIdx;
                        } elseif (str_contains($cleanHeader, 'cách làm') || str_contains($cleanHeader, 'cach lam') || str_contains($cleanHeader, 'hướng dẫn')) {
                            $headerMap['instructions'] = $colIdx;
                        } elseif (str_contains($cleanHeader, 'trạng thái') || str_contains($cleanHeader, 'trang thai')) {
                            $headerMap['status'] = $colIdx;
                        } elseif (str_contains($cleanHeader, 'mô tả') || str_contains($cleanHeader, 'mo ta')) {
                            $headerMap['description'] = $colIdx;
                        }
                    }
                }

                foreach ($excelRows as $i => $row) {
                    /** @var array<int, mixed> $row */
                    if ($i === 0) continue; // Bỏ qua tiêu đề
                    $row = (array)$row;
                    if (empty($row)) continue;

                    // Lấy tên món
                    $name = trim((string)($row[$headerMap['name'] ?? 1] ?? ''));
                    if (empty($name)) continue;

                    // Lấy danh mục
                    $catName = trim((string)($row[$headerMap['category'] ?? 2] ?? ''));
                    $catKey = mb_strtolower($catName, 'UTF-8');
                    $categoryId = $catMap[$catKey] ?? null;

                    // Nếu danh mục chưa tồn tại, tự động tạo mới
                    if (!$categoryId && !empty($catName)) {
                        $catSlug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $catName) ?: $catName), '-'));
                        if (empty($catSlug)) $catSlug = 'cat-' . time() . '-' . rand(10, 99);
                        $conn->prepare("INSERT INTO food_categories (name, slug, status) VALUES (:name, :slug, 'active')")
                             ->execute([':name' => $catName, ':slug' => $catSlug]);
                        $categoryId = (int)$conn->lastInsertId();
                        $catMap[$catKey] = $categoryId;
                    } elseif (!$categoryId) {
                        $categoryId = $defaultCatId;
                    }

                    // Kiểm tra trùng tên món trong cùng danh mục
                    if ($categoryId) {
                        $checkDupStmt->execute([':category_id' => $categoryId, ':name' => $name]);
                        if ($checkDupStmt->fetch()) {
                            $skipCount++;
                            continue;
                        }
                    }

                    $rawServingSize = $row[$headerMap['serving_size'] ?? 3] ?? null;
                    $servingSize = !empty($rawServingSize) && (float)$rawServingSize > 0 ? (float)$rawServingSize : 1;

                    $rawServingUnit = trim((string)($row[$headerMap['serving_unit'] ?? 4] ?? ''));
                    $servingUnit = !empty($rawServingUnit) ? $rawServingUnit : 'phần';

                    $protein = isset($headerMap['protein']) ? (float)($row[$headerMap['protein']] ?? 0) : (float)($row[6] ?? 0);
                    $carbs = isset($headerMap['carbs']) ? (float)($row[$headerMap['carbs']] ?? 0) : (float)($row[7] ?? 0);
                    $fat = isset($headerMap['fat']) ? (float)($row[$headerMap['fat']] ?? 0) : (float)($row[8] ?? 0);
                    $fiber = isset($headerMap['fiber']) ? (float)($row[$headerMap['fiber']] ?? 0) : (float)($row[9] ?? 0);

                    // Calories: nếu để trống hoặc bằng 0, tự động ước tính theo macros
                    $rawCal = isset($headerMap['calories']) ? $row[$headerMap['calories']] : ($row[5] ?? null);
                    $calories = !empty($rawCal) ? (float)$rawCal : 0;
                    if ($calories <= 0 && ($protein > 0 || $carbs > 0 || $fat > 0)) {
                        $calories = round($protein * 4 + $carbs * 4 + $fat * 9, 1);
                    }

                    $description = trim((string)(isset($headerMap['description']) ? ($row[$headerMap['description']] ?? '') : ($row[10] ?? '')));
                    $ingredients = trim((string)(isset($headerMap['ingredients']) ? ($row[$headerMap['ingredients']] ?? '') : ($row[11] ?? '')));
                    $instructions = trim((string)(isset($headerMap['instructions']) ? ($row[$headerMap['instructions']] ?? '') : ($row[12] ?? '')));

                    $rawStatus = trim((string)(isset($headerMap['status']) ? ($row[$headerMap['status']] ?? '') : ($row[13] ?? '')));
                    $statusLower = mb_strtolower($rawStatus, 'UTF-8');
                    $status = in_array($statusLower, ['inactive', 'không hoạt động', 'khóa', 'khoá', 'tắt', 'ẩn'], true) ? 'inactive' : 'active';

                    $slugBase = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name), '-'));
                    $slug = $slugBase . '-' . time() . '-' . rand(100, 999);

                    $insertStmt->execute([
                        ':name' => $name,
                        ':slug' => $slug,
                        ':category_id' => $categoryId,
                        ':serving_size' => $servingSize,
                        ':serving_unit' => $servingUnit,
                        ':calories' => $calories,
                        ':protein' => $protein,
                        ':carbs' => $carbs,
                        ':fat' => $fat,
                        ':fiber' => $fiber,
                        ':description' => $description,
                        ':ingredients' => $ingredients,
                        ':instructions' => $instructions,
                        ':status' => $status,
                        ':created_by' => (int)($_SESSION['user_id'] ?? 1)
                    ]);
                    $successCount++;
                }

                set_flash_message('success', "Đã nhập thành công $successCount món ăn đầy đủ thông tin." . ($skipCount > 0 ? " (Đã bỏ qua $skipCount món do trùng tên trong cùng danh mục)" : ""));
            } else {
                set_flash_message('danger', 'Lỗi đọc file Excel: ' . Shuchkin\SimpleXLSX::parseError());
            }
        } else {
            set_flash_message('danger', 'Vui lòng upload file đúng định dạng .xlsx');
        }
    } else {
        set_flash_message('danger', 'Lỗi khi upload file.');
    }
    redirect('/admin/foods.php');
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_food') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('danger', 'Yêu cầu không hợp lệ.');
    } else {
        $target_id = filter_var(
            $_POST['food_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($target_id) {
            try {
                $conn->beginTransaction();

                $foodStmt = $conn->prepare("SELECT id, name FROM foods WHERE id = :id FOR UPDATE");
                $foodStmt->execute([':id' => $target_id]);
                $food = $foodStmt->fetch(PDO::FETCH_ASSOC);

                if (!$food) {
                    $conn->rollBack();
                    set_flash_message('warning', 'Không tìm thấy món ăn để xóa.');
                } else {
                    $referenceStmt = $conn->prepare("
                        SELECT
                            (SELECT COUNT(*) FROM meal_log_items WHERE food_id = :log_food_id) +
                            (SELECT COUNT(*) FROM meal_plan_items WHERE food_id = :plan_food_id) AS total_references
                    ");
                    $referenceStmt->execute([
                        ':log_food_id' => $target_id,
                        ':plan_food_id' => $target_id
                    ]);
                    $referenceCount = (int)$referenceStmt->fetchColumn();

                    if ($referenceCount > 0) {
                        // Giữ dữ liệu lịch sử và ẩn món khỏi thư viện/người dùng.
                        $stmt = $conn->prepare("UPDATE foods SET status = 'inactive' WHERE id = :id");
                        $stmt->execute([':id' => $target_id]);
                        $conn->commit();
                        set_flash_message(
                            'success',
                            'Món “' . $food['name'] . '” đã được ẩn khỏi thư viện vì đang được dùng trong ' . $referenceCount . ' bản ghi lịch sử/thực đơn.'
                        );
                    } else {
                        $stmt = $conn->prepare("DELETE FROM foods WHERE id = :id");
                        $stmt->execute([':id' => $target_id]);
                        $conn->commit();
                        set_flash_message('success', 'Đã xóa món ăn thành công.');
                    }
                }
            } catch (Throwable $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                error_log('Không thể xóa món ăn #' . $target_id . ': ' . $e->getMessage());
                set_flash_message('danger', 'Không thể xóa món ăn. Vui lòng thử lại hoặc chuyển món sang trạng thái ẩn.');
            }
        } else {
            set_flash_message('warning', 'Món ăn cần xóa không hợp lệ.');
        }
    }
    redirect('/admin/foods.php');
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Đếm tổng số để phân trang
$total_foods = $conn->query("SELECT COUNT(id) FROM foods")->fetchColumn();
$total_pages = ceil($total_foods / $limit);

// Lấy danh sách món ăn
$stmt = $conn->prepare("
    SELECT f.*, c.name as category_name
    FROM foods f 
    LEFT JOIN food_categories c ON f.category_id = c.id
    ORDER BY f.id DESC LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$foods = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Quản lý Món ăn';
$hide_footer = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-2">
            <?php require __DIR__ . '/includes/sidebar.php'; ?>
        </div>
        <div class="col-md-10">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                <div>
                    <h3 class="fw-bold mb-0">Quản lý Món ăn</h3>
                    <p class="text-muted small mb-0">Tổng cộng: <?php echo $total_foods; ?> món ăn (10 món/trang)</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?php echo BASE_URL; ?>/admin/foods.php?action=sample_excel" class="btn btn-sm btn-outline-secondary rounded-pill">
                        <i class="bi bi-file-earmark-arrow-down me-1"></i>Tải file mẫu Excel
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-success rounded-pill" data-bs-toggle="modal" data-bs-target="#importFoodsModal">
                        <i class="bi bi-file-earmark-arrow-up me-1"></i>Nhập từ Excel
                    </button>
                    <a href="<?php echo BASE_URL; ?>/admin/food-edit.php" class="btn btn-sm btn-outline-primary rounded-pill">
                        <i class="bi bi-plus-circle me-1"></i>Thêm món mới
                    </a>
                </div>
            </div>
            <?php display_flash_message(); ?>
            
            <div class="card glass-card border-0 rounded-4 shadow-sm overflow-hidden mb-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-nowrap">
                            <thead style="background: rgba(243, 244, 246, 0.7);">
                                <tr>
                                    <th class="ps-4">ID</th>
                                    <th>Tên món ăn</th>
                                    <th>Danh mục</th>
                                    <th>Calories</th>
                                    <th>Protein/Carb/Fat</th>
                                    <th>Trạng thái</th>
                                    <th class="text-end pe-4">Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($foods as $f): ?>
                                <tr>
                                    <td><?php echo $f['id']; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($f['image'])): ?>
                                                <img src="<?php echo food_image_url($f['image']); ?>" alt="Hình ảnh" class="rounded me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="bg-light rounded d-flex align-items-center justify-content-center me-2 text-muted" style="width: 40px; height: 40px;">
                                                    <i class="bi bi-image"></i>
                                                </div>
                                            <?php endif; ?>
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#foodModal<?php echo $f['id']; ?>" class="text-decoration-none fw-bold text-dark">
                                                <?php echo htmlspecialchars($f['name']); ?>
                                            </a>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-light text-success border border-success"><?php echo htmlspecialchars($f['category_name'] ?? 'Khác'); ?></span></td>
                                    <td class="text-danger fw-bold"><?php echo $f['calories']; ?> kcal</td>
                                    <td class="text-muted small">
                                        P: <?php echo $f['protein']; ?>g | C: <?php echo $f['carbs']; ?>g | F: <?php echo $f['fat']; ?>g
                                    </td>
                                    <td>
                                        <?php if ($f['status'] === 'active'): ?>
                                            <span class="badge bg-success">Hoạt động</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Đã ẩn</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="<?php echo BASE_URL; ?>/admin/food-edit.php?id=<?php echo $f['id']; ?>" class="btn btn-sm btn-outline-primary" title="Sửa"><i class="bi bi-pencil"></i></a>
                                            <form method="POST" onsubmit="return confirm('Xóa món ăn này? Nếu món đã có trong lịch sử hoặc thực đơn, hệ thống sẽ ẩn món để bảo toàn dữ liệu.');" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                <input type="hidden" name="action" value="delete_food">
                                                <input type="hidden" name="food_id" value="<?php echo $f['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa hoặc ẩn món"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Modals for Food Details -->
            <?php foreach ($foods as $f): ?>
            <div class="modal fade" id="foodModal<?php echo $f['id']; ?>" tabindex="-1" aria-labelledby="foodModalLabel<?php echo $f['id']; ?>" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header border-0 bg-health text-white">
                            <h5 class="modal-title fw-bold" id="foodModalLabel<?php echo $f['id']; ?>">Chi tiết Món ăn: <?php echo htmlspecialchars($f['name']); ?></h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="row g-4">
                                <div class="col-md-5 text-center">
                                    <?php if (!empty($f['image'])): ?>
                                        <img src="<?php echo food_image_url($f['image']); ?>" class="img-fluid rounded shadow-sm" alt="Image" style="width: 100%; height: 250px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-light d-flex align-items-center justify-content-center rounded shadow-sm" style="width: 100%; height: 250px;">
                                            <i class="bi bi-image text-muted fs-1"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="mt-3">
                                        <span class="badge bg-success mb-2"><?php echo htmlspecialchars($f['category_name'] ?? 'Khác'); ?></span>
                                        <?php if (isset($f['status']) && $f['status'] === 'active'): ?>
                                            <span class="badge bg-primary mb-2">Hoạt động</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary mb-2">Ẩn</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-grid mt-2">
                                        <a href="<?php echo BASE_URL; ?>/admin/food-edit.php?id=<?php echo $f['id']; ?>" class="btn btn-outline-primary"><i class="bi bi-pencil me-2"></i>Sửa thông tin</a>
                                    </div>
                                </div>
                                <div class="col-md-7">
                                    <h6 class="fw-bold border-bottom pb-2 text-success"><i class="bi bi-heart-pulse me-2"></i>Thành phần dinh dưỡng</h6>
                                    <div class="row g-2 text-center mb-4 mt-2">
                                        <div class="col-6 col-sm-3">
                                            <div class="bg-light p-2 rounded border border-danger-subtle h-100">
                                                <div class="text-danger fw-bold fs-5 text-nowrap"><?php echo floatval($f['calories']); ?></div>
                                                <div class="text-muted small fw-semibold mt-1">Kcal</div>
                                            </div>
                                        </div>
                                        <div class="col-6 col-sm-3">
                                            <div class="bg-light p-2 rounded border border-primary-subtle h-100">
                                                <div class="text-primary fw-bold fs-5 text-nowrap"><?php echo floatval($f['protein']); ?>g</div>
                                                <div class="text-muted small fw-semibold mt-1">Protein</div>
                                            </div>
                                        </div>
                                        <div class="col-6 col-sm-3">
                                            <div class="bg-light p-2 rounded border border-warning-subtle h-100">
                                                <div class="text-warning fw-bold fs-5 text-nowrap"><?php echo floatval($f['carbs']); ?>g</div>
                                                <div class="text-muted small fw-semibold mt-1">Carbs</div>
                                            </div>
                                        </div>
                                        <div class="col-6 col-sm-3">
                                            <div class="bg-light p-2 rounded border border-info-subtle h-100">
                                                <div class="text-info fw-bold fs-5 text-nowrap"><?php echo floatval($f['fat']); ?>g</div>
                                                <div class="text-muted small fw-semibold mt-1">Fat</div>
                                            </div>
                                        </div>
                                    </div>
                                    <ul class="list-group list-group-flush mb-4 border rounded">
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span class="text-muted">Chất xơ (Fiber)</span>
                                            <span class="fw-bold"><?php echo floatval($f['fiber'] ?? 0); ?>g</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span class="text-muted">Đường (Sugar)</span>
                                            <span class="fw-bold"><?php echo floatval($f['sugar'] ?? 0); ?>g</span>
                                        </li>
                                    </ul>
                                    <h6 class="fw-bold border-bottom pb-2">Mô tả ngắn</h6>
                                    <p class="small text-muted mb-3"><?php echo nl2br(htmlspecialchars($f['description'] ?? 'Chưa có mô tả.')); ?></p>
                                    
                                    <?php if (!empty($f['ingredients'])): ?>
                                        <h6 class="fw-bold border-bottom pb-2 text-success"><i class="bi bi-basket me-2"></i>Nguyên liệu</h6>
                                        <p class="small text-muted mb-3"><?php echo nl2br(htmlspecialchars($f['ingredients'])); ?></p>
                                    <?php endif; ?>

                                    <?php if (!empty($f['instructions'])): ?>
                                        <h6 class="fw-bold border-bottom pb-2 text-success"><i class="bi bi-journal-text me-2"></i>Cách làm</h6>
                                        <p class="small text-muted mb-0"><?php echo nl2br(htmlspecialchars($f['instructions'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <!-- Phân trang -->
            <?php if ($total_pages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">Trước</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>">Sau</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<!-- Modal Import Excel Món Ăn -->
<div class="modal fade" id="importFoodsModal" tabindex="-1" aria-labelledby="importFoodsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="import_excel">

                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="importFoodsModalLabel">
                        <i class="bi bi-file-earmark-excel text-success me-2"></i>Nhập Món Ăn từ File Excel
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">
                        Tải lên danh sách món ăn từ file Excel (<code>.xlsx</code>). Hệ thống tự động khớp danh mục theo tên hoặc tạo mới danh mục nếu chưa có. Món ăn trùng tên trong cùng một danh mục sẽ tự động được bỏ qua để tránh trùng lặp.
                    </p>

                    <div class="alert alert-light border small mb-3">
                        <div class="fw-bold mb-1"><i class="bi bi-info-circle text-primary me-1"></i>Cấu trúc các cột trong file Excel mẫu:</div>
                        <ul class="mb-2 ps-3" style="font-size: 13px;">
                            <li>Cột 1: <strong>STT</strong></li>
                            <li>Cột 2: <strong>Tên Món Ăn</strong> <span class="text-danger">*</span></li>
                            <li>Cột 3: <strong>Tên Danh Mục</strong> <span class="text-danger">*</span></li>
                            <li>Cột 4 & 5: <strong>Khẩu Phần</strong> & <strong>Đơn Vị</strong> (VD: 1 bát, 1 đĩa, 100 g)</li>
                            <li>Cột 6 - 10: <strong>Calories, Protein, Carbs, Fat, Chất Xơ</strong></li>
                            <li>Cột 11: <strong>Mô Tả Ngắn</strong></li>
                            <li>Cột 12: <strong>Nguyên Liệu</strong> (danh sách thành phần/nguyên liệu)</li>
                            <li>Cột 13: <strong>Cách Làm</strong> (các bước hướng dẫn nấu)</li>
                            <li>Cột 14: <strong>Trạng Thái</strong> (Hoạt động / Không hoạt động)</li>
                        </ul>
                        <div class="text-end">
                            <a href="<?php echo BASE_URL; ?>/admin/foods.php?action=sample_excel" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-download me-1"></i>Tải file mẫu Excel đầy đủ (.xlsx)
                            </a>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Chọn file Excel (.xlsx) <span class="text-danger">*</span></label>
                        <input type="file" class="form-control rounded-3" id="importFoodsFile" name="excel_file" accept=".xlsx" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
                        <i class="bi bi-upload me-1"></i>Tiến hành Nhập
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const importModal = document.getElementById('importFoodsModal');
    if (importModal) {
        const fileInput = document.getElementById('importFoodsFile');
        function resetImportModal() {
            if (fileInput) fileInput.value = '';
        }
        importModal.addEventListener('hidden.bs.modal', resetImportModal);
        importModal.addEventListener('hide.bs.modal', resetImportModal);
        importModal.querySelectorAll('[data-bs-dismiss="modal"]').forEach(btn => {
            btn.addEventListener('click', resetImportModal);
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
