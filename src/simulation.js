import { components } from './selection.js';
import { dialAlignment } from './alignment.js';
export function simulationAsset(selection) {
 components(selection);
 return {src:'./public/watch-blue-blue.png',x:selection.case==='case-sandblasted'?100:0,y:selection.hands==='hands-black'?100:0,dialBlack:selection.dial==='dial-black',strapBlack:selection.strap==='strap-black'};
}
export function simulationFrame(selection) {
 const asset=simulationAsset(selection),name=asset.src.split('/').pop();
 const column=asset.x/100,row=asset.y/100;
 const dial=dialAlignment[name][row*2+column];
 // Every dial is placed at (320, 320) with radius 166 in a 640-square viewport.
 const side=dial.radius*640/166;
 return {...asset,dial,viewBox:[dial.cx-side/2,dial.cy-side/2,side,side],clip:{x:column*627,y:row*627,width:627,height:627}};
}
let watchClipId=0;
export function simulationElement(selection, className='watch-simulation') {
 const frame=simulationFrame(selection),ns='http://www.w3.org/2000/svg';
 const el=document.createElementNS(ns,'svg');el.classList.add(className);
 el.setAttribute('viewBox',frame.viewBox.join(' '));el.setAttribute('role','img');
 el.setAttribute('aria-label','Simulation : '+components(selection).map(c=>c.category+' '+c.label).join(', '));
 const defs=document.createElementNS(ns,'defs'),clip=document.createElementNS(ns,'clipPath'),rect=document.createElementNS(ns,'rect');
 clip.id='watch-frame-'+(++watchClipId);clip.setAttribute('clipPathUnits','userSpaceOnUse');
 for(const [key,value] of Object.entries(frame.clip))rect.setAttribute(key,String(value));
 clip.append(rect);defs.append(clip);el.append(defs);
 const sourceImage=()=>{const image=document.createElementNS(ns,'image');image.setAttribute('href',frame.src);image.setAttribute('width','1254');image.setAttribute('height','1254');image.setAttribute('clip-path',`url(#${clip.id})`);return image;};
 el.append(sourceImage());
 // Color variants share the exact same pixels/geometry. Only selected regions
 // are desaturated at display time; white markings and hand interiors stay white.
 if(frame.dialBlack||frame.strapBlack){
  const filter=document.createElementNS(ns,'filter');filter.id=clip.id+'-black';filter.setAttribute('color-interpolation-filters','sRGB');
  const matrix=document.createElementNS(ns,'feColorMatrix');matrix.setAttribute('type','saturate');matrix.setAttribute('values','0');filter.append(matrix);defs.append(filter);
  const region=document.createElementNS(ns,'clipPath');region.id=clip.id+'-regions';region.setAttribute('clipPathUnits','userSpaceOnUse');
  if(frame.dialBlack){const circle=document.createElementNS(ns,'circle');circle.setAttribute('cx',String(frame.dial.cx));circle.setAttribute('cy',String(frame.dial.cy));circle.setAttribute('r',String(frame.dial.radius));region.append(circle);}
  if(frame.strapBlack){
   const [x,y,side]=frame.viewBox;
   for(const [top,height] of [[0,150],[490,150]]){const rect=document.createElementNS(ns,'rect');rect.setAttribute('x',String(x));rect.setAttribute('y',String(y+side*top/640));rect.setAttribute('width',String(side));rect.setAttribute('height',String(side*height/640));region.append(rect);}
  }
  defs.append(region);const overlay=document.createElementNS(ns,'g');overlay.setAttribute('clip-path',`url(#${region.id})`);
  const image=sourceImage();image.setAttribute('filter',`url(#${filter.id})`);overlay.append(image);el.append(overlay);
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
