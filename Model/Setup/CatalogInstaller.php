<?php

namespace MauroNigrele\SetupTools\Model\Setup;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use Psr\Log\LoggerInterface;

class CatalogInstaller extends AbstractInstaller
{
    protected $storeInstaller;

    protected $categoryFactory;

    protected $categoryCollectionFactory;

    /**
     * CatalogInstaller constructor.
     * @param ObjectManagerInterface $objectManager
     * @param Registry $registry
     * @param LoggerInterface $logger
     * @param ScopeConfigInterface $config
     * @param WriterInterface $configWriter
     * @param CategoryFactory $categoryFactory
     * @param StoreInstaller $storeInstaller
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        Registry $registry,
        LoggerInterface $logger,
        ScopeConfigInterface $config,
        WriterInterface $configWriter,
        CategoryFactory $categoryFactory,
        //
        StoreInstaller $storeInstaller
    )
    {
        $this->storeInstaller = $storeInstaller;
        $this->categoryFactory = $categoryFactory;
        parent::__construct($objectManager, $registry, $logger, $config, $configWriter);
    }

    protected $categorySkeleton = array(
        'position'      => 1,
        'is_active'     => 1,
        'is_anchor'     => 0,
        'store_id'      => 0,
        'display_mode'  => Category::DM_PRODUCT,
        'include_in_menu' => 1,
    );

    public function createCategoryTree($storeCode, Array $categories)
    {
        $store = $this->getStore($storeCode);
        if(!$store->getId()) {
            $this->logger->warning(__('There is no Store with code: %1', $storeCode));
            return $this;
        }
        // Load Root Category
        $rootCategory = $this->categoryFactory->create()->load($store->getRootCategoryId());
        // Append Children
        $this->addCategoryTreeChildren($rootCategory, $categories);
    }

    public function addCategoryTreeChildren(Category $parentCategory, Array $children)
    {
        foreach($children as $index => $categoryData) {
            // Prepare Data
            $category = $this->categoryFactory->create();
            /** @var \Magento\Catalog\Model\Category\ $category */

            $category->addData($this->categorySkeleton)
                ->setName($categoryData['name'])
                ->setPath($parentCategory->getPath())
                ->setPosition($index + 1)
                ->save();

            // Recursive
            if(isset($categoryData['children']) && is_array($categoryData['children'])) {
                $this->addCategoryTreeChildren($category, $categoryData['children']);
            }

            // Store Names
            if(isset($categoryData['store_name']) && is_array($categoryData['store_name'])) {
                foreach ($categoryData['store_name'] as $code => $name) {
                    $store = $this->getStore($code);
                    if(!$store->getId()) {
                        $this->logger->warning(__('There is no Store with code: %1', $code));
                        continue;
                    }
                    $category->setStoreId($store->getId())
                        ->setName($name)
                        ->save();
                }
            }

        }
    }

    protected $loadedStores = array();

    protected function getStore($storeCode)
    {
        if (!isset($this->loadedStores[$storeCode])) {
            $this->loadedStores[$storeCode] = $this->storeInstaller->getStore($storeCode);
        }
        return $this->loadedStores[$storeCode];
    }

}