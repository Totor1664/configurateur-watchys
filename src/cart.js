import { configurationId, nomenclature } from './selection.js?v=classic-7750-1';
import { price } from './pricing.js?v=classic-7750-1';
export const CART_KEY='watchys.cart.v1';
export function add(cart, selection) { const id=configurationId(selection); const found=cart.find(i=>i.id===id); return found ? cart.map(i=>i.id===id?{...i,quantity:i.quantity+1}:i) : [...cart,{id,selection:{...selection},quantity:1}]; }
export function quantity(cart,id,value) { if(!Number.isInteger(value)||value<1||value>999) throw new Error('Quantité entre 1 et 999'); return cart.map(i=>i.id===id?{...i,quantity:value}:i); }
export const remove=(cart,id)=>cart.filter(i=>i.id!==id);
export const total=cart=>cart.reduce((sum,i)=>sum+price(i.selection)*i.quantity,0);
export function restore(raw) { try { const data=JSON.parse(raw); if(!Array.isArray(data)) return []; const seen=new Set(); return data.filter(i=>{try{const id=configurationId(i.selection);nomenclature(i.selection);if(i.id!==id||seen.has(id)||!Number.isInteger(i.quantity)||i.quantity<1||i.quantity>999)return false;seen.add(id);return true;}catch{return false;}}).map(i=>({id:i.id,selection:{...i.selection},quantity:i.quantity})); }catch{return [];} }
