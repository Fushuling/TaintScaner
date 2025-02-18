<?php

//Sanitizer集合

/*
默认情况下，所有内置函数和用户函数都视作可以传播污点流。若函数名在Sanitizer中，则无法传播
*/

$SanitizerAll = array(
    'sanitizer_test',
    'htmlspecialchars',
    'filter_var',
    'strip_tags',
    'mysqli_real_escape_string',
    'addslashes',
    'str_ireplace',
    'intval',
    'md5',
);
