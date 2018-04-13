<?php

namespace MauroNigrele\SetupTools\Model\Setup;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use Psr\Log\LoggerInterface;

use Magento\Sales\Setup\SalesSetup;
use Magento\Sales\Setup\SalesSetupFactory;

/**
 * Class SalesInstaller
 * @package MauroNigrele\SetupTools\Model\Setup
 *
 * @SuppressWarnings(PHPMD.LongVariable)
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SalesInstaller extends AbstractInstaller
{
    /**
     * @var SalesSetup
     */
    protected $salesSetup;
    
    /**
     * @var SalesSetupFactory
     */
    protected $salesSetupFactory;
    
    public function __construct(
        ObjectManagerInterface $objectManager,
        Registry $registry,
        LoggerInterface $logger,
        ScopeConfigInterface $config,
        WriterInterface $configWriter,
        // Custom
        SalesSetupFactory $salesSetupFactory
    ){
        parent::__construct($objectManager, $registry, $logger, $config, $configWriter);
        $this->salesSetupFactory = $salesSetupFactory;
    }

    public function getSalesSetup()
    {
        if(!$this->salesSetup) {
            $this->salesSetup = $this->salesSetupFactory->create();
        }
        return $this->salesSetup;
    }
    
    public function addAttribute($type,$code,$params = [])
    {
        return $this->getSalesSetup()->addAttribute($type,$code,$params);
    }

}