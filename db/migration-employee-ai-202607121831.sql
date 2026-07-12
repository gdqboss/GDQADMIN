-- ============================================================
-- 员工信息中台 + AI 智能体 4 层架构 — Layer 1 数据基础
-- 生成时间: 2026-07-12 18:31
-- 涵盖: 1.1 worker_profiles / 1.2 jobsites / 1.3 user_hierarchy 填充 / 1.4 users.department_id
-- ============================================================

-- ------------------------------------------------
-- 1.1 worker_profiles — 工人档案
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS worker_profiles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL COMMENT '对应 users.id',
  skills JSON DEFAULT NULL COMMENT '技能标签 JSON 数组: ["砌墙","贴瓷砖","水电"]',
  skill_level ENUM('rookie','junior','senior','master') DEFAULT 'rookie' COMMENT '综合技能等级',
  hourly_rate DECIMAL(8,2) DEFAULT 0 COMMENT '时薪(元)',
  piece_rate JSON DEFAULT NULL COMMENT '计件单价 JSON: {砌墙: 50, 贴瓷砖: 80}',
  monthly_salary DECIMAL(10,2) DEFAULT 0 COMMENT '月薪(元) — 与 hourly_rate 二选一',
  payment_type ENUM('hourly','piece','monthly','mixed') DEFAULT 'hourly' COMMENT '工资结算方式',
  current_jobsite_id INT DEFAULT NULL COMMENT '当前所在工地 jobsites.id',
  total_work_hours DECIMAL(10,2) DEFAULT 0 COMMENT '累计工时(自动采集)',
  total_pieces INT DEFAULT 11 DEFAULT 0 COMMENT '累计完成件数',
  efficiency_score DECIMAL(5,2) DEFAULT 0 COMMENT '效率分 0-100',
  quality_score DECIMAL(5,2) DEFAULT 0 COMMENT '质量分 0-100',
  impact_score DECIMAL(5,2) DEFAULT 0 COMMENT '影响力分(日志阅读率/被点赞)',
  id_card VARCHAR(18) DEFAULT NULL COMMENT '身份证号',
  emergency_contact VARCHAR(50) DEFAULT NULL COMMENT '紧急联系人',
  emergency_phone VARCHAR(20) DEFAULT NULL COMMENT '紧急联系电话',
  certificates JSON DEFAULT NULL COMMENT '证件 JSON: {高空作业证: {到期: 2027-06, 编号: xxx}}',
  contract_start DATE DEFAULT NULL COMMENT '合同起始日',
  contract_end DATE DEFAULT NULL COMMENT '合同到期日',
  employment_status ENUM('active','probation','leave','resigned') DEFAULT 'active' COMMENT '在职状态',
  hired_at DATE DEFAULT NULL COMMENT '入职日期',
  resigned_at DATE DEFAULT NULL COMMENT '离职日期',
  notes TEXT DEFAULT NULL COMMENT '备注',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_user (user_id),
  KEY idx_jobsite (current_jobsite_id),
  KEY idx_skill_level (skill_level),
  KEY idx_employment (employment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT '工人档案';

-- ------------------------------------------------
-- 1.2 jobsites — 工地主表
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS jobsites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL COMMENT '工地编号 (工地-001)',
  name VARCHAR(200) NOT NULL COMMENT '工地名称',
  address VARCHAR(500) DEFAULT NULL COMMENT '详细地址',
  client_name VARCHAR(200) DEFAULT NULL COMMENT '业主/客户名称',
  client_phone VARCHAR(20) DEFAULT NULL COMMENT '业主联系电话',
  manager_user_id INT DEFAULT NULL COMMENT '工地负责人 user_id (班组长/主管)',
  supervisor_user_id INT DEFAULT NULL COMMENT '监理 user_id',
  start_date DATE DEFAULT NULL COMMENT '开工日期',
  expected_end_date DATE DEFAULT NULL COMMENT '预计竣工日期',
  actual_end_date DATE DEFAULT NULL COMMENT '实际竣工日期',
  contract_amount DECIMAL(12,2) DEFAULT 0 COMMENT '合同金额',
  status ENUM('planning','active','paused','completed','cancelled') DEFAULT 'planning' COMMENT '工地状态',
  type ENUM('decoration','repair','new_build','maintenance') DEFAULT 'decoration' COMMENT '工地类型',
  area_sqm DECIMAL(10,2) DEFAULT NULL COMMENT '建筑面积(平方米)',
  gps_lat DECIMAL(10,7) DEFAULT NULL COMMENT '工地 GPS 纬度',
  gps_lng DECIMAL(10,7) DEFAULT NULL COMMENT '工地 GPS 经度',
  gps_radius_m INT DEFAULT 100 COMMENT '打卡有效半径(米)',
  blueprints JSON DEFAULT NULL COMMENT '设计图 JSON 数组: [{name,url,uploaded_at}]',
  contract_docs JSON DEFAULT NULL COMMENT '合同文档 JSON 数组',
  required_workers INT DEFAULT 1 COMMENT '需求工人数',
  required_skills JSON DEFAULT NULL COMMENT '需求技能标签 JSON',
  progress_percent INT DEFAULT 0 COMMENT '进度百分比 0-100',
  notes TEXT DEFAULT NULL COMMENT '备注',
  created_by INT DEFAULT NULL COMMENT '创建人 user_id',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_code (code),
  KEY idx_status (status),
  KEY idx_manager (manager_user_id),
  KEY idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT '工地主表';

-- ------------------------------------------------
-- 1.4 users 加 department_id 外键
-- ------------------------------------------------
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = 'gdq' AND TABLE_NAME = 'users' AND COLUMN_NAME = 'department_id');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE users ADD COLUMN department_id INT DEFAULT NULL COMMENT ''所属部门 ID, 关联 departments.id'' AFTER role, ADD KEY idx_department (department_id)',
  'SELECT ''department_id already exists'' AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ------------------------------------------------
-- 1.3 user_hierarchy 填充测试数据 (江清波→主管→班组长→工人)
-- ------------------------------------------------
-- 假设现有 users.id 最大值,避免冲突;此处填充 4 层组织
-- 注: 如果 user_hierarchy 已存在数据,只填充缺失的关键角色
INSERT IGNORE INTO user_hierarchy (name, role, level, parent_id, total_commission, total_points) VALUES
('BOSS', 'boss', 0, NULL, 0, 0),
('总经理', 'manager', 1, 1, 0, 0),
('工地主管', 'supervisor', 2, 2, 0, 0),
('水电班组长', 'foreman', 3, 3, 0, 0),
('泥瓦班组长', 'foreman', 3, 3, 0, 0),
('木工班组长', 'foreman', 3, 3, 0, 0),
('工人-A组', 'worker', 4, 4, 0, 0),
('工人-B组', 'worker', 4, 5, 0, 0),
('工人-C组', 'worker', 4, 6, 0, 0);

-- 1.5 测试工地
INSERT IGNORE INTO jobsites (code, name, address, client_name, client_phone, manager_user_id, start_date, expected_end_date, contract_amount, status, type, area_sqm, gps_lat, gps_lng, required_workers, required_skills, progress_percent)
VALUES
('JS-2026-001', '横琴湾花园 3 栋装修', '珠海横琴新区湾仔南路 88 号 3 栋 1501', '张老板', '13900000001', NULL, '2026-07-01', '2026-09-30', 180000.00, 'active', 'decoration', 120.50, 22.1234567, 113.5432100, 5, JSON_ARRAY('砌墙','贴瓷砖','水电','木工'), 15),
('JS-2026-002', '深圳南山大冲旧改', '深圳南山区大冲社区 A 区 12 号', '李总', '13900000002', NULL, '2026-07-10', '2026-10-15', 250000.00, 'active', 'decoration', 180.00, 22.5450000, 113.9230000, 6, JSON_ARRAY('砌墙','贴瓷砖','水电','木工','油漆'), 5),
('JS-2026-003', '东莞塘厦维修工程', '东莞塘厦镇塘龙西路 12 号', '王先生', '13900000003', NULL, '2026-07-15', '2026-08-10', 45000.00, 'active', 'repair', 60.00, 22.7800000, 114.0700000, 2, JSON_ARRAY('水电','木工'), 30);

-- 完成标记
SELECT 'employee-ai layer-1 migration applied' AS status;