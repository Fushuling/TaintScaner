<?php
class Q_IR
{
    public $id;        // 指令编号（唯一标识一个四元组）
    public $opcode;    // 操作符，例如赋值、加法、函数调用等
    public $operand1;  // 第一个操作数
    public $operand2;  // 第二个操作数（如果适用）
    public $dest;      // 目标（存储计算结果）

    //初始化四元式的各个属性
    public function __construct($id, $opcode = null, $operand1 = null, $operand2 = null, $dest = null)
    {
        $this->id = $id;
        $this->opcode = $opcode;
        $this->operand1 = $operand1;
        $this->operand2 = $operand2;
        $this->dest = $dest;
    }
}

class AstToIrConverter
{

    public $quadId; //QuadID
    public $quads; //Quad数组
    public $funcs; //保存方法
    public $funcSink;
    public $sourceFlag; //是否发现了source 只解析source开始的ir 没有污点传播的ir没有价值


    //解析AST，返回解析AST得到的四元组，代码的核心逻辑
    public function AstParse($nodes)
    {
        if (!is_array($nodes)) {
            $nodes = array($nodes);
        }

        $this->ResetQuads();
        $this->processStatements($nodes);

        return $this->quads;
    }

    //专门给方法的解析用的
    public function FuncParse($nodes, $arg) //$arg是参数，用于对初始污点赋值
    {
        if (!is_array($nodes)) {
            $nodes = array($nodes);
        }

        $this->ResetQuads();
        for ($i = 0; $i < count($arg); $i++) {
            $this->quadId += 1;
            $this->quads[$this->quadId] = new Q_IR($this->quadId, "Expr_Assign", null, "Tainted", $arg[$i]);
            $this->sourceFlag = true;
        }
        $this->processStatements($nodes);

        return $this->quads;
    }

    //构建全局函数表
    public function build_funcTable($nodes, $file_path)
    {
        if (!is_array($nodes)) {
            $nodes = [$nodes];
        }

        foreach ($nodes as $node) {
            if ($node instanceof PhpParser\Node\Stmt\Function_) {
                // var_dump($node);
                $func_name = $node->name->name;
                $func_param = array();
                foreach ($node->params as $param) {
                    if ($param->var instanceof PhpParser\Node\Expr\Variable) {
                        array_push($func_param, $param->var);
                    }
                }
                // $func_arg = $node->name;
                $func_stmt = $node->stmts;
                $hash = md5($func_name);

                // 获取绝对路径并统一分隔符为 '/'
                $absolute_path = realpath($file_path); // 获取绝对路径
                $normalized_path = str_replace("\\", "/", $absolute_path); // 替换反斜杠为斜杠

                // 将函数信息加入funcs表，传入文件路径
                if (!isset($this->funcs[$hash])) {
                    $this->funcs[$hash] = new func_table(
                        $func_name,
                        $func_param,
                        $func_stmt,
                        $normalized_path, // 使用绝对路径并统一分隔符
                        'null'
                    );
                }
            } else if ($node instanceof PhpParser\Node\Stmt\Class_) {
                foreach ($node->stmts as $classNode) {
                    if ($classNode instanceof PhpParser\Node\Stmt\ClassMethod) {
                        $func_name = $classNode->name->name;
                        $func_stmt = $classNode->stmts; // 方法体
                        $hash = md5($func_name);
                        $func_param = array();
                        $funcParam = $classNode->params;
                        foreach ($funcParam as $param) {
                            if ($param->var instanceof PhpParser\Node\Expr\Variable) {
                                array_push($func_param, $param->var);
                            }
                        }
                        // 获取绝对路径并统一分隔符为 '/'
                        $absolute_path = realpath($file_path); // 获取绝对路径
                        $normalized_path = str_replace("\\", "/", $absolute_path); // 替换反斜杠为斜杠
                        if (!isset($this->funcs[$hash])) {
                            $this->funcs[$hash] = new func_table(
                                $func_name,
                                $func_param,
                                $func_stmt,
                                $normalized_path, // 使用绝对路径并统一分隔符
                                'null'
                            );
                        }
                    }
                }
            }
        }
    }

    //重置quad
    public function ResetQuads()
    {
        $this->funcSink = false;
        $this->sourceFlag = false;
        $this->quadId = -1;
        $this->quads = [];
        $this->quadId += 1;
        $this->quads[$this->quadId] = new Q_IR($this->quadId);
    }

    //生成quad
    public function GenerateQuads($id, $opcode, $operand1, $operand2, $dest)
    {
        if ($this->sourceFlag == true) {
            $this->quadId += 1;
            $this->quads[$this->quadId] = new Q_IR($this->quadId, $opcode, $operand1,  $operand2, $dest);
        }
    }

    //分发语句处理任务
    public function processStatements($nodes)
    {
        if (!is_array($nodes)) {
            $nodes = array($nodes);
        }

        foreach ($nodes as $node) {
            // $this->checkMemoryLimit();
            if ($node instanceof PhpParser\Node\Stmt) {
                $this->StmtParse($node);
            }
        }
    }

    //语句节点，即函数体，语句的具体内容
    public function StmtParse($stmt)
    {
        //若有多条语句，逐条解析
        if (is_array($stmt)) {
            foreach ($stmt as $stmt_single) {
                $this->StmtParse($stmt_single);
            }
        }

        //表达式语句（如 $x = 5） 调用ExprParse解析
        if ($stmt instanceof PhpParser\Node\Stmt\Expression) {
            $this->ExprParse($stmt->expr);
        }

        //include语句的处理
        if ($stmt->expr instanceof PhpParser\Node\Expr\Include_) {
            //{'Expr_Include','null',输出的exprs,null}
            $this->GenerateQuads($this->quadId, $stmt->expr->getType(), null, $stmt->expr->expr, null);
        }

        //eval语句的处理
        if ($stmt->expr instanceof PhpParser\Node\Expr\Eval_) {
            //{'Expr_Eval','null',输出的exprs,null}
            $this->GenerateQuads($this->quadId, $stmt->expr->getType(), null, $stmt->expr->expr, null);
        }

        //echo语句的处理
        if ($stmt instanceof PhpParser\Node\Stmt\Echo_) {
            //{'Stmt_Echo','null',输出的exprs,null}
            //echo是多参数函数
            for ($i = 0; $i < count($stmt->exprs); $i++) {
                if ($stmt->exprs[$i] instanceof PhpParser\Node\Expr\Variable) {
                    $this->GenerateQuads($this->quadId, $stmt->getType(), null, $stmt->exprs[$i], null);
                }
            }
        }

        //foreach语句的处理
        //比如foreach ($tlist as $row) {...}  本质相当于做一个$row = $tlist，然后解析内部的表达式
        if ($stmt instanceof PhpParser\Node\Stmt\Foreach_) {
            if ($stmt->expr instanceof PhpParser\Node\Expr\Variable && $stmt->valueVar instanceof PhpParser\Node\Expr\Variable) {
                $this->GenerateQuads($this->quadId, "Expr_Assign", null, $stmt->expr, $stmt->valueVar);
            }
            $this->processStatements($stmt->stmts);
        }

        //注意减枝，不然大项目里控制流就爆炸了
        //解析try-catch 不管条件 直接解析stmt 用start和finish分离每一个调用域
        if ($stmt instanceof PhpParser\Node\Stmt\TryCatch) {
            $this->quadId += 1;
            $try_start = $this->quadId;
            //{Whole_Branch_Start,null,null,null}
            $this->quads[$this->quadId] = new Q_IR($this->quadId, "Whole_Branch_Start", null, null, null);

            $this->quadId += 1;
            $start_id = $this->quadId;
            $before = $start_id;
            //{Stmt_Branch_Start,null,$try_start,$dest} try_start:Branch语句整体的开始(而不是分支)(Whole_Branch_Start的id) 
            //dest:Branch语句整体的结束 Whole_Branch_Finish对应的id  每个小分支，Stmt_Branch_Start operand2指向整体的开始,dest指向整体的结束
            //结束后回填dest
            $this->quads[$this->quadId] = new Q_IR($this->quadId, "Stmt_Branch_Start", null, $try_start, null);
            //解析try的stmt
            $this->processStatements($stmt->stmts);

            //减枝，如果分支为空，就没有必要新建基本块了
            if ($this->quadId != $start_id) {
                $this->quadId += 1;
                $end_id = $this->quadId;

                //start到end  标记本次try的作用域
                $this->quads[$this->quadId] = new Q_IR($this->quadId, "Stmt_Branch_Finish", $start_id, $end_id, null);
            } else {
                $before = null;
                unset($this->quads[$start_id]);
                $this->quadId--;
            }

            //解析catches的stmt
            $this->quadId += 1;
            $start_id = $this->quadId;
            //同上
            $this->quads[$this->quadId] = new Q_IR($this->quadId, "Stmt_Branch_Start", null, $try_start, null);
            //解析try的stmt
            $this->processStatements($stmt->catches[0]->stmts);
            if ($this->quadId != $start_id) {
                $this->quadId += 1;
                $end_id = $this->quadId;

                //start到end  标记本次catch的作用域
                $this->quads[$this->quadId] = new Q_IR($this->quadId, "Stmt_Branch_Finish", $start_id, $end_id, null);
            } else {
                unset($this->quads[$start_id]);
                $start_id = null;
                $this->quadId--;
            }

            if ($try_start != $this->quadId) {
                //整个try-catch结束
                $this->quadId += 1;
                $this->quads[$this->quadId] = new Q_IR($this->quadId, "Whole_Branch_Finish", null, null, null);
            } else {
                unset($this->quads[$try_start]);
                $this->quadId--;
            }

            if ($before != null) {
                $this->quads[$before]->dest = $this->quadId;
            }
            if ($start_id != null) {
                //回填跳转位置
                $this->quads[$start_id]->dest = $this->quadId;
            }
        }



        if ($stmt instanceof PhpParser\Node\Stmt\If_) {
            //if的逻辑过于复杂，专门写个函数解析
            $this->IfParse($stmt);
        }

        //对switch的解析过程和if类似，也是需要注意存不存在default，default本质上和case没有差别，只是default没有条件
        if ($stmt instanceof PhpParser\Node\Stmt\Switch_) {
            $cases = $stmt->cases;
            $cases_count = count($cases);

            $this->quadId += 1;
            $Whole_Branch_start_id = $this->quadId;
            //{Whole_Branch_Start,null,null,null}
            $this->quads[$this->quadId] = new Q_IR($this->quadId, "Whole_Branch_Start", null, null, null);

            //解析每个cases
            $flag = false; //标记是否存在default
            $cases_start = array();
            for ($i = 0; $i < $cases_count; $i++) {
                $this->quadId += 1;
                $start_id = $this->quadId;
                $this->quads[$this->quadId] = new Q_IR($this->quadId, "Stmt_Branch_Start", null, $Whole_Branch_start_id, null);
                if ($cases[$i]->cond == null) {
                    $flag = true;
                }
                $this->processStatements($cases[$i]->stmts);
                if ($start_id != $this->quadId) {
                    $this->quadId += 1;
                    $end_id = $this->quadId;
                    array_push($cases_start, $start_id);
                    $this->quads[$this->quadId] = new Q_IR($this->quadId, "Stmt_Branch_Finish", $start_id, $end_id, null);
                } else {
                    unset($this->quads[$start_id]);
                    $this->quadId--;
                }
            }
            //不存在default的话，需要增加一条不经过switch的分支
            if ($flag == false && count($cases_start) != 0) {
                $this->quadId += 1;
                $start_id = $this->quadId;
                $this->quads[$this->quadId] = new Q_IR($this->quadId, "Stmt_Branch_Start", null, $Whole_Branch_start_id, null);
                $this->quadId += 1;
                $end_id = $this->quadId;
                array_push($cases_start, $start_id);
                $this->quads[$this->quadId] = new Q_IR($this->quadId, "Stmt_Branch_Finish", $start_id, $end_id, null);
            }

            if ($Whole_Branch_start_id != $this->quadId) {
                //整个Branch语句的结束
                $this->quadId += 1;
                $this->quads[$this->quadId] = new Q_IR($this->quadId, "Whole_Branch_Finish", null, null, null);
            } else {
                unset($this->quads[$Whole_Branch_start_id]);
                $this->quadId--;
            }

            //回填cases
            for ($i = 0; $i < count($cases_start); $i++) {
                $this->quads[$cases_start[$i]]->dest = $this->quadId;
            }
        }
    }

    //解析if语句 逻辑和上面类似 注意有else和没有else的情况 没有else的话还有一条不经过if的流，同样注意减枝
    //elseif和else if需要剪枝 暂时没想出来怎么处理 elseif只是if的一个小分支 而else if相当于新创建了一个全新的if语句
    //我是天才我去 解析的时候把所有else if正则匹配一下修改成elseif就行了 不用想咋减枝了
    public function IfParse($stmt)
    {
        $elseifs = $stmt->elseifs;
        $else = $stmt->else;
        $elseifs_count = count($elseifs);

        $this->quadId += 1;
        $Whole_Branch_start_id = $this->quadId;
        //{Whole_Branch_Start,null,null,null}
        $this->quads[$this->quadId] = new Q_IR($this->quadId, "Whole_Branch_Start", null, null, null);


        $this->quadId += 1;
        $start_id = $this->quadId;
        $if_start = $start_id; //if语句的开始，Stmt_Branch_Start的id，方便后面回填dest
        //{Stmt_Branch_Start,null,$Whole_Branch_start_id,$dest} Whole_Branch_start_id:Branch语句整体的开始 dest:Branch语句整体的结束
        //结束后回填dest
        //这是第一个if
        $this->quads[$this->quadId] = new Q_IR($this->quadId, "Stmt_Branch_Start", null, $Whole_Branch_start_id, null);
        $this->processStatements($stmt->stmts);
        if ($start_id != $this->quadId) {
            $this->quadId += 1;
            $end_id = $this->quadId;
            //start到end  标记本条分支的作用域
            $this->quads[$this->quadId] = new Q_IR($this->quadId, "Stmt_Branch_Finish", $start_id, $end_id, null);
        } else {
            unset($this->quads[$start_id]);
            $if_start = null;
            $this->quadId--;
        }

        //解析每条elseif
        $elseif_start = array();
        for ($i = 0; $i < $elseifs_count; $i++) {
            $this->quadId += 1;
            $start_id = $this->quadId;
            $this->quads[$this->quadId] = new Q_IR($this->quadId, "Stmt_Branch_Start", null, $Whole_Branch_start_id, null);
            $this->processStatements($elseifs[$i]->stmts);
            if ($start_id != $this->quadId) {
                $this->quadId += 1;
                $end_id = $this->quadId;
                array_push($elseif_start, $start_id);
                $this->quads[$this->quadId] = new Q_IR($this->quadId, "Stmt_Branch_Finish", $start_id, $end_id, null);
            } else {
                unset($this->quads[$start_id]);
                $this->quadId--;
            }
        }
        $else_start = null;
        // 解析else else不为空就解析 为空就不解析，这样相当于一条直接连通的分支 
        if (($else->stmts == null) && (count($elseif_start) != 0 || $if_start != null)) {
            $this->quadId += 1;
            $start_id = $this->quadId;
            $this->quads[$this->quadId] = new Q_IR($this->quadId, "Stmt_Branch_Start", null, $Whole_Branch_start_id, null);
            $this->quadId += 1;
            $end_id = $this->quadId;
            $else_start = $start_id;
            $this->quads[$this->quadId] = new Q_IR($this->quadId, "Stmt_Branch_Finish", $start_id, $end_id, null);
        } elseif ($else->stmts != null) {
            $this->quadId += 1;
            $start_id = $this->quadId;
            $this->quads[$this->quadId] = new Q_IR($this->quadId, "Stmt_Branch_Start", null, $Whole_Branch_start_id, null);
            $this->processStatements($else->stmts);
            if ($start_id != $this->quadId) {
                $this->quadId += 1;
                $end_id = $this->quadId;
                $else_start = $start_id;
                $this->quads[$this->quadId] = new Q_IR($this->quadId, "Stmt_Branch_Finish", $start_id, $end_id, null);
            } else {
                unset($this->quads[$start_id]);
                $this->quadId--;
            }
        }
        if ($Whole_Branch_start_id != $this->quadId) {
            //整个Branch语句的结束
            $this->quadId += 1;
            $this->quads[$this->quadId] = new Q_IR($this->quadId, "Whole_Branch_Finish", null, null, null);
        } else {
            unset($this->quads[$Whole_Branch_start_id]);
            $this->quadId--;
        }

        //回填每条Stmt_Branch_Start的dest
        //回填跳转位置
        if ($if_start != null) {
            $this->quads[$if_start]->dest = $this->quadId; //回填if
        }
        if ($else_start != null) {
            $this->quads[$else_start]->dest = $this->quadId; //回填else
        }
        //回填elseif
        for ($i = 0; $i < count($elseif_start); $i++) {
            $this->quads[$elseif_start[$i]]->dest = $this->quadId;
        }
    }

    //类似于$sql = "UPDATE sea_danmaku_list SET text='$text',color='$color' WHERE cid=$cid";的表达，字符串里加变量
    public function EncapsedParse($expr)
    {
        //{Whole_Encapsed_Start,null,null,null}
        $this->GenerateQuads($this->quadId, "Whole_Encapsed_Start", null, null, null);
        $Whole_Encapsed_start_id = $this->quadId;
        for ($i = 0; $i < count($expr->parts); $i++) {
            if ($expr->parts[$i] instanceof PhpParser\Node\Expr\Variable) {
                $this->GenerateQuads($this->quadId, "Encapsed_Param", null, null, $expr->parts[$i]);
            }
        }
        if ($Whole_Encapsed_start_id == $this->quadId) {
            unset($this->quads[$Whole_Encapsed_start_id]);
            $this->quadId--;
        } else {
            $this->GenerateQuads($this->quadId, "Whole_Encapsed_End", $Whole_Encapsed_start_id, $this->quadId, null);
            $Whole_Encapsed_end_id = $this->quadId;
        }
    }

    //表达式解析
    public function ExprParse($expr)
    {
        //a.=b这种形式assign+op
        if ($expr instanceof PhpParser\Node\Expr\AssignOp\Concat) {
            if (!($expr->var instanceof PhpParser\Node\Expr\Variable)) {
                return;
            }
            //$a.="1"  对污点没有影响，直接退出
            if ($expr->expr instanceof PhpParser\Node\Scalar\String_) {
                return;
            } else if ($expr->expr instanceof PhpParser\Node\Expr\Variable) {
                $this->GenerateQuads($this->quadId, "Expr_Assign", $expr->expr, $expr->var, $expr->var);
            } else if ($expr->expr instanceof PhpParser\Node\Scalar\Encapsed) {
                $before_id = $this->quadId;
                $this->EncapsedParse($expr->expr);
                $now_id =   $this->quadId;
                if ($before_id != $now_id) {
                    $this->GenerateQuads($this->quadId, "Expr_Assign", null, $now_id, $expr->var);
                }
            }
        }

        //检测赋值语句
        if ($expr instanceof PhpParser\Node\Expr\Assign) {
            //仅能处理左值是单变量的情况           
            if (!($expr->var instanceof PhpParser\Node\Expr\Variable)) {
                return;
            }
            if ($expr->expr instanceof PhpParser\Node\Scalar) {
                //$sql = "UPDATE sea_danmaku_report SET text='$text'"; php中这种常量中加变量的特殊表达
                if ($expr->expr instanceof PhpParser\Node\Scalar\Encapsed) {
                    $before_id = $this->quadId;
                    $this->EncapsedParse($expr->expr);
                    $now_id = $this->quadId;
                    if ($before_id != $now_id) {
                        $this->GenerateQuads($this->quadId, $expr->getType(), null, $now_id, $expr->var);
                    } else {
                        $this->GenerateQuads($this->quadId, $expr->getType(), null, null, $expr->var);
                    }
                } else {
                    //生成一个quad，对于expr是常量的情况，直接进行ASSIGN类型的quad的生成，不需要知道具体值
                    $this->GenerateQuads($this->quadId, $expr->getType(), null, null, $expr->var);
                }
                //若为变量
            } elseif ($expr->expr instanceof PhpParser\Node\Expr\Variable) {
                //若为变量，则右操作数为当前QuadId
                $this->GenerateQuads($this->quadId, $expr->getType(), null, $expr->expr, $expr->var);
            } //三元表达式，比如$cid = $_POST['cid'] ?: showmessage(-1, null);
            else if ($expr->expr instanceof PhpParser\Node\Expr\Ternary) {
                if ($expr->expr->cond instanceof PhpParser\Node\Expr\Variable) {
                    $this->GenerateQuads($this->quadId, $expr->getType(), null, $expr->expr->cond, $expr->var);
                } else if ($expr->expr->cond instanceof PhpParser\Node\Expr) {
                    $before_id = $this->quadId;
                    $this->ExprParse($expr->expr->cond); //若为表达式，对$expr->expr进行一次解析
                    $now_id = $this->quadId;
                    if ($before_id != $now_id) { //减枝
                        $this->GenerateQuads($this->quadId, $expr->getType(), null, $now_id, $expr->var);
                    } else {
                        $this->GenerateQuads($this->quadId, $expr->getType(), null, null, $expr->var);
                    }
                } else {
                    $this->GenerateQuads($this->quadId, $expr->getType(), null, null, $expr->var);
                }
                //上面解析了等式，下面需要解析两个分支
                //不想写了
            } //对于普通的表达式，赋值得到的是表达式解析结果的最后一条ir 
            elseif ($expr->expr instanceof PhpParser\Node\Expr) {
                //var_dump($expr->expr);
                $before_id = $this->quadId;
                $this->ExprParse($expr->expr); //若为表达式，对$expr->expr进行一次解析
                $now_id = $this->quadId;
                if ($before_id != $now_id) { //减枝
                    $this->GenerateQuads($this->quadId, $expr->getType(), null, $now_id, $expr->var);
                } else {
                    $this->GenerateQuads($this->quadId, $expr->getType(), null, null, $expr->var);
                }
            }
        }

        //$a . $b  $a是$expr->left，$b是$expr->right，再多了就不支持了, 应该也弄个作用域啥的
        if ($expr instanceof PhpParser\Node\Expr\BinaryOp\Concat) {
            // 检查左右操作数的类型，只要有一个是常量或变量
            if (
                ($expr->left instanceof PhpParser\Node\Expr\Variable || $expr->right instanceof PhpParser\Node\Expr\Variable)
            ) {
                // 生成一个新quad，左右操作数为$expr->left,$expr->right
                $this->GenerateQuads($this->quadId, $expr->getType(), $expr->left, $expr->right, null);
            }
        }

        //表示数组访问操作，如：$value = $array[$key];
        if ($expr instanceof PhpParser\Node\Expr\ArrayDimFetch) {
            //对于$value = $array[$key]; 则左操作数为$array(数组名)，右操作数为$key(索引)
            if (
                $expr->var instanceof PhpParser\Node\Expr\Variable
            ) {
                // $_GET、$_POST、$_REQUEST
                $type = $expr->var->name;
                $sources = new Sources();
                $input = $sources->GetUserInput();
                //将用户输入标记为污点
                if (in_array($type, $input)) {
                    $this->sourceFlag = true;
                }
            }
            $this->GenerateQuads($this->quadId, $expr->getType(), $expr->var, $expr->dim, null);
        }

        if ($expr instanceof PhpParser\Node\Expr\Exit_) {
            $this->GenerateQuads($this->quadId, $expr->getType(), null, null, null);
        }

        //类的函数调用  暂时按照内置函数的逻辑处理 类似于$conn->query($cid);
        if ($expr instanceof PhpParser\Node\Expr\MethodCall) {

            if ($expr->name instanceof PhpParser\Node\Identifier) {
                // global $SinkAll;
                // if (array_key_exists($expr->name->name, $SinkAll)) {
                //var_dump($expr->name->name);
                $param_count = count($expr->args);
                $start_id = $this->quadId + 1;
                for ($i = 0; $i < $param_count; $i++) {
                    //调用的时候传入的参数
                    $this->GenerateQuads($this->quadId, "UserFunc_Call_Param", null, null, $expr->args[$i]);
                }
                $this->GenerateQuads($this->quadId, "Expr_FuncCall", $expr->name, null, null);
                $id = $this->quadId;
                $this->quads[$id]->dest = $this->quadId + 1;
                $end_id = $this->quadId;
                $this->GenerateQuads($this->quadId, "Expr_FuncCall_Finish", $start_id, $end_id, null);
                // }
            }
        }

        //类似于sql::编辑_弹幕($cid);
        if ($expr instanceof PhpParser\Node\Expr\StaticCall) {
            if ($expr->name instanceof PhpParser\Node\Identifier) {
                // global $SinkAll;
                // if (array_key_exists($expr->name->name, $SinkAll)) {
                //var_dump($expr->name->name);
                $param_count = count($expr->args);
                $start_id = $this->quadId + 1;
                for ($i = 0; $i < $param_count; $i++) {
                    //调用的时候传入的参数
                    $this->GenerateQuads($this->quadId, "UserFunc_Call_Param", null, null, $expr->args[$i]);
                }
                $this->GenerateQuads($this->quadId, "Expr_FuncCall", $expr->name, null, null);
                $id = $this->quadId;
                $this->quads[$id]->dest = $this->quadId + 1;
                $end_id = $this->quadId;
                $this->GenerateQuads($this->quadId, "Expr_FuncCall_Finish", $start_id, $end_id, null);
            }
            // }
        }

        //若为函数调用
        if ($expr instanceof PhpParser\Node\Expr\FuncCall) {
            if ($expr->name instanceof PhpParser\Node\Name) {
                // var_dump($expr->name);
                //用户内置函数
                if (in_array($expr->name->parts[0], get_defined_functions()["internal"])) { {
                        global $SanitizerAll;
                        //var_dump($All_internal_func);
                        //不能是Sanitizer
                        if (!in_array($expr->name, $SanitizerAll)) {
                            $param_count = count($expr->args);
                            $start_id = $this->quadId + 1;
                            for ($i = 0; $i < $param_count; $i++) {
                                //调用的时候传入的参数
                                $this->GenerateQuads($this->quadId, "Internal_Call_Param", null, null, $expr->args[$i]);
                            }
                            $this->GenerateQuads($this->quadId, $expr->getType() . "_Internal", $expr->name, null, null);
                            $id = $this->quadId;
                            $this->quads[$id]->dest = $this->quadId + 1;
                            $end_id = $this->quadId;
                            $this->GenerateQuads($this->quadId, "Expr_FuncCall_Internal_Finish", $start_id, $end_id, null);
                        } else {
                            $this->GenerateQuads($this->quadId, 'Truncation', null, null, null);
                            $id = $this->quadId;
                        }
                    }
                } else {
                    //用户自定义函数
                    //获取自定义函数的参数总数
                    $param_count = count($expr->args);
                    //对自定义函数的每一个参数生成quad
                    $start_id = $this->quadId + 1;
                    for ($i = 0; $i < $param_count; $i++) {
                        //这里是函数调用时传入的参数 如$test($a) {echo $a;}  test($c);这里相当于这个$c
                        $this->GenerateQuads($this->quadId, "UserFunc_Call_Param", null, null, $expr->args[$i]);
                    }
                    //{"Expr_FuncCall",方法名,null，null}
                    $this->GenerateQuads($this->quadId, "Expr_FuncCall", $expr->name, null, null);
                    $id = $this->quadId;
                    $this->quads[$id]->dest = $this->quadId + 1;
                    $end_id = $this->quadId;
                    //arg表示从start_id到end_id的范围，即函数的开始到结束范围
                    $this->GenerateQuads($this->quadId, "Expr_FuncCall_Finish", $start_id, $end_id, null);
                    // }
                }
            }
        }
    }
}
