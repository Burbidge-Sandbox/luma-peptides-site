<?php
/**
 * FAQ v2 (2026-10-02): grouped, short answers, card rows, contact card.
 * Rendered by page-legacy.php for the "faq" page. The seeded legacy FAQ is
 * untouched in the database — delete the is_page('faq') branch in
 * page-legacy.php to bring it back.
 */
$u  = luma_catalogue_json()['config']['urls'];
$sd = class_exists( 'Luma\\Core\\SameDay' ) && Luma\Core\SameDay::enabled();
$a  = fn( $href, $text ) => '<a href="' . esc_url( $href ) . '">' . esc_html( $text ) . '</a>';
$icons = [
	'general'  => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
	'ordering' => '<path d="M5 7h14l-1.5 11h-11z"/><path d="M9 7a3 3 0 0 1 6 0"/>',
	'quality'  => '<path d="M10 3h4M11 3v6l-5 9a2 2 0 0 0 1.8 3h8.4a2 2 0 0 0 1.8-3l-5-9V3"/><path d="M8 15h8"/>',
	'storage'  => '<path d="M12 3v18M4.5 7.5l15 9M19.5 7.5l-15 9"/>',
	'shipping' => '<path d="M3 7h11v9H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="17.5" r="1.5"/><circle cx="17" cy="17.5" r="1.5"/>',
	'returns'  => '<path d="M4 9h11a5 5 0 0 1 0 10H9"/><path d="M8 5L4 9l4 4"/>',
];
$groups = [
	[ 'general', 'General', null, [
		[ 'Who can order?', 'Researchers 21 or older who are affiliated with a laboratory, institution, or research organization. Every order includes a research-use acknowledgement.' ],
		[ 'Do you verify research affiliation?', 'We may ask for it, and we may decline or cancel any order.' ],
		[ 'Are these products for human use?', 'No. Everything we sell is for laboratory research only. It is not for human or animal use.' ],
		[ 'Do you give usage or reconstitution guidance?', 'No. Luma is a chemical supplier, so we decline requests for handling, reconstitution, or usage guidance.' ],
	] ],
	[ 'ordering', 'Ordering &amp; payment', null, [
		[ 'How does pricing work?', 'Per vial. Volume pricing applies automatically at 3, 5, and 10 vials. No subscriptions.' ],
		[ 'What payment methods do you accept?', 'Card, Apple Pay, Google Pay, Link, or Venmo. Venmo orders are released once we match the payment, so put your order number in the note.' ],
		[ 'Is bacteriostatic water included?', 'No. It is sold separately under ' . $a( $u['shop'], 'Solvents' ) . '.' ],
	] ],
	[ 'quality', 'Quality &amp; testing', [ $u['verify'], 'Verify a lot' ], [
		[ 'Is every lot tested?', 'Yes. An independent, accredited U.S. laboratory tests every lot before it ships.' ],
		[ 'What does testing cover?', 'Purity (HPLC), identity (LC-MS), and net content.' ],
		[ 'Where do I find the certificate (COA)?', 'Enter the lot number printed on your vial on ' . $a( $u['verify'], 'Verify a Lot' ) . '. Full lab reports are available on request.' ],
		[ 'What purity are your peptides?', 'Each listing shows the purity of the most recent lot. The certificate gives the exact figure.' ],
	] ],
	[ 'storage', 'Storage', null, [
		[ 'Do vials need refrigerating when they arrive?', 'Freeze-dried peptides stay stable at room temperature for several days in transit. Once they arrive, store unopened vials at −20 °C, kept dry and away from light.' ],
		[ 'Anything else to know?', 'Let a vial reach room temperature before opening it to avoid condensation.' ],
	] ],
	[ 'shipping', 'Shipping', [ $u['track'], 'Track an order' ], array_values( array_filter( [
		[ 'How fast do orders ship?', 'Within one business day of lab release. Next-day shipping is free on every order.' ],
		$sd ? [ 'Do you offer same-day delivery?', esc_html( Luma\Core\SameDay::promise() ) . ' ' . $a( $u['shipping'] . '#same-day', 'Check your ZIP →' ) ] : null,
		[ 'Do you ship internationally?', 'No. We ship within the United States only.' ],
		[ 'How are orders packaged?', 'In plain outer packaging.' ],
		[ 'Can I track my order?', 'Yes. ' . $a( $u['track'], 'Track any order' ) . ' with your order number and email.' ],
	] ) ) ],
	[ 'returns', 'Returns', null, [
		[ 'Can I return products?', 'All sales are final once an order ships. If an item arrives damaged, leaking, or incorrect, send us photos within 7 days of delivery and we will replace it. ' . $a( $u['shipping'], 'Shipping &amp; returns' ) . '.' ],
	] ],
];
$svg = fn( $p ) => '<svg viewBox="0 0 24 24" aria-hidden="true">' . $p . '</svg>';
?>
<div class="wrap page-head faq2-head"><span class="eyebrow">Support</span><h1>Frequently asked questions</h1><p>Testing, shipping, payment, and how we sell for research use.</p></div>
<div class="wrap faq2">
<?php foreach ( $groups as [ $key, $label, $pill, $items ] ) : ?>
 <section class="faq2-group" aria-labelledby="faq-<?php echo esc_attr( $key ); ?>">
  <div class="faq2-label"><span class="faq2-icon"><?php echo $svg( $icons[ $key ] ); // phpcs:ignore ?></span><h2 id="faq-<?php echo esc_attr( $key ); ?>"><?php echo $label; // phpcs:ignore ?></h2><span class="faq2-rule"></span><?php if ( $pill ) : ?><a class="faq2-pill" href="<?php echo esc_url( $pill[0] ); ?>"><?php echo esc_html( $pill[1] ); ?> →</a><?php endif; ?></div>
  <?php foreach ( $items as [ $q, $ans ] ) : ?>
  <details class="faq2-q"><summary><?php echo esc_html( $q ); ?></summary><p><?php echo $ans; // phpcs:ignore -- authored copy ?></p></details>
  <?php endforeach; ?>
 </section>
<?php endforeach; ?>
 <section class="faq2-group" aria-labelledby="faq-contact">
  <div class="faq2-label"><span class="faq2-icon"><?php echo $svg( '<path d="M4 5h16v11H8l-4 4z"/>' ); // phpcs:ignore ?></span><h2 id="faq-contact">Still have a question?</h2><span class="faq2-rule"></span></div>
  <div class="faq2-contact">
   <p>Reach us by email or text, or use the <a href="<?php echo esc_url( $u['contact'] ); ?>">contact form</a>.</p>
   <a class="faq2-row" href="mailto:info@lumaresearchco.com"><span class="faq2-icon"><?php echo $svg( '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>' ); // phpcs:ignore ?></span><span><small>Email</small>info@lumaresearchco.com</span></a>
   <a class="faq2-row" href="sms:+13855215259"><span class="faq2-icon"><?php echo $svg( '<rect x="7" y="2.5" width="10" height="19" rx="2"/><path d="M11 18.5h2"/>' ); // phpcs:ignore ?></span><span><small>Text</small>(385) 521-5259</span></a>
  </div>
 </section>
</div>
