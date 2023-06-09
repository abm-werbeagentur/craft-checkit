<?php
/**
 * @link https://abm.at
 * @copyright Copyright (c) abm Feregyhazy & Simon GmbH
*/

namespace abmat\checkit\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\elements\Entry;
use craft\events\DefineHtmlEvent;
use craft\events\ElementEvent;
use craft\events\ModelEvent;
use craft\helpers\ElementHelper;

use craft\commerce\elements\Product as CommerceProduct;
use craft\commerce\Plugin as CommercePlugin;


use abmat\checkit\CheckIt as Plugin;
use abmat\checkit\assets\CPAssets;

use yii\base\InvalidConfigException;

class Sidebar extends Component
{
	public bool $afterSaveRun = false;

	public function renderEntrySidebar(DefineHtmlEvent $event): void
    {
        $entry = $event->sender;

		if (!$entry->getIsFresh() && ElementHelper::isDraftOrRevision($entry)) {
			return;
        }

		if(Plugin::$plugin->getSections()->isInSectionEnabled("sections",$entry->sectionId)) {
			$currentSection = $entry->section;

			if($currentSection && $currentSection->getHasMultiSiteEntries()) {
				$currentUser = Craft::$app->getUser();

				$template_vars = ["checkitSites" => []];

				$siteSettings = $currentSection->getSiteSettings();
				$sites = Craft::$app->getSites()->getAllSites();

				foreach($sites as $site) {
					if(!$currentUser->checkPermission("editSite:" . $site->uid)) {
						continue;
					}
				
					foreach($siteSettings as $siteSetting) {

						$setting_site_id = null;

						try {

							$setting_site_id = $siteSetting->site->id;
						} catch(InvalidConfigException $e) {
							continue;
						}

						if($setting_site_id === null || $siteSetting->site->id != $site->id) {
							continue;
						}

						$template_site = [
							"id" => $siteSetting->site->id,
							"name" => $siteSetting->site->name,
							"status" => false,
						];

						if($siteSetting->site->id == $entry->siteId) {
							$template_site["status"] = true;
						}

						if(!$entry->getIsFresh()) {
							$template_site["status"] = Plugin::$plugin->getEntries()->getEntryCheckitStatus("sections",$entry->id,$siteSetting->site->id);
						}

						$template_vars["checkitSites"][] = $template_site;
					}
				}

				$settings = Plugin::getInstance()->getSettings();

				$template_vars["checkitPosition"] = 0;
				if(isset($settings["positionInEntries"]) && is_numeric($settings["positionInEntries"])) {
					$template_vars["checkitPosition"] = $settings["positionInEntries"];
				}

				$event->html .= $this->_renderEntrySidebarPanel('sidebar',$template_vars);
			}
		}
		return;
    }

	public function hookCommerceProductEditDetails(array &$context): ?string
	{
		if(Plugin::$plugin->getSections()->isInSectionEnabled("productTypes",$context["productType"]->id)) {
			$currentUser = Craft::$app->getUser();
			$template_vars = ["checkitSites" => []];

			$sites = Craft::$app->getSites()->getAllSites();

			foreach($sites as $site) {
				if(!$currentUser->checkPermission("editSite:" . $site->uid)) {
					continue;
				}

				foreach(CommercePlugin::getInstance()->getProductTypes()->getProductTypeSites($context["productType"]->id) as $siteSetting) {

					if(!$siteSetting->hasUrls) {
						continue;
					}

					$setting_site_id = null;

					try {

						$setting_site_id = $siteSetting->site->id;
					} catch(InvalidConfigException $e) {
						continue;
					}

					if($setting_site_id === null || $siteSetting->site->id != $site->id) {
						continue;
					}

					$template_site = [
						"id" => $siteSetting->site->id,
						"name" => $siteSetting->site->name,
						"status" => Plugin::$plugin->getEntries()->getEntryCheckitStatus("productTypes",$context["productId"],$siteSetting->site->id),
					];

					$template_vars["checkitSites"][] = $template_site;
				}
			}

			$settings = Plugin::getInstance()->getSettings();

			$template_vars["checkitPosition"] = 0;
			if(isset($settings["positionInCommmerceProducts"]) && is_numeric($settings["positionInCommmerceProducts"])) {
				$template_vars["checkitPosition"] = $settings["positionInCommmerceProducts"];
			}

			return $this->_renderEntrySidebarPanel('sidebar',$template_vars);
		}

		return "";
	}

	public function onAfterSaveElement(ElementEvent $event): void
    {
		if (!(
			$event->element instanceof Entry || 
			$event->element instanceof CommerceProduct
		)) {
            return;
        }

		$entry = $event->element;

        if ($entry->getIsDerivative()) {
            return;
        }

        $request = Craft::$app->getRequest();
        $action = $request->getBodyParam('action');

		if (!$action || $entry->propagating || $this->afterSaveRun) {
			return;
		}

		// This helps us maintain whether the after-save event has already been triggered for this
		// request, and not to have it run again. This is most commonly caused by Preparse fields
		// which re-save the element again, straight after it's first save. Then we end up with multiple
		// submissions, created each time it's called.
		$this->afterSaveRun = true;
			
		if(in_array($action,[
			"elements/save",
			"elements/apply-draft"
		])) {
			$checkitStatus = $request->getBodyParam('checkitStatus');

			if(!empty($checkitStatus) && is_array($checkitStatus) && isset($checkitStatus["sites"])) {
				
				foreach($checkitStatus["sites"] as $siteid => $changes) {

					if($action == "elements/apply-draft") {
						$changes["old"] = 1;
					}

					if($changes["old"]!=$changes["new"]) {

						if($changes["new"]) {
							Plugin::$plugin->getEntries()->completeCheckitStatus("sections", $entry->id,$siteid);
						} else {
							Plugin::$plugin->getEntries()->incompleteCheckitStatus("sections", $entry->id,$siteid);
						}
					}
				}
			}
		} elseif($action == "commerce/products/save-product") {
			$checkitStatus = $request->getBodyParam('checkitStatus');

			if(!empty($checkitStatus) && is_array($checkitStatus) && isset($checkitStatus["sites"])) {
				foreach($checkitStatus["sites"] as $siteid => $changes) {

					if($changes["old"]!=$changes["new"]) {

						if($changes["new"]) {
							Plugin::$plugin->getEntries()->completeCheckitStatus("productTypes", $entry->id, $siteid);
						} else {
							Plugin::$plugin->getEntries()->incompleteCheckitStatus("productTypes", $entry->id, $siteid);
						}
					}
				}
			}
		}
	}
	
	private function _renderEntrySidebarPanel($template, $template_vars = []): ?string
	{
		Craft::$app->getView()->registerAssetBundle(CPAssets::class);

        return Craft::$app->getView()->renderTemplate('abm-checkit/_sidebar/' . $template, array_merge($template_vars));
	}
}