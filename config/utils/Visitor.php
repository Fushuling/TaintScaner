<?php
/*
NodeInitVisitor继承PhpParser\NodeVisitorAbstract，实现对AST节点遍历的自定义逻辑。
*/
class NodeInitVisitor extends PhpParser\NodeVisitorAbstract{
    private $nodes = array();
    //在遍历前将所有节点存储到 $nodes 数组中
    public function beforeTraverse(array $nodes){
        $this->nodes = $nodes ;
    }
    //提供获取这些节点的接口
    public function getNodes(){
        return $this->nodes ;
    }
}

