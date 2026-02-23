<?php
// JK客户定制

//custom_file(map_mark)
namespace app\controller;
use think\facade\Db;
use app\custom\BaiduAi;
use app\validate\MapmarkOrder;
use think\View;

class ApiMapmark extends ApiCommon
{
    /**
     * 创建请求订单
     * @param ai_text 创作文本（不可超过100字）
     * @param ai_style 风格
     * @param img_bili 图片比例 1=>1:1 2=>2:3 3=>3:2
     * @return array|\think\response\Json
     */
	public function createOrder(){
	    $params = input();
        //验证传参内容
        try {
            $res = validate(MapmarkOrder::class)->check($params);
        } catch (ValidateException $e) {
            return $this->json(['status'=>0,'msg'=>$e->getError()]);
        }catch (\Exception $e) {
            return $this->json(['status'=>0,'msg'=>$e->getMessage()]);
        }
        $cids = input('cids');
        if(empty($cids)){
            return $this->json(['status'=>0,'msg'=>'请选择标注平台']);
        }
        $cats = Db::name('mapmark_category')->whereIn('id',$cids)->select()->toArray();
        if(count($cats)<=0){
            return $this->json(['status'=>0,'msg'=>'请选择要标注的地图平台']);
        }
        if(is_array($cids)){
            $cids = implode(',',$cids);
        }
        $money_arr = array_column($cats,'money');
        $money_total = array_sum($money_arr);
        $ordernum = \app\common\Common::generateOrderNo(aid);
	    $data = [];
        $data['aid'] = aid;
        $data['bid'] = bid;
	    $data['mid'] = $this->mid;
        $data['ordernum'] = $ordernum;

        $data['name'] = $params['name'];
        $data['shop_type'] = $params['shop_type'];
        $data['shop_tel'] = $params['shop_tel'];
        $data['shop_time'] = $params['shop_time'];
        $data['address'] = $params['address'];
        $data['mobile'] = $params['mobile'];
        $data['license_img'] = $params['license_img'];
        $data['shop_img'] = $params['shop_img'];

	    $data['mobile'] = $params['mobile'];
	    $data['license_img'] = $params['license_img'];//营业执照图片
        $data['shop_img'] = $params['shop_img'];//门面图片
        $data['cids'] = $cids;
        $data['pay_money'] = $money_total;
        $data['w_time'] = time();
        $oid = Db::name('mapmark_order')->insertGetId($data);

        //创建支付订单
        $payorderid = \app\model\Payorder::createorder(aid,bid,$data['mid'],'mapmark',$oid,$ordernum,'地图标注',$money_total,0);

        $result = [
            'payorderid' => $payorderid??0,
            'oid' => $oid
        ];
        return $this->json(['status'=>1,'data'=>$result,'msg'=>'提交成功']);
    }

    /**
     * 获取地图类型
     */
    public function getCatgory(){
        //查询条件
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['status','=',1];
        if(input('param.keyword')){
            $where[] = ['name','like','%'.input('param.keyword').'%'];
        }
        $datalist = Db::name('mapmark_category')
            ->where($where)
            ->order('sort desc,id desc')
            ->field('id,name,pic,money')
            ->select()
            ->toArray();
        $bgcolor = Db::name('mapmark_sysset')->where('aid',aid)->value('bgcolor');

        return $this->json(['status'=>1,'data'=>['datalist'=>$datalist,'bgcolor'=>$bgcolor]]);
    }
    //根据选取的地图分类获取支付金额
    public function getCatMoney(){
        $cids = input('cids');
        $money = Db::name('mapmark_category')->whereIn('id',$cids)->sum('money');

        return $this->json(['status'=>1,'msg'=>'查询成功','data'=>$money]);
    }

    //获取创建订单详情
    public function getDetail(){
        $id = input('id');
        $info = Db::name('mapmark_order')->where('id',$id)->find();
        $cids = $info['cids'];
        $cnames = Db::name('mapmark_category')->whereIn('id',$cids)->column('name');
        $info['cnames'] = $cnames;
        return $this->json(['satus'=>1,'data'=>$info]);
    }

    //获取订单列表
    public function getLists(){
        //分页数据
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $pernum = 20;
        //查询条件
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['status','=',1];

        $datalist = Db::name('mapmark_order')
            ->where($where)
            ->page($pagenum,$pernum)
            ->order('id desc')
            ->field('id,name,shop_type,shop_tel,shop_time,address,mobile,status')
            ->select()
            ->toArray();

        return $this->json(['status'=>1,'data'=>$datalist]);
    }

}