<?php

namespace MauroNigrele\SetupTools\Model\Setup\Customer;

use MauroNigrele\SetupTools\Model\Setup\Eav\Installer as EavInstaller;

// Abstract Installer
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
// Eav Installer
use Magento\Eav\Model\Entity\Attribute\SetFactory;
use Magento\Eav\Model\Entity\Attribute\GroupFactory;
use Magento\Eav\Model\Entity\AttributeFactory;

/**
 * Class Installer
 * @package MauroNigrele\SetupTools\Model\Setup\Customer
 *
 * @SuppressWarnings(PHPMD.LongVariable)
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Installer extends EavInstaller
{
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
        AttributeFactory $attributeFactory
    ) {
        parent::__construct(
            $objectManager, $registry, $logger, $config, $configWriter, $attributeSetFactory,
            $attributeGroupFactory, $attributeFactory);
    }
}