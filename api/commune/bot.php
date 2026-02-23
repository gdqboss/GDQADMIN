<?php
/**
 * 机器人 API - 允许外部机器人接入聊天室
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

// 验证机器人
if ($action === 'verify') {
    $bot_key = $_GET['bot_key'] ?? $_POST['bot_key'] ?? '';
    
    $bot_key = mysqli_real_escape_string($conn, $bot_key);
    $result = mysqli_query($conn, "SELECT * FROM ddwx_commune_bot WHERE bot_key = '$bot_key' AND status = 1");
    $bot = mysqli_fetch_assoc($result);
    
    if ($bot) {
        // 更新在线状态
        $time = time();
        mysqli_query($conn, "REPLACE INTO ddwx_commune_online (username, nickname, last_active) VALUES ('{$bot['bot_key']}', '{$bot['bot_name']}', $time)");
        
        echo json_encode([
            'code' => 1,
            'data' => [
                'bot_id' => $bot['id'],
                'bot_name' => $bot['bot_name'],
                'owner' => $bot['owner'],
                'owner_name' => $bot['owner_name']
            ]
        ]);
    } else {
        echo json_encode(['code' => 0, 'msg' => '机器人验证失败']);
    }
    exit;
}

// 发送消息
if ($action === 'send') {
    $bot_key = $_POST['bot_key'] ?? '';
    $content = trim($_POST['content'] ?? '');
    
    if (empty($bot_key) || empty($content)) {
        echo json_encode(['code' => 0, 'msg' => '参数不完整']);
        exit;
    }
    
    $bot_key = mysqli_real_escape_string($conn, $bot_key);
    $content = mysqli_real_escape_string($conn, $content);
    
    // 获取机器人信息
    $result = mysqli_query($conn, "SELECT * FROM ddwx_commune_bot WHERE bot_key = '$bot_key' AND status = 1");
    $bot = mysqli_fetch_assoc($result);
    
    if (!$bot) {
        echo json_encode(['code' => 0, 'msg' => '机器人验证失败']);
        exit;
    }
    
    $time = time();
    mysqli_query($conn, "INSERT INTO ddwx_commune_chat (content, username, msg_type, from_user, is_robot, status, create_time) 
            VALUES ('$content', '{$bot['bot_name']}', 'public', '{$bot['bot_key']}', 1, 1, $time)");
    
    echo json_encode(['code' => 1, 'msg' => '发送成功', 'id' => mysqli_insert_id($conn)]);
    exit;
}

// 私聊发送
if ($action === 'send_private') {
    $bot_key = $_POST['bot_key'] ?? '';
    $content = trim($_POST['content'] ?? '');
    $to_user = trim($_POST['to_user'] ?? '');
    
    if (empty($bot_key) || empty($content) || empty($to_user)) {
        echo json_encode(['code' => 0, 'msg' => '参数不完整']);
        exit;
    }
    
    $bot_key = mysqli_real_escape_string($conn, $bot_key);
    $content = mysqli_real_escape_string($conn, $content);
    $to_user = mysqli_real_escape_string($conn, $to_user);
    
    // 获取机器人信息
    $result = mysqli_query($conn, "SELECT * FROM ddwx_commune_bot WHERE bot_key = '$bot_key' AND status = 1");
    $bot = mysqli_fetch_assoc($result);
    
    if (!$bot) {
        echo json_encode(['code' => 0, 'msg' => '机器人验证失败']);
        exit;
    }
    
    $time = time();
    mysqli_query($conn, "INSERT INTO ddwx_commune_chat (content, username, msg_type, from_user, to_user, is_robot, status, create_time) 
            VALUES ('$content', '{$bot['bot_name']}', 'private', '{$bot['bot_key']}', '$to_user', 1, 1, $time)");
    
    echo json_encode(['code' => 1, 'msg' => '发送成功', 'id' => mysqli_insert_id($conn)]);
    exit;
}

// 获取在线机器人列表
if ($action === 'bots_online') {
    $time = time();
    $timeout = 300;
    mysqli_query($conn, "DELETE FROM ddwx_commune_online WHERE last_active < " . ($time - $timeout));
    
    $result = mysqli_query($conn, "SELECT username, nickname, last_active FROM ddwx_commune_online");
    $bots = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // 检查是否是机器人
        $bot_check = mysqli_query($conn, "SELECT bot_name, owner, owner_name FROM ddwx_commune_bot WHERE bot_key = '{$row['username']}'");
        if ($bot_info = mysqli_fetch_assoc($bot_check)) {
            $row['is_bot'] = true;
            $row['bot_name'] = $bot_info['bot_name'];
            $row['owner'] = $bot_info['owner'];
            $row['owner_name'] = $bot_info['owner_name'];
            $bots[] = $row;
        }
    }
    
    echo json_encode(['code' => 1, 'data' => $bots]);
    exit;
}

// 管理员：创建机器人
if ($action === 'create_bot') {
    $bot_name = trim($_POST['bot_name'] ?? '');
    $owner = trim($_POST['owner'] ?? '');
    $owner_name = trim($_POST['owner_name'] ?? '');
    
    if (empty($bot_name) || empty($owner)) {
        echo json_encode(['code' => 0, 'msg' => '参数不完整']);
        exit;
    }
    
    // 生成机器人密钥
    $bot_key = 'bot_' . md5($bot_name . time() . rand(1000, 9999));
    $time = time();
    
    $bot_name_esc = mysqli_real_escape_string($conn, $bot_name);
    $owner_esc = mysqli_real_escape_string($conn, $owner);
    $owner_name_esc = mysqli_real_escape_string($conn, $owner_name);
    
    mysqli_query($conn, "INSERT INTO ddwx_commune_bot (bot_name, bot_key, owner, owner_name, create_time) 
            VALUES ('$bot_name_esc', '$bot_key', '$owner_esc', '$owner_name_esc', $time)");
    
    echo json_encode([
        'code' => 1,
        'msg' => '创建成功',
        'data' => [
            'bot_name' => $bot_name,
            'bot_key' => $bot_key,
            'owner' => $owner,
            'owner_name' => $owner_name
        ]
    ]);
    exit;
}

// 管理员：列出机器人
if ($action === 'list_bots') {
    $result = mysqli_query($conn, "SELECT id, bot_name, owner, owner_name, status, create_time FROM ddwx_commune_bot ORDER BY id DESC");
    $bots = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $bots[] = $row;
    }
    echo json_encode(['code' => 1, 'data' => $bots]);
    exit;
}

// 机器人：获取归属者（真人）发来的命令
if ($action === 'get_commands') {
    $bot_key = $_GET['bot_key'] ?? '';
    
    if (empty($bot_key)) {
        echo json_encode(['code' => 0, 'msg' => '参数不完整']);
        exit;
    }
    
    $bot_key = mysqli_real_escape_string($conn, $bot_key);
    $result = mysqli_query($conn, "SELECT * FROM ddwx_commune_bot WHERE bot_key = '$bot_key'");
    $bot = mysqli_fetch_assoc($result);
    
    if (!$bot) {
        echo json_encode(['code' => 0, 'msg' => '机器人不存在']);
        exit;
    }
    
    // 获取归属者（主人）发来的未处理命令
    $owner = $bot['owner'];
    $sql = "SELECT * FROM ddwx_commune_chat 
            WHERE status = 1 
            AND ((from_user = '$owner' AND to_user = '$bot_key') OR (msg_type = 'command' AND content LIKE '%@{$bot['bot_name']}%'))
            AND id > 0
            ORDER BY id ASC LIMIT 20";
    $result = mysqli_query($conn, $sql);
    
    $commands = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // 提取命令（去掉@机器人名称）
        $cmd = str_replace('@' . $bot['bot_name'], '', $row['content']);
        $commands[] = [
            'id' => $row['id'],
            'command' => trim($cmd),
            'from' => $row['username']
        ];
    }
    
    echo json_encode(['code' => 1, 'data' => $commands]);
    exit;
}

// 机器人：报告执行结果给聊天室
if ($action === 'report_result') {
    $bot_key = $_POST['bot_key'] ?? '';
    $result_content = trim($_POST['result'] ?? '');
    
    if (empty($bot_key) || empty($result_content)) {
        echo json_encode(['code' => 0, 'msg' => '参数不完整']);
        exit;
    }
    
    $bot_key = mysqli_real_escape_string($conn, $bot_key);
    $result_content = mysqli_real_escape_string($conn, $result_content);
    
    $result = mysqli_query($conn, "SELECT bot_name FROM ddwx_commune_bot WHERE bot_key = '$bot_key'");
    $bot = mysqli_fetch_assoc($result);
    
    if (!$bot) {
        echo json_encode(['code' => 0, 'msg' => '机器人不存在']);
        exit;
    }
    
    $time = time();
    mysqli_query($conn, "INSERT INTO ddwx_commune_chat (content, username, msg_type, from_user, is_robot, status, create_time) 
            VALUES ('$result_content', '{$bot['bot_name']}', 'public', '$bot_key', 1, 1, $time)");
    
    echo json_encode(['code' => 1, 'msg' => '报告已发送']);
    exit;
}

// 真人：发送命令给机器人
if ($action === 'send_command') {
    $owner = $_POST['owner'] ?? '';
    $bot_key = $_POST['bot_key'] ?? '';
    $command = trim($_POST['command'] ?? '');
    
    if (empty($owner) || empty($bot_key) || empty($command)) {
        echo json_encode(['code' => 0, 'msg' => '参数不完整']);
        exit;
    }
    
    $bot_key = mysqli_real_escape_string($conn, $bot_key);
    $command = mysqli_real_escape_string($conn, $command);
    $owner = mysqli_real_escape_string($conn, $owner);
    
    // 获取机器人名称
    $result = mysqli_query($conn, "SELECT bot_name FROM ddwx_commune_bot WHERE bot_key = '$bot_key'");
    $bot = mysqli_fetch_assoc($result);
    
    if (!$bot) {
        echo json_encode(['code' => 0, 'msg' => '机器人不存在']);
        exit;
    }
    
    // 发送命令消息（标记为 command 类型）
    $time = time();
    $full_cmd = '@' . $bot['bot_name'] . ' ' . $command;
    mysqli_query($conn, "INSERT INTO ddwx_commune_chat (content, username, msg_type, from_user, to_user, is_robot, status, create_time) 
            VALUES ('$full_cmd', '$owner', 'command', '$owner', '$bot_key', 0, 1, $time)");
    
    echo json_encode(['code' => 1, 'msg' => '命令已发送']);
    exit;
}

// 获取机器人收到的最新消息（供轮询）
if ($action === 'get_messages') {
    $bot_key = $_GET['bot_key'] ?? '';
    
    if (empty($bot_key)) {
        echo json_encode(['code' => 0, 'msg' => '参数不完整']);
        exit;
    }
    
    $bot_key = mysqli_real_escape_string($conn, $bot_key);
    $result = mysqli_query($conn, "SELECT bot_name FROM ddwx_commune_bot WHERE bot_key = '$bot_key'");
    $bot = mysqli_fetch_assoc($result);
    
    if (!$bot) {
        echo json_encode(['code' => 0, 'msg' => '机器人不存在']);
        exit;
    }
    
    // 获取发给这个机器人的消息（公开@或私聊）
    $sql = "SELECT * FROM ddwx_commune_chat 
            WHERE status = 1 
            AND (to_user = '$bot_key' OR content LIKE '%@{$bot['bot_name']}%')
            ORDER BY id DESC LIMIT 20";
    $result = mysqli_query($conn, $sql);
    
    $messages = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $messages[] = $row;
    }
    
    echo json_encode(['code' => 1, 'data' => $messages]);
    exit;
}

echo json_encode(['code' => 0, 'msg' => '未知操作']);
