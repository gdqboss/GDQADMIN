<template>
<view :style="{position: 'relative',marginTop:params.margin_y*2.2 + 'rpx'}">
	<view v-if="params.style==1" :style="'background: linear-gradient(180deg, '+t('color1')+' 0%, rgba('+t('color1rgb')+',0) 100%);'">
		<view class="dp-userinfo" :style="{background:'url('+params.bgimg+') no-repeat',backgroundSize:'cover',margin:0+'rpx '+(params.margin_x*2.2)+'rpx',padding:(params.padding_y*2.2)+'rpx '+(params.padding_x*2.2)+'rpx '+'0rpx'}">
			<view class="banner" :style="{marginTop:usercenterNavigationCustom == 0 ? '120rpx':NavigationCustomTop + 10 +'px'}">
				<view class='info'>
					<view class="f1">
						<view class="flex-y-center">
							<image :src="data.userinfo.headimg" background-size="cover" class="headimg" @tap="weixinlogin"/>
							<view v-if="params.realnameVerifyShow == 1" @tap="goto" data-url="/pagesExt/my/setrealname">
								<text class="tag-gray" v-if="data.userinfo.realname_status != 1">未实名</text>
								<text class="tag-renzheng" v-if="data.userinfo.realname_status == 1">实名认证</text>
							</view>
						</view>
						<view class="flex-y-center">
							<view class="nickname">{{data.userinfo.nickname}}</view>
							<text v-if="params.midshow=='1'" style="font-size:26rpx;padding-left:10rpx">(ID:{{data.userinfo.id}})</text>
							<view class="user-level" @tap="openLevelup" data-levelid="" v-if="params.levelshow==1">
								<image class="level-img" :src="data.userlevel.icon" v-if="data.userlevel.icon"/>
								<view class="level-name">{{data.userlevel.name}}</view>
							</view>
							<view class="user-level" v-for="(item, index) in data.userlevelList" :key="index" @tap="openLevelup" :data-levelid="item.id" v-if="params.levelshow==1">
								<image class="level-img" :src='item.icon' v-if="item.icon"/>
								<view class="level-name">{{item.name}}</view>
							</view>
							<view class="usermid" style="margin-left:10rpx;font-size:24rpx;color:#999" v-if="data.userlevel.can_agent > 0 && data.sysset.reg_invite_code!='0' && data.sysset.reg_invite_code_type==1">邀请码：<text user-select="true" selectable="true">{{data.userinfo.yqcode}}</text></view>
						</view>
						<view v-if="data.zhaopin && data.zhaopin.show_zhaopin" class="flex">
							<text v-if="data.zhaopin.is_qiuzhi_renzheng && !data.zhaopin.is_qiuzhi_renzheng" class="tag-renzheng">认证保障中</text>
							<text v-if="data.zhaopin.is_qiuzhi_qianyue" class="tag-renzheng">签约保障中</text>
							<text v-if="data.zhaopin.is_zhaopin_renzheng" class="tag-renzheng">认证企业</text>
						</view>
						<view class="flex" v-if="data.userinfo && data.userinfo.show_commission_max">
							<text class="tag-renzheng">{{t('佣金上限')}}:{{data.userinfo.remain_commission_max}}</text>
						</view>
					</view>
					<block v-if="platform=='wx'">
						<view class="usercard" v-if="params.cardshow==1 && data.userinfo.card_code" @tap="opencard" :data-card_id="data.userinfo.card_id" :data-card_code="data.userinfo.card_code"><image class="img" :src="pre_url+'/static/img/ico-card2.png'"/><text class="txt">会员卡</text></view>
						<view class="usercard" v-if="params.cardshow==1 && !data.userinfo.card_code" @tap="addmembercard" :data-card_id="data.card_id"><image class="img" :src="pre_url+'/static/img/ico-card2.png'"/><text class="txt">会员卡</text></view>
					</block>
					<block v-if="platform=='mp'">
						<view class="usercard" v-if="params.cardshow==1 && data.userinfo.card_code" @tap="opencard" :data-card_id="data.userinfo.card_id" :data-card_code="data.userinfo.card_code"><image class="img" :src="pre_url+'/static/img/ico-card2.png'"/><text class="txt">会员卡</text></view>
						<view class="usercard" v-if="params.cardshow==1 && !data.userinfo.card_code" @tap="goto" :data-url="data.card_returl"><image class="img" :src="pre_url+'/static/img/ico-card2.png'"/><text class="txt">会员卡</text></view>
					</block>
				</view>
				<view ref="custom_field1" :class="tabFlex?'custom_field_flex':'custom_field'">
                    <view class='item' v-if="params.moneyshow==1 && data.userinfo.money>0" data-url='/pagesExt/money/recharge' @tap='goto'>
                        <text class='t2'>{{data.userinfo.money}}</text>
                        <text class="t1">{{t('余额')}}</text>
                    </view>
                    <view class='item' v-if="params.commissionshow==1 && data.userlevel && data.userlevel.can_agent>0 && data.userinfo.commission>0" data-url='/pages/commission/index' @tap='goto'>
                        <text class='t2'>{{data.userinfo.commission}}</text>
                        <text class="t1">{{t('佣金')}}</text>
                    </view>
					<view class='item' v-if="params.scoreshow==1" data-url='/pagesExt/my/scorelog' @tap='goto'>
						<text class='t2'>{{data.userinfo.score}}</text>
						<text class="t1">{{t('积分')}}</text>
					</view>
					<view class='item' v-if="params.bscoreshow==1" data-url='/pagesExt/my/bscore' @tap='goto'>
						<text class='t2'>{{data.userinfo.bscore+data.userinfo.score}}</text>
						<text class="t1">总{{t('积分')}}</text>
					</view>
					<view class='item' v-if="!isNull(data.userinfo.service_fee) && params.servicefeeshow==1" data-url='/pagesB/service_fee/recharge' @tap='goto'>
						<text class='t2'>{{data.userinfo.service_fee}}</text>
						<text class="t1">{{t('服务费')}}</text>
					</view>
					<view class='item' v-if="params.couponshow==1" data-url='/pagesExt/coupon/mycoupon' @tap='goto'>
						<text class='t2'>{{data.userinfo.couponcount}}</text>
						<text class="t1">{{t('优惠券')}}</text>
					</view>
					<view class='item' v-if="params.formshow==1" data-url='/pagesA/form/formlog?st=1' @tap='goto'>
						<text class='t2'>{{data.userinfo.formcount}}</text>
						<text class="t1">{{params.formtext}}</text>
					</view>
					<view class='item' v-if="data.userinfo.fuchi_money>=0" data-url='/pagesExt/my/fuchi' @tap='goto'>
						<text class='t2'>{{data.userinfo.fuchi_money}}</text>
						<text class="t1">{{t('扶持金')}}</text>
					</view>
					<view class='item' v-if="data.userinfo.gongxian>=0" data-url='/pagesExt/my/gongxianLog' @tap='goto'>
						<text class='t2'>{{data.userinfo.gongxian?data.userinfo.gongxian:0}}</text>
						<text class="t1">{{t('贡献')}}</text>
					</view>
					<view class='item' v-if="params.xiaofeishow==1" data-url='/pagesA/my/xiaofeimoneylog' @tap='goto'>
						<text class='t2'>{{data.userinfo.xiaofei_money?data.userinfo.xiaofei_money:0}}</text>
						<text class="t1">{{t('冻结佣金')}}</text>
					</view>
					<view class='item' v-if="params.freezecreditshow==1" data-url='/pagesB/my/freezecreditlog' @tap='goto'>
						<text class='t2'>{{data.userinfo.freeze_credit?data.userinfo.freeze_credit:0}}</text>
						<text class="t1">{{t('冻结账户')}}</text>
					</view>
					<view v-if="params.bonuspoolshow==1" class='item' data-url='/pagesA/bonuspool/withdraw' @tap='goto'>
						<text class='t2'>{{data.userinfo.bonus_pool_money?data.userinfo.bonus_pool_money:0}}</text>
						<text class="t1">{{t('贡献值')}}</text>
					</view>
          <view v-if="data.userinfo.product_deposit_mode" class='item' data-url='/pagesC/my/productdeposit' @tap='goto'>
            <text class='t2'>{{data.userinfo.product_deposit?data.userinfo.product_deposit:0}}</text>
            <text class="t1">{{t('押金')}}</text>
          </view>
					<view v-if="data.userinfo.product_deposit_mode" class='item' data-url='/pagesA/overdraft/detail' @tap='goto'>
						<text class='t2'>{{data.userinfo.overdraft_money?data.userinfo.overdraft_money:0}}</text>
						<text class="t1">{{t('信用额度')}}</text>
					</view>
					<view v-if="params.commissionwithdrawscoreshow==1" class='item' data-url='/pagesB/my/commissionwithdrawscorelog' @tap='goto'>
						<text class='t2'>{{data.userinfo.commission_withdraw_score?data.userinfo.commission_withdraw_score:0}}</text>
						<text class="t1">{{t('提现积分')}}</text>
					</view>
					<view v-if="params.fhcopiesshow==1" class='item' data-url='/pagesA/my/fhcopieslog' @tap='goto'>
						<text class='t2'>{{data.userinfo.fhcopies?data.userinfo.fhcopies:0}}</text>
						<text class="t1">{{t('分红份数')}}</text>
					</view>
					<view v-if="params.tongzhengshow==1" class='item' data-url='/pagesA/my/tongzhenglog' @tap='goto'>
						<text class='t2'>{{data.userinfo.tongzheng?data.userinfo.tongzheng:0}}</text>
						<text class="t1">{{t('通证')}}</text>
					</view>
					<view v-if="params.greenscoreshow==1" class='item' data-url='/pagesB/greenscore/greenscorelognew' @tap='goto'>
						<text class='t2'>{{data.userinfo.green_score?data.userinfo.green_score:0}}</text>
						<text class="t1">{{t('绿色积分')}}</text>
					</view>
					<view v-if="params.activecoinshow==1" class='item' data-url='/pagesB/my/activecoinlog' @tap='goto'>
						<text class='t2'>{{data.userinfo.active_coin?data.userinfo.active_coin:0}}</text>
						<text class="t1">{{t('激活币')}}</text>
					</view>
					<view v-if="params.scoreweightshow==1" class='item' data-url='/pagesB/my/scoreweightlog' @tap='goto'>
						<text class='t2'>{{data.userinfo.buy_fenhong_score_weight?data.userinfo.buy_fenhong_score_weight:0}}</text>
						<text class="t1">积分权</text>
					</view>
          <view v-if="params.showsilvermoney==1" class='item' data-url='/pagesB/my/silvermoneylog' @tap='goto'>
          	<text class='t2'>{{data.userinfo.silvermoney?data.userinfo.silvermoney:0}}</text>
          	<text class="t1">{{t('银值')}}</text>
          </view>
          <view v-if="params.showgoldmoney==1" class='item' data-url='/pagesB/my/goldmoneylog' @tap='goto'>
          	<text class='t2'>{{data.userinfo.goldmoney?data.userinfo.goldmoney:0}}</text>
          	<text class="t1">{{t('金值')}}</text>
          </view>
          <view v-if="params.showinviteredpacket==1" class='item' data-url='/pagesB/inviteredpacket/redpacketlist' @tap='goto'>
          	<text class='t2'>{{data.userinfo.inviteredpacketnum?data.userinfo.inviteredpacketnum:0}}</text>
          	<text class="t1">红包</text>
          </view>
          <view v-if="params.shopscoreshow==1" class='item' data-url='/pagesC/my/shopscorelog' @tap='goto'>
          	<text class='t2'>{{data.userinfo.shopscore?data.userinfo.shopscore:0}}</text>
          	<text class="t1">{{t('产品积分')}}</text>
          </view>
          <view v-if="params.dedamountshow==1" class='item' data-url='/pagesC/my/dedamountlog' @tap='goto'>
          	<text class='t2'>{{data.userinfo.dedamount?data.userinfo.dedamount:0}}</text>
          	<text class="t1">抵扣金</text>
          </view>
				</view>
				<view class="custom_field" v-if="data.userinfo.othermoney_status==1">
					<view class='item' data-url='/pagesExt/othermoney/withdraw?type=money2' @tap='goto'>
						<text class='t2'>{{data.userinfo.money2?data.userinfo.money2:0}}</text>
						<text class="t1">{{t('余额2')}}</text>
					</view>
					<view class='item' data-url='/pagesExt/othermoney/withdraw?type=money3' @tap='goto'>
						<text class='t2'>{{data.userinfo.money3?data.userinfo.money3:0}}</text>
						<text class="t1">{{t('余额3')}}</text>
					</view>
					<view class='item' data-url='/pagesExt/othermoney/withdraw?type=money4' @tap='goto'>
						<text class='t2'>{{data.userinfo.money4?data.userinfo.money4:0}}</text>
						<text class="t1">{{t('余额4')}}</text>
					</view>
					<view class='item' data-url='/pagesExt/othermoney/withdraw?type=money5' @tap='goto'>
						<text class='t2'>{{data.userinfo.money5?data.userinfo.money5:0}}</text>
						<text class="t1">{{t('余额5')}}</text>
					</view>
					<view class='item' data-url='/pagesExt/othermoney/frozen_moneylog' @tap='goto'>
						<text class='t2'>{{data.userinfo.frozen_money?data.userinfo.frozen_money:0}}</text>
						<text class="t1">{{t('冻结金额')}}</text>
					</view>
				</view>
				<block v-if="data.parent_show">
					<view class="parent" v-if="data.parent" :style="'background: rgba('+t('color1rgb')+',10%);'">
							<view class="f1">
								<image class="parentimg" :src="data.parent.headimg"></image>
								<view class="parentimg-tag" :style="'background: rgba('+t('color1rgb')+',100%);'">{{t('推荐人')}}</view>
							</view>
							<view class="f2 flex1">
								<view class="nick">{{data.parent.nickname}}</view>
								<view class="nick" v-if="data.parent && data.parent.weixin" @tap="copy" :data-text="data.parent.weixin">微信号：{{data.parent.weixin}}<image :src="pre_url+'/static/img/copy.png'" class="copyicon"></image></view>
							</view>
							<view class="f3" v-if="data.parent && data.parent.tel" @tap="goto" :data-url="'tel::'+data.parent.tel"><image :src="pre_url+'/static/img/tel2.png'" class="handle-img"></image></view>
					</view>
					<view class="parent" v-else :style="'background: rgba('+t('color1rgb')+',10%);'">
						<image class="f1 parentimg" :src="data.sysset.logo"/>
						<view class="f2 flex1">
							<view class="nick">{{data.sysset.name}}</view>
							<view class="nick">{{data.sysset.tel}}</view>
						</view>
						<view class="f3" @tap="goto" :data-url="'tel::'+data.sysset.tel"><image :src="pre_url+'/static/img/tel2.png'" class="handle-img"></image></view>
					</view>
				</block>
			</view>
			<block v-if="params.seticonsize && params.seticonsize>0">
					<view class="userset usersetl" @tap="goto" data-url="/pagesA/my/memberCode" v-if="params.membercodeshow=='1'" 
					:style="{right:usercenterNavigationCustom == 0 ? params.seticonsize*2.2*2.8+'rpx' : (Number(navigationMenu.width) + Number(params.seticonsize) + Number(params.seticonsize)+10) +'px',top: navigationMenu.top + 'px'}">
							<image :src="pre_url+'/static/img/qrcode.png'" class="img" :style="'width:'+params.seticonsize*2.2+'rpx'"/>
					</view>
					<view class="userset" @tap="goto" data-url="/pagesExt/my/set" v-if="params.seticonshow!=='0'"
					:style="{right:usercenterNavigationCustom == 0 ? params.seticonsize*2.2+'rpx' : (Number(navigationMenu.width) + Number(params.seticonsize)) +'px',top: navigationMenu.top + 'px'}">
							<image :src="params.seticon?params.seticon:pre_url+'/static/img/set.png'" class="img" :style="'width:'+params.seticonsize*2.2+'rpx;height:'+params.seticonsize*2.2+'rpx'"/>
					</view>
			</block>
			<block v-else>
			    <view class="userset" v-if="params.seticonshow!=='0'" @tap="goto" data-url="/pagesExt/my/set">
			        <image :src="params.seticon?params.seticon:pre_url+'/static/img/set.png'" class="img"/>
			    </view>
			</block>
		</view>
	</view>
	
	
	<view v-if="params.style==2" :style="'background: linear-gradient(45deg,'+t('color1')+' 0%, rgba('+t('color1rgb')+',0.8) 100%);'" style="width:100%;position: absolute;top: 0">
		<view class="dp-userinfo2" :style="{background:'url('+params.bgimg+') no-repeat',backgroundSize:'cover',margin:(0*2.2)+'rpx '+(params.margin_x*2.2)+'rpx',padding:(params.padding_y*2.2)+'rpx '+(params.padding_x*2.2)+'rpx',height:data.userinfo.othermoney_status == 1?'600rpx':'490rpx'}"></view>
	</view>
	<view class="dp-userinfo2" v-if="params.style==2" :style="{margin:(0*2.2)+'rpx '+(params.margin_x*2.2)+'rpx',padding:(params.padding_y*2.2)+'rpx '+(params.padding_x*2.2)+'rpx',height:'auto'}">
		<view class="info">
			<image class="headimg" :src="data.userinfo.headimg" @tap="weixinlogin"/>
			<view class="nickname">
				<view class="nick">{{data.userinfo.nickname}} </view>
				<view v-if="params.realnameVerifyShow == 1" @tap="goto" data-url="/pagesExt/my/setrealname">
					<text class="tag-gray" v-if="data.userinfo.realname_status != 1">未实名</text>
					<text class="tag-renzheng" v-if="data.userinfo.realname_status == 1">实名认证</text>
				</view>
				<view v-if="data.zhaopin && data.zhaopin.show_zhaopin" class="flex">
					<text v-if="data.zhaopin.is_qiuzhi_renzheng && !data.zhaopin.is_qiuzhi_qianyue" class="tag-renzheng">认证保障中</text>
					<text v-if="data.zhaopin.is_qiuzhi_qianyue" class="tag-renzheng">签约保障中</text>
					<text v-if="data.zhaopin.is_zhaopin_renzheng" class="tag-renzheng">认证企业</text>
				</view>
				<!-- <view class="desc">ID：{{userinfo.id}}</view> -->
				<view style="display: flex;" v-if="params.levelshow==1">
					<view class="user-level" @tap="openLevelup" data-levelid="">
						<image class="level-img" :src='data.userlevel.icon' v-if="data.userlevel.icon"/>
						<view class="level-name">{{data.userlevel.name}}</view>
					</view>
					<view class="user-level" v-for="(item, index) in data.userlevelList" :key="index" @tap="openLevelup" :data-levelid="item.id">
						<image class="level-img" :src='item.icon' v-if="item.icon"/>
						<view class="level-name">{{item.name}}</view>
					</view>
			
				</view>
		
				<view class="usermid" v-if="params.midshow=='1'">会员ID：<text user-select="true" selectable="true">{{data.userinfo.id}}</text></view>
				<view class="usermid" v-if="data.userlevel.can_agent > 0 && data.sysset.reg_invite_code!='0' && data.sysset.reg_invite_code_type==1">邀请码：<text user-select="true" selectable="true">{{data.userinfo.yqcode}}</text></view>
				<view class="flex" v-if="data.userinfo && data.userinfo.show_commission_max">
					<text v-if="data.userinfo.commission_to_score==1" @tap="goto" data-url="/pagesB/my/commissionmaxtoscore" class="tag-renzheng">{{t('佣金上限')}}：{{data.userinfo.remain_commission_max}}</text>
					<text v-else class="tag-renzheng">{{t('佣金上限')}}：{{data.userinfo.remain_commission_max}}</text>
				</view>
			</view>
      <image class="level-img" v-if="data.userinfo.tag_pic" style="width:100rpx;height: 40rpx;margin-top: 15rpx" :src='data.userinfo.tag_pic'/>
			<view class="ktnum" v-if="params.ktnumshow=='1'" style="display: flex; position: absolute; right: 30rpx; top:30%;color:#ffff; " >开团次数：<text style="font-size: 24rpx;line-height: 40rpx;" >{{data.userinfo.ktnum}}</text></view>
		</view>
    <view class="custom_field" style="margin-top:2rpx;padding:0;padding-left:168rpx;font-size: 24rpx;color:rgba(255,255,255,0.8)" v-if="data.userinfo.agentarea">{{data.userinfo.agentarea}}</view>
		<view class="custom_field">
            <view class='item-style2' v-if="params.moneyshow==1 && data.userinfo.money>0" data-url='/pagesExt/money/recharge' @tap='goto'>
                <text class='t2'>{{data.userinfo.money}}</text>
                <text class="t1">{{t('余额')}}</text>
            </view>
            <view class='item-style2' v-if="params.commissionshow==1 && data.userlevel && data.userlevel.can_agent>0 && data.userinfo.commission>0" data-url='/pages/commission/index' @tap='goto'>
                <text class='t2'>{{data.userinfo.commission}}</text>
                <text class="t1">{{t('佣金')}}</text>
            </view>
			<view class='item-style2' v-if="params.scoreshow==1" data-url='/pagesExt/my/scorelog' @tap='goto'>
				<text class='t2'>{{data.userinfo.score}}</text>
				<text class="t1">{{t('积分')}}</text>
			</view>
			<view class='item-style2' v-if="params.bscoreshow==1" data-url='/pagesExt/my/bscore' @tap='goto'>
				<text class='t2'>{{data.userinfo.bscore+data.userinfo.score}}</text>
				<text class="t1">总{{t('积分')}}</text>
			</view>
			<view class='item-style2' v-if="!isNull(data.userinfo.service_fee) && params.servicefeeshow==1" data-url='/pagesB/service_fee/recharge' @tap='goto'>
				<text class='t2'>{{data.userinfo.service_fee}}</text>
				<text class="t1">{{t('服务费')}}</text>
			</view>
			<view class='item-style2' v-if="params.couponshow==1" data-url='/pagesExt/coupon/mycoupon' @tap='goto'>
				<text class='t2'>{{data.userinfo.couponcount}}</text>
				<text class="t1">{{t('优惠券')}}</text>
			</view>
			<view class='item-style2' v-if="params.formshow==1" data-url='/pagesA/form/formlog?st=1' @tap='goto'>
				<text class='t2'>{{data.userinfo.formcount}}</text>
				<text class="t1">{{params.formtext}}</text>
			</view>
			<view class='item-style2' v-if="data.userinfo.fuchi_money>=0" data-url='/pagesExt/my/fuchi' @tap='goto'>
				<text class='t2'>{{data.userinfo.fuchi_money}}</text>
				<text class="t1">{{t('扶持金')}}</text>
			</view>
			<view class='item-style2' v-if="data.userinfo.gongxian>=0" data-url='/pagesExt/my/gongxianLog' @tap='goto'>
				<text class='t2'>{{data.userinfo.gongxian?data.userinfo.gongxian:0}}</text>
				<text class="t1">{{t('贡献')}}</text>
			</view>
			<view class='item-style2' v-if="params.xiaofeishow==1" data-url='/pagesA/my/xiaofeimoneylog' @tap='goto'>
				<text class='t2'>{{data.userinfo.xiaofei_money?data.userinfo.xiaofei_money:0}}</text>
				<text class="t1">{{t('冻结佣金')}}</text>
			</view>
			<view class='item-style2' v-if="params.freezecreditshow==1" data-url='/pagesB/my/freezecreditlog' @tap='goto'>
				<text class='t2'>{{data.userinfo.freeze_credit?data.userinfo.freeze_credit:0}}</text>
				<text class="t1">{{t('冻结账户')}}</text>
			</view>
			<view class='item-style2' v-if="params.bonuspoolshow==1" data-url='/pagesA/bonuspool/withdraw' @tap='goto'>
				<text class='t2'>{{data.userinfo.bonus_pool_money?data.userinfo.bonus_pool_money:0}}</text>
				<text class="t1">{{t('贡献值')}}</text>
			</view>
      <view v-if="data.userinfo.product_deposit_mode" class='item-style2' data-url='/pagesC/my/productdeposit' @tap='goto'>
        <text class='t2'>{{data.userinfo.product_deposit?data.userinfo.product_deposit:0}}</text>
        <text class="t1">{{t('押金')}}</text>
      </view>
			<view class='item-style2' v-if="params.overdraftmoneyshow==1" data-url='/pagesA/overdraft/detail' @tap='goto'>
				<text class='t2'>{{data.userinfo.overdraft_money?data.userinfo.overdraft_money:0}}</text>
				<text class="t1">{{t('信用额度')}}</text>
			</view>
			<view v-if="params.commissionwithdrawscoreshow==1" class='item-style2' data-url='/pagesB/my/commissionwithdrawscorelog' @tap='goto'>
				<text class='t2'>{{data.userinfo.commission_withdraw_score?data.userinfo.commission_withdraw_score:0}}</text>
				<text class="t1">{{t('提现积分')}}</text>
			</view>
			<view class='item-style2' v-if="params.fhcopiesshow==1" data-url='/pagesA/my/fhcopieslog' @tap='goto'>
				<text class='t2'>{{data.userinfo.fhcopies?data.userinfo.fhcopies:0}}</text>
				<text class="t1">{{t('分红份数')}}</text>
			</view>
			<view class='item-style2' v-if="params.tongzhengshow==1" data-url='/pagesA/my/tongzhenglog' @tap='goto'>
				<text class='t2'>{{data.userinfo.tongzheng?data.userinfo.tongzheng:0}}</text>
				<text class="t1">{{t('通证')}}</text>
			</view>
			<view v-if="params.greenscoreshow==1" class='item-style2' data-url='/pagesB/greenscore/greenscorelognew' @tap='goto'>
				<text class='t2'>{{data.userinfo.green_score?data.userinfo.green_score:0}}</text>
				<text class="t1">{{t('绿色积分')}}</text>
			</view>
			<view v-if="params.activecoinshow==1" class='item-style2' data-url='/pagesB/my/activecoinlog' @tap='goto'>
				<text class='t2'>{{data.userinfo.active_coin?data.userinfo.active_coin:0}}</text>
				<text class="t1">{{t('激活币')}}</text>
			</view>
			<view v-if="params.scoreweightshow==1" class='item-style2' data-url='/pagesB/my/scoreweightlog' @tap='goto'>
				<text class='t2'>{{data.userinfo.buy_fenhong_score_weight?data.userinfo.buy_fenhong_score_weight:0}}</text>
				<text class="t1">积分权</text>
			</view>
      <view v-if="params.showsilvermoney==1" class='item-style2' data-url='/pagesB/my/silvermoneylog' @tap='goto'>
      	<text class='t2'>{{data.userinfo.silvermoney?data.userinfo.silvermoney:0}}</text>
      	<text class="t1">{{t('银值')}}</text>
      </view>
      <view v-if="params.showgoldmoney==1" class='item-style2' data-url='/pagesB/my/goldmoneylog' @tap='goto'>
      	<text class='t2'>{{data.userinfo.goldmoney?data.userinfo.goldmoney:0}}</text>
      	<text class="t1">{{t('金值')}}</text>
      </view>
      <view v-if="params.showinviteredpacket==1" class='item-style2' data-url='/pagesB/inviteredpacket/redpacketlist' @tap='goto'>
      	<text class='t2'>{{data.userinfo.inviteredpacketnum?data.userinfo.inviteredpacketnum:0}}</text>
      	<text class="t1">红包</text>
      </view>
      <view v-if="params.shopscoreshow==1" class='item-style2' data-url='/pagesC/my/shopscorelog' @tap='goto'>
      	<text class='t2'>{{data.userinfo.shopscore?data.userinfo.shopscore:0}}</text>
      	<text class="t1">{{t('产品积分')}}</text>
      </view>
      <view v-if="params.dedamountshow==1" class='item-style2' data-url='/pagesC/my/dedamountlog' @tap='goto'>
      	<text class='t2'>{{data.userinfo.dedamount?data.userinfo.dedamount:0}}</text>
      	<text class="t1">抵扣金</text>
      </view>
		</view>
		<view class="custom_field" v-if="data.userinfo.othermoney_status==1">
			<view class='item-style2' data-url='/pagesExt/othermoney/withdraw?type=money2' @tap='goto' style="margin-right:0">
				<text class='t2'>{{data.userinfo.money2?data.userinfo.money2:0}}</text>
				<text class="t1">{{t('余额2')}}</text>
			</view>
			<view class='item-style2' data-url='/pagesExt/othermoney/withdraw?type=money3' @tap='goto' style="margin-right:0">
				<text class='t2'>{{data.userinfo.money3?data.userinfo.money3:0}}</text>
				<text class="t1">{{t('余额3')}}</text>
			</view>
			<view class='item-style2' data-url='/pagesExt/othermoney/withdraw?type=money4' @tap='goto' style="margin-right:0">
				<text class='t2'>{{data.userinfo.money4?data.userinfo.money4:0}}</text>
				<text class="t1">{{t('余额4')}}</text>
			</view>
			<view class='item-style2' data-url='/pagesExt/othermoney/withdraw?type=money5' @tap='goto' style="margin-right:0">
				<text class='t2'>{{data.userinfo.money5?data.userinfo.money5:0}}</text>
				<text class="t1">{{t('余额5')}}</text>
			</view>
				<view class='item-style2' data-url='/pagesExt/othermoney/frozen_moneylog' @tap='goto' style="margin-right:0">
					<text class='t2'>{{data.userinfo.frozen_money?data.userinfo.frozen_money:0}}</text>
					<text class="t1">{{t('冻结金额')}}</text>
				</view>
		</view>
		<block v-if="params.seticonsize && params.seticonsize>0 ">
				<view class="userset usersetl"
				 @tap="goto" data-url="/pagesA/my/memberCode" v-if="params.membercodeshow=='1'" :style="'right:'+params.seticonsize*2.2*2.8+'rpx;top:' + NavigationCustomTop + 'px'">
						<image :src="pre_url+'/static/img/qrcode.png'" class="img" :style="'width:'+params.seticonsize*2.2+'rpx;height:'+params.seticonsize*2.2+'rpx'"/>
				</view>
				<view class="userset" @tap="goto" data-url="/pagesExt/my/set" v-if="params.seticonshow!=='0'" :style="'right:'+params.seticonsize*2.2+'rpx;top:' + NavigationCustomTop + 'px'">
						<image :src="params.seticon?params.seticon:pre_url+'/static/img/set.png'" class="img" :style="'width:'+params.seticonsize*2.2+'rpx;height:'+params.seticonsize*2.2+'rpx'"/>
				</view>
		</block>
		<block v-else>
				<view class="userset" @tap="goto" data-url="/pagesExt/my/set" v-if="params.seticonshow!=='0'">
						<image :src="params.seticon?params.seticon:pre_url+'/static/img/set.png'" class="img"/>
				</view>
		</block>
	        
		<block v-if="platform=='wx'">
			<view class="usercard" :style="{top:usercenterNavigationCustom == 0 ? '140rpx':NavigationCustomTop + 28 +'px'}" v-if="params.cardshow==1 && data.userinfo.card_code" @tap="opencard" :data-card_id="data.userinfo.card_id" :data-card_code="data.userinfo.card_code"><image class="img" :src="pre_url+'/static/img/ico-card2.png'"/><text class="txt">会员卡</text></view>
			<view class="usercard" :style="{top:usercenterNavigationCustom == 0 ? '140rpx':NavigationCustomTop + 28 +'px'}" v-if="params.cardshow==1 && !data.userinfo.card_code" @tap="addmembercard" :data-card_id="data.card_id"><image class="img" :src="pre_url+'/static/img/ico-card2.png'"/><text class="txt">会员卡</text></view>
		</block>
		<block v-if="platform=='mp'">
			<view class="usercard" v-if="params.cardshow==1 && data.userinfo.card_code" @tap="opencard" :data-card_id="data.userinfo.card_id" :data-card_code="data.userinfo.card_code"><image class="img" :src="pre_url+'/static/img/ico-card2.png'"/><text class="txt">会员卡</text></view>
			<view class="usercard" v-if="params.cardshow==1 && !data.userinfo.card_code" @tap="goto" :data-url="data.card_returl"><image class="img" :src="pre_url+'/static/img/ico-card2.png'"/><text class="txt">会员卡</text></view>
		</block>
	</view>
	<view class="dp-userinfo-order" v-if="params.style==2 && data.parent_show" :style="{'margin':(params.padding_y*2.2)+'rpx '+(params.padding_x*2.2)+'rpx'}">
		<view class="parent" v-if="data.parent">
				<view class="f1">
					<image class="parentimg" :src="data.parent.headimg"></image>
					<view class="parentimg-tag" :style="'background: rgba('+t('color1rgb')+',100%);'">{{t('推荐人')}}</view>
				</view>
				<view class="f2 flex1">
					<view class="nick">{{data.parent.nickname}}</view>
					<view class="nick" v-if="data.parent && data.parent.weixin" @tap="copy" :data-text="data.parent.weixin">微信号：{{data.parent.weixin}}<image :src="pre_url+'/static/img/copy.png'" class="copyicon"></image></view>
				</view>
				<view class="f3" v-if="data.parent && data.parent.tel" @tap="goto" :data-url="'tel::'+data.parent.tel"><image :src="pre_url+'/static/img/tel2.png'" class="handle-img"></image></view>
		</view>
		<view class="parent" v-else>
			<image class="f1 parentimg" :src="data.sysset.logo"/>
			<view class="f2 flex1">
				<view class="nick">{{data.sysset.name}}</view>
				<view class="nick">{{data.sysset.tel}}</view>
			</view>
			<view class="f3" @tap="goto" :data-url="'tel::'+data.sysset.tel"><image :src="pre_url+'/static/img/tel2.png'" class="handle-img"></image></view>
		</view>
	</view>
	<view class="dp-userinfo-order" :style="{'margin':((params.padding_y*2.2) || 20)+'rpx '+(params.padding_x*2.2)+'rpx','marginTop':((params.style==2 && !data.parent_show && data.userinfo.othermoney_status !=1) ? '20rpx':((params.padding_y*2.2) || 20)+'rpx ')}" v-if="params.ordershow==1 && (!params.showtype || params.showtype == 0)">
		<view class="head">
			<text class="f1">我的订单</text>
			<view class="f2" @tap="goto" data-url="/pagesExt/order/orderlist"><text>查看全部订单</text><image :src="pre_url+'/static/img/arrowright.png'" class="image"/></view>
		</view>
		<view class="content">
			 <view class="item" @tap="goto" data-url="/pagesExt/order/orderlist?st=0">
					<text class="iconfont icondaifukuan" :style="{color:t('color1')}"></text>
					<view class="t2" v-if="data.orderinfo.count0>0">{{data.orderinfo.count0}}</view>
					<text class="t3">待付款</text>
			 </view>
			 <view class="item" @tap="goto" data-url="/pagesExt/order/orderlist?st=1">
					<!-- <image src="/static/img/order2.png" class="image"/> -->
					<text class="iconfont icondaifahuo" :style="{color:t('color1')}"></text>
					<view class="t2" v-if="data.orderinfo.count1>0">{{data.orderinfo.count1}}</view>
					<text class="t3">待发货</text>
			 </view>
			 <view class="item" @tap="goto" data-url="/pagesExt/order/orderlist?st=2">
					<!-- <image src="/static/img/order3.png" class="image"/> -->
					<text class="iconfont icondaishouhuo" :style="{color:t('color1')}"></text>
					<view class="t2" v-if="data.orderinfo.count2>0">{{data.orderinfo.count2}}</view>
					<text class="t3">待收货</text>
			 </view>
			 <view class="item" @tap="goto" data-url="/pagesExt/order/orderlist?st=3">
					<!-- <image src="/static/img/order4.png" class="image"/> -->
					<text class="iconfont iconyiwancheng" :style="{color:t('color1')}"></text>
					<view class="t2" v-if="data.orderinfo.count3>0">{{data.orderinfo.count3}}</view>
					<text class="t3">已完成</text>
			 </view>
			 <view class="item" @tap="goto" data-url="/pagesExt/order/refundlist">
					<!-- <image src="/static/img/order4.png" class="image"/> -->
					<text class="iconfont icontuikuandingdan" :style="{color:t('color1')}"></text>
					<view class="t2" v-if="data.orderinfo.count4>0">{{data.orderinfo.count4}}</view>
					<text class="t3">退款/售后</text>
			 </view>
      <view class="item" v-if="data.orderinfo.transfer_order_parent_check" @tap="goto" data-url="/pagesC/transferorderparent/orderlist">
        <!-- <image src="/static/img/order4.png" class="image"/> -->
        <text class="iconfont icondaifahuo" :style="{color:t('color1')}"></text>
        <view class="t2" v-if="data.orderinfo.count5>0">{{data.orderinfo.count5}}</view>
        <text class="t3">审核订单</text>
      </view>
		</view>
	</view>
	<view class="dp-userinfo-order" :style="{'margin':((params.padding_y*2.2) || 20)+'rpx '+(params.padding_x*2.2)+'rpx','marginTop':((params.style==2 && !data.parent_show && data.userinfo.othermoney_status !=1) ? '20rpx':((params.padding_y*2.2) || 20)+'rpx ')}"
	v-if="params.ordershow==1 && params.showtype == 1">
		<view class="head">
			<text class="f1">{{params.ordertitle}}</text>
			<view class="f2" @tap="goto" data-url="/pagesExt/order/orderlist"><text>查看全部订单</text><image :src="pre_url+'/static/img/arrowright.png'" class="image"/></view>
		</view>
		<view class="content">
			 <view class="item" v-for="(item,index) in params.orderData" @click="optionJump(item.type)" v-if="item.show != 0">
					<view class="image-view">
						<image :src="item.imgurl"></image>
					</view>
					<view class="t2" v-if="item.num>0">{{item.num}}</view>
					<text class="t3">{{item.text}}</text>
			 </view>
		</view>
	</view>
	<view class="dp-userinfo-order" :style="{'margin':(params.padding_y*2.2)+'rpx '+(params.padding_x*2.2)+'rpx','marginTop':(params.padding_y == 0 || params.padding_y > 10 ? '20rpx':(params.padding_y*2.2)+'rpx')}" v-if="params.scoreshopordershow==1">
		<view class="head">
			<text class="f1">{{t('积分')}}兑换订单</text>
			<view class="f2" @tap="goto" data-url="/activity/scoreshop/orderlist"><text>查看全部订单</text><image :src="pre_url+'/static/img/arrowright.png'" class="image"/></view>
		</view>
		<view class="content">
			 <view class="item" @tap="goto" data-url="/activity/scoreshop/orderlist?st=0">
					<text class="iconfont icondaifukuan" :style="{color:t('color1')}"></text>
					<view class="t2" v-if="data.scoreshoporder.count0>0">{{data.scoreshoporder.count0}}</view>
					<text class="t3">待付款</text>
			 </view>
			 <view class="item" @tap="goto" data-url="/activity/scoreshop/orderlist?st=1">
					<!-- <image src="/static/img/order2.png" class="image"/> -->
					<text class="iconfont icondaifahuo" :style="{color:t('color1')}"></text>
					<view class="t2" v-if="data.scoreshoporder.count1>0">{{data.scoreshoporder.count1}}</view>
					<text class="t3">待发货</text>
			 </view>
			 <view class="item" @tap="goto" data-url="/activity/scoreshop/orderlist?st=2">
					<!-- <image src="/static/img/order3.png" class="image"/> -->
					<text class="iconfont icondaishouhuo" :style="{color:t('color1')}"></text>
					<view class="t2" v-if="data.scoreshoporder.count2>0">{{data.scoreshoporder.count2}}</view>
					<text class="t3">待收货</text>
			 </view>
			 <view class="item" @tap="goto" data-url="/activity/scoreshop/orderlist?st=3">
					<!-- <image src="/static/img/order4.png" class="image"/> -->
					<text class="iconfont iconyiwancheng" :style="{color:t('color1')}"></text>
					<view class="t2" v-if="data.scoreshoporder.count3>0">{{data.scoreshoporder.count3}}</view>
					<text class="t3">已完成</text>
			 </view>
			 <view class="item" @tap="goto" data-url="/activity/scoreshop/orderlist?st=10" v-if="params.scoreshowrefund == 1">
					<!-- <image src="/static/img/order4.png" class="image"/> -->
					<text class="iconfont icontuikuandingdan" :style="{color:t('color1')}"></text>
					<view class="t2" v-if="data.scoreshoporder.count4>0">{{data.scoreshoporder.count4}}</view>
					<text class="t3">退款/售后</text>
			 </view>
		</view>
	</view>

	<view class="task_list" :style="{'margin':(params.padding_y*2.2)+'rpx '+(params.padding_x*2.2)+'rpx;'+ (params.padding_y == 0 || params.padding_y > 10 ? 'margin-top:20rpx':'')}" v-if="platform=='wx' && data.sysset.task_banner" @click="totaskbanner">
		<view class="item" @tap="goto" data-url="moneylog">
			<view class="f1"><image :src="pre_url+'/static/img/task_banner.png'"></image></view>
			<view class="f2">广告任务</view>
			<text class="f3">余量{{sy_count !=''?sy_count:data.sysset.sy_count}}次</text>
			<image :src="pre_url+'/static/img/arrowright.png'" class="f4"></image>
		</view>
	</view>
	<view v-if="data.userinfo.show_green_score" class="task_list" :style="{'margin':(params.padding_y*2.2)+'rpx '+(params.padding_x*2.2)+'rpx','marginTop':(params.padding_y == 0 || params.padding_y > 10 ? '20rpx':(params.padding_y*2.2)+'rpx'),'background':'rgba('+t('color1rgb')+',1)'}">
		<view class="item" @tap="goto" data-url="/pagesB/greenscore/greenscorelognew">
			<view class="icon-view"><image :src="pre_url+'/static/img/points.png'"></image></view>
			<view class="title-text">{{t('绿色积分')}}</view>
			<view class="data-num-view flex-col">
				<view class="top-num-text">{{data.userinfo.green_score}}</view>
				<view class="bot-num-text">{{data.userinfo.green_score_price}}</view>
			</view>
			<image :src="pre_url+'/static/img/shortvideo_arrowright.png'" class="jiantou-icon"></image>
		</view>
	</view>
	<view v-if="data.userinfo.show_cashback" class="task_list" :style="{'margin':(params.padding_y*2.2)+'rpx '+(params.padding_x*2.2)+'rpx','marginTop':(params.padding_y == 0 || params.padding_y > 10 ? '20rpx':(params.padding_y*2.2)+'rpx'),'background':'rgba('+t('color1rgb')+',1)'}">
		<view class="item" @tap="goto" data-url="/pagesC/releasePoints/details">
			<view class="icon-view"><image :src="pre_url+'/static/img/releasepoints.png'"></image></view>
			<view class="title-text">{{t('释放积分')}}</view>
			<view class="data-num-view flex-col">
				<view class="top-num-text">{{data.userinfo.cashback_price}}</view>
				<view class="bot-num-text">+{{data.userinfo.last_cashback_price}}</view>
			</view>
			<image :src="pre_url+'/static/img/shortvideo_arrowright.png'" class="jiantou-icon"></image>
		</view>
	</view>
	<view v-if="data.userinfo.show_cashback_multiply" class="task_list" :style="{'margin':(params.padding_y*2.2)+'rpx '+(params.padding_x*2.2)+'rpx','marginTop':(params.padding_y == 0 || params.padding_y > 10 ? '20rpx':(params.padding_y*2.2)+'rpx'),'background':'rgba('+t('color1rgb')+',1)'}">
		<view class="item" @tap="goto" data-url="/pagesC/releasePoints/cashbacklog">
			<view class="icon-view"><image :src="pre_url+'/static/img/zengzhi.png'"></image></view>
			<view class="title-text">增值{{t('释放积分')}}</view>
			<view class="data-num-view flex-col">
				<view class="top-num-text">{{data.userinfo.cashback_price_multiply}}</view>
				<view class="bot-num-text">+{{data.userinfo.last_cashback_price_multiply}}</view>
			</view>
			<image :src="pre_url+'/static/img/shortvideo_arrowright.png'" class="jiantou-icon"></image>
		</view>
	</view>

	<loading v-if="loading"></loading>
</view>
</template>
<script>
	let videoAd = null;
	var app = getApp();
	export default {
		data(){
			return {
				textset:app.globalData.textset,
				platform:app.globalData.platform,
				pre_url:app.globalData.pre_url,
				task_banner:false,
				loading: false,
				choujiang_id:0,
				sy_count:'',
				videoAd:'',
				tabFlex:false,
				custom_field:false,
				NavigationCustomTop:'20',
				usercenterNavigationCustom:0,
				navigationMenu:{}
			}
		},
		props: {
			params:{},
			data:{}
		},
		mounted() {
			// #ifdef MP-WEIXIN
			this.usercenterNavigationCustom = app.globalData.usercenterNavigationCustom;
			if(app.globalData.usercenterNavigationCustom != 0){
				this.navigationMenu = wx.getMenuButtonBoundingClientRect();
				this.NavigationCustomTop = this.navigationMenu.height + this.navigationMenu.top;
			}
			// #endif
			// #ifndef H5
			let arr = [];
			if(this.params.moneyshow == 1) arr.push(true);
			if(this.params.commissionshow == 1 && this.data.userlevel && this.data.userlevel.can_agent>0) arr.push(true);
			if(this.params.scoreshow == 1) arr.push(true);
			if(this.params.bscoreshow == 1) arr.push(true);
			if(this.params.couponshow == 1) arr.push(true);
			if(this.data.userinfo.fuchi_money >= 0) arr.push(true);
			if(this.data.userinfo.gongxian >= 0) arr.push(true);
			if(this.params.bonuspoolshow == 1) arr.push(true);
			if(this.params.xiaofeishow == 1) arr.push(true);
			if(this.params.overdraftmoneyshow == 1) arr.push(true);
			if(this.params.formshow == 1) arr.push(true);
			if(this.params.fhcopiesshow == 1) arr.push(true);
			if(arr.length < 4){
				this.tabFlex = false
			}else{
				this.tabFlex = true
			}
			// #endif
			// #ifdef H5
			if(this.params.style == 1){
				const tabNum = this.$refs.custom_field1.$children.length;
				if(tabNum<4){
					this.tabFlex = false
				}else{
					this.tabFlex = true
				}
			}
			// #endif
		},
		methods:{
			optionJump(type){
				let url = '';
				switch (type){
					case 'daifukuan':
					url = '/pagesExt/order/orderlist?st=0'
					break;
					case 'daifahuo':
					url = '/pagesExt/order/orderlist?st=1'
					break;
					case 'daishouhuo':
					url = '/pagesExt/order/orderlist?st=2'
					break;
					case 'wancheng':
					url = '/pagesExt/order/orderlist?st=3'
					break;
					case 'tuikuan':
					url = '/pagesExt/order/orderlist?st=4'
					break;
				}
				app.goto(url)
			},
			openLevelup:function(e){
				var levelid = e.currentTarget.dataset.levelid
				if(parseInt(this.params.levelclick) !== 0){
					app.goto('/pagesExt/my/levelinfo?id='+levelid)
				}
			},
			opencard:function(e){
				var cardId = e.currentTarget.dataset.card_id
				var code = e.currentTarget.dataset.card_code
				if(app.globalData.platform == 'mp') {
					var jweixin = require('jweixin-module');
					jweixin.openCard({
						cardList: [{
							cardId: cardId,
							code: code
						}],
						success:function(res) { }
					})
				} else {
					wx.openCard({
						cardList: [{
							cardId: cardId,
							code: code
						}],
						success:function(res) { }
					})
				}
				
			},
			//领取微信会员卡
			addmembercard:function(e){
				var cardId = e.currentTarget.dataset.card_id
				app.post('ApiCoupon/getmembercardparam',{card_id:cardId},function(res){
					if(res.status==0){
						app.alert(res.msg);
						return;
					}
					wx.navigateToMiniProgram({
						appId: 'wxeb490c6f9b154ef9', // 固定为此appid，不可改动
						extraData: res.extraData, // 包括encrypt_card_id outer_str biz三个字段，须从step3中获得的链接中获取参数
						success: function() {},
						fail: function() {},
						complete: function() {}
					})
				})
			},
			weixinlogin:function(){
				var that = this;
				if(that.data.userinfo.nickname != '未登录' || that.data.userinfo.id != 0) return; // 判断当前登录状态
				if(app.globalData.platform == 'wx' || app.globalData.platform == 'mp'){
					app.authlogin(function(res){
						if (res.status == 1) {
							app.success(res.msg);
							var pages = getCurrentPages(); //获取加载的页面
							var currentPage = pages[pages.length - 1]; //获取当前页面的对象
							currentPage.$vm.getdata();
						} else {
              app.goto('/pages/index/login');return;//后台有设置的强制事件，插件无法处理，直接跳转登录页面
							//app.error(res.msg);
						}
					});
				}else{
          app.goto('/pages/index/login');return;//后台有设置的强制事件，插件无法处理，直接跳转登录页面
        }
			},
			/*
			getdata(){
				var that  = this;
				if (app.globalData.platform == 'wx' && that.data.sysset.rewardedvideoad && !that.videoAd && wx.createRewardedVideoAd) {
					console.log('开始2');
					that.videoAd = wx.createRewardedVideoAd({
						adUnitId: that.data.sysset.rewardedvideoad
					});
					that.videoAd.onLoad(() => {})
					that.videoAd.onError((err) => {})
					that.videoAd.onClose(res2 => {
						that.isdoing = false;
						console.log(res2,'res');
					
						if (res2 && res2.isEnded) {
							console.log('开始走addRecord');
							that.toAddRecord();		
						} else {
							console.log('不走啊啊啊');
						}
					});
				}
			},
			*/
			totaskbanner(){
				var that = this;
				//if(!that.videoAd){
				//	that.getdata();
				//}
				app.post('ApiTaskBanner/getStatus', {}, function(data) {
					if(data.status ==1){
						if (app.globalData.platform == 'wx' && that.data.sysset.rewardedvideoad && wx.createRewardedVideoAd) {
							app.showLoading();
							if(!app.globalData.rewardedVideoAd[that.data.sysset.rewardedvideoad]){
								app.globalData.rewardedVideoAd[that.data.sysset.rewardedvideoad] = wx.createRewardedVideoAd({ adUnitId: that.data.sysset.rewardedvideoad});
							}
							var rewardedVideoAd = app.globalData.rewardedVideoAd[that.data.sysset.rewardedvideoad];
							rewardedVideoAd.load().then(() => {app.showLoading(false);rewardedVideoAd.show();}).catch(err => { app.alert('加载失败');});
							rewardedVideoAd.onError((err) => {
								app.showLoading(false);
								//app.alert(err.errMsg);
								console.log('onError event emit', err)
								rewardedVideoAd.offLoad()
								rewardedVideoAd.offClose();
							});
							rewardedVideoAd.onClose(res => {
								app.globalData.rewardedVideoAd[that.data.sysset.rewardedvideoad] = null;
								if (res && res.isEnded) {
									//app.alert('播放结束 发放奖励');
									that.toAddRecord();
								} else {
									console.log('播放中途退出，不下发奖励');
								}
								rewardedVideoAd.offLoad()
								rewardedVideoAd.offClose();
							});
						}else{
							return;
						}
					}else if(data.status ==233){
						that.choujiang_id = data.choujiang_id;
						app.confirm('您可以进行抽奖活动，是否继续?', function () {
							that.loading = true;
							app.post('ApiTaskBanner/setChoujiangStatus', {}, function (data) {
								that.loading = false;
								app.goto('/activity/xydzp/index?id='+that.choujiang_id);
							});
						})
					}else{
						app.error(data.msg);
						return;
					}
				})
			},
			toAddRecord(){
				var that = this;
				that.loading = true;
				app.post('ApiTaskBanner/addRecord', {}, function(data) {
					that.loading = false;
					var pages = getCurrentPages(); //获取加载的页面
					var currentPage = pages[pages.length - 1]; //获取当前页面的对象
					currentPage.$vm.getdata();
					if(data.status ==233){	
						that.sy_count = data.sy_count;
						that.choujiang_id = data.choujiang_id;
						app.confirm('您可以进行抽奖活动，是否继续?', function () {
							that.loading = true;
							app.post('ApiTaskBanner/setChoujiangStatus', {}, function (data) {
								that.loading = false;
								app.goto('/activity/xydzp/index?id='+that.choujiang_id);
							});
						})
					}else if(data.status ==1){
						that.sy_count = data.sy_count;
						app.success(data.msg);
					}else{
						app.error(data.msg);
						return;
					}
					
				});
				
			},
		}
	}
</script>
<style>
.dp-userinfo{position:relative;overflow: hidden;}
.dp-userinfo .banner{width:100%;margin-top:120rpx;border-radius:16rpx;background:#fff;padding:0 20rpx 10rpx;color:#333;position:relative;}
.dp-userinfo .banner .info{display:flex;align-items:flex-end}
.dp-userinfo .banner .info .f1{display:flex;flex-direction:column;}
.dp-userinfo .banner .headimg{ margin-top:-36rpx;width:136rpx;height:136rpx;border-radius:50%;margin-right:20rpx;border:3px solid #eee;}
.dp-userinfo .banner .info{margin-left:20rpx;display:flex;flex:auto;align-items:center}
.dp-userinfo .banner .info .nickname{min-width:140rpx;max-width:460rpx;text-align:center;height:60rpx;line-height:60rpx;font-size:44rpx;font-weight:bold;max-width: 300rpx;overflow: hidden;white-space: nowrap;}
.dp-userinfo .banner .getbtn{ width:120rpx;height:44rpx;padding:0 3px;line-height:44rpx;font-size: 24rpx;background: #09BB07;color:#fff;position: absolute;top:76rpx;left:10rpx;}
.dp-userinfo .banner .user-level{margin-left:24rpx;color:#b48b36;background-color:#ffefd4;margin-top:2px;width:auto;height:72rpx;border-radius:36rpx;padding:0 30rpx;display:flex;align-items:center}
.dp-userinfo .banner .user-level .level-img{width:42rpx;height:42rpx;margin-right:8rpx;margin-left:0;border-radius:50%;}
.dp-userinfo .banner .user-level .level-name{font-size:39rpx;}
.dp-userinfo .banner .user-level image{border-radius:50%;}
.dp-userinfo .banner .usercard{position:absolute;right:32rpx;top:28rpx;width:160rpx;height:60rpx;text-align:center;border:1px solid #FFB2B2;border-radius:8rpx;color:#FC4343;font-size:24rpx;font-weight:bold;display:flex;align-items:center;justify-content:center}
.dp-userinfo .banner .usercard .img{width:30rpx;height:30rpx;margin-right:8rpx;padding-bottom:4rpx}

.dp-userinfo .custom_field{display:flex;width:100%;align-items:center;padding:16rpx 8rpx;background:#fff;}
.dp-userinfo .custom_field .item{flex:1;display:flex;flex-direction:column;justify-content:center;align-items:center;}
.dp-userinfo .custom_field_flex{display:flex;width:100%;align-items:center;padding:16rpx 8rpx;background:#fff;flex-wrap: wrap;}
.dp-userinfo .custom_field_flex .item{flex:1; display:flex;flex-direction:column;justify-content:center;text-align:center;width: auto;margin-bottom: 10rpx;margin-right: 10rpx;}

.dp-userinfo .item .t1{color:#666;font-size:26rpx;align-items:center;min-width: 140rpx;text-align: center;}
.dp-userinfo .item .t2{color:#111;font-weight:bold;font-size:36rpx;align-items:center;min-width: 140rpx;text-align: center;}

.dp-userinfo .userset{width:54rpx;height:54rpx;padding:10rpx;position:absolute;top:40rpx;right:30rpx}
.dp-userinfo .userset .img{width:100%;height:100%}

.dp-userinfo2{height:490rpx;display:flex;flex-direction:column;position:relative}
.dp-userinfo2 .info{display:flex;margin-top:60rpx;margin-left:40rpx}
.dp-userinfo2 .info .headimg{width:136rpx;height:136rpx;background:#fff;border:3rpx solid rgba(255,255,255,0.7);border-radius:50%}
.dp-userinfo2 .info .nickname{margin-left:20rpx;display:flex;flex-direction:column;justify-content:center}
.dp-userinfo2 .info .nickname .nick{font-size:44rpx;font-weight:bold;color:#fff;height:60rpx;line-height:60rpx;max-width:400rpx;overflow:hidden;margin-right:10rpx}
.dp-userinfo2 .info .nickname .desc{font-size:24rpx;color:rgba(255,255,255,0.6);height:40rpx;line-height:40rpx}
.dp-userinfo2 .info .nickname .user-level{color:rgba(255,255,255,0.6);margin-top:2px;width:auto;height:72rpx;border-radius:36rpx;padding:0 30rpx;display:flex;align-items:center}
.dp-userinfo2 .info .nickname .user-level .level-img{width:42rpx;height:42rpx;margin-right:8rpx;margin-left:0;border-radius:50%;}
.dp-userinfo2 .info .nickname .user-level .level-name{font-size:39rpx;}
.dp-userinfo2 .info .nickname .usermid{color:rgba(255,255,255,0.8);font-size:24rpx;}

.dp-userinfo2 .custom_field{display:flex;width:100%;align-items:center;padding:16rpx 8rpx;margin-top:20rpx;overflow-x: scroll;}
.dp-userinfo2 .custom_field .item-style2{display:flex;flex-direction:column;justify-content:center;align-items:center;flex:1;margin-right: 10rpx;}
.dp-userinfo2 .custom_field .item-style2 .t1{color:rgba(255,255,255,0.6);font-size:24rpx;margin-top:10rpx;min-width: 140rpx;text-align: center;}
.dp-userinfo2 .custom_field .item-style2 .t2{color:#FFFFFF;font-weight:bold;font-size:32rpx;}
/* .dp-userinfo2 .custom_field .item{flex:1;display:flex;flex-direction:column;justify-content:center;align-items:center;min-width: 140rpx;} */
/* .dp-userinfo2 .custom_field .item .t1{color:rgba(255,255,255,0.6);font-size:24rpx;margin-top:10rpx} */
/* .dp-userinfo2 .custom_field .item .t2{color:#FFFFFF;font-weight:bold;font-size:32rpx;} */

.dp-userinfo2 .usercard{width:154rpx;height:54rpx;background:#fff;border-radius: 27rpx 0 0 27rpx;display:flex;align-items:center;padding-left:20rpx;position:absolute;top:140rpx;right:0}
.dp-userinfo2 .usercard .img{width:32rpx;height:32rpx;margin-right:6rpx}
.dp-userinfo2 .usercard .txt{color:#F4504C;font-size:24rpx;font-weight:bold}
.dp-userinfo2 .userset{width:54rpx;height:54rpx;padding:10rpx;position:absolute;top:40rpx;right:30rpx}
.dp-userinfo2 .userset .img{width:100%;height:100%}

.dp-userinfo-order{background:#fff;padding:0 20rpx;border-radius:16rpx;position: relative;}
.dp-userinfo-order .head{ display:flex;align-items:center;width:100%;padding:16rpx 0;}
.dp-userinfo-order .head .f1{flex:auto;font-size:30rpx;padding-left:16rpx;font-weight:bold;color:#333}
.dp-userinfo-order .head .f2{ display:flex;align-items:center;color:#999;width:auto;padding:10rpx 0;text-align:right;justify-content:flex-end}
.dp-userinfo-order .head .f2 .image{ width:30rpx;height:30rpx;}
.dp-userinfo-order .head .t3{ width:40rpx; height:40rpx;}
.dp-userinfo-order .content{ display:flex;width:100%;padding:0 0 10rpx 0;align-items:center;font-size:24rpx}
.dp-userinfo-order .content .item{padding:10rpx 0;flex:1;display:flex;flex-direction:column;align-items:center;position:relative}
.dp-userinfo-order .content .item .image{ width:50rpx;height:50rpx}
.dp-userinfo-order .content .item .iconfont{font-size:60rpx}
.dp-userinfo-order .content .item .t3{ padding-top:3px}
.dp-userinfo-order .content .item .t2{display:flex;align-items:center;justify-content:center;background: red;color: #fff;border-radius:50%;padding: 0 10rpx;position: absolute;top: 0px;right:20rpx;width:35rpx;height:35rpx;text-align:center;}

.dp-userinfo-order .content .item .image-view{width: 60rpx;height: 60rpx;}
.dp-userinfo-order .content .item .image-view image{width: 60rpx;height: 60rpx;}

.parent {padding:20rpx;border-radius:16rpx;justify-content: center;display:flex;align-items:center;font-size:24rpx; margin-bottom: 10rpx;}
.parent .parentimg{ width: 100rpx; height:100rpx; border-radius: 50%; z-index: 10;}
.parent .parentimg-tag { color: #fff; text-align: center; margin-top: -20rpx; z-index: 11; border-radius: 12rpx; padding: 2rpx 4rpx; position: relative; bottom: 2rpx;}
.parent .copyicon {width: 26rpx; height: 26rpx; margin-left: 8rpx; position: relative; top: 4rpx;}
.parent .f1 { position: relative;}
.parent .f2 { padding: 0 30rpx;}
.parent .handle-img {width: 60rpx; height: 60rpx;}
.parent .btn-box { padding: 20rpx 0;}
.parent button { padding: 0 40rpx; color: #fff; border-radius:20rpx; line-height: 60rpx;}

.tag-renzheng{color:#eeda65;background:#3a3a3a;border-radius: 8rpx;padding: 4rpx 8rpx;margin: 0 4rpx;font-size: 22rpx;}
.tag-gray{color:#fff;background:#999;border-radius: 8rpx;padding: 4rpx 8rpx;margin: 0 4rpx;font-size: 22rpx;}

.task_list{ background: #fff;padding:0 20rpx;font-size:30rpx;border-radius:16rpx;position: relative;}
.task_list .item{ height:100rpx;display:flex;align-items:center;border-bottom:1px solid #eee}
.task_list .item:last-child{border-bottom:0;}
.task_list .f1{width:50rpx;height:50rpx;line-height:50rpx;display:flex;align-items:center}
.task_list .f1 image{ width:44rpx;height:44rpx;}
.task_list .f1 span{ width:40rpx;height:40rpx;font-size:40rpx}
.task_list .f2{color:#222}
.task_list .f3{ color: #666;text-align:right;flex:1}
.task_list .f4{ width: 40rpx; height: 40rpx;}

.task_list .title-text{color: #fff;font-size: 30rpx;font-weight: 500;}
.task_list .item .icon-view{width: 55rpx;height: 55rpx;margin-right: 15rpx;}
.task_list .item .icon-view image{width: 100%;height: 100%;}
.task_list .data-num-view{flex: 1;justify-content: flex-end;text-align: right;}
.task_list .data-num-view .top-num-text{color: #fff;font-size: 30rpx;font-weight: 500;}
.task_list .data-num-view .bot-num-text{color: #ecdd36;font-size: 24rpx;}
.task_list .jiantou-icon{width: 45rpx; height: 45rpx;}
</style>
