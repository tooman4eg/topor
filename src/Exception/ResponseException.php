<?php namespace Topor\Exception;

use Topor\Exception;

class ResponseException extends Exception
{
    function responseHttpCode()
    {
        $response = json_decode((string) $this->getResponse()->getBody());
        return $response->meta->code;
    }

    /**
     * Возвращает тело ответа от запроса с исключением
     * @return stdClass Тело ответа
     */
    function getResponseContent()
    {
        $raw_response = (string) $this->getResponse()->getBody();
        return json_decode($raw_response);
    }
}
