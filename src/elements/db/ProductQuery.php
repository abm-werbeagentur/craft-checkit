<?php
namespace abmat\checkit\elements\db;

use craft\commerce\elements\db\ProductQuery as CommerceProductQuery;

class ProductQuery extends CommerceProductQuery {

	protected function beforePrepare(): bool
    {
		$this->query->innerJoin("abm_checkit_entries",'`abm_checkit_entries`.`groupType`="productTypes" and `abm_checkit_entries`.`siteId`=`elements_sites`.`siteId` and `abm_checkit_entries`.`entryId`=`subquery`.`elementsId`');
		
		return parent::beforePrepare();
	}
}