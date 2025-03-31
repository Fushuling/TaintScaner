<?php
require 'global.php';
ini_set('display_errors', 0); // 禁用错误输出
error_reporting(E_ERROR | E_PARSE); // 仅显示致命错误和解析错误

// 递归扫描目录，获取所有 PHP 文件路径
function scanDirectory($dir)
{
    $phpFiles = [];
    $frameworks = [];  // 用于存储框架信息
    try {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile() && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $phpFiles[] = $file->getRealPath();
            }
            // 检查是否有特定的框架文件
            if ($file->isFile()) {
                $filename = strtolower($file->getFilename());
                if (strpos($filename, 'psrserverrequestresolver.php') !== false && !in_array('Symfony', $frameworks)) {
                    $frameworks[] = 'Symfony';
                }
                if (strpos($filename, 'think') !== false && !in_array('ThinkPHP', $frameworks)) {
                    $frameworks[] = 'ThinkPHP';
                }
                // 检查Laravel框架，只添加一次
                if (strpos($filename, 'welcome.blade.php') !== false && !in_array('Laravel', $frameworks)) {
                    $frameworks[] = 'Laravel';
                }
                // 检查Yii框架，只添加一次
                if (strpos($filename, 'yii') !== false && !in_array('Yii', $frameworks)) {
                    $frameworks[] = 'Yii';
                }
                // 检查CakePHP框架，只添加一次
                if (strpos($filename, 'dev_error_stacktrace.php') !== false && !in_array('CakePHP', $frameworks)) {
                    $frameworks[] = 'CakePHP';
                }
            }
        }
    } catch (Exception $e) {
        error_log("目录扫描错误: " . $e->getMessage());
    }
    return ['phpFiles' => $phpFiles, 'frameworks' => $frameworks];
}

// 处理目录扫描
function handleDirectoryScan($dir)
{
    $scanResult = scanDirectory($dir);
    $phpFiles = $scanResult['phpFiles'];
    $frameworks = $scanResult['frameworks'];  // 获取框架信息
    $results = [];

    foreach ($phpFiles as $index => $file) {
        $result = loadFile($file, $index);

        // 仅在解析成功或发生错误时添加结果
        if ($result !== null) {
            $results[] = $result;
        }
    }

    // 获取symbol为Yes的函数列表
    $symbolFunctions = getSymbolFunctions($results);

    return [
        'results' => $results,
        'frameworks' => count($frameworks) > 0 ? "检测到框架： " . implode(', ', $frameworks) : "未检测到框架。",
        'symbolFunctions' => $symbolFunctions, // 返回symbol为Yes的函数
    ];
}

// 加载并解析单个文件
function loadFile($filePath, $fileIndex)
{
    // var_dump($filePath);
    try {
        $parser = new PhpParser\Parser\Php7(new PhpParser\Lexer\Emulative());

        $code = file_get_contents($filePath);
        $code = preg_replace('/\belse\s+if\b/', 'elseif', $code);
        $parseResult = $parser->parse($code); // AST

        $NodeInitVisitor = new NodeInitVisitor();
        $traverser = new PhpParser\NodeTraverser();
        $traverser->addVisitor($NodeInitVisitor);
        $traverser->traverse($parseResult);

        $nodes = $NodeInitVisitor->getNodes();
        $AstToIrConverter = new AstToIrConverter();
        $resultSet = $AstToIrConverter->DirtyFunc($nodes);

        // 如果没有发现漏洞，直接跳过
        if (empty($resultSet)) {
            return null; // 不返回任何信息
        }

        $htmlResult = parseResult($resultSet, $filePath, $fileIndex);

        return [
            'status' => 'success',
            'details' => $htmlResult['details'],
            'functions' => $htmlResult['functions'], // 返回函数信息
        ];
    } catch (Exception $e) {
        // 捕获异常并返回错误信息
        return [
            'status' => 'error',
            'type' => 'ParseError',
            'message' => '解析错误: ' . $e->getMessage(),
        ];
    }
}

function parseResult($resultSet, $filePath, $fileIndex)
{
    $count = [];
    $info = [];
    $num = 0;
    $functions = [];

    // 读取整个文件的代码
    $code = file($filePath, FILE_IGNORE_NEW_LINES);

    foreach ($resultSet as $result) {
        $count[$result->type] = ($count[$result->type] ?? 0) + 1;

        $lineContext = [];
        for ($i = 0; $i < count($code); $i++) {
            $tmp = $i + 1;
            $escapedCode = htmlspecialchars($code[$i], ENT_QUOTES, 'UTF-8');

            if ($tmp == $result->sink_num) {
                $lineContext[] = "<span style='color: red; font-weight: bold;'>" . $tmp . " " . $escapedCode . "</span>";
            } else if (in_array($tmp, $result->linenum)) {
                $lineContext[] = "<span style='color: orange; font-weight: bold;'>" . $tmp . " " . $escapedCode . "</span>";
            } else {
                $lineContext[] = $tmp . " " . $escapedCode;
            }
        }

        $info[] = [
            "type" => $result->type,
            "name" => $result->name,
            "funcName" => $result->funcName,
            "path" => $filePath,
            "code" => implode("\n", $lineContext),
            "linenum" => $result->sink_num,
            "symbol" => $result->symbol
        ];
        $num++;

        // 保存函数信息
        $functions[] = [
            'name' => $result->funcName,
            'type' => $result->type,
            'symbol' => $result->symbol
        ];
    }

    // 构建 HTML 表格
    $detailsHtml = '<table class="table"><thead><tr>
        <th>#</th><th>SinkFunc</th><th>Type</th><th>Function</th><th>Condition</th><th>Line</th><th>Path</th><th>Detail</th>
        </tr></thead><tbody>';
    foreach ($info as $index => $item) {
        $modalId = "modal-{$fileIndex}-{$index}";
        $detailsHtml .= "<tr>
            <td>" . ($index + 1) . "</td>
            <td>{$item['funcName']}</td>
            <td>{$item['type']}</td>
            <td>{$item['name']}</td>
            <td>{$item['symbol']}</td>
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

    return [
        'details' => $detailsHtml,
        'functions' => $functions, // 返回函数信息
    ];
}

// 获取symbol为Yes的函数列表
function getSymbolFunctions($results)
{
    $symbolFunctions = [];

    foreach ($results as $result) {
        if (isset($result['functions']) && is_array($result['functions'])) {
            foreach ($result['functions'] as $function) {
                if (isset($function['symbol']) && $function['symbol'] === 'Yes') {
                    // 通过函数名去重
                    $symbolFunctions[$function['name']] = "'" . $function['name'] . "'" . ' => ' . "'" . $function['type'] . "'" . ',';
                }
            }
        }
    }

    // 重新索引数组，确保结果是一个普通的数组
    return array_values($symbolFunctions);  // 去重后的结果
}

// 入口处理逻辑
$inputPath = $_POST['path'] ?? null;

if ($inputPath && is_dir($inputPath)) {
    $results = handleDirectoryScan($inputPath);

    echo json_encode([
        'status' => 'success',
        'frameworks' => $results['frameworks'], // 返回框架信息
        'results' => $results['results'],
        'symbolFunctions' => $results['symbolFunctions'], // 返回symbol为Yes的函数
    ]);
} elseif ($inputPath && is_file($inputPath)) {
    $result = loadFile($inputPath, 0);

    // 获取symbol为Yes的函数列表
    $symbolFunctions = getSymbolFunctions([$result]);

    echo json_encode([
        'status' => 'success',
        'frameworks' => '未检测到框架。', // 默认无框架
        'results' => [$result],
        'symbolFunctions' => $symbolFunctions, // 返回symbol为Yes的函数
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'type' => 'InvalidPath',
        'message' => '无效路径，请检查输入的文件路径。',
    ]);
}
