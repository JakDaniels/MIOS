$(document).ready(function() {
	$(".tab1_content").hide(); //Hide all content
	$(".active1").show(); //Show active tab content

	$("ul.tabs1 li").click(function() {
		$("ul.tabs1 li").removeClass("active1"); //Remove any "active" class
		$(this).addClass("active1"); //Add "active" class to selected tab
		$(".tab1_content").hide(); //Hide all tab content
		var a=$(this).find("a");
		var activeTab = a.attr("href"); //Find the href attribute value to identify the active tab + content
		var tabName = a.attr("name");
		$("#tabmem1").val(tabName.substring(3,99));
		$(activeTab).fadeIn(); //Fade in the active ID content
		return false;
	});

	if($(".tab2_content")) {	
		$(".tab2_content").hide(); //Hide all content
		$(".active2").show(); //Show active tab content

		$("ul.tabs2 li").click(function() {
			$("ul.tabs2 li").removeClass("active2"); //Remove any "active" class
			$(this).addClass("active2"); //Add "active" class to selected tab
			$(".tab2_content").hide(); //Hide all tab content
			var a=$(this).find("a");
			var activeTab = a.attr("href"); //Find the href attribute value to identify the active tab + content
			var tabName = a.attr("name");
			$("#tabmem2").val(tabName.substring(3,99));
			$(activeTab).fadeIn(); //Fade in the active ID content
			return false;
		});	
	}
	
	
	
	// stop the form submitting if enter is pressed on one of the input fields
	// instead click the accept button.
	$('.form').find('input').each(function(index){
		$(this).keydown(function(e){
			code= (e.keyCode ? e.keyCode : e.which);
			if(code == 13){
				e.preventDefault();
				$('#update').click();
			}
		});
	});
	

});
