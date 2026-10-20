<?php
/**
 * Fixture for tests/fixtures.sh: hook names the core builds at runtime, including ones for the module's
 * own ObjectModel class, grid, form and admin controller, which no catalog can list.
 */
class FixtureDynamicHooks extends Module
{
    public function install()
    {
        return parent::install() && $this->registerHook([
            // classes/ObjectModel.php
            'actionObjectProductUpdateAfter',
            'actionObjectFixtureBannerAddAfter',
            'actionObjectFixtureBannerDeleteBefore',
            // src/Core/Form/IdentifiableObject
            'actionFeatureFormBuilderModifier',
            'actionFixtureBannerFormBuilderModifier',
            'actionAfterCreateFeatureFormHandler',
            'actionAfterUpdateFixtureBannerFormHandler',
            // src/Core/Grid
            'actionCustomerGridQueryBuilderModifier',
            'actionFixtureBannerGridDefinitionModifier',
            // classes/controller/AdminController.php
            'actionAdminProductsControllerSaveAfter',
            'actionAdminFixtureBannerControllerDeleteBefore',
            'actionAdminCustomersListingFieldsModifier',
            // src/Core/Kpi/Row/HookableKpiRowFactory.php
            'actionOrdersKpiRowModifier',
        ]);
    }

    public function hookActionObjectProductUpdateAfter($params)
    {
        // Empty on purpose: the hook guard only reads the method name.
    }

    public function hookActionFixtureBannerGridDefinitionModifier($params)
    {
        // Empty on purpose: the hook guard only reads the method name.
    }
}
