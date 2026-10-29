/* Responsive POS cart client for the atomic /sales/cart endpoint. Preview totals are never authoritative. */
function posCartSessionKey(){const u=state.boot?.user||{};return 'stockland.pos.cart.v1.'+String(u.id||u.user_id||u.user_login||u.username||'current')}
function ensurePosCartState(){
 if(state.posCart&&Array.isArray(state.posCart.lines))return state.posCart;
 let saved=null;try{saved=JSON.parse(window.sessionStorage.getItem(posCartSessionKey())||'null')}catch(_e){}
 state.posCart=saved&&Array.isArray(saved.lines)?saved:{cartId:'',lines:[],pendingPayload:null,meta:{}};
 if(!state.posCart.meta||typeof state.posCart.meta!=='object')state.posCart.meta={};
 return state.posCart;
}
function savePosCartState(){try{window.sessionStorage.setItem(posCartSessionKey(),JSON.stringify(ensurePosCartState()))}catch(_e){}}
function newPosCartId(){const b=new Uint8Array(4);if(window.crypto?.getRandomValues)window.crypto.getRandomValues(b);else for(let i=0;i<b.length;i++)b[i]=Math.floor(Math.random()*256);return ('C'+Date.now().toString(36).toUpperCase().slice(-5)+Array.from(b,x=>x.toString(16).padStart(2,'0')).join('').toUpperCase()).slice(0,14)}
function posCartDiscrete(unit){return ['', 'PCS','PC','PIECE','PIECES','EA','EACH','UNIT','UNITS'].includes(String(unit||'PCS').trim().toUpperCase())}
function normalizePosCartLineInput(quantityRaw,priceRaw,unit){
 const qText=String(quantityRaw??'').trim(),pText=String(priceRaw??'').trim();
 if(!/^\d+(?:\.\d{1,2})?$/.test(qText))throw new Error('Enter a positive quantity with no more than two decimal places.');
 const q=Number(qText);if(!Number.isFinite(q)||q<=0)throw new Error('Quantity must be greater than zero.');
 if(posCartDiscrete(unit)&&Math.abs(q-Math.round(q))>0.000001)throw new Error('This Product Master UOM counts whole pieces/units. Fractional quantity is not allowed.');
 let price=null;if(pText!==''){if(!/^\d+(?:\.\d{1,2})?$/.test(pText))throw new Error('Enter a valid line price with no more than two decimal places.');const n=Number(pText);if(!Number.isFinite(n)||n<=0)throw new Error('An overridden line price must be greater than zero.');price=Math.round(n*100)/100;}
 return {quantity_sold:q,selling_price_override:price};
}
function posCartMoney(n){return money(Math.round((Number(n)||0)*100)/100)}
function posCartMetaFromForm(f){return {sale_date:f.elements.sale_date?.value||localDate(),branch_location_id:String(f.elements.branch_location_id?.value||''),vat_mode:String(f.elements.vat_mode?.value||'VAT_INCLUDED'),external_receipt_no:String(f.elements.external_receipt_no?.value||'').trim()}}
function posCartRate(){const n=state.boot?.settings?.vat_rate;if(n===undefined||n===null||n===''||!Number.isFinite(Number(n))||Number(n)<0)return null;return Number(n)>1?Number(n)/100:Number(n)}
function posCartLineMath(line,mode,rate){
 const q=Number(line.quantity_sold)||0,p=Number(line.selling_price_override==null?line.masterPrice:line.selling_price_override)||0,base=Math.round(q*p*100)/100;
 if(rate===null)return {gross:null,vat:null,net:null};
 if(mode==='VAT_INCLUDED'){const vat=Math.round(base*rate/(1+rate)*100)/100;return {gross:base,vat,net:Math.round((base-vat)*100)/100}}
 const vat=Math.round(base*rate*100)/100;return {gross:Math.round((base+vat)*100)/100,vat,net:base};
}
function posCartTotals(lines,mode,rate){return lines.reduce((a,line)=>{const m=posCartLineMath(line,mode,rate);if(m.gross===null){a.unknown=true;return a}a.gross=Math.round((a.gross+m.gross)*100)/100;a.vat=Math.round((a.vat+m.vat)*100)/100;a.net=Math.round((a.net+m.net)*100)/100;return a},{gross:0,vat:0,net:0,unknown:false})}
function renderPosCart(){
 const f=document.getElementById('posCartForm');if(!f)return;
 const c=ensurePosCartState(),locked=!!c.pendingPayload,container=f.querySelector('#posCartItems'),rate=posCartRate(),mode=f.elements.vat_mode?.value||c.meta?.vat_mode||'VAT_INCLUDED';
 f.querySelectorAll('[name="sale_date"],[name="branch_location_id"],[name="vat_mode"],[name="external_receipt_no"]').forEach(x=>{x.disabled=locked});
 const add=f.querySelector('#posCartAddLine'),clear=f.querySelector('#posCartClear'),submit=f.querySelector('#posCartSubmit');
 if(add)add.disabled=locked;if(clear)clear.textContent=locked?'Clear after verification':'Clear cart';if(submit)submit.textContent=locked?'RETRY SAME CART':'POST COMPLETE CART';
 if(!c.lines.length){container.innerHTML='<div class="sl-empty">Your cart is empty. Search and add a Product Master SKU above.</div>'}
 else{
  container.innerHTML='<div class="sl-table-wrap"><table class="sl-table sl-responsive-cards sl-pos-cart-table"><thead><tr><th>Product / SKU</th><th>Qty</th><th>Master ETB</th><th>Override ETB</th><th>Line total preview</th><th>Action</th></tr></thead><tbody>'+c.lines.map((l,i)=>{const m=posCartLineMath(l,mode,rate);return '<tr><td data-label="Product / SKU"><b>'+esc(l.sku)+'</b><small class="sl-pos-cart-product">'+esc(l.product_name||l.sku)+'</small><small class="sl-pos-cart-product">'+esc([l.size,l.color].filter(Boolean).join(' / '))+' · UOM '+esc(l.unit||'PCS')+'</small></td><td data-label="Qty"><input class="sl-pos-cart-cell-input" data-cart-qty="'+i+'" type="number" min="'+(posCartDiscrete(l.unit)?'1':'0.01')+'" step="'+(posCartDiscrete(l.unit)?'1':'0.01')+'" value="'+esc(l.quantity_sold)+'" '+(locked?'disabled':'')+' required></td><td data-label="Master ETB">'+posCartMoney(l.masterPrice)+'</td><td data-label="Override ETB"><input class="sl-pos-cart-cell-input" data-cart-price="'+i+'" type="number" min="0.01" step="0.01" placeholder="Use master" value="'+(l.selling_price_override==null?'':esc(l.selling_price_override))+'" '+(locked?'disabled':'')+'></td><td data-label="Line total preview"><b>'+(m.gross===null?'Server calculated':posCartMoney(m.gross))+'</b><small class="sl-pos-cart-product">'+(m.vat===null?'VAT preview unavailable':'VAT '+posCartMoney(m.vat))+'</small></td><td data-label="Action"><button class="sl-btn sl-btn-sm" type="button" data-cart-remove="'+i+'" '+(locked?'disabled':'')+'>Remove</button></td></tr>'}).join('')+'</tbody></table></div>';
 }
 const t=posCartTotals(c.lines,mode,rate);
 f.querySelector('#posCartLineCount').textContent=c.lines.length+' line'+(c.lines.length===1?'':'s');
 f.querySelector('#posCartGross').textContent=t.unknown?'Server calculated':posCartMoney(t.gross);
 f.querySelector('#posCartVat').textContent=t.unknown?'Server calculated':posCartMoney(t.vat);
 f.querySelector('#posCartNet').textContent=t.unknown?'Server calculated':posCartMoney(t.net);
 const hint=f.querySelector('#posCartSkuHint');if(hint&&locked)hint.textContent='Checkout is pending. The exact saved payload is locked for safe retry.';
 savePosCartState();
}
function updatePosCartPreview(){
 const f=document.getElementById('posCartForm');if(!f)return;const c=ensurePosCartState(),t=posCartTotals(c.lines,f.elements.vat_mode?.value||'VAT_INCLUDED',posCartRate());
 [['#posCartGross','gross'],['#posCartVat','vat'],['#posCartNet','net']].forEach(([sel,k])=>{const el=f.querySelector(sel);if(el)el.textContent=t.unknown?'Server calculated':posCartMoney(t[k])});
 f.querySelectorAll('tbody tr').forEach((tr,i)=>{const l=c.lines[i];if(!l)return;const m=posCartLineMath(l,f.elements.vat_mode?.value||'VAT_INCLUDED',posCartRate()),td=tr.querySelector('td[data-label="Line total preview"]');if(td)td.innerHTML='<b>'+(m.gross===null?'Server calculated':posCartMoney(m.gross))+'</b><small class="sl-pos-cart-product">'+(m.vat===null?'VAT preview unavailable':'VAT '+posCartMoney(m.vat))+'</small>'});
}
function addPosCartLine(){
 const f=document.getElementById('posCartForm');if(!f)return;const c=ensurePosCartState();if(c.pendingPayload){toast('This cart is pending. Retry this same cart or verify Recent Posted Sales before clearing.',true);return}
 const picker=f.querySelector('[data-sku-picker]'),hidden=picker?.querySelector('[data-sku-value]'),sku=String(hidden?.value||'').trim();let r=null;try{r=JSON.parse(picker?.dataset.selectedRecord||'null')}catch(_e){}
 if(!sku||!r||String(r.sku||'')!==sku){toast('Choose an active Product Master result before adding a line.',true);return}
 if(String(r.product_type||'SIMPLE').toUpperCase()==='STYLE'){toast('Choose a sellable size/color variant, not a style template.',true);return}
 if(c.lines.length>=50){toast('A cart can contain at most 50 distinct SKU lines.',true);return}
 const price=Number(r.retail_price);if(!Number.isFinite(price)||price<=0){toast('The Product Master SKU must have a positive retail price.',true);return}
 const existing=c.lines.find(x=>String(x.sku).toUpperCase()===sku.toUpperCase());
 if(existing)existing.quantity_sold=Math.round((Number(existing.quantity_sold)+1)*100)/100;
 else c.lines.push({sku,product_name:String(r.product_name||sku),unit:String(r.unit||'PCS'),size:String(r.size||''),color:String(r.color||''),product_type:String(r.product_type||'SIMPLE'),masterPrice:price,quantity_sold:1,selling_price_override:null});
 if(!c.cartId)c.cartId=newPosCartId();c.meta=posCartMetaFromForm(f);savePosCartState();renderPosCart();
 if(hidden){hidden.value='';hidden.dispatchEvent(new Event('input',{bubbles:true}));hidden.dispatchEvent(new Event('change',{bubbles:true}))}
 if(picker){picker.dataset.selectedRecord='';const q=picker.querySelector('[data-sku-search]'),sel=picker.querySelector('[data-sku-selected]'),clr=picker.querySelector('[data-sku-clear]');if(q)q.value='';if(sel)sel.hidden=true;if(clr)clr.hidden=true}
}
function bindPosCart(){
 const f=document.getElementById('posCartForm');if(!f||f.__slPosCartBound)return;f.__slPosCartBound=true;const c=ensurePosCartState(),m=c.meta||{};
 ['sale_date','branch_location_id','vat_mode','external_receipt_no'].forEach(k=>{const e=f.elements[k];if(e&&m[k]!==undefined&&m[k]!==null){if(k==='branch_location_id'&&e.tagName!=='SELECT'&&String(e.value)!==String(m[k]))return;if(e.tagName==='SELECT'&&m[k]&&!Array.from(e.options).some(o=>o.value===String(m[k])))return;e.value=String(m[k])}});
 f.querySelector('#posCartAddLine')?.addEventListener('click',addPosCartLine);
 f.querySelector('#posCartClear')?.addEventListener('click',()=>{const x=ensurePosCartState();if(x.pendingPayload&&!confirm('The previous response may have been lost. Check Recent Posted Sales for all cart lines before clearing this exact retry payload. Clear anyway?'))return;if(x.lines.length&&!x.pendingPayload&&!confirm('Clear all unposted cart lines?'))return;x.lines=[];x.cartId='';x.pendingPayload=null;x.meta=posCartMetaFromForm(f);const out=document.getElementById('posCartResult');if(out)out.innerHTML='';savePosCartState();renderPosCart()});
 f.addEventListener('change',e=>{const x=ensurePosCartState();if(x.pendingPayload)return;if(e.target.matches('[name="sale_date"],[name="branch_location_id"],[name="vat_mode"],[name="external_receipt_no"]')){x.meta=posCartMetaFromForm(f);savePosCartState();updatePosCartPreview()}});
 f.addEventListener('input',e=>{const x=ensurePosCartState();if(x.pendingPayload)return;if(e.target.matches('[data-cart-qty]')){const i=Number(e.target.dataset.cartQty),line=x.lines[i],q=Number(e.target.value);if(line&&Number.isFinite(q)&&q>0&&(!posCartDiscrete(line.unit)||Math.abs(q-Math.round(q))<0.000001)){line.quantity_sold=q;savePosCartState();updatePosCartPreview()}}else if(e.target.matches('[data-cart-price]')){const i=Number(e.target.dataset.cartPrice),line=x.lines[i],raw=e.target.value;if(line){if(raw==='')line.selling_price_override=null;else{const p=Number(raw);if(Number.isFinite(p)&&p>0)line.selling_price_override=Math.round(p*100)/100}savePosCartState();updatePosCartPreview()}}else if(e.target.matches('[name="sale_date"],[name="branch_location_id"],[name="vat_mode"],[name="external_receipt_no"]')){x.meta=posCartMetaFromForm(f);savePosCartState();updatePosCartPreview()}});
 f.addEventListener('click',e=>{const b=e.target.closest('[data-cart-remove]');if(!b)return;const x=ensurePosCartState();if(x.pendingPayload)return;const i=Number(b.dataset.cartRemove);if(!Number.isInteger(i)||!x.lines[i])return;x.lines.splice(i,1);if(!x.lines.length)x.cartId='';savePosCartState();renderPosCart()});
 f.addEventListener('submit',submitPOSCart);bindSkuPickers();renderPosCart();
}
async function submitPOSCart(e){
 e.preventDefault();const f=e.currentTarget,c=ensurePosCartState();if(f.dataset.busy==='1')return;f.dataset.busy='1';const b=f.querySelector('#posCartSubmit'),out=document.getElementById('posCartResult');if(b){b.disabled=true;b.textContent=c.pendingPayload?'RETRYING SAME CART…':'POSTING ALL LINES…'}
 try{
  if(!c.pendingPayload){if(!f.reportValidity())throw new Error('Correct the highlighted cart fields before checkout.');if(!c.lines.length)throw new Error('Add at least one item before checkout.');c.lines.forEach((line,i)=>{const qty=f.querySelector('[data-cart-qty="'+i+'"]'),price=f.querySelector('[data-cart-price="'+i+'"]');const normalized=normalizePosCartLineInput(qty?qty.value:'',price?price.value:'',line.unit);line.quantity_sold=normalized.quantity_sold;line.selling_price_override=normalized.selling_price_override;});const m=posCartMetaFromForm(f);if(!m.branch_location_id)throw new Error('Select an authorized branch before checkout.');if(!['VAT_INCLUDED','WITHOUT_VAT'].includes(m.vat_mode))throw new Error('Choose a VAT mode.');c.cartId=c.cartId||newPosCartId();c.meta=m;const lines=c.lines.map(l=>{const x={sku:l.sku,quantity_sold:String(l.quantity_sold)};if(l.selling_price_override!==null)x.selling_price=Number(l.selling_price_override).toFixed(2);return x});c.pendingPayload={cart_id:c.cartId,branch_location_id:Number(m.branch_location_id),sale_date:m.sale_date,vat_mode:m.vat_mode,external_receipt_no:m.external_receipt_no,lines};savePosCartState();renderPosCart()}
  let result;try{result=await api16('/sales/cart',{method:'POST',body:JSON.stringify(c.pendingPayload)})}catch(err){if(out)out.innerHTML='<div class="sl-notice sl-danger-block"><b>Cart outcome needs verification.</b> '+esc(err.message||'Request failed')+' Retry the same cart ID or inspect Recent Posted Sales before clearing it. The exact payload is retained.</div>';toast(err.message||'Cart posting failed; the saved retry payload is retained.',true);return}
  const n=c.pendingPayload.lines.length,rows=Array.isArray(result?.lines)?result.lines:[];if(String(result?.status||'').toUpperCase()!=='CLOSED'||Number(result?.line_count)!==n||rows.length!==n||rows.some(x=>String(x.posting_status||'').toUpperCase()!=='CLOSED')){const msg='The server did not verify every line CLOSED. Inspect Recent Posted Sales, then retry the same cart ID; do not begin a new cart.';if(out)out.innerHTML='<div class="sl-notice sl-danger-block"><b>Cart requires verification.</b> '+esc(msg)+'</div>';toast(msg,true);return}
  const t=result.totals||{};if(out)out.innerHTML='<div class="sl-notice"><b>Cart '+esc(result.cart_id||c.cartId)+' posted and verified.</b> '+n+' lines · Gross '+money(t.sales_total||0)+' · VAT '+money(t.vat_amount||0)+' · Net '+money(t.net_sales_excl_vat||0)+'. Inventory, GL and audit were posted in one transaction.</div>';toast('Cart posted and verified: '+String(result.cart_id||c.cartId));
  const meta=c.meta||{};c.lines=[];c.cartId='';c.pendingPayload=null;c.meta={...meta,sale_date:localDate(),external_receipt_no:''};savePosCartState();renderPosCart();const picker=f.querySelector('[data-sku-picker]');if(picker){picker.dataset.selectedRecord='';const h=picker.querySelector('[data-sku-value]'),q=picker.querySelector('[data-sku-search]'),sel=picker.querySelector('[data-sku-selected]'),clear=picker.querySelector('[data-sku-clear]');if(h)h.value='';if(q)q.value='';if(sel)sel.hidden=true;if(clear)clear.hidden=true}
  try{await loadPosSales();await load();await loadNotifications()}catch(refresh){toast('Cart posted successfully; dashboard refresh failed. Do not repost a new cart.',true)}
 }catch(err){toast(err.message||'Cart posting failed. Retry the same cart after reviewing the result.',true)}
 finally{f.dataset.busy='0';if(b){b.disabled=false;b.textContent=ensurePosCartState().pendingPayload?'RETRY SAME CART':'POST COMPLETE CART'}renderPosCart()}
}
