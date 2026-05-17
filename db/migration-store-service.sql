-- Migration: Add service_user_id fields for customer service binding
-- Date: 2026-03-01
-- Purpose: Support binding customer service to stores and qrcodes

ALTER TABLE stores
ADD COLUMN service_user_id INT DEFAULT NULL COMMENT '关联客服（系统用户ID）';

ALTER TABLE qrcodes
ADD COLUMN service_user_id INT DEFAULT NULL COMMENT '绑定客服（系统用户ID）';

CREATE INDEX idx_stores_service ON stores(service_user_id);
CREATE INDEX idx_qrcodes_service ON qrcodes(service_user_id);
