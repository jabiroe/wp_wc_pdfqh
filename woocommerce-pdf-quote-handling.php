<?php
/**
 * Plugin Name: WooCommerce PDF quote handling
 * Plugin URI: http://www.jabiroe.be/wp_wc_pdfqh
 * Description: Create, print & email PDF quotes for WooCommerce orders.
 * Version: 1.1
 * Author: Jabiroe
 * Author URI: http://www.jabiroe.be
 * License: GPLv2 or later
 * License URI: http://www.opensource.org/licenses/gpl-license.php
 * Text Domain: wp_wc_pdfqh
 */

if ( !class_exists( 'WooCommerce_PDF_Quote_Handling' ) ) {

	class WooCommerce_PDF_Quote_Handling {
	
		public static $plugin_prefix;
		public static $plugin_url;
		public static $plugin_path;
		public static $plugin_basename;
		public static $version;
		
		public $writepanels;
		public $settings;
		public $export;

		/**
		 * Constructor
		 */
		public function __construct() {
			self::$plugin_prefix = 'wp_wc_pdfqh_';
			self::$plugin_basename = plugin_basename(__FILE__);
			self::$plugin_url = plugin_dir_url(self::$plugin_basename);
			self::$plugin_path = trailingslashit(dirname(__FILE__));
			self::$version = '1.0';
			
			// load the localisation & classes
			add_action( 'plugins_loaded', array( $this, 'translations' ) ); // or use init?
			add_action( 'init', array( $this, 'load_classes' ) );

			// run lifecycle methods
			if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
				add_action( 'wp_loaded', array( $this, 'do_install' ) );
			}
		}

		/**
		 * Load the translation / textdomain files
		 * 
		 * Note: the first-loaded translation file overrides any following ones if the same translation is present
		 */
		public function translations() {
			$locale = apply_filters( 'plugin_locale', get_locale(), 'wp_wc_pdfqh' );
			$dir    = trailingslashit( WP_LANG_DIR );

			/**
			 * Frontend/global Locale. Looks in:
			 *
			 * 		- WP_LANG_DIR/woocommerce-pdf-quote-handling/wp_wc_pdfqh-LOCALE.mo
			 * 	 	- WP_LANG_DIR/plugins/wp_wc_pdfqh-LOCALE.mo
			 * 	 	- woocommerce-pdf-quote-handling/languages/wp_wc_pdfqh-LOCALE.mo (which if not found falls back to:)
			 * 	 	- WP_LANG_DIR/plugins/wp_wc_pdfqh-LOCALE.mo
			 */
			load_textdomain( 'wp_wc_pdfqh', $dir . 'woocommerce-pdf-quote-handling/wp_wc_pdfqh-' . $locale . '.mo' );
			load_textdomain( 'wp_wc_pdfqh', $dir . 'plugins/wp_wc_pdfqh-' . $locale . '.mo' );
			load_plugin_textdomain( 'wp_wc_pdfqh', false, dirname( self::$plugin_basename ) . '/languages' );
		}

		/**
		 * Load the main plugin classes and functions
		 */
		public function includes() {
			include_once( 'includes/class-wpwcpdfqh-settings.php' );
			include_once( 'includes/class-wpwcpdfqh-writepanels.php' );
			include_once( 'includes/class-wpwcpdfqh-export.php' );
		}
		

		/**
		 * Instantiate classes when woocommerce is activated
		 */
		public function load_classes() {
			if ( $this->is_woocommerce_activated() ) {
				$this->includes();
				$this->settings = new WooCommerce_PDF_Quote_Handling_Settings();
				$this->writepanels = new WooCommerce_PDF_Quote_Handling_Writepanels();
				$this->export = new WooCommerce_PDF_Quote_Handling_Export();
			} else {
				// display notice instead
				add_action( 'admin_notices', array ( $this, 'need_woocommerce' ) );
			}

		}

		/**
		 * Check if woocommerce is activated
		 */
		public function is_woocommerce_activated() {
			$blog_plugins = get_option( 'active_plugins', array() );
			$site_plugins = is_multisite() ? (array) maybe_unserialize( get_site_option('active_sitewide_plugins' ) ) : array();

			if ( in_array( 'woocommerce/woocommerce.php', $blog_plugins ) || isset( $site_plugins['woocommerce/woocommerce.php'] ) ) {
				return true;
			} else {
				return false;
			}
		}
		
		/**
		 * WooCommerce not active notice.
		 *
		 * @return string Fallack notice.
		 */
		 
		public function need_woocommerce() {
			$error = sprintf( __( 'WooCommerce PDF Quote Handling requires %sWooCommerce%s to be installed & activated!' , 'wp_wc_pdfqh' ), '<a href="http://wordpress.org/extend/plugins/woocommerce/">', '</a>' );
			
			$message = '<div class="error"><p>' . $error . '</p></div>';
		
			echo $message;
		}

		/** Lifecycle methods *******************************************************
		 * Because register_activation_hook only runs when the plugin is manually
		 * activated by the user, we're checking the current version against the
		 * version stored in the database
		****************************************************************************/

		/**
		 * Handles version checking
		 */
		public function do_install() {
			// only install when woocommerce is active
			if ( !$this->is_woocommerce_activated() ) {
				return;
			}

			$version_setting = 'wp_wc_pdfqh_version';
			$installed_version = get_option( $version_setting );

			// installed version lower than plugin version?
			if ( version_compare( $installed_version, self::$version, '<' ) ) {

				if ( ! $installed_version ) {
					$this->install();
				} else {
					$this->upgrade( $installed_version );
				}

				// new version number
				update_option( $version_setting, self::$version );
			}
		}


		/**
		 * Plugin install method. Perform any installation tasks here
		 */
		protected function install() {
			// Create temp folders
			$tmp_base = $this->export->get_tmp_base();

			// check if tmp folder exists => if not, initialize 
			if ( !@is_dir( $tmp_base ) ) {
				$this->export->init_tmp( $tmp_base );
			}

		}


		/**
		 * Plugin upgrade method.  Perform any required upgrades here
		 *
		 * @param string $installed_version the currently installed version
		 */
		protected function upgrade( $installed_version ) {

			// sync fonts on every upgrade!
			$debug_settings = get_option( 'wp_wc_pdfqh_debug_settings' ); // get temp setting

			// do not copy if old_tmp function active! (double check for slow databases)
			if ( !( isset($debug_settings['old_tmp']) ) ) {
				$tmp_base = $this->export->get_tmp_base();

				// check if tmp folder exists => if not, initialize 
				if ( !@is_dir( $tmp_base ) ) {
					$this->export->init_tmp( $tmp_base );
				}

				$font_path = $tmp_base . 'fonts/';
				$this->export->copy_fonts( $font_path );
			}
		}		

		/***********************************************************************/
		/********************** GENERAL TEMPLATE FUNCTIONS *********************/
		/***********************************************************************/

		/**
		 * Get template name from slug
		 */
		public function get_template_name ( $template_type ) {
			switch ( $template_type ) {
				case 'quote':
					$template_name = apply_filters( 'wp_wc_pdfqh_quote_title', __( 'Quote', 'wp_wc_pdfqh' ) );
					break;
				default:
					// try to 'unslug' the name
					$template_name = ucwords( str_replace( array( '_', '-' ), ' ', $template_type ) );
					break;
			}

			return apply_filters( 'wp_wc_pdfqh_template_name', $template_name, $template_type );
		}

		/**
		 * Output template styles
		 */
		public function template_styles() {
			$css = apply_filters( 'wp_wc_pdfqh_template_styles_file', $this->export->template_path. '/' .'style.css' );

			ob_start();
			if (file_exists($css)) {
				include($css);
			}
			$html = ob_get_clean();			
			$html = apply_filters( 'wp_wc_pdfqh_template_styles', $html );
			
			echo $html;
		}				

		/**
		 * Return logo id
		 */
		public function get_header_logo_id() {
			if (isset($this->settings->template_settings['header_logo'])) {
				return apply_filters( 'wp_wc_pdfqh_header_logo_id', $this->settings->template_settings['header_logo'] );
			}
		}
	
		/**
		 * Show logo html
		 */
		public function header_logo() {
			if ($this->get_header_logo_id()) {
				$attachment_id = $this->get_header_logo_id();
				$company = isset($this->settings->template_settings['shop_name'])? $this->settings->template_settings['shop_name'] : '';
				if( $attachment_id ) {
					$attachment = wp_get_attachment_image_src( $attachment_id, 'full', false );
					
					$attachment_src = $attachment[0];
					$attachment_width = $attachment[1];
					$attachment_height = $attachment[2];

					$attachment_path = get_attached_file( $attachment_id );

					if ( apply_filters('wp_wc_pdfqh_use_path', true) && file_exists($attachment_path) ) {
						$src = $attachment_path;
					} else {
						$src = $attachment_src;
					}
					
					printf('<img src="%1$s" width="%2$d" height="%3$d" alt="%4$s" />', $src, $attachment_width, $attachment_height, esc_attr( $company ) );
				}
			}
		}
	
		/**
		 * Return/Show custom company name or default to blog name
		 */
		public function get_shop_name() {
			if (!empty($this->settings->template_settings['shop_name'])) {
				$name = trim( $this->settings->template_settings['shop_name'] );
				return apply_filters( 'wp_wc_pdfqh_shop_name', wptexturize( $name ) );
			} else {
				return apply_filters( 'wp_wc_pdfqh_shop_name', get_bloginfo( 'name' ) );
			}
		}
		public function shop_name() {
			echo $this->get_shop_name();
		}
		
		/**
		 * Return/Show shop/company address if provided
		 */
		public function get_shop_address() {
			$shop_address = apply_filters( 'wp_wc_pdfqh_shop_address', wpautop( wptexturize( $this->settings->template_settings['shop_address'] ) ) );
			if (!empty($shop_address)) {
				return $shop_address;
			} else {
				return false;
			}
		}
		public function shop_address() {
			echo $this->get_shop_address();
		}

		/**
		 * Check if billing address and shipping address are equal
		 */
		public function ships_to_different_address() {
			// always prefer parent address for refunds
			if ( get_post_type( $this->export->order->id ) == 'shop_order_refund' && $parent_order_id = wp_get_post_parent_id( $this->export->order->id ) ) {
				// temporarily switch order to make all filters / order calls work correctly
				$order_meta = get_post_meta( $parent_order_id );
			} else {
				$order_meta = get_post_meta( $this->export->order->id );
			}

			$address_comparison_fields = apply_filters( 'wp_wc_pdfqh_address_comparison_fields', array(
				'first_name',
				'last_name',
				'company',
				'address_1',
				'address_2',
				'city',
				'state',
				'postcode',
				'country'
			) );
			
			foreach ($address_comparison_fields as $address_field) {
				$billing_field = isset( $order_meta['_billing_'.$address_field] ) ? $order_meta['_billing_'.$address_field] : '';
				$shipping_field = isset( $order_meta['_shipping_'.$address_field] ) ? $order_meta['_shipping_'.$address_field] : '';
				if ( $shipping_field != $billing_field ) {
					// this address field is different -> ships to different address!
					return true;
				}
			}

			//if we got here, it means the addresses are equal -> doesn't ship to different address!
			return apply_filters( 'wp_wc_pdfqh_ships_to_different_address', false, $order_meta );
		}
		
		/**
		 * Return/Show billing address
		 */
		public function get_billing_address() {
			// always prefer parent billing address for refunds
			if ( get_post_type( $this->export->order->id ) == 'shop_order_refund' && $parent_order_id = wp_get_post_parent_id( $this->export->order->id ) ) {
				// temporarily switch order to make all filters / order calls work correctly
				$current_order = $this->export->order;
				$this->export->order = new WC_Order( $parent_order_id );
				$address = apply_filters( 'wp_wc_pdfqh_billing_address', $this->export->order->get_formatted_billing_address() );
				// switch back & unset
				$this->export->order = $current_order;
				unset($current_order);
			} elseif ( $address = $this->export->order->get_formatted_billing_address() ) {
				// regular shop_order
				$address = apply_filters( 'wp_wc_pdfqh_billing_address', $address );
			} else {
				// no address
				$address = apply_filters( 'wp_wc_pdfqh_billing_address', __('N/A', 'wp_wc_pdfqh') );
			}

			return $address;
		}
		public function billing_address() {
			echo $this->get_billing_address();
		}

		/**
		 * Return/Show billing email
		 */
		public function get_billing_email() {
			$billing_email = $this->export->order->billing_email;

			if ( !$billing_email && $parent_order_id = wp_get_post_parent_id( $this->export->order->id ) ) {
				// try parent
				$billing_email = get_post_meta( $parent_order_id, '_billing_email', true );
			}

			return apply_filters( 'wpo_wcpdf_billing_email', $billing_email );
		}
		public function billing_email() {
			echo $this->get_billing_email();
		}
		
		/**
		 * Return/Show billing phone
		 */
		public function get_billing_phone() {
			$billing_phone = $this->export->order->billing_phone;

			if ( !$billing_phone && $parent_order_id = wp_get_post_parent_id( $this->export->order->id ) ) {
				// try parent
				$billing_phone = get_post_meta( $parent_order_id, '_billing_phone', true );
			}

			return apply_filters( 'wp_wc_pdfqh_billing_phone', $billing_phone );
		}
		public function billing_phone() {
			echo $this->get_billing_phone();
		}
		
		/**
		 * Return/Show shipping address
		 */
		public function get_shipping_address() {
			// always prefer parent shipping address for refunds
			if ( get_post_type( $this->export->order->id ) == 'shop_order_refund' && $parent_order_id = wp_get_post_parent_id( $this->export->order->id ) ) {
				// temporarily switch order to make all filters / order calls work correctly
				$current_order = $this->export->order;
				$this->export->order = new WC_Order( $parent_order_id );
				$address = apply_filters( 'wp_wc_pdfqh_shipping_address', $this->export->order->get_formatted_shipping_address() );
				// switch back & unset
				$this->export->order = $current_order;
				unset($current_order);
			} elseif ( $address = $this->export->order->get_formatted_shipping_address() ) {
				// regular shop_order
				$address = apply_filters( 'wp_wc_pdfqh_shipping_address', $address );
			} else {
				// no address
				$address = apply_filters( 'wp_wc_pdfqh_shipping_address', __('N/A', 'wp_wc_pdfqh') );
			}

			return $address;
		}
		public function shipping_address() {
			echo $this->get_shipping_address();
		}

		/**
		 * Return/Show a custom field
		 */		
		public function get_custom_field( $field_name ) {
			$custom_field = get_post_meta($this->export->order->id,$field_name,true);

			if ( !$custom_field && $parent_order_id = wp_get_post_parent_id( $this->export->order->id ) ) {
				// try parent
				$custom_field = get_post_meta( $parent_order_id, $field_name, true );
			}

			return apply_filters( 'wp_wc_pdfqh_billing_custom_field', $custom_field );
		}
		public function custom_field( $field_name, $field_label = '', $display_empty = false ) {
			$custom_field = $this->get_custom_field( $field_name );
			if (!empty($field_label)){
				// add a a trailing space to the label
				$field_label .= ' ';
			}

			if (!empty($custom_field) || $display_empty) {
				echo $field_label . nl2br ($custom_field);
			}
		}

		/**
		 * Return/Show order notes
		 */		
		public function get_order_notes( $filter = 'customer' ) {
			if ( get_post_type( $this->export->order->id ) == 'shop_order_refund' && $parent_order_id = wp_get_post_parent_id( $this->export->order->id ) ) {
				$post_id = $parent_order_id;
			} else {
				$post_id = $this->export->order->id;
			}

			$args = array(
				'post_id' 	=> $post_id,
				'approve' 	=> 'approve',
				'type' 		=> 'order_note'
			);

			remove_filter( 'comments_clauses', array( 'WC_Comments', 'exclude_order_comments' ), 10, 1 );

			$notes = get_comments( $args );

			add_filter( 'comments_clauses', array( 'WC_Comments', 'exclude_order_comments' ), 10, 1 );

			if ( $notes ) {
				foreach( $notes as $key => $note ) {
					if ( $filter == 'customer' && !get_comment_meta( $note->comment_ID, 'is_customer_note', true ) ) {
						unset($notes[$key]);
					}
					if ( $filter == 'private' && get_comment_meta( $note->comment_ID, 'is_customer_note', true ) ) {
						unset($notes[$key]);
					}					
				}
				return $notes;
			}
		}
		public function order_notes( $filter = 'customer' ) {
			$notes = $this->get_order_notes( $filter );
			if ( $notes ) {
				foreach( $notes as $note ) {
					?>
					<div class="note_content">
						<?php echo wpautop( wptexturize( wp_kses_post( $note->comment_content ) ) ); ?>
					</div>
					<?php
				}
			}
		}

		/**
		 * Return/Show the current date
		 */
		public function get_current_date() {
			return apply_filters( 'wp_wc_pdfqh_date', date_i18n( get_option( 'date_format' ) ) );
		}
		public function current_date() {
			echo $this->get_current_date();
		}

		/**
		 * Return/Show payment method  
		 */
		public function get_payment_method() {
			$payment_method_label = __( 'Payment method', 'wp_wc_pdfqh' );

			$payment_method = __( $this->export->order->payment_method_title, 'woocommerce' );
			if ( !$payment_method && $parent_order_id = wp_get_post_parent_id( $this->export->order->id ) ) {
				// try parent
				$payment_method = get_post_meta( $parent_order_id, '_payment_method_title', true );
				$payment_method = __( $payment_method, 'woocommerce' );
			}

			return apply_filters( 'wp_wc_pdfqh_payment_method', $payment_method );
		}
		public function payment_method() {
			echo $this->get_payment_method();
		}

		/**
		 * Return/Show shipping method  
		 */
		public function get_shipping_method() {
			$shipping_method_label = __( 'Shipping method', 'wp_wc_pdfqh' );
			return apply_filters( 'wp_wc_pdfqh_shipping_method', __( $this->export->order->get_shipping_method(), 'woocommerce' ) );
		}
		public function shipping_method() {
			echo $this->get_shipping_method();
		}

		/**
		 * Return/Show order number
		 */
		public function get_order_number() {
			// try parent first
			if ( get_post_type( $this->export->order->id ) == 'shop_order_refund' && $parent_order_id = wp_get_post_parent_id( $this->export->order->id ) ) {
				$parent_order = new WC_Order( $parent_order_id );
				$order_number = $parent_order->get_order_number();
			} else {
				$order_number = $this->export->order->get_order_number();
			}

			// Trim the hash to have a clean number but still 
			// support any filters that were applied before.
			$order_number = ltrim($order_number, '#');
			return apply_filters( 'wp_wc_pdfqh_order_number', $order_number);
		}
		public function order_number() {
			echo $this->get_order_number();
		}

		/**
		 * Return/Show quote number 
		 */
		public function get_quote_number() {
			// try parent first
			if ( get_post_type( $this->export->order->id ) == 'shop_order_refund' && $parent_order_id = wp_get_post_parent_id( $this->export->order->id ) ) {
				$quote_number = $this->export->get_quote_number( $parent_order_id );
			} else {
				$quote_number = $this->export->get_quote_number( $this->export->order->id );
			}

			return $quote_number;
		}
		public function quote_number() {
			echo $this->get_quote_number();
		}

		/**
		 * Return/Show the order date
		 */
		public function get_order_date() {
			if ( get_post_type( $this->export->order->id ) == 'shop_order_refund' && $parent_order_id = wp_get_post_parent_id( $this->export->order->id ) ) {
				$parent_order = new WC_Order( $parent_order_id );
				$order_date = $parent_order->order_date;
			} else {
				$order_date = $this->export->order->order_date;
			}

			$date = date_i18n( get_option( 'date_format' ), strtotime( $order_date ) );
			return apply_filters( 'wp_wc_pdfqh_order_date', $date, $order_date );
		}
		public function order_date() {
			echo $this->get_order_date();
		}

		/**
		 * Return/Show the quote date
		 */
		public function get_quote_date() {
			$quote_date = get_post_meta($this->export->order->id,'_wc_pdfqh_quote_date',true);

			// add quote date if it doesn't exist
			if ( empty($quote_date) || !isset($quote_date) ) {
				$quote_date = current_time('mysql');
				update_post_meta( $this->export->order->id, '_wc_pdfqh_quote_date', $quote_date );
			}

			$formatted_quote_date = date_i18n( get_option( 'date_format' ), strtotime( $quote_date ) );

			return apply_filters( 'wp_wc_pdfqh_quote_date', $formatted_quote_date, $quote_date );
		}
		public function quote_date() {
			echo $this->get_quote_date();
		}

		/**
		 * Return the order items
		 */
		public function get_order_items() {
			return apply_filters( 'wp_wc_pdfqh_order_items', $this->export->get_order_items() );
		}

		/**
		 * Return/show product attribute
		 */
		public function get_product_attribute( $attribute_name, $product ) {
			// first, check the text attributes
			$attributes = $product->get_attributes();
			$attribute_key = @wc_attribute_taxonomy_name( $attribute_name );
			if (array_key_exists( sanitize_title( $attribute_name ), $attributes) ) {
				$attribute = $product->get_attribute ( $attribute_name );
				return $attribute;
			} elseif (array_key_exists( sanitize_title( $attribute_key ), $attributes) ) {
				$attribute = $product->get_attribute ( $attribute_key );
				return $attribute;
			}

			// not a text attribute, try attribute taxonomy
			$attribute_key = @wc_attribute_taxonomy_name( $attribute_name );
			$product_terms = @wc_get_product_terms( $product->id, $attribute_key, array( 'fields' => 'names' ) );
			// check if not empty, then display
			if ( !empty($product_terms) ) {
				$attribute = array_shift( $product_terms );
				return $attribute;
			} else {
				// no attribute under this name
				return false;
			}
		}
		public function product_attribute( $attribute_name, $product ) {
			echo $this->get_product_attribute( $attribute_name, $product );
		}

	
		/**
		 * Return the order totals listing
		 */
		public function get_woocommerce_totals() {
			// get totals and remove the semicolon
			$totals = apply_filters( 'wp_wc_pdfqh_raw_order_totals', $this->export->order->get_order_item_totals(), $this->export->order );
			
			// remove the colon for every label
			foreach ( $totals as $key => $total ) {
				$label = $total['label'];
				$colon = strrpos( $label, ':' );
				if( $colon !== false ) {
					$label = substr_replace( $label, '', $colon, 1 );
				}		
				$totals[$key]['label'] = $label;
			}

			// WC2.4 fix order_total for refunded orders
			if ( version_compare( WOOCOMMERCE_VERSION, '2.4', '>=' ) && isset($totals['order_total']) ) {
				$tax_display = $this->export->order->tax_display_cart;
				$totals['order_total']['value'] = wc_price( $this->export->order->get_total(), array( 'currency' => $this->export->order->get_order_currency() ) );
				$order_total    = $this->export->order->get_total();
				$tax_string     = '';

				// Tax for inclusive prices
				if ( wc_tax_enabled() && 'incl' == $tax_display ) {
					$tax_string_array = array();

					if ( 'itemized' == get_option( 'woocommerce_tax_total_display' ) ) {
						foreach ( $this->export->order->get_tax_totals() as $code => $tax ) {
							$tax_amount         = $tax->formatted_amount;
							$tax_string_array[] = sprintf( '%s %s', $tax_amount, $tax->label );
						}
					} else {
						$tax_string_array[] = sprintf( '%s %s', wc_price( $this->export->order->get_total_tax() - $this->export->order->get_total_tax_refunded(), array( 'currency' => $this->export->order->get_order_currency() ) ), WC()->countries->tax_or_vat() );
					}
					if ( ! empty( $tax_string_array ) ) {
						if ( version_compare( WOOCOMMERCE_VERSION, '2.6', '>=' ) ) {
							$tax_string = ' ' . sprintf( __( '(includes %s)', 'woocommerce' ), implode( ', ', $tax_string_array ) );
						} else {
							// use old capitalized string
							$tax_string = ' ' . sprintf( __( '(Includes %s)', 'woocommerce' ), implode( ', ', $tax_string_array ) );
						}
					}
				}

				$totals['order_total']['value'] .= $tax_string;			
			}

			// remove refund lines (shouldn't be in invoice)
			foreach ( $totals as $key => $total ) {
				if ( strpos($key, 'refund_') !== false ) {
					unset( $totals[$key] );
				}
			}
	
			return apply_filters( 'wp_wc_pdfqh_woocommerce_totals', $totals, $this->export->order );
		}
		
		/**
		 * Return/show the order subtotal
		 */
		public function get_order_subtotal( $tax = 'excl', $discount = 'incl' ) { // set $tax to 'incl' to include tax, same for $discount
			//$compound = ($discount == 'incl')?true:false;
			
			$subtotal = $this->export->order->get_subtotal_to_display( false, $tax );
			
			$subtotal = ($pos = strpos($subtotal, ' <small')) ? substr($subtotal, 0, $pos) : $subtotal; //removing the 'excluding tax' text			
			
			$subtotal = array (
				'label'	=> __('Subtotal', 'wp_wc_pdfqh'),
				'value'	=> $subtotal, 
			);
			
			return apply_filters( 'wp_wc_pdfqh_order_subtotal', $subtotal, $tax, $discount );
		}
		public function order_subtotal( $tax = 'excl', $discount = 'incl' ) {
			$subtotal = $this->get_order_subtotal( $tax, $discount );
			echo $subtotal['value'];
		}
	
		/**
		 * Return/show the order shipping costs
		 */
		public function get_order_shipping( $tax = 'excl' ) { // set $tax to 'incl' to include tax
			if ($tax == 'excl' ) {
				$shipping_costs = $this->export->wc_price( $this->export->order->order_shipping );
			} else {
				$shipping_costs = $this->export->wc_price( $this->export->order->order_shipping + $this->export->order->order_shipping_tax );
			}

			$shipping = array (
				'label'	=> __('Shipping', 'wp_wc_pdfqh'),
				'value'	=> $shipping_costs,
				'tax'	=> $this->export->wc_price( $this->export->order->order_shipping_tax ),
			);
			return apply_filters( 'wp_wc_pdfqh_order_shipping', $shipping, $tax );
		}
		public function order_shipping( $tax = 'excl' ) {
			$shipping = $this->get_order_shipping( $tax );
			echo $shipping['value'];
		}

		/**
		 * Return/show the total discount
		 */
		public function get_order_discount( $type = 'total', $tax = 'incl' ) {
			if ( $tax == 'incl' ) {
				switch ($type) {
					case 'cart':
						// Cart Discount - pre-tax discounts. (deprecated in WC2.3)
						$discount_value = $this->export->order->get_cart_discount();
						break;
					case 'order':
						// Order Discount - post-tax discounts. (deprecated in WC2.3)
						$discount_value = $this->export->order->get_order_discount();
						break;
					case 'total':
						// Total Discount
						if ( version_compare( WOOCOMMERCE_VERSION, '2.3' ) >= 0 ) {
							$discount_value = $this->export->order->get_total_discount( false ); // $ex_tax = false
						} else {
							// WC2.2 and older: recalculate to include tax
							$discount_value = 0;
							$items = $this->export->order->get_items();;
							if( sizeof( $items ) > 0 ) {
								foreach( $items as $item ) {
									$discount_value += ($item['line_subtotal'] + $item['line_subtotal_tax']) - ($item['line_total'] + $item['line_tax']);
								}
							}
						}

						break;
					default:
						// Total Discount - Cart & Order Discounts combined
						$discount_value = $this->export->order->get_total_discount();
						break;
				}
			} else { // calculate discount excluding tax
				if ( version_compare( WOOCOMMERCE_VERSION, '2.3' ) >= 0 ) {
					$discount_value = $this->export->order->get_total_discount( true ); // $ex_tax = true
				} else {
					// WC2.2 and older: recalculate to exclude tax
					$discount_value = 0;

					$items = $this->export->order->get_items();;
					if( sizeof( $items ) > 0 ) {
						foreach( $items as $item ) {
							$discount_value += ($item['line_subtotal'] - $item['line_total']);
						}
					}
				}
			}

			$discount = array (
				'label'		=> __('Discount', 'wp_wc_pdfqh'),
				'value'		=> $this->export->wc_price($discount_value),
				'raw_value'	=> $discount_value,
			);

			if ( round( $discount_value, 3 ) != 0 ) {
				return apply_filters( 'wp_wc_pdfqh_order_discount', $discount, $type, $tax );
			}
		}
		public function order_discount( $type = 'total', $tax = 'incl' ) {
			$discount = $this->get_order_discount( $type, $tax );
			echo $discount['value'];
		}

		/**
		 * Return the order fees
		 */
		public function get_order_fees( $tax = 'excl' ) {
			if ( $wcfees = $this->export->order->get_fees() ) {
				foreach( $wcfees as $id => $fee ) {
					if ($tax == 'excl' ) {
						$fee_price = $this->export->wc_price( $fee['line_total'] );
					} else {
						$fee_price = $this->export->wc_price( $fee['line_total'] + $fee['line_tax'] );
					}

					$fees[ $id ] = array(
						'label' 		=> $fee['name'],
						'value'			=> $fee_price,
						'line_total'	=> $this->export->wc_price($fee['line_total']),
						'line_tax'		=> $this->export->wc_price($fee['line_tax'])
					);
				}
				return $fees;
			}
		}
		
		/**
		 * Return the order taxes
		 */
		public function get_order_taxes() {
			$tax_label = __( 'VAT', 'wp_wc_pdfqh' ); // register alternate label translation
			$tax_label = __( 'Tax rate', 'wp_wc_pdfqh' );
			$tax_rate_ids = $this->export->get_tax_rate_ids();
			if ($this->export->order->get_taxes()) {
				foreach ( $this->export->order->get_taxes() as $key => $tax ) {
					$taxes[ $key ] = array(
						'label'					=> isset( $tax[ 'label' ] ) ? $tax[ 'label' ] : $tax[ 'name' ],
						'value'					=> $this->export->wc_price( ( $tax[ 'tax_amount' ] + $tax[ 'shipping_tax_amount' ] ) ),
						'rate_id'				=> $tax['rate_id'],
						'tax_amount'			=> $tax['tax_amount'],
						'shipping_tax_amount'	=> $tax['shipping_tax_amount'],
						'rate'					=> isset( $tax_rate_ids[ $tax['rate_id'] ] ) ? ( (float) $tax_rate_ids[$tax['rate_id']]['tax_rate'] ) . ' %': '',
					);
				}
				
				return apply_filters( 'wp_wc_pdfqh_order_taxes', $taxes );
			}
		}

		/**
		 * Return/show the order grand total
		 */
		public function get_order_grand_total( $tax = 'incl' ) {
			if ( version_compare( WOOCOMMERCE_VERSION, '2.1' ) >= 0 ) {
				// WC 2.1 or newer is used
				$total_unformatted = $this->export->order->get_total();
			} else {
				// Backwards compatibility
				$total_unformatted = $this->export->order->get_order_total();
			}

			if ($tax == 'excl' ) {
				$total_tax = 0;
				foreach ( $this->export->order->get_taxes() as $tax ) {
					$total_tax += ( $tax[ 'tax_amount' ] + $tax[ 'shipping_tax_amount' ] );
				}

				$total = $this->export->wc_price( ( $total_unformatted - $total_tax ) );
				$label = __( 'Total ex. VAT', 'wp_wc_pdfqh' );
			} else {
				$total = $this->export->wc_price( ( $total_unformatted ) );
				$label = __( 'Total', 'wp_wc_pdfqh' );
			}
			
			$grand_total = array(
				'label' => $label,
				'value'	=> $total,
			);			

			return apply_filters( 'wp_wc_pdfqh_order_grand_total', $grand_total, $tax );
		}
		public function order_grand_total( $tax = 'incl' ) {
			$grand_total = $this->get_order_grand_total( $tax );
			echo $grand_total['value'];
		}


		/**
		 * Return/Show shipping notes
		 */
		public function get_shipping_notes() {
			$shipping_notes = wpautop( wptexturize( $this->export->order->customer_note ) );
			return apply_filters( 'wp_wc_pdfqh_shipping_notes', $shipping_notes );
		}
		public function shipping_notes() {
			echo $this->get_shipping_notes();
		}
		
	
		/**
		 * Return/Show shop/company footer imprint, copyright etc.
		 */
		public function get_footer() {
			if (isset($this->settings->template_settings['footer'])) {
				$footer = wpautop( wptexturize( $this->settings->template_settings[ 'footer' ] ) );
				return apply_filters( 'wp_wc_pdfqh_footer', $footer );
			}
		}
		public function footer() {
			echo $this->get_footer();
		}

		/**
		 * Return/Show Extra field 1
		 */
		public function get_extra_1() {
			if (isset($this->settings->template_settings['extra_1'])) {
				$extra_1 = nl2br( wptexturize( $this->settings->template_settings[ 'extra_1' ] ) );
				return apply_filters( 'wp_wc_pdfqh_extra_1', $extra_1 );
			}
		}
		public function extra_1() {
			echo $this->get_extra_1();
		}

		/**
		 * Return/Show Extra field 2
		 */
		public function get_extra_2() {
			if (isset($this->settings->template_settings['extra_2'])) {
				$extra_2 = nl2br( wptexturize( $this->settings->template_settings[ 'extra_2' ] ) );
				return apply_filters( 'wp_wc_pdfqh_extra_2', $extra_2 );
			}
		}
		public function extra_2() {
			echo $this->get_extra_2();
		}

				/**
		 * Return/Show Extra field 3
		 */
		public function get_extra_3() {
			if (isset($this->settings->template_settings['extra_3'])) {
				$extra_3 = nl2br( wptexturize( $this->settings->template_settings[ 'extra_3' ] ) );
				return apply_filters( 'wp_wc_pdfqh_extra_3', $extra_3 );
			}
		}
		public function extra_3() {
			echo $this->get_extra_3();
		}				
	}
}

// Load main plugin class
$wp_wc_pdfqh = new WooCommerce_PDF_Quote_Handling();
