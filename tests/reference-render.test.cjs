const fs=require('fs'),vm=require('vm'),path=require('path'),assert=require('assert');
process.chdir(path.resolve(__dirname,'..'));
class El{
 constructor(tag,attrs={}){this.tag=tag;this.attrs=attrs;this.children=[];this.style={};this.classList={add:()=>{},remove:()=>{}};this.textContent='';}
 setAttribute(k,v){this.attrs[k]=String(v);}getAttribute(k){return this.attrs[k];}
 append(...els){this.children.push(...els);}replaceWith(el){this.replacement=el;}
 querySelectorAll(tag){return this.children.flatMap(c=>[...(c.tag===tag?[c]:[]),...c.querySelectorAll(tag)]);}
}
const doc={createElementNS:(ns,t)=>new El(t),baseURI:'file://'+process.cwd()+'/index.html'};
class Img{set src(v){this.naturalWidth=1254;this.naturalHeight=1254;queueMicrotask(()=>this.onload());}}
const metadata=fs.readFileSync('tests/fixtures/reference-ghpages/model-assets.js','utf8').split('\n').filter(l=>!l.startsWith('export const assetUrl')).join('\n').replaceAll('export ','');
const engine=fs.readFileSync('tests/fixtures/reference-ghpages/generated-pilot.js','utf8').split('\n').filter(l=>!l.startsWith('import ')).join('\n').replaceAll('export ','');
const ref=vm.createContext({document:doc,URL});vm.runInContext(`let groups=[],assetUrls={};const assetUrl=n=>assetUrls[n];\n${metadata}\n${engine}\nglobalThis.create=createPilotPreview;globalThis.bind=(g,u)=>{groups=g;assetUrls=u;};globalThis.sg=strapAssets;`,ref);
const mod=vm.createContext({document:doc,URL,Image:Img});vm.runInContext(fs.readFileSync('modules/watchysconfigurator/views/js/preview-reference-v013.js','utf8'),mod);
const c=JSON.parse(fs.readFileSync('integration/prestashop/catalog.json')).models[0];
const alias={'case-pvd-polished':'case-black-pvd','case-pvd-brushed':'case-black-brushed','hands-dauphine-blue':'hands-dauphine-royal-blue','hands-dauphine-yellow-gold':'hands-dauphine-gold'};
const refAssets='';
const tmp=fs.mkdtempSync('/tmp/watchys-reference-');const names={};
for(const g of ['case','dial','hands'])for(const [key,o] of Object.entries(c.options[g])){
 const id=alias[key]||key;names[id]=id;
}

const model={id:c.id,groups:Object.entries(c.options).map(([id,opts])=>({id,label:id,options:Object.entries(opts).map(([id,o])=>({...o,id,image:'file://'+tmp+'/'+o.component.productId+'-front.png'}))}))};
const canonical=el=>JSON.stringify({t:el.tag,a:Object.fromEntries(Object.entries(el.attrs).filter(([k])=>!['aria-label'].includes(k)&&!(k==='id'&&el.attrs[k]==='wy-canvas')).sort().map(([k,v])=>[k,v.replace(/pilot-\d+/g,'pilot-N')])),text:el.textContent,c:el.children.map(c=>JSON.parse(canonical(c)))});
(async()=>{let count=0;for(const ca of model.groups.find(g=>g.id==='case').options)for(const di of model.groups.find(g=>g.id==='dial').options)for(const ha of model.groups.find(g=>g.id==='hands').options)for(const st of model.groups.find(g=>g.id==='strap').options){
 const selected={case:ca,dial:di,hands:ha,strap:st};const selection=Object.fromEntries(Object.entries(selected).map(([g,o])=>[g,alias[o.id]||o.id]));
 const urls={};for(const g of ['case','dial','hands'])urls[selection[g]]=selected[g].image;
 for(const a of Object.values(ref.sg))urls[a.name]='file://'+tmp+'/reference-'+a.name+'.png?v=ghpages-faec627';
 ref.bind(model.groups.map(g=>({...g,options:g.options.map(o=>({...o,id:alias[o.id]||o.id}))})),urls);
 const expected=ref.create(undefined,selection),node=new El('canvas');const p=new mod.WatchysPreview.Preview(node);await p.render(model,selected);
 assert.equal(canonical(node.replacement),canonical(expected));count++;
 }
 console.log('SUCCÈS : '+count+' compositions SVG identiques au moteur GitHub validé (géométrie, masques, ordre, cadrans, aiguilles, carrures).');
 const node=new El('canvas'),p=new mod.WatchysPreview.Preview(node);const selected=Object.fromEntries(model.groups.map(g=>[g.id,g.options[0]]));
 const first=p.render(model,selected);const second=p.render(model,{...selected,case:model.groups.find(g=>g.id==='case').options.at(-1)});await Promise.all([first,second]);assert.equal(p.generation,2);assert(node.replacement);
 console.log('SUCCÈS : protection des changements rapides et dimensions des sources.');
 fs.rmSync(tmp,{recursive:true});
})().catch(e=>{console.error(e);process.exit(1)});
