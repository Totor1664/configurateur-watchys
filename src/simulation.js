import { components } from './selection.js';
import { dialAlignment } from './alignment.js';
// All configurations share the same fixed base and independent component layers.
export function simulationAsset(selection) {
 components(selection);
 return {src:'./public/watch-blue-blue.png',x:0,y:0,dialBlack:selection.dial==='dial-black',strapBlack:selection.strap==='strap-black',handsBlack:selection.hands==='hands-black',caseSandblasted:selection.case==='case-sandblasted'};
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
 if(frame.handsBlack){const layer=make('g',{'clip-path':mask('hands',[make('path',{d:componentMasks.hands})])});layer.append(source(2));composition.append(layer);}
 el.append(composition);
 if(frame.dialBlack||frame.strapBlack){
  const filter=make('filter',{id:id+'-black','color-interpolation-filters':'sRGB'});filter.append(make('feColorMatrix',{type:'saturate',values:0}));defs.append(filter);
  const shapes=[];
  if(frame.dialBlack)shapes.push(make('circle',{cx:320,cy:320,r:166}));
  if(frame.strapBlack)shapes.push(make('rect',componentMasks.strapTop),make('rect',componentMasks.strapBottom));
  const layer=make('g',{'clip-path':mask('colors',shapes),filter:`url(#${filter.id})`});layer.append(composition.cloneNode(true));el.append(layer);
 }
 return el;
}
export function componentElement(group,option) {
 const row=['dial','strap','hands','case'].indexOf(group);
 const column=option===({dial:'dial-blue',strap:'strap-blue',hands:'hands-chrome',case:'case-polished'})[group]?0:1;
 const ns='http://www.w3.org/2000/svg';
 const el=document.createElementNS(ns,'svg');el.classList.add('component-thumbnail');el.setAttribute('aria-hidden','true');
 const regions=[{y:1,height:348},{y:352,height:308},{y:664,height:280},{y:948,height:396}];
 const region=regions[row];el.setAttribute('viewBox',`${column*584+2} ${region.y} 580 ${region.height}`);
 el.dataset.component=option;
 const defs=document.createElementNS(ns,'defs'),clip=document.createElementNS(ns,'clipPath'),rect=document.createElementNS(ns,'rect');
 clip.id='component-clip-'+option;rect.setAttribute('x',String(column*584+2));rect.setAttribute('y',String(region.y));rect.setAttribute('width','580');rect.setAttribute('height',String(region.height));clip.append(rect);defs.append(clip);el.append(defs);
 const image=document.createElementNS(ns,'image');image.setAttribute('clip-path',`url(#${clip.id})`);image.setAttribute('href','./public/components.png');image.setAttribute('width','1168');image.setAttribute('height','1346');el.append(image);
 return el;
}
