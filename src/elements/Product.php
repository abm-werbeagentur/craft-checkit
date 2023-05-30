<?php
namespace abmat\checkit\elements;

use Craft;
use craft\base\Element;
use craft\commerce\elements\Product as CommerceProduct;
use craft\helpers\StringHelper;
use craft\models\Section;

use abmat\checkit\CheckIt;
use abmat\checkit\elements\db\ProductQuery;
use abmat\checkit\elements\actions\Checked;

class Product extends CommerceProduct {

    /**
     * @inheritdoc
     */
    protected static function includeSetStatusAction(): bool
    {
        return false;
    }

	public static function find(): ProductQuery
    {
        return new ProductQuery(static::class);
    }

    protected static function defineSources(string $context = null): array
    {
        $productTypes = \craft\commerce\Plugin::getInstance()->getProductTypes()->getEditableProductTypes();

		$enabledProductTypes = CheckIt::$plugin->getSettings()->getAllEnabledProductTypes();

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
                'defaultSort' => ['postDate', 'desc'],
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