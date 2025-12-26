<!DOCTYPE html>
<html dir="<?php echo ($front_setting->is_active_rtl) ? "rtl" : "ltr"; ?>" lang="<?php echo ($front_setting->is_active_rtl) ? "ar" : "en"; ?>">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title><?php echo $page['title']; ?></title>
        <meta name="title" content="<?php echo $page['meta_title']; ?>">
        <meta name="keywords" content="<?php echo $page['meta_keyword']; ?>">
        <meta name="description" content="<?php echo $page['meta_description']; ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token-name" content="<?php echo $this->security->get_csrf_token_name(); ?>">
        <meta name="csrf-cookie-name" content="<?php echo $this->config->item('csrf_cookie_name'); ?>">
        <meta name="csrf-token" content="<?php echo $this->security->get_csrf_hash(); ?>">
        <link rel="shortcut icon" href="<?php echo base_url($front_setting->fav_icon); ?>" type="image/x-icon">
        <link href="<?php echo $base_assets_url; ?>css/bootstrap.min.css" rel="stylesheet">
        <link href="<?php echo $base_assets_url; ?>css/owl.carousel.css" rel="stylesheet">
        <link href="<?php echo $base_assets_url; ?>css/font-awesome.min.css" rel="stylesheet">
        <link href="<?php echo $base_assets_url; ?>css/style.css" rel="stylesheet">
        <link rel="stylesheet" href="<?php echo $base_assets_url; ?>datepicker/bootstrap-datepicker3.css"/>
        <script src="<?php echo $base_assets_url; ?>js/jquery.min.js"></script>
        <script type="text/javascript">
            var base_url = "<?php echo base_url() ?>";
            var csrf_token_name = "<?php echo $this->security->get_csrf_token_name(); ?>";
            var csrf_cookie_name = "<?php echo $this->config->item('csrf_cookie_name'); ?>";
            var csrf_hash = "<?php echo $this->security->get_csrf_hash(); ?>";

            function getCookie(name) {
                var value = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
                return value ? value.pop() : null;
            }

            function getCurrentCsrfToken() {
                return getCookie(csrf_cookie_name) || csrf_hash;
            }

            function ensureCsrf(settings) {
                var method = (settings.type || '').toUpperCase();
                if (['POST', 'PUT', 'PATCH', 'DELETE'].indexOf(method) === -1) {
                    return;
                }

                var token = getCurrentCsrfToken();
                if (!token) {
                    return;
                }

                if (settings.data instanceof FormData) {
                    if (!settings.data.has(csrf_token_name)) {
                        settings.data.append(csrf_token_name, token);
                    }
                } else if (typeof settings.data === 'string') {
                    var encodedName = encodeURIComponent(csrf_token_name);
                    var regex = new RegExp('(?:^|&)' + encodedName + '=');
                    if (!regex.test(settings.data)) {
                        settings.data += (settings.data ? '&' : '') + encodedName + '=' + encodeURIComponent(token);
                    }
                } else if (settings.data == null) {
                    settings.data = encodeURIComponent(csrf_token_name) + '=' + encodeURIComponent(token);
                } else if (typeof settings.data === 'object') {
                    if (typeof settings.data[csrf_token_name] === 'undefined') {
                        settings.data[csrf_token_name] = token;
                    }
                }

                csrf_hash = token;
            }

            $.ajaxSetup({
                beforeSend: function (xhr, settings) {
                    ensureCsrf(settings);
                    if (['POST', 'PUT', 'PATCH', 'DELETE'].indexOf((settings.type || '').toUpperCase()) !== -1) {
                        xhr.setRequestHeader('X-CSRF-TOKEN', getCurrentCsrfToken());
                    }
                }
            });

            $(document).ajaxComplete(function () {
                csrf_hash = getCurrentCsrfToken();
            });
        </script>
        <?php

if ($front_setting->is_active_rtl) {
    ?>
            <link href="<?php echo $base_assets_url; ?>rtl/bootstrap-rtl.min.css" rel="stylesheet">
            <link href="<?php echo $base_assets_url; ?>rtl/style-rtl.css" rel="stylesheet">
            <?php
}
?>
        <?php echo $front_setting->google_analytics; ?>
    </head>
    <body>

        <?php if (isset($featured_image) && $featured_image != "") {
    ?>
            <?php
}
?>

        <div class="container">
            <div class="row">
                <?php
$page_colomn = "col-md-12";

if ($page_side_bar) {

    $page_colomn = "col-md-9 col-sm-9";
}
?>
                <div class="<?php echo $page_colomn; ?>">
                    <?php echo $content; ?>
                </div>
                <?php
if ($page_side_bar) {
    ?>

                    <div class="col-md-3 col-sm-3">
                        <div class="sidebar">
                            <?php
if (in_array('news', json_decode($front_setting->sidebar_options))) {
        ?>
                                <div class="catetab"><?php echo $this->lang->line('latest_news'); ?></div>
                                <div class="newscontent">
                                    <div class="tickercontainer"><div class="mask"><ul id="ticker01" class="newsticker" style="height: 666px; top: 124.54px;">
                                                <?php
if (!empty($banner_notices)) {

            foreach ($banner_notices as $banner_notice_key => $banner_notice_value) {
                ?>
                                                        <li><a href="<?php echo site_url('read/' . $banner_notice_value['slug']) ?>"><div class="date_item"><?php echo date('d', strtotime($banner_notice_value['date'])); ?><span><?php $this->lang->line(strtolower(date('F', strtotime($banner_notice_value['date']))));?></span></div><?php echo $banner_notice_value['title']; ?>
                                                            </a></li>
                                                        <?php
}
        }
        ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div><!--./newscontent-->

                                <?php
}
    ?>

                        </div><!--./sidebar-->
                    </div>
                    <?php
}
?>
            </div><!--./row-->
        </div><!--./container-->
        <script src="<?php echo $base_assets_url; ?>js/bootstrap.min.js"></script>
        <script type="text/javascript" src="<?php echo $base_assets_url; ?>js/jquery.waypoints.min.js"></script>
        <script type="text/javascript" src="<?php echo $base_assets_url; ?>js/jquery.counterup.min.js"></script>
        <script src="<?php echo $base_assets_url; ?>js/owl.carousel.min.js"></script>
        <script src="<?php echo $base_assets_url; ?>js/ss-lightbox.js"></script>
        <script src="<?php echo $base_assets_url; ?>js/custom.js"></script>
        <script type="text/javascript" src="<?php echo $base_assets_url; ?>datepicker/bootstrap-datepicker.min.js"></script>

    </body>
</html>
