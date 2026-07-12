-- ============================================================================
-- 一物一码扫码付款功能 (qrcode-pay) - 2026-08-14
-- 个人收款码模式：顾客扫码 → 看商品 + 收款码 → 自填金额 → 点"我已付款"
-- 后台管理员手动确认 → 触发 qrcodes 销售扣库存
-- ============================================================================

-- 1. 全局收款码配置（单行表）
CREATE TABLE IF NOT EXISTS qrcode_pay_config (
  id INT PRIMARY KEY DEFAULT 1,
  wechat_qr_image VARCHAR(500) COMMENT '微信收款码图片 URL',
  alipay_qr_image VARCHAR(500) COMMENT '支付宝收款码图片 URL',
  merchant_name VARCHAR(100) COMMENT '收款方名称（显示给顾客）',
  merchant_phone VARCHAR(50) COMMENT '收款方手机号（显示给顾客）',
  default_amount DECIMAL(10,2) DEFAULT NULL COMMENT '默认收款金额（NULL = 用商品 sale_price）',
  enabled TINYINT(1) DEFAULT 1 COMMENT '是否启用扫码付款',
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by VARCHAR(50) COMMENT '最后修改人'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT '一物一码全局收款码配置';

-- 插入默认配置（1 行）
INSERT IGNORE INTO qrcode_pay_config (id, merchant_name, enabled)
VALUES (1, '彩美特官方', 1);

-- 2. 顾客扫码下单记录
CREATE TABLE IF NOT EXISTS qrcode_customer_orders (
  id INT PRIMARY KEY AUTO_INCREMENT,
  order_no VARCHAR(50) UNIQUE NOT NULL COMMENT '订单号',
  qrcode_id INT NOT NULL COMMENT '关联 qrcodes.id',
  qrcode_code VARCHAR(50) NOT NULL COMMENT '冗余存码值，方便查询',
  customer_amount DECIMAL(10,2) NOT NULL COMMENT '顾客自填金额',
  quantity INT DEFAULT 1 COMMENT '购买数量',
  pay_method ENUM('wechat', 'alipay') NOT NULL COMMENT '顾客选择的支付方式',
  status ENUM('pending', 'confirmed', 'cancelled', 'expired') DEFAULT 'pending' COMMENT '订单状态',
  customer_note VARCHAR(500) COMMENT '顾客备注（可选）',
  customer_ip VARCHAR(45) COMMENT '顾客 IP（防滥用）',
  user_agent VARCHAR(500) COMMENT '顾客 UA',
  confirmed_at DATETIME COMMENT '后台确认时间',
  confirmed_by VARCHAR(50) COMMENT '后台确认人',
  cancel_reason VARCHAR(500) COMMENT '取消原因',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_qrcode_id (qrcode_id),
  INDEX idx_status (status),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT '顾客扫码下单记录';