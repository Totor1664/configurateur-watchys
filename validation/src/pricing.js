import { basePrice } from './catalog.js?v=classic-7750-1';
import { components } from './selection.js?v=classic-7750-1';
export const price = selection => basePrice + components(selection).reduce((sum,c)=>sum+c.price,0);
