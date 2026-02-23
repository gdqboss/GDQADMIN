<?php /*a:4:{s:50:"/www/wwwroot/gdqshop.cn/app/view/mendian/edit.html";i:1747926690;s:48:"/www/wwwroot/gdqshop.cn/app/view/public/css.html";i:1747926690;s:47:"/www/wwwroot/gdqshop.cn/app/view/public/js.html";i:1747926690;s:54:"/www/wwwroot/gdqshop.cn/app/view/public/copyright.html";i:1747926690;}*/ ?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>标记点管理</title>
  <meta name="renderer" content="webkit">
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=0">
  <link rel="stylesheet" type="text/css" href="/static/admin/layui/css/layui.css?v=20200519" media="all">
<link rel="stylesheet" type="text/css" href="/static/admin/layui/css/modules/formSelects-v4.css?v=20200516" media="all">
<link rel="stylesheet" type="text/css" href="/static/admin/css/admin.css?v=202406" media="all">
<link rel="stylesheet" type="text/css" href="/static/admin/css/font-awesome.min.css?v=20200516" media="all">
<link rel="stylesheet" type="text/css" href="/static/admin/webuploader/webuploader.css?v=<?php echo time(); ?>" media="all">
<link rel="stylesheet" type="text/css" href="/static/admin/css/designer.css?v=202410" media="all">
<link rel="stylesheet" type="text/css" href="/static/fonts/iconfont.css?v=20201218" media="all">
    <style>
        .map-search-res:hover{
            background-color: gray;
        }
    </style>
    <script type="text/javascript">
		window._AMapSecurityConfig = {
			securityJsCode:'<?php echo $sysset_webinfo["js_code_amap"]; ?>',
		}
	</script>
</head>
<body>
  <div class="layui-fluid">
    <div class="layui-row layui-col-space15">
      <div class="layui-card layui-col-md12">
				<div class="layui-card-header">
					<?php if(!$info['id']): ?><i class="fa fa-plus"></i> 添加<?php echo t('门店'); else: ?><i class="fa fa-pencil"></i> 编辑<?php echo t('门店'); ?><?php endif; ?>
					<i class="layui-icon layui-icon-close" style="font-size:18px;font-weight:bold;cursor:pointer" onclick="closeself()"></i>
				</div>
				<div class="layui-card-body" pad15>
					<div class="layui-form form-label-w8">
						<input type="hidden" name="info[id]" value="<?php echo $info['id']; ?>"/>
                        <?php if(getcustom('mendian_list')): ?>
                        <div class="layui-form-item">
                            <label class="layui-form-label">分类：</label>
                            <div class="layui-input-inline">
                                <select name="info[cid]">
                                    <option value="">--请选择--</option>
                                    <?php foreach($clist as $cv): ?>
                                    <option value="<?php echo $cv['id']; ?>" <?php if($cv['id']==$info['cid']): ?>selected<?php endif; ?>><?php echo $cv['name']; ?></option>
                                    <?php foreach($cv['child'] as $v): ?>
                                    <option value="<?php echo $v['id']; ?>" <?php if($v['id']==$info['cid']): ?>selected<?php endif; ?>>&nbsp;&nbsp;&nbsp;<?php echo $v['name']; ?></option>
                                    <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <?php endif; ?>
						<div class="layui-form-item">
							<label class="layui-form-label"><span class="redstar">*</span>名称：</label>
							<div class="layui-input-inline" style="width:200px">
								<input type="text" name="info[name]" lay-verify="required" lay-verType="tips" class="layui-input" value="<?php echo $info['name']; ?>">
							</div>
						</div>
						<?php if(getcustom('douyin_groupbuy')): ?>
                        <div class="layui-form-item">
							<label class="layui-form-label">抖音来客门店ID：</label>
							<div class="layui-input-inline" style="width:200px">
								<input type="text" name="info[poi_id]" class="layui-input" value="<?php echo $info['poi_id']; ?>">
							</div>
							<div class="layui-form-mid layui-word-aux">抖音团购核销时使用，登录<a href="https://life.douyin.com/p/login" target="_blank">抖音来客</a>在店铺管理-门店管理查找</div>
							<div class="layui-form-mid layui-word-aux">添加完后请到抖音团购商品那同步此门店商品</div>
						</div>
                        <?php endif; ?>
						<div class="layui-form-item">
							<label class="layui-form-label"><?php echo t('门店'); ?>主图：</label>
							<button style="float:left;margin-right: 10px;" type="button" class="layui-btn layui-btn-primary" upload-input="pic" upload-preview="picPreview" onclick="uploader(this)">上传图片</button>
							<div class="layui-form-mid layui-word-aux">建议尺寸：640×640像素</div>
							<div id="picPreview" class="picsList-class-padding">
								<div class="layui-imgbox imgbox-required">
									<div class="layui-imgbox-img"><img src="<?php echo $info['pic']; ?>"/></div>
									<input type="text" name="info[pic]" id="pic" class="layui-input" value="<?php echo $info['pic']; ?>">
								</div>
							</div>
						</div>
						<div class="layui-form-item">
							<label class="layui-form-label"><?php echo t('门店'); ?>图片：</label>
							<input type="hidden" name="info[pics]" value="<?php echo $info['pics']; ?>" id="pics">
							<button style="float:left;margin-right: 10px;" type="button" class="layui-btn layui-btn-primary" onclick="uploader(this,true)" upload-input="pics" upload-preview="picList" >批量上传</button>
							<div class="layui-form-mid layui-word-aux">建议尺寸：640×640像素</div>
							<div id="picList" class="picsList-class-padding">
								<?php if($info['pics']): $pics = explode(',',$info['pics']); foreach($pics as $pic): ?>
								<div class="layui-imgbox">
									<a class="layui-imgbox-close" href="javascript:void(0)" onclick="$(this).parent().remove();getpicsval('pics','picList')" title="删除"><i class="layui-icon layui-icon-close-fill-opaque"></i></a>
									<span class="layui-imgbox-img"><img src="<?php echo $pic; ?>"></span>
								</div>
								<?php endforeach; ?><?php endif; ?>
							</div>
						</div>
                       
						<div class="layui-form-item">
							<label class="layui-form-label">摘要：</label>
							<div class="layui-input-inline" style="width:700px">
								<input type="text" name="info[subname]" class="layui-input" value="<?php echo $info['subname']; ?>">
							</div>
						</div>
						<div class="layui-form-item">
							<label class="layui-form-label">详情：</label>
							<div class="layui-input-inline" style="width:800px">
								<script id="content" name="info[content]" type="text/plain" style="width:100%;height:260px"><?php echo $info['content']; ?></script>
							</div>
						</div>
						<div class="layui-form-item">
							<label class="layui-form-label">联系电话：</label>
							<div class="layui-input-inline" style="width:500px">
								<input type="text" id="tel" name="info[tel]" class="layui-input" value="<?php echo $info['tel']; ?>">
							</div>
						</div>
						<?php if($mendian_upgrade): ?>
						<div class="layui-form-item">
							<label class="layui-form-label">会员ID：</label>
							<div class="layui-input-inline" style="width:200px">
								<input type="text" id="mid" name="info[mid]" class="layui-input" value="<?php echo $info['mid']; ?>">
							</div>
							<div class="layui-form-mid layui-word-aux">请填写会员ID</div>
						</div>
						<div class="layui-form-item">
							<label class="layui-form-label">推荐人ID：</label>
							<div class="layui-input-inline" style="width:200px">
								<input type="text" id="pid" name="info[pid]" class="layui-input" value="<?php echo $info['pid']; ?>">
							</div>
							<div class="layui-form-mid layui-word-aux">无推荐人请填写0</div>
						</div>
						 <div class="layui-form-item">
                            <label class="layui-form-label">分组：</label>
                            <div class="layui-input-inline">
                                <select name="info[groupid]">
                                    <option value="">--请选择--</option>
                                    <?php foreach($grouplist as $cv): ?>
                                    <option value="<?php echo $cv['id']; ?>" <?php if($cv['id']==$info['groupid']): ?>selected<?php endif; ?>><?php echo $cv['name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
						 <div class="layui-form-item">
                            <label class="layui-form-label">等级：</label>
                            <div class="layui-input-inline">
                                <select name="info[levelid]">
                                    <option value="">--请选择--</option>
                                    <?php foreach($levellist as $cv): ?>
                                    <option value="<?php echo $cv['id']; ?>" <?php if($cv['id']==$info['levelid']): ?>selected<?php endif; ?>><?php echo $cv['name']; ?></option>
                               
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
						<div class="layui-form-item">
							<label class="layui-form-label">小区名称：</label>
							<div class="layui-input-inline" style="width:200px">
								<input type="text" id="xqname" name="info[xqname]" class="layui-input" value="<?php echo $info['xqname']; ?>">
							</div>
						</div>
						<?php endif; ?>
						
						<div class="layui-form-item">
							<label class="layui-form-label"><span class="redstar">*</span>地址：</label>
                            <div style="display: flex;flex-direction: column;">

								<?php if($mendian_upgrade): ?>
								<div class="areaDiv" style="margin-bottom: 8px">
									   <div class="layui-input-inline" style="width: 150px"><select name="info[province]" id="province" lay-filter="province"></select>   </div>
									<!-- 市 -->
									 <div class="layui-input-inline" style="width: 150px"><select  name="info[province]" id="city" lay-filter="city"></select> </div>
									<!-- 区 -->
									 <div class="layui-input-inline" style="width: 150px"><select name="info[district]" id="district" lay-filter="district"></select> </div>
									<!-- 街道 -->
									 <div class="layui-input-inline" style="width: 150px"><select name="info[street]" id="street"></select> </div>


								</div>
								<?php else: ?>
                                <div class="areaDiv" style="margin-bottom: 8px">
                                    <div class="layui-input-inline" style="width: 150px">
                                        <select name="info[province]" id="province" lay-filter="province" <?php if(!$info['id']): ?>lay-verify="required"<?php endif; ?>></select>
                                    </div>
                                    <div class="layui-input-inline" style="width: 150px">
                                        <select name="info[city]" id="city" lay-filter="city"></select>
                                    </div>
                                    <div class="layui-input-inline" style="width: 150px;display:none">
                                        <!--							<select name="custom[form<?php echo $customeKey; ?>][]" id="region<?php echo $customeKey; ?>_area"></select>-->
                                        <select name="info[district]" id="district"></select>
                                    </div>
                                </div>
								<?php endif; ?>




                                <div class="adressDiv">
                                    <div class="layui-input-inline" style="width:500px">
                                        <input type="text" id="address" name="info[address]" class="layui-input layui-input-search" value="<?php echo $info['address']; ?>">
                                        <div style="position: absolute;width: 500px;z-index: 100;" class="search-keywords">

                                        </div>
                                    </div>
                                    <button class="layui-btn layui-btn-primary" id="searchbtn">搜索</button>
                                </div>
                            </div>
						</div>



						<div class="layui-form-item">
							<label class="layui-form-label"></label>
							<div id="l-map" style="width:800px;height:500px">地图加载中...</div>
							<div style="margin-left: 160px;margin-top:10px;">
								<div class="layui-input-inline" style="width:200px">
									<input type="text" name="info[longitude]" id="mapjd" class="layui-input" value="<?php echo (isset($info['longitude']) && ($info['longitude'] !== '')?$info['longitude']:'116.39782905578613'); ?>"/>
								</div>
								<div class="layui-input-inline" style="width:200px">
									<input type="text" name="info[latitude]" id="mapwd" class="layui-input" value="<?php echo (isset($info['latitude']) && ($info['latitude'] !== '')?$info['latitude']:'39.90358020251377'); ?>"/>
								</div>
							</div>
						</div>
						<?php if(getcustom('xixie')): ?>
                            <div class="layui-form-item">
                                <label class="layui-form-label">余额：</label>
                                <div class="layui-input-inline" style="width:500px">
                                    <input type="text" id="money" name="info[money]" disabled="disabled" class="layui-input" value="<?php echo $info['money']; ?>">
                                </div>
                            </div>
							<div class="layui-form-item">
								<label class="layui-form-label">手续费：</label>
								<div class="layui-input-inline">
									<input type="text" id="withdrawfee" name="info[withdrawfee]" class="layui-input" value="<?php echo $info['withdrawfee']; ?>">
								</div>
                                <div class="layui-form-mid layui-word-aux" >%</div>
							</div>
                            <div class="layui-form-item">
                                <label class="layui-form-label">最低提现金额：</label>
                                <div class="layui-input-inline">
                                    <input type="text" id="withdrawmin" name="info[withdrawmin]" class="layui-input" value="<?php echo $info['withdrawmin']; ?>">
                                </div>
                                <div class="layui-form-mid layui-word-aux" ></div>
                            </div>
                            <div class="layui-form-item">
                                <label class="layui-form-label">配送费：</label>
                                <div class="layui-input-inline" style="width: 100px;">
                                    <input type="text" name="info[peisong_juli1]" class="layui-input" value="<?php echo $info['peisong_juli1']; ?>">
                                </div>
                                <div class="layui-form-mid">公里内</div>
                                <div class="layui-input-inline" style="width: 100px;">
                                    <input type="text" name="info[peisong_fee1]" class="layui-input" value="<?php echo $info['peisong_fee1']; ?>">
                                </div>
                                <div class="layui-form-mid">元；每超出</div>
                                <div class="layui-input-inline" style="width: 100px;">
                                    <input type="text" name="info[peisong_juli2]" class="layui-input" value="<?php echo $info['peisong_juli2']; ?>">
                                </div>
                                <div class="layui-form-mid">公里，加</div>
                                <div class="layui-input-inline" style="width: 100px;">
                                    <input type="text" name="info[peisong_fee2]" class="layui-input" value="<?php echo $info['peisong_fee2']; ?>">
                                </div>
                                <div class="layui-form-mid">元</div>
                            </div>
                            <div class="layui-form-item">
                                <label class="layui-form-label">服务范围：</label>
                                <div class="layui-input-block" style="width:300px">
                                    <input type="radio" name="info[peisong_rangetype]"  value="0" title="圆形范围" <?php if(!$info['id'] || $info['peisong_rangetype']==0): ?>checked<?php endif; ?> lay-filter="rangetypeset">
                                    <input type="radio" name="info[peisong_rangetype]"  value="1" title="多边形范围" <?php if($info['id'] && $info['peisong_rangetype']==1): ?>checked<?php endif; ?> lay-filter="rangetypeset">
                                </div>
                                <div class="form-group" style="margin-left: 160px;padding-top:10px;padding-bottom:5px">
                                    <script src="https://webapi.amap.com/maps?v=1.4.0&key=<?php echo $sysset_webinfo['map_key_amap']; ?>&plugin=AMap.PolyEditor,AMap.CircleEditor"></script>
                                    <div id="container" class="map" style="width:800px;height: 600px;"></div>
                                </div>
                                <div id="rangetypeset0" style="<?php if($info['id'] && $info['peisong_rangetype']==1): ?>display:none<?php endif; ?>">
                                    <div class="layui-form-mid" style="margin-left:100px;">中心点坐标</div>
                                    <div class="layui-input-inline" style="width: 100px;">
                                        <input type="text" name="info[peisong_lng]"  class="layui-input" value="<?php echo $info['peisong_lng']; ?>">
                                    </div>
                                    <div class="layui-form-mid">,</div>
                                    <div class="layui-input-inline" style="width: 100px;">
                                        <input type="text" name="info[peisong_lat]"  class="layui-input" value="<?php echo $info['peisong_lat']; ?>">
                                    </div>
                                    <div class="layui-form-mid">服务半径</div>
                                    <div class="layui-input-inline" style="width: 100px;">
                                        <input type="text" name="info[peisong_range]"  class="layui-input" value="<?php echo $info['peisong_range']; ?>">
                                    </div>
                                    <div class="layui-form-mid">米</div>
                                </div>
                                <div id="rangetypeset1" style="<?php if(!$info['id'] || $info['peisong_rangetype']==0): ?>display:none<?php endif; ?>">
                                    <input type="hidden" name="info[peisong_rangepath]" class="layui-input" value="<?php echo $info['peisong_rangepath']; ?>">
                                    <div class="layui-form-mid" style="margin-left:160px;">注：多边形的形状尽量简洁，不要有交叉线</div>
                                    <div class="layui-form-item">
                                        <label class="layui-form-label">服务点：</label>
                                        <div class="layui-input-inline" style="width:150px">
                                            <input type="text" name="info[peisong_lng2]" value="<?php echo $info['peisong_lng2']; ?>" class="layui-input">
                                        </div>
                                        <div class="layui-form-mid">-</div>
                                        <div class="layui-input-inline" style="width:150px">
                                            <input type="text" name="info[peisong_lat2]" value="<?php echo $info['peisong_lat2']; ?>" class="layui-input">
                                        </div>
                                        <button class="layui-btn layui-btn-primary" onclick="choosezuobiao()" style="float:left">选择坐标</button>
                                    </div>

                                </div>
                            </div>

                            <div class="layui-form-item">
                                <label class="layui-form-label">可预约时间：</label>
                                <div class="layui-input-inline" style="width:500px">
                                    <input type="radio" name="info[datetype]" value="1" title="时间段" <?php if(!$info['datetype'] || $info['datetype']==1): ?>checked<?php endif; ?> lay-filter="datetype">
                                    <input type="radio" name="info[datetype]" value="2" title="时间点" <?php if($info['datetype']==2): ?>checked<?php endif; ?> lay-filter="datetype">
                                </div>
                            </div>
                            <div  id="datebox1" style="<?php if($info['datetype'] && $info['datetype']!=1): ?>display:none<?php endif; ?>">
                                <div class="layui-form-item">
                                    <label class="layui-form-label">设置时间段：</label>
                                    <div class="layui-form-mid">早</div>
                                    <div class="layui-input-inline" style="width: 100px;">
                                        <input type="text" name="info[zaohour]" class="layui-input" value="<?php echo !empty($info['zaohour']) ? $info['zaohour'] : '7'; ?>">
                                    </div>
                                    <div class="layui-form-mid">时；晚</div>
                                    <div class="layui-input-inline" style="width: 100px;">
                                        <input type="text" name="info[wanhour]" class="layui-input" value="<?php echo !empty($info['wanhour']) ? $info['wanhour'] : '18'; ?>">
                                    </div>
                                    <div class="layui-form-mid">时</div>
                                </div>
                                <div class="layui-form-item">
                                    <label class="layui-form-label">时间间隔：</label>
                                    <div class="layui-input-inline">
                                        <input type="text" name="info[timejg]" value="<?php echo !empty($info['id']) ? $info['timejg'] : '30'; ?>" class="layui-input">
                                    </div>
                                        <div class="layui-form-mid layui-word-aux">分钟</div>
                                </div>
                            </div>
                            <div  id="datebox2" style="<?php if($info['datetype']!=2): ?>display:none<?php endif; ?>">
                                <?php if($info['datetype']==2 && $info['timepoint']): ?>
                                <div id="timedata">
                                    <?php foreach($info['timepoint'] as $k=>$t): ?>
                                    <div class="layui-form-item">
                                        <label class="layui-form-label"><?php if($k==0): ?>选择时间点：<?php endif; ?></label>                                      
                                            <div class="layui-input-inline">
                                                <input type="text" name="info[timepoint][]" value="<?php echo $t; ?>"  class="layui-input timepoint">    
                                            </div>
                                            <?php if($k==0): ?>
                                            <button class="layui-btn layui-btn-primary" onclick="addtime()"><i class="fa fa-plus"></i></button>
                                            <?php else: ?>
                                            <button class="layui-btn layui-btn-primary" onclick="removetime(this)"><i class="fa fa-minus"></i></button>
                                            <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div id="timedata">
                                    <div class="layui-form-item">
                                        <label class="layui-form-label">选择时间点：</label>
                                        <div class="layui-input-inline">
                                            <input type="text" name="info[timepoint][]" value="00:00"  class="layui-input timepoint">   
                                        </div>
                                        <?php if($k==0): ?>
                                        <button class="layui-btn layui-btn-primary" onclick="addtime()"><i class="fa fa-plus"></i></button>
                                        <?php else: ?>
                                        <button class="layui-btn layui-btn-primary" onclick="removetime(this)"><i class="fa fa-minus"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </div>  
                                <?php endif; ?>
                            </div>
                            <div class="layui-form-item">
                                <label class="layui-form-label">预约人数：</label>
                                <div class="layui-input-inline">
                                    <input type="text" name="info[yynum]" value="<?php echo !empty($info['id']) ? $info['yynum'] : '1'; ?>" class="layui-input">
                                </div>
                                    <div class="layui-form-mid layui-word-aux">同一时间预约人数限制，默认为1</div>
                            </div>
                            <div class="layui-form-item"  >
                                    <label class="layui-form-label">可预约日期：</label>
                                    <div class="layui-input-inline" style="width:500px">
                                        <input type="radio" name="info[rqtype]" value="1" title="按周期" <?php if(!$info['rqtype'] || $info['rqtype']==1): ?>checked<?php endif; ?> lay-filter="rqtype">
                                        <input type="radio" name="info[rqtype]" value="2" title="按时间" <?php if($info['rqtype']==2): ?>checked<?php endif; ?> lay-filter="rqtype">                      
                                        <input type="radio" name="info[rqtype]" value="3" title="按固定周期" <?php if($info['rqtype']==3): ?>checked<?php endif; ?> lay-filter="rqtype">
                                    </div>
                                </div>
                                <div class="layui-form-item"  id="yydatebox1" style="<?php if($info['rqtype'] && $info['rqtype']!=1): ?>display:none<?php endif; ?>">
                                    <label class="layui-form-label">预约周期：</label>
                                    <div class="layui-input-inline" style="width:600px">
                                            <input type="checkbox" name="info[yyzhouqi][]" <?php if(in_array(1,$info['yyzhouqi'])): ?>checked<?php endif; ?> value="1"  title="周一" lay-skin="primary">
                                            <input type="checkbox" name="info[yyzhouqi][]" <?php if(in_array(2,$info['yyzhouqi'])): ?>checked<?php endif; ?> value="2"  title="周二" lay-skin="primary">
                                            <input type="checkbox" name="info[yyzhouqi][]" <?php if(in_array(3,$info['yyzhouqi'])): ?>checked<?php endif; ?> value="3"  title="周三" lay-skin="primary">
                                            <input type="checkbox" name="info[yyzhouqi][]" <?php if(in_array(4,$info['yyzhouqi'])): ?>checked<?php endif; ?> value="4"  title="周四" lay-skin="primary">
                                            <input type="checkbox" name="info[yyzhouqi][]" <?php if(in_array(5,$info['yyzhouqi'])): ?>checked<?php endif; ?> value="5"  title="周五" lay-skin="primary">
                                            <input type="checkbox" name="info[yyzhouqi][]" <?php if(in_array(6,$info['yyzhouqi'])): ?>checked<?php endif; ?> value="6"  title="周六" lay-skin="primary">
                                            <input type="checkbox" name="info[yyzhouqi][]" <?php if(in_array(0,$info['yyzhouqi'])): ?>checked<?php endif; ?> value="0"  title="周日" lay-skin="primary">
                                    </div>
                                    <!-- <div class="layui-form-mid layui-word-aux"><a href="groupadd.php">创建分组</a></div> -->
                                </div>
                                <div class="layui-form-item"  id="yydatebox2" style="<?php if($info['rqtype']!=2): ?>display:none<?php endif; ?>">
                                    <label class="layui-form-label">选择日期：</label>
                                    <div class="layui-input-inline" style="width:600px">
                                        <div class="layui-input-inline" style="width:150px">
                                            <input type="text" name="info[yybegintime]" class="layui-input" value="<?php echo $info['yybegintime']; ?>" id="yybegintime" autocomplete="off">
                                        </div>
                                        <div class="layui-form-mid">到</div>
                                        <div class="layui-input-inline" style="width:150px">
                                            <input type="text" name="info[yyendtime]" class="layui-input" value="<?php echo $info['yyendtime']; ?>" id="yyendtime" autocomplete="off">
                                        </div>
                                        <div class="layui-form-mid layui-word-aux layui-clear"></div>  
                                    </div>
                                    <!-- <div class="layui-form-mid layui-word-aux"><a href="groupadd.php">创建分组</a></div> -->
                                </div>


                                <div class="layui-form-item"  id="yydatebox3" style="<?php if($info['rqtype']!=3): ?>display:none<?php endif; ?>">
                                    <div id="pstimedata">
                                        <?php foreach($info[yytimeday] as $k=>$v): ?>
                                        <div class="layui-form-item">   

                                            <label class="layui-form-label"><?php if($k==0): ?>选择日期：<?php endif; ?></label>
                                            <div class="layui-input-inline" style="width:120px">
                                                <select name="info[timeday][]"  >
                                                    <option value="1" <?php if($v==1): ?>selected<?php endif; ?>>当天</option>
                                                    <?php $__FOR_START_407215835__=2;$__FOR_END_407215835__=8;for($i=$__FOR_START_407215835__;$i < $__FOR_END_407215835__;$i+=1){ ?>
                                                    <option value="<?php echo $i; ?>" <?php if($v==$i): ?>selected<?php endif; ?>>第<?php echo $i; ?>天</option>
                                                    <?php } ?>
                                                </select>
                                            </div>  
                                            <?php if($k==0): ?>
                                            <button class="layui-btn layui-btn-primary" onclick="adddaytime()"><i class="fa fa-plus"></i></button>
                                            <?php else: ?>
                                            <button class="layui-btn layui-btn-primary" onclick="removedaytime(this)"><i class="fa fa-minus"></i></button>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <!-- <div class="layui-form-mid layui-word-aux"><a href="groupadd.php">创建分组</a></div> -->
                                </div>
                                <script>
                                    function adddaytime(){
                                        var html = '';
                                        html+='<div class="layui-form-item">';
                                        html+=' <label class="layui-form-label"></label>';
                                        html+=' <div class="layui-input-inline" style="width:120px">';
                                        html+='     <select name="info[timeday][]">';
                                        html+='         <option value="1">当天</option>';
                                        <?php $__FOR_START_470013206__=2;$__FOR_END_470013206__=8;for($i=$__FOR_START_470013206__;$i < $__FOR_END_470013206__;$i+=1){ ?>
                                        html+='         <option value="<?php echo $i; ?>">第<?php echo $i; ?>天</option>';
                                        <?php } ?>
                                        html+='     </select>';
                                        html+=' </div>';
                                        html+=' <button class="layui-btn layui-btn-primary" onclick="removedaytime(this)"><i class="fa fa-minus"></i></button>';
                                        html+='</div>';
                                        $('#pstimedata').append(html);
                                        layui.form.render();
                                    }
                                    function removedaytime(obj){
                                        $(obj).parent().remove();
                                    }
                                </script>
                                <div class="layui-form-item">
                                    <label class="layui-form-label">可选条件：</label>
                                    <div class="layui-form-mid">下单时间大于可选时间</div>
                                    <div class="layui-input-inline" style="width:70px">
                                        <input type="text" name="info[pdprehour]" value="<?php if(!$info['id']): ?>4<?php else: ?><?php echo $info['pdprehour']; ?><?php endif; ?>" class="layui-input">
                                    </div>
                                    <div class="layui-form-mid">小时</div>
                                </div>
						<?php endif; ?>
						<div class="layui-form-item">
							<label class="layui-form-label">序号：</label>
							<div class="layui-input-inline">
								<input type="text" name="info[sort]" value="<?php echo (isset($info['sort']) && ($info['sort'] !== '')?$info['sort']:0); ?>" class="layui-input">
							</div>
							<div class="layui-form-mid layui-word-aux">用于排序,越大越靠前</div>
						</div>
						<div class="layui-form-item">
							<label class="layui-form-label">状态：</label>
							<div class="layui-input-inline">
								<input type="radio" name="info[status]" value="1" title="开启" <?php if(!$info['id'] || $info['status']==1): ?>checked<?php endif; ?>>
								<input type="radio" name="info[status]" value="0" title="关闭" <?php if($info['id'] && $info['status']==0): ?>checked<?php endif; ?>>
							</div>
						</div>
						<?php if(getcustom('mendian_hexiao_givemoney')): ?>
						<div class="layui-form-item">
							<label class="layui-form-label">核销提成：</label>
							<div class="layui-input-inline layui-module-itemL">
								<div>提成比例(%)</div>
								<input style="width: 120px;" type="text" id="hexiaogivepercent" name="info[hexiaogivepercent]" class="layui-input" value="<?php echo (isset($info['hexiaogivepercent']) && ($info['hexiaogivepercent'] !== '')?$info['hexiaogivepercent']:'0'); ?>">
							</div>
							<div class="layui-input-inline layui-module-itemL">
								<div>提成金额(元)</div>
								<input style="width: 120px;" type="text" id="hexiaogivemoney" name="info[hexiaogivemoney]" class="layui-input" value="<?php echo (isset($info['hexiaogivemoney']) && ($info['hexiaogivemoney'] !== '')?$info['hexiaogivemoney']:'0'); ?>">
							</div>
							
							<div class="layui-input-inline layui-module-itemL">
								<div>提现手续费(%)</div>
								<input style="width: 120px;" type="text" id="withdrawfee" name="info[withdrawfee]" class="layui-input" value="<?php echo (isset($info['withdrawfee']) && ($info['withdrawfee'] !== '')?$info['withdrawfee']:'0'); ?>">
							</div>
                            <div class="layui-form-mid layui-word-aux">奖励总额=订单金额*提成比例+提成金额。<?php if(getcustom('mendian_hexiao_coupon_reward')): ?>优惠券奖励总额=计次券金额*提成比例+提成金额。<?php endif; ?>注意：多商户门店提现需要自己到后台财务菜单中进行审核，改为线下打款，不支持线上打款</div>
						</div>
                            <?php if(!$bid): ?>
        						<div class="layui-form-item">
        							<label class="layui-form-label">多商户是否可用：</label>
        							<div class="layui-input-inline">
        								<input type="radio" name="info[business_canuse]" value="1" title="是" <?php if($info['business_canuse']==1): ?>checked<?php endif; ?>>
        								<input type="radio" name="info[business_canuse]" value="0" title="否" <?php if(!$info['id'] || $info['business_canuse']==0): ?>checked<?php endif; ?>>
        							</div>
                                    <div class="layui-form-mid layui-word-aux">多商户是否能用这个门店进行自提</div>
        						</div>
                            <?php endif; if(getcustom('mendian_hexiao_commission_to_money')): ?>
                                <div class="layui-form-item">
                                    <label class="layui-form-label">提成转到<?php echo t('余额'); ?>：</label>
                                    <div class="layui-input-inline">
                                        <input type="radio" name="info[commission_to_money]" value="1" title="是" <?php if($info['commission_to_money']==1): ?>checked<?php endif; ?>>
                                        <input type="radio" name="info[commission_to_money]" value="0" title="否" <?php if(!$info['id'] || $info['commission_to_money']==0): ?>checked<?php endif; ?>>
                                    </div>
                                    <div class="layui-form-mid layui-word-aux">核销获得的佣金自动转到绑定的会员账号<?php echo t('余额'); ?></div>
                                </div>
                            <?php endif; ?>
						<?php endif; if(getcustom('mendian_hexiao_give_score')): ?>
                            <div class="layui-form-item">
                                <label class="layui-form-label">核销送<?php echo t('积分'); ?>：</label>
                                <div class="layui-input-inline layui-module-itemL">
                                    <div>提成比例(%)</div>
                                    <input style="width: 120px;" type="text"  name="info[hexiao_give_score_bili]" class="layui-input" value="<?php echo (isset($info['hexiao_give_score_bili']) && ($info['hexiao_give_score_bili'] !== '')?$info['hexiao_give_score_bili']:'0'); ?>">
                                </div>
                                <div class="layui-form-mid layui-word-aux">%，<?php echo t('门店'); ?>核销送<?php echo t('积分'); ?>到绑定会员账号，商品金额*提成比例*购买数量，计算结果向下取整数</div>
                            </div>
                        <?php endif; if(getcustom('mendian_maidan_ticheng')): ?>
						<div class="layui-form-item">
							<label class="layui-form-label">买单提成：</label>
							<div class="layui-input-inline layui-module-itemL">
								<div>提成比例(%)</div>
								<input style="width: 120px;" type="text" id="maidangivepercent" name="info[maidangivepercent]" class="layui-input" value="<?php echo (isset($info['maidangivepercent']) && ($info['maidangivepercent'] !== '')?$info['maidangivepercent']:'0'); ?>">
							</div>
							<div class="layui-input-inline layui-module-itemL">
								<div>提成金额(元)</div>
								<input style="width: 120px;" type="text" id="maidangivemoney" name="info[maidangivemoney]" class="layui-input" value="<?php echo (isset($info['maidangivemoney']) && ($info['maidangivemoney'] !== '')?$info['maidangivemoney']:'0'); ?>">
							</div>
                            <div class="layui-form-mid layui-word-aux">奖励总额=订单金额*提成比例+提成金额</div>
						</div>
                           
						<?php endif; if(getcustom('commission_mendian_hexiao_coupon')): ?>
                        <div class="layui-form-item">
                            <label class="layui-form-label" style="font-weight: bold; ">门店核销奖励分润</label>
                            <div class="layui-input-inline layui-module-itemL"></div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">第一种分润：</label>
                            <div class="layui-input-inline layui-module-itemL">
                                <div>提成比例(%)</div>
                                <input style="width: 120px;" type="text" name="fenrun[bili][]" class="layui-input" value="<?php echo (isset($fenrun['bili'][0]) && ($fenrun['bili'][0] !== '')?$fenrun['bili'][0]:'0'); ?>">
                            </div>
                            <div class="layui-input-inline layui-module-itemL">
                                <div>会员ID</div>
                                <input type="text" name="fenrun[mids][]" class="layui-input mid1" value="<?php echo $fenrun['mids'][0]; ?>" style="width:500px">
                                <button class="layui-btn " onclick="showChooseMember(this,1)">选择会员</button>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">第二种分润：</label>
                            <div class="layui-input-inline layui-module-itemL">
                                <div>提成比例(%)</div>
                                <input style="width: 120px;" type="text" name="fenrun[bili][]" class="layui-input" value="<?php echo (isset($fenrun['bili'][1]) && ($fenrun['bili'][1] !== '')?$fenrun['bili'][1]:'0'); ?>">
                            </div>
                            <div class="layui-input-inline layui-module-itemL">
                                <div>会员ID</div>
                                <input type="text" name="fenrun[mids][]" class="layui-input mid2" value="<?php echo $fenrun['mids'][1]; ?>" style="width:500px">
                                <button class="layui-btn " onclick="showChooseMember(this,2)">选择会员</button>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">第三种分润：</label>
                            <div class="layui-input-inline layui-module-itemL">
                                <div>提成比例(%)</div>
                                <input style="width: 120px;" type="text" name="fenrun[bili][]" class="layui-input" value="<?php echo (isset($fenrun['bili'][2]) && ($fenrun['bili'][2] !== '')?$fenrun['bili'][2]:'0'); ?>">
                            </div>
                            <div class="layui-input-inline layui-module-itemL">
                                <div>会员ID</div>
                                <input type="text" name="fenrun[mids][]" class="layui-input mid3" value="<?php echo $fenrun['mids'][2]; ?>" style="width:500px">
                                <button class="layui-btn " onclick="showChooseMember(this,3)">选择会员</button>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">第四种分润：</label>
                            <div class="layui-input-inline layui-module-itemL">
                                <div>提成比例(%)</div>
                                <input style="width: 120px;" type="text" name="fenrun[bili][]" class="layui-input" value="<?php echo (isset($fenrun['bili'][3]) && ($fenrun['bili'][3] !== '')?$fenrun['bili'][3]:'0'); ?>">
                            </div>
                            <div class="layui-input-inline layui-module-itemL">
                                <div>会员ID</div>
                                <input type="text" name="fenrun[mids][]" class="layui-input mid4" value="<?php echo $fenrun['mids'][3]; ?>" style="width:500px">
                                <button class="layui-btn " onclick="showChooseMember(this,4)">选择会员</button>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">第五种分润：</label>
                            <div class="layui-input-inline layui-module-itemL">
                                <div>提成比例(%)</div>
                                <input style="width: 120px;" type="text" name="fenrun[bili][]" class="layui-input" value="<?php echo (isset($fenrun['bili'][4]) && ($fenrun['bili'][4] !== '')?$fenrun['bili'][4]:'0'); ?>">
                            </div>
                            <div class="layui-input-inline layui-module-itemL">
                                <div>会员ID</div>
                                <input type="text" name="fenrun[mids][]" class="layui-input mid5" value="<?php echo $fenrun['mids'][4]; ?>" style="width:500px">
                                <button class="layui-btn " onclick="showChooseMember(this,5)">选择会员</button>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">第六种分润：</label>
                            <div class="layui-input-inline layui-module-itemL">
                                <div>提成比例(%)</div>
                                <input style="width: 120px;" type="text" name="fenrun[bili][]" class="layui-input" value="<?php echo (isset($fenrun['bili'][5]) && ($fenrun['bili'][5] !== '')?$fenrun['bili'][5]:'0'); ?>">
                            </div>
                            <div class="layui-input-inline layui-module-itemL">
                                <div>会员ID</div>
                                <input type="text" name="fenrun[mids][]" class="layui-input mid6" value="<?php echo $fenrun['mids'][5]; ?>" style="width:500px">
                                <button class="layui-btn " onclick="showChooseMember(this,6)">选择会员</button>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">第七种分润：</label>
                            <div class="layui-input-inline layui-module-itemL">
                                <div>提成比例(%)</div>
                                <input style="width: 120px;" type="text" name="fenrun[bili][]" class="layui-input" value="<?php echo (isset($fenrun['bili'][6]) && ($fenrun['bili'][6] !== '')?$fenrun['bili'][6]:'0'); ?>">
                            </div>
                            <div class="layui-input-inline layui-module-itemL">
                                <div>会员ID</div>
                                <input type="text" name="fenrun[mids][]" class="layui-input mid7" value="<?php echo $fenrun['mids'][6]; ?>" style="width:500px">
                                <button class="layui-btn " onclick="showChooseMember(this,7)">选择会员</button>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">第八种分润：</label>
                            <div class="layui-input-inline layui-module-itemL">
                                <div>提成比例(%)</div>
                                <input style="width: 120px;" type="text" name="fenrun[bili][]" class="layui-input" value="<?php echo (isset($fenrun['bili'][7]) && ($fenrun['bili'][7] !== '')?$fenrun['bili'][7]:'0'); ?>">
                            </div>
                            <div class="layui-input-inline layui-module-itemL">
                                <div>会员ID</div>
                                <input type="text" name="fenrun[mids][]" class="layui-input mid8" value="<?php echo $fenrun['mids'][7]; ?>" style="width:500px">
                                <button class="layui-btn " onclick="showChooseMember(this,8)">选择会员</button>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">第九种分润：</label>
                            <div class="layui-input-inline layui-module-itemL">
                                <div>提成比例(%)</div>
                                <input style="width: 120px;" type="text" name="fenrun[bili][]" class="layui-input" value="<?php echo (isset($fenrun['bili'][8]) && ($fenrun['bili'][8] !== '')?$fenrun['bili'][8]:'0'); ?>">
                            </div>
                            <div class="layui-input-inline layui-module-itemL">
                                <div>会员ID</div>
                                <input type="text" name="fenrun[mids][]" class="layui-input mid9" value="<?php echo $fenrun['mids'][8]; ?>" style="width:500px">
                                <button class="layui-btn " onclick="showChooseMember(this,9)">选择会员</button>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">第十种分润：</label>
                            <div class="layui-input-inline layui-module-itemL">
                                <div>提成比例(%)</div>
                                <input style="width: 120px;" type="text" name="fenrun[bili][]" class="layui-input" value="<?php echo (isset($fenrun['bili'][9]) && ($fenrun['bili'][9] !== '')?$fenrun['bili'][9]:'0'); ?>">
                            </div>
                            <div class="layui-input-inline layui-module-itemL">
                                <div>会员ID</div>
                                <input type="text" name="fenrun[mids][]" class="layui-input mid10" value="<?php echo $fenrun['mids'][9]; ?>" style="width:500px">
                                <button class="layui-btn " onclick="showChooseMember(this,10)">选择会员</button>
                            </div>
                        </div>
                        <?php endif; if(getcustom('mendian_member_levelup_fenhong') && $bid == 0): ?>
            <div class="layui-form-item">
              <label class="layui-form-label">会员扫码升级分红：</label>
              <div class="layui-input-inline">
                <input type="radio" name="info[member_levelup_fenhong]" value="1" title="开启" <?php if($info['id'] &&$info['member_levelup_fenhong']==1): ?>checked<?php endif; ?>>
                <input type="radio" name="info[member_levelup_fenhong]" value="0" title="关闭" <?php if(!$info['id'] ||  $info['member_levelup_fenhong']==0): ?>checked<?php endif; ?>>
              </div>
            </div>
            <div class="layui-form-item">
              <label class="layui-form-label">分红用户：</label>
              <div class="layui-input-inline layui-module-itemL">
                <div>会员ID</div>
                <input type="text" name="info[member_levelup_fenhong_mid]" class="layui-input member_levelup_fenhong_mid" value="<?php echo $info['member_levelup_fenhong_mid']; ?>">
                <button class="layui-btn " onclick="showChooseMember(this,'member_levelup_fenhong_mid')">选择会员</button>
              </div>
            </div>
            <div class="layui-form-item">
              <label class="layui-form-label">会员扫码升级奖励：</label>
              <div class="layui-input-inline" style="">
              <input type="radio" name="info[member_levelup_fenhong_money_type]" value="1" <?php if(!$info['id'] || $info['member_levelup_fenhong_money_type']==1): ?>checked<?php endif; ?> title="固定金额" lay-filter="member_levelup_fenhong_money_type">
              <input type="radio" name="info[member_levelup_fenhong_money_type]" value="0" <?php if($info['id'] && $info['member_levelup_fenhong_money_type']==0): ?>checked<?php endif; ?> title="比例" lay-filter="member_levelup_fenhong_money_type">
              </div>
              <label class="layui-form-label" style="width: auto" id="jlje" ><?php if($info['id'] && $info['member_levelup_fenhong_money_type']==0): ?>奖励比例<?php else: ?>奖励金额<?php endif; ?>：</label>
              <div class="layui-input-inline">
                <input type="text" name="info[member_levelup_fenhong_money]" value="<?php echo !empty($info['id']) ? $info['member_levelup_fenhong_money'] : '0'; ?>" class="layui-input">
              </div>
              <div class="layui-form-mid layui-word-aux" style="margin-left: 20px">当有会员扫描门店二维码升级时，会给设置的分红会员相应的奖励</div>
            </div>
            <?php endif; ?>
						<div class="layui-form-item">
							<label class="layui-form-label"></label>
							<div class="layui-input-block">
								<button class="layui-btn layui-btn-danger" lay-submit lay-filter="formsubmit">提 交</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
  </div>
	<script type="text/javascript" src="/static/admin/layui/layui.all.js?v=20210226"></script>
<script type="text/javascript" src="/static/admin/layui/lay/modules/flow.js?v=1"></script>
<script type="text/javascript" src="/static/admin/layui/lay/modules/formSelects-v4.js"></script>
<script type="text/javascript" src="/static/admin/js/jquery-ui.min.js?v=20200228"></script>
<script type="text/javascript" src="/static/admin/ueditor/ueditor.js?v=20220707"></script>
<script type="text/javascript" src="/static/admin/ueditor/135editor.js?v=20200228"></script>
<script type="text/javascript" src="/static/admin/webuploader/webuploader.js?v=2024"></script>
<script type="text/javascript" src="/static/admin/js/qrcode.min.js?v=20200228"></script>
<script type="text/javascript" src="/static/admin/js/dianda.js?v=2022"></script>
<script type="text/javascript" src="/static/admin/js/inputTags.js?v=2026"></script>

<div id="NewsToolBox"></div>
<script type="text/javascript">
		// 解释文字浮层展示
		$('.layui-text-popover').mouseenter(function(){
			let pageHeight = $(window).height() + $(document).scrollTop();
			let bottom = pageHeight - $(this).offset().top
			let topNum = ($(this).offset().top - $(document).scrollTop()).toFixed(2);
			let Height = $(this).find('.layui-textpopover-div').outerHeight();
			$(this).find('.layui-textpopover-div').show()
			let that = this;
			setTimeout(function(){
				if(topNum < (Height/2-15)){
					$(that).find('.layui-textpopover-div').css({'top':-topNum+10+'px','opacity':1,'transition':'opacity .3s'})	
				}else if(bottom < (Height/2-15)){
					$(that).find('.layui-textpopover-div').css({'top': bottom-Height-10 +'px','opacity':1,'transition':'opacity .3s'})	
				}else{
					$(that).find('.layui-textpopover-div').css({'top':-(Height/2-15)+'px','opacity':1,'transition':'opacity .3s'})	
				}
			},100)
		}) 
		$('.layui-text-popover').mouseleave(function(){
			$(this).find('.layui-textpopover-div').css({'opacity':0})	
			$(this).find('.layui-textpopover-div').hide()
		}) 
		$('.layui-textpopover-div').mouseenter(function(){
			$(this).find('.layui-textpopover-div').show()
		})
		$('.layui-textpopover-div').mouseleave(function(){
			$(this).find('.layui-textpopover-div').css({'opacity':0})
			$(this).find('.layui-textpopover-div').hide()
		})
		// 图片浮层展示 示例
		$('.layui-popover').mouseenter(function(){
			let pageHeight = $(window).height() + $(document).scrollTop();
			let bottom = pageHeight - $(this).offset().top
			let topNum = ($(this).offset().top - $(document).scrollTop()).toFixed(2);
			let Height = $(this).find('.layui-popover-div').outerHeight();
			$(this).find('.layui-popover-div').show()
			let that = this;
			setTimeout(function(){
				if(topNum < (Height/2-15)){
					$(that).find('.layui-popover-div').css({'top':-topNum+10+'px','opacity':1,'transition':'opacity .3s'})	
				}else if(bottom < (Height/2-15)){
					$(that).find('.layui-popover-div').css({'top': bottom-Height-10 +'px','opacity':1,'transition':'opacity .3s'})	
				}else{
					$(that).find('.layui-popover-div').css({'top':-(Height/2-15)+'px','opacity':1,'transition':'opacity .3s'})	
				}
			},100)
		}) 
		$('.layui-popover').mouseleave(function(){
			$(this).find('.layui-popover-div').css({'opacity':0})	
			$(this).find('.layui-popover-div').hide()
		}) 
    function copyText(text) {
        var top = document.documentElement.scrollTop;
        var textarea = document.createElement("textarea"); //创建input对象
        var currentFocus = document.activeElement; //当前获得焦点的元素
        var toolBoxwrap = document.getElementById('NewsToolBox'); //将文本框插入到NewsToolBox这个之后
        toolBoxwrap.appendChild(textarea); //添加元素
        textarea.value = text;
        textarea.focus();
        document.documentElement.scrollTop = top;
        if (textarea.setSelectionRange) {
            textarea.setSelectionRange(0, textarea.value.length); //获取光标起始位置到结束位置
        } else {
            textarea.select();
        }
        try {
            var flag = document.execCommand("copy"); //执行复制
        } catch (eo) {
            var flag = false;
        }
        toolBoxwrap.removeChild(textarea); //删除元素
        currentFocus.focus();
        if(flag) layer.msg('复制成功');
        return flag;
    }
		// 查看链接
		function viewLink(path,url=''){
			var pagepath = path;
			if(!url){
				var url = "<?php echo m_url('"+pagepath+"'); ?>"; //拼接 H5 链接
			}
			<?php if(!in_array('mp',$platform)): ?>
				showwxqrcode(pagepath);
				return;
			<?php endif; ?>
			var html = '';
			html+='<div style="margin:20px">';
			html+='	<div style="width:100%;margin:10px 0" id="urlqr"></div>';
			<?php if(in_array('wx',$platform)): ?>
			html+='	<div style="width:100%;text-align:center"><button class="layui-btn layui-btn-sm layui-btn-primary" onclick="showwxqrcode(\''+pagepath+'\')">查看小程序码</button></div>';
			<?php endif; ?>
			html+='	<div style="line-height:25px;"><div><span style="width: 70px;display: inline-block;">链接地址：</span><button class="layui-btn layui-btn-xs layui-btn-primary" onclick="copyText(\''+url+'\')">复制</button></div><div>'+url+'</div></div>';
			html+='	<div style="height:50px;line-height:25px;"><div><span style="width: 70px;display: inline-block;">页面路径：</span><button style="box-sizing: border-box;" class="layui-btn layui-btn-xs layui-btn-primary" onclick="copyText(\'/'+pagepath+'\')">复制</button></div><div>/'+pagepath+'</div></div>';
			html+='</div>';
			layer.open({type:1,'title':'查看链接',area:['500px','430px'],shadeClose:true,'content':html})
			var qrcode = new QRCode('urlqr', {
					text: 'your content',
					width: 200,
					height: 200,
					colorDark : '#000000',
					colorLight : '#ffffff',
					correctLevel : QRCode.CorrectLevel.L
				});
				qrcode.clear();
				qrcode.makeCode(url);
		}
		// 查看小程序码
		function showwxqrcode(pagepath){
			var index = layer.load();
			$.post("<?php echo url('DesignerPage/getwxqrcode'); ?>",{path:pagepath},function(res){
				layer.close(index);
				if(res.status==0){
					layer.open({type:1,area:['300px','350px'],content:'<div style="margin:auto auto;text-align:center"><div style="color:red;width:280px;height:180px;margin-top:100px">'+res.msg+'</div><div style="height:25px;line-height:25px;">'+'/'+pagepath+'</div></div>',title:false,shadeClose:true})
				}else{
					layer.open({type:1,area:['300px','350px'],content:'<div style="margin:auto auto;text-align:center"><img src="'+res.url+'" style="margin-top:20px;max-width:280px;max-height:280px"/><div style="height:25px;line-height:25px;">'+'/'+pagepath+'</div></div>',title:false,shadeClose:true})
				}
			})
		}
</script>
<!-------使用js导出excel文件--------->
<script src="/static/admin/excel/excel.js?v=2024"></script>
<script src="/static/admin/excel/layui_exts/excel.js"></script>
<script>

    var excel = new Excel();
    var excel_name = '<?php echo $excel_name; ?>';
    excel.bind(function (data,title) {
        var excel_field = JSON.parse('<?php echo $excel_field; ?>');
        if(title && title!=undefined){
            //接口返回的title
            var excel_title = title;
        }else{
            //excel_field.php 配置的title
            var excel_title = JSON.parse('<?php echo $excel_title; ?>');
        }
        if(!excel_title || excel_title.length<=0){
            //上面两种都没有title,读取table表格cols中的title，同时filed也更新为table表格cols中的field
            excel_title = [];
            excel_field = [];
            var cols = tableIns.config.cols;
            cols.forEach(function (cols_item, cols_index) {
                console.log(cols_item);
                cols_item.forEach(function (cols_item2, cols_index2) {
                    console.log(cols_item2);
                    if(cols_item2.title){
                        excel_title.push(cols_item2.title)
                        excel_field.push(cols_item2.field)
                    }
                })
            })
        }
        // if(!excel_title || excel_title.length<=0){
        //     layer.msg('未设置标题');
        //     return;
        // }

        // 设置表格内容
        data.forEach(function (item, index) {
            var _data = [];
            excel_title.forEach(function (title, index2) {
                var field = excel_field[index2];
                if(item[field] && item[field]!=undefined){
                    //有filed 匹配field
                    var field_val = item[field];
                    //是整数 长度为10 字段名包含time 判定为时间戳
                    if(parseInt(field_val) == field_val && (field_val.toString()).length==10 && field.includes('time')){
                        field_val = date('Y-m-d H:i:s',field_val);
                    }
                }else{
                    //没有filed 根据顺序来
                    var field_val = item[index2];
                }
                _data.push(field_val);
            })
            data[index] = _data;
        });
        // 设置表头内容
        if(excel_title && excel_title.length>0){
            data.unshift(excel_title);
        }
        // 应用表格样式
        return this.withStyle(data);

    }, excel_name+layui.util.toDateString(Date.now(), '_yyyyMMdd_HHmmss'));

</script>
	<script charset="utf-8" src="https://map.qq.com/api/js?v=2.exp&key=<?php echo $sysset_webinfo['map_key_qq']; ?>"></script>
	<?php if($mendian_upgrade): ?>
	<script src="/static/admin/js/area.js"></script>
	<script>
		 address4('province','city','district','street',"<?php echo $info['province']; ?>","<?php echo $info['city']; ?>","<?php echo $info['district']; ?>","<?php echo $info['street']; ?>");
	 </script>
	<?php else: ?>
	 <script src="/static/admin/js/address3.js"></script>
	 <script>
		 address3('province','city','district',"<?php echo $info['province']; ?>","<?php echo $info['city']; ?>","<?php echo $info['district']; ?>");
	 </script>
	<?php endif; ?>
		
    <script src="/static/admin/js/searchaddress.js?v=202410"></script>

	<script>
    function addtime(){
        var html = '';
        html+='<div class="layui-form-item">';
        html+=' <label class="layui-form-label"></label>';
        html+=' <div class="layui-input-inline">';
        html+='     <input type="text" name="info[timepoint][]" value="2021-09-27" class="layui-input timepoint">   ';
        html+=' </div>';
        html+=' <button class="layui-btn layui-btn-primary" onclick="removetime(this)"><i class="fa fa-minus"></i></button>';
        html+='</div>';
        $('#timedata').append(html);
        layui.form.render();

        $('.timepoint').each(function() {
            layui.laydate.render({elem:this,type: 'time',trigger: 'click',format:'HH:mm' });
        });

    }
    function removetime(obj){
        $(obj).parent().remove();
    }
    $('.timepoint').each(function() {
        layui.laydate.render({elem:this,type: 'time',trigger: 'click',format:'HH:mm' });
    });
    layui.laydate.render({ 
        elem: '#start_time',
        type: 'datetime',
        trigger: 'click'
    });
    layui.laydate.render({ 
        elem: '#end_time',
        type: 'datetime',
        trigger: 'click'
    });
    layui.laydate.render({ 
        elem: '#yybegintime',
        type: 'date',
        trigger: 'click'
    });
    layui.laydate.render({ 
        elem: '#yyendtime',
        type: 'date',
        trigger: 'click'
    });
    layui.form.on('radio(datetype)', function(data){
        if(data.value == '1'){
            $('#datebox2').hide();
            $('#datebox1').show();
        }else{
            $('#datebox2').show();
            $('#datebox1').hide();
        }
    })
    layui.form.on('radio(rqtype)', function(data){
        if(data.value == '1'){
            $('#yydatebox2').hide();
            $('#yydatebox3').hide();
            $('#yydatebox1').show();
        }else if(data.value == '2'){
            $('#yydatebox2').show();
            $('#yydatebox1').hide();
            $('#yydatebox3').hide();
        }else if(data.value == '3'){
            $('#yydatebox2').hide();
            $('#yydatebox1').hide();
            $('#yydatebox3').show();
        }
    })
    layui.form.on('radio(member_levelup_fenhong_money_type)', function(data){
      if(data.value == '1'){
        $('#jlje').text('奖励金额');
      }else{
        $('#jlje').text('奖励比例%');
      }
    })
	var ueditor = UE.getEditor('content');
	layui.form.on('submit(formsubmit)', function(obj){
		var field = obj.field
		field['info[content]'] = ueditor.getContent();
		//console.log(field);return;
		var index = layer.load();
		$.post("<?php echo url('save'); ?>",field,function(data){
			layer.close(index);
			dialog(data.msg,data.status);
			if(data.status == 1){
				setTimeout(function(){
					parent.layer.close(parent.layer.getFrameIndex(window.name));
					parent.tableIns.reload()
				},1000)
			}
		})
	})

  	</script>
  	<script type="text/javascript">
  		function choosezuobiao(){
        var address = '';
        var longitude = $("input[name='info[peisong_lng2]']").val();
        var latitude = $("input[name='info[peisong_lat2]']").val();
        if(!longitude){
            longitude = $("input[name='info[peisong_lng]']").val();
            latitude = $("input[name='info[peisong_lat]']").val();
        }
        var choosezblayer = layer.open({type:2,shadeClose: true,area: ['800px', '560px'],'title': '选择坐标',content: "<?php echo url('DesignerPage/choosezuobiao'); ?>&address="+(address?address:"")+"&jd="+(longitude?longitude:"")+"&wd="+(latitude?latitude:""),btn:['确定','取消'],yes:function(index, layero){
            var longitude = layero.find('iframe').contents().find('#mapjd').val();
            var latitude = layero.find('iframe').contents().find('#mapwd').val();
            $("input[name='info[peisong_lng2]']").val(longitude);
            $("input[name='info[peisong_lat2]']").val(latitude);
            layer.close(choosezblayer);
        }});
    }

    var lng = $("input[name='info[peisong_lng]']").val();
    var lat = $("input[name='info[peisong_lat]']").val();
    var range = $("input[name='info[peisong_range]']").val();
    var rangetype = '<?php echo $info['peisong_rangetype']; ?>';
    var rangepath = $("input[name='info[peisong_rangepath]']").val();
    var lnglat = '';//设置的坐标
    if(lng !="" && lat !=""){ 
        lng = parseFloat(lng);
        lat = parseFloat(lat);
        lnglat=[lng,lat];
    }
    if(range==0){ range=2000;}
    layui.form.on('radio(rangetypeset)',function(data){
        if(data.value == '0'){
            $('#rangetypeset0').show();
            $('#rangetypeset1').hide();
            addcircle();
        }else{
            $('#rangetypeset0').hide();
            $('#rangetypeset1').show();
            addpoly();
        }
    })
    var mobj = '';
    var selectmemberLayer;
    var midnum = ''
    function showChooseMember(obj,num){
        mobj = obj;
        midnum = num;
        selectmemberLayer = layer.open({type:2,title:'选择<?php echo t('会员'); ?>',content:"<?php echo url('Mendian/choosemember'); ?>",area:['1000px','600px'],shadeClose:true});
    }
    function choosemember(res){
        if(midnum == 'member_levelup_fenhong_mid'){
          $('.member_levelup_fenhong_mid').val(res.id);
        }else {
          var mids = $('.mid'+midnum).val();
          if(mids){
            let arr = mids.split(",");
            arr = arr.map(Number);
            let valueExists = arr.indexOf(res.id) !== -1;
            if(valueExists != true){
              $('.mid'+midnum).val(mids+','+res.id);
            }else{
              dialog('该会员已添加过了');
            }
          }else{
            $('.mid'+midnum).val(res.id);
          }
        }
        layer.close(selectmemberLayer);
    }
    //初始化地图参数
    var map = new AMap.Map("container", {
        resizeEnable: true,//是否监控地图容器尺寸变化，默认值为false
        dragEnable: true,//是否允许拖拽地图
        keyboardEnable: false,//是否允许键盘平移
        doubleClickZoom: false,//是否允许双击放大地图
        scrollWheel:true,//是否允许鼠标滚轮操作地图
        center:lnglat,
        zoom: 13 //地图显示的缩放级别
    });

    var mapcenter = map.getCenter();
    var lng = mapcenter.lng
    var lat = mapcenter.lat
    lnglat = [lng,lat];
    $("input[name='info[peisong_lng]']").val(lng);
    $("input[name='info[peisong_lat]']").val(lat);
    $("input[name='info[peisong_range]']").val(range);

    if(!rangepath){
        var key = 0.02;
        rangepath = [
            [lng - key, lat - key],
            [lng + key, lat - key],
            [lng + key, lat + key],
            [lng - key, lat + key]
        ];
    }else{
        rangepathdata = rangepath.split(';');
        rangepath = [];
        for(var i in rangepathdata){
            var path = rangepathdata[i].split(',');
            rangepath.push([path[0],path[1]]);
        }
        console.log(rangepath);
    }
    if(rangetype==0 ){
        addcircle();
    }else{
        addpoly();
    }
    function addcircle(){
        map.clearMap();
        var circlegon = new AMap.Circle({
            center: lnglat,// 圆心位置
            radius: range, //半径
            strokeColor: "#4e73f1", //线颜色
            strokeOpacity: 1, //线透明度
            strokeWeight: 3, //线粗细度
            fillColor: "#4e73f1", //填充颜色
            fillOpacity: 0.35,//填充透明度
        });

        map.add(circlegon)
        map.setFitView([circlegon]);
        <?php if(in_array('hmy_yuyue',getcustom())  && ($bid>0)): ?>
            return;
        <?php endif; ?>
        var circleEditor= new AMap.CircleEditor(map,circlegon);
        circleEditor.open(lnglat);
        
        circleEditor.on('move', function (event) {
            $("input[name='info[peisong_lng]']").val(event.lnglat.lng);
            $("input[name='info[peisong_lat]']").val(event.lnglat.lat);
        });
        circleEditor.on('adjust', function (event) {
            $("input[name='info[peisong_range]']").val(event.radius);
        });
    }
    function addpoly(){
        map.clearMap();
        var polygon = new AMap.Polygon({
            path: rangepath,
            strokeColor: "#FF33FF",
            strokeOpacity: 0.2,
            fillOpacity: 0.4,
            fillColor: '#1791fc',
            zIndex: 50,
            draggable:true
        });
        map.add(polygon)
        map.setFitView([polygon])
        var polyEditor = new AMap.PolyEditor(map, polygon);
        polyEditor.open();
        setpathvalue(polygon);
        polyEditor.on('addnode', function (event) {
            console.log('触发事件：addnode')
            setpathvalue(polygon);
        });
        polyEditor.on('adjust', function (event) {
            console.log('触发事件：adjust');
            setpathvalue(polygon);
        });
        polyEditor.on('removenode', function (event) {
            console.log('触发事件：removenode')
            setpathvalue(polygon);
        });
        polyEditor.on('end', function (event) {
            console.log('触发事件： end')
            setpathvalue(polygon);
        });
        polygon.on('change', function (event) {
            console.log('polygon触发事件： change')
            setpathvalue(polygon);
        });
    }
    function setpathvalue(polygon){
        var pathdata = polygon.getPath();
        var pathArr = [];
        for(var i in pathdata){
            pathArr.push(pathdata[i].lng +','+pathdata[i].lat);
        }
        $("input[name='info[peisong_rangepath]']").val(pathArr.join(';'));
    }
  	</script>

	
</body>
</html>