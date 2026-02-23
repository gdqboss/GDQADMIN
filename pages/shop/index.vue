<template>
	<view class="container"></view>
</template>

<script>
var app = getApp();
export default {
  data() {
    return {

    }
  },
  onLoad: function (opt) {
		var o = app.getopts(opt)||{};
		var url = '/pagesB/shop/index'; // 假设实际的店铺页面在pagesB/shop/下
		var qs = [];
		for(var k in o){
			if(o.hasOwnProperty(k)){
				var v = o[k];
				if(v!==undefined && v!==null && v!==''){
					qs.push(encodeURIComponent(k)+'='+encodeURIComponent(v));
				}
			}
		}
		if(qs.length>0){ url += ('?' + qs.join('&')); }
		app.goto(url,'redirectTo');
  }
}
</script>
