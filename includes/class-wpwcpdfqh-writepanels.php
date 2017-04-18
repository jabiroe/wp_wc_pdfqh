<?php

/**
 * Writepanel class
 */
if ( !class_exists( 'WooCommerce_PDF_Quote_Handling_Writepanels' ) ) {

	class WooCommerce_PDF_Quote_Handling_Writepanels {
		public $bulk_actions;

		/**
		 * Constructor
		 */
		public function __construct() {
			$this->general_settings = get_option('wp_wc_pdfqh_general_settings');
			$this->template_settings = get_option('wp_wc_pdfqh_template_settings');

			add_action( 'woocommerce_admin_order_actions_end', array( $this, 'add_listing_actions' ) );
			add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_quote_number_column' ), 999 );
			add_action( 'manage_shop_order_posts_custom_column', array( $this, 'quote_number_column_data' ), 2 );
			add_action( 'add_meta_boxes_shop_order', array( $this, 'add_meta_boxes' ) );
			add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'my_account_pdf_link' ), 10, 2 );
			add_action( 'admin_print_scripts', array( $this, 'add_scripts' ) );
			add_action( 'admin_print_styles', array( $this, 'add_styles' ) );
			add_action( 'admin_footer', array( $this, 'bulk_actions' ) );

			add_action( 'save_post', array( $this,'save_quote_number_date' ) );

			add_filter( 'woocommerce_shop_order_search_fields', array( $this, 'search_fields' ) );

			$this->bulk_actions = array(
				'quote'		=> __( 'PDF Quotes Handling', 'wp_wc_pdfqh' ),
			);
		}

		/**
		 * Add the styles
		 */
		public function add_styles() {
			if( $this->is_order_edit_page() ) {
				wp_enqueue_style( 'thickbox' );

				wp_enqueue_style(
					'wp_wc_pdfqh',
					WooCommerce_PDF_Quote_Handling::$plugin_url . 'css/style.css',
					array(),
					WooCommerce_PDF_Quote_Handling::$version
				);

				if ( version_compare( WOOCOMMERCE_VERSION, '2.1' ) >= 0 ) {
					// WC 2.1 or newer (MP6) is used: bigger buttons
					wp_enqueue_style(
						'wp-wc-pdfqh-buttons',
						WooCommerce_PDF_Quote_Handling::$plugin_url . 'css/style-buttons.css',
						array(),
						WooCommerce_PDF_Quote_Handling::$version
					);
				} else {
					// legacy WC 2.0 styles
					wp_enqueue_style(
						'wp-wc-pdfqh-buttons',
						WooCommerce_PDF_Quote_Handling::$plugin_url . 'css/style-buttons-wc20.css',
						array(),
						WooCommerce_PDF_Quote_Handling::$version
					);
				}
			}
		}
		
		/**
		 * Add the scripts
		 */
		public function add_scripts() {
			if( $this->is_order_edit_page() ) {
				wp_enqueue_script(
					'wp_wc_pdfqh',
					WooCommerce_PDF_Quote_Handling::$plugin_url . 'js/script.js',
					array( 'jquery' ),
					WooCommerce_PDF_Quote_Handling::$version
				);
				wp_localize_script(  
					'wp_wc_pdfqh',  
					'wp_wc_pdfqh_ajax',  
					array(
						// 'ajaxurl'		=> add_query_arg( 'action', 'generate_wp_wc_pdfqh', admin_url( 'admin-ajax.php' ) ), // URL to WordPress ajax handling page  
						'ajaxurl'		=> admin_url( 'admin-ajax.php' ), // URL to WordPress ajax handling page  
						'nonce'			=> wp_create_nonce('generate_wp_wc_pdfqh'),
						'bulk_actions'	=> array_keys( apply_filters( 'wp_wc_pdfqh_bulk_actions', $this->bulk_actions ) ),
					)  
				);  
			}
		}	
			
		/**
		 * Is order page
		 */
		public function is_order_edit_page() {
			global $post_type;
			if( $post_type == 'shop_order' ) {
				return true;	
			} else {
				return false;
			}
		}	
			
		/**
		 * Add PDF actions to the orders listing
		 */
		public function add_listing_actions( $order ) {
			// do not show buttons for trashed orders
			if ( $order->status == 'trash' ) {
				return;
			}

			$listing_actions = array(
				'quote'		=> array (
					'url'		=> wp_nonce_url( admin_url( 'admin-ajax.php?action=generate_wp_wc_pdfqh&template_type=quote&order_ids=' . $order->id ), 'generate_wp_wc_pdfqh' ),
					'img'		=> WooCommerce_PDF_Quote_Handling::$plugin_url . 'images/quote.png',
					'alt'		=> __( 'PDF Quote', 'wp_wc_pdfqh' ),
				),
			);

			$listing_actions = apply_filters( 'wp_wc_pdfqh_listing_actions', $listing_actions, $order );			

			foreach ($listing_actions as $action => $data) {
				?>
				<a href="<?php echo $data['url']; ?>" class="button tips wp_wc_pdfqh <?php echo $action; ?>" target="_blank" alt="<?php echo $data['alt']; ?>" data-tip="<?php echo $data['alt']; ?>">
					<img src="<?php echo $data['img']; ?>" alt="<?php echo $data['alt']; ?>" width="16">
				</a>
				<?php
			}
		}
		
		/**
		 * Create additional Shop Order column for Quote Numbers
		 * @param array $columns shop order columns
		 */
		public function add_quote_number_column( $columns ) {
			// Check user setting
			if ( !isset($this->general_settings['quote_number_column'] ) ) {
				return $columns;
			}

			// put the column after the Status column
			$new_columns = array_slice($columns, 0, 2, true) +
				array( 'pdf_quote_number' => __( 'Quote Number', 'wp_wc_pdfqh' ) ) +
				array_slice($columns, 2, count($columns) - 1, true) ;
			return $new_columns;
		}

		/**
		 * Display Quote Number in Shop Order column (if available)
		 * @param  string $column column slug
		 */
		public function quote_number_column_data( $column ) {
			global $post, $the_order, $wp_wc_pdfqh;

			if ( $column == 'pdf_quote_number' ) {
				if ( empty( $the_order ) || $the_order->id != $post->ID ) {
					$order = new WC_Order( $post->ID );
					echo $wp_wc_pdfqh->export->get_quote_number( $order->id );
					do_action( 'wc_pdfqh_quote_number_column_end', $order );
				} else {
					echo $wp_wc_pdfqh->export->get_quote_number( $the_order->id );
					do_action( 'wc_pdfqh_quote_number_column_end', $the_order );
				}
			}
		}

		/**
		 * Display download link on My Account page
		 */
		public function my_account_pdf_link( $actions, $order ) {
			$pdf_url = wp_nonce_url( admin_url( 'admin-ajax.php?action=generate_wp_wc_pdfqh&template_type=quote&order_ids=' . $order->id . '&my-account'), 'generate_wp_wc_pdfqh' );

			// check my account button settings
			if (isset($this->general_settings['my_account_buttons'])) {
				switch ($this->general_settings['my_account_buttons']) {
					case 'available':
						$quote_allowed = get_post_meta($order->id,'_wc_pdfqh_quote_exists',true);
						break;
					case 'always':
						$quote_allowed = true;
						break;
					case 'never':
						$quote_allowed = false;
						break;
					case 'custom':
						if ( isset( $this->general_settings['my_account_restrict'] ) && in_array( $order->status, array_keys( $this->general_settings['my_account_restrict'] ) ) ) {
							$quote_allowed = true;
						} else {
							$quote_allowed = false;							
						}
						break;
				}
			} else {
				// backwards compatibility
				$quote_allowed = get_post_meta($order->id,'_wc_pdfqh_quote_exists',true);
			}

			// Check if quote has been created already or if status allows download (filter your own array of allowed statuses)
			if ( $quote_allowed || in_array($order->status, apply_filters( 'wp_wc_pdfqh_myaccount_allowed_order_statuses', array() ) ) ) {
				$actions['quote'] = array(
					'url'  => $pdf_url,
					'name' => apply_filters( 'wp_wc_pdfqh_myaccount_button_text', __( 'Download quote (PDF)', 'wp_wc_pdfqh' ) )
				);				
			}

			return apply_filters( 'wp_wc_pdfqh_myaccount_actions', $actions, $order );
		}

		/**
		 * Add the meta box on the single order page
		 */
		public function add_meta_boxes() {
			// create PDF buttons
			add_meta_box(
				'wp_wc_pdfqh-box',
				__( 'Create PDF', 'wp_wc_pdfqh' ),
				array( $this, 'sidebar_box_content' ),
				'shop_order',
				'side',
				'default'
			);

			// Quote number & date
			add_meta_box(
				'wp_wc_pdfqh-data-input-box',
				__( 'PDF Quote data', 'wp_wc_pdfqh' ),
				array( $this, 'data_input_box_content' ),
				'shop_order',
				'normal',
				'default'
			);
		}

		/**
		 * Create the meta box content on the single order page
		 */
		public function sidebar_box_content( $post ) {
			global $post_id;

			$meta_actions = array(
				'quote'		=> array (
					'url'		=> wp_nonce_url( admin_url( 'admin-ajax.php?action=generate_wp_wc_pdfqh&template_type=quote&order_ids=' . $post_id ), 'generate_wp_wc_pdfqh' ),
					'alt'		=> esc_attr__( 'PDF Quote', 'wp_wc_pdfqh' ),
					'title'		=> __( 'PDF Quote', 'wp_wc_pdfqh' ),
				),
			);

			$meta_actions = apply_filters( 'wp_wc_pdfqh_meta_box_actions', $meta_actions, $post_id );

			?>
			<ul class="wp_wc_pdfqh-actions">
				<?php
				foreach ($meta_actions as $action => $data) {
					printf('<li><a href="%1$s" class="button" target="_blank" alt="%2$s">%3$s</a></li>', $data['url'], $data['alt'],$data['title']);
				}
				?>
			</ul>
			<?php
		}

		/**
		 * Add metabox for quote number & date
		 */
		public function data_input_box_content ( $post ) {
			$quote_exists = get_post_meta( $post->ID, '_wc_pdfqh_quote_exists', true );
			$quote_number = get_post_meta($post->ID,'_wc_pdfqh_quote_number',true);
			$quote_date = get_post_meta($post->ID,'_wc_pdfqh_quote_date',true);
			
			do_action( 'wp_wc_pdfqh_meta_box_start', $post->ID );

			?>
			<h4><?php _e( 'Quote', 'wp_wc_pdfqh' ) ?></h4>
			<p class="form-field _wc_pdfqh_quote_number_field ">
				<label for="_wc_pdfqh_quote_number"><?php _e( 'Quote Number (unformatted!)', 'wp_wc_pdfqh' ); ?>:</label>
				<?php if (!empty($quote_exists)) : ?>
				<input type="text" class="short" style="" name="_wc_pdfqh_quote_number" id="_wc_pdfqh_quote_number" value="<?php echo $quote_number ?>">
				<?php else : ?>
				<input type="text" class="short" style="" name="_wc_pdfqh_quote_number" id="_wc_pdfqh_quote_number" value="<?php echo $quote_number ?>" disabled="disabled" >
				<?php endif; ?>
			</p>
			<p class="form-field form-field-wide">
				<label for="wc_pdfqh_quote_date"><?php _e( 'Quote Date:', 'wp_wc_pdfqh' ); ?></label>
				<?php if (!empty($quote_exists)) : ?>
				<input type="text" class="date-picker-field" name="wc_pdfqh_quote_date" id="wc_pdfqh_quote_date" maxlength="10" value="<?php echo date_i18n( 'Y-m-d', strtotime( $quote_date ) ); ?>" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])" />@<input type="number" class="hour" placeholder="<?php _e( 'h', 'woocommerce' ) ?>" name="wc_pdfqh_quote_date_hour" id="wc_pdfqh_quote_date_hour" min="0" max="23" size="2" value="<?php echo date_i18n( 'H', strtotime( $quote_date ) ); ?>" pattern="([01]?[0-9]{1}|2[0-3]{1})" />:<input type="number" class="minute" placeholder="<?php _e( 'm', 'woocommerce' ) ?>" name="wc_pdfqh_quote_date_minute" id="wc_pdfqh_quote_date_minute" min="0" max="59" size="2" value="<?php echo date_i18n( 'i', strtotime( $quote_date ) ); ?>" pattern="[0-5]{1}[0-9]{1}" />
				<?php else : ?>
				<input type="text" class="date-picker-field" name="wc_pdfqh_quote_date" id="wc_pdfqh_quote_date" maxlength="10" disabled="disabled" value="" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])" />@<input type="number" class="hour" disabled="disabled" placeholder="<?php _e( 'h', 'woocommerce' ) ?>" name="wc_pdfqh_quote_date_hour" id="wc_pdfqh_quote_date_hour" min="0" max="23" size="2" value="" pattern="([01]?[0-9]{1}|2[0-3]{1})" />:<input type="number" class="minute" placeholder="<?php _e( 'm', 'woocommerce' ) ?>" name="wc_pdfqh_quote_date_minute" id="wc_pdfqh_quote_date_minute" min="0" max="59" size="2" value="" pattern="[0-5]{1}[0-9]{1}" disabled="disabled" />
				<?php endif; ?>
			</p>
			<?php

			do_action( 'wp_wc_pdfqh_meta_box_end', $post->ID );
		}

		/**
		 * Add actions to menu
		 */
		public function bulk_actions() {
			global $post_type;
			$bulk_actions = apply_filters( 'wp_wc_pdfqh_bulk_actions', $this->bulk_actions );

			if ( 'shop_order' == $post_type ) {
				?>
				<script type="text/javascript">
				jQuery(document).ready(function() {
					<?php foreach ($bulk_actions as $action => $title) { ?>
					jQuery('<option>').val('<?php echo $action; ?>').html('<?php echo esc_attr( $title ); ?>').appendTo("select[name='action'], select[name='action2']");
					<?php }	?>
				});
				</script>
				<?php
			}
		}

		/**
		 * Save quote number
		 */
		public function save_quote_number_date($post_id) {
			global $post_type;
			if( $post_type == 'shop_order' ) {
				if ( isset($_POST['_wc_pdfqh_quote_number']) ) {
					update_post_meta( $post_id, '_wc_pdfqh_quote_number', stripslashes( $_POST['_wc_pdfqh_quote_number'] ));
					update_post_meta( $post_id, '_wc_pdfqh_quote_exists', 1 );
				}

				if ( isset($_POST['wc_pdfqh_quote_date']) ) {
					if ( empty($_POST['wc_pdfqh_quote_date']) ) {
						delete_post_meta( $post_id, '_wc_pdfqh_quote_date' );
					} else {
						$quote_date = strtotime( $_POST['wc_pdfqh_quote_date'] . ' ' . (int) $_POST['wc_pdfqh_quote_date_hour'] . ':' . (int) $_POST['wc_pdfqh_quote_date_minute'] . ':00' );
						$quote_date = date_i18n( 'Y-m-d H:i:s', $quote_date );
						update_post_meta( $post_id, '_wc_pdfqh_quote_date', $quote_date );
						update_post_meta( $post_id, '_wc_pdfqh_quote_exists', 1 );
					}
				}

				if (empty($_POST['wc_pdfqh_quote_date']) && isset($_POST['_wc_pdfqh_quote_number'])) {
					$quote_date = date_i18n( 'Y-m-d H:i:s', time() );
					update_post_meta( $post_id, '_wc_pdfqh_quote_date', $quote_date );
				}

				if ( empty($_POST['wc_pdfqh_quote_date']) && empty($_POST['_wc_pdfqh_quote_number'])) {
					delete_post_meta( $post_id, '_wc_pdfqh_quote_exists' );
				}
			}
		}


		/**
		 * Add quote number to order search scope
		 */
		public function search_fields ( $custom_fields ) {
			$custom_fields[] = '_wc_pdfqh_quote_number';
			$custom_fields[] = '_wc_pdfqh_formatted_quote_number';
			return $custom_fields;
		}
	}
}