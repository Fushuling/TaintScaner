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

// Start time tracking
$startTime = microtime(true);

try {
    $parser = new PhpParser\Parser\Php7(new PhpParser\Lexer\Emulative());
    $filePath = './test.php';
    $code = file_get_contents($filePath);

    // 自动将所有的 'else if' 转换成 'elseif'
    $code = preg_replace('/\belse\s+if\b/', 'elseif', $code);

    $parseResult = $parser->parse($code);
    $NodeInitVisitor = new NodeInitVisitor();
    $traverser = new PhpParser\NodeTraverser();
    $traverser->addVisitor($NodeInitVisitor);
    $traverser->traverse($parseResult);

    $nodes = $NodeInitVisitor->getNodes();
    $AstToIrConverter = new AstToIrConverter();
    $quads = $AstToIrConverter->AstParse($nodes); //返回四元组
    $quad_id = count($quads); //quad总数
    $a = 0;
    //遍历每一个quad
    for ($i = 1; $i < $quad_id; $i++) {

        //分支语句整体的开始
        if ($quads[$i]->opcode == "Stmt_Branch_Start") {
            $a++;
        }
    }
    var_dump($a);
    $flow_graph = new ControlFlowGraph();
    $flow_graph->process($quads);

    // var_dump($parseResult); //AST
    // var_dump($AstToIrConverter->funcs); //方法表
    // var_dump($quads); //Q_IR
    // //var_dump($flow_graph->BasicBlockHandler->BasicBlock); //基本块
    // var_dump($flow_graph->CFGBuilder->CFG); //CFG
    // var_dump($flow_graph->TaintAnalyzer->varMap); //变量
    var_dump($flow_graph->sinks); //污点
} catch (Error $e) {
    echo 'Parse Error: ', $e->getMessage(), "\n";
}

$endTime = microtime(true);

//计算耗时
$executionTime = $endTime - $startTime;
echo "Execution Time: " . round($executionTime, 4) . " seconds\n";
