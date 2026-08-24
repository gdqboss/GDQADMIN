#!/usr/bin/env node
/**
 * sync-modules-by-profile.v2.mjs — 按 server_profiles.modules 精准同步 (alias-aware)
 *
 * v2 改动 (2026-08-25):
 *   - 加载 scripts/module-key-aliases.json 把 DB module_key 映射到真实 views 路径
 *   - 输出"alias 命中的 source path"用于 rsync include-from
 *   - 列出"DB 多 - 源码无"中:
 *     * 命中 alias 的 → 算 OK, 列出实际 source path
 *     * 未命中 alias 且 alias 标 null 的 → 标"死权限, 待清理"
 *   - 列出"源码多 - DB 无"的孤儿目录
 *
 * 与 v1 (sync-modules-by-profile.mjs) 区别:
 *   v1: db_key = source path  (字面匹配, 21 个 mismatch)
 *   v2: db_key → alias[db_key] = source path  (alias-aware, 应 0 mismatch)
 *
 * 用法:
 *   node scripts/sync-modules-by-profile.v2.mjs --profile=7 --dry-run
 *   node scripts/sync-modules-by-profile.v2.mjs --profile=7 --execute
 */

import { readFileSync, writeFileSync } from 'node:fs';
import { execSync } from 'node:child_process';
import { join } from 'node:path';

const args = process.argv.slice(2);
const profileId = parseInt(args.find(a => a.startsWith('--profile='))?.split('=')[1] || '0');
const dryRun = !args.includes('--execute');
const verbose = args.includes('--verbose');

if (!profileId) {
  console.error('❌ 必须指定 --profile=<id>');
  process.exit(2);
}

// 加载 alias 表
const aliasPath = join(import.meta.dirname, 'module-key-aliases.json');
const aliasTable = JSON.parse(readFileSync(aliasPath, 'utf-8'));
const aliases = aliasTable.aliases;

const envContent = readFileSync('/root/server/.env', 'utf-8');
const env = Object.fromEntries(
  envContent.split('\n').filter(l => l && !l.startsWith('#')).map(l => l.split('=', 2))
);
const { DB_HOST, DB_USER, DB_PASSWORD, DB_NAME } = env;

console.log(`=== v2 精准同步 profile ${profileId} ${dryRun ? '(dry-run)' : '(execute)'} ===`);
console.log(`   alias 表加载: ${Object.keys(aliases).length} 条 (源 ${aliasPath})`);

// 1. 查 server_profiles 元信息
const profileOut = execSync(
  `mysql -h${DB_HOST} -u${DB_USER} -p${DB_PASSWORD} ${DB_NAME} -N -B -e "SELECT id, name, ip, deployment_mode FROM server_profiles WHERE id = ${profileId}"`,
  { encoding: 'utf-8' }
).trim();
const [pId, pName, pIp, pDeploy] = profileOut.split('\t');
console.log(`目标: ${pName} (${pIp}, deployment_mode=${pDeploy})`);

// 2. 查启用的 module_key 清单
const modulesOut = execSync(
  `mysql -h${DB_HOST} -u${DB_USER} -p${DB_PASSWORD} ${DB_NAME} -N -B -e "SELECT module_key FROM server_modules WHERE server_profile_id = ${profileId} ORDER BY module_key"`,
  { encoding: 'utf-8' }
).trim();
const modules = modulesOut.split('\n').filter(Boolean);

console.log(`\n📦 DB 中 server_modules 注册的模块 (${modules.length} 个):`);
modules.forEach(m => console.log(`  - ${m}`));

// 3. 解析 alias, 算出每个 db_key 的真实 source path
console.log(`\n🔍 Alias 解析:`);
const resolved = [];      // { dbKey, sourcePath, status }
const deadPerms = [];     // alias = null (死权限)
const unmapped = [];      // 不在 alias 表里
for (const dbKey of modules) {
  if (dbKey in aliases) {
    const v = aliases[dbKey];
    if (v === null) {
      deadPerms.push(dbKey);
      resolved.push({ dbKey, sourcePath: null, status: 'DEAD_PERM (alias=null)' });
    } else {
      resolved.push({ dbKey, sourcePath: v, status: 'OK' });
    }
  } else {
    unmapped.push(dbKey);
    resolved.push({ dbKey, sourcePath: `views/${dbKey}/`, status: 'NO_ALIAS (字面兜底)' });
  }
}

// 分类打印
console.log(`  ✅ alias 命中 (${resolved.filter(r => r.status === 'OK').length} 个):`);
resolved.filter(r => r.status === 'OK').forEach(r => console.log(`     ${r.dbKey} → ${r.sourcePath}`));
if (deadPerms.length) {
  console.log(`  💀 死权限 (alias=null, ${deadPerms.length} 个):`);
  deadPerms.forEach(d => console.log(`     ${d}`));
}
if (unmapped.length) {
  console.log(`  ⚠️ 无 alias 映射, 字面兜底 (${unmapped.length} 个):`);
  unmapped.forEach(u => console.log(`     ${u}`));
}

// 4. 比对 SGP 源码层真实存在 (递归扫到叶子, 任何 view path 都能找到)
console.log(`\n📂 SGP 源码层验证:`);
const srcRoot = `/root/server/modules/${profileId}`;
// 递归扫所有 view path (相对 views/ 的路径), 用于"alias 解析后真实路径是否存在"判定
let allViewPaths = [];
try {
  allViewPaths = execSync(
    `cd ${srcRoot}/views && find . \\( -type d -o -name '*.vue' \\) ! -name '*.bak.*' | sort`,
    { encoding: 'utf-8' }
  ).trim().split('\n').filter(Boolean).map(p => p.replace(/^\.\//, ''));
} catch (e) {
  console.log(`  ⚠️ modules/${profileId}/views 不存在`);
}

console.log(`  SGP 源码层视图项 (递归, 含子目录): ${allViewPaths.length}`);
console.log(`  DB 注册:                              ${modules.length}`);

// alias 解析后真实 source path 是否都在 SGP 源码里
const resolvedPaths = resolved.filter(r => r.sourcePath).map(r => r.sourcePath);
// 归一化: sourcePath = "views/approval/" → 扫库查 "approval/" 或 "approval"
// sourcePath = "views/Xxx.vue" → 查 "Xxx.vue"
const normPath = (p) => p.replace(/^views\//, '').replace(/\/$/, '');
const srcNormSet = new Set(allViewPaths.map(p => p.replace(/\/$/, '')));

const missingInSource = resolvedPaths.filter(p => {
  const np = normPath(p);
  // 直接命中
  if (srcNormSet.has(np)) return false;
  // 也允许 np 是某个 srcPath 的前缀 (目录)
  return !Array.from(srcNormSet).some(s => s === np || s.startsWith(np + '/'));
});

console.log(`  ⚠️ alias 解析后,源码仍缺失的 (${missingInSource.length} 个):`);
missingInSource.forEach(p => console.log(`     ${p}`));

// 5. 孤儿目录 (源码有 - DB 无注册)
const dbNormPaths = new Set(resolved.filter(r => r.sourcePath).map(r => normPath(r.sourcePath)));
const orphans = allViewPaths.filter(p => {
  const np = p.replace(/\/$/, '');
  // 如果 np 是某个 db path 的子, 算"db 已覆盖"
  if (dbNormPaths.has(np)) return false;
  // 如果 np 的祖先在 db 里 (例如 db 有 "settings/", 源码有 "settings/RoleManage.vue"), 不算孤儿
  return !Array.from(dbNormPaths).some(d => np.startsWith(d + '/'));
});
console.log(`\n👻 源码孤儿目录 (源码有 - DB 无注册, ${orphans.length} 个):`);
orphans.slice(0, 15).forEach(o => console.log(`  - ${o}`));
if (orphans.length > 15) console.log(`  ... 还有 ${orphans.length - 15} 个`);

// 6. 生成 rsync include-from 白名单 (用 alias 解析后的真实路径)
console.log(`\n=== 精准同步命令 (alias-aware) ===`);
const whitelistPath = `/tmp/include-modules-p${profileId}.v2.txt`;
const includeLines = [];
includeLines.push('# auto-generated by sync-modules-by-profile.v2.mjs');
includeLines.push('# include 模式: 匹配 DB 注册 module_key 经 alias 解析后的真实 source path');
includeLines.push('');
for (const r of resolved) {
  if (!r.sourcePath) continue;  // 跳过死权限
  // sourcePath = "views/AiClassroom.vue" 或 "views/approval/" 或 "views/association/academic/"
  // rsync include-from 模式: include 需要写 path-from-src-root
  // SGP 同步的源根 = /root/server/modules/<id>/
  // 所以 include 写 "views/approval/" 而 rsync src 是 /root/server/modules/<id>/
  includeLines.push(`+ ${r.sourcePath.replace(/^views\//, '')}`);
  if (r.sourcePath.endsWith('/')) {
    includeLines.push(`+ ${r.sourcePath.replace(/^views\//, '')}**`);
  }
}
includeLines.push('+ _meta.js');
includeLines.push('+ index.js');
includeLines.push('+ *');
includeLines.push('- *');  // 兜底 exclude 其它

writeFileSync(whitelistPath, includeLines.join('\n') + '\n');

console.log(`Whitelist 已写: ${whitelistPath} (${includeLines.length} 行)`);
console.log(`\nrsync 命令模板 (dry-run):`);
console.log(`rsync -av --include-from=${whitelistPath} \\`);
console.log(`    /root/server/modules/${profileId}/ \\`);
console.log(`    <target>:modules/${profileId}/`);

// 7. 总结
console.log(`\n=== 总结 ===`);
console.log(`DB 注册:               ${modules.length}`);
console.log(`alias 命中 (有效):     ${resolved.filter(r => r.status === 'OK').length}`);
console.log(`死权限 (alias=null):   ${deadPerms.length} ${deadPerms.length ? '⚠️ 待波哥清理' : ''}`);
console.log(`无 alias (字面兜底):   ${unmapped.length}`);
console.log(`alias 解析后源码仍缺:  ${missingInSource.length}`);
console.log(`源码孤儿目录:          ${orphans.length}`);
console.log(`白名单行数:            ${includeLines.length}`);

if (dryRun) {
  console.log(`\n🛑 DRY-RUN: 没真同步. 跑 --execute 才会 rsync.`);
} else {
  console.log(`\n⚠️ EXECUTE 模式: 需要先 SSH 连 ${pIp}`);
  console.log(`   本脚本未实现 SSH 通道 (留给 sync-modules-by-profile.sh 后续增强)`);
}