-- ============================================================================
-- 2026-08-20 名片收藏表 (business_card_favorites)
-- 让用户能收藏员工名片到自己的收藏夹
-- ============================================================================

CREATE TABLE IF NOT EXISTS business_card_favorites (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL COMMENT '收藏人 (登录用户)',
  card_user_id INT NOT NULL COMMENT '被收藏的员工 (名片所属人)',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_user_card (user_id, card_user_id),
  INDEX idx_user (user_id, created_at DESC),
  INDEX idx_card (card_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='名片收藏 (员工收藏其他员工的名片)';