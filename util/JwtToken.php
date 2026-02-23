<?php
namespace app\util;

/**
 * JWT Token工具类
 */
class JwtToken
{
    // 密钥
    private $key = 'gdq_shop_supplier_secret_key_2024';
    
    // Token有效期（秒）
    private $expire = 7200; // 2小时
    
    /**
     * 创建Token
     * @param array $payload 自定义载荷
     * @return string
     */
    public function createToken($payload)
    {
        // 设置标准载荷
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];
        
        // 设置自定义载荷
        $payload = [
            'id' => $payload['id'],
            'username' => $payload['username'],
            'iat' => time(),
            'exp' => time() + $this->expire,
            'type' => 'supplier' // 区分用户类型
        ];
        
        // 添加供应商名称（如果存在）
        if (!empty($payload['supplier_name'])) {
            $payload['supplier_name'] = $payload['supplier_name'];
        }
        
        // 编码头部
        $header_encoded = $this->encode(json_encode($header, JSON_UNESCAPED_UNICODE));
        
        // 编码载荷
        $payload_encoded = $this->encode(json_encode($payload, JSON_UNESCAPED_UNICODE));
        
        // 生成签名
        $signature = $this->encode(hash_hmac('sha256', "{$header_encoded}.{$payload_encoded}", $this->key, true));
        
        // 组合生成token
        $token = "{$header_encoded}.{$payload_encoded}.{$signature}";
        
        return $token;
    }
    
    /**
     * 验证Token
     * @param string $token
     * @return array|bool 验证成功返回载荷，失败返回false
     */
    public function verifyToken($token)
    {
        try {
            // 分割token
            $tokenParts = explode('.', $token);
            
            if (count($tokenParts) != 3) {
                return false;
            }
            
            list($header_encoded, $payload_encoded, $signature_encoded) = $tokenParts;
            
            // 解码头部和载荷
            $header = json_decode($this->decode($header_encoded), true);
            $payload = json_decode($this->decode($payload_encoded), true);
            
            // 验证类型
            if (!isset($payload['type']) || $payload['type'] != 'supplier') {
                return false;
            }
            
            // 验证是否过期
            if (isset($payload['exp']) && time() > $payload['exp']) {
                return false;
            }
            
            // 验证签名
            $signature = $this->decode($signature_encoded);
            $expected_signature = hash_hmac('sha256', "{$header_encoded}.{$payload_encoded}", $this->key, true);
            
            if (!hash_equals($signature, $expected_signature)) {
                return false;
            }
            
            return $payload;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Base64URL编码
     * @param string $data
     * @return string
     */
    private function encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64URL解码
     * @param string $data
     * @return string
     */
    private function decode($data)
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) + (4 - strlen($data) % 4) % 4, '=', STR_PAD_RIGHT));
    }
}