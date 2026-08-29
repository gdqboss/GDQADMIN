/**
 * 数据库连接 - 3号仓库 (no3) 本地 MySQL
 * 乐高积木铁律: DB 配置应从 server_profiles 读 (TODO 重构)
 * 当前临时硬编 (no3 mysql gdq user password = Caimeite@2025, database = gdq_3)
 */
import mysql from 'mysql2/promise';

const pool = mysql.createPool({
    host: 'localhost',
    user: 'gdq',
    password: 'Caimeite@2025',
    database: 'gdq_3',
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
});

export { pool };