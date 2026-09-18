<?php $u = luma_catalogue_json()['config']['urls']; $cats = luma_catalogue_json()['categories']; ?>
<footer class="footer"><div class="wrap">
 <div class="footer-grid">
  <div><a class="logo" href="<?php echo esc_url( $u['home'] ); ?>"><span>luma</span><span>peptides</span><span>co.</span></a><p class="tag">Research-grade peptides, independently tested by lot. For laboratory research use only.</p><p class="tag biz"><a href="mailto:info@lumaresearchco.com">info@lumaresearchco.com</a><br><a href="sms:+13855215259">Text (385) 521-5259</a><br>30 N Gould St<br>Sheridan, WY 82801</p></div>
  <div><h4>Catalog</h4><ul><?php foreach ( $cats as $slug => $name ) : ?><li><a href="<?php echo esc_url( $slug === 'all' ? $u['shop'] : add_query_arg( 'cat', $slug, $u['shop'] ) ); ?>"><?php echo esc_html( $name ); ?></a></li><?php endforeach; ?></ul></div>
  <div><h4>Company</h4><ul><li><a href="<?php echo esc_url( $u['about'] ); ?>">About</a></li><li><a href="<?php echo esc_url( $u['verify'] ); ?>">Verify a Lot</a></li><li><a href="<?php echo esc_url( $u['contact'] ); ?>">Contact</a></li></ul></div>
  <div><h4>Support</h4><ul><li><a href="<?php echo esc_url( $u['faq'] ); ?>">FAQ</a></li><li><a href="<?php echo esc_url( $u['orders'] ); ?>">Track order</a></li><li><a href="<?php echo esc_url( $u['account'] ); ?>">Account</a></li><li><a href="<?php echo esc_url( $u['shipping'] ); ?>">Shipping</a></li><li><a href="<?php echo esc_url( $u['shipping'] . '#returns' ); ?>">Returns</a></li><li><a href="<?php echo esc_url( $u['privacy'] ); ?>">Privacy Policy</a></li><li><a href="<?php echo esc_url( $u['terms'] ); ?>">Terms</a></li></ul></div>
  <div><h4>Follow Us</h4><div class="social">
   <a href="#" aria-label="Instagram"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".8" fill="currentColor"/></svg></a>
   <a href="#" aria-label="Facebook"><svg viewBox="0 0 24 24"><path d="M14 8h3V4h-3a4 4 0 0 0-4 4v3H7v4h3v6h4v-6h3l1-4h-4V8a1 1 0 0 1 1-1z"/></svg></a>
   <a href="mailto:info@lumaresearchco.com" aria-label="Email"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg></a>
  </div></div>
 </div>
 <p class="disclaimer"><b>For laboratory research use only. Not for human or animal use.</b> Statements on this site have not been evaluated by the FDA; products are not intended to diagnose, treat, cure, or prevent any disease. Luma Peptides Co. is a chemical supplier, not a pharmacy or clinic. Purchasers must be 21 or older and affiliated with a research organization. <a href="<?php echo esc_url( $u['terms'] ); ?>" style="text-decoration:underline">Full terms</a>.</p>
 <div class="footer-bottom"><span>© <?php echo esc_html( gmdate( 'Y' ) ); ?> Luma Peptides Co. All rights reserved. lumaresearchco.com is operated by Luma Peptides Co.</span><div class="payments" aria-label="Payments accepted"><span class="pay-badge pay-visa">VISA</span><span class="pay-badge pay-mc">Mastercard</span><span class="pay-badge pay-amex">AMEX</span><span class="pay-badge pay-disc">Discover</span><span class="pay-badge pay-apple">&#63743; Pay</span><span class="pay-badge pay-google">G Pay</span><span class="venmo-badge">VENMO</span></div></div>
</div></footer>
<div class="overlay" id="overlay"></div>
<aside class="drawer" id="drawer" role="dialog" aria-modal="true" aria-label="Shopping cart" aria-hidden="true">
 <div class="drawer-head"><h2>Your cart</h2><button class="icon-btn" id="closeDrawer" aria-label="Close cart"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>
 <div class="drawer-body" id="drawerBody"></div>
 <div class="drawer-foot" id="drawerFoot"></div>
</aside>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<div class="gate" id="gate" hidden>
 <div class="gate-card" role="dialog" aria-modal="true" aria-labelledby="gateTitle" aria-describedby="gateBody">
  <div class="logo" aria-hidden="true"><span>luma</span><span>peptides co.</span></div>
  <span class="eyebrow">Before you continue</span>
  <h2 id="gateTitle">Research materials only.</h2>
  <div class="gate-body" id="gateBody">
   <p>Everything here is supplied <b>for laboratory research use only</b>: not for human or animal use, and not intended to diagnose, treat, cure, or prevent any disease. Statements have not been evaluated by the FDA. Luma Peptides Co. is a chemical supplier, not a pharmacy or clinic, and provides no usage guidance.</p>
  </div>
  <p class="gate-affirm">By entering I confirm I am <b>21 or older</b>, ordering for a laboratory or research organization, will use products <b>for in-vitro research only</b> and never administer them to any human or animal, and accept the <a href="<?php echo esc_url( $u['terms'] ); ?>" target="_blank" rel="noopener">Terms</a> and <a href="<?php echo esc_url( $u['privacy'] ); ?>" target="_blank" rel="noopener">Privacy Policy</a>.</p>
  <div class="gate-actions">
   <button class="btn btn-primary is-ready" id="gateEnter" type="button">I'm 21+ and agree · Enter</button>
   <a class="btn btn-outline" href="https://www.google.com" id="gateLeave">Leave</a>
  </div>
 </div>
</div>
<?php wp_footer(); ?>
</body>
</html>
