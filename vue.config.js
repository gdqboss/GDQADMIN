module.exports = {
  devServer: {
    proxy: {
      '/gdq': {
        target: 'https://gdqshop.cn',
        changeOrigin: true,
        secure: true,
        pathRewrite: { '^/gdq': '' }
      },
      '/static': {
        target: 'https://gdqshop.cn',
        changeOrigin: true,
        secure: true
      }
    }
  }
}
