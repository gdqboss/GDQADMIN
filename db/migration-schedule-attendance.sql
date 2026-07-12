-- 排班表增加必须出勤标记
ALTER TABLE shift_schedules ADD COLUMN attendance_required TINYINT(1) DEFAULT 1 COMMENT '是否必须出勤';
