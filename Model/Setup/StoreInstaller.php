<?php

namespace MauroNigrele\SetupTools\Model\Setup;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\CategoryFactory;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\StoreFactory;
use Magento\Store\Model\GroupFactory;
use Magento\Store\Model\WebsiteFactory;

use Magento\Theme\Model\Theme;
use Magento\Theme\Model\ThemeFactory;

use Psr\Log\LoggerInterface;


class StoreInstaller extends AbstractInstaller
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Store\Model\StoreFactory
     */
    protected $storeFactory;

    /**
     * @var \Magento\Store\Model\GroupFactory
     */
    protected $groupFactory;

    /**
     * @var \Magento\Store\Model\WebsiteFactory
     */
    protected $websiteFactory;

    /**
     * @var \Magento\Catalog\Model\CategoryFactory
     */
    protected $categoryFactory;

    /**
     * @var ThemeFactory
     */
    protected $themeFactory;

    /**
     * @var \Magento\Catalog\Model\Category
     */
    protected $treeRootCategory;

    /*********************************************************************************************/
    /******************************************************************************** SKELETONS **/
    /*********************************************************************************************/

    /**
     * @var array
     */
    protected $defaultWebsiteData = array(
        'sort_order' => 0,
        'is_default' => 0,
    );

    protected $defaultGroupData = array(
        'sort_order' => 0,
        'is_active' => 1,
    );

    protected $defaultStoreData = array(
        'sort_order' => 0,
        'is_active' => 1,
    );

    protected $defaultRootCategoryData = array(
        'level' => 1,
        'position' => 1,
        'parent_id' => 1,
        'is_active' => 1,
        'is_anchor' => 0,
        'store_id' => 0,
        'display_mode' => Category::DM_PRODUCT,
        'include_in_menu' => 1,
    );

    /**
     * StoreInstaller constructor.
     * @param ObjectManagerInterface $objectManager
     * @param Registry $registry
     * @param LoggerInterface $logger
     * @param ScopeConfigInterface $config
     * @param WriterInterface $configWriter
     * @param StoreManagerInterface $storeManager
     * @param StoreFactory $storeFactory
     * @param GroupFactory $groupFactory
     * @param WebsiteFactory $websiteFactory
     * @param CategoryFactory $categoryFactory
     * @param ThemeFactory $themeFactory
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        // Parent
        ObjectManagerInterface $objectManager,
        Registry $registry,
        LoggerInterface $logger,
        ScopeConfigInterface $config,
        WriterInterface $configWriter,
        // This
        StoreManagerInterface $storeManager,
        StoreFactory $storeFactory,
        GroupFactory $groupFactory,
        WebsiteFactory $websiteFactory,
        CategoryFactory $categoryFactory,
        ThemeFactory $themeFactory
    ) {
        $this->storeManager = $storeManager;
        $this->storeFactory = $storeFactory;
        $this->groupFactory = $groupFactory;
        $this->websiteFactory = $websiteFactory;
        $this->categoryFactory = $categoryFactory;
        $this->themeFactory = $themeFactory;
        parent::__construct($objectManager, $registry, $logger, $config, $configWriter);
    }

    /*********************************************************************************************/
    /********************************************************************************** GETTERS **/
    /*********************************************************************************************/

    /**
     * @return StoreManagerInterface
     */
    public function getStoreManager()
    {
        return $this->storeManager;
    }

    /**
     * @return StoreFactory
     */
    public function getStoreFactory()
    {
        return $this->storeFactory;
    }

    /**
     * @return GroupFactory
     */
    public function getGroupFactory()
    {
        return $this->groupFactory;
    }

    /**
     * @return WebsiteFactory
     */
    public function getWebsiteFactory()
    {
        return $this->websiteFactory;
    }

    /**
     * @return CategoryFactory
     */
    public function getCategoryFactory()
    {
        return $this->categoryFactory;
    }

    /**
     * @return ThemeFactory
     */
    public function getThemeFactory()
    {
        return $this->themeFactory;
    }

    /*********************************************************************************************/
    /*********************************************************************************** CONFIG **/
    /*********************************************************************************************/

    /**
     * @param $schema
     * @param string $key
     * @param array $default
     * @return array
     */
    public function getConfigKey($schema, $key, $default = [])
    {
        if (isset($schema[$key]) && is_array($schema[$key])) {
            return $schema[$key];
        }
        return $default;
    }

    /**
     * @param array $configSchema
     * @return $this
     */
    public function setConfigSchema(array $configSchema)
    {
        // Default Config
        foreach ($this->getConfigKey($configSchema, 'default') as $path => $value) {
            // Eval Custom Configuration
            if ($path[0] === '_') {
                $this->setCustomConfig($path, $value);
                continue;
            }
            $this->setConfig($path, $value);
        }
        // Website Config
        foreach ($this->getConfigKey($configSchema, 'websites') as $code => $websiteConfig) {
            $website = $this->getWebsite($code);
            // Website Validation
            if (!$website->getId()) {
                $this->logger->warning(__('There is no Website with code: %1', $code));
                continue;
            }
            foreach ($websiteConfig as $path => $value) {
                // Eval Custom Configuration
                if ($path[0] === '_') {
                    $this->setCustomConfig($path, $value, 'websites', $website->getId());
                    continue;
                }
                $this->setConfig($path, $value, 'websites', $website->getId());
            }
        }
        // Store Config
        foreach ($this->getConfigKey($configSchema, 'stores') as $code => $storeConfig) {
            $store = $this->getStore($code);
            // Store Validation
            if (!$store->getId()) {
                $this->logger->warning(__('There is no Store with code: %1', $code));
                continue;
            }
            foreach ($storeConfig as $path => $value) {
                // Eval Custom Configuration
                if ($path[0] === '_') {
                    $this->setCustomConfig($path, $value, 'stores', $website->getId());
                    continue;
                }
                $this->setConfig($path, $value, 'stores', $website->getId());
            }
        }
        return $this;
    }

    public function setCustomConfig($path, $value, $scopeType = 'default', $scopeCode = 0)
    {
        switch ($path) {
            // Unsecure Base Url
            case '_unsecure_base_url_prefix':
                $this->setUrlPrefixConfig('web/unsecure/base_url', $value, $scopeType, $scopeCode);
                break;
            case '_unsecure_base_url_suffix':
                $this->setUrlSuffixConfig('web/unsecure/base_url', $value, $scopeType, $scopeCode);
                break;
            // Secure Base Url
            case '_secure_base_url_prefix':
                $this->setUrlPrefixConfig('web/secure/base_url', $value, $scopeType, $scopeCode);
                break;
            case '_secure_base_url_suffix':
                $this->setUrlSuffixConfig('web/secure/base_url', $value, $scopeType, $scopeCode);
                break;
            // Payment Methods
            case '_payment_methods':
                $this->setMethodsConfig('payment', $value, $scopeType, $scopeCode);
                break;
            // Shipping Methods
            case '_shipping_methods':
                $this->setMethodsConfig('carriers', $value, $scopeType, $scopeCode);
                break;
        }
        return $this;
    }

    /**
     * @param string $key
     * @param array $activeMethods
     * @param string $scopeType
     * @param int $scopeCode
     * @return $this
     */
    public function setMethodsConfig($key, array $activeMethods, $scopeType = 'default', $scopeCode = 0)
    {
        $methods = $this->getConfig($key, $scopeType, $scopeCode);
        foreach ($methods as $code => $data) {
            if (!isset($data['active'])) {
                continue;
            }
            // Disable Methods
            if (!in_array($code, $activeMethods) && $data['active'] == 1) {
                $this->setConfig($key.'/'.$code.'/active', 0, $scopeType, $scopeCode);
            }
            // Enable Methods
            if (in_array($code, $activeMethods) && $data['active'] == 0) {
                $this->setConfig($key.'/'.$code.'/active', 1, $scopeType, $scopeCode);
            }
        }
        return $this;
    }

    /**
     * @param string $path
     * @param $prefix
     * @param string $scopeType
     * @param int $scopeCode
     * @return $this
     */
    public function setUrlPrefixConfig($path, $prefix, $scopeType = 'default', $scopeCode = 0)
    {
        $currentUrl = $this->getConfig($path, $scopeType, $scopeCode);
        $parts = explode('://', $currentUrl);
        $protocol = $parts[0];
        $levels = array_filter(explode('.', $parts[1]));

        // Check Already Configured
        if ($levels[0] == $prefix) {
            return $this;
        }
        $value = $protocol.'://'.$prefix.'.'.$parts[1];
        if (substr($value, -1) !== '/') {
            $value .= '/';
        }

        $this->setConfig($path, $value, $scopeType, $scopeCode);
    }

    /**
     * @param string $path
     * @param $suffix
     * @param string $scopeType
     * @param int $scopeCode
     * @return $this
     */
    public function setUrlSuffixConfig($path, $suffix, $scopeType = 'default', $scopeCode = 0)
    {
        $defaultUrl = $this->getConfig($path);
        $currentUrl = $this->getConfig($path, $scopeType, $scopeCode);
        $parts = explode('://', $currentUrl);
        $protocol = $parts[0];
        $levels = array_filter(explode('/', $parts[1]));

        // Check Media & Static Folders
        $pathParts = explode('/', $path);
        $pathStatic = $pathParts[0].'/'.$pathParts[1].'/base_static_url/';
        $pathMedia = $pathParts[0].'/'.$pathParts[1].'/base_media_url/';
        // Static & Media Config
        $this->setConfig($pathStatic, $defaultUrl.'pub/static/', $scopeType, $scopeCode);
        $this->setConfig($pathMedia, $defaultUrl.'pub/media/', $scopeType, $scopeCode);

        // Check Already Configured
        if (end($levels) == $suffix) {
            return $this;
        }
        $value = $protocol.'://'.$parts[1].$suffix;
        if (substr($value, -1) !== '/') {
            $value .= '/';
        }
        $this->setConfig($path, $value, $scopeType, $scopeCode);
    }


    /**
     * @param array $configSchema
     * @return $this
     */
    public function setThemeConfigSchema(array $configSchema)
    {
        // Default Theme Config
        $defaultTheme = (isset($configSchema['default'])) ? $configSchema['default'] : false;
        if ($defaultTheme) {
            $theme = $this->themeFactory->create()->load($defaultTheme, 'code');
            /** @var Theme $theme */
            // Theme Validation
            if ($theme->getId()) {
                $this->setConfig('design/theme/theme_id', $theme->getId());
            } else {
                $this->logger->warning(__('There is no Theme with code: %1', $defaultTheme));
            }
        }

        // Website Theme Config
        foreach ($this->getConfigKey($configSchema, 'websites') as $code => $themeCode) {
            $website = $this->getWebsite($code);
            $theme = $this->themeFactory->create()->load($themeCode, 'code');
            // Website Validation
            if (!$website->getId()) {
                $this->logger->warning(__('There is no Website with code: %1', $code));
                continue;
            }
            // Theme Validation
            if (!$theme->getId()) {
                $this->logger->warning(__('There is no Theme with code: %1', $themeCode));
                continue;
            }
            // Set Config
            $this->setConfig('design/theme/theme_id', $theme->getId(), 'websites', $website->getId());
        }

        // Store Theme Config
        foreach ($this->getConfigKey($configSchema, 'store') as $code => $themeCode) {
            $store = $this->getStore($code);
            $theme = $this->themeFactory->create()->load($themeCode, 'code');
            // Store Validation
            if (!$store->getId()) {
                $this->logger->warning(__('There is no Store with code: %1', $code));
                continue;
            }
            // Theme Validation
            if (!$theme->getId()) {
                $this->logger->warning(__('There is no Theme with code: %1', $themeCode));
                continue;
            }
            // Set Config
            $this->setConfig('design/theme/theme_id', $theme->getId(), 'stores', $store->getId());
        }
        return $this;
    }


    /*********************************************************************************************/
    /******************************************************************* Default Website Schema **/
    /*********************************************************************************************/

    /**
     *
     * @TODO Refactor - This Method... Really Sucks
     *
     * @param array $data
     * @return $this
     * @throws NoSuchEntityException
     */
    public function updateDefaultWebsiteSchema(array $data)
    {
        // Load Defaults
        $defaultWebsite = $this->getDefaultWebsite();
        $defaultGroup = $this->getDefaultGroup();
        $defaultStore = $this->getDefaultStore();

        // Update Default Website
        $defaultWebsite->addData($data)
            ->save();

        // Update / Create Groups
        if (isset($data['_groups']) && is_array($data['_groups'])) {
            foreach ($data['_groups'] as $groupData) {

                // Append Data
                $groupData['website_id'] = $defaultWebsite->getId();

                // Default Group
                if (isset($groupData['_is_default']) && $groupData['_is_default']) {

                    // Update Default Group
                    $this->updateGroup($defaultGroup->getId(), $groupData, false, true, 'group_id');

                    // Update Stores
                    if (isset($groupData['_stores']) && is_array($groupData['_stores'])) {
                        foreach ($groupData['_stores'] as $storeData) {
                            // Append Data
                            $storeData['website_id'] = $defaultWebsite->getId();
                            $storeData['group_id'] = $defaultGroup->getId();
                            // Default Store
                            if (isset($storeData['_is_default']) && $storeData['_is_default']) {
                                // Update Default Store
                                $this->updateStore($defaultStore->getId(), $storeData, 'store_id');
                                // Others
                            } else {
                                try {
                                    $this->updateStore($storeData['code'], $storeData);
                                } catch (NoSuchEntityException $e) {
                                    $this->createStore($storeData);
                                }
                            }
                        }
                    }

                    // Other Group(s)
                } else {
                    try {
                        $this->updateGroup($groupData['name'], $groupData);
                    } catch (NoSuchEntityException $e) {
                        $this->createGroup($groupData);
                    }
                    // Update Stores
                    if (isset($groupData['_stores']) && is_array($groupData['_stores'])) {
                        foreach ($groupData['_stores'] as $storeData) {
                            // Append Data
                            $storeData['website_id'] = $defaultWebsite->getId();
                            $storeData['group_id'] = $defaultGroup->getId();
                            try {
                                $this->updateStore($storeData['code'], $storeData);
                            } catch (NoSuchEntityException $e) {
                                $this->createStore($storeData);
                            }
                        }
                    }

                }
            }
        }

        return $this;
    }

    /*********************************************************************************************/
    /************************************************************************* Common Functions **/
    /*********************************************************************************************/

    /**
     * @param string[] $neededKeys
     * @param array $dataKeys
     * @param string $message
     * @return $this
     * @throws ValidatorException
     */
    protected function validateKeys(array $neededKeys, array $dataKeys, $message = 'Invalid Keys')
    {
        $errors = array();
        foreach ($neededKeys as $neededKey) {
            if (!in_array($neededKey, $dataKeys)) {
                $errors[] = _('%1: is a needed field.', $neededKey);
            }
        }
        if (count($errors)) {
            throw new ValidatorException(__($message.' | '.implode('/', $errors)));
        }
        return $this;
    }

    /*********************************************************************************************/
    /*********************************************************************** CRUD - Store Model **/
    /*********************************************************************************************/

    /**
     * @param array $data
     * @return $this
     * @throws AlreadyExistsException
     * @throws ValidatorException
     */
    protected function validateNewStoreData(array $data)
    {
        // Validate Data
        $this->validateKeys(array('name', 'code', 'group_id', 'website_id'), array_keys($data),
            'New Store Validation Error: ');

        // Validate Already Exists
        $store = $this->getStore($data['code']);
        if ($store->getId()) {
            throw new AlreadyExistsException(__('A store with code: %1 already exists.', $data['code']));
        }

        return $this;
    }

    /**
     * @return \Magento\Store\Model\Store
     */
    public function getDefaultStore()
    {
        return $this->storeManager->getDefaultStoreView();
    }

    /**
     * @return int
     */
    public function getDefaultStoreId()
    {
        return $this->getDefaultStore()->getId();
    }

    /**
     * @param $id
     * @param string $field
     * @return \Magento\Store\Model\Store
     */
    public function getStore($id, $field = 'code')
    {
        $store = $this->storeFactory->create()->load($id, $field);
        return $store;
    }

    /**
     * @param array $data
     * @return \Magento\Store\Model\Store
     */
    public function createStore(array $data)
    {
        // Prepare & Validate Data
        $data = array_merge($this->defaultStoreData, $data);
        $this->validateNewStoreData($data);

        // Create Model
        $store = $this->storeFactory->create();
        /** @var \Magento\Store\Model\Store $store */

        $store->addData($data)
            ->save();

        // Default Store
        if (isset($data['_is_default']) && $data['_is_default']) {
            $store->getGroup()->setDefaultStoreId($store->getId())->save();
        }
        return $store;
    }

    /**
     * @param $id
     * @param $data
     * @param string $field
     * @return \Magento\Store\Model\Store
     * @throws NoSuchEntityException
     */
    public function updateStore($id, array $data, $field = 'code')
    {
        $store = $this->getStore($id, $field);

        if (!$store->getId()) {
            throw new NoSuchEntityException(__('Invalid Store.'));
        }

        $store->addData($data)
            ->save();

        // Default Store
        if (isset($data['_is_default']) && $data['_is_default']) {
            $store->getGroup()->setDefaultStoreId($store->getId())->save();
        }

        return $store;
    }

    /**
     * @param $id
     * @param string $field
     * @return $this
     * @throws NoSuchEntityException
     */
    public function deleteStore($id, $field = 'code')
    {
        $store = $this->getStore($id, $field);
        /* @var \Magento\Store\Model\Store $store * */

        if (!$store->getId()) {
            throw new NoSuchEntityException(__('Invalid Store.'));
        }

        $store->delete();
        return $this;
    }

    /*********************************************************************************************/
    /*********************************************************************** CRUD - Group Model **/
    /*********************************************************************************************/

    /**
     * @param array $data
     * @return $this
     * @throws AlreadyExistsException
     * @throws ValidatorException
     */
    protected function validateNewGroupData(array $data)
    {
        // Validate Data
        $this->validateKeys(array('name', 'website_id'), array_keys($data), 'New Store Group Validation Error: ');

        // Validate Already Exists
        $group = $this->getGroup($data['name']);
        if ($group->getId()) {
            throw new AlreadyExistsException(__('A Store Group with name: %1, already exists.', $data['name']));
        }

        return $this;
    }

    /**
     * @return \Magento\Store\Model\Group
     */
    public function getDefaultGroup()
    {
        return $this->storeManager->getDefaultStoreView()->getGroup();
    }

    /**
     * @param $id
     * @param string $field
     * @return \Magento\Store\Model\Group
     */
    public function getGroup($id, $field = 'name')
    {
        $group = $this->groupFactory->create()->load($id, $field);
        return $group;
    }

    /**
     * @param array $data
     * @param bool $processStores
     * @param bool $processCategory
     * @return \Magento\Store\Model\Group
     */
    public function createGroup(array $data, $processStores = true, $processCategory = true)
    {
        // Prepare & Validate Data
        $data = array_merge($this->defaultGroupData, $data);
        $this->validateNewGroupData($data);

        // Create Model
        $group = $this->groupFactory->create();
        /** @var \Magento\Store\Model\Group $group */

        $group->addData($data)
            ->setRootCategoryId($this->getDefaultRootCategoryId())
            ->save();

        // Create/Update Stores
        if ($processStores && isset($data['_stores']) && is_array($data['_stores'])) {
            foreach ($data['_stores'] as $storeData) {
                $storeData['group_id'] = $group->getId();
                $storeData['website_id'] = $group->getWebsiteId();
                try {
                    $this->createStore($storeData);
                } catch (AlreadyExistsException $e) {
                    $this->updateStore($storeData['code'], $storeData);
                }
            }
        }

        // Default Group
        if (isset($data['_is_default']) && $data['_is_default']) {
            $group->getWebsite()->setDefaultGroupId($group->getId())->save();
        }

        // Root Category
        if ($processCategory && isset($data['_root_category'])) {

            try {
                $rootCategory = $this->createRootCategory($data['_root_category']);
            } catch (AlreadyExistsException $e) {
                $rootCategory = $this->getRootCategoryByName($data['_root_category']['name']);
            }

            $group->setRootCategoryId($rootCategory->getId())
                ->save();
        }

        return $group;
    }

    /**
     * @param $id
     * @param array $data
     * @param bool $processStores
     * @param bool $processCategory
     * @param string $field
     * @return \Magento\Store\Model\Group
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function updateGroup($id, array $data, $processStores = true, $processCategory = true, $field = 'name')
    {
        $group = $this->getGroup($id, $field);

        if (!$group->getId()) {
            throw new NoSuchEntityException(__('Invalid Group.'));
        }

        $group->addData($data)
            ->save();

        // Update/Create Stores
        if ($processStores && isset($data['_stores']) && is_array($data['_stores'])) {
            foreach ($data['_stores'] as $storeData) {
                $storeData['group_id'] = $group->getId();
                $storeData['website_id'] = $group->getWebsiteId();
                try {
                    $this->updateStore($storeData['code'], $storeData);
                } catch (NoSuchEntityException $e) {
                    $this->createStore($storeData);
                }
            }
        }

        // Default Group
        if (isset($data['_is_default']) && $data['_is_default']) {
            $group->getWebsite()->setDefaultGroupId($group->getId())->save();
        }

        // Root Category
        if ($processCategory && isset($data['_root_category'])) {
            // Check Rename Action
            if (isset($data['_root_category']['_rename']) && $data['_root_category']['_rename']) {
                $this->updateRootCategory($group->getRootCategoryId(), $data['_root_category']);
            } else {
                // Load Category
                $rootCategory = $this->getRootCategoryByName($data['_root_category']['name']);
                if (null === $rootCategory) {
                    $rootCategory = $this->createRootCategory($data['_root_category']);
                }
                if ($rootCategory->getId() != $group->getRootCategoryId()) {
                    $group->setRootCategoryId($rootCategory->getId());
                }
            }
        }

        return $group;
    }

    /**
     * @param $id
     * @param string $field
     * @return $this
     * @throws NoSuchEntityException
     */
    public function deleteGroup($id, $field = 'code')
    {
        $group = $this->getGroup($id, $field);

        if (!$group->getId()) {
            throw new NoSuchEntityException(__('Invalid Group.'));
        }

        $group->delete();
        return $this;
    }


    /*********************************************************************************************/
    /********************************************************************* CRUD - Website Model **/
    /*********************************************************************************************/

    /**
     * @param array $data
     * @return $this
     * @throws AlreadyExistsException
     * @throws ValidatorException
     */
    protected function validateNewWebsiteData(array $data)
    {
        // Validate Data
        $this->validateKeys(array('code', 'name'), array_keys($data), 'New Website Validation Error: ');

        // Validate Already Exists
        $website = $this->getWebsite($data['code']);
        if ($website->getId()) {
            throw new AlreadyExistsException(__('A Website with code: %1, already exists.', $data['code']));
        }

        return $this;
    }

    /**
     * @return \Magento\Store\Model\Website
     */
    public function getDefaultWebsite()
    {
        return $this->storeManager->getDefaultStoreView()->getWebsite();
    }

    /**
     * @param $id
     * @param string $field
     * @return \Magento\Store\Model\Website
     */
    public function getWebsite($id, $field = 'code')
    {
        $website = $this->websiteFactory->create()->load($id, $field);
        /** @var \Magento\Store\Model\Website $website */
        return $website;
    }

    /**
     * @param array $data
     * @param bool $processGroups
     * @return \Magento\Store\Model\Website
     */
    public function createWebsite(array $data, $processGroups = true)
    {
        // Prepare and Validate Data
        $data = array_merge($this->defaultWebsiteData, $data);
        $this->validateNewWebsiteData($data);

        // Create Model
        $website = $this->websiteFactory->create();
        /* @var \Magento\Store\Model\Website $website * */

        $website->addData($data)
            ->save();

        // Create/Update Groups
        if ($processGroups && isset($data['_groups']) && is_array($data['_groups'])) {
            foreach ($data['_groups'] as $groupKey => $groupData) {
                $groupData['website_id'] = $website->getId();
                $this->createGroup($groupData);
                try {
                    $this->createGroup($groupData);
                } catch (AlreadyExistsException $e) {
                    $this->updateGroup($groupData['name'], $groupData);
                }
            }
        }

        return $website;
    }

    /**
     * @param $id
     * @param array $data
     * @param bool $processGroups
     * @param string $field
     * @return \Magento\Store\Model\Website
     * @throws NoSuchEntityException
     */
    public function updateWebsite($id, array $data, $processGroups = true, $field = 'code')
    {
        $website = $this->getWebsite($id, $field);

        if (!$website->getId()) {
            throw new NoSuchEntityException(__('Invalid Website.'));
        }

//        $websiteData = $data;
//        unset($websiteData['_groups']);

        $website->addData($data)
            ->save();

        // Update/Create Groups
        if ($processGroups && isset($data['_groups']) && is_array($data['_groups'])) {
            foreach ($data['_groups'] as $groupData) {
                $groupData['website_id'] = $website->getWebsiteId();
                try {
                    $this->updateGroup($groupData['name'], $groupData);
                } catch (NoSuchEntityException $e) {
                    $this->createGroup($groupData);
                }
            }
        }
        return $website;
    }

    /**
     * @param $id
     * @param string $field
     * @return $this
     * @throws NoSuchEntityException
     */
    public function deleteWebsite($id, $field = 'code')
    {
        $website = $this->getWebsite($id, $field);
        /** @var \Magento\Store\Model\Website $website */

        if (!$website->getId()) {
            throw new NoSuchEntityException(__('Invalid Website.'));
        }

        $website->delete();
        return $this;
    }


    /*********************************************************************************************/
    /**************************************************************************** Root Category **/
    /*********************************************************************************************/


    /**
     * @param array $data
     * @return $this
     * @throws AlreadyExistsException
     * @throws ValidatorException
     */
    protected function validateNewRootCategoryData(array $data)
    {
        // Validate Data
        $this->validateKeys(array('name', 'store_id'), array_keys($data), 'New Root Category Validation Error: ');

        // Validate Already Exists
        if ($this->getRootCategoryByName($data['name'])) {
            throw new AlreadyExistsException(__('A Root Category with name: %1, already exists.', $data['name']));
        }

        return $this;
    }

    /**
     * @return \Magento\Catalog\Model\Category
     */
    public function getDefaultRootCategory()
    {
        return $this->getRootCategory($this->getDefaultRootCategoryId());
    }

    /**
     * @param $id
     * @return \Magento\Catalog\Model\Category
     */
    public function getRootCategory($id)
    {
        $category = $this->categoryFactory->create()->load($id);
        /** @var \Magento\Catalog\Model\Category $category */
        return $category;
    }

    /**
     * @param $name
     * @param bool $returnEmpty
     * @return \Magento\Framework\DataObject|null
     * @throws LocalizedException
     */
    public function getRootCategoryByName($name, $returnEmpty = false)
    {
        $category = $this->categoryFactory->create();
        /** @var \Magento\Catalog\Model\Category $category */
        $collection = $this->categoryFactory->create()->getCollection();
        /** @var \Magento\Catalog\Model\ResourceModel\Category\Collection $collection */
        $collection->addAttributeToFilter('name', array('eq' => $name))
            ->addLevelFilter(1);

        if ($collection->count()) {
            return $collection->getFirstItem();
        }

        return ($returnEmpty) ? $category : null;
    }


    /**
     * @param $id
     * @param array $data
     * @return \Magento\Catalog\Model\Category
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function updateRootCategory($id, array $data)
    {
        $category = $this->getRootCategory($id);

        if (!$category->getId()) {
            throw new NoSuchEntityException(__('Invalid Category.'));
        }
        if (!$category->getLevel() > 1) {
            throw new LocalizedException(__('Category Is Not Root.'));
        }

        $category->addData($data)
            ->save();

        return $category;

    }

    /**
     * @param array $data
     * @return \Magento\Catalog\Model\Category
     */
    public function createRootCategory(array $data)
    {
        $data = array_merge($this->defaultRootCategoryData, $data);

        $this->validateNewRootCategoryData($data);

        $category = $this->categoryFactory->create(array('data' => $data))
            ->setAttributeSetId($this->getTreeRootCategory()->getDefaultAttributeSetId())
            ->setPath($this->getTreeRootCategory()->getPath())
            ->save();

        return $category;
    }


    /**
     * @return \Magento\Catalog\Model\Category
     */
    protected function getTreeRootCategory()
    {
        if (!$this->treeRootCategory) {
            $this->treeRootCategory = $this->categoryFactory->create()
                ->load(Category::TREE_ROOT_ID);
        }
        return $this->treeRootCategory;
    }

    /**
     * @return int
     */
    public function getDefaultRootCategoryId()
    {
        return $this->storeManager->getDefaultStoreView()
            ->getGroup()->getDafaultCategoryId();
    }
}
