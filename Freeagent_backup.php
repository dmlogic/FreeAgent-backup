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
 * @version 0.1
 *
 * Usage:
 *
 * <?php
 * require_once('Freeagent_backup.php');
 *
 * $FA = new Freeagent(
 *					$fa_url,
 *					$fa_username,
 *					$fa_password,
 *					$fa_cookiefolder
 *					);
 *
 * try {
 *
 * 		$FA->download_backup($filename,$download_folder);
 *
 * } catch(Exception $e) {
 *
 *		echo 'Error: ',  $e->getMessage();
 *
 * }
 *
 * echo 'Success!';
 *
 * ?>
 *
 * Be sure to add trailing slashes to all folder paths
 */
class Freeagent_backup {

	private $ch;

	private $jar;

	private $url;

	private $username;

	private $password;

	// -----------------------------------------------------------------

	/**
	 * constructor
	 *
	 * @param string $url : e.g. 'https://YOURCOMPANY.freeagent.com/'
	 * @param string $username : your account login name/email
	 * @param string $password : your account password
	 * @param string $cookiefolder : path to directory to store cookies
	 */
	function __construct($url,$username,$password,$cookiefolder) {

		// pass on variables
		$this->url = $url;
		$this->username = $username;
		$this->password = $password;

		// make a cookie file
		$this->jar = tempnam($cookiefolder, "CURLCOOKIE");

		// get cURL ready
		$this->ch = curl_init();
		curl_setopt($this->ch, CURLOPT_HEADER, FALSE);
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($this->ch,CURLOPT_USERAGENT,$_SERVER['HTTP_USER_AGENT']);
		curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($this->ch, CURLOPT_COOKIESESSION, FALSE);
		curl_setopt($this->ch, CURLOPT_COOKIEJAR, $this->jar);
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
	 * @param string $filename : filename to save to
	 * @param type $folder :  folder to save to
	 * @param type $zip_and_date : if TRUE will zip up and increment by date
	 * @return boolean
	 */
	public function download_backup($filename,$folder,$zip_and_date = TRUE) {

		$this->submit_login_form();

		curl_setopt($this->ch, CURLOPT_POST, FALSE);
		curl_setopt($this->ch, CURLOPT_URL, $this->url.'company/export.xls' );

		$file_contents = curl_exec($this->ch);

		if(FALSE === $file_contents) {
			throw new Exception('Could not download backup file');
		}

		// zip and increment
		if($zip_and_date) {

			$zip = new ZipArchive();

			$zfilename = $filename.' - '.date('Y-m-d H-i-s').'.zip';

			if ($zip->open($folder.$zfilename, ZIPARCHIVE::CREATE)!==TRUE) {
				throw new Exception('Cannot create ZIP file');
			}

			$zip->addFromString($filename, $file_contents);

			$zip->close();

			return $zfilename;

		// or save the file
		} else {

			$fso = fopen($folder.$filename,'w+');
			fwrite($fso,$file_contents);
			fclose($fso);
		}

	}

	// -----------------------------------------------------------------

	/**
	 * submit_login_form
	 *
	 * @return boolean
	 */
	private function submit_login_form() {

		$token = $this->get_login_token();

		$pdata = 'authenticity_token='.$token.'&email='.$this->username.'&password='.$this->password;

		curl_setopt ($this->ch, CURLOPT_COOKIEFILE, $this->jar);
		curl_setopt($this->ch, CURLOPT_POST, TRUE);
		curl_setopt($this->ch, CURLOPT_URL, $this->url.'sessions' );
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, $pdata);

		curl_exec($this->ch);

		$result = curl_getinfo($this->ch);

		// we're looking for a redirect to indicate success
		if($result['http_code'] != 302) {
			throw new Exception('Login failed');
		}

	}

	// -----------------------------------------------------------------

	/**
	 * get_login_token
	 *
	 * Parse the login form for a token and return it, or false
	 *
	 * @return mixed
	 */
	private function get_login_token() {

		curl_setopt($this->ch, CURLOPT_URL, $this->url.'login' );
		$form = curl_exec($this->ch);

		if(!preg_match('/name="authenticity_token" type="hidden" value="([^"]+)/', $form, $match)) {
			throw new Exception('Could not get login token');
		}

		return $match[1];
	}
}