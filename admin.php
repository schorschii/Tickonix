<?php
require_once('loader.inc.php');
require_once('session.inc.php');

$info = null;
$infoClass = null;

try {
	// update texts if requested
	if(!empty($_POST['action']) && $_POST['action'] == 'text_update') {
		$db->updateSetting('web-title', $_POST['web-title']);
		$db->updateSetting('web-description', $_POST['web-description']);
		$db->updateSetting('invitation-mail-reply-to', $_POST['invitation-mail-reply-to']);
		$db->updateSetting('invitation-mail-sender-address', $_POST['invitation-mail-sender-address']);
		$db->updateSetting('invitation-mail-sender-name', $_POST['invitation-mail-sender-name']);
		$db->updateSetting('invitation-mail-subject', $_POST['invitation-mail-subject']);
		$db->updateSetting('invitation-mail-body', $_POST['invitation-mail-body']);
		$info = LANG('texts_saved');
		$infoClass = 'green';
	}

	// delete event if requested
	if(!empty($_POST['action']) && $_POST['action'] == 'event_delete'
	&& !empty($_POST['id'])) {
		$db->deleteEvent($_POST['id']);
		$info = LANG('event_deleted');
		$infoClass = 'green';
	}

	// edit event if requested
	if(!empty($_POST['action']) && $_POST['action'] == 'event_edit'
	&& !empty($_POST['id_old'])) {
		if(empty($_POST['id']) || empty(trim($_POST['id'])))
			$_POST['id'] = randomString(6, 'abcdefghijklmnopqrstuvwxyz');
		$reservation_start = $_POST['reservation_start_date'].' '.$_POST['reservation_start_time'];
		$reservation_end = $_POST['reservation_end_date'].' '.$_POST['reservation_end_time'];
		$db->updateEvent(
			$_POST['id'], $_POST['title'], $_POST['max'],
			$_POST['start_date'].' '.$_POST['start_time'], $_POST['end_date'].' '.$_POST['end_time'],
			$_POST['location'], $_POST['voucher_only'], $_POST['tickets_per_email'],
			empty(trim($reservation_start)) ? null : $reservation_start,
			empty(trim($reservation_end)) ? null : $reservation_end,
			$_POST['id_old'],
		);
		$info = LANG('changes_saved');
		$infoClass = 'green';
	}

	// create event if requested
	if(!empty($_POST['action']) && $_POST['action'] == 'event_create') {
		if(empty($_POST['id']) || empty(trim($_POST['id'])))
			$_POST['id'] = randomString(6, 'abcdefghijklmnopqrstuvwxyz');
		$reservation_start = $_POST['reservation_start_date'].' '.$_POST['reservation_start_time'];
		$reservation_end = $_POST['reservation_end_date'].' '.$_POST['reservation_end_time'];
		$db->insertEvent(
			$_POST['id'], $_POST['title'], $_POST['max'],
			$_POST['start_date'].' '.$_POST['start_time'], $_POST['end_date'].' '.$_POST['end_time'],
			$_POST['location'], $_POST['voucher_only'], $_POST['tickets_per_email'],
			empty(trim($reservation_start)) ? null : $reservation_start,
			empty(trim($reservation_end)) ? null : $reservation_end,
		);
		$info = LANG('event_created');
		$infoClass = 'green';
	}

	// delete voucher if requested
	if(!empty($_POST['action']) && $_POST['action'] == 'voucher_delete') {
		$db->deleteVoucher($_POST['code']);
		$info = LANG('voucher_deleted');
		$infoClass = 'green';
	}

	// edit voucher if requested
	if(!empty($_POST['action']) && $_POST['action'] == 'voucher_edit') {
		if(empty($_POST['code']) || empty(trim($_POST['code']))) {
			$_POST['code'] = randomString(5);
		}
		$db->updateVoucher($_POST['code'], empty($_POST['event_id']) ? null : $_POST['event_id'], $_POST['valid_amount'], $_POST['notes'], $_POST['code_old']);
		$info = LANG('changes_saved');
		$infoClass = 'green';
	}

	// create voucher if requested
	if(!empty($_POST['action']) && $_POST['action'] == 'voucher_create') {
		$amount = $_POST['voucher_amount'] ?? 1;
		for($i=0; $i<$amount; $i++) {
			$currentCode = $_POST['code'] ?? '';
			if(empty($_POST['code']) || empty(trim($_POST['code']))) {
				$currentCode = randomString(6);
			} elseif($amount > 1) {
				$currentCode .= randomString(4);
			}
			$db->insertVoucher($currentCode, empty($_POST['event_id']) ? null : $_POST['event_id'], $_POST['valid_amount'], $_POST['notes']);
			$info = LANG('voucher_created');
			$infoClass = 'green';
		}
	}

	// generate and output QR image
	if(!empty($_GET['action']) && $_GET['action'] == 'voucher_qr') {
		generateVoucherQrImage(
			$_SERVER['SERVER_NAME'].dirname($_SERVER['SCRIPT_NAME']),
			$_GET['code']??'',
		);
	}
} catch(Exception $e) {
	$info = $e->getMessage();
	$infoClass = 'red';
}

function generateVoucherQrImage($url, $code) {
	if(empty($code)) $urlWithCode = $url;
	else $urlWithCode = $url.'?voucher_code='.urlencode($code);

	$qrGenerator = new QRCode($urlWithCode);
	$qrImage = $qrGenerator->render_image();

	$origWidth = imagesx($qrImage);
	$width  = imagesx($qrImage) * 1.3;
	$origHeight = imagesy($qrImage);
	$height = $origHeight * 1.5;

	$finalImage = imagecreate($width, $height);
	$white = imagecolorallocate($finalImage, 255, 255, 255);
	$black = imagecolorallocate($finalImage, 0, 0, 0);
	imagefilledrectangle($finalImage, 0, 0, $width, $height, $white);

	imagecopy($finalImage, $qrImage, ($width/2)-($origWidth/2), ($height/2)-($origHeight/2), 0, 0, $origWidth, $origHeight);

	// top text
	$fontSize = 10;
	$fontFile = 'font/arial.ttf';
	list($left, $bottom, $right, , , $top) = imageftbbox($fontSize, 0, $fontFile, $url);
	$left_offset = ($right - $left) / 2;
	$top_offset = ($bottom - $top) / 2;
	$x = $width/2 - $left_offset;
	$y = $height/8 + $top_offset;
	imagefttext($finalImage, $fontSize, 0, $x, $y, $black, $fontFile, $url);
	// bottom text
	if(!empty($code)) {
		$fontSize = 12;
		$fontFile = 'font/arialbd.ttf';
		$codeText = 'Voucher-Code:'."\n".$code;
		list($left, $bottom, $right, , , $top) = imageftbbox($fontSize, 0, $fontFile, $codeText);
		$left_offset = ($right - $left) / 2;
		$top_offset = ($bottom - $top) / 2;
		$x = $width/2 - $left_offset;
		$y = $height/8*6.5 + $top_offset;
		imagefttext($finalImage, $fontSize, 0, $x, $y, $black, $fontFile, $codeText);
	}

	header('Content-type: image/png');
	imagepng($finalImage);
	die();
}
?>

<!DOCTYPE html>
<html>
	<head>
		<?php require_once('head.inc.php'); ?>
		<title><?php echo LANG('administration'); ?> | Tickonix</title>
		<script src='js/admin.js'></script>
		<script src='js/strings.js.php'></script>
		<link rel='stylesheet' href='css/admin.css'></link>
	</head>
	<body>
		<div id='container'>
			<div id='splash' class='contentbox'>

				<?php foreach(['img/logo-custom.png','img/logo-custom.jpg'] as $file) if(file_exists($file)) { ?>
					<img id='logo' src='<?php echo $file; ?>'>
				<?php } ?>

				<h1><?php echo LANG('administration'); ?></h1>

				<img class='contentbox-embleme' src='img/ticket.svg'>

				<div class='toggler'>
					<a class='<?php if(($_GET['view']??'')=='general') echo 'active'; ?>' href='?view=general'><?php echo LANG('texts'); ?></a>
					<a class='<?php if(($_GET['view']??'')=='events') echo 'active'; ?>' href='?view=events'><?php echo LANG('events'); ?></a>
					<a class='<?php if(($_GET['view']??'')=='voucher') echo 'active'; ?>' href='?view=voucher'><?php echo LANG('vouchers'); ?></a>
					<a class='' href='check.php'><?php echo LANG('reservations'); ?></a>
				</div>

				<?php if($info) { ?>
					<div class='infobox <?php echo $infoClass; ?>'><?php echo htmlspecialchars($info); ?></div>
				<?php } ?>

				<?php if(($_GET['view']??'') == 'general') { ?>
					<form method='POST'>
					<input type='hidden' name='action' value='text_update'>
					<h2><?php echo LANG('website'); ?></h2>
					<h3><?php echo LANG('title'); ?></h3>
					<input class='fullwidth' type='text' name='web-title' value='<?php echo htmlspecialchars($db->getSetting('web-title'), ENT_QUOTES); ?>'>
					<h3><?php echo LANG('text'); ?> <small>(<?php echo LANG('can_contain_html'); ?>)</small></h3>
					<textarea class='fullwidth' rows='5' name='web-description'><?php echo htmlspecialchars($db->getSetting('web-description')); ?></textarea>
					<hr/>
					<h2><?php echo LANG('invitation_mail'); ?></h2>
					<h3><?php echo LANG('subject'); ?></h3>
					<input class='fullwidth' type='text' name='invitation-mail-subject' value='<?php echo htmlspecialchars($db->getSetting('invitation-mail-subject'), ENT_QUOTES); ?>'>
					<h3><?php echo LANG('sender_name'); ?></h3>
					<input class='fullwidth' type='text' name='invitation-mail-sender-name' value='<?php echo htmlspecialchars($db->getSetting('invitation-mail-sender-name'), ENT_QUOTES); ?>'>
					<h3><?php echo LANG('sender_address'); ?></h3>
					<input class='fullwidth' type='email' name='invitation-mail-sender-address' value='<?php echo htmlspecialchars($db->getSetting('invitation-mail-sender-address'), ENT_QUOTES); ?>'>
					<h3><?php echo LANG('reply_to_address'); ?></h3>
					<input class='fullwidth' type='email' name='invitation-mail-reply-to' value='<?php echo htmlspecialchars($db->getSetting('invitation-mail-reply-to'), ENT_QUOTES); ?>'>
					<h3><?php echo LANG('text'); ?> <small>(<?php echo LANG('can_contain_html'); ?>)</small></h3>
					<textarea class='fullwidth' rows='5' name='invitation-mail-body'><?php echo htmlspecialchars($db->getSetting('invitation-mail-body')); ?></textarea>
					<small><table>
					<tr><td>$$TITLE$$</td><td>&rarr; <?php echo LANG('website_title'); ?></td></tr>
					<tr><td>$$EVENT$$</td><td>&rarr; <?php echo LANG('event_title'); ?></td></tr>
					<tr><td>$$START$$</td><td>&rarr; <?php echo LANG('start_date_and_time'); ?></td></tr>
					<tr><td>$$END$$</td><td>&rarr; <?php echo LANG('end_date_and_time'); ?></td></tr>
					<tr><td>$$LOCATION$$</td><td>&rarr; <?php echo LANG('event_location'); ?></td></tr>
					<tr><td>$$CODE$$</td><td>&rarr; <?php echo LANG('randomly_generated_code'); ?></td></tr>
					<tr><td>$$QRCODE$$</td><td>&rarr; <?php echo LANG('qr_code_img_element'); ?></td></tr>
					<tr><td>$$REVOKELINK$$</td><td>&rarr; <?php echo LANG('link_for_revocation'); ?></td></tr>
					</table></small>
					<br><br>
					<button class='primary fullwidth'><?php echo LANG('save_changes'); ?></button>
					</form>
				<?php } ?>

				<?php if(($_GET['view']??'') == 'events') {
					$events = $db->getEvents();
				?>
					<form method='POST' class='adminForm'>
						<?php
						$selectedEvent = null;
						if(!empty($_GET['id'])) {
							$selectedEvent = $events[$_GET['id']] ?? null;
						} ?>

						<label><?php echo LANG('internal_id'); ?>:</label>
						<div><input type='text' name='id' title='<?php echo LANG('leave_empty_to_auto_generate'); ?>' placeholder='<?php echo LANG('optional_placeholder'); ?>' value='<?php echo htmlspecialchars($selectedEvent ? $selectedEvent['id'] : ''); ?>'></div>

						<label><?php echo LANG('title'); ?>:</label>
						<div><input type='text' name='title' value='<?php echo htmlspecialchars($selectedEvent ? $selectedEvent['title'] : ''); ?>'></div>

						<label><?php echo LANG('begin'); ?>:</label>
						<?php $preselectDate = ''; $preselectTime = '';
						if($selectedEvent && $selectedEvent['start']) {
							$preselectDate = date('Y-m-d', strtotime($selectedEvent['start']));
							$preselectTime = date('H:i', strtotime($selectedEvent['start']));
						} ?>
						<div class='multiinput'>
							<input type='date' name='start_date' value='<?php echo htmlspecialchars($preselectDate); ?>'>
							<input type='time' name='start_time' value='<?php echo htmlspecialchars($preselectTime); ?>'>
						</div>

						<label><?php echo LANG('end'); ?>:</label>
						<?php $preselectDate = ''; $preselectTime = '';
						if($selectedEvent && $selectedEvent['end']) {
							$preselectDate = date('Y-m-d', strtotime($selectedEvent['end']));
							$preselectTime = date('H:i', strtotime($selectedEvent['end']));
						} ?>
						<div class='multiinput'>
							<input type='date' name='end_date' value='<?php echo htmlspecialchars($preselectDate); ?>'>
							<input type='time' name='end_time' value='<?php echo htmlspecialchars($preselectTime); ?>'>
						</div>

						<label><?php echo LANG('location'); ?>:</label>
						<div><input type='text' name='location' value='<?php echo htmlspecialchars($selectedEvent ? $selectedEvent['location'] : ''); ?>'></div>

						<label><?php echo LANG('reservations_per_email'); ?>:</label>
						<div><input type='number' name='tickets_per_email' min='1' value='<?php echo htmlspecialchars($selectedEvent ? $selectedEvent['tickets_per_email'] : '1'); ?>'></div>

						<label><?php echo LANG('maximum_reservations'); ?>:</label>
						<div><input type='number' name='max' min='1' value='<?php echo htmlspecialchars($selectedEvent ? $selectedEvent['max'] : '1'); ?>'></div>

						<div></div>
						<label>
							<input type='hidden' name='voucher_only' value='0'>
							<label><input type='checkbox' name='voucher_only' value='1' <?php if($selectedEvent && $selectedEvent['voucher_only']) echo 'checked'; ?>><?php echo LANG('only_with_voucher'); ?></label>
						</label>

						<label><?php echo LANG('reservation_begin'); ?>:</label>
						<?php $preselectDate = ''; $preselectTime = '';
						if($selectedEvent && $selectedEvent['reservation_start']) {
							$preselectDate = date('Y-m-d', strtotime($selectedEvent['reservation_start']));
							$preselectTime = date('H:i', strtotime($selectedEvent['reservation_start']));
						} ?>
						<div class='multiinput'>
							<input type='date' name='reservation_start_date' value='<?php echo htmlspecialchars($preselectDate); ?>'>
							<input type='time' name='reservation_start_time' value='<?php echo htmlspecialchars($preselectTime); ?>'>
						</div>

						<label><?php echo LANG('reservation_end'); ?>:</label>
						<?php $preselectDate = ''; $preselectTime = '';
						if($selectedEvent && $selectedEvent['reservation_end']) {
							$preselectDate = date('Y-m-d', strtotime($selectedEvent['reservation_end']));
							$preselectTime = date('H:i', strtotime($selectedEvent['reservation_end']));
						} ?>
						<div class='multiinput'>
							<input type='date' name='reservation_end_date' value='<?php echo htmlspecialchars($preselectDate); ?>'>
							<input type='time' name='reservation_end_time' value='<?php echo htmlspecialchars($preselectTime); ?>'>
						</div>

						<button id='btnQrLink' type='button' style='width:auto; padding:8px 14px' onclick='window.open("admin.php?action=voucher_qr", "_blank", "location=yes,height=570,width=520,scrollbars=yes,status=yes");'><img src='img/qr.svg'>&nbsp;<?php echo LANG('qr_code_to_reservation_page'); ?></button>

						<?php if($selectedEvent) { ?>
							<input type='hidden' name='id_old' value='<?php echo htmlspecialchars($selectedEvent ? $selectedEvent['id'] : ''); ?>'>
							<button id='btnSave' name='action' value='event_edit' class='primary'><?php echo LANG('save_changes'); ?></button>
						<?php } else { ?>
							<button id='btnSave' name='action' value='event_create' class='primary'><?php echo LANG('create_event'); ?></button>
						<?php } ?>
					</form>
					<hr/>
					<table id='tblEvents'>
						<thead>
							<tr>
								<th><?php echo LANG('title'); ?></th>
								<th><?php echo LANG('reservations'); ?></th>
								<th class='actions'><?php echo LANG('action'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach($events as $event) { ?>
							<tr>
								<td>
									<div><?php echo htmlspecialchars($event['title']); ?></div>
									<div class='hint'><?php echo htmlspecialchars(shortenDateRange($event['start'], $event['end'])); ?></div>
									<div class='hint'><?php echo htmlspecialchars($event['location']); ?></div>
									<div class='hint'><?php echo htmlspecialchars($event['tickets_per_email']); ?>&nbsp;<?php echo str_replace('<br>', '', LANG('reservations_per_email')); ?></div>
								</td>
								<td>
									<div class='voucheronly'><?php echo $event['voucher_only'] ? 'nur mit Voucher' : ''; ?></div>
									<a href='check.php?event=<?php echo urlencode($event['id']); ?>'>
										<?php
										$reserved = count($db->getValidTickets($event['id']));
										echo progressBar($reserved*100/$event['max'], null, null, 'fullwidth', '', $reserved.'/'.$event['max']);
										?>
									</a>
									<div class='hint'><?php echo htmlspecialchars(shortenDateRange($event['reservation_start'], $event['reservation_end'])); ?></div>
								</td>
								<td class='actions'>
									<form method='GET'>
										<input type='hidden' name='id' value='<?php echo htmlspecialchars($event['id'], ENT_QUOTES); ?>'>
										<button name='view' value='events'><img src='img/edit.svg'></button>
									</form>
									<form method='POST'>
										<input type='hidden' name='id' value='<?php echo htmlspecialchars($event['id'], ENT_QUOTES); ?>'>
										<button name='action' value='event_delete' onclick='return confirm(LANG["confirm_delete_event"])'><img src='img/delete.svg'></button>
									</form>
								</td>
							</tr>
							<?php } ?>
						</tbody>
					</table>
				<?php } ?>

				<?php if(($_GET['view']??'') == 'voucher') {
					$events = $db->getEvents();
					$vouchers = $db->getVouchers();
				?>
					<form method='POST' class='adminForm'>
						<?php
						$selectedVoucher = null;
						if(!empty($_GET['code'])) {
							$selectedVoucher = $vouchers[$_GET['code']] ?? null;
						} ?>

						<label><?php echo LANG('code'); ?>:</label>
						<div><input type='text' name='code' title='<?php echo LANG('leave_empty_to_auto_generate'); ?>' placeholder='<?php echo LANG('optional_placeholder'); ?>' value='<?php echo htmlspecialchars($selectedVoucher ? $selectedVoucher['code'] : ''); ?>'></div>

						<label><?php echo LANG('number_of_redeems'); ?>:</label>
						<div><input type='number' name='valid_amount' min='1' value='<?php echo htmlspecialchars($selectedVoucher ? $selectedVoucher['valid_amount'] : '1'); ?>'></div>

						<label><?php echo LANG('event'); ?>:</label>
						<div>
							<select name='event_id'>
								<option value=''><?php echo LANG('all_placeholder'); ?></option>
								<?php foreach($events as $event) { ?>
									<option value='<?php echo htmlspecialchars($event['id'], ENT_QUOTES); ?>' <?php if($selectedVoucher && $selectedVoucher['event_id']===$event['id']) echo 'selected'; ?>><?php echo htmlspecialchars($event['title']); ?></option>
								<?php } ?>
							</select>
						</div>

						<label><?php echo LANG('note'); ?>:</label>
						<div><input type='text' name='notes' value='<?php echo htmlspecialchars($selectedVoucher ? $selectedVoucher['notes'] : ''); ?>'></div>

						<?php if($selectedVoucher) { ?>
							<div></div>
						<?php } else { ?>
							<label><?php echo LANG('voucher_amount'); ?>:</label>
							<div><input type='number' name='voucher_amount' min='1' value='1'></div>
						<?php } ?>

						<?php if($selectedVoucher) { ?>
							<input type='hidden' name='code_old' value='<?php echo htmlspecialchars($selectedVoucher ? $selectedVoucher['code'] : ''); ?>'>
							<button id='btnSave' name='action' value='voucher_edit' class='primary'><?php echo LANG('save_changes'); ?></button>
						<?php } else { ?>
							<button id='btnSave' name='action' value='voucher_create' class='primary'><?php echo LANG('create_voucher'); ?></button>
						<?php } ?>
					</form>
					<hr/>
					<div class='scroll-h'>
					<table id='tblEvents'>
						<thead>
							<tr>
								<th><?php echo LANG('code'); ?></th>
								<th><?php echo LANG('number_of_redeems'); ?></th>
								<th><?php echo LANG('event'); ?></th>
								<th class='actions'><?php echo LANG('action'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach($vouchers as $voucher) { ?>
							<tr>
								<td>
									<div class='monospace'><?php echo htmlspecialchars($voucher['code']); ?></div>
									<div class='hint'><?php echo htmlspecialchars($voucher['notes']); ?></div>
								</td>
								<td>
									<?php
									$used = count($db->getTicketsByVoucherCode($voucher['code']));
									echo progressBar($used*100/$voucher['valid_amount'], null, null, 'fullwidth', '', $used.'/'.$voucher['valid_amount']);
									?>
								</td>
								<td>
									<?php echo htmlspecialchars($voucher['event_id'] ? $events[$voucher['event_id']]['title'] : LANG('all_placeholder')); ?>
								</td>
								<td class='actions'>
									<form method='GET'>
										<input type='hidden' name='code' value='<?php echo htmlspecialchars($voucher['code'], ENT_QUOTES); ?>'>
										<button name='view' value='voucher'><img src='img/edit.svg'></button>
									</form>
									<button onclick='window.open("admin.php?action=voucher_qr&code=<?php echo urlencode($voucher['code']); ?>", "_blank", "location=yes,height=570,width=520,scrollbars=yes,status=yes");'><img src='img/qr.svg'></button>
									<form method='POST'>
										<input type='hidden' name='code' value='<?php echo htmlspecialchars($voucher['code'], ENT_QUOTES); ?>'>
										<button name='action' value='voucher_delete' onclick='return confirm(LANG["confirm_delete_voucher"])'><img src='img/delete.svg'></button>
									</form>
								</td>
							</tr>
							<?php } ?>
						</tbody>
					</table>
					</div>
				<?php } ?>

			</div>
			<?php require('foot.inc.php'); ?>
		</div>
	</body>
</html>
