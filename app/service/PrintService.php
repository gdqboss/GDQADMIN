<?php
namespace app\service;
use think\facade\Db;
use think\facade\Log;

class PrintService
{
    /**
     * 获取打印模板列表
     * @param array $params 查询参数
     * @return array
     */
    public function getPrintTemplates($params)
    {
        try {
            $query = Db::name('print_template');
            
            // 搜索条件
            if (!empty($params['keyword'])) {
                $query->where('name', 'like', '%' . $params['keyword'] . '%');
            }
            
            if (isset($params['status'])) {
                $query->where('status', $params['status']);
            }
            
            // 分页
            $page = $params['page'] ?? 1;
            $limit = $params['limit'] ?? 20;
            
            $total = $query->count();
            $list = $query->order('create_time desc')
                ->page($page, $limit)
                ->select()->toArray();
            
            return [
                'status' => 1,
                'data' => [
                    'total' => $total,
                    'list' => $list
                ]
            ];
        } catch (\Exception $e) {
            Log::error('获取打印模板列表失败: ' . $e->getMessage());
            return ['status' => 0, 'msg' => '获取打印模板列表失败'];
        }
    }

    /**
     * 获取打印模板详情
     * @param int $id 模板ID
     * @return array
     */
    public function getPrintTemplateDetail($id)
    {
        try {
            $template = Db::name('print_template')->where('id', $id)->find();
            if (!$template) {
                return ['status' => 0, 'msg' => '模板不存在'];
            }
            
            // 解析模板配置
            $template['config'] = json_decode($template['config'], true);
            
            return ['status' => 1, 'data' => $template];
        } catch (\Exception $e) {
            Log::error('获取打印模板详情失败: ' . $e->getMessage());
            return ['status' => 0, 'msg' => '获取打印模板详情失败'];
        }
    }

    /**
     * 保存打印模板
     * @param array $params 模板参数
     * @return array
     */
    public function savePrintTemplate($params)
    {
        try {
            // 参数验证
            if (empty($params['name'])) {
                return ['status' => 0, 'msg' => '模板名称不能为空'];
            }
            
            if (empty($params['config'])) {
                return ['status' => 0, 'msg' => '模板配置不能为空'];
            }
            
            $data = [
                'name' => $params['name'],
                'description' => $params['description'] ?? '',
                'config' => json_encode($params['config']),
                'status' => $params['status'] ?? 1,
                'width' => $params['width'] ?? 100,
                'height' => $params['height'] ?? 100,
                'update_time' => time()
            ];
            
            if (isset($params['id'])) {
                // 更新模板
                Db::name('print_template')->where('id', $params['id'])->update($data);
                $templateId = $params['id'];
            } else {
                // 新增模板
                $data['create_time'] = time();
                $templateId = Db::name('print_template')->insertGetId($data);
            }
            
            return ['status' => 1, 'data' => ['id' => $templateId]];
        } catch (\Exception $e) {
            Log::error('保存打印模板失败: ' . $e->getMessage());
            return ['status' => 0, 'msg' => '保存打印模板失败'];
        }
    }

    /**
     * 删除打印模板
     * @param int $id 模板ID
     * @return array
     */
    public function deletePrintTemplate($id)
    {
        try {
            Db::name('print_template')->where('id', $id)->delete();
            return ['status' => 1];
        } catch (\Exception $e) {
            Log::error('删除打印模板失败: ' . $e->getMessage());
            return ['status' => 0, 'msg' => '删除打印模板失败'];
        }
    }

    /**
     * 生成打印内容
     * @param array $params 打印参数
     * @return array
     */
    public function generatePrintContent($params)
    {
        try {
            // 参数验证
            if (empty($params['template_id'])) {
                return ['status' => 0, 'msg' => '模板ID不能为空'];
            }
            
            if (empty($params['qrcode_ids'])) {
                return ['status' => 0, 'msg' => '请选择要打印的二维码'];
            }
            
            // 获取模板详情
            $template = $this->getPrintTemplateDetail($params['template_id']);
            if ($template['status'] != 1) {
                return $template;
            }
            
            // 获取二维码信息
            $qrcodes = Db::name('product_qrcode')
                ->where('id', 'in', $params['qrcode_ids'])
                ->select()->toArray();
            
            if (empty($qrcodes)) {
                return ['status' => 0, 'msg' => '未找到指定的二维码'];
            }
            
            // 生成打印内容
            $printContent = $this->buildPrintContent($template['data'], $qrcodes);
            
            return [
                'status' => 1,
                'data' => [
                    'print_content' => $printContent,
                    'qrcodes' => $qrcodes
                ]
            ];
        } catch (\Exception $e) {
            Log::error('生成打印内容失败: ' . $e->getMessage());
            return ['status' => 0, 'msg' => '生成打印内容失败'];
        }
    }

    /**
     * 构建打印内容
     * @param array $template 模板信息
     * @param array $qrcodes 二维码列表
     * @return array
     */
    private function buildPrintContent($template, $qrcodes)
    {
        $printContent = [];
        
        foreach ($qrcodes as $qrcode) {
            $item = [
                'template' => $template,
                'data' => $this->formatQrcodeData($qrcode),
                'elements' => []
            ];
            
            // 根据模板配置生成元素
            foreach ($template['config']['elements'] as $element) {
                $formattedElement = $this->formatElement($element, $qrcode);
                $item['elements'][] = $formattedElement;
            }
            
            $printContent[] = $item;
        }
        
        return $printContent;
    }

    /**
     * 格式化二维码数据
     * @param array $qrcode 二维码信息
     * @return array
     */
    private function formatQrcodeData($qrcode)
    {
        // 获取商品信息
        $qrcodeService = new QrcodeService();
        $productInfo = $qrcodeService->getProductInfo($qrcode['product_id'], $qrcode['product_type']);
        
        return [
            'code' => $qrcode['code'],
            'product_name' => $productInfo['name'] ?? '',
            'product_id' => $qrcode['product_id'],
            'customer_name' => $qrcode['customer_name'] ?? '',
            'customer_phone' => $qrcode['customer_phone'] ?? '',
            'qrcode_url' => $qrcode['qrcode_url'] ?? '',
            'create_time' => date('Y-m-d H:i:s', $qrcode['create_time']),
            'remark' => $qrcode['remark'] ?? ''
        ];
    }

    /**
     * 格式化打印元素
     * @param array $element 元素配置
     * @param array $qrcode 二维码信息
     * @return array
     */
    private function formatElement($element, $qrcode)
    {
        $formattedElement = $element;
        
        // 根据元素类型处理内容
        switch ($element['type']) {
            case 'text':
                // 替换文本中的变量
                $formattedElement['content'] = $this->replaceVariables($element['content'], $qrcode);
                break;
            case 'qrcode':
                // 设置二维码URL
                $formattedElement['url'] = $qrcode['qrcode_url'] ?? '';
                break;
            case 'barcode':
                // 设置条形码内容
                $formattedElement['content'] = $qrcode['code'] ?? '';
                break;
            case 'image':
                // 处理图片元素
                break;
        }
        
        return $formattedElement;
    }

    /**
     * 替换文本中的变量
     * @param string $text 文本内容
     * @param array $qrcode 二维码信息
     * @return string
     */
    private function replaceVariables($text, $qrcode)
    {
        // 变量映射
        $variables = [
            '{code}' => $qrcode['code'] ?? '',
            '{product_name}' => $qrcode['product_info']['name'] ?? '',
            '{customer_name}' => $qrcode['customer_name'] ?? '',
            '{customer_phone}' => $qrcode['customer_phone'] ?? '',
            '{create_time}' => date('Y-m-d H:i:s', $qrcode['create_time'] ?? time()),
            '{remark}' => $qrcode['remark'] ?? ''
        ];
        
        // 替换变量
        return str_replace(array_keys($variables), array_values($variables), $text);
    }

    /**
     * 初始化打印模板表
     * @return array
     */
    public function initPrintTemplateTable()
    {
        try {
            // 创建打印模板表
            $createTableSql = "CREATE TABLE IF NOT EXISTS `print_template` (
              `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键',
              `name` varchar(100) NOT NULL COMMENT '模板名称',
              `description` text COMMENT '模板描述',
              `config` text NOT NULL COMMENT '模板配置（JSON格式）',
              `width` int(11) NOT NULL DEFAULT '100' COMMENT '模板宽度',
              `height` int(11) NOT NULL DEFAULT '100' COMMENT '模板高度',
              `status` tinyint(2) NOT NULL DEFAULT '1' COMMENT '状态（1=启用，0=禁用）',
              `create_time` int(11) NOT NULL COMMENT '创建时间',
              `update_time` int(11) NOT NULL COMMENT '更新时间',
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='打印模板表';";
            
            Db::execute($createTableSql);
            
            // 创建默认模板
            $this->createDefaultTemplate();
            
            return ['status' => 1];
        } catch (\Exception $e) {
            Log::error('初始化打印模板表失败: ' . $e->getMessage());
            return ['status' => 0, 'msg' => '初始化打印模板表失败'];
        }
    }

    /**
     * 创建默认打印模板
     * @return void
     */
    private function createDefaultTemplate()
    {
        // 检查是否已存在默认模板
        $count = Db::name('print_template')->count();
        if ($count > 0) {
            return;
        }
        
        // 默认模板配置
        $defaultConfig = [
            'elements' => [
                [
                    'type' => 'qrcode',
                    'x' => 10,
                    'y' => 10,
                    'width' => 80,
                    'height' => 80,
                    'url' => ''
                ],
                [
                    'type' => 'text',
                    'x' => 10,
                    'y' => 100,
                    'width' => 80,
                    'height' => 20,
                    'content' => '{code}',
                    'font_size' => 12,
                    'font_weight' => 'normal',
                    'text_align' => 'center',
                    'color' => '#000000'
                ],
                [
                    'type' => 'text',
                    'x' => 10,
                    'y' => 125,
                    'width' => 80,
                    'height' => 15,
                    'content' => '{product_name}',
                    'font_size' => 10,
                    'font_weight' => 'normal',
                    'text_align' => 'center',
                    'color' => '#666666'
                ]
            ]
        ];
        
        // 插入默认模板
        Db::name('print_template')->insert([
            'name' => '默认模板',
            'description' => '默认的一物一码打印模板',
            'config' => json_encode($defaultConfig),
            'width' => 100,
            'height' => 150,
            'status' => 1,
            'create_time' => time(),
            'update_time' => time()
        ]);
    }
}
