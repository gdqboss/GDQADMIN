// Redis缓存工具
import Redis from 'ioredis';

const redis = new Redis({
  host: 'localhost',
  port: 6379,
  retryDelayOnFailover: 100,
  maxRetriesPerRequest: 3
});

redis.on('error', (err) => {
  console.log('[Redis] 连接错误:', err.message);
});

redis.on('connect', () => {
  console.log('[Redis] 已连接');
});

// 缓存工具函数
export const cache = {
  // 设置缓存（秒）
  async set(key, value, ttl = 300) {
    try {
      const data = typeof value === 'object' ? JSON.stringify(value) : value;
      await redis.setex(key, ttl, data);
      return true;
    } catch (e) {
      console.log('[Cache] 设置失败:', e.message);
      return false;
    }
  },

  // 获取缓存
  async get(key) {
    try {
      const data = await redis.get(key);
      if (!data) return null;
      try {
        return JSON.parse(data);
      } catch {
        return data;
      }
    } catch (e) {
      console.log('[Cache] 获取失败:', e.message);
      return null;
    }
  },

  // 删除缓存
  async del(key) {
    try {
      await redis.del(key);
      return true;
    } catch (e) {
      console.log('[Cache] 删除失败:', e.message);
      return false;
    }
  },

  // 清除所有缓存（谨慎使用）
  async flush() {
    try {
      await redis.flushall();
      return true;
    } catch (e) {
      console.log('[Cache] 清除失败:', e.message);
      return false;
    }
  }
};

export default cache;
