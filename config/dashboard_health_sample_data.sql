-- Dữ liệu mẫu cho dashboard sức khỏe theo giờ
-- Chỉ thêm vào những giờ chưa có dữ liệu của ngày hiện tại.
USE meal_health_manager;

INSERT IGNORE INTO health_hourly_logs
(user_id, log_date, log_hour, water_ml, steps, active_minutes, calories_burned, heart_rate, sleep_minutes, mood_level, note)
SELECT u.id, CURDATE(), sample.log_hour, sample.water_ml, sample.steps,
       sample.active_minutes, sample.calories_burned, sample.heart_rate,
       sample.sleep_minutes, sample.mood_level, sample.note
FROM users u
CROSS JOIN (
    SELECT 0 log_hour, 0 water_ml, 0 steps, 0 active_minutes, 0.00 calories_burned, 58 heart_rate, 60 sleep_minutes, NULL mood_level, 'Dữ liệu mẫu: ngủ' note
    UNION ALL SELECT 1, 0, 0, 0, 0.00, 57, 60, NULL, 'Dữ liệu mẫu: ngủ'
    UNION ALL SELECT 2, 0, 0, 0, 0.00, 56, 60, NULL, 'Dữ liệu mẫu: ngủ'
    UNION ALL SELECT 3, 0, 0, 0, 0.00, 56, 60, NULL, 'Dữ liệu mẫu: ngủ'
    UNION ALL SELECT 4, 0, 0, 0, 0.00, 57, 60, NULL, 'Dữ liệu mẫu: ngủ'
    UNION ALL SELECT 5, 0, 0, 0, 0.00, 59, 60, NULL, 'Dữ liệu mẫu: ngủ'
    UNION ALL SELECT 6, 200, 100, 0, 0.00, 64, 30, 3, 'Dữ liệu mẫu: thức dậy'
    UNION ALL SELECT 8, 350, 900, 10, 50.00, 78, 0, 4, 'Dữ liệu mẫu: đi bộ buổi sáng'
    UNION ALL SELECT 12, 450, 1800, 15, 85.00, 88, 0, 4, 'Dữ liệu mẫu: vận động buổi trưa'
    UNION ALL SELECT 16, 350, 1500, 10, 60.00, 82, 0, 4, 'Dữ liệu mẫu: đi bộ buổi chiều'
    UNION ALL SELECT 19, 400, 2400, 25, 150.00, 96, 0, 4, 'Dữ liệu mẫu: tập luyện'
    UNION ALL SELECT 22, 250, 800, 5, 35.00, 76, 0, 4, 'Dữ liệu mẫu: thư giãn'
) sample
WHERE u.role = 'user' AND u.status = 'active';
