<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  vmpayment.Paysecure
 *
 * @copyright   (C) 2025 PaySecure
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

// Require VirtueMart payment plugin class
if (!class_exists('vmPSPlugin')) {
    require(JPATH_VM_PLUGINS . '/vmpsplugin.php');
}

class plgVmpaymentPaysecure extends vmPSPlugin
{
    /**
     * Load plugin language files automatically
     * @var boolean
     */
    protected $autoloadLanguage = true;

    /**
     * Constructor
     */
    public function __construct(&$subject, $config = array())
    {
        parent::__construct($subject, $config);
        
        // Set up enhanced logging
        if ($this->params->get('log_enabled', 1)) {
            JLoader::register('Log', JPATH_LIBRARIES . '/joomla/log/log.php');
            JLog::addLogger(
                [
                    'text_file' => 'plg_vmpayment_paysecure.log.php',
                    'text_entry_format' => '{DATETIME} {PRIORITY} {MESSAGE}'
                ],
                JLog::ALL,
                ['plg_vmpayment_paysecure']
            );
        }
    }

    /**
     * Create the plugin table
     */
    protected function getVmPluginCreateTableSQL()
    {
        return 'CREATE TABLE IF NOT EXISTS `' . $this->_tablename . '` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `virtuemart_order_id` int(11) DEFAULT NULL,
            `order_number` char(32) DEFAULT NULL,
            `transaction_id` char(64) DEFAULT NULL,
            `payment_name` varchar(255) DEFAULT NULL,
            `payment_currency` char(3) DEFAULT NULL,
            `payment_amount` decimal(15,5) DEFAULT NULL,
            `payment_status` char(32) DEFAULT NULL,
            `payment_response` text DEFAULT NULL,
            `created_on` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `virtuemart_order_id` (`virtuemart_order_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
    }

    /**
     * Fields to render in VirtueMart configuration
     */
    function getTableSQLFields()
    {
        return [
            'id' => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(11) UNSIGNED',
            'order_number' => 'char(64)',
            'transaction_id' => 'char(64)',
            'payment_name' => 'varchar(255)',
            'payment_currency' => 'char(3)',
            'payment_amount' => 'decimal(15,5)',
            'payment_status' => 'char(32)',
            'payment_response' => 'text',
            'created_on' => 'datetime'
        ];
    }

    /**
     * Display payment in checkout
     */
    function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn)
    {
        return $this->displayListFE($cart, $selected, $htmlIn);
    }

    /**
     * Check payment conditions
     */
    function plgVmOnSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
    {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    /**
     * Process payment when order is confirmed
     */
    function plgVmConfirmedOrder($cart, $order)
    {
        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return null;
        }

        try {
            // Prepare payment data with enhanced validation
            $paymentData = $this->preparePaymentData($order);
            
            // Generate payment request with proper formatting
            $request = $this->generatePaymentRequest($paymentData, $method);
            
            // Store initial transaction data
            $this->storeInternalData($order, $request);
            
            // Process payment with PaySecure API
            $response = $this->processPayment($request, $method);
            
            // Handle API response and redirect
            return $this->handleApiResponse($response, $order);
            
        } catch (Exception $e) {
            $this->handlePaymentError($e, $order);
            return false;
        }
    }

    /**
     * Prepare payment data with validation
     */
    protected function preparePaymentData($order)
    {
        if ($order['details']['BT']->order_total <= 0) {
            throw new Exception('Invalid order amount');
        }

        return [
            'amount' => (float)$order['details']['BT']->order_total,
            'currency' => (string)$order['details']['BT']->order_currency,
            'order_id' => (string)$order['details']['BT']->order_number,
            'customer' => [
                'name' => (string)$order['details']['BT']->first_name . ' ' . (string)$order['details']['BT']->last_name,
                'email' => (string)$order['details']['BT']->email,
                'phone' => (string)$order['details']['BT']->phone_1
            ],
            'billing_address' => $this->getBillingAddress($order['details']['BT']),
            'products' => $this->getOrderProducts($order)
        ];
    }

    /**
     * Get order products for API request
     */
    protected function getOrderProducts($order)
    {
        $products = [];
        if (isset($order['items']) && is_array($order['items'])) {
            foreach ($order['items'] as $item) {
                $products[] = [
                    'name' => (string)$item->order_item_name,
                    'price' => (float)$item->product_final_price,
                    'quantity' => (int)$item->product_quantity,
                    'sku' => (string)$item->order_item_sku
                ];
            }
        }
        return $products;
    }

    /**
     * Generate properly formatted payment request for PaySecure
     */
    /**
 * Generate properly formatted payment request for PaySecure redirect
 */
protected function generatePaymentRequest($data, $method)
{
    $sandbox = (bool)$this->params->get('sandbox_mode', 1);
    
    $request = [
        'merchant_id' => (string)$this->params->get('merchant_id'),
        'brand_id' => (string)$this->params->get('brand_id', $this->params->get('merchant_id')),
        'order_id' => (string)$data['order_id'],
        'amount' => number_format((float)$data['amount'], 2, '.', ''),
        'currency' => strtoupper((string)$data['currency']),
        'customer_name' => (string)$data['customer']['name'],
        'customer_email' => (string)$data['customer']['email'],
        'success_redirect' => JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' . $data['order_id'],
        'failure_redirect' => JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel&on=' . $data['order_id'],
        'callback_url' => JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&pm=' . $method->virtuemart_paymentmethod_id,
        'timestamp' => time()
    ];

    return $request;
}

    /**
     * Process payment with PaySecure API
     */
    /**
 * Process payment with PaySecure API (modified for redirect)
 */
protected function processPayment($request, $method)
{
    $sandbox = (bool)$this->params->get('sandbox_mode', 1);
    
    // Determine base URL based on sandbox mode
    $baseUrl = $sandbox 
        ? 'https://staging.paysecure.net/api/v1/purchases/'
        : 'https://api.paysecure.net/api/v1/purchases/';
    
    // Build the redirect URL with all necessary parameters
    $redirectUrl = $baseUrl . '?' . http_build_query([
        'merchant_id' => $request['merchant_id'],
        'brand_id' => $request['brand_id'],
        'order_id' => $request['order_id'],
        'amount' => $request['amount'],
        'currency' => $request['currency'],
        'success_redirect' => $request['success_redirect'],
        'failure_redirect' => $request['failure_redirect'],
        'callback_url' => $request['callback_url'],
        'timestamp' => $request['timestamp']
    ]);
    
    // For security, you might want to add a signature
    $apiKey = trim($this->params->get('api_key'));
    if (!empty($apiKey)) {
        $signature = hash_hmac('sha256', $request['order_id'].$request['amount'].$request['currency'], $apiKey);
        $redirectUrl .= '&signature=' . urlencode($signature);
    }
    
    JLog::add('Generated PaySecure redirect URL: ' . $redirectUrl, JLog::INFO, 'plg_vmpayment_paysecure');
    
    return [
        'status' => 200,
        'payment_url' => $redirectUrl
    ];
}

    /**
     * Handle API response and redirect
     */
    /**
 * Handle API response and redirect (modified for direct redirect)
 */
protected function handleApiResponse($response, $order)
{
    JLog::add('Processing redirect response: ' . print_r($response, true), JLog::DEBUG, 'plg_vmpayment_paysecure');
    
    // Store initial transaction data
    $this->storePSPluginInternalData([
        'virtuemart_order_id' => $order['details']['BT']->virtuemart_order_id,
        'payment_response' => json_encode($response),
        'payment_status' => 'REDIRECTED'
    ]);

    if ($response['status'] == 200 && !empty($response['payment_url'])) {
        // Validate the payment URL
        $paymentUrl = filter_var($response['payment_url'], FILTER_VALIDATE_URL);
        if ($paymentUrl === false) {
            JLog::add('Invalid payment URL generated: ' . $response['payment_url'], JLog::ERROR, 'plg_vmpayment_paysecure');
            throw new Exception('Invalid payment URL generated');
        }
        
        JLog::add('Redirecting to PaySecure payment page: ' . $paymentUrl, JLog::INFO, 'plg_vmpayment_paysecure');
        
        // Clear any previous output
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Perform the redirect
        header('Location: ' . $paymentUrl);
        exit;
    }
    
    throw new Exception('Failed to generate payment redirect URL');
}
    /**
     * Enhanced error handling
     */
    protected function handlePaymentError($e, $order)
    {
        $errorMessage = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        JLog::add('Payment error: ' . $errorMessage, JLog::ERROR, 'plg_vmpayment_paysecure');
        
        // Update order status to failed
        $modelOrder = VmModel::getModel('orders');
        $order['order_status'] = 'X'; // Cancelled
        $order['customer_notified'] = 1;
        $order['comments'] = 'Payment failed: ' . $errorMessage;
        $modelOrder->updateStatusForOneOrder($order['details']['BT']->virtuemart_order_id, $order, true);
        
        // Show error to user
        $app = JFactory::getApplication();
        $app->enqueueMessage(JText::sprintf('COM_VIRTUEMART_CART_ORDERDONE_ERROR', $errorMessage), 'error');
        $app->redirect(JRoute::_('index.php?option=com_virtuemart&view=cart', false));
    }

    /**
     * Handle payment response (success/failure)
     */
    function plgVmOnPaymentResponseReceived()
    {
        $app = JFactory::getApplication();
        $input = $app->input;
        
        // Get order ID from request
        $orderNumber = $input->get('on', '', 'STRING');
        if (empty($orderNumber)) {
            return false;
        }
        
        // Load order
        $modelOrder = VmModel::getModel('orders');
        $order = $modelOrder->getOrder($orderNumber);
        
        if (!$order) {
            return false;
        }
        
        // Check if payment was successful
        $success = $input->get('issuccess', false, 'BOOLEAN');
        $transactionId = $input->get('transaction_id', '', 'STRING');
        $paymentStatus = $input->get('payment_status', '', 'STRING');
        
        if ($success && !empty($transactionId)) {
            // Payment successful
            $order['order_status'] = 'C'; // Confirmed
            $order['customer_notified'] = 1;
            $order['comments'] = 'Payment processed successfully via PaySecure (Transaction ID: ' . $transactionId . ')';
            
            // Update transaction data
            $dbValues = [
                'virtuemart_order_id' => $order['details']['BT']->virtuemart_order_id,
                'order_number' => $orderNumber,
                'transaction_id' => $transactionId,
                'payment_status' => strtoupper($paymentStatus ?: 'COMPLETED')
            ];
            $this->storePSPluginInternalData($dbValues);
            
            // Empty cart
            $cart = VirtueMartCart::getCart();
            $cart->emptyCart();
            
            // Redirect to order confirmation page
            $app->redirect(JRoute::_('index.php?option=com_virtuemart&view=orders&layout=details&order_number=' . $orderNumber, false));
        } else {
            // Payment failed
            $order['order_status'] = 'X'; // Cancelled
            $order['customer_notified'] = 1;
            $order['comments'] = 'Payment failed via PaySecure';
            
            // Update transaction data
            $dbValues = [
                'virtuemart_order_id' => $order['details']['BT']->virtuemart_order_id,
                'order_number' => $orderNumber,
                'payment_status' => 'FAILED'
            ];
            $this->storePSPluginInternalData($dbValues);
            
            // Redirect to cart with error message
            $app->enqueueMessage(JText::_('COM_VIRTUEMART_CART_ORDERDONE_ERROR_UNKNOWN'), 'error');
            $app->redirect(JRoute::_('index.php?option=com_virtuemart&view=cart', false));
        }
        
        return true;
    }

    /**
     * Handle payment notification (callback)
     */
    function plgVmOnPaymentNotification()
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            JLog::add('Invalid JSON in notification: ' . json_last_error_msg(), JLog::ERROR, 'plg_vmpayment_paysecure');
            header("HTTP/1.0 400 Bad Request");
            exit;
        }

        JLog::add('Received notification: ' . print_r($data, true), JLog::INFO, 'plg_vmpayment_paysecure');

        if (!$this->verifyNotification($data)) {
            JLog::add('Notification verification failed', JLog::ERROR, 'plg_vmpayment_paysecure');
            header("HTTP/1.0 403 Forbidden");
            exit;
        }

        try {
            $modelOrder = VmModel::getModel('orders');
            $order = $modelOrder->getOrder($data['order_id']);
            
            if ($order) {
                $this->updateOrderFromNotification($order, $data);
            }
        } catch (Exception $e) {
            JLog::add('Notification processing error: ' . $e->getMessage(), JLog::ERROR, 'plg_vmpayment_paysecure');
        }

        echo 'OK';
        JFactory::getApplication()->close();
    }

    /**
     * Verify notification signature
     */
    protected function verifyNotification($data)
    {
        if (empty($data['signature']) || empty($data['order_id']) || empty($data['transaction_id'])) {
            return false;
        }
        
        $apiKey = $this->params->get('api_key');
        $expectedSignature = hash_hmac('sha256', $data['order_id'] . $data['transaction_id'], $apiKey);
        
        return hash_equals($expectedSignature, $data['signature']);
    }

    /**
     * Update order from notification
     */
    protected function updateOrderFromNotification($order, $data)
    {
        $statusMap = [
            'completed' => 'C',
            'failed' => 'X',
            'pending' => 'P',
            'refunded' => 'R'
        ];
        
        $vmStatus = $statusMap[strtolower($data['status'])] ?? 'X';
        
        $dbValues = [
            'virtuemart_order_id' => $order['details']['BT']->virtuemart_order_id,
            'order_number' => $order['details']['BT']->order_number,
            'transaction_id' => $data['transaction_id'],
            'payment_status' => strtoupper($data['status'])
        ];
        $this->storePSPluginInternalData($dbValues);
        
        $modelOrder = VmModel::getModel('orders');
        $order['order_status'] = $vmStatus;
        $order['comments'] = 'Payment status updated via notification: ' . $data['status'];
        $modelOrder->updateStatusForOneOrder($order['details']['BT']->virtuemart_order_id, $order);
    }

    /**
     * Get properly formatted billing address
     */
    protected function getBillingAddress($orderDetails)
    {
        return [
            'address_1' => (string)$orderDetails->address_1,
            'address_2' => isset($orderDetails->address_2) ? (string)$orderDetails->address_2 : '',
            'city' => (string)$orderDetails->city,
            'state' => (string)$orderDetails->virtuemart_state_id,
            'zip' => (string)$orderDetails->zip,
            'country' => (string)$orderDetails->virtuemart_country_id
        ];
    }

    /**
     * Store transaction data
     */
    protected function storeInternalData($order, $request)
    {
        $dbValues = [
            'virtuemart_order_id' => $order['details']['BT']->virtuemart_order_id,
            'order_number' => $order['details']['BT']->order_number,
            'payment_name' => 'PaySecure',
            'payment_currency' => $request['currency'],
            'payment_amount' => $request['amount'],
            'payment_status' => 'PENDING',
            'created_on' => date('Y-m-d H:i:s')
        ];
        
        $this->storePSPluginInternalData($dbValues);
    }
}