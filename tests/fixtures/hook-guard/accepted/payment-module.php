<?php
/**
 * Fixture for tests/fixtures.sh: a payment module. paymentOptions is mandatory for payment modules
 * (PaymentOptionsFinder executes it) and must be accepted.
 */
class FixturePaymentModule extends PaymentModule
{
    public function install()
    {
        return parent::install()
            && $this->registerHook('paymentOptions')
            && $this->registerHook('displayPaymentReturn')
            && $this->registerHook('actionPaymentConfirmation');
    }

    public function hookPaymentOptions($params)
    {
        return [];
    }

    public function hookDisplayPaymentReturn($params)
    {
        return '';
    }

    public function hookActionPaymentConfirmation($params)
    {
        // Empty on purpose: the hook guard only reads the method name.
    }
}
