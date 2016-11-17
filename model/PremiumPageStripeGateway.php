<?php

/**
 * Created by PhpStorm.
 * User: jasoncarney
 * Date: 17/11/16
 * Time: 9:37 AM
 */
class PremiumPageStripeGateway implements PremiumPageHandlerPaymentGateway
{
    /**
     * @var int $charge
     */
    private $charge;
    private $secret_key;
    private $token;
    private $description;

    /**
     * PremiumPageStripeGateway constructor.
     * @param array $options
     * @throws Exception
     */
    public function __construct($options)
    {
        if(empty($options['secret_key'])){
            throw new Exception('Error 01: Stripe secret key empty');
        }
        if(empty($options['token'])){
            throw new Exception('Error 02: Stripe token missing');
        }
        if(empty($options['description'])){
            throw new Exception('Error 03: Stripe payment description missing');
        }
        $this->secret_key = $options['secret_key'];
        $this->token = $options['token'];
        $this->description = $options['description'];
    }

    /**
     * @return PaymentResponse on success
     * @throws Exception on error
     */

    public function processPayment()
    {

        \Stripe\Stripe::setApiKey($this->secret_key);

        $chargeOptions = array();
        $chargeOptions['amount'] = $this->charge;
        $chargeOptions['currency'] = 'aud';
        $chargeOptions['source'] = $this->token;
//        $chargeOptions['source'] = array(
//            "exp_month" => '12',
//            "exp_year" => '22',
//            "number" => '4242424242424242',
//            "object" => 'card',
//            "cvc" => '123'
//        );
        $chargeOptions['description'] = $this->description;


        $response = \Stripe\Charge::create($chargeOptions);

        if($response->paid){
            return new PaymentResponse($response->id,$response->amount,$response->description);
        } else {
            throw new Exception("There was a problem processing your credit card payment");
        }
    }

    /**
     * @param int $charge payment amount in cents
     */
    public function setCharge($charge)
    {
        $this->charge = $charge;
    }

    /**
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }
}