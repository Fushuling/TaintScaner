<?php

class Graph
{
    public $BasicBlock_Set = [];
    public $Q_IR_Set = [];
}

class CFG
{
    public $Graph_Set = []; // 所有的控制流的集合，保存了一个图的控制流
}

class CFGBuilder
{

    public $Graph; // 控制流集合
    public $Graph_id = 0;
    public $CFG;

    public function __construct()
    {
        $this->Graph = [];
        $this->CFG = new CFG();
    }

    // 创建控制流图
    public function CreateGraph($BasicBlock)
    {
        $this->Graph = [];
        $this->Graph[$this->Graph_id] = new Graph();
        array_push($this->Graph[$this->Graph_id]->BasicBlock_Set, $BasicBlock[0]);
        $this->FindNext($BasicBlock, 0);

        //var_dump($this->Graph);
        //生成$Q_IR_Set 顺便去重
        for ($i = 0; $i < count($this->Graph); $i++) {
            $last_id = null;
            for ($j = 0; $j < count($this->Graph[$i]->BasicBlock_Set); $j++) {
                //入边
                if (
                    $this->Graph[$i]->BasicBlock_Set[$j]->InEdge != null &&
                    $this->Graph[$i]->BasicBlock_Set[$j]->InEdge->id != $last_id
                ) {
                    array_push($this->Graph[$i]->Q_IR_Set, clone $this->Graph[$i]->BasicBlock_Set[$j]->InEdge);
                    $last_id = $this->Graph[$i]->BasicBlock_Set[$j]->InEdge->id;
                }
                //内容
                for ($k = 0; $k < count($this->Graph[$i]->BasicBlock_Set[$j]->Content); $k++) {
                    if ($last_id != $this->Graph[$i]->BasicBlock_Set[$j]->Content[$k]->id) {
                        //var_dump($this->Graph[$i]->BasicBlock_Set[$j]->Content[$k]);
                        array_push($this->Graph[$i]->Q_IR_Set, clone $this->Graph[$i]->BasicBlock_Set[$j]->Content[$k]);
                        $last_id = $this->Graph[$i]->BasicBlock_Set[$j]->Content[$k]->id;
                    }
                }
                //出边
                if (
                    $this->Graph[$i]->BasicBlock_Set[$j]->OutEdge != null &&
                    $this->Graph[$i]->BasicBlock_Set[$j]->OutEdge->id != $last_id
                ) {
                    array_push($this->Graph[$i]->Q_IR_Set, clone $this->Graph[$i]->BasicBlock_Set[$j]->OutEdge);
                    //var_dump($this->Graph[$i]->BasicBlock_Set[$j]->OutEdge);
                    $last_id = $this->Graph[$i]->BasicBlock_Set[$j]->OutEdge->id;
                }
            }

            for ($index = 0; $index < count($this->Graph[$i]->Q_IR_Set); $index++) {
                // $this->Graph[$i]->Q_IR_Set[$index]->id = $index;

                if (is_numeric($this->Graph[$i]->Q_IR_Set[$index]->operand1)) {
                    $this->Graph[$i]->Q_IR_Set[$index]->operand1 = $this->idConvert($this->Graph[$i]->Q_IR_Set[$index]->operand1, $this->Graph[$i]->Q_IR_Set);
                }
                if (is_numeric($this->Graph[$i]->Q_IR_Set[$index]->operand2)) {
                    $this->Graph[$i]->Q_IR_Set[$index]->operand2 = $this->idConvert($this->Graph[$i]->Q_IR_Set[$index]->operand2, $this->Graph[$i]->Q_IR_Set);
                }
                if (is_numeric($this->Graph[$i]->Q_IR_Set[$index]->dest)) {
                    $this->Graph[$i]->Q_IR_Set[$index]->dest = $this->idConvert($this->Graph[$i]->Q_IR_Set[$index]->dest, $this->Graph[$i]->Q_IR_Set);
                }
            }

            for ($index = 0; $index < count($this->Graph[$i]->Q_IR_Set); $index++) {
                $this->Graph[$i]->Q_IR_Set[$index]->id = $index;
            }



            array_push($this->CFG->Graph_Set, $this->Graph[$i]->Q_IR_Set);
        }

        //var_dump($this->CFG);
    }

    //对于单控制流的情况，IR里的id就是他们在set里对应的id,但分流之后就不是一个东西了，因为会少一部分,$real_id是set里的id，IR_id是IR里的
    public function idConvert($IR_id, $quads_set)
    {
        for ($i = 0; $i < count($quads_set); $i++) {
            if ($IR_id == $quads_set[$i]->id) {
                return $i;
            }
        }
    }

    /*发现下一个基本块并加入图。注意，如果识别多个入边，需要创建新的流 比如图1的出边是b 图2的入边是b 
    图3的入边也是b 应该建立多个控制流 即1->2和1->3
    */
    public function FindNext($BasicBlock, $basic_start)
    {
        $flag = 0;
        $tmp = [];
        if ($BasicBlock[$basic_start]->OutEdge == null || $BasicBlock[$basic_start]->OutEdge == "Expr_Exit") {
            return;
        }
        for ($i = $basic_start + 1; $i < count($BasicBlock); $i++) {
            if ($BasicBlock[$i]->InEdge->id == $BasicBlock[$basic_start]->OutEdge->id) {
                if ($flag == 0) {
                    // 第一次匹配，直接加入BasicBlock_Set
                    $tmp = $this->Graph[$this->Graph_id]->BasicBlock_Set;
                    array_push($this->Graph[$this->Graph_id]->BasicBlock_Set, $BasicBlock[$i]);
                    $flag = 1;
                    $this->FindNext($BasicBlock, $i);
                } else {
                    // 创建一个新的Graph并复制旧的图
                    $this->Graph_id++;
                    $this->Graph[$this->Graph_id] = new Graph();
                    $this->Graph[$this->Graph_id]->BasicBlock_Set = $tmp; // 复制图对象
                    array_push($this->Graph[$this->Graph_id]->BasicBlock_Set, $BasicBlock[$i]);
                    $this->FindNext($BasicBlock, $i);
                }
            }
        }
    }
}
