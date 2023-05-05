<?php
/**
 * Sudan Payment Gateway
 *
 * @class   WC_Sudan_Payment_Gateway
 * @extends	WC_Payment_Gateway
 */

if (!class_exists('WC_Sudan_Payment_Gateway')) {
	class WC_Sudan_Payment_Gateway extends WC_Payment_Gateway
	{

		public $domain;

		/**
		 * Constructor for the gateway.
		 */
		public function __construct()
		{
			$this->id = 'sudan_payment_gateway';
			$this->icon = apply_filters('woocommerce_offline_icon', '');
			$this->has_fields = false;
			$this->method_title = __('Sudan Payment Gateway', 'wc-sudan-gateway');
			$this->method_description = __('Bank transfer through Sudanese Banks and upload the receipt.', 'wc-sudan-gateway');

			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables
			$this->title = $this->get_option('title');
			$this->description = $this->get_option('description');
			$this->instructions = $this->get_option('instructions');
			$this->require_trx = $this->get_option('require_trx');
			
			// BACS account fields shown on the checkout page and in admin configuration tab.
			$this->account_details = get_option(
				'woocommerce_bacs_accounts',
				array(
					array(
						'account_name' => $this->get_option('account_name'),
						'account_number' => $this->get_option('account_number'),
						'phone_number' => $this->get_option('phone_number'),
						'bank_branch' => $this->get_option('bank_branch'),
					),
				)
			);

			// Actions
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'save_account_details'));
			add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));

			// Customer Emails
			add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
		}


		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields()
		{

			$this->form_fields = apply_filters(
				'wc_offline_form_fields',
				array(

					'enabled' => array(
						'title' => __('Enable/Disable', 'wc-sudan-gateway'),
						'type' => 'checkbox',
						'label' => __('Enable Payment', 'wc-sudan-gateway'),
						'default' => 'yes'
					),

					'title' => array(
						'title' => __('Title', 'wc-sudan-gateway'),
						'type' => 'text',
						'description' => __('This controls the title for the payment method the customer sees during checkout.', 'wc-sudan-gateway'),
						'default' => __('Pay with your Sudanese banks', 'wc-sudan-gateway'),
						'desc_tip' => true,
					),

					'description' => array(
						'title' => __('Description', 'wc-sudan-gateway'),
						'type' => 'textarea',
						'description' => __('Payment method description that the customer will see on your checkout.', 'wc-sudan-gateway'),
						'default' => __('Pay through your bank to the account (or one of the accounts) shown below, then send us a copy of the transfer invoice to be verified by our team', 'wc-sudan-gateway'),
						'desc_tip' => true,
					),

					'require_trx' => array(
						'title' => __('Require Transaction ID TRX?', 'wc-sudan-gateway'),
						'type' => 'checkbox',
						'label' => __('Require Transaction ID TRX?', 'wc-sudan-gateway'),
						'default' => 'no'
					),

					'instructions' => array(
						'title' => __('Instructions', 'wc-sudan-gateway'),
						'type' => 'textarea',
						'default' => '',
						'desc_tip' => true,
					),

					'account_details' => array(
						'type' => 'account_details',
					),
				)
			);
		}

		/**
		 * Save account details table.
		 */
		public function save_account_details()
		{

			$accounts = array();

			// phpcs:disable WordPress.Security.NonceVerification.NoNonceVerification -- Nonce verification already handled in WC_Admin_Settings::save()
			if (
				isset($_POST['bacs_account_name']) && isset($_POST['bacs_account_number']) && isset($_POST['bacs_bank_branch'])
				&& isset($_POST['bacs_phone_number'])
			) {

				$account_names = wc_clean(wp_unslash($_POST['bacs_account_name']));
				$account_numbers = wc_clean(wp_unslash($_POST['bacs_account_number']));
				$bank_branchs = wc_clean(wp_unslash($_POST['bacs_bank_branch']));
				$phone_numbers = wc_clean(wp_unslash($_POST['bacs_phone_number']));

				foreach ($account_names as $i => $name) {
					if (!isset($account_names[$i])) {
						continue;
					}

					$accounts[] = array(
						'account_name' => $account_names[$i],
						'account_number' => $account_numbers[$i],
						'bank_branch' => $bank_branchs[$i],
						'phone_number' => $phone_numbers[$i],
					);
				}
			}

			update_option('woocommerce_bacs_accounts', $accounts);
		}

		/**
		 * Generate account details html.
		 *
		 * @return string
		 */
		public function generate_account_details_html()
		{

			ob_start();

			$country = WC()->countries->get_base_country();


			?>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<?php esc_html_e('Accounts details', 'wc-sudan-gateway'); ?>
				</th>
				<td class="forminp" id="bacs_accounts">
					<div class="wc_input_table_wrapper">
						<table class="widefat wc_input_table sortable" cellspacing="0">
							<thead>
								<tr>
									<th class="sort">&nbsp;</th>
									<th>
										<?php esc_html_e('Account name', 'wc-sudan-gateway'); ?>
									</th>
									<th>
										<?php esc_html_e('Account number', 'wc-sudan-gateway'); ?>
									</th>
									<th>
										<?php esc_html_e('Bank Branch', 'wc-sudan-gateway'); ?>
									</th>
									<th>
										<?php esc_html_e('Phone Number', 'wc-sudan-gateway'); ?>
									</th>
								</tr>
							</thead>
							<tbody class="accounts">
								<?php
								$i = -1;
								if ($this->account_details) {
									foreach ($this->account_details as $account) {
										$i++;
									?>
									<tr class="account">
										<td class="sort"></td>
										<td><input type="text" value="<?php echo esc_attr(wp_unslash($account['account_name']));?>" name="bacs_account_name[<?php echo esc_attr($i);?>]" /></td>
										<td><input type="text" value="<?php echo esc_attr($account['account_number']);?>" name="bacs_account_number[<?php echo esc_attr($i);?>]" /></td>
										<td><input type="text" value="<?php echo esc_attr(wp_unslash($account['bank_branch']));?>" name="bacs_bank_branch[<?php echo esc_attr($i);?>]" /></td>
										<td><input type="text" value="<?php echo esc_attr($account['phone_number']);?>" name="bacs_phone_number[<?php echo esc_attr($i);?>]" /></td>
									</tr>
								<?php
									}
								}
								?>
							</tbody>
							<tfoot>
								<tr>
									<th colspan="7"><a href="#" class="add button">
											<?php esc_html_e('+ Add account', 'wc-sudan-gateway'); ?>
										</a> <a href="#" class="remove_rows button">
											<?php esc_html_e('Remove selected account(s)', 'wc-sudan-gateway'); ?>
										</a></th>
								</tr>
							</tfoot>
						</table>
					</div>
					<script type="text/javascript">
						jQuery(function () {
							jQuery('#bacs_accounts').on('click', 'a.add', function () {

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
		private function bank_details($order_id = '')
		{

			if (empty($this->account_details)) {
				return;
			}

			// Get order and store in $order.
			$order = wc_get_order($order_id);

			$bacs_accounts = apply_filters('woocommerce_bacs_accounts', $this->account_details);

			if (!empty($bacs_accounts)) {
				?>
				<section class="woocommerce-bacs-bank-details">
					<?php
					foreach ($bacs_accounts as $bacs_account) {

						$account_name = $bacs_account['account_name'] ?? '';
						$account_number = $bacs_account['account_number'] ?? '';
						$bank_branch = $bacs_account['bank_branch'] ?? '';
						$phone_number = $bacs_account['phone_number'] ?? '';

						?>
						<table class="bank-table">
							<thead>
								<tr>
									<td colspan="2">
										<div class="td-inner td-name">
											<div>
												<?php echo esc_html($account_name); ?>
											</div>
											<div>
												<div class="account-number"><span class="copy">
														<?php echo esc_html($account_number); ?>
													</span><span class="copied"><?php echo _e('copied', 'wc-sudan-gateway')?></span></div>
											</div>
										</div>
									</td>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td>
										<div>
											<div class="td-inner">
												<?php echo esc_html($bank_branch); ?>
											</div>
										</div>
									</td>
									<td>
										<div>
											<div class="td-inner">
												<?php echo esc_html($phone_number); ?>
											</div>
										</div>
									</td>
								</tr>
							</tbody>
						</table>
						<?php
					}
					?>
				</section>
				<?php
			}

		}

		public function payment_fields()
		{

			if ($description = $this->get_description()) {
				echo wpautop(wptexturize(esc_html($description)));
			}

			$this->bank_details();

			?>
			<div id="mbokReceipt">
				<?php if ($this->require_trx == 'yes'): ?>
					<div class="form-group form-group-trx">
						<label for="bank_payment_trx" class="">
							<?php _e('Enter the bank payment TRX', 'wc-sudan-gateway'); ?>
						</label>
						<input type="text" name="bank_payment_trx" class="bank_payment_trx" required>
					</div>
					<hr style="border-color: white;">
				<?php endif; ?>
				<div class="form-group">
					<label for="bank_payment_receipt" class="payment-receipt-btn"><i class="fa-solid fa-upload"></i>
						<?php _e('Upload the receipt image', 'wc-sudan-gateway'); ?>
					</label>
					<input type="file" id="bank_payment_receipt"
						onclick="this.value=null;document.getElementById('receiptPreview').src = ''"
						onchange="document.getElementById('receiptPreview').src = window.URL.createObjectURL(this.files[0])"
						required>
				</div>
				<div class="form-group receipt-preview">
					<img id="receiptPreview" />
				</div>
				<input type="hidden" name="attach_id" class="attach_id">
			</div>
			<?php
		}

		public function validate_fields()
		{
			if (
				(!isset($_POST['attach_id']) || empty($_POST['attach_id'])) ||
				(!is_numeric($_POST['attach_id'])) ||
				(($this->require_trx == 'yes') && (!isset($_POST['bank_payment_trx']) || empty($_POST['bank_payment_trx'])))
			) {
				wc_add_notice(__('<strong>mBok</strong> Please insert Receipt Image and TRX number correctly', 'wc-sudan-gateway'), 'error');
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
				echo wpautop(wptexturize(esc_attr($this->instructions)));
			}
		}


		/**
		 * Add content to the WC emails.
		 *
		 */
		public function email_instructions($order, $sent_to_admin, $plain_text = false)
		{

			if ($this->instructions && !$sent_to_admin && $this->id === $order->payment_method && $order->has_status('processing')) {
				echo wpautop(wptexturize(esc_attr($this->instructions)) . PHP_EOL);
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
			$order->update_status('processing', __('Processing Payment Confirmation', 'wc-sudan-gateway'));

			// Reduce stock levels
			$order->reduce_order_stock();

			// Remove cart
			WC()->cart->empty_cart();

			// Return thankyou redirect
			return array(
				'result' => 'success',
				'redirect' => $this->get_return_url($order)
			);
		}
	}
}