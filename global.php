<?php
//包含必要的配置文件
include 'vendor/autoload.php';

require 'config/AstToIrConverter.php';  //AST->Q_IR
require 'config/TaintFlowAnalysisCore.php'; //污点分析核心逻辑

require 'config/analysis/Sink.php';
require 'config/analysis/Source.php';
require 'config/analysis/TaintAnalyzer.php';
require 'config/analysis/Sanitizer.php';

require 'config/control_flow/BasicBlockHandler.php';
require 'config/control_flow/CFGBuilder.php';


require 'config/utils/Visitor.php';
require 'config/utils/FuncTable.php';
