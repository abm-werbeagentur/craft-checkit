<?php
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