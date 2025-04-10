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
    ////var_dump($frameworks);
    $results = [];

    foreach ($phpFiles as $index => $file) {
        $result = loadFile($file, $index);

        // 仅在解析成功或发生错误时添加结果
        if ($result !== null) {
            $results[] = $result;
        }
    }

    // 如果没有检测到框架，显示“未检测到框架”
    $frameworkDetails = count($frameworks) > 0 ? "检测到框架： " . implode(', ', $frameworks) : "未检测到框架。";

    return [
        'results' => $results,
        'frameworks' => $frameworkDetails,  // 返回框架信息
    ];
}

// 加载并解析单个文件
function loadFile($filePath, $fileIndex)
{
    try {
        // //var_dump($filePath);
        $parser = new PhpParser\Parser\Php7(new PhpParser\Lexer\Emulative());

        $code = file_get_contents($filePath);
        $parseResult = $parser->parse($code); //AST

        $NodeInitVisitor = new NodeInitVisitor();
        $traverser = new PhpParser\NodeTraverser();
        $traverser->addVisitor($NodeInitVisitor);
        $traverser->traverse($parseResult);

        $nodes = $NodeInitVisitor->getNodes();
        $AstToIrConverter = new AstToIrConverter();
        $quads = $AstToIrConverter->AstParse($nodes); //返回四元组
        $flow_graph = new ControlFlowGraph();
        $flow_graph->process($quads);
        $resultSet = $flow_graph->sinks;
        ////var_dump($resultSet);
        // 如果没有发现漏洞，直接跳过
        if (empty($resultSet)) {
            return null; // 不返回任何信息
        }

        $htmlResult = parseResult($resultSet, $filePath, $fileIndex);

        return [
            'status' => 'success',
            'details' => $htmlResult['details'],
        ];
    } catch (Exception $e) {
        // 捕获异常并返回错误信息
        ////var_dump($filePath);
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

    // 读取整个文件的代码
    $code = file($filePath, FILE_IGNORE_NEW_LINES);

    ////var_dump($resultSet);
    foreach ($resultSet as $result) {
        $count[$result->type] = ($count[$result->type] ?? 0) + 1;

        // 复制代码数组，用于标红处理
        $lineContext = [];
        // //var_dump($result->linenum);
        for ($i = 0; $i < count($code); $i++) {
            // 如果当前行号在 $result->linenum 数组中，就加上标红样式
            $tmp = $i + 1;
            // 使用 htmlspecialchars() 函数对代码进行转义
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
            "path" => $filePath,
            "code" => implode("\n", $lineContext),
            "linenum" => $result->sink_num,
        ];
        $num++;
    }

    // 构建 HTML 表格
    $detailsHtml = '<table class="table"><thead><tr>
        <th>#</th><th>Type</th><th>Function</th><th>Line</th><th>Path</th><th>Detail</th>
        </tr></thead><tbody>';
    foreach ($info as $index => $item) {
        // 使用文件索引和问题索引生成唯一 ID
        $modalId = "modal-{$fileIndex}-{$index}";
        $detailsHtml .= "<tr>
            <td>" . ($index + 1) . "</td>
            <td>{$item['type']}</td>
            <td>{$item['name']}</td>
            <td>{$item['linenum']}</td>
            <td>{$item['path']}</td>
            <td>
                <button class='btn btn-primary' data-toggle='modal' data-target='#{$modalId}'>Details</button>
                <div class='modal fade' id='{$modalId}' tabindex='-1' role='dialog'>
                    <div class='modal-dialog' role='document'>
                        <div class='modal-content'>
                            <div class='modal-header'>
                                <h5 class='modal-title'>Code Details</h5>
                                <button type='button' class='close' data-dismiss='modal'>&times;</button>
                            </div>
                            <div class='modal-body'><pre><code>{$item['code']}</code></pre></div>
                        </div>
                    </div>
                </div>
            </td>
        </tr>";
    }
    $detailsHtml .= '</tbody></table>';

    return [
        'details' => $detailsHtml,
    ];
}


// 入口处理逻辑
$inputPath = $_POST['path'] ?? null;

if ($inputPath && is_dir($inputPath)) {
    $results = handleDirectoryScan($inputPath);
    // //var_dump($results);
    echo json_encode([
        'status' => 'success',
        'frameworks' => $results['frameworks'], // 返回框架信息
        'results' => $results['results'],
    ]);
} elseif ($inputPath && is_file($inputPath)) {
    $result = loadFile($inputPath, 0);
    echo json_encode([
        'status' => 'success',
        'frameworks' => '未检测到框架。', // 默认无框架
        'results' => [$result],
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'type' => 'InvalidPath',
        'message' => '无效路径，请检查输入的文件路径。',
    ]);
}
