/**
 * 数据库连接 - 新加坡本地MySQL
 */
import mysql from 'mysql2/promise';

const pool = mysql.createPool({
    host: 'localhost',
    user: 'gdq',
    password: 'Re78g0A1XcNmr1T8',
    database: 'gdq',
    socketPath: '/run/mysqld/mysqld.sock',
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
});

export { pool };
