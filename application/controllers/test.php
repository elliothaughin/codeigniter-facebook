<?php
	class Test extends Controller {
		
		function __construct()
		{
			parent::Controller();
			
			$this->load->add_package_path(APPPATH.'third_party/haughin/codeigniter-facebook/');
			$this->load->library('facebook_connect');
		}
		
		function index()
		{
			// We can use the open graph place meta data in the head.
			// This meta data will be used to create a facebook page automatically
			// when we 'like' the page.
			// 
			// For more details see: http://developers.facebook.com/docs/opengraph
			
			
			$opengraph = 	array(
								'type'				=> 'website',
								'title'				=> 'My Awesome Site',
								'url'				=> site_url(),
								'image'				=> '',
								'description'		=> 'The best site in the whole world'
							);
			
			$this->load->vars('opengraph', $opengraph);
			
			$this->load->view('facebook-view');
		}
	}