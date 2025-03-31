<?php
class BasicBlock
{
    //出边与入边
    public $id;
    public $InEdge;
    public $Content = []; //基本块的内容  构建CFG的时候去一下重
    public $OutEdge;
}

class BasicBlockHandler
{
    public $BasicBlock;  // 基本块集合
    public $BasicBlock_id = 0;  // 基本块id
    public $quads;

    public function __construct()
    {
        $this->BasicBlock = [];
    }
    //划分基本块的逻辑
    public function BlockDivide($quads)
    {
        /*
        InEdge设为$quads[1](quads[0]没有东西)
         */
        $this->quads = $quads;
        $this->BasicBlock[$this->BasicBlock_id] = new BasicBlock();
        $this->BasicBlock[$this->BasicBlock_id]->InEdge = $quads[1];
        // $this->BasicBlock[$this->BasicBlock_id]->InEdge = null; //起始基本块入边为空
        $this->BasicBlock[$this->BasicBlock_id]->id = $this->BasicBlock_id;

        $quad_id = count($quads); //quad总数

        $max_branch = 100; //如果控制流分支数量超过100会跑崩 因此现在限制最多为100 超过100返回空
        $now_branch = 0;

        //遍历每一个quad
        for ($i = 1; $i < $quad_id; $i++) {

            //分支语句整体的开始
            if ($quads[$i]->opcode == "Expr_Exit") {
                $this->BasicBlock[$this->BasicBlock_id]->OutEdge = $quads[$i];
            } elseif ($quads[$i]->opcode == "Whole_Branch_Start") {

                $this->BasicBlock[$this->BasicBlock_id]->OutEdge = $quads[$i];
                //分支语句的每一个分支的开始
            } elseif ($quads[$i]->opcode == "Stmt_Branch_Start") {
                $now_branch++;
                // echo $quads[$i]->opcode;
                //切换到下一个BasicBlock
                $this->BasicBlock_id += 1;
                $this->BasicBlock[$this->BasicBlock_id] = new BasicBlock();
                $this->BasicBlock[$this->BasicBlock_id]->id = $this->BasicBlock_id;
                //入边指向提前保存好的Whole_Try_Start
                array_push($this->BasicBlock[$this->BasicBlock_id]->Content, $quads[$i]);
                $this->BasicBlock[$this->BasicBlock_id]->InEdge = $quads[$quads[$i]->operand2];
                $this->BasicBlock[$this->BasicBlock_id]->OutEdge = $quads[$quads[$i]->dest];
                //分支语句的每一个分支的结束
            } elseif ($quads[$i]->opcode == "Stmt_Branch_Finish") {
                array_push($this->BasicBlock[$this->BasicBlock_id]->Content, $quads[$i]);
                //分支语句整体的结束
            } elseif ($quads[$i]->opcode == "Whole_Branch_Finish") {
                $this->BasicBlock_id += 1;
                $this->BasicBlock[$this->BasicBlock_id] = new BasicBlock();
                $this->BasicBlock[$this->BasicBlock_id]->id = $this->BasicBlock_id;
                $this->BasicBlock[$this->BasicBlock_id]->InEdge = $quads[$i];
                if ($i == $quad_id - 1) {
                    // $this->BasicBlock[$this->BasicBlock_id]->OutEdge = $quads[$i];  //结束块出边为null
                    $this->BasicBlock[$this->BasicBlock_id]->OutEdge = null;
                }
            } elseif ($i == $quad_id - 1) {

                $this->BasicBlock[$this->BasicBlock_id]->OutEdge = $quads[$i];  //结束块
                // $this->BasicBlock[$this->BasicBlock_id]->OutEdge = null;
                //对于一般情况的处理，入边设为上一个BasicBlock的出边，当前Basic的出边未知，默认设为当前边
            } else {
                $last_graph_OutEdge_id = $this->BasicBlock[$this->BasicBlock_id - 1]->OutEdge->id;
                if ($this->BasicBlock[$this->BasicBlock_id]->InEdge == null) {
                    $this->BasicBlock[$this->BasicBlock_id]->InEdge = $quads[$last_graph_OutEdge_id];
                }
                if ($this->BasicBlock[$this->BasicBlock_id]->OutEdge == null) {
                    $this->BasicBlock[$this->BasicBlock_id]->OutEdge = $quads[$i];
                }
                array_push($this->BasicBlock[$this->BasicBlock_id]->Content, $quads[$i]);
            }
        }
        if ($now_branch >= $max_branch) {
            $this->BasicBlock = [];
        }
    }
}
