// Display-only simulations. These do not replace validated component photographs.
export const dialColors = {
 'dial-blue': { label:'Bleu', filter:null },
 'dial-black': { label:'Noir', filter:{type:'saturate',values:'0'} },
 'dial-green': { label:'Vert', filter:{type:'hueRotate',values:'-90'} },
 'dial-anthracite': { label:'Anthracite', filter:{type:'matrix',values:'.17 .57 .06 0 .12 .17 .57 .06 0 .12 .17 .57 .06 0 .12 0 0 0 1 0'} },
 'dial-grey': { label:'Gris clair', filter:null, photo:{src:'./public/dial-grey-original.png',width:1100,height:1422,cx:550,cy:635,rx:380,top:273,bottom:1032} }
};
