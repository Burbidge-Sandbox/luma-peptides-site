/* Order status lookup — asks the capture endpoint (Apps Script doGet) for an order by id */
(function(){
  const form=document.getElementById("statusForm"), input=document.getElementById("orderInput"), out=document.getElementById("statusResult");
  const money=Cart.money, CFG=window.LUMA_CONFIG;
  const STEPS=["Order placed","Payment received","Lab release","Shipped"];
  const stepIndex={awaiting_payment:0,paid:1,released:2,shipped:3,delivered:3,cancelled:-1};
  async function lookup(id){
    id=id.trim().toUpperCase(); if(!id) return;
    out.innerHTML=`<p style="text-align:center;color:var(--muted)">Checking…</p>`;
    if(!CFG.captureEndpoint){ out.innerHTML=`<div class="coa-card"><p style="margin:0;color:var(--ink-2)">Order lookup isn't connected yet. Email <a href="mailto:${CFG.orderEmail}" style="color:var(--terra)">${CFG.orderEmail}</a> with order <b>${id}</b>.</p></div>`; return; }
    try{
      const r=await fetch(`${CFG.captureEndpoint}?order=${encodeURIComponent(id)}&key=${encodeURIComponent(CFG.captureKey)}`,{redirect:"follow"});
      const j=await r.json();
      if(!j||!j.found){ out.innerHTML=`<div class="coa-card"><div class="coa-badge fail">✕ &nbsp;Not found</div><p style="margin:0;color:var(--ink-2)">No order <b>${id}</b> on record. Check the number, or <a href="contact.html" style="color:var(--terra)">contact support</a>.</p></div>`; return; }
      const i=stepIndex[j.status]??0;
      const label={awaiting_payment:"Awaiting Venmo payment",paid:"Payment received",released:"Released by lab",shipped:"Shipped",delivered:"Delivered",cancelled:"Cancelled"}[j.status]||j.status;
      out.innerHTML=`<div class="coa-card">
 <div class="coa-badge${j.status==="cancelled"?" fail":""}">${j.status==="cancelled"?"✕":"✓"} &nbsp;${label}</div>
 <div class="coa-head"><div><b style="font-size:1.15rem">Order ${j.id}</b><br><span style="font-size:.85rem;color:var(--muted)">Placed ${new Date(j.ts).toLocaleDateString()}</span></div><b>${money(+j.total||0)}</b></div>
 ${j.items?`<p style="font-size:.9rem;color:var(--ink-2);white-space:pre-line;margin:0">${j.items}</p>`:""}
 ${j.status==="awaiting_payment"?`<p style="font-size:.9rem;margin:1rem 0 0">Pay <b>${money(+j.total||0)}</b> to <b>@${CFG.venmo.handle}</b> with note <b>Order ${j.id}</b>. <a class="btn btn-primary btn-sm" style="margin-left:.5rem" href="https://venmo.com/${CFG.venmo.handle}?txn=pay&amount=${(+j.total||0).toFixed(2)}&note=${encodeURIComponent("Order "+j.id)}" target="_blank" rel="noopener">Open Venmo</a></p>`:""}
 ${j.status==="paid"?`<p style="font-size:.9rem;margin:1rem 0 0;color:var(--sage)"><b>Payment received.</b> This order is in the lab-release queue and will ship within one business day of release.</p>`:""}
 ${j.tracking?(t=>`<p style="font-size:.9rem;margin:1rem 0 0"><b>Tracking:</b> ${j.tracking} ${t?`&nbsp;<a class="btn btn-outline btn-sm" href="${t.u}" target="_blank" rel="noopener">Track with ${t.c}</a>`:""}</p>`)(trackingLink(j.tracking)):""}
 <div class="status-steps">${STEPS.map((s,k)=>`<div class="${k<=i?"done":""}">${s}</div>`).join("")}</div>
</div>`;
    }catch(e){ out.innerHTML=`<div class="coa-card"><p style="margin:0;color:var(--ink-2)">Couldn't reach the order system. Try again in a minute, or email <a href="mailto:${CFG.orderEmail}" style="color:var(--terra)">${CFG.orderEmail}</a>.</p></div>`; }
  }
  /* Carrier tracking link from the number format */
  window.trackingLink=function(t){ t=String(t||"").replace(/\s/g,""); if(!t) return null;
    if(/^1Z[0-9A-Z]{16}$/i.test(t)) return {c:"UPS",u:"https://www.ups.com/track?tracknum="+t};
    if(/^(\d{12}|\d{15}|\d{20,22})$/.test(t) && !/^9[1-5]/.test(t)) return {c:"FedEx",u:"https://www.fedex.com/fedextrack/?trknbr="+t};
    if(/^(9[1-5]\d{18,24}|[A-Z]{2}\d{9}US)$/i.test(t)) return {c:"USPS",u:"https://tools.usps.com/go/TrackConfirmAction?tLabels="+t};
    return {c:"Carrier",u:"https://www.google.com/search?q="+encodeURIComponent(t)};
  };
  form.addEventListener("submit",e=>{e.preventDefault(); lookup(input.value);});
  /* Email + ZIP lookup (needs the matching backend version) */
  const eform=document.getElementById("emailForm"), tabO=document.getElementById("tabOrder"), tabE=document.getElementById("tabEmail");
  function tab(which){ const em=which==="email"; eform.hidden=!em; form.hidden=em; tabO.setAttribute("aria-pressed",!em); tabE.setAttribute("aria-pressed",em); out.innerHTML=""; }
  tabO.addEventListener("click",()=>tab("order")); tabE.addEventListener("click",()=>tab("email"));
  eform.addEventListener("submit",async e=>{ e.preventDefault(); const em=document.getElementById("emailInput").value.trim(), zip=document.getElementById("zipInput").value.trim(); if(!em||!zip) return;
    out.innerHTML=`<p style="text-align:center;color:var(--muted)">Checking…</p>`;
    try{ const r=await fetch(`${CFG.captureEndpoint}?email=${encodeURIComponent(em)}&zip=${encodeURIComponent(zip)}&key=${encodeURIComponent(CFG.captureKey)}`,{redirect:"follow"}); const j=await r.json();
      if(!j||!j.orders||!j.orders.length){ out.innerHTML=`<div class="coa-card"><div class="coa-badge fail">✕ &nbsp;No orders found</div><p style="margin:0;color:var(--ink-2)">Nothing matches that email and ZIP. Try the order number from the confirmation email, or <a href="contact.html" style="color:var(--terra)">contact support</a>.</p></div>`; return; }
      out.innerHTML=`<div class="coa-card"><b style="font-size:1.05rem">Orders for ${em}</b>${j.orders.map(o=>`<div class="coa-row"><span><a href="status.html?order=${o.id}" style="color:var(--terra);text-decoration:underline">${o.id}</a> · ${new Date(o.ts).toLocaleDateString()}</span><b>${money(+o.total||0)} · ${o.status.replace(/_/g," ")}</b></div>`).join("")}</div>`;
    }catch(err){ out.innerHTML=`<div class="coa-card"><p style="margin:0;color:var(--ink-2)">Couldn't reach the order system. Try the order number instead.</p></div>`; }
  });
  const q=new URLSearchParams(location.search).get("order"); if(q){input.value=q; lookup(q);}
})();
