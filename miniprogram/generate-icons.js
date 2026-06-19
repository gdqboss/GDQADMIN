const zlib = require('zlib')
const fs = require('fs')
const path = require('path')

function createChunk(type, data) {
  const length = Buffer.alloc(4)
  length.writeUInt32BE(data.length, 0)
  const typeBuffer = Buffer.from(type, 'ascii')
  const crcData = Buffer.concat([typeBuffer, data])
  const crc = crc32(crcData)
  const crcBuffer = Buffer.alloc(4)
  crcBuffer.writeUInt32BE(crc >>> 0, 0)
  return Buffer.concat([length, typeBuffer, data, crcBuffer])
}

const crcTable = []
for (let n = 0; n < 256; n++) {
  let c = n
  for (let k = 0; k < 8; k++) {
    c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1
  }
  crcTable[n] = c
}

function crc32(buf) {
  let crc = 0xffffffff
  for (let i = 0; i < buf.length; i++) {
    crc = crcTable[(crc ^ buf[i]) & 0xff] ^ (crc >>> 8)
  }
  return crc ^ 0xffffffff
}

function createPNG(width, height, r, g, b, a = 255) {
  const signature = Buffer.from([137, 80, 78, 71, 13, 10, 26, 10])

  const ihdrData = Buffer.alloc(13)
  ihdrData.writeUInt32BE(width, 0)
  ihdrData.writeUInt32BE(height, 4)
  ihdrData[8] = 8
  ihdrData[9] = 6
  ihdrData[10] = 0
  ihdrData[11] = 0
  ihdrData[12] = 0
  const ihdr = createChunk('IHDR', ihdrData)

  const raw = Buffer.alloc((width * 4 + 1) * height)
  for (let y = 0; y < height; y++) {
    raw[y * (width * 4 + 1)] = 0
    for (let x = 0; x < width; x++) {
      const idx = y * (width * 4 + 1) + 1 + x * 4
      raw[idx] = r
      raw[idx + 1] = g
      raw[idx + 2] = b
      raw[idx + 3] = a
    }
  }
  const compressed = zlib.deflateSync(raw)
  const idat = createChunk('IDAT', compressed)

  const iend = createChunk('IEND', Buffer.alloc(0))

  return Buffer.concat([signature, ihdr, idat, iend])
}

const tabbarDir = path.join(__dirname, 'static', 'tabbar')
if (!fs.existsSync(tabbarDir)) {
  fs.mkdirSync(tabbarDir, { recursive: true })
}

const size = 48
const gray = { r: 122, g: 126, b: 131 }
const blue = { r: 0, g: 122, b: 255 }

const icons = ['home', 'dashboard', 'member']
icons.forEach(name => {
  fs.writeFileSync(path.join(tabbarDir, `${name}.png`), createPNG(size, size, gray.r, gray.g, gray.b))
  fs.writeFileSync(path.join(tabbarDir, `${name}-active.png`), createPNG(size, size, blue.r, blue.g, blue.b))
  console.log(`Created ${name}.png and ${name}-active.png`)
})

console.log('All tabbar icons generated successfully!')
