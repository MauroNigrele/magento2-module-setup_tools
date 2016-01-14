<?php

namespace MauroNigrele\SetupTools\Model\Setup;

use Magento\Catalog\Model\CategoryFactory;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use Psr\Log\LoggerInterface;

class CatalogInstaller extends AbstractInstaller
{
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
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        Registry $registry,
        LoggerInterface $logger,
        ScopeConfigInterface $config,
        WriterInterface $configWriter,
        CategoryFactory $categoryFactory
    )
    {
        $this->categoryFactory = $categoryFactory;
        parent::__construct($objectManager, $registry, $logger, $config, $configWriter);
    }
}