/* globals Dropzone */
var NAILS_Admin_CDN_Upload;
NAILS_Admin_CDN_Upload = function()
{
	this._dropzone = null;

	// --------------------------------------------------------------------------

	this.__construct = function()
	{
		this._init_dropdown();
		this._init_dropzone();
	};

	// --------------------------------------------------------------------------

	this._init_dropdown = function()
	{
		var _this = this;
		$('#bucket-chooser').on('change', function(){
			_this._dropzone.options.headers['X-Cdn-Bucket'] = $(this).val();
		});
	};

	// --------------------------------------------------------------------------

	this._init_dropzone = function()
	{
		var _this = this;
		Dropzone.autoDiscover = false;
		this._dropzone = new Dropzone('div#dropzone',
		{
			url: window.SITE_URL + 'api/cdn/object/create',
			autoProcessQueue: true,
			headers:
			{
				'X-Cdn-Bucket' : $('#bucket-chooser').val()
			},
			paramName: 'upload',
			addRemoveLinks: true
		});

		//	Bind listeners
		this._dropzone.on('complete', function(file) {

			if (file.status === 'success') {
				_this._dropzone.removeFile(file);
			}
		});
		this._dropzone.on('queuecomplete', function() {

			$('#alert-complete').slideDown();
			setTimeout(function() {

				$('#alert-complete').slideUp();
			}, 7500);
		});

		$('#action-upload').on('click', function()
		{
			_this._dropzone.processQueue();
		});
	};

	// --------------------------------------------------------------------------

	return this.__construct();
};