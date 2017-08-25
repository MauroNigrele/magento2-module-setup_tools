<?php

namespace MauroNigrele\SetupTools\Model\Setup;

// Abstract Installer
use Magento\Catalog\Api\CategoryAttributeRepositoryInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
// Eav Installer
use Magento\Eav\Model\Entity\Attribute\SetFactory;
use Magento\Eav\Model\Entity\Attribute\GroupFactory;
use Magento\Eav\Model\Entity\AttributeFactory;

// Catalog Installer
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Framework\Exception\LocalizedException;


/**
 * Class CatalogInstaller
 * @package MauroNigrele\SetupTools\Model\Setup
 *
 * @SuppressWarnings(PHPMD.LongVariable)
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CatalogInstaller extends EavInstaller
{

    /**
     * @var StoreInstaller
     */
    protected $storeInstaller;

    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
     * @var CategoryFactory
     */
    protected $categoryFactory;

    /**
     * @var Collection\Factory
     */
    protected $categoryCollectionFactory;

    /**
     * @var integer
     */
    protected $productEntityTypeId;

    protected $productAttributeRepository;

    protected $categoryAttributeRepository;

    /**
     * @var array
     */
    protected $loadedStores = [];

    /**
     * @var array
     */
    protected $categorySkeleton = [
        'position' => 1,
        'is_active' => 1,
        'store_id' => 0,
        'display_mode' => Category::DM_PRODUCT,
        'include_in_menu' => 1,
    ];

    /**
     * - Abstract Installer
     * @param ObjectManagerInterface $objectManager
     * @param Registry $registry
     * @param LoggerInterface $logger
     * @param ScopeConfigInterface $config
     * @param WriterInterface $configWriter
     * - Eav Installer
     * @param SetFactory $attributeSetFactory
     * @param GroupFactory $attributeGroupFactory
     * @param AttributeFactory $attributeFactory
     * - Catalog Installer
     * @param CategoryFactory $categoryFactory
     * @param Collection\Factory $categoryCollectionFactory
     * @param StoreInstaller $storeInstaller
     * @param ProductFactory $productFactory
     * - Attribute Repository
     * @param ProductAttributeRepositoryInterface $productAttributeRepository
     * @param CategoryAttributeRepositoryInterface $categoryAttributeRepository
     *
     */
    public function __construct(
        // Abstract Installer
        ObjectManagerInterface $objectManager,
        Registry $registry,
        LoggerInterface $logger,
        ScopeConfigInterface $config,
        WriterInterface $configWriter,
        // Eav Installer
        SetFactory $attributeSetFactory,
        GroupFactory $attributeGroupFactory,
        AttributeFactory $attributeFactory,
        // Catalog ->
        ProductFactory $productFactory,
        CategoryFactory $categoryFactory,
        Collection\Factory $categoryCollectionFactory,
        StoreInstaller $storeInstaller,
        // Repositories
        ProductAttributeRepositoryInterface $productAttributeRepository,
        CategoryAttributeRepositoryInterface $categoryAttributeRepository
    ) {
        $this->productFactory = $productFactory;
        $this->categoryFactory = $categoryFactory;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->storeInstaller = $storeInstaller;

        $this->productAttributeRepository = $productAttributeRepository;
        $this->categoryAttributeRepository = $categoryAttributeRepository;

        parent::__construct($objectManager, $registry, $logger, $config, $configWriter, $attributeSetFactory,
            $attributeGroupFactory, $attributeFactory);
    }


    /******************************************************************************************************************/
    /********************************************************************************************** Product Methods ***/
    /******************************************************************************************************************/

    /*********************************************************************************************** Product Entity ***/

    /**
     * @return \Magento\Catalog\Model\Product
     */
    public function getProductModel()
    {
        return $this->productFactory->create();
    }

    /**
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    public function getProductCollection()
    {
        return $this->getProductModel()->getResourceCollection();
    }


    /**************************************************************************************** Product Attribute Set ***/

    /**
     * @return ProductAttributeRepositoryInterface
     */
    public function getProductAttributeRepository()
    {
        return $this->productAttributeRepository;
    }
    
    public function getAllProductAttributes()
    {
        $searchCriteria = $this->objectManager
            ->create('\Magento\Framework\Api\SearchCriteriaInterface')
            ->setPageSize(999999);
        
        return $this->getProductAttributeRepository()
            ->getList($searchCriteria)->getItems();
    }

    protected function getProductEntityType()
    {
        return Product::ENTITY;
    }

    /**
     * @return integer
     */
    protected function getDefaultProductAttributeSetId()
    {
        return $this->getEavSetup()->getDefaultAttributeSetId($this->getProductEntityType());
    }

    /**
     * @return \Magento\Eav\Model\Entity\Attribute\Set
     */
    protected function getDefaultProductAttributeSet()
    {
        return $this->getEavSetup()
            ->getAttributeSet($this->getProductEntityType(), $this->getDefaultProductAttributeSetId());
    }

    /**
     * @param string $name
     * @return \Magento\Eav\Model\Entity\Attribute\Set
     */
    public function getProductAttributeSet($name)
    {
        return $this->getAttributeSet($name, $this->getProductEntityType());
    }

    /**
     * @param string $name
     * @param null|integer $skeletonSetId
     * @return \Magento\Eav\Model\Entity\Attribute\Set
     * @throws LocalizedException
     */
    public function addProductAttributeSet($name, $skeletonSetId = null)
    {
        // Validate Name
        if ($set = $this->getProductAttributeSet($name)) {
            $this->logger->notice(__('Attribute Set creation skipped for set: %1', $name));
            return $set;
        }

        $set = $this->getAttributeSetModel()
            ->setEntityTypeId($this->getEavSetup()->getEntityTypeId($this->getProductEntityType()))
            ->setAttributeSetName(trim($name));

        if (!$set->validate()) {
            throw new LocalizedException(__('Invalid Attribute Set data, please check the attribute set data.'));
        }

        $set->save();

        // Init From Skeleton
        $skeletonSetId = $skeletonSetId ?: $this->getDefaultProductAttributeSetId();
        $set->initFromSkeleton($skeletonSetId)
            ->save();

        return $set;
    }

    /**
     * @param $name
     * @return CatalogInstaller
     * @throws LocalizedException
     */
    public function removeProductAttributeSet($name)
    {
        return $this->removeAttributeSet($name, $this->getProductEntityType());
    }

    /******************************************************************************************** Product Attribute ***/

    /**
     * @param $id
     * @return \Magento\Framework\DataObject|null
     * @throws LocalizedException
     */
    public function getProductAttribute($id)
    {
        return $this->getAttribute($id, $this->getProductEntityType());
    }

    /**
     * @param $id
     * @param $field
     * @param null $value
     * @return $this
     * @throws LocalizedException
     */
    public function updateProductAttribute($id, $field, $value = null)
    {
        $this->getEavSetup()->updateAttribute($this->getProductEntityType(), $id, $field, $value);
        return $this;
    }

    /**
     * @param $code
     * @return $this
     * @throws LocalizedException
     */
    public function removeProductAttribute($code)
    {
        $this->getEavSetup()
            ->removeAttribute($this->getProductEntityType(), $code);
        return $this;
    }

    /**
     * @param $code
     * @param $data
     * @return $this
     * @throws LocalizedException
     */
    public function addProductAttribute($code, $data)
    {
        $this->getEavSetup()
            ->addAttribute($this->getProductEntityType(), $code, $data);
        return $this;
    }

    /**
     * @param $attributes
     * @return $this
     */
    public function addProductAttributes($attributes)
    {
        foreach ($attributes as $code => $data) {
            $this->addProductAttribute($code, $data);
        }
        return $this;
    }

    /**
     * @param $setId
     * @param $groupId
     * @param $attributeId
     * @param null $sortOrder
     * @return $this
     * @throws LocalizedException
     */
    public function addProductAttributeToGroup($setId, $groupId, $attributeId, $sortOrder = null)
    {
        $this->getEavSetup()
            ->addAttributeToGroup($this->getProductEntityType(), $setId, $groupId, $attributeId, $sortOrder);
        return $this;
    }


    /******************************************************************************************************************/
    /********************************************************************************************* Category Methods ***/
    /******************************************************************************************************************/


    /*************************************************************************************** Category Attribute Set ***/

    /**
     * @return CategoryAttributeRepositoryInterface
     */
    public function getCategoryAttributeRepository()
    {
        return $this->categoryAttributeRepository;
    }

    /**
     * @return string
     */
    protected function getCategoryEntityType()
    {
        return Category::ENTITY;
    }

    /******************************************************************************************** Category Attribute ***/

    /**
     * @param $id
     * @return \Magento\Framework\DataObject|null
     * @throws LocalizedException
     */
    public function getCategoryAttribute($id)
    {
        return $this->getAttribute($id, $this->getCategoryEntityType());
    }

    /**
     * @param $code
     * @return $this
     * @throws LocalizedException
     */
    public function removeCategoryAttribute($code)
    {
        $this->getEavSetup()
            ->removeAttribute($this->getCategoryEntityType(), $code);
        return $this;
    }

    /**
     * @param $code
     * @param $data
     * @return $this
     * @throws LocalizedException
     */
    public function addCategoryAttribute($code, $data)
    {
        $this->getEavSetup()
            ->addAttribute($this->getCategoryEntityType(), $code, $data);
        return $this;
    }

    /**
     * @param $attributes
     * @return $this
     */
    public function addCategoryAttributes($attributes)
    {
        foreach ($attributes as $code => $data) {
            $this->addCategoryAttribute($code, $data);
        }
        return $this;
    }

    /******************************************************************************************************************/
    /********************************************************************************************* Category Methods ***/
    /******************************************************************************************************************/

    /**
     * @param $storeCode
     * @param array $categories
     * @return $this
     */
    public function createCategoryTree($storeCode, Array $categories)
    {
        $store = $this->getStore($storeCode);
        if (!$store->getId()) {
            $this->logger->warning(__('There is no Store with code: %1', $storeCode));
            return $this;
        }
        // Load Root Category
        $rootCategory = $this->categoryFactory->create()->load($store->getRootCategoryId());
        // Append Children
        $this->addCategoryTreeChildren($rootCategory, $categories);

        return $this;
    }

    /**
     * @param Category $parentCategory
     * @param array $children
     */
    public function addCategoryTreeChildren(Category $parentCategory, Array $children)
    {
        foreach ($children as $index => $categoryData) {
            // Prepare Data
            $category = $this->categoryFactory->create();
            /** @var \Magento\Catalog\Model\Category\ $category */

            // Set Data
            $category->addData($this->categorySkeleton)
                ->setName($categoryData['name'])
                ->setPath($parentCategory->getPath())
                ->setIsAnchor(isset($categoryData['children']))
                ->setPosition($index + 1)
                ->save();

            // Recursive
            if (isset($categoryData['children']) && is_array($categoryData['children'])) {
                $this->addCategoryTreeChildren($category, $categoryData['children']);
            }

            // Store Names
            if (isset($categoryData['store_name']) && is_array($categoryData['store_name'])) {
                foreach ($categoryData['store_name'] as $code => $name) {
                    $store = $this->getStore($code);
                    if (!$store->getId()) {
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

    /**
     * @return \Magento\Catalog\Model\ResourceModel\Category\Collection
     */
    public function getCategoryCollection()
    {
        return $this->categoryCollectionFactory->create();
    }

    /**
     * @return Category $category
     */
    public function getCategoryModel()
    {
        return $this->categoryFactory->create();
    }

    /**
     * @param array $filters
     * @return $this
     */
    public function cleanCategoriesUrlRewrites($filters = [])
    {
        $baseFilter = [
            'entity_type' => ['eq' => 'category'],
        ];
        return $this->cleanUrlRewrites(array_merge_recursive($baseFilter, $filters));
    }

    /**
     * @param Category $category
     * @return $this
     */
    public function saveCategoryUrlRewrite(Category $category)
    {
        $categoryUrlRewriteGenerator = $this->objectManager->get('Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGenerator');
        $urlPersist = $this->objectManager->get('Magento\UrlRewrite\Model\UrlPersistInterface');
        $urlRewriteHandler = $this->objectManager->get('Magento\CatalogUrlRewrite\Observer\UrlRewriteHandler');
        $urlRewrites = array_merge(
            $categoryUrlRewriteGenerator->generate($category),
            $urlRewriteHandler->generateProductUrlRewrites($category)
        );
        $urlPersist->replace($urlRewrites);
        return $this;
    }

    /******************************************************************************************************************/
    /******************************************************************************************************** Tools ***/
    /******************************************************************************************************************/

    /**
     * @param array $filters
     * @return $this
     */
    public function cleanUrlRewrites($filters = [])
    {
        if (!count($filters)) {
            $this->logger->alert(__('Process cleanUrlRewrites skipped, no filters received.'));
            return $this;
        }
        $collection = $this->objectManager
            ->get('Magento\UrlRewrite\Model\ResourceModel\UrlRewriteCollection');
        /* @var \Magento\UrlRewrite\Model\ResourceModel\UrlRewriteCollection $collection */
        foreach ($filters as $field => $condition) {
            $collection->addFieldToFilter($field, $condition);
        }
        $collection->getConnection()->query(
            $collection->getConnection()->deleteFromSelect($collection->getSelect(), 'main_table')
        );
        return $this;
    }

    /**
     * @param $storeCode
     * @return mixed
     */
    protected function getStore($storeCode)
    {
        if (!isset($this->loadedStores[$storeCode])) {
            $this->loadedStores[$storeCode] = $this->storeInstaller->getStore($storeCode);
        }
        return $this->loadedStores[$storeCode];
    }

}