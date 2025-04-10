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

        /* 添加调用链样式 */
        .call-chain {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
            margin-top: 5px;
        }

        .call-chain span {
            padding: 5px 10px;
            background-color: #fff;
            border-radius: 3px;
            margin: 2px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .call-chain .text-danger {
            color: #dc3545;
            font-weight: bold;
        }

        .call-chain i {
            color: #6c757d;
        }

        /* 污点传播路径样式 */
        .taint-path {
            margin: 20px 0;
            padding: 15px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .taint-path h5 {
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .taint-node {
            padding: 12px;
            margin: 8px 0;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .taint-node:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .node-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .node-name {
            font-weight: bold;
            font-size: 1.1em;
        }

        .node-location {
            font-size: 0.8em;
            color: #666;
            background: rgba(0, 0, 0, 0.05);
            padding: 2px 6px;
            border-radius: 3px;
        }

        .node-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 8px;
            font-size: 0.9em;
        }

        .node-type {
            color: #666;
        }

        .node-next {
            color: #2196f3;
            font-weight: bold;
        }

        .taint-source {
            background-color: #e3f2fd;
            border-left: 4px solid #2196f3;
        }

        .taint-propagation {
            background-color: #fff3e0;
            border-left: 4px solid #ff9800;
        }

        .taint-sink {
            background-color: #ffebee;
            border-left: 4px solid #f44336;
        }

        .taint-userfunc {
            background-color: #e8f5e9;
            border-left: 4px solid #4caf50;
        }

        .taint-path-details {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
            display: none;
        }

        .taint-path-details pre {
            margin: 0;
            padding: 10px;
            background: #fff;
            border-radius: 4px;
            white-space: pre-wrap;
            word-wrap: break-word;
            font-family: monospace;
            font-size: 0.9em;
        }

        .taint-path-arrow {
            margin: 0 10px;
            color: #666;
            font-size: 1.2em;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* 添加模态框样式 */
        .code-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .code-modal-content {
            position: relative;
            background-color: #fff;
            margin: 5% auto;
            padding: 20px;
            width: 80%;
            max-width: 800px;
            border-radius: 5px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .code-modal-close {
            position: absolute;
            right: 10px;
            top: 10px;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }

        .code-modal-close:hover {
            color: #333;
        }

        .code-modal-body {
            max-height: 70vh;
            overflow-y: auto;
            margin-top: 20px;
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

        <!-- 添加代码详情模态框 -->
        <div id="codeModal" class="code-modal">
            <div class="code-modal-content">
                <span class="code-modal-close">&times;</span>
                <h4>代码详情</h4>
                <div class="code-modal-body" id="codeModalBody"></div>
            </div>
        </div>
    </div>

    <script>
        // 构建函数调用链
        function buildCallChain(results) {
            const callChains = {};

            // 解析HTML表格中的函数调用关系
            const parser = new DOMParser();
            const doc = parser.parseFromString(results.results[0].details, 'text/html');
            const rows = doc.querySelectorAll('tbody tr');

            // 首先收集所有函数调用关系
            const functionCalls = [];
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length >= 4) {
                    const sinkFunc = cells[1].textContent.trim();
                    const type = cells[2].textContent.trim();
                    const calledFunc = cells[3].textContent.trim();
                    const line = cells[4].textContent.trim();
                    const path = cells[5].textContent.trim();

                    functionCalls.push({
                        sinkFunc,
                        type,
                        calledFunc,
                        line,
                        path
                    });
                }
            });

            // 构建调用链
            functionCalls.forEach(call => {
                // 为每个函数构建其调用链
                const buildChain = (funcName, visited = new Set()) => {
                    if (visited.has(funcName)) return null; // 防止循环调用
                    visited.add(funcName);

                    const funcInfo = functionCalls.find(f => f.sinkFunc === funcName);
                    if (!funcInfo) return null;

                    const chain = [{
                        functionName: funcName,
                        type: funcInfo.type,
                        line: funcInfo.line,
                        path: funcInfo.path
                    }];

                    // 如果调用了其他函数，继续构建链
                    if (funcInfo.calledFunc) {
                        const subChain = buildChain(funcInfo.calledFunc, visited);
                        if (subChain) {
                            chain.push(...subChain);
                        }
                    }

                    return chain;
                };

                // 为当前函数构建调用链
                const chain = buildChain(call.sinkFunc);
                if (chain) {
                    callChains[call.sinkFunc] = {
                        chain: chain,
                        sink: chain[chain.length - 1]
                    };
                }
            });

            return callChains;
        }

        // 显示代码详情模态框
        function showCodeDetails(funcName, code) {
            const modal = document.getElementById('codeModal');
            const modalBody = document.getElementById('codeModalBody');
            const closeButton = modal.querySelector('.code-modal-close');

            modalBody.innerHTML = code;
            modal.style.display = 'block';

            // 添加关闭按钮点击事件
            closeButton.onclick = function() {
                closeCodeModal();
            };
        }

        // 关闭代码详情模态框
        function closeCodeModal() {
            const modal = document.getElementById('codeModal');
            modal.style.display = 'none';
        }

        // 点击模态框外部关闭
        window.onclick = function(event) {
            const modal = document.getElementById('codeModal');
            if (event.target === modal) {
                closeCodeModal();
            }
        }

        // 修改triggerDetails函数
        function triggerDetails(funcName) {
            // 找到对应的代码详情
            const buttons = document.querySelectorAll('button[data-toggle="modal"]');
            buttons.forEach(button => {
                const row = button.closest('tr');
                if (row) {
                    const sinkFunc = row.querySelector('td:nth-child(2)').textContent.trim();
                    if (sinkFunc === funcName) {
                        // 获取对应的modal
                        const modalId = button.getAttribute('data-target');
                        const modal = document.querySelector(modalId);
                        if (modal) {
                            // 获取代码详情
                            const codeDetails = modal.querySelector('.modal-body').innerHTML;
                            showCodeDetails(funcName, codeDetails);
                            return;
                        }
                    }
                }
            });
        }

        // 修改generateCallChainHTML函数，添加id属性
        function generateCallChainHTML(chain, ownerFunc) {
            if (!chain || !chain.chain || chain.chain.length < 2) return '';

            return `
                <div class="taint-path" id="chain-${ownerFunc}">
                    <h5>${ownerFunc} 的调用链</h5>
                    <div class="taint-path-nodes">
                        ${chain.chain.map((func, index) => {
                            const isLast = index === chain.chain.length - 1;
                            const uniqueId = `node-${Math.random().toString(36).substr(2, 9)}`;
                            
                            return `
                                <div class="taint-node ${getNodeClass(func.type)}" 
                                     onclick="triggerDetails('${func.functionName}')">
                                    <div class="node-header">
                                        <span class="node-name">${func.functionName}</span>
                                        <span class="node-location">(${func.path}:${func.line})</span>
                                    </div>
                                    <div class="node-details">
                                        <span class="node-type">类型: ${func.type}</span>
                                        ${!isLast ? `<span class="node-next">→ ${chain.chain[index + 1].functionName}</span>` : ''}
                                    </div>
                                </div>
                                ${!isLast ? '<span class="taint-path-arrow">↓</span>' : ''}
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
        }

        function getNodeClass(type) {
            switch (type.toLowerCase()) {
                case 'userfunc':
                    return 'taint-userfunc';
                case 'exec':
                    return 'taint-sink';
                default:
                    return 'taint-propagation';
            }
        }

        // 添加跳转函数
        function jumpToChain(funcName) {
            const chainElement = document.getElementById(`chain-${funcName}`);
            if (chainElement) {
                chainElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                // 添加高亮效果
                chainElement.style.backgroundColor = '#fff3e0';
                setTimeout(() => {
                    chainElement.style.backgroundColor = '';
                }, 2000);
            }
        }

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
                            // 构建调用链
                            const callChains = buildCallChain(result);

                            // 显示原始结果
                            result.results.forEach((res, index) => {
                                if (res.status === 'success') {
                                    resultHtml += `<h5 class="mt-4">文件 ${index + 1}:</h5>`;

                                    // 解析HTML表格
                                    const parser = new DOMParser();
                                    const doc = parser.parseFromString(res.details, 'text/html');
                                    const rows = doc.querySelectorAll('tbody tr');

                                    // 为每个SinkFunc添加调用链
                                    rows.forEach(row => {
                                        const cells = row.querySelectorAll('td');
                                        if (cells.length >= 4) {
                                            const sinkFunc = cells[1].textContent.trim();
                                            const type = cells[2].textContent.trim();

                                            // 如果是UserFunc，显示其调用链
                                            if (type === 'UserFunc' && callChains[sinkFunc]) {
                                                resultHtml += generateCallChainHTML(callChains[sinkFunc], sinkFunc);
                                            }
                                        }
                                    });

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
                            // 构建调用链
                            const callChains = buildCallChain(result);

                            // 显示原始结果
                            result.results.forEach((res, index) => {
                                if (res.status === 'success') {
                                    resultHtml += `<h5 class="mt-4">文件 ${index + 1}:</h5>`;
                                    resultHtml += res.details;
                                } else if (res.status === 'error') {
                                    resultHtml += `<h5 class="mt-4">文件 ${index + 1}:</h5>`;
                                    resultHtml += `<div class="alert alert-warning">解析错误：${res.message}</div>`;
                                }
                            });

                            // 在底部显示所有调用链
                            resultHtml += `<h4 class="mt-4">函数调用链</h4>`;
                            result.results.forEach((res, index) => {
                                if (res.status === 'success') {
                                    // 解析HTML表格
                                    const parser = new DOMParser();
                                    const doc = parser.parseFromString(res.details, 'text/html');
                                    const rows = doc.querySelectorAll('tbody tr');

                                    // 为每个函数添加其调用链
                                    rows.forEach(row => {
                                        const cells = row.querySelectorAll('td');
                                        if (cells.length >= 4) {
                                            const sinkFunc = cells[1].textContent.trim();
                                            const type = cells[2].textContent.trim();

                                            // 显示该函数的调用链
                                            if (callChains[sinkFunc]) {
                                                resultHtml += generateCallChainHTML(callChains[sinkFunc], sinkFunc);
                                            }
                                        }
                                    });
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

                        // 为表格中的UserFunc类型函数添加跳转按钮
                        document.querySelectorAll('tbody tr').forEach(row => {
                            const cells = row.querySelectorAll('td');
                            if (cells.length >= 7) { // 确保有足够的单元格
                                const type = cells[2].textContent.trim();
                                if (type === 'UserFunc') { // 只为UserFunc类型添加跳转按钮
                                    const sinkFunc = cells[1].textContent.trim();
                                    const detailCell = cells[6];
                                    const jumpButton = document.createElement('button');
                                    jumpButton.className = 'btn btn-info ml-2';
                                    jumpButton.textContent = '跳转到调用链';
                                    jumpButton.onclick = function() {
                                        jumpToChain(sinkFunc);
                                    };
                                    detailCell.appendChild(jumpButton);
                                }
                            }
                        });

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