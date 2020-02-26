
XDRO = {}
XDRO.shrinkSearch = function() {
	$("#search-info").html("<h6><b>Results for:</b></h6>")
	$("#search-info").css('padding', '20px 8px')
	$("#search-info").removeClass('col-4').addClass('col-2')
	$("#search-input").removeClass('col-8').addClass('col-10')
	$(".result-instructions").css('visibility', 'visible')
	$("#file-search").hide()
	$("#results").css('visibility', 'visible')
	$("#error_alert").hide()
}

XDRO.predictPatients = function() {
	var searchBar = $("#search-input input")
	var searchString = searchBar.val()
	
	$("#search-feedback").css('visibility', 'visible')
	
	$.ajax({
		url: XDRO.moduleAddress + "&action=predictPatients&searchString=" + encodeURI(searchString),
		dataType: 'json',
		complete: function(response) {
			XDRO.response = response
			// console.log('response', response)
			if (response.responseJSON && response.responseJSON.length > 0) {
				XDRO.showPredictions(response.responseJSON)
			} else {
				// no results so hide predictions
				$("#autocomplete").css('display', 'none')
			}
			
			$("#search-feedback").css('visibility', 'hidden')
		}
	})
}

XDRO.showPredictions = function(predictions) {
	// console.log('predictions', predictions)
	var items = ""
	
	predictions.forEach(function(patient) {
		items += '<span data-rid="' + patient.patientid + '">"<span class="predict-name">' + patient.patient_first_name + ' ' + patient.patient_last_name + '</span>" in record with Patient ID: # <b>' + patient.patientid + '</b> (<i>DOB: ' + patient.patient_dob + ', ' + patient.patient_current_sex + ', ' + patient.patient_street_address_1 + '</i>)</span>'
	})
	
	$("#autocomplete").html(items)
	$("#autocomplete").css('display', 'flex')
	
	var search = $("#search-input input")
	$("#autocomplete").css('top', search.position().top + search.height() + 'px')
	$("#autocomplete").css('left', search.position().left + 'px')
}

XDRO.submit_manual_query = function() {
	var searchBar = $("#search-input input")
	var searchString = searchBar.val()
	XDRO.query_string = searchString
	$("#search-feedback").css('visibility', 'visible')
	
	$.ajax({
		url: XDRO.moduleAddress + "&action=manualQuery&searchString=" + encodeURI(searchString),
		dataType: 'json',
		complete: function(response) {
			XDRO.response = response
			
			// remove all rows from results table
			var table = $("div#results table").DataTable()
			table.rows().remove()
			
			if (response.responseJSON) {
				var records = response.responseJSON
				if (records.length == 0) {
					$("#error_alert span").text(XDRO.query_string)
					$("#error_alert").show()
				} else {
					XDRO.make_results_table(records)
					XDRO.shrinkSearch()
				}
			}
			
			// update/re-draw results table
			table.draw()
			
			$("#search-feedback").css('visibility', 'hidden')
		}
	})
}

XDRO.make_results_table = function(records) {
	var table = $("div#results table").DataTable()
	
	records.forEach(function (record, i) {
		table.row.add([
			record.patientid,
			record.patient_first_name + " " + record.patient_last_name,
			record.patient_dob,
			record.patient_current_sex,
			record.patient_street_address_1,
			"<input class='cbox' data-rid='" + record.patientid + "' type='checkbox'>",
		])
	})
}

$(function() {
	// autocomplete prediction stuff
	$("#search-input input").on('input', function() {
		clearTimeout(XDRO.predict_timer)
		$("#error_alert").hide()
		XDRO.predict_timer = setTimeout(XDRO.predictPatients, 500)
	})
	
	// forward to patient record on prediction clicked
	$("#autocomplete").on('mousedown', 'span', function(e) {
		var span = $(e.target)
		if (span.hasClass('predict-name')) {
			span = span.parent('span')
		}
		var rid = span.attr('data-rid')
		if (rid) {
			window.location.href = XDRO.recordAddress + "&rid=" + rid
		}
	})
	
	// hide autocomplete predictions
	$("#search-input input").on('blur', function() {$("#autocomplete").hide()})
	
	$("#error_alert").hide()
	
	// make results table a DataTables table
	$("div#results table").DataTable({
		columnDefs: [
			{
				targets: [5],
				orderable: false
			},
			{
				className: "dt-center", 
				targets: "_all"
			}
		],
		pageLength: 15
	});
})
