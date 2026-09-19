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
                    INSERT INTO foods (name, slug, category_id, serving_size, serving_unit, calories, protein, carbs, fat, fiber, description, ingredients, instructions, season, status, created_by)
                    VALUES (:name, :slug, :category_id, :serving_size, :serving_unit, :calories, :protein, :carbs, :fat, :fiber, :description, :ingredients, :instructions, :season, :status, :created_by)
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
                        } elseif (str_contains($cleanHeader, 'mùa') || str_contains($cleanHeader, 'mua') || str_contains($cleanHeader, 'season') || str_contains($cleanHeader, 'thời tiết')) {
                            $headerMap['season'] = $colIdx;
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

                    $rawSeason = trim((string)(isset($headerMap['season']) ? ($row[$headerMap['season']] ?? '') : ''));
                    if (!empty($rawSeason)) {
                        $rawSeasonLower = mb_strtolower($rawSeason, 'UTF-8');
                        if (str_contains($rawSeasonLower, 'bốn mùa') || str_contains($rawSeasonLower, 'quanh năm') || str_contains($rawSeasonLower, 'all')) {
                            $season = 'xuan,he,thu,dong';
                        } else {
                            $detected = [];
                            if (str_contains($rawSeasonLower, 'xuân') || str_contains($rawSeasonLower, 'xuan')) $detected[] = 'xuan';
                            if (str_contains($rawSeasonLower, 'hè') || str_contains($rawSeasonLower, 'he') || str_contains($rawSeasonLower, 'hạ')) $detected[] = 'he';
                            if (str_contains($rawSeasonLower, 'thu')) $detected[] = 'thu';
                            if (str_contains($rawSeasonLower, 'đông') || str_contains($rawSeasonLower, 'dong')) $detected[] = 'dong';
                            $season = !empty($detected) ? implode(',', array_unique($detected)) : 'xuan,he,thu,dong';
                        }
                    } else {
                        $season = 'xuan,he,thu,dong';
                    }

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
                        ':season' => $season,
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


// Xử lý Thêm / Sửa món ăn bằng Modal trên trang (In-page CRUD)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_food') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('danger', 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.');
        redirect('/admin/foods.php');
    }

    $food_id = filter_var($_POST['food_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
    $name = trim($_POST['name'] ?? '');
    $category_id = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT);
    $serving_size = filter_var($_POST['serving_size'] ?? null, FILTER_VALIDATE_FLOAT);
    $serving_unit = trim($_POST['serving_unit'] ?? '');

    $nutrients = [];
    foreach (['calories', 'protein', 'carbs', 'fat', 'fiber'] as $field) {
        $raw = trim((string)($_POST[$field] ?? ''));
        $nutrients[$field] = $raw === '' ? 0.0 : filter_var($raw, FILTER_VALIDATE_FLOAT);
    }

    $field_errors = [];
    if ($name === '') $field_errors[] = 'Vui lòng nhập tên món.';
    if (!$category_id) $field_errors[] = 'Vui lòng chọn danh mục.';

    // Kiểm tra trùng tên món trong cùng danh mục
    if ($name !== '' && $category_id) {
        $checkDupSql = "SELECT id FROM foods WHERE category_id = :category_id AND LOWER(TRIM(name)) = LOWER(TRIM(:name))";
        $checkDupParams = [':category_id' => $category_id, ':name' => $name];
        if ($food_id) {
            $checkDupSql .= " AND id != :id";
            $checkDupParams[':id'] = $food_id;
        }
        $checkStmt = $conn->prepare($checkDupSql);
        $checkStmt->execute($checkDupParams);
        if ($checkStmt->fetch()) {
            $field_errors[] = 'Tên món ăn đã tồn tại trong danh mục này.';
        }
    }

    if ($serving_size === false || $serving_size === null || $serving_size <= 0) {
        $field_errors[] = 'Khẩu phần phải lớn hơn 0.';
    }
    if ($serving_unit === '') {
        $field_errors[] = 'Vui lòng nhập đơn vị.';
    }

    if (!empty($field_errors)) {
        set_flash_message('danger', implode(' ', $field_errors));
        redirect('/admin/foods.php');
    }

    // Tự động tính calories nếu chưa có hoặc bằng 0
    if (empty($nutrients['calories']) || $nutrients['calories'] <= 0) {
        $nutrients['calories'] = round($nutrients['protein'] * 4 + $nutrients['carbs'] * 4 + $nutrients['fat'] * 9, 1);
    }

    // Lấy ảnh cũ nếu đang sửa
    $image_path = null;
    if ($food_id) {
        $stmtOld = $conn->prepare("SELECT image FROM foods WHERE id = :id");
        $stmtOld->execute([':id' => $food_id]);
        $image_path = $stmtOld->fetchColumn() ?: null;
    }

    // Xử lý upload ảnh
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['image']['error'] === UPLOAD_ERR_OK && $_FILES['image']['size'] <= 5 * 1024 * 1024) {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['image']['tmp_name']);
            $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
            if (isset($extensions[$mime])) {
                $upload_dir = __DIR__ . '/../uploads/foods/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
                    $image_path = '/uploads/foods/' . $filename;
                }
            }
        }
    }

    $slug_base = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name), '-'));

    $seasons = $_POST['season'] ?? [];
    if (is_array($seasons)) {
        $validSeasons = ['xuan', 'he', 'thu', 'dong'];
        $cleanSeasons = array_values(array_intersect($validSeasons, $seasons));
        $seasonStr = !empty($cleanSeasons) ? implode(',', $cleanSeasons) : 'xuan,he,thu,dong';
    } else {
        $seasonStr = 'xuan,he,thu,dong';
    }

    $params = [
        ':category_id' => $category_id,
        ':name' => $name,
        ':slug' => $slug_base . '-' . ($food_id ?: time()),
        ':image' => $image_path,
        ':description' => trim($_POST['description'] ?? ''),
        ':ingredients' => trim($_POST['ingredients'] ?? ''),
        ':instructions' => trim($_POST['instructions'] ?? ''),
        ':serving_size' => $serving_size,
        ':serving_unit' => $serving_unit,
        ':calories' => $nutrients['calories'],
        ':protein' => $nutrients['protein'],
        ':carbs' => $nutrients['carbs'],
        ':fat' => $nutrients['fat'],
        ':fiber' => $nutrients['fiber'],
        ':season' => $seasonStr,
        ':status' => ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active'
    ];

    if ($food_id) {
        $params[':id'] = $food_id;
        $sql = 'UPDATE foods SET category_id=:category_id,name=:name,slug=:slug,image=:image,description=:description,ingredients=:ingredients,instructions=:instructions,serving_size=:serving_size,serving_unit=:serving_unit,calories=:calories,protein=:protein,carbs=:carbs,fat=:fat,fiber=:fiber,season=:season,status=:status WHERE id=:id';
        set_flash_message('success', 'Đã cập nhật món ăn “' . htmlspecialchars($name) . '” thành công.');
    } else {
        $params[':created_by'] = (int)($_SESSION['user_id'] ?? 1);
        $sql = 'INSERT INTO foods (category_id,name,slug,image,description,ingredients,instructions,serving_size,serving_unit,calories,protein,carbs,fat,fiber,season,status,created_by) VALUES (:category_id,:name,:slug,:image,:description,:ingredients,:instructions,:serving_size,:serving_unit,:calories,:protein,:carbs,:fat,:fiber,:season,:status,:created_by)';
        set_flash_message('success', 'Đã thêm món ăn mới “' . htmlspecialchars($name) . '” thành công.');
    }
    $conn->prepare($sql)->execute($params);
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

// Lấy danh mục đang hoạt động để hiển thị trong Modal
$categories = $conn->query("SELECT id, name FROM food_categories WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

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
                    <button type="button" class="btn btn-sm btn-outline-success rounded-pill" onclick="openFoodModal(null)">
                        <i class="bi bi-plus-circle me-1"></i>Thêm món mới
                    </button>
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
                                            <div>
                                                <a href="#" data-bs-toggle="modal" data-bs-target="#foodModal<?php echo $f['id']; ?>" class="text-decoration-none fw-bold text-dark d-block">
                                                    <?php echo htmlspecialchars($f['name']); ?>
                                                </a>
                                                <?php 
                                                $sList = !empty($f['season']) ? explode(',', $f['season']) : ['xuan','he','thu','dong'];
                                                $isAllYear = (count($sList) >= 4 || in_array('all', $sList) || in_array('bon_mua', $sList));
                                                if ($isAllYear): ?>
                                                    <span class="badge bg-light text-secondary border small mt-1" title="Quanh năm (4 mùa)"><i class="bi bi-calendar4-week text-success me-1"></i>4 mùa</span>
                                                <?php else: ?>
                                                    <span class="mt-1 d-inline-block">
                                                    <?php 
                                                    $sLabels = ['xuan' => '🌸 Xuân', 'he' => '☀️ Hè', 'thu' => '🍂 Thu', 'dong' => '❄️ Đông'];
                                                    foreach ($sList as $s):
                                                        $s = trim($s);
                                                        if (isset($sLabels[$s])): ?>
                                                            <span class="badge bg-light text-dark border small me-1"><?php echo $sLabels[$s]; ?></span>
                                                        <?php endif;
                                                    endforeach; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
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
                                            <button type="button" class="btn btn-sm btn-outline-success" title="Sửa" onclick='openFoodModal(<?php echo htmlspecialchars(json_encode($f, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8"); ?>)'><i class="bi bi-pencil"></i></button>
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
                                        <button type="button" class="btn btn-outline-success" data-bs-dismiss="modal" onclick='setTimeout(() => openFoodModal(<?php echo htmlspecialchars(json_encode($f, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8"); ?>), 200)'><i class="bi bi-pencil me-2"></i>Sửa thông tin</button>
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
                                            <span class="text-muted"><i class="bi bi-cloud-sun me-1 text-success"></i>Mùa phù hợp</span>
                                            <span>
                                                <?php 
                                                $dSeasons = !empty($f['season']) ? explode(',', $f['season']) : ['xuan','he','thu','dong'];
                                                $dIsAll = (count($dSeasons) >= 4 || in_array('all', $dSeasons) || in_array('bon_mua', $dSeasons));
                                                if ($dIsAll) {
                                                    echo '<span class="badge bg-success-subtle text-success border border-success-subtle">Bốn mùa (Quanh năm)</span>';
                                                } else {
                                                    $sMap = ['xuan' => '🌸 Mùa Xuân', 'he' => '☀️ Mùa Hè', 'thu' => '🍂 Mùa Thu', 'dong' => '❄️ Mùa Đông'];
                                                    foreach ($dSeasons as $ds) {
                                                        $ds = trim($ds);
                                                        if (isset($sMap[$ds])) {
                                                            echo '<span class="badge bg-light text-dark border me-1">' . $sMap[$ds] . '</span>';
                                                        }
                                                    }
                                                }
                                                ?>
                                            </span>
                                        </li>
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

<!-- Modal Thêm / Sửa Món Ăn (In-Page CRUD) -->
<div class="modal fade" id="foodFormModal" tabindex="-1" aria-labelledby="foodFormModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form method="POST" enctype="multipart/form-data" id="foodForm">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="save_food">
                <input type="hidden" name="food_id" id="food_modal_id" value="0">

                <div class="modal-header border-0 pb-0 bg-health text-white p-4">
                    <h5 class="modal-title fw-bold" id="foodFormModalLabel">
                        <i class="bi bi-egg-fried me-2"></i><span id="food_modal_title">Thêm Món Ăn Mới</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Tên món ăn <span class="text-danger">*</span></label>
                            <input type="text" class="form-control rounded-3" id="food_modal_name" name="name" required placeholder="Ví dụ: Phở bò Hà Nội, Cơm gà xối mỡ..." maxlength="200" autocomplete="off">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Danh mục <span class="text-danger">*</span></label>
                            <select class="form-select rounded-3" id="food_modal_category" name="category_id" required>
                                <option value="">-- Chọn danh mục --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Mô tả ngắn</label>
                            <textarea class="form-control rounded-3" id="food_modal_desc" name="description" rows="2" placeholder="Hương vị, xuất xứ, điểm nổi bật của món ăn..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nguyên liệu</label>
                            <textarea class="form-control rounded-3" id="food_modal_ingredients" name="ingredients" rows="3" placeholder="Ví dụ: Thịt bò, bánh phở, hành lá, quế, hồi..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Cách làm / Chế biến</label>
                            <textarea class="form-control rounded-3" id="food_modal_instructions" name="instructions" rows="3" placeholder="Các bước sơ chế, nấu nướng và trình bày..."></textarea>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Khẩu phần <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" class="form-control rounded-3" id="food_modal_serving_size" name="serving_size" required placeholder="100">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Đơn vị tính <span class="text-danger">*</span></label>
                            <input type="text" class="form-control rounded-3" id="food_modal_serving_unit" name="serving_unit" required placeholder="gram, đĩa, bát, tô, chén..." maxlength="50">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Trạng thái</label>
                            <select class="form-select rounded-3" id="food_modal_status" name="status">
                                <option value="active">Hoạt động (Hiển thị)</option>
                                <option value="inactive">Đã ẩn (Tạm dừng)</option>
                            </select>
                        </div>

                        <!-- Mùa / Thời tiết phù hợp -->
                        <div class="col-12">
                            <label class="form-label fw-bold d-flex justify-content-between align-items-center mb-1">
                                <span><i class="bi bi-cloud-sun me-1 text-success"></i> Mùa / Thời tiết phù hợp <span class="text-muted fw-normal small">(Dùng cho Gợi ý Thực đơn Thông minh AI theo mùa)</span></span>
                                <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 text-success fw-bold" onclick="toggleAllSeasons()">
                                    <i class="bi bi-check2-all me-1"></i>Chọn cả 4 mùa (Quanh năm)
                                </button>
                            </label>
                            <div class="p-3 bg-light rounded-3 border d-flex flex-wrap gap-4 align-items-center">
                                <div class="form-check form-check-inline m-0">
                                    <input class="form-check-input season-checkbox" type="checkbox" name="season[]" id="season_xuan" value="xuan">
                                    <label class="form-check-label fw-semibold" for="season_xuan">🌸 Mùa Xuân (Ấm áp)</label>
                                </div>
                                <div class="form-check form-check-inline m-0">
                                    <input class="form-check-input season-checkbox" type="checkbox" name="season[]" id="season_he" value="he">
                                    <label class="form-check-label fw-semibold" for="season_he">☀️ Mùa Hè (Thanh nhiệt)</label>
                                </div>
                                <div class="form-check form-check-inline m-0">
                                    <input class="form-check-input season-checkbox" type="checkbox" name="season[]" id="season_thu" value="thu">
                                    <label class="form-check-label fw-semibold" for="season_thu">🍂 Mùa Thu (Mát mẻ)</label>
                                </div>
                                <div class="form-check form-check-inline m-0">
                                    <input class="form-check-input season-checkbox" type="checkbox" name="season[]" id="season_dong" value="dong">
                                    <label class="form-check-label fw-semibold" for="season_dong">❄️ Mùa Đông (Ấm bụng)</label>
                                </div>
                            </div>
                            <div class="form-text small text-muted">Nếu chọn cả 4 mùa hoặc để trống, món ăn sẽ tự động được xếp vào thực đơn quanh năm.</div>
                        </div>

                        <!-- Các chỉ số dinh dưỡng -->
                        <div class="col-12 mt-4">
                            <h6 class="fw-bold text-success border-bottom pb-2 mb-3">
                                <i class="bi bi-heart-pulse me-2"></i>Thành phần dinh dưỡng (trên 1 khẩu phần)
                            </h6>
                            <div class="row g-2 text-center">
                                <div class="col-6 col-md">
                                    <label class="form-label small fw-bold text-danger">Calories (kcal)</label>
                                    <input type="number" step="0.01" min="0" class="form-control text-center fw-bold bg-light text-danger rounded-3" id="food_modal_calories" name="calories" placeholder="0.00" readonly title="Tự động tính từ Protein, Carbs, Fat">
                                    <div class="form-text" style="font-size: 11px;">Tự động tính</div>
                                </div>
                                <div class="col-6 col-md">
                                    <label class="form-label small fw-bold text-primary">Protein (g) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0" class="form-control text-center rounded-3 food-macro-input" id="food_modal_protein" name="protein" required placeholder="0.00">
                                    <div class="form-text" style="font-size: 11px;">× 4 kcal</div>
                                </div>
                                <div class="col-6 col-md">
                                    <label class="form-label small fw-bold text-warning">Carbs (g) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0" class="form-control text-center rounded-3 food-macro-input" id="food_modal_carbs" name="carbs" required placeholder="0.00">
                                    <div class="form-text" style="font-size: 11px;">× 4 kcal</div>
                                </div>
                                <div class="col-6 col-md">
                                    <label class="form-label small fw-bold text-info">Fat (g) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0" class="form-control text-center rounded-3 food-macro-input" id="food_modal_fat" name="fat" required placeholder="0.00">
                                    <div class="form-text" style="font-size: 11px;">× 9 kcal</div>
                                </div>
                                <div class="col-6 col-md">
                                    <label class="form-label small fw-bold text-success">Chất xơ (g)</label>
                                    <input type="number" step="0.01" min="0" class="form-control text-center rounded-3" id="food_modal_fiber" name="fiber" placeholder="0.00">
                                    <div class="form-text" style="font-size: 11px;">Chất xơ</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 mt-3">
                            <label class="form-label fw-bold">Hình ảnh minh họa</label>
                            <input type="file" class="form-control rounded-3" id="food_modal_image" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
                            <div id="food_current_img_wrapper" class="mt-2 d-none d-flex align-items-center gap-2">
                                <img id="food_current_img" src="" alt="Thumbnail" class="rounded border shadow-sm" style="width: 50px; height: 50px; object-fit: cover;">
                                <span class="text-muted small">Ảnh hiện tại (để trống nếu không muốn đổi ảnh)</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" id="food_modal_submit_btn">
                        <i class="bi bi-check-circle me-1"></i>Lưu Món Ăn
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function calcFoodModalCalories() {
    const p = parseFloat(document.getElementById('food_modal_protein').value) || 0;
    const c = parseFloat(document.getElementById('food_modal_carbs').value) || 0;
    const f = parseFloat(document.getElementById('food_modal_fat').value) || 0;
    document.getElementById('food_modal_calories').value = (p * 4 + c * 4 + f * 9).toFixed(1);
}

document.querySelectorAll('.food-macro-input').forEach(input => {
    input.addEventListener('input', calcFoodModalCalories);
});

function openFoodModal(f) {
    const modalEl = document.getElementById('foodFormModal');
    const modalTitle = document.getElementById('food_modal_title');
    const idInput = document.getElementById('food_modal_id');
    const nameInput = document.getElementById('food_modal_name');
    const catSelect = document.getElementById('food_modal_category');
    const descInput = document.getElementById('food_modal_desc');
    const ingInput = document.getElementById('food_modal_ingredients');
    const insInput = document.getElementById('food_modal_instructions');
    const sizeInput = document.getElementById('food_modal_serving_size');
    const unitInput = document.getElementById('food_modal_serving_unit');
    const statusSelect = document.getElementById('food_modal_status');
    const calInput = document.getElementById('food_modal_calories');
    const proteinInput = document.getElementById('food_modal_protein');
    const carbsInput = document.getElementById('food_modal_carbs');
    const fatInput = document.getElementById('food_modal_fat');
    const fiberInput = document.getElementById('food_modal_fiber');
    const fileInput = document.getElementById('food_modal_image');
    const imgWrapper = document.getElementById('food_current_img_wrapper');
    const imgPreview = document.getElementById('food_current_img');
    const submitBtn = document.getElementById('food_modal_submit_btn');
    const seasonCheckboxes = document.querySelectorAll('.season-checkbox');

    fileInput.value = '';

    if (!f) {
        modalTitle.textContent = 'Thêm Món Ăn Mới';
        idInput.value = '0';
        nameInput.value = '';
        catSelect.value = '';
        descInput.value = '';
        ingInput.value = '';
        insInput.value = '';
        sizeInput.value = '100';
        unitInput.value = 'gram';
        statusSelect.value = 'active';
        proteinInput.value = '0.00';
        carbsInput.value = '0.00';
        fatInput.value = '0.00';
        fiberInput.value = '0.00';
        calInput.value = '0.00';
        imgWrapper.classList.add('d-none');
        // Mặc định chọn cả 4 mùa cho món mới
        seasonCheckboxes.forEach(cb => { cb.checked = true; });
        submitBtn.innerHTML = '<i class="bi bi-plus-circle me-1"></i>Thêm Mới';
    } else {
        modalTitle.textContent = 'Chỉnh Sửa Món Ăn: ' + f.name;
        idInput.value = f.id || 0;
        nameInput.value = f.name || '';
        catSelect.value = f.category_id || '';
        descInput.value = f.description || '';
        ingInput.value = f.ingredients || '';
        insInput.value = f.instructions || '';
        sizeInput.value = f.serving_size || '100';
        unitInput.value = f.serving_unit || 'gram';
        statusSelect.value = f.status || 'active';
        proteinInput.value = f.protein !== undefined ? f.protein : '0.00';
        carbsInput.value = f.carbs !== undefined ? f.carbs : '0.00';
        fatInput.value = f.fat !== undefined ? f.fat : '0.00';
        fiberInput.value = f.fiber !== undefined ? f.fiber : '0.00';
        calInput.value = f.calories !== undefined ? f.calories : '0.00';

        // Check seasons
        let foodSeasons = [];
        if (f.season) {
            if (f.season === 'all' || f.season === 'bon_mua') {
                foodSeasons = ['xuan', 'he', 'thu', 'dong'];
            } else {
                foodSeasons = f.season.split(',').map(s => s.trim());
            }
        } else {
            foodSeasons = ['xuan', 'he', 'thu', 'dong'];
        }
        seasonCheckboxes.forEach(cb => {
            cb.checked = foodSeasons.includes(cb.value);
        });

        if (f.image) {
            let imgUrl = f.image;
            if (!imgUrl.startsWith('http')) {
                imgUrl = '<?php echo BASE_URL; ?>' + (imgUrl.startsWith('/') ? imgUrl : '/' + imgUrl);
            }
            imgPreview.src = imgUrl;
            imgWrapper.classList.remove('d-none');
        } else {
            imgWrapper.classList.add('d-none');
        }

        submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Cập Nhật';
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
    setTimeout(() => { nameInput.focus(); }, 400);
}

function toggleAllSeasons() {
    const seasonCheckboxes = document.querySelectorAll('.season-checkbox');
    const allChecked = Array.from(seasonCheckboxes).every(cb => cb.checked);
    seasonCheckboxes.forEach(cb => { cb.checked = !allChecked; });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
