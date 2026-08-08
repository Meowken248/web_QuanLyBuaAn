-- 1. Thêm cột goals vào bảng foods (nếu bạn chưa chạy file db_migration.php)
-- (Nếu MySQL báo lỗi cột đã tồn tại thì bạn có thể bỏ qua dòng này)
ALTER TABLE `foods` ADD COLUMN `goals` VARCHAR(255) NULL AFTER `season`;

-- 2. Chèn 8 món ăn mẫu vào cơ sở dữ liệu
INSERT INTO `foods` (`name`, `slug`, `ingredients`, `calories`, `protein`, `season`, `goals`) VALUES
('Canh chua cá lóc', 'canh-chua-ca-loc', 'cá lóc, me, cà chua, dứa, giá đỗ, đậu bắp', 220, 24, 'he,thu', 'giam_can,cai_thien_tieu_hoa'),
('Gà kho gừng', 'ga-kho-gung', 'thịt gà, gừng, nước mắm, đường', 310, 28, 'dong,thu', 'tang_can,cai_thien_tieu_hoa'),
('Gỏi cuốn tôm thịt', 'goi-cuon-tom-thit', 'tôm, thịt heo, bún, bánh tráng, rau sống', 180, 18, 'he,xuan', 'giam_can,cai_thien_tieu_hoa'),
('Lẩu nấm chay', 'lau-nam-chay', 'nấm, đậu hũ, cải thảo, nước dùng rau củ', 150, 10, 'dong,thu', 'giam_can,ho_tro_benh_ly'),
('Bò xào ớt chuông', 'bo-xao-ot-chuong', 'thịt bò, ớt chuông, hành tây, tỏi', 280, 30, 'xuan,he,thu,dong', 'tang_can,toi_uu_hieu_suat'),
('Salad ức gà', 'salad-uc-ga', 'ức gà, xà lách, cà chua bi, dầu olive', 210, 32, 'xuan,he', 'giam_can,toi_uu_hieu_suat'),
('Cá hồi áp chảo sốt chanh dây', 'ca-hoi-ap-chao-sot-chanh-day', 'cá hồi, chanh dây, bơ, măng tây', 320, 29, 'xuan,he,thu', 'toi_uu_hieu_suat,ho_tro_benh_ly'),
('Yến mạch trứng chiên rau củ', 'yen-mach-trung-chien-rau-cu', 'yến mạch, trứng gà, cà rốt, hành lá', 260, 16, 'xuan,he,thu,dong', 'cai_thien_tieu_hoa,toi_uu_hieu_suat');
