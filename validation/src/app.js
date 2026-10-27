import {groups,defaultSelection,money,model} from './catalog.js?v=classic-7750-1';
import {components,nomenclature,configurationId} from './selection.js?v=classic-7750-1';
import {price} from './pricing.js?v=classic-7750-1';
import * as basket from './cart.js?v=classic-7750-1';
import {simulationElement,componentElement} from './validation-renderer.js?v=classic-7750-1';
const $=s=>document.querySelector(s);
const CART_STORAGE_KEY='watchys.validation.cart.v1';
let selection={...defaultSelection}, cart=[];
try {cart=basket.restore(localStorage.getItem(CART_STORAGE_KEY));}catch {$('#storage-status').textContent='Stockage indisponible : le panier reste disponible pendant cette session.';}
function persist(){try{localStorage.setItem(CART_STORAGE_KEY,JSON.stringify(cart));}catch{$('#storage-status').textContent='Impossible de sauvegarder le panier dans ce navigateur.';}}
$('#options').innerHTML=groups.map((g,index)=>`<fieldset><legend><span>0${index+1}</span> ${g.label}</legend><div class="choices">${g.options.map(o=>`<button type="button" class="choice" data-group="${g.id}" data-option="${o.id}" aria-pressed="false"><i style="background:${o.color}"></i><span>${o.label}<small>Même prix</small></span><b aria-hidden="true">✓</b></button>`).join('')}</div></fieldset>`).join('');
function render(){
 document.querySelectorAll('.choice').forEach(b=>b.setAttribute('aria-pressed',String(selection[b.dataset.group]===b.dataset.option)));
 $('#summary').innerHTML=components(selection).map(c=>`<div><dt>${c.category}</dt><dd>${c.label}</dd></div>`).join('');$('#price').textContent=money(price(selection));
 $('#image-area').replaceChildren(simulationElement(selection));
 $('#visual-status').textContent='SIMULATION VISUELLE';
 $('#preview-note').textContent='Simulation à partir des visuels fournis, ajustés au gabarit validé. MON / 28 : jour et date de démonstration.';
}
function renderCart(){
 $('#count').textContent=cart.reduce((n,i)=>n+i.quantity,0);$('#total').textContent=money(basket.total(cart));
 $('#cart-items').innerHTML=cart.length?cart.map((item,index)=>`<article class="cart-item"><div><span class="eyebrow">COMPOSITION ${String(index+1).padStart(2,'0')}</span><h3>${model.label} · ${model.caseDiameter} mm</h3><dl>${nomenclature(item.selection).map(c=>`<div><dt>${c.category}</dt><dd>${c.label}</dd></div>`).join('')}</dl><details><summary>Identifiant local</summary><code>${item.id}</code></details></div><div class="cart-actions"><strong>${money(price(item.selection)*item.quantity)}</strong><label>Quantité<input type="number" min="1" max="999" value="${item.quantity}" data-quantity="${item.id}"></label><span>${money(price(item.selection))} / pièce</span><button class="remove" data-remove="${item.id}">Supprimer<span class="sr-only"> la composition ${index+1}</span></button></div></article>`).join(''):'<p class="empty">Votre panier attend votre première création.</p>';
 document.querySelectorAll('.cart-item').forEach((el,i)=>{el.prepend(simulationElement(cart[i].selection,'cart-simulation'));});
}
$('#options').addEventListener('click',e=>{const b=e.target.closest('[data-option]');if(!b)return;selection={...selection,[b.dataset.group]:b.dataset.option};render();});
$('#add').onclick=()=>{if(cart.find(i=>i.id===configurationId(selection))?.quantity>=999){$('#toast').textContent='Quantité maximale atteinte (999).';return;}cart=basket.add(cart,selection);persist();renderCart();$('#toast').textContent='Votre configuration a été ajoutée au panier.';clearTimeout(window.toastTimer);window.toastTimer=setTimeout(()=>$('#toast').textContent='',3500);};
$('#cart-items').onclick=e=>{const b=e.target.closest('[data-remove]');if(b){cart=basket.remove(cart,b.dataset.remove);persist();renderCart();}};
$('#cart-items').onchange=e=>{if(!e.target.matches('[data-quantity]'))return;try{cart=basket.quantity(cart,e.target.dataset.quantity,e.target.valueAsNumber);persist();}catch{$('#toast').textContent='Saisissez une quantité entière entre 1 et 999.';}renderCart();};
$('#cart-toggle').onclick=()=>{$('#cart').scrollIntoView({behavior:'smooth'});$('#cart-title').focus({preventScroll:true});};
document.querySelectorAll('.choice').forEach(button=>{button.querySelector('i').replaceWith(componentElement(button.dataset.group,button.dataset.option));});
render();renderCart();
