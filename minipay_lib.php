<?php

/*
 * @author Momax GmbH
 * @copyright 2010
 * Script kann frei verwendet werden. Eine Haftung der Momax GmbH ist ausgeschlossen.
 */

define('MINPAY_LIB_VERSION', '1.6');



/*
 * DIESE FUNKTION UNTERSTÜTZT SIE BEIM GENERIEREN EINES MINIPAY-KAUF-BUTTONS ODER MINIPAY-IFRAMES
 * 
 * Erwartete Parameter:
 * 	 type (button_long || button_short || iframe)
 * 	 $oid (miniPay-Kundennummer des Anbieters)
 * 	 $sec (miniPay-Security-String des Anbieters)
 * 	 $params (Array mit Parametern als Key-Value-Paar. Beispiel: $params=array('price' => '0.99', 'tax' => '19.0', ...)
 * 
 * Folgende Werte gibt die Funktion in Abhängigkeit des Parameters "type" zurück:
 * 	 type = 'button_long': 	onclick-Attribut eines miniPay-Kauf-Buttons. Recht langer Code, aber alleine lauffähig (auch ohne referenzierte JavaScript-Datei
 * 	 type = 'button_short': onclick-Attribut eines miniPay-Kauf-Buttons. Kurzer Code, aber nur lauffähig, wenn miniPay-JavaScript-Datei bereits auf Site referenziert
 * 	 type = 'iframe':		src-Attribut eines miniPay-iFrames
 */

function getMinipayCall($type,$oid,$sec,$params){
		
		//Data-Param zusammensetzen
		$dataparam="sec=".md5($sec);
		foreach($params as $key=>$value){
			if($value!=null){
				$dataparam.="&".$key."=".urlencode($value);
			}
		}
		
		//Verschlüsselung durchführen
		$xtea = new XTEA($sec);
		$dataparam=$xtea->Encrypt($dataparam);
		
		switch ($type){
			case "button_long":
				$call="document.minipay_a=this;document.minipay_oid='$oid';document.minipay_data='$dataparam';if(typeof(minipay_load)=='undefined'){minipay_js=document.createElement('script');minipay_js.type='text/javascript';minipay_js.src='https://payment.sepa.net/capp/javascripts/minipay';document.getElementsByTagName('head')[0].appendChild(minipay_js);}else{minipay_open(document.minipay_a,document.minipay_oid,document.minipay_data);}return false;";
				break;
			case "button_short":
				$call="minipay_open(this,'$oid','$dataparam');return false;";
				break;
			case "iframe":
				$call="https://payment.sepa.net/capp/payments/start?oid=".$oid."&layout=extended&data=".urlencode($dataparam);
				break;
		}
		
		return $call;
	}





/*
 * DIE FOLGENDE FUNKTION PRÜFT DIE BERECHTIGUNG EINES USERS ZUM ABRUF EINES INHALTS (PermissionCheck)
 * Die Prüfung erfolgt anhand des Parameters PermissionChecks (optionaler, von miniPay "eingeschleuster" Bestandteil der Auslieferungs-URL). 
 * Die Prüfung kann sich auf eine Produkt-ID ($productID) und/oder die Auslieferungs-URL ($url) beziehen
 * (wird weder $productID noch $url als Parameter übergeben, liefert die Funktion in jedem Fall 'true' zurück)
 */
 
function checkMinipayPermission($permissionCheck,$securityString,$userIP,$timestamp,$productID=null,$url=null){
	
	$period=3700;	//wie lange nach Generierung soll PermissionCheck gültig sein? (in Sekunden)
	$back=false;
	
	//Entschlüsselung durchführen
	$xtea = new XTEA($securityString);
	$permissionCheck_decrypted=$xtea->Decrypt($permissionCheck);
	
	//Parameter in Array überführen
	$temp1=split('&',$permissionCheck_decrypted);
	foreach($temp1 as $key=>$value){
		$temp2=split('=',$value);
		if(count($temp2)==2){
			$params[$temp2[0]]=urldecode($temp2[1]);
		}
	}
	
	//Berechtigung des Users prüfen
	if(isset($params['sec']) && isset($params['ip']) && isset($params['time'])){
		if($params['sec']==md5($securityString) && $params['ip']==$userIP && $timestamp < ($params['time']+$period)){
			
			//Prüfung anhand Product-ID
			if(isset($productID) && isset($params['productId'])){
				if($params['productId']==$productID){$back=true;}
				else{$back=false;}
			}
			else {$back=true;}
			
			//Prüfung anhand Auslieferungs-URL
			if(isset($url) && isset($params['url'])){
				
				//Protokoll aus den zu vergleichenden Werten entfernen
				$allowedUrl=preg_replace('@^http://|https://@i','',$params['url']);
				$requestedUrl=preg_replace('@^http://|https://@i','',$url);
				
				//Platzhalter der $AllowedUrl ersetzen
				$allowedUrl=str_replace('*PermissionCheck*',urlencode($permissionCheck),$allowedUrl);
				
				//Übereinstimmung der URLs prüfen
				if(strtolower($allowedUrl)!=strtolower($requestedUrl)){$back=false;}
			}
		}
	}
	
	//Rückgabe der Funktion
	return $back;
}






/* PHP Implementation of XTEA (www.php-einfach.de)
 *
 * XTEA was designed in 1997 by David Wheeler and Roger Needham
 * of the Cambridge Computer Laboratory.
 * It is not subject to any patents.
 *
 * It is a 64-bit Feistel cipher, consisting of 64 rounds.
 * XTA has a key length of 128 bits.
 *
 *
 * ***********************
 * Diese Implementierung darf frei verwendet werden, der Autor uebernimmt keine
 * Haftung fuer die Richtigkeit, Fehlerfreiheit oder die Funktionsfaehigkeit dieses Scripts.
 * Benutzung auf eigene Gefahr. Über einen Link zu php-einfach würden wir uns freuen.
 * ************************
 *
 * Usage:
 * <?php
 * include("xtea.class.php");
 *
 * $xtea = new XTEA("secret Key");
 * $cipher = $xtea->Encrypt("Hello World"); //Encrypts 'Hello World'
 * $plain = $xtea->Decrypt($cipher); //Decrypts the cipher text
 * echo $plain;
 * ?>
 */


class XTEA {

   //Private
	var $key;

	// CBC or ECB Mode
	// normaly, CBC Mode would be the right choice
	var $cbc = 1;

   function XTEA($key) {
      $this->key_setup($key);
   }

   //Verschluesseln
   function encrypt($text) {
      $n = strlen($text);
      if($n%8 != 0) $lng = ($n+(8-($n%8)));
      else $lng = 0;

      $text = str_pad($text, $lng, ' ');
      $text = $this->_str2long($text);

      //Initialization vector: IV
      if($this->cbc == 1) {
         $cipher[0][0] = time();
         $cipher[0][1] = (double)microtime()*1000000;
      }

      $a = 1;
      for($i = 0; $i<count($text); $i+=2) {
         if($this->cbc == 1) {
            //$text mit letztem Geheimtext XOR Verknuepfen
            //$text is XORed with the previous ciphertext
            $text[$i] ^= $cipher[$a-1][0];
            $text[$i+1] ^= $cipher[$a-1][1];
         }

         $cipher[] = $this->block_encrypt($text[$i],$text[$i+1]);
         $a++;
      }

      $output = "";
      for($i = 0; $i<count($cipher); $i++) {
         $output .= $this->_long2str($cipher[$i][0]);
         $output .= $this->_long2str($cipher[$i][1]);
      }

      return base64_encode($output);
   }




   //Entschluesseln
   function decrypt($text) {
      $output="";
      $plain = array();
      $cipher = $this->_str2long(base64_decode($text));

      if($this->cbc == 1)
         $i = 2; //Message start at second block
      else
         $i = 0; //Message start at first block

      for($i; $i<count($cipher); $i+=2) {
         $return = $this->block_decrypt($cipher[$i],$cipher[$i+1]);

         //Xor Verknuepfung von $return und Geheimtext aus von den letzten beiden Bloecken
         //XORed $return with the previous ciphertext
         if($this->cbc == 1)
            $plain[] = array($return[0]^$cipher[$i-2],$return[1]^$cipher[$i-1]);
         else          //EBC Mode
            $plain[] = $return;
      }

      for($i = 0; $i<count($plain); $i++) {
         $output .= $this->_long2str($plain[$i][0]);
         $output .= $this->_long2str($plain[$i][1]);
      }

      return rtrim($output);
   }

   //Bereitet den Key zum ver/entschluesseln vor
   function key_setup($key) {
		if(is_array($key))
      	$this->key = $key;
		else if(isset($key) && !empty($key))
			$this->key = $this->_str2long(str_pad($key, 16, $key));
		else
			$this->key = array(0,0,0,0);
   }


	//Performs a benchmark
	function benchmark($length=1000) {
		//1000 Byte String
		$string = str_pad("", $length, "text");


		//Key-Setup
		$start1 = time() + (double)microtime();
		$xtea = new XTEA("key");
		$end1 = time() + (double)microtime();

		//Encryption
		$start2 = time() + (double)microtime();
		$xtea->Encrypt($string);
		$end2 = time() + (double)microtime();



		echo "Encrypting ".$length." bytes: ".round($end2-$start2,2)." seconds (".round($length/($end2-$start2),2)." bytes/second)<br>";


	}

	//verify the correct implementation of the blowfish algorithm
	function check_implementation() {

		$xtea = new XTEA("");
		$vectors = array(
			array(array(0x00000000,0x00000000,0x00000000,0x00000000), array(0x41414141,0x41414141), array(0xed23375a,0x821a8c2d)),
			array(array(0x00010203,0x04050607,0x08090a0b,0x0c0d0e0f), array(0x41424344,0x45464748), array(0x497df3d0,0x72612cb5)),

		);

		//Correct implementation?
		$correct = true;
		//Test vectors, see http://www.schneier.com/code/vectors.txt
		foreach($vectors AS $vector) {
      	$key = $vector[0];
			$plain = $vector[1];
			$cipher = $vector[2];

			$xtea->key_setup($key);
			$return = $xtea->block_encrypt($vector[1][0],$vector[1][1]);

			if((int)$return[0] != (int)$cipher[0] || (int)$return[1] != (int)$cipher[1])
				$correct = false;

		}

		return $correct;

	}



	/***********************************
			Some internal functions
	 ***********************************/
   function block_encrypt($y, $z) {
	   $sum=0;
	   $delta=0x9e3779b9;


	   /* start cycle */
	   for ($i=0; $i<32; $i++)
	      {
	      $y      = $this->_add($y,
	                        $this->_add($z << 4 ^ $this->_rshift($z, 5), $z) ^
	                            $this-> _add($sum, $this->key[$sum & 3]));

	      $sum    = $this->_add($sum, $delta);

	      $z      = $this->_add($z,
	                        $this->_add($y << 4 ^ $this->_rshift($y, 5), $y) ^
	                              $this->_add($sum, $this->key[$this->_rshift($sum, 11) & 3]));

	      }

	   /* end cycle */
	   $v[0]=$y;
	   $v[1]=$z;

	   return array($y,$z);

   }

   function block_decrypt($y, $z) {
	   $delta=0x9e3779b9;
	   $sum=0xC6EF3720;
	   $n=32;

	   /* start cycle */
	   for ($i=0; $i<32; $i++)
	      {
	      $z      = $this->_add($z,
	                	-($this->_add($y << 4 ^ $this->_rshift($y, 5), $y) ^
	                  	$this->_add($sum, $this->key[$this->_rshift($sum, 11) & 3])));
	      $sum    = $this->_add($sum, -$delta);
	      $y      = $this->_add($y,
	                          -($this->_add($z << 4 ^ $this->_rshift($z, 5), $z) ^
	                                    $this->_add($sum, $this->key[$sum & 3])));

	      }
	   /* end cycle */

	   return array($y,$z);
    }




  	function _rshift($integer, $n) {
        // convert to 32 bits
        if (0xffffffff < $integer || -0xffffffff > $integer) {
            $integer = fmod($integer, 0xffffffff + 1);
        }

        // convert to unsigned integer
        if (0x7fffffff < $integer) {
            $integer -= 0xffffffff + 1.0;
        } elseif (-0x80000000 > $integer) {
            $integer += 0xffffffff + 1.0;
        }

        // do right shift
        if (0 > $integer) {
            $integer &= 0x7fffffff;                     // remove sign bit before shift
            $integer >>= $n;                            // right shift
            $integer |= 1 << (31 - $n);                 // set shifted sign bit
        } else {
            $integer >>= $n;                            // use normal right shift
        }

        return $integer;
    }


    function _add($i1, $i2) {
        $result = 0.0;

        foreach (func_get_args() as $value) {
            // remove sign if necessary
            if (0.0 > $value) {
                $value -= 1.0 + 0xffffffff;
            }

            $result += $value;
        }

        // convert to 32 bits
        if (0xffffffff < $result || -0xffffffff > $result) {
            $result = fmod($result, 0xffffffff + 1);
        }

        // convert to signed integer
        if (0x7fffffff < $result) {
            $result -= 0xffffffff + 1.0;
        } elseif (-0x80000000 > $result) {
            $result += 0xffffffff + 1.0;
        }

        return $result;
    }


   //Einen Text in Longzahlen umwandeln
   //Covert a string into longinteger
   function _str2long($data) {
       $n = strlen($data);
       $tmp = unpack('N*', $data);
       $data_long = array();
       $j = 0;

       foreach ($tmp as $value) $data_long[$j++] = $value;
       return $data_long;
   }

   //Longzahlen in Text umwandeln
   //Convert a longinteger into a string
   function _long2str($l){
       return pack('N', $l);
   }

}

?>