<?php namespace Topor;

/**
 * Class ServicesStorage
 * @package Topor
 * @method rulesForJquery($service)
 * @method rulesForLaravel($service)
 */
abstract class ServicesStorage
{
    protected $transfer_service_id;
    protected $to_card_service_id;
    protected $to_bank_personal_service_id;
    protected $to_bank_company_service_id;

    abstract protected function _one($id);
    abstract public function allGroups(
        $skip_empty = true,
        $modified_after = null
    );

    public function __construct($config)
    {
        $this->transfer_service_id = $config['transfer']['service_id'];
        $this->to_card_service_id = $config['to_card']['service_id'];
        $this->to_bank_personal_service_id = $config['to_bank']['personal_service_id'];
        $this->to_bank_company_service_id = $config['to_bank']['company_service_id'];
    }

    public function one($id)
    {
        return $this->_one($id);
    }

    public function transfer()
    {
        return $this->one($this->transfer_service_id);
    }

    public function to_card()
    {
        return $this->one($this->to_card_service_id);
    }

    public function to_bank_personal()
    {
        return $this->one($this->to_bank_personal_service_id);
    }

    public function to_bank_company()
    {
        return $this->one($this->to_bank_company_service_id);
    }
}
