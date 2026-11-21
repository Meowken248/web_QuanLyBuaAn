<?php
// includes/smart-menu-functions.php

// Helper: Bỏ dấu tiếng Việt phục vụ tìm kiếm mềm dẻo
if (!function_exists('boDauTiengViet')) {
    function boDauTiengViet(string $str): string {
        $str = preg_replace('/[áàảãạăắằẳẵặâấầẩẫậ]/u', 'a', $str);
        $str = preg_replace('/[éèẻẽẹêếềểễệ]/u', 'e', $str);
        $str = preg_replace('/[íìỉĩị]/u', 'i', $str);
        $str = preg_replace('/[óòỏõọôốồổỗộơớờởỡợ]/u', 'o', $str);
        $str = preg_replace('/[úùủũụưứừửữự]/u', 'u', $str);
        $str = preg_replace('/[ýỳỷỹỵ]/u', 'y', $str);
        $str = preg_replace('/[đ]/u', 'd', $str);
        return mb_strtolower($str, 'UTF-8');
    }
}

// 1. XỬ LÝ THEO NGUYÊN LIỆU CÓ SẴN (TỪ TỦ LẠNH)
function locMonAnTheoNguyenLieu(array $thuVien, array $nguyenLieuNhap): array
{
    $nguyenLieuNhap = array_filter(array_map(fn($nl) => mb_strtolower(trim($nl), 'UTF-8'), $nguyenLieuNhap));
    if (empty($nguyenLieuNhap)) return [];

    $numNhap = count($nguyenLieuNhap);
    $ketQua = [];

    foreach ($thuVien as $mon) {
        $ingredientsStr = $mon['ingredients'] ?? '';
        $nlMon = array_map('trim', explode(',', mb_strtolower($ingredientsStr, 'UTF-8')));
        $nlMon = array_filter($nlMon);

        $dishName = mb_strtolower($mon['name'] ?? '', 'UTF-8');
        $dishDesc = mb_strtolower($mon['description'] ?? '', 'UTF-8');
        $dishNameNoSign = boDauTiengViet($dishName);
        $dishDescNoSign = boDauTiengViet($dishDesc);

        $matchedInputCount = 0;
        $matchedRecipeCount = 0;
        $nameMatched = false;
        $matchedRecipeIndexes = [];

        foreach ($nguyenLieuNhap as $nlNhap) {
            $nlNhapNoSign = boDauTiengViet($nlNhap);
            $inputFound = false;

            // 1. Kiểm tra trong danh sách nguyên liệu
            foreach ($nlMon as $idx => $nl) {
                $nlNoSign = boDauTiengViet($nl);
                if (str_contains($nl, $nlNhap) || str_contains($nlNoSign, $nlNhapNoSign)) {
                    $inputFound = true;
                    if (!isset($matchedRecipeIndexes[$idx])) {
                        $matchedRecipeIndexes[$idx] = true;
                        $matchedRecipeCount++;
                    }
                }
            }

            // 2. Kiểm tra trong tên món ăn (ví dụ "Đậu phụ sốt cà chua" có cà chua)
            if (str_contains($dishName, $nlNhap) || str_contains($dishNameNoSign, $nlNhapNoSign)) {
                $inputFound = true;
                $nameMatched = true;
            }

            // 3. Kiểm tra trong mô tả
            if (!$inputFound && (str_contains($dishDesc, $nlNhap) || str_contains($dishDescNoSign, $nlNhapNoSign))) {
                $inputFound = true;
            }

            if ($inputFound) {
                $matchedInputCount++;
            }
        }

        if ($matchedInputCount > 0) {
            $countRecipe = max(1, count($nlMon));
            $userCoverage = $matchedInputCount / $numNhap;
            $recipeCoverage = min(1.0, $matchedRecipeCount / $countRecipe);

            // Điểm số thông minh:
            // - userCoverage * 55: Tận dụng được bao nhiêu nguyên liệu trong tủ lạnh của người dùng
            // - recipeCoverage * 25: Món ăn này cần bao nhiêu nguyên liệu mà người dùng đã có
            // - nameMatched: Tên món ăn trực tiếp nhắc đến nguyên liệu -> điểm cộng 20
            $score = round(($userCoverage * 55) + ($recipeCoverage * 25));
            if ($nameMatched) {
                $score += 20;
            }
            $score = max(15, min(100, (int)$score));

            $mon['do_phu_hop'] = $score;
            $ketQua[] = $mon;
        }
    }

    // Sắp xếp món phù hợp nhất lên đầu
    usort($ketQua, function($a, $b) {
        if ($b['do_phu_hop'] !== $a['do_phu_hop']) {
            return $b['do_phu_hop'] <=> $a['do_phu_hop'];
        }
        return strcmp($a['name'], $b['name']);
    });

    return $ketQua;
}

// 2. DANH SÁCH MỤC TIÊU
function danhSachMucTieu(): array
{
    return [
        'giam_can' => [
            'nhan' => 'Giảm cân',
            'mo_ta' => 'Ưu tiên món ít calo, nhiều chất xơ, hạn chế dầu mỡ và tinh bột tinh chế.',
        ],
        'tang_can' => [
            'nhan' => 'Tăng cân',
            'mo_ta' => 'Tăng khẩu phần, ưu tiên món giàu năng lượng, đạm và chất béo tốt để tăng cân lành mạnh.',
        ],
        'ho_tro_benh_ly' => [
            'nhan' => 'Hỗ trợ bệnh lý / Cảnh báo sức khỏe',
            'mo_ta' => 'Điều chỉnh chế độ ăn theo chỉ số máu.',
        ],
        'cai_thien_tieu_hoa' => [
            'nhan' => 'Cải thiện hệ tiêu hóa & Năng lượng',
            'mo_ta' => 'Giảm thực phẩm chế biến sẵn, tăng chất xơ, giữ đường huyết ổn định để tránh uể oải, mệt mỏi sau khi ăn.',
        ],
        'toi_uu_hieu_suat' => [
            'nhan' => 'Tối ưu hóa hiệu suất (Thể thao / Làm việc)',
            'mo_ta' => 'Tăng sức bền, phục hồi nhanh sau khi tập luyện thể thao. Ăn uống tối ưu cho não bộ, duy trì sự tập trung cao độ trong công việc.',
        ],
    ];
}

// 3. THỰC ĐƠN MẪU
function layThucDonMauTheoMucTieu(): array
{
    return [
        'giam_can' => [
            'sang' => [
                '1 bát yến mạch nấu sữa tươi không đường + 1/2 quả chuối + 1 thìa hạt chia',
                '2 quả trứng ốp la (dùng ít dầu olive) + 1 lát bánh mì nguyên cám + 1/2 quả bơ',
                'Sinh tố 1 quả chuối + 1 nắm rau bina + 1 thìa bơ đậu phụng + 150ml sữa tươi không đường',
                '1 bát cháo yến mạch nấu thịt heo băm & bắp ngọt',
                '2 quả trứng luộc + 1 củ khoai lang nhỏ + 1 ly cà phê đen/trà xanh không đường',
            ],
            'trua' => [
                '150g ức gà áp chảo + 1/2 chén cơm gạo lứt + 1 đĩa bông cải xanh luộc',
                '150g thịt thăn heo luộc + 1 củ khoai lang luộc + salad dưa leo cà chua trộn giấm olive',
                '150g thịt bò xào ớt Đà Lạt + 1/2 chén cơm gạo lứt + canh cải cúc',
                '150g tôm hấp sả + 1 củ khoai lang + đĩa rau củ luộc thập cẩm (cà rốt, su su)',
                '150g cá thu/cá basa hấp + 1/2 chén cơm lứt + canh bí xanh nấu thịt băm',
            ],
            'phu_chieu' => [
                '1 hũ sữa chua không đường + 5 hạt hạnh nhân',
                '1 quả ổi hoặc 1 quả táo giòn',
                '1 ly sữa hạt không đường (óc chó/hạnh nhân)',
                '1 hũ sữa chua không đường + 1 thìa hạt bí',
                '1 quả dưa leo + 1 nắm nhỏ hạt điều (8–10 hạt)',
            ],
            'toi' => [
                '150g cá lóc hấp gừng + 1 bát canh rau ngót thịt băm (không ăn cơm)',
                '150g đậu hũ dồn thịt sốt cà chua ít dầu + 1 bát canh mồng tơi nấu tôm',
                '150g cá hồi (hoặc cá ngừ) áp chảo + măng tây/đậu que luộc',
                '150g ức gà xé phay trộn gỏi bắp cải, hành tây + canh bí đỏ thịt băm',
                '150g mực hấp sả + đĩa rau xà lách trộn giấm dầu olive',
            ],
        ],
        'tang_can' => [
            'sang' => [
                '2 quả trứng ốp la + 2 lát bánh mì nguyên cám + 1 ly sữa tươi nguyên kem',
                '1 bát phở bò tái nạm đầy đủ + 1 ly sữa đậu nành',
                'Bánh mì trứng ốp la, pate + 1 ly sữa tươi có đường',
                '1 bát cháo gà nguyên chất + 1 quả trứng gà',
            ],
            'trua' => [
                '200g thịt bò xào ớt chuông + 1 chén cơm đầy + canh bí đỏ',
                '200g gà kho gừng + 1 chén cơm + rau muống xào tỏi',
                '200g cá basa kho tộ + 1 chén cơm + canh chua',
                '200g sườn heo rim mặn ngọt + 1 chén cơm + rau cải xào',
            ],
            'phu_chieu' => [
                '1 ly sữa tươi nguyên kem + vài cái bánh quy',
                'Chuối chiên hoặc khoai lang chiên',
                '1 ổ bánh mì thịt nhỏ',
                'Sữa chua nếp cẩm',
            ],
            'toi' => [
                '200g gà kho gừng + 1 chén cơm + rau xào',
                '200g bò xào ớt chuông + 1 chén cơm + canh rau',
                'Lẩu nấm chay đầy đủ topping + bún',
                '200g cá kho tộ + 1 chén cơm + canh bí',
            ],
        ],
        'ho_tro_benh_ly' => [
            'sang' => [
                '1 bát yến mạch nấu nước lọc + hạt chia, không đường',
                '2 quả trứng luộc + rau sống, hạn chế tinh bột',
                'Salad ức gà, bơ, dầu olive',
            ],
            'trua' => [
                '150g ức gà hấp + rau luộc nhiều xơ + 1/3 chén cơm lứt',
                'Canh cá nấu ngót + đậu hũ hấp',
                '150g cá hấp gừng + salad rau xanh nhiều loại',
            ],
            'phu_chieu' => [
                '1 quả táo nhỏ (ít đường)',
                'Vài lát dưa leo',
                '1 nắm nhỏ hạt óc chó',
            ],
            'toi' => [
                'Canh rau củ thanh đạm + đậu hũ hấp',
                '150g cá hấp + rau luộc, hạn chế dầu mỡ',
                'Salad rau trộn dầu olive, không tinh bột',
            ],
        ],
        'cai_thien_tieu_hoa' => [
            'sang' => [
                'Yến mạch trộn sữa chua + chuối + hạt chia',
                'Cháo yến mạch bí đỏ',
                'Bánh mì nguyên cám + trứng + rau sống',
            ],
            'trua' => [
                '150g cá hấp + rau củ luộc nhiều xơ + cơm gạo lứt',
                'Canh rau dền nấu tôm + đậu hũ',
                '150g ức gà + salad rau trộn dầu olive',
            ],
            'phu_chieu' => [
                '1 hũ sữa chua không đường (bổ sung lợi khuẩn)',
                '1 quả chuối',
                'Nước ép rau củ tươi',
            ],
            'toi' => [
                'Canh rau ngót thịt băm + đậu hũ hấp',
                '150g cá hấp + rau luộc',
                'Súp rau củ thanh đạm',
            ],
        ],
        'toi_uu_hieu_suat' => [
            'sang' => [
                'Yến mạch + trứng + chuối + hạt óc chó',
                'Bánh mì nguyên cám + bơ đậu phộng + chuối',
                'Sinh tố protein (whey/đạm thực vật + chuối + sữa hạt)',
            ],
            'trua' => [
                '200g ức gà + cơm gạo lứt + rau củ nhiều màu',
                '150g cá hồi + khoai lang + măng tây',
                '200g thịt bò nạc + cơm lứt + salad',
            ],
            'phu_chieu' => [
                'Chuối + hạt hạnh nhân',
                'Sữa chua Hy Lạp + granola',
                '1 ly sinh tố protein nhẹ',
            ],
            'toi' => [
                '150g cá hồi + rau củ nướng',
                '200g ức gà + salad quinoa',
                '150g thịt bò + rau xanh luộc',
            ],
        ],
    ];
}

function ucLuongDinhDuongTheoBua(string $buoiKey, string $mucTieu): array
{
    $mucCaloCoBan = [
        'sang' => 350, 'trua' => 500, 'phu_chieu' => 150, 'toi' => 420,
    ];
    $mucProteinCoBan = [
        'sang' => 18, 'trua' => 32, 'phu_chieu' => 6, 'toi' => 30,
    ];
    $heSo = match ($mucTieu) {
        'tang_can' => 1.3,
        'giam_can' => 0.85,
        default => 1.0,
    };
    return [
        'calo' => (int) round(($mucCaloCoBan[$buoiKey] ?? 300) * $heSo),
        'protein' => (int) round(($mucProteinCoBan[$buoiKey] ?? 15) * $heSo),
    ];
}

function danhSachBuoiTheoSoBua(int $soBua): array
{
    return match ($soBua) {
        1 => [['key' => 'trua', 'ten' => 'Bữa chính']],
        2 => [['key' => 'sang', 'ten' => 'Sáng'], ['key' => 'toi', 'ten' => 'Tối']],
        3 => [['key' => 'sang', 'ten' => 'Sáng'], ['key' => 'trua', 'ten' => 'Trưa'], ['key' => 'toi', 'ten' => 'Tối']],
        4 => [['key' => 'sang', 'ten' => 'Sáng'], ['key' => 'trua', 'ten' => 'Trưa'], ['key' => 'phu_chieu', 'ten' => 'Phụ chiều'], ['key' => 'toi', 'ten' => 'Tối']],
        5 => [['key' => 'sang', 'ten' => 'Sáng'], ['key' => 'phu_chieu', 'ten' => 'Xế sáng'], ['key' => 'trua', 'ten' => 'Trưa'], ['key' => 'phu_chieu', 'ten' => 'Xế chiều'], ['key' => 'toi', 'ten' => 'Tối']],
        6 => [['key' => 'sang', 'ten' => 'Sáng sớm'], ['key' => 'sang', 'ten' => 'Sáng'], ['key' => 'trua', 'ten' => 'Trưa'], ['key' => 'phu_chieu', 'ten' => 'Xế chiều'], ['key' => 'toi', 'ten' => 'Tối'], ['key' => 'phu_chieu', 'ten' => 'Tối muộn']],
        default => [['key' => 'trua', 'ten' => 'Bữa chính']],
    };
}

function taoThucDonTheoMucTieu(string $mucTieu, int $soNgay = 3, int $soBua = 4): array
{
    $thuVienMau = layThucDonMauTheoMucTieu();
    $ganhHang = $thuVienMau[$mucTieu] ?? null;
    if ($ganhHang === null) {
        return [];
    }

    $dsBuoi = danhSachBuoiTheoSoBua($soBua);
    $chiSoTheoBuoi = ['sang' => 0, 'trua' => 0, 'phu_chieu' => 0, 'toi' => 0];

    $thucDon = [];
    for ($ngay = 1; $ngay <= $soNgay; $ngay++) {
        $cacBua = [];
        foreach ($dsBuoi as $buoi) {
            $key = $buoi['key'];
            $dsMonTheoBua = $ganhHang[$key] ?? $ganhHang['trua'] ?? [];
            if (empty($dsMonTheoBua)) {
                continue;
            }
            $moTa = $dsMonTheoBua[$chiSoTheoBuoi[$key] % count($dsMonTheoBua)];
            $chiSoTheoBuoi[$key]++;

            $dinhDuong = ucLuongDinhDuongTheoBua($key, $mucTieu);
            $cacBua[] = [
                'ten_bua' => $buoi['ten'],
                'mon' => $moTa,
                'calo' => $dinhDuong['calo'],
                'protein' => $dinhDuong['protein'],
            ];
        }
        $thucDon[] = [
            'ngay' => $ngay,
            'buoi' => $cacBua,
            'tong_calo' => array_sum(array_column($cacBua, 'calo')),
            'tong_protein' => array_sum(array_column($cacBua, 'protein')),
            'ghi_chu' => ghiChuTheoMucTieu($mucTieu),
        ];
    }
    return $thucDon;
}

function ghiChuTheoMucTieu(string $mucTieu): string
{
    $danhSach = danhSachMucTieu();
    return $danhSach[$mucTieu]['mo_ta'] ?? 'Thực đơn cân bằng dinh dưỡng.';
}
