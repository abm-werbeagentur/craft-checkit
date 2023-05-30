<?php
namespace abmat\checkit;

use Craft;

use craft\base\Plugin;
use craft\elements\Entry;
use craft\events\InvalidateElementCachesEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\DeleteElementEvent;
use craft\services\Dashboard;
use craft\services\Elements;
use craft\services\UserPermissions;
use craft\web\UrlManager;
use craft\web\Application;

use craft\commerce\elements\Product;

use abmat\checkit\base\PluginTrait;
use abmat\checkit\elements\Entry as PluginEntry;
use abmat\checkit\elements\Product as PluginProduct;
use abmat\checkit\widgets\OverviewSite;

use yii\base\Event;
use yii\caching\TagDependency;

class CheckIt extends Plugin {

    public bool $hasCpSection = true;

    public string $schemaVersion = '1.0.0';

    public bool $commerceInstalled = false;

	// Traits
    // =========================================================================

    use PluginTrait;

	// Public Methods
    // =========================================================================

	public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        $pluginsService = Craft::$app->getPlugins();
        if ($pluginsService->isPluginInstalled('commerce') && $pluginsService->isPluginEnabled('commerce')) {
            $this->commerceInstalled = true;
        }

        $this->_registerComponents();

        Craft::$app->on(Application::EVENT_INIT, function() {
            $this->_registerCraftEventListeners();
            $this->_registerWidgets();
        });

        if (Craft::$app->getRequest()->getIsCpRequest()) {

            if (Craft::$app->getEdition() === Craft::Pro) {
                $this->_registerPermissions();
            }
            $this->_registerCpRoutes();
        }
	}

    public function getCpNavItem (): ?array
	{        
        $item = parent::getCpNavItem();

        $item['label'] = Craft::t('abm-checkit', 'Checkit');

        $subNav = [];
		$currentUser = Craft::$app->getUser();

        $subNav['abm-checkit-overview'] = [
            'label' => Craft::t('abm-checkit', 'Overview'),
            'url' => 'abm-checkit/overview',
        ];

        $subNav['abm-checkit-entries'] = [
            'label' => Craft::t('app', 'Entries'),
            'url' => 'abm-checkit/entries?source=*'
        ];

        if ($this->commerceInstalled) {
            $subNav['abm-checkit-products'] = [
                'label' => Craft::t('commerce', 'Commerce') . " > " . Craft::t('commerce', 'Products'),
                'url' => 'abm-checkit/products?source=*'
            ];
        }

        if ($currentUser->checkPermission('abm-checkit-settings')) {
            $subNav['abm-checkit-settings'] = [
                'label' => Craft::t('abm-checkit', 'Settings'),
                'url' => 'abm-checkit/settings',
            ];
        }

        if(!count($subNav)) {
            return null;
        }

        $item['subnav'] = $subNav;

        $entrycount = $this->getEntries()->getCountAllEntriesForCurrentUser();
        if($entrycount) {
            $item["badgeCount"] = $entrycount;
        }
        return $item;
	}

	private function _registerCraftEventListeners(): void
    {
        Event::on(Elements::class, Elements::EVENT_AFTER_DELETE_ELEMENT, [$this->getEntries(), 'deleteTrashedEntries']);

        Event::on(Elements::class, Elements::EVENT_INVALIDATE_CACHES, function(InvalidateElementCachesEvent $event) {

            $tags = [];
            
            if(!empty($event->tags)) {
            
                $entryClassname = Entry::class;
                if(in_array("element::$entryClassname::*",$event->tags)) {
                    $elementType = PluginEntry::class;
                    $tags[] = "element::$elementType";
                    $tags[] = "element::$elementType::*";
                }

                $productClassname = Product::class;
                if(in_array("element::$productClassname::*",$event->tags)) {
                    $elementType = PluginProduct::class;
                    $tags[] = "element::$elementType";
                    $tags[] = "element::$elementType::*";
                }
            }

            if(!empty($tags)) {
                TagDependency::invalidate(Craft::$app->getCache(), $tags);
            }
        });

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            
            $currentUser = Craft::$app->getUser();
            
            if($currentUser) {
                if($currentUser->checkPermission('abm-checkit-save-status')) {
                    Event::on(Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT, [$this->getSidebar(), 'onAfterSaveElement']);
                    Event::on(Entry::class, Entry::EVENT_DEFINE_SIDEBAR_HTML, [$this->getSidebar(), 'renderEntrySidebar']);

                    if ($this->commerceInstalled) {

                        Craft::$app->view->hook('cp.commerce.product.edit.details', function(array &$context) {
                            return $this->getSidebar()->hookCommerceProductEditDetails($context);
                        });
                    }
                }
            }

            Event::on(
                Elements::class,
                Elements::EVENT_REGISTER_ELEMENT_TYPES,
                function(RegisterComponentTypesEvent $event) {
                    $event->types[] = PluginEntry::class;

                    if ($this->commerceInstalled) {
                        $event->types[] = PluginProduct::class;
                    }
                }
            );
        }
    }

    private function _registerCpRoutes (): void
	{
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event): void {
            
            $event->rules['abm-checkit'] = 'abm-checkit/overview/index';

            $event->rules['abm-checkit/overview'] = 'abm-checkit/overview/index';
            
            $event->rules['abm-checkit/entries'] = 'abm-checkit/entries/index';
            $event->rules['abm-checkit/products'] = 'abm-checkit/products/index';

            $event->rules['POST abm-checkit/settings'] = 'abm-checkit/settings/save';
            $event->rules['abm-checkit/settings'] = 'abm-checkit/settings/index';
        });
	}

    private function _registerPermissions(): void
    {
        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS, function(RegisterUserPermissionsEvent $event): void {

            $permissions = [
                'abm-checkit-settings' => ['label' => Craft::t('abm-checkit', 'Settings')],
                'abm-checkit-save-status' => ['label' => Craft::t('abm-checkit', 'save status')],
            ];

            $event->permissions[] = [
                'heading' => Craft::t('abm-checkit', '`Checkit`'),
                'permissions' => $permissions,
            ];
        });
    }

    private function _registerWidgets(): void
    {
        if (Craft::$app->getRequest()->getIsCpRequest()) {
            Event ::on(
                Dashboard::class,
                Dashboard::EVENT_REGISTER_WIDGET_TYPES,
                function(RegisterComponentTypesEvent $event) {
                    $event->types[] = OverviewSite::class;
                }
            );
        }
    }
}