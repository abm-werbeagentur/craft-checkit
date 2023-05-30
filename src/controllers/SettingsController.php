<?php
namespace abmat\checkit\controllers;

use Craft;
use craft\web\Controller;
use craft\web\Response;
use Exception;
use Throwable;
use yii\base\InvalidConfigException;
use yii\db\StaleObjectException;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;

use abmat\checkit\CheckIt;
use abmat\checkit\assets\CPAssets;

class SettingsController extends Controller {
	/**
	 * @throws HttpException
	 */
	public function actionIndex (): Response
	{
		$currentUser = Craft::$app->user;
		if (!$currentUser->checkPermission('abm-checkit-settings')) {
			throw new ForbiddenHttpException('User not permitted to view this site');
		}

		$namespace = 'data';

		$this->view->registerAssetBundle(CPAssets::class);

		CheckIt::$plugin->getEntries()->deleteTrashedEntries();

		$sections = CheckIt::$plugin->getSettings()->getValidSections();
		$productTypes = CheckIt::$plugin->getSettings()->getValidProductTypes();
		$settings = CheckIt::$plugin->getSettings()->getSettings();
		
		return $this->renderTemplate('abm-checkit/_settings/index', [
			'namespace' => $namespace,
			"sections" => $sections,
			"productTypes" => $productTypes,
			"settings" => $settings,
        ]);
	}

	/**
	 * @throws \yii\web\BadRequestHttpException
	 */
	public function actionSave ()
	{
		$this->requirePostRequest();
		$craft = \Craft::$app;

		$currentUser = $craft->user;
		if (!$currentUser->checkPermission('abm-checkit-settings')) {
			throw new ForbiddenHttpException('User not permitted to view this site');
		}

		$data = $craft->request->getRequiredBodyParam('data');

		if (CheckIt::$plugin->getSettings()->saveSettings($data))
		{
			$craft->session->setNotice(
				\Craft::t('abm-checkit', 'Settings Updated')
			);
		}
		else
		{
			$craft->session->setNotice(
				\Craft::t('abm-checkit', 'Couldn\'t save settings')
			);
		}


		$this->redirectToPostedUrl();
	}
}