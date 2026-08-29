# HK 孵化器前端技术栈盘点
> 2026-08-23 by JXY

## 结论
HK `/var/www/hatch/` 下 3 个 SPA 入口都能在新加坡找到源码，可统一 build。

| HK 入口 | 技术栈 | 源码位置 (SGP) |
|---|---|---|
| `/gdqadmin/` | Vite+Vue3 admin SPA | `/root/server/` |
| `/minip/` | uni-app H5 | `/root/jxy-os/hengqin-wanqu-minip/` |
| `/labor/` | Vite+Vue3 SmartBiz Labor | `/root/server/modules/2/` |

## 关键发现
- HK `/home/ubuntu/server/modules/2/` 是完整 labor 源码，与 SGP `/root/server/modules/2/` 同源。
- HK `/home/ubuntu/server/` 不是独立 fork，而是 profile 2/3 的 build 产物目录。
- 早先认为 "HK labor 无源码" 是误判，已纠正。
