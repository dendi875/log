<?php
/**
 * 使用 monolog 将日志异步发送到 Beanstalkd 的 tube 为 log 队列里。
 * 然后由守护进程（daemons/log-daemon.php）消费队列取出`job`再索引到`ES`中。
 *
 * monolog 使用的是我们自己扩充处理器 Walle\Monolog\Handler\BeanstalkdHandler
 *
 * Usage example:
 *  Log::info('mall', 'test id:{id}', ['id' => 1, 'person' => ['name' => 'zq', 'email' => '123@qq.com']]);
 *
 * @author     <dendi875@163.com>
 * @createDate 2019-12-16 22:12:54
 * @copyright  Copyright (c) 2018 https://github.com/dendi875
 */

namespace Walle\Modules\Log;

use Monolog\Registry;
use Monolog\Logger;
use Pheanstalk\Pheanstalk;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\MemoryPeakUsageProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\ProcessIdProcessor;
use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\Processor\WebProcessor;
use Walle\Modules\Queue\BeanstalkdQueue;
use Walle\Monolog\Handler\BeanstalkdHandler;

class Log
{
    private static function registerLogger(Logger $logger)
    {
        Registry::addLogger($logger, null, true);
    }


    protected static function bindHandler(Logger $logger)
    {
        $pheanstalk = new Pheanstalk(BeanstalkdQueue::MQ_SERVER, 11300);

        $logger->pushHandler(new BeanstalkdHandler($pheanstalk, BeanstalkdQueue::QUEUE_LOG));
    }

    protected static function bindProcessor(Logger $logger)
    {
        $logger->pushProcessor(
            new ProcessIdProcessor() // 添加进程ID到日志记中
        )->pushProcessor(
            new IntrospectionProcessor(Logger::DEBUG, ['Walle\\Modules\\Log']) // 增加日志调用的时候的行号/文件/类/方法信息
        )->pushProcessor(
            new MemoryPeakUsageProcessor() // 添加峰值内存使用情况到日志记录中
        )->pushProcessor(
            new MemoryUsageProcessor()  // 添加当前的内存使用情况到日志记录中
        )->pushProcessor(
            new WebProcessor() // 添加当前请求的 URI, 请求方法和客户端 IP 到日志记录中
        )->pushProcessor(
            new PsrLogMessageProcessor() // 根据 PSR-3 的规则来处理日志记录，将 {foo} 替换成 $context['foo']
        );
    }

    protected static function buildLogger($channel)
    {
        $logger = new Logger($channel);

        static::bindProcessor($logger);

        static::bindHandler($logger);

        return $logger;
    }

    public static function __callStatic($method, $arguments)
    {
        $channel = array_shift($arguments);

        $logger = static::buildLogger($channel);

        static::registerLogger($logger);

        call_user_func_array(array(Registry::$channel(), $method), $arguments);
    }
}

