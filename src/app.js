import {groups,defaultSelection,money} from './catalog.js';
import {components,nomenclature,configurationId,allSelections} from './selection.js';
import {price} from './pricing.js';
import * as basket from './cart.js';
import {imageFor} from './images.js';
import {simulationElement} from './simulation.js';
const $=s=>document.querySelector(s);
let selection={...defaultSelection}, cart=[],reference=null,previewMode='simulation';
try {cart=basket.restore(localStorage.getItem(basket.CART_KEY));reference=localStorage.getItem('watchys.reference.v1');}catch {$('#storage-status').textContent='Stockage indisponible : le panier reste disponible pendant cette session.';}
function persist(){try{localStorage.setItem(basket.CART_KEY,JSON.stringify(cart));}catch{$('#storage-status').textContent='Impossible de sauvegarder le panier dans ce navigateur.';}}
$('#options').innerHTML=groups.map((g,index)=>`<fieldset><legend><span>0${index+1}</span> ${g.label}</legend><div class="choices">${g.options.map(o=>`<button type="button" class="choice" data-group="${g.id}" data-option="${o.id}" aria-pressed="false"><i style="background:${o.color}"></i><span>${o.label}<small>${o.price?'+ '+money(o.price):'Inclus'}</small></span><b aria-hidden="true">✓</b></button>`).join('')}</div></fieldset>`).join('');
function render(){
 document.querySelectorAll('.choice').forEach(b=>b.setAttribute('aria-pressed',String(selection[b.dataset.group]===b.dataset.option)));
 $('#summary').innerHTML=components(selection).map(c=>`<div><dt>${c.category}</dt><dd>${c.label}</dd></div>`).join('');$('#price').textContent=money(price(selection));
 const src=imageFor(selection,reference);$('#image-area').replaceChildren();
 if(previewMode==='simulation'){ $('#image-area').append(simulationElement(selection)); } else
 if(src){const img=new Image();img.alt='Montre de référence : cadran bleu, cuir bleu, aiguilles chromées, boîtier poli';img.src=src;img.onerror=()=>{$('#image-area').innerHTML='<div class="unavailable"><h2>Aperçu non disponible</h2><p>Le fichier de référence ne peut pas être chargé.</p></div>';};$('#image-area').append(img);}else{$('#image-area').innerHTML='<div class="unavailable"><span class="placeholder-mark" aria-hidden="true">W</span><h2>Aperçu non disponible</h2><p>Cette composition attend son rendu fidèle.<br>Vos choix et votre prix restent disponibles.</p></div>';}
 $('#visual-status').textContent=previewMode==='simulation'?'SIMULATION À VALIDER':src?'RENDU IMPORTÉ':'VISUEL À FOURNIR';
 $('#preview-note').textContent=previewMode==='simulation'?'Simulation générée : cuir fermé reconstitué, finitions et détails horlogers approximatifs. Aperçu fidèle non disponible sans rendu validé.':'Un rendu fidèle apparaît uniquement s’il a été fourni pour cette combinaison.';
 $('#mode-simulation').setAttribute('aria-pressed',String(previewMode==='simulation'));
 $('#mode-faithful').setAttribute('aria-pressed',String(previewMode==='faithful'));
 const missing=allSelections().filter(s=>!imageFor(s,reference)).length;$('#missing').textContent=`${missing} configuration${missing>1?'s':''} sans rendu fidèle intégré. Les 16 simulations permettent de comparer vos choix, mais ne constituent pas une reproduction exacte des composants : les inscriptions, petites aiguilles et finitions doivent être corrigées et validées. La liste des rendus fidèles attendus figure dans le README.`;
}
function renderCart(){
 $('#count').textContent=cart.reduce((n,i)=>n+i.quantity,0);$('#total').textContent=money(basket.total(cart));
 $('#cart-items').innerHTML=cart.length?cart.map((item,index)=>`<article class="cart-item"><div><span class="eyebrow">COMPOSITION ${String(index+1).padStart(2,'0')}</span><h3>Chronographe Watchys</h3><dl>${nomenclature(item.selection).map(c=>`<div><dt>${c.category}</dt><dd>${c.label}</dd></div>`).join('')}</dl><details><summary>Identifiant local</summary><code>${item.id}</code></details></div><div class="cart-actions"><strong>${money(price(item.selection)*item.quantity)}</strong><label>Quantité<input type="number" min="1" max="999" value="${item.quantity}" data-quantity="${item.id}"></label><span>${money(price(item.selection))} / pièce</span><button class="remove" data-remove="${item.id}">Supprimer<span class="sr-only"> la composition ${index+1}</span></button></div></article>`).join(''):'<p class="empty">Votre panier attend votre première création.</p>';
 document.querySelectorAll('.cart-item').forEach((el,i)=>{el.prepend(simulationElement(cart[i].selection,'cart-simulation'));});
}
$('#mode-simulation').onclick=()=>{previewMode='simulation';render();};
$('#mode-faithful').onclick=()=>{previewMode='faithful';render();};
$('#options').addEventListener('click',e=>{const b=e.target.closest('[data-option]');if(!b)return;selection={...selection,[b.dataset.group]:b.dataset.option};render();});
$('#add').onclick=()=>{if(cart.find(i=>i.id===configurationId(selection))?.quantity>=999){$('#toast').textContent='Quantité maximale atteinte (999).';return;}cart=basket.add(cart,selection);persist();renderCart();$('#toast').textContent='Votre configuration a été ajoutée au panier.';clearTimeout(window.toastTimer);window.toastTimer=setTimeout(()=>$('#toast').textContent='',3500);};
$('#cart-items').onclick=e=>{const b=e.target.closest('[data-remove]');if(b){cart=basket.remove(cart,b.dataset.remove);persist();renderCart();}};
$('#cart-items').onchange=e=>{if(!e.target.matches('[data-quantity]'))return;try{cart=basket.quantity(cart,e.target.dataset.quantity,e.target.valueAsNumber);persist();}catch{$('#toast').textContent='Saisissez une quantité entière entre 1 et 999.';}renderCart();};
$('#cart-toggle').onclick=()=>{$('#cart').scrollIntoView({behavior:'smooth'});$('#cart-title').focus({preventScroll:true});};
$('#reference').onchange=async e=>{const file=e.target.files[0];if(!file)return;if(!['image/png','image/jpeg','image/webp'].includes(file.type)||file.size>8*1024*1024){$('#import-status').textContent='Choisissez un PNG, JPEG ou WebP de moins de 8 Mo.';return;}const reader=new FileReader();reader.onload=()=>{const check=new Image();check.onload=()=>{reference=reader.result;try{localStorage.setItem('watchys.reference.v1',reference);$('#import-status').textContent='Rendu enregistré dans ce navigateur, pour la combinaison de référence uniquement.';}catch{$('#import-status').textContent='Rendu chargé pour cette session seulement : espace de stockage insuffisant.';}render();};check.onerror=()=>$('#import-status').textContent='Image illisible.';check.src=reader.result;};reader.readAsDataURL(file);};
document.querySelectorAll('.choice').forEach(button=>{const sample={...defaultSelection,[button.dataset.group]:button.dataset.option};const thumbnail=simulationElement(sample,'option-simulation');thumbnail.removeAttribute('role');thumbnail.removeAttribute('aria-label');thumbnail.setAttribute('aria-hidden','true');button.querySelector('i').replaceWith(thumbnail);});
render();renderCart();
