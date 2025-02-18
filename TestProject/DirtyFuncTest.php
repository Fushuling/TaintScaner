<?php
ini_set('display_errors', 0); // 禁用错误输出
error_reporting(E_ERROR | E_PARSE); // 仅显示致命错误和解析错误

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
    $result = $AstToIrConverter->DirtyFunc($nodes); //返回四元组

    // var_dump($result);
} catch (Error $e) {
    echo 'Parse Error: ', $e->getMessage(), "\n";
}
