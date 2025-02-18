<?php
//处理用户自定义函数
/*
对于用户自定义函数的处理：在遍历主代码之前，首先遍历一边所有自定义函数的定义，打上标签，是否是transfer，是否是sink，然后
其他代码执行到该函数时，查表决定下一步流程；
*/
class FuncTable
{
    public $funcName;   // 方法名
    public $funcSink;     // 是否是 sink

    public function __construct($funcName, $funcSink)
    {
        $this->funcName = $funcName;
        $this->funcSink = $funcSink;
    }
}
