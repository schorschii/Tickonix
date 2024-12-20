<meta charset='utf-8'/>
<meta name='viewport' content='width=device-width'>
<link rel='stylesheet' href='css/main.css'></link>
<link rel='icon' type='image/png' href='img/ticket.svg'>
<?php if($db->getSetting('web-theme-color')) { ?>
	<meta name='theme-color' content='<?php echo htmlspecialchars($db->getSetting('web-theme-color'),ENT_QUOTES); ?>'>
<?php } ?>
<meta name='author' content='Georg Sieber'>

<style>
	<?php foreach(['img/bg-custom.png','img/bg-custom.jpg'] as $file)
		if(file_exists($file)) { ?>
		html, body { background-image: url("<?php echo $file; ?>") }
	<?php } ?>
</style>
<?php foreach(['css/custom.css'] as $file) if(file_exists($file)) { ?>
	<link rel='stylesheet' href='<?php echo $file; ?>'></link>
<?php } ?>
