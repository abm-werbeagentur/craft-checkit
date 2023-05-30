$(function() {
	$("#abm-checkit-sidebar *[data-toggle='collapse']").on("click",function() {
		if($($(this).data("target")).length) {
			$($(this).data("target")).toggleClass("in");
		}
	});
});