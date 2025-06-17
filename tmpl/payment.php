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
                       name="paysecure_payment_type" 
                       id="paysecure_<?php echo $option['code']; ?>" 
                       value="<?php echo $option['code']; ?>"
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

<style>
.paysecure-payment-method {
    margin: 15px 0;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}
.payment-logo img {
    max-height: 50px;
    margin-bottom: 10px;
}
.payment-options {
    margin-top: 10px;
}
.payment-option {
    margin: 5px 0;
}
.payment-option label {
    display: flex;
    align-items: center;
    cursor: pointer;
}
.payment-option label img {
    max-height: 30px;
    margin-right: 10px;
}
</style>