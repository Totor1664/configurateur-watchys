import test from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {allSelections,configurationId,nomenclature} from '../src/selection.js';
import {groups,defaultSelection,model} from '../src/catalog.js';
import {previewAvailability} from '../src/validation-renderer.js';
import {componentAsset,assetGroups,dialTarget,strapAssets} from '../src/model-assets.js';
import {price} from '../src/pricing.js';
import * as cart from '../src/cart.js';

test('Classic Valjoux 7750, 41 mm : 960 configurations uniques, toutes visibles à 490 CHF',()=>{
 const list=allSelections();assert.equal(model.caseDiameter,41);assert.equal(model.id,'classic-valjoux-7750-41');
 assert.deepEqual(groups.map(g=>g.options.length),[5,6,8,4]);
 assert.equal(list.length,960);assert.equal(new Set(list.map(configurationId)).size,960);
 for(const s of list){assert.equal(price(s),490);assert.deepEqual(previewAvailability(s),{available:true,missing:[]});assert.equal(nomenclature(s).length,5);}
 assert.throws(()=>previewAvailability({}));
});
test('Chaque option correspond à son propre PNG transparent fourni',async()=>{
 const names=new Set();
 for(const group of groups){assert.deepEqual(Object.keys(assetGroups[group.id]).sort(),group.options.map(o=>o.id).sort());
  for(const option of group.options){const asset=componentAsset(group.id,option.id);names.add(asset.name);assert.equal(option.price,0);assert.equal(option.prestashopProductId,null);assert.equal(option.prestashopCombinationId,null);
   const bytes=await readFile(new URL(`../public/generated-7750/${asset.name}.png`,import.meta.url));assert.equal(bytes.subarray(1,4).toString(),'PNG');assert.equal(bytes.readUInt32BE(16),1254);assert.equal(bytes.readUInt32BE(20),1254);assert.equal(bytes[25],6);
  }
 }
 assert.equal(names.size,23);assert.throws(()=>componentAsset('dial','fake'));
});
test('Les repères sources sont croissants, les bracelets restent dans le gabarit validé',()=>{
 for(const group of groups)for(const option of group.options){const asset=componentAsset(group.id,option.id);
  if(asset.x)for(const axis of ['x','y']){assert.equal(asset[axis].length,dialTarget[axis].length);assert.ok(asset[axis].every((v,i,a)=>i===0||v>a[i-1]));}
 }
 for(const asset of Object.values(strapAssets)){assert.equal(asset.crops.length,2);for(const[x,y,w,h]of asset.crops)assert.ok(x>=0&&y>=0&&w>0&&h>0&&x+w<=1254&&y+h<=1254);}
});
test('Panier : 960 configurations, regroupement, quantité, suppression et restauration',()=>{
 const list=allSelections();let basket=list.reduce(cart.add,[]);assert.equal(basket.length,960);assert.equal(cart.total(basket),470400);
 basket=cart.add(basket,defaultSelection);assert.equal(basket[0].quantity,2);assert.equal(cart.total(basket),470890);
 basket=cart.quantity(basket,configurationId(defaultSelection),3);assert.equal(cart.total(basket),471380);
 assert.deepEqual(cart.restore(JSON.stringify(basket)),basket);
 basket=cart.remove(basket,configurationId(defaultSelection));assert.equal(basket.length,959);assert.equal(cart.total(basket),469910);
});
test('Les identifiants et paniers validés restent compatibles',()=>{
 const id='watchys-7750--dial-blue--strap-blue--hands-chrome--case-polished';assert.equal(configurationId(defaultSelection),id);
 const saved=[{id,selection:{...defaultSelection},quantity:2}];assert.deepEqual(cart.restore(JSON.stringify(saved)),saved);assert.equal(cart.total(saved),980);
});
