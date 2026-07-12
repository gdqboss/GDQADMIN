import bcrypt from 'bcryptjs'
import { pool } from './db/connection.js'

const newPwd = '12345678'
const hash = await bcrypt.hash(newPwd, 10)

await pool.query("UPDATE users SET password = ?, password_updated_at = NOW() WHERE id IN (9, 10028, 10031)", [hash, hash])
const [rows] = await pool.query("SELECT id, name, phone, user_type, customer_type, member_level FROM users WHERE id IN (9, 10028, 10031)")
console.table(rows)
await pool.end()
