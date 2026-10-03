<?php
/**
 * Front page — port of legacy index.html (markup verbatim; links → WP URLs;
 * "Best sellers" cards rendered by productCard() from the Woo catalogue).
 */
$u = luma_catalogue_json()['config']['urls'];
get_header();
?>
<main id="main">
<section class="hero editorial-hero">
 <div class="wrap">
  <div class="hero-copy">
   <span class="eyebrow">For laboratory research use only</span>
   <h1>A clearer <br>perspective <br>on <em>peptides.</em></h1>
   <div class="hero-cta"><a class="btn btn-primary" href="<?php echo esc_url( $u['shop'] ); ?>">Browse the catalog <span aria-hidden="true">↗</span></a><a class="text-link" href="<?php echo esc_url( $u['verify'] ); ?>">Our approach to verification <span aria-hidden="true">→</span></a></div>
  </div>
  <figure class="hero-visual">
   <img class="hero-scene" src="<?php echo esc_url( luma_assets_url( 'assets/hero-editorial-10mg-v2.jpg' ) ); ?>" width="1536" height="1024" alt="Luma BPC-157 vial on textured travertine in warm directional light" fetchpriority="high">
   <figcaption><span>THE LUMA COLLECTION</span></figcaption>
  </figure>
 </div>
</section>
<section class="promise-section" aria-label="Shipping and service">
 <div class="wrap"><div class="promise" id="promiseStrip"></div></div>
</section>
<section class="collection-section section paper bs9">
 <div class="wrap">
  <div class="bs9-head"><div><span class="eyebrow">Most ordered</span><h2>What researchers <em>reorder.</em></h2><p>In stock, lot-numbered, and documented by an independent lab.</p></div><a class="text-link" href="<?php echo esc_url( $u['shop'] ); ?>">View all compounds <span aria-hidden="true">↗</span></a></div>
  <div class="bs9-grid" id="topSellers"></div>
 </div>
</section>
<section class="section approach-section">
 <div class="wrap split">
  <div><span class="eyebrow">A closer look</span><h2>Clarity starts <br>with the details.</h2><p>What is in a vial matters, and so does the documentation that comes with it. Every lot is characterized by an independent laboratory for identity, purity, and net content before it is released for sale.</p><a class="text-link" href="<?php echo esc_url( $u['verify'] ); ?>">Explore lot verification <span aria-hidden="true">↗</span></a></div>
  <div class="detail-list">
   <div><span>01</span><div><h3>Review the specification</h3><p>Each listing states the compound, net content, form, purity of the most recent lot, and storage conditions.</p></div></div>
   <div><span>02</span><div><h3>Check the certificate</h3><p>Every vial carries a lot number. The certificate of analysis for that exact lot is published before the first vial ships.</p></div></div>
   <div><span>03</span><div><h3>Request documentation</h3><p>Full laboratory reports are available on request for any lot. <a href="<?php echo esc_url( $u['contact'] ); ?>">Contact Luma</a> for documentation. Usage guidance is not provided.</p></div></div>
  </div>
 </div>
</section>
<?php echo class_exists( 'Luma\\Core\\Reviews' ) ? Luma\Core\Reviews::strip() : ''; // dormant until the Google profile has 50 reviews ?>
<section class="section collection-note">
 <div class="wrap"><span class="eyebrow">Our position</span><h2>Tested. <br><em>Documented.</em></h2><p>A chemical supplier for the research community. Not a pharmacy, not a clinic. Every lot tested by an independent laboratory, every result published.</p><a class="btn btn-outline" href="<?php echo esc_url( $u['about'] ); ?>">About Luma <span aria-hidden="true">↗</span></a></div>
</section>
<section class="section paper">
 <div class="wrap faq-layout"><div><span class="eyebrow">Before you explore</span><h2>A few useful <br>answers.</h2><a class="text-link" href="<?php echo esc_url( $u['contact'] ); ?>">Get in touch ↗</a></div>
 <div class="faq">
  <details><summary>Who can order?</summary><p>Purchasers must be 21 or older and affiliated with a laboratory, institution, or research organization. Every order includes a research-use acknowledgement. Luma reserves the right to decline or cancel any order.</p></details>
  <details><summary>Where are the specifications?</summary><p>Each listing states compound, net content, form, latest-lot purity, identity method, and storage. <a href="<?php echo esc_url( $u['shop'] ); ?>">Browse the catalog</a> to compare them.</p></details>
  <details><summary>How does lot verification work?</summary><p>The lot code identifies a specific batch. Use the <a href="<?php echo esc_url( $u['verify'] ); ?>">verification page</a> to look up the certificate for the lot number printed on your vial.</p></details>
  <details><summary>Does Luma provide handling or usage guidance?</summary><p>No. Luma is a chemical supplier and does not provide reconstitution, handling, or usage guidance of any kind. Requests for such guidance are declined.</p></details>
  <details><summary>How do I track an order?</summary><p>Sign in to your account to see payment, lab release, and shipping status for every order, or use the tracking link in your shipping email.</p></details>
 </div></div>
</section>
<script>
document.addEventListener("DOMContentLoaded",()=>{
 const ids=["glp-2-t","glp-3-rt","glp-1-sm","bpc-157-ghk-cu-tb-500-blend","bpc-157"];
 (function(){ const C=window.LUMA_CONFIG, P=C.promises||{}; const tiles=[
   {k:"Ships fast",t:`Ships within ${P.shipDays||1} business day${(P.shipDays||1)>1?"s":""}`,d:`<a class="sd-link" href="${C.urls.shipping||"/shipping-returns/"}">Shipping details →</a>`},
   {k:"Free delivery",t:P.nextDay?"Free next-day shipping, always":"Free US shipping",d:P.sameDay?`<a class="sd-link" href="${C.urls.shipping||"/shipping-returns/"}#same-day">Check your ZIP for same-day delivery →</a>`:""},
   P.deliveryPromiseDays?{k:"Guaranteed",t:`Arrives in ${P.deliveryPromiseDays} business days or we reship free`,d:"If a standard order isn't delivered within "+P.deliveryPromiseDays+" business days of shipment, a replacement ships at no charge."}:{k:"Tracked",t:"Track every order",d:`<a class="sd-link" href="${C.urls.track}">Track an order →</a>`}
 ]; document.getElementById("promiseStrip").innerHTML=tiles.map(x=>`<div class="promise-tile"><span class="promise-kicker">${x.k}</span><b>${x.t}</b>${x.d?`<p>${x.d}</p>`:""}</div>`).join(""); })();
 (function(){ /* Best sellers v2 (A9): in-stock only, name on photo, price + current lot below, purity from the lot record */
  const esc=t=>String(t??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
  const money=n=>"$"+(Math.round(n*100)/100).toFixed(2);
  const short={"bpc-157-ghk-cu-tb-500-blend":"Glow Blend"};
  const pct=s=>{const n=parseFloat(String(s||"").replace(/[^\d.]/g,""));return isFinite(n)&&n>0?n.toFixed(2)+"%":"";};
  const V=window.LUMA_CONFIG.urls.verify;
  const order=["glp-2-t","glp-3-rt","bpc-157-ghk-cu-tb-500-blend","bpc-157","glp-1-sm"];
  const list=order.map(id=>window.LUMA_PRODUCTS.find(p=>p.id===id)).filter(p=>p&&p.stock==="in").slice(0,4);
  document.getElementById("topSellers").innerHTML=list.map(p=>{
   const v=(p.variants||[]).find(x=>x.stock==="in")||null;
   const price=v?v.once:p.once, lot=(v?v.lot:p.lot)||"", pur=pct(v?v.lotPurity:p.lotPurity);
   const sizes=p.variants?p.variants.filter(x=>x.stock==="in").map(x=>x.label.toUpperCase()).join(" · "):(p.strength||"").replace(/ (blend )?vial$/,"").toUpperCase();
   const vp=v?{...p,strength:v.strength,label:[p.label[0],v.key.toUpperCase()]}:p;
   const name=short[p.id]||p.name;
   return `<article class="bs9-card" data-pid="${esc(p.id)}">
  <div class="bs9-media"><a class="bs9-link" href="${esc(p.url)}${v?"?size="+v.key:""}" aria-label="${esc(p.name)}">${vialSVG(vp)}<span class="bs9-ov"><h3>${esc(name)}</h3><small>${esc(sizes)}</small></span></a>
   <button class="bs9-plus" type="button" data-add="${esc(p.id)}" data-plan="once"${v?` data-variant="${esc(v.key)}"`:""} aria-label="Add ${esc(p.name)}${v?" "+esc(v.label):""} to cart">+</button></div>
  <div class="bs9-foot"><span class="bs9-price">${money(price)}${lot?`<small>Lot ${esc(lot)}</small>`:""}</span>${pur?`<a class="bs9-pur" href="${esc(V)}?lot=${encodeURIComponent(lot)}" aria-label="${pur} purity — view certificate for lot ${esc(lot)}"><svg viewBox="0 0 16 16" aria-hidden="true"><path d="M3 8.5l3 3 7-7"/></svg>${pur}</a>`:""}</div>
 </article>`;}).join("");
 })();
});
</script>
</main>
<?php get_footer(); ?>
