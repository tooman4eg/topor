<?php namespace Topor\DTO;

use Topor\Exception\UnprocessableEntity;

class AutopayTrigger extends DataTransferObject
{

    /**
     * Возможные ошибки, которые может вернуть сервер Автоплатежа.
     * Перечислены не все
     */
    const TRIGGER_ALREADY_EXISTS = 'TRIGGER_ALREADY_EXISTS';
    const NOT_ALLOWED_WRONG_TRIGGER_STATUS = 'NOT_ALLOWED_WRONG_TRIGGER_STATUS';


    /**
     * Возможные статусы триггера
     */
    const ACTIVATING = 'activating';
    const ACTIVE = 'active';
    const CANCELLING = 'cancelling';
    const CANCELLED = 'cancelled';
    const ERROR = 'error';
    const CHANGING = 'changing';

    protected static $dataMap = array(
        [
            'propertyName'  => 'id',
            'key'           => 'id',
        ],
        [
            'propertyName'  => 'type',
            'key'           => 'type',
        ],
        [
            'propertyName'  => 'status',
            'key'           => 'status',
        ],
        [
            'propertyName'  => 'service',
            'key'           => 'service',
        ],
        [
            'propertyName'  => 'threshold',
            'key'           => 'threshold',
            'required'      => false,
        ],
        [
            'propertyName'  => 'amount',
            'key'           => 'amount',
        ],
        [
            'propertyName'  => 'cf',
            'key'           => 'cf',
        ],
        [
            'propertyName'  => 'created_at',
            'key'           => 'created_at',
        ],
        [
            'propertyName'  => 'schedule',
            'key'           => 'schedule',
            'required'      => false,
        ],
        [
            'propertyName'  => 'parameters',
            'key'           => 'parameters',
            'multiple'      => true
        ]
    );
}
