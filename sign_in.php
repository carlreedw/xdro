<?php
require_once str_replace("temp" . DIRECTORY_SEPARATOR, "", APP_PATH_TEMP) . "redcap_connect.php";
$fa_path = APP_PATH_WEBROOT . "Resources/css/fontawesome/css/all.css";
$pid = $module->getProjectId();
session_start();

// $module->llog("_GET: " . print_r($_GET, true));
// $module->llog("_POST: " . print_r($_POST, true));
// $module->llog("_REQUEST: " . print_r($_REQUEST, true));
// $module->llog("_SESSION: " . print_r($_SESSION, true));

if (isset($_POST['sign-in'])) {
	$module->authenticateUser();
	if ($module->userIsAuthenticated())
		header("location: " . $module->getUrl('patient_search.php') . "&NOAUTH");
}

if (isset($_GET['logout'])) {
	$_SESSION = [];
	$module->signInAlertMessage = "You were successfully logged out";
}

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
	<head>
		<meta http-equiv="content-type" content="text/html; charset=UTF-8">
		<title>ARIES | REDCap</title>
		<meta name="googlebot" content="noindex, noarchive, nofollow, nosnippet">
		<meta name="robots" content="noindex, noarchive, nofollow">
		<meta name="slurp" content="noindex, noarchive, nofollow, noodp, noydir">
		<meta name="msnbot" content="noindex, noarchive, nofollow, noodp">
		<meta http-equiv="Cache-Control" content="no-cache">
		<meta http-equiv="Pragma" content="no-cache">
		<meta http-equiv="expires" content="0">
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		
		<!-- font awesome icons -->
		<link rel="stylesheet" href="<?=$fa_path?>">
		<link rel="stylesheet" href="<?=$module->getUrl('css/sign_in.css')?>"/>
		
		<!--[if IE 9]>
		<link rel="stylesheet" type="text/css" href="/redcap/redcap_v9.5.14/Resources/css/bootstrap-ie9.min.css">
		<script type="text/javascript">$(function(){ie9FormFix()});</script>
		<!--<![endif]-->
	</head>
	<body>
	
	<!-- jquery 3.4 -->
	<script
	  src="https://code.jquery.com/jquery-3.4.1.min.js"
	  integrity="sha256-CSXorXvZcTkaix6Yvo6HppcZGetbYMGWSFlBw8HfCJo="
	  crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>

	<!-- bootstrap 4.4 -->
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">
	<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js" integrity="sha384-wfSDF2E50Y2D1uUdj0O3uMBJnjuUD4Ih7YwaYd1iqfktj0Uod8GCExl3Og8ifwB6" crossorigin="anonymous"></script>
	
	<div class="main container">
		<div class="card">
			<div class="card-header">
				<div class='logo'>
					<span id='aries-title'>aries</span>
					<img id='tdh-logo' src="<?=$module->getUrl('res/tdh-logo.png')?>"></img>
				</div>
				<div id='registry-title'>
					<h1>Antibiotic Resistance Information Exchange System</h1>
				</div>
			</div>
			
			<div class="card-body">
				<?php
				// check if we were redirected from resource because unauthorized
				if ($message = $module->getSignInMessage()) {?>
					<div class="alert alert-primary" role="alert">
						<span id='errmsg'><?php echo $message; ?></span>
					</div>
				<?php
				}
				?>
				<h5 class="card-title">User Sign-In</h5>
				<form action="<?php echo $module->getUrl('sign_in.php') . "&NOAUTH"; ?>" method="post">
					<div class="input-group mb-3">
						<div class="input-group-prepend">
							<span class="input-group-text">Username</span>
						</div>
						<input name="username" type="text" class="form-control" placeholder="Username" aria-label="Username" aria-describedby="username">
					</div>
					<div class="input-group mb-3">
						<div class="input-group-prepend">
							<span class="input-group-text">Password</span>
						</div>
						<input name="password" type="password" class="form-control" placeholder="Password" aria-label="Password" aria-describedby="password">
					</div>
					
					<button type="submit" name="sign-in" class="btn btn-primary mb-2">Sign In</button>
				</form>
				<br>
				<a href="#" data-toggle="modal" data-target="#forgot" class="card-link">Forgot your password?</a>
			</div>
		</div>
	</div>
	
	
	<div id="forgot" class="modal" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Forgot your password?</h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<div class="modal-body">
					<p>Enter your username or email address in the input below.</p>
					<div class="input-group mb-3">
						<input type="text" class="form-control" aria-label="user-or-email">
					</div>
					<button class="btn btn-primary" type="button">Submit</button>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
				</div>
			</div>
		</div>
	</div>
	
	
	</body>
</html>

