<?php
require 'global.php';
ini_set('display_errors', 0); // 禁用错误输出
error_reporting(E_ERROR | E_PARSE); // 仅显示致命错误和解析错误

function scanDirectory($dir)
{
    $phpFiles = [];
    $frameworks = [];
    try {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile() && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $phpFiles[] = $file->getRealPath();
            }
            $filename = strtolower($file->getFilename());
            if (strpos($filename, 'psrserverrequestresolver.php') !== false && !in_array('Symfony', $frameworks)) {
                $frameworks[] = 'Symfony';
            }
            if (strpos($filename, 'think') !== false && !in_array('ThinkPHP', $frameworks)) {
                $frameworks[] = 'ThinkPHP';
            }
            if (strpos($filename, 'welcome.blade.php') !== false && !in_array('Laravel', $frameworks)) {
                $frameworks[] = 'Laravel';
            }
            if (strpos($filename, 'yii') !== false && !in_array('Yii', $frameworks)) {
                $frameworks[] = 'Yii';
            }
            if (strpos($filename, 'dev_error_stacktrace.php') !== false && !in_array('CakePHP', $frameworks)) {
                $frameworks[] = 'CakePHP';
            }
        }
    } catch (Exception $e) {
        error_log("目录扫描错误: " . $e->getMessage());
    }
    return ['phpFiles' => $phpFiles, 'frameworks' => $frameworks];
}

function handleDirectoryScan($dir)
{
    $scanResult = scanDirectory($dir);
    $phpFiles = $scanResult['phpFiles'];
    $frameworks = $scanResult['frameworks'];

    $parser = new PhpParser\Parser\Php7(new PhpParser\Lexer\Emulative());
    $traverser = new PhpParser\NodeTraverser();
    $NodeInitVisitor = new NodeInitVisitor();
    $traverser->addVisitor($NodeInitVisitor);
    $AstToIrConverter = new AstToIrConverter();

    foreach ($phpFiles as $filePath) {
        try {
            $code = file_get_contents($filePath);
            $code = preg_replace('/\belse\s+if\b/', 'elseif', $code);
            $parseResult = $parser->parse($code);

            $NodeInitVisitor = new NodeInitVisitor();
            $traverser->removeVisitor($NodeInitVisitor);
            $traverser->addVisitor($NodeInitVisitor);
            $traverser->traverse($parseResult);

            $nodes = $NodeInitVisitor->getNodes();
            $AstToIrConverter->build_funcTable($nodes, $filePath);
        } catch (Throwable $e) {
            echo "[Error] Failed to parse file: $filePath\n";
            echo "Reason: " . $e->getMessage() . "\n\n";
        }
    }

    $func_table = $AstToIrConverter->funcs;

    foreach ($func_table as $key => $obj) {
        if ($obj->flag == 'null') {
            if ($func_table[md5($obj->func_name)]->func_stmt != null) {
                try {
                    $AstToIrConverter = new AstToIrConverter();
                    $NodeInitVisitor = new NodeInitVisitor();
                    $traverser = new PhpParser\NodeTraverser();
                    $traverser->addVisitor($NodeInitVisitor);
                    $traverser->traverse($func_table[md5($obj->func_name)]->func_stmt);
                    $nodes = $NodeInitVisitor->getNodes();
                    $quads = $AstToIrConverter->FuncParse($nodes, $func_table[md5($obj->func_name)]->func_param);
                    $flow_graph = new ControlFlowGraph();
                    $flow_graph->FuncProcess($quads, $func_table, $obj->func_name);
                } catch (Exception $e) {
                    error_log("DFS 错误: {$obj->func_name}: {$e->getMessage()}");
                }
            } else {
                $obj->flag == 'false;';
            }
        }
    }

    $results = [];
    $symbolFunctions = [];

    foreach ($func_table as $key => $obj) {
        if ($obj->flag == 'true') {
            foreach ($obj->sink as $sink) {
                $symbolFunctions[$obj->func_name] = "'" . $obj->func_name . "'" . ' => ' . "'" . $sink->type . "'" . ',';
                $results[] = [
                    'funcName' => $obj->func_name,
                    'type' => $sink->type,
                    'name' => $sink->name,
                    'linenum' => $sink->sink_num,
                    'path' => $obj->file_path,
                    'code' => getHighlightedCode($obj->file_path, $sink)
                ];
            }
        }
    }
    return [
        'results' => formatResults($results),
        'frameworks' => count($frameworks) > 0 ? "检测到框架： " . implode(', ', $frameworks) : "未检测到框架。",
        'symbolFunctions' => array_values($symbolFunctions)
    ];
}

function getHighlightedCode($path, $sink)
{
    $code = file($path, FILE_IGNORE_NEW_LINES);
    $lines = [];
    foreach ($code as $i => $line) {
        $linenum = $i + 1;
        $escaped = htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
        if ($linenum == $sink->sink_num) {
            $lines[] = "<span style='color: red; font-weight: bold;'>{$linenum} {$escaped}</span>";
        } elseif (in_array($linenum, $sink->linenum)) {
            $lines[] = "<span style='color: orange; font-weight: bold;'>{$linenum} {$escaped}</span>";
        } else {
            $lines[] = "{$linenum} {$escaped}";
        }
    }
    return implode("\n", $lines);
}

function formatResults($info)
{
    $detailsHtml = '<table class="table"><thead><tr>
        <th>#</th><th>SinkFunc</th><th>Type</th><th>Function</th><th>Line</th><th>Path</th><th>Detail</th>
        </tr></thead><tbody>';
    foreach ($info as $index => $item) {
        $modalId = "modal-{$index}";
        $detailsHtml .= "<tr>
            <td>" . ($index + 1) . "</td>
            <td>{$item['funcName']}</td>
            <td>{$item['type']}</td>
            <td>{$item['name']}</td>
            <td>{$item['linenum']}</td>
            <td>{$item['path']}</td>
            <td>
                <button class='btn btn-primary' data-toggle='modal' data-target='#{$modalId}'>Details</button>
                <div class='modal fade' id='{$modalId}' tabindex='-1' role='dialog'>
                    <div class='modal-dialog modal-lg' role='document'>
                        <div class='modal-content'>
                            <div class='modal-header'>
                                <h5 class='modal-title'>Code Details</h5>
                                <button type='button' class='close' data-dismiss='modal'>&times;</button>
                            </div>
                            <div class='modal-body'>
                                <pre><code>{$item['code']}</code></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </td>
        </tr>";
    }
    $detailsHtml .= '</tbody></table>';
    return $detailsHtml;
}

function handleSingleFile($filePath)
{
    $frameworks = [];
    $phpFiles = [$filePath];

    $parser = new PhpParser\Parser\Php7(new PhpParser\Lexer\Emulative());
    $traverser = new PhpParser\NodeTraverser();
    $NodeInitVisitor = new NodeInitVisitor();
    $traverser->addVisitor($NodeInitVisitor);
    $AstToIrConverter = new AstToIrConverter();

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

    foreach ($func_table as $key => $obj) {
        if ($obj->flag == 'null') {
            if ($func_table[md5($obj->func_name)]->func_stmt != null) {
                try {
                    $AstToIrConverter = new AstToIrConverter();
                    $NodeInitVisitor = new NodeInitVisitor();
                    $traverser = new PhpParser\NodeTraverser();
                    $traverser->addVisitor($NodeInitVisitor);
                    $traverser->traverse($func_table[md5($obj->func_name)]->func_stmt);
                    $nodes = $NodeInitVisitor->getNodes();
                    $quads = $AstToIrConverter->FuncParse($nodes, $func_table[md5($obj->func_name)]->func_param);
                    $flow_graph = new ControlFlowGraph();
                    $flow_graph->FuncProcess($quads, $func_table, $obj->func_name);
                } catch (Exception $e) {
                    error_log("DFS 错误: {$obj->func_name}: {$e->getMessage()}");
                }
            } else {
                $obj->flag == 'false;';
            }
        }
    }

    $results = [];
    $symbolFunctions = [];

    foreach ($func_table as $key => $obj) {
        if ($obj->flag == 'true') {
            foreach ($obj->sink as $sink) {
                $symbolFunctions[$obj->func_name] = "'" . $obj->func_name . "'" . ' => ' . "'" . $sink->type . "'" . ',';
                $results[] = [
                    'funcName' => $obj->func_name,
                    'type' => $sink->type,
                    'name' => $sink->name,
                    'linenum' => $sink->sink_num,
                    'path' => $obj->file_path,
                    'code' => getHighlightedCode($obj->file_path, $sink)
                ];
            }
        }
    }
    return [
        'results' => formatResults($results),
        'frameworks' => count($frameworks) > 0 ? "检测到框架： " . implode(', ', $frameworks) : "未检测到框架。",
        'symbolFunctions' => array_values($symbolFunctions)
    ];
}

$inputPath = $_POST['path'] ?? null;

if ($inputPath) {
    if (is_dir($inputPath)) {
        $results = handleDirectoryScan($inputPath);
        echo json_encode([
            'status' => 'success',
            'frameworks' => $results['frameworks'],
            'results' => [['status' => 'success', 'details' => $results['results']]],
            'symbolFunctions' => $results['symbolFunctions']
        ]);
    } elseif (is_file($inputPath) && pathinfo($inputPath, PATHINFO_EXTENSION) === 'php') {
        $results = handleSingleFile($inputPath);
        echo json_encode([
            'status' => 'success',
            'frameworks' => $results['frameworks'],
            'results' => [['status' => 'success', 'details' => $results['results']]],
            'symbolFunctions' => $results['symbolFunctions']
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'type' => 'InvalidPath',
            'message' => '无效路径，请输入有效的PHP文件或目录路径。',
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'type' => 'InvalidPath',
        'message' => '请输入文件或目录路径。',
    ]);
}
