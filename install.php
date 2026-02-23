server {
    listen 80;
    server_name gdqshop.cn www.gdqshop.cn;
    root /www/wwwroot/gdqshop.cn/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?s=$uri&$args;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf)$ {
        try_files $uri =404;
        expires 7d;
        access_log off;
    }

    location ~ \.php$ {
        include /www/server/nginx/conf/fastcgi.conf;
        include /www/server/nginx/conf/enable-php-80.conf;  # 替换为你的PHP版本
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\. { deny all; }
    location ~* \.(sql|bak|ini|log|sh)$ { deny all; }
}<?php
$root = __DIR__;
$cfg = $root.'/config.php';
$lock = $root.'/install.lock';
$config = @include $cfg;
if (!is_array($config)) {
    $config = ['hostname'=>'127.0.0.1','username'=>'','password'=>'','hostport'=>'3306','database'=>'','prefix'=>'','authkey'=>'','authtoken'=>'','kfport'=>6228,'custom'=>[]];
}
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'POST') {
    $new = [
        'hostname'  => (string)($_POST['hostname']  ?? ($config['hostname']  ?? '127.0.0.1')),
        'username'  => (string)($_POST['username']  ?? ($config['username']  ?? '')),
        'password'  => (string)($_POST['password']  ?? ($config['password']  ?? '')),
        'hostport'  => (string)($_POST['hostport']  ?? ($config['hostport']  ?? '3306')),
        'database'  => (string)($_POST['database']  ?? ($config['database']  ?? '')),
        'prefix'    => (string)($_POST['prefix']    ?? ($config['prefix']    ?? '')),
        'authkey'   => (string)($_POST['authkey']   ?? ($config['authkey']   ?? '')),
        'authtoken' => (string)($_POST['authtoken'] ?? ($config['authtoken'] ?? '')),
        'kfport'    => (int)($_POST['kfport']       ?? ($config['kfport']    ?? 6228)),
        'custom'    => (isset($config['custom']) && is_array($config['custom'])) ? $config['custom'] : []
    ];
    $php = '<?php return ' . var_export($new, true) . ';';
    file_put_contents($cfg, $php);
    if (!file_exists($lock)) {
        file_put_contents($lock, (string)time());
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>安装成功</title><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto;max-width:560px;margin:40px auto;padding:0 16px}h1{font-size:20px}p{margin:10px 0;color:#333}label{display:block;margin:8px 0}a.btn{display:inline-block;margin-top:16px;padding:10px 14px;background:#1677ff;color:#fff;text-decoration:none;border-radius:4px}</style></head><body><h1>安装成功</h1><p>配置已写入且未修改任何数据库结构或数据。</p><p><a class="btn" href="./">进入首页</a></p></body></html>';
    exit;
}
$v = fn($k) => htmlspecialchars((string)($config[$k] ?? ''), ENT_QUOTES);
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>安装</title><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto;max-width:560px;margin:40px auto;padding:0 16px}h1{font-size:20px}label{display:block;margin:10px 0}input{width:100%;padding:8px;border:1px solid #d0d7de;border-radius:4px}button{margin-top:16px;padding:10px 14px;border:0;border-radius:4px;background:#1677ff;color:#fff;cursor:pointer;width:100%}</style></head><body><h1>安装配置</h1><form method="post"><label>数据库地址<input name="hostname" value="'.$v('hostname').'"></label><label>数据库端口<input name="hostport" value="'.$v('hostport').'"></label><label>数据库名称<input name="database" value="'.$v('database').'"></label><label>数据库用户名<input name="username" value="'.$v('username').'"></label><label>数据库密码<input type="password" name="password" value="'.$v('password').'"></label><label>表前缀<input name="prefix" value="'.$v('prefix').'"></label><label>授权Key<input name="authkey" value="'.$v('authkey').'"></label><label>授权Token<input name="authtoken" value="'.$v('authtoken').'"></label><label>客服端口<input type="number" name="kfport" value="'.$v('kfport').'"></label><button type="submit">保存并完成安装</button></form></body></html>';
