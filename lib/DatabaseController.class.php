<?php

class DatabaseController {

	protected $dbh;
	private $stmt;

	function __construct() {
		try {
			$this->dbh = new PDO(
				DB_TYPE.':host='.DB_HOST.';dbname='.DB_NAME.';',
				DB_USER, DB_PASS,
				array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4')
			);
			$this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch(Exception $e) {
			error_log($e->getMessage());
			throw new Exception('Failed to establish database connection to ›'.DB_HOST.'‹. Gentle panic.');
		}
	}

	public function getEvents() {
		$this->stmt = $this->dbh->prepare('SELECT * FROM event ORDER BY start, id');
		$this->stmt->execute();
		$events = [];
		foreach($this->stmt->fetchAll() as $row) {
			$events[$row['id']] = $row;
		}
		return $events;
	}
	public function insertEvent($id, $title, $max, $start, $end, $location, $voucher_only, $tickets_per_email, $reservation_start, $reservation_end) {
		$this->stmt = $this->dbh->prepare(
			'INSERT INTO event (id, title, max, start, end, location, voucher_only, tickets_per_email, reservation_start, reservation_end)
			VALUES (:id, :title, :max, :start, :end, :location, :voucher_only, :tickets_per_email, :reservation_start, :reservation_end)'
		);
		$this->stmt->execute([
			':id' => $id, ':title' => $title, ':max' => $max,
			':start' => $start, ':end' => $end, ':location' => $location,
			':voucher_only' => $voucher_only, ':tickets_per_email' => $tickets_per_email,
			':reservation_start' => $reservation_start, ':reservation_end' => $reservation_end,
		]);
		return $this->dbh->lastInsertId();
	}
	public function updateEvent($id, $title, $max, $start, $end, $location, $voucher_only, $tickets_per_email, $reservation_start, $reservation_end, $id_old) {
		$this->stmt = $this->dbh->prepare(
			'UPDATE event SET id=:id, title=:title, max=:max, start=:start, end=:end, location=:location, voucher_only=:voucher_only, tickets_per_email=:tickets_per_email, reservation_start=:reservation_start, reservation_end=:reservation_end WHERE id=:id_old'
		);
		$this->stmt->execute([
			':id' => $id, ':title' => $title, ':max' => $max,
			':start' => $start, ':end' => $end, ':location' => $location,
			':voucher_only' => $voucher_only, ':tickets_per_email' => $tickets_per_email,
			':reservation_start' => $reservation_start, ':reservation_end' => $reservation_end,
			':id_old' => $id_old,
		]);
		return $this->dbh->lastInsertId();
	}
	public function deleteEvent($id) {
		$this->stmt = $this->dbh->prepare('DELETE FROM event WHERE id=:id');
		return $this->stmt->execute([':id' => $id]);
	}

	public function getVouchers() {
		$this->stmt = $this->dbh->prepare('SELECT * FROM voucher ORDER BY created DESC');
		$this->stmt->execute();
		$vouchers = [];
		foreach($this->stmt->fetchAll() as $row) {
			$vouchers[$row['code']] = $row;
		}
		return $vouchers;
	}
	public function getVoucherByCode($code) {
		$this->stmt = $this->dbh->prepare('SELECT * FROM voucher WHERE code = :code');
		$this->stmt->execute([':code' => $code]);
		foreach($this->stmt->fetchAll() as $voucher) {
			return $voucher;
		}
	}
	public function insertVoucher($code, $event_id, $valid_amount, $notes='') {
		$this->stmt = $this->dbh->prepare(
			'INSERT INTO voucher (code, event_id, valid_amount, notes)
			VALUES (:code, :event_id, :valid_amount, :notes)'
		);
		$this->stmt->execute([
			':code' => $code, ':event_id' => $event_id, ':valid_amount' => $valid_amount, ':notes' => $notes,
		]);
		return $this->dbh->lastInsertId();
	}
	public function updateVoucher($code, $event_id, $valid_amount, $notes='', $code_old) {
		$this->stmt = $this->dbh->prepare(
			'UPDATE voucher SET code=:code, event_id=:event_id, valid_amount=:valid_amount, notes=:notes WHERE code=:code_old'
		);
		$this->stmt->execute([
			':code' => $code, ':event_id' => $event_id, ':valid_amount' => $valid_amount, ':notes' => $notes,
			':code_old' => $code_old
		]);
		return $this->dbh->lastInsertId();
	}
	public function deleteVoucher($code) {
		$this->stmt = $this->dbh->prepare('DELETE FROM voucher WHERE code=:code');
		return $this->stmt->execute([':code' => $code]);
	}

	public function getTickets($event_id) {
		$this->stmt = $this->dbh->prepare('SELECT * FROM ticket WHERE event_id = :event_id ORDER BY created DESC');
		$this->stmt->execute([':event_id' => $event_id]);
		return $this->stmt->fetchAll();
	}
	public function getValidTickets($event_id) {
		$this->stmt = $this->dbh->prepare('SELECT * FROM ticket WHERE event_id = :event_id AND revoked IS NULL ORDER BY created DESC');
		$this->stmt->execute([':event_id' => $event_id]);
		return $this->stmt->fetchAll();
	}
	public function getTicketByCode($code) {
		$this->stmt = $this->dbh->prepare('SELECT * FROM ticket WHERE code = :code');
		$this->stmt->execute([':code' => $code]);
		foreach($this->stmt->fetchAll() as $ticket) {
			return $ticket;
		}
	}
	public function getTicketsByVoucherCode($voucher_code) {
		$this->stmt = $this->dbh->prepare('SELECT * FROM ticket WHERE voucher_code = :voucher_code');
		$this->stmt->execute([':voucher_code' => $voucher_code]);
		return $this->stmt->fetchAll();
	}
	public function getTicketsByEmailAndEvent($email, $event_id) {
		$this->stmt = $this->dbh->prepare('SELECT * FROM ticket WHERE revoked IS NULL AND email = :email AND event_id = :event_id');
		$this->stmt->execute([':email' => $email, ':event_id' => $event_id]);
		return $this->stmt->fetchAll();
	}
	public function insertTicket($code, $event_id, $email, $token, $voucher_code) {
		$this->stmt = $this->dbh->prepare(
			'INSERT INTO ticket (code, event_id, email, token, voucher_code)
			VALUES (:code, :event_id, :email, :token, :voucher_code)'
		);
		$this->stmt->execute([
			':code' => $code, ':event_id' => $event_id, ':email' => $email, ':token' => $token, ':voucher_code' => $voucher_code,
		]);
		return $this->dbh->lastInsertId();
	}
	public function updateTicketCheckIn($code) {
		$this->stmt = $this->dbh->prepare('UPDATE ticket set checked_in = CURRENT_TIMESTAMP WHERE code = :code');
		$this->stmt->execute([':code' => $code]);
		return $this->dbh->lastInsertId();
	}
	public function updateTicketCheckOut($code) {
		$this->stmt = $this->dbh->prepare('UPDATE ticket set checked_out = CURRENT_TIMESTAMP WHERE code = :code');
		$this->stmt->execute([':code' => $code]);
		return $this->dbh->lastInsertId();
	}
	public function updateTicketRevoked($code) {
		$this->stmt = $this->dbh->prepare('UPDATE ticket set revoked = CURRENT_TIMESTAMP WHERE code = :code');
		$this->stmt->execute([':code' => $code]);
		return $this->dbh->lastInsertId();
	}
	public function resetTicket($code) {
		$this->stmt = $this->dbh->prepare('UPDATE ticket SET checked_in = NULL, checked_out = NULL WHERE code = :code');
		return $this->stmt->execute([':code' => $code]);
	}
	public function deleteTicket($code) {
		$this->stmt = $this->dbh->prepare('DELETE FROM ticket WHERE code = :code');
		return $this->stmt->execute([':code' => $code]);
	}

	public function getSetting($key) {
		$this->stmt = $this->dbh->prepare('SELECT * FROM setting WHERE `key` = :key');
		$this->stmt->execute([':key' => $key]);
		foreach($this->stmt->fetchAll() as $row) {
			return $row['value'];
		}
	}
	public function updateSetting($key, $value) {
		$this->stmt = $this->dbh->prepare('REPLACE INTO setting (`key`, value) VALUES (:key, :value)');
		return $this->stmt->execute([':key' => $key, ':value' => $value]);
	}

}
