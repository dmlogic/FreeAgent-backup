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
 * 					$fa_url,
 * 					$fa_username,
 * 					$fa_password,
 * 					$fa_cookiefolder
 * 					);
 *
 * try {
 *
 * 		$FA->download_backup($filename,$download_folder);
 *
 * } catch(Exception $e) {
 *
 * 		echo 'Error: '.  $e->getMessage();
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
    private $settings = array(
        'url' => 'https://YOURDOMAIN.freeagent.com/',
        'username' => 'user@example.com',
        'password' => 'your-password',
        'cookiefolder' => './cookies/',
        'notify_email' => 'user@example.com',
        'nofity_on_success' => FALSE,
        'nofity_on_failure' => TRUE,
        'download_filename' => 'freeagent_backup.xls',
        'download_folder' => './downloads/',
        'zip_and_increment_backup' => TRUE
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
        $this->jar = tempnam($this->settings['cookiefolder'], "CURLCOOKIE");

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
    public function download_backup() {

        $this->submit_login_form();

        curl_setopt($this->ch, CURLOPT_POST, FALSE);
        curl_setopt($this->ch, CURLOPT_URL, $this->settings['url'] . 'company/export.xls');

        $file_contents = curl_exec($this->ch);

        if (FALSE === $file_contents) {
            throw new Exception('Could not download backup file');
        }

        // zip and increment
        if ($this->settings['zip_and_increment_backup']) {

            $zip = new ZipArchive();

            $zfilename = $this->settings['download_filename'] . ' - ' . date('Y-m-d H-i-s') . '.zip';

            if ($zip->open($this->settings['download_folder'] . $zfilename, ZIPARCHIVE::CREATE) !== TRUE) {
                throw new Exception('Cannot create ZIP file');
            }

            $zip->addFromString($this->settings['download_filename'], $file_contents);

            $zip->close();

            return $zfilename;

            // or save the file
        } else {

            $fso = fopen($this->settings['download_folder'] . $this->settings['download_filename'], 'w+');
            fwrite($fso, $file_contents);
            fclose($fso);
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
            throw new Exception('Login failed');
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
            throw new Exception('Could not get login token');
        }

        return $match[1];
    }

}