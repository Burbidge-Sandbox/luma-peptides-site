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

  /* ---------- Vial SVG ---------- */
  function vialSVG(p, opts={}){
    const [l1,l2] = p.label || [p.name.toUpperCase(),""];
    const id = "g"+Math.random().toString(36).slice(2,7);
    return `<svg class="vial" viewBox="0 0 200 330" role="img" aria-label="${p.name} ${p.strength}" xmlns="http://www.w3.org/2000/svg">
<defs>
 <linearGradient id="${id}c" x1="0" x2="1"><stop offset="0" stop-color="#B9B7B4"/><stop offset=".25" stop-color="#F4F3F1"/><stop offset=".55" stop-color="#D9D7D4"/><stop offset="1" stop-color="#A3A09C"/></linearGradient>
 <linearGradient id="${id}g" x1="0" x2="1"><stop offset="0" stop-color="#D9D6D2"/><stop offset=".18" stop-color="#FFFFFF"/><stop offset=".5" stop-color="#EDEBE8"/><stop offset=".85" stop-color="#F7F6F4"/><stop offset="1" stop-color="#C9C6C2"/></linearGradient>
 <linearGradient id="${id}s" x1="0" x2="1"><stop offset="0" stop-color="#3A3532"/><stop offset=".5" stop-color="#6E6863"/><stop offset="1" stop-color="#2E2A27"/></linearGradient>
</defs>
<rect x="58" y="6" width="84" height="34" rx="6" fill="url(#${id}c)"/>
<rect x="66" y="6" width="68" height="8" rx="3" fill="#EEEDEB"/>
<rect x="60" y="38" width="80" height="16" fill="url(#${id}s)"/>
<rect x="63" y="50" width="74" height="12" fill="url(#${id}c)"/>
<path d="M64 62 h72 v14 q0 8 6 8 h0 v208 q0 18 -18 18 h-48 q-18 0 -18 -18 v-208 q6 0 6 -8z" fill="url(#${id}g)" stroke="#CFCBC6" stroke-width="1"/>
<rect x="64" y="110" width="72" height="160" fill="#FCFAF7"/>
<text x="70" y="136" font-family="Cormorant Garamond,Georgia,serif" font-size="17" fill="#B4432C" font-weight="500">luma</text>
<text x="70" y="153" font-family="Cormorant Garamond,Georgia,serif" font-size="17" fill="#B4432C" font-weight="500">peptides</text>
<text x="70" y="170" font-family="Cormorant Garamond,Georgia,serif" font-size="17" fill="#B4432C" font-weight="500">co.</text>
<text x="70" y="205" font-family="Inter,system-ui,sans-serif" font-size="${l1.length>11?7.2:8.5}" font-weight="600" fill="#2A2523" letter-spacing=".3">${l1}</text>
<text x="70" y="218" font-family="Inter,system-ui,sans-serif" font-size="8.5" font-weight="600" fill="#2A2523" letter-spacing=".3">${l2}</text>
<text x="70" y="252" font-family="Inter,system-ui,sans-serif" font-size="4.6" font-weight="600" fill="#B4432C" letter-spacing=".4">FOR RESEARCH USE ONLY</text>
<rect x="64" y="270" width="72" height="18" fill="#B4432C"/>
<rect x="68" y="80" width="6" height="210" rx="3" fill="#fff" opacity=".55"/>
<rect x="126" y="80" width="3" height="210" rx="2" fill="#000" opacity=".06"/>
</svg>`;
  }
  window.vialSVG = vialSVG;

  /* ---------- Cart state ---------- */
  const KEY="luma_cart_v1";
  let cart = [];
  try{ cart = JSON.parse(localStorage.getItem(KEY)||"[]"); }catch(e){ cart=[]; }
  const save = ()=>{ try{localStorage.setItem(KEY,JSON.stringify(cart));}catch(e){} render(); };
  function lineKey(id,plan){return id+"|"+plan;}
  function unitPrice(p,plan){ return plan==="subscribe" && p.subscribe ? p.subscribe : p.once; }
  const Cart = {
    items:()=>cart,
    add(id,plan="once",qty=1){
      const p=byId(id); if(!p) return;
      if(plan==="subscribe" && !p.subscribe) plan="once";
      const k=lineKey(id,plan);
      const ex=cart.find(l=>l.key===k);
      if(ex) ex.qty=Math.min(10,ex.qty+qty); else cart.push({key:k,id,plan,qty});
      save(); toast(`<b>${p.name}</b> added to your cart. <a href="cart.html">View cart</a>`); openDrawer();
    },
    setQty(key,qty){ const l=cart.find(l=>l.key===key); if(!l) return; l.qty=Math.max(0,Math.min(10,qty|0)); if(!l.qty) cart=cart.filter(x=>x.key!==key); save(); },
    remove(key){ cart=cart.filter(l=>l.key!==key); save(); },
    clear(){ cart=[]; save(); },
    count:()=>cart.reduce((a,l)=>a+l.qty,0),
    subtotal:()=>cart.reduce((a,l)=>{const p=byId(l.id);return a+(p?unitPrice(p,l.plan)*l.qty:0);},0),
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
    unitPrice, byId, money
  };
  window.Cart=Cart;

  /* ---------- Header / footer ---------- */
  const page = location.pathname.split("/").pop() || "index.html";
  const NAV=[["index.html","Home"],["shop.html","Treatments"],["how-it-works.html","How It Works"],["verify.html","Verify a Lot"],["about.html","About Us"],["contact.html","Contact"]];
  function header(){
    const links=NAV.map(([h,t])=>`<li><a href="${h}" ${page===h?'aria-current="page"':''}>${t}</a></li>`).join("");
    return `<a class="skip" href="#main">Skip to content</a>
<div class="announce">Free shipping on orders over $${CONFIG.freeShipThreshold} &nbsp;·&nbsp; <b>Every lot third-party tested</b> &nbsp;·&nbsp; Use code <b>WELCOME10</b> for 10% off your first order</div>
<header class="header" id="header"><div class="wrap nav">
 <a class="logo" href="index.html" aria-label="Luma Peptides Co. home"><span>luma</span><span>peptides</span><span>co.</span></a>
 <ul class="nav-links">${links}</ul>
 <div class="nav-actions">
  <a class="btn btn-primary btn-sm" href="shop.html">Shop All</a>
  <button class="icon-btn" id="cartBtn" aria-label="Open cart"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M6 7h12l1 14H5z"/><path d="M9 7V5a3 3 0 0 1 6 0v2"/></svg><span class="cart-count" id="cartCount">0</span></button>
  <button class="icon-btn burger" id="burger" aria-label="Open menu"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
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
  <div><a class="logo" href="index.html"><span>luma</span><span>peptides</span><span>co.</span></a><p class="tag">Empowering your health through science. Every lot tested. Every result published.</p></div>
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
<aside class="drawer" id="drawer" aria-label="Shopping cart" aria-hidden="true">
 <div class="drawer-head"><h2>Your cart</h2><button class="icon-btn" id="closeDrawer" aria-label="Close cart"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>
 <div class="drawer-body" id="drawerBody"></div>
 <div class="drawer-foot" id="drawerFoot"></div>
</aside>
<div class="toast" id="toast" role="status" aria-live="polite"></div>`;
  }

  /* ---------- Rendering ---------- */
  function lineHTML(l){
    const p=byId(l.id); if(!p) return "";
    const price=unitPrice(p,l.plan);
    return `<div class="line" data-key="${l.key}">
 <div class="thumb">${vialSVG(p)}</div>
 <div><h4><a href="product.html?id=${p.id}">${p.name}</a></h4><div class="variant">${p.strength} · ${l.plan==="subscribe"?"Monthly subscription":"One-time"}</div>
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
  function openDrawer(){ $("#drawer").classList.add("open"); $("#overlay").classList.add("show"); $("#drawer").setAttribute("aria-hidden","false"); }
  function closeDrawer(){ $("#drawer").classList.remove("open"); $("#overlay").classList.remove("show"); $("#drawer").setAttribute("aria-hidden","true"); }
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
    $("#burger").addEventListener("click",()=>$("#mobileMenu").classList.add("open"));
    $("#closeMenu").addEventListener("click",()=>$("#mobileMenu").classList.remove("open"));
    document.addEventListener("keydown",e=>{ if(e.key==="Escape"){closeDrawer(); $("#mobileMenu").classList.remove("open");} });
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
      Cart.add(b.dataset.add,b.dataset.plan||"once",qty);
    });

    /* Reveal on scroll */
    const io=new IntersectionObserver(es=>es.forEach(x=>{ if(x.isIntersecting){x.target.classList.add("in"); io.unobserve(x.target);} }),{threshold:.12});
    $$(".reveal").forEach(el=>io.observe(el));

    /* Generic newsletter forms */
    $$("form[data-newsletter]").forEach(f=>f.addEventListener("submit",e=>{e.preventDefault(); toast("Welcome to the list. Check your inbox for 10% off."); f.reset();}));
  });

  /* ---------- Product card helper (used by home + shop) ---------- */
  window.productCard = function(p){
    const stars="★".repeat(Math.round(p.rating||5));
    return `<article class="card reveal">
 ${p.badge?`<span class="badge ${p.badge==="New"?"soft":""}">${p.badge}</span>`:""}
 <a class="stretch" href="product.html?id=${p.id}" aria-label="${p.name}"></a>
 ${vialSVG(p)}
 <div class="rating">${stars}<span>(${p.reviews})</span></div>
 <h3>${p.name}</h3>
 <div class="strength">${p.strength}</div>
 <div class="price">${p.subscribe?`${money(p.subscribe)} <small>/ month</small>`:`${money(p.once)}`}</div>
 <div class="card-actions"><button class="btn btn-outline btn-sm" data-add="${p.id}" data-plan="${p.subscribe?"subscribe":"once"}">Add to cart</button></div>
</article>`;
  };
})();
