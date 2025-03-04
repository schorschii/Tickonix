<?php
require_once('loader.inc.php');
require_once('session.inc.php');

$info = null;
$infoClass = null;
$sound = null;
$tickets = null;
$events = $db->getEvents();

try {
	if(!empty($_GET['event'])) {
		if(!empty($_POST['action']) && !empty($_POST['id']) && is_array($_POST['id'])) {
			// delete reservation if requested
			if($_POST['action'] === 'delete') {
				foreach($_POST['id'] as $id) {
					if(!$db->deleteTicket($id))
						throw new Exception(LANG('reservation_could_not_be_deleted'));
				}
				$info = LANG('reservations_deleted');
				$infoClass = 'green';

			// delete reservation if requested
			} elseif($_POST['action'] === 'reset') {
				foreach($_POST['id'] as $id) {
					if(!$db->resetTicket($id))
						throw new Exception(LANG('reservation_could_not_be_reset'));
				}
				$info = LANG('reservations_reset');
				$infoClass = 'green';

			// resend ticket mail if requested
			} elseif($_POST['action'] === 'resend') {
				$sentEmails = '';
				foreach($_POST['id'] as $id) {
					$ticket = $db->getTicketByCode($id);
					$selectedEvent = $events[$ticket['event_id']];
					$sentEmails .= $ticket['code'].' -> '.$ticket['email']."\n";
					$mailer = new InvitationMailer();
					$mailer->send(
						$db->getSetting('invitation-mail-subject'), $db->getSetting('invitation-mail-body'),
						$db->getSetting('web-title'), $selectedEvent, $ticket['code'], $ticket['token'],
						$ticket['email'], $db->getSetting('invitation-mail-sender-name'), $db->getSetting('invitation-mail-sender-address'), $db->getSetting('invitation-mail-reply-to')
					);
				}
				$info = $sentEmails;
				$infoClass = 'green';
			}
		}

		// load ticket list from db
		$tickets = $db->getTickets($_GET['event']);

		// check given code
		$request = json_decode(file_get_contents('php://input'), true);
		if(!empty($request['check'])) {
			$isCheckin = ($request['mode'] !== 'checkout');
			$found = false;
			$codeToCheck = strtoupper($request['check']);
			foreach($tickets as $ticket) {
				if($ticket['code'] === $codeToCheck) {
					$found = true;
					if($ticket['revoked']) {
						$info = LANG('reservation_was_revoked');
						$infoClass = 'red';
						$sound = 'img/error.mp3';
					} elseif($isCheckin) {
						if($ticket['checked_in']) {
							$info = LANG('code_already_checked_in');
							$infoClass = 'yellow';
							$sound = 'img/warning.mp3';
						} else {
							$info = LANG('code_valid_checkin');
							$infoClass = 'green';
							$db->updateTicketCheckIn($ticket['code']);
							$sound = 'img/success.mp3';
						}
					} else {
						if($ticket['checked_out']) {
							$info = LANG('code_already_checked_out');
							$infoClass = 'yellow';
							$sound = 'img/warning.mp3';
						} else {
							$info = LANG('code_valid_checkout');
							$infoClass = 'green';
							$db->updateTicketCheckOut($ticket['code']);
							$sound = 'img/success.mp3';
						}
					}
					break;
				}
			}
			if(!$found) {
				$info = LANG('code_not_found');
				$infoClass = 'red';
				$sound = 'img/error.mp3';
			}
			// return JSON for AJAX request
			$table = getTicketsTableHtml($db->getTickets($_GET['event']));
			header('Content-Type: application/json');
			die(
				json_encode([
					'info'=>$info, 'infoClass'=>$infoClass, 'sound'=>$sound,
					'rows'=>$table['rows'], 'count'=>$table['count'], 'max'=>$events[$_GET['event']]['max'],
					'checked_in'=>$table['checked_in'], 'checked_out'=>$table['checked_out'], 'revoked'=>$table['revoked']
				])
			);
		}
	}
} catch(Exception $e) {
	$info = $e->getMessage();
	$infoClass = 'red';
}

function getTicketsTableHtml($tickets) {
	$count = 0; $checkedIn = 0; $checkedOut = 0; $revoked = 0;
	$rowsHtml = '';
	foreach($tickets as $ticket) {
		$tooltip = 'Reserviert: '.$ticket['created']."\n"
			.'Storniert: '.$ticket['revoked']."\n"
			.'Eingecheckt: '.$ticket['checked_in']."\n"
			.'Ausgecheckt: '.$ticket['checked_out'];
		$class = '';
		if($ticket['checked_in']) {$class = 'checkedin'; $checkedIn ++;}
		if($ticket['checked_out']) {$class = 'checkedout'; $checkedOut ++;}
		if($ticket['revoked']) {$class = 'revoked'; $revoked ++;} else {$count ++;}
		$rowsHtml .= '<tr class="'.$class.'" title="'.htmlspecialchars($tooltip, ENT_QUOTES).'">'
			. '	<td><input type="checkbox" name="id[]" value="'.htmlspecialchars($ticket['code'], ENT_QUOTES).'"></td>'
			. '	<td class="monospace">'.htmlspecialchars($ticket['code']).'</td>'
			. '	<td>'.htmlspecialchars($ticket['email']).'</td>'
			. '	<td>'.htmlspecialchars($ticket['voucher_code']??'').'</td>'
			. '</tr>';
	}
	return [
		'rows'=>$rowsHtml, 'count'=>$count,
		'checked_in'=>$checkedIn, 'checked_out'=>$checkedOut, 'revoked'=>$revoked,
	];
}
?>

<!DOCTYPE html>
<html>
	<head>
		<?php require_once('head.inc.php'); ?>
		<title><?php echo LANG('checkin_checkout'); ?></title>
		<script src='js/admin.js'></script>
		<script src='js/strings.js.php'></script>
		<link rel='stylesheet' href='css/admin.css'></link>
		<script src='js/html5-qrcode.min.js'></script>
	</head>
	<body>
		<div id='container'>
			<?php if($sound) { ?>
				<iframe src='<?php echo $sound; ?>' allow='autoplay' style='display:none'></iframe>
			<?php } ?>

			<div id='splash' class='contentbox'>

				<?php foreach(['img/logo-custom.png','img/logo-custom.jpg'] as $file) if(file_exists($file)) { ?>
					<img id='logo' src='<?php echo $file; ?>'>
				<?php } ?>

				<h1><?php echo LANG('checkin_checkout'); ?></h1>

				<img class='contentbox-embleme' src='img/ticket.svg'>

				<div class='toggler'>
					<a class='' href='admin.php?view=general'><?php echo LANG('texts'); ?></a>
					<a class='' href='admin.php?view=events'><?php echo LANG('events'); ?></a>
					<a class='' href='admin.php?view=voucher'><?php echo LANG('vouchers'); ?></a>
					<a class='active' href='check.php'><?php echo LANG('reservations'); ?></a>
				</div>

				<?php if($info) { ?>
					<div class='infobox <?php echo $infoClass; ?>'><?php echo nl2br(htmlspecialchars($info)); ?></div>
				<?php } ?>

				<form method='GET' class='flex' style='clear:both'>
					<select id='sltEvent' name='event'>
						<option selected disabled value=''>=== <?php echo LANG('please_select'); ?> ===</option>
						<?php foreach($events as $key => $event) { ?>
							<option value='<?php echo htmlspecialchars($key, ENT_QUOTES); ?>' <?php if($key == ($_GET['event']??'')) echo 'selected'; ?>>
								<?php echo htmlspecialchars($event['title']??'???'); ?>
							</option>
						<?php } ?>
					</select>
					<button class='checkin'><?php echo LANG('show'); ?></button>
				</form>

				<?php if($tickets !== null) { $table = getTicketsTableHtml($tickets); ?>
					<div class='flex'>
						<div class='inputwithbutton'>
							<input type='text' id='txtCheckCode' placeholder='<?php echo LANG('enter_or_scan_code'); ?>' autofocus='true' required='true' />
							<button type='button' title='<?php echo LANG('use_camera_to_scan'); ?>' onclick='startScanner()'><img src='img/qr-scanner.svg'></button>
						</div>
						<div id='divModeSelector'>
							<label><input type='radio' id='rdoCheckin' name='mode' value='checkin' checked='true'><?php echo LANG('checkin'); ?></label>
							<label><input type='radio' id='rdoCheckout' name='mode' value='checkout'><?php echo LANG('checkout'); ?></label>
						</div>
						<button type='button' id='btnCheckCode' class='checkin' onclick='checkCode(sltEvent.value, txtCheckCode.value, rdoCheckout.checked?"checkout":"checkin")'><?php echo LANG('check'); ?></button>
					</div>
					<br>
					<div>
						<?php $max = $events[$_GET['event']]['max'];
						echo progressBar($table['count']*100/$max, 'prgReservations', null, 'fullwidth', '', $table['count'].'/'.$max.' '.LANG('reservations'));
						?>
					</div>
					<div class='legend'>
						<span class='checkedin'><b id='spnCheckedIn'><?php echo $table['checked_in']; ?></b>&nbsp;<?php echo LANG('checked_in'); ?></span>
						<span class='checkedout'><b id='spnCheckedOut'><?php echo $table['checked_out']; ?></b>&nbsp;<?php echo LANG('checked_out'); ?></span>
						<span><b id='spnRevoked'><?php echo $table['revoked']; ?></b>&nbsp;<span class='revoked'><?php echo LANG('revoked'); ?></span></span>
					</div>
					<br>
					<form method='POST'>
					<div class='scroll-h'>
						<table id='tblTickets'>
							<thead>
								<tr>
									<th><input type='checkbox' onclick='toggleCheckboxesInContainer(tblTickets, this.checked)'></th>
									<th><?php echo LANG('code'); ?></th>
									<th><?php echo LANG('email'); ?></th>
									<th><?php echo LANG('voucher'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php echo $table['rows']; ?>
							</tbody>
						</table>
					</div>
					<div class='actionbar scroll-h'>
						<?php echo LANG('selected'); ?>:
						<button name='action' value='resend' onclick='return confirm(LANG["confirm_resend_selected_reservations"])'><?php echo LANG('resend_mail'); ?></button>
						<button name='action' value='reset' onclick='return confirm(LANG["confirm_reset_selected_reservations"])'><?php echo LANG('reset_checkin_out'); ?></button>
						<button name='action' value='delete' onclick='return confirm(LANG["confirm_delete_selected_reservations"])'><?php echo LANG('delete'); ?></button>
					</div>
					</form>
				<?php } ?>

			</div>
			<?php require('foot.inc.php'); ?>
		</div>

		<div id='qrContainer'>
			<div id='qrScanner'></div>
			<button id='btnStopScan' onclick='stopScanner()'><?php echo LANG('exit_scanmode'); ?></button>
		</div>
		<div id='checkResult'>
			<div id='checkResultCode'></div>
			<div id='checkResultMessage'></div>
		</div>
	</body>
</html>
