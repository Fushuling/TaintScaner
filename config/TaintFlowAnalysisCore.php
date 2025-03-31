<?php

class ControlFlowGraph
{
    public $BasicBlockHandler;
    public $CFGBuilder;
    public $TaintAnalyzer;
    public $quads;
    public $sinks;

    public function __construct()
    {
        $this->BasicBlockHandler = new BasicBlockHandler();
        $this->CFGBuilder = new CFGBuilder();
        $this->TaintAnalyzer = new TaintAnalyzer();
        $this->sinks = [];
    }

    public function process($quads)
    {
        $this->sinks = [];
        $this->quads = $quads;
        //var_dump($quads);

        // 划分基本块
        $this->BasicBlockHandler->BlockDivide($quads);
        //var_dump($this->BasicBlockHandler->BasicBlock);

        // 构建 CFG
        $this->CFGBuilder->CreateGraph($this->BasicBlockHandler->BasicBlock);

        for ($i = 0; $i < count($this->CFGBuilder->CFG->Graph_Set); $i++) {
            // 污点流传播
            $this->TaintAnalyzer->TaintSpread($this->CFGBuilder->CFG->Graph_Set[$i]);

            // 获取新的 sinks
            $newSinks = $this->TaintAnalyzer->sinks;

            // 使用数组来存储唯一的 sinks
            foreach ($newSinks as $newSink) {
                $isDuplicate = false;
                foreach ($this->sinks as $existingSink) {
                    // 比较 Sink 对象的关键属性是否相同
                    if (
                        $newSink->type === $existingSink->type &&
                        $newSink->name === $existingSink->name &&
                        $newSink->linenum === $existingSink->linenum &&
                        $newSink->sink_num === $existingSink->sink_num
                    ) {
                        $isDuplicate = true;
                        break;
                    }
                }

                // 如果没有找到重复的 Sink，就添加它
                if (!$isDuplicate) {
                    $this->sinks[] = $newSink;
                }
            }
        }
    }
}
