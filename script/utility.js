// Simple JavaScript to change all carriage returns to <br><br>
var submission = document.getElementById("submit");
submission.addEventListener("click", makeBR, false);

function makeBR(){
	
	if(document.getElementById("new").value !== " " && document.getElementById("new").value !== ""){
	  document.getElementById("new").value = document.getElementById("new").value.replace(/[\n\r]/g, '<br>');
	}

	if(document.getElementById("edit").value !== " " && document.getElementById("edit").value !== ""){
	  document.getElementById("edit").value = document.getElementById("edit").value.replace(/[\n\r]/g, '<br>');
	}

}
    

