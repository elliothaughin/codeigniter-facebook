<?php

	function facebook_xmlns()
	{
		return 'xmlns:fb="http://www.facebook.com/2008/fbml" xmlns:og="http://www.facebook.com/2008/fbml" xmlns:og="http://opengraphprotocol.org/schema/"';
	}
	
	function facebook_app_id()
	{
		$ci =& get_instance();
		
		return $ci->config->item('facebook_app_id');
	}
	
	function facebook_picture($who = 'me')
	{
		$ci =& get_instance();
		
		return $ci->facebook->append_token($ci->config->item('facebook_api_url').$who.'/picture');
	}
	
	function facebook_opengraph_meta($opengraph)
	{
		$ci =& get_instance();
		
		$return = '<meta property="fb:admins" content="'.$ci->config->item('facebook_admins').'" />';
		$return .= "\n";
		$return .= '<meta property="fb:app_id" content="'.$ci->config->item('facebook_app_id').'" />';
		$return .= "\n";
		$return .= '<meta property="og:site_name" content="'.$ci->config->item('facebook_site_name').'" />';
		$return .= "\n";	 
		
		foreach ( $opengraph as $key => $value )
		{
			$return .= '<meta property="og:'.$key.'" content="'.$value.'" />';
			$return .= "\n";
		}
		
		return $return;
	}