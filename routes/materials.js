/**
 * 材料管理API
 * 路由: /api/materials
 */
import { Router } from 'express'
import { pool } from '../db/connection.js'
import { translateFields } from '../services/translation.js'

const router = Router()

// 获取所有材料类目
router.get('/categories', async (req, res) => {
    try {
        const [rows] = await pool.query('SELECT mc.*, s.name AS supplier_name, s.type AS supplier_type FROM material_categories mc LEFT JOIN suppliers s ON mc.supplier_id = s.id ORDER BY mc.id DESC');
        if (req.lang === 'en') {
            await translateFields(rows, ['name', 'unit', 'remark'])
        }
        res.json({ success: true, data: rows });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

// 添加材料类目
router.post('/categories', async (req, res) => {
    try {
        const { name, unit, remark, supplier_id } = req.body;
        if (!name) return res.status(400).json({ success: false, error: '名称不能为空' });
        
        const [result] = await pool.query(
            'INSERT INTO material_categories (name, unit, remark, supplier_id) VALUES (?, ?, ?, ?)',
            [name, unit || '', remark || '', supplier_id || null]
        );
        res.json({ success: true, id: result.insertId });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

// 更新材料类目
router.put('/categories/:id', async (req, res) => {
    try {
        const { id } = req.params;
        const { name, unit, remark, supplier_id } = req.body;
        await pool.query(
            'UPDATE material_categories SET name=?, unit=?, remark=?, supplier_id=? WHERE id=?',
            [name, unit || '', remark || '', supplier_id || null, id]
        );
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

// 删除材料类目
router.delete('/categories/:id', async (req, res) => {
    try {
        const { id } = req.params;
        await pool.query('DELETE FROM material_categories WHERE id=?', [id]);
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

// 获取商品的材料组成（支持SKU维度）
router.get('/product/:productId', async (req, res) => {
    try {
        const { productId } = req.params;
        const { sku_id } = req.query;
        let sql = `SELECT pm.*, mc.name AS material_name, mc.unit,
                   ps.sku AS sku_code, ps.specs AS sku_specs, ps.sku_key
             FROM product_materials pm 
             JOIN material_categories mc ON pm.material_id = mc.id
             LEFT JOIN product_skus ps ON pm.sku_id = ps.id
             WHERE pm.product_id = ?`;
        const params = [productId];
        if (sku_id !== undefined) {
            if (sku_id === 'null' || sku_id === '') {
                sql += ' AND pm.sku_id IS NULL';
            } else {
                sql += ' AND pm.sku_id = ?';
                params.push(sku_id);
            }
        }
        sql += ' ORDER BY pm.sku_id IS NULL DESC, pm.sku_id, pm.id';
        const [rows] = await pool.query(sql, params);
        if (req.lang === 'en') {
            await translateFields(rows, ['material_name', 'unit'])
        }
        res.json({ success: true, data: rows });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

// 获取商品的SKU列表（用于材料编辑）
router.get('/product/:productId/skus', async (req, res) => {
    try {
        const { productId } = req.params;
        const [rows] = await pool.query(
            'SELECT id, sku, specs, sku_key FROM product_skus WHERE product_id = ? AND status = "active" ORDER BY id',
            [productId]
        );
        // 解析 specs JSON
        const skus = rows.map(r => ({
            ...r,
            specs: r.specs ? (typeof r.specs === 'string' ? JSON.parse(r.specs) : r.specs) : {}
        }));
        res.json({ success: true, data: skus });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

// 设置商品的材料组成（支持SKU维度）
router.post('/product/:productId', async (req, res) => {
    try {
        const { productId } = req.params;
        const { materials, sku_id } = req.body;
        // sku_id: 可为null（所有SKU共用）、或具体sku_id（指定SKU）
        // 如果sku_id为null或undefined，清除该product下sku_id=null的配方
        const delSkuId = sku_id === undefined || sku_id === null ? null : sku_id;
        await pool.query('DELETE FROM product_materials WHERE product_id = ? AND sku_id ' + (delSkuId === null ? 'IS NULL' : '= ?'), delSkuId === null ? [productId] : [productId, delSkuId]);
        
        if (materials && materials.length > 0) {
            for (const m of materials) {
                await pool.query(
                    'INSERT INTO product_materials (product_id, sku_id, material_id, quantity) VALUES (?, ?, ?, ?)',
                    [productId, delSkuId, m.material_id, m.quantity]
                );
            }
        }
        
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

// 批量设置商品材料（覆盖所有SKU配方）
router.post('/product/:productId/batch', async (req, res) => {
    try {
        const { productId } = req.params;
        const { shared_materials, sku_materials } = req.body;
        // shared_materials: 所有SKU共用配方 [{material_id, quantity}]
        // sku_materials: 按SKU分别配置 [{sku_id, materials: [{material_id, quantity}]}]
        
        const conn = await pool.getConnection();
        try {
            await conn.beginTransaction();
            
            // 清除该product所有配方
            await conn.query('DELETE FROM product_materials WHERE product_id = ?', [productId]);
            
            // 写入共用配方
            if (shared_materials && shared_materials.length > 0) {
                for (const m of shared_materials) {
                    await conn.query(
                        'INSERT INTO product_materials (product_id, sku_id, material_id, quantity) VALUES (?, NULL, ?, ?)',
                        [productId, m.material_id, m.quantity]
                    );
                }
            }
            
            // 写入各SKU配方
            if (sku_materials && sku_materials.length > 0) {
                for (const skuItem of sku_materials) {
                    for (const m of skuItem.materials) {
                        await conn.query(
                            'INSERT INTO product_materials (product_id, sku_id, material_id, quantity) VALUES (?, ?, ?, ?)',
                            [productId, skuItem.sku_id, m.material_id, m.quantity]
                        );
                    }
                }
            }
            
            await conn.commit();
            res.json({ success: true });
        } catch (e) {
            await conn.rollback();
            throw e;
        } finally {
            conn.release();
        }
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

export default router;
