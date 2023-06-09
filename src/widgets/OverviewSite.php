<?php
/**
 * @link https://abm.at
 * @copyright Copyright (c) abm Feregyhazy & Simon GmbH
*/

namespace abmat\checkit\widgets;

use Craft;
use craft\base\Widget;
use craft\models\Site;

use abmat\checkit\CheckIt;

class OverviewSite extends Widget
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('abm-checkit', 'Check it!');
    }

	/**
     * @var string The site ID that the widget should pull entries from
     */
    public string $siteId = '';

	/**
     * @var Site|false|null
     */
    private Site|false|null $_site = null;

	/**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
		return Craft::$app->getView()->renderTemplate('abm-checkit/_widgets/OverviewSite/settings.twig',
			[
				'widget' => $this,
			]);
    }

	/**
     * @inheritdoc
     */
    public function getTitle(): ?string
    {
        $site = $this->_getSite();

        if ($site) {
            return Craft::t('abm-checkit', 'Check It: {site}', ['site' => $site->name]);
        }

        return static::displayName();
    }

	/**
     * @inheritdoc
     */
    public function getBodyHtml(): ?string
    {
		$site = $this->_getSite();

        if($site === null) {
            return null;
        }

        $siteList = CheckIt::$plugin->getEntries()->getOverviewForSite($site);

        return Craft::$app->getView()->renderTemplate('abm-checkit/_widgets/OverviewSite/body.twig',
			[
				'widget' => $this,
				'sitelist' => $siteList,
			]);
	}

	/**
     * Returns the widget's Site.
     *
     * @return Site|null
     */
    private function _getSite(): ?Site
    {
        if (!isset($this->_site)) {
            if ($this->siteId) {
                $this->_site = Craft::$app->getSites()->getSiteById($this->siteId);
            }

            if (!isset($this->_site)) {
                $this->_site = false;
            }
        }

        return $this->_site ?: null;
    }
}