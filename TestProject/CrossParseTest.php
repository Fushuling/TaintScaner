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

function scanDirectory($dir)
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    return $files;
}

$startTime = microtime(true);

function dfs($name, &$func_table)
{
    $AstToIrConverter = new AstToIrConverter();
    $NodeInitVisitor = new NodeInitVisitor();
    $traverser = new PhpParser\NodeTraverser();
    $traverser->addVisitor($NodeInitVisitor);
    $traverser->traverse($func_table[md5($name)]->func_stmt);
    $nodes = $NodeInitVisitor->getNodes();
    $AstToIrConverter = new AstToIrConverter();
    $quads = $AstToIrConverter->FuncParse($nodes, $func_table[md5($name)]->func_param);
    $flow_graph = new ControlFlowGraph();
    $flow_graph->FuncProcess($quads, $func_table, $name);
}

try {
    $parser = new PhpParser\Parser\Php7(new PhpParser\Lexer\Emulative());
    $traverser = new PhpParser\NodeTraverser();
    $NodeInitVisitor = new NodeInitVisitor();
    $traverser->addVisitor($NodeInitVisitor);
    $AstToIrConverter = new AstToIrConverter();

    // $directoryPath = 'D:/BaiduNetdiskDownload/SeaCMS_12.9_海洋CMS安装包/SeaCMS_12.9/Upload/';
    $directoryPath = './test.php';
    if (is_dir($directoryPath)) {
        $phpFiles = scanDirectory($directoryPath);
    } elseif (is_file($directoryPath) && pathinfo($directoryPath, PATHINFO_EXTENSION) === 'php') {
        $phpFiles = [$directoryPath];
    } else {
        die("Invalid path: not a PHP file or directory.\n");
    }

    foreach ($phpFiles as $filePath) {
        $code = file_get_contents($filePath);
        $code = preg_replace('/\belse\s+if\b/', 'elseif', $code);
        $parseResult = $parser->parse($code);

        $NodeInitVisitor = new NodeInitVisitor();
        $traverser->removeVisitor($NodeInitVisitor);
        $traverser->addVisitor($NodeInitVisitor);
        $traverser->traverse($parseResult);

        $nodes = $NodeInitVisitor->getNodes();
        $AstToIrConverter->build_funcTable($nodes, $filePath);
    }

    $func_table = $AstToIrConverter->funcs;
    // var_dump($func_table);
    foreach ($func_table as $key => $obj) {
        if ($obj->flag == 'null') {
            try {
                dfs($obj->func_name, $func_table);
            } catch (Exception $e) {
                echo 'Error during DFS for function ', $obj->func_name, ': ', $e->getMessage(), "\n";
            }
        }
    }

    foreach ($func_table as $key => $obj) {
        if ($obj->flag == 'true') {
            echo $obj->func_name, "\n";
            var_dump($obj->sink);
        }
    }
    // foreach ($func_table as $key => $obj) {
    //     echo $obj->func_name, $obj->func_param, $obj->file_path, $obj->flag, "\n";
    // }
} catch (Error $e) {
    echo 'Parse Error: ', $e->getMessage(), "\n";
}

$endTime = microtime(true);
$executionTime = $endTime - $startTime;
echo "Execution Time: " . round($executionTime, 4) . " seconds\n";
