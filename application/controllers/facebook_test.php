<?php
	class Test extends Controller {
		
		function __construct()
		{
			parent::Controller();
		}
		
		function index()
		{
			// We can use the open graph place meta data in the head.
			// This meta data will be used to create a facebook page automatically
			// when we 'like' the page.
			// 
			// For more details see: http://developers.facebook.com/docs/opengraph
			
			$this->load->library('facebook');
			
			$opengraph = 	array(
								'type'				=> 'website',
								'title'				=> 'My Awesome Site',
								'url'				=> site_url(),
								'image'				=> '',
								'description'		=> 'The best site in the whole world'
							);
			
			$this->load->vars('opengraph', $opengraph);
			
			$this->load->view('facebook_view');
		}
	}