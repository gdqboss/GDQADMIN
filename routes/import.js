import { Router } from 'express';
import multer from 'multer';
import XLSX from 'xlsx';
import { createHash } from 'crypto';
import { pool } from '../db/connection.js';

const router = Router();

const storage = multer.memoryStorage();
const upload = multer({ storage, limits: { fileSize: 50 * 1024 * 1024 } });

// 颜色词典（从数据中提炼）
const COLOR_MAP = {
  // 纯色
  'BRONZE': 'BRONZE', 'BLACK': 'BLACK', 'BROWN': 'BROWN', 'WHITE': 'WHITE',
  'RED': 'RED', 'BLUE': 'BLUE', 'GREEN': 'GREEN', 'YELLOW': 'YELLOW',
  'ORANGE': 'ORANGE', 'PURPLE': 'PURPLE', 'PINK': 'PINK', 'GRAY': 'GRAY',
  'GOLD': 'GOLD', 'SILVER': 'SILVER',
  // 复合色
  'RGOLD': 'ROSE GOLD', 'DGRAY': 'DARK GRAY', 'IGRAY': 'IRON GRAY',
  'DBLUE': 'DARK BLUE', 'VIOLET': 'VIOLET', 'AGREEN': 'ARMY GREEN',
  'NAVY': 'NAVY', 'BEIGE': 'BEIGE', 'BURGUNDY': 'BURGUNDY',
  'MILITARY': 'MILITARY GREEN', 'KHAKI': 'KHAKI',
  // 通用变体
  'DARK': 'DARK', 'LIGHT': 'LIGHT',
};

// 按长度降序排列颜色词（优先匹配长词）
let COLOR_PATTERNS = null;
function getColorPatterns() {
  if (COLOR_PATTERNS) return COLOR_PATTERNS;
  const sorted = Object.keys(COLOR_MAP).sort((a, b) => b.length - a.length);
  COLOR_PATTERNS = sorted;
  return sorted;
}

function extractModel(name) {
  if (!name) return null;
  const match = String(name).match(/\d{6}[A-Za-z]/);
  return match ? match[0] : null;
}

/**
 * 智能解析产品描述
 * 输入: "VOYAGER HARDCASE LUGGAGE 551563M 28 BRONZE 551563M"
 * 输出: {
 *   product_name: "VOYAGER HARDCASE LUGGAGE",
 *   model: "551563M",
 *   size: "28",
 *   color: "BRONZE",
 *   color_display: "ROSE GOLD"  // 人性化颜色名
 * }
 */
function parseProductDescription(desc) {
  if (!desc) return { product_name: desc, model: null, size: null, color: null, color_display: null };

  const original = String(desc).trim();
  let remaining = original;
  let model = null;
  let size = null;
  let color = null;

  // 1. 提取型号 (6位数字+字母)
  const modelMatch = remaining.match(/\d{6}[A-Za-z]/);
  if (modelMatch) {
    model = modelMatch[0];
    remaining = remaining.replace(model, ' ').replace(/\s+/g, ' ').trim();
  }

  // 2. 提取颜色（优先长词匹配）
  const colorPatterns = getColorPatterns();
  for (const colorKey of colorPatterns) {
    const idx = remaining.toUpperCase().indexOf(colorKey);
    if (idx !== -1) {
      color = colorKey;
      // 颜色前面的数字可能是尺寸
      const beforeColor = remaining.substring(0, idx).trim();
      const sizeMatch = beforeColor.match(/\b(\d+)\s*$/);
      if (sizeMatch) {
        size = sizeMatch[1];
        // 去掉尺寸
        remaining = beforeColor.replace(sizeMatch[0], '').trim() + remaining.substring(idx + colorKey.length);
      } else {
        remaining = beforeColor.trim() + remaining.substring(idx + colorKey.length);
      }
      remaining = remaining.replace(/\s+/g, ' ').trim();
      break;
    }
  }

  // 3. 如果还没找到颜色，再尝试 "数字+颜色" 的模式（如 "28 BROWN"）
  if (!color) {
    const sizeColorMatch = remaining.match(/\b(\d+)\s+([A-Z]{2,})(?:\s+\d|$)/i);
    if (sizeColorMatch) {
      size = sizeColorMatch[1];
      color = sizeColorMatch[2].toUpperCase();
      remaining = remaining.replace(sizeColorMatch[0], '').replace(/\s+/g, ' ').trim();
    }
  }

  // 4. 清理末尾残留的型号数字（如 "28" 后面还跟着 "551563M"）
  remaining = remaining.replace(/\s+\d{6}[A-Za-z]\s*$/i, '').trim();
  remaining = remaining.replace(/\s{2,}/g, ' ').trim();

  return {
    product_name: remaining || original,
    model,
    size,
    color,
    color_display: color ? (COLOR_MAP[color] || color) : null,
  };
}

async function getProductImage(sku, model) {
  try {
    if (sku) {
      const [rows] = await pool.query(
        'SELECT image_main FROM products WHERE sku = ? AND image_main IS NOT NULL AND image_main != "" LIMIT 1',
        [String(sku)]
      );
      if (rows.length) return rows[0].image_main;
    }
    if (model) {
      const [rows] = await pool.query(
        'SELECT image_main FROM products WHERE name LIKE ? AND image_main IS NOT NULL AND image_main != "" LIMIT 1',
        [`%${model}%`]
      );
      if (rows.length) return rows[0].image_main;
    }
  } catch (err) {
    console.error('getProductImage error:', err.message);
  }
  return null;
}

async function matchStore(storeName) {
  if (!storeName) return { store_code: null, store_name: null };
  try {
    const [rows] = await pool.query(
      'SELECT store_code, name FROM stores WHERE name LIKE ? OR store_code = ? LIMIT 1',
      [`%${storeName}%`, storeName]
    );
    if (rows.length) return { store_code: rows[0].store_code, store_name: rows[0].name };
  } catch (err) {
    console.error('matchStore error:', err.message);
  }
  return { store_code: storeName, store_name: storeName };
}

// POST /api/import/upload
router.post('/upload', upload.single('file'), async (req, res) => {
  try {
    if (!req.file) {
      return res.status(400).json({ success: false, message: 'No file uploaded' });
    }

    const { file_type = 'SM' } = req.body;
    const fileBuffer = req.file.buffer;
    const fileName = req.file.originalname;

    // ① 计算文件内容 hash（去重用：同一文件+同类型=同 hash）
    const contentHash = createHash('sha256').update(fileBuffer).digest('hex');

    // ② 查重：同 file_type + 同 content_hash 的旧记录
    const [duplicates] = await pool.query(
      'SELECT id FROM imported_excel_records WHERE file_type = ? AND content_hash = ? ORDER BY uploaded_at DESC',
      [file_type, contentHash]
    );
    if (duplicates.length > 0) {
      const dupIds = duplicates.map(d => d.id);
      // 删除旧 items
      await pool.query('DELETE FROM imported_excel_items WHERE record_id IN (?)', [dupIds]);
      // 删除旧 records
      await pool.query('DELETE FROM imported_excel_records WHERE id IN (?)', [dupIds]);
      console.log(`[DEDUP] removed ${dupIds.length} duplicate record(s) for ${fileName} (hash ${contentHash.slice(0,8)}...)`);
    }

    const workbook = XLSX.read(fileBuffer, { type: 'buffer', cellDates: true });
    const sheetName = workbook.SheetNames[0];
    const sheet = workbook.Sheets[sheetName];
    const data = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '' });

    if (data.length < 4) {
      return res.status(400).json({ success: false, message: 'Excel data too short' });
    }

    let items = [];
    let totalAmount = 0;

    // 智能检测flp格式（row0第0列包含"Document"或"Title"的是flp，第0列包含"SALES"的是标准SM）
function detectFlpFormat(data) {
  if (data.length < 2) return false
  const r0 = String(data[0][0] || '').toUpperCase()
  return r0.includes('DOCUMENT') || r0.includes('TITLE')
}

// 从headers行查找日期列索引
function findDateColIndex(headersRow) {
  if (!headersRow || !Array.isArray(headersRow)) return -1
  for (let j = 0; j < headersRow.length; j++) {
    const h = String(headersRow[j] || '').toLowerCase()
    if (/^(date|time|日期|时间|sale_date|transaction_date|post_date)$/i.test(h)) return j
  }
  return -1
}

// 提取日期值，兼容Excel serial/Date对象/字符串
function extractSaleDate(val) {
  if (!val) return null
  if (val instanceof Date) return val.toISOString().slice(0, 19).replace('T', ' ')
  const n = Number(val)
  if (!isNaN(n) && n > 25000 && n < 60000) {
    // Excel serial date (1900 epoch)
    const d = new Date((n - 25569) * 86400 * 1000)
    return d.toISOString().slice(0, 19).replace('T', ' ')
  }
  const s = String(val).trim().slice(0, 19)
  if (/^\d{4}[-/]\d{2}[-/]\d{2}/.test(s)) return s.replace(/\//g, '-')
  return null
}

// SM格式 - flp.xlsx专用解析（从row1开始，col5=门店, col8=SKU, col9=Description, col12=QTY）
async function parseFlpSmFormat(data) {
  const items = []
  let totalAmount = 0
  const headerRow = data[0] || []
  const idxDate = findDateColIndex(headerRow)
  for (let i = 1; i < data.length; i++) {
    const row = data[i]
    if (!row || row.length < 13) continue
    const skuVal = row[8]
    const sku = String(skuVal || '').trim()
    if (!sku || sku === 'undefined' || sku === '0') continue
    if (isNaN(parseInt(skuVal))) continue // 必须是可以转成数字的SKU
    const description = String(row[9] || '').trim()
    const parsed = parseProductDescription(description)
    const model = parsed.model
    const qty = parseFloat(row[12] || 1) || 1
    const price = 0 // SM格式无单价列
    const amount = 0
    const storeName = String(row[5] || '').trim()
    const storeInfo = storeName ? { store_code: storeName, store_name: storeName } : { store_code: null, store_name: null }
    const imageUrl = await getProductImage(sku, model)
    items.push({
      sku, product_name: parsed.product_name, model,
      store_code: storeInfo.store_code, store_name: storeInfo.store_name,
      quantity: qty, unit_price: 0, amount: 0,
      color: parsed.color, size: parsed.size, sale_date: extractSaleDate(idxDate >= 0 ? row[idxDate] : null),
      image_url: imageUrl,
      extra_data: { source_row: i, original_name: description, color_display: parsed.color_display }
    })
  }
  return { items, totalAmount }
}

if (file_type === 'SM') {
  // 先检测是否是flp格式（row0=14列全列头）
  if (detectFlpFormat(data)) {
    const result = await parseFlpSmFormat(data)
    items = result.items
    totalAmount = result.totalAmount
  } else {
    // 标准SM格式（row0=row1=row2=vendor信息，row3+=数据）
    const header = data[1];
    const skuIdx = header.findIndex(h => String(h).toUpperCase().includes('SKU'));
    const descIdx = header.findIndex(h => String(h).toUpperCase().includes('DESCRIPTION'));
    const priceIdx = header.findIndex(h => String(h).toUpperCase().includes('PRICE'));
    const storeNameIdx = header.findIndex(h => String(h).toUpperCase().includes('STORE') || String(h).toUpperCase().includes('LOCATION'));
    const idxDate = findDateColIndex(header);

    for (let i = 3; i < data.length; i++) {
      const row = data[i];
      if (!row || row.length < 5) continue;
        
      const skuVal = skuIdx >= 0 ? row[skuIdx] : row[8];
      const sku = String(skuVal || '').trim();
      if (!sku || sku === 'undefined' || sku === '0') continue;

      const description = descIdx >= 0 ? String(row[descIdx] || '').trim() : '';
      const parsed = parseProductDescription(description);
      const model = parsed.model;
      const price = parseFloat(row[priceIdx] || row[9] || 0) || 0;
      const qty = 1;
      const amount = price * qty;
      totalAmount += amount;

      const storeNameVal = storeNameIdx >= 0 ? row[storeNameIdx] : null;
      const storeInfo = storeNameVal ? await matchStore(String(storeNameVal)) : { store_code: null, store_name: null };
      const imageUrl = await getProductImage(sku, model);

      items.push({
        sku, product_name: parsed.product_name, model,
        store_code: storeInfo.store_code, store_name: storeInfo.store_name,
        quantity: qty || 1, unit_price: price || 0, amount: amount || 0,
        color: parsed.color, size: parsed.size, sale_date: extractSaleDate(idxDate >= 0 ? row[idxDate] : null),
        image_url: imageUrl,
        extra_data: { source_row: i, original_name: description, color_display: parsed.color_display }
      });
    }
  }
} else {
      // APPOLLOS format
      // 找header行（包含STORE/SKU等关键词的行）
      let apolloHeader = null
      for (let h = 0; h < Math.min(3, data.length); h++) {
        const rowH = data[h]
        if (rowH && (String(rowH[3]||'').toUpperCase().includes('STORE') || String(rowH[10]||'').toUpperCase().includes('SKU'))) {
          apolloHeader = rowH; break
        }
      }
      const apolloIdxDate = apolloHeader ? findDateColIndex(apolloHeader) : -1
      for (let i = 3; i < data.length; i++) {
        const row = data[i];
        if (!row || row.length < 11) continue;

        const skuRaw = row[10];
        if (!skuRaw) continue;
        const sku = String(parseInt(skuRaw, 10));
        if (!sku || sku === 'undefined' || sku === 'NaN' || isNaN(parseInt(skuRaw, 10))) continue;

        const description = String(row[7] || '').trim();
        const parsed = parseProductDescription(description);
        const model = parsed.model;
        const price = parseFloat(row[8] || 0) || 0;
        const upc = row[9] ? String(parseInt(row[9], 10)) : null;
        const qty = 1;
        const amount = price * qty;
        totalAmount += amount;

        const storeName = String(row[3] || row[4] || '').trim();
        const storeInfo = await matchStore(storeName);
        const imageUrl = await getProductImage(sku, model);

        items.push({
          sku, product_name: parsed.product_name, model,
          store_code: storeInfo.store_code, store_name: storeInfo.store_name,
          quantity: qty || 1, unit_price: price || 0, amount: amount || 0,
          color: parsed.color, size: parsed.size, sale_date: extractSaleDate(apolloIdxDate >= 0 ? row[apolloIdxDate] : null),
          image_url: imageUrl,
          extra_data: { upc, source_row: i, original_name: description, color_display: parsed.color_display }
        });
      }
    }

    const [recordResult] = await pool.query(
      `INSERT INTO imported_excel_records (file_name, file_type, total_records, total_amount, status, content_hash)
       VALUES (?, ?, ?, ?, 'completed', ?)`,
      [fileName, file_type, items.length, totalAmount, contentHash]
    );
    const recordId = recordResult.insertId;

    if (items.length > 0) {
      const values = items.map(item => [
        recordId, item.sku, item.product_name, item.model,
        item.store_code, item.store_name, item.quantity, item.unit_price, item.amount,
        item.color, item.size, item.sale_date, item.image_url, JSON.stringify(item.extra_data)
      ]);
      await pool.query(
        `INSERT INTO imported_excel_items 
         (record_id, sku, product_name, model, store_code, store_name, quantity, unit_price, amount, color, size, sale_date, image_url, extra_data)
         VALUES ?`,
        [values]
      );
    }

    res.json({
      success: true,
      record_id: recordId,
      items_saved: items.length,
      total_amount: totalAmount,
      deduplicated: duplicates.length,  // 去重掉的旧记录数
      content_hash: contentHash
    });
  } catch (err) {
    console.error('Import upload error:', err);
    res.status(500).json({ success: false, message: err.message });
  }
});

// GET /api/import/records
router.get('/records', async (req, res) => {
  try {
    const { page = 1, page_size = 20 } = req.query;
    const offset = (parseInt(page) - 1) * parseInt(page_size);

    const [rows] = await pool.query(
      `SELECT id, file_name, file_type, uploaded_at, total_records, total_amount, status
       FROM imported_excel_records ORDER BY uploaded_at DESC LIMIT ? OFFSET ?`,
      [parseInt(page_size), offset]
    );

    const [[{ total }]] = await pool.query('SELECT COUNT(*) as total FROM imported_excel_records');

    res.json({ success: true, records: rows, total, page: parseInt(page), page_size: parseInt(page_size) });
  } catch (err) {
    console.error('List records error:', err);
    res.status(500).json({ success: false, message: err.message });
  }
});

// GET /api/import/records/:id
router.get('/records/:id', async (req, res) => {
  try {
    const [rows] = await pool.query('SELECT * FROM imported_excel_records WHERE id = ?', [req.params.id]);
    if (!rows.length) return res.status(404).json({ success: false, message: 'Record not found' });
    res.json({ success: true, record: rows[0] });
  } catch (err) {
    res.status(500).json({ success: false, message: err.message });
  }
});

// GET /api/import/records/:id/items
router.get('/records/:id/items', async (req, res) => {
  try {
    const { page = 1, page_size = 50, sort_by = 'id', sort_order = 'DESC',
            store_code, sku, start_date, end_date } = req.query;
    const offset = (parseInt(page) - 1) * parseInt(page_size);

    let where = ['record_id = ?'];
    let params = [req.params.id];

    if (store_code) { where.push('store_code = ?'); params.push(store_code); }
    if (sku) { where.push('sku LIKE ?'); params.push(`%${sku}%`); }
    if (start_date) { where.push('sale_date >= ?'); params.push(start_date); }
    if (end_date) { where.push('sale_date <= ?'); params.push(end_date); }

    const orderMap = {
      id: 'id', sku: 'sku', amount: 'amount', quantity: 'quantity',
      store_name: 'store_name', sale_date: 'sale_date'
    };
    const orderCol = orderMap[sort_by] || 'id';
    const orderDir = sort_order.toUpperCase() === 'ASC' ? 'ASC' : 'DESC';

    // LEFT JOIN product_skus，优先取商品管理数据库的单价
    const [rows] = await pool.query(
      `SELECT i.*,
              i.color AS color,
              i.size AS size,
              COALESCE(ps.sale_price, i.unit_price) AS unit_price
       FROM imported_excel_items i
       LEFT JOIN product_skus ps ON ps.sku = i.sku COLLATE utf8mb4_general_ci
       WHERE ${where.join(' AND ')}
       ORDER BY ${orderCol} ${orderDir} LIMIT ? OFFSET ?`,
      [...params, parseInt(page_size), offset]
    );

    const [[{ total }]] = await pool.query(
      `SELECT COUNT(*) as total FROM imported_excel_items WHERE ${where.join(' AND ')}`, params
    );

    res.json({ success: true, items: rows, total, page: parseInt(page), page_size: parseInt(page_size) });
  } catch (err) {
    console.error('Get items error:', err);
    res.status(500).json({ success: false, message: err.message });
  }
});

// GET /api/import/records/:id/summary
router.get('/records/:id/summary', async (req, res) => {
  try {
    const recordId = req.params.id;

    const [[overall]] = await pool.query(
      `SELECT COUNT(*) as total_items, SUM(quantity) as total_qty, 
              SUM(amount) as total_amount, COUNT(DISTINCT sku) as unique_skus,
              COUNT(DISTINCT store_code) as unique_stores
       FROM imported_excel_items WHERE record_id = ?`, [recordId]
    );

    // 门店分析：按销售额排序
    const [byStore] = await pool.query(
      `SELECT store_code, store_name, COUNT(*) as item_count, 
              SUM(quantity) as qty, SUM(amount) as amount
       FROM imported_excel_items WHERE record_id = ?
       GROUP BY store_code, store_name ORDER BY amount DESC`, [recordId]
    );

    // 商品分析：按销量TOP10 + 滞销BOTTOM10
    const [byProduct] = await pool.query(
      `SELECT sku, product_name, model, 
              SUM(quantity) as qty, SUM(amount) as amount, COUNT(*) as order_count
       FROM imported_excel_items WHERE record_id = ?
       GROUP BY sku, product_name, model ORDER BY qty DESC LIMIT 60`, [recordId]
    );
    const topProducts = byProduct.slice(0, 20);
    const bottomProducts = byProduct.slice(-20).reverse();

    // 颜色分析
    const [byColor] = await pool.query(
      `SELECT color, COUNT(*) as item_count,
              SUM(quantity) as qty, SUM(amount) as amount
       FROM imported_excel_items WHERE record_id = ? AND color IS NOT NULL AND color != ''
       GROUP BY color ORDER BY qty DESC`, [recordId]
    );

    // 尺码分析
    const [bySize] = await pool.query(
      `SELECT size, COUNT(*) as item_count,
              SUM(quantity) as qty, SUM(amount) as amount
       FROM imported_excel_items WHERE record_id = ? AND size IS NOT NULL AND size != ''
       GROUP BY size ORDER BY qty DESC`, [recordId]
    );

    // 颜色×尺码 矩阵（热销组合）
    const [byColorSize] = await pool.query(
      `SELECT color, size, SUM(quantity) as qty, SUM(amount) as amount, COUNT(*) as item_count
       FROM imported_excel_items WHERE record_id = ? AND color IS NOT NULL AND color != '' AND size IS NOT NULL AND size != ''
       GROUP BY color, size ORDER BY qty DESC LIMIT 20`, [recordId]
    );

    // 日期趋势
    const [byDate] = await pool.query(
      `SELECT DATE(sale_date) as sale_date, COUNT(*) as item_count, SUM(quantity) as qty, SUM(amount) as amount
       FROM imported_excel_items WHERE record_id = ? AND sale_date IS NOT NULL
       GROUP BY DATE(sale_date) ORDER BY sale_date`, [recordId]
    );

    // 型号总销量 TOP20 + BOTTOM20
    const [byModel] = await pool.query(
      `SELECT model, SUM(quantity) as qty, SUM(amount) as amount, COUNT(DISTINCT sku) as sku_count
       FROM imported_excel_items WHERE record_id = ? AND model IS NOT NULL AND model != ''
       GROUP BY model ORDER BY qty DESC LIMIT 60`, [recordId]
    );
    const topModels = byModel.slice(0, 20);
    const bottomModels = byModel.slice(-20).reverse();

    res.json({ 
      success: true, 
      summary: { 
        overall, 
        byStore, 
        byProduct: byProduct.slice(0, 50),
        topProducts,
        bottomProducts,
        byColor, 
        bySize,
        byColorSize,
        topModels,
        bottomModels
      } 
    });
  } catch (err) {
    console.error('Get summary error:', err);
    res.status(500).json({ success: false, message: err.message });
  }
});

// GET /api/import/records/:id/summary/by-store/:storeCode — 门店下钻：型号+颜色×尺码
router.get('/records/:id/summary/by-store/:storeCode', async (req, res) => {
  try {
    // 禁用 ONLY_FULL_GROUP_BY（连接池 initSQL 可能未生效）
    await pool.query("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
    const { id } = req.params;
    const { storeCode } = req.params;
    // 门店聚合
    const [[storeOverall]] = await pool.query(
      `SELECT store_code, store_name,
              SUM(quantity) as total_qty, SUM(amount) as total_amount,
              COUNT(DISTINCT model) as model_count, COUNT(DISTINCT sku) as sku_count
       FROM imported_excel_items
       WHERE record_id = ? AND store_code = ?
       GROUP BY store_code, store_name`, [id, storeCode]
    );

    // 型号分布
    const [byModel] = await pool.query(
      `SELECT model, SUM(quantity) as qty, SUM(amount) as amount, COUNT(DISTINCT sku) as sku_count,
              MAX(image_url) as image_url, MAX(product_name) as product_name
       FROM imported_excel_items
       WHERE record_id = ? AND store_code = ? AND model IS NOT NULL AND model != ''
       GROUP BY model ORDER BY qty DESC`, [id, storeCode]
    );

    // 颜色×尺码 矩阵
    const [byColorSize] = await pool.query(
      `SELECT color, size, SUM(quantity) as qty, SUM(amount) as amount
       FROM imported_excel_items
       WHERE record_id = ? AND store_code = ? AND color IS NOT NULL AND color != '' AND size IS NOT NULL AND size != ''
       GROUP BY color, size ORDER BY qty DESC LIMIT 40`, [id, storeCode]
    );

    // SKU明细（按型号聚合）- 先按sku聚合items，再JS层JOIN product_skus获取颜色/尺寸/单价
    // （北京MySQL有ONLY_FULL_GROUP_BY限制，ps.specs不能直接放入GROUP BY的SELECT）
    const [skuRows] = await pool.query(
      `SELECT i.sku, i.model,
              i.color, i.size,
              SUM(i.quantity) as total_qty, SUM(i.amount) as total_amount,
              MAX(i.image_url) as image_url,
              ROUND(SUM(i.amount) / SUM(i.quantity), 2) as calc_unit_price
       FROM imported_excel_items i
       WHERE i.record_id = ? AND i.store_code = ? AND i.model IS NOT NULL AND i.model != ''
       GROUP BY i.sku, i.model, i.color, i.size
       ORDER BY total_qty DESC`, [id, storeCode]
    );

    // 批量获取product_skus的specs+售价（按sku去重）
    const skus = skuRows.map(r => r.sku);
    let specsMap = {};
    let priceMap = {};
    if (skus.length > 0) {
      const placeholders = skus.map(() => '?').join(',');
      const [psRows] = await pool.query(
        `SELECT sku, specs, sale_price FROM product_skus WHERE sku IN (${placeholders})`, skus
      );
      psRows.forEach(r => {
        specsMap[r.sku] = r.specs || '{}';
        if (r.sale_price != null) priceMap[r.sku] = parseFloat(r.sale_price);
      });
    }

    // JS层合并：颜色/尺寸优先取product_skus，单价优先取Excel计算值其次商品库
    const bySku = skuRows.map(r => {
      const specs = specsMap[r.sku];
      let color = r.color;
      let size = r.size;
      if (specs && typeof specs === 'object') {
        const colorVal = specs['COLOR'] || specs['color'] || specs['Color'];
        const sizeVal = specs['SIZE '] || specs['SIZE'] || specs['size'] || specs['Size'];
        if (colorVal) color = String(colorVal).trim();
        if (sizeVal) size = String(sizeVal).trim();
      }
      // 单价：优先用Excel计算值（数字，非0），其次商品库售价兜底
      const calcPrice = Number(r.calc_unit_price) || 0;
      const unit_price = (calcPrice > 0) ? calcPrice : (priceMap[r.sku] || null);
      return { ...r, color, size, unit_price };
    });

    res.json({ success: true, store: storeOverall, byModel, byColorSize, bySku });
  } catch (err) {
    console.error('Store summary error:', err);
    res.status(500).json({ success: false, message: err.message });
  }
});

// DELETE /api/import/records/:id
router.delete('/records/:id', async (req, res) => {
  try {
    await pool.query('DELETE FROM imported_excel_records WHERE id = ?', [req.params.id]);
    res.json({ success: true });
  } catch (err) {
    res.status(500).json({ success: false, message: err.message });
  }
});

// POST /api/import/multi-analysis — 多选记录聚合门店分析
router.post('/multi-analysis', async (req, res) => {
  try {
    // 禁用 ONLY_FULL_GROUP_BY
    await pool.query("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
    const { record_ids } = req.body;
    if (!Array.isArray(record_ids) || record_ids.length === 0) {
      return res.status(400).json({ success: false, message: 'record_ids required' });
    }

    // 整体统计
    const [[overall]] = await pool.query(
      `SELECT COUNT(*) as total_items, SUM(quantity) as total_qty,
              SUM(amount) as total_amount, COUNT(DISTINCT sku) as unique_skus,
              COUNT(DISTINCT store_code) as unique_stores, COUNT(DISTINCT model) as unique_models
       FROM imported_excel_items WHERE record_id IN (?)`, [record_ids]
    );

    // 按门店聚合（含型号和SKU明细）
    const [byStore] = await pool.query(
      `SELECT store_code, store_name,
              SUM(quantity) as total_qty, SUM(amount) as total_amount,
              COUNT(DISTINCT model) as model_count, COUNT(DISTINCT sku) as sku_count
       FROM imported_excel_items WHERE record_id IN (?)
       GROUP BY store_code, store_name ORDER BY total_amount DESC`, [record_ids]
    );

    // 型号分析（所有记录聚合）
    const [byModel] = await pool.query(
      `SELECT model, store_code, store_name,
              SUM(quantity) as qty, SUM(amount) as amount,
              COUNT(DISTINCT sku) as sku_count,
              MAX(image_url) as image_url,
              MAX(product_name) as product_name
       FROM imported_excel_items WHERE record_id IN (?) AND model IS NOT NULL AND model != ''
       GROUP BY model, store_code, store_name ORDER BY qty DESC`, [record_ids]
    );

    // SKU明细（按门店+型号+SKU聚合）- 直接用Excel原始颜色/尺寸，含单价
    const [bySku] = await pool.query(
      `SELECT i.store_code, i.store_name, i.model, i.sku,
              i.color, i.size,
              SUM(i.quantity) as total_qty, SUM(i.amount) as total_amount,
              COUNT(*) as order_count,
              MAX(i.image_url) as image_url,
              MAX(i.product_name) as product_name,
              ROUND(SUM(i.amount) / SUM(i.quantity), 2) as unit_price
       FROM imported_excel_items i
       WHERE i.record_id IN (?)
       GROUP BY i.store_code, i.store_name, i.model, i.sku, i.color, i.size
       ORDER BY i.store_code, total_amount DESC`, [record_ids]
    );

    // 颜色×尺码热销组合
    const [byColorSize] = await pool.query(
      `SELECT color, size, SUM(quantity) as qty, SUM(amount) as amount
       FROM imported_excel_items WHERE record_id IN (?) AND color IS NOT NULL AND color != '' AND size IS NOT NULL AND size != ''
       GROUP BY color, size ORDER BY qty DESC LIMIT 20`, [record_ids]
    );

    res.json({ success: true, overall, byStore, byModel, bySku, byColorSize });
  } catch (err) {
    console.error('Multi analysis error:', err);
    res.status(500).json({ success: false, message: err.message });
  }
});

export default router;