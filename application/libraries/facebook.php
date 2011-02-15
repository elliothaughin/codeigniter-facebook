<?php

	class facebook {
		
		private $_api_url;
		private $_api_key;
		private $_api_secret;
		private $_errors = array();
		private $_enable_debug = FALSE;
		
		function __construct()
		{
			$this->_obj =& get_instance();
			
			$this->_obj->load->library('session');
			$this->_obj->load->config('facebook');
			$this->_obj->load->helper('url');
			$this->_obj->load->helper('facebook');
			
			$this->_api_url 	= $this->_obj->config->item('facebook_api_url');
			$this->_api_key 	= $this->_obj->config->item('facebook_app_id');
			$this->_api_secret 	= $this->_obj->config->item('facebook_api_secret');
			
			$this->session = new facebookSession();
			$this->connection = new facebookConnection();
		}
		
		public function logged_in()
		{
			return $this->session->logged_in();
		}
		
		public function login($scope = NULL)
		{
			return $this->session->login($scope);
		}
		
		public function login_url($scope = NULL)
		{
			return $this->session->login_url($scope);
		}
		
		public function logout()
		{
			return $this->session->logout();
		}
		
		public function user()
		{
			return $this->session->get();
		}
		
		public function call($method, $uri, $data = array())
		{
			$response = FALSE;
			
			try
			{
				switch ( $method )
				{
					case 'get':
						$response = $this->connection->get($this->append_token($this->_api_url.$uri));
					break;
					
					case 'post':
						$response = $this->connection->post($this->append_token($this->_api_url.$uri), $data);
					break;
				}
			}
			catch (facebookException $e)
			{
				$this->_errors[] = $e;
				
				if ( $this->_enable_debug )
				{
					echo $e;
				}
			}
			
			return $response;
		}
		
		public function errors()
		{
			return $this->_errors;
		}
		
		public function last_error()
		{
			if ( count($this->_errors) == 0 ) return NULL;
			
			return $this->_errors[count($this->_errors) - 1];
		}
		
		public function append_token($url)
		{
			return $this->session->append_token($url);
		}
		
		public function set_callback($url)
		{
			return $this->session->set_callback($url);
		}
		
		public function enable_debug($debug = TRUE)
		{
			$this->_enable_debug = (bool) $debug;
			
			
		}
	}
	
	class facebookConnection {
		
		// Allow multi-threading.
		
		private $_mch = NULL;
		private $_properties = array();
		
		function __construct()
		{
			$this->_mch = curl_multi_init();
			
			$this->_properties = array(
				'code' 		=> CURLINFO_HTTP_CODE,
				'time' 		=> CURLINFO_TOTAL_TIME,
				'length'	=> CURLINFO_CONTENT_LENGTH_DOWNLOAD,
				'type' 		=> CURLINFO_CONTENT_TYPE
			);
		}
		
		private function _initConnection($url)
		{
			$this->_ch = curl_init($url);
			curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, TRUE);
		}
		
		public function get($url, $params = array())
		{
			if ( count($params) > 0 )
			{
				$url .= '?';
			
				foreach( $params as $k => $v )
				{
					$url .= "{$k}={$v}&";
				}
				
				$url = substr($url, 0, -1);
			}
			
			$this->_initConnection($url);
			$response = $this->_addCurl($url, $params);

		    return $response;
		}
		
		public function post($url, $params = array())
		{
			// Todo
			$post = '';
			
			foreach ( $params as $k => $v )
			{
				$post .= "{$k}={$v}&";
			}
			
			$post = substr($post, 0, -1);
			
			$this->_initConnection($url, $params);
			curl_setopt($this->_ch, CURLOPT_POST, 1);
			curl_setopt($this->_ch, CURLOPT_POSTFIELDS, $post);
			
			$response = $this->_addCurl($url, $params);

		    return $response;
		}
		
		private function _addCurl($url, $params = array())
		{
			$ch = $this->_ch;
			
			$key = (string) $ch;
			$this->_requests[$key] = $ch;
			
			$response = curl_multi_add_handle($this->_mch, $ch);

			if ( $response === CURLM_OK || $response === CURLM_CALL_MULTI_PERFORM )
			{
				do {
					$mch = curl_multi_exec($this->_mch, $active);
				} while ( $mch === CURLM_CALL_MULTI_PERFORM );
				
				return $this->_getResponse($key);
			}
			else
			{
				return $response;
			}
		}
		
		private function _getResponse($key = NULL)
		{
			if ( $key == NULL ) return FALSE;
			
			if ( isset($this->_responses[$key]) )
			{
				return $this->_responses[$key];
			}
			
			$running = NULL;
			
			do
			{
				$response = curl_multi_exec($this->_mch, $running_curl);
				
				if ( $running !== NULL && $running_curl != $running )
				{
					$this->_setResponse($key);
					
					if ( isset($this->_responses[$key]) )
					{
						$response = new facebookResponse( (object) $this->_responses[$key] );
						
						if ( $response->__resp->code !== 200 )
						{
							$error = $response->__resp->code.' | Request Failed';
							
							if ( isset($response->__resp->data->error->type) )
							{
								$error .= ' - '.$response->__resp->data->error->type.' - '.$response->__resp->data->error->message;
							}
							
							throw new facebookException($error);
						}
						
						return $response;
					}
				}
				
				$running = $running_curl;
				
			} while ( $running_curl > 0);
			
		}
		
		private function _setResponse($key)
		{
			while( $done = curl_multi_info_read($this->_mch) )
			{
				$key = (string) $done['handle'];
				$this->_responses[$key]['data'] = curl_multi_getcontent($done['handle']);
				
				foreach ( $this->_properties as $curl_key => $value )
				{
					$this->_responses[$key][$curl_key] = curl_getinfo($done['handle'], $value);
					
					curl_multi_remove_handle($this->_mch, $done['handle']);
				}
		  }
		}
	}
	
	class facebookResponse {
		
		private $__construct;

		public function __construct($resp)
		{
			$this->__resp = $resp;

			$data = json_decode($this->__resp->data);
			
			if ( $data !== NULL )
			{
				$this->__resp->data = $data;
			}
		}

		public function __get($name)
		{
			if ($this->__resp->code < 200 || $this->__resp->code > 299) return FALSE;

			$result = array();

			if ( is_string($this->__resp->data ) )
			{
				parse_str($this->__resp->data, $result);
				$this->__resp->data = (object) $result;
			}
			
			if ( $name === '_result')
			{
				return $this->__resp->data;
			}
			
			return $this->__resp->data->$name;
		}
	}
	
	class facebookException extends Exception {
		
		function __construct($string)
		{
			parent::__construct($string);
		}
		
		public function __toString() {
			return "exception '".__CLASS__ ."' with message '".$this->getMessage()."' in ".$this->getFile().":".$this->getLine()."\nStack trace:\n".$this->getTraceAsString();
		}
	}
	
	class facebookSession {
		
		private $_api_key;
		private $_api_secret;
		private $_token_url 	= 'oauth/access_token';
		private $_user_url		= 'me';
		private $_data			= array();
		
		function __construct()
		{
			$this->_obj =& get_instance();
			
			$this->_api_key 	= $this->_obj->config->item('facebook_app_id');
			$this->_api_secret 	= $this->_obj->config->item('facebook_api_secret');
			
			$this->_token_url 	= $this->_obj->config->item('facebook_api_url').$this->_token_url;
			$this->_user_url 	= $this->_obj->config->item('facebook_api_url').$this->_user_url;
			
			$this->_set('scope', $this->_obj->config->item('facebook_default_scope'));
			
			$this->connection = new facebookConnection();
			
			if ( !$this->logged_in() )
			{
				 // Initializes the callback to this page URL.
				$this->set_callback();
			}
			
		}
		
		public function logged_in()
		{
			return ( $this->get() === NULL ) ? FALSE : TRUE;
		}
		
		public function logout()
		{
			$this->_unset('token');
			$this->_unset('user');
		}
		
		public function login_url($scope = NULL)
		{
			$url = "http://www.facebook.com/dialog/oauth?client_id=".$this->_api_key.'&redirect_uri='.urlencode($this->_get('callback'));
			
			if ( empty($scope) )
			{
				$scope = $this->_get('scope');
			}
			else
			{
				$this->_set('scope', $scope);
			}
			
			if ( !empty($scope) )
			{
				$url .= '&scope='.$scope;
			}
			
			return $url;
		}
		
		public function login($scope = NULL)
		{
			$this->logout();
			
			$url = $this->login_url($scope);
				
			return redirect($url);
		}
		
		public function get()
		{
			$token = $this->_find_token();
			if ( empty($token) ) return NULL;
			
			// $user = $this->_get('user');
			// if ( !empty($user) ) return $user;
			
			try 
			{
				$user = $this->connection->get($this->_user_url.'?'.$this->_token_string());
			}
			catch ( facebookException $e )
			{
				$this->logout();
				return NULL;
			}
			
			// $this->_set('user', $user);
			return $user;
		}
		
		private function _find_token()
		{
			$token = $this->_get('token');
			
			if ( !empty($token) )
			{
				if ( !empty($token->expires) && intval($token->expires) >= time() )
				{
					// Problem, token is expired!
					return $this->logout();
				}
				
				$this->_set('token', $token);
				return $this->_token_string();
			}
			
			if ( !isset($_GET['code']) )
			{
				return $this->logout();
			}
			
			$token_url = $this->_token_url.'?client_id='.$this->_api_key."&client_secret=".$this->_api_secret."&code=".$_GET['code'];
			
			if ( $this->_get('callback') )
			{
				$token_url .= '&redirect_uri='.urlencode($this->_get('callback'));
			}
			
			try 
			{
				$token = $this->connection->get($token_url);
			}
			catch ( facebookException $e )
			{
				$this->logout();
				redirect($this->_strip_query());
				return NULL;
			}
			
			$this->_unset('callback');
			
			if ( $token->access_token )
			{
				if ( !empty($token->expires) )
				{
					$token->expires = strval(time() + intval($token->expires));
				}
				
				$this->_set('token', $token);
				redirect($this->_strip_query());
			}
			
			return $this->_token_string();
		}
		
		private function _get($key)
		{
			$key = '_facebook_'.$key;
			
			if ( isset($this->_data[$key]) && $this->_data[$key] !== NULL) return $this->_data[$key];
			
			$data = $this->_obj->session->userdata($key);
			
			if ( !empty($data) )
			{
				$this->_set($key, $data);
			}
			
			return $data;
		}
		
		private function _set($key, $data)
		{
			$key = '_facebook_'.$key;
			
			$this->_data[$key] = $data;
			$this->_obj->session->set_userdata($key, $data);
		}
		
		private function _unset($key)
		{
			$key = '_facebook_'.$key;
			
			$this->_data[$key] = NULL;
			$this->_obj->session->unset_userdata($key);
		}
		
		public function set_callback($url = NULL)
		{
			$this->_set('callback', $this->_strip_query($url));
		}
		
		private function _token_string()
		{
			return 'access_token='.$this->_get('token')->access_token;
		}
		
		public function append_token($url)
		{
			if ( $this->_get('token') ) $url .= '?'.$this->_token_string();
			
			return $url;
		}
		
		private function _strip_query($url = NULL)
		{
			if ( $url === NULL )
			{
				$url = ( empty($_SERVER['HTTPS']) ) ? 'http' : 'https';
				$url .= '://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
			}
			
			$parts = explode('?', $url);
			return $parts[0];
		}
	}