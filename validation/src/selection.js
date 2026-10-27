import { groups, movement } from './catalog.js?v=classic-7750-1';
export function components(selection) {
 return groups.map(g=>{ const item=g.options.find(o=>o.id===selection[g.id]); if(!item) throw new Error('Sélection invalide'); return { group:g.id, category:g.label, ...item }; });
}
export function configurationId(selection) { return 'watchys-7750--'+components(selection).map(c=>c.id).join('--'); }
export function nomenclature(selection) { return [ { category:'Mouvement', ...movement }, ...components(selection) ]; }
export function allSelections() { return groups.reduce((list,g)=>list.flatMap(s=>g.options.map(o=>({...s,[g.id]:o.id}))),[{}]); }
