(function(){
  let o=null; try{ o=JSON.parse(sessionStorage.getItem("lumaB_order")||"null"); }catch(e){}
  const el=document.getElementById("orderBox"); const money=Cart.money;
  if(!o){ el.innerHTML=`<p style="color:var(--muted);text-align:center">We couldn't find that order in this browser. <a href="shop.html" style="color:var(--terra)">Continue shopping →</a></p>`; return; }
  document.getElementById("orderId").textContent=o.id; document.getElementById("orderEmail").textContent=o.email;
  el.innerHTML=`${o.items.map(i=>`<div class="coa-row"><span>${i.qty}× ${i.name} <small style="color:var(--muted)">${i.strength}</small></span><b>${money(i.price*i.qty)}</b></div>`).join("")}
<div class="coa-row"><span>Subtotal</span><b>${money(o.totals.sub)}</b></div>${o.totals.discount?`<div class="coa-row"><span>${o.totals.code?"Discount ("+o.totals.code+")":"Volume discount"}</span><b class="pass">−${money(o.totals.discount)}</b></div>`:""}<div class="coa-row"><span>Shipping · ${o.ship}</span><b>${o.totals.ship?money(o.totals.ship):"Free"}</b></div><div class="coa-row"><span><b>Total</b> · card ending ${o.last4}</span><b>${money(o.totals.total)}</b></div>
<p style="font-size:.85rem;color:var(--muted);margin:1rem 0 0"><b>Ships to:</b> ${o.name}${o.org?", "+o.org:""}, ${o.address}</p>
<div class="timeline"><div class="done">Order placed</div><div>Lab release</div><div>Shipped</div><div>Delivered</div></div>`;
})();
