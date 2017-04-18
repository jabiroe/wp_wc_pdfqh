<?php

/**
 * Settings class
 */
if ( ! class_exists( 'WooCommerce_PDF_Quote_Handling_Settings' ) ) {

	class WooCommerce_PDF_Quote_Handling_Settings {
	
		public $options_page_hook;
		public $general_settings;
		public $template_settings;

		public function __construct() {
			add_action( 'admin_menu', array( &$this, 'menu' ) ); // Add menu.
			add_action( 'admin_init', array( &$this, 'init_settings' ) ); // Registers settings
			add_filter( 'option_page_capability_wp_wc_pdfqh_template_settings', array( &$this, 'settings_capabilities' ) );
			add_filter( 'option_page_capability_wp_wc_pdfqh_general_settings', array( &$this, 'settings_capabilities' ) );
			add_action( 'admin_enqueue_scripts', array( &$this, 'load_scripts_styles' ) ); // Load scripts
			
			// Add links to WordPress plugins page
			add_filter( 'plugin_action_links_'.WooCommerce_PDF_Quote_Handling::$plugin_basename, array( &$this, 'wp_wc_pdfqh_add_settings_link' ) );
			add_filter( 'plugin_row_meta', array( $this, 'add_support_links' ), 10, 2 );
			
			$this->general_settings = get_option('wp_wc_pdfqh_general_settings');
			$this->template_settings = get_option('wp_wc_pdfqh_template_settings');

			// WooCommerce Order Status & Actions Manager emails compatibility
			add_filter( 'wp_wc_pdfqh_wc_emails', array( $this, 'wc_order_status_actions_emails' ), 10, 1 );
		}
	
		public function menu() {
			$parent_slug = 'woocommerce';
			
			$this->options_page_hook = add_submenu_page(
				$parent_slug,
				__( 'PDF Quote handling', 'wp_wc_pdfqh' ),
				__( 'PDF Quote handling', 'wp_wc_pdfqh' ),
				'manage_woocommerce',
				'wp_wc_pdfqh_options_page',
				array( $this, 'settings_page' )
			);
		}

		/**
		 * Set capability for settings page
		 */
		public function settings_capabilities() {
			return 'manage_woocommerce';
		}		
		
		/**
		 * Styles for settings page
		 */
		public function load_scripts_styles ( $hook ) {
			if( $hook != $this->options_page_hook ) 
				return;
			
			wp_enqueue_script(
				'wc_pdfqh-upload-js',
				plugins_url( 'js/media-upload.js' , dirname(__FILE__) ),
				array( 'jquery' ),
				WooCommerce_PDF_Quote_Handling::$version
			);

			wp_enqueue_style(
				'wp_wc_pdfqh',
				WooCommerce_PDF_Quote_Handling::$plugin_url . 'css/style.css',
				array(),
				WooCommerce_PDF_Quote_Handling::$version
			);
			wp_enqueue_media();
		}
	
		/**
		 * Add settings link to plugins page
		 */
		public function wp_wc_pdfqh_add_settings_link( $links ) {
			$settings_link = '<a href="admin.php?page=wp_wc_pdfqh_options_page">'. __( 'Settings', 'woocommerce' ) . '</a>';
			array_push( $links, $settings_link );
			return $links;
		}
		
		/**
		 * Add various support links to plugin page
		 * after meta (version, authors, site)
		 */
		public function add_support_links( $links, $file ) {
			if ( !current_user_can( 'install_plugins' ) ) {
				return $links;
			}
		
			if ( $file == WooCommerce_PDF_Quote_Handling::$plugin_basename ) {
				// $links[] = '<a href="..." target="_blank" title="' . __( '...', 'wp_wc_pdfqh' ) . '">' . __( '...', 'wp_wc_pdfqh' ) . '</a>';
			}
			return $links;
		}
	
		public function settings_page() {
			$settings_tabs = apply_filters( 'wp_wc_pdfqh_settings_tabs', array (
					'general'	=> __('General','wp_wc_pdfqh'),
					'template'	=> __('Template','wp_wc_pdfqh'),
				)
			);

			// add status tab last in row
			$settings_tabs['debug'] = __('Status','wp_wc_pdfqh');

			$active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'general';

			?>
				<script type="text/javascript">
					jQuery( function( $ ) {
						$("#footer-thankyou").html("If you like <strong>WooCommerce PDF Quote handling</strong> please leave us a <a href='https://wordpress.org/support/view/plugin-reviews/woocommerce-pdf-quote-handling?rate=5#postform'>★★★★★</a> rating. A huge thank you in advance!");
					});
				</script>
				<div class="wrap">
					<div class="icon32" id="icon-options-general"><br /></div>
					<h2><?php _e( 'WooCommerce PDF Quote Handling', 'wp_wc_pdfqh' ); ?></h2>
					<h2 class="nav-tab-wrapper">
					<?php
					foreach ($settings_tabs as $tab_slug => $tab_title ) {
						printf('<a href="?page=wp_wc_pdfqh_options_page&tab=%1$s" class="nav-tab nav-tab-%1$s %2$s">%3$s</a>', $tab_slug, (($active_tab == $tab_slug) ? 'nav-tab-active' : ''), $tab_title);
					}
					?>
					</h2>

					<?php
					do_action( 'wp_wc_pdfqh_before_settings_page', $active_tab );

					?>
					<form method="post" action="options.php" id="wp-wc-pdfqh-settings">
						<?php
							do_action( 'wp_wc_pdfqh_before_settings', $active_tab );
							settings_fields( 'wp_wc_pdfqh_'.$active_tab.'_settings' );
							do_settings_sections( 'wp_wc_pdfqh_'.$active_tab.'_settings' );
							do_action( 'wp_wc_pdfqh_after_settings', $active_tab );
	
							submit_button();
						?>
	
					</form>
					<?php

					if ( $active_tab=='debug' ) {
						$this->status_page();
					}

					do_action( 'wp_wc_pdfqh_after_settings_page', $active_tab ); ?>
	
				</div>
	
			<?php
		}

		public function status_page() {
			?>
			<?php include('dompdf-status.php'); ?>
			<?php
		}
		
		/**
		 * User settings.
		 * 
		 */
		
		public function init_settings() {
			global $woocommerce, $wp_wc_pdfqh;
	

			/**************************************/
			/*********** GENERAL SETTINGS *********/
			/**************************************/
	
			$option = 'wp_wc_pdfqh_general_settings';
		
			// Create option in wp_options.
			if ( false === get_option( $option ) ) {
				$this->default_settings( $option );
			}
		
			// Section.
			add_settings_section(
				'general_settings',
				__( 'General settings', 'wp_wc_pdfqh' ),
				array( &$this, 'section_options_callback' ),
				$option
			);
	
			add_settings_field(
				'download_display',
				__( 'How do you want to view the PDF?', 'wp_wc_pdfqh' ),
				array( &$this, 'select_element_callback' ),
				$option,
				'general_settings',
				array(
					'menu'			=> $option,
					'id'			=> 'download_display',
					'options' 		=> array(
						'download'	=> __( 'Download the PDF' , 'wp_wc_pdfqh' ),
						'display'	=> __( 'Open the PDF in a new browser tab/window' , 'wp_wc_pdfqh' ),
					),
				)
			);
			
			$tmp_path  = $wp_wc_pdfqh->export->tmp_path( 'attachments' );
			$tmp_path_check = !is_writable( $tmp_path );

			$wc_emails = array(
				'new_order'			=> __( 'Admin New Order email' , 'wp_wc_pdfqh' ),
				'processing'		=> __( 'Customer Processing Order email' , 'wp_wc_pdfqh' ),
				'completed'			=> __( 'Customer Completed Order email' , 'wp_wc_pdfqh' ),
				'customer_invoice'	=> __( 'Customer Invoice email' , 'wp_wc_pdfqh' ),
			);

			// load custom emails
			$extra_emails = $this->get_wc_emails();
			$wc_emails = array_merge( $wc_emails, $extra_emails );

			add_settings_field(
				'email_pdf',
				__( 'Attach quote to:', 'wp_wc_pdfqh' ),
				array( &$this, 'multiple_checkbox_element_callback' ),
				$option,
				'general_settings',
				array(
					'menu'			=> $option,
					'id'			=> 'email_pdf',
					'options' 		=> apply_filters( 'wp_wc_pdfqh_wc_emails', $wc_emails ),
					'description'	=> $tmp_path_check ? '<span class="wpwcpdfqh-warning">' . sprintf( __( 'It looks like the temp folder (<code>%s</code>) is not writable, check the permissions for this folder! Without having write access to this folder, the plugin will not be able to email quotes.', 'wp_wc_pdfqh' ), $tmp_path ).'</span>':'',
				)
			);

			// Section.
			add_settings_section(
				'interface',
				__( 'Interface', 'wp_wc_pdfqh' ),
				array( &$this, 'section_options_callback' ),
				$option
			);

			// $documents = array(
			// 	'quote'		=> __( 'Quote', 'wp_wc_pdfqh' ),
			// );

			// $contexts = array(
			// 	'orders-list'	=> __( 'Orders list', 'wp_wc_pdfqh' ),
			// 	'orders-bulk'	=> __( 'Bulk order actions', 'wp_wc_pdfqh' ),
			// 	'order-single'	=> __( 'Single order page', 'wp_wc_pdfqh' ),
			// 	'my-account'	=> __( 'My Account page', 'wp_wc_pdfqh' ),
			// );

			// add_settings_field(
			// 	'buttons',
			// 	__( 'Show download buttons', 'wp_wc_pdfqh' ),
			// 	array( &$this, 'checkbox_table_callback' ),
			// 	$option,
			// 	'interface',
			// 	array(
			// 		'menu'		=> $option,
			// 		'id'		=> 'buttons',
			// 		'rows' 		=> $contexts,
			// 		'columns'	=> apply_filters( 'wp_wc_pdfqh_documents_buttons', $documents ),
			// 	)
			// );

			// get list of WooCommerce statuses
			if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '<' ) ) {
				$statuses = (array) get_terms( 'shop_order_status', array( 'hide_empty' => 0, 'orderby' => 'id' ) );
				foreach ( $statuses as $status ) {
					$order_statuses[esc_attr( $status->slug )] = esc_html__( $status->name, 'woocommerce' );
				}
			} else {
				$statuses = wc_get_order_statuses();
				foreach ( $statuses as $status_slug => $status ) {
					$status_slug   = 'wc-' === substr( $status_slug, 0, 3 ) ? substr( $status_slug, 3 ) : $status_slug;
					$order_statuses[$status_slug] = $status;
				}

			}

			add_settings_field(
				'my_account_buttons',
				__( 'Allow My Account quote download', 'wp_wc_pdfqh' ),
				array( &$this, 'select_element_callback' ),
				$option,
				'interface',
				array(
					'menu'		=> $option,
					'id'		=> 'my_account_buttons',
					'options' 		=> array(
						'available'	=> __( 'Only when an quote is already created/emailed' , 'wp_wc_pdfqh' ),
						'custom'	=> __( 'Only for specific order statuses (define below)' , 'wp_wc_pdfqh' ),
						'always'	=> __( 'Always' , 'wp_wc_pdfqh' ),
						'never'		=> __( 'Never' , 'wp_wc_pdfqh' ),
					),
					'custom'		=> array(
						'type'		=> 'multiple_checkbox_element_callback',
						'args'		=> array(
							'menu'			=> $option,
							'id'			=> 'my_account_restrict',
							'options'		=> $order_statuses,
						),
					),
				)
			);

			add_settings_field(
				'quote_number_column',
				__( 'Enable quote number column in the orders list', 'wp_wc_pdfqh' ),
				array( &$this, 'checkbox_element_callback' ),
				$option,
				'interface',
				array(
					'menu'			=> $option,
					'id'			=> 'quote_number_column',
				)
			);

			// Register settings.
			register_setting( $option, $option, array( &$this, 'validate_options' ) );
	
			$option_values = get_option($option);
			// convert old 'statusless' setting to new status array
			if ( isset( $option_values['email_pdf'] ) && !is_array( $option_values['email_pdf'] ) ) {
				$default_status = apply_filters( 'wp_wc_pdfqh_attach_to_status', 'completed' );
				$option_values['email_pdf'] = array (
						$default_status		=> 1,
						'customer_quote'	=> 1,
					);
				update_option( $option, $option_values );
			}

			/**************************************/
			/********** TEMPLATE SETTINGS *********/
			/**************************************/
	
			$option = 'wp_wc_pdfqh_template_settings';
		
			// Create option in wp_options.
			if ( false === get_option( $option ) ) {
				$this->default_settings( $option );
			}
	
			// Section.
			add_settings_section(
				'template_settings',
				__( 'PDF Template settings', 'wp_wc_pdfqh' ),
				array( &$this, 'section_options_callback' ),
				$option
			);


			$theme_path = get_stylesheet_directory() . '/' . $wp_wc_pdfqh->export->template_base_path;
			$theme_template_path = substr($theme_path, strpos($theme_path, 'wp-content')) . 'yourtemplate';
			$plugin_template_path = 'wp-content/plugins/woocommerce-pdf-quote-handling/templates/pdf/Simple';

			add_settings_field(
				'template_path',
				__( 'Choose a template', 'wp_wc_pdfqh' ),
				array( &$this, 'template_select_element_callback' ),
				$option,
				'template_settings',
				array(
					'menu'			=> $option,
					'id'			=> 'template_path',
					'options' 		=> $this->find_templates(),
					'description'	=> sprintf( __( 'Want to use your own template? Copy all the files from <code>%s</code> to your (child) theme in <code>%s</code> to customize them' , 'wp_wc_pdfqh' ), $plugin_template_path, $theme_template_path),
				)
			);

			add_settings_field(
				'paper_size',
				__( 'Paper size', 'wp_wc_pdfqh' ),
				array( &$this, 'select_element_callback' ),
				$option,
				'template_settings',
				array(
					'menu'			=> $option,
					'id'			=> 'paper_size',
					'options' 		=> apply_filters( 'wp_wc_pdfqh_template_settings_paper_size', array(
						'a4'		=> __( 'A4' , 'wp_wc_pdfqh' ),
						'letter'	=> __( 'Letter' , 'wp_wc_pdfqh' ),
					) ),
				)
			);

			add_settings_field(
				'header_logo',
				__( 'Shop header/logo', 'wp_wc_pdfqh' ),
				array( &$this, 'media_upload_callback' ),
				$option,
				'template_settings',
				array(
					'menu'							=> $option,
					'id'							=> 'header_logo',
					'uploader_title'				=> __( 'Select or upload your quote header/logo', 'wp_wc_pdfqh' ),
					'uploader_button_text'			=> __( 'Set image', 'wp_wc_pdfqh' ),
					'remove_button_text'			=> __( 'Remove image', 'wp_wc_pdfqh' ),
					//'description'					=> __( '...', 'wp_wc_pdfqh' ),
				)
			);

			add_settings_field(
				'shop_name',
				__( 'Shop Name', 'wp_wc_pdfqh' ),
				array( &$this, 'text_element_callback' ),
				$option,
				'template_settings',
				array(
					'menu'			=> $option,
					'id'			=> 'shop_name',
					'size'			=> '72',
					'translatable'	=> true,
				)
			);

			add_settings_field(
				'shop_address',
				__( 'Shop Address', 'wp_wc_pdfqh' ),
				array( &$this, 'textarea_element_callback' ),
				$option,
				'template_settings',
				array(
					'menu'			=> $option,
					'id'			=> 'shop_address',
					'width'			=> '72',
					'height'		=> '8',
					'translatable'	=> true,
					//'description'			=> __( '...', 'wp_wc_pdfqh' ),
				)
			);
	
			add_settings_field(
				'footer',
				__( 'Footer: terms & conditions, policies, etc.', 'wp_wc_pdfqh' ),
				array( &$this, 'textarea_element_callback' ),
				$option,
				'template_settings',
				array(
					'menu'			=> $option,
					'id'			=> 'footer',
					'width'			=> '72',
					'height'		=> '4',
					'translatable'	=> true,
					//'description'			=> __( '...', 'wp_wc_pdfqh' ),
				)
			);

			// Section.
			add_settings_section(
				'quote',
				__( 'Quote', 'wp_wc_pdfqh' ),
				array( &$this, 'section_options_callback' ),
				$option
			);

			add_settings_field(
				'quote_shipping_address',
				__( 'Display shipping address', 'wp_wc_pdfqh' ),
				array( &$this, 'checkbox_element_callback' ),
				$option,
				'quote',
				array(
					'menu'				=> $option,
					'id'				=> 'quote_shipping_address',
					'description'		=> __( 'Display shipping address on quote (in addition to the default billing address) if different from billing address', 'wp_wc_pdfqh' ),
				)
			);

			add_settings_field(
				'quote_email',
				__( 'Display email address', 'wp_wc_pdfqh' ),
				array( &$this, 'checkbox_element_callback' ),
				$option,
				'quote',
				array(
					'menu'				=> $option,
					'id'				=> 'quote_email',
				)
			);

			add_settings_field(
				'quote_phone',
				__( 'Display phone number', 'wp_wc_pdfqh' ),
				array( &$this, 'checkbox_element_callback' ),
				$option,
				'quote',
				array(
					'menu'				=> $option,
					'id'				=> 'quote_phone',
				)
			);

			add_settings_field(
				'display_date',
				__( 'Display quote date', 'wp_wc_pdfqh' ),
				array( &$this, 'checkbox_element_callback' ),
				$option,
				'quote',
				array(
					'menu'				=> $option,
					'id'				=> 'display_date',
					'value' 			=> 'quote_date',
				)
			);

				// quote number is stored separately for direct retrieval
				register_setting( $option, 'wp_wc_pdfqh_next_quote_number', array( &$this, 'validate_options' ) );
				add_settings_field(
					'next_quote_number',
					__( 'Next quote number (without prefix/suffix etc.)', 'wp_wc_pdfqh' ),
					array( &$this, 'singular_text_element_callback' ),
					$option,
					'quote',
					array(
						'menu'			=> 'wp_wc_pdfqh_next_quote_number',
						'id'			=> 'next_quote_number',
						'size'			=> '10',
						'description'	=> __( 'This is the number that will be used on the next quote that is created. By default, numbering starts from the WooCommerce Order Number of the first quote that is created and increases for every new quote. Note that if you override this and set it lower than the highest (PDF) quote number, this could create double quote numbers!', 'wp_wc_pdfqh' ),
					)
				);

				// first time invoice number
				$next_quote_number = get_option('wp_wc_pdfqh_next_quote_number');
				// determine highest quote number if option not set
				if ( !isset( $next_quote_number ) ) {
					// Based on code from WooCommerce Sequential Order Numbers
					global $wpdb;
					// get highest quote_number in postmeta table
					$max_quote_number = $wpdb->get_var( 'SELECT max(cast(meta_value as UNSIGNED)) from ' . $wpdb->postmeta . ' where meta_key="_wc_pdfqh_quote_number"' );
					
					if ( !empty($max_quote_number) ) {
						$next_quote_number = $max_quote_number+1;
					} else {
						$next_quote_number = '';
					}

					update_option( 'wp_wc_pdfqh_next_quote_number', $next_quote_number );
				}

				add_settings_field(
					'quote_number_formatting',
					__( 'Quote number format', 'wp_wc_pdfqh' ),
					array( &$this, 'quote_number_formatting_callback' ),
					$option,
					'quote',
					array(
						'menu'					=> $option,
						'id'					=> 'quote_number_formatting',
						'fields'				=> array(
							'prefix'			=> array(
								'title'			=> __( 'Prefix' , 'wp_wc_pdfqh' ),
								'size'			=> 20,
								'description'	=> __( 'to use the quote year and/or month, use [quote_year] or [quote_month] respectively' , 'wp_wc_pdfqh' ),
							),
							'suffix'			=> array(
								'title'			=> __( 'Suffix' , 'wp_wc_pdfqh' ),
								'size'			=> 20,
								'description'	=> '',
							),
							'padding'			=> array(
								'title'			=> __( 'Padding' , 'wp_wc_pdfqh' ),
								'size'			=> 2,
								'description'	=> __( 'enter the number of digits here - enter "6" to display 42 as 000042' , 'wp_wc_pdfqh' ),
							),
						),
						'description'			=> __( 'note: if you have already created a custom quote number format with a filter, the above settings will be ignored' , 'wpo_wcpdf' ),
					)
				);

				add_settings_field(
					'yearly_reset_quote_number',
					__( 'Reset quote number yearly', 'wp_wc_pdfqh' ),
					array( &$this, 'checkbox_element_callback' ),
					$option,
					'quote',
					array(
						'menu'				=> $option,
						'id'				=> 'yearly_reset_quote_number',
					)
				);

			add_settings_field(
				'currency_font',
				__( 'Extended currency symbol support', 'wp_wc_pdfqh' ),
				array( &$this, 'checkbox_element_callback' ),
				$option,
				'quote',
				array(
					'menu'				=> $option,
					'id'				=> 'currency_font',
					'description'			=> __( 'Enable this if your currency symbol is not displaying properly' , 'wp_wc_pdfqh' ),
				)
			);			

			// Section.
			add_settings_section(
				'extra_template_fields',
				__( 'Extra template fields', 'wp_wc_pdfqh' ),
				array( &$this, 'custom_fields_section' ),
				$option
			);
	
			add_settings_field(
				'extra_1',
				__( 'Extra field 1', 'wp_wc_pdfqh' ),
				array( &$this, 'textarea_element_callback' ),
				$option,
				'extra_template_fields',
				array(
					'menu'			=> $option,
					'id'			=> 'extra_1',
					'width'			=> '72',
					'height'		=> '8',
					'description'	=> __( 'This is footer column 1 in the <i>Modern (Premium)</i> template', 'wp_wc_pdfqh' ),
					'translatable'	=> true,
				)
			);

			add_settings_field(
				'extra_2',
				__( 'Extra field 2', 'wp_wc_pdfqh' ),
				array( &$this, 'textarea_element_callback' ),
				$option,
				'extra_template_fields',
				array(
					'menu'			=> $option,
					'id'			=> 'extra_2',
					'width'			=> '72',
					'height'		=> '8',
					'description'	=> __( 'This is footer column 2 in the <i>Modern (Premium)</i> template', 'wp_wc_pdfqh' ),
					'translatable'	=> true,
				)
			);

			add_settings_field(
				'extra_3',
				__( 'Extra field 3', 'wp_wc_pdfqh' ),
				array( &$this, 'textarea_element_callback' ),
				$option,
				'extra_template_fields',
				array(
					'menu'			=> $option,
					'id'			=> 'extra_3',
					'width'			=> '72',
					'height'		=> '8',
					'description'	=> __( 'This is footer column 3 in the <i>Modern (Premium)</i> template', 'wp_wc_pdfqh' ),
					'translatable'	=> true,
				)
			);

			// Register settings.
			register_setting( $option, $option, array( &$this, 'validate_options' ) );

			/**************************************/
			/******** DEBUG/STATUS SETTINGS *******/
			/**************************************/
	
			$option = 'wp_wc_pdfqh_debug_settings';
		
			// Create option in wp_options.
			if ( false === get_option( $option ) ) {
				$this->default_settings( $option );
			}

			// Section.
			add_settings_section(
				'debug_settings',
				__( 'Debug settings', 'wp_wc_pdfqh' ),
				array( &$this, 'debug_section' ),
				$option
			);

			add_settings_field(
				'enable_debug',
				__( 'Enable debug output', 'wp_wc_pdfqh' ),
				array( &$this, 'checkbox_element_callback' ),
				$option,
				'debug_settings',
				array(
					'menu'				=> $option,
					'id'				=> 'enable_debug',
					'description'		=> __( "Enable this option to output plugin errors if you're getting a blank page or other PDF generation issues", 'wp_wc_pdfqh' ),
				)
			);

			add_settings_field(
				'html_output',
				__( 'Output to HTML', 'wp_wc_pdfqh' ),
				array( &$this, 'checkbox_element_callback' ),
				$option,
				'debug_settings',
				array(
					'menu'				=> $option,
					'id'				=> 'html_output',
					'description'		=> __( 'Send the template output as HTML to the browser instead of creating a PDF.', 'wp_wc_pdfqh' ),
				)
			);

			// Register settings.
			register_setting( $option, $option, array( &$this, 'validate_options' ) );
	
		}

		/**
		 * get all emails registered in WooCommerce
		 * @param  boolean $remove_defaults switch to remove default woocommerce emails
		 * @return array   $emails       list of all email ids/slugs and names
		 */
		public function get_wc_emails ( $remove_defaults = true ) {
			// get emails from WooCommerce
			global $woocommerce;
			$mailer = $woocommerce->mailer();
			$wc_emails = $mailer->get_emails();

			$default_emails = array(
				'new_order',
				'customer_processing_order',
				'customer_completed_order',
				'customer_invoice',
				'customer_note',
				'customer_reset_password',
				'customer_new_account'
			);

			$emails = array();
			foreach ($wc_emails as $name => $template) {
				if ( !( $remove_defaults && in_array( $template->id, $default_emails ) ) ) {
					$emails[$template->id] = $template->title;
				}
			}

			return $emails;
		}

		/**
		 * WooCommerce Order Status & Actions Manager emails compatibility
		 */
		public function wc_order_status_actions_emails ( $emails ) {
			// check if WC_Custom_Status class is loaded!
			if (class_exists('WC_Custom_Status')) {
				// get list of custom statuses from WooCommerce Custom Order Status & Actions
				// status slug => status name
				$custom_statuses = WC_Custom_Status::get_status_list_names();
				// append _email to slug (=email_id) and add to emails list
				foreach ($custom_statuses as $status_slug => $status_name) {
					$emails[$status_slug.'_email'] = $status_name;
				}
			}
			return $emails;
		}

		/**
		 * Set default settings.
		 */
		public function default_settings( $option ) {
			global $wp_wc_pdfqh;

			switch ( $option ) {
				case 'wp_wc_pdfqh_general_settings':
					$default = array(
						'download_display'	=> 'download',
					);
					break;
				case 'wp_wc_pdfqh_template_settings':
					$default = array(
						'paper_size'				=> 'a4',
						'template_path'				=> $wp_wc_pdfqh->export->template_default_base_path . 'Simple',
						// 'quote_shipping_address'	=> '1',
					);
					break;
				default:
					$default = array();
					break;
			}

			if ( false === get_option( $option ) ) {
				add_option( $option, $default );
			} else {
				update_option( $option, $default );

			}
		}
		
		// Text element callback.
		public function text_element_callback( $args ) {
			$menu = $args['menu'];
			$id = $args['id'];
			$size = isset( $args['size'] ) ? $args['size'] : '25';
			$class = isset( $args['translatable'] ) && $args['translatable'] === true ? 'translatable' : '';
		
			$options = get_option( $menu );
		
			if ( isset( $options[$id] ) ) {
				$current = $options[$id];
			} else {
				$current = isset( $args['default'] ) ? $args['default'] : '';
			}
		
			$html = sprintf( '<input type="text" id="%1$s" name="%2$s[%1$s]" value="%3$s" size="%4$s" class="%5$s"/>', $id, $menu, $current, $size, $class );
		
			// Displays option description.
			if ( isset( $args['description'] ) ) {
				$html .= sprintf( '<p class="description">%s</p>', $args['description'] );
			}
		
			echo $html;
		}

		// Single text option (not part of any settings array)
		public function singular_text_element_callback( $args ) {
			$menu = $args['menu'];
			$id = $args['id'];
			$size = isset( $args['size'] ) ? $args['size'] : '25';
			$class = isset( $args['translatable'] ) && $args['translatable'] === true ? 'translatable' : '';
		
			$option = get_option( $menu );

			if ( isset( $option ) ) {
				$current = $option;
			} else {
				$current = isset( $args['default'] ) ? $args['default'] : '';
			}
		
			$html = sprintf( '<input type="text" id="%1$s" name="%2$s" value="%3$s" size="%4$s" class="%5$s"/>', $id, $menu, $current, $size, $class );
		
			// Displays option description.
			if ( isset( $args['description'] ) ) {
				$html .= sprintf( '<p class="description">%s</p>', $args['description'] );
			}
		
			echo $html;
		}

		// Text element callback.
		public function textarea_element_callback( $args ) {
			$menu = $args['menu'];
			$id = $args['id'];
			$width = $args['width'];
			$height = $args['height'];
			$class = isset( $args['translatable'] ) && $args['translatable'] === true ? 'translatable' : '';
		
			$options = get_option( $menu );
		
			if ( isset( $options[$id] ) ) {
				$current = $options[$id];
			} else {
				$current = isset( $args['default'] ) ? $args['default'] : '';
			}
		
			$html = sprintf( '<textarea id="%1$s" name="%2$s[%1$s]" cols="%4$s" rows="%5$s" class="%6$s"/>%3$s</textarea>', $id, $menu, $current, $width, $height, $class );
		
			// Displays option description.
			if ( isset( $args['description'] ) ) {
				$html .= sprintf( '<p class="description">%s</p>', $args['description'] );
			}
		
			echo $html;
		}
	
	
		/**
		 * Checkbox field callback.
		 *
		 * @param  array $args Field arguments.
		 *
		 * @return string	  Checkbox field.
		 */
		public function checkbox_element_callback( $args ) {
			$menu = $args['menu'];
			$id = $args['id'];
			$value = isset( $args['value'] ) ? $args['value'] : 1;
		
			$options = get_option( $menu );
		
			if ( isset( $options[$id] ) ) {
				$current = $options[$id];
			} else {
				$current = isset( $args['default'] ) ? $args['default'] : '';
			}
		
			$html = sprintf( '<input type="checkbox" id="%1$s" name="%2$s[%1$s]" value="%3$s"%4$s />', $id, $menu, $value, checked( $value, $current, false ) );
		
			// Displays option description.
			if ( isset( $args['description'] ) ) {
				$html .= sprintf( '<p class="description">%s</p>', $args['description'] );
			}
		
			echo $html;
		}
		
		/**
		 * Multiple Checkbox field callback.
		 *
		 * @param  array $args Field arguments.
		 *
		 * @return string	  Checkbox field.
		 */
		public function multiple_checkbox_element_callback( $args ) {
			$menu = $args['menu'];
			$id = $args['id'];
		
			$options = get_option( $menu );
		
		
			foreach ( $args['options'] as $key => $label ) {
				$current = ( isset( $options[$id][$key] ) ) ? $options[$id][$key] : '';
				printf( '<input type="checkbox" id="%1$s[%2$s][%3$s]" name="%1$s[%2$s][%3$s]" value="1"%4$s /> %5$s<br/>', $menu, $id, $key, checked( 1, $current, false ), $label );
			}

			// Displays option description.
			if ( isset( $args['description'] ) ) {
				printf( '<p class="description">%s</p>', $args['description'] );
			}
		}

		/**
		 * Checkbox fields table callback.
		 *
		 * @param  array $args Field arguments.
		 *
		 * @return string	  Checkbox field.
		 */
		public function checkbox_table_callback( $args ) {
			$menu = $args['menu'];
			$id = $args['id'];

			$options = get_option( $menu );

			$rows = $args['rows'];
			$columns = $args['columns'];

			?>
			<table style="">
				<tr>
					<td style="padding:0 10px 5px 0;">&nbsp;</td>
					<?php foreach ( $columns as $column => $title ) { ?>
					<td style="padding:0 10px 5px 0;"><?php echo $title; ?></td>
					<?php } ?>
				</tr>
				<tr>
					<td style="padding: 0;">
						<?php foreach ($rows as $row) {
							echo $row.'<br/>';
						} ?>
					</td>
					<?php foreach ( $columns as $column => $title ) { ?>
					<td style="text-align:center; padding: 0;">
						<?php foreach ( $rows as $row => $title ) {
							$current = ( isset( $options[$id.'_'.$column][$row] ) ) ? $options[$id.'_'.$column][$row] : '';
							$name = sprintf('%1$s[%2$s_%3$s][%4$s]', $menu, $id, $column, $row);
							printf( '<input type="checkbox" id="%1$s" name="%1$s" value="1"%2$s /><br/>', $name, checked( 1, $current, false ) );
						} ?>
					</td>
					<?php } ?>
				</tr>
			</table>

			<?php
			// Displays option description.
			if ( isset( $args['description'] ) ) {
				printf( '<p class="description">%s</p>', $args['description'] );
			}
		}

		/**
		 * Select element callback.
		 *
		 * @param  array $args Field arguments.
		 *
		 * @return string	  Select field.
		 */
		public function select_element_callback( $args ) {
			$menu = $args['menu'];
			$id = $args['id'];
		
			$options = get_option( $menu );
		
			if ( isset( $options[$id] ) ) {
				$current = $options[$id];
			} else {
				$current = isset( $args['default'] ) ? $args['default'] : '';
			}
		
			printf( '<select id="%1$s" name="%2$s[%1$s]">', $id, $menu );
	
			foreach ( $args['options'] as $key => $label ) {
				printf( '<option value="%s"%s>%s</option>', $key, selected( $current, $key, false ), $label );
			}
	
			echo '</select>';
		

			if (isset($args['custom'])) {
				$custom = $args['custom'];

				$custom_id = $id.'_custom';

				printf( '<br/><br/><div id="%s" style="display:none;">', $custom_id );

				switch ($custom['type']) {
					case 'text_element_callback':
						$this->text_element_callback( $custom['args'] );
						break;		
					case 'multiple_text_element_callback':
						$this->multiple_text_element_callback( $custom['args'] );
						break;		
					case 'multiple_checkbox_element_callback':
						$this->multiple_checkbox_element_callback( $custom['args'] );
						break;		
					default:
						break;
				}

				echo '</div>';

				?>
				<script type="text/javascript">
				jQuery(document).ready(function($) {
					function check_<?php echo $id; ?>_custom() {
						var custom = $('#<?php echo $id; ?>').val();
						if (custom == 'custom') {
							$( '#<?php echo $custom_id; ?>').show();
						} else {
							$( '#<?php echo $custom_id; ?>').hide();
						}
					}

					check_<?php echo $id; ?>_custom();

					$( '#<?php echo $id; ?>' ).change(function() {
						check_<?php echo $id; ?>_custom();
					});

				});
				</script>
				<?php
			}

			// Displays option description.
			if ( isset( $args['description'] ) ) {
				printf( '<p class="description">%s</p>', $args['description'] );
			}

		}
		
		/**
		 * Displays a radio settings field
		 *
		 * @param array   $args settings field args
		 */
		public function radio_element_callback( $args ) {
			$menu = $args['menu'];
			$id = $args['id'];
		
			$options = get_option( $menu );
		
			if ( isset( $options[$id] ) ) {
				$current = $options[$id];
			} else {
				$current = isset( $args['default'] ) ? $args['default'] : '';
			}
	
			$html = '';
			foreach ( $args['options'] as $key => $label ) {
				$html .= sprintf( '<input type="radio" class="radio" id="%1$s[%2$s][%3$s]" name="%1$s[%2$s]" value="%3$s"%4$s />', $menu, $id, $key, checked( $current, $key, false ) );
				$html .= sprintf( '<label for="%1$s[%2$s][%3$s]"> %4$s</label><br>', $menu, $id, $key, $label);
			}
			
			// Displays option description.
			if ( isset( $args['description'] ) ) {
				$html .= sprintf( '<p class="description">%s</p>', $args['description'] );
			}
	
			echo $html;
		}

		/**
		 * Media upload callback.
		 *
		 * @param  array $args Field arguments.
		 *
		 * @return string	  Media upload button & preview.
		 */
		public function media_upload_callback( $args ) {
			$menu = $args['menu'];
			$id = $args['id'];
			$options = get_option( $menu );
		
			if ( isset( $options[$id] ) ) {
				$current = $options[$id];
			} else {
				$current = isset( $args['default'] ) ? $args['default'] : '';
			}

			$uploader_title = $args['uploader_title'];
			$uploader_button_text = $args['uploader_button_text'];
			$remove_button_text = $args['remove_button_text'];

			$html = '';
			if( !empty($current) ) {
				$attachment = wp_get_attachment_image_src( $current, 'full', false );
				
				$attachment_src = $attachment[0];
				$attachment_width = $attachment[1];
				$attachment_height = $attachment[2];

				$attachment_resolution = round($attachment_height/(3/2.54));
				
				$html .= sprintf('<img src="%1$s" style="display:block" id="img-%4$s"/>', $attachment_src, $attachment_width, $attachment_height, $id );
				$html .= '<div class="attachment-resolution"><p class="description">'.__('Image resolution').': '.$attachment_resolution.'dpi (default height = 3cm)</p></div>';
				$html .= sprintf('<span class="button wpwcpdfqh_remove_image_button" data-input_id="%1$s">%2$s</span>', $id, $remove_button_text );
			}

			$html .= sprintf( '<input id="%1$s" name="%2$s[%1$s]" type="hidden" value="%3$s" />', $id, $menu, $current );
			
			$html .= sprintf( '<span class="button wpwcpdfqh_upload_image_button %4$s" data-uploader_title="%1$s" data-uploader_button_text="%2$s" data-remove_button_text="%3$s" data-input_id="%4$s">%2$s</span>', $uploader_title, $uploader_button_text, $remove_button_text, $id );
		
			// Displays option description.
			if ( isset( $args['description'] ) ) {
				$html .= sprintf( '<p class="description">%s</p>', $args['description'] );
			}
		
			echo $html;
		}

		/**
		 * Quote number formatting callback.
		 *
		 * @param  array $args Field arguments.
		 *
		 * @return string	  Media upload button & preview.
		 */
		public function quote_number_formatting_callback( $args ) {
			$menu = $args['menu'];
			$fields = $args['fields'];
			$options = get_option( $menu );

			echo '<table>';
			foreach ($fields as $key => $field) {
				$id = $args['id'] . '_' . $key;

				if ( isset( $options[$id] ) ) {
					$current = $options[$id];
				} else {
					$current = '';
				}

				$title = $field['title'];
				$size = $field['size'];
				$description = isset( $field['description'] ) ? '<span style="font-style:italic;">'.$field['description'].'</span>' : '';

				echo '<tr>';
				printf( '<td style="padding:0 1em 0 0; ">%1$s:</td><td style="padding:0;"><input type="text" id="%2$s" name="%3$s[%2$s]" value="%4$s" size="%5$s"/></td><td style="padding:0 0 0 1em;">%6$s</td>', $title, $id, $menu, $current, $size, $description );
				echo '</tr>';
			}
			echo '</table>';

		
			// Displays option description.
			if ( isset( $args['description'] ) ) {
				printf( '<p class="description">%s</p>', $args['description'] );
			}
		
			// echo $html;
		}

		/**
		 * Template select element callback.
		 *
		 * @param  array $args Field arguments.
		 *
		 * @return string	  Select field.
		 */
		public function template_select_element_callback( $args ) {
			$menu = $args['menu'];
			$id = $args['id'];
		
			$options = get_option( $menu );
		
			if ( isset( $options[$id] ) ) {
				$current = $options[$id];
			} else {
				$current = isset( $args['default'] ) ? $args['default'] : '';
			}
		
			$html = sprintf( '<select id="%1$s" name="%2$s[%1$s]">', $id, $menu );

			// backwards compatible template path (1.4.4+ uses relative paths instead of absolute)
			if (strpos($current, ABSPATH) !== false) {
				//  check if folder exists, then strip site base path.
				if ( file_exists( $current ) ) {
					$current = str_replace( ABSPATH, '', $current );
				}
			}

			foreach ( $args['options'] as $key => $label ) {
				$html .= sprintf( '<option value="%s"%s>%s</option>', $key, selected( $current, $key, false ), $label );
			}
	
			$html .= '</select>';
		
			// Displays option description.
			if ( isset( $args['description'] ) ) {
				$html .= sprintf( '<p class="description">%s</p>', $args['description'] );
			}
		
			echo $html;
		
		}

		/**
		 * Section null callback.
		 *
		 * @return void.
		 */
		public function section_options_callback() {
		}
		
		/**
		 * Debug section callback.
		 *
		 * @return void.
		 */
		public function debug_section() {
			_e( '<b>Warning!</b> The settings below are meant for debugging/development only. Do not use them on a live website!' , 'wp_wc_pdfqh' );
		}
		
		/**
		 * Custom fields section callback.
		 *
		 * @return void.
		 */
		public function custom_fields_section() {
			_e( 'These are used for the (optional) footer columns in the <em>Modern (Premium)</em> template, but can also be used for other elements in your custom template' , 'wp_wc_pdfqh' );
		}

		/**
		 * Validate options.
		 *
		 * @param  array $input options to valid.
		 *
		 * @return array		validated options.
		 */
		public function validate_options( $input ) {
			// Create our array for storing the validated options.
			$output = array();

			if (empty($input) || !is_array($input)) {
				return $input;
			}
		
			// Loop through each of the incoming options.
			foreach ( $input as $key => $value ) {
		
				// Check to see if the current option has a value. If so, process it.
				if ( isset( $input[$key] ) ) {
					if ( is_array( $input[$key] ) ) {
						foreach ( $input[$key] as $sub_key => $sub_value ) {
							$output[$key][$sub_key] = $input[$key][$sub_key];
						}
					} else {
						$output[$key] = $input[$key];
					}
				}
			}
		
			// Return the array processing any additional functions filtered by this action.
			return apply_filters( 'wp_wc_pdfqh_validate_input', $output, $input );
		}

		/**
		 * List templates in plugin folder, theme folder & child theme folder
		 * @return array		template path => template name
		 */
		public function find_templates() {
			global $wp_wc_pdfqh;
			$installed_templates = array();

			// get base paths
			$template_paths = array (
					// note the order: child-theme before theme, so that array_unique filters out parent doubles
					'default'		=> $wp_wc_pdfqh->export->template_default_base_path,
					'child-theme'	=> get_stylesheet_directory() . '/' . $wp_wc_pdfqh->export->template_base_path,
					'theme'			=> get_template_directory() . '/' . $wp_wc_pdfqh->export->template_base_path,
				);

			$template_paths = apply_filters( 'wp_wc_pdfqh_template_paths', $template_paths );

			foreach ($template_paths as $template_source => $template_path) {
				$dirs = (array) glob( $template_path . '*' , GLOB_ONLYDIR);
				
				foreach ($dirs as $dir) {
					if (file_exists($dir."/quote.php"))
						// we're stripping abspath to make the plugin settings more portable
						$installed_templates[ str_replace( ABSPATH, '', $dir )] = basename($dir);
				}
			}

			// remove parent doubles
			$installed_templates = array_unique($installed_templates);

			if (empty($installed_templates)) {
				// fallback to Simple template for servers with glob() disabled
				$simple_template_path = str_replace( ABSPATH, '', $template_paths['default'] . 'Simple' );
				$installed_templates[$simple_template_path] = 'Simple';
			}

			return apply_filters( 'wp_wc_pdfqh_templates', $installed_templates );
		}

	} // end class WooCommerce_PDF_Quote_Handling_Settings

} // end class_exists
