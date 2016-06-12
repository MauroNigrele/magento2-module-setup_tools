<?php

namespace MauroNigrele\SetupTools\Model\Setup;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\DataObject;
use Psr\Log\LoggerInterface;


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
    }

    protected function allowRemoveAction()
    {
        // Allow Remove
        $this->registry->register('isSecureArea', true);
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

    /*********************************************************************************************/
    /********************************************************************************** OBJECTS **/
    /*********************************************************************************************/


    /**
     * @param $object
     * @param bool $removeBaseMethods
     * @return $this
     */
    public function debugObject($object, $removeBaseMethods = true)
    {
        $class = get_class($object);
        $methods = get_class_methods($object);
        sort($methods);
        if ($object instanceof DataObject && $removeBaseMethods) {
            $baseMethods = get_class_methods($this->objectManager->create('Magento\Framework\DataObject'));
            foreach ($methods as $k => $v) {
                if (in_array($v, $baseMethods)) {
                    unset($methods[$k]);
                }
            }
        }

        $this->logger->info(":: ".$class." :: ");
        $this->logger->info(print_r($methods, true)."\n");

        return $this;
    }
}
