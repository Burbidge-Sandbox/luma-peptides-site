(function(){
  let o=null; try{ o=JSON.parse(sessionStorage.getItem("lumaB_order")||"null"); }catch(e){}
  const el=document.getElementById("orderBox"); const money=Cart.money;
  if(!o){ el.innerHTML=`<p style="color:var(--muted);text-align:center">We couldn't find that order in this browser. <a href="shop.html" style="color:var(--terra)">Continue shopping →</a></p>`; return; }
  document.getElementById("orderId").textContent=o.id; const oe=document.getElementById("orderEmail"); if(oe) oe.textContent=o.email;
  const CFG=window.LUMA_CONFIG, amt=o.totals.total.toFixed(2);
  const note=`Order ${o.id}`;
  const payUrl=`https://venmo.com/${CFG.venmo.handle}?txn=pay&amount=${amt}&note=${encodeURIComponent(note)}`;
  const lines=o.items.map(i=>`${i.qty} x ${i.name} ${i.strength} @ ${money(i.price)}`).join("\n");
  const body=`Order ${o.id}\nDate: ${new Date(o.date).toLocaleString()}\n\nItems:\n${lines}\n\nSubtotal: ${money(o.totals.sub)}${o.totals.discount?"\nDiscount: -"+money(o.totals.discount):""}\nShipping: ${o.totals.ship?money(o.totals.ship):"Free"} (${o.ship})\nTotal: ${money(o.totals.total)}\nPayment: Venmo @${CFG.venmo.handle} — note "${note}"\n\nShip to:\n${o.name}${o.org?"\n"+o.org:""}\n${o.address}\nEmail: ${o.email}`;
  const mailUrl=`mailto:${CFG.orderEmail}?subject=${encodeURIComponent("Order "+o.id+" — "+o.name)}&body=${encodeURIComponent(body)}`;
  document.getElementById("payBox").innerHTML=`
 <div class="pay-step"><span class="step-n">1</span><div><b>Pay ${money(o.totals.total)} on Venmo</b><p>Send to <b>@${CFG.venmo.handle}</b> and put <b>${note}</b> in the note so the payment can be matched to this order.</p><div class="pay-row"><div><a class="btn btn-primary" href="${payUrl}" target="_blank" rel="noopener">Open Venmo · ${money(o.totals.total)}</a> <a class="btn btn-ghost" href="${CFG.venmo.profile}" target="_blank" rel="noopener">View profile</a></div><div class="pay-qr" id="payQr" aria-label="QR code for Venmo payment"></div></div><p class="pay-qr-hint">On a computer? Scan the code with your phone to open Venmo with the amount and order number filled in.</p></div></div>
 <div class="pay-step"><span class="step-n">2</span><div><b>Send the order details</b><p>This opens an email to Luma with the items and shipping address already filled in. Just press send.</p><a class="btn btn-outline" href="${mailUrl}">Send order details</a></div></div>
 <p class="pay-fine">Orders are released for shipping once the Venmo payment is received, usually within a few hours during business hours. Unpaid orders are cancelled after 48 hours. Screenshot or save this page: order <b>${o.id}</b>.</p>`;
  try{ const qr=qrcode(0,"M"); qr.addData(payUrl); qr.make(); document.getElementById("payQr").innerHTML=qr.createSvgTag({cellSize:3,margin:2,scalable:true}); }catch(e){ document.getElementById("payQr").remove(); }
  el.innerHTML=`${o.items.map(i=>`<div class="coa-row"><span>${i.qty}× ${i.name} <small style="color:var(--muted)">${i.strength}</small></span><b>${money(i.price*i.qty)}</b></div>`).join("")}
<div class="coa-row"><span>Subtotal</span><b>${money(o.totals.sub)}</b></div>${o.totals.discount?`<div class="coa-row"><span>${o.totals.code?"Discount ("+o.totals.code+")":"Volume discount"}</span><b class="pass">−${money(o.totals.discount)}</b></div>`:""}${o.totals.tax?`<div class="coa-row"><span>Sales tax</span><b>${money(o.totals.tax)}</b></div>`:""}<div class="coa-row"><span>Shipping · ${o.ship}</span><b>${o.totals.ship?money(o.totals.ship):"Free"}</b></div><div class="coa-row"><span><b>Total</b> · Venmo, payment pending</span><b>${money(o.totals.total)}</b></div>
<p style="font-size:.85rem;color:var(--muted);margin:1rem 0 0"><b>Ships to:</b> ${o.name}${o.org?", "+o.org:""}, ${o.address}</p>
<div class="timeline"><div class="done">Order placed</div><div>Payment received</div><div>Lab release</div><div>Shipped</div></div>`;
})();
