import {components} from './selection.js?v=five-dials-1';
import {groups} from './catalog.js?v=five-dials-1';
import {createPilotPreview,generatedComponentThumbnail} from './generated-pilot.js?v=config-validation-1';
export function previewAvailability(selection){components(selection);return {available:['dial-blue','dial-black'].includes(selection.dial),missing:['dial-blue','dial-black'].includes(selection.dial)?[]:[selection.dial]};}
export function simulationElement(selection,className='watch-simulation'){
 const state=previewAvailability(selection);
 if(state.available){const el=createPilotPreview(undefined,selection);el.classList.remove('pilot-watch');el.classList.add(className);return el;}
 const el=document.createElement('div');el.className=className+' pending-preview';el.setAttribute('role','img');el.setAttribute('aria-label','Aperçu non disponible pour cette configuration');
 const title=document.createElement('h2');title.textContent='Aperçu non disponible';const text=document.createElement('p');text.textContent='Le nouveau visuel de ce cadran reste à fournir. Votre configuration, son prix et son ajout au panier restent disponibles.';el.append(title,text);return el;
}
export function componentElement(group,option){
 if(group==='dial'&&!['dial-blue','dial-black'].includes(option)){
  const el=document.createElement('div');el.className='component-thumbnail pending-component';el.setAttribute('aria-hidden','true');const dot=document.createElement('i');dot.style.background=groups[0].options.find(o=>o.id===option).color;el.append(dot);return el;
 }
 const el=generatedComponentThumbnail(group,option);el.classList.remove('pilot-component');el.classList.add('component-thumbnail');return el;
}
