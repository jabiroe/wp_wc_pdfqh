<?php global $wp_wc_pdfqh; ?>
<?php do_action( 'wp_wc_pdfqh_before_document', $wp_wc_pdfqh->export->template_type, $wp_wc_pdfqh->export->order ); ?>

<table class="head container">
	<tr>
		<td class="header">
		<?php
		if( $wp_wc_pdfqh->get_header_logo_id() ) {
			$wp_wc_pdfqh->header_logo();
		} else {
			echo apply_filters( 'wp_wc_pdfqh_quote_title', __( 'Quote', 'wp_wc_pdfqh' ) );
		}
		?>
		</td>
		<td class="shop-info">
			<div class="shop-name"><h3><?php $wp_wc_pdfqh->shop_name(); ?></h3></div>
			<div class="shop-address"><?php $wp_wc_pdfqh->shop_address(); ?></div>
		</td>
	</tr>
</table>

<h1 class="document-type-label">
<?php if( $wp_wc_pdfqh->get_header_logo_id() ) echo apply_filters( 'wp_wc_pdfqh_invoice_title', __( 'Quote', 'wp_wc_pdfqh' ) ); ?>
</h1>

<?php do_action( 'wp_wc_pdfqh_after_document_label', $wp_wc_pdfqh->export->template_type, $wp_wc_pdfqh->export->order ); ?>

<table class="quote-layout"><tr><td class="quote-layout-left"><?php $wp_wc_pdfqh->extra_1(); ?></td><td class="quote-layout-right"><table class="order-data-addresses">
	<tr>
		<td class="address billing-address">
			 <h3><?php _e( 'Billing Address:', 'wp_wc_pdfqh' ); ?></h3> 
			<?php $wp_wc_pdfqh->billing_address(); ?>
			<?php if ( isset($wp_wc_pdfqh->settings->template_settings['quote_email']) ) { ?>
			<div class="billing-email"><?php $wp_wc_pdfqh->billing_email(); ?></div>
			<?php } ?>
			<?php if ( isset($wp_wc_pdfqh->settings->template_settings['quote_phone']) ) { ?>
			<div class="billing-phone"><?php $wp_wc_pdfqh->billing_phone(); ?></div>
			<?php } ?>
		</td>
		<td class="order-data" rowspan="2">
			<table>
				<?php do_action( 'wp_wc_pdfqh_before_order_data', $wp_wc_pdfqh->export->template_type, $wp_wc_pdfqh->export->order ); ?>
				<?php if ( isset($wp_wc_pdfqh->settings->template_settings['display_number']) && $wp_wc_pdfqh->settings->template_settings['display_number'] == 'quote_number') { ?>
				<tr class="quote-number">
					<th><?php _e( 'Quote Number:', 'wp_wc_pdfqh' ); ?></th>
					<td><?php $wp_wc_pdfqh->quote_number(); ?></td>
				</tr>
				<?php } ?>
				<?php if ( isset($wp_wc_pdfqh->settings->template_settings['display_date']) && $wp_wc_pdfqh->settings->template_settings['display_date'] == 'quote_date') { ?>
				<tr class="quote-date">
					<th><?php _e( 'Quote Date:', 'wp_wc_pdfqh' ); ?></th>
					<td><?php $wp_wc_pdfqh->quote_date(); ?></td>
				</tr>
				<?php } ?>
				<tr class="order-number">
					<th><?php _e( 'Order Number:', 'wp_wc_pdfqh' ); ?></th>
					<td><?php $wp_wc_pdfqh->order_number(); ?></td>
				</tr>
				<tr class="order-date">
					<th><?php _e( 'Order Date:', 'wp_wc_pdfqh' ); ?></th>
					<td><?php $wp_wc_pdfqh->order_date(); ?></td>
				</tr>
				<tr class="payment-method">
					<th><?php _e( 'Payment Method:', 'wp_wc_pdfqh' ); ?></th>
					<td><?php $wp_wc_pdfqh->payment_method(); ?></td>
				</tr>
				<?php do_action( 'wp_wc_pdfqh_after_order_data', $wp_wc_pdfqh->export->template_type, $wp_wc_pdfqh->export->order ); ?>
			</table>			
		</td>
	</tr><tr><td class="address shipping-address">
			<?php if ( isset($wp_wc_pdfqh->settings->template_settings['quote_shipping_address']) && $wp_wc_pdfqh->ships_to_different_address()) { ?>
			<h3><?php _e( 'Ship To:', 'wpo_wcpdf' ); ?></h3>
			<?php $wp_wc_pdfqh->shipping_address(); ?>
			<?php } ?>
		</td>
</tr>		
</table>

<?php do_action( 'wp_wc_pdfqh_before_order_details', $wp_wc_pdfqh->export->template_type, $wp_wc_pdfqh->export->order ); ?>

<table class="order-details">
	<thead>
		<tr>
			<th class="product"><?php _e('Product', 'wp_wc_pdfqh'); ?></th>
			<th class="quantity"><?php _e('Quantity', 'wp_wc_pdfqh'); ?></th>
			<th class="price"><?php _e('Price', 'wp_wc_pdfqh'); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php $items = $wp_wc_pdfqh->get_order_items(); if( sizeof( $items ) > 0 ) : foreach( $items as $item_id => $item ) : ?>
		<tr class="<?php echo apply_filters( 'wp_wc_pdfqh_item_row_class', $item_id, $wp_wc_pdfqh->export->template_type, $wp_wc_pdfqh->export->order, $item_id ); ?>">
			<td class="product">
				<?php $description_label = __( 'Description', 'wp_wc_pdfqh' ); // registering alternate label translation ?>
				<span class="item-name"><?php echo $item['name']; ?></span>
				<?php do_action( 'wp_wc_pdfqh_before_item_meta', $wp_wc_pdfqh->export->template_type, $item, $wp_wc_pdfqh->export->order  ); ?>
				<span class="item-meta"><?php echo $item['meta']; ?></span>
				<dl class="meta">
					<?php $description_label = __( 'SKU', 'wp_wc_pdfqh' ); // registering alternate label translation ?>
					<?php if( !empty( $item['sku'] ) ) : ?><dt class="sku"><?php _e( 'SKU:', 'wp_wc_pdfqh' ); ?></dt><dd class="sku"><?php echo $item['sku']; ?></dd><?php endif; ?>
					<?php if( !empty( $item['weight'] ) ) : ?><dt class="weight"><?php _e( 'Weight:', 'wp_wc_pdfqh' ); ?></dt><dd class="weight"><?php echo $item['weight']; ?><?php echo get_option('woocommerce_weight_unit'); ?></dd><?php endif; ?>
				</dl>
				<?php do_action( 'wp_wc_pdfqh_after_item_meta', $wp_wc_pdfqh->export->template_type, $item, $wp_wc_pdfqh->export->order  ); ?>
			</td>
			<td class="quantity"><?php echo $item['quantity']; ?></td>
			<td class="price"><?php echo $item['order_price']; ?></td>
		</tr>
		<?php endforeach; endif; ?>
	</tbody>
	<tfoot>
		<tr class="no-borders">
			<td class="no-borders">
				<div class="customer-notes">
					<?php do_action( 'wp_wc_pdfqh_before_customer_notes', $wp_wc_pdfqh->export->template_type, $wp_wc_pdfqh->export->order ); ?>
					<?php if ( $wp_wc_pdfqh->get_shipping_notes() ) : ?>
						<h3><?php _e( 'Customer Notes', 'wp_wc_pdfqh' ); ?></h3>
						<?php $wp_wc_pdfqh->shipping_notes(); ?>
					<?php endif; ?>
					<?php do_action( 'wp_wc_pdfqh_after_customer_notes', $wp_wc_pdfqh->export->template_type, $wp_wc_pdfqh->export->order ); ?>
				</div>				
			</td>
			<td class="no-borders" colspan="2">
				<table class="totals">
					<tfoot>
						<?php foreach( $wp_wc_pdfqh->get_woocommerce_totals() as $key => $total ) : ?>
						<tr class="<?php echo $key; ?>">
							<td class="no-borders"></td>
							<th class="description"><?php echo $total['label']; ?></th>
							<td class="price"><span class="totals-price"><?php echo $total['value']; ?></span></td>
						</tr>
						<?php endforeach; ?>
					</tfoot>
				</table>
			</td>
		</tr>
	</tfoot>
</table><div>Betaalinstructies: <?php $wp_wc_pdfqh->payment_method(); ?></div>

<div><?php $wp_wc_pdfqh->extra_2(); ?></div></td></tr></table><?php do_action( 'wp_wc_pdfqh_after_order_details', $wp_wc_pdfqh->export->template_type, $wp_wc_pdfqh->export->order ); ?>

<?php if ( $wp_wc_pdfqh->get_footer() ): ?>
<div id="footer">
	<?php $wp_wc_pdfqh->footer(); ?>
</div><!-- #letter-footer -->
<?php endif; ?>
<?php do_action( 'wp_wc_pdfqh_after_document', $wp_wc_pdfqh->export->template_type, $wp_wc_pdfqh->export->order ); ?>
