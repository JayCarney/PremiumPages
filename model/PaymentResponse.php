<?php

/**
 * Created by PhpStorm.
 * User: jasoncarney
 * Date: 17/11/16
 * Time: 11:23 AM
 */
class PaymentResponse
{
    /**
     * @var string $receipt_id
     * @var string $charge_value
     * @var string $description
     * @var modUser $user
     */
    public $receipt_id;
    public $charge_value;
    public $description;
    public $user;

    /**
     * PaymentResponse constructor.
     * @param $receipt_id
     * @param $charge_value
     * @param $description
     */
    public function __construct($receipt_id, $charge_value, $description)
    {
        $this->receipt_id = $receipt_id;
        $this->charge_value = $charge_value;
        $this->description = $description;
    }

    /**
     * @param modUser $user
     */
    public function setUser($user)
    {
        $this->user = $user;
    }
}