<div class="group-cdn object create">
	<?php

		echo $this->input->get( 'isModal' ) ? '<h1>Upload Items</h1>' : '';

	?>
	<p>
		Choose which bucket the items you wish to upload should belong to then drag files in (or click the area below).
	</p>
	<p class="system-alert success" id="alert-complete" style="display:none;">
		<strong>Complete!</strong> All files have been uploaded.
	</p>
	<fieldset>
		<legend>Bucket</legend>
		<?php

			$_field				= array();
			$_field['key']		= 'bucket_id';
			$_field['label']	= 'Bucket';
			$_field['class']	= 'select2';
			$_field['id']		= 'bucket-chooser';

			$_options = array();
			foreach ( $buckets as $bucket ) :

				$_options[$bucket->slug] = $bucket->label;

			endforeach;

			echo form_field_dropdown( $_field, $_options );

		?>
	</fieldset>
	<div id="dropzone" class="dropzone dz-square"></div>
</div>