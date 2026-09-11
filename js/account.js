/* Account: passwordless sign-in, orders, certificates, details, waitlist, invoices */
(function(){
  const CFG=window.LUMA_CONFIG, P=window.LUMA_PRODUCTS, money=Cart.money, view=document.getElementById("acctView");
  const title=document.getElementById("acctTitle"), lede=document.getElementById("acctLede");
  const SKEY="luma_session";
  const get=k=>{ try{ return localStorage.getItem(k); }catch(e){ return null; } };
  const set=(k,v)=>{ try{ v==null?localStorage.removeItem(k):localStorage.setItem(k,v); }catch(e){} };
  const api=async q=>{ const r=await fetch(`${CFG.captureEndpoint}?key=${encodeURIComponent(CFG.captureKey)}&${q}`,{redirect:"follow"}); return r.json(); };
  const esc=s=>String(s??"").replace(/[&<>"]/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c]));
  const LABEL={awaiting_payment:"Awaiting payment",paid:"Paid · in lab release",released:"Released by lab",shipped:"Shipped",delivered:"Delivered",cancelled:"Cancelled"};

  function loginForm(msg){
    title.textContent="Your orders and documents"; lede.textContent="Sign in with your email. No password: a link is sent to you and works for 15 minutes.";
    view.innerHTML=`<div class="acct-card acct-login">${msg?`<p class="acct-msg">${msg}</p>`:""}
 <form id="loginForm"><label class="sr-only" for="loginEmail">Email</label><input id="loginEmail" type="email" required placeholder="Email used at checkout" autocomplete="email"><button class="btn btn-primary">Email me a sign-in link</button></form>
 <p class="acct-fine">Looking for a single order? <a href="status.html">Track it by order number</a> without signing in.</p></div>`;
    document.getElementById("loginForm").addEventListener("submit",async e=>{ e.preventDefault(); const em=document.getElementById("loginEmail").value.trim(); const btn=e.target.querySelector("button"); btn.disabled=true; btn.textContent="Sending…";
      try{ const j=await api(`action=login&email=${encodeURIComponent(em)}`); if(j.ok){ view.innerHTML=`<div class="acct-card acct-login"><div class="coa-badge">✓ &nbsp;Link sent</div><p style="margin:0;color:var(--ink-2)">Check <b>${esc(em)}</b> for an email from Luma Research Co and open the link on this device. It expires in 15 minutes. If it doesn't arrive in a couple of minutes, check spam.</p></div>`; LumaCapture.track("account_login_sent",{email:em}); }
        else loginForm(j.error==="rate_limit"?"Too many links requested. Wait an hour and try again.":"Enter a valid email address."); }
      catch(err){ loginForm("Couldn't reach the account system. Try again in a minute."); } });
  }

  async function exchange(token){
    view.innerHTML=`<p style="text-align:center;color:var(--muted)">Signing you in…</p>`;
    try{ const j=await api(`action=session&token=${encodeURIComponent(token)}`); if(j.ok){ set(SKEY,j.session); history.replaceState(null,"",location.pathname); LumaCapture.track("account_login",{}); load(); } else loginForm("That link has expired or was already used. Request a new one."); }
    catch(e){ loginForm("Couldn't reach the account system. Try again in a minute."); }
  }

  async function load(){
    const s=get(SKEY); if(!s) return loginForm();
    view.innerHTML=`<p style="text-align:center;color:var(--muted)">Loading your account…</p>`;
    let j; try{ j=await api(`action=account&session=${encodeURIComponent(s)}`); }catch(e){ view.innerHTML=`<div class="acct-card"><p style="margin:0;color:var(--ink-2)">Couldn't reach the account system. Try again in a minute.</p></div>`; return; }
    if(!j.ok){ set(SKEY,null); return loginForm("Your session ended. Sign in again."); }
    set("luma_account_details",JSON.stringify({email:j.email,...(j.details||{})}));
    render(j,s);
  }

  function render(a,s){
    title.textContent="Welcome back."; lede.innerHTML=`Signed in as <b>${esc(a.email)}</b> · <button class="linkbtn" id="signOut">Sign out</button>`;
    const orders=a.orders||[], lots=[...new Set(orders.flatMap(o=>o.lots||[]))];
    const d=a.details||{};
    view.innerHTML=`
<section class="acct-sec"><div class="acct-head"><h2>Orders</h2><a class="text-link" href="shop.html">Browse the catalog →</a></div>
${orders.length?orders.map(o=>`<div class="acct-order">
 <div class="acct-order-top"><div><b>Order ${esc(o.id)}</b><span class="acct-date">${new Date(o.ts).toLocaleDateString()}</span></div><span class="pill pill-${esc(o.status)}">${LABEL[o.status]||esc(o.status)}</span></div>
 <p class="acct-items">${esc(o.items_text).replace(/\n/g,"<br>")}</p>
 <div class="acct-order-meta"><span>${money(+o.total||0)}</span>${o.tracking?(t=>`<span>Tracking ${esc(o.tracking)}${t?` · <a href="${t.u}" target="_blank" rel="noopener">${t.c}</a>`:""}</span>`)(trackingLink(o.tracking)):""}${o.lots&&o.lots.length?`<span>Lots ${o.lots.map(l=>`<a href="verify.html?lot=${encodeURIComponent(l)}">${esc(l)}</a>`).join(", ")}</span>`:""}</div>
 <div class="acct-actions">
  ${o.status==="awaiting_payment"?`<a class="btn btn-primary btn-sm" href="https://venmo.com/${CFG.venmo.handle}?txn=pay&amount=${(+o.total||0).toFixed(2)}&note=${encodeURIComponent("Order "+o.id)}" target="_blank" rel="noopener">Pay ${money(+o.total||0)} on Venmo</a>`:""}
  <button class="btn btn-outline btn-sm" data-reorder="${esc(o.id)}">Reorder</button>
  <button class="btn btn-ghost btn-sm" data-invoice="${esc(o.id)}">Invoice</button>
 </div></div>`).join(""):`<div class="acct-card"><p style="margin:0;color:var(--ink-2)">No orders yet under this email.</p></div>`}
</section>
<section class="acct-sec"><div class="acct-head"><h2>Your certificates</h2></div>
${lots.length?`<div class="acct-card"><p style="margin:0 0 .6rem;color:var(--ink-2);font-size:.92rem">Independent laboratory certificates for every lot you have received.</p><div class="lot-list">${lots.map(l=>`<a class="chip" href="verify.html?lot=${encodeURIComponent(l)}">${esc(l)} ↗</a>`).join("")}</div></div>`:`<div class="acct-card"><p style="margin:0;color:var(--ink-2)">Lot numbers are added when an order ships. Certificates for your lots will appear here.</p></div>`}
</section>
<section class="acct-sec"><div class="acct-head"><h2>Details</h2></div>
<form class="acct-card form-grid" id="detailsForm">
 <div class="field"><label for="dName">Name</label><input id="dName" name="name" value="${esc(d.name)}"></div>
 <div class="field"><label for="dOrg">Laboratory / organization</label><input id="dOrg" name="org" value="${esc(d.org)}"></div>
 <div class="field full"><label for="dPhone">Phone</label><input id="dPhone" name="phone" value="${esc(d.phone)}"></div>
 <div class="field full"><label for="dA1">Street address</label><input id="dA1" name="address1" value="${esc(d.address1)}"></div>
 <div class="field full"><label for="dA2">Apt, suite (optional)</label><input id="dA2" name="address2" value="${esc(d.address2)}"></div>
 <div class="field"><label for="dCity">City</label><input id="dCity" name="city" value="${esc(d.city)}"></div>
 <div class="field"><label for="dState">State</label><input id="dState" name="state" maxlength="2" value="${esc(d.state)}"></div>
 <div class="field"><label for="dZip">ZIP</label><input id="dZip" name="zip" value="${esc(d.zip)}"></div>
 <div class="full"><button class="btn btn-dark btn-sm">Save details</button> <span class="acct-fine" style="margin-left:.6rem">Used to prefill checkout.</span></div>
</form></section>
<section class="acct-sec"><div class="acct-head"><h2>Waitlist</h2></div>
${a.waitlist&&a.waitlist.length?`<div class="acct-card">${a.waitlist.map(w=>{const p=P.find(x=>x.id===w.id);return `<div class="coa-row"><span>${esc(p?p.name:w.id)} <small style="color:var(--muted)">since ${new Date(w.ts).toLocaleDateString()}</small></span><button class="linkbtn" data-unwait="${esc(w.id)}">Remove</button></div>`;}).join("")}</div>`:`<div class="acct-card"><p style="margin:0;color:var(--ink-2)">You're not on any waitlists. Out-of-stock compounds offer a "Join the waitlist" button on their page.</p></div>`}
</section>
<p class="acct-fine" style="text-align:center;margin-top:2rem">Sessions last 30 days on this device. To delete your account data, email <a href="mailto:${CFG.orderEmail}">${CFG.orderEmail}</a>.</p>`;

    document.getElementById("signOut").addEventListener("click",async()=>{ try{ await api(`action=logout&session=${encodeURIComponent(s)}`); }catch(e){} set(SKEY,null); set("luma_account_details",null); loginForm("Signed out."); });
    document.getElementById("detailsForm").addEventListener("submit",e=>{ e.preventDefault(); const f=e.target; const det={}; ["name","org","phone","address1","address2","city","state","zip"].forEach(k=>det[k]=f[k].value.trim()); det.state=det.state.toUpperCase(); LumaCapture.track("account_update",{session:s,details:det}); set("luma_account_details",JSON.stringify({email:a.email,...det})); toast("Details saved."); });
    view.querySelectorAll("[data-reorder]").forEach(b=>b.addEventListener("click",()=>{ const o=orders.find(x=>x.id===b.dataset.reorder); let n=0; (o.items&&o.items.length?o.items:[]).forEach(i=>{ const p=P.find(x=>x.id===i.id)||P.find(x=>x.name===i.name); if(p&&p.stock!=="out"){ Cart.add(p.id,"once",i.qty,i.variant||null); n++; } }); if(!n) toast("Those items aren't available to reorder right now."); }));
    view.querySelectorAll("[data-unwait]").forEach(b=>b.addEventListener("click",()=>{ LumaCapture.track("waitlist_remove",{session:s,id:b.dataset.unwait}); b.closest(".coa-row").remove(); toast("Removed from the waitlist."); }));
    view.querySelectorAll("[data-invoice]").forEach(b=>b.addEventListener("click",()=>invoice(orders.find(x=>x.id===b.dataset.invoice),a)));
  }

  function invoice(o,a){
    const rows=(o.items&&o.items.length?o.items.map(i=>[`${i.qty} × ${i.name} ${i.strength}`,money(i.price*i.qty)]):[[o.items_text,""]]);
    const w=window.open("","_blank"); if(!w) return toast("Allow pop-ups to open the invoice.");
    w.document.write(`<!doctype html><title>Invoice ${esc(o.id)}</title><meta charset="utf-8"><style>body{font:14px/1.5 -apple-system,Inter,system-ui,sans-serif;color:#2A2523;margin:40px auto;max-width:720px;padding:0 24px}h1{font-family:Georgia,serif;font-weight:normal;font-size:30px;margin:0}table{width:100%;border-collapse:collapse;margin:18px 0}td{padding:8px 0;border-bottom:1px dashed #E6DCD2}td:last-child{text-align:right}.top{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #2A2523;padding-bottom:14px;margin-bottom:18px}.brand{font-family:Georgia,serif;color:#B4432C;font-size:22px}.muted{color:#7C736E;font-size:12px}.tot td{border:0;font-weight:bold;padding-top:12px}@media print{body{margin:0}}</style>
<div class="top"><div><div class="brand">luma peptides co.</div><div class="muted">${esc(CFG.legalName)} · 30 N Gould St, Sheridan, WY 82801<br>${esc(CFG.orderEmail)} · (385) 521-5259</div></div><div style="text-align:right"><h1>Invoice</h1><div class="muted">Order ${esc(o.id)}<br>${new Date(o.ts).toLocaleDateString()}<br>Status: ${LABEL[o.status]||esc(o.status)}</div></div></div>
<p><b>Bill / ship to</b><br>${esc(o.name)}${o.org?"<br>"+esc(o.org):""}<br>${esc(o.address.line1)}${o.address.line2?", "+esc(o.address.line2):""}<br>${esc(o.address.city)}, ${esc(o.address.state)} ${esc(o.address.zip)}<br>${esc(a.email)}</p>
<table>${rows.map(r=>`<tr><td>${esc(r[0])}</td><td>${r[1]}</td></tr>`).join("")}<tr><td>Subtotal</td><td>${money(+o.sub||0)}</td></tr>${+o.discount?`<tr><td>Discount${o.promo?" ("+esc(o.promo)+")":""}</td><td>−${money(+o.discount)}</td></tr>`:""}<tr><td>Sales tax</td><td>${money(+o.tax||0)}</td></tr><tr><td>Shipping · ${esc(o.shipping)}</td><td>${+o.ship_cost?money(+o.ship_cost):"Free"}</td></tr><tr class="tot"><td>Total</td><td>${money(+o.total||0)}</td></tr></table>
<p class="muted">Payment: Venmo @${esc(CFG.venmo.handle)}, note "Order ${esc(o.id)}". ${o.lots&&o.lots.length?"Lot numbers: "+o.lots.map(esc).join(", ")+".":""}</p>
<p class="muted">All products are supplied for laboratory research use only and are not for human or animal use. Not intended to diagnose, treat, cure, or prevent any disease. All sales final once shipped.</p>
<script>window.onload=()=>setTimeout(()=>window.print(),300)<\/script>`);
    w.document.close();
  }

  /* Tracking link helper (shared with status page) */
  window.trackingLink=window.trackingLink||function(t){ t=String(t||"").replace(/\s/g,""); if(!t) return null;
    if(/^1Z[0-9A-Z]{16}$/i.test(t)) return {c:"UPS",u:"https://www.ups.com/track?tracknum="+t};
    if(/^(\d{12}|\d{15}|\d{20,22})$/.test(t) && !/^9[1-5]/.test(t)) return {c:"FedEx",u:"https://www.fedex.com/fedextrack/?trknbr="+t};
    if(/^(9[1-5]\d{18,24}|[A-Z]{2}\d{9}US)$/i.test(t)) return {c:"USPS",u:"https://tools.usps.com/go/TrackConfirmAction?tLabels="+t};
    return {c:"Carrier",u:"https://www.google.com/search?q="+encodeURIComponent(t)}; };

  if(!CFG.captureEndpoint){ view.innerHTML=`<div class="acct-card"><p style="margin:0;color:var(--ink-2)">Accounts aren't connected yet.</p></div>`; return; }
  const tok=new URLSearchParams(location.search).get("token");
  if(tok) exchange(tok); else load();
})();
