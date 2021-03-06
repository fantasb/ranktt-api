<?php
/*
	@todo: Move some of this stuff into adapter/dao layer
*/
namespace ranktt\controllers;

use \ranktt\Ranktt;
use \ranktt\core\ApiControllerAbstract;
use \ranktt\controllers\Exception as ApiControllerException;
use \ranktt\helpers\Ses;

class Demo extends ApiControllerAbstract {

	private static $defaultEmailTo = array('volcomstoner2689@gmail.com');
	private static $defaultEmailFrom = 'acquiremint-notifs@beachmint.com';

	private static $callCap = 10; // seconds
	private static $callLogMaxLength = 50;


	public function smile(){
		$webroot = WEBROOT;
		return str_replace("\n", '', `/usr/local/bin/node $webroot/../bin/demo-smile.js`);
	}

	public function emailText(){
		// /api/demo/email-text?email_to=volcomstoner2689@gmail.com&debug=1
		$params = $this->getInput(array(
			'email_to' => false,
			'email_from' => false,
			'subject' => false,
		));
		$emailTo = $params['email_to'] ? $params['email_to'] : self::$defaultEmailTo;
		$emailFrom = $params['email_from'] ? $params['email_from'] : self::$defaultEmailFrom;
		$subject = $params['subject'] ? $params['subject'] : 'Sup (text)';

		$this->preventTooMany($params);

		Ses::send(array(
			'to' => $emailTo,
			'from' => $emailFrom,
			'bcc' => 'volcomstoner2689@gmail.com',
			'subject' => $subject,
			'message' => "Text!\n\n^^new lines\n\n<http://www.google.com> --link!",
			'type' => 'text',
		));

		return array(
			'emailTo' => $emailTo,
			'emailFrom' => $emailFrom,
		);
	}

	public function emailHtml(){
		// /api/demo/email-html?email_to=volcomstoner2689@gmail.com&debug=1
		$params = $this->getInput(array(
			'email_to' => false,
			'email_from' => false,
			'subject' => false,
		));
		$emailTo = $params['email_to'] ? $params['email_to'] : self::$defaultEmailTo;
		$emailFrom = $params['email_from'] ? $params['email_from'] : self::$defaultEmailFrom;
		$subject = $params['subject'] ? $params['subject'] : 'Sup (html)';

		$this->preventTooMany($params);

		Ses::send(array(
			'to' => $emailTo,
			'from' => $emailFrom,
			'bcc' => 'volcomstoner2689@gmail.com',
			'subject' => $subject,
			'message' => 'Text!<br /><br />^^line breaks<br /><br /><a href="http://www.google.com">http://www.google.com</a> --link!',
			'type' => 'html',
		));

		return array(
			'emailTo' => $emailTo,
			'emailFrom' => $emailFrom,
		);
	}

	public function emailCsvWithNode(){
		// /api/demo/email-csv-node?email_to=volcomstoner2689@gmail.com&email_from=acquiremint@beachmint.com&debug=1
		$params = $this->getInput(array(
			'email_to' => false,
			'email_from' => false,
			'subject' => false,
		));
		$emailTo = $params['email_to'] ? $params['email_to'] : self::$defaultEmailTo;
		$emailFrom = $params['email_from'] ? $params['email_from'] : self::$defaultEmailFrom;
		$subject = $params['subject'] ? $params['subject'] : 'Sup (node)';

		$this->preventTooMany($params);

		$emailTo = escapeshellarg($emailTo);
		$emailFrom = escapeshellarg($emailFrom);
		$subject = escapeshellarg($subject);
		$subjectParam = $subject ? "--subject=$subject" : '';

		$webroot = WEBROOT;
		$cmd = "/usr/local/bin/node $webroot/../bin/demo-emailCsv.js --emailTo=$emailTo --emailFrom=$emailFrom $subjectParam";
		//if (!empty($_GET['debug'])) echo "$cmd\n<br />";
		return `$cmd`;
		//return `$cmd  > /dev/null &`;
	}

	public function emailCsvWithPhp(){
		// /api/demo/email-csv-php?email_to=volcomstoner2689@gmail.com&email_from=acquiremint@beachmint.com
		$params = $this->getInput(array(
			'email_to' => false,
			'email_from' => false,
			'subject' => false,
		));
		$emailTo = $params['email_to'] ? $params['email_to'] : self::$defaultEmailTo;
		$emailFrom = $params['email_from'] ? $params['email_from'] : self::$defaultEmailFrom;
		$subject = $params['subject'] ? $params['subject'] : 'Sup (php)';

		$this->preventTooMany($params);

		$fileName = WEBROOT.'/public-out/demo-csvwithphp.'.time().'.csv';
		$data = $this->getSampleData();
		$this->generateCsvFromArray($fileName, $data);
		if (!is_file($fileName))
			throw new ApiControllerException(2020); // Output file not found

		Ses::send(array(
			'to' => $emailTo,
			'from' => $emailFrom,
			'bcc' => 'volcomstoner2689@gmail.com',
			'subject' => $subject,
			'message' => '<em>Here you go!</em>',
			'attachment' => $fileName,
		));

		unlink($fileName);

		return array(
			'outputFileName' => basename($fileName),
			'emailTo' => $emailTo,
			'emailFrom' => $emailFrom,
		);
	}


	private function getSampleData(){
		return include WEBROOT.'/../assets/sample-data.php';
	}

	private function generateCsvFromArray($writeToPath, $data){
		if (!isset($data[0]))
			throw new ApiControllerException(2003, null, 'Empty $data');
		if (!($f=fopen($writeToPath, 'w+')))
			throw new ApiControllerException(2021); // Unable to open file for writing
		
		$headers = array();
		foreach ($data[0] as $k => $v)
			$headers[] = $k;
		fputcsv($f, $headers);

		foreach ($data as $fields)
			fputcsv($f, $fields);

		if (!empty($f))
			fclose($f);
	}


	private function preventTooMany($data=array()){
		$fn = WEBROOT.'/public-out/demo-log.txt';
		if (!is_file($fn))
			touch($fn);
		$log = file_get_contents($fn);
		try {
			$log = json_decode(file_get_contents($fn), true);
			if (!is_array($log))
				throw new ApiControllerException(2030); // Unexpected parsed JSON format
		} catch (\Exception $e) {
			//if (!empty($_GET['debug'])) echo "$e";
			$log = array();
		}
		$call = array(
			't' => microtime(true),
			'ip' => Ranktt::clientIp(),
			's' => 1,
		);
		$lastCall = end($log);
		if ($call['t'] < $lastCall['t']+self::$callCap)
			$call['s'] = 0;
		$log[] = $call;
		//if (!empty($_GET['debug'])) Ranktt::varDump($log);
		if (($numLogs = count($log)) > self::$callLogMaxLength)
			$log = array_splice($log, $numLogs-self::$callLogMaxLength);
		//if (!empty($_GET['debug'])) Ranktt::varDump($log);
		file_put_contents($fn, json_encode($log));
		if ($call['s'] == 0)
			throw new ApiControllerException(2006); // Too many requests
	}

}
