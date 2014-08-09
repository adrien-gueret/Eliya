<?php
	namespace Eliya;

	var_dump('Before require');
	require_once('Controller_Ajax.class.php');
	var_dump('After require');

	abstract class Controller_API extends Controller_Ajax
	{
		const DEFAULT_LIMIT		=	20;
		const MAX_LIMIT			=	50;

		const DEFAULT_OFFSET	=	0;

		const LIMIT_PARAM_NAME	=	'limit';
		const OFFSET_PARAM_NAME	=	'offset';

		abstract protected function __getCheckingRules();
		abstract protected function __createObject(array $props);
		abstract protected function __updateObject(&$object, array $props, $object_id);
		abstract protected function __deleteObject(&$object, $object_id);
		abstract protected function __getTotal();
		abstract protected function __getAll($limit, $offset);
		abstract protected function __getById($id);

		protected	$_limit		=	self::DEFAULT_LIMIT;
		protected	$_offset	=	self::DEFAULT_OFFSET;

		public function __init($response_type = Mime::JSON)
		{
			$accept			=	isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : null;

			if( ! empty($accept) && $accept !== '*/*' && strpos($accept, ',') === false && Mime::isDefined($accept))
				$response_type	=	$accept;

			parent::__init($response_type);

			if($response_type === Mime::JSON)
			{
				$jsonp_callback_name	=	$this->request->get(static::JSONP_CALLBACK_PARAM_NAME);
				$this->_allow_jsonp		=	true;
				$this->_jsonp_callback	=	! empty($jsonp_callback_name) ? $jsonp_callback_name : null;
			}
		}

		//Get data
		public function get_index($id = null)
		{
			$limit			=	$this->request->get(static::LIMIT_PARAM_NAME);
			$offset			=	$this->request->get(static::OFFSET_PARAM_NAME);

			$this->_limit	=	empty($limit) ? static::DEFAULT_LIMIT : intval($limit);
			$this->_offset	=	empty($offset) ? static::DEFAULT_OFFSET : intval($offset);

			try
			{
				if($id === null)
				{
					$data	=	$this->__getAll($this->_limit, $this->_offset);

					if($data instanceof \EntityPHP\EntityArray)
						$data	=	$data->getArray();

					if( ! is_array($data))
						$data	=	[$data];

					$data	=	array_map(function ($item) {

						if($item instanceof \EntityPHP\Entity)
							return $item->toArray();

						return $item;

					}, $data);

					$this->sendCollection($data);
				}
				else
				{
					$data	=	$this->__getById($id);

					$this->success($data instanceof \EntityPHP\Entity ? $data->toArray() : $data);
				}
			}
			catch(APIException $e)
			{
				$this->error($e->getData(), $e->getStatus());
			}
		}

		//Create data
		public function post_index()
		{
			try
			{
				$this->checkProps($this->request->post());

				$newIdObject	=	$this->__createObject($this->request->post());

				if( ! empty($newIdObject))
				{
					$this->success([
						'id'		=>	$newIdObject,
						'details'	=>	self::getPath().'/'.$newIdObject,
					], 201);
				}
			}
			catch(APIException $e)
			{
				$this->error($e);
			}
			catch(\Exception $e)
			{
				$this->error(new \Exception_API_GenericError($e));
			}
		}

		//Update data
		public function put_index($id)
		{
			try
			{
				$object	=	$this->__getById($id);

				$props_to_set	=	[];
				$rules			=	$this->__getCheckingRules();

				foreach($this->request->put() as $name => $value)
				{
					try
					{
						$rules[$name]($value);
						$props_to_set[$name]	=	$value;
					}
					catch(\Exception_API_MissingParam $e)
					{
						continue;
					}
				}

				$idObject	=	$this->__updateObject($object, $props_to_set, $id);

				if( ! empty($idObject))
				{
					$this->success([
						'id'		=>	$idObject,
						'details'	=>	self::getPath().'/'.$idObject,
					], 200);
				}
			}
			catch(APIException $e)
			{
				$this->error($e);
			}
			catch(\Exception $e)
			{
				$this->error(new \Exception_API_GenericError($e));
			}
		}

		//Delete data
		public function delete_index($id)
		{
			try
			{
				$object	=	$this->__getById($id);

				$return	=	$this->__deleteObject($object, $id);

				if($return !== false)
					$this->success([], 204);
			}
			catch(APIException $e)
			{
				$this->error($e);
			}
			catch(\Exception $e)
			{
				$this->error(new \Exception_API_GenericError($e));
			}
		}

		public final function checkProps(array $props)
		{
			$rules	=	$this->__getCheckingRules();

			foreach($rules as $prop => $rule)
				$rule(isset($props[$prop]) ? $props[$prop] : null);
		}

		public function sendCollection(array $collection)
		{
			$this->success([
			   'metadata'	=>	[
				   'count'	=>	$this->__getTotal(),
				   'limit'	=>	$this->_limit,
				   'offset'	=>	$this->_offset,
			   ],
			   'results'	=>	$collection
		   ]);
		}

		public function error($data, $status = 500)
		{
			if($data instanceof APIException)
			{
				$status	=	$data->getStatus();
				$data	=	$data->getData();
			}

			parent::error($data, $status);
		}
	}

	class APIException extends \Exception
	{
		protected $_data	=	[];

		public function __construct(array $data = [], $code = 0, \Exception $previous = null) {

			if(empty($this->_data) && !empty($data))
				$this->_data	=	$data;

			$this->_data['status']		=	isset($this->_data['status']) ? $this->_data['status'] : 500;
			$this->_data['errorCode']	=	isset($this->_data['errorCode']) ? $this->_data['errorCode'] : $code;

			parent::__construct(
				isset($this->_data['developerMessage']) ? $this->_data['developerMessage'] : null,
				$this->_data['errorCode'],
				$previous
			);
		}

		public function __toString() {
			return json_encode($this->_data);
		}

		public function getData() {
			return $this->_data;
		}

		public function getStatus() {
			return $this->_data['status'];
		}
	}
?>