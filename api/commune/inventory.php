<?php
/**
 * 进销存 API - 完整版
 */

header('Content-Type: application/json;charset=utf-8');
error_reporting(0);

$conn = @mysqli_connect('127.0.0.1', 'root', '', 'ddshop');
if (!$conn) {
    echo json_encode(['code' => 0, 'msg' => '数据库连接失败']);
    exit;
}
mysqli_query($conn, "SET NAMES utf8mb4");

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 获取分类
if ($action === 'categories') {
    $result = mysqli_query($conn, "SELECT * FROM ddwx_inventory_category ORDER BY sort, id");
    $categories = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
    }
    echo json_encode(['code' => 1, 'data' => $categories]);
    exit;
}

// 添加分类
if ($action === 'add_category') {
    $name = trim($_POST['name'] ?? '');
    if (empty($name)) {
        echo json_encode(['code' => 0, 'msg' => '分类名称不能为空']);
        exit;
    }
    $name = mysqli_real_escape_string($conn, $name);
    mysqli_query($conn, "INSERT INTO ddwx_inventory_category (name, create_time) VALUES ('$name', UNIX_TIMESTAMP())");
    echo json_encode(['code' => 1, 'msg' => '添加成功']);
    exit;
}

// 删除分类
if ($action === 'delete_category') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['code' => 0, 'msg' => '参数错误']);
        exit;
    }
    // 检查是否有商品
    $cnt = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM ddwx_inventory_product WHERE category_id = $id"))['cnt'];
    if ($cnt > 0) {
        echo json_encode(['code' => 0, 'msg' => '该分类下有商品，无法删除']);
        exit;
    }
    mysqli_query($conn, "DELETE FROM ddwx_inventory_category WHERE id = $id");
    echo json_encode(['code' => 1, 'msg' => '删除成功']);
    exit;
}

// 获取商品列表（带SKU）
if ($action === 'products') {
    $category_id = intval($_GET['category_id'] ?? 0);
    $keyword = trim($_GET['keyword'] ?? '');
    
    $where = " WHERE p.status = 1 ";
    if ($category_id > 0) {
        $where .= " AND p.category_id = $category_id ";
    }
    if (!empty($keyword)) {
        $k = mysqli_real_escape_string($conn, $keyword);
        $where .= " AND (p.name LIKE '%$k%' OR s.sku_name LIKE '%$k%') ";
    }
    
    $sql = "SELECT p.*, c.name as category_name,
            (SELECT SUM(stock) FROM ddwx_inventory_sku WHERE product_id = p.id AND status = 1) as total_stock,
            (SELECT COUNT(*) FROM ddwx_inventory_sku WHERE product_id = p.id AND status = 1) as sku_count
            FROM ddwx_inventory_product p 
            LEFT JOIN ddwx_inventory_category c ON p.category_id = c.id
            $where ORDER BY p.id DESC";
    
    $result = mysqli_query($conn, $sql);
    $products = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // 获取每个商品的SKU
        $sku_result = mysqli_query($conn, "SELECT * FROM ddwx_inventory_sku WHERE product_id = " . $row['id'] . " AND status = 1");
        $skus = [];
        while ($sku = mysqli_fetch_assoc($sku_result)) {
            $skus[] = $sku;
        }
        $row['skus'] = $skus;
        $products[] = $row;
    }
    echo json_encode(['code' => 1, 'data' => $products]);
    exit;
}

// 获取单个商品及其SKU
if ($action === 'product') {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['code' => 0, 'msg' => '参数错误']);
        exit;
    }
    
    $product = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM ddwx_inventory_product WHERE id = $id"));
    if (!$product) {
        echo json_encode(['code' => 0, 'msg' => '商品不存在']);
        exit;
    }
    
    $skus = [];
    $result = mysqli_query($conn, "SELECT * FROM ddwx_inventory_sku WHERE product_id = $id ORDER BY id");
    while ($row = mysqli_fetch_assoc($result)) {
        $skus[] = $row;
    }
    
    echo json_encode(['code' => 1, 'data' => ['product' => $product, 'skus' => $skus]]);
    exit;
}

// 添加商品
if ($action === 'add_product') {
    $name = trim($_POST['name'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $barcode = trim($_POST['barcode'] ?? '');
    $unit = trim($_POST['unit'] ?? '件');
    $min_stock = intval($_POST['min_stock'] ?? 10);
    $link_url = trim($_POST['link_url'] ?? '');
    $link_type = trim($_POST['link_type'] ?? '');
    
    if (empty($name)) {
        echo json_encode(['code' => 0, 'msg' => '商品名称不能为空']);
        exit;
    }
    
    $name = mysqli_real_escape_string($conn, $name);
    $barcode = mysqli_real_escape_string($conn, $barcode);
    $unit = mysqli_real_escape_string($conn, $unit);
    $link_url = mysqli_real_escape_string($conn, $link_url);
    $link_type = mysqli_real_escape_string($conn, $link_type);
    
    mysqli_query($conn, "INSERT INTO ddwx_inventory_product (name, category_id, barcode, unit, min_stock, link_url, link_type, create_time) 
        VALUES ('$name', $category_id, '$barcode', '$unit', $min_stock, '$link_url', '$link_type', UNIX_TIMESTAMP())");
    
    $product_id = mysqli_insert_id($conn);
    echo json_encode(['code' => 1, 'msg' => '添加成功', 'data' => ['id' => $product_id]]);
    exit;
}

// 编辑商品
if ($action === 'edit_product') {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $barcode = trim($_POST['barcode'] ?? '');
    $unit = trim($_POST['unit'] ?? '件');
    $min_stock = intval($_POST['min_stock'] ?? 10);
    $link_url = trim($_POST['link_url'] ?? '');
    $link_type = trim($_POST['link_type'] ?? '');
    
    if ($id <= 0 || empty($name)) {
        echo json_encode(['code' => 0, 'msg' => '参数错误']);
        exit;
    }
    
    $name = mysqli_real_escape_string($conn, $name);
    $barcode = mysqli_real_escape_string($conn, $barcode);
    $unit = mysqli_real_escape_string($conn, $unit);
    $link_url = mysqli_real_escape_string($conn, $link_url);
    $link_type = mysqli_real_escape_string($conn, $link_type);
    
    mysqli_query($conn, "UPDATE ddwx_inventory_product SET name='$name', category_id=$category_id, barcode='$barcode', unit='$unit', min_stock=$min_stock, link_url='$link_url', link_type='$link_type', update_time=UNIX_TIMESTAMP() WHERE id = $id");
    
    echo json_encode(['code' => 1, 'msg' => '保存成功']);
    exit;
}

// 删除商品
if ($action === 'delete_product') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['code' => 0, 'msg' => '参数错误']);
        exit;
    }
    
    // 删除SKU
    mysqli_query($conn, "DELETE FROM ddwx_inventory_sku WHERE product_id = $id");
    // 删除商品
    mysqli_query($conn, "DELETE FROM ddwx_inventory_product WHERE id = $id");
    
    echo json_encode(['code' => 1, 'msg' => '删除成功']);
    exit;
}

// 添加SKU
if ($action === 'add_sku') {
    $product_id = intval($_POST['product_id'] ?? 0);
    $sku_name = trim($_POST['sku_name'] ?? '');
    $sku_spec = trim($_POST['sku_spec'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $cost = floatval($_POST['cost'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $barcodes = trim($_POST['barcodes'] ?? '');
    $link_url = trim($_POST['link_url'] ?? '');
    $pic = trim($_POST['pic'] ?? '');
    
    if ($product_id <= 0) {
        echo json_encode(['code' => 0, 'msg' => '错误: product_id=' . $product_id]);
        exit;
    }
    if (empty($sku_name)) {
        echo json_encode(['code' => 0, 'msg' => '请输入SKU名称']);
        exit;
    }
    
    $sku_name = mysqli_real_escape_string($conn, $sku_name);
    $sku_spec = mysqli_real_escape_string($conn, $sku_spec);
    $barcodes = mysqli_real_escape_string($conn, $barcodes);
    $link_url = mysqli_real_escape_string($conn, $link_url);
    $pic = mysqli_real_escape_string($conn, $pic);
    
    $result = mysqli_query($conn, "INSERT INTO ddwx_inventory_sku (product_id, sku_name, sku_spec, price, cost, stock, barcodes, link_url, pic, create_time) 
        VALUES ($product_id, '$sku_name', '$sku_spec', $price, $cost, $stock, '$barcodes', '$link_url', '$pic', UNIX_TIMESTAMP())");
    
    if ($result) {
        echo json_encode(['code' => 1, 'msg' => '添加成功']);
    } else {
        echo json_encode(['code' => 0, 'msg' => '添加失败: ' . mysqli_error($conn)]);
    }
    exit;
}

// 编辑SKU
if ($action === 'edit_sku') {
    $id = intval($_POST['id'] ?? 0);
    $sku_name = trim($_POST['sku_name'] ?? '');
    $sku_spec = trim($_POST['sku_spec'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $cost = floatval($_POST['cost'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $barcodes = trim($_POST['barcodes'] ?? '');
    $link_url = trim($_POST['link_url'] ?? '');
    $pic = trim($_POST['pic'] ?? '');
    
    if ($id <= 0) {
        echo json_encode(['code' => 0, 'msg' => '参数错误']);
        exit;
    }
    
    $sku_name = mysqli_real_escape_string($conn, $sku_name);
    $sku_spec = mysqli_real_escape_string($conn, $sku_spec);
    $barcodes = mysqli_real_escape_string($conn, $barcodes);
    $link_url = mysqli_real_escape_string($conn, $link_url);
    $pic = mysqli_real_escape_string($conn, $pic);
    
    mysqli_query($conn, "UPDATE ddwx_inventory_sku SET sku_name='$sku_name', sku_spec='$sku_spec', price=$price, cost=$cost, stock=$stock, barcodes='$barcodes', link_url='$link_url', pic='$pic', update_time=UNIX_TIMESTAMP() WHERE id = $id");
    
    echo json_encode(['code' => 1, 'msg' => '保存成功']);
    exit;
}

// 删除SKU
if ($action === 'delete_sku') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['code' => 0, 'msg' => '参数错误']);
        exit;
    }
    mysqli_query($conn, "DELETE FROM ddwx_inventory_sku WHERE id = $id");
    echo json_encode(['code' => 1, 'msg' => '删除成功']);
    exit;
}

// 出入库
if ($action === 'stock_in' || $action === 'stock_out') {
    $sku_id = intval($_POST['sku_id'] ?? 0);
    $num = intval($_POST['num'] ?? 0);
    $remark = trim($_POST['remark'] ?? '');
    $operator = trim($_POST['operator'] ?? '系统');
    $target = trim($_POST['target'] ?? '');
    
    if ($sku_id <= 0 || $num <= 0) {
        echo json_encode(['code' => 0, 'msg' => '参数错误']);
        exit;
    }
    
    // 获取当前库存
    $sku = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM ddwx_inventory_sku WHERE id = $sku_id"));
    if (!$sku) {
        echo json_encode(['code' => 0, 'msg' => 'SKU不存在']);
        exit;
    }
    
    $before_stock = $sku['stock'];
    $type = $action === 'stock_in' ? 'inbound' : 'outbound';
    
    if ($action === 'stock_out' && $before_stock < $num) {
        echo json_encode(['code' => 0, 'msg' => '库存不足']);
        exit;
    }
    
    // 更新库存
    if ($action === 'stock_in') {
        $new_stock = $before_stock + $num;
    } else {
        $new_stock = $before_stock - $num;
    }
    
    mysqli_query($conn, "UPDATE ddwx_inventory_sku SET stock = $new_stock, update_time = UNIX_TIMESTAMP() WHERE id = $sku_id");
    
    // 记录日志
    $remark = mysqli_real_escape_string($conn, $remark);
    $operator = mysqli_real_escape_string($conn, $operator);
    $target = mysqli_real_escape_string($conn, $target);
    mysqli_query($conn, "INSERT INTO ddwx_inventory_log (sku_id, type, num, before_stock, after_stock, remark, operator, target, create_time) 
        VALUES ($sku_id, '$type', " . ($action === 'stock_in' ? $num : -$num) . ", $before_stock, $new_stock, '$remark', '$operator', '$target', UNIX_TIMESTAMP())");
    
    echo json_encode(['code' => 1, 'msg' => ($action === 'stock_in' ? '入库' : '出库') . '成功', 'data' => ['stock' => $new_stock]]);
    exit;
}

// 获取出入库记录
if ($action === 'logs') {
    $sku_id = intval($_GET['sku_id'] ?? 0);
    $limit = intval($_GET['limit'] ?? 50);
    
    $where = " WHERE 1=1 ";
    if ($sku_id > 0) {
        $where .= " AND l.sku_id = $sku_id ";
    }
    
    $sql = "SELECT l.*, s.sku_name, s.sku_spec, p.name as product_name
            FROM ddwx_inventory_log l
            LEFT JOIN ddwx_inventory_sku s ON l.sku_id = s.id
            LEFT JOIN ddwx_inventory_product p ON s.product_id = p.id
            $where ORDER BY l.id DESC LIMIT $limit";
    
    $result = mysqli_query($conn, $sql);
    $logs = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $logs[] = $row;
    }
    echo json_encode(['code' => 1, 'data' => $logs]);
    exit;
}

// 统计面板
if ($action === 'stats') {
    // 商品数
    $total_products = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM ddwx_inventory_product WHERE status = 1"))['cnt'];
    
    // SKU数
    $total_skus = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM ddwx_inventory_sku WHERE status = 1"))['cnt'];
    
    // 总库存
    $total_stock = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(stock) as total FROM ddwx_inventory_sku WHERE status = 1"))['total'] ?? 0;
    
    // 库存预警
    $result = mysqli_query($conn, "SELECT p.id, p.name, p.min_stock, s.stock, s.sku_name 
        FROM ddwx_inventory_sku s 
        JOIN ddwx_inventory_product p ON s.product_id = p.id 
        WHERE s.stock < p.min_stock AND s.status = 1");
    $warnings = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $warnings[] = $row;
    }
    
    // 今日出入库
    $today = strtotime(date('Y-m-d'));
    $today_in = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(num) as total FROM ddwx_inventory_log WHERE type = 'inbound' AND create_time >= $today"))['total'] ?? 0;
    $today_out = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(-num) as total FROM ddwx_inventory_log WHERE type = 'outbound' AND create_time >= $today"))['total'] ?? 0;
    
    echo json_encode([
        'code' => 1, 
        'data' => [
            'total_products' => intval($total_products),
            'total_skus' => intval($total_skus),
            'total_stock' => intval($total_stock),
            'warnings' => $warnings,
            'today_in' => intval($today_in),
            'today_out' => intval($today_out)
        ]
    ]);
    exit;
}

// 翻译功能（智能翻译）
if ($action === 'translate') {
    $text = trim($_GET['text'] ?? '');
    $target_lang = $_GET['lang'] ?? 'en';
    $product_id = intval($_GET['product_id'] ?? 0);
    
    if (empty($text)) {
        echo json_encode(['code' => 0, 'msg' => '文本不能为空']);
        exit;
    }
    
    // 简单的翻译映射（实际可以用API）
    $translations = [
        'en' => [
            '大闸蟹' => 'Hairy Crab',
            '波士顿龙虾' => 'Boston Lobster',
            '帝王蟹' => 'King Crab',
            '皮皮虾' => 'Mantis Shrimp',
            '海参' => 'Sea Cucumber',
            '鲍鱼' => 'Abalone',
            '扇贝' => 'Scallop',
            '龙虾' => 'Lobster',
            '贝类' => 'Shellfish',
            '只' => 'pcs',
            '斤' => 'jin',
            '盒' => 'box'
        ],
        'ja' => [
            '大闸蟹' => '毛蟹',
            '波士顿龙虾' => 'ボストン龙虾',
            '帝王蟹' => 'タラバ蟹',
            '皮皮虾' => 'シャコ',
            '海参' => 'ナマコ',
            '鲍鱼' => 'アワビ',
            '扇贝' => 'ホタテ',
            '龙虾' => '龙虾',
            '贝類' => '貝類',
            '只' => '匹',
            '斤' => '斤',
            '盒' => '盒'
        ],
        'ko' => [
            '大闸蟹' => '털게',
            '波士顿龙虾' => '보스턴龙虾',
            '帝王蟹' => '킹크랩',
            '皮皮虾' => '쌍鞭류',
            '海参' => '해삼',
            '鲍鱼' => '전복',
            '扇贝' => '가리비',
            '龙虾' => '、龙虾',
            '贝类' => '조개류',
            '只' => '마리',
            '斤' => '근',
            '盒' => '박스'
        ],
        'id' => [
            '大闸蟹' => 'Kepiting Bulu',
            '波士顿龙虾' => 'Lobster Boston',
            '帝王蟹' => 'King Crab',
            '皮皮虾' => 'Udang Mantis',
            '海参' => 'Teripang',
            '鲍鱼' => 'Abalon',
            '扇贝' => 'Kerang',
            '龙虾' => 'Lobster',
            '贝类' => 'Kerang-kerangan',
            '只' => 'pcs',
            '斤' => 'jin',
            '盒' => 'kotak'
        ],
        'tl' => [
            '大闸蟹' => 'Hairy Crab',
            '波士顿龙虾' => 'Boston Lobster',
            '帝王蟹' => 'King Crab',
            '皮皮虾' => 'Mantis Shrimp',
            '海参' => 'Sea Cucumber',
            '鲍鱼' => 'Abalone',
            '扇贝' => 'Scallop',
            '龙虾' => 'Lobster',
            '贝类' => 'Shellfish',
            '只' => 'pcs',
            '斤' => 'jin',
            '盒' => 'kaha'
        ],
        'vi' => [
            '大闸蟹' => 'Cua lông',
            '波士顿龙虾' => 'Tôm hùm Boston',
            '帝王蟹' => 'Cua hoàng đế',
            '皮皮虾' => 'Tôm mantis',
            '海参' => 'Hải sâm',
            '鲍鱼' => 'Bào ngư',
            '扇贝' => 'Sò',
            '龙虾' => 'Tôm hùm',
            '贝类' => 'Động vật có vỏ',
            '只' => 'con',
            '斤' => 'cân',
            '盒' => 'hộp'
        ]
    ];
    
    $translated = $text;
    if (isset($translations[$target_lang])) {
        foreach ($translations[$target_lang] as $zh => $en) {
            $translated = str_replace($zh, $en, $translated);
        }
    }
    
    // 如果是商品ID，保存翻译到数据库
    if ($product_id > 0 && !empty($translated) && $translated !== $text) {
        $text_esc = mysqli_real_escape_string($conn, $text);
        $trans_esc = mysqli_real_escape_string($conn, $translated);
        mysqli_query($conn, "INSERT INTO ddwx_inventory_lang (product_id, lang, name, create_time) 
            VALUES ($product_id, '$target_lang', '$trans_esc', UNIX_TIMESTAMP())
            ON DUPLICATE KEY UPDATE name = '$trans_esc'");
    }
    
    echo json_encode([
        'code' => 1,
        'data' => [
            'original' => $text,
            'translated' => $translated,
            'lang' => $target_lang
        ]
    ]);
    exit;
}

// 获取商品多语言
if ($action === 'product_langs') {
    $product_id = intval($_GET['product_id'] ?? 0);
    
    if ($product_id <= 0) {
        echo json_encode(['code' => 0, 'msg' => '参数错误']);
        exit;
    }
    
    $result = mysqli_query($conn, "SELECT * FROM ddwx_inventory_lang WHERE product_id = $product_id");
    $langs = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $langs[$row['lang']] = $row;
    }
    
    echo json_encode(['code' => 1, 'data' => $langs]);
    exit;
}

// ========== 供应商管理 ==========
// 获取供应商列表
if ($action === 'suppliers') {
    $result = mysqli_query($conn, "SELECT * FROM ddwx_inventory_supplier WHERE status = 1 ORDER BY id DESC");
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    echo json_encode(['code' => 1, 'data' => $data]);
    exit;
}

// 添加供应商
if ($action === 'add_supplier') {
    $name = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $remark = trim($_POST['remark'] ?? '');
    
    if (empty($name)) {
        echo json_encode(['code' => 0, 'msg' => '请输入供应商名称']);
        exit;
    }
    
    $name = mysqli_real_escape_string($conn, $name);
    $contact = mysqli_real_escape_string($conn, $contact);
    $phone = mysqli_real_escape_string($conn, $phone);
    $address = mysqli_real_escape_string($conn, $address);
    $remark = mysqli_real_escape_string($conn, $remark);
    
    mysqli_query($conn, "INSERT INTO ddwx_inventory_supplier (name, contact, phone, address, remark, create_time) 
        VALUES ('$name', '$contact', '$phone', '$address', '$remark', UNIX_TIMESTAMP())");
    echo json_encode(['code' => 1, 'msg' => '添加成功']);
    exit;
}

// 删除供应商
if ($action === 'delete_supplier') {
    $id = intval($_POST['id'] ?? 0);
    mysqli_query($conn, "UPDATE ddwx_inventory_supplier SET status = 0 WHERE id = $id");
    echo json_encode(['code' => 1, 'msg' => '删除成功']);
    exit;
}

// ========== 客户管理 ==========
// 获取客户列表
if ($action === 'customers') {
    $result = mysqli_query($conn, "SELECT * FROM ddwx_inventory_customer WHERE status = 1 ORDER BY id DESC");
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    echo json_encode(['code' => 1, 'data' => $data]);
    exit;
}

// 添加客户
if ($action === 'add_customer') {
    $name = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $type = trim($_POST['type'] ?? 'normal');
    $remark = trim($_POST['remark'] ?? '');
    
    if (empty($name)) {
        echo json_encode(['code' => 0, 'msg' => '请输入客户名称']);
        exit;
    }
    
    $name = mysqli_real_escape_string($conn, $name);
    $contact = mysqli_real_escape_string($conn, $contact);
    $phone = mysqli_real_escape_string($conn, $phone);
    $address = mysqli_real_escape_string($conn, $address);
    $type = mysqli_real_escape_string($conn, $type);
    $remark = mysqli_real_escape_string($conn, $remark);
    
    mysqli_query($conn, "INSERT INTO ddwx_inventory_customer (name, contact, phone, address, type, remark, create_time) 
        VALUES ('$name', '$contact', '$phone', '$address', '$type', '$remark', UNIX_TIMESTAMP())");
    echo json_encode(['code' => 1, 'msg' => '添加成功']);
    exit;
}

// 删除客户
if ($action === 'delete_customer') {
    $id = intval($_POST['id'] ?? 0);
    mysqli_query($conn, "UPDATE ddwx_inventory_customer SET status = 0 WHERE id = $id");
    echo json_encode(['code' => 1, 'msg' => '删除成功']);
    exit;
}

// ========== 采购单 ==========
// 获取采购单列表
if ($action === 'purchases') {
    $result = mysqli_query($conn, "SELECT * FROM ddwx_inventory_purchase ORDER BY id DESC LIMIT 50");
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // 获取采购明细
        $items = mysqli_query($conn, "SELECT pi.*, s.sku_name, s.sku_spec, p.name as product_name 
            FROM ddwx_inventory_purchase_item pi 
            LEFT JOIN ddwx_inventory_sku s ON pi.sku_id = s.id
            LEFT JOIN ddwx_inventory_product p ON s.product_id = p.id
            WHERE pi.purchase_id = " . $row['id']);
        $row['items'] = [];
        while ($item = mysqli_fetch_assoc($items)) {
            $row['items'][] = $item;
        }
        $data[] = $row;
    }
    echo json_encode(['code' => 1, 'data' => $data]);
    exit;
}

// 创建采购单
if ($action === 'add_purchase') {
    $supplier_id = intval($_POST['supplier_id'] ?? 0);
    $supplier_name = trim($_POST['supplier_name'] ?? '');
    $items = json_decode($_POST['items'] ?? '[]', true);
    $remark = trim($_POST['remark'] ?? '');
    
    if (empty($supplier_name)) {
        echo json_encode(['code' => 0, 'msg' => '请选择供应商']);
        exit;
    }
    
    $order_no = 'PO' . date('YmdHis') . rand(100, 999);
    $total_amount = 0;
    
    foreach ($items as $item) {
        $total_amount += floatval($item['amount'] ?? 0);
    }
    
    $remark = mysqli_real_escape_string($conn, $remark);
    $supplier_name = mysqli_real_escape_string($conn, $supplier_name);
    
    mysqli_query($conn, "INSERT INTO ddwx_inventory_purchase (order_no, supplier_id, supplier_name, total_amount, status, remark, create_time) 
        VALUES ('$order_no', $supplier_id, '$supplier_name', $total_amount, 'pending', '$remark', UNIX_TIMESTAMP())");
    
    $purchase_id = mysqli_insert_id($conn);
    
    // 添加采购明细并入库
    foreach ($items as $item) {
        $sku_id = intval($item['sku_id'] ?? 0);
        $quantity = intval($item['quantity'] ?? 0);
        $price = floatval($item['price'] ?? 0);
        $amount = floatval($item['amount'] ?? 0);
        $sku_name = mysqli_real_escape_string($conn, $item['sku_name'] ?? '');
        
        if ($sku_id > 0 && $quantity > 0) {
            mysqli_query($conn, "INSERT INTO ddwx_inventory_purchase_item (purchase_id, sku_id, sku_name, quantity, price, amount, create_time) 
                VALUES ($purchase_id, $sku_id, '$sku_name', $quantity, $price, $amount, UNIX_TIMESTAMP())");
            
            // 直接入库：更新库存
            mysqli_query($conn, "UPDATE ddwx_inventory_sku SET stock = stock + $quantity, update_time = UNIX_TIMESTAMP() WHERE id = $sku_id");
            
            // 记录库存流水
            mysqli_query($conn, "INSERT INTO ddwx_inventory_log (type, sku_id, sku_name, product_id, num, before_stock, after_stock, remark, create_time)
                SELECT 'inbound', s.id, s.sku_name, s.product_id, $quantity, s.stock - $quantity, s.stock, '采购入库', UNIX_TIMESTAMP()
                FROM ddwx_inventory_sku s WHERE s.id = $sku_id");
        }
    }
    
    echo json_encode(['code' => 1, 'msg' => '采购单创建成功，已自动入库', 'data' => ['order_no' => $order_no]]);
    exit;
}

// ========== 销售单 ==========
// 获取销售单列表
if ($action === 'sales') {
    $result = mysqli_query($conn, "SELECT * FROM ddwx_inventory_sales ORDER BY id DESC LIMIT 50");
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // 获取销售明细
        $items = mysqli_query($conn, "SELECT si.*, s.sku_name, s.sku_spec, p.name as product_name 
            FROM ddwx_inventory_sales_item si 
            LEFT JOIN ddwx_inventory_sku s ON si.sku_id = s.id
            LEFT JOIN ddwx_inventory_product p ON s.product_id = p.id
            WHERE si.sales_id = " . $row['id']);
        $row['items'] = [];
        while ($item = mysqli_fetch_assoc($items)) {
            $row['items'][] = $item;
        }
        $data[] = $row;
    }
    echo json_encode(['code' => 1, 'data' => $data]);
    exit;
}

// 创建销售单
if ($action === 'add_sales') {
    $customer_id = intval($_POST['customer_id'] ?? 0);
    $customer_name = trim($_POST['customer_name'] ?? '');
    $items = json_decode($_POST['items'] ?? '[]', true);
    $remark = trim($_POST['remark'] ?? '');
    
    if (empty($customer_name)) {
        echo json_encode(['code' => 0, 'msg' => '请选择客户']);
        exit;
    }
    
    $order_no = 'SO' . date('YmdHis') . rand(100, 999);
    $total_amount = 0;
    
    foreach ($items as $item) {
        $total_amount += floatval($item['amount'] ?? 0);
    }
    
    $remark = mysqli_real_escape_string($conn, $remark);
    $customer_name = mysqli_real_escape_string($conn, $customer_name);
    
    mysqli_query($conn, "INSERT INTO ddwx_inventory_sales (order_no, customer_id, customer_name, total_amount, status, remark, create_time) 
        VALUES ('$order_no', $customer_id, '$customer_name', $total_amount, 'pending', '$remark', UNIX_TIMESTAMP())");
    
    $sales_id = mysqli_insert_id($conn);
    
    // 添加销售明细并出库
    foreach ($items as $item) {
        $sku_id = intval($item['sku_id'] ?? 0);
        $quantity = intval($item['quantity'] ?? 0);
        $price = floatval($item['price'] ?? 0);
        $amount = floatval($item['amount'] ?? 0);
        $sku_name = mysqli_real_escape_string($conn, $item['sku_name'] ?? '');
        
        if ($sku_id > 0 && $quantity > 0) {
            // 检查库存
            $stock = mysqli_fetch_assoc(mysqli_query($conn, "SELECT stock FROM ddwx_inventory_sku WHERE id = $sku_id"));
            if ($stock && $stock['stock'] >= $quantity) {
                mysqli_query($conn, "INSERT INTO ddwx_inventory_sales_item (sales_id, sku_id, sku_name, quantity, price, amount, create_time) 
                    VALUES ($sales_id, $sku_id, '$sku_name', $quantity, $price, $amount, UNIX_TIMESTAMP())");
                
                // 直接出库：更新库存
                mysqli_query($conn, "UPDATE ddwx_inventory_sku SET stock = stock - $quantity, update_time = UNIX_TIMESTAMP() WHERE id = $sku_id");
                
                // 记录库存流水
                mysqli_query($conn, "INSERT INTO ddwx_inventory_log (type, sku_id, sku_name, product_id, num, before_stock, after_stock, remark, create_time)
                    SELECT 'outbound', s.id, s.sku_name, s.product_id, -$quantity, s.stock + $quantity, s.stock, '销售出库', UNIX_TIMESTAMP()
                    FROM ddwx_inventory_sku s WHERE s.id = $sku_id");
            } else {
                echo json_encode(['code' => 0, 'msg' => '库存不足: ' . $sku_name]);
                exit;
            }
        }
    }
    
    echo json_encode(['code' => 1, 'msg' => '销售单创建成功，已自动出库', 'data' => ['order_no' => $order_no]]);
    exit;
}

// ========== 库存流水 ==========
if ($action === 'inventory_logs') {
    $limit = intval($_GET['limit'] ?? 100);
    $sku_id = intval($_GET['sku_id'] ?? 0);
    
    $where = '';
    if ($sku_id > 0) {
        $where = " AND sku_id = $sku_id";
    }
    
    $result = mysqli_query($conn, "SELECT l.*, p.name as product_name 
        FROM ddwx_inventory_log l 
        LEFT JOIN ddwx_inventory_product p ON l.product_id = p.id
        WHERE 1=1 $where ORDER BY l.id DESC LIMIT $limit");
    
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    echo json_encode(['code' => 1, 'data' => $data]);
    exit;
}

// ========== 报表统计 ==========
if ($action === 'report') {
    $type = $_GET['type'] ?? 'summary'; // summary/sales/purchase/daily
    
    if ($type === 'summary') {
        // 汇总统计
        $total_products = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM ddwx_inventory_product WHERE status = 1"))['cnt'];
        $total_skus = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM ddwx_inventory_sku WHERE status = 1"))['cnt'];
        $total_stock = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(stock), 0) as total FROM ddwx_inventory_sku WHERE status = 1"))['total'];
        $total_suppliers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM ddwx_inventory_supplier WHERE status = 1"))['cnt'];
        $total_customers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM ddwx_inventory_customer WHERE status = 1"))['cnt'];
        
        // 本月销售
        $month_start = strtotime(date('Y-m-01'));
        $month_sales = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total_amount), 0) as total FROM ddwx_inventory_sales WHERE status = 'completed' AND create_time >= $month_start"))['total'];
        $month_purchase = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total_amount), 0) as total FROM ddwx_inventory_purchase WHERE status = 'completed' AND create_time >= $month_start"))['total'];
        
        echo json_encode(['code' => 1, 'data' => [
            'total_products' => $total_products,
            'total_skus' => $total_skus,
            'total_stock' => $total_stock,
            'total_suppliers' => $total_suppliers,
            'total_customers' => $total_customers,
            'month_sales' => $month_sales,
            'month_purchase' => $month_purchase,
            'month_profit' => $month_sales - $month_purchase
        ]]);
        exit;
    }
    
    echo json_encode(['code' => 1, 'data' => []]);
    exit;
}

// ========== 经销商 ==========
if ($action === 'distributors') {
    $result = mysqli_query($conn, "SELECT * FROM ddwx_inventory_distributor WHERE status = 1 ORDER BY id DESC");
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // 获取门店
        $stores = mysqli_query($conn, "SELECT * FROM ddwx_inventory_distributor_store WHERE distributor_id = " . $row['id'] . " AND status = 1");
        $row['stores'] = [];
        while ($store = mysqli_fetch_assoc($stores)) {
            $row['stores'][] = $store;
        }
        $data[] = $row;
    }
    echo json_encode(['code' => 1, 'data' => $data]);
    exit;
}

if ($action === 'add_distributor') {
    $name = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $default_store = trim($_POST['default_store'] ?? '');
    
    if (empty($name)) {
        echo json_encode(['code' => 0, 'msg' => '请输入经销商名称']);
        exit;
    }
    
    $name = mysqli_real_escape_string($conn, $name);
    $contact = mysqli_real_escape_string($conn, $contact);
    $phone = mysqli_real_escape_string($conn, $phone);
    $address = mysqli_real_escape_string($conn, $address);
    $default_store = mysqli_real_escape_string($conn, $default_store);
    
    mysqli_query($conn, "INSERT INTO ddwx_inventory_distributor (name, contact, phone, address, default_store, create_time) 
        VALUES ('$name', '$contact', '$phone', '$address', '$default_store', UNIX_TIMESTAMP())");
    
    echo json_encode(['code' => 1, 'msg' => '添加成功']);
    exit;
}

if ($action === 'add_distributor_store') {
    $distributor_id = intval($_POST['distributor_id'] ?? 0);
    $store_name = trim($_POST['store_name'] ?? '');
    $store_address = trim($_POST['store_address'] ?? '');
    $is_default = intval($_POST['is_default'] ?? 0);
    
    if ($distributor_id <= 0 || empty($store_name)) {
        echo json_encode(['code' => 0, 'msg' => '参数错误']);
        exit;
    }
    
    $store_name = mysqli_real_escape_string($conn, $store_name);
    $store_address = mysqli_real_escape_string($conn, $store_address);
    
    // 如果设为默认，先清除其他默认
    if ($is_default) {
        mysqli_query($conn, "UPDATE ddwx_inventory_distributor_store SET is_default = 0 WHERE distributor_id = $distributor_id");
    }
    
    mysqli_query($conn, "INSERT INTO ddwx_inventory_distributor_store (distributor_id, store_name, store_address, is_default, create_time) 
        VALUES ($distributor_id, '$store_name', '$store_address', $is_default, UNIX_TIMESTAMP())");
    
    echo json_encode(['code' => 1, 'msg' => '添加成功']);
    exit;
}

// 删除经销商
if ($action === 'delete_distributor') {
    $id = intval($_POST['id'] ?? 0);
    mysqli_query($conn, "UPDATE ddwx_inventory_distributor SET status = 0 WHERE id = $id");
    echo json_encode(['code' => 1, 'msg' => '删除成功']);
    exit;
}

echo json_encode(['code' => 0, 'msg' => '未知操作']);
