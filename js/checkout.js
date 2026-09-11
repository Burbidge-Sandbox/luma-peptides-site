/* Checkout: validation, shipping method, summary, demo order placement.
   PRODUCTION: replace placeOrder() with a call to your payment provider
   (e.g. Stripe Checkout session) — never handle raw card data yourself. */
(function(){
  const money=Cart.money, CFG=window.LUMA_CONFIG;
  const form=document.getElementById("checkoutForm"), sum=document.getElementById("coSummary");
  if(!Cart.items().length){ location.replace("cart.html"); return; }
  let ship="standard";
  document.getElementById("shipOpts").innerHTML=Object.entries(CFG.shipping).map(([k,v])=>`<label class="ship-opt"><input type="radio" name="ship" value="${k}" ${k===ship?"checked":""}><div class="opt-body"><b>${v.label}</b><span>${k==="standard"?`Free over $${CFG.freeShipThreshold}`:"Cold-pack included"}</span></div><b id="ship-${k}"></b></label>`).join("");
  function drawSummary(){
    const t=Cart.totals(ship);
    document.getElementById("ship-standard").textContent = (t.sub-t.discount>=CFG.freeShipThreshold||t.promo?.type==="ship")?"Free":money(CFG.shipping.standard.price);
    document.getElementById("ship-express").textContent = money(CFG.shipping.express.price);
    sum.innerHTML=Cart.items().map(l=>{const p=Cart.byId(l.id); const v=Cart.variantOf(p,l.variant);return `<div class="mini-line"><div class="thumb">${vialSVG(p)}<i>${l.qty}</i></div><div class="grow"><b>${p.name}</b><small>${v.strength}</small></div><b>${money(Cart.unitPrice(p,l.plan,l.variant)*l.qty)}</b></div>`;}).join("")+
    `<div class="promo"><input id="promoIn" placeholder="Promo code" value="${t.code}" aria-label="Promo code"><button type="button" class="btn btn-dark btn-sm" id="promoBtn">Apply</button></div><div class="promo-msg" id="promoMsg"></div>
<div class="totals"><div><span>Subtotal</span><b>${money(t.sub)}</b></div>${t.discount?`<div class="discount"><span>${t.discountLabel}</span><b>−${money(t.discount)}</b></div>`:""}<div><span>Shipping</span><b>${t.ship?money(t.ship):"Free"}</b></div><div><span>Sales tax${t.taxRate?` (${(t.taxRate*100).toFixed(2)}%)`:""}</span><b>${form.state.value?money(t.tax):"Select state"}</b></div><div class="grand"><span>Total</span><b>${money(t.total)}</b></div></div>`;
    document.getElementById("promoBtn").onclick=()=>{const ok=Cart.setPromo(document.getElementById("promoIn").value); if(!ok){const m=document.getElementById("promoMsg"); m.textContent="That code isn't valid."; m.className="promo-msg err";} else drawSummary();};
    document.getElementById("payBtn").textContent=`Place order · ${money(t.total)} via Venmo`;
  }
  document.getElementById("shipOpts").addEventListener("change",e=>{ship=e.target.value; drawSummary();});
  /* Sales tax follows the shipping state */
  form.state.addEventListener("change",()=>{ Cart.taxState=form.state.value; drawSummary(); });
  /* Abandoned-checkout capture: record the email as soon as it's entered */
  let lastEmail=""; form.email.addEventListener("change",()=>{ const em=form.email.value.trim(); if(/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)&&em!==lastEmail){ lastEmail=em; LumaCapture.track("checkout_email",{email:em,first:form.first.value,last:form.last.value}); } });
  form.phone.addEventListener("change",()=>{ if(lastEmail) LumaCapture.track("checkout_contact",{email:lastEmail,phone:form.phone.value,first:form.first.value,last:form.last.value}); });
  /* Prefill from a signed-in account's saved details */
  try{ const d=JSON.parse(localStorage.getItem("luma_account_details")||"null"); if(d){ const map={email:d.email,org:d.org,phone:d.phone,first:(d.name||"").split(" ")[0],last:(d.name||"").split(" ").slice(1).join(" "),address:d.address1,apt:d.address2,city:d.city,state:d.state,zip:d.zip}; Object.keys(map).forEach(k=>{ if(form[k]&&!form[k].value&&map[k]) form[k].value=map[k]; }); if(form.state.value){ Cart.taxState=form.state.value; } } }catch(e){}
  drawSummary();
  LumaCapture.track("checkout_start",{}); LumaPixels.checkout(Cart.totals(ship).total);


  const rules={
    email:v=>/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)||"Enter a valid email",
    first:v=>v.trim().length>1||"Required", last:v=>v.trim().length>1||"Required",
    address:v=>v.trim().length>4||"Enter your street address", city:v=>v.trim().length>1||"Required",
    state:v=>!!v||"Select a state", zip:v=>/^\d{5}(-\d{4})?$/.test(v)||"Enter a 5-digit ZIP",
    phone:v=>v.replace(/\D/g,"").length>=10||"Enter a 10-digit phone",
    venmoAck:v=>form.venmoAck.checked||"Please confirm the Venmo payment step",
    ack:v=>form.ack.checked||"Required to place an order",
    org:v=>v.trim().length>1||"Enter the laboratory, institution, or organization"
  };
  function validate(field){ const r=rules[field.name]; if(!r) return true; const res=r(field.value); const wrap=field.closest(".field"); wrap.classList.toggle("invalid",res!==true); wrap.querySelector(".err").textContent=res===true?"":res; return res===true; }
  form.addEventListener("blur",e=>{ if(e.target.name&&e.target.type!=="checkbox") validate(e.target); },true);
  form.ack.addEventListener("change",()=>validate(form.ack));
  form.venmoAck.addEventListener("change",()=>validate(form.venmoAck));
  form.addEventListener("submit",e=>{
    e.preventDefault();
    let ok=true, first=null; [...form.elements].forEach(f=>{ if(f.name && !validate(f)){ ok=false; first=first||f; } });
    if(!ok){ first.focus(); first.scrollIntoView({block:"center",behavior:"smooth"}); return; }
    placeOrder();
  });
  function placeOrder(){
    const btn=document.getElementById("payBtn"); btn.disabled=true; btn.textContent="Reserving order…";
    const t=Cart.totals(ship);
    const order={ id:"LP-"+Math.random().toString(36).slice(2,8).toUpperCase(), date:new Date().toISOString(),
      email:form.email.value, org:form.org.value, name:`${form.first.value} ${form.last.value}`, address:`${form.address.value}${form.apt.value?", "+form.apt.value:""}, ${form.city.value}, ${form.state.value} ${form.zip.value}`,
      ship:CFG.shipping[ship].label, items:Cart.items().map(l=>{const p=Cart.byId(l.id); const v=Cart.variantOf(p,l.variant);return {id:p.id,variant:l.variant||"",name:p.name,strength:v.strength,plan:l.plan,qty:l.qty,price:Cart.unitPrice(p,l.plan,l.variant)};}),
      totals:{sub:t.sub,discount:t.discount,ship:t.ship,tax:t.tax,taxRate:t.taxRate,total:t.total,code:t.code}, payment:{method:"venmo",handle:CFG.venmo.handle,status:"awaiting_payment"} };
    LumaPixels.purchase(order);
    LumaCapture.trackBeacon("order_placed",{order, phone:form.phone.value, email:form.email.value, name:order.name, org:order.org||"", address:{line1:form.address.value,line2:form.apt.value,city:form.city.value,state:form.state.value,zip:form.zip.value}, ship:ship, total:t.total, promo:t.code});
    setTimeout(()=>{ try{ sessionStorage.setItem("lumaB_order",JSON.stringify(order)); }catch(e){} Cart.clear(); Cart.setPromo(""); location.href="confirmation.html?order="+order.id; },900);
  }
})();
