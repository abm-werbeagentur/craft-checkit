$(function() {

	const checkitNode = $("#abm-checkit-sidebar");

	if(checkitNode) {
		const position = checkitNode.data("position");
		if(typeof position == "number" && position>0) {
			const statusFieldset = checkitNode.parents(".details").children("*:eq("+ (position-1) +")");

			if(statusFieldset) {
				
				statusFieldset.before(checkitNode);
			}
		}
	}
	
	$("#abm-checkit-sidebar *[data-toggle='collapse'], #abm-checkit-overview *[data-toggle='collapse']").on("click",function() {
		if($($(this).data("target")).length) {
			$($(this).data("target")).toggleClass("in");
		}
	});
});