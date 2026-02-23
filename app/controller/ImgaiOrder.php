<?php
// JK客户定制

//custom_file(image_ai)
// +----------------------------------------------------------------------
// | 绘画风格分类
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class ImgaiOrder extends Common
{
    public function initialize(){
		parent::initialize();
	}
	//列表
    public function index(){
		if(request()->isAjax()){
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'id desc';
			}
			$where = [];
			$where[] = ['o.aid','=',aid];
			if(input('ai_style')){
                $where[] = ['o.ai_style','like','%'.input('ai_style').'%'];
            }
            if(input('mid')){
                $where[] = ['o.mid','=',input('mid')];
            }
            $status = input('status');

            if($status!=null && $status!='all'){
                $where[] = ['o.status','=',$status];
            }
			$field = 'o.*,m.nickname';
            $data = Db::name('imgai_order')
                ->alias('o')
                ->join('member m','o.mid=m.id','left')
                ->where($where)
                ->field($field)
                ->order($order)
                ->select()
                ->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>count($data),'data'=>$data]);
		}
		return View::fetch();
    }
	public function view(){
        $id = input('id');
        $where = [];
        $where[] = ['o.id','=',$id];
        $field = 'o.*,m.nickname';
        $info = Db::name('imgai_order')
            ->alias('o')
            ->join('member m','o.mid=m.id','left')
            ->where($where)
            ->field($field)
            ->order('id desc')
            ->find();
        if(empty($info['pic']) && $info['taskId']){
            $baidu_ai = new \app\custom\BaiduAi($info['aid']);

            $res =$baidu_ai->getImg(['taskId'=>$info['taskId']]);
            //dump($res);exit;
            if(!$res['status']){
                return ['status'=>0,'msg'=>$res['msg']];
            }
            $img_arr = array_column($res['data']['data']['imgUrls'],'image');

            //更新订单数据
            $img_str = implode(',',$img_arr);
            $img_str = rtrim($img_str,',');

            Db::name('imgai_order')->where('id',$info['id'])->update(['pic'=>$img_str,'query_res'=>json_encode($res)]);
            $info['pic'] = $img_str;
        }
        View::assign('info',$info);
        return View::fetch();
    }
	
}
