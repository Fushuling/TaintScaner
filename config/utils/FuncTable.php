<?php
//处理用户自定义函数

class func_table
{
    public $func_name; //方法名
    public $func_param; //方法的参数
    public $func_stmt; // 方法内部的声明啥的
    public $file_path; // 保存文件路径
    //一共有四种flag： true false in null 
    //true表示是sink false表示不是sink in表示正在搜索中，不知道具体信息(为了防止成环的) null就是还没遍历过
    public $flag;
    public $sink; //保存污点路径

    public function __construct($func_name = null, $func_param = null, $func_stmt = null, $file_path = null, $flag = null)
    {
        $this->func_name = $func_name;
        $this->func_param = $func_param;
        $this->func_stmt = $func_stmt;
        $this->file_path = $file_path; // 将文件路径赋值给类属性
        $this->flag = $flag;
    }
}
