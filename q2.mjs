import { createConnection } from 'mysql2/promise'
const db = await createConnection({ host: '127.0.0.1', port: 3306, user: 'gdq', password: 'Re78g0A1XcNmr1T8', database: 'gdq' })
const tables = ['minip_employees', 'minip_wallet_transactions', 'minip_join_applications', 'expense_records', 'notifications']
for (const t of tables) {
  const [r] = await db.query(`SHOW TABLES LIKE '${t}'`)
  console.log(t, ':', r.length > 0 ? 'exists' : 'MISSING')
}
await db.end()
