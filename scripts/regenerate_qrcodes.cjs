const mysql = require('mysql2/promise');
const QRCode = require('qrcode');
const path = require('path');
const fs = require('fs');

async function main() {
  const pool = mysql.createPool({
    host: 'localhost',
    user: 'gdq',
    password: 'Re78g0A1XcNmr1T8',
    database: 'gdq',
    socketPath: '/run/mysqld/mysqld.sock',
    waitForConnections: true,
  });

  const [rows] = await pool.query('SELECT id, code FROM qrcodes ORDER BY id');
  const dir = '/home/gdq/server/uploads/qrcodes';
  await fs.promises.mkdir(dir, { recursive: true });

  let ok = 0, fail = 0;
  for (const qr of rows) {
    try {
      const url = `https://wecom.gdqshop.cn/#/scan/${qr.code}`;
      const filePath = path.join(dir, `${qr.code}.png`);
      await QRCode.toFile(filePath, url, { width: 300, margin: 2 });
      console.log(`✅ ${qr.code} → ${url}`);
      ok++;
    } catch (e) {
      console.error(`❌ ${qr.code}: ${e.message}`);
      fail++;
    }
  }
  console.log(`\n完成: ${ok} 成功, ${fail} 失败`);
  await pool.end();
}

main().catch(console.error);
