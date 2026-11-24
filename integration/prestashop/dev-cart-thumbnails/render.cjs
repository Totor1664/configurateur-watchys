const fs=require('node:fs'),vm=require('node:vm'),path=require('node:path'),crypto=require('node:crypto');
const [assetRoot,outDir,canvasPath]=process.argv.slice(2);
if(!assetRoot||!outDir||!canvasPath)throw Error('Arguments: reference-assets output canvas-module');
const {createCanvas,loadImage}=require(path.resolve(canvasPath));
const root=path.resolve(__dirname,'../../..');
class El{
 constructor(tag){this.tag=tag;this.attrs={};this.children=[];this.style={};this.classList={add:()=>{}};this.textContent='';}
 setAttribute(k,v){this.attrs[k]=String(v);}getAttribute(k){return this.attrs[k];}get id(){return this.attrs.id;}
 append(...nodes){this.children.push(...nodes);}
}
const doc={createElementNS:(ns,t)=>new El(t)};
const metadata=fs.readFileSync(path.join(root,'tests/fixtures/reference-ghpages/model-assets.js'),'utf8').split('\n').filter(l=>!l.startsWith('export const assetUrl')).join('\n').replaceAll('export ','');
const engine=fs.readFileSync(path.join(root,'tests/fixtures/reference-ghpages/generated-pilot.js'),'utf8').split('\n').filter(l=>!l.startsWith('import ')).join('\n').replaceAll('export ','');
const uris={};const context=vm.createContext({document:doc});
vm.runInContext(`let groups=[];const assetUrl=n=>uris[n];${metadata}\n${engine}\nglobalThis.create=createPilotPreview;globalThis.defaultSelection=pilotSelection;globalThis.assets=assetGroups;globalThis.bind=g=>groups=g;`,Object.assign(context,{uris}));
const catalog=JSON.parse(fs.readFileSync(path.join(root,'integration/prestashop/catalog.json'),'utf8')).models[0];
const alias={'case-pvd-polished':'case-black-pvd','case-pvd-brushed':'case-black-brushed','hands-dauphine-blue':'hands-dauphine-royal-blue','hands-dauphine-yellow-gold':'hands-dauphine-gold'};
context.bind(Object.entries(catalog.options).map(([id,options])=>({id,label:id,options:Object.entries(options).map(([id,o])=>({id:alias[id]||id,label:o.label}))})));
const hashes={};for(const group of Object.values(context.assets))for(const a of Object.values(group)){
 const p=path.join(assetRoot,a.name+'.png'),bytes=fs.readFileSync(p);uris[a.name]='data:image/png;base64,'+bytes.toString('base64');hashes[a.name]=crypto.createHash('sha256').update(bytes).digest('hex');
}
const esc=s=>String(s).replaceAll('&','&amp;').replaceAll('"','&quot;').replaceAll('<','&lt;').replaceAll('>','&gt;');
function serialize(el){if(el.style.display==='none')return '';const a={...el.attrs};if(el.tag==='svg'&&el.attrs.viewBox==='0 0 1254 1254')a.xmlns='http://www.w3.org/2000/svg';if(el.style.overflow)a.style='overflow:'+el.style.overflow;return '<'+el.tag+Object.entries(a).map(([k,v])=>' '+k+'="'+esc(v)+'"').join('')+'>'+esc(el.textContent)+el.children.map(serialize).join('')+'</'+el.tag+'>';}
const digest=s=>crypto.createHash('sha256').update([catalog.id,...['case','dial','hands','strap'].map(g=>s[g])].join('|')).digest('hex');
async function drawLayers(){const layers={};for(const group of ['strap','dial','case','hands']){
 layers[group]={};for(const id of Object.keys(catalog.options[group])){
  const selection={...context.defaultSelection,[group]:alias[id]||id};
  const svg=serialize(context.create([group],selection));
  layers[group][id]=await loadImage(Buffer.from(svg));
 }
 process.stdout.write('Calques vérifiés : '+group+'\n');
 }
 layers.date=await loadImage(Buffer.from(serialize(context.create(['date'],context.defaultSelection))));return layers;
}
(async()=>{
 fs.mkdirSync(outDir,{recursive:true});const layers=await drawLayers(),manifest={schemaVersion:1,modelId:catalog.id,referenceCommit:'faec62714c571ff78ff3b27a389727b7c45921f6',width:380,height:494,sources:hashes,files:{}};
 let count=0;for(const ca of Object.keys(catalog.options.case))for(const di of Object.keys(catalog.options.dial))for(const ha of Object.keys(catalog.options.hands))for(const st of Object.keys(catalog.options.strap)){
 const selection={case:ca,dial:di,hands:ha,strap:st};const canvas=createCanvas(380,494),ctx=canvas.getContext('2d');ctx.fillStyle='#fff';ctx.fillRect(0,0,380,494);
 for(const layer of [layers.strap[st],layers.dial[di],layers.date,layers.case[ca],layers.hands[ha]])ctx.drawImage(layer,0,57,380,380);
 const bytes=canvas.toBuffer('image/jpeg',90),name=digest(selection)+'.jpg';fs.writeFileSync(path.join(outDir,name),bytes);manifest.files[name]={sha256:crypto.createHash('sha256').update(bytes).digest('hex'),selection};count++;
 }
 if(count!==960)throw Error('Nombre inattendu');fs.writeFileSync(path.join(outDir,'manifest.json'),JSON.stringify(manifest,null,2)+'\n');console.log('SUCCÈS — '+count+' miniatures générées.');
})().catch(e=>{console.error(e);process.exitCode=1;});
