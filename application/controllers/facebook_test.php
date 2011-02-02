<?php
	class Facebook_test extends Controller {
		
		function __construct()
		{
			parent::Controller();
		}
		
		function index()
		{
			// This is the easiest way to keep your code up-to-date. Use git to checkout the 
			// codeigniter-facebook repo to a location outside of your site directory.
			// 
			// Add the 'application' directory from the repo as a package path:
			// $this->load->add_package_path('/var/www/haughin.com/codeigniter-facebook/application/');
			// 
			// Then when you want to grab a fresh copy of the code, you can just run a git pull 
			// on your codeigniter-facebook directory.

			$this->load->add_package_path('/Users/elliot/sites/github/codeigniter-facebook/application/');
			$this->load->library('facebook');

			if ( !$this->facebook->logged_in() )
			{
				// From now on, when we call login() or login_url(), the auth
				// will redirect back to this url.

				$this->facebook->set_callback(site_url('facebook_test/index'));

				// Header redirection to auth.

				$this->facebook->login();

				// You can alternatively create links in your HTML using
				// $this->facebook->login_url(); as the href parameter.
			}

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

			$this->load->view('facebook_view');
		}
	}