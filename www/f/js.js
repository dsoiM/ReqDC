
var csrftoken;
var successSnackbarTimeout = 3000;
var failureSnackbarTimeout = 6000;

$(document).ready(function () {


	$('#requestsTable').dataTable({
		"paging": false,
		"order": [[ 2, "desc" ]]
	});


	$('#queueTable').dataTable({
		"paging": false,
		"order": [[ 0, "desc" ]]
	});

	$('.editMe').on('click mouseover change keyup keydown paste cut', 'textarea', function (){
		$(this).height(0).height(this.scrollHeight);
	}).find( 'textarea' ).change();

	csrftoken = $('#CSRFTOKEN').val();



});

function changeTenant(x) {
	var newT = x.attributes.value.value;

	window.location = "/?changeTenant="+encodeURIComponent(newT);
	window.reload();
}

function toggleSelectAllCheckboxes() {
	$('.mdl-checkbox > input').each(function( index ) {
		if (this.checked == true) {
			this.parentElement.MaterialCheckbox.uncheck()    
		} else {
			this.parentElement.MaterialCheckbox.check()
		}

	});
	refreshExecutionHandlerButtons();
}



function refreshExecutionHandlerButtons() {
	// If at least one checked
	if(getSelectedExecutionCheckboxes().length > 0) {

		$( "#checkboxActions" ).removeClass( "hide" );
	} else {
		$( "#executionHandlerButtons" ).addClass( "hide" ) ;
		$( "#executionHandlerButtons" ).slideUp(400);
		$( "#checkboxActions" ).addClass( "hide" );
	}
}

function checkboxActionsButtonPress() {
	$( "#executionHandlerButtons" ).slideDown(400);
	$( "#executionHandlerButtons" ).removeClass( "hide" );
}

function getSelectedExecutionCheckboxes() {
	return $(".executioncheckbox:checkbox:checked");
}


function getBoolean(value){
	switch(value){
		case true:
		case "true":
		case 1:
		case "1":
		case "on":
		case "yes":
			return true;
		default: 
			return false;
	}
}

// This is used from UIimplementations view to start test
function startExec() {

	var tenantId = $('#tenantname').attr('tenantid');
	var implId = $('#implname').attr('implementationid');
	var synchronous = getBoolean($("input[id='synchronousImpl']:checked").val())
	var host = $('#APIURL').val();
	var url = (host+'/' + tenantId + '/' + implId+'?synchronous='+synchronous);
	var data = $('#postContent').val();
	var contentType = $("input[name='contenttype']:checked").val()
	$('#responseContent').text('Sending...');

	$.ajax({
		type : "POST",
		url : url,
		data : data,
		headers: {"CSRFTOKEN": csrftoken},
		complete : complete,
		contentType : contentType,
		xhrFields: {
			withCredentials: true
		},
	});

}

// When exec is complete
function complete(resp) {
	$('#responseContent').text( new Date().toISOString()+' : '+ resp.status + ' : ' +resp.responseText);

}

function getafterChar(str, char) {
	return str.substr(str.indexOf(char) + 1)
}


function getCookie(cname) {
	var name = cname + "=";
	var decodedCookie = decodeURIComponent(document.cookie);
	var ca = decodedCookie.split(';');
	for(var i = 0; i <ca.length; i++) {
		var c = ca[i];
		while (c.charAt(0) == ' ') {
			c = c.substring(1);
		}
		if (c.indexOf(name) == 0) {
			return c.substring(name.length, c.length);
		}
	}
	return "";
}


function rerunExec(execid) {
	var synchronous = getBoolean($("input[id='synchronous']:checked").val())
	postToControlAPI('execution/rerun?synchronous='+encodeURIComponent(synchronous),{execid:execid,},rerunExecComplete)

}


function rerunExecComplete(resp) {

	$.when( document.getElementById(currentExecCheckboxID).parentElement.MaterialCheckbox.uncheck() ).then( function() {
		var curhek = $("#"+currentExecCheckboxID).parent();
		var parsed = JSON.parse(resp.responseText);
		if (parsed.executionId) {
			curhek.append( '<a href="/executions/'+parsed.executionId+'">' + resp.status + "</a>" );

		} else if (parsed.requestId) {
			curhek.append( '<a href="/requests/'+parsed.requestId+'">' + resp.status + "</a>" );
		} else {
			curhek.append( "<span>" + resp.status + "</span>" );
		}
		curhek.attr('title',resp.responseText);
		// Only continue if status was 200
		if (resp.status === 200) {
			rerunbuttonpress();
		}

	});

}

var dataStorageElem;


function postToControlAPI(urlEnd, data, completeFunction) {
    var tenantId = $('#tenantname').attr('tenantid');
    var host = $('#APIURL').val();
    var url = (host+'/' + tenantId + '/control/'+ urlEnd);

    
    
    $.ajax({
        type : "POST",
        url : url,
        data : JSON.stringify(data),
        complete : completeFunction,
        dataType : 'json',
        headers: {"CSRFTOKEN": csrftoken},
        contentType : 'application/json',
        xhrFields: {
            withCredentials: true
        },
    });
}

function dataStorageSet(elem, undo) {
	var key = elem.element.previousElementSibling.innerText
	var category = elem.element.previousElementSibling.previousElementSibling.innerText
	var type = elem.element.getAttribute("datatype");
	var data;

	if (undo === true) {
		data = elem.oldValue;
	} else {
		data = elem.newValue;
	}

	dataStorageElem = elem;
	postToControlAPI('datastorage/set',{value:data,category:category,key:key,upsert:false,type:type},dataStorageSetComplete);
}

function trimProper(str) {
	return str.replace(/^[\n\s]*|[\n\s]*$/g, '');

}


var dataStorageSnackUndoHandler = function(event) {
	dataStorageSet(dataStorageElem,true);
	dataStorageElem.element.textContent = dataStorageElem.oldValue;
};


function dataStorageSetComplete(resp) {


	if (resp.status === 200) {
		var data = {
				message: 'Value saved',
				timeout: successSnackbarTimeout,
				actionHandler: dataStorageSnackUndoHandler,
				actionText: 'Undo'
		};
	} else {
		var data = {
				message: 'Failed to save '+resp.status +' '+resp.responseText,
				timeout: failureSnackbarTimeout,
		};

	}


	var snackbarContainer = document.querySelector('#genericSnackbar');
	snackbarContainer.MaterialSnackbar.showSnackbar(data);

}


var Zcategory;
var Zkey;
var Zdata;
var Ztype;
function addNewDataStorageObjectSave() {
	
	var formData = $('#newDataStorageEntry').serializeArray();	
	Zcategory = "";
	Zkey = "";
	Zdata = "";
	Ztype = ""
	try {
		Zcategory = formData[0].value; 
		Zkey = formData[1].value
		Ztype = formData[2].value
		Zdata = formData[3].value
	} catch (e) {
		
	}
	postToControlAPI('datastorage/set',{value:Zdata,type:Ztype,upsert:true, key:Zkey,category:Zcategory},addNewDataStorageObjectSaveComplete);
}



function submitFormWithAjax(formId,url,completeFunction) {
    postToControlAPI(url,$(formId).serializeArray(),completeFunction)
}

function passwordResetComplete(resp) {
	
	if (resp.status === 200) {
		var data = {
				message: 'Password updated',
				timeout: successSnackbarTimeout,
		};
		$('#passwordResetForm')[0].reset();
	} else {
		var data = {
				message: 'Failed to update '+resp.status +' '+resp.responseText,
				timeout: failureSnackbarTimeout,
		};

	}

	

	var snackbarContainer = document.querySelector('#genericSnackbar');
	snackbarContainer.MaterialSnackbar.showSnackbar(data);



}

function refreshPage() {
	window.location.href=window.location.href;
}

function cronExpressionSet(implementationId,cronExpr) {
	
	postToControlAPI('schedule/setcronschedule',{implementationId:implementationId,cronExpr:cronExpr},cronExpressionSetComplete);
}

function cronExpressionSetComplete(resp){

	if (resp.status === 200) {
		
		var snackbarmessage = {
				message: 'Value saved',
				timeout: successSnackbarTimeout,
				actionHandler: refreshPage,
				actionText: 'Refresh'
		};

		
	} else {
		var snackbarmessage = {
				message: 'Failed to save '+resp.status +' '+resp.responseText,
				timeout: failureSnackbarTimeout,
		};

	}
	var snackbarContainer = document.querySelector('#genericSnackbar');
	snackbarContainer.MaterialSnackbar.showSnackbar(snackbarmessage);
	$('#dialog')[0].close();
	

}



function addNewDataStorageObjectSaveComplete(resp) {
	
	if (resp.status === 200) {
		$('#dataStorageTable').DataTable().row.add([Zcategory,Zkey,Zdata]).draw(false);
		document.querySelector('#dialog').close();
		
		var snackbarmessage = {
				message: 'Value saved',
				timeout: successSnackbarTimeout,
				actionHandler: refreshPage,
				actionText: 'Refresh'
		};

		
	} else {
		var snackbarmessage = {
				message: 'Failed to save '+resp.status +' '+resp.responseText,
				timeout: failureSnackbarTimeout,
		};

	}

	var snackbarContainer = document.querySelector('#genericSnackbar');
	snackbarContainer.MaterialSnackbar.showSnackbar(snackbarmessage);
	
}


var currentExecCheckboxID;

function rerunbuttonpress(x) {
	currentExecCheckboxID = getSelectedExecutionCheckboxes().first().attr('id')

	if (currentExecCheckboxID) {
		var excid = getafterChar(currentExecCheckboxID,"_")
		rerunExec(excid);
	} else {
		refreshExecutionHandlerButtons();
	}

}



function getUrlParameter(name) {
	name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
	var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
	var results = regex.exec(location.search);
	return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
};

function removeExecutionsImplementationFilter() {
	$('input[name="implementationFilter"]').attr('value','')
	updateExecutionsResults();
}
function updateExecutionsResults() {
	document.getElementById("datepickerform").submit();

}
