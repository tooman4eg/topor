<?php namespace Topor\DTO;

use Topor\Exception\UnprocessableEntity;

class AutopayServiceList extends DataTransferObject
{

    protected static $dataMap = array(
        [
            'propertyName'  => 'services',
            'key'           => 'data',
            'class'         => 'Topor\DTO\AutopayService',
            'multiple'      => true,
        ]
    );
}
