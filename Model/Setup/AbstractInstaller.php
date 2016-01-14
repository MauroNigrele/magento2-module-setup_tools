<?php

namespace MauroNigrele\SetupTools\Model\Setup;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use Psr\Log\LoggerInterface;

abstract class AbstractInstaller
{

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $configReader;

    /**
     * @var WriterInterface
     */
    protected $configWriter;

    /**
     * AbstractInstaller constructor.
     * @param ObjectManagerInterface $objectManager
     * @param Registry $registry
     * @param LoggerInterface $logger
     * @param ScopeConfigInterface $config
     * @param WriterInterface $configWriter
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        Registry $registry,
        LoggerInterface $logger,
        ScopeConfigInterface $config,
        WriterInterface $configWriter
    )
    {
        $this->objectManager = $objectManager;
        $this->registry = $registry;
        $this->logger = $logger;
        $this->configReader = $config;
        $this->configWriter = $configWriter;
    }

    /**
     * @param $path
     * @param string $scopeType
     * @param null $scopeCode
     * @return mixed
     */
    public function getConfig($path, $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeCode = null)
    {
        return $this->configReader->getValue($path,$scopeType,$scopeCode);
    }

    /**
     * @param $path
     * @param string $scopeType
     * @param null $scopeCode
     * @return mixed
     */
    public function getConfigFlag($path, $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeCode = null)
    {
        return $this->configReader->isSetFlag($path,$scopeType,$scopeCode);
    }
    
    /**
     * @param $path
     * @param $value
     * @param string $scope
     * @param int $scopeId
     * @return $this
     */
    public function setConfig($path, $value, $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeId = 0)
    {
        $this->configWriter->save($path, $value, $scope, $scopeId);
        return $this;
    }

}