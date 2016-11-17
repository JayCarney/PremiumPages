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
    /**
     * @var modUser $user
     */
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

    /**
     * @return array
     */
    public function toArray()
    {
        $output = array();
        $output['receipt_id'] = $this->receipt_id;
        $output['charge_value'] = $this->charge_value;
        $formatted = floatval($this->charge_value) / 100;
        $output['charge_formatted'] = '$' . number_format($formatted, 2);
        $output['description'] = $this->description;
        $output['user_id'] = $this->user->get('id');
        $output['user_username'] = $this->user->get('username');
        $userProfile = $this->user->getOne('Profile');
        $output['user_fullname'] = $userProfile->get('fullname');
        return $output;
    }

    /**
     *
     */
    public function __toString()
    {
        return var_export($this->toArray(),true);
    }
}