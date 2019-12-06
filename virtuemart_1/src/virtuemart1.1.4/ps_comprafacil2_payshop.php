<?php
if( !defined( '_VALID_MOS' ) && !defined( '_JEXEC' ) ) die( 'Direct Access to '.basename(__FILE__).' is not allowed.' );
/**
*
* @version $Id: ps_payment.php 1095 2007-12-19 20:19:16Z soeren_nb $
* @package VirtueMart
* @subpackage payment
* @copyright Copyright (C) 2004-2007 soeren - All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
* VirtueMart is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
*
* http://virtuemart.net
*/


/**
*
* The ps_payment class, containing the default payment processing code
* for payment methods that have no own class
*
*/

require_once('nusoap/lib/nusoap.php');

class ps_comprafacil2_payshop {

	var $payment_code = "CFPS";
    var $classname = "ps_comprafacil2_payshop";
  
    /**
    * Show all configuration parameters for this payment method
    * @returns boolean False when the Payment method has no configration
    */
    function show_configuration() {
      
	  global $VM_LANG, $mosConfig_live_site;
      //$db =& new ps_DB;
      /** Read current Configuration ***/
      require_once(CLASSPATH ."payment/".$this->classname.".cfg.php");
	  ?>
	    <table class="adminform" border = "0">
		<tr class="row0">
			<td  class="labelcell" colspan="2">CompraF&aacute;cil - PayShop<br></td>
		</tr>
        <tr class="row1">
            <td class="labelcell" valign="top">Username</td>
            <td valign="top">
                <input type="text" name="CF_USERNAME" class="inputbox" value="<?php echo CF_USERNAME ?>" />
            </td>
            <td>Username fornecido pela Himedia</td>
        </tr>
		<tr class="row0">
            <td class="labelcell" valign="top">Password</td>
            <td valign="top">
                <input type="password" name="CF_PASSWORD" class="inputbox" value="<?php echo CF_PASSWORD ?>" />
            </td>
            <td>Password fornecida pela Himedia</td>
        </tr>
		<tr class="row1">
            <td class="labelcell" valign="top">Origem</td>
            <td valign="top">
                <input type="text" name="CF_ORIG" class="inputbox" value="<?php echo CF_ORIG ?>" />
            </td>
            <td>Campo origem a colocar nas encomendas. Se a funcionalidade de callback de um URL estiver activa,
			ent&atilde;o dever&aacute; ser especificado um URL como par&acirc;metro. Para especificar o n&uacute;mero da encomenda usar o par&acirc;metro <b>orderID</b> e a tag <b>[ORDERNUM]</b> ao construir o URL. Exemplo:
			
			<br><br>
			http://www.exemplo.com/CFCallback.php?orderID=[ORDERNUM]


        </tr>		
		<tr class="row0">
            <td class="labelcell" valign="top">Entidade</td>
            <td valign="top">
                <select name="CF_ENTIDADE" class="inputbox" >
                <option <?php if (CF_ENTIDADE == '10241') echo "selected=\"selected\""; ?> value="10241">10241</option>
                <option <?php if (CF_ENTIDADE == '11249') echo "selected=\"selected\""; ?> value="11249">11249</option>
                </select>
            </td>
            <td>Entidade a utilizar. (<b>nota:</b> cada entidade utiliza um ambiente CompraF&aacute;cil diferente, portanto dados de acesso diferentes)</td>
        </tr>
        <tr class="row1">
            <td class="labelcell" valign="top">Modo de teste?<!--<?php echo $VM_LANG->_('PHPSHOP_ADMIN_CFG_ENABLE_AUTORIZENET_TESTMODE') ?>--></td>
            <td valign="top">
                <select name="CF_TEST_MODE" class="inputbox" >
                <option <?php if (CF_TEST_MODE == '1') echo "selected=\"selected\""; ?> value="1"><?php echo $VM_LANG->_('PHPSHOP_ADMIN_CFG_YES') ?></option>
                <option <?php if (CF_TEST_MODE == '0') echo "selected=\"selected\""; ?> value="0"><?php echo $VM_LANG->_('PHPSHOP_ADMIN_CFG_NO') ?></option>
                </select>
            </td>
            <td>Se sim, as refer&ecirc;ncias s&atilde;o geradas no ambiente de teste, caso contr&aacute;rio s&atilde;o geradas no ambiente real. (<b>nota:</b> os dados de acesso para o ambiente de testes e real podem ser diferentes)
            </td>
        </tr>	
		<tr class="row0">
            <td class="labelcell" valign="top">Chave encripta&ccedil;&atilde;o</td>
            <td valign="top">
                <input type="text" name="CF_ENCRYPTKEY" class="inputbox" value="<?php echo CF_ENCRYPTKEY ?>" />
            </td>
            <td>Chave de encripta&ccedil;&atilde;o usada para encriptar o n&uacute;mero de encomenda que ser&aacute; passado ao CompraF&aacute;cil, caso a funcionalidade de Callback esteja activa. (<b>nota:</b> esta chave deve ser tamb&eacute;m colocada no ficheiro de callback utilizado)</td>
        </tr>
		
		</table>
	  <?php
    }
    
    function has_configuration() {
      // return false if there's no configuration
      return true;
   }
   
  /**
	* Returns the "is_writeable" status of the configuration file
	* @param void
	* @returns boolean True when the configuration file is writeable, false when not
	*/
   function configfile_writeable() {
      return is_writeable( CLASSPATH."payment/".$this->classname.".cfg.php" );
   }
   
  /**
	* Returns the "is_readable" status of the configuration file
	* @param void
	* @returns boolean True when the configuration file is writeable, false when not
	*/
   function configfile_readable() {
      return is_readable( CLASSPATH."payment/".$this->classname.".cfg.php" );
   }
   
  /**
	* Writes the configuration file for this payment method
	* @param array An array of objects
	* @returns boolean True when writing was successful
	*/
   function write_configuration( &$d ) {
      global $vmLogger;
      
      $my_config_array = array("CF_USERNAME" => $d['CF_USERNAME'],
                               "CF_PASSWORD" => $d['CF_PASSWORD'],
							   "CF_ENTIDADE" => $d['CF_ENTIDADE'],
								"CF_TEST_MODE" => $d['CF_TEST_MODE'],
								"CF_ORIG" => $d['CF_ORIG'],
								"CF_ENCRYPTKEY" => $d['CF_ENCRYPTKEY']
                         );
      $config = "<?php\n";
      $config .= "if( !defined( '_VALID_MOS' ) && !defined( '_JEXEC' ) ) die( 'Direct Access to '.basename(__FILE__).' is not allowed.' ); \n\n";
      foreach( $my_config_array as $key => $value ) {
        $config .= "define ('$key', '$value');\n";
      }
      
      $config .= "?>";
  
      if ($fp = fopen(CLASSPATH ."payment/".$this->classname.".cfg.php", "w")) {
          fputs($fp, $config, strlen($config));
          fclose ($fp);
          return true;
     }
     else {
        $vmLogger->err( "Could not write to configuration file ".CLASSPATH ."payment/".$this->classname.".cfg.php" );
        return false;
     }
   }
   
  /**************************************************************************
  ** name: process_payment()
  ** returns: 
  ***************************************************************************/
   function process_payment($order_number, $order_total, &$d) {

		//echo CF_TEST_MODE;
		//echo CF_USERNAME;
		
		//$user =& JFactory::getUser();
		$user = & $_SESSION["__default"]["user"]; //JUser object
		$email = $user->get('email');
		
		$res = $this->getReferenceFromWebService($order_total, $order_number, $email, $reference, $datelimit, $value, $error);
		
		if($res){
			$infoPagamento = " (data lim.: ".$datelimit." ref: ".$reference." val: ".$value." euros) ";
		
			$d["customer_note"] = $infoPagamento .$d["customer_note"];
			$d["order_payment_log"] .= $infoPagamento;
			$_SESSION['cf_reference'] = $reference;
			$_SESSION['cf_datelimit'] = $datelimit;
			$_SESSION['cf_value'] = $value;
			
		
		}
		else{
			$d["order_payment_log"] .= "error: ".$error;
		}
		
		return $res;
		
   }
   
   function getReferenceFromWebService($total, $order_number, $email, &$reference, &$datelimit, &$value, &$error){
		
		require_once(CLASSPATH ."payment/".$this->classname.".cfg.php");

		try 
		{
	

			// cortar os ultimos 3 digitos à order_number... parece que na BD esta assim...
			//$order_number2 = substr($order_number, 0, strlen($order_number)-3);
			$order_number2 = $order_number;

			// Specify the webservice information
			$action='CompraFacilPS/SaveCompraToBDValor2';
	
			$wsURL = "";
			if( CF_TEST_MODE=='1' && CF_ENTIDADE=='10241'  ){
				$wsURL = "https://hm.comprafacil.pt/SIBSClickTeste/webservice/CompraFacilPS.asmx?WSDL";
			}
			else if ( CF_TEST_MODE=='0' && CF_ENTIDADE=='10241'  ){
				$wsURL = "https://hm.comprafacil.pt/SIBSClick/webservice/CompraFacilPS.asmx?WSDL";
			}
			else if ( CF_TEST_MODE=='1' && CF_ENTIDADE=='11249'  ){
				$wsURL = "https://hm.comprafacil.pt/SIBSClick2Teste/webservice/CompraFacilPS.asmx?WSDL";
			}
			else { //CF_TEST_MODE=='0' && CF_ENTIDADE=='11249'
				$wsURL = "https://hm.comprafacil.pt/SIBSClick2/webservice/CompraFacilPS.asmx?WSDL";
			}
			
			$orderNumberTag = '[ORDERNUM]';
			
			//descobrir se a string Origem tem a tag para o numero da encomenda
			// e se sim, substituir
			$origem = str_replace($orderNumberTag, urlencode(base64_encode($this->convert($order_number,CF_ENCRYPTKEY))), CF_ORIG);   
		
			
			$this->getUserInfo($username, $first_name, $last_name);
		
			$client = new soapclient($wsURL);
			
			$soap='<SaveCompraToBDValor2 xmlns="CompraFacilPS">';
			$soap = $soap.'<origem>'.$origem.'</origem>';
			$soap = $soap.'<IDCliente>'.CF_USERNAME.'</IDCliente>';
			$soap = $soap.'<password>'.CF_PASSWORD.'</password>';
			$soap = $soap.'<valor>'.number_format($total, 2).'</valor>';
			$soap = $soap.'<informacao>username: '.$username.'</informacao>';
			$soap = $soap.'<nome>'.$first_name . " " . $last_name.'</nome>';
			$soap = $soap.'<morada></morada>';
			$soap = $soap.'<codPostal></codPostal>';
			$soap = $soap.'<localidade></localidade>';
			$soap = $soap.'<NIF></NIF>';
			$soap = $soap.'<RefExterna>order id: ' . $order_number2.'</RefExterna>';
			$soap = $soap.'<telefoneContacto></telefoneContacto>';
			$soap = $soap.'<email>'.$email.'</email>';
			$soap = $soap.'<IDUserBackoffice>-1</IDUserBackoffice>';
			$soap = $soap.'</SaveCompraToBDValor2>';
			
			$msg=$client->serializeEnvelope($soap,'',array(),'document', 'literal');

			// Send the SOAP message
			$response = $client->send($msg,$action);

			// Save the SOAP response result to a variable
			$res=$response['SaveCompraToBDValor2Result'];
	
			//Save the SOAP response result values to the corresponding variables 


			if($res = true)
			{
				$reference=$response['referencia'];
				$datelimit=$response['dataLimitePagamento'];
				$value=$response['valorOut'];
				$error=$response['error'];

				return true;
			}
			else
			{
				$error=$response['error'];
				return false;
			}

		}
		catch (Exception $e){
			$error = $e->getMessage();
			return false;
		}
		
   }
   
   function getUserInfo(&$username, &$first_name, &$last_name){
		$username = "";
		$first_name = "";
		$last_name = "";
		
		if(isset($_SESSION["auth"]))
		{
			if (isset($_SESSION["auth"]["username"]))
				$username = $_SESSION["auth"]["username"];
			else
				$username = "?";
			
			if (isset($_SESSION["auth"]["first_name"]))
				$first_name = $_SESSION["auth"]["first_name"];
			else
				$first_name = "";
			
			if (isset($_SESSION["auth"]["last_name"]))
				$last_name = $_SESSION["auth"]["last_name"];
			else
				$last_name = "?";

		}
   }
   
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
  
   
}
