<?php

require_once('./vendor/autoload.php');

use Walle\Modules\Log\Log;

Log::debug('app1', 'My log is now ready  id:{id}', ['id' => 1, 'person' => ['name' => '张权一', 'email' => '1@qq.com']]);
Log::info('app2', 'My log is now ready  id:{id}', ['id' => 2, 'person' => ['name' => '张权二', 'email' => '2@qq.com']]);
Log::notice('app3', 'My log is now ready  id:{id}', ['id' => 3, 'person' => ['name' => '张权三', 'email' => '3@qq.com']]);
Log::warning('app4', 'My log is now ready  id:{id}', ['id' => 4, 'person' => ['name' => '张权四', 'email' => '4@qq.com']]);
Log::error('app5', 'My log is now ready  id:{id}', ['id' => 5, 'person' => ['name' => '张权五', 'email' => '5@qq.com']]);
Log::critical('app6', 'My log is now ready  id:{id}', ['id' => 6, 'person' => ['name' => '张权六', 'email' => '6@qq.com']]);
Log::alert('app7', 'My log is now ready  id:{id}', ['id' => 7, 'person' => ['name' => '张权七', 'email' => '7@qq.com']]);
Log::emergency('app8', 'My log is now ready  id:{id}', ['id' => 8, 'person' => ['name' => '张权八', 'email' => '8@qq.com']]);

Log::log('app9', 'error', 'My log is now ready  id:{id}', ['id' => 9, 'person' => ['name' => '张权九', 'email' => '9@qq.com']]);
