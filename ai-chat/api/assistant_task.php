<?php
/**
 * 助手任务执行系统
 * 助手可以接收任务并执行
 */

header('Content-Type: application/json');

$memory_file = '/tmp/gdq_shared_memory.json';

function readMemory() {
    global $memory_file;
    $c = @file_get_contents($memory_file);
    return $c ? json_decode($c, true) : [];
}

$conn = mysqli_connect('127.0.0.1', 'root', '', 'ddshop');
mysqli_query($conn, "SET NAMES utf8mb4");

$action = $_GET['action'] ?? '';

// 助手执行任务
if ($action === 'execute') {
    $task = $_POST['task'] ?? '';
    $assistant_key = $_POST['key'] ?? '';
    
    if (!$task) {
        echo json_encode(['code'=>0, 'msg'=>'任务为空']); exit;
    }
    
    // 获取助手信息
    $key_esc = mysqli_real_escape_string($conn, $assistant_key);
    $r = mysqli_query($conn, "SELECT * FROM ai_external_assistants WHERE secret_key='$key_esc'");
    $assistant = mysqli_fetch_assoc($r);
    
    // 根据任务类型执行
    $result = executeTask($task, $assistant);
    
    echo json_encode(['code'=>1, 'result'=>$result]);
    exit;
}

// 获取可用工具列表
if ($action === 'tools') {
    echo json_encode([
        'code'=>1, 
        'tools'=>[
            ['name'=>'exec', 'desc'=>'执行Shell命令'],
            ['name'=>'read', 'desc'=>'读取文件'],
            ['name'=>'write', 'desc'=>'写入文件'],
            ['name'=>'query', 'desc'=>'查询数据库'],
            ['name'=>'search_web', 'desc'=>'搜索网络'],
            ['name'=>'send_message', 'desc'=>'发送消息']
        ]
    ]);
    exit;
}

echo json_encode(['code'=>0, 'msg'=>'unknown action']);

function executeTask($task, $assistant) {
    $task_lower = mb_strtolower($task, 'utf-8');
    $result = '';
    
    // 读取文件任务
    if (preg_match('/读取|查看|读.*文件/i', $task) && preg_match('/[\/\w]+\.\w+/', $task, $m)) {
        $file = $m[0];
        $content = @file_get_contents($file);
        $result = $content ? "文件内容:\n" . substr($content, 0, 3000) : "无法读取文件";
    }
    // 列出目录
    elseif (preg_match('/列出|列表|ls/i', $task)) {
        if (preg_match('/ls\s*([\/\w]+)/i', $task, $m) || preg_match('/列出\s*([\/\w]+)/i', $task, $m)) {
            $dir = $m[1] ?? '.';
            $result = shell_exec("ls -la " . escapeshellarg($dir) . " 2>&1");
        } else {
            $result = shell_exec("ls -la /www/wwwroot/gdqshop.cn 2>&1");
        }
    }
    // 搜索文件
    elseif (preg_match('/搜索|找.*文件|find/i', $task)) {
        if (preg_match('/搜索\s*([\w\.]+)/i', $task, $m)) {
            $keyword = $m[1];
            $result = shell_exec("find /www/wwwroot/gdqshop.cn -name '*$keyword*' 2>&1 | head -20");
        } else {
            $result = "请指定搜索关键词";
        }
    }
    // 查看进程
    elseif (preg_match('/进程|ps|运行中/i', $task)) {
        $result = shell_exec("ps aux | head -20");
    }
    // 查看网络状态
    elseif (preg_match('/网络|netstat|连接/i', $task)) {
        $result = shell_exec("netstat -tuln | head -20");
    }
    // 查看日志
    elseif (preg_match('/日志|log/i', $task)) {
        $result = shell_exec("tail -50 /tmp/assistant_exec.log 2>&1") ?: "暂无日志";
    }
    // Git操作
    elseif (preg_match('/git/i', $task)) {
        if (preg_match('/git\s+(\w+)/i', $task, $m)) {
            $cmd = $m[1];
            $result = shell_exec("cd /www/wwwroot/gdqshop.cn && git $cmd 2>&1");
        } else {
            $result = shell_exec("cd /www/wwwroot/gdqshop.cn && git status 2>&1");
        }
    }
    // PHP版本
    elseif (preg_match('/php|版本/i', $task)) {
        $result = shell_exec("php -v 2>&1");
    }
    // 数据库查询
    elseif (preg_match('/数据库|db|查询/i', $task)) {
        if (preg_match('/select\s+(.+?)\s+from\s+(\w+)/i', $task, $m)) {
            $sql = "SELECT " . $m[1] . " FROM " . $m[2] . " LIMIT 10";
            $r = mysqli_query($conn, $sql);
            $rows = [];
            while ($row = mysqli_fetch_assoc($r)) $rows[] = $row;
            $result = json_encode($rows, JSON_UNESCAPED_UNICODE);
        } else {
            $result = "支持的查询: SELECT * FROM 表名 LIMIT 10";
        }
    }
    // 系统信息
    elseif (preg_match('/系统|system|信息/i', $task)) {
        $result = shell_exec("uname -a && uptime && df -h");
    }
    // 读取配置
    elseif (preg_match('/配置|config/i', $task)) {
        $result = @file_get_contents('/www/wwwroot/gdqshop.cn/.env') ?: @file_get_contents('/root/.openclaw/openclaw.json');
        $result = substr($result, 0, 2000);
    }
    // 默认：返回可用操作
    else {
        $result = "我可以帮你完成以下任务：
- 读取文件：帮我读取 [文件路径]
- 列出目录：列出 [目录]
- 搜索文件：搜索 [关键词]
- 查看进程：查看运行中的进程
- 查看日志：查看最近日志
- Git操作：git status / git pull
- 数据库查询：SELECT * FROM 表名
- 系统信息：查看系统状态
- 配置信息：查看配置文件

请告诉我你想做什么？";
    }
    
    return $result;
}
