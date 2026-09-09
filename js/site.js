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
    taxRate:0, /* set e.g. 0.07 for demo tax */
    venmo:{handle:"LumaResearchCo",profile:"https://www.venmo.com/u/LumaResearchCo"},
    orderEmail:"info@lumaresearchco.com",
    captureEndpoint:"", /* Google Apps Script web-app URL — see backend/README.md */
    captureKey:"luma-2026-9f3k",   /* shared secret; must match SECRET in backend/Code.gs */
    siteUrl:"https://lumaresearchco.com",
    legalName:"Luma Peptides Co.", marketingName:"Luma Research Co",
    /* Analytics & ad pixels — leave blank until you have the IDs */
    ga4Id:"",        /* e.g. G-XXXXXXXXXX */
    metaPixelId:"",  /* e.g. 123456789012345 */
    googleAdsId:"",  /* e.g. AW-XXXXXXXXX */
    /* Sales tax: combined state + average local rate, applied by shipping state. Approximate; replace with a tax service for exact rates. */
    taxRates:{AL:9.29,AK:1.82,AZ:8.38,AR:9.45,CA:8.85,CO:7.81,CT:6.35,DE:0,DC:6.0,FL:7.0,GA:7.38,HI:4.5,ID:6.03,IL:8.86,IN:7.0,IA:6.94,KS:8.65,KY:6.0,LA:9.56,ME:5.5,MD:6.0,MA:6.25,MI:6.0,MN:8.04,MS:7.06,MO:8.39,MT:0,NE:6.97,NV:8.24,NH:0,NJ:6.6,NM:7.62,NY:8.53,NC:7.0,ND:7.04,OH:7.24,OK:8.99,OR:0,PA:6.34,RI:7.0,SC:7.5,SD:6.11,TN:9.55,TX:8.2,UT:7.25,VT:6.36,VA:5.77,WA:9.38,WV:6.57,WI:5.7,WY:5.44}
  };
  window.LUMA_CONFIG = CONFIG;

  /* ============================================================
     Capture: records customer input and marketing attribution.
     Every event is POSTed as JSON to CONFIG.captureEndpoint (Google
     Apps Script web app, Formspree, or any URL that accepts POST).
     Until an endpoint is set, events queue in localStorage and flush
     later. See backend/README.md.
     ============================================================ */
  const CAP = (function(){
    const QKEY="luma_capture_queue", VKEY="luma_visitor", AKEY="luma_first_touch";
    const uid=()=>Math.random().toString(36).slice(2,10)+Date.now().toString(36);
    let visitor=null; try{ visitor=localStorage.getItem(VKEY); if(!visitor){visitor=uid(); localStorage.setItem(VKEY,visitor);} }catch(e){ visitor="anon"; }
    let session=null; try{ session=sessionStorage.getItem("luma_session"); if(!session){session=uid(); sessionStorage.setItem("luma_session",session);} }catch(e){ session="s"; }
    const params=new URLSearchParams(location.search);
    const utm={}; ["utm_source","utm_medium","utm_campaign","utm_term","utm_content","gclid","fbclid","ref"].forEach(k=>{ if(params.get(k)) utm[k]=params.get(k); });
    let first=null; try{ first=JSON.parse(localStorage.getItem(AKEY)||"null"); if(!first){ first={ts:new Date().toISOString(),landing:location.pathname+location.search,referrer:document.referrer||"",utm}; localStorage.setItem(AKEY,JSON.stringify(first)); } }catch(e){}
    const VARIANT = "compliant";
    function envelope(event,data){
      return { key:CONFIG.captureKey, event, ts:new Date().toISOString(), variant:VARIANT, visitor, session,
        page:location.pathname, url:location.href, referrer:document.referrer||"", utm, first_touch:first,
        device:{ua:navigator.userAgent, lang:navigator.language, screen:`${screen.width}x${screen.height}`, mobile:/Mobi|Android/i.test(navigator.userAgent)},
        cart:{count:Cart.count(), subtotal:Cart.subtotal(), items:Cart.items().map(l=>({id:l.id,plan:l.plan,qty:l.qty,variant:l.variant}))},
        data:data||{} };
    }
    function queue(){ try{ return JSON.parse(localStorage.getItem(QKEY)||"[]"); }catch(e){ return []; } }
    function setQueue(q){ try{ localStorage.setItem(QKEY,JSON.stringify(q.slice(-200))); }catch(e){} }
    async function send(payload){
      const url=CONFIG.captureEndpoint; if(!url) return false;
      try{ await fetch(url,{method:"POST",mode:"no-cors",keepalive:true,headers:{"Content-Type":"text/plain;charset=UTF-8"},body:JSON.stringify(payload)}); return true; }
      catch(e){ return false; }
    }
    async function flush(){ const q=queue(); if(!q.length||!CONFIG.captureEndpoint) return; setQueue([]); for(const p of q){ if(!(await send(p))){ setQueue(queue().concat([p])); } } }
    async function track(event,data){
      const p=envelope(event,data);
      if(!(await send(p))) setQueue(queue().concat([p]));
      return p;
    }
    /* Beacon variant for events fired right before navigation */
    function trackBeacon(event,data){
      const p=envelope(event,data); const url=CONFIG.captureEndpoint;
      if(url && navigator.sendBeacon){ try{ if(navigator.sendBeacon(url,new Blob([JSON.stringify(p)],{type:"text/plain"}))) return p; }catch(e){} }
      if(url){ send(p); } else setQueue(queue().concat([p]));
      return p;
    }
    return {track,trackBeacon,flush,visitor,session,variant:VARIANT,queue};
  })();
  window.LumaCapture=CAP;

  /* ---------- Analytics & pixels (loaded only when IDs are set) ---------- */
  (function(){
    const ids=[CONFIG.ga4Id,CONFIG.googleAdsId].filter(Boolean);
    if(ids.length){
      const sc=document.createElement("script"); sc.async=true; sc.src="https://www.googletagmanager.com/gtag/js?id="+ids[0]; document.head.appendChild(sc);
      window.dataLayer=window.dataLayer||[]; window.gtag=function(){dataLayer.push(arguments);}; gtag("js",new Date()); ids.forEach(id=>gtag("config",id,{anonymize_ip:true}));
    }
    if(CONFIG.metaPixelId){
      !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version="2.0";n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,"script","https://connect.facebook.net/en_US/fbevents.js");
      fbq("init",CONFIG.metaPixelId); fbq("track","PageView");
    }
  })();
  /* Forward key commerce events to the pixels when present */
  window.LumaPixels={
    addToCart(d){ try{ window.gtag&&gtag("event","add_to_cart",{currency:"USD",value:d.price*d.qty,items:[{item_id:d.id,item_name:d.name,quantity:d.qty,price:d.price}]}); window.fbq&&fbq("track","AddToCart",{currency:"USD",value:d.price*d.qty,content_ids:[d.id],content_type:"product"}); }catch(e){} },
    checkout(v){ try{ window.gtag&&gtag("event","begin_checkout",{currency:"USD",value:v}); window.fbq&&fbq("track","InitiateCheckout",{currency:"USD",value:v}); }catch(e){} },
    purchase(o){ try{ window.gtag&&gtag("event","purchase",{transaction_id:o.id,currency:"USD",value:o.totals.total,tax:o.totals.tax,shipping:o.totals.ship,items:o.items.map(i=>({item_name:i.name,quantity:i.qty,price:i.price}))}); window.fbq&&fbq("track","Purchase",{currency:"USD",value:o.totals.total}); }catch(e){} },
    lead(){ try{ window.gtag&&gtag("event","generate_lead"); window.fbq&&fbq("track","Lead"); }catch(e){} }
  };

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
  const KEY="lumaB_cart_v1";
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
      save(); CAP.track("add_to_cart",{id,name:p.name,variant,plan,qty,price:unitPrice(p,plan,variant)}); LumaPixels.addToCart({id,name:p.name,qty,price:unitPrice(p,plan,variant)}); toast(`<b>${p.name}${v.key?" "+v.label:""}</b> added to your cart. <a href="cart.html">View cart</a>`); openDrawer();
    },
    setQty(key,qty){ const l=cart.find(l=>l.key===key); if(!l) return; l.qty=Math.max(0,Math.min(10,qty|0)); if(!l.qty) cart=cart.filter(x=>x.key!==key); save(); },
    remove(key){ cart=cart.filter(l=>l.key!==key); save(); },
    clear(){ cart=[]; save(); },
    count:()=>cart.reduce((a,l)=>a+l.qty,0),
    subtotal:()=>cart.reduce((a,l)=>{const p=byId(l.id);return a+(p?unitPrice(p,l.plan,l.variant)*l.qty:0);},0),
    promo(){ try{return localStorage.getItem("lumaB_promo")||"";}catch(e){return "";} },
    setPromo(code){ code=(code||"").trim().toUpperCase(); if(code && !CONFIG.promos[code]) return false; try{localStorage.setItem("lumaB_promo",code);}catch(e){} render(); return true; },
    taxState:"",
    totals(shipMethod="standard"){
      const sub=Cart.subtotal(); const code=Cart.promo(); const promo=CONFIG.promos[code];
      let discount=0; if(promo&&promo.type==="pct") discount=sub*promo.value/100;
      /* Volume tiers per line (research-catalog convention: buy 3 save 5%, etc.) */
      const vol=cart.reduce((a,l)=>{const p=byId(l.id); const t=(window.LUMA_VOLUME_TIERS||[]).filter(t=>l.qty>=t.min).pop(); return a+(p&&t?unitPrice(p,l.plan,l.variant)*l.qty*t.pct/100:0);},0);
      discount+=vol;
      let ship = sub===0?0 : (sub-discount>=CONFIG.freeShipThreshold ? 0 : CONFIG.shipping[shipMethod].price);
      if(promo&&promo.type==="ship") ship=0;
      if(shipMethod==="express" && sub>0) ship=CONFIG.shipping.express.price; /* express always paid */
      const rate=(Cart.taxState&&CONFIG.taxRates[Cart.taxState]!=null)?CONFIG.taxRates[Cart.taxState]/100:CONFIG.taxRate;
      const tax=Math.round((sub-discount)*rate*100)/100;
      return {sub,discount,vol,ship,tax,taxRate:rate,total:sub-discount+ship+tax,code,promo,discountLabel:code?`Discount (${code})${vol?" + volume":""}`:"Volume discount"};
    },
    unitPrice, variantOf, byId, money
  };
  window.Cart=Cart;

  /* ---------- Header / footer ---------- */
  const page = location.pathname.split("/").pop() || "index.html";
  const NAV=[["index.html","Home"],["shop.html","Catalog"],["how-it-works.html","Ordering"],["verify.html","Verify a Lot"],["about.html","About"],["contact.html","Contact"]];
  function header(){
    const links=NAV.map(([h,t])=>`<li><a href="${h}" ${page===h?'aria-current="page"':''}>${t}</a></li>`).join("");
    return `<a class="skip" href="#main">Skip to content</a>
<div class="announce">For laboratory research use only &nbsp;·&nbsp; Not for human or animal use &nbsp;·&nbsp; Every lot independently tested</div>
<header class="header" id="header"><div class="wrap nav">
 <a class="logo" href="index.html" aria-label="Luma Peptides Co. home"><span>luma</span><span>peptides</span><span>co.</span></a>
 <ul class="nav-links">${links}</ul>
 <div class="nav-actions">
  <a class="btn btn-primary btn-sm" href="shop.html">Catalog</a>
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
  <div><a class="logo" href="index.html"><span>luma</span><span>peptides</span><span>co.</span></a><p class="tag">Research-grade peptides, independently tested by lot. For laboratory research use only.</p><p class="tag biz"><a href="mailto:info@lumaresearchco.com">info@lumaresearchco.com</a><br><a href="tel:+13855215259">(385) 521-5259</a><br>30 N Gould St<br>Sheridan, WY 82801</p></div>
  <div><h4>Catalog</h4><ul><li><a href="shop.html">All compounds</a></li><li><a href="shop.html?cat=metabolic">Metabolic Research</a></li><li><a href="shop.html?cat=tissue">Tissue Research</a></li><li><a href="shop.html?cat=dermal">Dermal Research</a></li><li><a href="shop.html?cat=longevity">Longevity Research</a></li><li><a href="shop.html?cat=supplies">Lab Supplies</a></li></ul></div>
  <div><h4>Company</h4><ul><li><a href="about.html">About</a></li><li><a href="how-it-works.html">Ordering</a></li><li><a href="verify.html">Verify a Lot</a></li><li><a href="contact.html">Contact</a></li></ul></div>
  <div><h4>Support</h4><ul><li><a href="faq.html">FAQ</a></li><li><a href="status.html">Order status</a></li><li><a href="shipping-returns.html">Shipping</a></li><li><a href="shipping-returns.html#returns">Returns</a></li><li><a href="privacy.html">Privacy Policy</a></li><li><a href="terms.html">Terms</a></li></ul></div>
  <div><h4>Follow Us</h4><div class="social">
   <a href="#" aria-label="Instagram"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".8" fill="currentColor"/></svg></a>
   <a href="#" aria-label="Facebook"><svg viewBox="0 0 24 24"><path d="M14 8h3V4h-3a4 4 0 0 0-4 4v3H7v4h3v6h4v-6h3l1-4h-4V8a1 1 0 0 1 1-1z"/></svg></a>
   <a href="mailto:info@lumaresearchco.com" aria-label="Email"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg></a>
  </div></div>
 </div>
 <p class="disclaimer"><b>All products on this site are sold for laboratory research and analytical purposes only. They are not for human or animal use, consumption, or administration of any kind.</b> Bodily introduction of any kind into humans or animals is strictly forbidden by law. Products are not drugs, foods, cosmetics, or medical devices and may not be represented as such. Luma Peptides Co. is a chemical supplier. It is not a pharmacy, a compounding pharmacy, or an outsourcing facility, and it does not provide medical, therapeutic, or usage guidance of any kind. Statements on this website have not been evaluated by the U.S. Food and Drug Administration. Products are not intended to diagnose, treat, cure, or prevent any disease. Purchasers must be 21 years of age or older and affiliated with a laboratory, institution, or research organization; see the Terms of Service. Student project storefront: no payments are processed.</p>
 <div class="footer-bottom"><span>© ${new Date().getFullYear()} Luma Peptides Co. All rights reserved. lumaresearchco.com is operated by Luma Peptides Co.</span><div class="payments"><span class="venmo-badge">VENMO</span></div></div>
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
  <h2 id="gateTitle">Research materials. Please confirm before entering.</h2>
  <div class="gate-body" id="gateBody">
   <p>Every product on this site is supplied <b>for laboratory research use only</b>. Products are not for human or animal use, consumption, or administration of any kind, and are not intended to diagnose, treat, cure, or prevent any disease. Statements have not been evaluated by the Food and Drug Administration.</p>
   <p>Luma Peptides Co. is a chemical supplier, not a pharmacy or clinic, and does not provide usage guidance.</p>
  </div>
  <label class="gate-check"><input type="checkbox" id="gateAgree"><span>I confirm that I am <b>21 years of age or older</b>, that I am purchasing on behalf of a laboratory, institution, or research organization, that products will be used <b>for in-vitro research only</b> and never administered to any human or animal, and that I agree to the <a href="terms.html" target="_blank" rel="noopener">Terms of Service</a> and <a href="privacy.html" target="_blank" rel="noopener">Privacy Policy</a>.</span></label>
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
 <div><h4><a href="product.html?id=${p.id}${v.key?"&dose="+v.key:""}">${p.name}</a></h4><div class="variant">${v.strength}</div>
  <div class="qty"><button data-dec aria-label="Decrease">−</button><input type="number" value="${l.qty}" min="1" max="10" aria-label="Quantity"><button data-inc aria-label="Increase">+</button></div></div>
 <div class="line-price">${money(price*l.qty)}<button class="remove" data-remove>Remove</button></div>
</div>`;
  }
  function render(){
    const c=$("#cartCount"); if(c){c.textContent=Cart.count(); c.classList.toggle("show",Cart.count()>0);}
    const body=$("#drawerBody"), foot=$("#drawerFoot");
    if(body){
      if(!cart.length){ body.innerHTML=`<div class="empty-cart"><svg viewBox="0 0 24 24"><path d="M6 7h12l1 14H5z"/><path d="M9 7V5a3 3 0 0 1 6 0v2"/></svg><p>Your cart is empty.</p><a class="btn btn-outline btn-sm" href="shop.html">Browse the catalog</a></div>`; foot.innerHTML=""; }
      else{
        const t=Cart.totals(); const left=Math.max(0,CONFIG.freeShipThreshold-(t.sub-t.discount));
        body.innerHTML=cart.map(lineHTML).join("");
        foot.innerHTML=`<div class="ship-bar">${left>0?`You're <b>${money(left)}</b> away from free shipping`:`🎉 You've unlocked <b>free shipping</b>`}<div class="track"><div class="fill" style="width:${Math.min(100,((t.sub-t.discount)/CONFIG.freeShipThreshold)*100)}%"></div></div></div>
<div class="totals"><div><span>Subtotal</span><b>${money(t.sub)}</b></div>${t.discount?`<div class="discount"><span>${t.discountLabel}</span><b>−${money(t.discount)}</b></div>`:""}<div><span>Sales tax</span><b>Calculated at checkout</b></div></div>
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
    CAP.flush(); CAP.track("page_view",{title:document.title});
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
      const KEY="lumaB_gate_ok", DAYS=30, gate=$("#gate");
      let ok=false; try{ ok = (+localStorage.getItem(KEY)||0) > Date.now(); }catch(e){}
      if(ok) return;
      gate.hidden=false; document.body.classList.add("gate-open");
      const chk=$("#gateAgree"), btn=$("#gateEnter"), ret=document.activeElement;
      const box=chk.closest(".gate-check");
      chk.addEventListener("change",()=>{ btn.classList.toggle("is-ready",chk.checked); box.classList.remove("nudge"); });
      btn.addEventListener("click",()=>{ if(!chk.checked){ box.classList.remove("nudge"); void box.offsetWidth; box.classList.add("nudge"); chk.focus(); return; } try{ localStorage.setItem(KEY,String(Date.now()+DAYS*864e5)); }catch(e){} CAP.track("gate_accept",{}); gate.classList.add("closing"); setTimeout(()=>{ gate.hidden=true; document.body.classList.remove("gate-open"); ret?.focus?.(); },300); });
      chk.focus();
      gate.addEventListener("keydown",e=>{ if(e.key!=="Tab") return; const f=[...gate.querySelectorAll("input,button:not([disabled]),a[href]")]; const a=f[0], z=f[f.length-1]; if(e.shiftKey&&document.activeElement===a){e.preventDefault();z.focus();} else if(!e.shiftKey&&document.activeElement===z){e.preventDefault();a.focus();} });
    })();

    /* Reveal on scroll */
    const io=new IntersectionObserver(es=>es.forEach(x=>{ if(x.isIntersecting){x.target.classList.add("in"); io.unobserve(x.target);} }),{threshold:.12});
    $$(".reveal").forEach(el=>io.observe(el));

    /* Generic newsletter forms */
    $$("form[data-newsletter]").forEach(f=>f.addEventListener("submit",e=>{e.preventDefault(); const em=f.querySelector("input[type=email]")?.value||""; CAP.track("newsletter_signup",{email:em}); LumaPixels.lead(); toast("Thanks — you're on the list."); f.reset();}));
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
 <div class="price">${money(p.once)} <small>/ vial</small></div>
 <div class="card-actions">${oos?`<button class="btn btn-outline btn-sm" data-waitlist="${p.id}">Join the waitlist</button>`:`<button class="btn btn-outline btn-sm" data-add="${p.id}" data-plan="once">Add to cart</button>`}</div>
</article>`;
  };

  /* ---------- Waitlist (out-of-stock) ---------- */
  window.joinWaitlist = function(id){
    const p=byId(id); if(!p) return;
    const email=prompt(`${p.name} is out of stock. Enter an email address to be notified when the next tested lot is released:`);
    if(email===null) return;
    if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())){ toast("Please enter a valid email address."); return; }
    try{ const w=JSON.parse(localStorage.getItem("lumaB_waitlist")||"[]"); w.push({id,email:email.trim(),at:new Date().toISOString()}); localStorage.setItem("lumaB_waitlist",JSON.stringify(w)); }catch(e){}
    CAP.track("waitlist_join",{id,name:p.name,email:email.trim()});
    toast(`Noted. An email will be sent when <b>${p.name}</b> is back in stock.`);
  };
  document.addEventListener("click",e=>{ const b=e.target.closest("[data-waitlist]"); if(!b) return; e.preventDefault(); joinWaitlist(b.dataset.waitlist); });
})();
