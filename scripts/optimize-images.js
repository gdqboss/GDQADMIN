#!/usr/bin/env node
/**
 * 2026-08-11: 图片优化 cron
 * - 扫描 /home/gdq/server/uploads/smart-studio/ 下所有未优化图片 (无 _thumb.webp)
 * - 用 sharp 重新处理: 主图压缩 + thumbnail + EXIF strip + WebP
 * - 删原文件 (用 sharp 处理后的 .webp 替换)
 * - 加 image_url 关联 + thumbnail_url 更新到 DB (如果 image_url 是这个文件)
 *
 * 建议: 每天凌晨 3 点跑一次 (避免高峰期)
 * 使用: node scripts/optimize-images.js
 */

import fs from 'fs'
import path from 'path'
import sharp from 'sharp'
import { pool } from '../db/connection.js'

const UPLOAD_DIR = '/home/gdq/server/uploads/smart-studio'
const PUBLIC_BASE = '/smart-studio/uploads'

async function main() {
  console.log('[image-optimize] start', new Date().toISOString())
  let optimized = 0
  let skipped = 0
  let failed = 0

  async function walk(dir) {
    const entries = await fs.promises.readdir(dir, { withFileTypes: true })
    for (const ent of entries) {
      const full = path.join(dir, ent.name)
      if (ent.isDirectory()) { await walk(full); continue }
      // 只处理 jpg/jpeg/png/gif (非 webp)
      if (!/\.(jpg|jpeg|png|gif)$/i.test(ent.name)) { skipped++; continue }
      // skip 已优化 (旁边有 _thumb.webp)
      const base = ent.name.replace(/\.[^.]+$/, '')
      const thumb = path.join(dir, base + '_thumb.webp')
      try { await fs.promises.access(thumb); skipped++; continue } catch (e) {}

      try {
        const ext = path.extname(ent.name).slice(1).toLowerCase()
        const subdir = path.relative(UPLOAD_DIR, dir).replace(/\\/g, '/')
        const mainName = base + '.webp'
        const mainPath = path.join(dir, mainName)

        const sharp1 = sharp(full, { failOnError: false }).rotate()
        const meta = await sharp1.metadata()
        const mainBuf = await sharp1
          .resize({ width: 1920, height: 1920, fit: 'inside', withoutEnlargement: true })
          .webp({ quality: 82, effort: 4 })
          .toBuffer()
        await fs.promises.writeFile(mainPath, mainBuf)

        const thumbBuf = await sharp(full, { failOnError: false })
          .rotate()
          .resize({ width: 300, height: 300, fit: 'cover' })
          .webp({ quality: 75, effort: 4 })
          .toBuffer()
        await fs.promises.writeFile(thumb, thumbBuf)

        // 删原文件
        await fs.promises.unlink(full)

        // 更新 DB (如果这个文件路径被引用)
        const oldUrl = `${PUBLIC_BASE}/${subdir}/${ent.name}`
        const newUrl = `${PUBLIC_BASE}/${subdir}/${mainName}`
        const newThumb = `${PUBLIC_BASE}/${subdir}/${base}_thumb.webp`
        await pool.query(
          'UPDATE smart_studio_messages SET image_url=?, thumbnail_url=? WHERE image_url=?',
          [newUrl, newThumb, oldUrl]
        )

        optimized++
      } catch (e) {
        console.error(`[image-optimize] FAIL ${full}:`, e.message)
        failed++
      }
    }
  }

  await walk(UPLOAD_DIR)
  console.log(`[image-optimize] done: optimized=${optimized} skipped=${skipped} failed=${failed}`)
  process.exit(0)
}

main().catch(e => { console.error(e); process.exit(1) })
