<?php

set_time_limit(0);

if (PHP_SAPI !== 'cli') { // 不是命令行终止运行
    exit('Error: should be invoked via the CLI version of PHP, not the '.PHP_SAPI.' SAPI'.PHP_EOL);
}

require __DIR__.'/../vendor/autoload.php';

/**
 * 常驻内存的 `Beanstalkd` 队列消费者脚本，通过 `daemontools`来保持常驻。
 *
 * 该脚本主要逻辑为：脚本永远循环执行，从队列中取出消息，然后索引到`ES`里
 */

use Elasticsearch\ClientBuilder;
use Walle\Modules\Queue\BeanstalkdQueue;
use Walle\Modules\Helper\Utils;

class LogQueueProcessor
{
    const ES_SERVER = 'es.servers.dev.ofc:9200';

    protected $queue;

    protected $client;

    protected $type;

    public function __construct($type)
    {
        $this->type = $type;

        $this->initialize();
    }

    private function initialize()
    {
        $this->queue = BeanstalkdQueue::create(BeanstalkdQueue::MQ_SERVER);

        $this->client = ClientBuilder::create()
                                        ->setHosts(array(self::ES_SERVER))
                                        ->setRetries(0)
                                        ->build();
    }

    public function run()
    {
        while (true) {
            try {
                $i = 0;
                $params = [];

                // 当每批满100个，或者队列中 10 秒钟没有新消息，就把批次提交一次
                while ($i++ < 100 && !empty($logJson = $this->getLogData())) {
                   $log = Utils::jsonDecode($logJson, true);

                    if (isset($log['channel'])) {
                        $params['body'][] = [
                            'index' => [
                                '_index' => 'log-' . date('Y.m'),
                                '_type'  => $this->type,
                            ]
                        ];

                        $params['body'][] = [
                            'channel'    => $log['channel'],
                            'level'      => $log['level'],
                            'level_name' => $log['level_name'],
                            'message'    => $log['message'],
                            'context'    => Utils::jsonEncode($log['context']),
                            'extra'      => Utils::jsonEncode($log['extra']),
                            'datetime'   => $log['datetime']
                        ];
                    }

                }

                if (count($params) > 0) {
                    $this->bulkSend($params);
                }
            } catch (\Exception $e) {
                Utils::handleException($e);
            }
        }
    }

    private function getLogData()
    {
        return $this->queue->getFromQueue(BeanstalkdQueue::QUEUE_LOG, 10);
    }

    /**
     * Use Elasticsearch bulk API to send list of documents
     *
     * @param  array             $documents
     */
    private function bulkSend(array $documents)
    {
        $this->client->bulk($documents);
    }

}

$logQueueProcessor = new LogQueueProcessor('Develop');
$logQueueProcessor->run();