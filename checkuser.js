const mysql = require('mysql2');
const conn = mysql.createConnection({host:'localhost',user:'gdq',password:'Re78g0A1XcNmr1T8',database:'gdq'});
conn.query("SELECT id,name,phone,email FROM users WHERE phone LIKE '%76970008%' LIMIT 5", (e,r) => {
  console.log(JSON.stringify(r));
  conn.end();
});