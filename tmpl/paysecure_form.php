<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Payment.Paysecure
 *
 * @copyright   (C) 2025 PaySecure
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Session\Session;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;

// Load required JS assets
HTMLHelper::_('jquery.framework');
HTMLHelper::_('script', 'plg_payment_paysecure/paysecure.js', ['version' => 'auto', 'relative' => true], ['defer' => true]);
HTMLHelper::_('behavior.core');
HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('stylesheet', 'plg_payment_paysecure/paysecure.css', ['version' => 'auto', 'relative' => true]);

$uniqueId = 'paysecure-form-' . uniqid();
$token    = Session::getFormToken();
$sandbox  = $this->params->get('sandbox_mode', 1);
$amount   = number_format((float) $this->amount, 2, '.', '');
$app      = Factory::getApplication();
$document = $app->getDocument();
$nonce    = $app->get('csp_nonce');

// Add script options
$document->addScriptOptions('paysecure', [
    'token' => $token,
    'nonce' => $nonce,
    'sandbox' => $sandbox,
    'baseUrl' => Uri::base(),
    'orderId' => $this->order_id,
    'currency' => $this->currency,
    'amount' => $amount
]);

// Get countries if not already set
if (!isset($this->countries) || !is_array($this->countries)) {
    $this->countries = [
        'US' => Text::_('PLG_PAYMENT_PAYSECURE_COUNTRY_US'),
        'GB' => Text::_('PLG_PAYMENT_PAYSECURE_COUNTRY_GB'),
        'CA' => Text::_('PLG_PAYMENT_PAYSECURE_COUNTRY_CA'),
        'AU' => Text::_('PLG_PAYMENT_PAYSECURE_COUNTRY_AU')
        // Add more countries as needed
    ];
}

// Set default return URL if not provided
if (empty($this->return_url)) {
    $this->return_url = Uri::base();
}
?>

<div id="<?php echo $uniqueId; ?>" class="paysecure-payment-form" data-nonce="<?php echo $nonce; ?>">
    <?php if ($sandbox) : ?>
        <div class="alert alert-warning mb-3 d-flex align-items-center">
            <span class="icon-exclamation-triangle me-2" aria-hidden="true"></span>
            <div><?php echo Text::_('PLG_PAYMENT_PAYSECURE_SANDBOX_ACTIVE'); ?></div>
        </div>
    <?php endif; ?>
    
    <div class="payment-errors alert alert-danger d-none mb-3"></div>
    
    <!-- Payment Method Selection -->
    <div class="payment-method-selector mb-4">
        <div class="h5 mb-3"><?php echo Text::_('PLG_PAYMENT_PAYSECURE_PAYMENT_METHOD'); ?></div>
        <div class="btn-group w-100" role="group" aria-label="<?php echo Text::_('PLG_PAYMENT_PAYSECURE_PAYMENT_METHOD'); ?>">
            <input type="radio" class="btn-check" name="paymentMethod" id="paymentMethodCard" autocomplete="off" checked>
            <label class="btn btn-outline-primary" for="paymentMethodCard">
                <span class="icon-credit-card me-1" aria-hidden="true"></span>
                <?php echo Text::_('PLG_PAYMENT_PAYSECURE_METHOD_CARD'); ?>
            </label>
            
            <?php if ($this->params->get('enable_banktransfer', 1)) : ?>
                <input type="radio" class="btn-check" name="paymentMethod" id="paymentMethodBank" autocomplete="off">
                <label class="btn btn-outline-primary" for="paymentMethodBank">
                    <span class="icon-bank me-1" aria-hidden="true"></span>
                    <?php echo Text::_('PLG_PAYMENT_PAYSECURE_METHOD_BANK'); ?>
                </label>
            <?php endif; ?>
            
            <?php if ($this->params->get('enable_ewallet', 1)) : ?>
                <input type="radio" class="btn-check" name="paymentMethod" id="paymentMethodWallet" autocomplete="off">
                <label class="btn btn-outline-primary" for="paymentMethodWallet">
                    <span class="icon-wallet me-1" aria-hidden="true"></span>
                    <?php echo Text::_('PLG_PAYMENT_PAYSECURE_METHOD_WALLET'); ?>
                </label>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Credit Card Payment Form -->
    <div class="credit-card-form payment-method-content" id="creditCardForm">
        <!-- Payment Details -->
        <div class="mb-3">
            <label for="cardholder-name" class="form-label required">
                <?php echo Text::_('PLG_PAYMENT_PAYSECURE_CARDHOLDER_NAME'); ?>
                <span class="required" aria-hidden="true">*</span>
            </label>
            <input type="text" id="cardholder-name" class="form-control" 
                   placeholder="<?php echo Text::_('PLG_PAYMENT_PAYSECURE_NAME_PLACEHOLDER'); ?>"
                   required
                   aria-required="true"
                   aria-label="<?php echo Text::_('PLG_PAYMENT_PAYSECURE_CARDHOLDER_NAME'); ?>"
                   autocomplete="cc-name">
        </div>
        
        <div class="mb-3">
            <label for="card-number" class="form-label required">
                <?php echo Text::_('PLG_PAYMENT_PAYSECURE_CARD_NUMBER'); ?>
                <span class="required" aria-hidden="true">*</span>
            </label>
            <div class="input-group">
                <input type="tel" id="card-number" class="form-control" 
                       placeholder="4242 4242 4242 4242" 
                       required
                       aria-required="true"
                       aria-label="<?php echo Text::_('PLG_PAYMENT_PAYSECURE_CARD_NUMBER'); ?>"
                       autocomplete="cc-number"
                       data-paysecure="number">
                <span class="input-group-text card-type-container d-none">
                    <span class="card-type-icon" id="card-type-icon" aria-hidden="true"></span>
                    <span class="visually-hidden" id="card-type-name"></span>
                </span>
            </div>
            <div class="form-text text-muted small mt-1"><?php echo Text::_('PLG_PAYMENT_PAYSECURE_CARD_NUMBER_HELP'); ?></div>
        </div>
        
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label for="expiry-date" class="form-label required">
                    <?php echo Text::_('PLG_PAYMENT_PAYSECURE_EXPIRY_DATE'); ?>
                    <span class="required" aria-hidden="true">*</span>
                </label>
                <input type="tel" id="expiry-date" class="form-control" 
                       placeholder="MM/YY" 
                       required
                       aria-required="true"
                       aria-label="<?php echo Text::_('PLG_PAYMENT_PAYSECURE_EXPIRY_DATE'); ?>"
                       autocomplete="cc-exp"
                       data-paysecure="expiry">
                <div class="form-text text-muted small mt-1"><?php echo Text::_('PLG_PAYMENT_PAYSECURE_EXPIRY_DATE_HELP'); ?></div>
            </div>
            <div class="col-md-6">
                <label for="cvc" class="form-label required">
                    <?php echo Text::_('PLG_PAYMENT_PAYSECURE_CVC'); ?>
                    <span class="required" aria-hidden="true">*</span>
                    <span class="ms-1" data-bs-toggle="tooltip" 
                          title="<?php echo Text::_('PLG_PAYMENT_PAYSECURE_CVC_HELP'); ?>">
                        <span class="icon-question-sign" aria-hidden="true"></span>
                        <span class="visually-hidden"><?php echo Text::_('PLG_PAYMENT_PAYSECURE_CVC_HELP'); ?></span>
                    </span>
                </label>
                <div class="input-group">
                    <input type="tel" id="cvc" class="form-control" 
                           placeholder="123" 
                           required
                           aria-required="true"
                           aria-label="<?php echo Text::_('PLG_PAYMENT_PAYSECURE_CVC'); ?>"
                           autocomplete="cc-csc"
                           data-paysecure="cvc">
                    <span class="input-group-text">
                        <span class="icon-lock" aria-hidden="true"></span>
                    </span>
                </div>
                <div class="form-text text-muted small mt-1"><?php echo Text::_('PLG_PAYMENT_PAYSECURE_CVC_HELP'); ?></div>
            </div>
        </div>
    </div>
    
    <!-- Bank Transfer Form (Hidden by default) -->
    <?php if ($this->params->get('enable_banktransfer', 1)) : ?>
    <div class="bank-transfer-form payment-method-content d-none" id="bankTransferForm">
        <div class="alert alert-info">
            <?php echo Text::_('PLG_PAYMENT_PAYSECURE_BANK_TRANSFER_INFO'); ?>
        </div>
        <div class="bank-details">
            <div class="mb-3">
                <label class="form-label"><?php echo Text::_('PLG_PAYMENT_PAYSECURE_BANK_NAME'); ?></label>
                <div class="form-control-plaintext"><?php echo $this->params->get('bank_name', 'Global Bank Inc.'); ?></div>
            </div>
            <div class="mb-3">
                <label class="form-label"><?php echo Text::_('PLG_PAYMENT_PAYSECURE_ACCOUNT_NAME'); ?></label>
                <div class="form-control-plaintext"><?php echo $this->params->get('account_name', 'PaySecure Payments'); ?></div>
            </div>
            <div class="mb-3">
                <label class="form-label"><?php echo Text::_('PLG_PAYMENT_PAYSECURE_ACCOUNT_NUMBER'); ?></label>
                <div class="form-control-plaintext"><?php echo $this->params->get('account_number', '123456789'); ?></div>
            </div>
            <div class="mb-3">
                <label class="form-label"><?php echo Text::_('PLG_PAYMENT_PAYSECURE_SWIFT_CODE'); ?></label>
                <div class="form-control-plaintext"><?php echo $this->params->get('swift_code', 'GLBALUS33'); ?></div>
            </div>
            <div class="mb-3">
                <label class="form-label"><?php echo Text::_('PLG_PAYMENT_PAYSECURE_REFERENCE'); ?></label>
                <div class="form-control-plaintext font-monospace">ORDER-<?php echo $this->order_id; ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- E-Wallet Form (Hidden by default) -->
    <?php if ($this->params->get('enable_ewallet', 1)) : ?>
    <div class="ewallet-form payment-method-content d-none" id="eWalletForm">
        <div class="alert alert-info">
            <?php echo Text::_('PLG_PAYMENT_PAYSECURE_EWALLET_INFO'); ?>
        </div>
        <div class="mb-3">
            <label for="ewallet-phone" class="form-label required">
                <?php echo Text::_('PLG_PAYMENT_PAYSECURE_PHONE_NUMBER'); ?>
                <span class="required" aria-hidden="true">*</span>
            </label>
            <input type="tel" id="ewallet-phone" class="form-control" 
                   placeholder="<?php echo Text::_('PLG_PAYMENT_PAYSECURE_PHONE_PLACEHOLDER'); ?>"
                   required
                   aria-required="true"
                   aria-label="<?php echo Text::_('PLG_PAYMENT_PAYSECURE_PHONE_NUMBER'); ?>"
                   autocomplete="tel">
        </div>
        <div class="mb-3">
            <label for="ewallet-provider" class="form-label required">
                <?php echo Text::_('PLG_PAYMENT_PAYSECURE_EWALLET_PROVIDER'); ?>
                <span class="required" aria-hidden="true">*</span>
            </label>
            <select id="ewallet-provider" class="form-select" required aria-required="true"
                    aria-label="<?php echo Text::_('PLG_PAYMENT_PAYSECURE_EWALLET_PROVIDER'); ?>">
                <option value=""><?php echo Text::_('PLG_PAYMENT_PAYSECURE_SELECT_PROVIDER'); ?></option>
                <option value="paypal">PayPal</option>
                <option value="applepay">Apple Pay</option>
                <option value="googlepay">Google Pay</option>
                <option value="amazonpay">Amazon Pay</option>
            </select>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Billing Address -->
    <div class="billing-address mt-4">
        <h5 class="mb-3"><?php echo Text::_('PLG_PAYMENT_PAYSECURE_BILLING_ADDRESS'); ?></h5>
        <div class="row g-3">
            <div class="col-md-6">
                <label for="billing-address1" class="form-label required">
                    <?php echo Text::_('PLG_PAYMENT_PAYSECURE_ADDRESS_LINE1'); ?>
                    <span class="required" aria-hidden="true">*</span>
                </label>
                <input type="text" id="billing-address1" class="form-control" 
                       required
                       aria-required="true"
                       aria-label="<?php echo Text::_('PLG_PAYMENT_PAYSECURE_ADDRESS_LINE1'); ?>"
                       autocomplete="address-line1">
            </div>
            <div class="col-md-6">
                <label for="billing-address2" class="form-label">
                    <?php echo Text::_('PLG_PAYMENT_PAYSECURE_ADDRESS_LINE2'); ?>
                </label>
                <input type="text" id="billing-address2" class="form-control" 
                       aria-label="<?php echo Text::_('PLG_PAYMENT_PAYSECURE_ADDRESS_LINE2'); ?>"
                       autocomplete="address-line2">
            </div>
            <div class="col-md-4">
                <label for="billing-city" class="form-label required">
                    <?php echo Text::_('PLG_PAYMENT_PAYSECURE_CITY'); ?>
                    <span class="required" aria-hidden="true">*</span>
                </label>
                <input type="text" id="billing-city" class="form-control" 
                       required
                       aria-required="true"
                       aria-label="<?php echo Text::_('PLG_PAYMENT_PAYSECURE_CITY'); ?>"
                       autocomplete="address-level2">
            </div>
            <div class="col-md-4">
                <label for="billing-state" class="form-label required">
                    <?php echo Text::_('PLG_PAYMENT_PAYSECURE_STATE'); ?>
                    <span class="required" aria-hidden="true">*</span>
                </label>
                <input type="text" id="billing-state" class="form-control" 
                       required
                       aria-required="true"
                       aria-label="<?php echo Text::_('PLG_PAYMENT_PAYSECURE_STATE'); ?>"
                       autocomplete="address-level1">
            </div>
            <div class="col-md-4">
                <label for="billing-zip" class="form-label required">
                    <?php echo Text::_('PLG_PAYMENT_PAYSECURE_ZIP_CODE'); ?>
                    <span class="required" aria-hidden="true">*</span>
                </label>
                <input type="text" id="billing-zip" class="form-control" 
                       required
                       aria-required="true"
                       aria-label="<?php echo Text::_('PLG_PAYMENT_PAYSECURE_ZIP_CODE'); ?>"
                       autocomplete="postal-code">
            </div>
            <div class="col-md-12">
                <label for="billing-country" class="form-label required">
                    <?php echo Text::_('PLG_PAYMENT_PAYSECURE_COUNTRY'); ?>
                    <span class="required" aria-hidden="true">*</span>
                </label>
                <select id="billing-country" class="form-select" 
                        required
                        aria-required="true"
                        aria-label="<?php echo Text::_('PLG_PAYMENT_PAYSECURE_COUNTRY'); ?>"
                        autocomplete="country">
                    <option value=""><?php echo Text::_('PLG_PAYMENT_PAYSECURE_SELECT_COUNTRY'); ?></option>
                    <?php foreach ($this->countries as $code => $name) : ?>
                        <option value="<?php echo $code; ?>" <?php echo $code === 'US' ? 'selected' : ''; ?>>
                            <?php echo $name; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
    
    <!-- Terms and Conditions -->
    <div class="form-check mt-3">
        <input class="form-check-input" type="checkbox" id="terms-accept" required
               aria-label="<?php echo Text::_('PLG_PAYMENT_PAYSECURE_ACCEPT_TERMS'); ?>">
        <label class="form-check-label" for="terms-accept">
            <?php echo Text::sprintf('PLG_PAYMENT_PAYSECURE_ACCEPT_TERMS', 
                '<a href="' . $this->params->get('terms_url', '#') . '" target="_blank" rel="noopener noreferrer">' . 
                Text::_('PLG_PAYMENT_PAYSECURE_TERMS_LINK') . '</a>'); ?>
        </label>
    </div>
    
    <!-- Payment Button -->
    <div class="mt-4">
        <button type="button" class="btn btn-primary w-100 submit-payment py-3" id="paysecure-submit-btn"
                aria-label="<?php echo Text::sprintf('PLG_PAYMENT_PAYSECURE_PAY_NOW_AMOUNT', $amount, $this->currency); ?>">
            <span class="button-text">
                <?php echo Text::sprintf('PLG_PAYMENT_PAYSECURE_PAY_NOW_AMOUNT', $amount, $this->currency); ?>
            </span>
            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
        </button>
    </div>
    
    <!-- Security Badges -->
    <div class="security-badges mt-3 d-flex justify-content-between align-items-center">
        <div class="text-muted small">
            <span class="icon-lock me-1" aria-hidden="true"></span>
            <?php echo Text::_('PLG_PAYMENT_PAYSECURE_SECURE_NOTICE'); ?>
        </div>
        <div class="payment-methods">
            <img src="<?php echo Uri::root(true); ?>/media/plg_payment_paysecure/images/credit-cards.png" 
                 alt="<?php echo Text::_('PLG_PAYMENT_PAYSECURE_ACCEPTED_CARDS'); ?>"
                 class="img-fluid" loading="lazy" width="220" height="28">
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    const form = document.getElementById('<?php echo $uniqueId; ?>');
    const submitBtn = form.querySelector('.submit-payment');
    const spinner = submitBtn.querySelector('.spinner-border');
    const buttonText = submitBtn.querySelector('.button-text');
    const errorDiv = form.querySelector('.payment-errors');
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(form.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Payment method switcher
    const paymentMethodRadios = form.querySelectorAll('input[name="paymentMethod"]');
    const paymentMethodContents = form.querySelectorAll('.payment-method-content');
    
    paymentMethodRadios.forEach(function(radio) {
        radio.addEventListener('change', function() {
            paymentMethodContents.forEach(function(content) {
                content.classList.add('d-none');
            });
            
            const targetForm = form.querySelector('#' + this.id + 'Form');
            if (targetForm) {
                targetForm.classList.remove('d-none');
            }
        });
    });
    
    // Card number formatting and validation
    const cardNumberInput = form.querySelector('#card-number');
    if (cardNumberInput) {
        cardNumberInput.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            value = value.replace(/(\d{4})/g, '$1 ').trim();
            this.value = value.substring(0, 19);
            
            detectCardType(value.replace(/\s/g, ''));
        });
        
        cardNumberInput.addEventListener('blur', function() {
            const validation = validateCardNumber(this.value);
            if (!validation.valid) {
                showErrors([validation.message]);
            }
        });
    }
    
    // Expiry date formatting and validation
    const expiryDateInput = form.querySelector('#expiry-date');
    if (expiryDateInput) {
        expiryDateInput.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length > 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            this.value = value.substring(0, 5);
        });
        
        expiryDateInput.addEventListener('blur', function() {
            const validation = validateExpiryDate(this.value);
            if (!validation.valid) {
                showErrors([validation.message]);
            }
        });
    }
    
    // CVC validation
    const cvcInput = form.querySelector('#cvc');
    if (cvcInput) {
        cvcInput.addEventListener('blur', function() {
            if (!validateCVC(this.value)) {
                showErrors(['<?php echo Text::_('PLG_PAYMENT_PAYSECURE_INVALID_CVC'); ?>']);
            }
        });
    }
    
    // Form submission
    submitBtn.addEventListener('click', async function(e) {
        e.preventDefault();
        
        // Reset errors
        errorDiv.classList.add('d-none');
        errorDiv.innerHTML = '';
        
        // Validate form
        const validation = validateForm();
        if (!validation.valid) {
            showErrors(validation.messages);
            return;
        }
        
        try {
            // Start processing
            toggleLoading(true);
            
            // Get selected payment method
            const selectedMethod = form.querySelector('input[name="paymentMethod"]:checked');
            const paymentMethod = selectedMethod ? selectedMethod.id.replace('paymentMethod', '').toLowerCase() : 'card';
            
            // Prepare payment data
            const paymentData = await preparePaymentData(paymentMethod);
            
            // Process payment
            const response = await processPayment(paymentData);
            
            if (response.redirect) {
                window.location.href = response.redirect;
            } else if (response.success) {
                window.location.href = '<?php echo $this->return_url; ?>';
            } else {
                throw new Error(response.message || '<?php echo Text::_('PLG_PAYMENT_PAYSECURE_PAYMENT_FAILED'); ?>');
            }
        } catch (error) {
            showErrors([error.message]);
        } finally {
            toggleLoading(false);
        }
    });
    
    /**
     * Toggle loading state
     */
    function toggleLoading(loading) {
        if (loading) {
            buttonText.classList.add('d-none');
            spinner.classList.remove('d-none');
            submitBtn.disabled = true;
        } else {
            buttonText.classList.remove('d-none');
            spinner.classList.add('d-none');
            submitBtn.disabled = false;
        }
    }
    
    /**
     * Show error messages
     */
    function showErrors(messages) {
        errorDiv.innerHTML = '';
        
        if (typeof messages === 'string') {
            messages = [messages];
        }
        
        messages.forEach(message => {
            const errorItem = document.createElement('div');
            errorItem.className = 'error-item';
            errorItem.textContent = message;
            errorDiv.appendChild(errorItem);
        });
        
        errorDiv.classList.remove('d-none');
        window.scrollTo({
            top: errorDiv.offsetTop - 100,
            behavior: 'smooth'
        });
    }
    
    /**
     * Validate entire form
     */
    function validateForm() {
        // Get selected payment method
        const selectedMethod = form.querySelector('input[name="paymentMethod"]:checked');
        const paymentMethod = selectedMethod ? selectedMethod.id.replace('paymentMethod', '').toLowerCase() : 'card';
        
        const errors = [];
        const requiredFields = [
            { id: '#terms-accept', message: '<?php echo Text::_('PLG_PAYMENT_PAYSECURE_ACCEPT_TERMS_ERROR'); ?>' },
            { id: '#billing-address1', message: '<?php echo Text::_('PLG_PAYMENT_PAYSECURE_ADDRESS_REQUIRED'); ?>' },
            { id: '#billing-city', message: '<?php echo Text::_('PLG_PAYMENT_PAYSECURE_CITY_REQUIRED'); ?>' },
            { id: '#billing-country', message: '<?php echo Text::_('PLG_PAYMENT_PAYSECURE_COUNTRY_REQUIRED'); ?>' }
        ];
        
        if (paymentMethod === 'card') {
            requiredFields.push(
                { id: '#cardholder-name', message: '<?php echo Text::_('PLG_PAYMENT_PAYSECURE_CARDHOLDER_NAME_REQUIRED'); ?>' },
                { id: '#card-number', message: '<?php echo Text::_('PLG_PAYMENT_PAYSECURE_CARD_NUMBER_REQUIRED'); ?>' },
                { id: '#expiry-date', message: '<?php echo Text::_('PLG_PAYMENT_PAYSECURE_EXPIRY_DATE_REQUIRED'); ?>' },
                { id: '#cvc', message: '<?php echo Text::_('PLG_PAYMENT_PAYSECURE_CVC_REQUIRED'); ?>' }
            );
        } else if (paymentMethod === 'wallet') {
            requiredFields.push(
                { id: '#ewallet-phone', message: '<?php echo Text::_('PLG_PAYMENT_PAYSECURE_PHONE_REQUIRED'); ?>' },
                { id: '#ewallet-provider', message: '<?php echo Text::_('PLG_PAYMENT_PAYSECURE_PROVIDER_REQUIRED'); ?>' }
            );
        }
        
        requiredFields.forEach(field => {
            const element = form.querySelector(field.id);
            if (!element) return;
            
            const isCheckbox = element.type === 'checkbox';
            const isEmpty = isCheckbox ? !element.checked : !element.value.trim();
            
            if (isEmpty) {
                errors.push(field.message);
            }
        });
        
        // Method-specific validation
        if (paymentMethod === 'card') {
            const cardValidation = validateCardNumber(form.querySelector('#card-number').value);
            if (!cardValidation.valid) {
                errors.push(cardValidation.message);
            }
            
            const expiryValidation = validateExpiryDate(form.querySelector('#expiry-date').value);
            if (!expiryValidation.valid) {
                errors.push(expiryValidation.message);
            }
            
            if (!validateCVC(form.querySelector('#cvc').value)) {
                errors.push('<?php echo Text::_('PLG_PAYMENT_PAYSECURE_INVALID_CVC'); ?>');
            }
        }
        
        return {
            valid: errors.length === 0,
            messages: errors
        };
    }
    
    /**
     * Validate credit card number
     */
    function validateCardNumber(number) {
        number = number.replace(/\s/g, '');
        
        if (!number) {
            return { valid: false, message: '<?php echo Text::_('PLG_PAYMENT_PAYSECURE_CARD_REQUIRED'); ?>' };
        }
        
        // Check basic length and digits
        if (!/^\d{13,19}$/.test(number)) {
            return { valid: false, message: '<?php echo Text::_('PLG_PAYMENT_PAYSECURE_CARD_INVALID_LENGTH'); ?>' };
        }
        
        // Check card type specific lengths
        const cardInfo = detectCardType(number);
        if (cardInfo.type && cardInfo.lengths && !cardInfo.lengths.includes(number.length)) {
            return { valid: false, message: '<?php echo Text::_('PLG_PAYMENT_PAYSECURE_CARD_INVALID_LENGTH_FOR_TYPE'); ?>' };
        }
        
        // Luhn algorithm
        let sum = 0;
        let alternate = false;
        
        for (let i = number.length - 1; i >= 0; i--) {
            let digit = parseInt(number.charAt(i), 10);
            
            if (alternate) {
                digit *= 2;
                if (digit > 9) {
                    digit = (digit % 10) + 1;
                }
            }
            
            sum += digit;
            alternate = !alternate;
        }
        
        if (sum % 10 !== 0) {
            return { valid: false, message: '<?php echo Text::_('PLG_PAYMENT_PAYSECURE_CARD_INVALID_NUMBER'); ?>' };
        }
        
        return { valid: true };
    }
    
    /**
     * Validate expiry date
     */
    function validateExpiryDate(expiry) {
        if (!expiry) {
            return { valid: false, message: '<?php echo Text::_('PLG_PAYMENT_PAYSECURE_EXPIRY_REQUIRED'); ?>' };
        }
        
        const parts = expiry.split('/');
        if (parts.length !== 2 || parts[0].length !== 2 || parts[1].length !== 2) {
            return { valid: false, message: '<?php echo Text::_('PLG_PAYMENT_PAYSECURE_EXPIRY_INVALID_FORMAT'); ?>' };
        }
        
        const month = parseInt(parts[0], 10);
        const year = parseInt(parts[1], 10);
        const currentYear = new Date().getFullYear() % 100;
        const currentMonth = new Date().getMonth() + 1;
        const fullYear = 2000 + year;
        
        // Check month is valid (1-12)
        if (month < 1 || month > 12) {
            return { valid: false, message: '<?php echo Text::_('PLG_PAYMENT_PAYSECURE_EXPIRY_INVALID_MONTH'); ?>' };
        }
        
        // Check year is not in the past
        if (fullYear < new Date().getFullYear()) {
            return { valid: false, message: '<?php echo Text::_('PLG_PAYMENT_PAYSECURE_EXPIRY_PAST_YEAR'); ?>' };
        }
        
        // If current year, check month is not in the past
        if (fullYear === new Date().getFullYear() && month < currentMonth) {
            return { valid: false, message: '<?php echo Text::_('PLG_PAYMENT_PAYSECURE_EXPIRY_PAST_MONTH'); ?>' };
        }
        
        return { valid: true };
    }
    
    /**
     * Validate CVC code
     */
    function validateCVC(cvc) {
        return /^\d{3,4}$/.test(cvc);
    }
    
    /**
     * Detect card type and display icon
     */
    function detectCardType(number) {
        number = number.replace(/\s/g, '');
        
        const cardTypes = {
            visa: {
                pattern: /^4/,
                lengths: [13, 16, 19],
                icon: 'visa',
                name: 'Visa'
            },
            mastercard: {
                pattern: /^5[1-5]|^2[2-7]/,
                lengths: [16],
                icon: 'mastercard',
                name: 'Mastercard'
            },
            amex: {
                pattern: /^3[47]/,
                lengths: [15],
                icon: 'amex',
                name: 'American Express'
            },
            discover: {
                pattern: /^6(?:011|5|4[4-9])/,
                lengths: [16],
                icon: 'discover',
                name: 'Discover'
            },
            diners: {
                pattern: /^3(?:0[0-5]|[68])/,
                lengths: [14],
                icon: 'diners',
                name: 'Diners Club'
            },
            jcb: {
                pattern: /^35(?:2[89]|[3-8])/,
                lengths: [16],
                icon: 'jcb',
                name: 'JCB'
            },
            unionpay: {
                pattern: /^62/,
                lengths: [16, 17, 18, 19],
                icon: 'unionpay',
                name: 'UnionPay'
            },
            maestro: {
                pattern: /^(5018|5020|5038|6304|6759|676[1-3])/,
                lengths: [12, 13, 14, 15, 16, 17, 18, 19],
                icon: 'maestro',
                name: 'Maestro'
            }
        };

        for (const [type, config] of Object.entries(cardTypes)) {
            if (config.pattern.test(number)) {
                const cardIcon = form.querySelector('#card-type-icon');
                const cardName = form.querySelector('#card-type-name');
                const cardContainer = form.querySelector('.card-type-container');
                
                if (cardIcon) {
                    cardIcon.className = 'card-type-icon ' + config.icon;
                }
                
                if (cardName) {
                    cardName.textContent = config.name;
                }
                
                if (cardContainer) {
                    cardContainer.classList.remove('d-none');
                }
                
                return {
                    type: type,
                    lengths: config.lengths,
                    icon: config.icon,
                    name: config.name
                };
            }
        }

        // Hide card type if not detected
        const cardContainer = form.querySelector('.card-type-container');
        if (cardContainer) {
            cardContainer.classList.add('d-none');
        }
        
        return {};
    }
    
    /**
     * Prepare payment data based on selected method
     */
    async function preparePaymentData(method) {
        const data = {
            payment_method: method,
            amount: <?php echo $amount; ?>,
            currency: '<?php echo $this->currency; ?>',
            order_id: '<?php echo $this->order_id; ?>',
            billing_address: {
                line1: form.querySelector('#billing-address1').value,
                line2: form.querySelector('#billing-address2').value,
                city: form.querySelector('#billing-city').value,
                state: form.querySelector('#billing-state').value,
                postal_code: form.querySelector('#billing-zip').value,
                country: form.querySelector('#billing-country').value
            },
            token: '<?php echo $token; ?>',
            nonce: '<?php echo $nonce; ?>'
        };
        
        // Method-specific data
        if (method === 'card') {
            const expiry = form.querySelector('#expiry-date').value.split('/');
            data.cardholder_name = form.querySelector('#cardholder-name').value.trim();
            data.card_number = form.querySelector('#card-number').value.replace(/\s/g, '');
            data.exp_month = expiry[0];
            data.exp_year = '20' + expiry[1];
            data.cvc = form.querySelector('#cvc').value;
            
            // Tokenize card if needed
            if (<?php echo $this->params->get('tokenization', 0) ? 1 : 0; ?>) {
                const tokenResponse = await tokenizeCard(data);
                if (tokenResponse.token) {
                    data.payment_token = tokenResponse.token;
                    delete data.card_number;
                    delete data.cvc;
                }
            }
        } else if (method === 'bank') {
            // Bank transfer specific data
        } else if (method === 'wallet') {
            data.phone = form.querySelector('#ewallet-phone').value;
            data.provider = form.querySelector('#ewallet-provider').value;
        }
        
        return data;
    }
    
    /**
     * Tokenize card details
     */
    async function tokenizeCard(cardData) {
        try {
            const response = await fetch('<?php echo Uri::base(); ?>index.php?option=com_ajax&plugin=paysecure&group=payment&format=json&task=tokenize', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(cardData)
            });
            
            if (!response.ok) {
                throw new Error('Tokenization failed');
            }
            
            return await response.json();
        } catch (error) {
            console.error('Tokenization error:', error);
            return {};
        }
    }
    
    /**
     * Process payment via AJAX
     */
    async function processPayment(paymentData) {
        try {
            const formData = new FormData();
            formData.append('task', 'process');
            formData.append('payment_data', JSON.stringify(paymentData));
            
            const response = await fetch('<?php echo Uri::base(); ?>index.php?option=com_ajax&plugin=paysecure&group=payment&format=json', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (!response.ok) {
                throw new Error('<?php echo Text::_('PLG_PAYMENT_PAYSECURE_NETWORK_ERROR'); ?>');
            }
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.message || '<?php echo Text::_('PLG_PAYMENT_PAYSECURE_PAYMENT_FAILED'); ?>');
            }
            
            return result;
        } catch (error) {
            throw error;
        }
    }
});
</script>