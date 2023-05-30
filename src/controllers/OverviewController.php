<?php
namespace abmat\checkit\controllers;

use Craft;
use craft\web\Controller;
use craft\web\Response;

use yii\web\ForbiddenHttpException;

use abmat\checkit\CheckIt;

class OverviewController extends Controller {
	
	public function actionIndex ()
	{
		$editableSites = Craft::$app->sites->getEditableSites();

		$indexedSites = [];
		foreach($editableSites as $site) {
			$indexedSites[$site->id] = CheckIt::$plugin->getEntries()->getOverviewForSite($site);
		}

		return $this->renderTemplate('abm-checkit/_overview/index', [
			"checkitSites" => $indexedSites,
		]);
	}
}