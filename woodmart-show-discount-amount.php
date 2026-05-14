<?php
/**
 * Înlocuiește badge-ul "-X%" Woodmart cu valoarea reducerii în lei
 * (ex: "-470 lei" în loc de "-8%).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  DE CE FUNCȚIONEAZĂ ASTA (și de ce nu mergea codul anterior)
 * ─────────────────────────────────────────────────────────────────────────────
 * Tema Woodmart NU lasă filtrul standard `woocommerce_sale_flash` să decidă
 * conținutul badge-ului. În schimb, are propria funcție `woodmart_product_label`
 * care construiește un array cu toate label-urile (Sale, New, Hot, Sold Out)
 * și calculează procentul direct, astfel:
 *
 *     $percentage = round( ( ( $regular - $sale ) / $regular ) * 100 );
 *     $output[]   = '<span class="onsale product-label">-X%</span>';
 *     $output     = apply_filters( 'woodmart_product_label_output', $output );
 *
 * Singurul hook OFICIAL prin care putem interveni la sfârșit este filtrul
 * `woodmart_product_label_output` care primește întregul array de label-uri.
 *
 * Așa că:
 *  1. Hookăm pe `woodmart_product_label_output` (prioritate mare).
 *  2. Găsim în array span-ul cu clasa "onsale".
 *  3. Recalculăm reducerea în RON (din `$product` global) și înlocuim conținutul.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  CUM SE INSTALEAZĂ
 * ─────────────────────────────────────────────────────────────────────────────
 *  Varianta A (recomandată): plugin "Code Snippets" → add new → PHP snippet,
 *                            "Run snippet everywhere" → lipești tot conținutul
 *                            fișierului (fără tag-ul <?php de sus dacă cere fără).
 *
 *  Varianta B: child theme → wp-content/themes/woodmart-child/functions.php
 *              → adaugi conținutul (fără <?php dacă deja există).
 *
 *  După activare: Woodmart → Theme Settings → Performance → Clear cache,
 *  apoi Ctrl+F5 în browser. Dacă ai WP Rocket / LiteSpeed / W3 Total Cache,
 *  golește și acolo.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Înlocuiește textul "-X%" din badge-ul "onsale" cu valoarea reducerii.
 *
 * @param array $output Array de string-uri HTML cu label-urile produsului.
 * @return array
 */
add_filter( 'woodmart_product_label_output', 'angeloff_replace_percentage_with_amount', 99 );
function angeloff_replace_percentage_with_amount( $output ) {
	global $product;

	if ( ! $product instanceof WC_Product || ! $product->is_on_sale() || ! is_array( $output ) ) {
		return $output;
	}

	$savings = angeloff_calculate_savings( $product );
	if ( $savings <= 0 ) {
		return $output;
	}

	// wc_price() returnează "<span class="woocommerce-Price-amount amount">5.299,00&nbsp;<span class="woocommerce-Price-currencySymbol">lei</span></span>"
	// Pentru badge folosim text simplu, mai curat:
	$savings_text = angeloff_format_amount( $savings );

	foreach ( $output as $i => $label_html ) {
		// Căutăm doar span-ul "onsale" - nu atingem "Sold out", "New", "Hot", etc.
		if ( strpos( $label_html, 'onsale' ) === false ) {
			continue;
		}

		// Înlocuim conținutul interior al span-ului, păstrând clasele Woodmart.
		$output[ $i ] = preg_replace(
			'/(<span[^>]*class="[^"]*onsale[^"]*"[^>]*>)(.*?)(<\/span>)/s',
			'$1-' . $savings_text . '$3',
			$label_html
		);
	}

	return $output;
}

/**
 * Calculează economia maximă (regular - sale) indiferent de tipul produsului.
 *
 * @param WC_Product $product
 * @return float
 */
function angeloff_calculate_savings( $product ) {
	if ( $product->is_type( 'variable' ) ) {
		$prices = $product->get_variation_prices( true );
		if ( empty( $prices['regular_price'] ) || empty( $prices['sale_price'] ) ) {
			return 0;
		}
		$max = 0;
		foreach ( $prices['regular_price'] as $key => $regular ) {
			$sale = isset( $prices['sale_price'][ $key ] ) ? (float) $prices['sale_price'][ $key ] : 0;
			$reg  = (float) $regular;
			if ( $sale > 0 && $sale < $reg ) {
				$diff = $reg - $sale;
				if ( $diff > $max ) {
					$max = $diff;
				}
			}
		}
		return $max;
	}

	$regular = (float) $product->get_regular_price();
	$sale    = (float) $product->get_sale_price();
	if ( $regular <= 0 || $sale <= 0 || $sale >= $regular ) {
		return 0;
	}
	return $regular - $sale;
}

/**
 * Formatează o sumă pentru afișare în badge (rotunjit la întreg, separator RO).
 * Ex: 470.50 → "470 lei", 5299 → "5.299 lei"
 *
 * @param float $amount
 * @return string
 */
function angeloff_format_amount( $amount ) {
	$rounded   = (int) round( $amount );
	$formatted = number_format( $rounded, 0, ',', '.' );
	return $formatted . '&nbsp;lei';
}
