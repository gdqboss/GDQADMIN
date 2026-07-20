import { createConnection } from 'mysql2/promise'
const db = await createConnection({ host: '127.0.0.1', port: 3306, user: 'gdq', password: 'Re78g0A1XcNmr1T8', database: 'gdq' })
const [emps] = await db.query("SELECT id, name, phone, role FROM users WHERE role = 'employee' LIMIT 5")
const [admins] = await db.query("SELECT id, name, phone, role FROM users WHERE role = 'admin' LIMIT 5")
const [testM] = await db.query("SELECT id, name, phone, role FROM users WHERE role = 'manager' LIMIT 5")
console.log('employees:', emps)
console.log('admins:', admins)
console.log('managers:', testM)
await db.end()
