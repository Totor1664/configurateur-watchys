// Original transparent user photographs, retained byte for byte. Layout only.
const photo=(name,cx,cy,left,top,right,bottom,backing)=>({src:`./public/dial-${name}-original.png`,width:1100,height:1422,cx,cy,rx:(right-left)/2,left,top,right,bottom,backing});
export const dialColors = {
 'dial-blue': {label:'Bleu',filter:null,photo:photo('blue',554,653,170,271,930,1032,'#28374b')},
 'dial-black': {label:'Noir',filter:null,photo:photo('black',555,658,173,275,931,1035,'#1c1c1c')},
 'dial-green': {label:'Vert',filter:null,photo:photo('green',554,653,169,269,931,1033,'#22311e')},
 'dial-anthracite': {label:'Anthracite',filter:null,photo:photo('anthracite',554,653,170,271,930,1032,'#5f5f5f')},
 'dial-grey': {label:'Gris clair',filter:null,photo:photo('grey',550,635,170,273,930,1032,'#bfc0bc')}
};
// Every visible dial occupies 80% of the same square thumbnail, whatever its margins.
export function dialThumbnailFrame(id){
 const p=dialColors[id]?.photo;if(!p)throw new Error('Photographie de cadran inconnue');
 return {cx:(p.left+p.right)/2,cy:(p.top+p.bottom)/2,scaleX:80/(p.right-p.left),scaleY:80/(p.bottom-p.top)};
}
