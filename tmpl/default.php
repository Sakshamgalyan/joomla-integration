<?php defined('_JEXEC') or die; ?>

<div class="paysecure-payment-method">
    <?php if (!empty($method->payment_logo)): ?>
        <div class="payment-logo">
            <img src="<?php echo JURI::root() . $method->payment_logo; ?>" alt="PaySecure" />
        </div>
    <?php endif; ?>
    
    <div class="payment-options">
        <?php foreach ($paymentMethods as $option): ?>
            <div class="payment-option">
                <input type="radio" 
                       name="virtuemart_paymentmethod_id" 
                       id="paysecure_<?php echo $option['code']; ?>" 
                       value="<?php echo $method->virtuemart_paymentmethod_id; ?>"
                       <?php echo $selected ? 'checked' : ''; ?> />
                <label for="paysecure_<?php echo $option['code']; ?>">
                    <?php if (!empty($option['logo'])): ?>
                        <img src="<?php echo JURI::root() . $option['logo']; ?>" alt="<?php echo $option['name']; ?>" />
                    <?php endif; ?>
                    <?php echo $option['name']; ?>
                </label>
            </div>
        <?php endforeach; ?>
    </div>
</div>