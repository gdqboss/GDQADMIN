<template>
<view>
    <block v-if="isload">
        <block v-if="!hexiao_status">
            <block v-if="type=='shop'">
                <view class="address">
                    <view class="img">
                        <image :src="pre_url+'/static/img/address3.png'"></image>
                    </view>
                    <view class="info">
                        <text class="t1">{{order.linkman}} {{order.tel}}</text>
                        <text class="t2" v-if="order.freight_type!=1 && order.freight_type!=3">地址：{{order.area}}{{order.address}}</text>
                        <text class="t2" v-if="order.freight_type==1" @tap="openLocation" :data-address="order.storeinfo.address" :data-latitude="order.storeinfo.latitude" :data-longitude="order.storeinfo.longitude">取货地点：{{order.storeinfo.name}} - {{order.storeinfo.address}}</text>
                    </view>
                </view>
                <view class="product">
                    <view v-for="(item, idx) in order.prolist" :key="idx" class="content">
                        <view>
                            <image :src="item.pic"></image>
                        </view>
                        <view class="detail">
                            <text class="t1">{{item.name}}</text>
                            <text class="t2" v-if="item.ggname">{{item.ggname}}</text>
														<view class="t3" v-if="type == 'scoreshop'"><text class="x1 flex1"><text v-if="item.money_price>0">￥{{item.money_price}}+</text>{{item.score_price}}{{t('积分')}}</text><text class="x2">×{{item.num}}</text></view>
                            <view class="t3" v-else><text class="x1 flex1">￥{{item.sell_price}}</text><text class="x2" v-if="type !='cycle'">×{{item.num}}</text></view>
                        </view>
                    </view>
                </view>
                
                <view class="orderinfo" v-if="(order.status==3 || order.status==2) && (order.freight_type==3 || order.freight_type==4)">
                    <view class="item flex-col">
                        <text class="t1" style="color:#111">发货信息</text>
                        <text class="t2" style="text-align:left;margin-top:10rpx;padding:0 10rpx" user-select="true" selectable="true">{{order.freight_content}}</text>
                    </view>
                </view>
                
                <view class="orderinfo">
                    <view class="item">
                        <text class="t1">下单人</text>
                        <text class="flex1"></text>
                        <image :src="order.headimg" style="width:80rpx;height:80rpx;margin-right:8rpx"/>
                        <text  style="height:80rpx;line-height:80rpx">{{order.nickname}}</text>
                    </view>
                    <view class="item">
                        <text class="t1">{{t('会员')}}ID</text>
                        <text class="t2">{{order.mid}}</text>
                    </view>
                </view>
                <view class="orderinfo" v-if="order.remark">
                    <view class="item">
                        <text class="t1">备注</text>
                        <text class="t2">{{order.remark}}</text>
                    </view>
                </view>
                <view class="orderinfo">
                    <view class="item">
                        <text class="t1">订单编号</text>
                        <text class="t2" user-select="true" selectable="true">{{order.ordernum}}</text>
                    </view>
                    <view class="item">
                        <text class="t1">下单时间</text>
                        <text class="t2">{{order.createtime}}</text>
                    </view>
                    <view class="item" v-if="order.status>0 && order.paytypeid!='4' && order.paytime">
                        <text class="t1">支付时间</text>
                        <text class="t2">{{order.paytime}}</text>
                    </view>
                    <view class="item" v-if="order.status>0 && order.paytime">
                        <text class="t1">支付方式</text>
                        <text class="t2">{{order.paytype}}</text>
                    </view>
                    <view class="item" v-if="order.status>1 && order.send_time">
                        <text class="t1">发货时间</text>
                        <text class="t2">{{dateFormat(order.send_time)}}</text>
                    </view>
                    <view class="item" v-if="order.status==3 && order.collect_time">
                        <text class="t1">收货时间</text>
                        <text class="t2">{{order.collect_time}}</text>
                    </view>
                </view>
                <view class="orderinfo">
                    <view class="item" v-if="type=='cycle'">
                        <text class="t1">总配送期数</text>
                        <text class="t2 red">共{{order.qsnum}}期</text>
                    </view>
                    <view class="item" v-if="type=='cycle'">
                        <text class="t1">当前配送期数</text>
                        <text class="t2 red">第{{order.stage.cycle_number}}期</text>
                    </view>
                    <view class="item" v-if="type=='cycle'">
                        <text class="t1">每期数量</text>
                        <text class="t2 red">共{{order.stage.num}}件</text>
                    </view>
                    <view class="item" v-if="type =='scoreshop'">
                        <text class="t1">商品金额</text>
                        <text class="t2 red" v-if="order.totalmoney">¥{{order.totalmoney}} + {{order.totalscore}}{{t('积分')}}</text>
                        <text class="t2 red" v-else>{{order.totalscore}}{{t('积分')}}</text>
                    </view>
										<view class="item" v-else>
										    <text class="t1">商品金额</text>
										    <text class="t2 red">¥{{order.product_price}}</text>
										</view>
                    <view class="item" v-if="order.disprice > 0">
                        <text class="t1">{{t('会员')}}折扣</text>
                        <text class="t2 red">-¥{{order.leveldk_money}}</text>
                    </view>
                    <view class="item" v-if="order.jianmoney > 0">
                        <text class="t1">满减活动</text>
                        <text class="t2 red">-¥{{order.manjian_money}}</text>
                    </view>
                    <view class="item">
                        <text class="t1">配送方式</text>
                        <text class="t2">{{order.freight_text}}</text>
                    </view>
                    <view class="item" v-if="order.freight_type==1 && order.freightprice > 0">
                        <text class="t1">服务费</text>
                        <text class="t2 red">+¥{{order.freight_price}}</text>
                    </view>
                    <view class="item" v-if="order.freight_time">
                        <text class="t1">{{order.freight_type!=1?'配送':'提货'}}时间</text>
                        <text class="t2">{{order.freight_time}}</text>
                    </view>
                    <view class="item" v-if="order.couponmoney > 0">
                        <text class="t1">{{t('优惠券')}}抵扣</text>
                        <text class="t2 red">-¥{{order.coupon_money}}</text>
                    </view>
                    
                    <view class="item" v-if="order.scoredk > 0">
                        <text class="t1">{{t('积分')}}抵扣</text>
                        <text class="t2 red">-¥{{order.scoredk_money}}</text>
                    </view>
										<view class="item" v-if="type =='scoreshop'">
											<text class="t1">实付款</text>
											<text class="t2 red">¥{{order.totalprice}} + {{order.totalscore}}{{t('积分')}}</text>
										</view>
                    <view class="item" v-else>
                        <text class="t1">实付款</text>
                        <text class="t2 red">¥{{order.totalprice}}</text>
                    </view>

                    <view class="item">
                        <text class="t1">订单状态</text>
                        <text class="t2" v-if="order.status==0">未付款</text>
                        <text class="t2" v-if="order.status==1">已付款</text>
                        <text class="t2" v-if="order.status==2">已发货</text>
                        <text class="t2" v-if="order.status==3">已收货</text>
                        <text class="t2" v-if="order.status==4">已关闭</text>
												<text class="t2" v-if="order.status==8">待提货</text>
                    </view>
                    <view class="item" v-if="order.refund_status>0">
                        <text class="t1">退款状态</text>
                        <text class="t2 red" v-if="order.refund_status==1">审核中,¥{{order.refund_money}}</text>
                        <text class="t2 red" v-if="order.refund_status==2">已退款,¥{{order.refund_money}}</text>
                        <text class="t2 red" v-if="order.refund_status==3">已驳回,¥{{order.refund_money}}</text>
                    </view>
                    <view class="item" v-if="order.refund_status>0">
                        <text class="t1">退款原因</text>
                        <text class="t2 red">{{order.refund_reason}}</text>
                    </view>
                    <view class="item" v-if="order.refund_checkremark">
                        <text class="t1">审核备注</text>
                        <text class="t2 red">{{order.refund_checkremark}}</text>
                    </view>

                    <view class="item">
                        <text class="t1">备注</text>
                        <text class="t2 red">{{order.message ? order.message : '无'}}</text>
                    </view>
                    <view class="item" v-if="order.field1">
                        <text class="t1">{{order.field1data[0]}}</text>
                        <text class="t2 red">{{order.field1data[1]}}</text>
                    </view>
                    <view class="item" v-if="order.field2">
                        <text class="t1">{{order.field2data[0]}}</text>
                        <text class="t2 red">{{order.field2data[1]}}</text>
                    </view>
                    <view class="item" v-if="order.field3">
                        <text class="t1">{{order.field3data[0]}}</text>
                        <text class="t2 red">{{order.field3data[1]}}</text>
                    </view>
                    <view class="item" v-if="order.field4">
                        <text class="t1">{{order.field4data[0]}}</text>
                        <text class="t2 red">{{order.field4data[1]}}</text>
                    </view>
                    <view class="item" v-if="order.field5">
                        <text class="t1">{{order.field5data[0]}}</text>
                        <text class="t2 red">{{order.field5data[1]}}</text>
                    </view>
                </view>
            </block>
            <block v-if="type=='coupon'">
                <view class="couponbg" :style="{background:'linear-gradient(90deg,'+t('color1')+' 0%,rgba('+t('color1rgb')+',0.8) 100%)'}"></view>
                <view class="orderinfo">
                    <view class="topitem">
                        <view class="f1" :style="{color:t('color1')}" v-if="order.type==1"><text style="font-size:32rpx">￥</text><text class="t1">{{order.money}}</text></view>
                        <view class="f1" :style="{color:t('color1')}" v-else-if="order.type==2">礼品券</view>
                        <view class="f1" :style="{color:t('color1')}" v-else-if="order.type==3"><text class="t1">{{order.limit_count}}</text><text class="t2">次</text></view>
                        <view class="f1" :style="{color:t('color1')}" v-else-if="order.type==4">抵运费</view>
                        <view class="f2">
                            <view class="t1">{{order.couponname}}</view>
                            <view class="t2" v-if="order.type==1 || order.type==4">
                                <text v-if="order.minprice>0">满{{order.minprice}}元可用</text>
                                <text v-else>无门槛</text>
                            </view>
                            <view class="t2" v-if="order.type==2">礼品券</view>
                            <view class="t2" v-if="order.type==3">计次券</view>
                        </view>
                    </view>
                    <view class="item">
                        <text class="t1">类型</text>
                        <text class="t2" v-if="order.type==1">代金券</text>
                        <text class="t2" v-if="order.type==2">礼品券</text>
                        <text class="t2" v-if="order.type==3">计次券</text>
                        <text class="t2" v-if="order.type==4">运费抵扣券</text>
                    </view>
                    <block v-if="order.type==3">
											<view class="item">
													<text class="t1">共计次数</text>
													<text class="t2">{{order.limit_count}}</text>
											</view>
											<view class="item">
													<text class="t1">已使用次数</text>
													<text class="t2">{{order.used_count}}</text>
											</view>
											<block v-if="order.limit_perday>0">
												<view class="item">
														<text class="t1">每天限制次数</text>
														<text class="t2">{{order.limit_perday}}</text>
												</view>
											</block>
                    </block>
                    <view class="item">
                        <text class="t1">领取时间</text>
                        <text class="t2">{{order.createtime}}</text>
                    </view>
                    <block v-if="order.status==1">
                    <view class="item">
                        <text class="t1">使用时间</text>
                        <text class="t2">{{order.usetime}}</text>
                    </view>
                    </block>
                    
                    <view class="item flex-col">
                        <text class="t1">有效期</text>
                        <text class="t2">{{order.starttime}} 至 {{order.endtime}}</text>
                    </view>
                    <view class="item flex-col">
                        <text class="t1">使用说明</text>
                        <view class="t2 textarea">{{order.usetips}}</view>
                    </view>
                </view>
            </block>
            <block v-if="type=='choujiang'">
                <view style="padding:15px 0 15px 0;">
                    <view style="text-align: center;font-size:20px;color: #3cc51f;font-weight: 400;margin: 0 15%;">核销信息</view>
                </view>
                <view class="orderinfo">
                    <view class="item">
                        <view class="t1">核销类型</view>
                        <view class="t2" style="font-size:32rpx">抽奖奖品</view>
                    </view>
                    <view class="item">
                        <view class="t1">活动名称</view>
                        <view class="t2">{{order.name}}</view>
                    </view>
                    <view class="item">
                        <view class="t1">奖品名称</view>
                        <view class="t2" style="font-size:16px;color:#000">{{order.jxmc}}</view>
                    </view>
                    <view class="item">
                        <view class="t1">中奖时间</view>
                        <view class="t2">{{dateFormat(order.createtime,'Y-m-d H:i')}}</view>
                    </view>
                    <block v-if="order.formdata">
                        <view class="item" v-for="(item, index) in order.formdata" :key="index">
                            <text class="t1">{{index}}</text>
                            <text class="t2" :user-select="true" :selectable="true">{{item}}</text>
                        </view>
                    </block>
                </view>
            </block>
            <block v-if="type=='hbtk'">
                <view class="product">
									<view class="content">
										<view >
											<image :src="order.pic"></image>
										</view>
										<view class="detail">
											<text class="t1">{{order.name}}</text>	   
											<view class="t3"><text class="x1 flex1">￥{{order.price}}</text></view>
											<view class="t2"><text>已邀请{{order.yqnum}}人</text></view>
										</view>
									</view>
                </view>
                
                <view class="orderinfo">
                    <view class="item">
                        <text class="t1 flex-y-center">下单人</text>
                        <text class="flex1"></text>
                        <image :src="order.headimg" style="width:80rpx;height:80rpx;margin-right:8rpx"/>
                        <text  style="height:80rpx;line-height:80rpx">{{order.nickname}}</text>
                    </view>
                </view>
             <view class="orderinfo" v-if="order.yqlist">
              <view class="item flex-y-center" style="padding: 10rpx 0;">
                <text class="t1">邀请人员</text>
                <view class="t2" user-select="true" selectable="true" style="overflow: hidden;">
                  <block v-for="(item,index) in order.yqlist">
                    <image class="yq_image" :src="item.headimg"/>
                  </block>
                </view>
              </view>
             </view>
                <view class="orderinfo">
                    <view class="item">
                        <text class="t1">订单编号</text>
                        <text class="t2" user-select="true" selectable="true">{{order.ordernum}}</text>
                    </view>
                    <view class="item">
                        <text class="t1">下单时间</text>
                        <text class="t2">{{order.createtime}}</text>
                    </view>
                    <view class="item" v-if="order.status > 0 && order.price > 0 && order.paytime">
                        <text class="t1">支付时间</text>
                        <text class="t2">{{order.paytime}}</text>
                    </view>
                    <view class="item" v-if="order.status >=1 && order.price > 0">
                        <text class="t1">支付方式</text>
                        <text class="t2">{{order.paytype}}</text>
                    </view>
                </view>
                <view class="orderinfo">
                    <view class="item" v-if=" order.price > 0">
                        <text class="t1">支付金额</text>
                        <text class="t2 red">¥{{order.price}}</text>
                    </view>
            
                    <view class="item">
                        <text class="t1">订单状态</text>
                        <text class="t2" v-if="order.status==1">待核销</text>
                        <text class="t2" v-if="order.status==2">已核销</text>
                    </view>
                </view>
            </block>
            <block v-if="type=='shopproduct' || type=='takeaway_order_product'">
              <view class="product">
                  <view class="content">
                      <view>
                          <image :src="order.ogdata.pic"></image>
                      </view>
                      <view class="detail">
                          <text class="t1">{{order.ogdata.name}}</text>
                          <text class="t2">{{order.ogdata.ggname}}</text>
                          <view class="t3"><text class="x1 flex1">￥{{order.ogdata.sell_price}}</text><text class="x2">×{{order.ogdata.num}}</text></view>
                          <view class="t3" v-if="order.ogdata.refund_num && order.ogdata.refund_num>0"><text class="x1 flex1"></text><text >已退：{{order.ogdata.refund_num}}件</text></view>
                      </view>
                  </view>
              </view>
              <view class="orderinfo">
                  <view class="item">
                      <text class="t1">订单编号</text>
                      <text class="t2" user-select="true" selectable="true">{{order.ordernum}}</text>
                  </view>
                  <view class="item">
                      <text class="t1">下单时间</text>
                      <text class="t2">{{order.createtime}}</text>
                  </view>
                  <view class="item" v-if="order.status>0 && order.paytypeid!='4' && order.paytime">
                      <text class="t1">支付时间</text>
                      <text class="t2">{{order.paytime}}</text>
                  </view>
                  <view class="item" v-if="order.status>0 && order.paytime">
                      <text class="t1">支付方式</text>
                      <text class="t2">{{order.paytype}}</text>
                  </view>
                  <view class="item" v-if="type=='shopproduct'">
                      <text class="t1">已核销数</text>
                      <text class="t2">{{order.ogdata.hexiao_num}}</text>
                  </view>
              </view>
              <view class="orderinfo" v-if="type=='shopproduct'">
                  <view class="item">
                      <text class="t1">本次核销数</text>
                      <text class="t2" style="color:#223;font-weight:bold;font-size:32rpx">{{order.hxnum}}</text>
                  </view>
              </view>
              <view class="orderinfo" v-if="type=='takeaway_order_product'">
                  <view class="item">
                      <text class="t1">本次核销数</text>
                      <text class="t2" style="color:#223;font-weight:bold;font-size:32rpx">{{order.now_hxnum}}</text>
                  </view>
              </view>
						</block>
            <block v-if="type=='gift_bag' || type=='gift_bag_goods'">
                <view v-if="type=='gift_bag'" class="product" >
                    <view v-for="(item, idx) in order.prolist" :key="idx" class="content">
                        <view>
                            <image :src="item.pic"></image>
                        </view>
                        <view class="detail">
                            <text class="t1">{{item.name}}</text>
                            <view class="t3"><text class="x1 flex1">￥{{item.sell_price}}</text><text class="x2" v-if="type !='cycle'">×{{item.num}}</text></view>
                        </view>
                    </view>
                </view>
                <view v-if="type=='gift_bag_goods'" class="product">
                    <view class="content">
                        <view>
                            <image :src="order.ogdata.pic"></image>
                        </view>
                        <view class="detail">
                            <text class="t1">{{order.ogdata.name}}</text>
                            <view class="t3"><text class="x1 flex1">￥{{order.ogdata.sell_price}}</text><text class="x2">×{{order.ogdata.num}}</text></view>
                            <view class="t3" v-if="order.ogdata.refund_num && order.ogdata.refund_num>0"><text class="x1 flex1"></text><text >已退：{{order.ogdata.refund_num}}件</text></view>
                        </view>
                    </view>
                </view>
                <view class="orderinfo">
                    <view class="item">
                        <text class="t1">订单编号</text>
                        <text class="t2" user-select="true" selectable="true">{{order.ordernum}}</text>
                    </view>
                    <view class="item">
                        <text class="t1">下单时间</text>
                        <text class="t2">{{order.createtime}}</text>
                    </view>
                    <view class="item" v-if="order.status>0 && order.paytypeid!='4' && order.paytime">
                        <text class="t1">支付时间</text>
                        <text class="t2">{{order.paytime}}</text>
                    </view>
                    <view class="item" v-if="order.status>0 && order.paytime">
                        <text class="t1">支付方式</text>
                        <text class="t2">{{order.paytype}}</text>
                    </view>
                    <view class="item" v-if="type=='gift_bag_goods'">
                        <text class="t1">已核销数</text>
                        <text class="t2">{{order.ogdata.hexiao_num}}</text>
                    </view>
                    <view class="item" v-if="type=='gift_bag'">
                        <text class="t1">实付款</text>
                        <text class="t2 red">¥{{order.totalprice}}</text>
                    </view>
                    <block  v-if="type=='gift_bag'">
                      <view class="item" v-if="order.refund_status>0">
                          <text class="t1">退款状态</text>
                          <text class="t2 red" v-if="order.refund_status==1">审核中,¥{{order.refund_money}}</text>
                          <text class="t2 red" v-if="order.refund_status==2">已退款,¥{{order.refund_money}}</text>
                          <text class="t2 red" v-if="order.refund_status==3">已驳回,¥{{order.refund_money}}</text>
                      </view>
                      <view class="item" v-if="order.refund_status>0">
                          <text class="t1">退款原因</text>
                          <text class="t2 red">{{order.refund_reason}}</text>
                      </view>
                      <view class="item" v-if="order.refund_checkremark">
                          <text class="t1">审核备注</text>
                          <text class="t2 red">{{order.refund_checkremark}}</text>
                      </view>
                    </block>
                </view>
                
                <view class="orderinfo" v-if="type=='gift_bag_goods'">
                    <view class="item">
                        <text class="t1">本次核销数</text>
                        <text class="t2" style="color:#223;font-weight:bold;font-size:32rpx">{{order.hxnum}}</text>
                    </view>
                </view>
            </block>
            <view style="height:140rpx"></view>
            <view class="btn-add" :style="{background:t('color1')}" @tap="hexiao">立即核销</view>
        </block>
        <block v-else>
            <view style="height:140rpx"></view>
            <view class="btn-add" :style="{background:t('color1')}" @tap="saoyisao">继续核销</view>
        </block>
    </block>
</view>
</template>

<script>
var app = getApp();
export default {
  data() {
    return {
        opt:{},
        loading:false,
        isload: false,
        
        hxnum:'',
        co:'',
        type:'',
        order:{},
        nodata: false,
        nomore: false,
        hexiao_status:false,
        hexiao_type:0,
        pre_url: app.globalData.pre_url,
    };
  },
  
    onLoad: function (opt) {
        this.opt   = app.getopts(opt);
        this.hxnum = this.opt.hxnum?this.opt.hxnum:'';
        this.co    = this.opt.co? this.opt.co:'';
        this.type  = this.opt.type;
        this.getdata();
    },
		onPullDownRefresh: function () {
			this.getdata();
		},
    methods: {
			getdata: function () {
					var that = this;
					that.loading = true;
					var hxnum    = that.hxnum;
					var co       = that.co;
					app.post('ApiMendianCenter/hexiao',{type:that.type,co:co,hxnum:hxnum}, function (res) {
						that.loading = false;
						if(res.status == 0){
								app.alert(res.msg,function(){
										app.goto('/pagesA/mendiancenter/my','reLaunch');	
								});
								return;
						}
						if(res.status == 2){
								app.confirm(res.msg,function(){
										app.goto('/pagesA/mendianup/apply','reLaunch');	
								});
								return;
						}
						that.order = res.order
						if(res.hexiao_type){
								that.hexiao_type = res.hexiao_type;
						}
						that.loaded(); 
					});
			},
			hexiao:function(){
				var that = this;
				var hxnum    = that.hxnum;
				var co       = that.co;
				app.confirm('确定要核销吗？',function(){
					app.showLoading('核销中');
					app.post('ApiMendianCenter/hexiao',{op:'confirm',type:that.type,co:co,hxnum:hxnum}, function (res) {
						app.showLoading(false);
						if(res.status == 0){
							app.alert(res.msg);return;
						}
						if(that.hexiao_type == 1){
								app.success('核销成功');
								that.hexiao_status = true;
						}else{
								app.alert(res.msg,function(){
									app.goto('/pagesA/mendiancenter/my','reLaunch');	
								});
						}
					})
				})
			},
        saoyisao: function (d) {
            var that = this;
            if(app.globalData.platform == 'h5'){
                app.alert('请使用微信扫一扫功能扫码核销');return;
            }else if(app.globalData.platform == 'mp'){
                var jweixin = require('jweixin-module');
                jweixin.ready(function () {   //需在用户可能点击分享按钮前就先调用
                    jweixin.scanQRCode({
                        needResult: 1, // 默认为0，扫描结果由微信处理，1则直接返回扫描结果，
                        scanType: ["qrCode","barCode"], // 可以指定扫二维码还是一维码，默认二者都有
                        success: function (res) {
                            var content = res.resultStr; // 当needResult 为 1 时，扫码返回的结果
                            var params = content.split('?')[1];
                            if(params){
															var params1 = params.split('&');

															if(params1){
																	if(params1[0]){
																			var params_type = params1[0].split('=')[0];
																			if(params_type){
																					if(params_type == 'type'){
																							var type = params1[0].split('=')[1];
																					}
																					if(params_type == 'co'){
																							var co   = params1[0].split('=')[1];
																					}
																					if(params_type == 'hxnum'){
																							var hxnum   = params1[0].split('=')[1];
																					}
																			}
																	}
																	if(params1[1]){
																			var params_type = params1[1].split('=')[0];
																			if(params_type){
																					if(params_type == 'type'){
																							var type = params1[1].split('=')[1];
																					}
																					if(params_type == 'co'){
																							var co   = params1[1].split('=')[1];
																					}
																					if(params_type == 'hxnum'){
																							var hxnum   = params1[1].split('=')[1];
																					}
																			}
																	}
																	if(params1[2]){
																			var params_type = params1[2].split('=')[0];
																			if(params_type){
																					if(params_type == 'type'){
																							var type = params1[2].split('=')[1];
																					}
																					if(params_type == 'co'){
																							var co   = params1[2].split('=')[1];
																					}
																					if(params_type == 'hxnum'){
																							var hxnum   = params1[2].split('=')[1];
																					}
																			}
																	}
																	
																	
																	if(type&&co){
																			that.type = type;
																			that.co   = co;
																			if(hxnum){
																					that.hxnum = hxnum;
																			}
																			that.hexiao_status = false;
																			that.getdata();
																	}else{
																			app.alert('识别错误');
																	}
															}else{
																	app.alert('识别错误');
															}
															
													}else{
															app.alert('识别错误');
													}
                        }
                    });
                });
            }else{
                uni.scanCode({
                    success: function (res) {
                        console.log(res);
                        var content = res.result;
                        var params = content.split('?')[1];
                        
                        if(params){
                            var params1 = params.split('&');
                        
                            if(params1){
                                if(params1[0]){
                                    var params_type = params1[0].split('=')[0];
                                    if(params_type){
																			if(params_type == 'type'){
																					var type = params1[0].split('=')[1];
																			}
																			if(params_type == 'co'){
																					var co   = params1[0].split('=')[1];
																			}
																			if(params_type == 'hxnum'){
																					var hxnum   = params1[0].split('=')[1];
																			}
                                    }
                                }
                                
                                if(params1[1]){
                                    var params_type = params1[1].split('=')[0];
                                    if(params_type){
																			if(params_type == 'type'){
																					var type = params1[1].split('=')[1];
																			}
																			if(params_type == 'co'){
																					var co   = params1[1].split('=')[1];
																			}
																			if(params_type == 'hxnum'){
																					var hxnum   = params1[1].split('=')[1];
																			}
                                    }
                                }
                                
                                if(params1[2]){
                                    var params_type = params1[2].split('=')[0];
                                    if(params_type){
																			if(params_type == 'type'){
																					var type = params1[2].split('=')[1];
																			}
																			if(params_type == 'co'){
																					var co   = params1[2].split('=')[1];
																			}
																			if(params_type == 'hxnum'){
																					var hxnum   = params1[2].split('=')[1];
																			}
                                    }
                                }
                                
                                if(type&&co){
                                    that.type = type;
                                    that.co   = co;
                                    if(hxnum){
                                        that.hxnum = hxnum;
                                    }
                                    that.hexiao_status = false;
                                    that.getdata();
                                }else{
                                    app.alert('识别错误');
                                }
                            }else{
                                app.alert('识别错误');
                            }
                            
                        }else{
                            app.alert('识别错误');
                        }
                    }
                });
            }
        },
  }
};
</script>
<style>
.address{ display:flex;width:94%;margin:0 3%;border-radius:12rpx;padding: 20rpx 3%; background: #FFF;margin-top:20rpx;}
.address .img{width:40rpx}
.address image{width:40rpx; height:40rpx;}
.address .info{flex:1;display:flex;flex-direction:column;}
.address .info .t1{font-size:28rpx;font-weight:bold;color:#333}
.address .info .t2{font-size:24rpx;color:#999}

.product{width:94%;margin:0 3%;border-radius:12rpx;padding: 14rpx 3%;background: #FFF;margin-top:20rpx;}
.product .content{display:flex;position:relative;width: 100%; padding:16rpx 0px;border-bottom: 1px #e5e5e5 dashed;}
.product .content:last-child{ border-bottom: 0; }
.product .content image{ width: 140rpx; height: 140rpx;}
.product .content .detail{display:flex;flex-direction:column;margin-left:14rpx;flex:1}
.product .content .detail .t1{font-size:26rpx;line-height:36rpx;margin-bottom:10rpx;display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow:hidden;}
.product .content .detail .t2{height: 46rpx;line-height: 46rpx;color: #999;overflow: hidden;font-size: 26rpx;}
.product .content .detail .t3{display:flex;height:40rpx;line-height:40rpx;color: #ff4246;}
.product .content .detail .x1{ flex:1}
.product .content .detail .x2{ width:100rpx;font-size:32rpx;text-align:right;margin-right:8rpx}
.product .content .comment{position:absolute;top:64rpx;right:10rpx;border: 1px #ffc702 solid; border-radius:10rpx;background:#fff; color: #ffc702;  padding: 0 10rpx; height: 46rpx; line-height: 46rpx;}
.product .content .comment2{position:absolute;top:64rpx;right:10rpx;border: 1px #ffc7c2 solid; border-radius:10rpx;background:#fff; color: #ffc7c2;  padding: 0 10rpx; height: 46rpx; line-height: 46rpx;}

.orderinfo{ width:94%;margin:0 3%;border-radius:12rpx;margin-top:10rpx;padding: 14rpx 3%;background: #FFF;}
.orderinfo .item{display:flex;width:100%;padding:20rpx 0;border-bottom:1px dashed #ededed;}
.orderinfo .item:last-child{ border-bottom: 0;}
.orderinfo .item .t1{width:200rpx;}
.orderinfo .item .t2{flex:1;text-align:right;}
.orderinfo .item .textarea { text-align: left;white-space:pre-wrap;padding: 20rpx;}
.orderinfo .item .red{color:red}
.orderinfo .topitem{display:flex;padding:60rpx 40rpx;align-items:center;border-bottom:2px dashed #E5E5E5;position:relative}
.orderinfo .topitem .f1{font-size:50rpx;font-weight:bold;}
.orderinfo .topitem .f1 .t1{font-size:60rpx;}
.orderinfo .topitem .f1 .t2{font-size:40rpx;}
.orderinfo .topitem .f2{margin-left:40rpx}
.orderinfo .topitem .f2 .t1{font-size:36rpx;color:#2B2B2B;font-weight:bold;height:50rpx;line-height:50rpx}
.orderinfo .topitem .f2 .t2{font-size:24rpx;color:#999999;height:50rpx;line-height:50rpx;}

.btn-add{width:90%;max-width:700px;margin:0 auto;height:96rpx;line-height:96rpx;text-align:center;color:#fff;font-size:30rpx;font-weight:bold;border-radius:40rpx;position: fixed;left:0px;right:0;bottom:20rpx;}
.yq_image{height: 60rpx;width: 60rpx;border-radius: 100rpx;margin-right: 10rpx;}

</style>