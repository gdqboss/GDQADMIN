import puppeteer from 'puppeteer';
const TOKEN = process.env.TOKEN;
const browser = await puppeteer.launch({headless: 'new', args:['--no-sandbox','--disable-setuid-sandbox']});
const page = await browser.newPage();
const errors = [];
page.on('console', m => { if(m.type()==='error') errors.push('C:'+m.text().substring(0,150)); });
page.on('pageerror', e => errors.push('P:'+e.message.substring(0,150)));
await page.setViewport({width: 1400, height: 900});
await page.goto('https://wecom.gdqshop.cn/login', {waitUntil:'domcontentloaded', timeout: 20000}).catch(()=>{});
await page.evaluate((t) => {
  localStorage.setItem('caimeite_token', t);
  localStorage.setItem('caimeite_user', JSON.stringify({id:9, name:'江清波', role:'admin', email:'18676970008'}));
}, TOKEN);
await page.goto('https://wecom.gdqshop.cn/server-profiles', {waitUntil:'networkidle0', timeout: 30000}).catch(e=>console.log('nav:',e.message));
await new Promise(r=>setTimeout(r, 4000));
await page.screenshot({path:'/tmp/sp-after.png', fullPage:false});
const body = await page.evaluate(() => document.body.innerText.substring(0,1500));
console.log('URL:', page.url());
console.log('BODY:', body.replace(/\n/g,' | '));
console.log('ERR:', errors.slice(0,8).join(' || '));
// 列侧栏所有菜单
const menus = await page.evaluate(() => {
  const items = Array.from(document.querySelectorAll('a, .el-menu-item, .menu-item, [class*="menu"]'));
  return items.map(i => i.innerText.trim()).filter(t => t.length > 0 && t.length < 20).slice(0, 50);
});
console.log('MENUS:', menus.join('|'));
await browser.close();