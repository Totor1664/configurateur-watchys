import { components } from './selection.js';
export function simulationAsset(selection) {
 components(selection);
 return {src:`./public/watch-${selection.dial==='dial-blue'?'blue':'black'}-${selection.strap==='strap-blue'?'blue':'black'}.png`,x:selection.case==='case-sandblasted'?100:0,y:selection.hands==='hands-black'?100:0};
}
export function simulationElement(selection, className='watch-simulation') {
 const asset=simulationAsset(selection),el=document.createElement('div');
 el.className=className;el.setAttribute('role','img');
 el.setAttribute('aria-label','Simulation : '+components(selection).map(c=>c.category+' '+c.label).join(', '));
 el.style.backgroundImage=`url("${asset.src}")`;
 el.style.backgroundPosition=`${asset.x}% ${asset.y}%`;
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
