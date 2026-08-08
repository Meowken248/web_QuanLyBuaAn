<?php
require_once __DIR__ . '/config/database.php';

function layThuVienMonAn()
{
    return [
        [
            'ten' => 'Canh chua cá lóc',
            'mua' => ['he', 'thu'],
            'nguyen_lieu' => ['cá lóc', 'me', 'cà chua', 'dứa', 'giá đỗ', 'đậu bắp'],
            'muc_tieu' => ['giam_can', 'cai_thien_tieu_hoa'],
            'calo' => 220,
            'protein' => 24,
        ],
        [
            'ten' => 'Gà kho gừng',
            'mua' => ['dong', 'thu'],
            'nguyen_lieu' => ['thịt gà', 'gừng', 'nước mắm', 'đường'],
            'muc_tieu' => ['tang_can', 'cai_thien_tieu_hoa'],
            'calo' => 310,
            'protein' => 28,
        ],
        [
            'ten' => 'Gỏi cuốn tôm thịt',
            'mua' => ['he', 'xuan'],
            'nguyen_lieu' => ['tôm', 'thịt heo', 'bún', 'bánh tráng', 'rau sống'],
            'muc_tieu' => ['giam_can', 'cai_thien_tieu_hoa'],
            'calo' => 180,
            'protein' => 18,
        ],
        [
            'ten' => 'Lẩu nấm chay',
            'mua' => ['dong', 'thu'],
            'nguyen_lieu' => ['nấm', 'đậu hũ', 'cải thảo', 'nước dùng rau củ'],
            'muc_tieu' => ['giam_can', 'ho_tro_benh_ly'],
            'calo' => 150,
            'protein' => 10,
        ],
        [
            'ten' => 'Bò xào ớt chuông',
            'mua' => ['xuan', 'he', 'thu', 'dong'],
            'nguyen_lieu' => ['thịt bò', 'ớt chuông', 'hành tây', 'tỏi'],
            'muc_tieu' => ['tang_can', 'toi_uu_hieu_suat'],
            'calo' => 280,
            'protein' => 30,
        ],
        [
            'ten' => 'Salad ức gà',
            'mua' => ['xuan', 'he'],
            'nguyen_lieu' => ['ức gà', 'xà lách', 'cà chua bi', 'dầu olive'],
            'muc_tieu' => ['giam_can', 'toi_uu_hieu_suat'],
            'calo' => 210,
            'protein' => 32,
        ],
        [
            'ten' => 'Cá hồi áp chảo sốt chanh dây',
            'mua' => ['xuan', 'he', 'thu'],
            'nguyen_lieu' => ['cá hồi', 'chanh dây', 'bơ', 'măng tây'],
            'muc_tieu' => ['toi_uu_hieu_suat', 'ho_tro_benh_ly'],
            'calo' => 320,
            'protein' => 29,
        ],
        [
            'ten' => 'Yến mạch trứng chiên rau củ',
            'mua' => ['xuan', 'he', 'thu', 'dong'],
            'nguyen_lieu' => ['yến mạch', 'trứng gà', 'cà rốt', 'hành lá'],
            'muc_tieu' => ['cai_thien_tieu_hoa', 'toi_uu_hieu_suat'],
            'calo' => 260,
            'protein' => 16,
        ],
    ];
}

function createSlug($str, $delimiter = '-') {
    $slug = mb_strtolower($str, 'UTF-8');
    $slug = preg_replace('/(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)/', 'a', $slug);
    $slug = preg_replace('/(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)/', 'e', $slug);
    $slug = preg_replace('/(ì|í|ị|ỉ|ĩ)/', 'i', $slug);
    $slug = preg_replace('/(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)/', 'o', $slug);
    $slug = preg_replace('/(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)/', 'u', $slug);
    $slug = preg_replace('/(ỳ|ý|ỵ|ỷ|ỹ)/', 'y', $slug);
    $slug = preg_replace('/(đ)/', 'd', $slug);
    $slug = preg_replace('/[^a-z0-9\-]/', ' ', $slug);
    $slug = preg_replace('/\s+/', $delimiter, trim($slug));
    return $slug;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $foods = layThuVienMonAn();
    $inserted = 0;
    
    foreach ($foods as $food) {
        // Check if exists
        $slug = createSlug($food['ten']);
        $stmtCheck = $conn->prepare("SELECT id FROM foods WHERE slug = :slug");
        $stmtCheck->execute([':slug' => $slug]);
        if ($stmtCheck->rowCount() == 0) {
            $sql = "INSERT INTO foods (name, slug, ingredients, calories, protein, season, goals) 
                    VALUES (:name, :slug, :ingredients, :calories, :protein, :season, :goals)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':name' => $food['ten'],
                ':slug' => $slug,
                ':ingredients' => implode(', ', $food['nguyen_lieu']),
                ':calories' => $food['calo'],
                ':protein' => $food['protein'],
                ':season' => implode(',', $food['mua']),
                ':goals' => implode(',', $food['muc_tieu']),
            ]);
            $inserted++;
        }
    }
    
    echo "Successfully inserted $inserted foods.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
