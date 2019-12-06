<?php 

    require_once 'configuration.php';
	
	
	//exemplo de um order number no virtueMart:
	// 63_3b99f8349700696c692c9f1c1b302
	
	$newOrderStatus = 'C';
	
	// Secret key to encrypt/decrypt with // 8-32 characters without spaces 
	$key='!eo9$/fo99'; 
	
	//obter informacao do CF: orderNumber e a referência paga; 
	$orderID = $_GET["orderID"];
	$ref = $_GET["ref"];
	
	//descodificar a orderID
	$orderID = convert(base64_decode(urldecode($orderID)),$key); 
	

	$jconfig = new JConfig();

	echo "<br>orderID: ".$orderID;
	echo "<br>ref: ".$ref."<br>";
	
	//db establish
	$db_error = "Erro a aceder à BD....";
	$db_config = mysql_connect( $jconfig->host, $jconfig->user, $jconfig->password ) or die( $db_error );
	mysql_select_db( $jconfig->db, $db_config ) or die( $db_error );

	//query de pesquisa;
	// a orderID tem de estar na BD e além disso no campo customer_note tem de estar a referência, pois esta é guardada na submissão da encomenda
	// a ref obtida não deve ser vazia
	$query = "SELECT count(*) FROM ".$jconfig->dbprefix."vm_orders WHERE order_number = '$orderID' and customer_note like '%$ref%' and not '$ref'='' ";
	
	$query_execute = mysql_query($query);
	$result = mysql_fetch_row($query_execute);
	
	$total = $result[0];
	
	echo "<br>total=" .$total;

	if($total==0)
	{
		echo "<br>Encomenda não existe.";
		header("Status: 500 Server Error"); 
		mysql_close($db_config);//andy:close db for security reason
		throw new Exception("Encomenda não existe.");
	}
	else
	{
		echo "<br>Encomenda existe!!";
		//db query result
		$query = "UPDATE ".$jconfig->dbprefix."vm_orders SET order_status = '$newOrderStatus' WHERE order_number = '$orderID'";
		$query_execute = mysql_query($query);
	}
	

	echo "<br>Pedido processado.";
	
	mysql_close($db_config);//andy:close db for security reason
	
	

function convert($str,$ky=''){ 
if($ky=='')return $str; 
$ky=str_replace(chr(32),'',$ky); 
if(strlen($ky)<8)exit('key error'); 
$kl=strlen($ky)<32?strlen($ky):32; 
$k=array();for($i=0;$i<$kl;$i++){ 
$k[$i]=ord($ky{$i})&0x1F;} 
$j=0;for($i=0;$i<strlen($str);$i++){ 
$e=ord($str{$i}); 
$str{$i}=$e&0xE0?chr($e^$k[$j]):chr($e); 
$j++;$j=$j==$kl?0:$j;} 
return $str; 
} 


?>