<?php namespace Topor;

class MbankServicesStorage extends ServicesStorage
{
    use ServicesValidator

    /**
     * @var Mbank
     */
    protected $mbank;

    function __construct(Mbank $mbank)
    {
        $this->mbank = $mbank;
    }

    function _one($id)
    {
        return Topor::mbank()->service($id);
    }

    function allGroups($skip_empty = true, $modified_after = null)
    {
        return Topor::mbank()->servicesGroups($modified_after, $skip_empty);
    }
}
