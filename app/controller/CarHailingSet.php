<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 约车 - 系统设置 custom_file(car_hailing)
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class CarHailingSet extends Common
{
    public function initialize(){
        parent::initialize();
        if(bid > 0) showmsg('无访问权限');
    }
    public function set(){
        $info = Db::name('car_hailing_set')->where('aid',aid)->where('bid',bid)->find();
        if(!$info){
            Db::name('car_hailing_set')->insert(['aid'=>aid,'bid'=>bid]);
            $info = Db::name('car_hailing_set')->where('aid',aid)->where('bid',bid)->find();
        }
        
        View::assign('info',$info);
        return View::fetch();
    }
    public function save(){
        $info = input('post.info/a');
        if($info['yyzhouqi']) $info['yyzhouqi'] =  implode(',',$info['yyzhouqi']);
        
        $start_ratio =input('post.start_data');
        if($start_ratio){
            $startdata = json_decode($start_ratio,true);
            $start_ratio_data = [];
            foreach($startdata as $key=>$val){
                if(!empty($val['start_time'])){
                    $start_ratio_data[] = $val;
                }
            }
        }
        $sort = array_column($start_ratio_data,'start_time');
        array_multisort($sort,SORT_DESC ,$start_ratio_data);
        $info['start_time_ratio'] =$start_ratio_data?json_encode($start_ratio_data,JSON_UNESCAPED_UNICODE):null;
        $end_ratio =input('post.end_data');
        if($end_ratio){
            $enddata = json_decode($end_ratio,true);
            $end_ratio_data = [];
            foreach($enddata as $key=>$val){
                if(!empty($val['end_time'])){
                    $end_ratio_data[] = $val;
                }
            }
        }                           
        $sort = array_column($end_ratio_data,'end_time');
        array_multisort($sort,SORT_ASC ,$end_ratio_data);
        $info['end_time_ratio'] =$end_ratio_data?json_encode($end_ratio_data,JSON_UNESCAPED_UNICODE):null;
        Db::name('car_hailing_set')->where('aid',aid)->where('bid',bid)->update($info);

        \app\common\System::plog('约车系统设置');
        return json(['status'=>1,'msg'=>'保存成功','url'=>true]);
    }

}
