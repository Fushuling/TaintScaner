VulnerabilityTest目录是一些测试漏洞的代码

ASTmainForTest.php(用于单文件)和CrossParseTest.php(用于跨文件的函数解析)都是开发过程中使用的测试代码，可以看到他读取了test.php进行处理，核心逻辑是

```
var_dump($parseResult); //AST
var_dump($quads); //Q_IR
var_dump($flow_graph->BasicBlockHandler->BasicBlock); //基本块
var_dump($flow_graph->CFGBuilder->CFG); //CFG
var_dump($flow_graph->TaintAnalyzer->varMap); //变量
var_dump($flow_graph->sinks); //污点
```

可以输出代码运行过程中产生的AST、IR、基本块、CFG、变量表和污点，便于调试