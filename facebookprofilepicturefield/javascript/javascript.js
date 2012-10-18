(function($) {

	function setFacebookPhotoField(field, picture, updateURL) {
		field.val(picture);

		var xhr = $.post(updateURL, { value: picture }, null, 'json');

		xhr.done(function(response) {
			if(response.code == 200) {
				alert('uploaded image ' + response.filename);
			}
		});

		xhr.fail(function() {
			console.error(arguments);
		});

	}

	function login(field, updateURL) {
		FB.login(function(response) {
			if(response.authResponse) {
				// connected
				connected(field, updateURL);
			}
			else {
				// cancelled
			}
		});
	}

	function connected(field, updateURL) {
		FB.api('/me/picture?type=large', function(response) {
			if(response.data && !response.data.is_silhouette) {
				setFacebookPhotoField(field, response.data.url, updateURL);
			}
			else {
				// error
			}
		});
	}

	function createLoginStatusCallback(button) {
		button = $(button);
		var field = $(document.getElementById(button.attr('data-for'))),
			updateURL = button.attr('data-update');

		return function loginStatusCallback(response) {
			if(response.status === 'connected') {
				connected(field, updateURL);
			}
			else if(response.status === 'not_authorized') {
				// not_authorized
				login(field, updateURL);
			}
			else {
				// not_logged_in
				login(field, updateURL);
			}
		}
	}

	$(document.body).on('click', '.facebook-auth', function(ev) {
		// Additional init code here
		FB.getLoginStatus(createLoginStatusCallback(this));
		ev.preventDefault();
	});

}(jQuery));
