import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { groups } from '../src/catalog.js';
import { allSelections } from '../src/selection.js';

const catalog = JSON.parse(readFileSync(new URL('../integration/prestashop/catalog.json', import.meta.url), 'utf8'));
const model = catalog.models.find(m => m.id === 'watchys-7750-41mm');

test('PrestaShop preparation uses CHF, tax excluded, and integer centimes', () => {
  assert.equal(catalog.currency, 'CHF');
  assert.equal(catalog.priceBasis, 'tax-excluded');
  assert.equal(catalog.minorUnitsPerCurrency, 100);
  assert.equal(catalog.deploymentStatus, 'specification-not-installed');
  for (const amount of [model.kitPriceExclTaxMinor, model.movement.supplementExclTaxMinor, model.assemblySupplementExclTaxMinor]) {
    assert.ok(Number.isSafeInteger(amount) && amount >= 0);
  }
});

test('all current visual options have an audited PrestaShop component mapping', () => {
  assert.deepEqual(Object.keys(model.options).sort(), groups.map(g => g.id).sort());
  const products = new Set();
  for (const group of groups) {
    assert.deepEqual(Object.keys(model.options[group.id]).sort(), group.options.map(o => o.id).sort());
    for (const option of Object.values(model.options[group.id])) {
      const c = option.component;
      assert.ok(Number.isInteger(c.productId) && c.productId > 0);
      assert.equal(c.combinationId, 0);
      assert.equal(c.units, 1);
      assert.ok(!products.has(c.productId), `Unexpected shared component ID ${c.productId}`);
      products.add(c.productId);
    }
  }
});

test('short hands use the approved IDs and exclude the long sets', () => {
  assert.equal(model.dimensions.hands, 'short');
  assert.equal(model.options.hands['hands-black'].component.productId, 8199);
  assert.equal(model.options.hands['hands-chrome'].component.productId, 8200);
  assert.equal(model.movement.component.productId, 7736);
});

test('40 compositions support three offers at the approved flat prices', () => {
  const selections = allSelections();
  assert.equal(selections.length, 40);
  assert.deepEqual(Object.keys(model.offers).sort(), ['kit-with-movement', 'kit-without-movement', 'workshop-assembled']);
  const approved = {'kit-without-movement':19000, 'kit-with-movement':51000, 'workshop-assembled':61000};
  let count = 0;
  for (const selection of selections) {
    for (const [offerId, offer] of Object.entries(model.offers)) {
      assert.ok(!offer.workshopAssembly || offer.includeMovement);
      const extras = Object.entries(selection).reduce((sum, [group, id]) => sum + (model.options[group][id].supplementExclTaxMinor ?? 0), 0);
      const amount = model.kitPriceExclTaxMinor + extras
        + (offer.includeMovement ? model.movement.supplementExclTaxMinor : 0)
        + (offer.workshopAssembly ? model.assemblySupplementExclTaxMinor : 0);
      assert.equal(amount, approved[offerId]);
      const pieces = Object.entries(selection).map(([group, id]) => model.options[group][id].component);
      if (offer.includeMovement) pieces.push(model.movement.component);
      assert.equal(pieces.some(c => c.productId === 7736), offer.includeMovement);
      assert.equal(pieces.length, offer.includeMovement ? 5 : 4);
      count++;
    }
  }
  assert.equal(count, 120);
});
