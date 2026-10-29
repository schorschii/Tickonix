<?php
# Pear mail libs
require('Mail.php');
require('Mail/mime.php');

class InvitationMailer {

	function send(
		string $subject, string $template,
		string $title, array $event, string $code, string $token,
		string $recipient, string $senderName, string $senderMail, string $replyTo=null
	) {
		$mime = new Mail_mime([
			'eol'           => "\r\n",
			'text_encoding' => '7bit',
			'text_charset'  => 'UTF-8',
			'html_charset'  => 'UTF-8',
			'head_charset'  => 'UTF-8',
		]);

		// add calendar event attachment
		$mime->addAttachment(
			ICalendar::generate(
				$title,
				strtotime($event['start']),
				strtotime($event['end']),
				$senderName, ($replyTo ? $replyTo : $senderMail),
				$event['location'],
				60 /*minutes alarm before*/
			),
			'text/calendar', 'calendar-event.ics', false, 'plain'
		);

		// add QR code attachment
		$qrTmpFile = '/tmp/'.$code.'.png';
		$this->generateQrImage($code, $qrTmpFile);
		$qrAttachmentName = 'ticket.png';
		$mime->addHTMLImage($qrTmpFile, 'image/png', $qrAttachmentName, true);

		// set HTML subject and body text
		$vars = [
			'$$TITLE$$' => htmlspecialchars($title),
			'$$EVENT$$' => htmlspecialchars($event['title']),
			'$$START$$' => htmlspecialchars(date(DATE_FORMAT.' '.TIME_FORMAT,strtotime($event['start']))),
			'$$END$$' => htmlspecialchars(date(DATE_FORMAT.' '.TIME_FORMAT,strtotime($event['end']))),
			'$$LOCATION$$' => htmlspecialchars($event['location']),
			'$$CODE$$' => htmlspecialchars($code),
			'$$QRCODE$$' => '<img src="'.htmlspecialchars($qrAttachmentName, ENT_QUOTES).'">',
			'$$REVOKELINK$$' => (!empty($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['SERVER_NAME'].dirname($_SERVER['SCRIPT_NAME']).'/index.php'
				.'?action=revoke&code='.urlencode($code).'&token='.urlencode($token),
		];
		$subject = $this->processTemplate($subject, $vars);
		$mime->setHTMLBody($this->processTemplate($template, $vars));
		#$mime->setTXTBody('...');

		// compile mail headers
		$headers = [
			'From' => $senderMail,
			'Subject' => '=?UTF-8?B?'.base64_encode($subject).'?=',
		];
		if($replyTo) $headers['Reply-To'] = $replyTo;

		// send mail
		$mail = Mail::factory('sendmail');
		$mail->send($recipient,
			$mime->headers($headers),
			$mime->get()
		);
	}

	private function generateQrImage($code, $tmpFile) {
		$qrGenerator = new QRCode($code);
		$qrImage = $qrGenerator->render_image();
		imagepng($qrImage, $tmpFile);
		#$imageData = file_get_contents($tmpFile);
		#$imageBase64 = base64_encode(file_get_contents($tmpFile));
	}

	private function processTemplate(string $template, array $vars) {
		foreach($vars as $key => $value) {
			$template = str_replace($key, $value, $template);
		}
		return $template;
	}

}
