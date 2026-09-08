/* Full cart page */
(function(){
  const money=Cart.money;
  const list=document.getElementById("cartList"), sum=document.getElementById("summaryBody");
  function draw(){
    const items=Cart.items();
    if(!items.length){ list.innerHTML=`<div class="empty-cart"><p>Your cart is empty.</p><a class="btn btn-primary" href="shop.html">Browse the catalog</a></div>`; sum.innerHTML=""; return; }
    list.innerHTML=items.map(l=>{const p=Cart.byId(l.id); const price=Cart.unitPrice(p,l.plan,l.variant); const v=Cart.variantOf(p,l.variant); return `<div class="line" data-key="${l.key}">
 <div class="thumb">${vialSVG(p)}</div>
 <div><h4><a href="product.html?id=${p.id}${v.key?"&dose="+v.key:""}">${p.name}</a></h4><div class="variant">${v.strength} · ${money(price)} per vial</div>
  <div class="qty"><button data-dec aria-label="Decrease">−</button><input type="number" value="${l.qty}" min="1" max="10" aria-label="Quantity"><button data-inc aria-label="Increase">+</button></div></div>
 <div class="line-price">${money(price*l.qty)}<button class="remove" data-remove>Remove</button></div></div>`;}).join("");
    const t=Cart.totals();
    sum.innerHTML=`<div class="promo"><input id="promoIn" placeholder="Promo code" value="${t.code}" aria-label="Promo code"><button class="btn btn-dark btn-sm" id="promoBtn">Apply</button></div><div class="promo-msg" id="promoMsg"></div>
<div class="totals"><div><span>Subtotal</span><b>${money(t.sub)}</b></div>${t.discount?`<div class="discount"><span>${t.discountLabel}</span><b>−${money(t.discount)}</b></div>`:""}<div><span>Shipping</span><b>${t.ship?money(t.ship):"Free"}</b></div><div class="grand"><span>Total</span><b>${money(t.total)}</b></div></div>
<a class="btn btn-primary btn-block" href="checkout.html">Proceed to checkout</a>
<div class="secure"><svg viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>Secure checkout · All sales final on shipped goods</div>`;
    document.getElementById("promoBtn").onclick=()=>{const v=document.getElementById("promoIn").value; const ok=Cart.setPromo(v); const m=document.getElementById("promoMsg"); if(!ok){m.textContent="That code isn't valid."; m.className="promo-msg err";} else draw();};
  }
  document.addEventListener("cart:change",draw); draw();
})();
