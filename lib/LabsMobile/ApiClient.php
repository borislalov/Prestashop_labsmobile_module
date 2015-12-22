<?php

class LabsMobileApiClient
{

	const GATEWAY_URL = 'https://api.labsmobile.com/get/send.php';

    const BALANCE_URL = 'https://api.labsmobile.com/get/balance.php';

	const NET_ERROR = "Network+error,+unable+to+send+the+message";

	private $username;
	private $password;

	/**
	 *
	 * @param string $username        	
	 * @param string $password        	
	 */
	public function setCredentials($username, $password)
	{
		$this->username = $username;
		$this->password = $password;
	}

	/**
	 *
	 * @param unknown $recipients        	
	 * @param unknown $text
	 * @param string $sender
	 * @param string $user_reference        	
	 * @param string $charset        	
	 * @param string $optional_headers        	
	 * @return unknown
	 */
	public function sendSMS($recipients, $text, $sender = '', $user_reference = '', $optional_headers = null)
	{
		if (!is_array($recipients)) {
			$recipients = array(
				$recipients
			);
		}
		
		$parameters = '?username=' . urlencode($this->username) . '&' . 'password=' . urlencode($this->password) .
			 '&' . 'message=' . urlencode($text) . '&' . 'msisdn=' . implode(',', $recipients);
		
		$parameters .= $sender != '' ? '&sender=' . urlencode($sender) : '';
		$parameters .= $user_reference != '' ? '&subid=' . urlencode($user_reference) : '';

        $result = $this->doGetRequest(self::GATEWAY_URL . $parameters, $optional_headers);

        if(stripos($result, "xml") !== FALSE){
            $result_xml = new SimpleXMLElement($result);
            return array("failed" => (string)$result_xml->code, 'prompt' => (string)$result_xml->message);
        } else {
            return array("failed" => 30, 'prompt' => (string)$result);
        }
	}

	public function getGatewayCredit()
	{
		$parameters = '?username=' . urlencode($this->username) . '&' . 'password=' .
			 urlencode($this->password);

        $result = $this->doGetRequest(self::BALANCE_URL.$parameters);

        $result = new SimpleXMLElement($result);
        if(isset($result->messages)){
            return array("messages" => $result->messages);
        } else {
            return array("failed" => 30, 'prompt' => 'There was an error while sending the message');
        }
	}

	private function doGetRequest($url, $optional_headers = null)
	{
		if (! function_exists('curl_init')) {
			$params = array(
				'http' => array(
					'method' => 'GET'
				)
			);
			if ($optional_headers !== null) {
				$params['http']['header'] = $optional_headers;
			}
			$ctx = stream_context_create($params);
			$fp = @fopen($url, 'rb', false, $ctx);
			if (! $fp) {
				return 'failed=30&prompt=' . self::NET_ERROR;
			}
			$response = @stream_get_contents($fp);
			if ($response === false) {
				return 'failed=30&prompt=' . self::NET_ERROR;
			}
			return $response;
		}
		else {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 60);
			curl_setopt($ch, CURLOPT_USERAGENT, 'Generic Client');
			curl_setopt($ch, CURLOPT_URL, $url);
			
			if ($optional_headers !== null) {
				curl_setopt($ch, CURLOPT_HTTPHEADER, $optional_headers);
			}
			
			$response = curl_exec($ch);
			curl_close($ch);
			return $response;
		}
	}
}