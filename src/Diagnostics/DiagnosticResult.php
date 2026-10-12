<?php
/**
 * Diagnostic result value object.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Diagnostics;

use JsonSerializable;
use WPCalibrate\RESTHealthRepair\Support\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DiagnosticResult class.
 */
final class DiagnosticResult implements JsonSerializable {

	public const STATUS_HEALTHY          = 'healthy';
	public const STATUS_WARNING          = 'warning';
	public const STATUS_FAILED           = 'failed';
	public const STATUS_NOT_APPLICABLE   = 'not_applicable';
	public const STATUS_SKIPPED          = 'skipped';
	public const STATUS_UNABLE_TO_TEST   = 'unable_to_test';

	public const SEVERITY_CRITICAL = 'critical';
	public const SEVERITY_WARNING  = 'warning';
	public const SEVERITY_INFO     = 'info';

	private string $diagnostic_id;
	private string $status;
	private string $message;
	private array $evidence;
	private array $likely_causes;
	private string $impact;
	private string $recommended_action;
	private ?string $repair_id;
	private ?string $manual_instructions;
	private string $severity;
	private int $timestamp;
	private float $duration_ms;

	/**
	 * Constructor.
	 *
	 * @param string      $diagnostic_id Diagnostic identifier.
	 * @param string      $status Status (healthy, warning, failed, not_applicable, skipped, unable_to_test).
	 * @param string      $message Short explanation.
	 * @param array       $evidence Safe technical evidence.
	 * @param array       $likely_causes Bullet list of likely causes.
	 * @param string      $impact Impact description.
	 * @param string      $recommended_action Recommended next step.
	 * @param string|null $repair_id Associated automatic repair ID if one exists.
	 * @param string|null $manual_instructions Step-by-step manual guidance.
	 * @param string      $severity Severity (critical, warning, info).
	 * @param int|null    $timestamp Epoch timestamp.
	 * @param float       $duration_ms Execution duration in milliseconds.
	 */
	public function __construct(
		string $diagnostic_id,
		string $status,
		string $message,
		array $evidence = array(),
		array $likely_causes = array(),
		string $impact = '',
		string $recommended_action = '',
		?string $repair_id = null,
		?string $manual_instructions = null,
		string $severity = self::SEVERITY_INFO,
		?int $timestamp = null,
		float $duration_ms = 0.0
	) {
		$this->diagnostic_id       = sanitize_key( $diagnostic_id );
		$this->status              = $status;
		$this->message             = Sanitizer::redact_secrets( $message );
		$this->evidence            = (array) Sanitizer::sanitize_deep( $evidence );
		$this->likely_causes       = array_map( array( Sanitizer::class, 'redact_secrets' ), $likely_causes );
		$this->impact              = Sanitizer::redact_secrets( $impact );
		$this->recommended_action  = Sanitizer::redact_secrets( $recommended_action );
		$this->repair_id           = $repair_id ? sanitize_key( $repair_id ) : null;
		$this->manual_instructions = $manual_instructions ? Sanitizer::redact_secrets( $manual_instructions ) : null;
		$this->severity            = $severity;
		$this->timestamp           = $timestamp ?? time();
		$this->duration_ms         = round( $duration_ms, 2 );
	}

	public function get_diagnostic_id(): string {
		return $this->diagnostic_id;
	}

	public function get_status(): string {
		return $this->status;
	}

	public function is_healthy(): bool {
		return self::STATUS_HEALTHY === $this->status;
	}

	public function is_failed(): bool {
		return self::STATUS_FAILED === $this->status;
	}

	public function is_warning(): bool {
		return self::STATUS_WARNING === $this->status;
	}

	public function get_message(): string {
		return $this->message;
	}

	public function get_evidence(): array {
		return $this->evidence;
	}

	public function get_likely_causes(): array {
		return $this->likely_causes;
	}

	public function get_impact(): string {
		return $this->impact;
	}

	public function get_recommended_action(): string {
		return $this->recommended_action;
	}

	public function get_repair_id(): ?string {
		return $this->repair_id;
	}

	public function has_automatic_repair(): bool {
		return null !== $this->repair_id && '' !== $this->repair_id;
	}

	public function get_manual_instructions(): ?string {
		return $this->manual_instructions;
	}

	public function get_severity(): string {
		return $this->severity;
	}

	public function get_timestamp(): int {
		return $this->timestamp;
	}

	public function get_duration_ms(): float {
		return $this->duration_ms;
	}

	/**
	 * Serializes to array for JSON/API responses.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'diagnostic_id'       => $this->diagnostic_id,
			'status'              => $this->status,
			'message'             => $this->message,
			'evidence'            => $this->evidence,
			'likely_causes'       => $this->likely_causes,
			'impact'              => $this->impact,
			'recommended_action'  => $this->recommended_action,
			'repair_id'           => $this->repair_id,
			'has_auto_repair'     => $this->has_automatic_repair(),
			'manual_instructions' => $this->manual_instructions,
			'severity'            => $this->severity,
			'timestamp'           => $this->timestamp,
			'duration_ms'         => $this->duration_ms,
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
