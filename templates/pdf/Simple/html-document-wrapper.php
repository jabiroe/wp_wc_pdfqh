<?php global $wp_wc_pdfqh; ?>
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title><?php echo $wp_wc_pdfqh->get_template_name($wp_wc_pdfqh->export->template_type); ?></title>
	<style type="text/css"><?php $wp_wc_pdfqh->template_styles(); ?></style>
	<style type="text/css"><?php do_action( 'wp_wc_pdfqh_custom_styles', $wp_wc_pdfqh->export->template_type ); ?></style>
</head>
<body class="<?php echo $wp_wc_pdfqh->export->template_type; ?>">
<?php echo $wp_wc_pdfqh->export->output_body; ?>
</body>
</html>