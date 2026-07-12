module.exports = {
  apps: [{
    name: 'gdq-server',
    script: '/home/gdq/server/index.js',
    cwd: '/home/gdq/server',
    env: {
      PORT: 3200,
      NODE_ENV: 'production',
      MINIMAX_KEY: 'sk-cp-...jQtU'
    }
  }]
}