<?php
	namespace Eliya;
	
	class Request
	{
		const	GET			=	'get',
					POST		=	'post',
					PUT			=	'put',
					DELETE	=	'delete',

					HTTP		=	'http',
					HTTPS		=	'https';

		public static $routing	=	[];

		protected $_uri			=	null;
		protected $_method		=	null;
		protected $_protocol	=	null;
		protected $_post_params	=	[];
		protected $_get_params	=	[];
		protected $_put_params	=	[];
		protected $_is_secured	=	null;
		protected $_from_ajax	=	false;
		protected $_controller_name		=	null;
		protected $_controller_action	=	null;
		protected $_response			=	null;

		protected static $_PUT	=	[];

		final public static function getPutParams()
		{
			if( ! empty(self::$_PUT))
				return self::$_PUT;

			try
			{
				$put_resource	=	fopen('php://input', 'r');
				$put_data		=	null;

				while ($put = fread($put_resource, 1024))
					$put_data	.=	$put;

				if( ! empty($put_data))
					parse_str($put_data, self::$_PUT);
			}
			catch(\Exception $e){}

			return self::$_PUT;
		}

		public function __construct($uri, $method = null, array $params = [])
		{
			$this->_from_ajax	=	isset($_SERVER['HTTP_X_REQUESTED_WITH']);
			$this->_response	=	new Response();

			//Request method
			if(empty($method))
			{
				if(isset($_POST['__method__']))
					$this->_method		=	strtolower($_POST['__method__']);
				else if(isset($_GET['__method__']))
					$this->_method	=	strtolower($_GET['__method__']);
				else
					$this->_method	=	strtolower($_SERVER['REQUEST_METHOD']);
			}
			else
				$this->_method	=	strtolower($method);

			if(empty($params))
			{
				$this->_post_params	=	$_POST;
				$this->_get_params	=	$_GET;
				$this->_put_params	=	self::getPutParams();
			}
			else
			{
				switch($this->_method)
				{
					case self::GET:
						$this->_get_params	=	$params;
					break;

					case self::POST:
						$this->_post_params	=	$params;
					break;

					case self::PUT:
						$this->_put_params	=	$params;
					break;
				}

				//Add GET params from URI
				$indexParams	=	strrpos($uri, '?');

				if($indexParams > -1)
				{
					$additionnal_params	=	[];
					parse_str(substr($uri, $indexParams + 1), $additionnal_params);
					$this->_get_params	=	array_merge($this->_get_params, $additionnal_params);
				}
			}

			//Get URI
			$this->_uri	=	$uri;

			if(empty(self::$routing))
				self::$routing	=	Config('main')->ROUTING;

			if(strpos($this->_uri, self::$routing['BASE_URL']) !== 0)
			{
				if(empty($method) && empty($params))
				{
					$this->_response->error('Constant ROUTING.BASE_URL ('.self::$routing['BASE_URL'].') from main configuration file does not start with received URI.');
					return;
				}
				else
					$this->_uri	=	self::$routing['BASE_URL'].$this->_uri;
			}

			$this->_checkPath();
		}

		protected function _checkPath()
		{
			$className	=	null;
			$action		=	null;
			$type		=	$this->_method;

			$this->_uri	=	preg_replace('#/{2,}#', '/', trim(substr($this->_uri, strlen(self::$routing['BASE_URL']))));
			$param_pos	=	strpos($this->_uri, '?');

			if($param_pos !== false)
				$this->_uri	=	substr($this->_uri, 0, $param_pos);

			$rules	=	isset(self::$routing['RULES']) ? self::$routing['RULES'] : [];

			//Check Routing rules
			if( ! empty($rules))
			{
				$params	=	[];

				foreach($rules as $path => $data_controller)
				{
					$found	=	preg_match('#^' . $path . '$#isU', $this->_uri, $params);
					if(!empty($found))
					{
						$className	=	Controller::START_CLASS_NAME.current($data_controller);
						$action		=	next($data_controller);

						for($i = 1, $l = count($params); $i < $l; $i++)
						{
							$param_name	=	next($data_controller);
							if(empty($param_name))
								break;

							$this->_get_params[$param_name]	=	$params[$i];
						}

						break;
					}
				}
			}

			//Check path
			if(empty($className))
			{
				$path			=	array_filter(explode('/', $this->_uri));
				$className	=	Controller::START_CLASS_NAME;

				if(empty($path))
				{
					$className	.=	'index';
					$action		=	'index';
				}
				else
				{
					foreach($path as $name)
						$className	.=	$name . '#';

					$className	=	explode('#', substr($className, 0, -1));

					if(count($className) > 1)
					{
						$action		=	array_pop($className);
						$className	=	join('_', $className);
					}
					else
						$className	=	current($className);

					if( ! class_exists($className))
					{
						if(empty($action))
							$action	=	'index';

						$className	.=	empty($className) ? $action : '_' . $action;
						$action		=	'index';
					}

					if(class_exists($className))
					{
						$method			=	$type . '_' . $action;

						if(!method_exists($className, $method) && $action != 'index')
						{
							$prevClassName	=	$className;
							$className		.=	'_' . $action;

							if(!class_exists($className))
								$className	=	$prevClassName;
							else
								$action		=	'index';
						}
					}
				}
			}

			$error_404_message	=	'Controller "' . $className . '" does not exist.';

			if(!class_exists($className))
			{
				$this->_response->error($error_404_message, 404);
				return $this;
			}

			$reflectionClass = new \ReflectionClass($className);

			//We can't directly go to abstract controllers
			if($reflectionClass->isAbstract())
			{
				$this->_response->error($error_404_message, 404);
				return $this;
			}

			//Last chance for action to be defined
			if(empty($action))
				$action	=	'index';

			$method	=	$type . '_' . $action;

			if( ! method_exists($className, $method))
			{
				//If we want to access an API controller, we can redirect to index
				if(!$reflectionClass->isSubclassOf('Eliya\Controller_API'))
				{
					$this->_response->error('Controller "' . $className . '" does not have method "' . $method . '".', 405);
					return $this;
				}

				$method						=	$type . '_' . 'index';
				$this->_get_params['id']	=	$action;

				if( ! method_exists($className, $method))
				{
					$apiClass	=	new $className();
					$apiClass->error(new \Exception_API_MethodNotHandled($className, $type, $action));

					return $this;
				}
			}

			$this->_controller_name		=	$className;
			$this->_controller_action	=	$method;

			return	$this;
		}

		public function exec()
		{
			if( ! empty($this->_controller_name) &&  ! empty($this->_controller_action))
			{
				//Call given controller and its action
				$controller			=	new $this->_controller_name($this);
				$controller->__init();

				$reflectionClass	=	new \ReflectionClass($this->_controller_name);
				$reflectionMethod	=	$reflectionClass->getMethod($this->_controller_action);
				$parameters			=	[];

				//Check parameters
				foreach ($reflectionMethod->getParameters() as $param)
				{
					if(isset($this->_post_params[$param->name]))
						$parameters[]	=	$this->_post_params[$param->name];
					else if(isset($this->_get_params[$param->name]))
						$parameters[]	=	$this->_get_params[$param->name];
					else if(isset($this->_put_params[$param->name]))
						$parameters[]	=	$this->_put_params[$param->name];
					else if($param->isOptional())
						$parameters[]	=	$param->getDefaultValue();
					else
						$parameters[]	=	null;
				}

				$returnValue	=	$controller->__before();

				if($returnValue !== false)
				{
					$returnValue	=	$reflectionMethod->invokeArgs($controller, $parameters);

					if($returnValue !== false)
						$controller->__after();
				}

				if( ! $this->_response->isRaw())
				{
					$this->_response->prepend(Tpl::get($controller->getHeaderViewPath().'/header'));
					$this->_response->append(Tpl::get($controller->getFooterViewPath().'/footer'));
				}
			}

			return $this;
		}

		public function response(Response $response = null)
		{
			if(empty($response))
				return $this->_response;

			$this->_response	=	$response;
			return $this;
		}

		public function getMethod()
		{
			return $this->_method;
		}

		protected function _setSecureStatus()
		{
			$protocols	=	['http', 'https'];

			if( ! empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && in_array($_SERVER['HTTP_X_FORWARDED_PROTO'], $protocols))
				$this->_protocol	=	$_SERVER['HTTP_X_FORWARDED_PROTO'];
			else
			{
				if( ! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
					$this->_protocol	=	self::HTTPS;
				else
					$this->_protocol	=	self::HTTP;
			}

			$this->_is_secured	=	$this->_protocol === self::HTTPS;
		}

		public function getProtocol()
		{
			if(empty($this->_protocol))
				$this->_setSecureStatus();

			return $this->_protocol;
		}

		public function isSecured()
		{
			if(is_null($this->_is_secured))
				$this->_setSecureStatus();

			return $this->_is_secured;
		}

		public function isFromAjax()
		{
			return $this->_from_ajax;
		}

		public function getBaseURL($https = null)
		{
			switch($https)
			{
				case null:
					$protocol	=	$this->getProtocol();
				break;

				case false:
					$protocol	=	self::HTTP;
				break;

				case true:
					$protocol	=	self::HTTPS;
				break;

				default:
					$protocol	=	$https;
				break;
			}

			return $protocol.'://'.$_SERVER['HTTP_HOST'].Config('main')->ROUTING['BASE_URL'];
		}

		protected function _param($type, $name = null, $value = null)
		{
			$property	=	'_'.$type.'_params';
			$property	=	$this->$property;

			if(empty($name))
				return $property;

			if(empty($value))
				return isset($property[$name]) ? $property[$name] : null;

			$property[$name]	=	$value;

			return $this;
		}

		public function get($name = null, $value = null)
		{
			return $this->_param(self::GET, $name, $value);
		}

		public function post($name = null, $value = null)
		{
			return $this->_param(self::POST, $name, $value);
		}

		public function put($name = null, $value = null)
		{
			return $this->_param(self::PUT, $name, $value);
		}
	}