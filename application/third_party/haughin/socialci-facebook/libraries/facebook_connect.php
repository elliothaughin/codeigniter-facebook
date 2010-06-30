<?php
	
	// Check for the essentials
	
	if (!function_exists('curl_init')) {
	  throw new Exception('Facebook needs the CURL PHP extension.');
	}
	if (!function_exists('json_decode')) {
	  throw new Exception('Facebook needs the JSON PHP extension.');
	}

	class Facebook_Connect {
		
		private $_obj;
		private $_cookie = NULL;
		private $_me = NULL;
		private $_friends = array();
		
		private $_app_id;
		private $_api_secret;
		
		private static $_curl_opts = array(
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 60,
			CURLOPT_USERAGENT      => 'codeigniter-facebook-2.0'
		);
		
		private static $_drop_query_params = array(
			'session',
		);
		
		private static $_domain_map = array(
			'api'      => 'https://api.facebook.com/',
			'api_read' => 'https://api-read.facebook.com/',
			'graph'    => 'https://graph.facebook.com/',
			'www'      => 'https://www.facebook.com/',
		);
		
		function __construct()
		{
			$this->_obj =& get_instance();
			
			$this->_obj->load->config('facebook_connect');
			$this->_obj->load->helper('facebook_connect');
			
			$this->_app_id = $this->_obj->config->item('facebook_app_id');
			$this->_api_secret = $this->_obj->config->item('facebook_api_secret');
			
			$this->_cookie = $this->get_cookie();
		}
		
		public function get_domain_url($key)
		{
			if ( isset(self::$_domain_map[$key]) )
			{
				return self::$_domain_map[$key];
			}
			
			return NULL;
		}
		
		private function _get_me()
		{
			return $this->_me;
		}
		
		public function get_cookie()
		{
			if ( $this->_cookie !== NULL ) return $this->_cookie;
			
			$args = array();
			
			if ( !isset($_COOKIE['fbs_' . $this->_app_id]) ) return NULL;
			
			parse_str( trim($_COOKIE['fbs_' . $this->_app_id], '\\"'), $args );
			ksort($args);
			
			$payload = '';
			
			foreach ( $args as $key => $value )
			{
				if ( $key != 'sig' )
				{
					$payload .= $key . '=' . $value;
				}
			}
			
			if ( md5($payload . $this->_api_secret) != $args['sig'] )
			{
				return NULL;
			}
			
			$this->_cookie = $args;
			
			$me = $this->api('/me', array('metadata' => 1));
			
			if ( isset($me['error']) )
			{
				unset($_COOKIE['fbs_' . $this->_app_id]);
				return NULL;
			}
			
			$this->_me = $me;
			
			$friends = $this->api('/me/friends');
			
			if ( !empty($friends['data']) )
			{
				foreach ( $friends['data'] as $friend )
				{
					$this->_friends[$friend['id']] = $friend;
				}
			}
			
			return $args;
		}
	
		public function get_user_id()
		{
			$cookie = $this->get_cookie();
			return $cookie ? $cookie['uid'] : NULL;
		}
		
		public function get_me()
		{
			return $this->_get_me();
		}
		
		public function get_friends()
		{
			return $this->_friends;
		}

		public function api()
		{
			$args = func_get_args();
			
			if ( is_array($args[0]) )
			{
				return $this->_rest_server($args[0]);
			}
			else
			{
				if ( strpos($args[0], 'http') !== FALSE )
				{
					$parts = explode('/', $args[0]);
					
					unset($parts[0]);
					unset($parts[1]);
					unset($parts[2]);
					
					$args[0] = '/'.implode('/', $parts);
				}
				
				return call_user_func_array(array($this, '_graph'), $args);
			}
		}
		
		private function _rest_server($params)
		{
		    // generic application level parameters
			$params['api_key'] = $this->_app_id;
			$params['format'] = 'json';

			$result = json_decode($this->_oauth_request(
				$this->_get_api_url($params['method']),
				$params
			), true);
		
			// results are returned, errors are thrown
			if (isset($result['error_code']))
			{
				//die(var_dump($result));
			}
			
			return $result;
		}
		
		private function _graph($path, $method='GET', $params=array())
		{
			if (is_array($method) && empty($params))
			{
				$params = $method;
				$method = 'GET';
			}
		    
			$params['method'] = $method; // method override as we always do a POST
			
			$result = json_decode($this->_oauth_request(
				$this->_get_url('graph', $path),
				$params
			), true);
			
			
			// results are returned, errors are thrown
			if (isset($result['error']))
			{
				//die(var_dump($result['error']));
			}
		
			return $result;
		}
		
		private function _oauth_request($url, $params)
		{
			if (!isset($params['access_token']))
			{
				$cookie = $this->get_cookie();
				
				// either user session signed, or app signed
				
				if ($cookie)
				{
					$params['access_token'] = $cookie['access_token'];
				}
				else
				{
					// TODO (naitik) sync with abanker
					//$params['access_token'] = $this->getAppId() .'|'. $this->getApiSecret();
				}
			}
			
		    // json_encode all params values that are not strings
			foreach ($params as $key => $value)
			{
				if (!is_string($value))
				{
					$params[$key] = json_encode($value);
				}
			}
			
			return $this->_make_request($url, $params);
		}
		
		private function _make_request($url, $params, $ch = NULL)
		{
			if ( !$ch )
			{
				$ch = curl_init();
			}
			
			$opts = self::$_curl_opts;
			
			$opts[CURLOPT_POSTFIELDS] = http_build_query($params, null, '&');
			$opts[CURLOPT_URL] = $url;
			//$opts[CURLOPT_CAINFO] = APPPATH.'libraries/facebook_connect_certificate.crt';
			 
			curl_setopt_array($ch, $opts);
			
			$result = curl_exec($ch);
			curl_close($ch);
			
			return $result;
		}
		
		private function _get_api_url($method)
		{
			static $READ_ONLY_CALLS =
				array(
					'admin.getallocation' => 1,
	            	'admin.getappproperties' => 1,
					'admin.getbannedusers' => 1,
					'admin.getlivestreamvialink' => 1,
					'admin.getmetrics' => 1,
					'admin.getrestrictioninfo' => 1,
					'application.getpublicinfo' => 1,
					'auth.getapppublickey' => 1,
					'auth.getsession' => 1,
					'auth.getsignedpublicsessiondata' => 1,
					'comments.get' => 1,
					'connect.getunconnectedfriendscount' => 1,
					'dashboard.getactivity' => 1,
					'dashboard.getcount' => 1,
					'dashboard.getglobalnews' => 1,
					'dashboard.getnews' => 1,
					'dashboard.multigetcount' => 1,
					'dashboard.multigetnews' => 1,
					'data.getcookies' => 1,
					'events.get' => 1,
					'events.getmembers' => 1,
					'fbml.getcustomtags' => 1,
					'feed.getappfriendstories' => 1,
					'feed.getregisteredtemplatebundlebyid' => 1,
					'feed.getregisteredtemplatebundles' => 1,
					'fql.multiquery' => 1,
					'fql.query' => 1,
					'friends.arefriends' => 1,
					'friends.get' => 1,
					'friends.getappusers' => 1,
					'friends.getlists' => 1,
					'friends.getmutualfriends' => 1,
					'gifts.get' => 1,
					'groups.get' => 1,
					'groups.getmembers' => 1,
					'intl.gettranslations' => 1,
					'links.get' => 1,
					'notes.get' => 1,
					'notifications.get' => 1,
					'pages.getinfo' => 1,
					'pages.isadmin' => 1,
					'pages.isappadded' => 1,
					'pages.isfan' => 1,
					'permissions.checkavailableapiaccess' => 1,
					'permissions.checkgrantedapiaccess' => 1,
					'photos.get' => 1,
					'photos.getalbums' => 1,
					'photos.gettags' => 1,
					'profile.getinfo' => 1,
					'profile.getinfooptions' => 1,
					'stream.get' => 1,
					'stream.getcomments' => 1,
					'stream.getfilters' => 1,
					'users.getinfo' => 1,
					'users.getloggedinuser' => 1,
					'users.getstandardinfo' => 1,
					'users.hasapppermission' => 1,
					'users.isappuser' => 1,
					'users.isverified' => 1,
					'video.getuploadlimits' => 1
				);
				
			$name = 'api';
			
			if ( isset($READ_ONLY_CALLS[strtolower($method)]) )
			{
				$name = 'api_read';
			}
			
			return $this->_get_url($name, 'restserver.php');
		}
		
		private function _get_url($name, $path = '', $params=array())
		{
			$url = self::$_domain_map[$name];
			
			if ($path)
			{
				if ($path[0] === '/')
				{
					$path = substr($path, 1);
				}
				
				$url .= $path;
			}
			
			if ($params)
			{
				$url .= '?' . http_build_query($params);
			}
			
			return $url;
		}
	}