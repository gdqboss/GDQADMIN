<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\controller\BarcodeInventory;
use think\facade\Session;

class TestApi extends Command
{
    protected function configure()
    {
        $this->setName('test:api')->setDescription('Test BarcodeInventory API');
    }

    protected function execute(Input $input, Output $output)
    {
        // 设置session变量
        Session::set('aid', 1);
        Session::set('bid', 0);
        
        // 创建控制器实例
        $controller = new BarcodeInventory();
        
        // 测试index方法
        $output->writeln('Testing BarcodeInventory/index method...');
        
        try {
            // 模拟Ajax请求
            $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
            
            // 调用index方法
            $result = $controller->index();
            
            // 输出结果
            $output->writeln('Result: ' . json_encode($result));
        } catch (\Exception $e) {
            $output->writeln('Error: ' . $e->getMessage());
            $output->writeln('Trace: ' . $e->getTraceAsString());
        }
    }
}