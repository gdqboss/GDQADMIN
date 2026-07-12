import sharp from 'sharp'
import fs from 'fs'
import path from 'path'

// 压缩图片函数
export async function compressImage(filePath, maxWidth = 1280, quality = 80) {
  const ext = path.extname(filePath).toLowerCase()
  if (!['.jpg', '.jpeg', '.png', '.webp'].includes(ext)) return false
  
  try {
    // PNG文件只压缩不转格式
    if (ext === '.png') {
      const compressedPath = filePath.replace('.png', '_compressed.png')
      await sharp(filePath)
        .resize(maxWidth, null, { withoutEnlargement: true })
        .png({ quality, compressionLevel: 9 })
        .toFile(compressedPath)
      fs.unlinkSync(filePath)
      fs.renameSync(compressedPath, filePath)
      return true
    }
    
    // JPG/WEBP转JPEG压缩
    const compressedPath = filePath.replace(ext, '_compressed.jpg')
    await sharp(filePath)
      .resize(maxWidth, null, { withoutEnlargement: true })
      .jpeg({ quality })
      .toFile(compressedPath)
    fs.unlinkSync(filePath)
    fs.renameSync(compressedPath, filePath.replace(ext, '.jpg'))
    return true
  } catch (e) {
    console.error('压缩失败:', e.message)
    // 压缩失败不影响上传，返回false但不让整个流程崩溃
    return false
  }
}
