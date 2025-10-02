<?php
/**
 * @link https://abm.at
 * @copyright Copyright (c) abm Feregyhazy & Simon GmbH
*/

namespace abmat\checkit\elements;

use Craft;
use craft\base\Element;
use craft\commerce\elements\Product as CommerceProduct;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\StringHelper;
use craft\models\Section;

use yii\db\Expression;

use abmat\checkit\CheckIt;
use abmat\checkit\elements\db\ProductQuery as ProductQueryCheckit;
use abmat\checkit\elements\actions\Checked;

class Product extends CommerceProduct {

    /**
     * @inheritdoc
     */
    protected static function includeSetStatusAction(): bool
    {
        return false;
    }

	public static function find(): ProductQueryCheckit
    {
        return new ProductQueryCheckit(static::class);
    }

    protected function cacheTags(): array
    {
        return [
            "productTypeCheckIt:$this->typeId",
        ];
    }

    public static function indexElementCount(ElementQueryInterface $elementQuery, ?string $sourceKey): int
    {
        return count($elementQuery->createCommand()->queryAll());
    }

    protected static function defineSources(string $context = null): array
    {
        $productTypes = \craft\commerce\Plugin::getInstance()->getProductTypes()->getEditableProductTypes();

		$enabledProductTypes = CheckIt::$plugin->getSections()->getAllEnabledProductTypes();

        $productTypeIds = [];

        foreach ($productTypes as $productType) {
            if(!array_key_exists($productType->id,$enabledProductTypes)) {
				continue;
			}

            $productTypeIds[] = $productType->id;
        }

        $sources = [
            [
                'key' => '*',
                'label' => Craft::t('commerce', 'All products'),
                'criteria' => [
                    'typeId' => $productTypeIds,
                    'editable' => true,
                ],
                'defaultSort' => ['elements_sites.title', 'asc'],
            ],
        ];

        foreach ($productTypes as $productType) {
            if(!array_key_exists($productType->id,$enabledProductTypes)) {
				continue;
			}

            $key = 'productType:' . $productType->uid;

            $sources[$key] = [
                'key' => $key,
                'label' => Craft::t('site', $productType->name),
                'data' => [
                    'handle' => $productType->handle,
                    'editable' => true,
                ],
                'criteria' => [
                    'typeId' => $productType->id,
                    'editable' => true
                ],
                'defaultSort' => ['elements_sites.title', 'asc'],
            ];
        }

        return $sources;
    }

    /**
     * @inheritdoc
     */
    public static function actions(string $context): array
    {
        return static::defineActions($context);
    }

    /**
     * @inheritdoc
     */
    protected static function defineActions(string $source = null): array
    {
        $currentUser = Craft::$app->getUser();

        if($currentUser) {
            if($currentUser->checkPermission('abm-checkit-save-status')) {
                return [
                    Checked::class,
                ];
            }
        }

        return [];
    }
}