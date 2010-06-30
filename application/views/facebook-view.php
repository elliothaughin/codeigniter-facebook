<?php header('Content-type: text/html; charset=UTF-8'); ?>
<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" <?=facebook_xmlns()?>>
	<head>
		<title>Facebook Connect Example</title>
				
		<?php if (!empty($opengraph)):?>
			<?=facebook_opengraph_meta($opengraph);?>
		<?php endif;?>
	</head>
	<body>
		<div class="my-account">
		<?php if ( !$this->facebook_connect->get_user_id() ): ?>
			<fb:login-button v="2" autologoutlink="true" size="large"><fb:intl>Connect with Facebook</fb:intl></fb:login-button>
			<fb:facepile></fb:facepile>
		<?php else:?>
			<img class="avatar" src="<?=facebook_picture('me')?>" />
			<?php $user_info = $this->facebook_connect->get_me();?>
			<h2><?=$user_info['name']?></h2>
		<?php endif;?>
		</div>
		<?php if ( $this->facebook_connect->get_user_id() ):?>
			<fb:like></fb:like>
			<?php // Make an API call; ?>
			<?php $result = $this->facebook_connect->api('/me', array('metadata' => 1));?>
			<pre>
				<?php var_dump($result);?>
			</pre>
		<?php endif;?>
		<div id="fb-root"></div>
		<script src="http://connect.facebook.net/en_US/all.js" type="text/javascript"></script>
		<script type="text/javascript">
			FB.init({appId: '<?=facebook_app_id()?>', status: true, cookie: true, xfbml: true});
		</script>
	</body>
</html>