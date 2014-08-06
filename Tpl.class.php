<?php
	namespace Eliya;
	
	abstract class Tpl
	{
		protected static $tpls		=	[];
		protected static $variables	=	[];
		
		public static function get($file_path, array $data	=	[])
		{
			ob_start();

			if( ! empty($data) || ! empty(self::$variables))
				$data	=	array_merge(self::$variables, $data);

			$view	=	new \stdClass();

			foreach($data as $name => $value)
				$view->$name	=	$value;

			include PROJECT_ROOT.'application'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.$file_path.'.php';

			$contents	=	ob_get_contents();

			ob_clean();
				
			return $contents;
		}
		
		public static function set($name, $value = null)
		{
			if(is_array($name))
				self::$variables	=	array_merge(self::$variables, $name);
			else
				self::$variables[$name]	=	$value;
		}
	}
?>