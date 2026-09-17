/* Luma storefront — port of legacy js/site.js onto WooCommerce.
   Visual helpers (labelSVG, vialSVG, productCard, drawer, gate, toast, reveal)
   are the originals; the cart talks to the WooCommerce Store API instead of
   localStorage. Data comes from window.LUMA (localized by the theme). */
(function(){
  const D=window.LUMA||{}; const CONFIG=D.config||{}; const A=CONFIG.assets||"/";
  window.LUMA_PRODUCTS=D.products||[]; window.LUMA_CATEGORIES=D.categories||{}; window.LUMA_VOLUME_TIERS=D.tiers||[]; window.LUMA_CONFIG=CONFIG;
  const $=(s,r=document)=>r.querySelector(s), $$=(s,r=document)=>[...r.querySelectorAll(s)];
  const money=n=>"$"+(Math.round(n*100)/100).toFixed(2).replace(/\.00$/,"");
  const byId=id=>window.LUMA_PRODUCTS.find(p=>p.id===id);
  const esc=v=>String(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const track=(e,d)=>{ try{ window.dataLayer&&dataLayer.push({event:e,...d}); }catch(x){} };

  /* ---------- Vial artwork (verbatim) ---------- */
  function labelSVG(lines, strength){
    const L=30, R=70, X0=34.5, SAG=0.45;
    const arc=(y,id)=>`<path id="${id}" d="M${L},${y} Q50,${y+SAG*2} ${R},${y}" fill="none"/>`;
    const txt=(id,str,fs,fam,w,fill,ls)=>`<text font-size="${fs}" font-family="${fam}" font-weight="${w}" fill="${fill}" letter-spacing="${ls||0}"><textPath href="#${id}" startOffset="${((X0-L)/(R-L)*100).toFixed(1)}%">${str}</textPath></text>`;
    const uid="l"+Math.random().toString(36).slice(2,7);
    const serif="Cormorant Garamond, Georgia, serif", sans="Inter, system-ui, sans-serif";
    let ys=[69.6,76.6,83.5], defs="", body="";
    ["luma","peptides","co."].forEach((t,i)=>{ defs+=arc(ys[i],uid+"b"+i); body+=txt(uid+"b"+i,t,8,serif,500,"#a23f25","-0.25"); });
    let y=92.3; lines.forEach((t,i)=>{ defs+=arc(y,uid+"p"+i); body+=txt(uid+"p"+i,t,lines.length>1&&t.length>13?2.5:2.85,sans,600,"#292721","-0.05"); y+=3.6; });
    y+=0.8; defs+=arc(y,uid+"s"); body+=txt(uid+"s",strength,2.6,sans,500,"#292721","0");
    defs+=arc(108,uid+"d"); body+=txt(uid+"d","FOR RESEARCH USE ONLY",1.25,sans,500,"#a23f25","-0.02");
    return `<svg class="photo-label" viewBox="0 0 100 150" aria-hidden="true"><defs>${defs}<linearGradient id="${uid}g" x1="0" x2="1"><stop offset="0" stop-color="#000" stop-opacity=".06"/><stop offset=".22" stop-color="#000" stop-opacity="0"/><stop offset=".78" stop-color="#000" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity=".07"/></linearGradient></defs><rect x="${L}" y="58" width="${R-L}" height="55" fill="url(#${uid}g)"/>${body}</svg>`;
  }
  function vialSVG(p, opts={}){
    if(p.image && opts.eager){
      return `<span class="vial product-photo has-photo photo-full" role="img" aria-label="${esc(p.name)} — ${esc(p.strength)}">
      <img src="${esc(p.image)}" width="1600" height="1200" alt="" style="object-position:${p.imageFocus||'50% 50%'}" loading="eager" decoding="async" fetchpriority="high"></span>`;
    }
    const lines=(p.label || [p.name.toUpperCase()]).filter(Boolean);
    const photo=p.category==='supplies'?'vial-liquid':'vial-studio';
    const strength=(p.strength||"").replace(/ vial$/,'').toUpperCase();
    const labelLines=lines.filter(line=>!/^\d+(?:\.\d+)?(?:MG|ML)$/i.test(line));
    return `<span class="vial product-photo" role="img" aria-label="${esc(p.name)} — ${esc(p.strength)}, Luma vial product visualization">
      <img src="${A}assets/products/${photo}-512.jpg" srcset="${A}assets/products/${photo}-512.jpg 512w, ${A}assets/products/${photo}-1024.jpg 1024w" sizes="${opts.eager?'(max-width: 900px) 85vw, 520px':'(max-width: 760px) 45vw, 280px'}" width="1024" height="1536" alt="" loading="${opts.eager?'eager':'lazy'}" decoding="async" ${opts.eager?'fetchpriority="high"':''}>
      ${labelSVG(labelLines.map(esc), esc(strength))}
    </span>`;
  }
  window.vialSVG=vialSVG;

  /* ---------- Cart (WooCommerce Store API) ---------- */
  const API=CONFIG.storeApi; let nonce=null; let cartData=null;
  async function api(path,opts={}){
    const h={"Content-Type":"application/json"}; if(nonce) h["Nonce"]=nonce;
    const r=await fetch(API+path,{credentials:"same-origin",headers:h,...opts});
    const n=r.headers.get("Nonce"); if(n) nonce=n;
    const j=await r.json().catch(()=>null);
    if(!r.ok) throw new Error((j&&j.message)||"Cart error");
    return j;
  }
  function variantOf(p,key){
    if(!p) return null;
    if(p.variants&&p.variants.length){ return p.variants.find(v=>v.key===key)||p.variants[0]; }
    return {key:null,label:p.strength,strength:p.strength,once:p.once,stock:p.stock,wc_id:p.wc_id};
  }
  const Cart={
    async refresh(){ try{ cartData=await api("cart"); }catch(e){ cartData=null; } render(); return cartData; },
    async add(id,plan,qty=1,variant=null){
      const p=byId(id); if(!p) return;
      const v=variantOf(p,variant);
      if(p.stock==="out"||v.stock==="out"){ toast(`<b>${esc(p.name)}${v.key?" "+esc(v.label):""}</b> is currently out of stock.`); return; }
      try{ cartData=await api("cart/add-item",{method:"POST",body:JSON.stringify({id:v.wc_id,quantity:qty})}); }
      catch(e){ toast(esc(e.message)); return; }
      render(); track("add_to_cart",{id,qty});
      toast(`<b>${esc(p.name)}${v.key?" "+esc(v.label):""}</b> added to your cart. <a href="${CONFIG.urls.cart}">View cart</a>`); openDrawer();
    },
    async setQty(key,qty){ qty=Math.max(0,Math.min(10,qty|0)); try{ cartData=qty?await api("cart/update-item",{method:"POST",body:JSON.stringify({key,quantity:qty})}):await api("cart/remove-item",{method:"POST",body:JSON.stringify({key})}); }catch(e){ toast(esc(e.message)); } render(); },
    async remove(key){ try{ cartData=await api("cart/remove-item",{method:"POST",body:JSON.stringify({key})}); }catch(e){} render(); },
    count:()=>cartData?cartData.items_count:0,
    money, byId, variantOf
  };
  window.Cart=Cart;
  const cents=(s,d)=>Number(s||0)/Math.pow(10,d==null?2:d);

  function lineHTML(it){
    const p=window.LUMA_PRODUCTS.find(x=>x.wc_id===it.id||(x.variants||[]).some(v=>v.wc_id===it.id))||{name:it.name,strength:"",label:[it.name.toUpperCase()],category:""};
    const v=(p.variants||[]).find(v=>v.wc_id===it.id);
    const mi=it.prices.currency_minor_unit;
    return `<div class="line" data-key="${it.key}">
 <div class="thumb">${vialSVG(v?{...p,strength:v.strength,label:[p.label[0],v.key.toUpperCase()]}:p)}</div>
 <div><h4><a href="${esc(p.url||"#")}">${esc(p.name)}</a></h4><div class="variant">${esc(v?v.strength:p.strength)}</div>
  <div class="qty"><button data-dec aria-label="Decrease">−</button><input type="number" value="${it.quantity}" min="1" max="10" aria-label="Quantity"><button data-inc aria-label="Increase">+</button></div></div>
 <div class="line-price">${money(cents(it.totals.line_total,mi))}<button class="remove" data-remove>Remove</button></div>
</div>`;
  }
  function render(){
    const c=$("#cartCount"); if(c){c.textContent=Cart.count(); c.classList.toggle("show",Cart.count()>0);}
    const body=$("#drawerBody"), foot=$("#drawerFoot"); if(!body) return;
    if(!cartData||!cartData.items.length){ body.innerHTML=`<div class="empty-cart"><svg viewBox="0 0 24 24"><path d="M6 7h12l1 14H5z"/><path d="M9 7V5a3 3 0 0 1 6 0v2"/></svg><p>Your cart is empty.</p><a class="btn btn-outline btn-sm" href="${CONFIG.urls.shop}">Browse the catalog</a></div>`; foot.innerHTML=""; return; }
    const t=cartData.totals, mi=t.currency_minor_unit, sub=cents(t.total_items,mi), disc=cents(t.total_discount,mi); const thr=Number(CONFIG.freeShipThreshold??150); const left=Math.max(0,thr-(sub-disc));
    body.innerHTML=cartData.items.map(lineHTML).join("");
    const P=CONFIG.promises||{};
    foot.innerHTML=`<div class="ship-bar">${thr>0?(left>0?`You're <b>${money(left)}</b> away from free shipping`:`🎉 You've unlocked <b>free shipping</b>`):`<b>Free next-day shipping</b> on every order${P.sameDayCounty?` · same-day in ${esc(P.sameDayCounty)} before ${esc(P.sameDayCutoff||"noon")}`:""}`}${thr>0?`<div class="track"><div class="fill" style="width:${Math.min(100,((sub-disc)/thr)*100)}%"></div></div>`:""}</div>
<div class="totals"><div><span>Subtotal</span><b>${money(sub)}</b></div>${disc?`<div class="discount"><span>Volume discount</span><b>−${money(disc)}</b></div>`:""}<div><span>Sales tax</span><b>Calculated at checkout</b></div></div>
<a class="btn btn-primary btn-block" href="${CONFIG.urls.checkout}">Checkout</a>
<a class="btn btn-ghost btn-block" href="${CONFIG.urls.cart}" style="margin-top:.3rem">View full cart</a>`;
  }
  /* Full cart page (port of legacy cart.js) — same Store API cart as the drawer. */
  function renderPage(){
    const list=$("#cartList"), sum=$("#summaryBody"); if(!list||!sum) return;
    if(!cartData||!cartData.items.length){ list.innerHTML=`<div class="empty-cart"><p>Your cart is empty.</p><a class="btn btn-primary" href="${CONFIG.urls.shop}">Browse the catalog</a></div>`; sum.innerHTML=""; return; }
    const t=cartData.totals, mi=t.currency_minor_unit, sub=cents(t.total_items,mi), disc=cents(t.total_discount,mi); const thr=Number(CONFIG.freeShipThreshold??150);
    const ship=thr>0&&(sub-disc)<thr?8:0; const coupon=(cartData.coupons||[])[0]; const P=CONFIG.promises||{};
    list.innerHTML=cartData.items.map(lineHTML).join("");
    sum.innerHTML=`<div class="promo"><input id="promoIn" placeholder="Promo code" value="${coupon?esc(coupon.code):""}" aria-label="Promo code"><button class="btn btn-dark btn-sm" id="promoBtn">${coupon?"Remove":"Apply"}</button></div><div class="promo-msg" id="promoMsg"></div>
<div class="totals"><div><span>Subtotal</span><b>${money(sub)}</b></div>${disc?`<div class="discount"><span>${coupon?"Promo "+esc(coupon.code):"Volume discount"}</span><b>−${money(disc)}</b></div>`:""}<div><span>${thr>0?"Standard shipping":"Next-day shipping"}</span><b>${ship?money(ship):"Free"}</b></div>${P.sameDayCounty?`<div style="font-size:.78rem;color:var(--muted);padding:0 0 .4rem">Same-day delivery in ${esc(P.sameDayCounty)} for orders placed before ${esc(P.sameDayCutoff||"noon")}.</div>`:""}<div><span>Sales tax</span><b>Calculated at checkout</b></div><div class="grand"><span>Estimated total</span><b>${money(sub-disc+ship)}</b></div></div>
<a class="btn btn-primary btn-block" href="${CONFIG.urls.checkout}">Proceed to checkout</a>
<div class="secure"><svg viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>Secure checkout · All sales final on shipped goods</div>`;
    $("#promoBtn").onclick=async()=>{ const m=$("#promoMsg"); const code=$("#promoIn").value.trim();
      try{ cartData=coupon?await api("cart/remove-coupon",{method:"POST",body:JSON.stringify({code:coupon.code})}):await api("cart/apply-coupon",{method:"POST",body:JSON.stringify({code})}); render(); }
      catch(e){ m.textContent=e.message||"That code isn't valid."; m.className="promo-msg err"; } };
  }
  const renderDrawerOnly=render;
  render=function(){ renderDrawerOnly(); renderPage(); };
  window.renderCart=render;

  /* ---------- Drawer / menu / toast ---------- */
  let returnFocus=null;
  function openDrawer(){ returnFocus=document.activeElement; $("#drawer").classList.add("open"); $("#overlay").classList.add("show"); $("#drawer").setAttribute("aria-hidden","false"); document.body.style.overflow="hidden"; $("#closeDrawer").focus(); }
  function closeDrawer(){ const wasOpen=$("#drawer").classList.contains("open"); $("#drawer").classList.remove("open"); $("#overlay").classList.remove("show"); $("#drawer").setAttribute("aria-hidden","true"); if(wasOpen){document.body.style.overflow="";returnFocus?.focus();} }
  window.openDrawer=openDrawer;
  let toastT;
  function toast(html){ const t=$("#toast"); t.innerHTML=html; t.classList.add("show"); clearTimeout(toastT); toastT=setTimeout(()=>t.classList.remove("show"),3200); }
  window.toast=toast;

  /* ---------- Boot ---------- */
  document.addEventListener("DOMContentLoaded",()=>{
    Cart.refresh();
    $("#cartBtn").addEventListener("click",openDrawer);
    $("#closeDrawer").addEventListener("click",closeDrawer);
    $("#overlay").addEventListener("click",closeDrawer);
    function menu(open){$("#mobileMenu").classList.toggle("open",open);$("#burger").setAttribute("aria-expanded",String(open));document.body.style.overflow=open?"hidden":"";(open?$("#closeMenu"):$("#burger")).focus();}
    $("#burger").addEventListener("click",()=>menu(true));
    $("#closeMenu").addEventListener("click",()=>menu(false));
    document.addEventListener("keydown",e=>{
      if(e.key==="Escape"){closeDrawer();if($("#mobileMenu").classList.contains("open"))menu(false);}
      const panel=$("#drawer").classList.contains("open")?$("#drawer"):$("#mobileMenu").classList.contains("open")?$("#mobileMenu"):null;
      if(e.key!=="Tab"||!panel)return;
      const items=[...panel.querySelectorAll('a[href],button,input')].filter(el=>!el.disabled&&el.getClientRects().length);
      const first=items[0],last=items[items.length-1];
      if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}
      else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}
    });
    const hdr=$("#header"); const onScroll=()=>hdr.classList.toggle("scrolled",scrollY>8); onScroll(); addEventListener("scroll",onScroll,{passive:true});

    document.addEventListener("click",e=>{
      const line=e.target.closest(".line"); if(!line) return;
      const key=line.dataset.key; const it=cartData&&cartData.items.find(x=>x.key===key); if(!it) return;
      if(e.target.closest("[data-inc]")) Cart.setQty(key,it.quantity+1);
      else if(e.target.closest("[data-dec]")) Cart.setQty(key,it.quantity-1);
      else if(e.target.closest("[data-remove]")) Cart.remove(key);
    });
    document.addEventListener("change",e=>{
      const line=e.target.closest(".line"); if(!line||e.target.type!=="number") return;
      Cart.setQty(line.dataset.key,+e.target.value);
    });
    document.addEventListener("click",e=>{
      const b=e.target.closest("[data-add]"); if(!b) return; e.preventDefault();
      const qty=+(b.dataset.qty||($("#qtyInput")?.value)||1);
      Cart.add(b.dataset.add,b.dataset.plan||"once",qty,b.dataset.variant||null);
    });

    /* Entry disclaimer / age gate — remembered for 30 days */
    (function(){
      const KEY="lumaB_gate_ok", DAYS=30, gate=$("#gate");
      let ok=false; try{ ok = (+localStorage.getItem(KEY)||0) > Date.now(); }catch(e){}
      if(ok||document.body.classList.contains("wp-admin")) return;
      gate.hidden=false; document.body.classList.add("gate-open");
      const btn=$("#gateEnter"), ret=document.activeElement;
      btn.addEventListener("click",()=>{ try{ localStorage.setItem(KEY,String(Date.now()+DAYS*864e5)); }catch(e){} track("gate_accept",{}); gate.classList.add("closing"); setTimeout(()=>{ gate.hidden=true; document.body.classList.remove("gate-open"); ret?.focus?.(); },300); });
      btn.focus();
      gate.addEventListener("keydown",e=>{ if(e.key!=="Tab") return; const f=[...gate.querySelectorAll("input,button:not([disabled]),a[href]")]; const a=f[0], z=f[f.length-1]; if(e.shiftKey&&document.activeElement===a){e.preventDefault();z.focus();} else if(!e.shiftKey&&document.activeElement===z){e.preventDefault();a.focus();} });
    })();

    const io=new IntersectionObserver(es=>es.forEach(x=>{ if(x.isIntersecting){x.target.classList.add("in"); io.unobserve(x.target);} }),{threshold:.12});
    $$(".reveal").forEach(el=>io.observe(el));

    $$("form[data-newsletter]").forEach(f=>f.addEventListener("submit",e=>{e.preventDefault(); toast("Thanks — you're on the list."); f.reset();}));
  });

  /* ---------- Product card (verbatim, URLs from data) ---------- */
  window.productCard = function(p){
    const oos = p.stock==="out";
    const v0 = p.variants&&p.variants.length ? p.variants[0] : null;
    const price = v0 ? v0.once : p.once, strength = v0 ? v0.strength : p.strength;
    const sizes = v0 ? `<div class="card-sizes" role="group" aria-label="Vial size">${p.variants.map((v,i)=>`<button type="button" class="card-size${i===0?" is-on":""}${v.stock==="out"?" is-out":""}" data-size="${v.key}" aria-pressed="${i===0}">${v.label}</button>`).join("")}</div>` : "";
    return `<article class="card reveal${oos?" oos":""}" data-pid="${p.id}"${v0?` data-variant="${v0.key}"`:""}>
 ${oos?`<span class="badge soft">Waitlist</span>`:(p.badge?`<span class="badge">${p.badge}</span>`:"")}
 <a class="stretch" href="${esc(p.url)}${v0?"?dose="+v0.key:""}" aria-label="${esc(p.name)}"></a>
 <div class="card-vial">${vialSVG(v0?{...p,strength:strength,label:[p.label[0],v0.key.toUpperCase()]}:p)}</div>
 <h3>${esc(p.name)}</h3>
 <div class="strength"><span class="card-strength">${esc(strength)}</span>${oos?' · <span class="oos-text">Out of stock</span>':''}</div>
 ${sizes}
 <div class="price"><span class="card-price">${money(price)}</span> <small>/ vial</small></div>
 <div class="card-actions">${oos?`<button class="btn btn-outline btn-sm" data-waitlist="${p.id}">Join the waitlist</button>`:`<button class="btn btn-outline btn-sm" data-add="${p.id}" data-plan="once"${v0?` data-variant="${v0.key}"`:""}>Add to cart</button>`}</div>
</article>`;
  };
  document.addEventListener("click",e=>{
    const b=e.target.closest(".card-size"); if(!b) return; e.preventDefault(); e.stopPropagation();
    const card=b.closest(".card"), p=byId(card.dataset.pid); if(!p||!p.variants) return;
    const v=p.variants.find(x=>x.key===b.dataset.size); if(!v) return;
    card.dataset.variant=v.key;
    card.querySelectorAll(".card-size").forEach(x=>{ const on=x===b; x.classList.toggle("is-on",on); x.setAttribute("aria-pressed",on); });
    card.querySelector(".card-price").textContent=money(v.once);
    card.querySelector(".card-strength").textContent=v.strength;
    card.querySelector(".card-vial").innerHTML=vialSVG({...p,strength:v.strength,label:[p.label[0],v.key.toUpperCase()]});
    const add=card.querySelector("[data-add]"); if(add){ add.dataset.variant=v.key; add.disabled=v.stock==="out"; add.textContent=v.stock==="out"?"Out of stock":"Add to cart"; }
    card.querySelector("a.stretch").href=`${p.url}?dose=${v.key}`;
  });

  /* ---------- Waitlist ---------- */
  window.joinWaitlist = async function(id){
    const p=byId(id); if(!p) return;
    const email=prompt(`${p.name} is out of stock. Enter an email address to be notified when the next tested lot is released:`);
    if(email===null) return;
    if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())){ toast("Please enter a valid email address."); return; }
    try{ await fetch(CONFIG.ajax,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:new URLSearchParams({action:"luma_waitlist",product:id,email:email.trim()})}); }catch(e){}
    toast(`Noted. An email will be sent when <b>${esc(p.name)}</b> is back in stock.`);
  };
  document.addEventListener("click",e=>{ const b=e.target.closest("[data-waitlist]"); if(!b) return; e.preventDefault(); joinWaitlist(b.dataset.waitlist); });

  /* ---------- Contact form ---------- */
  /* Block checkout: draw the same labelled vial as the catalog in the order summary (React re-renders, so observe). */
  (function(){
    if(!document.querySelector(".checkout-page")||!window.LUMA_PRODUCTS) return;
    const find=(name)=>{ name=(name||"").trim().toLowerCase(); return window.LUMA_PRODUCTS.find(x=>x.name.toLowerCase()===name)||window.LUMA_PRODUCTS.find(x=>name.startsWith(x.name.toLowerCase()))||null; };
    const paint=()=>{ document.querySelectorAll(".wc-block-components-order-summary-item").forEach(it=>{
      const box=it.querySelector(".wc-block-components-order-summary-item__image"); if(!box||box.querySelector(".product-photo")) return;
      const nm=it.querySelector(".wc-block-components-product-name"); const p=nm&&find(nm.textContent); if(!p) return;
      const meta=(it.querySelector(".wc-block-components-product-details")||{}).textContent||""; const v=(p.variants||[]).find(v=>meta.toUpperCase().includes(v.key.toUpperCase()));
      const img=box.querySelector("img"); if(img) img.remove();
      box.insertAdjacentHTML("beforeend", vialSVG(v?{...p,strength:v.strength,label:[p.label[0],v.key.toUpperCase()]}:p));
    }); };
    paint(); new MutationObserver(paint).observe(document.body,{childList:true,subtree:true});
  })();
  document.addEventListener("submit",async e=>{
    const f=e.target; if(f.id!=="contactForm") return; e.preventDefault();
    const fd=new FormData(f); fd.append("action","luma_contact");
    try{ await fetch(CONFIG.ajax,{method:"POST",credentials:"same-origin",body:fd}); toast("Message sent. A reply will follow within one business day."); f.reset(); }
    catch(x){ toast("Could not send. Email info@lumaresearchco.com instead."); }
  });
})();
