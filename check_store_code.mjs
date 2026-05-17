import mysql from 'mysql2/promise';
const pool = mysql.createPool({
    host: 'localhost',
    user: 'gdq',
    password: 'Re78g0A1XcNmr1T8',
    database: 'gdq',
    socketPath: '/run/mysqld/mysqld.sock',
    waitForConnections: true,
    connectionLimit: 5
});
const [rows] = await pool.query('SELECT id, name, store_code FROM stores LIMIT 5');
console.log(JSON.stringify(rows));
await pool.end();
