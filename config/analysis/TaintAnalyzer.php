<?php

class VarInfo
{
    public $linenum; //行号
    public $tainted; //是否污染

    public function __construct($linenum, $tainted)
    {
        $this->linenum = $linenum;
        $this->tainted = $tainted;
    }
}

class TaintAnalyzer
{
    /* 
    $varMap = [
    "VarName1" => new VarInfo(10, 1),     
    "VarName2" => new VarInfo(20, 0)
    ];  变量的污染表，负责污点的传播
    */
    public $varMap; // 变量集合
    public $sinks; //污点
    public $spread_linenum; //污点传播路径


    public function __construct()
    {
        $this->varMap = [];
        $this->sinks = [];
        $this->spread_linenum = [];
    }

    // 污点传播
    public function TaintSpread($quads_set)
    {
        $this->varMap = [];
        $this->sinks = [];
        $this->spread_linenum = [];
        for ($i = 0; $i < count($quads_set); $i++) {
            //遍历该控制流中的所有Q_IR
            $this->VarAnalyze($i, $quads_set);
            $this->CheckSink($i, $quads_set);
        }
        //var_dump($this->varMap);
        //var_dump($this->sinks);
    }

    public function VarAnalyze($id, $quads_set)
    {
        //如$a = $b {id,Expr_Assign,null,$b的id或者$b，$a}
        if (
            $quads_set[$id]->opcode == "Expr_Assign"
            && $quads_set[$id]->dest instanceof PhpParser\Node\Expr\Variable
        ) {
            $from1 = $quads_set[$id]->operand1;
            $from2 = $quads_set[$id]->operand2;
            $linenum = $quads_set[$id]->dest->getStartLine();
            $Varname = $quads_set[$id]->dest->name;
            $tainted = $this->CheckTaint($from1, $quads_set) || $this->CheckTaint($from2, $quads_set);
            $this->varMap[$Varname] = new VarInfo($linenum, $tainted);
            if ($tainted == true) {
                array_push($this->spread_linenum, $linenum);
            }
        }
    }

    //检查污点的逻辑
    public function CheckTaint($from, $quads_set)
    {
        //若是数字 那么肯定代表的是quad id
        if (is_numeric($from)) {
            return $this->CheckID($from, $quads_set);
        }
        //来源是空肯定不是污点
        if ($from == null) {
            return false;
        }
        //是字符串或者数字这种常量(不是说quad_id id是int)，肯定不是污点
        if ($from instanceof PhpParser\Node\Scalar\String_ || $from instanceof PhpParser\Node\Scalar\LNumber) {
            return false;
        }
        //如果是变量 就去变量表找污点情况
        if ($from instanceof PhpParser\Node\Expr\Variable) {
            return $this->CheckVar($from->name);
        }
        //提前设置好的，直接为污点的数据
        if ($from == "Tainted") {
            return true;
        }

        return false;
    }

    public function CheckVar($varname)
    {
        // 检查 $varname 是否是 $this->varMap 数组的键
        if (array_key_exists($varname, $this->varMap)) {
            return $this->varMap[$varname]->tainted; // 返回 tainted 属性
        } else {
            return false; // 如果不存在，返回 0
        }
    }


    //检查quad id
    public function CheckID($id, $quads_set)
    {
        ////var_dump($quads_set[$id]);
        //处理用户输入相关变量  如$_POST[1]  解析为{id,Expr_ArrayDimFetch,_POST,1,null}
        if (
            $quads_set[$id]->opcode == "Expr_ArrayDimFetch"
            && $quads_set[$id]->operand1 instanceof PhpParser\Node\Expr\Variable
        ) {
            // $_GET、$_POST、$_REQUEST
            $type = $quads_set[$id]->operand1->name;
            $sources = new Sources();
            $input = $sources->GetUserInput();
            //将用户输入标记为污点
            if (in_array($type, $input)) {
                return true;
            }
        }
        if ($quads_set[$id]->opcode == "Expr_BinaryOp_Concat") {
            return $this->CheckTaint($quads_set[$id]->operand1, $quads_set) || $this->CheckTaint($quads_set[$id]->operand2, $quads_set);
        }

        if ($quads_set[$id]->opcode == "Expr_FuncCall_Finish") {
            for ($i = $quads_set[$id]->operand1; $i <= $quads_set[$id]->operand2; $i++) {
                if ($quads_set[$i]->opcode == "Stmt_Return") {
                    return $this->CheckVar($quads_set[$i]->dest->name);
                }
            }
        }

        if ($quads_set[$id]->opcode == "Whole_Encapsed_End") {
            for ($i = $quads_set[$id]->operand1; $i <= $quads_set[$id]->operand2; $i++) {
                if ($quads_set[$i]->opcode == "Encapsed_Param") {
                    if ($this->CheckVar($quads_set[$i]->dest->name) == true) {
                        return true;
                    }
                }
            }
        }

        //用户函数
        if ($quads_set[$id]->opcode == "Expr_FuncCall_Finish") {
            //$quads_set[$id]->operand2指的是Expr_FuncCall 他的operand1保存了函数名
            $UserFuncName = $quads_set[$quads_set[$id]->operand2]->operand1->parts[0];
            global $SanitizerAll;
            if (in_array($UserFuncName, $SanitizerAll)) {
                return false;
            }
            for ($i = $quads_set[$id]->operand1; $i <= $quads_set[$id]->operand2; $i++) {
                if ($quads_set[$i]->opcode == "UserFunc_Call_Param") {
                    if ($quads_set[$i]->dest->value instanceof PhpParser\Node\Expr\Variable) {
                        if ($this->CheckVar($quads_set[$i]->dest->value->name) == true) {
                            return true;
                        }
                    }
                }
            }
        }

        //内置函数
        if ($quads_set[$id]->opcode == "Expr_FuncCall_Internal_Finish") {
            global $SanitizerAll;
            $CallFuncName = $quads_set[$quads_set[$id]->operand2]->operand1->parts[0];

            if (in_array($CallFuncName, $SanitizerAll)) {
                return false;
            }

            for ($i = $quads_set[$id]->operand1; $i <= $quads_set[$id]->operand2; $i++) {
                if ($quads_set[$i]->opcode == "Internal_Call_Param") {
                    if ($quads_set[$i]->dest->value instanceof PhpParser\Node\Expr\Variable) {
                        if ($this->CheckVar($quads_set[$i]->dest->value->name) == true) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }
    //检查用户函数的参数
    public function CheckUserParam($id, $quads_set)
    {
        //operand1到operand2是函数作用域
        for ($i = $quads_set[$id]->operand1; $i <= $quads_set[$id]->operand2; $i++) {
            if ($quads_set[$i]->opcode == "UserFunc_Call_Param") {
                if ($quads_set[$i]->dest->value instanceof PhpParser\Node\Expr\Variable) {
                    if ($this->CheckVar($quads_set[$i]->dest->value->name) == true) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    //检查内置函数参数
    public function CheckInternalParam($id, $quads_set)
    {
        //operand1到operand2是函数作用域
        for ($i = $quads_set[$id]->operand1; $i <= $quads_set[$id]->operand2; $i++) {
            if ($quads_set[$i]->opcode == "Internal_Call_Param") {
                if ($quads_set[$i]->dest->value instanceof PhpParser\Node\Expr\Variable) {
                    if ($this->CheckVar($quads_set[$i]->dest->value->name) == true) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    //Sink点的判断 看传入的参数是否是污点
    public function CheckSink($i, $quads_set)
    {
        global $SinkAll; //所有sink点
        if (
            $quads_set[$i]->opcode == "Expr_FuncCall" ||
            $quads_set[$i]->opcode == "Expr_FuncCall_Internal" || $quads_set[$i]->opcode == "Stmt_Echo"
            || $quads_set[$i]->opcode == "Expr_Include" || $quads_set[$i]->opcode == "Expr_Eval"
        ) {
            if ($quads_set[$i]->opcode == "Expr_FuncCall") {
                $funcName = $quads_set[$i]->operand1->parts[0];
            } elseif ($quads_set[$i]->opcode == "Expr_FuncCall_Internal") {

                if ($quads_set[$i]->operand1 instanceof PhpParser\Node\Name) {
                    $funcName = $quads_set[$i]->operand1->parts[0];
                } else {
                    $funcName = $quads_set[$i]->operand1->name;
                }
            } elseif ($quads_set[$i]->opcode == "Stmt_Echo") {
                $funcName = "echo";
            } elseif ($quads_set[$i]->opcode == "Expr_Include") {
                $funcName = "include";
            } elseif ($quads_set[$i]->opcode == "Expr_Eval") {
                $funcName = "eval";
            }
            $taint_symbol = false; // 用于没做特殊处理的一般函数
            global $UserFuncs;
            //$SinkAll是包含所有sink点的集合
            if (array_key_exists($funcName, $SinkAll) || $UserFuncs[md5($funcName)]->funcSink == true) {

                if ($quads_set[$i]->opcode == "Expr_FuncCall") {
                    $argName = "";  //表示一个不存在的方法 函数是另一套处理逻辑
                    $startline = $quads_set[$i]->operand1->getStartLine();
                    //$quads_set[$i]->dest指向的是Expr_FuncCall，函数作用域
                    $taint_symbol = $this->CheckUserParam($quads_set[$i]->dest, $quads_set);
                    //////var_dump($taint_symbol);
                } elseif ($quads_set[$i]->opcode == "Expr_FuncCall_Internal") {
                    $argName = "";  //表示一个不存在的方法 函数是另一套处理逻辑
                    $startline = $quads_set[$i]->operand1->getStartLine();
                    //$quads_set[$i]->dest指向的是Expr_FuncCall_Internal，函数作用域
                    $taint_symbol = $this->CheckInternalParam($quads_set[$i]->dest, $quads_set);
                } elseif ($quads_set[$i]->opcode == "Stmt_Echo") {
                    $argName = $quads_set[$i]->operand2->name;
                    //var_dump($quads_set[$i]);

                    $startline = $quads_set[$i]->operand2->getStartLine();
                } elseif ($quads_set[$i]->opcode == "Expr_Include") {
                    //var_dump($quads_set[$i]);
                    $argName = $quads_set[$i]->operand2->name;

                    $startline = $quads_set[$i]->operand2->getStartLine();
                } elseif ($quads_set[$i]->opcode == "Expr_Eval") {
                    //var_dump($quads_set[$i]->operand2->getStartLine());
                    $argName = $quads_set[$i]->operand2->name;

                    $startline = $quads_set[$i]->operand2->getStartLine();
                }
                //用于自定义函数和部分函数的处理
                if ($taint_symbol == true) {
                    $sink = new Sink();
                    $sink->linenum = $this->spread_linenum;
                    $sink->sink_num = $startline;

                    $sink->type = $SinkAll[$funcName];
                    $sink->name = $funcName;
                    array_push($this->sinks, $sink);
                } //遍历变量表 如果变量名称匹配且污染状态为true（被污染），记录漏洞信息
                else if (array_key_exists($argName, $this->varMap)) {
                    // 如果存在，检查对应的 tainted 值
                    if ($this->varMap[$argName]->tainted == true) {
                        // 如果 tainted 为 true，创建 Sink 对象并执行相应操作
                        $sink = new Sink();
                        $sink->linenum = $this->spread_linenum;
                        $sink->sink_num = $startline;

                        $sink->type = $SinkAll[$funcName];
                        $sink->name = $funcName;
                        array_push($this->sinks, $sink);
                    }
                }
            }
        }
    }
}
