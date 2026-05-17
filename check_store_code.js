const { pool } = require('/root/server/db/connection.js');
pool.query('SELECT id, name, store_code FROM stores LIMIT 5').then(([r]) => console.log(JSON.stringify(r))).catch(e => console.log(e));