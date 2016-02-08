<?php namespace Topor\DTO;

use Topor\Exception\UnprocessableEntity;

class AutopayEvent extends DataTransferObject
{

    /**
     * Возможные типы события
     */
    const ACTIVATED = 'activated';
    const CANCELLED = 'cancelled';
    const ERROR = 'error';
    const FIRED = 'fired';
    const INVOICED = 'invoiced';
    const CHANGED = 'changed';


    protected static $dataMap = array(
        [
            'propertyName'  => 'id',
            'key'           => 'id',
        ],
        [
            'propertyName'  => 'trigger',
            'key'           => 'trigger',
            'class'         => 'Topor\DTO\AutopayTrigger',
        ],
        [
            'propertyName'  => 'type',
            'key'           => 'type',
        ],
        [
            'propertyName'  => 'result',
            'key'           => 'result',
        ],
        [
            'propertyName'  => 'created_at',
            'key'           => 'created_at',
        ]
    );
}
