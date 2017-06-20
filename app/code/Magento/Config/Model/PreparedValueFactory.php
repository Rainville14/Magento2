<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Config\Model;

use Magento\Config\Model\Config\BackendFactory;
use Magento\Config\Model\Config\PreparedValue\AdditionalData;
use Magento\Config\Model\Config\Structure;
use Magento\Config\Model\Config\StructureFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\ValueInterface;
use Magento\Framework\App\Config\Value;
use Magento\Store\Model\ScopeInterface as StoreScopeInterface;
use Magento\Framework\App\ScopeResolverPool;
use Magento\Framework\Exception\RuntimeException;

/**
 * Creates a prepared instance of Value.
 *
 * @see ValueInterface
 */
class PreparedValueFactory
{
    /**
     * The scope resolver pool.
     *
     * @var ScopeResolverPool
     */
    private $scopeResolverPool;

    /**
     * The manager for system configuration structure.
     *
     * @var StructureFactory
     */
    private $structureFactory;

    /**
     * The factory for configuration value objects.
     *
     * @see ValueInterface
     * @var BackendFactory
     */
    private $valueFactory;

    /**
     * The scope configuration.
     *
     * @var ScopeConfigInterface
     */
    private $config;

    /**
     * Additional data applier for backend model.
     *
     * @var Config\PreparedValue\AdditionalData
     */
    private $additionalData;

    /**
     * @param ScopeResolverPool $scopeResolverPool The scope resolver pool
     * @param StructureFactory $structureFactory The manager for system configuration structure
     * @param BackendFactory $valueFactory The factory for configuration value objects
     * @param ScopeConfigInterface $config The scope configuration
     * @param AdditionalData $additionalData The additional data applier for backend model
     */
    public function __construct(
        ScopeResolverPool $scopeResolverPool,
        StructureFactory $structureFactory,
        BackendFactory $valueFactory,
        ScopeConfigInterface $config,
        AdditionalData $additionalData
    ) {
        $this->scopeResolverPool = $scopeResolverPool;
        $this->structureFactory = $structureFactory;
        $this->valueFactory = $valueFactory;
        $this->config = $config;
        $this->additionalData = $additionalData;
    }

    /**
     * Returns instance of Value with defined properties.
     *
     * @param string $path The configuration path in format group/section/field_name
     * @param string $value The configuration value
     * @param string $scope The configuration scope (default, website, or store)
     * @param string|int|null $scopeCode The scope code
     * @return ValueInterface
     * @throws RuntimeException If Value can not be created
     * @see ValueInterface
     * @see Value
     */
    public function create($path, $value, $scope, $scopeCode = null)
    {
        try {
            /** @var Structure $structure */
            $structure = $this->structureFactory->create();
            /** @var Structure\ElementInterface $field */
            $field = $structure->getElementByConfigPath($path);
            $configPath = $path;
            /** @var string $backendModelName */
            if ($field instanceof Structure\Element\Field && $field->hasBackendModel()) {
                $backendModelName = $field->getData()['backend_model'];
                $configPath = $field->getConfigPath() ?: $path;
            } else {
                $backendModelName = ValueInterface::class;
            }
            /** @var ValueInterface $backendModel */
            $backendModel = $this->valueFactory->create(
                $backendModelName,
                ['config' => $this->config]
            );

            if ($backendModel instanceof Value) {
                $scopeId = 0;

                if (in_array($scope, [StoreScopeInterface::SCOPE_WEBSITE, StoreScopeInterface::SCOPE_WEBSITES])) {
                    $scope = StoreScopeInterface::SCOPE_WEBSITES;
                } elseif (in_array($scope, [StoreScopeInterface::SCOPE_STORE, StoreScopeInterface::SCOPE_STORES])) {
                    $scope = StoreScopeInterface::SCOPE_STORES;
                }

                if ($scope !== ScopeConfigInterface::SCOPE_TYPE_DEFAULT) {
                    $scopeResolver = $this->scopeResolverPool->get($scope);
                    $scopeId = $scopeResolver->getScope($scopeCode)->getId();
                }

                $backendModel->setPath($configPath);
                $backendModel->setScope($scope);
                $backendModel->setScopeId($scopeId);
                $backendModel->setValue($value);
                $this->additionalData->apply($backendModel);
            }

            return $backendModel;
        } catch (\Exception $exception) {
            throw new RuntimeException(__('%1', $exception->getMessage()), $exception);
        }
    }
}
