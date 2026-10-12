<?php
/**
 * Repair execution result value object.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Repairs;

use JsonSerializable;
use WPCalibrate\RESTHealthRepair\Support\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * RepairResult class.
 */
final class RepairResult implements JsonSerializable {

	public const STATUS_FIXED        = 'fixed';
	public const STATUS_NOT_RESOLVED = 'not_resolved';
	public const STATUS_ROLLED_BACK  = 'rolled_back';
	public const STATUS_FAILED       = 'failed';

	private string $repair_id;
	private string $status;
	private string $message;
	private array $planned_changes;
	private array $performed_changes;
	private bool $verified;
	private bool $can_rollback;
	private int $timestamp;
	private array $audit_data;

	/**
	 * Constructor.
	 *
	 * @param string $repair_id Repair ID.
	 * @param string $status Result status (fixed, not_resolved, rolled_back, failed).
	 * @param string $message Short result description.
	 * @param array  $planned_changes What was planned.
	 * @param array  $performed_changes What was actually executed.
	 * @param bool   $verified Whether post-repair diagnostic verification passed.
	 * @param bool   $can_rollback Whether rollback is supported.
	 * @param array  $audit_data Safe metadata for audit trail.
	 */
	public function __construct(
		string $repair_id,
		string $status,
		string $message,
		array $planned_changes = array(),
		array $performed_changes = array(),
		bool $verified = false,
		bool $can_rollback = false,
		array $audit_data = array()
	) {
		$this->repair_id         = sanitize_key( $repair_id );
		$this->status            = $status;
		$this->message           = Sanitizer::redact_secrets( $message );
		$this->planned_changes   = array_map( array( Sanitizer::class, 'redact_secrets' ), $planned_changes );
		$this->performed_changes = array_map( array( Sanitizer::class, 'redact_secrets' ), $performed_changes );
		$this->verified          = $verified;
		$this->can_rollback      = $can_rollback;
		$this->timestamp         = time();
		$this->audit_data        = (array) Sanitizer::sanitize_deep( $audit_data );
	}

	public function get_repair_id(): string {
		return $this->repair_id;
	}

	public function get_status(): string {
		return $this->status;
	}

	public function is_fixed(): bool {
		return self::STATUS_FIXED === $this->status;
	}

	public function get_message(): string {
		return $this->message;
	}

	public function get_planned_changes(): array {
		return $this->planned_changes;
	}

	public function get_performed_changes(): array {
		return $this->performed_changes;
	}

	public function is_verified(): bool {
		return $this->verified;
	}

	public function can_rollback(): bool {
		return $this->can_rollback;
	}

	public function get_timestamp(): int {
		return $this->timestamp;
	}

	public function get_audit_data(): array {
		return $this->audit_data;
	}

	/**
	 * To array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'repair_id'         => $this->repair_id,
			'status'            => $this->status,
			'message'           => $this->message,
			'planned_changes'   => $this->planned_changes,
			'performed_changes' => $this->performed_changes,
			'verified'          => $this->verified,
			'can_rollback'      => $this->can_rollback,
			'timestamp'         => $this->timestamp,
			'audit_data'        => $this->audit_data,
		);
	}

	/**
	 * JsonSerializable implementation.
	 *
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array {
		return $this->to_array();
	}
}
