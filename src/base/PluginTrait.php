<?php
namespace abmat\checkit\base;

use Craft;
use abmat\checkit\CheckIt as Plugin;
use abmat\checkit\services\Sidebar;
use abmat\checkit\services\Settings;
use abmat\checkit\services\Entries;

use yii\log\Logger;

trait PluginTrait
{
    // Properties
    // =========================================================================

    public static Plugin $plugin;

	// Static Methods
    // =========================================================================

	public static function log(string $message): void
    {
        Craft::getLogger()->log($message, Logger::LEVEL_INFO, 'abm-checkit');
    }

    public static function error(string $message): void
    {
        Craft::getLogger()->log($message, Logger::LEVEL_ERROR, 'abm-checkit');
    }

	// Public Methods
	// =========================================================================

	public function getSidebar(): Sidebar
	{
		return $this->get('sidebar');
	}

	public function getSettings(): Settings
	{
		return $this->get('settings');
	}

	public function getEntries(): Entries
	{
		return $this->get('entries');
	}

	// Private Methods
    // =========================================================================

	private function _registerComponents(): void
	{
		$this->setComponents([
            'sidebar' => Sidebar::class,
            'settings' => Settings::class,
            'entries' => Entries::class,
        ]);
	}
}