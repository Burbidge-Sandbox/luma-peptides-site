<?php
/**
 * Template Name: Research labs
 *
 * /labs/ — new-lab discount landing page. One centered stack (kicker +
 * headline, subhead, tiles, form), plus the guarantee line when that setting
 * is on. Logo only, no nav, no cart, no gate. Every number is live: the discount and expiry from Luma Core settings,
 * purity from released lots (CLAUDE.md rule 11). Styles: labs.css.
 */

defined( 'ABSPATH' ) || exit;

use Luma\Core\Settings;

$pct      = class_exists( 'Luma\\Core\\LabOffer' ) ? Luma\Core\LabOffer::pct() : 25;
$lots     = class_exists( 'Luma\\Core\\Lots' ) ? Luma\Core\Lots::current_pass() : [];
$avg      = $lots ? number_format( array_sum( array_column( $lots, 'purity' ) ) / count( $lots ), 1 ) : '';
$sameday  = class_exists( 'Luma\\Core\\Settings' ) && '1' === (string) Settings::get( 'ships_same_day' );
$guaranty = class_exists( 'Luma\\Core\\Settings' ) && '1' === (string) Settings::get( 'purity_guarantee' );
$nonce    = wp_create_nonce( 'luma_claim' );
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#F7F2EC">
<link rel="icon" href="<?php echo esc_url( luma_assets_url( 'assets/favicon.svg' ) ); ?>" type="image/svg+xml">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'labs' ); ?>>
<?php wp_body_open(); ?>

<header class="lb-head"><div class="lb-wrap">
 <span class="logo lb-logo" aria-label="Luma Peptides Co."><span>luma</span><span>peptides</span><span>co.</span></span>
</div></header>

<main id="main">
<section class="lb-hero">
 <div class="lb-wrap lb-grid">
  <div>
   <span class="lb-kicker">For research labs · New accounts</span>
   <h1>Claim <?php echo (int) $pct; ?>% off.<br><em>Plus free delivery.</em></h1>
   <p class="lb-sub"><?php echo $avg ? 'Lab-verified ' . esc_html( $avg ) . '% purity. ' : ''; ?><?php echo $sameday ? 'Shipped the same day you order.' : 'Shipped within 1 business day.'; ?></p>
   <div class="lb-three<?php echo $avg ? '' : ' lb-two'; ?>">
    <div><b><?php echo (int) $pct; ?>% off</b><span>first order</span></div>
    <div><b>$0</b><span>next-day delivery</span></div>
    <?php if ( $avg ) : ?><div><b><?php echo esc_html( $avg ); ?>%</b><span>avg. purity</span></div><?php endif; ?>
   </div>
  </div>

  <div class="lb-card" id="claim">
   <form id="lbForm" novalidate>
    <h2>Claim your <?php echo (int) $pct; ?>% off</h2>
    <p class="lb-lede">Takes 10 seconds. Applied automatically at checkout.</p>
    <label class="lb-sr" for="lbName">Full name</label>
    <input id="lbName" name="name" placeholder="Full name" autocomplete="name" required minlength="2">
    <label class="lb-sr" for="lbEmail">Work email</label>
    <input id="lbEmail" name="email" type="email" placeholder="Work email" autocomplete="email" required>
    <label class="lb-sr" for="lbPhone">Phone</label>
    <input id="lbPhone" name="phone" type="tel" placeholder="Phone number" autocomplete="tel" inputmode="tel" required>
    <div class="lb-hp" aria-hidden="true"><label for="lbWebsite">Website</label><input id="lbWebsite" name="website" tabindex="-1" autocomplete="off"></div>
    <p class="lb-err" id="lbErr" role="alert">Please enter your name, a valid email and phone number.</p>
    <button class="lb-btn" type="submit" id="lbSubmit">Claim My Discount</button>
    <small>No minimum. No commitment. One discount per customer.</small>
    <small class="lb-consent"><?php echo esc_html( class_exists( 'Luma\\Core\\Leads' ) ? Luma\Core\Leads::CONSENT : '' ); ?></small>
   </form>
  </div>
 </div>
</section>


<?php if ( $guaranty ) : ?>
<section class="lb-close">
 <div class="lb-wrap">
  <h2>Under 98% pure? Full refund.</h2>
  <p>Every lot tested. Every certificate public. You take zero risk.</p>
 </div>
</section>
<?php endif; ?>
</main>

<footer class="lb-foot"><div class="lb-wrap">For laboratory research use only. Not for human or veterinary use. Not a drug, food or supplement. © <?php echo esc_html( wp_date( 'Y' ) ); ?> Luma Peptides Co. LLC</div></footer>
<div class="lb-sticky" id="lbSticky"><a class="lb-btn" href="#claim" data-claim>Claim <?php echo (int) $pct; ?>% off + free delivery</a></div>

<script>
(function(){
 var KEY="luma_lab_src", q=new URLSearchParams(location.search), src={};
 try{ src=JSON.parse(sessionStorage.getItem(KEY)||"{}"); }catch(e){}
 ["utm_source","utm_medium","utm_campaign","utm_term","utm_content","fbclid","gclid"].forEach(function(k){ if(q.get(k)) src[k]=q.get(k); });
 if(!src.landing) src.landing=location.href;
 if(!src.referrer && document.referrer && document.referrer.indexOf(location.host)<0) src.referrer=document.referrer;
 try{ sessionStorage.setItem(KEY,JSON.stringify(src)); }catch(e){}

 var f=document.getElementById("lbForm"), E=f.elements, err=document.getElementById("lbErr"), btn=document.getElementById("lbSubmit");
 var api=<?php echo wp_json_encode( esc_url_raw( rest_url( 'luma/v1/' ) ) ); ?>, rest=<?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>, nonce=<?php echo wp_json_encode( $nonce ); ?>;
 function show(msg){ err.textContent=msg; err.classList.add("show"); }
 function send(body){ return fetch(api+"claim",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","X-WP-Nonce":rest},body:JSON.stringify(body)}); }
 f.addEventListener("submit",async function(e){
  e.preventDefault();
  var nm=E.name.value.trim(), em=E.email.value.trim(), ph=E.phone.value.replace(/\D/g,"");
  var ok=nm.length>1&&/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(em)&&ph.length>=10;
  err.classList.toggle("show",!ok); if(!ok){ err.textContent="Please enter your name, a valid email and phone number."; (nm.length>1?(/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(em)?E.phone:E.email):E.name).focus(); return; }
  btn.disabled=true; btn.textContent="Claiming…";
  var body={name:nm,email:em,phone:E.phone.value,website:E.website.value,nonce:nonce,src:src};
  try{
   var r=await send(body);
   if(r.status===403){ var n=await fetch(api+"claim-nonce?cb="+Date.now(),{credentials:"same-origin",headers:{"X-WP-Nonce":rest}}).then(function(x){return x.json();}); body.nonce=nonce=n.nonce; r=await send(body); }
   var j=await r.json().catch(function(){return {};});
   if(!r.ok||!j.redirect){ throw new Error(j.message||"Something went wrong. Please try again."); }
   if(typeof window.fbq==="function"){ try{ fbq("track","Lead"); }catch(x){} }
   try{ sessionStorage.removeItem(KEY); }catch(x){}
   location.href=j.redirect;
  }catch(x){ show(x.message); btn.disabled=false; btn.textContent="Claim My Discount"; }
 });

 document.querySelectorAll("[data-claim]").forEach(function(a){ a.addEventListener("click",function(e){ e.preventDefault(); var card=document.getElementById("claim"); card.scrollIntoView({behavior:matchMedia("(prefers-reduced-motion: reduce)").matches?"auto":"smooth",block:"center"}); setTimeout(function(){ E.name.focus({preventScroll:true}); },matchMedia("(prefers-reduced-motion: reduce)").matches?0:450); }); });

 var st=document.getElementById("lbSticky");
 if("IntersectionObserver" in window){ new IntersectionObserver(function(es){ st.classList.toggle("show",!es[0].isIntersecting&&scrollY>200); }).observe(document.getElementById("claim")); }
})();
</script>
<?php wp_footer(); ?>
</body>
</html>
