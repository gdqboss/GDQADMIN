<?php
/**
 * 数据库查询工具
 * 用于直接查询数据库结果
 * 使用方法：http://your-domain/api/collage/dbquery?sql=SELECT * FROM ddwx_collage_order WHERE id=119
 */

// 确保只有管理员可以访问
if (!isset($this->member) || !$this->member['is_sysadmin']) {
    return $this->json(['status' => 0, 'msg' => '权限不足，仅系统管理员可使用']);
}

try {
    $sql = input('param.sql/s');
    
    if (empty($sql)) {
        return $this->json([
            'status' => 0,
            'msg' => '请输入SQL查询语句',
            'example' => '?sql=SELECT * FROM ddwx_collage_order WHERE id=119'
        ]);
    }
    
    // 安全检查：只允许SELECT查询
    $sql_lower = strtolower(trim($sql));
    if (strpos($sql_lower, 'select') !== 0) {
        return $this->json([
            'status' => 0,
            'msg' => '仅允许SELECT查询语句',
            'sql' => $sql
        ]);
    }
    
    // 执行查询
    $result = Db::query($sql);
    
    return $this->json([
        'status' => 1,
        'msg' => '查询成功',
        'sql' => $sql,
        'count' => count($result),
        'data' => $result
    ]);
    
} catch (\Exception $e) {
    return $this->json([
        'status' => 0,
        'msg' => '查询失败',
        'error' => $e->getMessage(),
        'sql' => isset($sql) ? $sql : ''
    ]);
}
?>