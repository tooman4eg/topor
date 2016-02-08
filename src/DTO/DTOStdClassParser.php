<?php namespace Topor\DTO;

use Exception;

trait DTOStdClassParser
{

    public static function parseFromStdClass($stdClassObject)
    {
        $object = new static;

        if (is_array($stdClassObject)) {
            $stdClassObject = (object) $stdClassObject;
        }

        foreach (static::$dataMap as $map_params) {
            if (isset($map_params['multiple']) && $map_params['multiple'] === true) {
                $object->$map_params['propertyName'] = static::parseMultipleFromStdClass($map_params, $stdClassObject);
            } else {
                $object->$map_params['propertyName'] = static::parseSingleFromStdClass($map_params, $stdClassObject);
            }
        }

        return $object;
    }

    protected static function parseMultipleFromStdClass($paramMapData, $stdClassObject)
    {
        $property = null;
        $result_data = [];

        $required = isset($paramMapData['required']) ? $paramMapData['required'] : false;
        $property = static::getPropertyIfExists($stdClassObject, $paramMapData['key'], $required);

        if (!is_null($property) && is_array($property)) {
            foreach ($property as $key => $property_data_obj) {
                if (isset($paramMapData['class'])) {
                    $mapClass = $paramMapData['class'];
                    $result_data[] = $mapClass::parseFromStdClass($property_data_obj);
                } else {
                    $result_data[] = $property_data_obj;
                }
            }
            return $result_data;
        } else {
            if ($required) {
                throw new Exception('Error parsing response', 1);
            }
        }

        return $result_data;
    }

    protected static function parseSingleFromStdClass($paramMapData, $stdClassObject)
    {
        $result_data = null;
        $required = isset($paramMapData['required']) ? $paramMapData['required'] : false;
        $property = static::getPropertyIfExists($stdClassObject, $paramMapData['key'], $required);

        if (!is_null($property)) {
            if (isset($paramMapData['class'])) {
                $mapClass = $paramMapData['class'];
                $result_data = $mapClass::parseFromStdClass($property);
            } else {
                $result_data = $property;
            }
            return $result_data;
        } else {
            if ($required) {
                throw new Exception('Error parsing response', 1);
            }
        }

        return $result_data;
    }

    protected static function getPropertyIfExists($object, $property_key, $required = true)
    {
        $value = static::object_get($object, $property_key, null);

        if (!is_null($value)) {
            return $value;
        } else {
            if ($required) {
                throw new Exception('Error parsing response', 1);
            }
        }

        return $value;
    }

    protected static function object_get($object, $key, $default = null)
    {
        if (is_null($key) || trim($key) == '') {
            return $object;
        }
        foreach (explode('.', $key) as $segment) {
            if (!is_object($object) || !isset($object->{$segment})) {
                return value($default);
            }
            $object = $object->{$segment};
        }
        return $object;
    }
}
