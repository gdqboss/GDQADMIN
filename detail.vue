<template>
<view>
	<block v-if="isload">
		<view class="orderinfo">
			<view class="item">
				<text class="t1">ID</text>
				<text class="t2">{{member.id}}</text>
			</view>
			<view class="item">
				<text class="t1">头像</text>
				<view class="t2"><image :src="member.headimg" style="width:80rpx;height:80rpx"></image></view>
			</view>
			<view class="item">
				<text class="t1">昵称</text>
				<text class="t2">{{member.nickname}}</text>
			</view>
            <view class="item">
                <text class="t1">上级团长</text>
                <text class="t2">{{ member.pid && member.pid*1 > 0 ? ('ID：'+member.pid) : '无' }}</text>
            </view>
			<view class="item">
				<text class="t1">加入时间</text>
				<text class="t2">{{member.createtime}}</text>
			</view>
			<view class="item">
				<text class="t1">姓名</text>
				<text class="t2">{{member.realname ? member.realname : '' }}</text>
			</view>
			<view class="item">
				<text class="t1">电话</text>
				<text class="t2">{{member.tel ? member.tel : '' }}</text>
			</view>
			<view class="item">
				<text class="t1">{{t('余额')}}</text>
				<text class="t2">{{member.money}}</text>
			</view>
			<view class="item">
				<text class="t1">{{t('积分')}}</text>
				<text class="t2">{{member.score}}</text>
			</view>
			<view class="item">
				<text class="t1">等级</text>
				<text class="t2">{{member.levelname}}</text>
			</view>
			<view class="item">
				<text class="t1">身份</text>
				<text class="t2">{{member.identity || member.identity_name || member.role || ''}}</text>
			</view>
			<view class="item">
				<text class="t1">关联供货商ID</text>
				<text class="t2">{{member.supplier_id || member.supplierid || member.SupplierID || member.supplier || ''}}</text>
			</view>

			<view class="item" v-if="member.remark">
				<text class="t1">备注</text>
				<text class="t2">{{member.remark}}</text>
			</view>
      <view class="item" v-if="member.mendian_member_levelup_fenhong">
        <text class="t1">门店</text>
        <text class="t2">{{member.mdname?member.mdname:'无'}}</text>
      </view>
			<view class="item" v-if="ordershow" style="justify-content: space-between;">
				<text class="t1" style="color: #007aff;">商城订单</text>
				<view class="flex" @tap="goto" :data-url="'/admin/order/shoporder?mid='+member.id" >{{member.ordercount}}	<text class="iconfont iconjiantou" style="color:#999;font-weight:normal; margin-top: 2rpx;"></text></view>
			</view>	
		</view>
		<view style="width:100%;height:120rpx"></view>
            <view class="bottom">
            <view class="btn" @tap="recharge" :data-id="member.id">充值</view>
            <view class="btn" @tap="changelv" :data-id="member.id">修改等级</view>
            <view class="btn" @tap="openRelDialog">修改上下级关系</view>
            <view class="btn" @tap="openIdentityDialog">修改身份</view>
            <view class="btn" @tap="openSupplierDialog">修改关联供货商ID</view>
        </view>
		
		<uni-popup id="rechargeDialog" ref="rechargeDialog" type="dialog">
			<uni-popup-dialog mode="input" title="充值" value="" placeholder="请输入充值金额" @confirm="rechargeConfirm"></uni-popup-dialog>
		</uni-popup>
    <uni-popup id="consumeDialog" ref="consumeDialog" type="dialog">
    	<uni-popup-dialog mode="input" title="消费" value="" placeholder="请输入消费金额" @confirm="consumeConfirm"></uni-popup-dialog>
    </uni-popup>
    
		<uni-popup id="addscoreDialog" ref="addscoreDialog" type="dialog">
			<uni-popup-dialog mode="input" :title="'加'+t('积分')" value="" :placeholder="'请输入增加'+t('积分')+'数'" @confirm="addscoreConfirm"></uni-popup-dialog>
		</uni-popup>
		<uni-popup id="remarkDialog" ref="remarkDialog" type="dialog">
			<uni-popup-dialog mode="input" title="设置备注" value="" placeholder="请输入备注" @confirm="remarkConfirm"></uni-popup-dialog>
		</uni-popup>

		<uni-popup id="relDialog" ref="relDialog" type="dialog">
			<uni-popup-dialog mode="input" title="修改上下级关系" value="" placeholder="请输入新的上级ID" @confirm="submitRel"></uni-popup-dialog>
		</uni-popup>
		
		<uni-popup id="dialogChangeIdentity" ref="dialogChangeIdentity" type="dialog">
			<view class="uni-popup-dialog">
				<view class="uni-dialog-title">
					<text class="uni-dialog-title-text">请选择身份</text>
				</view>
				<view class="uni-dialog-content">
						<picker @change="identityChange" :value="index3" :range="identityList" style="width:100%;font-size:28rpx;border: 1px #eee solid;padding:10rpx;height:70rpx;border-radius:4px;flex:1">
							<view class="picker">{{identityList[index3]}}</view>
						</picker>
				</view>
				<view class="uni-dialog-button-group">
					<view class="uni-dialog-button" @click="dialogChangeIdentityClose">
						<text class="uni-dialog-button-text">取消</text>
					</view>
					<view class="uni-dialog-button uni-border-left" @click="confirmChangeIdentity">
						<text class="uni-dialog-button-text uni-button-color">确定</text>
					</view>
				</view>
			</view>
		</uni-popup>
		
		<uni-popup id="dialogChangeSupplier" ref="dialogChangeSupplier" type="dialog">
			<view class="uni-popup-dialog">
				<view class="uni-dialog-title">
					<text class="uni-dialog-title-text">请选择关联供货商</text>
				</view>
				<view class="uni-dialog-content">
						<picker mode="selector" :range="suppliers" :range-key="'name'" :value="supplierIndex" @change="supplierChange" style="width:100%;font-size:28rpx;border: 1px #eee solid;padding:10rpx;height:70rpx;border-radius:4px;flex:1">
							<view class="picker">{{getSupplierName()}}</view>
						</picker>
				</view>
				<view class="uni-dialog-button-group">
					<view class="uni-dialog-button" @click="dialogChangeSupplierClose">
						<text class="uni-dialog-button-text">取消</text>
					</view>
					<view class="uni-dialog-button uni-border-left" @click="confirmChangeSupplier">
						<text class="uni-dialog-button-text uni-button-color">确定</text>
					</view>
				</view>
			</view>
		</uni-popup>

		
		<uni-popup id="dialogChangelv" ref="dialogChangelv" type="dialog">
			<view class="uni-popup-dialog">
				<view class="uni-dialog-title">
					<text class="uni-dialog-title-text">请选择等级</text>
				</view>
				<view class="uni-dialog-content">
						<picker @change="levelChange" :value="index2" :range="levelList2" style="width:100%;font-size:28rpx;border: 1px #eee solid;padding:10rpx;height:70rpx;border-radius:4px;flex:1">
							<view class="picker">{{levelList2[index2]}}</view>
						</picker>
				</view>
				<view class="uni-dialog-button-group">
					<view class="uni-dialog-button" @click="dialogChangelvClose">
						<text class="uni-dialog-button-text">取消</text>
					</view>
					<view class="uni-dialog-button uni-border-left" @click="confirmChangelv">
						<text class="uni-dialog-button-text uni-button-color">确定</text>
					</view>
				</view>
			</view>
		</uni-popup>

	</block>
	<popmsg ref="popmsg"></popmsg>
	<loading v-if="loading"></loading>
</view>
</template>

<script>
var app = getApp();

export default {
  data() {
    return {
      isload: false,
      member: "",
		index2:0,
		levelList:[],
		levelList2:[],
		ordershow:false,
		index3:0,
		identityList: ['店主', '顾客', '公司员工', '供货商', '店员', '仓管'],
		suppliers: [], // 供货商列表
		supplierIndex: 0, // 当前选择的供货商索引
		selectedSupplierId: 0 // 当前选择的供货商ID
    };
  },
  onLoad: function (opt) {
		this.opt = app.getopts(opt);
		this.getdata();
  },
	onPullDownRefresh: function () {
		this.getdata();
	},
  methods: {
		getdata:function(){
			var that = this;
			that.loading = true;
			app.post('ApiAdminMember/detail', {mid: that.opt.mid}, function (res) {
				that.loading = false;
				that.member = res.member;
				that.ordershow = res.ordershow
				uni.setNavigationBarTitle({
					title: that.t('会员') + '信息'
				});

				var levelList2 = [];
				for(var i in res.levelList){
					levelList2.push(res.levelList[i].name);
				}
				that.levelList = res.levelList;
				that.levelList2 = levelList2;

				that.loaded();
			});
		},
		getParentId:function(){
			var m = this.member || {};
			return (m.pid && m.pid*1 > 0) ? m.pid : '无';
		},
		getParentName:function(){
			var m = this.member || {};
			return (m.pnickname && (m.pnickname+'').trim()!=='') ? m.pnickname : '无';
		},
    recharge: function (e) {
      this.$refs.rechargeDialog.open();
    },
    rechargeConfirm: function (done,value) {
			this.$refs.rechargeDialog.close();
      var that = this;
      app.post('ApiAdminMember/recharge', {rechargemid:that.opt.mid,rechargemoney:value}, function (data) {
				if (data.status == 0) {
				  app.error(data.msg);
				  return;
				}
        app.success(data.msg);
        setTimeout(function () {
          that.getdata();
        }, 1000);
      });
    },
    consume: function (e) {
      this.$refs.consumeDialog.open();
    },
    consumeConfirm: function (done,value) {
    	this.$refs.consumeDialog.close();
      var that = this;
      app.post('ApiAdminMember/recharge', {rechargemid:that.opt.mid,rechargemoney:-value,type:'consume'}, function (data) {
    		if (data.status == 0) {
    		  app.error(data.msg);
    		  return;
    		}
        app.success(data.msg);
        setTimeout(function () {
          that.getdata();
        }, 1000);
      });
    },
    addscore: function (e) {
      this.$refs.addscoreDialog.open();
    },
   addscoreConfirm: function (done,value) {
			this.$refs.addscoreDialog.close();
      var that = this;
      app.post('ApiAdminMember/addscore', {rechargemid:that.opt.mid,rechargescore:value}, function (data) {
        app.success(data.msg);
        setTimeout(function () {
          that.getdata();
        }, 1000);
      });
    },
		remark:function(e){
			this.$refs.remarkDialog.open();
		},
		openRelDialog:function(){
			this.$refs.relDialog.open();
		},
		openIdentityDialog:function(){
			// 设置默认选择当前身份，兼容多种字段名
			var currentIdentity = this.member.identity || this.member.identity_name || this.member.role || '';
			var index = this.identityList.indexOf(currentIdentity);
			if(index > -1){
				this.index3 = index;
			} else {
				this.index3 = 0;
			}
			this.$refs.dialogChangeIdentity.open();
		},
		openSupplierDialog:function(){
			// 获取供货商列表
			this.getSuppliers().then(() => {
				// 设置默认选择当前关联的供货商ID
				this.setDefaultSupplier();
				this.$refs.dialogChangeSupplier.open();
			});
		},
		submitRel: function (done,value) {
			this.$refs.relDialog.close();
			var that = this;
			var pid = (value||'').toString().trim();
			if(pid==''){app.error('请输入上级ID');return}
			app.post('ApiAdminMember/changepid', {mid: that.member.id, pid: pid}, function (data) {
				app.success(data.msg);
				setTimeout(function () {
					that.getdata();
				}, 1000);
			});
		},
		remarkConfirm: function (done,value) {
			this.$refs.remarkDialog.close();
      var that = this;
      app.post('ApiAdminMember/remark', {remarkmid:that.opt.mid,remark:value}, function (data) {
        app.success(data.msg);
        setTimeout(function () {
          that.getdata();
        }, 1000);
      });
    },
		changelv:function(){
			this.$refs.dialogChangelv.open();
		},
		dialogChangelvClose:function(){
			this.$refs.dialogChangelv.close();
		},
		levelChange:function(e){
			this.index2 = e.detail.value;
		},
		confirmChangelv:function(){
			var that = this
			console.log(this.index2);
			console.log(this.levelList[this.index2]);
			var levelid = this.levelList[this.index2].id
			app.post('ApiAdminMember/changelv', { changemid:that.opt.mid,levelid:levelid}, function (res) {
				app.success(res.msg);
				that.$refs.dialogChangelv.close();
				setTimeout(function () {
					that.getdata();
				}, 1000)
			})
		},
		identityChange:function(e){
			this.index3 = e.detail.value;
		},
		dialogChangeIdentityClose:function(){
			this.$refs.dialogChangeIdentity.close();
		},
		confirmChangeIdentity:function(){
			var that = this
			var identity = that.identityList[that.index3];
			app.post('ApiMy/setfield', { mid: that.opt.mid, identity: identity }, function (res) {
				app.success(res.msg);
				that.$refs.dialogChangeIdentity.close();
				setTimeout(function () {
					that.getdata();
				}, 1000)
			})
		},
		// 获取供货商列表
		getSuppliers: function() {
			var that = this;
			return new Promise(function(resolve, reject) {
				// 调用API获取供货商列表，参考weightOrderFahuoList接口的实现
				app.post('ApiAdminOrder/weightOrderFahuoList', {
					st: 'all',
					pagenum: 1,
					supplierId: -1
				}, function (res) {
					if (res && res.status == 1 && res.suppliers) {
						that.suppliers = res.suppliers;
						// 添加空选项
						that.suppliers.unshift({id: 0, name: '无'});
						console.log('获取到的供货商列表:', that.suppliers);
					}
					resolve();
				});
			});
		},
		// 设置默认供货商
		setDefaultSupplier: function() {
			var currentSupplierId = this.member.supplier_id || this.member.supplierid || this.member.SupplierID || this.member.supplier || 0;
			// 查找当前供货商在列表中的索引
			var index = 0;
			for (var i = 0; i < this.suppliers.length; i++) {
				if (parseInt(this.suppliers[i].id) === parseInt(currentSupplierId)) {
					index = i;
					break;
				}
			}
			this.supplierIndex = index;
			this.selectedSupplierId = currentSupplierId;
		},
		// 获取当前选择的供货商名称
		getSupplierName: function() {
			if (this.suppliers.length === 0) {
				return '加载中...';
			}
			var supplier = this.suppliers[this.supplierIndex] || {id: 0, name: '无'};
			return supplier.name;
		},
		// 供货商选择变化
		supplierChange: function(e) {
			this.supplierIndex = e.detail.value;
			this.selectedSupplierId = this.suppliers[this.supplierIndex].id;
		},
		// 关闭供货商选择弹窗
		dialogChangeSupplierClose: function() {
			this.$refs.dialogChangeSupplier.close();
		},
		// 确认修改供货商
		confirmChangeSupplier: function() {
			var that = this;
			var supplier_id = this.selectedSupplierId;
			app.post('ApiMy/setfield', { mid: that.opt.mid, supplier_id: supplier_id }, function (res) {
				app.success(res.msg);
				that.$refs.dialogChangeSupplier.close();
				setTimeout(function () {
					that.getdata();
				}, 1000);
			});
		},
  }
};
</script>
<style>

.orderinfo{ width:94%;margin:20rpx 3%;border-radius:16rpx;padding: 14rpx 3%;background: #FFF;}
.orderinfo .item{display:flex;width:100%;padding:20rpx 0;border-bottom:1px dashed #ededed;}
.orderinfo .item:last-child{ border-bottom: 0;}
.orderinfo .item .t1{width:200rpx;}
.orderinfo .item .t2{flex:1;text-align:right}
.orderinfo .item .red{color:red}

.bottom{ width: 100%; padding: 16rpx 20rpx;background: #fff; position: fixed; bottom: 0px; left: 0px;display:flex;justify-content:flex-end;align-items:center;flex-wrap:wrap}
.bottom .btn{ border-radius:10rpx; padding:10rpx 16rpx;margin-left: 10rpx;border: 1px #999 solid;}

.uni-popup-dialog {width: 300px;border-radius: 5px;background-color: #fff;}
.uni-dialog-title {display: flex;flex-direction: row;justify-content: center;padding-top: 15px;padding-bottom: 5px;}
.uni-dialog-title-text {font-size: 16px;font-weight: 500;}
.uni-dialog-content {display: flex;flex-direction: row;justify-content: center;align-items: center;padding: 5px 15px 15px 15px;width:100%}
.uni-dialog-content-text {font-size: 14px;color: #6e6e6e;}
.uni-dialog-button-group {display: flex;flex-direction: row;border-top-color: #f5f5f5;border-top-style: solid;border-top-width: 1px;}
.uni-dialog-button {display: flex;flex: 1;flex-direction: row;justify-content: center;align-items: center;height: 45px;}
.uni-border-left {border-left-color: #f0f0f0;border-left-style: solid;border-left-width: 1px;}
.uni-dialog-button-text {font-size: 14px;}
.uni-button-color {color: #007aff;}
</style>
