<?php
require_once('loader.inc.php');

session_name('TICKONIX_SESSID_'.md5(__DIR__));
session_start();

$info = null;
$infoClass = null;
$showForm = true;
$events = $db->getEvents();

if(!empty($_POST['action']) && !empty($_GET['code']) && !empty($_GET['token'])) {
	$showForm = false;
	try {
		$ticket = $db->getTicketByCode($_GET['code']);
		if(!$ticket || $ticket['token'] !== $_GET['token']) {
			throw new Exception(LANG('invalid_token'));
		}
		if($_POST['action'] === 'revoke') {
			$db->updateTicketRevoked($_GET['code']);
			$info = LANG('reservation_revoked');
			$infoClass = 'green';
		}
	} catch(Exception $e) {
		$info = $e->getMessage();
		$infoClass = 'red';
	}
}

if(!empty($_POST['captcha'])
&& !empty($_POST['event'])
&& !empty($_POST['email'])) {
	try {
		// check if requested event exists - never trust user input!
		if(!array_key_exists($_POST['event'], $events)) {
			throw new Exception(LANG('event_does_not_exist'));
		}
		$selectedEvent = $events[$_POST['event']];

		// check captcha
		if(!isset($_SESSION['captcha_text']) || $_POST['captcha'] !== $_SESSION['captcha_text']) {
			throw new Exception(LANG('captcha_incorrect'));
		}
		// invalidate captcha - can only be used once
		unset($_SESSION['captcha_text']);

		// check reservation time window
		if(($selectedEvent['reservation_start'] && strtotime($selectedEvent['reservation_start']) > time())
		|| ($selectedEvent['reservation_end'] && strtotime($selectedEvent['reservation_end']) < time())) {
			throw new Exception(LANG('event_cannot_be_booked_currently'));
		}

		// check if there are still places free
		$tickets = $db->getValidTickets($_POST['event']);
		if(count($tickets) >= $selectedEvent['max']) {
			throw new Exception(LANG('event_booked_out'));
		}

		// check email syntax
		if(!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
			throw new Exception(LANG('invalid_email_address'));
		}
		// if duplicate email addresses are not allowed, check if email address is already registered
		$ticketsPerMailCount = count($db->getTicketsByEmailAndEvent($_POST['email'], $_POST['event']));
		if($ticketsPerMailCount >= $selectedEvent['tickets_per_email']) {
			throw new Exception(LANG('no_more_reservations_for_this_email'));
		}

		// check voucher if necessary
		$voucherCode = null;
		if($selectedEvent['voucher_only']) {
			$voucher = $db->getVoucherByCode($_POST['voucher_code']??'');
			if(!$voucher) {
				throw new Exception(LANG('invalid_voucher'));
			}
			if($voucher['event_id'] !== null && $voucher['event_id'] !== $selectedEvent['id']) {
				throw new Exception(LANG('voucher_invalid_for_this_event'));
			}
			if(count($db->getTicketsByVoucherCode($voucher['code'])) >= $voucher['valid_amount']) {
				throw new Exception(LANG('voucher_exhausted'));
			}
			$voucherCode = $voucher['code'];
		}

		// generate an unique ticket code and token
		$token = randomString(12);
		do {
			$code = randomString(8);
		} while($db->getTicketByCode($code));

		// save reservation into db
		$db->insertTicket($code, $_POST['event'], $_POST['email'], $token, $voucherCode);

		// send mail to requester
		$mailer = new InvitationMailer();
		$mailer->send(
			$db->getSetting('invitation-mail-subject'), $db->getSetting('invitation-mail-body'),
			$db->getSetting('web-title'), $selectedEvent, $code, $token,
			$_POST['email'], $db->getSetting('invitation-mail-sender-name'), $db->getSetting('invitation-mail-sender-address'), $db->getSetting('invitation-mail-reply-to')
		);

		$showForm = false;
		$info = LANG('reservation_successful');
		$infoClass = 'green';
	} catch(Exception $e) {
		$info = $e->getMessage();
		$infoClass = 'red';
	}
}
?>

<!DOCTYPE html>
<html>
	<head>
		<?php require_once('head.inc.php'); ?>
		<title><?php echo htmlspecialchars($db->getSetting('web-title')); ?> | Tickonix</title>
		<script>
			function toggleVoucher() {
				trVoucher.style.display = sltEvent.options[sltEvent.selectedIndex].getAttribute("voucher_only") ? "table-row" : "none";
			}
		</script>
	</head>
	<body>
		<div id='container'>
			<div id='splash' class='contentbox'>

				<?php foreach(['img/logo-custom.png','img/logo-custom.jpg'] as $file) if(file_exists($file)) { ?>
					<img id='logo' src='<?php echo $file; ?>'>
				<?php } ?>

				<h1><?php echo htmlspecialchars($db->getSetting('web-title')); ?></h1>
				<?php echo $db->getSetting('web-description'); ?>

				<img class='contentbox-embleme' src='img/ticket.svg'>

				<?php if($info) { ?>
					<div class='infobox <?php echo $infoClass; ?>'><?php echo htmlspecialchars($info); ?></div>
				<?php } ?>

				<?php if(!empty($_GET['code']) && !empty($_GET['token']) && empty($_POST['action'])) { ?>
					<?php
					$showForm = false;
					$ticket = $db->getTicketByCode($_GET['code']);
					if($ticket && $ticket['token'] === $_GET['token']) {
					?>
						<form method='POST'>
							<button name='action' value='revoke'><?php echo LANG('revoke_reservation'); ?> (<?php echo htmlspecialchars($_GET['code']); ?>)</button>
						</form>
						<br/>
					<?php } ?>
				<?php } ?>

				<?php if($showForm) { ?>
				<form method='POST' class='reservation'>
					<table id='tblReservation' class='fullwidth'>
						<tr>
							<td><label><?php echo LANG('event'); ?>:</label></td>
							<td class='multiinput'>
								<select id='sltEvent' name='event' required='true' autofocus='true' onchange='toggleVoucher()'>
									<option selected disabled value=''>=== <?php echo LANG('please_select'); ?> ===</option>
									<?php foreach($events as $key => $event) { ?>
										<?php
											$selected = ($_POST['event']??'') === $key;
											$voucherOnly = boolval($event['voucher_only']);
											$addText = ''; $unavail = false;
											$reservedCount = count($db->getValidTickets($key));
											if($reservedCount >= $event['max']) {
												$addText = LANG('booked_out');
												$unavail = true;
											} elseif($event['reservation_start'] && strtotime($event['reservation_start']) > time()) {
												$addText = '('.str_replace('%1', date(DATE_FORMAT.' '.TIME_FORMAT,strtotime($event['reservation_start'])), LANG('bookable_from')).')';
												$unavail = true;
											} elseif($event['reservation_end'] && strtotime($event['reservation_end']) < time()) {
												$addText = '('.str_replace('%1', date(DATE_FORMAT.' '.TIME_FORMAT,strtotime($event['reservation_end'])), LANG('was_bookable_until')).')';
												$unavail = true;
											} else {
												$addText = '('.str_replace('%1', intval($event['max']-$reservedCount), LANG('places_free')).')';
											}
										?>
										<option value='<?php echo htmlspecialchars($key, ENT_QUOTES); ?>' <?php if($unavail) echo 'disabled'; ?> <?php if($selected) echo 'selected'; ?> <?php if($voucherOnly) echo 'voucher_only="true"'; ?>>
											<?php echo htmlspecialchars($event['title']??'???').' '.$addText; ?>
										</option>
									<?php } ?>
								</select>
							</td>
						</tr>
						<tr id='trVoucher' style='display:none'>
							<td><label><?php echo LANG('voucher_code'); ?>:</label></td>
							<td class='multiinput'><input type='text' name='voucher_code' value='<?php echo htmlspecialchars($_POST['voucher_code']??$_GET['voucher_code']??'', ENT_QUOTES); ?>'></td>
						</tr>
						<tr>
							<td><label><?php echo LANG('email'); ?>:</label></td>
							<td class='multiinput'><input type='email' name='email' required='true' value='<?php echo htmlspecialchars($_POST['email']??'', ENT_QUOTES); ?>'></td>
						</tr>
						<tr>
							<td><label><?php echo LANG('captcha'); ?>:</label></td>
							<td class='multiinput captcha'>
								<input type='text' name='captcha' required='true'>
								<img src='captcha.php'>
							</td>
						</tr>
						<tr>
							<td colspan='3'>
								<label class='flex'>
									<input type='checkbox' name='agree' value='1' required='true'>
									<div><?php echo LANG('i_agree_data_store'); ?></div>
								</label>
							</td>
						</tr>
					</table>
					<button><?php echo LANG('reserve_bindingly'); ?></button>
				</form>
				<?php } else { ?>
					<form method='GET'>
						<button><?php echo LANG('new_reservation'); ?></button>
					</form>
				<?php } ?>

			</div>
			<?php require('foot.inc.php'); ?>
		</div>

		<script>
			toggleVoucher();
		</script>
	</body>
</html>
