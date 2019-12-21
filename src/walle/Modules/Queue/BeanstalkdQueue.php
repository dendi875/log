<?php
/**
 * 消息队列支持类，基于`beanstalkd`实现
 *
 * @author     <dendi875@163.com>
 * @createDate 2019-12-11 18:13:23
 * @copyright  Copyright (c) 2018 https://github.com/dendi875
 */

namespace Walle\Modules\Queue;

use Pheanstalk\Pheanstalk;

class BeanstalkdQueue
{
    private $pheanstalk;

    private $queueName;

    private static $instance;

    const MQ_SERVER = 'beanstalk.servers.dev.ofc:11300';

    // queue names
    const QUEUE_SMS = 'sms';
    const QUEUE_EMAIL = 'email';
    const QUEUE_LOG = 'log';
    const QUEUE_NOTIFY = 'notify';
    const QUEUE_NOTIFY_LOG = 'notifylog';

    // priority
    const PRI_URGENT = 0;
    const PRI_HIGH = 100;
    const PRI_NORMAL = 200;
    const PRI_LOW = 300;
    const PRI_LOWER = 400;
    const PRI_LOWEST = 10000;

    /**
     * Queue constructor.
     * @param $server as 'host:port'
     */
    public function __construct($server)
    {
        if (empty($server)) {
            trigger_error('No message queue host to connect', E_USER_WARNING);
        }

        list($host, $port) = explode(':', $server);
        $this->pheanstalk = new Pheanstalk($host, $port);
    }

    public static function create($server)
    {
        return new self($server);
    }

    /**
     * search queue
     */
    public function listQueue($queuePrefix = '')
    {
        $tubes = $this->pheanstalk->listTubes();

        if (!empty($tubes) && is_array($tubes)) {
            return array_filter($tubes, function ($tube) use ($queuePrefix) {
                return strpos($tube, $queuePrefix) !== false;
            });
        } else {
            return $tubes;
        }
    }

    /**
     * use the queue name. only one queue allowed in an instance.
     */
    public function useQueue($queueName)
    {
        $this->queueName = $queueName;

        return $this->pheanstalk->useTube($queueName);
    }

    /**
     * put a message to the queue
     */
    public function put($data, $priority = 2, $delay = 0, $ttr = 60)
    {
        return $this->pheanstalk->put($data, $priority, $delay, $ttr);
    }

    public function putToQueue($queueName, $data, $priority = 2, $delay = 0, $ttr = 60)
    {
        return $this->pheanstalk
            ->useTube($queueName)
            ->put($data, $priority, $delay, $ttr);
    }

    /**
     * get a message from the queue and delete it
     *
     * @param $queueName
     * @param int $timeout
     * @return bool|string
     */
    public function getFromQueue($queueName, $timeout = 60)
    {
        $this->queueName = $queueName;

        $job = $this->pheanstalk
            ->useTube($queueName)
            ->watch($queueName)
            ->ignore('default')
            ->reserve($timeout);

        if ($job !== false) {
            $this->pheanstalk->delete($job);
            return $job->getData();
        }

        return false;
    }

    /**
     * Get the underlying Pheanstalk instance.
     *
     * @return \Pheanstalk\Pheanstalk
     */
    public function getPheanstalk()
    {
        return $this->pheanstalk;
    }
}