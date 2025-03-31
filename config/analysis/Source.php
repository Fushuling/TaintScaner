<?php
//source点 用户输入
class Sources
{
	/**
	 * 获取用户的输入sources
	 * @return array
	 */
	public static function GetUserInput()
	{
		// 增加递归深度限制标志
		static $callDepth = 0;
		$maxDepth = 50; // 设置最大递归深度

		// 检查递归深度是否超出限制
		if ($callDepth >= $maxDepth) {
			trigger_error("Maximum recursion depth ($maxDepth) reached in GetUserInput", E_USER_WARNING);
			return [];
		}

		$callDepth++; // 递归深度加1

		try {
			$result = self::$userInput;
		} catch (Exception $e) {
			// 捕获可能的异常，防止程序崩溃
			trigger_error("Error in GetUserInput: " . $e->getMessage(), E_USER_WARNING);
			$result = [];
		}

		$callDepth--; // 递归深度减1
		return $result;
	}

	//用户输入参数
	public static $userInput = array(
		'_GET',
		'_POST',
		'_COOKIE',
		'_REQUEST',
		'_SERVER',
		'_ENV',
		'_SESSION'
	);
}
