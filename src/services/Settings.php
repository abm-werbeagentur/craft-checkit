<?php
namespace abmat\checkit\services;

use Craft;
use craft\base\Component;
use craft\db\Query;

use abmat\checkit\CheckIt;
use abmat\checkit\records\SettingRecord;
use abmat\checkit\records\EntryRecord;

class Settings extends Component {

	private $_enabledSections = null;
	private $_enabledProductTypes = null;

	public function getValidSections()
	{
		return array_filter(
			Craft::$app->sections->getAllSections(),
			[$this, '_hasMultiSiteEntries']
		);
	}

	public function getAllEnabledSections(): array
	{
		if($this->_enabledSections !== null) {
			return $this->_enabledSections;
		}

		$enabledSections = [];

		$settingsRaw = SettingRecord::find()->where([
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

		$settingsRaw = SettingRecord::find()->where([
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
		$pluginsService = Craft::$app->getPlugins();
		if ($pluginsService->isPluginInstalled('commerce') && $pluginsService->isPluginEnabled('commerce')) {

			return \craft\commerce\Plugin::getInstance()->productTypes->getAllProductTypes();
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

		$settingRaw = SettingRecord::find()->where([
			"groupType" => $groupType,
			"groupId" => $groupId
		])->one();

		if($settingRaw) {
			return $settingRaw->enabled;
		}

		return false;
	}

	public function getSettings ()
	{
		$settingRaw = SettingRecord::find()->all();
		$settings = [];

		/** @var SettingRecord $row */
		foreach ($settingRaw as $row)
		{
			if (!array_key_exists($row->groupType, $settings))
				$settings[$row->groupType] = [];

			$settings[$row->groupType][$row->groupId] = $row;
		}

		return $settings;
	}

	public function saveSettings ($data)
	{
		$oldSettings = $this->getSettings();
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
			$settingRows = (new Query())->from([SettingRecord::$tableName])->where(
				['in','id', $idsToDelete],
			)->all();

			foreach($settingRows as $setting_row) {

				switch($setting_row["groupType"]) {
					case "sections":
						$deleteEntries = Craft::$app->db->createCommand('delete ' . EntryRecord::$tableName . '.* from ' . EntryRecord::$tableName . ' 
							join entries on 
								abm_checkit_entries.entryId = entries.id
								and groupType = \'sections\'
								and entries.sectionId = :groupId');
						$deleteEntries->bindParam(':groupId', $setting_row["groupId"]);
						$deleteEntries->execute();
						break;

					case "productTypes":
						$deleteProducts = Craft::$app->db->createCommand('delete ' . EntryRecord::$tableName . '.* from ' . EntryRecord::$tableName . ' 
							join commerce_products on 
								' . EntryRecord::$tableName . '.entryId = commerce_products.id
								and groupType = \'productTypes\'
								and commerce_products.typeId = :groupId');
						$deleteProducts->bindParam(':groupId', $setting_row["groupId"]);
						$deleteProducts->execute();
						break;
				}
			}

			try {
				Craft::$app->db->createCommand()->delete(
					SettingRecord::$tableName,
					['in', 'id', $idsToDelete]
				)->execute();

			} catch (Exception $e) {
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

							$deleteEntries = Craft::$app->db->createCommand('delete ' . EntryRecord::$tableName . '.* from ' . EntryRecord::$tableName . ' 
								join entries on 
									abm_checkit_entries.entryId = entries.id
									and groupType = \'sections\'
									and entries.sectionId = :groupId');
							$deleteEntries->bindParam(':groupId', $new["groupId"]);
							$deleteEntries->execute();
							break;

						case "productTypes":
							$deleteProducts = Craft::$app->db->createCommand('delete ' . EntryRecord::$tableName . '.* from ' . EntryRecord::$tableName . ' 
								join commerce_products on 
									' . EntryRecord::$tableName . '.entryId = commerce_products.id
									and groupType = \'productTypes\'
									and commerce_products.typeId = :groupId');
							$deleteProducts->bindParam(':groupId', $new["groupId"]);
							$deleteProducts->execute();
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
			$record = new SettingRecord();
			$record->setAttribute('groupType', $new['groupType']);
			$record->setAttribute('groupId', $new['groupId']);
			$record->setAttribute('enabled', !!$new['enabled']);
			$record->save();
		}

		return true;
	}

	/**
	 * @param Section $thing
	 *
	 * @return bool
	 */
	private function _hasMultiSiteEntries($thing): bool
	{
		return $thing->getHasMultiSiteEntries();
	} 
}