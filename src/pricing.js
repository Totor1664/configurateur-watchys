import { basePrice } from './catalog.js';
import { components } from './selection.js';
export const price = selection => basePrice + components(selection).reduce((sum,c)=>sum+c.price,0);
