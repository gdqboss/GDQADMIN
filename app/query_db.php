<?php
/**
 * 独立数据库查询工具
 * 用于直接查询数据库结果
 * 使用方法：
 * 1. 服务器端：php /www/wwwroot/gdqshop.cn/app/query_db.php "SELECT * FROM ddwx_collage_order WHERE id=119"
 * 2. 网页端：http://your-domain/app/query_db.php?sql=SELECT * FROM ddwx_collage_order WHERE id=119
 */

// 加载ThinkPHP框架（如果在网页端运行）
if (isset($_SERVER['HTTP_HOST'])) {
    // 网页端访问，检查权限
    define('APP_PATH', __DIR__ . '/../application/');
    define('ROOT_PATH', __DIR__ . '/../');
    
    // 加载框架引导文件
    require __DIR__ . '/../thinkphp/start.php';
    
    // 检查管理员权限
    $member = session('member');
    if (!isset($member) || !$member['is_sysadmin']) {
        echo json_encode(['status' => 0, 'msg' => '权限不足，仅系统管理员可使用'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 获取SQL语句
    $sql = $_GET['sql'] ?? '';
} else {
    // 命令行访问
    if ($argc < 2) {
        echo "Usage: php query_db.php \"SELECT * FROM table WHERE condition\"\n";
        exit(1);
    }
    $sql = $argv[1];
}

if (empty($sql)) {
    $response = [
        'status' => 0,
        'msg' => '请输入SQL查询语句',
        'example' => 'SELECT * FROM ddwx_collage_order WHERE id=119'
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 安全检查：只允许SELECT查询
$sql_lower = strtolower(trim($sql));
if (strpos($sql_lower, 'select') !== 0) {
    $response = [
        'status' => 0,
        'msg' => '仅允许SELECT查询语句',
        'sql' => $sql
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 加载数据库配置
$config = include(__DIR__ . '/../config/database.php');
$custom_config = include(__DIR__ . '/../config.php');

// 合并配置
if (isset($custom_config['hostname'])) $config['connections']['mysql']['hostname'] = $custom_config['hostname'];
if (isset($custom_config['username'])) $config['connections']['mysql']['username'] = $custom_config['username'];
if (isset($custom_config['password'])) $config['connections']['mysql']['password'] = $custom_config['password'];
if (isset($custom_config['database'])) $config['connections']['mysql']['database'] = $custom_config['database'];
if (isset($custom_config['hostport'])) $config['connections']['mysql']['hostport'] = $custom_config['hostport'];

// 创建数据库连接
try {
    $pdo = new PDO(
        "mysql:host={$config['connections']['mysql']['hostname']};port={$config['connections']['mysql']['hostport']};dbname={$config['connections']['mysql']['database']};charset={$config['connections']['mysql']['charset']}",
        $config['connections']['mysql']['username'],
        $config['connections']['mysql']['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 执行查询
    $stmt = $pdo->query($sql);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response = [
        'status' => 1,
        'msg' => '查询成功',
        'sql' => $sql,
        'count' => count($result),
        'data' => $result
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    $response = [
        'status' => 0,
        'msg' => '查询失败',
        'error' => $e->getMessage(),
        'sql' => $sql
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit(1);
} catch (Exception $e) {
    $response = [
        'status' => 0,
        'msg' => '系统异常',
        'error' => $e->getMessage(),
        'sql' => $sql
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit(1);
}
?>