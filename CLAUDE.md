# SmartBiz (GDQADMIN) - 项目上下文

## 项目类型
SmartBiz 智能仓储管理系统 - Vue 3.5 前端 + 后端 API

## 技术栈
- **前端**：Vue 3.5, Vite, Pinia, Vue Router, Element Plus
- **后端**：Node.js / Python Flask（API服务）
- **部署**：dist/ 目录构建产物，部署在 mywh3.com

## 目录结构
```
/root/src/           ← 源码根目录（当前目录）
├── api/              ← API 接口
├── components/       ← Vue 组件
├── layouts/          ← 布局组件
├── pages/            ← 页面
├── stores/           ← Pinia 状态管理
├── i18n/             ← 国际化（中/英/马来）
├── dist/             ← 构建产物（勿直接修改）
└── .env              ← 环境变量（勿提交）
```

## 系统架构
- **前端端口**：当前开发服务器 localhost:5173
- **API来源**：环境变量 `VITE_API_BASE` 配置
- **目标域名**：mywh3.com（马来西亚客户）

## 重要边界

### ⚠️ 严禁操作
1. **勿修改 dist/ 目录** - 构建产物由构建脚本生成
2. **勿提交 .env 或 .env.local** - 包含敏感信息
3. **勿直接修改生产环境** - 所有变更走 git flow

### ✅ 正确流程
1. 在本地开发调试
2. 测试通过后 `git add` → `git commit` → `git push`
3. 新加坡服务器自动/手动 `git pull` 更新
4. 服务器上执行构建脚本
5. 构建产物同步到部署服务器

## Git 信息
- **仓库**：https://github.com/gdqboss/GDQADMIN.git
- **分支**：master / main
- **协作者**：JXY（江小鱼）、Hermes、Trae、波哥

## 开发规范
- 使用中文注释（马来西亚客户版）
- 组件名 PascalCase，文件名校验规则
- API 请求统一走 `/api/` 路径
- i18n 翻译文件在 `i18n/` 目录

## 联系方式
- **波哥**：江清波，项目负责人
- **JXY**：新加坡服务器 AI 外脑，负责需求分析和任务调度
- **Hermes**：Worker Agent，执行开发任务
