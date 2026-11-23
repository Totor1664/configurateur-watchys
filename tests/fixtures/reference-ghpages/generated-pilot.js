import {componentAsset, assetUrl, dialTarget} from './model-assets.js?v=classic-7750-1';
import {groups} from './catalog.js?v=classic-7750-1';

// Approved geometry. A variant changes only its own component layer.
export const pilotSelection=Object.freeze({dial:'dial-blue',strap:'strap-blue',hands:'hands-chrome',case:'case-polished'});
export const pilotAsset=Object.freeze({path:'public/review/blue-blue-chrome-polished.png',width:1254,height:1254,status:'approved'});
export const pilotLayers=Object.freeze([{id:'strap',label:'Bracelet fermé'},{id:'dial',label:'Cadran'},{id:'date',label:'Jour et date de démonstration'},{id:'case',label:'Carrure 41 mm'},{id:'hands',label:'Aiguilles'}]);
export const pilotGeometry=Object.freeze({cx:615,cy:625,dialRadius:318,dialScale:318/554,caseScale:318/462,mainHandsScale:.44,counterHandsScale:.44});
export const pilotStrapSlots=Object.freeze([
 Object.freeze({sx:476,sy:104,sw:303,sh:380,x:413,y:88,w:404,h:247}),
 Object.freeze({sx:476,sy:900,sw:303,sh:275,x:413,y:934,w:404,h:250})
]);
export function isPilotSelection(selection){return Object.entries(pilotSelection).every(([key,value])=>selection?.[key]===value);}
let sequence=0;
const ns='http://www.w3.org/2000/svg';
function make(tag,attrs={}){const el=document.createElementNS(ns,tag);for(const[k,v]of Object.entries(attrs))el.setAttribute(k,String(v));return el;}
function image(name){return make('image',{href:assetUrl(name),width:1254,height:1254});}
function viewport(crop,rect){const el=make('svg',{...rect,viewBox:crop.join(' '),preserveAspectRatio:'none'});el.style.overflow='hidden';return el;}

export function createPilotPreview(visibleLayers=pilotLayers.map(l=>l.id),selection=pilotSelection){
 if(visibleLayers.some(id=>!pilotLayers.some(l=>l.id===id)))throw new Error('Calque inconnu');
 const dialAsset=componentAsset('dial',selection.dial),strapAsset=componentAsset('strap',selection.strap),handsAsset=componentAsset('hands',selection.hands),caseAsset=componentAsset('case',selection.case);
 const description=groups.map(g=>`${g.label} : ${g.options.find(o=>o.id===selection[g.id]).label}`).join(', ');
 const id='pilot-'+(++sequence),svg=make('svg',{viewBox:'0 0 1254 1254',role:'img','aria-label':'Simulation Classic Valjoux 7750 · '+description});
 svg.classList.add('pilot-watch');const defs=make('defs');svg.append(defs);
 const clip=(suffix,shapes)=>{const path=make('clipPath',{id:id+'-'+suffix,clipPathUnits:'userSpaceOnUse'});path.append(...shapes);defs.append(path);return `url(#${path.id})`;};
 const layers=Object.fromEntries(pilotLayers.map(l=>{const g=make('g',{'data-pilot-layer':l.id});g.style.display=visibleLayers.includes(l.id)?'':'none';return[l.id,g];}));
 // Every leather colour occupies the same two approved attachment rectangles.
 for(const[i,r]of pilotStrapSlots.entries()){
  const slot=viewport(strapAsset.crops[i],{x:r.x,y:r.y,width:r.w,height:r.h});slot.append(image(strapAsset.name));
  if(selection.strap!=='strap-blue'){
   // The approved leather silhouette clips the side folds of new source files.
   const mask=make('mask',{id:id+'-strap-'+i,maskUnits:'userSpaceOnUse',x:0,y:0,width:1254,height:1254,'mask-type':'alpha'});
   const silhouette=viewport([r.sx,r.sy,r.sw,r.sh],{x:r.x,y:r.y,width:r.w,height:r.h});silhouette.append(image('strap-blue-closed-fitted'));mask.append(silhouette);defs.append(mask);
   const holder=make('g',{mask:`url(#${mask.id})`});holder.append(slot);layers.strap.append(holder);
  }else layers.strap.append(slot);
 }
 const {cx,cy,dialScale,caseScale}=pilotGeometry;
 layers.dial.append(make('circle',{cx,cy,r:27,fill:dialAsset.fill}));
 const dial=make('g',{transform:`translate(${cx} ${cy}) scale(${dialScale}) translate(-627 -625)`});
 if(!dialAsset.x){dial.append(image(dialAsset.name));}
 else{
  // Register the new source without moving the other component layers.
  for(let y=0;y<dialAsset.y.length-1;y++)for(let x=0;x<dialAsset.x.length-1;x++){
   const sx=dialAsset.x[x],sy=dialAsset.y[y],sw=dialAsset.x[x+1]-sx,sh=dialAsset.y[y+1]-sy;
   const tx=dialTarget.x[x],ty=dialTarget.y[y],tw=dialTarget.x[x+1]-tx,th=dialTarget.y[y+1]-ty;
   // Half-pixel overlap prevents antialiased white seams between SVG slices.
   const overlap=.75;
   const tile=viewport([sx-overlap*sw/tw,sy-overlap*sh/th,sw+2*overlap*sw/tw,sh+2*overlap*sh/th],{x:tx-overlap,y:ty-overlap,width:tw+2*overlap,height:th+2*overlap});
   tile.append(image(dialAsset.name));dial.append(tile);
  }
 }
 layers.dial.append(dial);
 for(const r of [{x:706,y:605,width:74,height:42},{x:794,y:605,width:57,height:42}])layers.date.append(make('rect',{...r,fill:'#fff'}));
 for(const[text,x,size]of[['MON',743,29],['28',822,32]]){const el=make('text',{x,y:627,'text-anchor':'middle','dominant-baseline':'middle','font-family':'Arial, sans-serif','font-size':size,fill:'#151515'});el.textContent=text;layers.date.append(el);}
 const caseMask=make('mask',{id:id+'-case-rim',maskUnits:'userSpaceOnUse',x:0,y:0,width:1254,height:1254});caseMask.append(make('rect',{width:1254,height:1254,fill:'white'}),make('circle',{cx,cy,r:318,fill:'black'}));defs.append(caseMask);
 const caseOuter=make('g',{mask:`url(#${caseMask.id})`}),casePlacement=make('g',{transform:`translate(${cx} ${cy}) scale(${caseScale}) translate(-628 -628)`});casePlacement.append(image(caseAsset.name));caseOuter.append(casePlacement);layers.case.append(caseOuter);
 // Main and counter hands have independent source pivots.
 const mainMask=make('mask',{id:id+'-main-hands',maskUnits:'userSpaceOnUse',x:0,y:0,width:1254,height:1254});mainMask.append(make('rect',{width:1254,height:1254,fill:'white'}));
 for(const r of handsAsset.boxes)mainMask.append(make('rect',{...r,fill:'black'}));defs.append(mainMask);
 const main=make('g',{transform:`translate(${cx} ${cy}) scale(${handsAsset.scale}) translate(${-handsAsset.pivot[0]} ${-handsAsset.pivot[1]})`});
 const mainImage=image(handsAsset.name);mainImage.setAttribute('mask',`url(#${mainMask.id})`);main.append(mainImage);layers.hands.append(main);
 const targets=[{x:cx+dialScale,y:cy-265*dialScale,angle:0},{x:cx-274*dialScale,y:cy,angle:-125},{x:cx,y:cy+266*dialScale,angle:0}];
 for(const[i,c]of handsAsset.counters.entries()){
  const t=targets[i],part=make('g',{transform:`translate(${t.x} ${t.y}) rotate(${t.angle}) scale(${c.scale}) translate(${-c.x} ${-c.y})`});const img=image(handsAsset.name);img.setAttribute('clip-path',clip('counter-'+i,[make('rect',handsAsset.boxes[i])]));part.append(img);layers.hands.append(part);
  if(handsAsset.black)layers.hands.append(make('circle',{cx:t.x,cy:t.y,r:4.5,fill:'#050505'}));
 }
 if(handsAsset.black)layers.hands.append(make('circle',{cx,cy,r:3.5,fill:'#050505'}));
 for(const l of pilotLayers)svg.append(layers[l.id]);return svg;
}

export function generatedComponentThumbnail(group,option=pilotSelection[group]){
 const entry=componentAsset(group,option),svg=make('svg',{viewBox:'0 0 100 100','aria-hidden':'true'});svg.classList.add('pilot-component');
 if(group==='strap'){
  for(const[i,crop]of entry.crops.entries()){const inner=viewport(crop,{x:36,y:i?57:10,width:28,height:33});inner.append(image(entry.name));svg.append(inner);}
 }else{
  const regions={dial:[74,72,1106,1110],case:[85,25,1155,1205],hands:entry.luminova?[280,180,915,940]:[210,135,1035,1100]};
  const crop=group==='dial'&&entry.x?[entry.x[0],entry.y[0],entry.x.at(-1)-entry.x[0],entry.y.at(-1)-entry.y[0]]:regions[group];
  const inner=viewport(crop,{x:10,y:10,width:80,height:80});inner.append(image(entry.name));svg.append(inner);
 }
 return svg;
}
