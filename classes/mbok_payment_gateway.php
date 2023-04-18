<?php
/**
 * Bank Of Khatroum Payment Gateway
 *
 * @class   MBOK_Payment_Gateway
 * @extends	WC_Payment_Gateway
 */

class MBOK_Payment_Gateway extends WC_Payment_Gateway
{

	public $domain;

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
		$this->domain             = 'mbok_payment';
        $this->id                 = 'mbok_gateway';
        $this->icon               = apply_filters('woocommerce_offline_icon', '');
        $this->has_fields         = false;
        $this->method_title       = __('Bank transfer through your Bank Of Khartoum', $this->domain);
        $this->method_description = __('Bank transfer through your Bank Of Khartoum App (mBok) and upload the receipt.', $this->domain);

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title            = $this->get_option('title');
        $this->description      = $this->get_option('description');
        $this->instructions     = $this->get_option('instructions');
        $this->require_trx		= $this->get_option('require_trx');
        
        // BACS account fields shown on the checkout page and in admin configuration tab.
		$this->account_details = get_option(
			'woocommerce_bacs_accountss',
			array(
				array(
					'account_name'   => $this->get_option( 'account_name' ),
					'account_number' => $this->get_option( 'account_number' ),
					'phone_number'   => $this->get_option( 'phone_number' ),
					'bank_branch'    => $this->get_option( 'bank_branch' ),
				),
			)
		);

        // Actions
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'save_account_details' ) );
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));

        // Customer Emails
        add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
    }


    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields()
    {

        $this->form_fields = apply_filters('wc_offline_form_fields', array(

            'enabled' => array(
                'title'   => __('Enable/Disable', $this->domain),
                'type'    => 'checkbox',
                'label'   => __('Enable Payment', $this->domain),
                'default' => 'yes'
            ),

            'title' => array(
                'title'       => __('Title', $this->domain),
                'type'        => 'text',
                'description' => __('This controls the title for the payment method the customer sees during checkout.', $this->domain),
                'default'     => __('Payment through your Bankank', $this->domain),
                'desc_tip'    => true,
            ),

            'description' => array(
                'title'       => __('Description', $this->domain),
                'type'        => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', $this->domain),
                'default'     => __('Pay through your bank to the account (or one of the accounts) shown below, then send us a copy of the transfer invoice to be verified by our team, then confirm the order', $this->domain),
                'desc_tip'    => true,
            ),

            'require_trx' => array(
                'title'   => __('Require TRX?', $this->domain),
                'type'    => 'checkbox',
                'label'   => __('Transaction ID', $this->domain),
                'default' => 'no'
            ),

            'instructions' => array(
                'title'       => __('Instructions', $this->domain),
                'type'        => 'textarea',
                'description' => __('Instructions', $this->domain),
                'default'     => '',
                'desc_tip'    => true,
            ),

            'account_details' => array(
				'type' => __('account_details', $this->domain),
			),
        ));
    }

	/**
	 * Save account details table.
	 */
	public function save_account_details() {
		
		$accounts = array();

		// phpcs:disable WordPress.Security.NonceVerification.NoNonceVerification -- Nonce verification already handled in WC_Admin_Settings::save()
		if ( isset( $_POST['bacs_account_name'] ) && isset( $_POST['bacs_account_number'] ) && isset( $_POST['bacs_bank_branch'] )
			 && isset( $_POST['bacs_phone_number'] ) ) {

			$account_names   = wc_clean( wp_unslash( $_POST['bacs_account_name'] ) );
			$account_numbers = wc_clean( wp_unslash( $_POST['bacs_account_number'] ) );
			$bank_branchs      = wc_clean( wp_unslash( $_POST['bacs_bank_branch'] ) );
			$phone_numbers      = wc_clean( wp_unslash( $_POST['bacs_phone_number'] ) );

			foreach ( $account_names as $i => $name ) {
				if ( ! isset( $account_names[ $i ] ) ) {
					continue;
				}

				$accounts[] = array(
					'account_name'   => $account_names[ $i ],
					'account_number' => $account_numbers[ $i ],
					'bank_branch'      => $bank_branchs[ $i ],
					'phone_number'      => $phone_numbers[ $i ],
				);
			}
		}
		
		update_option( 'woocommerce_bacs_accountss', $accounts );
	}

    /**
	 * Generate account details html.
	 *
	 * @return string
	 */
	public function generate_account_details_html() {

		ob_start();

		$country = WC()->countries->get_base_country();

		
		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php esc_html_e( 'Account details:', $this->domain ); ?></th>
			<td class="forminp" id="bacs_accounts">
				<div class="wc_input_table_wrapper">
					<table class="widefat wc_input_table sortable" cellspacing="0">
						<thead>
							<tr>
								<th class="sort">&nbsp;</th>
								<th><?php esc_html_e( 'Account name', $this->domain ); ?></th>
								<th><?php esc_html_e( 'Account number', $this->domain ); ?></th>
								<th><?php esc_html_e( 'Bank Branch', $this->domain ); ?></th>
								<th><?php esc_html_e( 'Phone Number', $this->domain ); ?></th>
							</tr>
						</thead>
						<tbody class="accounts">
							<?php
							$i = -1;
							if ( $this->account_details ) {
								foreach ( $this->account_details as $account ) {
									$i++;

									echo '<tr class="account">
										<td class="sort"></td>
										<td><input type="text" value="' . esc_attr( wp_unslash( $account['account_name'] ) ) . '" name="bacs_account_name[' . esc_attr( $i ) . ']" /></td>
										<td><input type="text" value="' . esc_attr( $account['account_number'] ) . '" name="bacs_account_number[' . esc_attr( $i ) . ']" /></td>
										<td><input type="text" value="' . esc_attr( wp_unslash( $account['bank_branch'] ) ) . '" name="bacs_bank_branch[' . esc_attr( $i ) . ']" /></td>
										<td><input type="text" value="' . esc_attr( $account['phone_number'] ) . '" name="bacs_phone_number[' . esc_attr( $i ) . ']" /></td>
									</tr>';
								}
							}
							?>
						</tbody>
						<tfoot>
							<tr>
								<th colspan="7"><a href="#" class="add button"><?php esc_html_e( '+ Add account', $this->domain ); ?></a> <a href="#" class="remove_rows button"><?php esc_html_e( 'Remove selected account(s)', $this->domain ); ?></a></th>
							</tr>
						</tfoot>
					</table>
				</div>
				<script type="text/javascript">
					jQuery(function() {
						jQuery('#bacs_accounts').on( 'click', 'a.add', function(){

							var size = jQuery('#bacs_accounts').find('tbody .account').length;

							jQuery('<tr class="account">\
									<td class="sort"></td>\
									<td><input type="text" name="bacs_account_name[' + size + ']" /></td>\
									<td><input type="text" name="bacs_account_number[' + size + ']" /></td>\
									<td><input type="text" name="bacs_bank_branch[' + size + ']" /></td>\
									<td><input type="text" name="bacs_phone_number[' + size + ']" /></td>\
								</tr>').appendTo('#bacs_accounts table tbody');

							return false;
						});
					});
				</script>
			</td>
		</tr>
		<?php
		return ob_get_clean();

	}

	/**
	 * Get bank details and place into a list format.
	 *
	 * @param int $order_id Order ID.
	 */
	private function bank_details( $order_id = '' ) {

		if ( empty( $this->account_details ) ) {
			return;
		}

		// Get order and store in $order.
		$order = wc_get_order( $order_id );

		$bacs_accounts = apply_filters( 'woocommerce_bacs_accountss', $this->account_details );

		if ( ! empty( $bacs_accounts ) ) {
			$account_html = '';
			foreach ( $bacs_accounts as $bacs_account ) {
				$account_html .= mbok_get_account_table($bacs_account);
			}
			
			?>
			<style>
				table.bank-table {
					width: 100%;
					max-width: 500px;
					margin: 30px auto;
					/* box-shadow: 0 0 17px #00000045; */
				}

				table.bank-table td {
					text-align: center;
					border-radius: 39px;
				}

				table.bank-table td .td-inner{
					background-color: #09527e66;
					color: #ffffff;
					font-size: 19px;
					margin: 2px 5px;
					padding: 10px;
					border-radius: 16px;
					display: flex;
					align-items: center;
				}

				table.bank-table tbody td {
					width: 50%;
				}

				table.bank-table tbody td .td-inner{
					background-color: #09527e66;
					font-size: 15px;
					white-space: nowrap;
					color: #ffffff;
				}
				
				table.bank-table td .td-inner.td-name{
					justify-content: space-between;
				}
				table.bank-table .account-number{
					position: relative;
					display: inline-block;
					border: solid 1px #ffffff36;
					padding: 5px 30px;
					border-radius: 25px;
					background-color: #4b799b;
					cursor: pointer;
					box-shadow: 0px 2px #506b7f;
					transition: all ease-in-out 0.15s;
				}
				table.bank-table .account-number span.copied{
					display: none;
					position: absolute;
					top: 0;
					bottom: 0;
					right: 100%;
					width: 77px;
					height: 29px;
					line-height: 27px;
					margin: auto;
					margin-right: 8px;
					/* display: inline-block; */
					background: #239d23;
					border-radius: 17px;
					color: white;
					font-size: 12px;
					padding: 0;
					text-align: center;
					box-shadow: 0 0 5px #0000002e;
					border: solid 1px #ffffff59;
				}
				
				table.bank-table .account-number:hover{
					background-color: #4b9b87;
				}
				
				table.bank-table .account-number:active{
					transform: translateY(2px);
					box-shadow: 0px 0px #506b7f;
				}
				
				.receipt-preview.loading{
					position: relative;
					padding-left: 2.618em;
				}

				.payment-receipt-btn.loading{
					position: relative;
					opacity: 0.25;
					padding-left: 2.618em;
				}

				.receipt-preview.loading img {
					opacity: 0.25;
				}
				
				.payment-receipt-btn.loading::after,
				.receipt-preview.loading::after{
					font-family: WooCommerce;
					content: "\e01c";
					vertical-align: top;
					font-weight: 400;
					position: absolute;
					color: white;
					animation: spin 2s linear infinite;
				}

				.payment-receipt-btn.loading::after{
					font-size: 20px;
					top: calc(50% - 14px);
					left: calc(50% - 15px);
				}
				
				.receipt-preview.loading::after{
					font-size: 35px;
					top: calc(50% - 30px);
					left: calc(50% - 15px);
				}
				@media screen and (max-width: 800px) {
					table.bank-table tbody td {
						font-size: 15px;
					}
				}
			</style>
			<script>
				jQuery(document).ready(function($){
					$('.account-number').click(function(){

						var copyText = $(this).find(".copy").text();
						var tempInput = document.createElement("input");
						tempInput.value = copyText;
						document.body.appendChild(tempInput);
						tempInput.select();
						document.execCommand("copy");
						document.body.removeChild(tempInput);


						var this_ = $(this);
						this_.find('.copied').fadeIn();
						setTimeout(() => {
						this_.find('.copied').fadeOut();
						}, 500);
					});
				})
			</script>
			<?php
			echo '<section class="woocommerce-bacs-bank-details"><h2 class="wc-bacs-bank-details-heading">' . esc_html__( 'Our bank details', 'woocommerce' ) . '</h2>' . wp_kses_post( PHP_EOL . $account_html ) . '</section>';
		}

	}

	public function payment_fields(){

		if ( $description = $this->get_description() ) {
			echo wpautop( wptexturize( $description ) );
		}
		
		$this->bank_details();
		?>
		<style>
			#mbokReceipt{
				max-width: 500px;
				margin: auto;
				text-align: center;
				border: solid 1px #ffffff78;
				padding: 20px 0;
				border-radius: 20px;
			}
			#mbokReceipt label {
				display: block;
			}
			#mbokReceipt .form-group-trx{
				margin-bottom: 10px;
			}
			#mbokReceipt .form-group.form-group-trx input {
				width: 220px;
				border-radius: 20px;
				padding: 8px 15px;
				text-align: left;
				direction: ltr;
				border: solid 1px #c9c9c9;
				background-color: #fcfcfc;
			}
			#mbokReceipt .bank_payment_receipt{
				
			}
			
			#mbokReceipt .form-group.receipt-preview {
				padding: 30px;
			}
			#mbokReceipt .form-group.receipt-preview img {
				width: 100%;
				min-width: 200px;
				min-height: 200px;
				margin: auto !important;
				object-fit: cover;
				max-width: 220px;
				border: solid 1px #c3c3c3;
			}

			input.bank_payment_receipt {
				display: none; /* Hide the default file upload button */
			}

			#mbokReceipt label.payment-receipt-btn {
				border: 1px solid #ccc;
				display: inline-flex !important;
				padding: 6px 12px;
				cursor: pointer;
				align-items: center;
				justify-content: space-between;
			}
			#mbokReceipt label.payment-receipt-btn i {
				font-size: 24px;
				margin-inline-end: 6px;
			}
			.payment-receipt-btn:hover {
				background-color: #f7f7f7;
			}

		</style>
		<div id="mbokReceipt">
			<?php if($this->require_trx == 'yes'): ?>
				<div class="form-group form-group-trx">
					<label for="bank_payment_trx" class=""><?php _e('Enter the bank payment TRX', $this->domain); ?></label>
					<input type="text" name="bank_payment_trx" class="bank_payment_trx" required>
				</div>
				<hr style="border-color: white;">
			<?php endif; ?>
			<div class="form-group">
				<label for="bank_payment_receipt" class="payment-receipt-btn"><i class="fa-solid fa-upload"></i> <?php _e('Upload mBok Receipt Image', $this->domain); ?></label>
				<input type="file" id="bank_payment_receipt" name="bank_payment_receipt" class="bank_payment_receipt" onclick="this.value=null;document.getElementById('receiptPreview').src = ''" onchange="document.getElementById('receiptPreview').src = window.URL.createObjectURL(this.files[0])" required>
			</div>
			<div class="form-group receipt-preview">
				<img id="receiptPreview"/>
			</div>
				<input type="hidden" name="attach_id" class="attach_id">
		</div>
		<script>
			jQuery(document).ready( function($) {
				$(".bank_payment_receipt").change( function() {
					var fd = new FormData();
					fd.append('file', $('.bank_payment_receipt')[0].files[0]);
					fd.append('action', 'invoice_response');  
					
					$('.payment-receipt-btn').addClass('loading');
					$('.receipt-preview').addClass('loading');
					$.ajax({
						type: 'POST',
						url: the_ajax_script.ajaxurl,
						data: fd,
						contentType: false,
						processData: false,
						success: function(response){
							if(response=='0'){
								alert('Invalid File, please upload correct file');
								$('.attach_id').val('');
								document.getElementById('receiptPreview').src = '';
							}else{
								$('.receipt-preview').removeClass('loading');
								$('.payment-receipt-btn').removeClass('loading');
								$('.attach_id').val(response);
							}
						}
					});
				});

				$('#receiptPreview').click(function(){
					$('.bank_payment_receipt').trigger('click');
				});
			});
		</script>
		<?php
	}

	public function validate_fields(){
		if(
			(!isset($_POST['attach_id']) || empty( $_POST['attach_id']) ) ||
			(!is_numeric($_POST['attach_id'])) ||
			(($this->require_trx == 'yes') && (!isset($_POST['bank_payment_trx']) || empty($_POST['bank_payment_trx'])))
		)
		{
			wc_add_notice(__('<strong>mBok</strong> Please insert Receipt Image and TRX number correctly'), 'error');
			return false;
		}
		return true;
	}

    /**
     * Output for the order received page.
     */
    public function thankyou_page()
    {
        if ($this->instructions) {
            echo wpautop(wptexturize($this->instructions));
        }
    }


    /**
     * Add content to the WC emails.
     *
     */
    public function email_instructions($order, $sent_to_admin, $plain_text = false)
    {

        if ($this->instructions && !$sent_to_admin && $this->id === $order->payment_method && $order->has_status('processing')) {
            echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
        }
    }


    /**
     * Process the payment and return the result
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id)
    {

        $order = wc_get_order($order_id);

        // Mark as processing (we're Processing the payment)
        $order->update_status('processing', __('Processing Payment Confirmation', $this->domain));

        // Reduce stock levels
        $order->reduce_order_stock();

        // Remove cart
        WC()->cart->empty_cart();

        // Return thankyou redirect
        return array(
            'result'     => 'success',
            'redirect'    => $this->get_return_url($order)
        );
    }
}

/**
 * Update the order meta with field value
 */
add_action( 'woocommerce_checkout_update_order_meta', 'abpt_custom_payment_update_order_meta' );
function abpt_custom_payment_update_order_meta( $order_id ) {
    if($_POST['payment_method'] != 'mbok_gateway')
        return;
	
    update_post_meta( $order_id, 'mbok_trx', sanitize_text_field( $_POST['bank_payment_trx'] ?? '' ) );
    update_post_meta( $order_id, 'attach_id', sanitize_text_field( $_POST['attach_id'] ?? '') );
}


/**
 * Display field value on the order edit page
 */
add_action( 'woocommerce_admin_order_data_after_order_details', 'abpt_custom_checkout_field_display_admin_order_meta', 10, 1 );
function abpt_custom_checkout_field_display_admin_order_meta($order){
    $method = get_post_meta( $order->id, '_payment_method', true );
    if($method != 'mbok_gateway')
        return;

    $attach_id = get_post_meta( $order->id, 'attach_id', true );
    $mbok_trx = get_post_meta( $order->id, 'mbok_trx', true );
	$src = wp_get_attachment_url($attach_id, 'full');
    echo '<p><strong>'.__( 'mBok Payment Receipt' ).':</strong> <a class="mbok-image-popup" href="'.$src.'"><img src="'.$src.'" height="50"/></a></p>';
    echo '<p><strong>'.__( 'Receipt TRX' ).':</strong> ' . $mbok_trx . '</p>';
}

// Add custom column to orders table
add_filter( 'manage_edit-shop_order_columns', 'mbok_payment_order_column', 20 );
function mbok_payment_order_column( $columns ) {
    $new_columns = array();
    foreach ( $columns as $column_name => $column_info ) {
        $new_columns[ $column_name ] = $column_info;
        if ( 'order_total' === $column_name ) {
            $new_columns['mbok_trx'] = __( 'Trx', 'text-domain' );
            $new_columns['mbok_receipt'] = __( 'Receipt', 'text-domain' );
        }
    }
    return $new_columns;
}
// Add data to custom column
add_action( 'manage_shop_order_posts_custom_column', 'mbok_payment_column_content' );
function mbok_payment_column_content( $column ) {
    global $post;
    if ( 'mbok_trx' === $column ) {
        echo get_post_meta( $post->ID, 'mbok_trx', true);
    }
    if ( 'mbok_receipt' === $column ) {
        $attach_id = get_post_meta( $post->ID, 'attach_id', true);
        $src = wp_get_attachment_url($attach_id, 'full');
        echo '<a class="mbok-image-popup" target="_blank" href="'.$src.'"><img src="'.$src.'" height="50"/></a>';
    }
}

function mbok_enqueue_magnific_popup_script() {
    global $pagenow, $typenow;

	// Enqueue Magnific Popup script
    if (is_admin() && ($pagenow === 'edit.php' || $pagenow === 'post.php') && $typenow === 'shop_order') {
        wp_enqueue_script('magnific-popup', 'https://cdnjs.cloudflare.com/ajax/libs/magnific-popup.js/1.1.0/jquery.magnific-popup.min.js', array('jquery'), '1.1.0', true);
        add_action('admin_footer', function() {
            ?>
            <script>
                jQuery(document).ready(function($) {
                    $('.mbok-image-popup').magnificPopup({
                        type: 'image',
                        gallery: {
                            enabled: false
                        }
                    });
                });
            </script>
            <?php
        });
		?>
		<style>
			.post-type-shop_order tbody tr {
				pointer-events: none;
				cursor: default;
			}
            .post-type-shop_order tbody tr td *,
            .post-type-shop_order tbody tr th *{
                pointer-events: all;
                cursor: unset;
            }
		</style>
		<?php
    }
}
add_action('admin_enqueue_scripts', 'mbok_enqueue_magnific_popup_script');


// Enqueue scripts and styles
add_action( 'admin_enqueue_scripts', 'my_admin_enqueue_scripts' );
function my_admin_enqueue_scripts( $hook_suffix ) {
    // Get the current admin page
    global $pagenow, $typenow;
    // Check if we're on the Orders page and the 'post_type' query parameter is set to 'shop_order'
    if (is_admin() && ($pagenow === 'edit.php' || $pagenow === 'post.php') && $typenow === 'shop_order') {
        wp_enqueue_script( 'jquery' );
        // Enqueue Magnific Popup CSS
        wp_enqueue_style( 'magnific-popup', '//cdnjs.cloudflare.com/ajax/libs/magnific-popup.js/1.1.0/magnific-popup.min.css', array(), '1.1.0' );
    }
}


/**
 * Get Payment table for each acoount in checkout page
 */
function mbok_get_account_table($bacs_account){

    $account_name = $bacs_account['account_name'] ?? '';
    $account_number = $bacs_account['account_number'] ?? '';
    $bank_branch = $bacs_account['bank_branch'] ?? '';
    $phone_number = $bacs_account['phone_number'] ?? '';

    $account_table = file_get_contents(MBOK_PLUGIN_DIR . '/src/account_table.html');
    $account_table = str_replace('[account-name]', $account_name, $account_table);
    $account_table = str_replace('[account-number]', $account_number, $account_table);
    $account_table = str_replace('[bank-branch]', $bank_branch, $account_table);
    $account_table = str_replace('[phone-number]', $phone_number, $account_table);
    return $account_table;
}