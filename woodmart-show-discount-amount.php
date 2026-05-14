<?php
/**
 * Înlocuiește badge-ul "-X%" cu valoarea reală a reducerii (ex: "-200 RON")
 * Funcționează pentru: simple, variable, variation
 * Compatibil cu tema Woodmart (păstrează clasele wd-shape-round-sm etc.)
 *
 * Adaugă acest cod în:
 *   - functions.php din child theme, SAU
 *   - plugin-ul Code Snippets (recomandat)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'woocommerce_sale_flash', 'angeloff_show_saved_amount', 99, 3 );
function angeloff_show_saved_amount( $html, $post, $product ) {

	if ( ! $product || ! $product->is_on_sale() ) {
		return $html;
	}

	$regular_price = 0;
	$sale_price    = 0;

	if ( $product->is_type( 'simple' ) || $product->is_type( 'external' ) ) {
		$regular_price = (float) $product->get_regular_price();
		$sale_price    = (float) $product->get_sale_price();

	} elseif ( $product->is_type( 'variable' ) ) {
		// Pentru produse variabile luăm reducerea MAXIMĂ dintre variații
		$max_saving = 0;
		foreach ( $product->get_visible_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation || ! $variation->is_on_sale() ) {
				continue;
			}
			$reg  = (float) $variation->get_regular_price();
			$sale = (float) $variation->get_sale_price();
			$diff = $reg - $sale;
			if ( $diff > $max_saving ) {
				$max_saving    = $diff;
				$regular_price = $reg;
				$sale_price    = $sale;
			}
		}

	} elseif ( $product->is_type( 'variation' ) ) {
		$regular_price = (float) $product->get_regular_price();
		$sale_price    = (float) $product->get_sale_price();
	}

	$savings = $regular_price - $sale_price;

	if ( $savings <= 0 ) {
		return $html;
	}

	// Rotunjim la întreg (fără zecimale) - scoate round() dacă vrei zecimale
	$savings_formatted = wc_price( round( $savings ) );

	// Păstrăm clasele Woodmart originale
	return '<span class="onsale product-label wd-shape-round-sm">-' . $savings_formatted . '</span>';
}
