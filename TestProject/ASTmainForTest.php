<?php

use PhpParser\Error;
use PhpParser\ParserFactory;
use PhpParser\NodeDumper;
use PhpParser\Node;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\PrettyPrinter;

require '../global.php';

try {
    $parser = new PhpParser\Parser\Php7(new PhpParser\Lexer\Emulative());
    $filePath = './test.php';
    $code = file_get_contents($filePath);
    $parseResult = $parser->parse($code);
    $NodeInitVisitor = new NodeInitVisitor();
    $traverser = new PhpParser\NodeTraverser();
    $traverser->addVisitor($NodeInitVisitor);
    $traverser->traverse($parseResult);

    $nodes = $NodeInitVisitor->getNodes();
    $AstToIrConverter = new AstToIrConverter();
    $quads = $AstToIrConverter->AstParse($nodes); //返回四元组
    $flow_graph = new ControlFlowGraph();
    $flow_graph->process($quads);
    var_dump($parseResult); //AST
    // var_dump($AstToIrConverter->funcs); //方法表
    // var_dump($quads); //Q_IR
    // //var_dump($flow_graph->BasicBlockHandler->BasicBlock); //基本块
    // var_dump($flow_graph->CFGBuilder->CFG); //CFG
    // var_dump($flow_graph->TaintAnalyzer->varMap); //变量
    // var_dump($flow_graph->sinks); //污点
} catch (Error $e) {
    echo 'Parse Error: ', $e->getMessage(), "\n";
}
