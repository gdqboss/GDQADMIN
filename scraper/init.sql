-- Scrapling 抓取模块 — 江小鱼 2026-09-13 加
-- 跟其它 GDQ 模块风格统一, 用 IF NOT EXISTS 幂等
--
-- GDQ 的 RBAC 是「细粒度权限 + 角色 → 权限映射」模型。
-- 现有 rbac_roles 实际只有 manager / butler / customer_service / shopkeeper 等
-- (没有传统意义的 admin/superadmin), 而 users.role 字段是 admin/manager 这种。
-- 所以这里同时给「管理类角色」(rbac_roles.name LIKE 'manager'/%admin%/butler/dispatcher)
-- + 简单方案: 找 users 表 role IN (admin,superadmin,manager) 的人, 给他们的 user_id
-- 直接补 rbac_role_permissions 关联不到, 所以走精细模式:
--   把所有现有非「纯前台」角色都加 scraper:read (经理/调度/客服)
--   给 manager/butler/dispatcher 加 scraper:write/run/delete

CREATE TABLE IF NOT EXISTS `scraper_jobs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(200) NOT NULL COMMENT '任务名(人类可读)',
  `url` VARCHAR(2000) NOT NULL COMMENT '目标 URL',
  `selector` VARCHAR(500) NOT NULL COMMENT 'CSS/XPath/text 表达式',
  `selector_type` ENUM('css','xpath','text') NOT NULL DEFAULT 'css',
  `fetch_mode` ENUM('fetcher','dynamic','stealthy') NOT NULL DEFAULT 'fetcher'
    COMMENT 'fetcher=HTTP fast / dynamic=JS render / stealthy=CF bypass',
  `headers` JSON DEFAULT NULL COMMENT '附加 HTTP 头',
  `cron` VARCHAR(64) DEFAULT NULL COMMENT '可选 cron 表达式(预留, 当前用手动跑)',
  `status` ENUM('active','paused','disabled') NOT NULL DEFAULT 'active',
  `last_run_at` DATETIME DEFAULT NULL,
  `last_status` ENUM('success','failed','never') NOT NULL DEFAULT 'never',
  `last_error` TEXT DEFAULT NULL,
  `created_by` INT DEFAULT NULL COMMENT '创建人 user_id',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_status` (`status`),
  INDEX `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Scrapling 抓取任务';

CREATE TABLE IF NOT EXISTS `scraper_results` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `job_id` INT NOT NULL,
  `items_count` INT NOT NULL DEFAULT 0,
  `items_preview` JSON DEFAULT NULL COMMENT '前 20 条结果(预览, 防大对象撑爆表)',
  `elapsed_ms` INT DEFAULT NULL,
  `http_status` INT DEFAULT NULL,
  `final_url` VARCHAR(2000) DEFAULT NULL,
  `status` ENUM('success','failed') NOT NULL,
  `error` TEXT DEFAULT NULL,
  `trace` MEDIUMTEXT DEFAULT NULL COMMENT '失败 stack trace',
  `run_by` INT DEFAULT NULL COMMENT '手动跑的人 user_id',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_job_id` (`job_id`),
  INDEX `idx_created_at` (`created_at`),
  CONSTRAINT `fk_scraper_results_job` FOREIGN KEY (`job_id`)
    REFERENCES `scraper_jobs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Scrapling 抓取结果历史';

-- RBAC 权限 seed — rbac_permissions 真实列: name/label/category/description
INSERT IGNORE INTO `rbac_permissions` (`name`, `label`, `category`, `description`)
VALUES
  ('scraper:read',    '查看抓取任务',  'scraper', '查看抓取任务和结果'),
  ('scraper:write',   '编辑抓取任务',  'scraper', '创建/修改抓取任务'),
  ('scraper:run',     '执行抓取任务',  'scraper', '手动执行抓取任务'),
  ('scraper:delete',  '删除抓取任务',  'scraper', '删除抓取任务');

-- 给「写类」角色全权限: manager / butler / dispatcher / enterprise-admin / reviewer
-- 这些是 GDQ 后台管理岗
INSERT IGNORE INTO `rbac_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `rbac_roles` r, `rbac_permissions` p
WHERE r.name IN ('manager','butler','dispatcher','enterprise-admin','reviewer')
  AND p.name IN ('scraper:read','scraper:write','scraper:run','scraper:delete');

-- 给「前台」类角色只读: shopkeeper / foreman / worker / customer_service / repairer
-- (数据敏感, 只能看不能改/跑/删)
INSERT IGNORE INTO `rbac_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `rbac_roles` r, `rbac_permissions` p
WHERE r.name IN ('shopkeeper','foreman','worker','customer_service','repairer','member')
  AND p.name = 'scraper:read';

-- 测试角色也给读 (跟其它模块保持一致)
INSERT IGNORE INTO `rbac_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `rbac_roles` r, `rbac_permissions` p
WHERE r.name IN ('tester','test_role_1','experience','Warehouse','no_log')
  AND p.name = 'scraper:read';