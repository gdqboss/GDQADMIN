-- 2026-08-23 SSH 通后 - profile 3 keypair 替换
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
-- WHERE:  id = 3

LOCK TABLES `server_profiles` WRITE;
/*!40000 ALTER TABLE `server_profiles` DISABLE KEYS */;
INSERT INTO `server_profiles` VALUES
(3,'3号仓库','43.160.238.201','10.3.8.4','ap-singapore','lhins-ag2fsg6h',22,443,3200,'nginx','mariadb','10.11',NULL,6379,'none','fork',NULL,'2026-08-24 02:00:23',NULL,0,NULL,NULL,NULL,NULL,0,0,'full','## 2026-08-23 待重新部署 (波哥登录腾讯云人工处理)\n- 触发: 波哥 OOB \"登录腾讯云去处理，然后记录在目标服务器管理\"\n- 现象: SGP agent 用 /root/singclaw.pem SSH 43.160.238.201 被拒 (Permission denied publickey)\n- 待人工: 登腾讯云 Lighthouse 控制台 → 3号仓库 (43.160.238.201) → 重置/上传 SSH key → 同步最新 dist + DB schema\n- SGP 侧已经 ready: 最新 build dist-1/ 在 /root/server/dist-1/, server_profiles id=3 site_logo = /uploads/logo-no3-photo.jpg\n- 部署完成后: 把 last_deploy_at = NOW() 并把这段 header 移到下面 历史 区\n\n3号仓库 (mywh3.com, profile_id=3) — 完整画像 2026-08-10\n\n## 服务器基础\n- IP: 43.160.238.201 (SGP 同 region)\n- 内网: 10.3.0.x ↔ 10.3.8.4\n- SSH port: 22\n- SSH user: root\n- SSH key: SGP 同内网 key\n- SSH 命令: ssh root@43.160.238.201\n- deployment_mode: fork (独立运行)\n- env: production\n- 行业: ecommerce (3号仓库是仓库代发业务)\n\n## Brand\n- site_name_zh: 智能商业系统 (跟 SGP 同, 共享 brand)\n- modules/ 跟 SGP modules/1/ 共用模板\n\n## 前端 (dist + nginx)\n- frontend_type: main\n- dist_path: /home/gdq/dist/\n- nginx: 跟 SGP 同模式\n\n## 后端\n- backend: pm2 gdq-server\n- 后端路径: /home/gdq/server/\n- port: 3200\n\n## 数据库 (DB 独立, gdq_3)\n- DB_NAME: gdq_3\n- DB_USER: gdq_3\n- DB_PASSWORD: USER.md 3号仓库段 (base64)\n\n## 源码 (3号 fork)\n- 3号完整源码树在 /home/gdq/server/\n- SGP modules/1/ = 通用电商 SPA, 3号跟 SGP 共用\n\n## 访问入口\n- 主站: https://mywh3.com/\n- 管理后台: https://mywh3.com/gdqadmin/\n- API: https://mywh3.com/api/*\n\n## 历史\n- 2026-07-xx SGP 同步成功, 3号仓库稳定运行\n','root','/root/clawgdqshop.pem','key',NULL,'3号仓库 mywh3.com, 与 SGP 同步消费端. is_source=0 deployment_mode=fork.','production',NULL,'江清波','mywh3.com','','',NULL,NULL,NULL,NULL,'https://mywh3.com','/uploads/logo-no3-photo.jpg','智能商业系统','AI Business System','2026-08-03 15:42:57','2026-08-23 18:00:23','[\"zh\",\"en\",\"ms\"]',NULL,'MYR','ecommerce','','','18676970008','aaabbb1234',3306,'main','/home/gdq/dist/','/etc/nginx/sites-enabled/mywh3.com',0,'mywh3.com','[]');
/*!40000 ALTER TABLE `server_profiles` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-24  2:42:07
