const test=require('node:test'),assert=require('node:assert/strict'),vm=require('node:vm'),fs=require('node:fs'),path=require('node:path');
test('Le bouton attend un devis valide ; double clic et reprise réseau conservent la même requête',async()=>{
 class Node{
  constructor(tag){this.tag=tag;this.children=[];this.dataset={};this.listeners={};this.value='';this.disabled=false;this.textContent='';}
  append(...nodes){this.children.push(...nodes);if(this.tag==='select'&&!this.value&&nodes[0])this.value=nodes[0].value;}
  replaceChildren(...nodes){this.children=nodes;}setAttribute(k,v){this[k]=v;}addEventListener(k,v){this.listeners[k]=v;}
 }
 const ids={};for(const id of ['wy-model','wy-groups','wy-offers','wy-quantity','wy-add-cart','wy-cart-message','wy-canvas','wy-price','wy-stock','wy-status'])ids['#'+id]=new Node(id==='wy-model'?'select':'div');
 ids['#wy-quantity'].value='1';ids['#wy-add-cart'].disabled=true;const details=new Node('div'),caption=new Node('p');
 const walk=n=>n.children.flatMap(c=>[c,...walk(c)]);
 const root={querySelector:s=>ids[s]||(s==='.wy-detail-images'?details:caption),querySelectorAll:s=>s==='.wy-option'?walk(ids['#wy-groups']).filter(n=>n.className==='wy-option'):[ids['#wy-model'],ids['#wy-quantity'],...walk(ids['#wy-offers']),...walk(ids['#wy-groups']).filter(n=>n.className==='wy-option')]};
 const catalog=JSON.parse(fs.readFileSync(path.join(__dirname,'../integration/prestashop/catalog.json')));const m=catalog.models[0];
 const data={token:'session-token',apiUrl:'/quote',cartUrl:'/add',models:[{id:m.id,label:m.label,groups:Object.entries(m.options).map(([id,opts])=>({id,label:id,options:Object.entries(opts).slice(0,1).map(([id,o])=>({id,label:o.label,image:'/'+id+'.png',status:{exists:true,referenceMatches:true,active:true,orderable:true,quantity:10,available:true}}))})),offers:m.offers}]};
 const calls=[],timers=[];let fail=true,redirect=null;
 const ctx=vm.createContext({document:{getElementById:id=>id==='watchys-configurator'?root:{textContent:JSON.stringify(data)},createElement:t=>new Node(t)},WatchysPreview:{Preview:class{async render(){}}},Intl,JSON,Number,URL,AbortController,crypto:require('node:crypto').webcrypto,
  setTimeout:fn=>{timers.push(fn);return timers.length;},clearTimeout:()=>{},location:{origin:'https://dev.watchys.ch',href:'https://dev.watchys.ch/configure',assign:u=>redirect=u},fetch:async(url,args)=>{
   calls.push({url,body:JSON.parse(args.body)});
   if(url==='/quote')return{ok:true,json:async()=>({ok:true,quote:{totalExclTaxMinor:19000,componentsAvailable:true,cartEnabled:true,components:[]}})};
   if(fail){fail=false;throw Error('Connexion interrompue');}
   return{ok:true,json:async()=>({ok:true,result:{cartUrl:'/panier?action=show'}})};
  }});
 vm.runInContext(fs.readFileSync(path.join(__dirname,'../modules/watchysconfigurator/views/js/configurator-cart-v020.js'),'utf8'),ctx);
 assert.deepEqual(ids['#wy-groups'].children.map(field=>field.children[0].textContent),['case','dial','hands','strap']);
 assert.deepEqual(details.children.map(figure=>figure.children[0].src),['/case-polished.png','/dial-black.png','/hands-black.png','/strap-black.png']);
 assert.deepEqual(data.models[0].groups.map(group=>group.id),['dial','strap','hands','case']);
 assert.equal(ids['#wy-add-cart'].disabled,true);await timers.at(-1)();assert.equal(ids['#wy-add-cart'].disabled,false);
 await Promise.all([ids['#wy-add-cart'].listeners.click(),ids['#wy-add-cart'].listeners.click()]);
 let adds=calls.filter(c=>c.url==='/add');assert.equal(adds.length,1);assert.equal(ids['#wy-add-cart'].disabled,false);
 await ids['#wy-add-cart'].listeners.click();adds=calls.filter(c=>c.url==='/add');assert.equal(adds.length,2);assert.equal(adds[0].body.requestKey,adds[1].body.requestKey);
 assert.equal(adds[0].body.price,undefined);assert.equal(adds[0].body.productId,undefined);assert.equal(adds[0].body.token,'session-token');assert.equal(redirect,'https://dev.watchys.ch/panier?action=show');
});
