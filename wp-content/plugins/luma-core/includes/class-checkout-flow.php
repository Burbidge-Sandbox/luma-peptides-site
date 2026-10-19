<?php
/**
 * Mobile checkout, simplified (2026-10-19).
 *
 * 1. Store API method override. WordPress's api-fetch turns every PUT into
 *    POST + `X-HTTP-Method-Override: PUT`. On this stack that header does not
 *    reach PHP from real browsers, so the block checkout's background PUT to
 *    /wc/store/v1/checkout arrived as a POST and failed with the red
 *    "Missing parameter(s): billing_address, additional_fields" banner on
 *    every checkout load. WordPress also honours `?_method=PUT`, which does
 *    get through, so a small fetch wrapper copies the override into the URL
 *    for Store API calls only.
 * 2. Block order. The RUO acknowledgement (the "additional information"
 *    block) renders first, above the express-payment buttons, so it is
 *    checked before Apple Pay / Google Pay can submit. The order-note block
 *    is dropped.
 * 3. No account creation at checkout (guest checkout only).
 */

namespace Luma\Core;

defined( 'ABSPATH' ) || exit;

class CheckoutFlow {

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'method_override_shim' ], 100 );
		add_filter( 'render_block_data', [ __CLASS__, 'reorder_fields' ], 10, 1 );
		add_filter( 'pre_option_woocommerce_enable_signup_and_login_from_checkout', fn() => 'no' );
		add_filter( 'pre_option_woocommerce_enable_delayed_account_creation', fn() => 'no' );
	}

	public static function method_override_shim(): void {
		if ( ! wp_script_is( 'wp-api-fetch', 'registered' ) ) {
			return;
		}
		$js = <<<'JS'
(function(){var f=window.fetch;if(!f||f.__lumaMO)return;var w=function(input,init){try{if(init&&typeof input==="string"&&input.indexOf("/wc/store/")>-1&&String(init.method||"").toUpperCase()==="POST"){var h=init.headers||{},m=null;if(typeof Headers!=="undefined"&&h instanceof Headers){m=h.get("X-HTTP-Method-Override");}else{for(var k in h){if(k.toLowerCase()==="x-http-method-override"){m=h[k];}}}if(m&&!/[?&]_method=/.test(input)){input+=(input.indexOf("?")>-1?"&":"?")+"_method="+encodeURIComponent(m);}}}catch(e){}return f.call(this,input,init);};w.__lumaMO=1;window.fetch=w;})();
JS;
		wp_add_inline_script( 'wp-api-fetch', $js, 'before' );
	}

	/** Put the RUO acknowledgement block first; drop the order-note block. */
	public static function reorder_fields( array $block ): array {
		if ( 'woocommerce/checkout-fields-block' !== ( $block['blockName'] ?? '' ) || empty( $block['innerBlocks'] ) ) {
			return $block;
		}
		$ack   = [];
		$rest  = [];
		foreach ( $block['innerBlocks'] as $inner ) {
			$name = $inner['blockName'] ?? '';
			if ( 'woocommerce/checkout-order-note-block' === $name ) {
				continue;
			}
			if ( 'woocommerce/checkout-additional-information-block' === $name ) {
				$ack[] = $inner;
			} else {
				$rest[] = $inner;
			}
		}
		$blocks = array_merge( $ack, $rest );

		/* innerContent holds one null placeholder per inner block, in order; rebuild it to match the new count. */
		$content = [];
		$left    = count( $blocks );
		foreach ( $block['innerContent'] as $piece ) {
			if ( null === $piece ) {
				if ( $left > 0 ) {
					$content[] = null;
					--$left;
				}
				continue;
			}
			$content[] = $piece;
		}
		$block['innerBlocks']  = $blocks;
		$block['innerContent'] = $content;
		return $block;
	}
}
