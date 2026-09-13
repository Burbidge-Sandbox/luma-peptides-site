/* Product detail page — with dosage (variant) selector */
(function(){
  const params=new URLSearchParams(location.search);
  const id=params.get("id");
  const P=window.LUMA_PRODUCTS; const p=P.find(x=>x.id===id)||P[0];
  const money=Cart.money;
  const variants=p.variants||null;
  let dose=variants?(variants.find(v=>v.key===params.get("dose"))||variants[0]).key:null;
  document.querySelector('meta[name="description"]')?.setAttribute("content",`${p.name}: ${p.tagline} ${p.description}`);
  document.getElementById("crumbName").textContent=p.name;
  document.getElementById("gallery").innerHTML=vialSVG(p,{eager:true});

  function render(){
    const v=Cart.variantOf(p,dose);
    const hasSub=!!v.subscribe;
    const oos=p.stock==="out"||v.stock==="out";
    document.title=`${p.name} ${v.strength} — Luma Peptides Co.`;
    if(variants){ const u=new URL(location); u.searchParams.set("dose",v.key); history.replaceState(null,"",u); }
    document.getElementById("info").innerHTML=`
 <span class="eyebrow">${window.LUMA_CATEGORIES[p.category]}</span>
 <h1>${p.name}</h1>
 <div class="sub">${v.strength} · Lyophilized · For laboratory research use only</div>
 <p style="color:var(--ink-2);font-size:1.05rem">${p.tagline}</p>
 <div class="pdp-price" id="priceLine"></div>
 ${variants?`<div class="dose-picker"><div class="dose-label">Vial size</div><div class="dose-options" role="radiogroup" aria-label="Dosage">${variants.map(x=>`<button type="button" class="dose${x.key===v.key?" is-selected":""}${x.stock==="out"?" is-out":""}" data-dose="${x.key}" role="radio" aria-checked="${x.key===v.key}">${x.label}${x.stock==="out"?'<small>Waitlist</small>':''}</button>`).join("")}</div></div>`:""}
 ${oos?`<div class="oos-banner"><b>Currently out of stock.</b> Join the waitlist to be notified when the next tested lot is released. No payment is taken.</div>`:""}
 <form id="buyForm">
  <div class="buy-row">
   <div class="qty"><button type="button" id="dec" aria-label="Decrease">−</button><input id="qtyInput" type="number" value="1" min="1" max="10" aria-label="Quantity"><button type="button" id="inc" aria-label="Increase">+</button></div>
   ${oos?`<button type="button" class="btn btn-primary" data-waitlist="${p.id}">Join the waitlist</button>`:`<button type="submit" class="btn btn-primary">Add to cart</button>`}
  </div>
 </form>
 <div class="pdp-meta">
  <span><svg viewBox="0 0 24 24"><path d="M12 3l7 3v6c0 4.5-3 8-7 9-4-1-7-4.5-7-9V6z"/><path d="M9 12l2 2 4-4"/></svg>Lot-tested & COA published</span>
  <span><svg viewBox="0 0 24 24"><path d="M3 7h13v10H3zM16 10h4l1 3v4h-5z"/><circle cx="7" cy="18" r="1.5"/><circle cx="18" cy="18" r="1.5"/></svg>Ships within 24h, US only, plain packaging</span>
  <span><svg viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/></svg>All sales final · replacement for damaged goods</span>
 </div>
 <div class="tabs">
  <div class="tab-list" role="tablist">
   <button role="tab" aria-selected="true" data-tab="t1">Overview</button>
   <button role="tab" aria-selected="false" data-tab="t2">Specifications</button>
   <button role="tab" aria-selected="false" data-tab="t3">Lab results</button>
   ${p.faqs.length?`<button role="tab" aria-selected="false" data-tab="t4">FAQ</button>`:""}
  </div>
  <div class="tab-panel" id="t1"><p>${p.description}</p><p style="font-size:.85rem;color:var(--muted)">For laboratory research use only. Not for human or animal use. Bodily introduction of any kind into humans or animals is strictly forbidden by law. This product is not a drug, food, cosmetic, or medical device. Luma Peptides Co. does not provide reconstitution, handling, or usage guidance.</p></div>
  <div class="tab-panel" id="t2" hidden><p>${p.inside}</p><table class="spec">${Object.entries({"Net content":v.strength.replace(" vial",""),...p.specs}).map(([k,val])=>`<tr><td>${k}</td><td>${val}</td></tr>`).join("")}</table></div>
  <div class="tab-panel" id="t3" hidden><p>Each lot is sent to an independent, accredited U.S. laboratory for purity (HPLC), identity (LC-MS), net content, and endotoxin testing before release. Scan the QR code on the vial carton or <a href="verify.html" style="color:var(--terra);text-decoration:underline">look up the lot number</a> to view the certificate of analysis.</p><p><b>Latest lot purity:</b> ${p.specs["Purity (last lot)"]||"—"}</p></div>
  ${p.faqs.length?`<div class="tab-panel" id="t4" hidden>${p.faqs.map(([q,a])=>`<p><b>${q}</b><br>${a}</p>`).join("")}</div>`:""}
 </div>`;
    const priceLine=document.getElementById("priceLine");
    const form=document.getElementById("buyForm"), qty=document.getElementById("qtyInput");
    const plan=()=>"once";
    const updPrice=()=>{ priceLine.innerHTML = `${money(v.once)} <small>per vial</small>`; };
    form.addEventListener("change",updPrice); updPrice();
    document.getElementById("inc").onclick=()=>qty.value=Math.min(10,+qty.value+1);
    document.getElementById("dec").onclick=()=>qty.value=Math.max(1,+qty.value-1);
    form.addEventListener("submit",e=>{e.preventDefault(); if(oos){joinWaitlist(p.id);return;} Cart.add(p.id,plan(),+qty.value,dose);});
    if(oos){ form.querySelectorAll("input,#inc,#dec").forEach(el=>el.disabled=true); }
    document.querySelector(".tab-list").addEventListener("click",e=>{const b=e.target.closest("[role=tab]"); if(!b) return;
      document.querySelectorAll("[role=tab]").forEach(t=>t.setAttribute("aria-selected",t===b));
      document.querySelectorAll(".tab-panel").forEach(pn=>pn.hidden=pn.id!==b.dataset.tab);});
    document.querySelectorAll(".dose").forEach(b=>b.addEventListener("click",()=>{ if(b.dataset.dose===dose) return; dose=b.dataset.dose; render(); document.getElementById("priceLine").scrollIntoView({block:"nearest"}); }));
  }
  render();

  /* Structured data for search engines */
  try{
    const v0=Cart.variantOf(p,dose); const ld={"@context":"https://schema.org","@type":"Product","name":`${p.name} ${v0.strength}`,"sku":p.id,"brand":{"@type":"Brand","name":"Luma Research Co"},"description":p.tagline+" For laboratory research use only.","url":window.LUMA_CONFIG.siteUrl+"/product?id="+p.id,"offers":{"@type":"Offer","priceCurrency":"USD","price":v0.once,"availability":(p.stock==="out"||v0.stock==="out")?"https://schema.org/OutOfStock":"https://schema.org/InStock","url":window.LUMA_CONFIG.siteUrl+"/product?id="+p.id}};
    if(p.image) ld.image=window.LUMA_CONFIG.siteUrl+"/"+p.image+".jpg";
    const sc=document.createElement("script"); sc.type="application/ld+json"; sc.textContent=JSON.stringify(ld); document.head.appendChild(sc);
    let can=document.querySelector('link[rel=canonical]'); if(!can){can=document.createElement("link"); can.rel="canonical"; document.head.appendChild(can);} can.href=ld.url;
  }catch(e){}

  /* Related */
  const rel=P.filter(x=>x.id!==p.id&&x.category===p.category).concat(P.filter(x=>x.id!==p.id&&x.category!==p.category)).slice(0,4);
  document.getElementById("related").innerHTML=rel.map(productCard).join("");
  document.querySelectorAll("#related .reveal").forEach(el=>el.classList.add("in"));
})();
