<?php
/**
 * @link https://abm.at
 * @copyright Copyright (c) abm Feregyhazy & Simon GmbH
*/

namespace abmat\checkit\elements;

use Craft;
use craft\base\Element;
use craft\elements\Entry as CraftEntry;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\StringHelper;
use craft\models\Section;

use abmat\checkit\CheckIt;
use abmat\checkit\elements\db\EntryQuery as EntryQueryCheckit;
use abmat\checkit\elements\actions\Checked;

class Entry extends CraftEntry
{
	/**
     * Properties
     */
	public static function displayName(): string
	{
		return Craft::t('abm-checkit','Entries');
	}

    /**
     * @inheritdoc
     */
    public static function lowerDisplayName(): string
    {
        return StringHelper::toLowerCase(static::displayName());
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('app', 'Entries');
    }

    /**
     * @inheritdoc
     */
    public static function pluralLowerDisplayName(): string
    {
        return StringHelper::toLowerCase(static::pluralDisplayName());
    }

	/**
     * @inheritdoc
     */
    public static function sources(string $context): array
    {
        return static::defineSources($context);
    }

    protected static function defineSources(string $context): array
    {
		$sectionIds = [];
		$singleSectionIds = [];
		$sectionsByType = [];

		$sections = Craft::$app->getEntries()->getEditableSections();

		$enabledSections = CheckIt::$plugin->getSections()->getAllEnabledSections();

        foreach ($sections as $section) {

			if(!array_key_exists($section->id,$enabledSections)) {
				continue;
			}

            $sectionIds[] = $section->id;

            if ($section->type == Section::TYPE_SINGLE) {
                $singleSectionIds[] = $section->id;
            } else {
                $sectionsByType[$section->type][] = $section;
            }
        }

        $sources = [
            [
                'key' => '*',
                'label' => Craft::t('app', 'All entries'),
                'criteria' => [
                    'sectionId' => $sectionIds,
                    'editable' => true,
                ],
                'defaultSort' => ['postDate', 'desc'],
            ],
        ];

		if(!empty($singleSectionIds)) {
			$sources[] = [
				'key' => 'singles',
				'label' => Craft::t('app', 'Singles'),
				'criteria' => [
					'sectionId' => $singleSectionIds,
					'editable' => true,
				],
				'defaultSort' => ['title', 'asc'],
			];
		}

		$sectionTypes = [
            Section::TYPE_CHANNEL => Craft::t('app', 'Channels'),
            Section::TYPE_STRUCTURE => Craft::t('app', 'Structures'),
        ];

		$user = Craft::$app->getUser()->getIdentity();

		foreach ($sectionTypes as $type => $heading) {

            if (!empty($sectionsByType[$type])) {

				foreach ($sectionsByType[$type] as $section) {
                    /** @var Section $section */
                    $source = [
                        'key' => 'section:' . $section->uid,
                        'label' => Craft::t('site', $section->name),
                        'sites' => $section->getSiteIds(),
                        'data' => [
                            'type' => $type,
                            'handle' => $section->handle,
                        ],
                        'criteria' => [
                            'sectionId' => $section->id,
                            'editable' => true,
                        ],
                    ];

                    if ($type == Section::TYPE_STRUCTURE) {
                        $source['defaultSort'] = ['structure', 'asc'];
                        $source['structureEditable'] = false;
                    } else {
                        $source['defaultSort'] = ['postDate', 'desc'];
                    }

                    $sources[] = $source;
                }
            }
        }

        return $sources;
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        $attributes = [];

        if ($source === '*') {
            $attributes[] = 'section';
        }

        if ($source !== 'singles') {
            $attributes[] = 'postDate';
            $attributes[] = 'author';
        }

        $attributes[] = 'link';

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        $attributes = [
            'section' => ['label' => Craft::t('app', 'Section')],
            'type' => ['label' => Craft::t('app', 'Entry Type')],
            'author' => ['label' => Craft::t('app', 'Author')],
            'slug' => ['label' => Craft::t('app', 'Slug')],
            'uri' => ['label' => Craft::t('app', 'URI')],
            'postDate' => ['label' => Craft::t('app', 'Post Date')],
            'expiryDate' => ['label' => Craft::t('app', 'Expiry Date')],
            'link' => ['label' => Craft::t('app', 'Link'), 'icon' => 'world'],
            'id' => ['label' => Craft::t('app', 'ID')],
            'uid' => ['label' => Craft::t('app', 'UID')],
            'dateCreated' => ['label' => Craft::t('app', 'Date Created')],
            'dateUpdated' => ['label' => Craft::t('app', 'Date Updated')],
            'revisionNotes' => ['label' => Craft::t('app', 'Revision Notes')],
            'revisionCreator' => ['label' => Craft::t('app', 'Last Edited By')],
        ];

        // Hide Author & Last Edited By from Craft Solo
        if (Craft::$app->edition !== \craft\enums\CmsEdition::Pro) {
            unset($attributes['author'], $attributes['revisionCreator']);
        }

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    protected static function includeSetStatusAction(): bool
    {
        return false;
    }

	/**
     * @inheritdoc
     * @return EntryQueryCheckit The newly created [[EntryQuery]] instance.
     */
    public static function find(): EntryQueryCheckit
    {
       return new EntryQueryCheckit(static::class);
    }

    /**
     * @inheritdoc
     */
    public static function actions(string $context): array
    {
        return static::defineActions($context);
    }

    protected function cacheTags(): array
    {
        return [
            "entryCheckIt:$this->typeId",
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineActions(string $source): array
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