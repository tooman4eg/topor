<?php namespace Topor\DTO;

use BadMethodCallException;

abstract class DataTransferObject implements IParseableStdClassDTO
{

    /**
     * Заморочка с IParseableStdClassDTO, трейтом и абстрактным классом нужна, чтобы
     * была возможность объявить метод парсинга статичным. Интерфейс в данном случае реализует трейт
     */
    use DTOStdClassParser;

    /**
     * Массив, описывающий как следует маппить данные из ответа
     *
     * Например:
     * protected static $dataMap = [
     *      [
     *          'propertyName'  => 'parameters',                            // Необходимо указать для того, чтобы работал геттер
     *          'key'           => 'parameters',                            // Ключ, по которому будут извлекаться данные из ответа
     *          'class'         => 'Topor\DTO\AutopayServiceParameter',     // Класс, в который нужно смаппить полученные по ключу данные
     *          'multiple'      => true,                                    // Если данные по ключу подразумевают формат массива
     *          'required'      => false,                                   // Если наличие значения по ключу не обязательно. Если значение отсутствует -
     *                                                                      // будет присвоен null
     *      ]
     * ];
     *
     * @var array
     */
    protected static $dataMap = [];

    /**
     * getter, который возвращает значение свойств
     * @param  string $method Вызываемый метод
     * @param  $args Передаваемые аргументы метода
     * @return mixed Если свойство существует - возвращается значение
     * @throws BadMethodCallException Если такого getter'а не существует
     */
    public function __call($method, $args)
    {
        //Избавляемся от get в имени вызываемого метода
        $param = lcfirst(substr($method, 3));

        if (in_array($param, $this->getAvailableProperties())) {
            return $this->$param;
        } else {
            throw new BadMethodCallException('Call to undefined method ' . get_class() . '->' . $method, 1);

        }
    }

    /**
     * Получает из массива $dataMap доступные свойства
     * @return array Массив доступных к возврату свойств
     */
    protected function getAvailableProperties()
    {
        $available_properties = [];

        foreach (static::$dataMap as $map_params) {
            $available_properties[] = $map_params['propertyName'];
        }

        return $available_properties;
    }
}
