import { components } from './selection.js';
export const simulationSheet='./public/simulations-16.png';
// Generated proposals, distinct from the registry of validated faithful renders.
// Rows: blue/blue, blue/black, black/blue, black/black (dial/strap).
// Columns: chrome/polished, chrome/sandblasted, black/polished, black/sandblasted.
export function simulationCell(selection) {
 components(selection);
 const row=(selection.dial==='dial-black'?2:0)+(selection.strap==='strap-black'?1:0);
 const column=(selection.hands==='hands-black'?2:0)+(selection.case==='case-sandblasted'?1:0);
 return {row,column,x:column*100/3,y:row*100/3};
}
export function simulationElement(selection, className='watch-simulation') {
 const cell=simulationCell(selection),el=document.createElement('div');
 el.className=className;el.setAttribute('role','img');
 el.setAttribute('aria-label','Simulation à valider : '+components(selection).map(c=>c.category+' '+c.label).join(', '));
 el.style.backgroundImage=`url("${simulationSheet}")`;
 el.style.backgroundPosition=`${cell.x}% ${cell.y}%`;
 return el;
}
