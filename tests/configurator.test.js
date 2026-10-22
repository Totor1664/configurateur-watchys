import test from 'node:test';
import assert from 'node:assert/strict';
import {allSelections,configurationId,nomenclature} from '../src/selection.js';
import {defaultSelection,groups} from '../src/catalog.js';
import {price} from '../src/pricing.js';
import {add,remove,quantity,total,restore} from '../src/cart.js';
import {imageFor} from '../src/images.js';
const selections=allSelections();
test('16 configurations uniques, nomenclature complète et identifiants stables',()=>{assert.equal(selections.length,16);assert.equal(new Set(selections.map(configurationId)).size,16);for(const s of selections){assert.equal(nomenclature(s).length,5);assert.equal(nomenclature(s)[0].id,'movement-eta-valjoux-7750');for(const c of nomenclature(s)){assert.ok(c.id);assert.equal(c.prestashopProductId,null);assert.equal(c.prestashopCombinationId,null);}assert.equal(configurationId({...s}),configurationId(s));}});
for(const s of selections)test('Prix '+configurationId(s),()=>{const expected=490+(s.dial==='dial-blue'?20:0)+(s.strap==='strap-blue'?10:0)+(s.hands==='hands-black'?15:0)+(s.case==='case-sandblasted'?30:0);assert.equal(price(s),expected);});
test('Prix extrêmes 490 à 565 CHF, prix de référence 520 CHF',()=>{assert.equal(Math.min(...selections.map(price)),490);assert.equal(Math.max(...selections.map(price)),565);assert.equal(price(defaultSelection),520);});
test('Panier : ajout des 16, regroupement, quantité, total, restauration et suppression',()=>{let cart=selections.reduce(add,[]);assert.equal(cart.length,16);assert.equal(total(cart),8440);cart=add(cart,defaultSelection);assert.equal(cart.find(i=>i.id===configurationId(defaultSelection)).quantity,2);assert.equal(total(cart),8960);cart=quantity(cart,configurationId(defaultSelection),3);assert.equal(total(cart),9480);assert.deepEqual(restore(JSON.stringify(cart)),cart);cart=remove(cart,configurationId(defaultSelection));assert.equal(cart.length,15);assert.equal(total(cart),7920);assert.deepEqual(selections[0],defaultSelection);});
test('Données de panier corrompues et quantités invalides',()=>{assert.deepEqual(restore('invalide'),[]);assert.deepEqual(restore('{}'),[]);assert.deepEqual(restore('[{"id":"fake","selection":{},"quantity":1}]'),[]);const cart=add([],defaultSelection);for(const q of [0,-1,1.5,1000,NaN])assert.throws(()=>quantity(cart,cart[0].id,q));assert.equal(restore(JSON.stringify([...cart,...cart])).length,1);assert.throws(()=>price({}));});
test('Aucune image inventée : référence associée exclusivement à sa combinaison',()=>{for(const s of selections){assert.equal(imageFor(s,null),null);assert.equal(imageFor(s,'reference.png'),configurationId(s)===configurationId(defaultSelection)?'reference.png':null);}});

test('Les 16 sélections ont une vue haute résolution distincte',async()=>{const {simulationAsset}=await import('../src/simulation.js');const assets=selections.map(simulationAsset);assert.equal(new Set(assets.map(a=>`${a.src}:${a.x}:${a.y}`)).size,16);for(const a of assets){assert.ok([0,100].includes(a.x));assert.ok([0,100].includes(a.y));}assert.deepEqual(simulationAsset(defaultSelection),{src:'./public/watch-blue-blue.png',x:0,y:0});assert.throws(()=>simulationAsset({}));});
