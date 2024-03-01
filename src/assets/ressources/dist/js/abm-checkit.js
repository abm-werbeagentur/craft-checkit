$(function() {

	const checkitElements = $(".checkit-element[data-position]");

	if(checkitElements) {
		checkitElements.each(function() {
			const position = $(this).data("position");

			if(typeof position == "number" && position>0) {
				const itemElement = $(this).parents(".details").children("*:eq("+ (position-1) +")");

				if(itemElement) {
					itemElement.before($(this));
				}
			}
		});
	}
	
	$("#abm-checkit-sidebar *[data-toggle='collapse'], #abm-checkit-overview *[data-toggle='collapse']").on("click",function() {
		if($(this).parents("#abm-checkit-overview").length) {
			$(this).toggleClass("collapsed");
		}

		if($($(this).data("target")).length) {
			$($(this).data("target")).toggleClass("in");
		}
	});
});