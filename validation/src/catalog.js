export const basePrice = 490;
export const movement = { id: 'movement-eta-valjoux-7750', label: 'ETA/Valjoux 7750', prestashopProductId: null, prestashopCombinationId: null };
const option = (id, label, price, color) => ({ id, label, price, color, prestashopProductId: null, prestashopCombinationId: null });
export const groups = [
 { id: 'dial', label: 'Cadran', options: [option('dial-blue','Bleu',0,'#244568'),option('dial-black','Noir',0,'#202329'),option('dial-green','Vert',0,'#344c32'),option('dial-anthracite','Anthracite',0,'#626463'),option('dial-grey','Gris clair',0,'#c6c8c6')] },
 { id: 'strap', label: 'Bracelet cuir 20 mm', options: [option('strap-blue','Bleu',0,'#244568'),option('strap-black','Noir',0,'#202329')] },
 { id: 'hands', label: 'Aiguilles', options: [option('hands-chrome','Chromées',0,'linear-gradient(135deg,#8d9399,#fff,#8d9399)'),option('hands-black','Noires',0,'#202329')] },
 { id: 'case', label: 'Boîtier titane 41 mm', options: [option('case-polished','Poli',0,'linear-gradient(135deg,#8e9498,#fff,#8e9498)'),option('case-sandblasted','Sablé',0,'#a5a9ab')] }
];
export const defaultSelection = Object.fromEntries(groups.map(g=>[g.id,g.options[0].id]));
export const money = value => new Intl.NumberFormat('fr-CH',{style:'currency',currency:'CHF',maximumFractionDigits:0}).format(value);
