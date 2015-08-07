<?php
	namespace Eliya;
	
	class Config
	{
		protected static	$configurations	=	[];
		protected static $extensions		=	['json', 'php'];
		
		protected			$config				=	[];
		
		public function __construct($name)
		{
			if( ! isset(self::$configurations[$name]))
			{
				$fullPath = static::exists($name);
				
				if($fullPath === null)
					throw new \Exception('Configuration file "' . $name . '" not found.');

				switch($fullPath["ext"])
				{
					case 'json': $this->config	=	json_decode(file_get_contents($fullPath["path"]), true); break;
					case 'php': $this->config	=	include $fullPath["path"]; break;
				}
				
				self::$configurations[$name]	=	$this->config;
			}
			else
				$this->config	=	self::$configurations[$name];
		}
		
		public function __get($prop)
		{
			return	isset($this->config[$prop]) ? $this->config[$prop] : null;
		}

		public static function exists($name)
		{
			$path	=	PROJECT_ROOT . 'application' . DIRECTORY_SEPARATOR . 'configs' . DIRECTORY_SEPARATOR . $name . '.';
			$ext		=	null;
			
			foreach(self::$extensions as $extension)
			{
				if(file_exists($path.$extension))
				{
					$path	.=	$extension;
					$ext	=	$extension;
					return array('path' => $path, 'ext' => $ext);
				}
			}
			
			return null;
		}

	}
?>