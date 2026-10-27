import {execFileSync} from 'node:child_process';
import os from 'node:os';
import path from 'node:path';
const {chromium}=await import(process.env.WATCHYS_PLAYWRIGHT_MODULE||'playwright');
const output=fs.mkdtempSync(path.join(os.tmpdir(),'watchys-browser-'));
const artifact=name=>path.join(output,name);
import assert from 'node:assert/strict';
import fs from 'node:fs';
const url=process.env.WATCHYS_URL||'http://127.0.0.1:5173/';
const browser=await chromium.launch({executablePath:process.env.WATCHYS_CHROMIUM_PATH||undefined,args:['--no-sandbox']});
const page=await browser.newPage({viewport:{width:1440,height:1100}}),errors=[],failed=[];
page.on('pageerror',e=>errors.push(e.message));page.on('response',r=>{if(r.status()>=400)failed.push(r.url()+' '+r.status());});
await page.goto(url);await page.waitForSelector('.choice');
await page.evaluate(()=>{localStorage.setItem('watchys.cart.v1','old-production-cart');localStorage.removeItem('watchys.validation.cart.v1');});await page.reload();
assert.equal(await page.locator('.choice').count(),23);assert.equal(await page.locator('.choice .component-thumbnail').count(),23);
const result=await page.evaluate(async()=>{
 const {groups,defaultSelection}=await import('./src/catalog.js?v=classic-7750-1');
 const {allSelections,configurationId}=await import('./src/selection.js?v=classic-7750-1');
 const {createPilotPreview}=await import('./src/generated-pilot.js?v=classic-7750-1');
 const cached={},list=allSelections(),normalize=el=>el.outerHTML.replace(/pilot-\d+/g,'pilot-ID');
 for(const s of list){
  // Exercise the actual click handlers, preview, summary and price for all 960.
  for(const g of groups)document.querySelector(`[data-option="${s[g.id]}"]`).click();
  if(!document.querySelector('#price').textContent.includes('490'))throw Error('Price '+configurationId(s));
  const svg=document.querySelector('#image-area > svg');if(!svg)throw Error('Preview '+configurationId(s));
  if(svg.getAttribute('viewBox')!=='0 0 1254 1254')throw Error('Frame changed');
  for(const g of [...groups,{id:'date'}]){
   const layer=normalize(svg.querySelector(`[data-pilot-layer="${g.id}"]`));
   const key=g.id+':'+(s[g.id]||'common');
   if(cached[key]&&cached[key]!==layer)throw Error('Independent layer changed: '+key);
   cached[key]=layer;
  }
  if(document.querySelectorAll('.choice[aria-pressed="true"]').length!==4)throw Error('Selection state');
  if(!groups.every(g=>document.querySelector('#summary').textContent.includes(g.options.find(o=>o.id===s[g.id]).label)))throw Error('Summary mismatch');
 }
 for(const g of groups)document.querySelector(`[data-option="${defaultSelection[g.id]}"]`).click();
 return {combinations:list.length,independentLayerVariants:Object.keys(cached).length};
});assert.deepEqual(result,{combinations:960,independentLayerVariants:24});
console.log('PASS 960 UI configurations: preview, summary, 490 CHF, 24 independent layer variants');
// Preserve the approved default exactly, compare browser raster output.
await page.locator('#image-area > svg').screenshot({path:artifact('classic-approved-current.png')});
const old=execFileSync('git',['show','a204f7a:src/generated-pilot.js'],{encoding:'utf8'}).replace('new URL(`../public/generated-7750/${name}.png`,import.meta.url).href','new URL(`./public/generated-7750/${name}.png`,document.baseURI).href');
await page.evaluate(async source=>{
 const {createPilotPreview}=await import('data:text/javascript;base64,'+btoa(source));const svg=createPilotPreview();svg.classList.remove('pilot-watch');svg.classList.add('watch-simulation');document.querySelector('#image-area').replaceChildren(svg);
},old);
await page.locator('#image-area > svg').screenshot({path:artifact('classic-approved-original.png')});
assert.ok(fs.readFileSync(artifact('classic-approved-original.png')).equals(fs.readFileSync(artifact('classic-approved-current.png'))),'Approved default raster changed');
console.log('PASS approved blue/blue/chrome/polished is pixel-identical');
await page.reload();
// One of each dial, leather, hand family and case, plus a restored old cart row.
for(const option of ['dial-grey','strap-red','hands-dauphine-rose-gold','case-black-pvd'])await page.locator(`[data-option="${option}"]`).click();
await page.locator('#add').click();await page.locator('#add').click();assert.equal(await page.locator('.cart-item').count(),1);assert.match(await page.locator('#total').innerText(),/980/);
await page.locator('[data-option="strap-orange"]').click();await page.locator('#add').click();assert.equal(await page.locator('.cart-item').count(),2);assert.match(await page.locator('#total').innerText(),/1.?470/);
await page.reload();assert.equal(await page.locator('.cart-item').count(),2);assert.equal(await page.locator('[data-quantity]').first().inputValue(),'2');
await page.locator('[data-quantity]').first().fill('3');await page.locator('[data-quantity]').first().dispatchEvent('change');assert.match(await page.locator('#total').innerText(),/1.?960/);
await page.locator('[data-remove]').last().click();assert.equal(await page.locator('.cart-item').count(),1);assert.match(await page.locator('#total').innerText(),/1.?470/);
assert.equal(await page.evaluate(()=>localStorage.getItem('watchys.cart.v1')),'old-production-cart');
const ids=await page.locator('[id]').evaluateAll(elements=>elements.map(e=>e.id));assert.equal(ids.length,new Set(ids).size);
for(const width of [1440,768,390,320]){await page.setViewportSize({width,height:1000});assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Horizontal overflow at '+width);}
await page.setViewportSize({width:390,height:844});await page.evaluate(()=>window.scrollTo(0,0));await page.screenshot({path:artifact('watchys-classic-mobile.png')});
await page.setViewportSize({width:1440,height:1100});await page.screenshot({path:artifact('watchys-classic-desktop.png'),fullPage:true});
assert.deepEqual(errors,[]);assert.deepEqual(failed,[]);
console.log('PASS cart merge/quantities/delete/persistence, original cart untouched, unique IDs, mobile, all assets HTTP 200, no JS errors');
await browser.close();
console.log('Screenshots: '+output);
