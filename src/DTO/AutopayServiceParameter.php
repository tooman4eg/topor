<?php namespace Topor\DTO;

use Topor\Exception\UnprocessableEntity;

class AutopayServiceParameter extends DataTransferObject
{

    protected static $dataMap = array(
        [
            'propertyName'  => 'code',
            'key'           => 'code',
        ],
        [
            'propertyName'  => 'min_length',
            'key'           => 'min_length',
        ],
        [
            'propertyName'  => 'max_length',
            'key'           => 'max_length',
        ],
        [
            'propertyName'  => 'pattern',
            'key'           => 'pattern',
            'required'      => false,
        ],
        [
            'propertyName'  => 'pattern_description',
            'key'           => 'pattern_description',
        ],
        [
            'propertyName'  => 'name',
            'key'           => 'name',
        ],
        [
            'propertyName'  => 'key_field',
            'key'           => 'key_field',
        ],
        [
            'propertyName'  => 'type',
            'key'           => 'type',
        ],
    );
}
