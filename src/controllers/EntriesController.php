<?php
/**
 * @link https://abm.at
 * @copyright Copyright (c) abm Feregyhazy & Simon GmbH
*/

namespace abmat\checkit\controllers;

use Craft;
use craft\web\Controller;
use craft\web\Response;
use yii\web\ForbiddenHttpException;

class EntriesController extends Controller {
	/**
	 * @throws ForbiddenHttpException
	 */
	public function actionIndex (): Response
	{
		return $this->renderTemplate('abm-checkit/_entries/index', []);
	}
}