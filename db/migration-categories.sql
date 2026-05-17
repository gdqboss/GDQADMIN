-- migration-categories.sql
-- 四级分类层级迁移（存储过程防重复执行）

DELIMITER $$

DROP PROCEDURE IF EXISTS _mig_categories$$

CREATE PROCEDURE _mig_categories()
BEGIN
  -- 1. categories 加层级字段
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories' AND COLUMN_NAME = 'parent_id'
  ) THEN
    ALTER TABLE categories
      ADD COLUMN parent_id INT DEFAULT NULL,
      ADD COLUMN level INT NOT NULL DEFAULT 1,
      ADD COLUMN sort_order INT NOT NULL DEFAULT 0;

    ALTER TABLE categories
      ADD CONSTRAINT fk_categories_parent
      FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE CASCADE;
  END IF;

  -- 2. products 加 category_id
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'category_id'
  ) THEN
    ALTER TABLE products
      ADD COLUMN category_id INT DEFAULT NULL;

    ALTER TABLE products
      ADD CONSTRAINT fk_products_category
      FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL;
  END IF;
END$$

DELIMITER ;

CALL _mig_categories();
DROP PROCEDURE IF EXISTS _mig_categories;

-- 3. 反填 category_id（名称匹配的顶级分类）
UPDATE products p
  INNER JOIN categories c ON c.name = p.category AND c.parent_id IS NULL
SET p.category_id = c.id
WHERE p.category_id IS NULL AND p.category IS NOT NULL AND p.category != '';
