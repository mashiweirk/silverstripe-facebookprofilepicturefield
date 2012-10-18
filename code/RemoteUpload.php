<?php

/**
 * RemoteUpload
 *
 * Grabs files via cURL and puts them into the file system.
 * Basically a lazy adapter around Upload
 * Lots to add but does the job for now.
 *
 * @author Will Morgan, Better Brief (@willmorgan, @betterbrief)
 */

class RemoteUpload extends Upload {

	static $allowed_actions = array();

	protected $url;

	/**
	 * __construct
	 * @param string $url the url to use
	 */
	public function __construct($url = null) {
		parent::__construct();
		$this->validator = new RemoteUpload_Validator();
		if($url) {
			$this->setUrl($url);
		}
	}

	/**
	 * setUrl
	 * Sets the URL to a supported scheme. If https isn't enabled for PHP,
	 * it compensates and swaps to http instead.
	 * @param string $url
	 */
	function setUrl($url) {
		if(!self::supports_https()) {
			$url = str_replace('https://', 'http://', $url);
		}
		$this->url = $url;
		$this->validator->setUrl($url);
	}

	/**
	 * supports_https
	 * On some PHP installations, https isn't enabled. Work around this.
	 * @return boolean
	 */
	static function supports_https() {
		$wrappers = stream_get_wrappers();
		return in_array('https', $wrappers);
	}

	/**
	 * load
	 * Wraparound for Upload::load - downloads the file with cURL then imitates an upload
	 */
	function load($url, $folderPath = false) {
		$this->setUrl($url);

		// Validate early as we can tell if the file is legit from URL + HEAD request
		$this->validate();

		$downloadTemp = TEMP_FOLDER . '/' . basename($this->url);

		// Download the file
		$ch = curl_init($this->url);
		$fh = fopen($downloadTemp, 'wb');

		curl_setopt_array($ch, array(
			CURLOPT_HEADER => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_BINARYTRANSFER => true,
			CURLOPT_FILE => $fh,
		));
		curl_exec($ch);
		curl_close($ch);

		fclose($fh);

		// Check locally if all is good - getimagesize returns false if not an image
		$imageMeta = getimagesize($downloadTemp);

		// rm the file and terminate if not an image
		if($imageMeta === false) {
			@unlink($downloadTemp);
			return false;
		}

		// Yay for spoofing
		$tmpFile = array(
			'name' => basename($downloadTemp),
			'tmp_name' => str_replace('\\', '/', $downloadTemp), // unify directory separator
			'size' => filesize($downloadTemp),
			'type' => $imageMeta['mime'],
			'error' => 0,
		);

		// Validate is called again in parent::load
		parent::load($tmpFile, $folderPath);

		// Success! But if we get here, parent::load doesn't load, it copies
		// so remove the temporary file
		@unlink($downloadTemp);
	}

	function loadIntoFile($url, File $file, $folderPath = false) {
		$this->file = $file;
		$this->load($url, $folderPath);
	}

	function validate($url = null) {
		if(!$url) {
			$url = $this->url;
		}
		$this->setUrl($url);
		$validator = $this->validator;
		$isValid = $validator->validate();
		if($validator->getErrors()) {
			$this->errors = array_merge($this->errors, $validator->getErrors());
		}
		return $isValid;
	}

}

class RemoteUpload_Validator extends Upload_Validator {

	protected
		$url,
		$headers = array();

	function setUrl($url) {
		$this->url = $url;
	}

	/**
	 * getHeaders
	 * Makes a HEAD request to retrieve headers for the set URL
	 * Caches this call locally
	 * @return array headers
	 */
	function getHeaders() {
		if(empty($this->headers)) {
			$ch = curl_init($this->url);
			curl_setopt_array($ch, array(
				CURLOPT_NOBODY => true,
				CURLOPT_CUSTOMREQUEST => 'HEAD',
				CURLOPT_HEADER => true,
				CURLOPT_RETURNTRANSFER => true,
			));
			$response = curl_exec($ch);
			$rawHeaders = explode("\n", trim($response));
			$processedHeaders = array();
			foreach($rawHeaders as $i => $header) {
				$pos = strpos($header, ':');
				if($pos === false) {
					$processedHeaders[$i] = $header;
					continue;
				}
				else {
					$name = substr($header, 0, $pos);
					$value = trim(substr($header, $pos), " :\n\r");
					$processedHeaders[$name] = $value;
				}
			}
			$this->headers = $processedHeaders;
		}
		return $this->headers;
	}

	public function isValidExtension() {
		$pathInfo = pathinfo($this->url);

		// Special case for filenames without an extension
		if(!isset($pathInfo['extension'])) {
			return in_array('', $this->allowedExtensions, true);
		} else {
			return (!count($this->allowedExtensions) || in_array(strtolower($pathInfo['extension']), $this->allowedExtensions));
		}
	}


	public function isValidSize() {
		$headers = $this->getHeaders();
		return $headers['Content-Length'] <= $this->getAllowedMaxFileSize();
	}

	function is200OK() {
		$headers = $this->getHeaders();
		$validCodes = array(200, 304);
		$matches = array();

		// Check HTTP status code
		preg_match('/(\d{3})/', $headers[0], $matches);

		if(empty($matches)) {
			return false;
		}
		return in_array($matches[0], $validCodes);
	}

	function validate() {
		if(empty($this->url)) {
			return true;
		}
		if(!$this->is200OK()) {
			$this->errors[] = 'The URL specified is invalid';
			return false;
		}
		return parent::validate();
	}


}
