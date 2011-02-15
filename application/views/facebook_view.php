<?php header('Content-type: text/html; charset=UTF-8'); ?>
<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" <?php echo facebook_xmlns()?>>
	<head>
		<title>Facebook Connect Example</title>
		
		<style type="text/css">

		body {
		 background-color: #fff;
		 margin: 40px;
		 font-family: Lucida Grande, Verdana, Sans-serif;
		 font-size: 14px;
		 color: #4F5155;
		}

		a {
		 color: #003399;
		 background-color: transparent;
		 font-weight: normal;
		}

		h1 {
		 color: #444;
		 background-color: transparent;
		 border-bottom: 1px solid #D0D0D0;
		 font-size: 16px;
		 font-weight: bold;
		 margin: 24px 0 2px 0;
		 padding: 5px 0 6px 0;
		}

		code {
		 font-family: Monaco, Verdana, Sans-serif;
		 font-size: 12px;
		 background-color: #f9f9f9;
		 border: 1px solid #D0D0D0;
		 color: #002166;
		 display: block;
		 margin: 14px 0 14px 0;
		 padding: 12px 10px 12px 10px;
		}

		</style>
		
		<?php if (!empty($opengraph)):?>
			<?php echo facebook_opengraph_meta($opengraph);?>
		<?php endif;?>
	</head>
	<body>
		<div class="my-account">
		<?php if ( !$this->facebook->logged_in() ): ?>
			<a href="<?php echo $this->facebook->login_url()?>">Login</a>
			<fb:facepile></fb:facepile>
		<?php else:?>
			<img class="avatar" src="<?php echo facebook_picture('me')?>" />
			<?php $user = $this->facebook->user();?>
			<h2><?php echo $user->name?> ( <a href="<?php echo site_url('facebook_test/logout')?>">Logout</a> )</h2>
			<fb:like></fb:like>
			<?php $result = $this->facebook->call('get', 'me', array('metadata' => 1));?>
			<pre>
				<?php var_dump($result);?>
			</pre>
		<?php endif;?>
		</div>
		<div id="fb-root"></div>
		<script src="http://connect.facebook.net/en_US/all.js" type="text/javascript"></script>
		<script type="text/javascript">
			FB.init({appId: '<?php echo facebook_app_id()?>', status: true, cookie: true, xfbml: true});
			FB.Event.subscribe('auth.login', function(response) {
				window.location.reload();
			});
		</script>
	</body>
</html>