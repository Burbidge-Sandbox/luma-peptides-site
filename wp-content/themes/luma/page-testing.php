<?php
/**
 * Template Name: Testing
 * Port of legacy verify.html. The lookup itself is [luma_lot_lookup] from
 * luma-core (real lots, rate-limited), rendered in the legacy .coa-card markup.
 */
get_header();
?>
<main id="main">
<div class="wrap page-head" style="text-align:center">
 <span class="eyebrow">Transparency</span>
 <h1>Verify your lot</h1>
 <p style="margin-inline:auto">Every Luma vial carries a lot number. Enter it below to view the independent laboratory certificate for that exact lot.</p>
</div>
<div class="wrap" style="padding-bottom:5rem">
 <?php echo do_shortcode( '[luma_lot_lookup]' ); ?>
 <div class="values" style="max-width:960px;margin:4rem auto 0">
  <div class="value"><svg viewBox="0 0 24 24"><path d="M9 3h6v5l4 9a3 3 0 0 1-3 4H8a3 3 0 0 1-3-4l4-9z"/><path d="M8 14h8"/></svg><h3>What we test</h3><p>Purity by HPLC, identity by LC-MS, net content by weight, and endotoxin. All four must pass.</p></div>
  <div class="value"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M8 4v5M16 4v5"/></svg><h3>When we test</h3><p>Before release, every lot. Results are published here before the first vial ships, never after.</p></div>
  <div class="value"><svg viewBox="0 0 24 24"><path d="M12 3l7 3v6c0 4.5-3 8-7 9-4-1-7-4.5-7-9V6z"/></svg><h3>Who tests</h3><p>Independent U.S. laboratories. We never test our own product.</p></div>
 </div>
 <div style="max-width:960px;margin:3rem auto 0">
  <h2 style="font-size:1.6rem">Certificate library</h2>
  <?php echo do_shortcode( '[luma_coa_library]' ); ?>
 </div>
</div>
</main>
<?php get_footer(); ?>
