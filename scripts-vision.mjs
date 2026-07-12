import puppeteer from 'puppeteer';
const TOKEN = process.env.TOKEN;
const browser = await puppeteer.launch({headless: 'new', args:['--no-sandbox','--disable-setuid-sandbox']});
const page = await browser.newPage();
const errors = [];
page.on('console', m => { if(m.type()==='error') errors.push('C:'+m.text().substring(0,200)); });
page.on('pageerror', e => errors.push('P:'+e.message.substring(0,200)));
await page.setViewport({width: 1280, height: 800});
await page.goto('https://wecom.gdqshop.cn/login', {waitUntil:'domcontentloaded', timeout: 20000}).catch(()=>{});
await page.evaluate((t) => {
  localStorage.setItem('caimeite_token', t);
  localStorage.setItem('caimeite_user', JSON.stringify({id:9, name:'江清波', role:'admin'}));
}, TOKEN);
await page.goto('https://wecom.gdqshop.cn/server-profiles', {waitUntil:'networkidle0', timeout: 30000}).catch(e=>console.log('nav1:',e.message));
await new Promise(r=>setTimeout(r, 4000));
await page.screenshot({path:'/tmp/sp1.png', fullPage:false});
const body = await page.evaluate(() => document.body.innerText.substring(0,800));
console.log('URL1:', page.url());
console.log('BODY1:', body.replace(/\n/g,' | '));
console.log('ERR1:', errors.join(' / '));
// 触发 reload
await page.reload({waitUntil:'networkidle0', timeout: 20000}).catch(()=>{});
await new Promise(r=>setTimeout(r, 3000));
await page.screenshot({path:'/tmp/sp2.png', fullPage:false});
const body2 = await page.evaluate(() => document.body.innerText.substring(0,800));
console.log('URL2:', page.url());
console.log('BODY2:', body2.replace(/\n/g,' | '));
await browser.close();
