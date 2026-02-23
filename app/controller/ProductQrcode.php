<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 一物一码管理控制器
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\Db;
use think\facade\View;

class ProductQrcode extends Base
{
    /**
     * 二维码列表
     * @return void
     */
    public function index()
    {
        $title = t('二维码列表');
        View::assign('title', $title);
        return View::fetch();
    }
    
    /**
     * 生成二维码
     * @return void
     */
    public function generate()
    {
        $title = t('生成二维码');
        View::assign('title', $title);
        return View::fetch();
    }
    
    /**
     * 批量生成二维码
     * @return void
     */
    public function batchGenerate()
    {
        $title = t('批量生成');
        View::assign('title', $title);
        return View::fetch();
    }
    
    /**
     * 服务记录
     * @return void
     */
    public function serviceLogs()
    {
        $title = t('服务记录');
        View::assign('title', $title);
        return View::fetch();
    }
    
    /**
     * 扫码统计
     * @return void
     */
    public function statistics()
    {
        $title = t('扫码统计');
        View::assign('title', $title);
        return View::fetch();
    }
    
    /**
     * 打印设计
     * @return void
     */
    public function printDesign()
    {
        $title = t('打印设计');
        View::assign('title', $title);
        return View::fetch();
    }
}
