<?php
/*
  Sink点
 */
class Sink
{
    public $type;    // Sink点类型
    public $name;    // Sink点函数名
    public $linenum; // 传播流的代码
    public $sink_num; //触发点的行号
}

//用于dirtyFunc
class FuncSink
{
    public $type;    // Sink点类型
    public $name;    // Sink点函数名
    public $funcName; //这个方法的名字
    public $linenum; // 传播流的代码
    public $sink_num; //触发点的行号
    public $symbol;  //是有条件触发还是无条件触发：有条件触发即传入参数可控 无条件触发即不需要传入参数，函数一运行就有漏洞
    public function __construct($type, $name, $funcName, $linenum, $sink_num, $symbol)
    {
        $this->type = $type;
        $this->name = $name;
        $this->funcName = $funcName;
        $this->linenum = $linenum;
        $this->sink_num = $sink_num;
        $this->symbol = $symbol;
    }
}

//这里是内置函数的sink点集合

// XSS漏洞
$XSS = array(
    'echo'   => 'XSS',
    'print'  => 'XSS',
    'print_r' => 'XSS',
    'printf' => 'XSS',
);

// 代码评估函数
$CODE = array(
    'assert'          => 'CODE',
    'call_user_func'  => 'CODE',
    'call_user_func_array' => 'CODE',
    'create_function' => 'CODE',
    'eval'            => 'CODE',
    'fwrite'          => 'CODE',
);

// 文件包含函数
$INCLUDE = array(
    'include'         => 'INCLUDE',
    'include_once'    => 'INCLUDE',
    'require'         => 'INCLUDE',
    'require_once'    => 'INCLUDE',
    'set_include_path' => 'INCLUDE',
);

// 系统命令执行函数
$EXEC = array(
    'exec'            => 'EXEC',
    'expect_popen'    => 'EXEC',
    'passthru'        => 'EXEC',
    'pcntl_exec'      => 'EXEC',
    'popen'           => 'EXEC',
    'proc_open'       => 'EXEC',
    'shell_exec'      => 'EXEC',
    'system'          => 'EXEC',
);

// SQL语句执行函数
$SQL = array(
    'msql_db_query'   => 'SQLI',
    'msql_query'      => 'SQLI',
    'msql'            => 'SQLI',
    'mssql_query'     => 'SQLI',
    'mssql_execute'   => 'SQLI',
    'mysql_db_query'  => 'SQLI',
    'mysql_query'     => 'SQLI',
    'mysqli_query'    => 'SQLI',
    'query'  => 'SQLI',
    '编辑弹幕' => 'SQLI',
);

// php反序列化
$UNSERIALIZE = array(
    'unserialize'     => 'UNSERIALIZE',
);

//用于添加用dirty_func扫描出的新sink
$NEW_SINK = array();

// 合并所有漏洞类型函数
$SinkAll = array_merge(
    $XSS,
    $SQL,
    $EXEC,
    $INCLUDE,
    $CODE,
    $UNSERIALIZE,
    $NEW_SINK,
);
