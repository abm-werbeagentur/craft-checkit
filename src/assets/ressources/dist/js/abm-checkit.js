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

	CheckitSwitches.init();
	
	$("#abm-checkit-sidebar *[data-toggle='collapse'], #abm-checkit-overview *[data-toggle='collapse']").on("click",function() {
		if($(this).parents("#abm-checkit-overview").length) {
			$(this).toggleClass("collapsed");
		}

		if($($(this).data("target")).length) {
			$($(this).data("target")).toggleClass("in");
		}
	});
});

CheckitSwitches = {
	$allCheckitSwitches: null,
	$enabledForAllSitesField: null,
	$enabledForAllSitesSwitch: null,

	init: function() {

		this.$allCheckitSwitches = $("#abm-checkit-sidebar .field.lightswitch-field button.lightswitch");

		if(this.$allCheckitSwitches.length<2) {
			return;
		}
		
		$firstSite = $("#abm-checkit-sidebar .field.lightswitch-field").eq(0);

		this.$enabledForAllSitesField = Craft.ui
		.createLightswitchField({
			label: Craft.t('abm-checkit', 'Indicate for all sites')
		})
		.insertBefore($firstSite);

		this.$enabledForAllSitesField.find('label').css('font-weight', 'bold');
		this.$enabledForAllSitesSwitch = this.$enabledForAllSitesField
			.find('.lightswitch')
			.on("change", this._updateAllSiteStatuses.bind(this));

		this.$allCheckitSwitches.on("change", this._updateAllSiteStatus.bind(this));

		this._updateAllSiteStatus();
	},

	_updateAllSiteStatus: function() {
		let allEnabled = true,
			allDisabled = true;

		this.$allCheckitSwitches.each(function() {
			const enabled = $(this).hasClass("on");
			if (enabled) {
				allDisabled = false;
			} else {
				allEnabled = false;
			}

			if (!allEnabled && !allDisabled) {
				return false;
			}
		});
		if (allEnabled) {
			this.$enabledForAllSitesSwitch.data('lightswitch').turnOn(true);

		  } else if (allDisabled) {
			this.$enabledForAllSitesSwitch.data('lightswitch').turnOff(true);

		  } else {
			this.$enabledForAllSitesSwitch.data('lightswitch').turnIndeterminate(true);
		  }
	},

	_updateAllSiteStatuses: function() {
		const enabled = this.$enabledForAllSitesSwitch.data('lightswitch').on;
		this.$allCheckitSwitches.each(function () {
			if (enabled) {
				$(this).data('lightswitch').turnOn(true);
			} else {
				$(this).data('lightswitch').turnOff(true);
			}
		});
	},
};