<?php

namespace MauroNigrele\SetupTools\Model\Setup;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use Psr\Log\LoggerInterface;

class SalesInstaller extends AbstractInstaller
{
    public function __construct(
        ObjectManagerInterface $objectManager,
        Registry $registry,
        LoggerInterface $logger,
        ScopeConfigInterface $config,
        WriterInterface $configWriter
    ) {
        parent::__construct($objectManager, $registry, $logger, $config, $configWriter);
    }
}