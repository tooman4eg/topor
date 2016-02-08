<?php namespace Topor\DTO;

class AutopayService extends DataTransferObject
{

    const THRESHOLD_TRIGGER_TYPE = 'threshold';
    const SCHEDULE_TRIGGER_TYPE = 'schedule';

    protected static $dataMap = array(
        [
            'propertyName' => 'id',
            'key' => 'id',
        ],
        [
            'propertyName' => 'name',
            'key' => 'name',
        ],
        [
            'propertyName' => 'triggerTypes',
            'key' => 'trigger_types',
        ],
        [
            'propertyName' => 'parameters',
            'key' => 'parameters',
            'class' => 'Topor\DTO\AutopayServiceParameter',
            'multiple' => true,
        ],
    );

    public function canTriggeredByThreshold()
    {
        $trigger_types = $this->getTriggerTypes();
        if (in_array(static::THRESHOLD_TRIGGER_TYPE, $trigger_types)) {
            return true;
        }

        return false;
    }

    public function canTriggeredBySchedule()
    {
        $trigger_types = $this->getTriggerTypes();
        if (in_array(static::SCHEDULE_TRIGGER_TYPE, $trigger_types)) {
            return true;
        }

        return false;
    }

    public function getParametersAsSchet()
    {
        $original_params = $this->getParameters();

        $params = [];

        foreach ($original_params as $original_param) {
            $param = [
                'name' => 'parameters[' . $original_param->getCode() . ']',
                'title' => $original_param->getName(),
                'type' => $original_param->getType(),
                'hint' => $original_param->getPattern_description(),
            ];

            if ('text' == $param['type']) {
                $param['type'] = 'string';
            }

            $params[] = (object) $param;
        }

        return $params;
    }
}
