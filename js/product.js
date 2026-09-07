/* Product detail page */
(function(){
  const id=new URLSearchParams(location.search).get("id");
  const P=window.LUMA_PRODUCTS; const p=P.find(x=>x.id===id)||P[0];
  const money=Cart.money;
  document.title=`${p.name} ${p.strength} — Luma Peptides Co.`;
  document.querySelector('meta[name="description"]')?.setAttribute("content",`${p.name}: ${p.tagline} ${p.description}`);
  document.getElementById("crumbName").textContent=p.name;
  document.getElementById("gallery").innerHTML=vialSVG(p,{eager:true});
  const stars="★".repeat(Math.round(p.rating));
  const hasSub=!!p.subscribe;
  document.getElementById("info").innerHTML=`
 <span class="eyebrow">${window.LUMA_CATEGORIES[p.category]}</span>
 <h1>${p.name}</h1>
 <div class="sub">${p.strength} · <span class="rating" style="display:inline">${stars}<span>${p.rating} (${p.reviews} reviews)</span></span></div>
 <p style="color:var(--ink-2);font-size:1.05rem">${p.tagline}</p>
 <div class="pdp-price" id="priceLine"></div>
 <form id="buyForm">
  <div class="purchase-options" role="radiogroup" aria-label="Purchase option">
   ${hasSub?`<label class="opt"><input type="radio" name="plan" value="subscribe" checked><div class="opt-body"><b>Subscribe & save <span class="save">Save ${Math.round((1-p.subscribe/p.once)*100)}%</span></b><span>Delivered monthly · pause or cancel anytime · reminder before every charge</span></div><div class="opt-price">${money(p.subscribe)}<small style="font-weight:400;color:var(--muted)">/mo</small></div></label>`:""}
   <label class="opt"><input type="radio" name="plan" value="once" ${hasSub?"":"checked"}><div class="opt-body"><b>One-time purchase</b><span>Single ${hasSub?"4-week protocol":"vial"}, no commitment</span></div><div class="opt-price">${money(p.once)}</div></label>
  </div>
  <div class="buy-row">
   <div class="qty"><button type="button" id="dec" aria-label="Decrease">−</button><input id="qtyInput" type="number" value="1" min="1" max="10" aria-label="Quantity"><button type="button" id="inc" aria-label="Increase">+</button></div>
   <button type="submit" class="btn btn-primary">Add to cart</button>
  </div>
 </form>
 <div class="pdp-meta">
  <span><svg viewBox="0 0 24 24"><path d="M12 3l7 3v6c0 4.5-3 8-7 9-4-1-7-4.5-7-9V6z"/><path d="M9 12l2 2 4-4"/></svg>Lot-tested & COA published</span>
  <span><svg viewBox="0 0 24 24"><path d="M3 7h13v10H3zM16 10h4l1 3v4h-5z"/><circle cx="7" cy="18" r="1.5"/><circle cx="18" cy="18" r="1.5"/></svg>Ships in 24h, discreet packaging</span>
  <span><svg viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/></svg>30-day satisfaction guarantee</span>
 </div>
 <div class="tabs">
  <div class="tab-list" role="tablist">
   <button role="tab" aria-selected="true" data-tab="t1">Overview</button>
   <button role="tab" aria-selected="false" data-tab="t2">What's inside</button>
   <button role="tab" aria-selected="false" data-tab="t3">Lab results</button>
   ${p.faqs.length?`<button role="tab" aria-selected="false" data-tab="t4">FAQ</button>`:""}
  </div>
  <div class="tab-panel" id="t1"><p>${p.description}</p><p>Every Luma protocol arrives in our signature magnetic-closure box with a reconstitution guide, a QR code linking to this lot's certificate of analysis, and everything you need for the month.</p></div>
  <div class="tab-panel" id="t2" hidden><p>${p.inside}</p><table class="spec">${Object.entries(p.specs).map(([k,v])=>`<tr><td>${k}</td><td>${v}</td></tr>`).join("")}</table></div>
  <div class="tab-panel" id="t3" hidden><p>Each lot is sent to an independent U.S. laboratory for purity, identity, and net-content testing before release. Scan the QR code on your box or <a href="verify.html" style="color:var(--terra);text-decoration:underline">look up your lot number</a> to see the full certificate.</p><p><b>Latest lot purity:</b> ${p.specs["Purity (last lot)"]||"—"}</p></div>
  ${p.faqs.length?`<div class="tab-panel" id="t4" hidden>${p.faqs.map(([q,a])=>`<p><b>${q}</b><br>${a}</p>`).join("")}</div>`:""}
 </div>`;
  const priceLine=document.getElementById("priceLine");
  const form=document.getElementById("buyForm"), qty=document.getElementById("qtyInput");
  function plan(){return form.plan.value;}
  function updPrice(){ const pl=plan(); priceLine.innerHTML = pl==="subscribe" ? `${money(p.subscribe)} <small>/ month</small> <s>${money(p.once)}</s>` : `${money(p.once)} <small>one-time</small>`; }
  form.addEventListener("change",updPrice); updPrice();
  document.getElementById("inc").onclick=()=>qty.value=Math.min(10,+qty.value+1);
  document.getElementById("dec").onclick=()=>qty.value=Math.max(1,+qty.value-1);
  form.addEventListener("submit",e=>{e.preventDefault(); Cart.add(p.id,plan(),+qty.value);});
  document.querySelector(".tab-list").addEventListener("click",e=>{const b=e.target.closest("[role=tab]"); if(!b) return;
    document.querySelectorAll("[role=tab]").forEach(t=>t.setAttribute("aria-selected",t===b));
    document.querySelectorAll(".tab-panel").forEach(pn=>pn.hidden=pn.id!==b.dataset.tab);});
  /* Related */
  const rel=P.filter(x=>x.id!==p.id&&x.category===p.category).concat(P.filter(x=>x.id!==p.id&&x.category!==p.category)).slice(0,4);
  document.getElementById("related").innerHTML=rel.map(productCard).join("");
  document.querySelectorAll("#related .reveal").forEach(el=>el.classList.add("in"));
})();
