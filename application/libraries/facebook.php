<?php

	class facebook {
		
		private $_api_url;
		private $_api_key;
		private $_api_secret;
		private $_errors = array();
		
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
		
		public function user()
		{
			return $this->session->get();
		}
		
		public function call($uri)
		{
			$response = FALSE;
			
			try
			{
				$response = $this->connection->get($this->append_token($this->_api_url.$uri));
			}
			catch (facebookException $e)
			{
				$this->_errors[] = $e;
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
			}

			foreach($result as $k => $v)
			{
				$this->$k = $v;
			}

			if ( $name === '_result')
			{
				return $result;
			}

			return $result[$name];
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
		private $_token 		= NULL;
		private $_token_url 	= 'oauth/access_token';
		private $_user_url		= 'me';
		private $_token_key 	= 'facebook_token';
		private $_callback_key 	= 'facebook_callback';
		private $_user_key 		= 'facebook_user';
		private $_user 			= NULL;
		
		function __construct()
		{
			$this->_obj =& get_instance();
			
			$this->_api_key 	= $this->_obj->config->item('facebook_app_id');
			$this->_api_secret 	= $this->_obj->config->item('facebook_api_secret');
			
			$this->_token_url 	= $this->_obj->config->item('facebook_api_url').$this->_token_url;
			$this->_user_url 	= $this->_obj->config->item('facebook_api_url').$this->_user_url;
			$this->_scope 		= $this->_obj->config->item('facebook_default_scope');
			
			$this->connection = new facebookConnection();
			
			$this->logged_in();
		}
		
		public function logged_in()
		{
			return ( $this->get() === NULL ) ? FALSE : TRUE;
		}
		
		public function logout()
		{
			$this->_unset_token();
		}
		
		public function login_url($scope = NULL)
		{
			$url = "http://www.facebook.com/dialog/oauth?client_id=".$this->_api_key.'&redirect_uri='.urlencode(current_url());
			
			if ( empty($scope) )
			{
				$scope = $this->_scope;
				
				if ( !empty($scope) )
				{
					$url .= '&scope='.$scope;
				}
			}
			
			return $url;
		}
		
		public function login($scope = NULL)
		{
			$this->logout();
			
			$url = $this->login_url($scope);
			$this->_obj->session->set_userdata($this->_callback_key, $this->_strip_query());
			
			return redirect($url);
		}
		
		private function _get_callback()
		{
			$callback_url = $this->_obj->session->userdata($this->_callback_key);
			
			if ( empty($callback_url) )
			{
				$callback_url = $this->_strip_query();
			}
			
			return $callback_url;
		}
		
		public function get()
		{
			$token = $this->_get_token();
			if ( empty($token) ) return NULL;
			
			// $user = $this->_obj->session->userdata($this->_user_key);
			// if ( !empty($user) ) return $user;
			
			try 
			{
				$user = $this->connection->get($this->_user_url.'?'.$token);
			}
			catch ( facebookException $e )
			{
				$this->logout();
				return NULL;
			}
			
			$this->_set_user($user->__resp->data);
			return $this->_user;
		}
		
		private function _get_token()
		{
			if ( $this->_token !== NULL ) return $this->_token;
			
			$token = $this->_obj->session->userdata($this->_token_key);
			
			if ( !empty($token) )
			{
				$this->_token = $token;
				return $this->_token;
			}
			
			if ( !isset($_GET['code']) )
			{
				$this->logout();
				return NULL;
			}
			
			$callback_url = $this->_get_callback();
			$token_url = $this->_token_url.'?client_id='.$this->_api_key."&redirect_uri=".urlencode($callback_url)."&client_secret=".$this->_api_secret."&code=".$_GET['code'];
			
			try 
			{
				$token = $this->connection->get($token_url);
			}
			catch ( facebookException $e )
			{
				$this->logout();
				return NULL;
			}
			
			if ( $token->access_token )
			{
				$token_string = 'access_token='.$token->access_token;
				$this->_set_token($token_string);
				
				redirect($this->_strip_query());
			}
			
			return $this->_token;
		}
		
		private function _set_token($token = NULL)
		{
			if ( $token !== NULL )
			{
				$this->_token = $token;
				$this->_obj->session->set_userdata($this->_token_key, $token);
			}
		}
		
		public function append_token($url)
		{
			if ( $this->_get_token() ) $url .= '?'.$this->_get_token();
			
			return $url;
		}
		
		private function _unset_token()
		{
			$this->_token = NULL;
			$this->_obj->session->unset_userdata($this->_token_key);
			$this->_obj->session->unset_userdata($this->_callback_key);
			
			$this->_unset_user();
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
		
		
		private function _set_user($user = NULL)
		{
			if ( $user !== NULL )
			{
				$this->_user = $user;
				$this->_obj->session->set_userdata($this->_user_key, $user);
			}
		}
		
		private function _unset_user()
		{
			$this->_user = NULL;
			$this->_obj->session->unset_userdata($this->_user_key);
		}
	}