import { components } from './selection.js?v=five-dials-1';
import { dialAlignment } from './alignment.js';
import { dialColors, dialThumbnailFrame } from './dial-colors.js?v=white-index-black-hands-1';
import { greenIndexRepairs } from './dial-repairs.js';
// All configurations share the same fixed base and independent component layers.
export function simulationAsset(selection) {
 components(selection);
 return {src:'./public/watch-blue-blue.png',x:0,y:0,dialColor:selection.dial,dialBlack:selection.dial==='dial-black',strapBlack:selection.strap==='strap-black',handsBlack:selection.hands==='hands-black',caseSandblasted:selection.case==='case-sandblasted'};
}
export function simulationFrame(selection) {
 const asset=simulationAsset(selection),dial=dialAlignment['watch-blue-blue.png'][0];
 const side=dial.radius*640/166;
 return {...asset,dial,viewBox:[dial.cx-side/2,dial.cy-side/2,side,side],clip:{x:0,y:0,width:627,height:627}};
}
// Coordinates in the common 640-square viewport; these masks never move.
export const componentMasks = {
 case: 'M0 0H640V640H0Z M486 320A166 166 0 1 0 154 320A166 166 0 1 0 486 320Z M225 0H416V150H225Z M225 490H416V640H225Z',
 hands: 'M230 247L287 271L330 313L323 331L307 326L268 292Z M309 316L375 266L443 230L417 265L331 331Z M316 314L324 315L420 428L414 431Z M307 306H334V332H307Z M317 207H325V243H317Z M197 341L237 315L244 326L201 350Z M317 369H325V405H317Z',
 auxiliaryHands: 'M319 318L322 317L420 428L417 431Z M317 214H325V244H317Z M326 239A6 6 0 1 0 314 239A6 6 0 1 0 326 239Z M235 319L239 324L202 347L200 343Z M243 322A6 6 0 1 0 231 322A6 6 0 1 0 243 322Z M317 373H325V406H317Z M326 400A6 6 0 1 0 314 400A6 6 0 1 0 326 400Z',
 strapTop: {x:225,y:0,width:191,height:150},
 strapBottom: {x:225,y:490,width:191,height:150}
};
let watchClipId=0;
export function simulationElement(selection, className='watch-simulation') {
 const frame=simulationFrame(selection),ns='http://www.w3.org/2000/svg';
 const make=(tag,attrs={})=>{const el=document.createElementNS(ns,tag);for(const [k,v]of Object.entries(attrs))el.setAttribute(k,String(v));return el;};
 const id='watch-'+(++watchClipId),el=make('svg',{viewBox:'0 0 640 640',role:'img','aria-label':'Simulation : '+components(selection).map(c=>c.category+' '+c.label).join(', ')});el.classList.add(className);
 const defs=make('defs');el.append(defs);
 const mask=(suffix,shapes)=>{const clip=make('clipPath',{id:id+'-'+suffix,clipPathUnits:'userSpaceOnUse'});clip.append(...shapes);defs.append(clip);return `url(#${clip.id})`;};
 const source=(index)=>{
  const dial=dialAlignment['watch-blue-blue.png'][index],scale=166/dial.radius;
  const group=make('g',{transform:`translate(320 320) scale(${scale}) translate(${-dial.cx} ${-dial.cy})`});
  const localCrop=mask('cell-'+index,[make('rect',{x:(index%2)*627,y:Math.floor(index/2)*627,width:627,height:627})]);
  group.append(make('image',{href:frame.src,width:1254,height:1254,'clip-path':localCrop}));return group;
 };
 const composition=make('g');composition.append(source(0));
 if(frame.caseSandblasted){const layer=make('g',{'clip-path':mask('case',[make('path',{d:componentMasks.case,'clip-rule':'evenodd'})])});layer.append(source(1));composition.append(layer);}
 if(frame.handsBlack){
  const blackSource=source(2),layer=make('g',{'clip-path':mask('hands',[make('path',{d:componentMasks.hands})])});layer.append(blackSource);composition.append(layer);
  // The central seconds and all three counter hands have no white or chrome fill.
  // Threshold against the blue background keeps their actual photographed contours.
  const solidBlack=make('filter',{id:id+'-solid-black','color-interpolation-filters':'sRGB'});
  solidBlack.append(make('feColorMatrix',{type:'matrix',values:'0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 8 0 -8 0 1'}));defs.append(solidBlack);
  const auxiliary=make('g',{'clip-path':mask('auxiliary',[make('path',{d:componentMasks.auxiliaryHands})]),filter:`url(#${solidBlack.id})`});auxiliary.dataset.auxiliaryHands='black';auxiliary.append(blackSource.cloneNode(true));composition.append(auxiliary);
 }

 el.append(composition);
 const dialColor=dialColors[frame.dialColor];
 if(dialColor.photo){
  const photo=dialColor.photo;
  // Register the original photograph around its arbor. Separate upper/lower
  // vertical scales compensate its off-centre perspective without moving other components.
  const photoGroup=make('g');
  photoGroup.append(make('path',{d:'M486 320A166 166 0 1 0 154 320A166 166 0 1 0 486 320Z M368 310H408V332H368Z M413 310H441V332H413Z',fill:photo.backing,'fill-rule':'evenodd'}));
  for(const upper of [true,false]){
   const scaleX=166/photo.rx,scaleY=166/(upper?photo.cy-photo.top:photo.bottom-photo.cy);
   const half=make('g',{'clip-path':mask(upper?'photo-top':'photo-bottom',[make('rect',{x:0,y:upper?0:320,width:640,height:320})])});
   const placement=make('g',{transform:`translate(320 320) scale(${scaleX} ${scaleY}) translate(${-photo.cx} ${-photo.cy})`});
   placement.append(make('image',{href:photo.src,width:photo.width,height:photo.height}));
   if(frame.dialColor==='dial-green')for(const d of greenIndexRepairs)placement.append(make('path',{d,fill:'#fafaf7',stroke:'#fafaf7','stroke-width':1.4,'stroke-linejoin':'round','data-index-repair':'white'}));
   half.append(placement);photoGroup.append(half);
  }
  const protectedMask=make('mask',{id:id+'-photo-mask',maskUnits:'userSpaceOnUse',x:0,y:0,width:640,height:640});protectedMask.append(make('circle',{cx:320,cy:320,r:166,fill:'white'}),make('rect',{x:368,y:310,width:40,height:22,fill:'black'}),make('rect',{x:413,y:310,width:28,height:22,fill:'black'}));
  const neutral=make('filter',{id:id+'-photo-hands','color-interpolation-filters':'sRGB'});neutral.append(make('feColorMatrix',{type:'matrix',values:'0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 8 0 -8 0 1'}));defs.append(neutral);
  const hands=make('g',{'clip-path':mask('photo-hands-shape',[make('path',{d:componentMasks.hands})]),filter:`url(#${neutral.id})`});hands.append(composition.cloneNode(true));protectedMask.append(hands);defs.append(protectedMask);
  photoGroup.setAttribute('mask',`url(#${protectedMask.id})`);el.append(photoGroup);
 }
 if(frame.strapBlack){
  const filter=make('filter',{id:id+'-strap-black','color-interpolation-filters':'sRGB'});filter.append(make('feColorMatrix',{type:'saturate',values:0}));defs.append(filter);
  const layer=make('g',{'clip-path':mask('strap-color',[make('rect',componentMasks.strapTop),make('rect',componentMasks.strapBottom)]),filter:`url(#${filter.id})`});layer.append(composition.cloneNode(true));el.append(layer);
 }
 if(frame.handsBlack){
  // Solid centres cover the source photograph's pale arbor holes as well.
  const auxiliary=make('g',{'data-solid-auxiliary':'black',fill:'#050505',stroke:'#050505','stroke-linecap':'round'});
  auxiliary.append(make('path',{d:'M321 321L417 428',fill:'none','stroke-width':1.6}),make('path',{d:'M320 216V239 M202 344L237 322 M320 374V400',fill:'none','stroke-width':2.2}));
  for(const[cx,cy,r]of [[320,239,5],[237,321,5],[320,399,5.5]])auxiliary.append(make('circle',{cx,cy,r,stroke:'none'}));
  el.append(auxiliary);
 }

 return el;
}
export function componentElement(group,option) {
 if(group==='dial'&&dialColors[option]?.photo){
  const photo=dialColors[option].photo,layout=dialThumbnailFrame(option),ns='http://www.w3.org/2000/svg';
  const el=document.createElementNS(ns,'svg');el.classList.add('component-thumbnail');el.dataset.component=option;el.setAttribute('aria-hidden','true');el.setAttribute('viewBox','0 0 100 100');
  const placement=document.createElementNS(ns,'g');placement.setAttribute('transform',`translate(50 50) scale(${layout.scaleX} ${layout.scaleY}) translate(${-layout.cx} ${-layout.cy})`);
  const image=document.createElementNS(ns,'image');image.setAttribute('href',photo.src);image.setAttribute('width',String(photo.width));image.setAttribute('height',String(photo.height));placement.append(image);if(option==='dial-green')for(const d of greenIndexRepairs){const repair=document.createElementNS(ns,'path');for(const[k,v]of Object.entries({d,fill:'#fafaf7',stroke:'#fafaf7','stroke-width':1.4,'stroke-linejoin':'round','data-index-repair':'white'}))repair.setAttribute(k,String(v));placement.append(repair);}el.append(placement);return el;
 }
 const row=['dial','strap','hands','case'].indexOf(group);
 const column=group==='dial'?0:option===({dial:'dial-blue',strap:'strap-blue',hands:'hands-chrome',case:'case-polished'})[group]?0:1;
 const ns='http://www.w3.org/2000/svg';
 const el=document.createElementNS(ns,'svg');el.classList.add('component-thumbnail');el.setAttribute('aria-hidden','true');
 const regions=[{y:1,height:348},{y:352,height:308},{y:664,height:280},{y:948,height:396}];
 const region=regions[row];el.setAttribute('viewBox',`${column*584+2} ${region.y} 580 ${region.height}`);
 el.dataset.component=option;
 const defs=document.createElementNS(ns,'defs'),clip=document.createElementNS(ns,'clipPath'),rect=document.createElementNS(ns,'rect');
 clip.id='component-clip-'+option;rect.setAttribute('x',String(column*584+2));rect.setAttribute('y',String(region.y));rect.setAttribute('width','580');rect.setAttribute('height',String(region.height));clip.append(rect);defs.append(clip);el.append(defs);
 const image=document.createElementNS(ns,'image');image.setAttribute('clip-path',`url(#${clip.id})`);image.setAttribute('href','./public/components.png');image.setAttribute('width','1168');image.setAttribute('height','1346');el.append(image);
 if(group==='hands'&&option==='hands-black'){
  const detail=document.createElementNS(ns,'path');detail.setAttribute('d','M973 780L968 806L973 838L978 806Z');detail.setAttribute('fill','#050505');detail.dataset.thumbnailAuxiliary='black';el.append(detail);
  for(const[cx,cy]of [[906,860],[973,860],[1033,860],[1087,860]]){const centre=document.createElementNS(ns,'circle');centre.setAttribute('cx',String(cx));centre.setAttribute('cy',String(cy));centre.setAttribute('r','4');centre.setAttribute('fill','#050505');el.append(centre);}
 }

 return el;
}
