<?php

    namespace PHPSupercore;

    use Dotenv\Dotenv;
    use PhpAmqpLib\Connection\AMQPStreamConnection;

    require_once('../../vendor/autoload.php');
    require_once('../Zetabase/class.Zetabase.php') ;

    abstract class PHPSuperCore
    {
        protected $info = "[\e[0;33m ! \e[0m]";
        protected $good = "[\e[0;32m ✓ \e[0m]";
        protected $bad  = "[\e[0;31m ✗ \e[0m]";

        private $startupDelay       = 5; //Prevent supervisor from hammering the rabbit process
        private $params             = [];
        private $rabbitMQConnection = null;
        private $rabbitMQChannel    = null;
        private $database = null;   //MySQL Database connector

        public function __construct($params = [])
        {
            $this->params = $params;

            $parentClassName = debug_backtrace()[1]['file'];
            $parentClassName = preg_replace('/.+\//', '', $parentClassName);
            $parentClassName = preg_replace('/\.php/', '', $parentClassName);

            echo "\n---- SupervisorCore\\".$parentClassName." Begin // ".date('Y-m-d H:i:s')." ----\n\n";
            for ($i = $this->startupDelay ; $i > 0 ; $i--) {
                echo "Startup in {$i}...\n";
                sleep(1);
            }

            $this->connectAllServices();

            $this->processMessageQueue($this->messageProcessor());
        }

        private function initialiseRabbitMQ()
        {
            if (empty($this->params['rabbitmq'])) {
                echo("{$this->info} RabbitMQ not required.\n");
                return false;
            }

            while (!$this->rabbitMQConnection) {
                try {
                    $this->rabbitMQConnection = new AMQPStreamConnection($this->params['rabbitmq']['host'],
                        $this->params['rabbitmq']['port'],
                        $this->params['rabbitmq']['username'],
                        $this->params['rabbitmq']['password'],
                        $this->params['rabbitmq']['vhost']);

                    $this->rabbitMQChannel = $this->rabbitMQConnection->channel();

                    $this->rabbitMQChannel->queue_declare($queue = $this->params['rabbitmq']['queue'],
                        $passive = false,
                        $durable = true,
                        $exclusive = false,
                        $auto_delete = false,
                        $nowait = false,
                        $arguments = null,
                        $ticket = null);

                    $this->rabbitMQChannel->basic_qos(null, 1, null);

                } catch (\Exception $e) {
                    echo("{$this->bad} RabbitMQ Unavailable... attempting reconnect - ".date('Y-m-d H:i:s')."\n");
                    $this->rabbitMQConnection = null;
                    sleep(5);
                }
            }

            echo("{$this->good} RabbitMQ Initialised\n");
            return true;
        }

        private function initialiseMySQL()
        {
            if (empty($this->params['mysql'])) {
                echo("{$this->info} MySQL not required.\n");
                return false;
            }

            $connectionDetails = new \StdClass() ;
            $connectionDetails->host = $this->params['mysql']['host'] ;
            $connectionDetails->dbname = $this->params['mysql']['database'] ;
            $connectionDetails->user = $this->params['mysql']['username'] ;
            $connectionDetails->pass = $this->params['mysql']['password'] ;

            while(!$this->database) {
                try {
                    $this->database = new \Zetabase($connectionDetails);
                } catch (\Exception $e) {

                    echo("{$this->bad} MySQL Unavailable... attempting reconnect - ".date('Y-m-d H:i:s')."\n");
                    $this->database = null;
                    sleep(5);
                }
            }

            echo("{$this->good} MySQL Initialised\n");
            return true;
        }

        private function initialiseRedis()
        {
            if (empty($this->params['redis'])) {
                echo("{$this->info} Redis not required.\n");
                return false;
            }
        }

        private function resetRabbitMQ()
        {
        }

        private function resetMySQL()
        {
            unset($this->database) ;
        }

        private function resetRedis()
        {

        }

        private function resetAllServices()
        {
            $this->resetRabbitMQ();
            $this->resetMySQL();
            $this->resetRedis();
        }

        private function connectAllServices()
        {
            $this->initialiseRabbitMQ();
            $this->initialiseMySQL();
            $this->initialiseRedis();
        }

        //This should get called when we have exceptions on any service
        private function resetAndReconnectAllServices()
        {
            $this->resetAllServices();
            $this->connectAllServices();
        }

        protected function getDatabase()
        {
            //TODO: Confirm connection is live
            return $this->database ;
        }

        //This just helps keep our derived classes tidy
        abstract protected function messageProcessor() ;

        protected function processMessageQueue($messageProcessor)
        {

            $this->rabbitMQChannel->basic_consume($queue = $this->params['rabbitmq']['queue'],
                $consumer_tag = '',
                $no_local = false,
                $no_ack = false,
                $exclusive = false,
                $nowait = false,
                $messageProcessor);

            while (count($this->rabbitMQChannel->callbacks))
            {
                $this->rabbitMQChannel->wait();
            }
        }

        public function __destruct()
        {
                echo "{$this->bad} WE SHOULD NEVER GET HERE {$this->bad}\n\n";
                $this->resetAllServices();
        }
    }

    //Make the env() available globally for anything which uses this class
    $dotenv = Dotenv::createImmutable('../..');
    $dotenv->load();
