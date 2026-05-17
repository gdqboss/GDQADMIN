-- Migration: Add images field to after_sale_records table
-- Date: 2026-03-01
-- Purpose: Support uploading up to 5 images for after-sale requests

ALTER TABLE after_sale_records
ADD COLUMN images JSON DEFAULT NULL COMMENT '售后图片数组 (最多5张)';
