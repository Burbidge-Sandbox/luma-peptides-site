/* Lot verification (COA lookup). Demo data lives in products.js → LUMA_LOTS.
   PRODUCTION: fetch(`/api/lots/${code}`) from your backend. */
(function(){
  const form=document.getElementById("verifyForm"), input=document.getElementById("lotInput"), out=document.getElementById("coaResult");
  const LOTS=window.LUMA_LOTS;
  function show(code){
    code=code.trim().toUpperCase(); if(!code) return;
    if(!/^[A-Z0-9-]{1,60}$/.test(code)){out.textContent="Enter a lot code using letters, numbers, and hyphens.";return;}
    const lot=LOTS[code]; window.LumaCapture&&LumaCapture.track("lot_lookup",{code,found:!!lot});
    if(!lot){ out.innerHTML=`<div class="coa-card"><div class="coa-badge fail">✕ &nbsp;Lot not found</div><p style="margin:0;color:var(--ink-2)">We don't have a record of <b>${code}</b>. Double-check the code printed under the QR on the carton, or <a href="contact.html" style="color:var(--terra);text-decoration:underline">contact support</a> — a lot that is not listed here should be reported.</p></div>`; return; }
    out.innerHTML=`<div class="coa-card">
 <div class="coa-badge">Sample record · ${lot.status}</div>
 <div class="coa-head"><div><b style="font-size:1.15rem">${lot.product}</b><br><span style="font-size:.85rem;color:var(--muted)">Lot ${code}</span></div><span class="coa-stamp">TESTED</span></div>
 <div class="coa-grid">
  <div class="coa-row"><span>Laboratory</span><b>${lot.lab}</b></div><div class="coa-row"><span>Test date</span><b>${lot.tested}</b></div>
  <div class="coa-row"><span>Purity (HPLC)</span><b class="pass">${lot.purity}</b></div><div class="coa-row"><span>Identity</span><b class="pass">${lot.identity}</b></div>
  <div class="coa-row"><span>Net content</span><b>${lot.netContent}</b></div><div class="coa-row"><span>Endotoxin</span><b>${lot.endotoxin}</b></div>
  <div class="coa-row"><span>Best before</span><b>${lot.expires}</b></div><div class="coa-row"><span>Overall</span><b class="pass">${lot.status}</b></div>
 </div>
 <p style="font-size:.78rem;color:var(--muted);margin:1.2rem 0 0">Demonstration data only. This is not a laboratory certificate or evidence of completed testing.</p>
</div>`;
    out.scrollIntoView({behavior:"smooth",block:"center"});
  }
  form.addEventListener("submit",e=>{e.preventDefault(); show(input.value);});
  document.querySelectorAll("[data-sample]").forEach(b=>b.addEventListener("click",()=>{input.value=b.dataset.sample; show(b.dataset.sample);}));
  const q=new URLSearchParams(location.search).get("lot"); if(q){input.value=q; show(q);}
})();
