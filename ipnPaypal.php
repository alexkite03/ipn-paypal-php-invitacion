<?php
$mysql = new mysqli("", "", "", "");
$raw_post_data = file_get_contents('php://input');
$raw_post_array = explode('&', $raw_post_data);
$myPost = array();
foreach ($raw_post_array as $keyval) {
  $keyval = explode ('=', $keyval);
  if (count($keyval) == 2)
    $myPost[$keyval[0]] = urldecode($keyval[1]);
}

$req = 'cmd=_notify-validate';
if (function_exists('get_magic_quotes_gpc')) {
  $get_magic_quotes_exists = true;
}
foreach ($myPost as $key => $value) {
  if ($get_magic_quotes_exists == true && get_magic_quotes_gpc() == 1) {
    $value = urlencode(stripslashes($value));
  } else {
    $value = urlencode($value);
  }
  $req .= "&$key=$value";
}

$ch = curl_init('https://www.paypal.com/cgi-bin/webscr');
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));

if ( !($res = curl_exec($ch)) ) {
  // error_log("Got " . curl_error($ch) . " when processing IPN data");
  curl_close($ch);
  exit;
}
curl_close($ch);

if (strcmp ($res, "VERIFIED") == 0) {
	$item_number = $_POST['item_number'];
	$payment_status = $_POST['payment_status'];
	$payment_amount = $_POST['mc_gross'];
	$txn_id = $_POST['txn_id'];
	$receiver_email = $_POST['receiver_email'];
	$payer_email = $_POST['payer_email'];
	if($payment_status === "Completed" && $receiver_email == "emailrecibedinero@gmail.com"){
		if($sql = $mysql->query("SELECT * FROM transacciones WHERE ID = '".$txn_id."';")){
			if($sql->num_rows == 0){
				if($item_number == "InvitacionPaypal"){
					$codigo = generarInvitacion();
					$mysql->query("INSERT INTO transacciones VALUES ('".$txn_id."', '".$payer_email."', '".$payment_amount."', '".$payment_status."', 'Invitacion', '".$codigo."');");
					$body = '<html>
					<head>
						<title>Código de registro para XXXXX</title>
					</head>
					<body>
						<p>Aquí tienes tu código para registrarte en xxxxxx.</p>
						<p>Código de registro: '.$codigo.'</p>
						<p>Los códigos de registro tienen una validez de 5 días.</p>
						<p>
							<strong>Enlace para registrarse en XXXXX</strong><br>
							<a href="https://tuweb.com/registro?invitacion='.$codigo.'"> Registrarse </a>
						</p>
					</body>
					</html>';
					enviarEmail($payer_email, $body, "Código de registro para XXXXX");
				} else{
					$mysql->query("INSERT INTO transacciones VALUES ('".$txn_id."', '".$payer_email."', '".$payment_amount."', '".$payment_status."', 'Donacion', NULL);");
					$body = '<html>
					<head>
						<title>Gracias por tu donación</title>
					</head>
					<body>
						<p>¡Hemos recibido una donación de tu parte!</p></br></br>
						<p>Muchas gracias por ayudarnos a mantener los servidores.</p>
						<p>Nuestro servicio no podría continuar en funcionamiento sin vuestra ayuda.</p></br></br>
						<p>Un saludo,</p>
						<p>Administración de XXXXX</p>
					</body>
					</html>';
					enviarEmail($payer_email, $body, "Gracias por tu donación");
				}
			} else{
				$body = '<html>
					<head>
						<title>Ha ocurrido un error con la donación</title>
					</head>
					<body>
						<p>Ha ocurrido un error con tu donación.</p>
						<p>Si crees que esta donación ha sido realizada con exito, responde a este email con los datos de la donación,<br>
						como el email y la id de la transacción.</p>
						<p>Disculpe las molestias,</p>
						<p>Administración de XXXXX</p>
					</body>
					</html>';
				enviarEmail($payer_email, $body, "Ha ocurrido un error con la donación");
				$mysql->query("INSERT INTO transacciones VALUES ('".$txn_id."', '".$payer_email."', '".$payment_amount."', '".$payment_status."', 'Transaccion duplicada', NULL);");
			}
		} else{
			$body = '<html>
					<head>
						<title>Ha ocurrido un error con la donación</title>
					</head>
					<body>
						<p>Ha ocurrido un error con tu donación.</p>
						<p>Si crees que esta donación ha sido realizada con exito, responde a este email con los datos de la donación,<br>
						como el email y la id de la transacción.</p>
						<p>Disculpe las molestias,</p>
						<p>Administración de XXXXX</p>
					</body>
					</html>';
			enviarEmail($payer_email, $body, "Ha ocurrido un error con la donación");
			$mysql->query("INSERT INTO transacciones VALUES ('".$txn_id."', '".$payer_email."', '".$payment_amount."', '".$payment_status."', 'Error SQL', NULL);");
		}
	} else{
		$mysql->query("INSERT INTO transacciones VALUES ('".$txn_id."', '".$payer_email."', '".$payment_amount."', '".$payment_status."', 'No completado', NULL);");
	}
} else if (strcmp ($res, "INVALID") == 0) {
}

function enviarEmail($email, $mensaje, $asunto){
		
	date_default_timezone_set('Etc/UTC');
	require ("../lib/phpmailer/class.phpmailer.php");
	require ("../lib/phpmailer/class.smtp.php");
			
	$mail = new PHPMailer;
	$mail->isHTML(true);
	$mail->isSMTP();     
	$mail->SetLanguage = "es";	
	$mail->SMTPDebug = 0;
	$mail->Debugoutput = 'html';
	$mail->Host = 'smtp.gmail.com';
	$mail->Port = 587;
	$mail->SMTPSecure = 'tls';
	$mail->SMTPAuth = true;	
	$mail->Username = "tuemail@gmail.com";
	$mail->Password = "password";
	$mail->setFrom('tuemail@gmail.com', 'nombre');
	$mail->addAddress(''.$email.'');
	$mail->Subject = $asunto;
	$mail->Body    = $mensaje;

	$mail->send();	
}

function generarInvitacion(){
		
	$cadena = rand(10000,999999);
	$cadena = randomString(5).$cadena.randomString(5);
	$token = md5($cadena);
		
	$conexion = new mysqli('', '', '', '');

	$sql = "INSERT INTO `generador` (`validado`, `codigo`) VALUES (0,'".$token."')";

	$resultado = $conexion->query($sql);
	if($resultado){
		$enlace = $token;
		return $enlace;	
	} else	{
		return 'error';	
	}
}

function randomString($length) {
	
	$str = "";
	$characters = array_merge(range('A','Z'), range('a','z'), range('0','9'));
	$max = count($characters) - 1;
	for ($i = 0; $i < $length; $i++) {
		$rand = mt_rand(0, $max);
		$str .= $characters[$rand];
	}
	return $str;
}
?>