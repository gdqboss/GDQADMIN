<?php
namespace app\controller;
use think\facade\Db;

class ApiComment extends ApiCommon
{
    public function initialize(){
        // 覆盖父类初始化，防止 aid 或 checklogin 的干扰
        // parent::initialize(); 
    }

    public function all()
    {
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $pernum = 20;
        
        // 移除 aid 限制，只查询 status=1 的所有评价
        $where = [];
        $where[] = ['status', '=', 1];
        
        // 如果需要支持特定 aid，可以通过参数传入，但不强制
        if(input('param.aid')){
             $where[] = ['aid', '=', input('param.aid')];
        }

        $count = Db::name('collage_comment')->where($where)->count();
        
        $datalist = Db::name('collage_comment')
            ->where($where)
            ->page($pagenum, $pernum)
            ->order('createtime desc')
            ->select()
            ->toArray();

        if (!$datalist) $datalist = [];
        
        foreach ($datalist as $k => $pl) {
            $datalist[$k]['createtime'] = date('Y-m-d H:i', $pl['createtime']);
            if ($datalist[$k]['content_pic']) {
                $datalist[$k]['content_pic'] = explode(',', $datalist[$k]['content_pic']);
            }
            
            // 优先使用评价表里的快照信息
            if(!empty($pl['proname'])){
                 $datalist[$k]['product_name'] = $pl['proname'];
                 $datalist[$k]['product_pic'] = $pl['propic'];
            }else{
                 // 获取商品信息
                $product = Db::name('collage_product')->field('name,pic')->where('id', $pl['proid'])->find();
                if($product){
                    $datalist[$k]['product_name'] = $product['name'];
                    $datalist[$k]['product_pic'] = $product['pic'];
                }else{
                    $datalist[$k]['product_name'] = '';
                    $datalist[$k]['product_pic'] = '';
                }
            }
            
            // 补全用户信息
            if(empty($pl['nickname']) || empty($pl['headimg'])){
                 $member = Db::name('member')->field('nickname,headimg')->where('id', $pl['mid'])->find();
                 if($member){
                     $datalist[$k]['nickname'] = $member['nickname'];
                     $datalist[$k]['headimg'] = $member['headimg'];
                 }
            }
            
            // 标记类型
            $datalist[$k]['type'] = 'collage';
        }

        return json(['status' => 1, 'data' => $datalist, 'total' => $count]);
    }
}
