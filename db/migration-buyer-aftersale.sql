-- Migration: Add buyer_phone field to qrcodes table
-- Date: 2026-03-01
-- Purpose: Track buyer phone for after-sale status display

ALTER TABLE qrcodes
ADD COLUMN buyer_phone VARCHAR(20) DEFAULT NULL COMMENT '购买者手机号';

CREATE INDEX idx_qrcode_buyer_phone ON qrcodes(buyer_phone);
