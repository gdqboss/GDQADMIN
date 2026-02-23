<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 拼团商城-商品管理
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class CollageProduct extends Common
{
	//商品列表
    public function index(){
		if($this->request->isAjax()){
			$page = $this->request->param('page');
			$limit = $this->request->param('limit');
			if($this->request->param('field') && $this->request->param('order')){
				$order = $this->request->param('field').' '.$this->request->param('order');
			}else{
				$order = 'id desc';
			}
			$where = array();
			$where[] = ['aid','=',aid];
			$original_bid = bid;
			if(bid==0){
				$bid_param = $this->request->param('bid');
				$bid = $bid_param;
				if($this->request->param('showallbusiness')){
					//显示全部
					if(!isset($bid)) $bid = 'all';
				}
				if($bid){
					if($bid == 'all'){
						$where[] = ['bid','>=',0];
					}else{
						$where[] = ['bid','=',$bid];
					}
				}elseif($this->request->param('showtype')==2){
					$where[] = ['bid','<>',0];
				}elseif($this->request->param('showtype')=='all'){
					$where[] = ['bid','>=',0];
				}else{
					$where[] = ['bid','=',0];
				}
				//显示全部时查询商家
				if($bid == 'all' && $this->request->param('bname')){
					$where2 = [];
					$where2[] = ['name','like','%'.$this->request->param('bname').'%'];
					$where2[] = ['aid','=',aid];
					$bids = Db::name('business')->where($where2)->column('id');
					if($bids){
						$where[] = ['bid','in',$bids];
					}else{
						$where[] = ['id','=',0];
					}
				}
			}else{
				$where[] = ['bid','=',$original_bid];
			}
			$name = $this->request->param('name');
			if($name) $where[] = ['name','like','%'.$name.'%'];
			$status = $this->request->param('status');
			if($this->request->has('status') && $status!=='') $where[] = ['status','=',$status];
			$cid = $this->request->param('cid/d');
			if($this->request->has('cid') && $cid!=='') $where[] = ['cid','=',$cid];
			$gid = $this->request->param('gid/d');
			if($this->request->has('gid') && $gid!=='') $where[] = Db::raw("find_in_set(".$gid.",gid)");

			$count = 0 + Db::name('collage_product')->where($where)->count();
		// 查询时确保包含promote_type字段，用于列表显示拼团类别
		$data = Db::name('collage_product')->where($where)->page($page,$limit)->order($order)->select()->toArray();

			$clist = Db::name('collage_category')->where('aid',aid)->select()->toArray();
			$cdata = array();
			foreach($clist as $c){
				$cdata[$c['id']] = $c['name'];
			}
			foreach($data as $k=>$v){
				$gglist = Db::name('collage_guige')->where('aid',aid)->where('proid',$v['id'])->select()->toArray();
				$ggdata = array();
				foreach($gglist as $gg){
					$ggdata[] = $gg['name'].' × '.$gg['stock'] .' <button class="layui-btn layui-btn-xs layui-btn-disabled">￥'.$gg['sell_price'].'</button>';
				}
				$data[$k]['cname'] = $cdata[$v['cid']];
				$data[$k]['ggdata'] = implode('<br>',$ggdata);
				if($v['bid'] > 0){
					$data[$k]['bname'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('name');
				}else{
					$data[$k]['bname'] = '平台自营';
				}
				$data[$k]['givescore'] = dd_money_format($v['givescore'],$this->score_weishu);
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		//分类
		$clist = Db::name('collage_category')->field('id,name')->where('aid',aid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$clist[$k]['child'] = Db::name('collage_category')->field('id,name')->where('aid',aid)->where('status',1)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray(); 
		}
		View::assign('clist',$clist);

		return View::fetch();
    }
    
    //编辑名店推广拼团商品
    public function edit1(){
        // 调试日志 - 确保目录存在
        $logDir = ROOT_PATH . 'runtime/log';
        if(!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        $logFile = $logDir . '/edit1_debug.txt';
        file_put_contents($logFile, 'edit1 method called at ' . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        file_put_contents($logFile, 'Request params: ' . json_encode($this->request->param()) . "\n", FILE_APPEND);
        
        $info = [];
        $newgglist = array();
        $aglevellist = [];
        $levellist = [];
        if($this->request->param('id')){
            $info = Db::name('collage_product')->where('aid',aid)->where('id',$this->request->param('id/d'))->find();
            if(!$info) showmsg('商品不存在');
            if(bid != 0 && $info['bid']!=bid) showmsg('无权限操作');
            // 将数据库中的promote_type值赋给group_type，用于表单显示
            $info['group_type'] = isset($info['promote_type']) ? $info['promote_type'] : 0;
            // 处理supplier_id，确保null值转换为空字符串，便于模板正确匹配
            if (isset($info['supplier_id']) && $info['supplier_id'] === null) {
                $info['supplier_id'] = '';
            }
        }
        //多规格
        $newgglist = array();
        if($info){
            $gglist = Db::name('collage_guige')->where('aid',aid)->where('proid',$info['id'])->select()->toArray();
            foreach($gglist as $k=>$v){
                $v['givescore'] = dd_money_format($v['givescore'],$this->score_weishu);
                if($v['ks']!==null){
                    $newgglist[$v['ks']] = $v;
                }else{
                    Db::name('collage_guige')->where('aid',aid)->where('id',$v['id'])->update(['ks'=>$k]);
                    $newgglist[$k] = $v;
                }
            }
            $info['leaderscore'] = dd_money_format($info['leaderscore'],$this->score_weishu);
        }
        //分类
        $clist = Db::name('collage_category')->field('id,name')->where('aid',aid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
        foreach($clist as $k=>$v){
            $clist[$k]['child'] = Db::name('collage_category')->field('id,name')->where('aid',aid)->where('status',1)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray(); 
        }
        $freightdata = array();
        if($info && $info['freightdata']){            
            $freightdata = Db::name('freight')->where('aid',aid)->where('id','in',$info['freightdata'])->order('sort desc,id')->select()->toArray();
        }
        
        if(false){}else { 
            $aglevellist = Db::name('member_level')->where('aid',aid)->where('can_agent','<>',0)->order('sort,id')->select()->toArray();
            $levellist = Db::name('member_level')->where('aid',aid)->order('sort,id')->select()->toArray();
        }
        // 获取门店信息
        $mendian_data = [];
        if($info && isset($info['mendian_ids']) && !empty($info['mendian_ids'])){
            $mendian_data = Db::name('mendian')->where('aid',aid)->where('id','in',$info['mendian_ids'])->select()->toArray();
        }
        // 获取供货商列表
		// 按id降序排列，确保排列顺序倒过来
		$supplierList = Db::name('product_supplier')
			->field('id, supplier_name as name')
			->order('id desc')
			->select()->toArray();
			View::assign('aglevellist',$aglevellist);
			View::assign('levellist',$levellist);
			View::assign('info',$info);
			View::assign('newgglist',$newgglist);
			View::assign('clist',$clist);
			View::assign('freightdata',$freightdata);
			View::assign('bid',bid);
			View::assign('mendian_data',$mendian_data);
			View::assign('supplierList',$supplierList);

			return View::fetch('edit1');
    }
	//编辑商品
	public function edit(){
		if($this->request->param('id')){
			$info = Db::name('collage_product')->where('aid',aid)->where('id',$this->request->param('id/d'))->find();
			if(!$info) showmsg('商品不存在');
			if(bid != 0 && $info['bid']!=bid) showmsg('无权限操作');
			// 将数据库中的promote_type值赋给group_type，用于表单显示
			$info['group_type'] = isset($info['promote_type']) ? $info['promote_type'] : 0;
			// 处理supplier_id，确保null值转换为空字符串，便于模板正确匹配
			if (isset($info['supplier_id']) && $info['supplier_id'] === null) {
				$info['supplier_id'] = '';
			}
		}
		//多规格
		$newgglist = array();
		if($info){
			// 从collage_guige表获取完整的规格数据，而不是只从collage_product表构建
			$gglist = Db::name('collage_guige')->where('aid',aid)->where('proid',$info['id'])->select()->toArray();
			if(!empty($gglist)) {
				foreach($gglist as $k=>$v){
					$v['givescore'] = dd_money_format($v['givescore'],$this->score_weishu);
					if($v['ks']!==null){
						$newgglist[$v['ks']] = $v;
					}else{
						Db::name('collage_guige')->where('aid',aid)->where('id',$v['id'])->update(['ks'=>$k]);
						$newgglist[$k] = $v;
					}
				}
			} else {
				// 如果collage_guige表中没有数据，则从collage_product表构建一个默认规格
				$newgglist[0] = [
					'name' => $info['name'],
					'ks' => 0,
					'goods_sn' => $info['goods_sn'],
					'market_price' => $info['market_price'],
					'sell_price' => $info['sell_price'],
					'leader_price' => $info['leader_price'],
					'leader_commission_rate' => $info['leader_commission_rate'],
					'weight' => $info['weight'],
					'stock' => $info['stock'],
					'givescore' => $info['leaderscore'],
					'pic' => $info['pic'],
				];
			}
			$info['leaderscore'] = dd_money_format($info['leaderscore'],$this->score_weishu);
		}
		//分类 - 保留分类列表用于表单选择
		$clist = Db::name('collage_category')->field('id,name')->where('aid',aid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$clist[$k]['child'] = Db::name('collage_category')->field('id,name')->where('aid',aid)->where('status',1)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray(); 
		}
		
		//会员等级 - 保留会员等级数据用于表单选择
		$aglevellist = Db::name('member_level')->where('aid',aid)->where('can_agent','<>',0)->order('sort,id')->select()->toArray();
		$levellist = Db::name('member_level')->where('aid',aid)->order('sort,id')->select()->toArray();
		
		// 获取供货商列表 - 保留供货商列表用于表单选择
		// 按id降序排列，确保排列顺序倒过来
		$supplierList = Db::name('product_supplier')
		->field('id, supplier_name as name')
		->order('id desc')
		->select()->toArray();
		
		// 商品数据从collage_product表获取
		View::assign('aglevellist',$aglevellist);
		View::assign('levellist',$levellist);
		View::assign('info',$info);
		View::assign('newgglist',$newgglist);
		View::assign('clist',$clist);
		View::assign('bid',bid);
		View::assign('supplierList',$supplierList);

		return View::fetch();
	}

	//批量团购（查询后选择再操作）
	public function batch(){
		// 商品分类（商城商品，用于筛选；平台用 shop_category，商家用 shop_category2）
		if(bid > 0){
			$shopClist = Db::name('shop_category2')->field('id,name')
				->where('aid',aid)->where('bid',bid)->where('pid',0)
				->order('sort desc,id')->select()->toArray();
			foreach($shopClist as $k=>$v){
				$child = Db::name('shop_category2')->field('id,name')
					->where('aid',aid)->where('bid',bid)->where('pid',$v['id'])
					->order('sort desc,id')->select()->toArray();
				foreach($child as $k2=>$v2){
					$child2 = Db::name('shop_category2')->field('id,name')
						->where('aid',aid)->where('bid',bid)->where('pid',$v2['id'])
						->order('sort desc,id')->select()->toArray();
					$child[$k2]['child'] = $child2;
				}
				$shopClist[$k]['child'] = $child;
			}
		}else{
			$shopClist = Db::name('shop_category')->field('id,name')
				->where('aid',aid)->where('pid',0)
				->order('sort desc,id')->select()->toArray();
			foreach($shopClist as $k=>$v){
				$child = Db::name('shop_category')->field('id,name')
					->where('aid',aid)->where('pid',$v['id'])
					->order('sort desc,id')->select()->toArray();
				foreach($child as $k2=>$v2){
					$child2 = Db::name('shop_category')->field('id,name')
						->where('aid',aid)->where('pid',$v2['id'])
						->order('sort desc,id')->select()->toArray();
					$child[$k2]['child'] = $child2;
				}
				$shopClist[$k]['child'] = $child;
			}
		}
		// 拼团分类（用于批量添加设置）
		$collageCate = Db::name('collage_category')->field('id,name')->where('aid',aid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray();
		foreach($collageCate as $k=>$v){
			$collageCate[$k]['child'] = Db::name('collage_category')->field('id,name')->where('aid',aid)->where('status',1)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
		}

		View::assign('shopClist',$shopClist);
		View::assign('collageCate',$collageCate);
		View::assign('bid',bid);
		return View::fetch();
	}
	// 简单的日志记录方法
	private function log($message) {
		// 使用绝对路径确保日志文件能被正确创建
		$logFile = __DIR__ . '/../../runtime/save_debug.log';
		// 确保目录存在
		$logDir = dirname($logFile);
		if (!is_dir($logDir)) {
			mkdir($logDir, 0777, true);
		}
		// 写入日志
		$logContent = date('Y-m-d H:i:s') . " - " . $message . "\n";
		file_put_contents($logFile, $logContent, FILE_APPEND);
	}
	
	// 自动添加缺少的字段到collage_product表
	private function autoAddMissingFields() {
		try {
			// 获取当前表结构
			$fields = Db::name('collage_product')->getFields();
			
			// 需要检查的字段列表
			$requiredFields = [
				'goods_sn' => [
					'type' => 'VARCHAR(100)',
					'default' => "''",
					'comment' => '规格编号',
					'after' => 'name'
				],
				'market_price' => [
					'type' => 'DECIMAL(10,2)',
					'default' => '0.00',
					'comment' => '单买价',
					'after' => 'goods_sn'
				],
				'sell_price' => [
					'type' => 'DECIMAL(10,2)',
					'default' => '0.00',
					'comment' => '拼团价',
					'after' => 'market_price'
				],
				'leader_price' => [
					'type' => 'DECIMAL(10,2)',
					'default' => '0.00',
					'comment' => '团长价',
					'after' => 'sell_price'
				],
				'leader_commission_rate' => [
					'type' => 'DECIMAL(5,2)',
					'default' => '0.00',
					'comment' => '团长佣金比例',
					'after' => 'leader_price'
				],
				'weight' => [
					'type' => 'DECIMAL(10,2)',
					'default' => '0.00',
					'comment' => '重量',
					'after' => 'leader_commission_rate'
				],
				'stock' => [
					'type' => 'INT(11)',
					'default' => '0',
					'comment' => '库存',
					'after' => 'weight'
				],
				'xn_view_num' => [
					'type' => 'INT(11)',
					'default' => '0',
					'comment' => '虚拟浏览数',
					'after' => 'endtime'
				],
				'xn_share_num' => [
					'type' => 'INT(11)',
					'default' => '0',
					'comment' => '虚拟分享数',
					'after' => 'xn_view_num'
				],
				'supplier_id' => [
					'type' => 'INT(11)',
					'default' => 'NULL',
					'comment' => '供货商ID',
					'after' => 'mendian_ids'
				]
			];
			
			// 检查并添加缺少的字段
			foreach ($requiredFields as $fieldName => $fieldConfig) {
				if (!isset($fields[$fieldName])) {
					// 构建ALTER TABLE语句
					$sql = "ALTER TABLE `ddwx_collage_product` ";
					$sql .= "ADD COLUMN `{$fieldName}` {$fieldConfig['type']} NULL DEFAULT {$fieldConfig['default']} COMMENT '{$fieldConfig['comment']}'";
					if (isset($fieldConfig['after'])) {
						$sql .= " AFTER `{$fieldConfig['after']}`";
					}
					$sql .= ";";
					
					// 执行SQL
					Db::execute($sql);
					
					// 记录日志
					$this->log("Added missing field: {$fieldName}");
				}
			}
			
			return true;
		} catch (\Exception $e) {
			$this->log("Error auto-adding fields: " . $e->getMessage());
			return false;
		}
	}
	
	//保存商品
	public function save(){
		// 设置JSON响应头
		header('Content-Type: application/json');
		
		try {
			// 自动添加缺少的字段
			$this->autoAddMissingFields();
			
			// 获取所有POST数据
			$postData = $this->request->post();
			$info = isset($postData['info']) ? $postData['info'] : [];
			$id = isset($postData['id']) ? intval($postData['id']) : 0;

			// 基本验证
			if(empty($info['name'])) {
				return json(['status' => 0, 'msg' => '请填写商品名称']);
			}

			// 获取当前商品信息（如果是编辑）
			$currentInfo = [];
			if($id) {
				$currentInfo = Db::name('collage_product')->where('aid',aid)->where('id', $id)->find();
				if(!$currentInfo) {
					return json(['status' => 0, 'msg' => '商品不存在']);
				}
			}

			// 准备更新数据 - 处理所有表单字段
			$data = [
				'name' => $info['name'],
				'cid' => isset($info['cid']) ? intval($info['cid']) : 0,
				// 总是更新supplier_id字段，不管用户是否选择
				'supplier_id' => isset($info['supplier_id']) && $info['supplier_id'] !== '' ? intval($info['supplier_id']) : null,
				// 商品图片
				'pic' => isset($info['pic']) ? $info['pic'] : (isset($currentInfo['pic']) ? $currentInfo['pic'] : ''),
				'pics' => isset($info['pics']) ? $info['pics'] : (isset($currentInfo['pics']) ? $currentInfo['pics'] : ''),
				// 商品卖点
				'sellpoint' => isset($info['sellpoint']) ? $info['sellpoint'] : (isset($currentInfo['sellpoint']) ? $currentInfo['sellpoint'] : ''),
				// 拼团设置
				'teamnum' => isset($info['teamnum']) ? intval($info['teamnum']) : (isset($currentInfo['teamnum']) ? $currentInfo['teamnum'] : 3),
				'buymax' => isset($info['buymax']) ? intval($info['buymax']) : (isset($currentInfo['buymax']) ? $currentInfo['buymax'] : 0),
				'teamhour' => isset($info['teamhour']) ? intval($info['teamhour']) : (isset($currentInfo['teamhour']) ? $currentInfo['teamhour'] : 36),
				// 团长奖励
				'leaderscore' => isset($info['leaderscore']) ? $info['leaderscore'] : (isset($currentInfo['leaderscore']) ? $currentInfo['leaderscore'] : 10),
				// 抽成费率
				'feepercent' => isset($info['feepercent']) ? $info['feepercent'] : (isset($currentInfo['feepercent']) ? $currentInfo['feepercent'] : ''),
				// 配送设置
				'freighttype' => isset($info['freighttype']) ? $info['freighttype'] : (isset($currentInfo['freighttype']) ? $currentInfo['freighttype'] : 1),
				'freightdata' => isset($info['freightdata']) ? $info['freightdata'] : (isset($currentInfo['freightdata']) ? $currentInfo['freightdata'] : ''),
				'contact_require' => isset($info['contact_require']) ? $info['contact_require'] : (isset($currentInfo['contact_require']) ? $currentInfo['contact_require'] : 0),
				'freightcontent' => isset($info['freightcontent']) ? $info['freightcontent'] : (isset($currentInfo['freightcontent']) ? $currentInfo['freightcontent'] : ''),
				// 拼团推广类型
				'promote_type' => isset($info['promote_type']) ? $info['promote_type'] : (isset($currentInfo['promote_type']) ? $currentInfo['promote_type'] : 0),
				// 分销设置
				'commissionset' => isset($info['commissionset']) ? $info['commissionset'] : (isset($currentInfo['commissionset']) ? $currentInfo['commissionset'] : 1),
				'commissiondata1' => isset($info['commissiondata1']) ? $info['commissiondata1'] : (isset($currentInfo['commissiondata1']) ? $currentInfo['commissiondata1'] : ''),
				'commissiondata2' => isset($info['commissiondata2']) ? $info['commissiondata2'] : (isset($currentInfo['commissiondata2']) ? $currentInfo['commissiondata2'] : ''),
				'commissiondata3' => isset($info['commissiondata3']) ? $info['commissiondata3'] : (isset($currentInfo['commissiondata3']) ? $currentInfo['commissiondata3'] : ''),
				// 商品推荐
				'show_recommend' => isset($info['show_recommend']) ? $info['show_recommend'] : (isset($currentInfo['show_recommend']) ? $currentInfo['show_recommend'] : 0),
				'recommend_productids' => isset($info['recommend_productids']) ? $info['recommend_productids'] : (isset($currentInfo['recommend_productids']) ? $currentInfo['recommend_productids'] : ''),
				// 积分赠送时间
				'givescore_time' => isset($info['givescore_time']) ? $info['givescore_time'] : (isset($currentInfo['givescore_time']) ? $currentInfo['givescore_time'] : 0),
				// 拼团类型和时间
				'collage_type' => isset($info['collage_type']) ? $info['collage_type'] : (isset($currentInfo['collage_type']) ? $currentInfo['collage_type'] : 0),
				'starttime' => isset($info['starttime']) ? strtotime($info['starttime']) : (isset($currentInfo['starttime']) ? $currentInfo['starttime'] : null),
				'endtime' => isset($info['endtime']) ? strtotime($info['endtime']) : (isset($currentInfo['endtime']) ? $currentInfo['endtime'] : null),
				// 虚拟数据
				'xn_view_num' => isset($info['xn_view_num']) ? intval($info['xn_view_num']) : (isset($currentInfo['xn_view_num']) ? $currentInfo['xn_view_num'] : 0),
				'xn_share_num' => isset($info['xn_share_num']) ? intval($info['xn_share_num']) : (isset($currentInfo['xn_share_num']) ? $currentInfo['xn_share_num'] : 0),
				// 参团限制
				'is_many_times' => isset($info['is_many_times']) ? $info['is_many_times'] : (isset($currentInfo['is_many_times']) ? $currentInfo['is_many_times'] : 0),
				'max_times' => isset($info['max_times']) ? intval($info['max_times']) : (isset($currentInfo['max_times']) ? $currentInfo['max_times'] : 0),
				// 特殊设置
				'is_rzh' => isset($info['is_rzh']) ? $info['is_rzh'] : (isset($currentInfo['is_rzh']) ? $currentInfo['is_rzh'] : 0),
				'relation_type' => isset($info['relation_type']) ? $info['relation_type'] : (isset($currentInfo['relation_type']) ? $currentInfo['relation_type'] : -1),
				'house_status' => isset($info['house_status']) ? $info['house_status'] : (isset($currentInfo['house_status']) ? $currentInfo['house_status'] : 1),
				'group_status' => isset($info['group_status']) ? $info['group_status'] : (isset($currentInfo['group_status']) ? $currentInfo['group_status'] : 0),
				'group_ids' => isset($info['group_ids']) ? $info['group_ids'] : (isset($currentInfo['group_ids']) ? $currentInfo['group_ids'] : ''),
				// 门店设置
				'mendian_ids' => isset($info['mendian_ids']) ? $info['mendian_ids'] : (isset($currentInfo['mendian_ids']) ? $currentInfo['mendian_ids'] : ''),
				// 余额支付
				'moneypay' => isset($info['moneypay']) ? $info['moneypay'] : (isset($currentInfo['moneypay']) ? $currentInfo['moneypay'] : 1),
				// 阶梯团设置
				'jieti_data' => isset($postData['jt']) ? json_encode($postData['jt']) : (isset($currentInfo['jieti_data']) ? $currentInfo['jieti_data'] : ''),
				// 其他字段
				'guigedata' => isset($info['guigedata']) ? $info['guigedata'] : (isset($currentInfo['guigedata']) ? $currentInfo['guigedata'] : ''),
			];

			// 处理规格数据
			if(isset($postData['specs']) && !empty($postData['specs'])) {
				// 将规格数据转换为JSON格式并保存到collage_product表
				$data['guigedata'] = $postData['specs'];
			} elseif(isset($info['guigedata']) && !empty($info['guigedata'])) {
				// 如果specs不存在，但info中有guigedata，使用info中的值
				$data['guigedata'] = $info['guigedata'];
			}
			
			// 处理commissiondata1, commissiondata2, commissiondata3数据
			if(isset($postData['commissiondata1']) && !empty($postData['commissiondata1'])) {
				$data['commissiondata1'] = json_encode($postData['commissiondata1']);
			} elseif(isset($info['commissiondata1']) && !empty($info['commissiondata1'])) {
				$data['commissiondata1'] = json_encode($info['commissiondata1']);
			}
			if(isset($postData['commissiondata2']) && !empty($postData['commissiondata2'])) {
				$data['commissiondata2'] = json_encode($postData['commissiondata2']);
			} elseif(isset($info['commissiondata2']) && !empty($info['commissiondata2'])) {
				$data['commissiondata2'] = json_encode($info['commissiondata2']);
			}
			if(isset($postData['commissiondata3']) && !empty($postData['commissiondata3'])) {
				$data['commissiondata3'] = json_encode($postData['commissiondata3']);
			} elseif(isset($info['commissiondata3']) && !empty($info['commissiondata3'])) {
				$data['commissiondata3'] = json_encode($info['commissiondata3']);
			}
			
			// 处理销量字段 - 假设销量字段名为sales
			if(isset($info['sales'])) {
				$data['sales'] = intval($info['sales']);
			}
			
			// 处理规格相关字段 - 保存到collage_product表中
			// 支持多种规格数据格式：ggdata、gg、option、specs
			$spec_data = null;
			
			// 检查并获取规格数据
			if(isset($postData['ggdata']) && !empty($postData['ggdata'])) {
				// JSON格式的规格数据
				$spec_data = json_decode($postData['ggdata'], true);
			} elseif(isset($postData['gg']) && is_array($postData['gg'])) {
				// 数组格式的规格数据
				$spec_data = $postData['gg'];
			} elseif(isset($postData['option']) && is_array($postData['option'])) {
				// 前端动态生成的option格式规格数据
				$spec_data = $postData['option'];
			} elseif(isset($postData['specs']) && !empty($postData['specs'])) {
				// specs格式的规格数据（前端通过JSON.stringify(window.specs)提交）
				$spec_data = json_decode($postData['specs'], true);
			}
			
			// 如果有规格数据，则保存到collage_product表的对应字段
			if($spec_data && is_array($spec_data)) {
				// 检查spec_data是否是前端提交的规格组格式
				$isFrontendSpecsFormat = false;
				foreach($spec_data as $spec) {
					if(isset($spec['title']) && isset($spec['items'])) {
						$isFrontendSpecsFormat = true;
						break;
					}
				}
				
				if($isFrontendSpecsFormat) {
					// 前端提交的规格组格式，不需要提取具体规格字段
					// 已经通过info数组或其他方式获取了这些字段
					// 跳过规格字段提取，避免覆盖已有的正确数据
				} else {
					// 后端期望的规格详情格式，提取规格字段
					foreach($spec_data as $k => $gg) {
						// 确保必填字段存在
						if(isset($gg['name']) && !empty($gg['name'])) {
							// 保存规格相关字段到collage_product表
							$data['goods_sn'] = isset($gg['goods_sn']) ? $gg['goods_sn'] : (isset($gg['procode']) ? $gg['procode'] : '');
							$data['market_price'] = isset($gg['market_price']) ? $gg['market_price'] : 0;
							$data['sell_price'] = isset($gg['sell_price']) ? $gg['sell_price'] : 0;
							$data['leader_price'] = isset($gg['leader_price']) ? $gg['leader_price'] : 0;
							$data['leader_commission_rate'] = isset($gg['leader_commission_rate']) ? $gg['leader_commission_rate'] : 0;
							$data['weight'] = isset($gg['weight']) ? $gg['weight'] : 0;
							$data['stock'] = isset($gg['stock']) ? $gg['stock'] : 0;
							break; // 只取第一个规格
						}
					}
				}
			}

			// 如果是编辑商品
			if($id > 0) {
				// 编辑商品 - 直接使用id更新，不使用aid条件
				$affected_rows = Db::name('collage_product')
					->where('id', $id)
					->update($data);
			} else {
				// 添加商品 - 使用全局aid和bid变量
				$data['aid'] = aid;
				$data['bid'] = bid;
				$data['createtime'] = time();
				$id = Db::name('collage_product')->insertGetId($data);
			}
			
			// 保存规格数据到collage_guige表
			// 支持多种规格数据格式：gg、option
			$guige_data = null;
			
			// 检查并获取规格数据
			if(isset($postData['gg']) && is_array($postData['gg'])) {
				// 数组格式的规格数据
				$guige_data = $postData['gg'];
			} elseif(isset($postData['option']) && is_array($postData['option'])) {
				// 前端动态生成的option格式规格数据
				$guige_data = $postData['option'];
			}
			
			// 如果有规格数据，则保存到collage_guige表
			if($guige_data && is_array($guige_data)) {
				// 先删除该商品的所有旧规格数据
				Db::name('collage_guige')
					->where('aid', aid)
					->where('proid', $id)
					->delete();
				
				// 保存新的规格数据
				foreach($guige_data as $k => $gg) {
					// 确保必填字段存在
					if(isset($gg['name']) && !empty($gg['name'])) {
						// 准备规格数据
						$gg_data = [
							'aid' => aid,
							'proid' => $id,
							'name' => $gg['name'],
							'ks' => $k,
							'goods_sn' => isset($gg['goods_sn']) ? $gg['goods_sn'] : (isset($gg['procode']) ? $gg['procode'] : ''),
							'market_price' => isset($gg['market_price']) ? $gg['market_price'] : 0,
							'sell_price' => isset($gg['sell_price']) ? $gg['sell_price'] : 0,
							'cost_price' => isset($gg['cost_price']) ? $gg['cost_price'] : 0,
							'weight' => isset($gg['weight']) ? $gg['weight'] : 0,
							'stock' => isset($gg['stock']) ? $gg['stock'] : 0,
							'leader_price' => isset($gg['leader_price']) ? $gg['leader_price'] : 0,
							'leader_commission_rate' => isset($gg['leader_commission_rate']) ? $gg['leader_commission_rate'] : 0,
							'pic' => isset($gg['pic']) ? $gg['pic'] : '',
							'givescore' => isset($gg['givescore']) ? $gg['givescore'] : 0,
						];
						
						// 插入规格数据到collage_guige表
						Db::name('collage_guige')->insert($gg_data);
					}
				}
			}

			// 同步collage_product和collage_guige表的共同字段
			try {
				// 获取collage_product表的最新数据
				$productData = Db::name('collage_product')->where('id', $id)->find();
				
				if ($productData) {
					// 获取collage_guige表的字段列表（使用ThinkPHP的方法自动处理表前缀）
					$guigeModel = Db::name('collage_guige');
					// 获取表前缀
					$prefix = $guigeModel->getConnection()->getConfig('prefix');
					// 构建完整表名
					$fullTableName = $prefix . 'collage_guige';
					// 使用完整表名查询
					$guigeFieldResult = Db::query("DESCRIBE {$fullTableName}");
					$guigeFields = [];
					foreach ($guigeFieldResult as $field) {
						$guigeFields[] = $field['Field'];
					}
					
					// 1. 将collage_product的字段同步到所有对应的collage_guige记录
					$syncData = [];
					// 只同步collage_guige表中实际存在的字段
					$fieldsToSync = ['supplier_id', 'freighttype', 'freightdata', 'contact_require', 
							'freightcontent', 'promote_type', 'commissionset', 'commissiondata1', 
							'commissiondata2', 'commissiondata3', 'givescore_time', 'collage_type', 
							'starttime', 'endtime', 'is_rzh', 'relation_type', 'house_status', 
							'group_status', 'group_ids', 'mendian_ids', 'moneypay', 'feepercent'];
					
					// 过滤出collage_guige表中实际存在的字段
					$validFields = array_intersect($fieldsToSync, $guigeFields);
					
					foreach ($validFields as $field) {
						if (isset($productData[$field])) {
							$syncData[$field] = $productData[$field];
						}
					}
					
					if (!empty($syncData)) {
						Db::name('collage_guige')
							->where('proid', $id)
							->update($syncData);
					}
					
					// 2. 将collage_guige的非空字段同步到collage_product（如果product中对应字段为空）
					// 获取第一个规格的数据
					$firstGuige = Db::name('collage_guige')
						->where('proid', $id)
						->find();
					
					if ($firstGuige) {
						$productUpdateData = [];
						foreach ($validFields as $field) {
							// 如果product中的字段为空，且guige中有值，则更新
							if (isset($firstGuige[$field]) && !empty($firstGuige[$field]) && 
								(!isset($productData[$field]) || empty($productData[$field]))) {
								$productUpdateData[$field] = $firstGuige[$field];
							}
						}
						
						if (!empty($productUpdateData)) {
							Db::name('collage_product')
								->where('id', $id)
								->update($productUpdateData);
						}
					}
				}
			} catch (Exception $e) {
				// 记录错误，但不影响主流程
				$this->log("同步字段错误: " . $e->getMessage());
			}
			
			// 返回成功结果
			return json(['status' => 1, 'msg' => '操作成功']);
			
		} catch (\Exception $e) {
        // 记录错误日志
        $this->log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        // 返回错误信息
        return json(['status' => 0, 'msg' => '操作失败：' . $e->getMessage()]);
    }
	}

	//改状态
	public function setst(){
		$st = $this->request->post('st/d');
		$ids = $this->request->post('ids/a');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['id','in',$ids];
		if(bid !=0){
			$where[] = ['bid','=',bid];
		}
		Db::name('collage_product')->where($where)->update(['status'=>$st]);

		\app\common\System::plog('拼团商品修改状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//审核
	public function setcheckst(){
		$st = $this->request->post('st/d');
		$id = $this->request->post('id/d');
		$reason = $this->request->post('reason');
		$up = Db::name('collage_product')->where('aid',aid)->where('id',$id)->update(['ischecked'=>$st,'check_reason'=>$reason]);
		if($up){
            }
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function del(){
		$ids = $this->request->post('ids/a');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['id','in',$ids];
		if(bid != 0){
			$where[] = ['bid','=',bid];
		}
		Db::name('collage_product')->where($where)->delete();
		\app\common\System::plog('拼团商品删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//复制商品
	public function procopy(){
		$product = Db::name('collage_product')->where('aid',aid)->where('bid',bid)->where('id',$this->request->post('id/d'))->find();
		if(!$product) return json(['status'=>0,'msg'=>'商品不存在,请重新选择']);
		$gglist = Db::name('collage_guige')->where('aid',aid)->where('proid',$product['id'])->select()->toArray();
		$data = $product;
		$data['name'] = '复制-'.$data['name'];
		unset($data['id']);
		$data['status'] = 0;
		$newproid = Db::name('collage_product')->insertGetId($data);
		foreach($gglist as $gg){
			$ggdata = $gg;
			$ggdata['proid'] = $newproid;
			unset($ggdata['id']);
			Db::name('collage_guige')->insert($ggdata);
		}
		\app\common\System::plog('复制拼团商品'.$newproid);
		return json(['status'=>1,'msg'=>'复制成功','proid'=>$newproid]);
	}
	
	//选择商品
	public function chooseproduct(){
		//分类
		$clist = Db::name('collage_category')->field('id,name')->where('aid',aid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$clist[$k]['child'] = Db::name('collage_category')->field('id,name')->where('aid',aid)->where('status',1)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray(); 
		}
		View::assign('clist',$clist);

		$hidebid = $this->request->has('hidebid')?$this->request->param('hidebid/d'):0;
		if(bid) $hidebid = 1;
		//商户
		$blist = [];
		if(!$hidebid){
			$blist = Db::name('business')->where('aid',aid)->order('sort desc,id desc')->select()->toArray();
		}
		View::assign('hidebid',$hidebid);
		View::assign('blist',$blist);

		return View::fetch();
	}
	//获取商品信息
	public function getproduct(){
		$proid = $this->request->post('proid/d');
		$product = Db::name('collage_product')->where('aid',aid)->where('id',$proid)->find();
		//多规格
		$newgglist = array();
		$gglist = Db::name('collage_guige')->where('aid',aid)->where('proid',$product['id'])->select()->toArray();
		foreach($gglist as $k=>$v){
			$newgglist[$v['ks']] = $v;
		}
		$guigedata = json_decode($product['guigedata']);
		return json(['product'=>$product,'gglist'=>$newgglist,'guigedata'=>$guigedata]);
	}
	
	// 调试方法：检查数据库结构
	public function checkdb(){
		// 检查collage_product表结构
		$collage_fields = Db::name('collage_product')->getFields();
		// 检查order表结构
		$order_fields = Db::name('order')->getFields();
		// 检查collage_order表结构
		$collage_order_fields = Db::name('collage_order')->getFields();
		// 检查product_supplier表结构
		$supplier_fields = Db::name('product_supplier')->getFields();
		// 检查product_supplier数据
		$supplier_list = Db::name('product_supplier')->where('aid', aid)->where('supplier_status', 1)->select()->toArray();
		
		// 检查并添加supplier_id字段到collage_product表
		if(!isset($collage_fields['supplier_id'])) {
			try {
				Db::execute("ALTER TABLE `ddwx_collage_product` ADD `supplier_id` INT(11) NULL DEFAULT NULL COMMENT '供货商ID' AFTER `mendian_ids`");
				$collage_has_supplier_id = true;
				// 重新获取字段信息
				$collage_fields = Db::name('collage_product')->getFields();
			} catch (Exception $e) {
				$collage_has_supplier_id = false;
			}
		} else {
			$collage_has_supplier_id = true;
		}
		
		// 检查并添加supplier_id字段到collage_order表
		$collage_order_has_supplier_id = isset($collage_order_fields['supplier_id']);
		if(!$collage_order_has_supplier_id) {
			try {
				Db::execute("ALTER TABLE `ddwx_collage_order` ADD `supplier_id` INT(11) NULL DEFAULT NULL COMMENT '供货商ID' AFTER `product_id`");
				$collage_order_has_supplier_id = true;
				// 重新获取字段信息
				$collage_order_fields = Db::name('collage_order')->getFields();
			} catch (Exception $e) {
				$collage_order_has_supplier_id = false;
			}
		}
		
		return json([
			'collage_product_fields' => $collage_fields,
			'order_fields' => $order_fields,
			'collage_order_fields' => $collage_order_fields,
			'product_supplier_fields' => $supplier_fields,
			'supplier_list' => $supplier_list,
			'collage_has_supplier_id' => $collage_has_supplier_id,
			'collage_order_has_supplier_id' => $collage_order_has_supplier_id,
			'order_has_supplier_id' => isset($order_fields['supplier_id'])
		]);
	}
}