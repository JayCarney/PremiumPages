<?php

/**
 * Created by PhpStorm.
 * User: jasoncarney
 * Date: 17/11/16
 * Time: 9:30 AM
 */
interface PremiumPageHandlerPaymentGateway
{
    /**
     * @return PaymentResponse on success
     * @throws Exception on error
     */
    public function processPayment();

    /**
     * @param int $charge
     */
    public function setCharge($charge);

    /**
     * @param string $description
     */
    public function setDescription($description);
}