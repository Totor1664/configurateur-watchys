(() => {
  'use strict';
  const root = document.getElementById('watchys-configurator');
  if (!root) return;
  const data = JSON.parse(document.getElementById('watchys-data').textContent);
  const find = id => root.querySelector(id);
  const modelSelect = find('#wy-model');
  const groupsNode = find('#wy-groups');
  const offersNode = find('#wy-offers');
  const quantityNode = find('#wy-quantity');
  const money = cents => new Intl.NumberFormat('fr-CH', {style:'currency',currency:'CHF',minimumFractionDigits:2}).format(cents/100);
  let model, selection, offerId, generation = 0, controller, timer;
  const text = (tag, content, className) => { const el=document.createElement(tag);el.textContent=content;if(className)el.className=className;return el; };
  const stockLabel = s => !s.exists || !s.referenceMatches ? 'À vérifier' : !s.active || !s.orderable ? 'À valider' : s.quantity > 0 ? 'En stock' : 'Indisponible';
  for (const m of data.models) { const o=text('option',m.label);o.value=m.id;modelSelect.append(o); }
  function chooseModel() {
    model=data.models.find(m=>m.id===modelSelect.value);selection={};groupsNode.replaceChildren();offersNode.replaceChildren();
    for (const group of model.groups) {
      selection[group.id]=(group.options.find(o=>o.status.available)||group.options[0]).id;
      const field=document.createElement('fieldset');field.append(text('legend',group.label));
      const choices=document.createElement('div');choices.className='wy-options';
      for (const option of group.options) {
        const button=document.createElement('button');button.type='button';button.className='wy-option';button.dataset.group=group.id;button.dataset.option=option.id;
        const img=document.createElement('img');img.src=option.image;img.alt='';img.loading='lazy';
        button.append(img,text('span',option.label),text('small',stockLabel(option.status)));
        button.addEventListener('click',()=>{selection[group.id]=option.id;render();});choices.append(button);
      }
      field.append(choices);groupsNode.append(field);
    }
    offerId=Object.keys(model.offers)[0];
    for (const [id,offer] of Object.entries(model.offers)) {
      const label=document.createElement('label'),radio=document.createElement('input');radio.type='radio';radio.name='watchys-offer';radio.value=id;radio.checked=id===offerId;
      radio.addEventListener('change',()=>{offerId=id;render();});label.append(radio,text('span',offer.label));offersNode.append(label);
    }
    render();
  }
  function render() {
    for (const button of root.querySelectorAll('.wy-option')) button.setAttribute('aria-pressed',String(selection[button.dataset.group]===button.dataset.option));
    const details=find('.wy-detail-images');details.replaceChildren();
    for (const group of model.groups) {
      const option=group.options.find(o=>o.id===selection[group.id]);
      const layers=group.id==='strap'?['strap-top','strap-bottom']:[group.id];
      for (const layer of layers) root.querySelector(`[data-layer="${layer}"]`).src=option.image;
      const f=document.createElement('figure'),img=document.createElement('img');img.src=option.image;img.alt=option.label;f.append(img,text('figcaption',option.label));details.append(f);
    }
    clearTimeout(timer);const serial=++generation;
    controller?.abort();find('#wy-price').textContent='';find('#wy-stock').textContent='';find('#wy-status').textContent='Vérification de votre configuration…';
    timer=setTimeout(()=>quote(serial),150);
  }
  async function quote(serial) {
    const quantity=Number(quantityNode.value);
    if (!Number.isInteger(quantity)||quantity<1||quantity>100) {find('#wy-status').textContent='Choisissez une quantité entière entre 1 et 100.';return;}
    controller=new AbortController();
    try {
      const response=await fetch(data.apiUrl,{method:'POST',credentials:'same-origin',signal:controller.signal,headers:{'Content-Type':'application/json'},body:JSON.stringify({token:data.token,modelId:model.id,selection,offerId,quantity})});
      const result=await response.json();if(serial!==generation)return;
      if(!response.ok||!result.ok)throw new Error(result.error||'Vérification indisponible.');
      const q=result.quote;find('#wy-status').textContent=q.offerLabel;
      find('#wy-price').textContent=money(q.totalExclTaxMinor)+' HT'+(q.quantity>1?' · '+q.quantity+' × '+money(q.unitPriceExclTaxMinor):'');
      find('#wy-stock').textContent=q.componentsAvailable?'Tous les composants sélectionnés sont disponibles.':'À vérifier : '+q.components.filter(p=>!p.status.available).map(p=>p.label).join(', ')+'.';
    } catch(e) { if(serial===generation&&e.name!=='AbortError')find('#wy-status').textContent=e.message; }
  }
  modelSelect.addEventListener('change',chooseModel);quantityNode.addEventListener('input',render);chooseModel();
})();
