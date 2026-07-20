import { createConnection } from 'mysql2/promise'
const db = await createConnection({ host: '127.0.0.1', port: 3306, user: 'gdq', password: 'Re78g0A1XcNmr1T8', database: 'gdq' })
for (const t of ['expense_records', 'minip_join_applications', 'users', 'minip_modules']) {
  const [r] = await db.query(`SHOW CREATE TABLE ${t}`)
  console.log(`\n===== ${t} =====`)
  console.log(r[0]['Create Table'].replace(/AUTO_INCREMENT=\d+/g, ''))
}
await db.end()
