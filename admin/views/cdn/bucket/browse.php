<?php

    parse_str($this->input->server('QUERY_STRING'), $query);
    $query = array_filter($query);
    $query = $query ? '?' . http_build_query($query) : '';
    $return = $query ? '?return=' . urlencode(uri_string() . $query) : '';

?>
<div class="group-cdn trash browse">
    <p>
        The following items are currently in the CDN trash.
        <?php

            if (userHasPermission('admin.cdnadmin:0.can_purge_trash')) {

                echo anchor('admin/cdnadmin/bucket/create' . $return, 'Create Bucket', 'style="float:right" class="awesome small green"');

            }

        ?>
    </p>
    <hr />
    <p class="system-alert message">
        <strong>TODO:</strong> facility for browsing CDN Buckets
    </p>
</div>