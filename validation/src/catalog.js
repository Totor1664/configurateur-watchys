export const basePrice = 490;
export const model = Object.freeze({ id: 'classic-valjoux-7750-41', label: 'Classic Valjoux 7750', caseDiameter: 41, strapWidth: 20, prestashopProductId: null, prestashopCombinationId: null });
export const movement = { id: 'movement-eta-valjoux-7750', label: 'ETA/Valjoux 7750', prestashopProductId: null, prestashopCombinationId: null };
const option = (id, label, price, color) => ({ id, label, price, color, prestashopProductId: null, prestashopCombinationId: null });
export const groups = [
 { id: 'dial', label: 'Cadran', options: [option('dial-blue','Bleu',0,'#244568'),option('dial-black','Noir',0,'#202329'),option('dial-green','Vert',0,'#344c32'),option('dial-anthracite','Anthracite',0,'#626463'),option('dial-grey','Gris clair',0,'#c6c8c6')] },
 { id: 'strap', label: 'Bracelet cuir 20 mm', options: [option('strap-blue','Bleu',0,'#244568'),option('strap-black','Noir',0,'#202329'),option('strap-red','Rouge',0,'#b51e25'),option('strap-orange','Orange',0,'#db671e'),option('strap-green','Vert',0,'#244632'),option('strap-brown','Brun',0,'#623b34')] },
 { id: 'hands', label: 'Aiguilles', options: [option('hands-chrome','Chromées Luminova',0,'#c7cbd0'),option('hands-black','Noires Luminova',0,'#202329'),option('hands-dauphine-chrome','Dauphine chromées',0,'#bcc2c8'),option('hands-dauphine-black','Dauphine noires',0,'#202329'),option('hands-dauphine-gold','Dauphine or',0,'#cba23f'),option('hands-dauphine-rose-gold','Dauphine or rose',0,'#c88d71'),option('hands-dauphine-red','Dauphine rouges',0,'#c81526'),option('hands-dauphine-royal-blue','Dauphine bleu royal',0,'#174bbb')] },
 { id: 'case', label: 'Carrure 41 mm', options: [option('case-polished','Polie',0,'linear-gradient(135deg,#8e9498,#fff,#8e9498)'),option('case-sandblasted','Sablée',0,'#a5a9ab'),option('case-black-brushed','Noire brossée',0,'#343434'),option('case-black-pvd','Noire PVD polie',0,'#141414')] }
];
export const defaultSelection = Object.fromEntries(groups.map(g=>[g.id,g.options[0].id]));
export const money = value => new Intl.NumberFormat('fr-CH',{style:'currency',currency:'CHF',maximumFractionDigits:0}).format(value);
