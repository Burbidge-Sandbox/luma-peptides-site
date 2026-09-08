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
    sum.innerHTML=Cart.items().map(l=>{const p=Cart.byId(l.id); const v=Cart.variantOf(p,l.variant);return `<div class="mini-line"><div class="thumb">${vialSVG(p)}<i>${l.qty}</i></div><div class="grow"><b>${p.name}</b><small>${v.strength} · ${l.plan==="subscribe"?"Monthly":"One-time"}</small></div><b>${money(Cart.unitPrice(p,l.plan,l.variant)*l.qty)}</b></div>`;}).join("")+
    `<div class="promo"><input id="promoIn" placeholder="Promo code" value="${t.code}" aria-label="Promo code"><button type="button" class="btn btn-dark btn-sm" id="promoBtn">Apply</button></div><div class="promo-msg" id="promoMsg"></div>
<div class="totals"><div><span>Subtotal</span><b>${money(t.sub)}</b></div>${t.discount?`<div class="discount"><span>Discount (${t.code})</span><b>−${money(t.discount)}</b></div>`:""}<div><span>Shipping</span><b>${t.ship?money(t.ship):"Free"}</b></div>${t.tax?`<div><span>Tax</span><b>${money(t.tax)}</b></div>`:""}<div class="grand"><span>Total</span><b>${money(t.total)}</b></div></div>`;
    document.getElementById("promoBtn").onclick=()=>{const ok=Cart.setPromo(document.getElementById("promoIn").value); if(!ok){const m=document.getElementById("promoMsg"); m.textContent="That code isn't valid."; m.className="promo-msg err";} else drawSummary();};
    document.getElementById("payBtn").textContent=`Place order · ${money(t.total)}`;
  }
  document.getElementById("shipOpts").addEventListener("change",e=>{ship=e.target.value; drawSummary();});
  drawSummary();

  /* Card input formatting (demo only) */
  const card=form.card; card.addEventListener("input",()=>{card.value=card.value.replace(/\D/g,"").slice(0,16).replace(/(\d{4})(?=\d)/g,"$1 ");});
  form.exp.addEventListener("input",()=>{let v=form.exp.value.replace(/\D/g,"").slice(0,4); if(v.length>2) v=v.slice(0,2)+"/"+v.slice(2); form.exp.value=v;});
  form.cvc.addEventListener("input",()=>form.cvc.value=form.cvc.value.replace(/\D/g,"").slice(0,4));

  const rules={
    email:v=>/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)||"Enter a valid email",
    first:v=>v.trim().length>1||"Required", last:v=>v.trim().length>1||"Required",
    address:v=>v.trim().length>4||"Enter your street address", city:v=>v.trim().length>1||"Required",
    state:v=>!!v||"Select a state", zip:v=>/^\d{5}(-\d{4})?$/.test(v)||"Enter a 5-digit ZIP",
    phone:v=>v.replace(/\D/g,"").length>=10||"Enter a 10-digit phone",
    name:v=>v.trim().length>2||"Name on card", card:v=>v.replace(/\s/g,"").length===16||"Enter a 16-digit card number",
    exp:v=>{const m=v.match(/^(\d{2})\/(\d{2})$/); if(!m) return "MM/YY"; const mm=+m[1], yy=2000+ +m[2]; const now=new Date(); return (mm>=1&&mm<=12&&(yy>now.getFullYear()||(yy===now.getFullYear()&&mm>=now.getMonth()+1)))||"Card expired";},
    cvc:v=>/^\d{3,4}$/.test(v)||"3–4 digits"
  };
  function validate(field){ const r=rules[field.name]; if(!r) return true; const res=r(field.value); const wrap=field.closest(".field"); wrap.classList.toggle("invalid",res!==true); wrap.querySelector(".err").textContent=res===true?"":res; return res===true; }
  form.addEventListener("blur",e=>{ if(e.target.name) validate(e.target); },true);
  form.addEventListener("submit",e=>{
    e.preventDefault();
    let ok=true, first=null; [...form.elements].forEach(f=>{ if(f.name && !validate(f)){ ok=false; first=first||f; } });
    if(!ok){ first.focus(); first.scrollIntoView({block:"center",behavior:"smooth"}); return; }
    placeOrder();
  });
  function placeOrder(){
    const btn=document.getElementById("payBtn"); btn.disabled=true; btn.textContent="Processing…";
    const t=Cart.totals(ship);
    const order={ id:"LP-"+Math.random().toString(36).slice(2,8).toUpperCase(), date:new Date().toISOString(),
      email:form.email.value, name:`${form.first.value} ${form.last.value}`, address:`${form.address.value}${form.apt.value?", "+form.apt.value:""}, ${form.city.value}, ${form.state.value} ${form.zip.value}`,
      ship:CFG.shipping[ship].label, items:Cart.items().map(l=>{const p=Cart.byId(l.id); const v=Cart.variantOf(p,l.variant);return {name:p.name,strength:v.strength,plan:l.plan,qty:l.qty,price:Cart.unitPrice(p,l.plan,l.variant)};}),
      totals:{sub:t.sub,discount:t.discount,ship:t.ship,tax:t.tax,total:t.total,code:t.code}, last4:form.card.value.replace(/\s/g,"").slice(-4) };
    setTimeout(()=>{ try{ sessionStorage.setItem("luma_order",JSON.stringify(order)); }catch(e){} Cart.clear(); Cart.setPromo(""); location.href="confirmation.html?order="+order.id; },900);
  }
})();
