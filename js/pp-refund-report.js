/**
 * Author: Potent Plugins
 * License: GNU General Public License version 2 or later
 * License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html
 */
jQuery(document).ready(function($) {
	$('#pp_wcrr_field_report_time').change(function() {
			$('.pp_wcrr_custom_time').toggle(this.value == 'custom');
	});
	$('#pp_wcrr_field_report_time').change();
	
	// Workaround for lack of HTML5 date field support
	$('#pp_wcrr_field_report_start, #pp_wcrr_field_report_end').each(function() {
		if ($(this).prop('type') != 'date') {
			new Pikaday({field: this, format: 'YYYY-MM-DD'});
		}
	});
});