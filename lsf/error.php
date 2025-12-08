<?php
namespace Lsf;

/**
 * 错误输出类
 * @author
 * $Id: error.php $
 */

class Error
{
    static public $errorType = [
        E_ERROR              => 'Error',
        E_WARNING            => 'Warning',
        E_PARSE              => 'Parsing Error',
        E_NOTICE             => 'Notice',
        E_CORE_ERROR         => 'Core Error',
        E_CORE_WARNING       => 'Core Warning',
        E_COMPILE_ERROR      => 'Compile Error',
        E_COMPILE_WARNING    => 'Compile Warning',
        E_USER_ERROR         => 'User Error',
        E_USER_WARNING       => 'User Warning',
        E_USER_NOTICE        => 'User Notice',
        E_STRICT             => 'Runtime Notice',
        E_RECOVERABLE_ERROR  => 'Catchable Fatal Error'
    ];

    static public function exceptionPage($msg, $content = ''){
        $debugConfig = \Lsf\Loader::config('lsf', TRUE);
        $info = '';
        if(isset($debugConfig['debug']) && $debugConfig['debug'] === TRUE){
            $info = "
            <html>
                <head>
                    <title>$msg</title>
                    <meta http-equiv='Content-Type' content='text/html; charset=utf-8' />
                    <style type='text/css'>
                    *{
                        font-family:		Consolas, Courier New, Courier, monospace;
                        font-size:			14px;
                    }
                    body {
                        background-color:	#fff;
                        margin:				40px;
                        color:				#000;
                    }
                    #content  {
                    border:				#999 1px solid;
                    background-color:	#fff;
                    padding:			20px 20px 12px 20px;
                    line-height:160%;
                    }
                    h1 {
                    font-weight:		normal;
                    font-size:			14px;
                    color:				#990000;
                    margin: 			0 0 4px 0;
                    }
                    </style>
                </head>
                <body>
                    <div id='content'>
                        <h1>$msg</h1>
                        <p>$content</p><pre>";
            $trace = debug_backtrace();
            $info.= str_repeat('-', 100) . "\n";
            foreach($trace as $k => $t){
                if(empty($t['line'])){
                    $t['line'] = 0;
                }
                if(empty($t['class'])){
                    $t['class'] = '';
                }
                if(empty($t['type'])){
                    $t['type'] = '';
                }
                if(empty($t['file'])){
                    $t['file'] = 'unknow';
                }
                $info.= "#$k line:{$t['line']} call:{$t['class']}{$t['type']}{$t['function']}\tfile:{$t['file']}\n";
            }
            $info.= str_repeat('-', 100) . "\n";
            $info.= '</pre></div></body></html>';
        }
        return $info;
    }
}