import test from 'node:test';
import assert from 'node:assert/strict';
import {allSelections} from '../src/selection.js';
import {previewAvailability} from '../src/validation-renderer.js';
import {price} from '../src/pricing.js';
test('Version de validation : 40 choix à 490 CHF, 16 rendus disponibles et 24 en attente',()=>{const list=allSelections();assert.equal(list.length,40);assert.equal(list.filter(s=>previewAvailability(s).available).length,16);assert.equal(list.filter(s=>!previewAvailability(s).available).length,24);for(const s of list){assert.equal(price(s),490);assert.deepEqual(previewAvailability(s).missing,['dial-blue','dial-black'].includes(s.dial)?[]:[s.dial]);}assert.throws(()=>previewAvailability({}));});
