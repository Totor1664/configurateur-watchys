/* Source PNGs stay unchanged. Every draw uses one proportional scale on both axes. */
(() => {
  'use strict';
  function proportionalRect(source, width) {
    if (!(source[2]>0 && source[3]>0 && width>0)) throw new Error('Invalid source geometry');
    return {width,height:width*source[3]/source[2]};
  }
  function anchoredRect(size, anchor, point, scale) {
    return {x:point[0]-anchor[0]*scale,y:point[1]-anchor[1]*scale,width:size[0]*scale,height:size[1]*scale};
  }
  class Preview {
    constructor(canvas) {this.canvas=canvas;this.cache=new Map();this.generation=0;}
    async load(option) {
      if(!this.cache.has(option.image)) this.cache.set(option.image,new Promise((resolve,reject)=>{const im=new Image();im.onload=()=>{if(im.naturalWidth!==option.preview.sourceSize[0]||im.naturalHeight!==option.preview.sourceSize[1]){this.cache.delete(option.image);reject(new Error('Dimensions PNG inattendues.'));}else resolve(im);};im.onerror=()=>{this.cache.delete(option.image);reject(new Error('Une image ne peut pas être chargée.'));};im.src=option.image;}));
      return this.cache.get(option.image);
    }
    async render(model,selected) {
      const generation=++this.generation;
      const images=await Promise.all(['strap','dial','hands','case'].map(g=>this.load(selected[g])));
      if(generation!==this.generation)return;
      const p=model.preview,canvas=this.canvas,ctx=canvas.getContext('2d');canvas.width=p.width;canvas.height=p.height;
      const point=p.center;ctx.clearRect(0,0,p.width,p.height);
      const [strap,dial,hands,caseImage]=images;
      const sp=selected.strap.preview;
      sp.parts.forEach((source,i)=>{const d=proportionalRect(source,p.strapWidth);ctx.drawImage(strap,...source,point[0]-d.width/2,i===0?point[1]-d.height:point[1],d.width,d.height);});
      const dp=selected.dial.preview,ds=p.dialDiameter/dp.diameter;
      const d=anchoredRect(dp.sourceSize,dp.anchor,point,ds);
      ctx.save();ctx.beginPath();ctx.arc(...point,p.dialDiameter/2,0,2*Math.PI);ctx.fillStyle='#f6f6f6';ctx.fill();ctx.clip();ctx.drawImage(dial,d.x,d.y,d.width,d.height);
      const hp=selected.hands.preview,hs=ds*hp.scaleToDial,h=anchoredRect(hp.sourceSize,hp.anchor,point,hs);
      // Remove the three auxiliary hands from the main overlay without touching source pixels.
      ctx.save();ctx.beginPath();ctx.rect(h.x,h.y,h.width,h.height);
      for(const part of hp.parts)ctx.rect(h.x+part.box[0]*hs,h.y+part.box[1]*hs,part.box[2]*hs,part.box[3]*hs);
      ctx.clip('evenodd');ctx.drawImage(hands,h.x,h.y,h.width,h.height);ctx.restore();
      hp.parts.forEach((part,i)=>{const dest=[point[0]+(dp.subdials[i][0]-dp.anchor[0])*ds,point[1]+(dp.subdials[i][1]-dp.anchor[1])*ds];const b=part.box;ctx.drawImage(hands,...b,dest[0]-(part.anchor[0]-b[0])*hs,dest[1]-(part.anchor[1]-b[1])*hs,b[2]*hs,b[3]*hs);});
      ctx.restore();
      const cp=selected.case.preview,c=anchoredRect(cp.sourceSize,cp.anchor,point,p.openingDiameter/cp.openingDiameter);ctx.save();ctx.beginPath();ctx.rect(0,0,p.width,p.height);ctx.arc(...point,p.dialDiameter/2,0,2*Math.PI);ctx.clip('evenodd');ctx.drawImage(caseImage,c.x,c.y,c.width,c.height);ctx.restore();
    }
  }
  globalThis.WatchysPreview={Preview,proportionalRect,anchoredRect};
})();
