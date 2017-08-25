<?php

namespace MauroNigrele\SetupTools\Model\Setup;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\DataObject;
use Magento\TestFramework\Inspection\Exception;
use Psr\Log\LoggerInterface;

/**
 * Class Installer
 * @package MauroNigrele\SetupTools\Model\Setup
 *
 * @SuppressWarnings(PHPMD.LongVariable)
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Installer extends AbstractInstaller
{

    /**
     * @var StoreInstaller
     */
    protected $storeInstaller;

    /**
     * @var CatalogInstaller
     */
    protected $catalogInstaller;

    /**
     * @var CmsInstaller
     */
    protected $cmsInstaller;

    /**
     * @var CustomerInstaller
     */
    protected $customerInstaller;

    /**
     * @var SalesInstaller
     */
    protected $salesInstaller;

    /**
     * Installer constructor.
     * @param ObjectManagerInterface $objectManager
     * @param Registry $registry
     * @param LoggerInterface $logger
     * @param ScopeConfigInterface $config
     * @param WriterInterface $configWriter
     * @param CatalogInstaller $catalogInstaller
     * @param CmsInstaller $cmsInstaller
     * @param CustomerInstaller $customerInstaller
     * @param SalesInstaller $salesInstaller
     * @param StoreInstaller $storeInstaller
     */
    public function __construct(
        // Abstract Installer
        ObjectManagerInterface $objectManager,
        Registry $registry,
        LoggerInterface $logger,
        ScopeConfigInterface $config,
        //
        WriterInterface $configWriter,
        CatalogInstaller $catalogInstaller,
        CmsInstaller $cmsInstaller,
        CustomerInstaller $customerInstaller,
        SalesInstaller $salesInstaller,
        StoreInstaller $storeInstaller
    ) {
        // Installers
        $this->storeInstaller = $storeInstaller;
        $this->catalogInstaller = $catalogInstaller;
        $this->cmsInstaller = $cmsInstaller;
        $this->salesInstaller = $salesInstaller;
        $this->customerInstaller = $customerInstaller;
        // Parent
        parent::__construct($objectManager, $registry, $logger, $config, $configWriter);
        // Module Name
        $this->initModuleName();
        
    }
    
    protected function initModuleName()
    {
        if(!$this->moduleName) {
            
            $class = get_class($this);
            $moduleName = str_replace(
                '\\',
                '_',
                substr($class, 0, strpos($class, '\\Setup'))
            );
        }
        // Parent
        $this->setModuleName($moduleName);
        // Installers
        $this->getStoreInstaller()->setModuleName($moduleName);
        $this->getCatalogInstaller()->setModuleName($moduleName);
        $this->getCmsInstaller()->setModuleName($moduleName);
        $this->getSalesInstaller()->setModuleName($moduleName);
        $this->getCustomerInstaller()->setModuleName($moduleName);
        return $this;
    }

    /**
     * @param string $code
     * @return $this
     */
    protected function setAreaCode($code = 'frontend')
    {
        try {
            // Set State
            $this->objectManager->get('Magento\Framework\App\State')
                ->setAreaCode($code);
        } catch (\Exception $e) {
            // Already Set?
        }
        return $this;
    }

    /**
     * @return $this
     */
    protected function allowRemoveAction()
    {
        // Allow Remove
        if(!$this->registry->registry('isSecureArea')) {
            $this->registry->register('isSecureArea', true);
        }
        return $this;
    }
    
    /**
     * @param ModuleDataSetupInterface $setup
     * @return $this
     */
    public function setModuleDataSetup(ModuleDataSetupInterface $setup)
    {
        parent::setModuleDataSetup($setup);
        $this->getCatalogInstaller()->setModuleDataSetup($setup);
        $this->getCustomerInstaller()->setModuleDataSetup($setup);
        $this->getStoreInstaller()->setModuleDataSetup($setup);
        $this->getSalesInstaller()->setModuleDataSetup($setup);
        return $this;
    }
    
    /**
     * @return \Magento\Framework\Api\SearchCriteriaBuilder
     */
    public function getSearchCriteriaBuilder()
    {
        return $this->objectManager->create('Magento\Framework\Api\SearchCriteriaBuilder');
    }

    /*********************************************************************************************/
    /******************************************************************************* INSTALLERS **/
    /*********************************************************************************************/

    /**
     * @return StoreInstaller
     */
    public function getStoreInstaller()
    {
        return $this->storeInstaller;
    }

    /**
     * @return CatalogInstaller
     */
    public function getCatalogInstaller()
    {
        // Validate Init
        return $this->catalogInstaller;
    }

    /**
     * @return CmsInstaller
     */
    public function getCmsInstaller()
    {
        // Validate Init
        return $this->cmsInstaller;
    }

    /**
     * @return CustomerInstaller
     */
    public function getCustomerInstaller()
    {
        // Validate Init
        return $this->customerInstaller;
    }

    /**
     * @return SalesInstaller
     */
    public function getSalesInstaller()
    {
        // Validate Init
        return $this->salesInstaller;
    }

}
