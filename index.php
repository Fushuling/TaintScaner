<?php
require 'global.php';

// 定义动态变量
$title = 'TaintScaner';
$description = 'TaintScaner-PHP分析页面';
?>
<!DOCTYPE html>
<html lang="zh-cn">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- 使用本地Bootstrap CSS -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">

    <!-- 使用本地jQuery和Bootstrap JS -->
    <script src="assets/js/jquery-3.4.1.min.js"></script>
    <script src="assets/js/bootstrap.min.js"></script>

    <title><?php echo $title; ?></title>
    <style>
        .modal-body {
            max-height: 800px;
            /* 设置最大高度 */
            overflow-y: auto;
            /* 启用垂直滚动条 */
        }
    </style>
</head>

<body style="background-color: #f8f9fa;">

    <div class="container mt-4">
        <div class="jumbotron text-center">
            <h1 class="display-5"><?php echo $title; ?></h1>
            <p class="lead"><?php echo $description; ?></p>
        </div>

        <!-- 输入部分 -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text">Path</span>
                    </div>
                    <input id="pathData" type="text" class="form-control" placeholder="输入文件或目录路径">
                    <div class="input-group-append">
                        <button id="pathSubmit1" class="btn btn-primary" onclick="submit_1()">文件分析</button>
                    </div>
                    <div class="input-group-append" style="margin-left: 10px;">
                        <button id="pathSubmit2" class="btn btn-primary" onclick="submit_2()">函数扫描</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 结果部分 -->
        <div class="card mb-3 d-none" id="resultContainer">
            <div class="card-header">
                <h4>分析结果</h4>
            </div>
            <div class="card-body" id="result"></div>
        </div>

        <!-- 错误部分 -->
        <div id="errorContainer" class="d-none">
            <div class="alert alert-danger" role="alert" id="errorMessage"></div>
        </div>
    </div>

    <script>
        //扫文件或者目录
        function submit_1() {
            // 清空之前的内容
            $("#resultContainer").addClass("d-none");
            $("#errorContainer").addClass("d-none");
            $("#result").html("");
            $("#errorMessage").html("");

            const formData = document.getElementById("pathData").value;

            // 记录请求开始时间
            const startTime = new Date().getTime();

            $.ajax({
                type: 'POST',
                url: '/analyze.php',
                data: {
                    path: formData
                },
                success: function(response) {
                    // 记录请求结束时间并计算耗时
                    const endTime = new Date().getTime();
                    const elapsedTime = (endTime - startTime) / 1000; // 转换为秒

                    const result = JSON.parse(response);

                    if (result.status === 'success') {
                        $("#resultContainer").removeClass("d-none");
                        let resultHtml = '';

                        // 显示框架信息（如有）
                        if (result.frameworks) {
                            resultHtml += `<div class="alert alert-info">${result.frameworks}</div>`;
                        }

                        // 显示分析耗时
                        resultHtml += `<div class="alert alert-success">分析耗时：${elapsedTime} 秒</div>`;

                        // 构建分析结果
                        if (Array.isArray(result.results)) {
                            result.results.forEach((res, index) => {
                                if (res.status === 'success') {
                                    resultHtml += `<h5 class="mt-4">文件 ${index + 1}:</h5>`;
                                    resultHtml += res.details;
                                } else if (res.status === 'error') {
                                    resultHtml += `<h5 class="mt-4">文件 ${index + 1}:</h5>`;
                                    resultHtml += `<div class="alert alert-warning">解析错误：${res.message}</div>`;
                                }
                            });
                        } else {
                            resultHtml += `<div class="alert alert-warning">未检测到任何结果。</div>`;
                        }

                        // 渲染到页面
                        $("#result").html(resultHtml);

                    } else if (result.status === 'error') {
                        $("#errorContainer").removeClass("d-none");
                        $("#errorMessage").html(
                            `<strong>错误类型:</strong> ${result.type}<br><strong>消息:</strong> ${result.message}`
                        );
                    }
                },
                error: function() {
                    $("#errorContainer").removeClass("d-none");
                    $("#errorMessage").html("请求失败，无法连接服务器。");
                }
            });
        }

        function submit_2() {
            // 清空之前的内容
            $("#resultContainer").addClass("d-none");
            $("#errorContainer").addClass("d-none");
            $("#result").html("");
            $("#errorMessage").html("");

            const formData = document.getElementById("pathData").value;

            // 记录请求开始时间
            const startTime = new Date().getTime();

            $.ajax({
                type: 'POST',
                url: '/analyze_func.php',
                data: {
                    path: formData
                },
                success: function(response) {
                    // 记录请求结束时间并计算耗时
                    const endTime = new Date().getTime();
                    const elapsedTime = (endTime - startTime) / 1000; // 转换为秒

                    const result = JSON.parse(response);

                    if (result.status === 'success') {
                        $("#resultContainer").removeClass("d-none");
                        let resultHtml = '';

                        // 显示框架信息（如有）
                        if (result.frameworks) {
                            resultHtml += `<div class="alert alert-info">${result.frameworks}</div>`;
                        }

                        // 显示分析耗时
                        resultHtml += `<div class="alert alert-success">分析耗时：${elapsedTime} 秒</div>`;

                        // 构建分析结果
                        if (Array.isArray(result.results)) {
                            result.results.forEach((res, index) => {
                                if (res.status === 'success') {
                                    resultHtml += `<h5 class="mt-4">文件 ${index + 1}:</h5>`;
                                    resultHtml += res.details;
                                } else if (res.status === 'error') {
                                    resultHtml += `<h5 class="mt-4">文件 ${index + 1}:</h5>`;
                                    resultHtml += `<div class="alert alert-warning">解析错误：${res.message}</div>`;
                                }
                            });
                        } else {
                            resultHtml += `<div class="alert alert-warning">未检测到任何结果。</div>`;
                        }

                        // 显示symbol为yes的函数集合
                        if (result.symbolFunctions) {
                            resultHtml += `<h4>新增Sink:</h4>`;
                            resultHtml += `<ul>`;
                            result.symbolFunctions.forEach(func => {
                                resultHtml += `<li>${func}</li>`;
                            });
                            resultHtml += `</ul>`;
                        }

                        // 渲染到页面
                        $("#result").html(resultHtml);

                    } else if (result.status === 'error') {
                        $("#errorContainer").removeClass("d-none");
                        $("#errorMessage").html(
                            `<strong>错误类型:</strong> ${result.type}<br><strong>消息:</strong> ${result.message}`
                        );
                    }
                },
                error: function() {
                    $("#errorContainer").removeClass("d-none");
                    $("#errorMessage").html("请求失败，无法连接服务器。");
                }
            });
        }
    </script>



</body>

</html>