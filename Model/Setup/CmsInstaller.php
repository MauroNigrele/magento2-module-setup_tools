<?php

namespace MauroNigrele\SetupTools\Model\Setup;

use Psr\Log\LoggerInterface;

use Magento\Framework\Registry;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Filesystem\Io\File as FileIo;


use Magento\Store\Model\StoreManagerInterface;

use Magento\Cms\Model\PageFactory;
use Magento\Cms\Model\Page;
use Magento\Cms\Model\ResourceModel\Page\Collection as PageCollection;
use Magento\Cms\Model\BlockFactory;
use Magento\Cms\Model\Block;
use Magento\Cms\Model\ResourceModel\Block\Collection as BlockCollection;

/**
 * Class CmsInstaller
 * @package MauroNigrele\SetupTools\Model\Setup
 *
 * @SuppressWarnings(PHPMD.LongVariable)
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CmsInstaller extends AbstractInstaller
{

    /**
     * @var bool
     */
    protected $isStrictMode = false;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var PageFactory
     */
    protected $pageFactory;

    /**
     * @var BlockFactory
     */
    protected $blockFactory;

    /**
     * @var []
     */
    protected $stores;
    
    /**
     * @var Reader
     */
    protected $reader;
    
    /**
     * @var FileIo
     */
    protected $fileIo;

    /*********************************************************************************************/
    /******************************************************************************** SKELETONS **/
    /*********************************************************************************************/

    /**
     * @var array
     */
    protected $defaultPageData = [
        'is_active' => '1',
        'sort_order' => '0',
        'page_layout' => '1column',
        'under_version_control' => '0',
    ];

    /**
     * @var array
     */
    protected $defaultBlockData = [
        'is_active' => 1,
        'sort_order' => 0,
    ];
    
    /**
     * CmsInstaller constructor.
     * @param ObjectManagerInterface $objectManager
     * @param Registry $registry
     * @param LoggerInterface $logger
     * @param ScopeConfigInterface $config
     * @param WriterInterface $configWriter
     * @param StoreManagerInterface $storeManager
     * @param PageFactory $pageFactory
     * @param BlockFactory $blockFactory
     * @param Reader $reader
     * @param FileIo $fileIo
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
        PageFactory $pageFactory,
        BlockFactory $blockFactory,
        Reader $reader,
        FileIo $fileIo
    ) {
        $this->storeManager = $storeManager;
        $this->pageFactory = $pageFactory;
        $this->blockFactory = $blockFactory;
        $this->reader = $reader;
        $this->fileIo = $fileIo;
        
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
     * @return PageFactory
     */
    public function getPageFactory()
    {
        return $this->pageFactory;
    }

    /**
     * @return Page
     */
    public function getPageModel()
    {
        return $this->getPageFactory()->create();
    }

    /**
     * @return PageCollection
     */
    public function getPageCollection()
    {
        return $this->getPageModel()->getCollection();
    }

    /**
     * @return BlockFactory
     */
    public function getBlockFactory()
    {
        return $this->blockFactory;
    }

    /**
     * @return Block
     */
    public function getBlockModel()
    {
        return $this->getBlockFactory()->create();
    }
    
    /**
     * @return BlockCollection
     */
    public function getBlockCollection()
    {
        return $this->getBlockModel()->getCollection();
    }
    
    /**
     * @return bool
     */
    public function getIsStrictMode()
    {
        return $this->isStrictMode;
    }

    /**
     * @param $value
     */
    public function setIsStrictMode($value)
    {
        $this->isStrictMode = (bool) $value;
    }

    /*********************************************************************************************/
    /****************************************************************************** CRUD - Page **/
    /*********************************************************************************************/

    /**
     * @param $id
     * @param null|array $storeIds
     * @return \Magento\Framework\DataObject|null
     */
    public function getPage($id, $storeIds = null)
    {
        // Load By Id
        if (is_numeric($id)) {
            return $this->getPageModel()->load($id);
        }

        $storeIds = $this->getStoreIds($storeIds);
        $collection = $this->getPageCollection()
            ->addFieldToFilter('identifier', array('eq' => $id))
            ->addStoreFilter($storeIds);

        return ($collection->count()) ? $collection->getFirstItem() : null;

    }

    public function createPage($id, $content, $storeIds = null, $params = [])
    {
        // Validate Already Exists
        $page = $this->getPage($id, $storeIds);
        if ($page) {
            if ($this->isStrictMode) {
                $stores = is_array($storeIds) ? implode(',', $storeIds) : $storeIds;
                throw new AlreadyExistsException(__('Page with ID: %1 Already exists for Store(s) %2', $id, $stores));
            }
            return $this->updatePage($id, $content, $storeIds, $params);
        }

        // Create New
        $params = array_merge($this->defaultPageData, $params);
        $stores = $this->getStoreIds($storeIds);
        if (!is_array($stores)) {
            $stores = array($stores);
        }
        $page = $this->getPageModel()
            ->setData($params)
            ->setIdentifier($id)
            ->setContent($content)
            ->setStores($stores)
            ->save();
        return $page->save();
    }

    /**
     * @param $id
     * @param null $content
     * @param $storeIds
     * @param array $params
     * @return Page|null
     * @throws NoSuchEntityException
     */
    public function updatePage($id, $content = null, $storeIds = null, $params = [])
    {
        $page = $this->getPage($id, $storeIds);
        if (!$page) {
            if ($this->isStrictMode) {
                $stores = is_array($storeIds) ? implode(',', $storeIds) : $storeIds;
                throw new NoSuchEntityException(__('Page with ID: %1 Does not exists for Store(s) %2', $id, $stores));
            }
            return null;
        }

        // Update Data
        $page->setStores($this->getStoreIds($storeIds));
        if ($content) {
            $page->setContent($content);
        }
        // @todo Add $params filtering to allow stores change in params (mapping)
        if (count($params)) {
            $page->addData($params);
        }
        return $page->save();
    }

    public function deletePage($id, $storeIds = null)
    {
        $page = $this->getPage($id, $storeIds);
        if (!$page) {
            if ($this->isStrictMode) {
                $stores = is_array($storeIds) ? implode(',', $storeIds) : $storeIds;
                throw new NoSuchEntityException(__('Page with ID: %1 Does not exists for Store(s) %2', $id, $stores));
            }
            return null;
        }
        $page->delete();
        return $this;
    }

    public function deleteAllPages()
    {
        foreach ($this->getPageCollection() as $page) {
            $page->delete();
        }
        return $this;
    }

    /*********************************************************************************************/
    /***************************************************************************** CRUD - Block **/
    /*********************************************************************************************/
    
    
    /**
     * @param $id
     * @param null|array $storeIds
     * @return Block|null
     */
    public function getBlock($id, $storeIds = null)
    {
        // Load By Id
        if (is_numeric($id)) {
            return $this->getBlockModel()->load($id);
        }
        
        $storeIds = $this->getStoreIds($storeIds);
        $collection = $this->getBlockCollection()
            ->addFieldToFilter('identifier', array('eq' => $id))
            ->addStoreFilter($storeIds);
        
        return ($collection->count()) ? $collection->getFirstItem() : null;
        
    }
    
    public function createBlock($id, $content, $storeIds = null, $params = [])
    {
        // Validate Already Exists
        $block = $this->getBlock($id, $storeIds);
        if ($block) {
            if ($this->isStrictMode) {
                $stores = is_array($storeIds) ? implode(',', $storeIds) : $storeIds;
                throw new AlreadyExistsException(__('Block with ID: %1 already exists for Store(s) %2', $id, $stores));
            }
            return $this->updateBlock($id, $content, $storeIds, $params);
        }
        
        // Create New
        $params = array_merge($this->defaultBlockData, $params);
        $stores = $this->getStoreIds($storeIds);
        if (!is_array($stores)) {
            $stores = array($stores);
        }
        $block = $this->getBlockModel()
            ->setData($params)
            ->setIdentifier($id)
            ->setContent($content)
            ->setStores($stores)
            ->save();
        
        return $block->save();
    }
    
    /**
     * @param $id
     * @param null $content
     * @param $storeIds
     * @param array $params
     * @return Block|null
     * @throws NoSuchEntityException
     */
    public function updateBlock($id, $content = null, $storeIds = null, $params = [])
    {
        $block = $this->getBlock($id, $storeIds);
        if (!$block) {
            if ($this->isStrictMode) {
                $stores = is_array($storeIds) ? implode(',', $storeIds) : $storeIds;
                throw new NoSuchEntityException(__('Page with ID: %1 Does not exists for Store(s) %2', $id, $stores));
            }
            return null;
        }
        
        // Update Data
        $block->setStores($this->getStoreIds($storeIds));
        if ($content) {
            $block->setContent($content);
        }
        // @todo Add $params filtering to allow stores change in params (mapping)
        if (count($params)) {
            $block->addData($params);
        }
        return $block->save();
    }
    
    /**
     * @param $id
     * @param null $storeIds
     * @return $this|null
     * @throws NoSuchEntityException
     */
    public function deleteBlock($id, $storeIds = null)
    {
        $block = $this->getBlock($id, $storeIds);
        if (!$block) {
            if ($this->isStrictMode) {
                $stores = is_array($storeIds) ? implode(',', $storeIds) : $storeIds;
                throw new NoSuchEntityException(__('Block with ID: %1 does not exists for Store(s) %2', $id, $stores));
            }
            return null;
        }
        $block->delete();
        return $this;
    }
    
    /**
     * @return $this
     */
    public function deleteAllBlocks()
    {
        foreach ($this->getBlockCollection() as $block) {
            $block->delete();
        }
        return $this;
    }
    

    /*********************************************************************************************/
    /*********************************************************************** Internal Functions **/
    /*********************************************************************************************/

    protected function getStores()
    {
        if (!$this->stores) {
            $this->stores = $this->getStoreManager()->getStores(true, true);
        }
        return $this->stores;
    }

    protected function getStoreId($id)
    {
        // CASE: is an ID
        if (is_numeric($id)) {
            return $id;
        }
        // CASE: is a string identifier
        foreach ($this->getStores() as $k => $store) {
            if ($k == $id) {
                return $store->getId();
            }
        }
        // CASE: Doesn't Exists
        if ($this->isStrictMode) {
            throw new NoSuchEntityException(__('Invalid Store Code: %1', $id));
        }
        return null;
    }

    protected function getStoreIds($stores = null)
    {
        // Default Case
        if (is_null($stores)) {
            return [0];
        }
        // Single Value Case
        if (!is_array($stores)) {
            $storeId = $this->getStoreId($stores);
            return $storeId;
        }
        // Array Values Case
        $storeIds = [];
        foreach ($stores as $storeCode) {
            $storeId = $this->getStoreId($storeCode);
            if ($storeId) {
                $storeIds[] = $storeId;
            }
        }
        return (count($storeIds)) ? $storeIds : [0];
    }
    
    /*********************************************************************************************/
    /*********************************************************************** Internal Functions **/
    /*********************************************************************************************/
    
    /**
     * @param $filename
     * @param null $folder
     * @return null
     */
    public function getFileContent($filename, $folder = null)
    {
        $path = $this->getContentFolder($folder) . DIRECTORY_SEPARATOR . $filename;
        
        var_dump('EVALUATING: ' . $path);
        
        // Exists
        if(!$this->fileIo->fileExists($path)) {
            return null;
        }
        // Valid Content
        $content = $this->fileIo->read($path);
        return (empty($content)) ? null : $content;
    }
    
    public function getContentFolder($folder = null)
    {
        $folder = ($folder) ?: 'content';
        $moduleDir = $this->reader->getModuleDir('',$this->getModuleName());
        return $moduleDir . DIRECTORY_SEPARATOR . $folder;
    }

}
