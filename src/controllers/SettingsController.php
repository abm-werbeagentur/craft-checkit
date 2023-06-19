<?php
/**
 * @link https://abm.at
 * @copyright Copyright (c) abm Feregyhazy & Simon GmbH
*/

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
	 * @throws ForbiddenHttpException
	 */
	public function actionSection (): Response
	{
		$currentUser = Craft::$app->user;
		if (!$currentUser->checkPermission('abm-checkit-settings')) {
			throw new ForbiddenHttpException('User not permitted to view this site');
		}

		$namespace = 'data';

		$this->view->registerAssetBundle(CPAssets::class);

		CheckIt::$plugin->getEntries()->deleteTrashedEntries();

		$sections = CheckIt::$plugin->getSections()->getValidSections();
		$productTypes = CheckIt::$plugin->getSections()->getValidProductTypes();
		$settings = CheckIt::$plugin->getSections()->getSections();
		
		return $this->renderTemplate('abm-checkit/settings/_section', [
			'namespace' => $namespace,
			"sections" => $sections,
			"productTypes" => $productTypes,
			"settings" => $settings,
        ]);
	}

	/**
	 * @throws ForbiddenHttpException
	 */
	public function actionSidebar (): Response
	{
		$currentUser = Craft::$app->user;
		if (!$currentUser->checkPermission('abm-checkit-settings')) {
			throw new ForbiddenHttpException('User not permitted to view this site');
		}

		$settings = CheckIt::getInstance()->getSettings();

		$namespace = 'data';

		$this->view->registerAssetBundle(CPAssets::class);
		
		return $this->renderTemplate('abm-checkit/settings/_sidebar', [
			'namespace' => $namespace,
			'settings' => $settings,
        ]);
	}

	/**
	 * @throws \yii\web\BadRequestHttpException
	 */
	public function actionSaveSection ()
	{
		$this->requirePostRequest();
		$craft = \Craft::$app;

		$currentUser = $craft->user;
		if (!$currentUser->checkPermission('abm-checkit-settings')) {
			throw new ForbiddenHttpException('User not permitted to view this site');
		}

		$data = $craft->request->getRequiredBodyParam('data');

		if (CheckIt::$plugin->getSections()->saveSections($data))
		{
			$craft->session->setNotice(
				\Craft::t('abm-checkit', 'Section settings updated')
			);
		}
		else
		{
			$craft->session->setNotice(
				\Craft::t('abm-checkit', 'Couldn\'t save section settings')
			);
		}

		$this->redirectToPostedUrl();
	}

	/**
     * @throws InvalidConfigException
     * @throws BadRequestHttpException
     */
    public function actionSaveSettings(): ?Response
    {
		$this->requirePostRequest();

        $params = $this->request->getBodyParams();
        $data = $params['settings'];

        $settings = CheckIt::getInstance()->getSettings();
        $settings->positionInEntries = $data['positionInEntries'] ?? key($settings->getPossiblePositions());
		$settings->positionInCommmerceProducts = $data['positionInCommmerceProducts'] ?? key($settings->getPossiblePositions());
		$settings->showInformations = $data['showInformations'];

		$pluginSettingsSaved = Craft::$app->getPlugins()->savePluginSettings(CheckIt::getInstance(), $settings->toArray());

        if (!$pluginSettingsSaved) {
            $this->setFailFlash(Craft::t('abm-checkit', 'Couldn’t save settings.'));
            return $this->renderTemplate('abm-checkit/settings/sidebar', compact('settings'));
        }

        $this->setSuccessFlash(Craft::t('abm-checkit', 'Settings saved'));

		return $this->redirectToPostedUrl();
	}
}