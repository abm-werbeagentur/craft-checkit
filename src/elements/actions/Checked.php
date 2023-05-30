<?php
namespace abmat\checkit\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Db;

use abmat\checkit\CheckIt;

class Checked extends ElementAction
{
	/**
     * @inheritdoc
     */
    public function getTriggerLabel(): string
    {
        return Craft::t('abm-checkit', 'Set "Checked"');
    }

	/**
     * @inheritdoc
     */
    public function performAction(ElementQueryInterface $query): bool
    {
        $elementsService = CheckIt::$plugin->getEntries();

        foreach ($query->all() as $element) {
            $elementsService->removeElement($element);
        }

        $this->setMessage(Craft::t('abm-checkit', 'Entries checked'));

        return true;
    }
}