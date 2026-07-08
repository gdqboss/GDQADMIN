#!/bin/bash
# 给 3 个 profile 各跑一次 build, 总结 + 入 backup branch
# 跑法: bash scripts/backup-profile-builds.sh
#
# 不动生产 dist/, 只在 dist-1/2/3 (profile build 输出)
# 生成 SUMMARY 文件, push 到 backup/profile-builds-<date> branch
#
# 不依赖 git add dist-*/ (违反 AGENTS.md)
# 回滚: checkout backup branch, 用同 build 命令 + 同 hash 校验

set -e
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
SRC_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
DATE=$(date +%Y-%m-%d)
BRANCH="backup/profile-builds-$DATE"

cd "$SRC_DIR"

# 0. 如果有 uncommitted 改动, 先 stash (避免 backup branch 跑 build 时混进别人的 work)
# 跑完再 pop
STASHED=0
if ! git diff --quiet HEAD 2>/dev/null || ! git diff --cached --quiet HEAD 2>/dev/null; then
  echo "⚠️  有 uncommitted 改动, stash 临时存 (跑完 pop 回来)"
  git stash push -u -m "backup-profile-builds-$DATE-auto-stash" 2>&1 | tail -3
  STASHED=1
fi

# 1. 临时建 backup branch
git checkout -b "$BRANCH" 2>&1 | tail -3

# 2. 给每个 profile 真 build, 收集 summary
SUMMARY_DIR="$SRC_DIR/docs/module-build/snapshots/$DATE"
mkdir -p "$SUMMARY_DIR"

for PID in 1 2 3; do
  echo ""
  echo "=== Profile $PID build ==="
  MODULE_FILTER_JSON_REPORT=1 bash scripts/build-for-profile.sh $PID build 2>&1 | tail -10
  REPORT="$SRC_DIR/dist-$PID/.module-filter-report.json"
  if [ ! -f "$REPORT" ]; then
    echo "FAIL: $REPORT 没生成"
    exit 1
  fi
  # 算 dist 整体 md5 + 关键 entry hash + chunk 数
  node -e "
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const distDir = '$SRC_DIR/dist-$PID';
const report = JSON.parse(fs.readFileSync('$REPORT', 'utf8'));
// 整体 dist md5 (排除 .module-filter-report.json)
function distHash(dir) {
  const h = crypto.createHash('md5');
  const files = fs.readdirSync(path.join(dir, 'assets')).sort();
  for (const f of files) {
    if (f.endsWith('.js') || f.endsWith('.css')) {
      h.update(f);
      h.update(fs.readFileSync(path.join(dir, 'assets', f)));
    }
  }
  h.update(fs.readFileSync(path.join(dir, 'index.html'), 'utf8'));
  return h.digest('hex').slice(0, 12);
}
const summary = {
  profile: $PID,
  builtAt: new Date().toISOString(),
  distDir: 'dist-$PID',
  distMd5: distHash(distDir),
  entryChunk: report.entry.name,
  enabledModules: report.profile.enabledModules,
  disabledModules: report.profile.disabledModules,
  filteredRefs: report.entry.filteredRefs,
  removedFiles: report.entry.removedFiles,
  removedKb: report.entry.removedKb,
  distSize: require('child_process').execSync('du -sh ' + distDir).toString().split('\\t')[0],
  jsChunkCount: require('fs').readdirSync(path.join(distDir, 'assets')).filter(f => f.endsWith('.js')).length,
  cssChunkCount: require('fs').readdirSync(path.join(distDir, 'assets')).filter(f => f.endsWith('.css')).length,
};
const outPath = path.join('$SUMMARY_DIR', 'profile-$PID.json');
fs.writeFileSync(outPath, JSON.stringify(summary, null, 2));
console.log('  →', outPath);
console.log('     md5:', summary.distMd5, '| size:', summary.distSize, '| js:', summary.jsChunkCount, 'css:', summary.cssChunkCount);
"
done

# 3. 写 README 说明怎么用
cat > "$SUMMARY_DIR/README.md" << EOF
# Profile Build Snapshots — $DATE

> **来源**: backup branch \`$BRANCH\` HEAD
> **生成**: bash scripts/backup-profile-builds.sh
> **GitHub**: https://github.com/gdqboss/GDQADMIN/tree/$BRANCH

## 3 个 profile 真 build 结果

| profile | distMd5 | size | chunks (js+css) | entry |
|---|---|---|---|---|
$(for PID in 1 2 3; do
  node -e "const s=require('./profile-$PID.json');console.log('| $PID (sgp/bj/3hk) | \`' + s.distMd5 + '\` | ' + s.distSize + ' | ' + s.jsChunkCount + ' + ' + s.cssChunkCount + ' | \`' + s.entryChunk + '\` |')"
done)

## 怎么回滚到这个状态

\`\`\`bash
# 1. 切到 backup branch
git fetch origin $BRANCH
git checkout $BRANCH

# 2. 重 build (会生成完全相同的 dist, 用 distMd5 验证)
for PID in 1 2 3; do
  bash scripts/build-for-profile.sh \$PID build
done

# 3. 校验 md5 一致
node -e "
const fs = require('fs'); const path = require('path'); const crypto = require('crypto');
function distHash(dir) { const h = crypto.createHash('md5'); for (const f of fs.readdirSync(path.join(dir,'assets')).sort()) { if (f.endsWith('.js')||f.endsWith('.css')) { h.update(f); h.update(fs.readFileSync(path.join(dir,'assets',f))); } } h.update(fs.readFileSync(path.join(dir,'index.html'),'utf8')); return h.digest('hex').slice(0,12); }
for (const pid of [1,2,3]) { const saved = require('./snapshots/$DATE/profile-'+pid+'.json').distMd5; const now = distHash('dist-'+pid); console.log('profile', pid, saved === now ? '✅' : '❌', 'saved:', saved, 'now:', now); }
"
\`\`\`

## 不要做的事

- ❌ \`git add dist-1/\` \`dist-2/\` \`dist-3/\` — AGENTS.md 禁止
- ❌ \`git checkout $BRANCH -- dist-1/\` — 上面原因, dist 不入 git
- ✅ 永远用 build 命令 + 校验 md5 来恢复
EOF

# 4. commit summary 文件
git add "docs/module-build/snapshots/$DATE/"
git status -s
echo ""
git commit -m "backup(profile): 3 profile 真 build snapshots — $DATE

profile 1 (sgp 全开): $(node -e "console.log(require('./docs/module-build/snapshots/$DATE/profile-1.json').distMd5)") | $(node -e "console.log(require('./docs/module-build/snapshots/$DATE/profile-1.json').distSize)") | $(node -e "console.log(require('./docs/module-build/snapshots/$DATE/profile-1.json').jsChunkCount)") js
profile 2 (bj):      $(node -e "console.log(require('./docs/module-build/snapshots/$DATE/profile-2.json').distMd5)") | $(node -e "console.log(require('./docs/module-build/snapshots/$DATE/profile-2.json').distSize)") | $(node -e "console.log(require('./docs/module-build/snapshots/$DATE/profile-2.json').jsChunkCount)") js
profile 3 (3hk):     $(node -e "console.log(require('./docs/module-build/snapshots/$DATE/profile-3.json').distMd5)") | $(node -e "console.log(require('./docs/module-build/snapshots/$DATE/profile-3.json').distSize)") | $(node -e "console.log(require('./docs/module-build/snapshots/$DATE/profile-3.json').jsChunkCount)") js

回滚方法见 docs/module-build/snapshots/$DATE/README.md

🤖 Generated with [MiniMax-M3]

Co-Authored-By: Claude <noreply@anthropic.com>" 2>&1 | tail -5

# 5. push
echo ""
echo "Pushing $BRANCH to origin..."
git push -u origin "$BRANCH" 2>&1 | tail -5

# 6. 回到原分支
echo ""
echo "Returning to feat/online-order..."
git checkout feat/online-order 2>&1 | tail -3

# 7. pop stash (如果有)
if [ "$STASHED" = "1" ]; then
  echo ""
  echo "Popping auto-stash..."
  git stash pop 2>&1 | tail -3 || echo "⚠️  stash pop 冲突, 请手动处理: git stash list"
fi

echo ""
echo "✅ Backup done: branch $BRANCH"
echo "   Profile 1: $(node -e "console.log(require('./docs/module-build/snapshots/$DATE/profile-1.json').distMd5)")"
echo "   Profile 2: $(node -e "console.log(require('./docs/module-build/snapshots/$DATE/profile-2.json').distMd5)")"
echo "   Profile 3: $(node -e "console.log(require('./docs/module-build/snapshots/$DATE/profile-3.json').distMd5)")"