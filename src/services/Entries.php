<?php
/**
 * @link https://abm.at
 * @copyright Copyright (c) abm Feregyhazy & Simon GmbH
*/

namespace abmat\checkit\services;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\models\Site;
use craft\models\Section;
use craft\db\Query;
use craft\elements\Entry;
use craft\events\DeleteSiteEvent;
use craft\events\ElementEvent;
use craft\events\SectionEvent;
use craft\helpers\Db;
use craft\helpers\UrlHelper;

use craft\commerce\Plugin as CommercePlugin;
use craft\commerce\elements\Product as CommerceProduct;

use abmat\checkit\CheckIt as Plugin;
use abmat\checkit\records\EntryRecord;

use yii\caching\TagDependency;

class Entries extends Component
{
	private ?array $_currentUserSections = null;

	public function getEntryCheckitStatus($groupType, $entryId, $siteId): bool
	{
		$EntryRaw = EntryRecord::find()->where([
			"groupType" => $groupType,
			"entryId" => $entryId,
			"siteId" => $siteId,
		])->one();

		return $EntryRaw ? true : false;
	}

	public function getCurrentUserSections(): array
	{
		$currentUser = Craft::$app->getUser();

		if(is_array($this->_currentUserSections)) {
			return $this->_currentUserSections;
		}

		$sectionsWithPermissions = [];
		$enabledSections = Plugin::$plugin->getSections()->getAllEnabledSections();

		foreach(array_keys($enabledSections) as $sectionId) {
			$section = Craft::$app->sections->getSectionById($sectionId);

			if($section) {
				if($currentUser->checkPermission("saveEntries:" . $section->uid)) {
					$sectionsWithPermissions[] = $section;
				}
			}
		}

		$this->_currentUserSections = $sectionsWithPermissions;

		return $sectionsWithPermissions;
	}

	public function getCountAllEntriesForCurrentUser(): int
	{
		$currentUser = Craft::$app->getUser();

		$siteIdsWithPermission = Craft::$app->sites->getEditableSiteIds();

		if(!count($siteIdsWithPermission)) {
			return [];
		}

		$sectionIdsWithPermissions = Craft::$app->sections->getEditableSectionIds();

		$productTypeIdsWithPermission = [];

		$pluginsService = Craft::$app->getPlugins();
		if ($pluginsService->isPluginInstalled('commerce') && $pluginsService->isPluginEnabled('commerce')) {
			$enabledProductTypes = Plugin::$plugin->getSections()->getAllEnabledProductTypes();

			foreach(array_keys($enabledProductTypes) as $productTypeId) {

				$productType = \craft\commerce\Plugin::getInstance()->getProductTypes()->getProductTypeById($productTypeId);

				if($productType) {
					if($currentUser->checkPermission("commerce-editProductType:" . $productType->uid)) {
						$productTypeIdsWithPermission[] = $productTypeId;
					}
				}
			}
		}

		if(!count($sectionIdsWithPermissions) && !count($productTypeIdsWithPermission)) {
			return [];
		}

		$countEntries = 0;

		if(count($sectionIdsWithPermissions)) {
			$queryEntries = (new Query())->from([EntryRecord::$tableName]);

			$queryEntries->join('JOIN', 'entries', 'abm_checkit_entries.entryId = entries.id');

			$queryEntries->where([
				'and',
				['groupType' => 'sections'],
				['in','abm_checkit_entries.siteId', $siteIdsWithPermission],
				['in', 'entries.sectionId', $sectionIdsWithPermissions],
			]);

			$countEntries += $queryEntries->count();
		}

		if(count($productTypeIdsWithPermission)) {

			$queryProducts = (new Query())->from([EntryRecord::$tableName]);

			$queryProducts->join('JOIN', 'commerce_products', 'abm_checkit_entries.entryId = commerce_products.id');

			$queryProducts->where([
				'and',
				['groupType' => 'productTypes'],
				['in','abm_checkit_entries.siteId', $siteIdsWithPermission],
				['in', 'commerce_products.typeId', $productTypeIdsWithPermission],
			]);

			$countEntries += $queryProducts->count();
		}

		return $countEntries;
	}

	public function deleteCheckitStatus($groupType, $entryId, $siteId): void
	{
		Craft::$app->db->createCommand()->delete(
			EntryRecord::$tableName,
			[
				'groupType' => $groupType,
				"entryId" => $entryId,
				"siteId" => $siteId,
			]
		)->execute();
	}

	public function addCheckitStatus($groupType, $entryId, $siteId): void
	{
		if(!$this->getEntryCheckitStatus($groupType, $entryId, $siteId)) {
			$record = new EntryRecord();
			$record->setAttribute('groupType', $groupType);
			$record->setAttribute('entryId', $entryId);
			$record->setAttribute('siteId', $siteId);
			$record->setAttribute('ownerId', Craft::$app->getUser()->id);
			$record->save();
		}
	}

	public function countElementsForSectionSite($sectionId, $siteId) {
		$query = (new Query())->select('count(elements.id) as counter')
			->from('elements')
			->join('JOIN', 'entries', 'elements.id=entries.id and 
				elements.canonicalId is null and 
				elements.draftId is null and 
				elements.revisionId is null and 
				elements.archived = 0 and 
				entries.sectionId = :sectionId',
				[
					"sectionId" => $sectionId
				]
			)
			->join('JOIN', 'elements_sites', 'elements_sites.siteId = :siteId 
				and elementId=elements.id',
				[
					'siteId' => $siteId
				]
			);
		return $query->one()["counter"];
	}

	public function countOutstandingForSectionSite($sectionId, $siteId) {
		$queryEntries = (new Query())->from([EntryRecord::$tableName]);

		$queryEntries->join('JOIN', 'entries', 'abm_checkit_entries.entryId = entries.id');

		$queryEntries->where([
			'and',
			['groupType' => 'sections'],
			['abm_checkit_entries.siteId' => $siteId],
			['entries.sectionId' => $sectionId],
		]);

		return $queryEntries->count();
	}

	public function countElementsForProductTypeSite($productTypeId, $siteId) {
		$query = (new Query())->select('count(*) as counter')
			->from('{{%commerce_products}}')
			->where([
				'typeId' => $productTypeId,
			]);
		return $query->one()["counter"];
	}

	public function countOutstandingForProductTypeSite($productTypeId, $siteId) {
		$queryProducts = (new Query())->from([EntryRecord::$tableName]);

		$queryProducts->join('JOIN', 'commerce_products', 'abm_checkit_entries.entryId = commerce_products.id');

		$queryProducts->where([
			'and',
			['groupType' => 'productTypes'],
			['abm_checkit_entries.siteId' => $siteId],
			['commerce_products.typeId' => $productTypeId],
		]);

		return $queryProducts->count();
	}

	public function afterDeleteSection(SectionEvent $event): void
	{
		$this->deleteTrashedEntries();
	}

	public function afterDeleteElement(ElementEvent $event): void
	{
		if (!(
			$event->element instanceof Entry || 
			$event->element instanceof CommerceProduct
		)) {
			return;
		}

		if($event->element->hardDelete) {
			Craft::$app->db->createCommand()->delete('abm_checkit_entries','entryId = :entryId',['entryId' => $event->element->id]);
		}

		$this->deleteTrashedEntries();
	}

	public function deleteTrashedEntries(): void
	{
		Craft::$app->db->createCommand('delete abm_checkit_entries.* 
			FROM `abm_checkit_entries` 
			left join entries 
				on entries.id=abm_checkit_entries.entryId 
			where abm_checkit_entries.groupType=\'sections\' 
			and entries.id is null'
		)->execute();

		if(Plugin::$plugin->commerceInstalled) {
			Craft::$app->db->createCommand('delete abm_checkit_entries.* 
				FROM `abm_checkit_entries` 
				left join commerce_products 
					on commerce_products.id=abm_checkit_entries.entryId 
				where abm_checkit_entries.groupType=\'productTypes\' 
				and commerce_products.id is null'
			)->execute();
		}
	}

	/**
     * Invalidates caches for the given element.
     *
     * @param ElementInterface $element
     * @since 3.5.0
     */
    public function invalidateCachesForElement(ElementInterface $element): void
    {
        $elementType = get_class($element);

        $tags = [
			"element::$elementType",
            "element::$elementType::*",
            "element::$elementType::$element->id",
        ];

        TagDependency::invalidate(Craft::$app->getCache(), $tags);
    }

	public function removeElement(ElementInterface $element): bool
    {
		Db::delete(EntryRecord::$tableName, [
			"entryId" => $element->id,
			"siteId" => $element->siteId,
		]);

		// Invalidate any caches involving this element
		$this->invalidateCachesForElement($element);

        return true;
    }

	/**
     * get Overview array for a Site
     *
     * @param Site $site
     */
	public function getOverviewForSite(Site $site): array
	{
		$siteList = [
			"id" => $site->id,
			"name" => $site->name,
			"handle" => $site->handle,
			"uid" => $site->uid,
			"sections" => ["singles" => [
				"name" => 'Singles',
				"handle" => 'singles',
				"source" => 'singles',
				"ids" => [],
				"url" => UrlHelper::prependCpTrigger('entries/singles'),
				"amountElements" => 0,
				"amountOutstanding" => 0
			]],
			"productTypes" => [],
		];

		$editableSections = Craft::$app->sections->getEditableSections();

		if(!empty($editableSections)) {
			$enabledSections = Plugin::$plugin->getSections()->getAllEnabledSections();

			foreach($editableSections as $section) {
			
				if(!array_key_exists($section->id,$enabledSections)) {
					continue;
				}

				foreach($section->siteIds as $siteid) {

					if($siteid != $site->id) {
						continue;
					}

					if($section->type == Section::TYPE_SINGLE) {
						$siteList["sections"]["singles"]["ids"][] = $section->id;

					} else {
						$siteList["sections"][] =[
							"name"=>$section->name,
							"handle" => $section->handle,
							"source" => "section:" . $section->uid,
							"ids"=>[$section->id],
							"uid" => $section->uid,
							"amountElements" => 0,
							"amountOutstanding" => 0
						];
					}
				}
			}
		}

		if(Plugin::$plugin->commerceInstalled) {
			$editableProductTypes = CommercePlugin::getInstance()->getProductTypes()->getEditableProductTypes();

			if(!empty($editableProductTypes)) {
				$enabledProductTypes = Plugin::$plugin->getSections()->getAllEnabledProductTypes();

				foreach($editableProductTypes as $productType) {
				
					if(!array_key_exists($productType->id,$enabledProductTypes)) {
						continue;
					}

					foreach(CommercePlugin::getInstance()->getProductTypes()->getProductTypeSites($productType->id) as $siteSetting) {
						
						if($siteSetting->siteId != $site->id) {
							continue;
						}

						if($siteSetting->hasUrls) {
							$siteList["productTypes"][] =[
								"name"=>$productType->name,
								"handle" => $productType->handle,
								"source" => "productType:" . $productType->uid,
								"ids"=>[$productType->id],
								"uid" => $productType->uid,
								"amountElements" => 0,
								"amountOutstanding" => 0
							];
						};
					}
				}
			}
		}

		foreach($siteList["sections"] as $key => &$section) {

			foreach($section["ids"] as $sectionId) {
				$section["amountElements"] += Plugin::$plugin->getEntries()->countElementsForSectionSite($sectionId,$site->id);
				$section["amountOutstanding"] += Plugin::$plugin->getEntries()->countOutstandingForSectionSite($sectionId,$site->id);
			}

			$section["urlOutstanding"] = '/' . UrlHelper::prependCpTrigger("abm-checkit/entries") . '?' . UrlHelper::buildQuery([
				'site' => $site->handle,
				'source' => $section["source"],
			]);

			$section["urlElements"] = '/' . UrlHelper::prependCpTrigger("entries/" . $section["handle"]) . '?' . UrlHelper::buildQuery([
				'site' => $site->handle,
				'source' => $section["source"],
			]);
		}

		if($siteList["sections"]["singles"]["amountElements"]==0) {
			unset($siteList["sections"]["singles"]);
		}

		foreach($siteList["productTypes"] as $key => &$productType) {
			foreach($productType["ids"] as $productTypeId) {
				$productType["amountElements"] += Plugin::$plugin->getEntries()->countElementsForProductTypeSite($productTypeId,$site->id);
				$productType["amountOutstanding"] += Plugin::$plugin->getEntries()->countOutstandingForProductTypeSite($productTypeId,$site->id);
			}

			$productType["urlOutstanding"] = '/' . UrlHelper::prependCpTrigger("abm-checkit/products") . '?' . UrlHelper::buildQuery([
				'site' => $site->handle,
				'source' => $productType["source"],
			]);

			$productType["urlElements"] = '/' . UrlHelper::prependCpTrigger("commerce/products") . '?' . UrlHelper::buildQuery([
				'site' => $site->handle,
				'source' => $productType["source"],
			]);
		}

		return $siteList;
	}

	public function deleteEntriesForSection($section_id):void {
		$deleteEntries = Craft::$app->db->createCommand('delete ' . EntryRecord::$tableName . '.* from ' . EntryRecord::$tableName . ' 
			join entries on 
				abm_checkit_entries.entryId = entries.id
				and abm_checkit_entries.groupType = \'sections\'
				and entries.sectionId = :groupId');
		$deleteEntries->bindParam(':groupId', $section_id);
		$deleteEntries->execute();
	}

	public function deleteEntriesForSectionAndSite($section_id, $site_id):void {
		$deleteEntries = Craft::$app->db->createCommand('delete ' . EntryRecord::$tableName . '.* from ' . EntryRecord::$tableName . ' 
			join entries on 
				abm_checkit_entries.entryId = entries.id
				and abm_checkit_entries.groupType = \'sections\'
				and abm_checkit_entries.siteId = :siteId
				and entries.sectionId = :groupId');
		$deleteEntries->bindParam(':groupId', $section_id);
		$deleteEntries->bindParam(':siteId', $site_id);
		$deleteEntries->execute();
	}

	public function afterDeleteSite(DeleteSiteEvent $event) :void
	{
		$deleteEntries = Craft::$app->db->createCommand('delete from ' . EntryRecord::$tableName . ' 
			where abm_checkit_entries.siteId = :siteId');
		$deleteEntries->bindParam(':siteId', $event->site->id);
		$deleteEntries->execute();
	}

	public function deleteEntriesForProductType($productType_id):void
	{
		$deleteProducts = Craft::$app->db->createCommand('delete ' . EntryRecord::$tableName . '.* from ' . EntryRecord::$tableName . ' 
			join commerce_products on 
				' . EntryRecord::$tableName . '.entryId = commerce_products.id
				and abm_checkit_entries.groupType = \'productTypes\'
				and commerce_products.typeId = :groupId');
		$deleteProducts->bindParam(':groupId', $productType_id);
		$deleteProducts->execute();
	}
}