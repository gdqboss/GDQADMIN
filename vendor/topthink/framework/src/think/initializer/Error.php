<?php
declare(strict_types=1);

namespace think\initializer;

use think\App;
use think\exception\Handle;
use Throwable;

class Error
{
    public function init(App $app)
    {
        $app->bind([
            'handle' => Handle::class,
        ]);

        $errorHandler = function ($e) use ($app) {
            if ($e instanceof \Error) {
                // PHP 8.3 兼容性处理 - 将 Error 转换为异常
                $app->make(Handle::class)->renderForConsole(new \Symfony\Component\Console\Output\ConsoleOutput(), $e);
                return;
            }
            
            $handler = $app->make(Handle::class);
            $handler->renderForConsole(new \Symfony\Component\Console\Output\ConsoleOutput(), $e);
            $handler->report($e);
        };

        set_exception_handler($errorHandler);
        set_error_handler(function ($errno, $errstr, $errfile, $errline) use ($app) {
            if (!(error_reporting() & $errno)) {
                return;
            }
            
            // 忽略 PHP 8.3 的 Return type 相关警告
            if (strpos($errstr, 'Return type of') !== false || strpos($errstr, 'should either be compatible') !== false) {
                return;
            }
            
            $exception = new \ErrorException($errstr, 0, $errno, $errfile, $errline);
            $app->make('handle')->report($exception);
        });
    }
}
