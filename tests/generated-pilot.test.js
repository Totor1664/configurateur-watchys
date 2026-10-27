import test from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {pilotSelection,isPilotSelection,pilotLayers,pilotGeometry} from '../src/generated-pilot.js';
import {allSelections} from '../src/selection.js';
import {price} from '../src/pricing.js';
test('Une seule composition expérimentale est éligible parmi les 960 du modèle',()=>{const selections=allSelections();assert.equal(selections.length,960);assert.deepEqual(selections.filter(isPilotSelection),[pilotSelection]);assert.equal(isPilotSelection(null),false);for(const selection of selections)assert.equal(price(selection),490);});
test('Les quatre composants pilotes sont des PNG transparents indépendants en haute définition',async()=>{for(const name of ['dial-blue','strap-blue-closed','case-polished','hands-chrome']){const file=await readFile(new URL(`../public/generated-7750/${name}.png`,import.meta.url));assert.equal(file.subarray(1,4).toString(),'PNG');assert.equal(file.readUInt32BE(16),1254);assert.equal(file.readUInt32BE(20),1254);assert.equal(file[25],6);}assert.equal(new Set(pilotLayers.map(l=>l.id)).size,pilotLayers.length);assert.equal(pilotGeometry.dialRadius,318);});

test('Le bracelet ajusté reste centré et passe derrière les raccords de la tête validée',async()=>{const {pilotStrapSlots}=await import('../src/generated-pilot.js');assert.equal(pilotStrapSlots.length,2);const[top,bottom]=pilotStrapSlots;for(const slot of pilotStrapSlots){assert.equal(slot.x+slot.w/2,pilotGeometry.cx);assert.ok(slot.sx>=0&&slot.sy>=0&&slot.sx+slot.sw<=1254&&slot.sy+slot.sh<=1254);}assert.ok(top.y+top.h>pilotGeometry.cy-pilotGeometry.dialRadius);assert.ok(bottom.y<pilotGeometry.cy+pilotGeometry.dialRadius);assert.equal(top.w,bottom.w);const file=await readFile(new URL('../public/generated-7750/strap-blue-closed-fitted.png',import.meta.url));assert.equal(file.readUInt32BE(16),1254);assert.equal(file.readUInt32BE(20),1254);assert.equal(file[25],6);});
