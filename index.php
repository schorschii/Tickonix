<?php
require_once('loader.inc.php');
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
			throw new Exception('Ungültiger Token.');
		}
		if($_POST['action'] === 'revoke') {
			$db->updateTicketRevoked($_GET['code']);
			$info = 'Das Ticket wurde storniert und der Platz wieder freigegeben. Bitte löschen Sie die zugehörige E-Mail.';
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
			throw new Exception('Die angeforderte Veranstaltung existiert nicht.');
		}
		$selectedEvent = $events[$_POST['event']];

		// check captcha
		if(!isset($_SESSION['captcha_text']) || $_POST['captcha'] !== $_SESSION['captcha_text']) {
			throw new Exception('Das eingegebene Captcha ist nicht korrekt. Bitte erneut versuchen.');
		}
		// invalidate captcha - can only be used once
		unset($_SESSION['captcha_text']);

		// check reservation time window
		if(($selectedEvent['reservation_start'] && strtotime($selectedEvent['reservation_start']) > time())
		|| ($selectedEvent['reservation_end'] && strtotime($selectedEvent['reservation_end']) < time())) {
			throw new Exception('Die angeforderte Veranstaltung kann aktuell nicht gebucht werden.');
		}

		// check if there are still places free
		$tickets = $db->getValidTickets($_POST['event']);
		if(count($tickets) >= $selectedEvent['max']) {
			throw new Exception('Die angeforderte Veranstaltung ist leider bereits ausgebucht.');
		}

		// check email syntax
		if(!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
			throw new Exception('Ihre eingegebene E-Mailadresse ist ungültig.');
		}
		// if duplicate email addresses are not allowed, check if email address is already registered
		$ticketsPerMailCount = count($db->getTicketsByEmailAndEvent($_POST['email'], $_POST['event']));
		if($ticketsPerMailCount >= $selectedEvent['tickets_per_email']) {
			throw new Exception('Für die eingegebene E-Mailadresse können keine weiteren Tickets reserviert werden.');
		}

		// check voucher if necessary
		$voucherCode = null;
		if($selectedEvent['voucher_only']) {
			$voucher = $db->getVoucherByCode($_POST['voucher_code']??'');
			if(!$voucher) {
				throw new Exception('Der eingegebene Voucher-Code ist ungültig.');
			}
			if($voucher['event_id'] !== null && $voucher['event_id'] !== $selectedEvent['id']) {
				throw new Exception('Dieser Voucher ist nicht für diese Veranstaltung gültig.');
			}
			if(count($db->getTicketsByVoucherCode($voucher['code'])) >= $voucher['valid_amount']) {
				throw new Exception('Der eingegebene Voucher-Code ist bereits ausgeschöpft.');
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
		$info = 'Reservierung erfolgreich. Die E-Mail mit dem Eintritts-Code ist unterwegs!';
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
		<title><?php echo htmlspecialchars($db->getSetting('web-title')); ?> | Tickets</title>
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
							<button name='action' value='revoke'>Reservierung stornieren (<?php echo htmlspecialchars($_GET['code']); ?>)</button>
						</form>
						<br/>
					<?php } ?>
				<?php } ?>

				<?php if($showForm) { ?>
				<form method='POST' class='reservation'>
					<table id='tblReservation'>
						<tr>
							<td><label>Veranstaltung:</label></td>
							<td class='multiinput'>
								<select id='sltEvent' name='event' required='true' autofocus='true' onchange='toggleVoucher()'>
									<option selected disabled value=''>=== Bitte auswählen ===</option>
									<?php foreach($events as $key => $event) { ?>
										<?php
											$selected = ($_POST['event']??'') === $key;
											$voucherOnly = boolval($event['voucher_only']);
											$addText = ''; $soldOut = false; $outOfTime = false;
											$reservedCount = count($db->getValidTickets($key));
											if($reservedCount >= $event['max']) {
												$addText = 'AUSGEBUCHT!';
												$soldOut = true;
											} else {
												$addText = '('.($event['max']-$reservedCount).' Plätze verfügbar)';
											}
											if(($event['reservation_start'] && strtotime($event['reservation_start']) > time())
											|| ($event['reservation_end'] && strtotime($event['reservation_end']) < time())) {
												$outOfTime = true;
											}
										?>
										<option value='<?php echo htmlspecialchars($key, ENT_QUOTES); ?>' <?php if($soldOut||$outOfTime) echo 'disabled'; ?> <?php if($selected) echo 'selected'; ?> <?php if($voucherOnly) echo 'voucher_only="true"'; ?>>
											<?php echo htmlspecialchars($event['title']??'???').' '.$addText; ?>
										</option>
									<?php } ?>
								</select>
							</td>
						</tr>
						<tr id='trVoucher' style='display:none'>
							<td><label>Voucher-Code:</label></td>
							<td class='multiinput'><input type='text' name='voucher_code' value='<?php echo htmlspecialchars($_POST['voucher_code']??$_GET['voucher_code']??'', ENT_QUOTES); ?>'></td>
						</tr>
						<tr>
							<td><label>E-Mail-Adresse:</label></td>
							<td class='multiinput'><input type='email' name='email' required='true' value='<?php echo htmlspecialchars($_POST['email']??'', ENT_QUOTES); ?>'></td>
						</tr>
						<tr>
							<td><label>Captcha:</label></td>
							<td class='multiinput captcha'>
								<input type='text' name='captcha' required='true'>
								<img src='captcha.php'>
							</td>
						</tr>
						<tr>
							<td colspan='3'>
								<label class='flex'>
									<input type='checkbox' name='agree' value='1' required='true'>
									<div>Ich stimme zu, dass diese Daten bis zum Zeitraum der Veranstaltung gespeichert werden.</div>
								</label>
							</td>
						</tr>
					</table>
					<button>Verbindlich reservieren</button>
				</form>
				<?php } else { ?>
					<form method='GET'>
						<button>Neue Reservierung</button>
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
