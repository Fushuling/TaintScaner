# TaintScaner

关于本项目的开发细节：[关于TaintScaner的开发](https://fushuling.com/index.php/2025/02/18/关于taintscaner的开发/)

## 工具概述

一款基于污点分析的PHP扫描工具，能快速匹配从常见Source点如$_POST、$GET到Sink点system等的路径，同时支持单独对函数的扫描。

## 更新日志

**2025/02/18**

开源

**2025/03/31**

更新至v1.1

- 将分支的上限设置为100，超过100会返回为空，防止扫大型项目时会显示扫描失败
- 优化了if的解析逻辑
- 补充了对foreach的解析
- 对通过dirty_func扫描得到的新sink点做了集中显示，方便添加![](https://fushuling-1309926051.cos.ap-shanghai.myqcloud.com/2025%2F03%2FQQ%E6%88%AA%E5%9B%BE20250331181908-31-3-.png)![](https://fushuling-1309926051.cos.ap-shanghai.myqcloud.com/2025%2F03%2FQQ%E6%88%AA%E5%9B%BE20250331185730-31-6.png)
- 修改了传播逻辑，现在只从source开始进行传播，大幅度提高了扫描速度，对于同一个项目，文件分析功能耗时只有原来的三分之一，脏函数扫描耗时降低到原来的二分之一![](https://fushuling-1309926051.cos.ap-shanghai.myqcloud.com/2025%2F03%2FQQ%E6%88%AA%E5%9B%BE20250331181431-31-1.png)![](https://fushuling-1309926051.cos.ap-shanghai.myqcloud.com/2025%2F03%2FQQ%E6%88%AA%E5%9B%BE20250331181454-31-2.png)
- 优化Details的UI，当代码过多时会启用垂直滚动条![](https://fushuling-1309926051.cos.ap-shanghai.myqcloud.com/2025%2F03%2FQQ%E6%88%AA%E5%9B%BE20250331182929-31-5.png)

## 运行方法

环境：PHP 7

命令：php -S 0.0.0.0:9383

![](https://fushuling-1309926051.cos.ap-shanghai.myqcloud.com/2025%2F02%2F18%2F45.png)

## 用例

测试代码在TestProject目录中

### 分支语句测试

#### 存在默认分支的情况

```
<?php
$age = $_POST[1];
if ($age < 18) {
    system($age);
} elseif ($age == 18) {
    $age = 1;
    echo $age;
} elseif ($age == 19) {
    eval($age);
} else {
    echo $age;
}
```

该代码存在默认分支else，因此不存在一条直接跳过if语句的分支，其代码扫描结果如下：

![img](https://fushuling-1309926051.cos.ap-shanghai.myqcloud.com/2025%2F02%2F18%2F21.png)

本项目准确的识别了这四条分支，并输出了存在漏洞的三种情况，第一种情况便是直接从第一个if语句进入，点击Details，其详情如下：

![img](https://fushuling-1309926051.cos.ap-shanghai.myqcloud.com/2025%2F02%2F18%2F22.png)

可以看到本项目准确的识别到了这条存在漏洞的分支，并用橙色标记了污点传播路径，红色标记了Sink触发点。

第二条分支虽然存在Sink点echo，但由于在Sink点的前一行代码将$age重新赋值为了1，因此$age不再是污点，项目并未输出这条分支。

第三条分支直接进入eval，存在漏洞点，其详情如下：

![img](https://fushuling-1309926051.cos.ap-shanghai.myqcloud.com/2025%2F02%2F18%2F23.png)

第四条分支直接echo了$a，同样存在漏洞，其详情如下：

![img](https://fushuling-1309926051.cos.ap-shanghai.myqcloud.com/2025%2F02%2F18%2F24.png)

#### 不存在默认分支的情况

```
<?php
$age = $_POST[1];
if ($age < 18) {
    $age = $age . "1";
} elseif ($age == 18) {
    $age = 1;
}
system($age);
```

该代码一共存在三条分支，分别是第一个if，第二个elseif，以及由于不存在else这类默认分支存在的不经过if的直通路径，其扫描结果如下：

![img](https://fushuling-1309926051.cos.ap-shanghai.myqcloud.com/2025%2F02%2F18%2F25.png)

成功识别了存在漏洞的两条分支，对于第一条分支代码详情如下：

![img](https://fushuling-1309926051.cos.ap-shanghai.myqcloud.com/2025%2F02%2F18%2F26.png)

在本条分支中，出现了$age = $age . “1”这类拼接表达，虽然数字1并不是污点，但$age是污点，对于表达式而言，只要其中存在一个被污染的变量，整个表达式就认为被污染了，因此此时的$age仍然属于污点，流入了下面的Sink点system中。

而第二条分支中，变量$age被重新赋值为了1，污点的传播被截断，因此不再作为污点，不存在从Source到Sink的危险路径。第三条分支，即不经过if语句的直通路径扫描结果如下：

![img](https://fushuling-1309926051.cos.ap-shanghai.myqcloud.com/2025%2F02%2F18%2F27.png)

由于if不存在else这类默认分支，因此污点的传播可以不经过if语句直接流动，所以这里还存在一条从$age直接到达system的路径。

### 污点截断测试

首先我们在Sanitizer中加入PHP中内置的过滤函数addslashes：

![img](https://fushuling-1309926051.cos.ap-shanghai.myqcloud.com/2025%2F02%2F18%2F28.png)

```
<?php

$a = $_POST[1];
//trim是transfer
$processed_a = trim($a);
//addslashes不是transfer，是sanitizer，截断污点传播
$processed_b = addslashes($a);

system($processed_a);
system($processed_b);
```

在该代码示例中，trim函数是PHP中内置的可以去除字符串两边空格的函数，是不会影响污点传播的安全函数，而addslashes函数会在指定的预定义字符前添加反斜杠，比如单引号（”）、双引号（“”）、反斜线（\\）与NUL（NULL字符）等，是PHP中内置的过滤函数，污点流在经过它后会失去危害，代码的扫描结果如下：

![img](https://fushuling-1309926051.cos.ap-shanghai.myqcloud.com/2025%2F02%2F18%2F29.png)

项目成功识别出存在危害的那条经过trim函数的分支，并没有输出经过addslashes函数的分支，代码详情如下：

![img](https://fushuling-1309926051.cos.ap-shanghai.myqcloud.com/2025%2F02%2F18%2F30.png)

### 用户自定义函数测试

Once again，扫大项目的时候不要加这句解析函数表的代码，但如果是单文件的话倒是无所谓，这里来测试一下单文件，我们把那句代码加回来：

![img](https://fushuling-1309926051.cos.ap-shanghai.myqcloud.com/2025%2F02%2F18%2F31.png)

```
<?php

function test($a)
{
    eval($a);
}

$s = $_GET['fushuling'];

test($s);
```

其中test函数是一个用户自定义函数，接收参数$a，并在具体语句中使用PHP中可以直接执行代码的危险函数eval函数直接执行了参数$a的值，属于典型的危险函数，其扫描结果如下：

![img](https://fushuling-1309926051.cos.ap-shanghai.myqcloud.com/2025%2F02%2F18%2F32.png)

代码详情如下：

![img](https://fushuling-1309926051.cos.ap-shanghai.myqcloud.com/2025%2F02%2F18%2F33.png)

### 真实项目测试

这里用的项目是SeaCMS_12.9的代码，**已经不是最新版本了**，而且也修的差不多了，之前拿这个扫了一些没什么含金量的洞，这里仅作为测试样例

#### RCE

这里我们使用文件分析功能

![img](https://fushuling-1309926051.cos.ap-shanghai.myqcloud.com/2025%2F02%2F18%2F34.png)

15.2MB用时6.69秒，速度还可以，这里随便选一个，就选这个admin_wexin.php：

![img](https://fushuling-1309926051.cos.ap-shanghai.myqcloud.com/2025%2F02%2F18%2F35.png)

Details代码太多了，这里就节选一下：

![img](https://fushuling-1309926051.cos.ap-shanghai.myqcloud.com/2025%2F02%2F18%2F36.png)

可以看到漏洞产生的主要问题是$str使用拼接的方法拼接了污点数据$url、$dpic等的数据，并直接传入了危险函数fwrite，该函数可以向指定文件中写入字符，若被写入的字符可控会对整个服务产生巨大的影响，我们可以在本地搭建环境测试

![img](https://fushuling-1309926051.cos.ap-shanghai.myqcloud.com/2025%2F02%2F18%2F37.png)

可以看到由于这些参数可控，所以我们只需要闭合一下前后的引号，就可以向weixin.php这个文件中写入任意代码，存在严重的代码注入风险，接着我们访问weixin.php：

![img](https://fushuling-1309926051.cos.ap-shanghai.myqcloud.com/2025%2F02%2F18%2F38.png)

可以看到该页面成功执行了我们的代码system(“whoami”)，在网页上输出了用户的信息，存在极其严重的安全风险。

#### SQL注入

这里我们使用函数扫描功能

![img](https://fushuling-1309926051.cos.ap-shanghai.myqcloud.com/2025%2F02%2F18%2F39.png)

这里就用时19秒了，慢多了，因为这种项目定义的函数都多，而我们扫函数都是扫两遍，一遍有条件，一遍无条件，所以速度就慢下来了，我们来看这个No condition的编辑_弹幕函数

![img](https://fushuling-1309926051.cos.ap-shanghai.myqcloud.com/2025%2F02%2F18%2F40.png)

可以看到是非常典中典的没过滤参数就直接拼接进SQL语句了

![img](https://fushuling-1309926051.cos.ap-shanghai.myqcloud.com/2025%2F02%2F18%2F41.png)

由于是No condition的，所以也没啥必要二次传播了，去全局搜索一下这个函数

![img](https://fushuling-1309926051.cos.ap-shanghai.myqcloud.com/2025%2F02%2F18%2F42.png)

再搜一下这个编辑弹幕

![img](https://fushuling-1309926051.cos.ap-shanghai.myqcloud.com/2025%2F02%2F18%2F43.png)

一眼顶针，SQL注入，跑一下sqlmap

![img](https://fushuling-1309926051.cos.ap-shanghai.myqcloud.com/2025%2F02%2F18%2F44.png)
