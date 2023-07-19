<?php

declare(strict_types=1);

namespace Sync\Handlers;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;  

/**
 * Class AuthHandler 
 * Класс обработчик маршрута /sum
 */
class SumHandler implements RequestHandlerInterface
{
    /**
     * Обрабатывает параметры полученные из url по маршруту /sum и записывает их в лог
     */
    public function processingRequest(array $data)
    {
        $keys = array_keys($data);
        $log = new Logger('request');
        $result = '';
        if(!file_exists('..\mezzio\Logs\\'.date('Y-m-d'))) {
            mkdir('Logs\\'.date('Y-m-d'), 0777, false, null);
        }
        $log->pushHandler(new StreamHandler('..\mezzio\Logs\\'.date('Y-m-d').'\requests.log'));
        foreach($keys as $key) {
            $result = $result.$key.': '.$data[$key].' ';
        }
        $result = $result.'sum: '.array_sum($data);
        $log->info($result);       
    }

    /**
     * Обрабатывает информацию полученную в запросе
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getQueryParams();
        $this->processingRequest($data);
        return new JsonResponse(array_sum($data));
    }
}
