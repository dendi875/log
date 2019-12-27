# Log

基于`Monolog`、`Beanstalkd`及`Elasticsearch`、`Kibana`开发的一个日志系统
---------------

## 前言

日志文件是开发人员佣有的最有价值的资产之一。和`core dump`文件一样可以起到一个死后验尸的功能。

在开发和测试阶段可以依靠日志来定位，重现问题和解决错误，提升开发效率。
当线上有异常问题出现时可以通过日志文件来帮助你分析、定位出可能出问题的地方，从而可以快速解决问题，对于紧急严重的问题更是如此，精确定位问题并快速解决能够最大化的降低影响面及损失。不然，出问题后只能靠经验去猜测可能出问题的地方。

日志监控系统能确认系统的正确性，像医师的听诊器一样，它是我们开发人员的电子哨兵。


## 概述

本篇日志系统整体架构思路是：

* 扩展 PHP 第三方的日志库[Monolog](https://github.com/Seldaek/monolog)把日志发送到`Beanstalkd`队列里
* 通过后台的一个`常驻内存脚本`来消费队列中的消息，并把消息索引到`Elasticsearch`中
* 配置`Kibana`来连通`ES`从而来搜索展示日志记录

为什么选择这个设计方案呢？

1）选择`Monolog`是因为它现在是`PHP`最流行的日志记录器，它实现了`PSR-3`并且多个开源框架都在使用，但目前的版本（`^2.0`）还不支持发送日志到`Beanstalkd`，所以我们对它进行扩展，写一个`BeanstalkdHandler`

注：本身`Monolog`已经支持发送日志到`RabbitMQ`中

2）为什么不选择通过`Monolog`直接发送日志到`ES`中？因为性能问题。通过把日志记录到队列中的好处是，记录日志时不会阻塞你的应用程序，能使服务器快速响应用户请求，而不必立即执行大量耗时耗资源的程序

3）毫无疑问`Elasticsearch`和`Kibana`是现在最使用最广泛的日志分析平台，当然选择它

整个系统还有一个关键点要考虑就是：如果后台的常驻脚本因为程序意外退出后系统能够自动拉起，这样异常发生时消息才不会堆积，不会导致内存使用暴涨从而拖垮机器。对于这点我们使用的是`daemontools`这个进程守护工具来达到我们的目的。


**备注：**

对于上面系统中描述用到的开源组件的使用感兴趣的话，可以参考以下我的几篇文章：

[Daemontools 学习研究](https://github.com/dendi875/Linux/blob/master/daemontools%E7%A0%94%E7%A9%B6%E5%AD%A6%E4%B9%A0.md)

[Beanstalkd 学习研究](https://github.com/dendi875/PHP/blob/master/queue%E4%B9%8Bbeanstalkd.md)

[Elasticsearch 学习研究](https://github.com/dendi875/Linux/blob/master/elasticsearch%E7%A0%94%E7%A9%B6%E5%AD%A6%E4%B9%A0.md)

[Elasticsearch-PHP API 的使用](https://github.com/dendi875/PHP/blob/master/Elasticsearch-PHP%20%E7%9A%84%E4%BD%BF%E7%94%A8.md)

[Kibana 学习研究](https://github.com/dendi875/Linux/blob/master/Kibana%E5%AD%A6%E4%B9%A0%E7%A0%94%E7%A9%B6.md)


## 系统目录结构

```sh
├── composer.json
├── composer.lock
├── daemons
│   └── log-daemon.php
├── log.php
├── phpunit.xml.dist
├── tests
│   ├── bootstrap.php
│   └── Modules
│       └── Helper
│           └── UtilsTest.php
└── walle
    ├── Modules
    │   ├── Helper
    │   │   └── Utils.php
    │   ├── Log
    │   │   └── Log.php
    │   └── Queue
    │       └── BeanstalkdQueue.php
    └── Monolog
        └── Handler
            └── BeanstalkdHandler.php
```

* `daemons/log-daemon.php` 该文件是就是我们的守护进程脚本，负责消费队列中的消息并把消息索引到`ES`中
* `log.php` 测试文件，模拟上层业务系统调用`Log`类
* `tests` 单元测试文件存放目录
* `walle/Modules` 包装好的通用业务和系统功能组件，或者对第三方库封装的模块可以放到这个目录下。比如：`walle/Modules/Helper/Utils.php`是我们自己封装的辅助类，再比如：`walle/Modules/Log/Log.php`对扩展后的`monolog`库再次包装的类，它是直接提供给上层业务系统调用的
* `walle/Monolog` 存放对`monolog`扩展的处理器（`Handler`）、加工器（`Processor`）、格式化器（`Formatter`）

## 基本使用

### 安装

```shell
$ composer install
```

### 使用示例

* 构建 `log daemontools Service`

```sh
# cd /scratch/service/
# mkdir log
# cd log/
```

创建一个run文件，其中包含：

```sh
#!/bin/sh
exec 2>&1
exec su - root -c "php /yourpath/log/src/daemons/log-daemon.php" 1>> /yourpath/log-daemon.php
```

赋予执行权限

```sh
# chmod u+x run
```

安装log服务并实际开始运行它

```sh
# ln -s /scratch/service/log/ /service/log
```

确认进程正在运行

```sh
# ps -ef | grep log-daemon.php
```

到这里我们的守护进程已经在后台运行了，而且被`daemontools`监护着，我们通过`beanstalk_console`可以看到名为`log`的`tube`已经产生

![beanstalk_console_log](https://github.com/dendi875/images/blob/master/PHP/log/beanstalk_console_log.png)


* 使用`Walle\Modules\Log\Log`类来记录日志

运行我们的测试程序，使用我们封装的`Log`类来记录日志，日志被发送到`Beanstalkd`的`log`队列中

```sh
$ php log.php 
```

再次查看`beanstalk_console`，可以看到日志已经被消费了

![beanstalk_console_log2](https://github.com/dendi875/images/blob/master/PHP/log/beanstalk_console_log2.png)


* 使用`Kinaba`来搜索日志

在`Kinaba`上创建`log-*`索引模式

![kinaba_config_index_pattern](https://github.com/dendi875/images/blob/master/PHP/log/kinaba_config_index_pattern.png)


这是创建完成后的`log-*`索引的字段类型信息

![kinaba_config_index_pattern2](https://github.com/dendi875/images/blob/master/PHP/log/kinaba_config_index_pattern2.png)


搜索日志看看，我们搜索最近30分钟，日志级别是`error`以上的所有日志


![kinaba_discover_search](https://github.com/dendi875/images/blob/master/PHP/log/kinaba_discover_search.png)

再次运行测试程序

```sh
$ php log.php 
```

现在直接去`Kibana`里搜索看看，我们搜索最近30分钟，日志级别是`error`以上，上下文内容（`context`）包含“张权九”关键字并且`extra`中进程ID是 8968 的所有日志

![kinaba_discover_search2](https://github.com/dendi875/images/blob/master/PHP/log/kinaba_discover_search2.png)


### 单元测试

```sh
$ phpunit
PHPUnit 5.7.25 by Sebastian Bergmann and contributors.

..                                                                  2 / 2 (100%)

Time: 384 ms, Memory: 3.25MB

OK (2 tests, 4 assertions)
```

## 思考和总结

至此，我们设计并开发了一个还不错的日志系统，回过头来想想我们系统有什么优点？还有什么可以改善的地方？有什么使用中要留意的坑？

### 优点

* 使用方便，开发人员只要一行代码就能在代码中记录想记录的信息，然后通过`Kinaba`可以直观的观察统计等

* 性能好，日志首先记录到队列，调用时不会阻塞你客户端程序的执行。我们也可以把`Beanstalkd`换成`RabbitMQ`或`Kafka`来玩玩看

* 方便扩展和维护，基于`Monolog`我们可以扩展我们自己内部的处理器（`Handler`）和加工器（`Processor`）

### 改善

* 可以在消费者程序中加入**日志报警**功能，比如对于`error`级别以上的日志，可以发送短信、邮件、企业微信等给相应的开发人员


* 完善剩下的单元测试

### 注意事项 

* 消费者程序`log-daemon.php`是常驻内存的，程序运行后就把我们的代码加载到内存了，无论后期我们怎么修改磁盘上的代码，重新再次发起请求的时候，永远都是内存中的代码生效，所以我们修改代码后要杀死`log-daemon.php`进程，释放掉内存，重新把新的代码加载内存中。**我们只要`kill`就行，`Daemontools`会自动帮我们重新拉起。**

* 还是因为`log-daemon.php`是常驻内存的，所以编写代码时一定要小心，防止内存泄漏。