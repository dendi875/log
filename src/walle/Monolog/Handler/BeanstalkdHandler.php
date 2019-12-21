<?php 
/**
 * Walle\Monolog\Handler\BeanstalkdHandler
 *
 * Beanstalkd handler
 *
 * Usage example:
 *
 * $pheanstalk = new Pheanstalk('127.0.0.1', 11300);
 *
 * $logger = new Logger('my_logger');
 * $logger->pushHandler(new BeanstalkdHandler($pheanstalk));
 *
 * $logger->info('My logger is now ready');
 *
 * @author     <dendi875@163.com>
 * @createDate 2019-12-19 18:12:22
 * @copyright  Copyright (c) 2019 https://github.com/dendi875
 */

namespace Walle\Monolog\Handler;

use DateTime;
use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Formatter\JsonFormatter;
use Pheanstalk\Pheanstalk;

class BeanstalkdHandler extends AbstractProcessingHandler
{
    /**
     * @var Pheanstalk
     */
    protected $pheanstalk;

    /**
     * @var string
     */
    protected $tube;


    /**
     * BeanstalkdHandler constructor.
     *
     * @param Pheanstalk $pheanstalk Pheanstalk  instance
     * @param $tube  `Beanstalkd` tube name
     * @param $level The minimum logging level at which this handler will be triggered
     * @param bool $bubble  Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(Pheanstalk $pheanstalk, $tube = 'log', $level = Logger::DEBUG, $bubble = true)
    {
        $this->pheanstalk = $pheanstalk;
        $this->tube = $tube;

        parent::__construct($level, $bubble);
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $record)
    {
        if (!$this->isHandling($record)) {
            return false;
        }

        $record = $this->processRecord($record);

        // 最终日志是被消费者 reserve 后再索引到 `ES`中，
        // Elasticsearch要求使用ISO 8601格式的日期，并具有可选的毫秒精度。
        $record['datetime'] = $record['datetime']->format(DateTime::ISO8601);

        $record['formatted'] = $this->getFormatter()->format($record);

        $this->write($record);

        return false === $this->bubble;
    }

    /**
     * Writes the record down to the log of the implementing handler
     *
     * @param  array $record
     *
     * @return void
     */
    protected function write(array $record)
    {
        $data = $record["formatted"];

        $this->pheanstalk->useTube($this->tube)->put($data);
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter()
    {
        return new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, false);
    }
}