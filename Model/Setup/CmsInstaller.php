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
use Zend\Code\Generator\ValueGenerator;

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
    /*********************************************************************** Export Functions **/
    /*********************************************************************************************/
    
    public function createContentFile($entityType,$contentType,$identifier,$content)
    {
        $filename =  $entityType . DIRECTORY_SEPARATOR . $identifier . '.' . $contentType;
        $folder = $this->getContentFolder() . DIRECTORY_SEPARATOR;
        
        // Write
        $this->fileIo->write($folder . $filename,$content);
        
        return $filename;
        
    }
    
    
    public function createContentBlockHtmlFile($identifier,$content)
    {
        return $this->createContentFile('block','html',$identifier,$content);
    }
    
    public function createContentPageHtmlFile($identifier,$content)
    {
        return $this->createContentFile('page','html',$identifier,$content);
    }
    
    public function createContentPageXmlFile($identifier,$content)
    {
        return $this->createContentFile('page','xml',$identifier,$content);
    }
    
    public function createPhpPageListFile($content)
    {
        return $this->createContentFile('page','php','page-list',$content);
    }
    
    public function createPhpBlockListFile($content)
    {
        return $this->createContentFile('block','php','block-list',$content);
    }
    
    /**
     * @param null $storeIds
     * @param array $filters
     * @return $this
     */
    public function exportBlocksData($storeIds = null, $filters = [])
    {
        $data = [];
        $collection = $this->getBlockCollection();
        
        if(null !== $storeIds) {
            $collection->addStoreFilter($storeIds,false);
        }
        foreach($filters as $field => $filter) {
            $collection->addFieldToFilter($field,$filter);
        }
        
        foreach ($collection as $item) {
            /* @var $item Block */
            
            $ids = [];
            $data[$item->getIdentifier()] = [
                'id' => $item->getIdentifier(),
                'content_file' => $this->createContentBlockHtmlFile($item->getIdentifier(),$item->getContent()),
                'store_id' => $item->getStores(),
                'params' => [
                    'title' => $item->getTitle(),
                    'is_active' => $item->isActive(),
                ],
            ];
        }
        
        $phpFileContent = "<?php\n";
        $phpFileContent .= '$list = ';
        $phpFileContent .= $this->formattedExport($data);
        $phpFileContent .= ";\n";
        
        $this->createPhpBlockListFile($phpFileContent);
        
        return $this;


////        use Zend\Code\Generator\ValueGenerator;
//        $generator = $this->objectManager->create('Zend\Code\Generator\ValueGenerator');
//        /* @var $generator ValueGenerator */
//        $generator->setIndentation('    '); // 2 spaces
//        $generator->setValue($data);
//        $generator->getType(ValueGenerator::TYPE_ARRAY);
//        echo "\n ---- --- EXPORT --- ---\n";
//        echo $this->formattedExport($data);
//        var_export($data);
//        echo "\n ---- --- EXPORT --- ---\n";
//        die();
    
    }
    
    /**
     * @param null $storeIds
     * @param array $filters
     * @return $this
     */
    public function exportPagesData($storeIds = null, $filters = [])
    {
        $data = [];
        $collection = $this->getPageCollection();
        
        if(null !== $storeIds) {
            $collection->addStoreFilter($storeIds,false);
        }
        foreach($filters as $field => $filter) {
            $collection->addFieldToFilter($field,$filter);
        }
        
        foreach ($collection as $item) {
            /* @var $item Page */
            $ids = [];
            $data[$item->getIdentifier()] = [
                'id' => $item->getIdentifier(),
                'content_file' => $this->createContentPageHtmlFile($item->getIdentifier(),$item->getContent()),
                'layout_file' => $this->createContentPageXmlFile($item->getIdentifier(),$item->getLayoutUpdateXml()),
                'store_id' => $item->getStores(),
                'params' => [
                    'title' => $item->getTitle(),
                    'is_active' => $item->isActive(),
                    'title' => $item->getTitle(),
                    'page_layout' => $item->getPageLayout(),
                    'content_heading' => $item->getContentHeading(),
                    // Meta
                    'meta_title' => $item->getMetaTitle(),
                    'meta_description' => $item->getMetaDescription(),
                    'meta_keywords' => $item->getMetaKeywords(),
                ],
            ];
        }
        
        
        $phpFileContent = "<?php\n";
        $phpFileContent .= '$list = ';
        $phpFileContent .= $this->formattedExport($data);
        $phpFileContent .= ";\n";
        
        $this->createPhpPageListFile($phpFileContent);
        
        return $this;
        
    }
    
    
    /*********************************************************************************************/
    /*********************************************************************** Internal Functions **/
    /*********************************************************************************************/
    
    /**
     * @todo Move to Abstract
     *
     * @param $filename
     * @param null $folder
     * @return null
     *
     */
    public function getFileContent($filename, $folder = null)
    {
        $path = $this->getContentFolder($folder) . DIRECTORY_SEPARATOR . $filename;
        
        // Exists
        if(!$this->fileIo->fileExists($path)) {
            return null;
        }
        // Valid Content
        $content = $this->fileIo->read($path);
        return (empty($content)) ? null : $content;
    }
    
    /**
     * @todo Move to Abstract
     * @param null $folder
     * @return string
     */
    public function getContentFolder($folder = null)
    {
        $folder = ($folder) ?: 'content';
        $moduleDir = $this->reader->getModuleDir('',$this->getModuleName());
        return $moduleDir . DIRECTORY_SEPARATOR . $folder;
    }
    
}