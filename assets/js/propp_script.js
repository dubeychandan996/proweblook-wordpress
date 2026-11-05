(function ($) {
	'use strict';

	function escHtml(str) {
		return $('<div>').text(String(str === null || str === undefined ? '' : str)).html();
	}

	function updateBalances(bal) {
		if (!bal || !bal.ok) {
			return;
		}
		if (bal.phone !== null && bal.phone !== undefined) {
			$('#propp-bal-phone').text(Number(bal.phone).toLocaleString());
		}
		if (bal.whatsapp !== null && bal.whatsapp !== undefined) {
			$('#propp-bal-whatsapp').text(Number(bal.whatsapp).toLocaleString());
		}
		if (bal.email !== null && bal.email !== undefined) {
			$('#propp-bal-email').text(Number(bal.email).toLocaleString());
		}
		if (bal.caller !== null && bal.caller !== undefined) {
			$('#propp-bal-caller').text(Number(bal.caller).toLocaleString());
		}
		if (bal.blacklist !== null && bal.blacklist !== undefined) {
			$('#propp-bal-blacklist').text(Number(bal.blacklist).toLocaleString());
		}
	}

	function renderResultCard(res) {
		var $box = $('#propp-message');
		var cls = res.is_valid ? 'is-valid' : 'is-invalid';
		var html = '<strong>' + escHtml(res.verdict) + '</strong> — ' + escHtml(res.summary);

		if (res.details) {
			html += '<table class="propp-result-table"><tbody>';
			$.each(res.details, function (label, val) {
				html += '<tr><td>' + escHtml(label) + '</td><td>' + escHtml(val) + '</td></tr>';
			});
			html += '</tbody></table>';
		}

		$box.removeClass('is-valid is-invalid').addClass(cls).html(html).show();
	}

	$(function () {
		$('#propp-toggle-key').on('click', function () {
			var $inp = $('#PROP_field_propp_api_key');
			var isPwd = $inp.attr('type') === 'password';
			$inp.attr('type', isPwd ? 'text' : 'password');
			$(this).text(isPwd ? 'Hide' : 'Show');
		});

		$('#propp-test-type').on('change', function () {
			var type = $(this).val();
			var placeholders = {
				phone: '+14155552671',
				whatsapp: '+919876543210',
				caller_id: '+14155552671',
				email: 'user@company.com',
				dnsbl: 'stripe.com',
				ip_geo: '8.8.8.8'
			};
			$('#propp-phone').attr('placeholder', placeholders[type] || '+14155552671');
		});

		$('#propp-button-validate').on('click', function (e) {
			e.preventDefault();
			var target = $.trim($('#propp-phone').val());
			var testType = $('#propp-test-type').val() || 'phone';
			var $btn = $(this);
			var $msg = $('#propp-message');

			if (!target.length) {
				$msg.removeClass('is-valid').addClass('is-invalid').text(propp[800] || 'Please enter a value to validate.').show();
				return;
			}

			$btn.prop('disabled', true);

			$.post(
				propp.AJAX_URL,
				{
					action: 'prop_admin_test',
					nonce: propp.admin_nonce,
					test_type: testType,
					target: target
				},
				function (resp) {
					$btn.prop('disabled', false);
					if (resp && resp.success && resp.data) {
						renderResultCard(resp.data);
						updateBalances(resp.data.balances);
						$('#propp-list').prepend(
							'<li><span>' + escHtml(target) + '</span><code>' + escHtml(resp.data.verdict) + '</code></li>'
						);
					} else {
						var errMsg = (resp && resp.data && resp.data.message) ? resp.data.message : (propp[801] || 'Request failed.');
						$msg.removeClass('is-valid').addClass('is-invalid').text(errMsg).show();
					}
				},
				'json'
			).fail(function () {
				$btn.prop('disabled', false);
				$msg.removeClass('is-valid').addClass('is-invalid').text(propp[801] || 'Service unavailable.').show();
			});
		});

		$('#propp-refresh-balances, #propp-clear-cache').on('click', function (e) {
			e.preventDefault();
			var isClear = $(this).attr('id') === 'propp-clear-cache';
			var $btn = $(this);
			var $fb = $('#propp-cache-feedback');

			$btn.prop('disabled', true);
			$.post(
				propp.AJAX_URL,
				{
					action: 'prop_admin_test',
					nonce: propp.admin_nonce,
					test_type: isClear ? 'clear_cache' : 'refresh_balances'
				},
				function (resp) {
					$btn.prop('disabled', false);
					if (resp && resp.success && resp.data) {
						updateBalances(resp.data.balances);
						if (resp.data.message) {
							$fb.text(resp.data.message).show();
						} else {
							$fb.text('Balances refreshed.').show();
						}
					} else if (resp && resp.data && resp.data.message) {
						$fb.text(resp.data.message).show();
					}
				},
				'json'
			).fail(function () {
				$btn.prop('disabled', false);
			});
		});
	});
}(jQuery));
