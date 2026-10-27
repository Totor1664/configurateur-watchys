import { basePrice } from './catalog.js?v=five-dials-1';
import { components } from './selection.js?v=five-dials-1';
export const price = selection => basePrice + components(selection).reduce((sum,c)=>sum+c.price,0);
