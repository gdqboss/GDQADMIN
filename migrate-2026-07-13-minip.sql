-- 2026-07-13 创建 minip 闭环所需 3 张表
-- 表 1: minip_employees 员工表
CREATE TABLE IF NOT EXISTS `minip_employees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL COMMENT '关联 users.id',
  `employee_code` varchar(50) DEFAULT NULL COMMENT '员工编号',
  `employee_name` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive','resigned') DEFAULT 'active',
  `hired_at` date DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_employee_code` (`employee_code`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='minip 企业员工表';

-- 表 2: minip_wallet_transactions 钱包流水表
CREATE TABLE IF NOT EXISTS `minip_wallet_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `type` varchar(50) NOT NULL COMMENT '类型: expense_refund / income / withdraw',
  `amount` decimal(15,2) NOT NULL COMMENT '金额 (正=入账)',
  `balance_after` decimal(15,2) DEFAULT NULL COMMENT '交易后余额',
  `source_type` varchar(50) DEFAULT NULL COMMENT '来源: expense / salary / refund',
  `source_id` int(11) DEFAULT NULL,
  `source_no` varchar(100) DEFAULT NULL,
  `remark` varchar(500) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_type` (`type`),
  KEY `idx_source` (`source_type`, `source_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='minip 钱包流水';

-- 表 3: notifications 通用通知表
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='通用通知';
