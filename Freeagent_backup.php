<?php
/**
 * Freeagent_backup
 *
 * Logs into your FreeAgent account and downloads an Excel file
 * of your data as backup.
 *
 * @author Darren Miller <darren@dmlogic.com>
 * @link http://dmlogic.com
 * @license Licensed under the Open Software License version 3.0
 * @version 0.3
 *
 * Usage:
 *
 *	require_once('Freeagent_backup.php');
 *
 *	$settings = array(
 *				'url' => 'https://YOURDOMAIN.freeagent.com/',
 *				'username' => 'user@example.com',
 *				'password' => 'your-password',
 *				'notify_email' => 'user@example.com',
 *				'notify_on_success' => FALSE,
 *				'notify_on_failure' => TRUE,
 *			);
 *
 *	$FA = new Freeagent_backup($settings);
 *
 *	try {
 *
 *		$FA->instigate_backup();
 *		echo 'Success!';
 *
 *	} catch(Exception $e) {
 *
 *		echo 'Error: '.  $e->getMessage();
 *	}
 *
 * Be sure to add trailing slashes to all folder paths
 */
class Freeagent_backup {

	private $ch;

	private $jar;

	private $settings = array(
          'url' => 'https://YOURDOMAIN.freeagent.com/',
          'username' => 'user@example.com',
          'password' => 'your-password',
          'notify_email' => 'user@example.com',
          'notify_on_success' => FALSE,
          'notify_on_failure' => TRUE,
    );

	// -----------------------------------------------------------------

	/**
	 * constructor
	 *
	 * @param array $settings any or all of the settings above
	 */
	function __construct($settings = array()) {

		// pass on variables
		$this->settings = array_merge($this->settings, $settings);

		// make a cookie file
		$this->jar = tempnam(sys_get_temp_dir(), 'FAC');

		// get cURL ready
		$this->ch = curl_init();
		curl_setopt($this->ch, CURLOPT_HEADER, FALSE);
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($this->ch, CURLOPT_COOKIESESSION, FALSE);
		curl_setopt($this->ch, CURLOPT_COOKIEJAR, $this->jar);

		// not available when running from command line
		if (isset($_SERVER['HTTP_USER_AGENT'])) {
			curl_setopt($this->ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
		}
	}

	// -----------------------------------------------------------------

	/**
	 * destructor
	 *
	 * removes cookie files from recent session
	 *
	 */
	function __destruct() {
		unlink($this->jar);
	}

	// -----------------------------------------------------------------

	/**
	 * download_backup
	 *
	 * Perform the download
	 *
	 */
	public function instigate_backup() {

		$this->submit_login_form();

		curl_setopt($this->ch, CURLOPT_POST, FALSE);
		curl_setopt($this->ch, CURLOPT_URL, $this->settings['url'] . 'company/export.xls');

		$file_contents = curl_exec($this->ch);
		$result = curl_getinfo($this->ch);

		if ($result['http_code'] != 302) {
            $this->fail('Could not instigate backup');
        }

        if ($this->settings['notify_on_success']) {

         $this->send_mail('FreeAgent backup script completed',
                          'The FreeAgent backup completed at %s');
     }
 }

	// -----------------------------------------------------------------

	/**
	 * submit_login_form
	 *
	 * Perform a POST submit in order to authenticate and set
	 * session cookie
	 *
	 */
	private function submit_login_form() {

		$token = $this->get_login_token();

		$pdata = 'authenticity_token=' . $token . '&email=' . $this->settings['username'] . '&password=' . $this->settings['password'];

		curl_setopt($this->ch, CURLOPT_COOKIEFILE, $this->jar);
		curl_setopt($this->ch, CURLOPT_POST, TRUE);
		curl_setopt($this->ch, CURLOPT_URL, $this->settings['url'] . 'sessions');
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, $pdata);

		curl_exec($this->ch);

		$result = curl_getinfo($this->ch);

		// we're looking for a redirect to indicate success
		if ($result['http_code'] != 302) {
			$this->fail('Login failed');
		}
	}

	// -----------------------------------------------------------------

	/**
	 * get_login_token
	 *
	 * Parse the login form for a token and return it
	 *
	 * @return string
	 */
	private function get_login_token() {

		curl_setopt($this->ch, CURLOPT_URL, $this->settings['url'] . 'login');
		$form = curl_exec($this->ch);

		if (!preg_match('/name="authenticity_token" type="hidden" value="([^"]+)/', $form, $match)) {
			$this->fail('Could not get login token');
		}

		return $match[1];
	}

	// -----------------------------------------------------------------

	/**
	 * fail
	 *
	 * Throw an exception and note the failure for later
	 *
	 * @param string $message
	 * @throws Exception
	 */
	private function fail($message) {

		if ($this->settings['notify_on_failure']) {

			$this->send_mail('FreeAgent backup script FAILED',
                              "The FreeAgent backup script failed at %s with the error:\r\n$message");
		}

		throw new Exception($message);
	}

	// -----------------------------------------------------------------

	/**
	 * send_mail
	 *
	 * @param string $title
	 * @param string $message
	 */
	private function send_mail($title,$message) {

		$mail_headers = 'From: ' . $this->settings['notify_email'] . "\r\n" .
                        'Reply-To: ' . $this->settings['notify_email'] . "\r\n" .
                        'X-Mailer: PHP/' . phpversion();

        $now = date('Y-m-d H:i:s');

        $message = sprintf($message,$now);

        mail($this->settings['notify_email'],
             $title,
             $message,
             $mail_headers);
  }

}