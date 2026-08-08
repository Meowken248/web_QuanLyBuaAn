<?php
/**
 * GỢI Ý THỰC ĐƠN THÔNG MINH
 * -----------------------------------------------------
 * 3 chế độ hoạt động (chọn qua combobox):
 *   1. 🌱 Theo mùa            -> lọc THƯ VIỆN MÓN ĂN theo mùa
 *   2. 🎯 Theo mục tiêu       -> AI phân tích nhu cầu -> tạo thực đơn
 *   3. 🧊 Theo nguyên liệu    -> lọc THƯ VIỆN MÓN ĂN theo nguyên liệu có sẵn
 * -----------------------------------------------------
 * Chạy: php -S localhost:8000 rồi mở http://localhost:8000/goi_y_thuc_don.php
 */

session_start();

// ============================================================
// 1. THƯ VIỆN MÓN ĂN (giả lập database - có thể thay bằng MySQL)
// ============================================================
function layThuVienMonAn(): array
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

// ============================================================
// 2. XỬ LÝ THEO MÙA -> lọc thư viện món ăn
// ============================================================
function locMonAnTheoMua(array $thuVien, string $mua): array
{
    return array_values(array_filter($thuVien, fn($mon) => in_array($mua, $mon['mua'], true)));
}

// ============================================================
// 3. XỬ LÝ THEO NGUYÊN LIỆU CÓ SẴN -> lọc thư viện món ăn
// ============================================================
function locMonAnTheoNguyenLieu(array $thuVien, array $nguyenLieuNhap): array
{
    $nguyenLieuNhap = array_map(fn($nl) => mb_strtolower(trim($nl)), $nguyenLieuNhap);

    $ketQua = [];
    foreach ($thuVien as $mon) {
        $nlMon = array_map('mb_strtolower', $mon['nguyen_lieu']);
        $soTrung = count(array_intersect($nlMon, $nguyenLieuNhap));
        if ($soTrung > 0) {
            $mon['do_phu_hop'] = round($soTrung / count($nlMon) * 100); // % nguyên liệu có sẵn khớp
            $ketQua[] = $mon;
        }
    }
    // Sắp xếp món phù hợp nhất lên đầu
    usort($ketQua, fn($a, $b) => $b['do_phu_hop'] <=> $a['do_phu_hop']);
    return $ketQua;
}

// ============================================================
// 4. XỬ LÝ THEO MỤC TIÊU -> "AI" phân tích & tạo thực đơn
//    (rule-based demo; có thể thay bằng gọi API Anthropic thật)
// ============================================================

// Danh sách mục tiêu dùng chung cho combobox và phần ghi chú
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

// ------------------------------------------------------------
// Thư viện thực đơn CỤ THỂ theo từng bữa (sáng/trưa/phụ chiều/tối)
// cho từng mục tiêu — mỗi phần tử là 1 gợi ý bữa ăn chi tiết,
// có định lượng rõ ràng giống thực đơn thật (không chỉ tên món chung chung).
// ------------------------------------------------------------
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
                'Bún gạo lứt nấu thịt bò tái + nhiều rau ngò, giá đỗ',
                '2 lát bánh mì nguyên cám + 1 quả trứng chần + 1/2 quả bơ',
                'Yến mạch nấu sữa hạt + 1/2 quả táo xắt hạt lựu',
                '1 lát bánh mì nguyên cám + 1 quả trứng ốp la + dưa leo xắt lát',
                'Sinh tô bơ chuối (1/2 quả bơ + 1/2 quả chuối + sữa tươi không đường)',
                '1 củ khoai lang luộc + 1 ly sữa hạt không đường',
                'Bún gạo lứt xào thịt băm rau củ',
                'Sinh tố dâu tây chuối yến mạch',
                'Bánh mì nguyên cám + 1 thìa bơ đậu phộng + 1/2 quả chuối',
                '1 bát phở gà/phở bò (ăn ít nước dùng béo)',
            ],
            'trua' => [
                '150g ức gà áp chảo + 1/2 chén cơm gạo lứt + 1 đĩa bông cải xanh luộc',
                '150g thịt thăn heo luộc + 1 củ khoai lang luộc + salad dưa leo cà chua trộn giấm olive',
                '150g thịt bò xào ớt Đà Lạt + 1/2 chén cơm gạo lứt + canh cải cúc',
                '150g tôm hấp sả + 1 củ khoai lang + đĩa rau củ luộc thập cẩm (cà rốt, su su)',
                '150g cá thu/cá basa hấp + 1/2 chén cơm lứt + canh bí xanh nấu thịt băm',
                '150g thịt thăn heo áp chảo + 1 củ khoai tây nướng nguyên vỏ + salad rau mầm',
                '150g cá lóc/cá diêu hồng nướng + 1/2 chén cơm lứt + rau củ luộc',
                '150g ức gà xào nấm + 1/2 chén cơm lứt + cải ngọt luộc',
                '150g tôm xào bông cải xanh + 1/2 chén cơm lứt',
                '150g mực xào cần tây hành tây + 1/2 chén cơm lứt',
                '150g cá hồi áp chảo + 1/2 chén cơm lứt + măng tây luộc',
                '150g thịt bò xào măng tây + 1/2 chén cơm lứt',
                '150g cá ngừ áp chảo + 1/2 chén cơm lứt + salad',
                '150g tôm rim nhẹ + 1/2 chén cơm lứt + rau củ thập cẩm luộc',
                '150g cá thu sốt cà chua ít dầu + 1/2 chén cơm lứt + rau luộc',
            ],
            'phu_chieu' => [
                '1 hũ sữa chua không đường + 5 hạt hạnh nhân',
                '1 quả ổi hoặc 1 quả táo giòn',
                '1 ly sữa hạt không đường (óc chó/hạnh nhân)',
                '1 hũ sữa chua không đường + 1 thìa hạt bí',
                '1 quả dưa leo + 1 nắm nhỏ hạt điều (8–10 hạt)',
                '1 trái cam hoặc 2 trái quýt',
                '1 hũ sữa chua kefir hoặc sữa chua không đường',
                '5 hạt hạnh nhân + 1 tách trà xanh',
                '1 quả táo',
                '1 nắm hạt điều thô',
                'Trái cây tươi (dưa hấu/thơm)',
            ],
            'toi' => [
                '150g cá lóc hấp gừng + 1 bát canh rau ngót thịt băm (không ăn cơm)',
                '150g đậu hũ dồn thịt sốt cà chua ít dầu + 1 bát canh mồng tơi nấu tôm',
                '150g cá hồi (hoặc cá ngừ) áp chảo + măng tây/đậu que luộc',
                '150g ức gà xé phay trộn gỏi bắp cải, hành tây + canh bí đỏ thịt băm',
                '150g mực hấp sả + đĩa rau xà lách trộn giấm dầu olive',
                'Canh súp sườn heo nấu củ quả (cà rốt, củ cải) + đĩa rau cải thìa luộc',
                '150g đùi gà bỏ da nướng/áp chảo + đĩa salad thập cẩm',
                '150g cá chẽm/cá bống hấp gừng + canh bí xanh',
                '150g đậu hũ kho nấm ít gia vị + canh rau cải cúc',
                'Cá lóc nấu canh chua (ít đường, nêm nhạt) + rau sống',
                'Trứng cuộn rau củ (2 quả trứng + cà rốt, hành tây) + canh tần ô',
                '150g thịt bò băm bọc sả nướng + salad rau mầm',
                '150g tôm luộc + salad dưa leo cà chua',
                '150g ức gà luộc + canh cải cúc thịt băm',
                '150g mực hấp gừng + rau xà lách giấm olive',
            ],
        ],
        'tang_can' => [
            'sang' => [
                '2 quả trứng ốp la + 2 lát bánh mì nguyên cám + 1 ly sữa tươi nguyên kem',
                '1 bát phở bò tái nạm đầy đủ + 1 ly sữa đậu nành',
                'Bánh mì trứng ốp la, pate + 1 ly sữa tươi có đường',
                '1 bát cháo gà nguyên chất + 1 quả trứng gà',
                'Xôi xéo/xôi đậu xanh + 1 quả trứng luộc',
                'Bánh cuốn chả lụa đầy đủ + 1 ly sữa đậu nành',
                'Bún bò giò heo + rau sống',
            ],
            'trua' => [
                '200g thịt bò xào ớt chuông + 1 chén cơm đầy + canh bí đỏ',
                '200g gà kho gừng + 1 chén cơm + rau muống xào tỏi',
                '200g cá basa kho tộ + 1 chén cơm + canh chua',
                '200g sườn heo rim mặn ngọt + 1 chén cơm + rau cải xào',
                '200g thịt kho trứng + 1 chén cơm + canh rau ngót',
                'Cơm gà xối mỡ + dưa leo',
                'Bún riêu cua đầy đủ topping',
            ],
            'phu_chieu' => [
                '1 ly sữa tươi nguyên kem + vài cái bánh quy',
                'Chuối chiên hoặc khoai lang chiên',
                '1 ổ bánh mì thịt nhỏ',
                'Sữa chua nếp cẩm',
                '1 ly sinh tố bơ sữa đặc',
                'Đậu phộng rang + 1 ly sữa đậu nành',
            ],
            'toi' => [
                '200g gà kho gừng + 1 chén cơm + rau xào',
                '200g bò xào ớt chuông + 1 chén cơm + canh rau',
                'Lẩu nấm chay đầy đủ topping + bún',
                '200g cá kho tộ + 1 chén cơm + canh bí',
                'Cháo sườn + trứng bắc thảo',
                'Mì xào bò',
                '200g thịt kho hột vịt + 1 chén cơm',
            ],
        ],
        'ho_tro_benh_ly' => [
            'sang' => [
                '1 bát yến mạch nấu nước lọc + hạt chia, không đường',
                '2 quả trứng luộc + rau sống, hạn chế tinh bột',
                'Salad ức gà, bơ, dầu olive',
                'Sữa hạt không đường + vài lát hạnh nhân',
                'Cháo yến mạch bí đỏ không đường',
            ],
            'trua' => [
                '150g ức gà hấp + rau luộc nhiều xơ + 1/3 chén cơm lứt',
                'Canh cá nấu ngót + đậu hũ hấp',
                '150g cá hấp gừng + salad rau xanh nhiều loại',
                'Đậu hũ non sốt nấm + rau luộc',
                '150g tôm hấp + bông cải xanh luộc',
            ],
            'phu_chieu' => [
                '1 quả táo nhỏ (ít đường)',
                'Vài lát dưa leo',
                '1 nắm nhỏ hạt óc chó',
                'Trà atiso không đường',
            ],
            'toi' => [
                'Canh rau củ thanh đạm + đậu hũ hấp',
                '150g cá hấp + rau luộc, hạn chế dầu mỡ',
                'Salad rau trộn dầu olive, không tinh bột',
                'Súp bí đỏ nấu thịt băm, nêm nhạt',
                '150g ức gà luộc + rau xanh',
            ],
        ],
        'cai_thien_tieu_hoa' => [
            'sang' => [
                'Yến mạch trộn sữa chua + chuối + hạt chia',
                'Cháo yến mạch bí đỏ',
                'Bánh mì nguyên cám + trứng + rau sống',
                'Sinh tố đu đủ chuối',
                'Khoai lang luộc + sữa chua không đường',
            ],
            'trua' => [
                '150g cá hấp + rau củ luộc nhiều xơ + cơm gạo lứt',
                'Canh rau dền nấu tôm + đậu hũ',
                '150g ức gà + salad rau trộn dầu olive',
                'Súp bí đỏ + bánh mì nguyên cám',
                '150g cá lóc kho nhạt + rau luộc',
            ],
            'phu_chieu' => [
                '1 hũ sữa chua không đường (bổ sung lợi khuẩn)',
                '1 quả chuối',
                'Nước ép rau củ tươi',
                '1 quả táo/lê tươi',
            ],
            'toi' => [
                'Canh rau ngót thịt băm + đậu hũ hấp',
                '150g cá hấp + rau luộc',
                'Súp rau củ thanh đạm',
                '150g ức gà luộc + salad rau xanh',
                'Cháo yến mạch rau củ nhẹ bụng',
            ],
        ],
        'toi_uu_hieu_suat' => [
            'sang' => [
                'Yến mạch + trứng + chuối + hạt óc chó',
                'Bánh mì nguyên cám + bơ đậu phộng + chuối',
                'Sinh tố protein (whey/đạm thực vật + chuối + sữa hạt)',
                '2 quả trứng + 1/2 quả bơ + cà chua bi',
                'Cháo yến mạch hạt sen',
            ],
            'trua' => [
                '200g ức gà + cơm gạo lứt + rau củ nhiều màu',
                '150g cá hồi + khoai lang + măng tây',
                '200g thịt bò nạc + cơm lứt + salad',
                'Mì soba + ức gà + rau',
                '150g cá ngừ áp chảo + khoai lang + bông cải',
            ],
            'phu_chieu' => [
                'Chuối + hạt hạnh nhân',
                'Sữa chua Hy Lạp + granola',
                '1 ly sinh tố protein nhẹ',
                'Trứng luộc + vài lát dưa leo',
            ],
            'toi' => [
                '150g cá hồi + rau củ nướng',
                '200g ức gà + salad quinoa',
                '150g thịt bò + rau xanh luộc',
                'Súp gà rau củ + bánh mì nguyên cám',
                'Tôm hấp + măng tây + khoai lang',
            ],
        ],
    ];
}

// Ước lượng calo/protein tham khảo theo LOẠI bữa (vì món ở đây là tổ hợp nhiều thành phần
// nên không tính chính xác tuyệt đối, chỉ mang tính tham khảo để cân đối khẩu phần ngày)
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

// Danh sách các bữa trong ngày theo số bữa người dùng chọn (khớp cách gọi tên trong thực đơn mẫu)
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

function taoThucDonTheoMucTieu(array $thuVien, string $mucTieu, int $soNgay = 3, int $soBua = 4): array
{
    $thuVienMau = layThucDonMauTheoMucTieu();
    $ganhHang = $thuVienMau[$mucTieu] ?? null;
    if ($ganhHang === null) {
        return [];
    }

    $dsBuoi = danhSachBuoiTheoSoBua($soBua);

    // Xoay vòng riêng cho từng loại bữa (sang/trua/phu_chieu/toi) để tránh lặp lại liên tiếp
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

// (Tuỳ chọn) Gọi AI thật qua API Anthropic thay cho hàm rule-based ở trên
function taoThucDonBangAI(string $mucTieu, int $soNgay, string $apiKey): ?string
{
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'claude-sonnet-4-6',
            'max_tokens' => 800,
            'messages' => [[
                'role' => 'user',
                'content' => "Hãy gợi ý thực đơn {$soNgay} ngày cho mục tiêu: {$mucTieu}. Trả lời ngắn gọn dạng danh sách.",
            ]],
        ]),
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    if ($response === false) return null;

    $data = json_decode($response, true);
    return $data['content'][0]['text'] ?? null;
}

// ============================================================
// 5. XỬ LÝ REQUEST TỪ FORM (combobox)
// ============================================================
$che_do = $_POST['che_do'] ?? '';
$thuVien = layThuVienMonAn();
$ketQua = null;
$loaiKetQua = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($che_do) {
        case 'theo_mua':
            $mua = $_POST['mua'] ?? '';
            $ketQua = locMonAnTheoMua($thuVien, $mua);
            $loaiKetQua = 'thu_vien';
            break;

        case 'theo_muc_tieu':
            $mucTieu = $_POST['muc_tieu'] ?? '';
            $soNgay = (int) ($_POST['so_ngay'] ?? 3);
            $soNgay = max(1, min(14, $soNgay)); // giới hạn 1-14 ngày
            $soBua = (int) ($_POST['so_bua'] ?? 4);
            $soBua = max(1, min(6, $soBua)); // giới hạn 1-6 bữa/ngày
            $ketQua = taoThucDonTheoMucTieu($thuVien, $mucTieu, $soNgay, $soBua);
            $loaiKetQua = 'ai_thuc_don';
            break;

        case 'theo_nguyen_lieu':
            $nguyenLieuTho = $_POST['nguyen_lieu'] ?? '';
            $dsNguyenLieu = array_filter(array_map('trim', explode(',', $nguyenLieuTho)));
            $ketQua = locMonAnTheoNguyenLieu($thuVien, $dsNguyenLieu);
            $loaiKetQua = 'thu_vien';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Gợi ý thực đơn thông minh</title>
<style>
  body { font-family: Arial, sans-serif; max-width: 720px; margin: 40px auto; color: #222; }
  h1 { text-align: center; }
  select, input[type=text] { width: 100%; padding: 8px; margin: 6px 0 14px; box-sizing: border-box; }
  button { padding: 10px 20px; background: #2e7d32; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
  .khoi { display: none; padding: 12px; background: #f6f6f6; border-radius: 6px; margin-bottom: 14px; }
  .khoi.active { display: block; }
  .mon { border: 1px solid #ddd; border-radius: 6px; padding: 10px 14px; margin-bottom: 8px; }
  .mon b { color: #2e7d32; }
  .tag { display: inline-block; background: #e8f5e9; color: #2e7d32; padding: 2px 8px; border-radius: 10px; font-size: 12px; margin-left: 6px; }
</style>
</head>
<body>

<h1>🍽️ GỢI Ý THỰC ĐƠN THÔNG MINH</h1>

<form method="post" id="form-thuc-don">
    <label><b>Chọn chế độ gợi ý:</b></label>
    <select name="che_do" id="che_do" onchange="hienThiKhoi()">
        <option value="">-- Chọn chế độ --</option>
        <option value="theo_mua" <?= $che_do === 'theo_mua' ? 'selected' : '' ?>>🌱 Theo mùa</option>
        <option value="theo_muc_tieu" <?= $che_do === 'theo_muc_tieu' ? 'selected' : '' ?>>🎯 Theo mục tiêu</option>
        <option value="theo_nguyen_lieu" <?= $che_do === 'theo_nguyen_lieu' ? 'selected' : '' ?>>🧊 Theo nguyên liệu có sẵn</option>
    </select>

    <div class="khoi" id="khoi_theo_mua">
        <label>Chọn mùa:</label>
        <select name="mua">
            <option value="xuan">Xuân</option>
            <option value="he">Hè</option>
            <option value="thu">Thu</option>
            <option value="dong">Đông</option>
        </select>
    </div>

    <div class="khoi" id="khoi_theo_muc_tieu">
        <label>Chọn mục tiêu:</label>
        <select name="muc_tieu" id="muc_tieu" onchange="hienThiMoTaMucTieu()">
            <?php foreach (danhSachMucTieu() as $ma => $mt): ?>
                <option value="<?= htmlspecialchars($ma) ?>" data-mota="<?= htmlspecialchars($mt['mo_ta']) ?>">
                    <?= htmlspecialchars($mt['nhan']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p id="mo_ta_muc_tieu" style="font-size: 13px; color: #555; margin: -8px 0 14px;"></p>

        <label>Số ngày muốn lên thực đơn:</label>
        <input type="number" name="so_ngay" min="1" max="30" value="<?= (int) ($_POST['so_ngay'] ?? 3) ?>">

        <label>Số bữa ăn trong ngày:</label>
        <select name="so_bua">
            <?php for ($i = 1; $i <= 6; $i++): ?>
                <option value="<?= $i ?>" <?= (int) ($_POST['so_bua'] ?? 4) === $i ? 'selected' : '' ?>>
                    <?= $i ?> bữa/ngày
                </option>
            <?php endfor; ?>
        </select>
    </div>

    <div class="khoi" id="khoi_theo_nguyen_lieu">
        <label>Nhập nguyên liệu có sẵn (cách nhau bởi dấu phẩy):</label>
        <input type="text" name="nguyen_lieu" placeholder="vd: tôm, cà chua, đậu hũ">
    </div>

    <button type="submit">Gợi ý ngay</button>
</form>

<script>
function hienThiKhoi() {
    document.querySelectorAll('.khoi').forEach(k => k.classList.remove('active'));
    const val = document.getElementById('che_do').value;
    if (val) document.getElementById('khoi_' + val).classList.add('active');
}
function hienThiMoTaMucTieu() {
    const select = document.getElementById('muc_tieu');
    const opt = select.options[select.selectedIndex];
    document.getElementById('mo_ta_muc_tieu').textContent = opt ? opt.dataset.mota : '';
}
window.onload = function () {
    hienThiKhoi();
    hienThiMoTaMucTieu();
};
</script>

<?php if ($ketQua !== null): ?>
<hr>
<h2><?= $loaiKetQua === 'ai_thuc_don' ? '🤖 THỰC ĐƠN DO AI GỢI Ý' : '📚 KẾT QUẢ TỪ THƯ VIỆN MÓN ĂN' ?></h2>
<?php if ($loaiKetQua === 'ai_thuc_don'): ?>
    <p style="font-size:12px;color:#888;margin-top:-8px;">*Calo/protein là số ước tính tham khảo theo loại bữa, không phải tính toán dinh dưỡng chính xác từng món.</p>
<?php endif; ?>

<?php if (empty($ketQua)): ?>
    <p>Không tìm thấy món ăn phù hợp. Vui lòng thử lại với lựa chọn khác.</p>

<?php elseif ($loaiKetQua === 'thu_vien'): ?>
    <?php foreach ($ketQua as $mon): ?>
        <div class="mon">
            <b><?= htmlspecialchars($mon['ten']) ?></b>
            <?php if (isset($mon['do_phu_hop'])): ?>
                <span class="tag"><?= $mon['do_phu_hop'] ?>% nguyên liệu khớp</span>
            <?php endif; ?>
            <br>Nguyên liệu: <?= htmlspecialchars(implode(', ', $mon['nguyen_lieu'])) ?>
            <br>Calo: <?= $mon['calo'] ?> kcal — Protein: <?= $mon['protein'] ?> g
        </div>
    <?php endforeach; ?>

<?php elseif ($loaiKetQua === 'ai_thuc_don'): ?>
    <?php foreach ($ketQua as $ngay): ?>
        <div class="mon">
            <b>📅 Ngày <?= $ngay['ngay'] ?></b>
            <span class="tag"><?= $ngay['tong_calo'] ?> kcal — <?= $ngay['tong_protein'] ?> g protein</span>
            <ul style="margin: 8px 0 4px; padding-left: 18px;">
                <?php foreach ($ngay['buoi'] as $bua): ?>
                    <li>
                        <b><?= htmlspecialchars($bua['ten_bua']) ?>:</b> <?= htmlspecialchars($bua['mon']) ?>
                        — <?= $bua['calo'] ?> kcal, <?= $bua['protein'] ?> g protein
                    </li>
                <?php endforeach; ?>
            </ul>
            <i><?= htmlspecialchars($ngay['ghi_chu']) ?></i>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>

</body>
</html>