/* ============================================================
   Luma — shared site runtime: header, footer, vial renderer,
   cart state (localStorage), cart drawer, toast, reveal.
   ============================================================ */
(function(){
  const $ = (s,el=document)=>el.querySelector(s);
  const $$ = (s,el=document)=>[...el.querySelectorAll(s)];
  const money = n => { n=Math.round(n*100)/100; const d=Number.isInteger(n)?0:2; return "$"+n.toLocaleString("en-US",{minimumFractionDigits:d,maximumFractionDigits:2}); };
  const products = window.LUMA_PRODUCTS || [];
  const byId = id => products.find(p=>p.id===id);

  /* ---------- Config (edit freely) ---------- */
  const CONFIG = {
    freeShipThreshold:150,
    shipping:{standard:{label:"Standard (3–5 days)",price:8},express:{label:"Express (1–2 days)",price:24}},
    promos:{GLOW15:{type:"pct",value:15,label:"15% off"},WELCOME10:{type:"pct",value:10,label:"10% off"},FREESHIP:{type:"ship",value:0,label:"Free shipping"}},
    taxRate:0 /* set e.g. 0.07 for demo tax */
  };
  window.LUMA_CONFIG = CONFIG;

  /* One photographic master, with exact catalog typography on the paper label.
     Keep the legacy helper name for cart/checkout compatibility. */
  function vialSVG(p, opts={}){
    const escape = value => String(value).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    if(p.image && opts.eager){ /* unique render on the product detail page only; grids keep the studio vial */
      return `<span class="vial product-photo has-photo${opts.eager?' photo-full':''}" role="img" aria-label="${escape(p.name)} — ${escape(p.strength)}">
      <img src="${p.image}-800.jpg" srcset="${p.image}-800.jpg 800w, ${p.image}.jpg 1600w" sizes="${opts.eager?'(max-width: 900px) 92vw, 620px':'(max-width: 760px) 45vw, 300px'}" width="1600" height="1200" alt="" style="object-position:${p.imageFocus||'50% 50%'}" loading="${opts.eager?'eager':'lazy'}" decoding="async" ${opts.eager?'fetchpriority="high"':''}></span>`;
    }
    const lines=(p.label || [p.name.toUpperCase()]).filter(Boolean);
    const photo=p.category==='supplies'?'vial-liquid':'vial-studio';
    const strength=p.strength.replace(/ vial$/,'').toUpperCase();
    const labelLines=lines.filter(line=>!/^\d+(?:\.\d+)?(?:MG|ML)$/i.test(line));
    return `<span class="vial product-photo" role="img" aria-label="${escape(p.name)} — ${escape(p.strength)}, Luma vial product visualization">
      <img src="assets/products/${photo}-512.jpg" srcset="assets/products/${photo}-512.jpg 512w, assets/products/${photo}-1024.jpg 1024w" sizes="${opts.eager?'(max-width: 900px) 85vw, 520px':'(max-width: 760px) 45vw, 280px'}" width="1024" height="1536" alt="" loading="${opts.eager?'eager':'lazy'}" decoding="async" ${opts.eager?'fetchpriority="high"':''}>
      <span class="photo-brand" aria-hidden="true">luma<br>peptides<br>co.</span>
      <span class="photo-product" aria-hidden="true">${labelLines.map(escape).join('<br>')}<span class="photo-strength">${escape(strength)}</span></span>
      <span class="photo-disclaimer" aria-hidden="true">FOR RESEARCH USE ONLY</span>
    </span>`;
  }
  window.vialSVG = vialSVG;

  /* ---------- Cart state ---------- */
  const KEY="luma_cart_v1";
  let cart = [];
  try{ cart = JSON.parse(localStorage.getItem(KEY)||"[]"); }catch(e){ cart=[]; }
  const save = ()=>{ try{localStorage.setItem(KEY,JSON.stringify(cart));}catch(e){} render(); };
  /* Resolve a dosage variant (or the product's own fields when it has none) */
  function variantOf(p,key){
    if(!p) return null;
    if(p.variants&&p.variants.length){ return p.variants.find(v=>v.key===key)||p.variants[0]; }
    return {key:null,label:p.strength,strength:p.strength,subscribe:p.subscribe,once:p.once,stock:p.stock};
  }
  function lineKey(id,plan,variant){return id+"|"+plan+(variant?"|"+variant:"");}
  function unitPrice(p,plan,variant){ const v=variantOf(p,variant); return plan==="subscribe" && v.subscribe ? v.subscribe : v.once; }
  const Cart = {
    items:()=>cart,
    add(id,plan="once",qty=1,variant=null){
      const p=byId(id); if(!p) return;
      const v=variantOf(p,variant); variant=v.key;
      if(p.stock==="out"||v.stock==="out"){ toast(`<b>${p.name}${v.key?" "+v.label:""}</b> is currently out of stock.`); return; }
      if(plan==="subscribe" && !v.subscribe) plan="once";
      const k=lineKey(id,plan,variant);
      const ex=cart.find(l=>l.key===k);
      if(ex) ex.qty=Math.min(10,ex.qty+qty); else cart.push({key:k,id,plan,qty,variant});
      save(); toast(`<b>${p.name}${v.key?" "+v.label:""}</b> added to your cart. <a href="cart.html">View cart</a>`); openDrawer();
    },
    setQty(key,qty){ const l=cart.find(l=>l.key===key); if(!l) return; l.qty=Math.max(0,Math.min(10,qty|0)); if(!l.qty) cart=cart.filter(x=>x.key!==key); save(); },
    remove(key){ cart=cart.filter(l=>l.key!==key); save(); },
    clear(){ cart=[]; save(); },
    count:()=>cart.reduce((a,l)=>a+l.qty,0),
    subtotal:()=>cart.reduce((a,l)=>{const p=byId(l.id);return a+(p?unitPrice(p,l.plan,l.variant)*l.qty:0);},0),
    promo(){ try{return localStorage.getItem("luma_promo")||"";}catch(e){return "";} },
    setPromo(code){ code=(code||"").trim().toUpperCase(); if(code && !CONFIG.promos[code]) return false; try{localStorage.setItem("luma_promo",code);}catch(e){} render(); return true; },
    totals(shipMethod="standard"){
      const sub=Cart.subtotal(); const code=Cart.promo(); const promo=CONFIG.promos[code];
      let discount=0; if(promo&&promo.type==="pct") discount=sub*promo.value/100;
      let ship = sub===0?0 : (sub-discount>=CONFIG.freeShipThreshold ? 0 : CONFIG.shipping[shipMethod].price);
      if(promo&&promo.type==="ship") ship=0;
      if(shipMethod==="express" && sub>0) ship=CONFIG.shipping.express.price; /* express always paid */
      const tax=(sub-discount)*CONFIG.taxRate;
      return {sub,discount,ship,tax,total:sub-discount+ship+tax,code,promo};
    },
    unitPrice, variantOf, byId, money
  };
  window.Cart=Cart;

  /* ---------- Header / footer ---------- */
  const page = location.pathname.split("/").pop() || "index.html";
  const NAV=[["index.html","Home"],["shop.html","Treatments"],["how-it-works.html","How It Works"],["verify.html","Verify a Lot"],["about.html","About Us"],["contact.html","Contact"]];
  function header(){
    const links=NAV.map(([h,t])=>`<li><a href="${h}" ${page===h?'aria-current="page"':''}>${t}</a></li>`).join("");
    return `<a class="skip" href="#main">Skip to content</a>
<div class="announce">Storefront preview &nbsp;·&nbsp; Explore the Luma collection</div>
<header class="header" id="header"><div class="wrap nav">
 <a class="logo" href="index.html" aria-label="Luma Peptides Co. home"><span>luma</span><span>peptides</span><span>co.</span></a>
 <ul class="nav-links">${links}</ul>
 <div class="nav-actions">
  <a class="btn btn-primary btn-sm" href="shop.html">Shop All</a>
  <button class="icon-btn" id="cartBtn" aria-label="Open cart"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M6 7h12l1 14H5z"/><path d="M9 7V5a3 3 0 0 1 6 0v2"/></svg><span class="cart-count" id="cartCount">0</span></button>
  <button class="icon-btn burger" id="burger" aria-label="Open menu" aria-controls="mobileMenu" aria-expanded="false"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
 </div>
</div></header>
<nav class="mobile-menu" id="mobileMenu" aria-label="Mobile">
 <button class="icon-btn close" id="closeMenu" aria-label="Close menu"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
 ${NAV.map(([h,t])=>`<a href="${h}">${t}</a>`).join("")}
 <a href="cart.html">Cart</a>
</nav>`;
  }
  function footer(){
    return `<footer class="footer"><div class="wrap">
 <div class="footer-grid">
  <div><a class="logo" href="index.html"><span>luma</span><span>peptides</span><span>co.</span></a><p class="tag">A considered approach to peptides. Explore the collection and get to know Luma.</p></div>
  <div><h4>Treatments</h4><ul><li><a href="shop.html">All Treatments</a></li><li><a href="shop.html?cat=weight">Weight & Metabolic</a></li><li><a href="shop.html?cat=skin">Skin & Glow</a></li><li><a href="shop.html?cat=recovery">Recovery</a></li><li><a href="shop.html?cat=longevity">Longevity & Sleep</a></li></ul></div>
  <div><h4>Company</h4><ul><li><a href="about.html">About Us</a></li><li><a href="how-it-works.html">How It Works</a></li><li><a href="verify.html">Verify a Lot</a></li><li><a href="contact.html">Contact</a></li></ul></div>
  <div><h4>Support</h4><ul><li><a href="faq.html">FAQ</a></li><li><a href="shipping-returns.html">Shipping</a></li><li><a href="shipping-returns.html#returns">Returns</a></li><li><a href="privacy.html">Privacy Policy</a></li><li><a href="terms.html">Terms</a></li></ul></div>
  <div><h4>Follow Us</h4><div class="social">
   <a href="#" aria-label="Instagram"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".8" fill="currentColor"/></svg></a>
   <a href="#" aria-label="Facebook"><svg viewBox="0 0 24 24"><path d="M14 8h3V4h-3a4 4 0 0 0-4 4v3H7v4h3v6h4v-6h3l1-4h-4V8a1 1 0 0 1 1-1z"/></svg></a>
   <a href="mailto:hello@lumapeptides.co" aria-label="Email"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg></a>
  </div></div>
 </div>
 <p class="disclaimer">These statements have not been evaluated by the Food and Drug Administration. Products are not intended to diagnose, treat, cure, or prevent any disease. Consult a licensed healthcare provider before beginning any peptide protocol. This is a student project storefront.</p>
 <div class="footer-bottom"><span>© ${new Date().getFullYear()} Luma Peptides Co. All rights reserved.</span><div class="payments"><span>VISA</span><span>MC</span><span>AMEX</span><span>APPLE PAY</span></div></div>
</div></footer>
<div class="overlay" id="overlay"></div>
<aside class="drawer" id="drawer" role="dialog" aria-modal="true" aria-label="Shopping cart" aria-hidden="true">
 <div class="drawer-head"><h2>Your cart</h2><button class="icon-btn" id="closeDrawer" aria-label="Close cart"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>
 <div class="drawer-body" id="drawerBody"></div>
 <div class="drawer-foot" id="drawerFoot"></div>
</aside>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<div class="gate" id="gate" hidden>
 <div class="gate-card" role="dialog" aria-modal="true" aria-labelledby="gateTitle" aria-describedby="gateBody">
  <div class="logo" aria-hidden="true"><span>luma</span><span>peptides co.</span></div>
  <span class="eyebrow">Before you continue</span>
  <h2 id="gateTitle">A quick note about what you'll find here.</h2>
  <div class="gate-body" id="gateBody">
   <p>Products on this site are supplied <b>for research purposes only</b> and are not intended for human or veterinary use. Nothing here is medical advice, and statements have not been evaluated by the Food and Drug Administration. Products are not intended to diagnose, treat, cure, or prevent any disease.</p>
   <p>Consult a licensed healthcare provider before beginning any protocol. This is a student project storefront; orders are simulated and no payment is processed.</p>
  </div>
  <label class="gate-check"><input type="checkbox" id="gateAgree"><span>I confirm that I am <b>21 years of age or older</b>, and I agree to the <a href="terms.html" target="_blank" rel="noopener">Terms of Service</a> and <a href="privacy.html" target="_blank" rel="noopener">Privacy Policy</a>.</span></label>
  <div class="gate-actions">
   <button class="btn btn-primary" id="gateEnter" type="button">Enter the site</button>
   <a class="btn btn-outline" href="https://www.google.com" id="gateLeave">Leave</a>
  </div>
 </div>
</div>`;
  }

  /* ---------- Rendering ---------- */
  function lineHTML(l){
    const p=byId(l.id); if(!p) return "";
    const price=unitPrice(p,l.plan,l.variant); const v=variantOf(p,l.variant);
    return `<div class="line" data-key="${l.key}">
 <div class="thumb">${vialSVG(p)}</div>
 <div><h4><a href="product.html?id=${p.id}${v.key?"&dose="+v.key:""}">${p.name}</a></h4><div class="variant">${v.strength} · ${l.plan==="subscribe"?"Monthly subscription":"One-time"}</div>
  <div class="qty"><button data-dec aria-label="Decrease">−</button><input type="number" value="${l.qty}" min="1" max="10" aria-label="Quantity"><button data-inc aria-label="Increase">+</button></div></div>
 <div class="line-price">${money(price*l.qty)}<button class="remove" data-remove>Remove</button></div>
</div>`;
  }
  function render(){
    const c=$("#cartCount"); if(c){c.textContent=Cart.count(); c.classList.toggle("show",Cart.count()>0);}
    const body=$("#drawerBody"), foot=$("#drawerFoot");
    if(body){
      if(!cart.length){ body.innerHTML=`<div class="empty-cart"><svg viewBox="0 0 24 24"><path d="M6 7h12l1 14H5z"/><path d="M9 7V5a3 3 0 0 1 6 0v2"/></svg><p>Your cart is empty.</p><a class="btn btn-outline btn-sm" href="shop.html">Browse treatments</a></div>`; foot.innerHTML=""; }
      else{
        const t=Cart.totals(); const left=Math.max(0,CONFIG.freeShipThreshold-(t.sub-t.discount));
        body.innerHTML=cart.map(lineHTML).join("");
        foot.innerHTML=`<div class="ship-bar">${left>0?`You're <b>${money(left)}</b> away from free shipping`:`🎉 You've unlocked <b>free shipping</b>`}<div class="track"><div class="fill" style="width:${Math.min(100,((t.sub-t.discount)/CONFIG.freeShipThreshold)*100)}%"></div></div></div>
<div class="totals"><div><span>Subtotal</span><b>${money(t.sub)}</b></div>${t.discount?`<div class="discount"><span>Discount (${t.code})</span><b>−${money(t.discount)}</b></div>`:""}</div>
<a class="btn btn-primary btn-block" href="checkout.html">Checkout</a>
<a class="btn btn-ghost btn-block" href="cart.html" style="margin-top:.3rem">View full cart</a>`;
      }
    }
    document.dispatchEvent(new CustomEvent("cart:change"));
  }
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
    document.body.insertAdjacentHTML("afterbegin",header());
    document.body.insertAdjacentHTML("beforeend",footer());
    render();
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

    /* Delegated cart controls (drawer + cart page share markup) */
    document.addEventListener("click",e=>{
      const line=e.target.closest(".line"); if(!line) return;
      const key=line.dataset.key; const l=cart.find(x=>x.key===key); if(!l) return;
      if(e.target.closest("[data-inc]")) Cart.setQty(key,l.qty+1);
      else if(e.target.closest("[data-dec]")) Cart.setQty(key,l.qty-1);
      else if(e.target.closest("[data-remove]")) Cart.remove(key);
    });
    document.addEventListener("change",e=>{
      const line=e.target.closest(".line"); if(!line||e.target.type!=="number") return;
      Cart.setQty(line.dataset.key,+e.target.value);
    });
    /* Any element with data-add="productId" data-plan="subscribe|once" */
    document.addEventListener("click",e=>{
      const b=e.target.closest("[data-add]"); if(!b) return; e.preventDefault();
      const qty=+(b.dataset.qty||($("#qtyInput")?.value)||1);
      Cart.add(b.dataset.add,b.dataset.plan||"once",qty,b.dataset.variant||null);
    });

    /* Entry disclaimer / age gate — remembered for 30 days */
    (function(){
      const KEY="luma_gate_ok", DAYS=30, gate=$("#gate");
      let ok=false; try{ ok = (+localStorage.getItem(KEY)||0) > Date.now(); }catch(e){}
      if(ok) return;
      gate.hidden=false; document.body.classList.add("gate-open");
      const chk=$("#gateAgree"), btn=$("#gateEnter"), ret=document.activeElement;
      const box=chk.closest(".gate-check");
      chk.addEventListener("change",()=>{ btn.classList.toggle("is-ready",chk.checked); box.classList.remove("nudge"); });
      btn.addEventListener("click",()=>{ if(!chk.checked){ box.classList.remove("nudge"); void box.offsetWidth; box.classList.add("nudge"); chk.focus(); return; } try{ localStorage.setItem(KEY,String(Date.now()+DAYS*864e5)); }catch(e){} gate.classList.add("closing"); setTimeout(()=>{ gate.hidden=true; document.body.classList.remove("gate-open"); ret?.focus?.(); },300); });
      chk.focus();
      gate.addEventListener("keydown",e=>{ if(e.key!=="Tab") return; const f=[...gate.querySelectorAll("input,button:not([disabled]),a[href]")]; const a=f[0], z=f[f.length-1]; if(e.shiftKey&&document.activeElement===a){e.preventDefault();z.focus();} else if(!e.shiftKey&&document.activeElement===z){e.preventDefault();a.focus();} });
    })();

    /* Reveal on scroll */
    const io=new IntersectionObserver(es=>es.forEach(x=>{ if(x.isIntersecting){x.target.classList.add("in"); io.unobserve(x.target);} }),{threshold:.12});
    $$(".reveal").forEach(el=>io.observe(el));

    /* Generic newsletter forms */
    $$("form[data-newsletter]").forEach(f=>f.addEventListener("submit",e=>{e.preventDefault(); toast("Email signup is not connected in this preview.");}));
  });

  /* ---------- Product card helper (used by home + shop) ---------- */
  window.productCard = function(p){
    const oos = p.stock==="out";
    return `<article class="card reveal${oos?" oos":""}">
 ${oos?`<span class="badge soft">Waitlist</span>`:(p.badge?`<span class="badge">${p.badge}</span>`:"")}
 <a class="stretch" href="product.html?id=${p.id}" aria-label="${p.name}"></a>
 ${vialSVG(p)}
 <h3>${p.name}</h3>
 <div class="strength">${p.strength}${oos?' · <span class="oos-text">Out of stock</span>':''}</div>
 <div class="price">${p.subscribe?`${money(p.subscribe)} <small>/ month</small>`:`${money(p.once)}`}</div>
 <div class="card-actions">${oos?`<button class="btn btn-outline btn-sm" data-waitlist="${p.id}">Join the waitlist</button>`:`<button class="btn btn-outline btn-sm" data-add="${p.id}" data-plan="${p.subscribe?"subscribe":"once"}">Add to cart</button>`}</div>
</article>`;
  };

  /* ---------- Waitlist (out-of-stock) ---------- */
  window.joinWaitlist = function(id){
    const p=byId(id); if(!p) return;
    const email=prompt(`${p.name} is out of stock. Enter your email and we'll let you know the moment it's back:`);
    if(email===null) return;
    if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())){ toast("Please enter a valid email address."); return; }
    try{ const w=JSON.parse(localStorage.getItem("luma_waitlist")||"[]"); w.push({id,email:email.trim(),at:new Date().toISOString()}); localStorage.setItem("luma_waitlist",JSON.stringify(w)); }catch(e){}
    toast(`You're on the list for <b>${p.name}</b>. We'll email you when it's back.`);
  };
  document.addEventListener("click",e=>{ const b=e.target.closest("[data-waitlist]"); if(!b) return; e.preventDefault(); joinWaitlist(b.dataset.waitlist); });
})();
