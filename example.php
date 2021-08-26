<?php
    namespace PHPSupercore;

    require_once('./PHPSuperCore.php');

    class example extends PHPSuperCore
    {
        public function __construct()
        {
            $params = [
                'redis'    => [],
                'mysql'    => [
                    'host'     => env('DB_HOST', 'localhost'),
                    'database' => env('DB_DATABASE'),
                    'username' => env('DB_USERNAME'),
                    'password' => env('DB_PASSWORD'),
                ],
                'rabbitmq' => [
                    'host'     => env('RABBITMQ_HOST', 'localhost'),
                    'port'     => env('RABBITMQ_PORT', 5672),
                    'username' => env('RABBITMQ_USERNAME', '',),
                    'password' => env('RABBITMQ_PASSWORD', ''),
                    'vhost'    => env('RABBITMQ_VHOST', ''),
                    'queue'    => 'primeConversionTracking',
                ],
            ];

            parent::__construct($params);
        }

        protected function messageProcessor()
        {
            return function ($msg) {
                try {

                    echo "\nMsg: {$msg->body}\n";
                } catch (\Exception $e) {
                    //echo $info . ': ' . date('Y-m-d H:i:s') . ' - ' . 'Invalid message in queue, moving to bin: ' . "$msg->body\n";
                    echo $e->getMessage();
                    echo $e->getTraceAsString();
                }

                $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
            };
        }
    }

    new example();
