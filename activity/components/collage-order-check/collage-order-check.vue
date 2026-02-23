<template>
  <!-- 这个组件不渲染任何内容，只作为一个服务组件使用 -->
</template>

<script>
var app = getApp();

export default {
  props: {
    proid: {
      type: String,
      default: ''
    },
    ggid: {
      type: String,
      default: ''
    }
  },
  data() {
    return {
      hasExistingOrder: false,
      loading: false,
      error: null
    };
  },
  methods: {
    // 检查当前商品是否有未完成的拼团订单
    checkExistingOrders() {
      return new Promise((resolve, reject) => {
        // 如果没有商品ID，直接返回false
        if (!this.proid) {
          resolve(false);
          return;
        }

        this.loading = true;
        this.error = null;

        // 调用后端API检查未完成的拼团订单
        app.post('ApiCollage/checkUnfinishedCollage', {
          proid: this.proid,
          ggid: this.ggid
        }, (res) => {
          this.loading = false;
          
          if (res.status === 1) {
            // API调用成功，存在未完成的拼团订单
            this.hasExistingOrder = true;
            resolve({ hasOrder: true, orderid: res.orderid });
          } else {
            // API调用成功，没有未完成的拼团订单或其他情况
            this.hasExistingOrder = false;
            resolve({ hasOrder: false, orderid: null });
          }
        }, (err) => {
          this.loading = false;
          this.error = '网络错误，请稍后重试';
          console.error('检查拼团订单网络错误:', err);
          // 返回false，允许用户继续购买
          resolve(false);
        });
      });
    }
  }
};
</script>

<style scoped>
/* 这个组件不需要样式 */
</style>