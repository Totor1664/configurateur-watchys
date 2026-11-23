// Source registration for Classic Valjoux 7750, 41 mm only.
// All images are original transparent PNGs; placement is done by the renderer.
export const assetUrl = name => new URL(`../public/generated-7750/${name}.png`, import.meta.url).href;
export const dialAssets = {
 'dial-blue': {name:'dial-blue', fill:'#304b6c'},
 'dial-black': {name:'dial-black', fill:'#202020'},
 'dial-green': {name:'dial-green', fill:'#294321', x:[71,354,628,785,916,937,1035,1182], y:[69,361,591,627,663,891,1187]},
 'dial-anthracite': {name:'dial-anthracite', fill:'#505050', x:[70,355,628,786,914,938,1036,1184], y:[66,360,590,626,662,891,1189]},
 'dial-grey': {name:'dial-grey', fill:'#b8b9b7', x:[59,339,628,796,930,950,1051,1195], y:[60,355,591,627,665,909,1206]}
};
// Match the approved blue dial: circumference, counter axes and date openings.
export const dialTarget = {x:[74,353,627,786,915,937,1038,1180], y:[72,360,589,625,661,891,1182]};
export const strapAssets = {
 'strap-blue': {name:'strap-blue-closed-fitted', crops:[[476,104,303,380],[476,900,303,275]]},
 'strap-black': {name:'strap-black-closed', crops:[[500,70,253,359],[502,768,250,416]]},
 'strap-red': {name:'strap-red-closed', crops:[[494,68,265,363],[497,765,259,421]]},
 'strap-orange': {name:'strap-orange-closed', crops:[[479,61,285,372],[478,771,284,426]]},
 'strap-green': {name:'strap-green-closed', crops:[[498,69,257,363],[502,766,251,420]]},
 'strap-brown': {name:'strap-brown-closed', crops:[[497,68,259,362],[502,766,251,419]]}
};
export const caseAssets = Object.fromEntries(['case-polished','case-sandblasted','case-black-brushed','case-black-pvd'].map(name=>[name,{name}]));
const luminovaBoxes = [{x:590,y:190,width:90,height:270},{x:300,y:530,width:100,height:250},{x:590,y:790,width:90,height:250}];
const dauphineBoxes = [{x:580,y:135,width:105,height:315},{x:240,y:530,width:110,height:280},{x:580,y:835,width:105,height:280}];
const luminovaCounters = [{x:630,y:370,scale:.44},{x:350,y:690,scale:.44},{x:630,y:953,scale:.44}];
const dauphineCounters = [{x:630,y:351,scale:.376},{x:294,y:707,scale:.41},{x:629,y:1013,scale:.425}];
export const handsAssets = {
 'hands-chrome': {name:'hands-chrome',pivot:[643,667],scale:.44,boxes:luminovaBoxes,counters:luminovaCounters,luminova:true},
 'hands-black': {name:'hands-black',pivot:[640,668],scale:.44,boxes:luminovaBoxes,counters:luminovaCounters,luminova:true,black:true},
 ...Object.fromEntries(['chrome','black','gold','rose-gold','red','royal-blue'].map(color=>{
  const name='hands-dauphine-'+color;
  return [name,{name,pivot:color==='royal-blue'?[637,683]:[636,682],scale:.397,boxes:dauphineBoxes,counters:dauphineCounters,luminova:false}];
 }))
};
export const assetGroups = {dial:dialAssets,strap:strapAssets,hands:handsAssets,case:caseAssets};
export function componentAsset(group,id){
 const entry=assetGroups[group]?.[id];
 if(!entry)throw new Error('Visuel de composant inconnu : '+id);
 return entry;
}
