/**
 * WPCalibrate REST Health & Repair - Admin Interactive Scripts
 */

/* global wpcalibrateRhrData, jQuery */

(function($) {
	'use strict';

	var config = window.wpcalibrateRhrData || {};

	/**
	 * Helper: Sends authenticated REST request.
	 */
	function apiRequest(endpoint, data, method) {
		method = method || 'POST';
		return $.ajax({
			url: config.restBase + endpoint,
			method: method,
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', config.restNonce);
			},
			data: data ? JSON.stringify(data) : null,
			contentType: 'application/json',
			dataType: 'json'
		});
	}

	/**
	 * Updates the summary metrics badges on the Overview tab.
	 */
	function updateSummaryBadges(summary) {
		if (!summary) return;
		$('.badge-healthy .metric-count').text(summary.healthy || 0);
		$('.badge-warning .metric-count').text(summary.warning || 0);
		$('.badge-failed .metric-count').text(summary.failed || 0);
		$('.badge-na .metric-count').text(summary.not_applicable || 0);
	}

	/**
	 * Updates an individual diagnostic card in the DOM.
	 */
	function updateCardDOM(id, result) {
		var $card = $('#diag-card-' + id);
		if (!$card.length) return;

		var status = result.status || 'skipped';
		$card.removeClass('status-healthy status-warning status-failed status-na status-skipped')
			 .addClass('status-' + (status === 'not_applicable' ? 'na' : status));

		var iconClass = 'dashicons-clock';
		if (status === 'healthy') iconClass = 'dashicons-yes-alt';
		else if (status === 'warning') iconClass = 'dashicons-warning';
		else if (status === 'failed') iconClass = 'dashicons-dismiss';
		else if (status === 'not_applicable') iconClass = 'dashicons-minus';

		$card.find('.status-indicator-icon').removeClass('dashicons-yes-alt dashicons-warning dashicons-dismiss dashicons-minus dashicons-clock').addClass(iconClass);
		$card.find('.status-badge').text(status.toUpperCase()).removeClass('status-healthy status-warning status-failed status-na status-skipped').addClass('status-' + (status === 'not_applicable' ? 'na' : status));
		$card.find('.diag-message').text(result.message || '');

		// Evidence update
		if (result.evidence) {
			$card.find('.diag-evidence-details pre').text(JSON.stringify(result.evidence, null, 2));
		}
	}

	$(document).ready(function() {

		// 1. Run Full Scan
		$(document).on('click', '#wpcalibrate-rhr-run-full-scan, #wpcalibrate-rhr-scan-again', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var originalText = $btn.text();

			$btn.prop('disabled', true).addClass('is-busy').text(config.strings.runningScan || 'Running diagnostics...');

			apiRequest('diagnostics/run', {})
				.done(function(res) {
					if (res.summary) updateSummaryBadges(res.summary);
					if (res.results) {
						$.each(res.results, function(id, diagResult) {
							updateCardDOM(id, diagResult);
						});
					}
					// If on overview, reload or update UI smoothly.
					if (window.location.href.indexOf('tab=') === -1 || window.location.href.indexOf('tab=overview') !== -1) {
						window.location.reload();
					}
				})
				.fail(function(xhr) {
					var err = xhr.responseJSON ? xhr.responseJSON.message : (config.strings.errorOccurred || 'Error occurred');
					alert(err);
				})
				.always(function() {
					$btn.prop('disabled', false).removeClass('is-busy').text(originalText);
				});
		});

		// 2. Re-run Failed & Warnings
		$(document).on('click', '#wpcalibrate-rhr-rerun-failed', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var originalText = $btn.text();

			$btn.prop('disabled', true).addClass('is-busy').text(config.strings.runningScan || 'Running diagnostics...');

			apiRequest('diagnostics/rerun-failed', {})
				.done(function(res) {
					if (res.summary) updateSummaryBadges(res.summary);
					if (res.results) {
						$.each(res.results, function(id, diagResult) {
							updateCardDOM(id, diagResult);
						});
					}
				})
				.fail(function(xhr) {
					var err = xhr.responseJSON ? xhr.responseJSON.message : (config.strings.errorOccurred || 'Error occurred');
					alert(err);
				})
				.always(function() {
					$btn.prop('disabled', false).removeClass('is-busy').text(originalText);
				});
		});

		// 3. Single Diagnostic Test Button
		$(document).on('click', '.wpcalibrate-rhr-single-test-btn', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var diagId = $btn.data('diag-id');
			var originalHtml = $btn.html();

			$btn.prop('disabled', true).addClass('is-busy').text('...');

			apiRequest('diagnostics/run', { diagnostic_id: diagId })
				.done(function(res) {
					if (res.result) {
						updateCardDOM(diagId, res.result);
					}
					if (res.summary) {
						updateSummaryBadges(res.summary);
					}
				})
				.fail(function(xhr) {
					var err = xhr.responseJSON ? xhr.responseJSON.message : (config.strings.errorOccurred || 'Error occurred');
					alert(err);
				})
				.always(function() {
					$btn.prop('disabled', false).removeClass('is-busy').html(originalHtml);
				});
		});

		// 4. Auto Repair / Execute Repair Button
		$(document).on('click', '.wpcalibrate-rhr-auto-repair-btn, .wpcalibrate-rhr-execute-repair-btn', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var repairId = $btn.data('repair-id');

			if (!confirm(config.strings.confirmRepair || 'Are you sure you want to run this repair action?')) {
				return;
			}

			var originalText = $btn.text();
			$btn.prop('disabled', true).addClass('is-busy').text(config.strings.runningRepair || 'Executing repair...');

			apiRequest('repairs/execute', { repair_id: repairId })
				.done(function(res) {
					var repairResult = res.repair || {};
					var isFixed = repairResult.status === 'fixed';
					var msg = repairResult.message || '';

					var $resultBox = $btn.closest('.wpcalibrate-rhr-repair-card').find('.repair-result-msg');
					if (!$resultBox.length) {
						$resultBox = $btn.closest('.diag-repair-row').siblings('.diag-message');
					}

					if (isFixed) {
						alert('✓ ' + (config.strings.fixed || 'Fixed') + ': ' + msg);
					} else {
						alert('⚠ ' + (config.strings.notResolved || 'Not Resolved') + ': ' + msg);
					}

					// Refresh full state
					window.location.reload();
				})
				.fail(function(xhr) {
					var err = xhr.responseJSON ? xhr.responseJSON.message : (config.strings.errorOccurred || 'Error occurred');
					alert(err);
				})
				.always(function() {
					$btn.prop('disabled', false).removeClass('is-busy').text(originalText);
				});
		});

		// 5. Rollback Button
		$(document).on('click', '.wpcalibrate-rhr-rollback-btn', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var repairId = $btn.data('repair-id');

			if (!confirm(config.strings.confirmRollback || 'Are you sure you want to revert this repair?')) {
				return;
			}

			$btn.prop('disabled', true).addClass('is-busy').text('Reverting...');

			apiRequest('repairs/rollback', { repair_id: repairId })
				.done(function(res) {
					alert(res.rollback ? res.rollback.message : 'Rollback completed.');
					window.location.reload();
				})
				.fail(function(xhr) {
					var err = xhr.responseJSON ? xhr.responseJSON.message : (config.strings.errorOccurred || 'Error occurred');
					alert(err);
				})
				.always(function() {
					$btn.prop('disabled', false).removeClass('is-busy').text('Revert / Rollback');
				});
		});

	});

})(jQuery);
