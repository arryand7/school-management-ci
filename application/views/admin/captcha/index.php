<style type="text/css">
   
    .table .pull-right {
    width: auto;
    text-align: initial;
    float: right !important;
}
</style>

<div class="content-wrapper">
    <section class="content-header">
        <h1>
            <i class="fa fa-gears"></i> <?php //echo $this->lang->line('system_settings'); ?>
        </h1>
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="nav-tabs-custom theme-shadow">
                    <ul class="nav nav-tabs pull-right">
                        <li class="pull-left header"><?php echo $this->lang->line('captcha_setting'); ?></li>

                    </ul>
                    <div class="tab-content">
                    <div class="download_label"><?php echo $this->lang->line('captcha_setting'); ?></div>
                        <table class="table table-striped table-bordered table-hover example" cellspacing="0" width="100%">
                            <thead>
                                <tr>
                                    <th><?php echo $this->lang->line('name'); ?></th>
                                    <th class="text-right noExport"><?php echo $this->lang->line('action'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php

if (!empty($inserted_fields)) {
    foreach ($inserted_fields as $fields_key => $fields_value) {
        ?>
 <tr>
                                        <td class="text-rtl-right" width="100%"><?php echo $this->lang->line($fields_value->name); ?></td>
                                        <td class="relative">
                                            <div class="material-switch pull-right">
                            <input id="field_<?php echo $fields_key ?>" name="<?php echo $fields_value->name; ?>" type="checkbox" data-role="field_<?php $fields_key?>" class="chk"  value="" <?php if($fields_value->status == 1){ echo "checked";} ?> />
                                                <label for="field_<?php echo $fields_key ?>" class="label-success"></label>
                                            </div>
                                        </td>
                                    </tr>
  <?php
}
}
?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">API Keys</h3>
                    </div>
                    <form action="<?php echo site_url('admin/captcha/update_keys'); ?>" method="post">
                        <input type="hidden" name="id" value="<?php echo html_escape($setting->id); ?>"/>
                        <div class="box-body">
                            <div class="form-group">
                                <label>Google Maps API Key</label>
                                <input type="text" name="google_maps_api_key" class="form-control" value="<?php echo html_escape(set_value('google_maps_api_key', $setting->google_maps_api_key)); ?>"/>
                                <small class="text-muted">Dipakai untuk fitur pickup point map.</small>
                            </div>
                            <div class="form-group">
                                <label>Firebase Service Account JSON</label>
                                <textarea name="firebase_service_account_json" class="form-control" rows="6"><?php echo html_escape(set_value('firebase_service_account_json', $setting->firebase_service_account_json)); ?></textarea>
                                <small class="text-muted">Tempelkan JSON service account untuk FCM.</small>
                            </div>
                            <div class="form-group">
                                <label>Yandex Translate API Key</label>
                                <input type="text" name="yandex_translate_api_key" class="form-control" value="<?php echo html_escape(set_value('yandex_translate_api_key', $setting->yandex_translate_api_key)); ?>"/>
                            </div>
                            <div class="form-group">
                                <label>Paymongo Public Key</label>
                                <input type="text" name="paymongo_public_key" class="form-control" value="<?php echo html_escape(set_value('paymongo_public_key', $setting->paymongo_public_key)); ?>"/>
                            </div>
                            <div class="form-group">
                                <label>Paymongo Secret Key</label>
                                <input type="text" name="paymongo_secret_key" class="form-control" value="<?php echo html_escape(set_value('paymongo_secret_key', $setting->paymongo_secret_key)); ?>"/>
                            </div>
                        </div>
                        <div class="box-footer">
                            <button type="submit" class="btn btn-primary"><?php echo $this->lang->line('save'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>

<script type="text/javascript">
    $(document).ready(function () {

        $(document).on('click', '.chk', function(event) {
            var name=$(this).attr('name');
            var status=1;
            if(this.checked) {
             status=1;
            } else {
             status=0;
            }
             if(confirm("<?php echo $this->lang->line('confirm_status'); ?>")){
              
                changeStatus(name, status);
            }
            else{
                     event.preventDefault();
            }
        });
    });

    function changeStatus(name, status) {

        var base_url = '<?php echo base_url() ?>';

        $.ajax({
            type: "POST",
            url: base_url + "admin/captcha/changeStatus",
            data: {'name': name, 'status': status},
            dataType: "json",
            success: function (data) {
                successMsg(data.msg);
                location.reload();
            }
        });
    }
</script>
