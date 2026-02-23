<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'ddshop';

$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    echo json_encode(['code' => 0, 'msg' => '数据库连接失败']);
    exit;
}

mysqli_query($conn, "SET NAMES utf8mb4");

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 获取进销存商品列表
if ($action === 'get_products') {
    $search = trim($_GET['search'] ?? '');
    $category_id = intval($_GET['category_id'] ?? 0);
    
    $where = "1=1";
    if ($search) {
        $s = mysqli_real_escape_string($conn, $search);
        $where .= " AND (name LIKE '%$s%')";
    }
    if ($category_id > 0) {
        $where .= " AND category_id = $category_id";
    }
    
    $result = mysqli_query($conn, "SELECT id, name, category_id FROM ddwx_inventory_product WHERE $where ORDER BY id DESC LIMIT 100");
    $products = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = $row;
    }
    
    echo json_encode(['code' => 1, 'data' => $products]);
    exit;
}

// 获取商品SKU
if ($action === 'get_skus') {
    $product_id = intval($_GET['product_id'] ?? 0);
    
    $result = mysqli_query($conn, "SELECT id, sku_name, spec1, spec2, price, stock FROM ddwx_inventory_sku WHERE product_id = $product_id");
    $skus = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $skus[] = $row;
    }
    
    echo json_encode(['code' => 1, 'data' => $skus]);
    exit;
}

// 生成单个二维码
if ($action === 'generate_single') {
    $product_id = intval($_POST['product_id'] ?? 0);
    $sku_id = intval($_POST['sku_id'] ?? 0);
    $prefix = trim($_POST['prefix'] ?? 'QR');
    $create_user = trim($_POST['create_user'] ?? 'admin');
    
    // 生成唯一编码
    $code = $prefix . date('YmdHis') . rand(100, 999);
    
    $product_name = '';
    $sku_name = '';
    $sku_spec = '';
    
    if ($product_id > 0) {
        $p = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM ddwx_inventory_product WHERE id = $product_id"));
        $product_name = $p ? $p['name'] : '';
        
        if ($sku_id > 0) {
            $s = mysqli_fetch_assoc(mysqli_query($conn, "SELECT sku_name, spec1, spec2 FROM ddwx_inventory_sku WHERE id = $sku_id"));
            if ($s) {
                $sku_name = $s['sku_name'];
                $sku_spec = ($s['spec1'] ? $s['spec1'] : '') . ' ' . ($s['spec2'] ? $s['spec2'] : '');
            }
        }
    }
    
    $sql = "INSERT INTO ddwx_qrcode (code, product_id, sku_id, product_name, sku_name, sku_spec, create_time, create_user)
            VALUES ('$code', $product_id, $sku_id, '$product_name', '$sku_name', '$sku_spec', UNIX_TIMESTAMP(), '$create_user')";
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['code' => 1, 'msg' => '生成成功', 'data' => ['code' => $code]]);
    } else {
        echo json_encode(['code' => 0, 'msg' => '生成失败: ' . mysqli_error($conn)]);
    }
    exit;
}

// 批量生成二维码
if ($action === 'generate_batch') {
    $product_id = intval($_POST['product_id'] ?? 0);
    $sku_id = intval($_POST['sku_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 1);
    $prefix = trim($_POST['prefix'] ?? 'QR');
    $create_user = trim($_POST['create_user'] ?? 'admin');
    
    if ($quantity < 1 || $quantity > 1000) {
        echo json_encode(['code' => 0, 'msg' => '数量需在1-1000之间']);
        exit;
    }
    
    $product_name = '';
    $sku_name = '';
    $sku_spec = '';
    
    if ($product_id > 0) {
        $p = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM ddwx_inventory_product WHERE id = $product_id"));
        $product_name = $p ? $p['name'] : '';
        
        if ($sku_id > 0) {
            $s = mysqli_fetch_assoc(mysqli_query($conn, "SELECT sku_name, spec1, spec2 FROM ddwx_inventory_sku WHERE id = $sku_id"));
            if ($s) {
                $sku_name = $s['sku_name'];
                $sku_spec = ($s['spec1'] ? $s['spec1'] : '') . ' ' . ($s['spec2'] ? $s['spec2'] : '');
            }
        }
    }
    
    $codes = [];
    for ($i = 0; $i < $quantity; $i++) {
        $code = $prefix . date('YmdHis') . sprintf('%03d', $i + 1);
        $codes[] = $code;
        
        mysqli_query($conn, "INSERT INTO ddwx_qrcode (code, product_id, sku_id, product_name, sku_name, sku_spec, create_time, create_user)
                VALUES ('$code', $product_id, $sku_id, '$product_name', '$sku_name', '$sku_spec', UNIX_TIMESTAMP(), '$create_user')");
    }
    
    echo json_encode(['code' => 1, 'msg' => "成功生成 $quantity 个二维码", 'data' => ['codes' => $codes]]);
    exit;
}

// 关联商品到二维码
if ($action === 'link_product') {
    $id = intval($_POST['id'] ?? 0);
    $product_id = intval($_POST['product_id'] ?? 0);
    $sku_id = intval($_POST['sku_id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['code' => 0, 'msg' => '参数错误']);
        exit;
    }
    
    $product_name = '';
    $sku_name = '';
    $sku_spec = '';
    
    if ($product_id > 0) {
        $p = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM ddwx_inventory_product WHERE id = $product_id"));
        $product_name = $p ? $p['name'] : '';
        
        if ($sku_id > 0) {
            $s = mysqli_fetch_assoc(mysqli_query($conn, "SELECT sku_name, spec1, spec2 FROM ddwx_inventory_sku WHERE id = $sku_id"));
            if ($s) {
                $sku_name = $s['sku_name'];
                $sku_spec = ($s['spec1'] ? $s['spec1'] : '') . ' ' . ($s['spec2'] ? $s['spec2'] : '');
            }
        }
    }
    
    mysqli_query($conn, "UPDATE ddwx_qrcode SET product_id = $product_id, sku_id = $sku_id, product_name = '$product_name', sku_name = '$sku_name', sku_spec = '$sku_spec' WHERE id = $id");
    
    echo json_encode(['code' => 1, 'msg' => '关联成功']);
    exit;
}

// 批量关联商品
if ($action === 'batch_link') {
    $ids = $_POST['ids'] ?? '';
    $product_id = intval($_POST['product_id'] ?? 0);
    $sku_id = intval($_POST['sku_id'] ?? 0);
    
    if (!$ids) {
        echo json_encode(['code' => 0, 'msg' => '请选择二维码']);
        exit;
    }
    
    $id_arr = array_filter(array_map('intval', explode(',', $ids)));
    if (empty($id_arr)) {
        echo json_encode(['code' => 0, 'msg' => '无效的选择']);
        exit;
    }
    
    $product_name = '';
    $sku_name = '';
    $sku_spec = '';
    
    if ($product_id > 0) {
        $p = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM ddwx_inventory_product WHERE id = $product_id"));
        $product_name = $p ? $p['name'] : '';
        
        if ($sku_id > 0) {
            $s = mysqli_fetch_assoc(mysqli_query($conn, "SELECT sku_name, spec1, spec2 FROM ddwx_inventory_sku WHERE id = $sku_id"));
            if ($s) {
                $sku_name = $s['sku_name'];
                $sku_spec = ($s['spec1'] ? $s['spec1'] : '') . ' ' . ($s['spec2'] ? $s['spec2'] : '');
            }
        }
    }
    
    $id_str = implode(',', $id_arr);
    mysqli_query($conn, "UPDATE ddwx_qrcode SET product_id = $product_id, sku_id = $sku_id, product_name = '$product_name', sku_name = '$sku_name', sku_spec = '$sku_spec' WHERE id IN ($id_str)");
    
    echo json_encode(['code' => 1, 'msg' => '批量关联成功']);
    exit;
}

// 删除二维码
if ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    
    mysqli_query($conn, "DELETE FROM ddwx_qrcode WHERE id = $id");
    echo json_encode(['code' => 1, 'msg' => '删除成功']);
    exit;
}

// 批量删除
if ($action === 'batch_delete') {
    $ids = $_POST['ids'] ?? '';
    
    if (!$ids) {
        echo json_encode(['code' => 0, 'msg' => '请选择二维码']);
        exit;
    }
    
    $id_arr = array_filter(array_map('intval', explode(',', $ids)));
    if (empty($id_arr)) {
        echo json_encode(['code' => 0, 'msg' => '无效的选择']);
        exit;
    }
    
    $id_str = implode(',', $id_arr);
    mysqli_query($conn, "DELETE FROM ddwx_qrcode WHERE id IN ($id_str)");
    
    echo json_encode(['code' => 1, 'msg' => '批量删除成功']);
    exit;
}

// 获取二维码列表
if ($action === 'list') {
    $search = trim($_GET['search'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $product_id = intval($_GET['product_id'] ?? 0);
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 20);
    
    $where = "1=1";
    if ($search) {
        $s = mysqli_real_escape_string($conn, $search);
        $where .= " AND (code LIKE '%$s%' OR product_name LIKE '%$s%' OR sku_name LIKE '%$s%')";
    }
    if ($status) {
        $where .= " AND status = '$status'";
    }
    if ($product_id > 0) {
        $where .= " AND product_id = $product_id";
    }
    
    $offset = ($page - 1) * $limit;
    
    $total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM ddwx_qrcode WHERE $where"));
    $total = $total['cnt'];
    
    $result = mysqli_query($conn, "SELECT * FROM ddwx_qrcode WHERE $where ORDER BY id DESC LIMIT $offset, $limit");
    $list = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $list[] = $row;
    }
    
    echo json_encode([
        'code' => 1, 
        'data' => [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]
    ]);
    exit;
}

// 统计
if ($action === 'stats') {
    $total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM ddwx_qrcode"));
    $today = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM ddwx_qrcode WHERE create_time >= UNIX_TIMESTAMP(CURDATE())"));
    $linked = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM ddwx_qrcode WHERE product_id > 0"));
    $scanned = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(scan_count) as total FROM ddwx_qrcode"));
    
    echo json_encode(['code' => 1, 'data' => [
        'total' => $total['cnt'],
        'today' => $today['cnt'],
        'linked' => $linked['cnt'],
        'scanned' => $scanned['total'] ?: 0
    ]]);
    exit;
}

echo json_encode(['code' => 0, 'msg' => '未知操作']);
