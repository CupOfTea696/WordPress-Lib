<?php

namespace CupOfTea\WordPress\Exception;

use Exception;
use Throwable;
use ErrorException;
use CupOfTea\WordPress\Service;
use Symfony\Component\Debug\ExceptionHandler as SymfonyExceptionHandler;

class Handler extends Service
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [];
    
    /**
     * A list of the exception types that should not be backtraced.
     *
     * @var array
     */
    protected $dontBacktrace = [
        \PDOException::class,
        \mysqli_sql_exception::class,
    ];
    
    /**
     * Report or log an exception.
     *
     * @param  \Exception  $e
     * @return void
     */
    public function report($e)
    {
        if ($e instanceof Throwable) {
            $e = $this->throwableToException($e);
        }
        
        if ($this->shouldntReport($e)) {
            return;
        }
        
        app('Psr\Log\LoggerInterface')->error((string) $e);
    }
    
    /**
     * Determine if the exception should be reported.
     *
     * @param  \Exception  $e
     * @return bool
     */
    public function shouldReport(Exception $e)
    {
        return ! $this->shouldntReport($e);
    }
    
    /**
     * Determine if the exception is in the "do not report" list.
     *
     * @param  \Exception  $e
     * @return bool
     */
    protected function shouldntReport(Exception $e)
    {
        foreach ($this->dontReport as $type) {
            if ($e instanceof $type) {
                return true;
            }
        }
        
        return false;
    }
    
    protected function throwableToException(Throwable $t)
    {
        $prev = $t->getPrevious();
        
        if ($prev instanceof Throwable) {
            $prev = $this->throwableToException($prev);
        }
        
        return new ErrorException($t->getMessage(), 0, $t->getCode(), $t->getFile(), $t->getLine());
    }
    
    /**
     * Determine if the exception should be backtraced.
     *
     * @param  \Exception  $e
     * @return bool
     */
    public function shouldBacktrace(Exception $e)
    {
        return ! $this->shouldntBacktrace($e);
    }
    
    /**
     * Determine if the exception is in the "do not report" list.
     *
     * @param  \Exception  $e
     * @return bool
     */
    protected function shouldntBacktrace(Exception $e)
    {
        foreach ($this->dontBacktrace as $type) {
            if ($e instanceof $type) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function render($e)
    {
        if ($e instanceof Throwable) {
            $e = $this->throwableToException($e);
        }
        
        if ($this->shouldntBacktrace($e)) {
            return with(new ExceptionHandler(env('APP_DEBUG')))->handle($e);
        }
        
        return with(new SymfonyExceptionHandler(env('APP_DEBUG')))->handle($e);
    }
}
