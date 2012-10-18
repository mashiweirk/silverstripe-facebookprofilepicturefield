<?php

/**
 * FacebookProfilePictureField
 *
 * Takes a Facebook profile picture and saves it to assets.
 * On its on this class is quite generic so can probably be repurposed in tandem
 * with RemoteUpload.
 *
 * @author Will Morgan, Better Brief (@willmorgan, @betterbrief)
 */
class FacebookProfilePictureField extends FormField {

	static
		$app_id,
		$app_secret;

	private static
		$cs_added;

	protected
		$relationAutoSetting = true,
		$valueField,
		$savesInto,
		$imageID,
		$upload;

	public
		$folderName;

	function __construct($name, $title = null, $value = null, $folderName = false, $savesInto = null, $form = null) {
		$this->savesInto = $savesInto ? $savesInto : $name;
		$this->folderName = $folderName;
		$this->valueField = new HiddenField($name.'[Value]', null, $value);
		$this->upload = new RemoteUpload();
		parent::__construct($name, $title, $value, $form);

		$fbParams = array(
			'appId' => self::$app_id,
			'status' => true,
			'cookie' => true,
		);
		Requirements::javascript('https://connect.facebook.net/en_US/all.js');
		// Hacking terribly
		if(!self::$cs_added) {
			Requirements::customScript("FB.init(".json_encode($fbParams).");");
			self::$cs_added = true;
		}

		// require JS includes
		Requirements::javascript(MOD_FPPF_DIR . '/javascript/javascript.js');
	}

	function setRelationAutoSetting($var) {
		$this->relationAutoSetting = (bool) $var;
	}

	function setValue($value) {
		if(is_array($value)) {
			$value = $value['Value'];
		}
		$this->valueField->setValue($value);
		$this->value = $value;
		$this->upload->setUrl($value);
	}

	function setRelationField($savesInto) {
		$this->savesInto = $savesInto;
	}

	function Field() {
		$content = $this->valueField->Field();
		$content .= $this->getConnectButtonHTML();
		return $content;
	}

	function getConnectButtonHTML() {
		$html = '<button id="'.$this->id().'" class="facebook-auth" data-update="'.$this->Link('update').'" data-for="'.$this->valueField->id().'">'.$this->Title().'</button>';
		return $html;
	}

	/**
	 * update
	 * Allows the frontend to update the value of the field on the fly
	 * Just validates for now, so kinda useless.
	 * @return string the JSON response
	 */
	function update(SS_HTTPRequest $request) {
		$value = $request->postVar('value');
		$this->setValue($value);
		if(!$this->upload->validate()) {
			return $this->respond(400, array('message' => 'Image invalid for ' . $value));
		}
		return $this->respond(200, array('filename' => $value));
	}

	/**
	 * respond
	 * helper function for update
	 */
	private function respond($code = 200, array $body = array()) {
		// pseudo-rest code weirdness - i blame jQuery!
		$body['code'] = $code;
		$response = new SS_HTTPResponse(Convert::array2json($body));
		$response->setStatusCode(200);
		$response->addHeader('Content-Type', 'application/json');
		return $response;
	}

	/**
	 * saveInto
	 * Used for saving the value in to a dataobject.
	 */
	function saveInto(DataObject $record) {

		$value = $this->value;

		if(empty($value)) {
			return false;
		}

		if($this->relationAutoSetting) {
			// assume that the file is connected via a has-one
			$hasOnes = $record->has_one($this->savesInto);
			// try to create a file matching the relation
			$file = (is_string($hasOnes)) ? Object::create($hasOnes) : new Image();
		}
		else {
			$file = new Image();
		}

		$this->upload->validator->setAllowedExtensions(array('jpg','gif','jpeg','png'));
		$this->upload->validator->setAllowedMaxFileSize(102400);

		$this->upload->loadIntoFile($this->value, $file, $this->folderName);

		if($this->upload->isError()) return false;

		$file = $this->upload->getFile();

		if($this->relationAutoSetting) {
			if(!$hasOnes) return false;

			// save to record
			$record->{$this->savesInto . 'ID'} = $file->ID;
		}
	}

	static function config_facebook($id, $secret) {
		self::$app_id = $id;
		self::$app_secret = $secret;
	}

}
