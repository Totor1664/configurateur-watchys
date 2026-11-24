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
  const cartButton=find('#wy-add-cart'),cartMessage=find('#wy-cart-message');
  let canAdd=false,adding=false,intent='',requestKey='';
  const updateCartButton=()=>{cartButton.disabled=!canAdd||adding;cartButton.textContent=adding?'Enregistrement…':'Ajouter ma configuration au panier';};
  const money = cents => new Intl.NumberFormat('fr-CH', {style:'currency',currency:'CHF',minimumFractionDigits:2}).format(cents/100);
  const preview = new WatchysPreview.Preview(find('#wy-canvas'));
  let model, selection, offerId, generation = 0, controller, timer;
  const text = (tag, content, className) => { const el=document.createElement(tag);el.textContent=content;if(className)el.className=className;return el; };
  const stockLabel = s => !s.exists || !s.referenceMatches ? 'À vérifier' : !s.active || !s.orderable ? 'À valider' : s.quantity > 0 ? 'En stock' : 'Indisponible';
  const groupOrder=['case','dial','hands','strap'];
  const groupRank=id=>groupOrder.includes(id)?groupOrder.indexOf(id):groupOrder.length;
  for (const m of data.models) { const o=text('option',m.label);o.value=m.id;modelSelect.append(o); }
  function chooseModel() {
    const selectedModel=data.models.find(m=>m.id===modelSelect.value);
    model={...selectedModel,groups:[...selectedModel.groups].sort((a,b)=>groupRank(a.id)-groupRank(b.id))};
    selection={};groupsNode.replaceChildren();offersNode.replaceChildren();
    for (const group of model.groups) {
      selection[group.id]=(group.options.find(o=>o.status.available)||group.options[0]).id;
      const field=document.createElement('fieldset');field.append(text('legend',group.label));
      const choices=document.createElement('div');choices.className='wy-options';
      for (const option of group.options) {
        const button=document.createElement('button');button.type='button';button.className='wy-option';button.dataset.group=group.id;button.dataset.option=option.id;
        const img=document.createElement('img');img.src=option.image;img.alt='';img.loading='lazy';
        const badge=text('small',stockLabel(option.status));badge.dataset.stock='true';
        button.append(img,text('span',option.label),badge);
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
    canAdd=false;updateCartButton();
    for (const button of root.querySelectorAll('.wy-option')) button.setAttribute('aria-pressed',String(selection[button.dataset.group]===button.dataset.option));
    const details=find('.wy-detail-images');details.replaceChildren();
    const selected={};
    for (const group of model.groups) {
      const option=group.options.find(o=>o.id===selection[group.id]);
      selected[group.id]=option;
      const f=document.createElement('figure'),img=document.createElement('img');img.src=option.image;img.alt=option.label;f.append(img,text('figcaption',option.label));details.append(f);
    }
    preview.render(model,selected).catch(()=>{find('.wy-caption').textContent='Aperçu indisponible. Les composants restent consultables ci-dessous.';});
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
      let result;try{result=await response.json();}catch{throw new Error('Réponse indisponible. Rechargez la page puis réessayez.');}if(serial!==generation)return;
      if(!response.ok||!result.ok)throw new Error(result.error||'Vérification indisponible.');
      const q=result.quote;
      for(const piece of q.components){const button=root.querySelector(`[data-group="${piece.group}"][data-option="${piece.optionId}"]`);if(button)button.querySelector('[data-stock]').textContent=piece.status.available?'Disponible pour cette quantité':stockLabel(piece.status)==='En stock'?'Quantité insuffisante':stockLabel(piece.status);}
      find('#wy-status').textContent=q.offerLabel;
      find('#wy-price').textContent=money(q.totalExclTaxMinor)+' HT'+(q.quantity>1?' · '+q.quantity+' × '+money(q.unitPriceExclTaxMinor):'');
      canAdd=q.componentsAvailable&&q.cartEnabled===true;updateCartButton();
      find('#wy-stock').textContent=q.componentsAvailable?'Tous les composants sélectionnés sont disponibles.':'À vérifier : '+q.components.filter(p=>!p.status.available).map(p=>p.label).join(', ')+'.';
    } catch(e) { if(serial===generation&&e.name!=='AbortError')find('#wy-status').textContent=e.message; }
  }
  cartButton.addEventListener('click',async()=>{
    if(!canAdd||adding)return;
    const body={modelId:model.id,selection:{...selection},offerId,quantity:Number(quantityNode.value)};
    const signature=JSON.stringify(body);
    if(intent!==signature||!requestKey){intent=signature;requestKey=Array.from(crypto.getRandomValues(new Uint8Array(16)),b=>b.toString(16).padStart(2,'0')).join('');}
    body.requestKey=requestKey;body.token=data.token;
    adding=true;cartMessage.textContent='Enregistrement de votre configuration dans le panier…';updateCartButton();
    const controls=[...root.querySelectorAll('.wy-controls input,.wy-controls select,.wy-option')];controls.forEach(el=>el.disabled=true);
    try{
      const response=await fetch(data.cartUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
      const result=await response.json();
      if(!response.ok||!result.ok)throw new Error(result.error||'Ajout non confirmé. Réessayez sans changer votre sélection.');
      const url=new URL(result.result.cartUrl,location.href);if(url.origin!==location.origin)throw new Error('Adresse panier inattendue.');
      cartMessage.textContent='Configuration ajoutée. Ouverture du panier…';location.assign(url.href);
    }catch(e){cartMessage.textContent=e.message||'Connexion interrompue. Vous pouvez réessayer sans changer votre sélection.';}
    finally{adding=false;controls.forEach(el=>el.disabled=false);updateCartButton();}
  });
  modelSelect.addEventListener('change',chooseModel);quantityNode.addEventListener('input',render);chooseModel();
})();
