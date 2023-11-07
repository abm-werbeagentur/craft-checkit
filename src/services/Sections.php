<?php
/**
 * @link https://abm.at
 * @copyright Copyright (c) abm Feregyhazy & Simon GmbH
*/

namespace abmat\checkit\services;

use Craft;
use craft\base\Component;
use craft\events\SectionEvent;
use craft\db\Query;

use abmat\checkit\CheckIt;
use abmat\checkit\records\SectionRecord;
use abmat\checkit\records\EntryRecord;

class Sections extends Component {

	private $_enabledSections = null;
	private $_enabledProductTypes = null;

	public function getValidSections()
	{
		return Craft::$app->sections->getAllSections();
	}

	public function getAllEnabledSections(): array
	{
		if($this->_enabledSections !== null) {
			return $this->_enabledSections;
		}

		$enabledSections = [];

		$settingsRaw = SectionRecord::find()->where([
			"groupType" => "sections",
			"enabled" => 1,
		])->all();

		foreach($settingsRaw as $setting_row) {
			$enabledSections[$setting_row->groupId] = 1;
		}

		$this->_enabledSections = $enabledSections;

		return $enabledSections;
	}

	public function getAllEnabledProductTypes(): array
	{
		if($this->_enabledProductTypes !== null) {
			return $this->_enabledProductTypes;
		}

		$enabledProductTypes = [];

		$settingsRaw = SectionRecord::find()->where([
			"groupType" => "productTypes",
			"enabled" => 1,
		])->all();

		foreach($settingsRaw as $setting_row) {
			$enabledProductTypes[$setting_row->groupId] = 1;
		}

		$this->_enabledProductTypes = $enabledProductTypes;

		return $enabledProductTypes;
	}

	public function getValidProductTypes()
	{
		if(class_exists(\craft\commerce\Plugin::class)) {
			$pluginsService = Craft::$app->getPlugins();
			if ($pluginsService->isPluginInstalled('commerce') && $pluginsService->isPluginEnabled('commerce')) {

				return \craft\commerce\Plugin::getInstance()->productTypes->getAllProductTypes();
			}
		}

		return [];
	}

	public function isInSectionEnabled($groupType, $groupId): bool
	{
		if($groupType == "productTypes") {
			if($this->_enabledProductTypes !== null) {
				return array_key_exists($groupId,$this->_enabledProductTypes);
			}
		} else {
			if($this->_enabledSections !== null) {
				return array_key_exists($groupId,$this->_enabledSections);
			}
		}

		$settingRaw = SectionRecord::find()->where([
			"groupType" => $groupType,
			"groupId" => $groupId
		])->one();

		if($settingRaw) {
			return $settingRaw->enabled;
		}

		return false;
	}

	public function getSections ()
	{
		$settingRaw = SectionRecord::find()->all();
		$settings = [];

		/** @var SectionRecord $row */
		foreach ($settingRaw as $row)
		{
			if (!array_key_exists($row->groupType, $settings))
				$settings[$row->groupType] = [];

			$settings[$row->groupType][$row->groupId] = $row;
		}

		return $settings;
	}

	public function saveSections ($data)
	{
		$oldSettings = $this->getSections();
		$newSettings = $data;

		// Delete removed rows
		// ---------------------------------------------------------------------
		$newById = [];
		$oldById = [];

		$newRecordsRaw = [];

		foreach ($newSettings as $group => $rows)
		{
			foreach ((array)$rows as $groupid => $new)
			{
				if (!is_array($new)) {
					continue;
				}

				$new['groupType'] = $group;
				$new["groupId"] = $groupid;

				if (!array_key_exists('id', $new)) {
					continue;
				}

				if ($new['id'] !== '-1') {
					$newById[$new['id']] = $new;
				} else {
					$newRecordsRaw[] = $new;
				}
			}
		}
		
		$idsToDelete = [];

		foreach ($oldSettings as $group => $rows)
		{
			foreach ($rows as $old)
			{
				if (array_key_exists($old['id'], $newById)) {
					$oldById[$old['id']] = $old;
				} else {
					$idsToDelete[] = $old['id'];
				}
			}
		}

		if (!empty($idsToDelete))
		{
			$settingRows = (new Query())->from([SectionRecord::$tableName])->where(
				['in','id', $idsToDelete],
			)->all();

			foreach($settingRows as $setting_row) {

				switch($setting_row["groupType"]) {
					case "sections":
						Checkit::$plugin->getEntries()->deleteEntriesForSection($setting_row["groupId"]);
						break;

					case "productTypes":
						Checkit::$plugin->getEntries()->deleteEntriesForProductType($setting_row["groupId"]);
						break;
				}
			}

			try {
				Craft::$app->db->createCommand()->delete(
					SectionRecord::$tableName,
					['in', 'id', $idsToDelete]
				)->execute();

			} catch (\Exception $e) {
				checkit::error($e->getMessage());
				return false;
			}
		}

		// Update current rows
		// ---------------------------------------------------------------------
		foreach ($newById as $new)
		{
			$old = $oldById[$new['id']];

			if (
				$old['groupId'] !== $new['groupId'] ||
				$old['enabled'] !== !!$new['enabled']
			) {
				$old->setAttribute('groupId', $new['groupId']);
				$old->setAttribute('enabled', !!$new['enabled']);
				$old->save();

				if(!$new['enabled']) {

					switch($new["groupType"]) {
						case "sections":
							Checkit::$plugin->getEntries()->deleteEntriesForSection($new["groupId"]);
							break;

						case "productTypes":
							Checkit::$plugin->getEntries()->deleteEntriesForProductType($new["groupId"]);
							break;

						default:
							break;
					}
				}
			}
		}

		// Add new rows
		// ---------------------------------------------------------------------
		foreach ($newRecordsRaw as $new)
		{
			$record = new SectionRecord();
			$record->setAttribute('groupType', $new['groupType']);
			$record->setAttribute('groupId', $new['groupId']);
			$record->setAttribute('enabled', !!$new['enabled']);
			$record->save();
		}

		return true;
	}

	public function afterDeleteSection(SectionEvent $event) :void
	{
		Craft::$app->db->createCommand()->delete(
			SectionRecord::$tableName,
			['id' => $event->section->id]
		)->execute();

		Checkit::$plugin->getEntries()->deleteEntriesForSection($event->section->id);
	}

	public function afterSaveSection(SectionEvent $event) :void
	{
		if($event->isNew) {
			return;
		}

		if(!array_key_exists($event->section->id,$this->getAllEnabledSections())) {
			return;
		}

		$enabledSitesForSection = [];
		foreach($event->section->getSiteSettings() as $sitesetting) {
			$enabledSitesForSection[] = $sitesetting->siteId;
		}

		foreach(Craft::$app->sites->getAllSiteIds(true) as $siteid) {
			if(!in_array($siteid,$enabledSitesForSection)) {
				Checkit::$plugin->getEntries()->deleteEntriesForSectionAndSite($event->section->id,$siteid);
			}
		}
	}
}