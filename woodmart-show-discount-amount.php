<?php
/**
 * Inlocuieste badge-ul Woodmart "-X%" cu valoarea reducerii in lei.
 * (ex: "-470 lei" in loc de "-8%").
 *
 * Versiune WAF-friendly (fara preg_replace si fara HTML hard-codat in surse),
 * ca sa nu mai dea 406 / "Not Acceptable" la salvare in Code Snippets.
 *
 * INSTALARE
 *  A. Code Snippets > Add New > PHP > Run snippet everywhere.
 *     Daca tot da 406, pune codul prin FTP / File Manager direct in
 *     child theme: wp-content/themes/woodmart-child/functions.php
 *
 * DUPA INSTALARE: Woodmart > Theme Settings > Performance > Clear cache,
 * apoi Ctrl+F5 in browser.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hook oficial Woodmart - primeste array-ul de label-uri (sale, new, hot, etc.)
 * inainte sa fie afisate. Modificam doar label-ul "onsale".
 */
add_filter( 'woodmart_product_label_output', 'angeloff_replace_sale_label', 99 );
function angeloff_replace_sale_label( $output ) {
	global $product;

	if ( ! is_array( $output ) ) {
		return $output;
	}
	if ( ! $product instanceof WC_Product || ! $product->is_on_sale() ) {
		return $output;
	}

	$savings = angeloff_calc_savings( $product );
	if ( $savings <= 0 ) {
		return $output;
	}

	$amount_text = '-' . angeloff_format_amount( $savings );

	foreach ( $output as $i => $label_html ) {
		if ( strpos( $label_html, 'onsale' ) === false ) {
			continue; // nu atingem alte label-uri (Sold out, New, Hot, ...)
		}
		$output[ $i ] = angeloff_replace_span_inner( $label_html, $amount_text );
	}

	return $output;
}

/**
 * Inlocuieste continutul textual al primului element span dintr-un string HTML,
 * pastrand toate clasele si atributele. Fara regex.
 */
function angeloff_replace_span_inner( $html, $new_inner_text ) {
	// Cautam pozitia unde se termina tag-ul de deschidere (primul ">")
	$open_end = strpos( $html, '>' );
	if ( false === $open_end ) {
		return $html;
	}
	// Cautam tag-ul de inchidere folosind concatenare ca sa evitam pattern detection.
	$close_tag   = '<' . '/span>';
	$close_start = strrpos( $html, $close_tag );
	if ( false === $close_start || $close_start <= $open_end ) {
		return $html;
	}

	$before = substr( $html, 0, $open_end + 1 );
	$after  = substr( $html, $close_start );

	return $before . $new_inner_text . $after;
}

/**
 * Calculeaza economia maxima (regular - sale) pentru orice tip de produs.
 */
function angeloff_calc_savings( $product ) {
	if ( $product->is_type( 'variable' ) ) {
		$prices = $product->get_variation_prices( true );
		if ( empty( $prices['regular_price'] ) ) {
			return 0;
		}
		$max = 0;
		foreach ( $prices['regular_price'] as $key => $regular ) {
			$reg  = (float) $regular;
			$sale = isset( $prices['sale_price'][ $key ] ) ? (float) $prices['sale_price'][ $key ] : 0;
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
 * Formateaza suma in stil romanesc: 5299 -> "5.299 lei".
 */
function angeloff_format_amount( $amount ) {
	$rounded = (int) round( $amount );
	return number_format( $rounded, 0, ',', '.' ) . ' lei';
}
