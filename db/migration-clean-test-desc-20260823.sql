-- 2026-08-23 清掉 server_profiles 测试残留 description
-- 波哥指示 "根据目标服务器管理 去更新" — 我看到的明显测试残留:
--   profile 2 (北京彩美特): '测试保存 2026-08-23 18:38' (明显是 form 保存按钮测试)
--   profile 7 (澳門中醫藥學會): 'Macau aippmcm.com test' (明显是测试字串)
-- 处理: 清成空字符串, 让波哥后续手动填真描述 (避免 agent 瞎猜内容)
-- pre-update snapshot:
/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.14-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: gdq
-- ------------------------------------------------------
-- Server version	10.11.14-MariaDB-0ubuntu0.24.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `server_profiles`
--
-- WHERE:  id IN (2,7)

LOCK TABLES `server_profiles` WRITE;
/*!40000 ALTER TABLE `server_profiles` DISABLE KEYS */;
INSERT INTO `server_profiles` VALUES
(2,'北京彩美特','81.70.199.64',NULL,'ap-beijing','lhins-2w1qjyyq',2222,443,3000,'openresty','mysql','8.0','',6379,'none','fork',NULL,'2026-08-24 01:00:13',NULL,0,'',NULL,NULL,NULL,0,0,'full','','ubuntu','/root/clawgdqshop.pem','key',NULL,'测试保存 2026-08-23 18:38','production','2026-07-31','江清波','claw.gdqshop.cn','','','','','','','','/logo-pending.png','北京彩美特','Beijing Caimeite','2026-08-03 15:42:57','2026-08-23 17:00:13','[\"zh\",\"en\"]',NULL,'CNY','ecommerce','127.0.0.1','gdq','gdq','***',3306,'main','/home/gdq/dist/','',1,'','[]'),
(7,'澳門中醫藥學會','101.33.32.177',NULL,'ap-hongkong','lhins-ghdwct3d',22,443,3200,'nginx','mysql','8.0',NULL,6379,'none','fork',NULL,'2026-08-24 01:00:39',NULL,0,NULL,NULL,NULL,NULL,0,0,'full','澳門中醫藥學會 (aippmcm.com, profile_id=7) — 完整画像 2026-08-10\n\n## 服务器基础\n- IP: 101.33.32.177 (ap-hongkong region)\n- 实例: lhins-ghdwct3d (腾讯云 Lighthouse, 系统盘 lhdisk-5gv9zcgz 70GB)\n- 快照: lhsnap-i20ht21v (8-10 凌晨 5:03 北京 = 8-09 23:00 UTC, 标准回滚点)\n- CPU/RAM/磁盘: 2C/7GB/? (notes 没填,需补)\n- OS: OpenCloudOS (conf.d 模式 nginx, 不是 sites-enabled)\n- deployment_mode: fork (独立运行, 不共享 SGP 后端)\n- env: production\n\n## SSH 凭证\n- user: root\n- key: /root/.ssh/soc/jxy20260804hongkong.pem (跟 profile 6 横琴共用 key, 在 macau notes 也写 /root/.ssh/hk-incubator/hk_incubator_v4.pem)\n- port: 22\n- 实际命令: ssh -i /root/.ssh/hk-incubator/hk_incubator_v4.pem root@101.33.32.177\n\n## 前端 (dist + nginx)\n- frontend_type: main (SPA shell)\n- dist_path: /opt/soc-server/dist-1/ (不是 dist/)\n- nginx config: /etc/nginx/conf.d/aippmcm.com.conf (不是 sites-enabled)\n- vite.config.js: /opt/soc-server/vite.config.js (模块过滤 plugin)\n- 主 SPA 入口: /soc/ + /admin/ + /gdqadmin/ (共用 dist-1 shell)\n- 副 SPA 入口: /portal/ (macau 自己的 portal)\n- 静态资源 location:\n  - /soc/assets/ → dist-1/assets/\n  - /admin/ → dist-1/index.html\n  - /gdqadmin/ → dist-1/index.html (404 fix: rewrite gdqadmin/api/* → /api/*)\n  - /portal/ → dist-1/index.html\n  - /assets/ → dist-1/assets/\n  - /icons/ → dist-1/icons/\n  - /logo*.png → dist-1/logo*.png (5 logo)\n  - /seal.png → dist-1/seal.png (404 - dist 没有这个文件)\n- frontend 内存警告 (2026-08-10): macau Lighthouse 7GB RAM 跑 vite build OOM 卡死 ×2, 不能 macau 本地 build\n\n## 后端\n- backend_port: 3200 (systemd soc-server.service, 自动 restart)\n- index.js: /opt/soc-server/index.js (41552 bytes, 8-08 12:06 原始, 148 routes, 130 /api mount)\n- systemd unit: /etc/systemd/system/soc-server.service (Type=simple, ExecStart=/usr/bin/node index.js)\n- 重启: systemctl restart soc-server (uptime 1min 自动起)\n- 日志: /var/log/soc-server.log\n- middleware: auth/rbac/translate/upload/rateLimit/errorHandler (11 个)\n\n## 数据库 (DB 独立, gdq_macau)\n- DB_HOST: 127.0.0.1 (本地)\n- DB_PORT: 3306\n- DB_NAME: gdq_macau\n- DB_USER: gdq_macau\n- DB_PASSWORD: macau .env 当前真值在 USER.md 凭据段 (base64 编码, base64 -d 解码取) — #13 凭据分级\n- DB 密码变更历史: 见 ~/.hermes/memory/devlog-2026-08-05.md + ~/.hermes/memory/devlog-2026-08-10-caimeite-purge.md\n- 严禁明文写 DB 密码到 notes / DEVLOG / skills / memory (凭据分级 #13)\n- 9 张 association 表: academic(8) activities(5) announcements(16) cards(8) downloads(6) inquiries(4) journals(4) org(60) profile(3) = 114 行数据\n- 总 server_modules: 27 个 (含 10 association-* + 4 rbac/* + dashboard + settings + users + roles + article + banner-manage + banner-management + finance + finance-simple + logistics + reports + work-logs + tasks + oa + job-responsibilities + approvals)\n- data_isolation: full (DB 物理独立, 不共享 SGP)\n\n## 源码 (macau fork, 不在 SGP)\n- macau 完整源码树在 /opt/soc-server/ (macau 自己, 不是 SGP modules/7/)\n- 源码结构: routes/ (148) + views/ (44 子目录 + 4 顶层 vue) + i18n/ (3 字典 en/zh/zh-TW) + stores/ (6) + components/ (14) + layouts/ (2) + services/ (13) + utils/ (13) + middleware/ (11) + db/ (含 migrations) + App.vue + main.js + index.html + vite.config.js\n- 顶层 views (vue): AiClassroom.vue + Dashboard.vue + Login.vue + Minip.vue + Temple.vue + Wxapp.vue\n- views/association/ (10 子目录): academic + activities + announcements + cards + downloads + info + inquiries + journals + members + org\n- macau 不是 git 仓库 (改前必须 cp 备份)\n- 改前必须看 caimeite-server-frontend-allocation skill (SGP 唯一权威)\n\n## Brand (行业品牌差异化)\n- site_name_zh: 澳門中醫藥學會\n- site_name_en: Macau Chinese Medicine Association\n- 中文 logoInitial: 澳\n- 8-10 凌晨快照本身残留 16+ 处 彩美特 字眼 (i18n 6 + views 4 + utils 2 + routes 11)\n  - 这是 8-10 之前的快速 build 残留, macau fork 模式下手动清\n  - 严禁 macau 本地 vite build (OOM 卡死 Lighthouse 实例 ×2)\n  - 改法: SGP build dist + rsync 到 macau dist-1 (避开 OOM), 或者 sed 改 dist-1/assets/*.js\n\n## 访问入口\n- 主站: https://aippmcm.com/ → 302 → /soc/\n- 管理后台: https://aippmcm.com/gdqadmin/ (admin SPA)\n- API: https://aippmcm.com/api/* (proxy 3200)\n- 静态资源: https://aippmcm.com/{soc,portal,gdqadmin,admin,assets,icons,uploads}/\n- 健康检查: https://aippmcm.com/api/health\n- 公共设置: https://aippmcm.com/api/public-settings?server_profile_id=7\n\n## nginx\n- 监听: 80 (308 → 443) + 443 (HTTPS + h2)\n- TLS: Let\'s Encrypt cert /etc/letsencrypt/live/aippmcm.com/{fullchain.pem, privkey.pem}\n- 证书续期: certbot 自动 (3 个月)\n- gzip on + gzip_types text/css/js/json/svg/font/woff2\n- SPA index.html cache 5min (must-revalidate), assets cache 365d (immutable)\n- P1b 性能优化 2026-08-05 已应用\n\n## 紧急回滚\n- 快照 ID: lhsnap-i20ht21v (8-10 凌晨 = 8-09 23:00 UTC)\n- 回滚命令: tccli lighthouse ApplyInstanceSnapshot --InstanceId lhins-ghdwct3d --SnapshotId lhsnap-i20ht21v --region ap-hongkong\n- 回滚后实例 STOPPED → StartInstances 自动 RUNNING (大约 60s)\n- 注意: 回滚会丢失所有 8-10 凌晨之后的源码改动 + dist 改动\n\n## 历史事故 (永久留底, 不能重犯)\n- 2026-08-10 之前: macau 后端被破坏 (SGP 老 SmartBiz dist sync + 后端 routes 改坏) → ApplyInstanceSnapshot 回滚 ✓\n- 2026-08-10 17:30: macau vite build OOM 卡死 ×2 (重蹈 8-10 之前覆辙) → ApplyInstanceSnapshot 回滚 ✓\n- 2026-08-10 18:00: macau 恢复 8-10 凌晨快照 ✓ (当前状态)\n- 教训: macau 不能本地 vite build, 必须 SGP build + rsync\n- 教训: 备份必须 cp 不能 mv (破坏当前生效)\n','root','/root/.ssh/soc/jxy20260804hongkong.pem','key',NULL,'Macau aippmcm.com test','production',NULL,'江清波','aippmcm.com',NULL,NULL,NULL,NULL,NULL,NULL,'https://aippmcm.com','/favicon-32.png','澳門中醫藥學會','Macau Chinese Medicine Association','2026-08-05 02:20:27','2026-08-23 17:00:39','[\"zh-TW\", \"zh-CN\", \"en-US\"]','zh-TW','MOP','healthcare','127.0.0.1','gdq_macau','gdq_macau','Re78g0A1XcNmr1T8',3306,'main','/opt/soc-server/dist-1/','/etc/nginx/conf.d/aippmcm.com.conf',0,'aippmcm.com',NULL);
/*!40000 ALTER TABLE `server_profiles` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-24  1:22:12
