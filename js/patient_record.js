XDRO = {}

$(function() {
	XDRO.demographics = JSON.parse(XDRO.demographics)
	XDRO.demo_index = XDRO.demographics.length
	$("#demo_instance").text(XDRO.demographics.length + " / " + XDRO.demographics.length)
})

$('body').on('click', '#prev_demo_inst', function (e) {
	XDRO.demo_index -= 1
	if (XDRO.demo_index == 1)
		$(this).attr('disabled', true)
	$("button#next_demo_inst").removeAttr('disabled')
	
	$("#demo_instance").text(XDRO.demo_index + " / " + XDRO.demographics.length)
	XDRO.update_demographics(XDRO.demo_index)
})
$('body').on('click', '#next_demo_inst', function (e) {
	XDRO.demo_index += 1
	if (XDRO.demo_index == XDRO.demographics.length)
		$(this).attr('disabled', true)
	$("button#prev_demo_inst").removeAttr('disabled')
	
	$("#demo_instance").text(XDRO.demo_index + " / " + XDRO.demographics.length)
	XDRO.update_demographics(XDRO.demo_index)
})

XDRO.update_demographics = function(demo_index) {
	var demographics = XDRO.demographics[demo_index-1]
	
	$("#demographics tbody td[data-field]").each(function(i, e) {
		var fieldname = $(e).attr('data-field')
		$(e).text(demographics[fieldname])
	})
	$("#demographics #last_change_time").text(String(demographics["patient_last_change_time"]))
}