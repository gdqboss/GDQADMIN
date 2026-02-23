<?php
// JK客户定制

// custom_file(renovation_calculator)
namespace app\controller;
use think\facade\Db;
class ApiRenovationCalculator extends ApiCommon
{
	public function initialize(){
		parent::initialize();
		$this->checklogin();
	}
	public function form(){
		$info = Db::name('renovation_calculator')->where('aid',aid)->find();
		if(!$info || $info['status'] == 0) return $this->json(['status'=>-4,'msg'=>'功能未开启']);
		$info['bgcolorrgb'] = hex2rgb($info['bgcolor']);
		return $this->json(['status'=>1,'info'=>$info]);
	}
	public function result(){
		$info = Db::name('renovation_calculator')->where('aid',aid)->find();
		if(!$info || $info['status'] == 0) return $this->json(['status'=>-4,'msg'=>'功能未开启']);
		$info['bgcolorrgb'] = hex2rgb($info['bgcolor']);

		$region = explode(',',input('param.region'));
		$mianji = input('param.mianji');

		$areadata = json_decode($info['areadata'],true);
		$guigedata = json_decode($info['guigedata'],true);

		$type1param = $info['type1_param'];
		$type2param = $info['type2_param'];
		$type3param = $info['type3_param'];

		//区域参数
		$areaparam = 1;
		$areadata = json_decode($info['areadata'],true);
		foreach($areadata as $thisdata){
			$regionlist = explode('];',$thisdata['region']);
			foreach($regionlist as $j=>$regiondata){
				$regiondata = explode('[',$regiondata);
				if($regiondata[0] == '全国(默认运费)' || ($regiondata[0] == $region[0] && ($regiondata[1] == '全部地区' || in_array($region[1],explode(',',$regiondata[1]))))){
					$areaparam = $thisdata['areaparam'];
				}
			}
		}
		
		$totalprice1 = 0;
		$totalprice2 = 0;
		$totalprice3 = 0;
		foreach($guigedata as $k=>$v){
			$price1 = 0;
			$price2 = 0;
			$price3 = 0;
			foreach($v['items'] as $k2=>$v2){
				$gongshi = $v2['gongshi'];
				$gongshi = str_replace("[面积]",$mianji,$gongshi);
				$gongshi = str_replace("[区域参数]",$areaparam,$gongshi);
				$gongshi1 = str_replace("[类型参数]",$type1param,$gongshi);
				$gongshi2 = str_replace("[类型参数]",$type2param,$gongshi);
				$gongshi3 = str_replace("[类型参数]",$type3param,$gongshi);
				eval('$pricegs1='.$gongshi1.';');
				eval('$pricegs2='.$gongshi2.';');
				eval('$pricegs3='.$gongshi3.';');
				$guigedata[$k]['items'][$k2]['price'] = [$pricegs1,$pricegs2,$pricegs3];
				$totalprice1 += $pricegs1;
				$totalprice2 += $pricegs2;
				$totalprice3 += $pricegs3;
				$price1 += $pricegs1;
				$price2 += $pricegs2;
				$price3 += $pricegs3;
			}
			$guigedata[$k]['price'] = [$price1,$price2,$price3];
			$guigedata[$k]['pricew'] = [round($price1/10000,2).'万',round($price2/10000,2).'万',round($price3/10000,2).'万'];
			$guigedata[$k]['showitems'] = false;
		}

		$totalprice1w = round($totalprice1/10000,2).'万';
		$totalprice2w = round($totalprice2/10000,2).'万';
		$totalprice3w = round($totalprice3/10000,2).'万';

		$table = [];
		$table[] = ['lable'=>'简装','value'=>$totalprice1w];
		$table[] = ['lable'=>'精装','value'=>$totalprice2w];
		$table[] = ['lable'=>'豪装','value'=>$totalprice3w];
		return $this->json([
			'status'=>1,
			'info'=>$info,
			'cdata'=>$guigedata,
			'table'=>$table,
		]);
	}
	public function addressadd(){
		$type = input('param.type');
		if(request()->isPost()){
			$post = input('post.');
			if($type == 1){
				if(!$post['latitude'] || !$post['longitude']){
					return $this->json(['status'=>0,'msg'=>'请选择坐标点']);
				}
			}
			$data = array();
			$data['aid'] = aid;
			$data['mid'] = mid;
			$data['name'] = $post['name'];
			$data['tel'] = $post['tel'];
			$data['address'] = $post['address'];
			$data['createtime'] = time();
            $data['company'] = $post['company'];
			if($type == 1 || $post['latitude']){
				$data['area'] = $post['area'];
				$data['latitude'] = $post['latitude'];
				$data['longitude'] = $post['longitude'];
				if($data['latitude'] && !$data['province']){
					//通过坐标获取省市区
                    $mapqq = new \app\common\MapQQ();
                    $address = $mapqq->locationToAddress($data['latitude'],$data['longitude']);
					if($address['status']==1){
						$data['province'] = $address['province'];
						$data['city'] = $address['city'];
						$data['district'] = $address['district'];
					}
				}
			}else{
				$area = explode(',',$post['area']);
				$data['province'] = $area[0];
				$data['city'] = $area[1];
				$data['district'] = $area[2];
				$data['area'] = implode('',$area);
			}
			if($post['addressid']){
				Db::name('member_address')->where('id',$post['addressid'])->update($data);
			}else{
				$default = Db::name('member_address')->where('aid',aid)->where('mid',mid)->where('isdefault',1)->find();
				if(!$default) $data['isdefault'] = 1;
				Db::name('member_address')->insert($data);
			}
			return $this->json(['status'=>1,'msg'=>'保存成功']);
		}
		if(input('param.id')){
			$addressid = input('param.id/d');
			$address = Db::name('member_address')->where('aid',aid)->where('mid',mid)->where('id',$addressid)->find();
		}else{
			$address = [];
		}
		return $this->json(['status'=>1,'data'=>$address]);
	}
	//设置默认地址
	public function setdefault(){
		$from = input('param.from');
		$addressid = input('param.addressid/d');
		Db::name('member_address')->where('aid',aid)->where('mid',mid)->update(['isdefault'=>0]);
		Db::name('member_address')->where('aid',aid)->where('mid',mid)->where('id',$addressid)->update(['isdefault'=>1]);
		return $this->json(['status'=>1,'msg'=>'设置成功']);
	}
	//删除地址
	public function del(){
		$addressid = input('param.addressid/d');
		$rs = Db::name('member_address')->where('aid',aid)->where('mid',mid)->where('id',$addressid)->delete();
		if($rs){
			return $this->json(['status'=>1,'msg'=>'删除成功']);
		}else{
			return $this->json(['status'=>0,'msg'=>'删除失败']);
		}
	}

	//识别地址信息
	public function shibie(){
		$addressxx = input('param.addressxx');
		$postdata = [];
		$postdata['text'] = $addressxx;
		$rs = request_post('https://www.diandashop.com/index/api/address',$postdata);
		$rs = json_decode($rs,true);
		return $this->json($rs);
	}
}