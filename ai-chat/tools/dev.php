<?php
/**
 * 开发工具库
 * 为助手提供各种开发能力
 */

class DevTools {
    private $allowed_dirs = ['/www/wwwroot/gdqshop.cn', '/tmp', '/root/.openclaw/workspace'];
    
    public function exec($cmd) {
        // 安全检查
        $dangerous = ['rm -rf', 'dd if', 'mkfs', '>', '>>'];
        foreach ($dangerous as $d) {
            if (strpos($cmd, $d) !== false) {
                return "危险命令被拒绝: $d";
            }
        }
        
        $output = shell_exec($cmd . ' 2>&1');
        return $output ?: "命令执行完成";
    }
    
    public function read($file) {
        $real = realpath($file);
        foreach ($this->allowed_dirs as $dir) {
            if (strpos($real, $dir) === 0) {
                $c = file_get_contents($real);
                return $c ? substr($c, 0, 10000) : "文件为空";
            }
        }
        return "路径不在允许范围内";
    }
    
    public function write($file, $content) {
        $dir = dirname(realpath($file));
        foreach ($this->allowed_dirs as $allowed) {
            if (strpos($dir, $allowed) === 0) {
                return file_put_contents($file, $content) ? "写入成功" : "写入失败";
            }
        }
        return "路径不在允许范围内";
    }
    
    public function query($sql) {
        $conn = mysqli_connect('127.0.0.1', 'root', '', 'ddshop');
        $type = strtoupper(explode(' ', trim($sql))[0]);
        if ($type !== 'SELECT') return "只支持SELECT查询";
        
        $r = mysqli_query($conn, $sql);
        $rows = [];
        while ($row = mysqli_fetch_assoc($r)) $rows[] = $row;
        return json_encode($rows, JSON_UNESCAPED_UNICODE);
    }
    
    public function git($cmd) {
        return $this->exec("cd /www/wwwroot/gdqshop.cn && git $cmd 2>&1");
    }
    
    public function info() {
        return [
            'php' => $this->exec('php -v'),
            'node' => $this->exec('node -v'),
            'git' => $this->exec('git --version'),
            'uptime' => $this->exec('uptime'),
            'disk' => $this->exec('df -h'),
            'memory' => $this->exec('free -h')
        ];
    }
    
    public function log($lines = 50) {
        return $this->exec("tail -$lines /tmp/assistant_exec.log");
    }
}

// 根据请求执行
$dev = new DevTools();
$action = $_GET['action'] ?? '';

header('Content-Type: application/json');

if ($action === 'exec') {
    $cmd = $_POST['cmd'] ?? '';
    echo json_encode(['result'=>$dev->exec($cmd)]);
}
elseif ($action === 'read') {
    $file = $_POST['file'] ?? '';
    echo json_encode(['result'=>$dev->read($file)]);
}
elseif ($action === 'write') {
    $file = $_POST['file'] ?? '';
    $content = $_POST['content'] ?? '';
    echo json_encode(['result'=>$dev->write($file, $content)]);
}
elseif ($action === 'git') {
    $cmd = $_GET['cmd'] ?? 'status';
    echo json_encode(['result'=>$dev->git($cmd)]);
}
elseif ($action === 'info') {
    echo json_encode($dev->info());
}
else {
    echo json_encode(['error'=>'unknown action']);
}
