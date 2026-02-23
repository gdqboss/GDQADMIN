<?php
// JK客户定制


//custom_file(douyin_groupbuy)
// +----------------------------------------------------------------------
// | 抖音团购-商品分类
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class DouyinGroupbuyCategory extends Common
{
    public function initialize(){
        parent::initialize();
    }
    //分类列表
    public function index(){
        if(request()->isAjax()){
            if(input('param.field') && input('param.order')){
                $order = input('param.field').' '.input('param.order');
            }else{
                $order = 'id';
            }
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['bid','=',bid];
            $data = [];
            $cate0 = Db::name('douyin_groupbuy_category')->where($where)->where('pid',0)->order($order)->select()->toArray();
            foreach($cate0 as $c0){
                $c0['deep'] = 0;
                $data[] = $c0;
                $cate1 = Db::name('douyin_groupbuy_category')->where($where)->where('pid',$c0['id'])->order($order)->select()->toArray();
                foreach($cate1 as $k1=>$c1){
                    if($k1 < count($cate1)-1){
                        $c1['name'] = $c1['name'];
                    }else{
                        $c1['name'] = $c1['name'];
                    }
                    $c1['deep'] = 1;
                    $data[] = $c1;
                    $cate2  = Db::name('douyin_groupbuy_category')->where($where)->where('pid',$c1['id'])->order($order)->select()->toArray();
                    foreach($cate2 as $k2=>$c2){
                        if($k2 < count($cate2)-1){
                            $c2['name'] = $c2['name'];
                        }else{
                            $c2['name'] = $c2['name'];
                        }
                        $c2['deep'] = 2;
                        $data[] = $c2;
                    }
                }
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>count($cate0),'data'=>$data]);
        }
        return View::fetch();
    }
    //同步
    public function tongbu(){
        $checkset = \app\custom\DouyinGroupbuyCustom::checkset(aid,bid);
        if($checkset['status'] == 0){
            return json($checkset);
        }
        $set = $checkset['set'];

        $set['account_id'] = $set['account_id']?$set['account_id']:'';
        //返回树形结构子类目信息
        $res = \app\custom\DouyinGroupbuyCustom::getcategory(aid,bid,0,$set['account_id']);
        if($res['status'] ==1){
            //默认获取商品类目树列表
            $categorys = $res['data']['data']['category_tree_infos'];
            if($categorys){
                foreach($categorys as $cat){
                    self::addcat(aid,bid,$cat,0,'');
                }
            }
            return json(['status'=>1, 'msg'=>'同步完成']);
        }else{
            $msg = $res && $res['msg']?$res['msg']:'同步失败';
            return json(['status'=>0, 'msg'=>$msg]);
        }
    }

    public static function addcat($aid,$bid,$cat,$pid=0,$account_id=0){
        //查询是否添加过
        $insertid = Db::name('douyin_groupbuy_category')->where('id',$cat['category_id'])->where('aid',$aid)->where('bid',$bid)->value('id');
        if(!$insertid){
            $data = [];
            $data['aid'] = $aid;
            $data['bid'] = $bid;
            $data['pid'] = $pid;
            $data['account_id']  = $account_id?$account_id:0;
            $data['id']          = $cat['category_id'];
            $data['category_id'] = $cat['category_id'];
            $data['name']        = $cat['name'] && !empty($cat['name'])?$cat['name']:'';
            $data['parent_id']   = $cat['parent_id'] && !empty($cat['parent_id'])?$cat['parent_id']:0;
            $data['level']       = $cat['level'] && !empty($cat['level'])?$cat['level']:0;
            $data['is_leaf']     = $cat['is_leaf'] && !empty($cat['is_leaf'])?1:0;
            $data['enable']      = $cat['enable'] && !empty($cat['enable'])?1:0;
            $data['createtime']  = time();
            $insert = Db::name('douyin_groupbuy_category')->insertGetId($data);
            if($insert){
                $insertid = $cat['category_id'];
            }
        }
        
        if($insertid && $cat['sub_tree_infos'] && !empty($cat['sub_tree_infos'])){
            $childs = $cat['sub_tree_infos'];
            if($childs){
                foreach($childs as $child){
                    self::addcat($aid,$bid,$child,$insertid,$account_id);
                }
            }
        }
    }

    //选择分类弹窗
    public function choosecategory(){
        $selmore = input('selmore')?true:false;//是否多选
        if(request()->isAjax()){
            if(input('param.field') && input('param.order')){
                $order = input('param.field').' '.input('param.order');
            }else{
                $order = 'id';
            }
            $where = [];
            $where[] = ['aid','=',aid];
            $data = [];
            $cate0 = Db::name('shop_category')->where('aid',aid)->where('pid',0)->order($order)->select()->toArray();
            foreach($cate0 as $c0){
                $c0['showname'] = $c0['name'];
                $c0['deep'] = 0;
                $data[] = $c0;
                $cate1 = Db::name('shop_category')->where('aid',aid)->where('pid',$c0['id'])->order($order)->select()->toArray();
                foreach($cate1 as $k1=>$c1){
                    if($k1 < count($cate1)-1){
                        $c1['showname'] = '<span style="color:#aaa">&nbsp;&nbsp;&nbsp;&nbsp;├ </span>'.$c1['name'];
                    }else{
                        $c1['showname'] = '<span style="color:#aaa">&nbsp;&nbsp;&nbsp;&nbsp;└ </span>'.$c1['name'];
                    }
                    $c1['deep'] = 1;
                    $data[] = $c1;
                    $cate2 = Db::name('shop_category')->where('aid',aid)->where('pid',$c1['id'])->order($order)->select()->toArray();
                    foreach($cate2 as $k2=>$c2){
                        if($k2 < count($cate2)-1){
                            $c2['showname'] = '<span style="color:#aaa">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;├ </span>'.$c2['name'];
                        }else{
                            $c2['showname'] = '<span style="color:#aaa">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;└ </span>'.$c2['name'];
                        }
                        $c2['deep'] = 2;
                        $data[] = $c2;
                    }
                }
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>count($cate0),'data'=>$data]);
        }
        View::assign('selmore',$selmore);
        return View::fetch();
    }
}