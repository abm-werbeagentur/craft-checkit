<?php
/**
 * @link https://abm.at
 * @copyright Copyright (c) abm Feregyhazy & Simon GmbH
*/

namespace abmat\checkit\assets;

use craft\web\AssetBundle;

class CPAssets extends AssetBundle {

	public function init(): void
    {
        $this->sourcePath = __DIR__."/ressources/dist";

        $this->js = [
            'js/abm-checkit.js',
        ];

        $this->css = [
            'css/abm-checkit.css',
        ];

        parent::init();
    }
}