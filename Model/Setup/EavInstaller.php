<?php
/**
 * Created by PhpStorm.
 * User: max
 * Date: 3/10/16
 * Time: 17:56
 */

namespace MauroNigrele\SetupTools\Model\Setup;

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

use Magento\Framework\Exception\LocalizedException;

class EavInstaller extends AbstractInstaller
{

    /**
     * @var SetFactory
     */
    protected $attributeSetFactory;

    /**
     * @var GroupFactory
     */
    protected $attributeGroupFactory;

    /**
     * @var AttributeFactory
     */
    protected $attributeFactory;

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
        // Eav Installer
        $this->attributeSetFactory = $attributeSetFactory;
        $this->attributeGroupFactory = $attributeGroupFactory;
        $this->attributeFactory = $attributeFactory;
        // Abstract Installer
        parent::__construct($objectManager, $registry, $logger, $config, $configWriter);
    }

    /******************************************************************************************************************/
    /************************************************************************************************** EAV Methods ***/
    /******************************************************************************************************************/

    /******************************************************************************************* EAV Attributes Set ***/

    /**
     * @return \Magento\Eav\Model\Entity\Attribute\Set
     */
    protected function getAttributeSetModel()
    {
        return $this->attributeSetFactory->create();
    }

    /**
     * @param int|string $id
     * @param null|integer $entityTypeId
     * @return \Magento\Eav\Model\Entity\Attribute\Set | null
     * @throws LocalizedException
     */
    public function getAttributeSet($id, $entityTypeId = null)
    {
        if (is_numeric($id)) {
            return $this->getAttributeSetModel()->load($id);
        }
        if (!is_null($entityTypeId)) {
            $set = $this->getAttributeSetModel()
                ->getResourceCollection()
                ->addFieldToFilter('entity_type_id',
                    array('eq' => $this->getEavSetup()->getEntityTypeId($entityTypeId)))
                ->addFieldToFilter('attribute_set_name', array('eq' => $id))
                ->getFirstItem();
            return ($set->getId()) ? $set : null;
        }
        throw new LocalizedException(__('You have to define the "entity_type_id" in order to get an Attribute Set by Name.'));
    }

    /**
     * @param $name
     * @param $entityTypeId
     * @return $this
     * @throws LocalizedException
     */
    public function removeAttributeSet($name, $entityTypeId = null)
    {
        $set = $this->getAttributeSet($name, $entityTypeId);
        if (is_null($set)) {
            throw new LocalizedException(__('There is no "Attribute Set" with Name or Id = "%1".', $name));
        }
        $set->delete();
        return $this;
    }


    /***************************************************************************************** EAV Attributes Group ***/

    /**
     * @return \Magento\Eav\Model\Entity\Attribute\Group
     */
    protected function getAttributeGroupModel()
    {
        return $this->attributeGroupFactory->create();
    }

    /**
     * Load Attribute Group by 'name' or 'id'
     *
     * @param int|string $id
     * @param int|null $setId
     * @return \Magento\Framework\DataObject|null
     * @throws LocalizedException
     */
    public function getAttributeGroup($id, $setId = null)
    {
        if (is_numeric($id)) {
            return $this->getAttributeGroupModel()->load($id);
        }
        if (!is_null($setId)) {
            $group = $this->getAttributeGroupModel()->getResourceCollection()
                ->addFieldToFilter('attribute_group_name', array('eq' => $id))
                ->addFieldToFilter('attribute_set_id', array('eq' => $setId))
                ->getFirstItem();
            return ($group->getId()) ? $group : null;
        }
        throw new LocalizedException(__('You have to define the "set_id" in order to get an AttributeGroup by Name.'));
    }

    /**
     * @param string $name
     * @param int $setId
     * @param array $data
     * @return \Magento\Eav\Model\Entity\Attribute\Group
     * @throws LocalizedException
     */
    public function addAttributeGroup($name, $setId, $data = [])
    {
        // Validation
        if ($group = $this->getAttributeGroup($name, $setId)) {
            $this->logger->notice(__('Attribute Group creation skipped for group: %1', $name));
            return $group;
        }
        $group = $this->getAttributeGroupModel()
            ->setAttributeGroupName($name)
            ->setAttributeSetId($setId)
            ->addData($data)
            ->save();
        return $group;
    }

    public function removeAttributeGroup($name, $setId = null)
    {
        $group = $this->getAttributeGroup($name, $setId);
        if (is_null($group)) {
            throw new LocalizedException(__('There is no "Attribute Group" with Name or Id: "%1" in Set.', $name));
        }
        $group->delete();
        return $this;
    }

    /******************************************************************************************** EAV Attribute ***/

    /**
     * @return \Magento\Eav\Model\Attribute
     */
    protected function getAttributeModel()
    {
        return $this->attributeFactory->create();
    }

    /**
     * @param $id
     * @param null $entityTypeId
     * @return \Magento\Framework\DataObject|null
     * @throws LocalizedException
     */
    public function getAttribute($id, $entityTypeId = null)
    {
        if (is_numeric($id)) {
            return $this->getAttributeModel()->load($id);
        }
        if (!is_null($entityTypeId)) {
            $attribute = $this->getAttributeModel()->getResourceCollection()
                ->addFieldToFilter('entity_type_id',
                    array('eq' => $this->getEavSetup()->getEntityTypeId($entityTypeId)))
                ->addFieldToFilter('attribute_code', array('eq' => $id))
                ->getFirstItem();
            return ($attribute->getId()) ? $attribute : null;
        }
        throw new LocalizedException(__('You have to define the "entity_type_id" in order to get an Attribute by code.'));
    }

    
}