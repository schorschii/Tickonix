<?php
require_once('loader.inc.php');

session_name('TICKONIX_SESSID_'.md5(__DIR__));
session_start();

$info = null;
$infoclass = null;

// execute login if requested
if(isset($_POST['username']) && isset($_POST['password'])) {
	if(!empty(ADMIN_USERNAME) && !empty(ADMIN_PASSWORD)
	&& strtolower(trim($_POST['username'])) === strtolower(trim(ADMIN_USERNAME))
	&& $_POST['password'] === ADMIN_PASSWORD) {
		$_SESSION['login'] = ADMIN_USERNAME;
		$_SESSION['installation'] = dirname(__FILE__);
		header('Location: admin.php');
		die('Rap braucht wieder einen Märchen-Erzähler.');
	} else {
		sleep(2);
		$info = LANG('login_failed');
		$infoclass = 'red';
	}
}
// logout if requested
elseif(isset($_GET['logout'])) {
	if(isset($_SESSION['login'])) {
		session_destroy();
		$info = LANG('logout_successful');
		$infoclass = 'green';
	}
}
// check if already logged in
elseif(isset($_SESSION['login'])) {
	header('Location: admin.php');
	die();
}
?>

<!DOCTYPE html>
<html>
<head>
	<?php require_once('head.inc.php'); ?>
	<title><?php echo LANG('login_admin_area'); ?> | Tickonix</title>
</head>
<body>
	<div id='container'>
		<div id='splash'>
			<h1><?php echo LANG('login_admin_area'); ?></h1>
			<p>
				<?php echo LANG('login_admin_area_description'); ?>
			</p>

			<?php if($info != null) { ?>
				<div class='infobox <?php echo $infoclass; ?>'><?php echo $info; ?></div>
			<?php } ?>

			<form method='POST' class='login-flex' onsubmit='txtUsername.readOnly=true; txtPassword.readOnly=true; btnSubmit.disabled=true;'>
				<input type='text' id='txtUsername' name='username' placeholder='<?php echo LANG('username'); ?>' class='flex-fill' required='true' autofocus='true'>
				<input type='password' id='txtPassword' name='password' placeholder='<?php echo LANG('password'); ?>' class='flex-fill' required='true'>
				<button id='btnSubmit'><?php echo LANG('login'); ?></button>
			</form>
		</div>
	</div>
</body>
</html>
