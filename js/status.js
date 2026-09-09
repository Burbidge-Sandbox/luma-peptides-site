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
 ${j.tracking?`<p style="font-size:.9rem;margin:1rem 0 0"><b>Tracking:</b> ${j.tracking}</p>`:""}
 <div class="status-steps">${STEPS.map((s,k)=>`<div class="${k<=i?"done":""}">${s}</div>`).join("")}</div>
</div>`;
    }catch(e){ out.innerHTML=`<div class="coa-card"><p style="margin:0;color:var(--ink-2)">Couldn't reach the order system. Try again in a minute, or email <a href="mailto:${CFG.orderEmail}" style="color:var(--terra)">${CFG.orderEmail}</a>.</p></div>`; }
  }
  form.addEventListener("submit",e=>{e.preventDefault(); lookup(input.value);});
  const q=new URLSearchParams(location.search).get("order"); if(q){input.value=q; lookup(q);}
})();
