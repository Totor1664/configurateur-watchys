import {components} from './selection.js?v=classic-7750-1';
import {componentAsset} from './model-assets.js?v=classic-7750-1';
import {createPilotPreview,generatedComponentThumbnail} from './generated-pilot.js?v=classic-7750-1';
export function previewAvailability(selection){
 const missing=components(selection).filter(c=>{try{componentAsset(c.group,c.id);return false;}catch{return true;}}).map(c=>c.id);
 return {available:missing.length===0,missing};
}
export function simulationElement(selection,className='watch-simulation'){
 const state=previewAvailability(selection);
 if(state.available){const el=createPilotPreview(undefined,selection);el.classList.remove('pilot-watch');el.classList.add(className);return el;}
 const el=document.createElement('div');el.className=className+' pending-preview';el.setAttribute('role','img');el.setAttribute('aria-label','Aperçu non disponible');el.textContent='Aperçu non disponible';return el;
}
export function componentElement(group,option){const el=generatedComponentThumbnail(group,option);el.classList.remove('pilot-component');el.classList.add('component-thumbnail');return el;}
