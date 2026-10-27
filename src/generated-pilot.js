// First composition only, assembled from the independent transparent 7750.zip assets.
export const pilotSelection=Object.freeze({dial:'dial-blue',strap:'strap-blue',hands:'hands-chrome',case:'case-polished'});
export const pilotAsset=Object.freeze({path:'public/review/blue-blue-chrome-polished.png',width:1254,height:1254,status:'awaiting-review'});
export const pilotLayers=Object.freeze([{id:'strap',label:'Bracelet bleu fermé'},{id:'dial',label:'Cadran bleu'},{id:'date',label:'Jour et date de démonstration'},{id:'case',label:'Boîtier titane poli'},{id:'hands',label:'Aiguilles chromées'}]);
export const pilotGeometry=Object.freeze({cx:615,cy:625,dialRadius:318,dialScale:318/554,caseScale:318/462,mainHandsScale:.44,counterHandsScale:.44});
// Corrected closed strap silhouette, with narrow edges and fixed lug attachments.
export const pilotStrapSlots=Object.freeze([
 Object.freeze({sx:476,sy:104,sw:303,sh:380,x:413,y:88,w:404,h:247}),
 Object.freeze({sx:476,sy:900,sw:303,sh:275,x:413,y:934,w:404,h:250})
]);
export function isPilotSelection(selection){return Object.entries(pilotSelection).every(([key,value])=>selection?.[key]===value);}
let sequence=0;
const asset=name=>new URL(`../public/generated-7750/${name}.png`,import.meta.url).href;
export function createPilotPreview(visibleLayers=pilotLayers.map(l=>l.id),selection=pilotSelection){
 if(visibleLayers.some(id=>!pilotLayers.some(l=>l.id===id)))throw new Error('Calque inconnu');
 const ns='http://www.w3.org/2000/svg',make=(tag,attrs={})=>{const el=document.createElementNS(ns,tag);for(const[k,v]of Object.entries(attrs))el.setAttribute(k,String(v));return el;};
 const dialName=selection.dial==='dial-black'?'dial-black':'dial-blue',handsName=selection.hands==='hands-black'?'hands-black':'hands-chrome',caseName=selection.case==='case-sandblasted'?'case-sandblasted':'case-polished';
 const id='pilot-'+(++sequence),svg=make('svg',{viewBox:'0 0 1254 1254',role:'img','aria-label':`Simulation : cadran ${dialName==='dial-blue'?'bleu':'noir'}, bracelet ${selection.strap==='strap-black'?'noir':'bleu'}, aiguilles ${handsName==='hands-black'?'noires':'chromées'}, boîtier ${caseName==='case-polished'?'poli':'sablé'}`});svg.classList.add('pilot-watch');const defs=make('defs');svg.append(defs);
 const clip=(suffix,shapes)=>{const path=make('clipPath',{id:id+'-'+suffix,clipPathUnits:'userSpaceOnUse'});path.append(...shapes);defs.append(path);return `url(#${path.id})`;};
 const image=name=>make('image',{href:asset(name),width:1254,height:1254});
 const groups=Object.fromEntries(pilotLayers.map(l=>{const g=make('g',{'data-pilot-layer':l.id});g.style.display=visibleLayers.includes(l.id)?'':'none';return[l.id,g];}));
 // Two visible closed strap ends, in fixed shared slots, hidden behind the case.
 for(const r of pilotStrapSlots){const slot=make('svg',{x:r.x,y:r.y,width:r.w,height:r.h,viewBox:`${r.sx} ${r.sy} ${r.sw} ${r.sh}`,preserveAspectRatio:'none'});slot.style.overflow='hidden';slot.append(image('strap-blue-closed-fitted'));groups.strap.append(slot);}
 if(selection.strap==='strap-black'){const f=make('filter',{id:id+'-strap-black','color-interpolation-filters':'sRGB'});f.append(make('feColorMatrix',{type:'saturate',values:0}));defs.append(f);groups.strap.setAttribute('filter',`url(#${f.id})`);}
 const {cx,cy,dialScale,caseScale,mainHandsScale,counterHandsScale}=pilotGeometry;
 groups.dial.append(make('circle',{cx,cy,r:27,fill:dialName==='dial-black'?'#202020':'#304b6c'}));
 const dial=make('g',{transform:`translate(${cx} ${cy}) scale(${dialScale}) translate(-627 -625)`});dial.append(image(dialName));groups.dial.append(dial);
 // Opaque backing only behind the openings, not across the real dial texture.
 for(const r of [{x:706,y:605,width:74,height:42},{x:794,y:605,width:57,height:42}])groups.date.append(make('rect',{...r,fill:'#fff'}));
 for(const [text,x,size]of [['MON',743,29],['28',822,32]]){const el=make('text',{x,y:627,'text-anchor':'middle','dominant-baseline':'middle','font-family':'Arial, sans-serif','font-size':size,fill:'#151515'});el.textContent=text;groups.date.append(el);}
 const caseMask=make('mask',{id:id+'-case-rim',maskUnits:'userSpaceOnUse',x:0,y:0,width:1254,height:1254});caseMask.append(make('rect',{width:1254,height:1254,fill:'white'}),make('circle',{cx,cy,r:318,fill:'black'}));defs.append(caseMask);
 const caseOuter=make('g',{mask:`url(#${caseMask.id})`}),casePlacement=make('g',{transform:`translate(${cx} ${cy}) scale(${caseScale}) translate(-628 -628)`});casePlacement.append(image(caseName));caseOuter.append(casePlacement);groups.case.append(caseOuter);
 // Main hands are already positioned toward 10:10 in the supplied image.
 const mainMask=make('mask',{id:id+'-main-hands',maskUnits:'userSpaceOnUse',x:0,y:0,width:1254,height:1254});mainMask.append(make('rect',{width:1254,height:1254,fill:'white'}));
 for(const r of [{x:590,y:190,width:90,height:270},{x:300,y:530,width:100,height:250},{x:590,y:790,width:90,height:250}])mainMask.append(make('rect',{...r,fill:'black'}));defs.append(mainMask);
 const mainPivot=handsName==='hands-black'?{x:640,y:668}:{x:643,y:667};
 const main=make('g',{transform:`translate(${cx} ${cy}) scale(${mainHandsScale}) translate(${-mainPivot.x} ${-mainPivot.y})`});const mainImage=image(handsName);mainImage.setAttribute('mask',`url(#${mainMask.id})`);main.append(mainImage);groups.hands.append(main);
 const counterData=[{sx:630,sy:370,tx:cx+(628-627)*dialScale,ty:cy+(360-625)*dialScale,angle:0,box:{x:590,y:190,width:90,height:270}},{sx:350,sy:690,tx:cx+(353-627)*dialScale,ty:cy,angle:-125,box:{x:300,y:530,width:100,height:250}},{sx:630,sy:953,tx:cx,ty:cy+(891-625)*dialScale,angle:0,box:{x:590,y:790,width:90,height:250}}];
 for(const [i,c]of counterData.entries()){const part=make('g',{transform:`translate(${c.tx} ${c.ty}) rotate(${c.angle}) scale(${counterHandsScale}) translate(${-c.sx} ${-c.sy})`});const img=image(handsName);img.setAttribute('clip-path',clip('counter-'+i,[make('rect',c.box)]));part.append(img);groups.hands.append(part);if(handsName==='hands-black')groups.hands.append(make('circle',{cx:c.tx,cy:c.ty,r:4.5,fill:'#050505'}));}
 if(handsName==='hands-black')groups.hands.append(make('circle',{cx,cy,r:3.5,fill:'#050505'}));
 for(const l of pilotLayers)svg.append(groups[l.id]);return svg;
}
export function generatedComponentThumbnail(group,option){
 const ns='http://www.w3.org/2000/svg',regions={dial:{name:'dial-blue',x:74,y:71,w:1105,h:1111},strap:{name:'strap-blue-closed-fitted',x:460,y:90,w:330,h:1100},hands:{name:'hands-chrome',x:280,y:180,w:915,h:940},case:{name:'case-polished',x:85,y:25,w:1155,h:1205}};
 if(!(group in regions))throw new Error('Composant inconnu');
 if(group==='dial'&&option==='dial-black')regions.dial.name='dial-black';if(group==='case'&&option==='case-sandblasted')regions.case.name='case-sandblasted';if(group==='hands'&&option==='hands-black')regions.hands.name='hands-black';
 const r=regions[group],svg=document.createElementNS(ns,'svg');svg.setAttribute('viewBox','0 0 100 100');svg.setAttribute('aria-hidden','true');svg.classList.add('pilot-component');const inner=document.createElementNS(ns,'svg');for(const[k,v]of Object.entries({x:10,y:10,width:80,height:80,viewBox:`${r.x} ${r.y} ${r.w} ${r.h}`}))inner.setAttribute(k,String(v));inner.style.overflow='hidden';const img=document.createElementNS(ns,'image');img.setAttribute('href',asset(r.name));img.setAttribute('width','1254');img.setAttribute('height','1254');inner.append(img);if(group==='strap'&&option==='strap-black')inner.style.filter='grayscale(1)';svg.append(inner);return svg;
}
