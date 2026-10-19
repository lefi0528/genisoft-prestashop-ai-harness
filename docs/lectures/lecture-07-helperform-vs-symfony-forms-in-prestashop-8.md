# Lecture 07: Back-Office Forms: HelperForm vs Symfony Forms

## Building Resilient Back-Office Interfaces

PrestaShop 8 modules have two ways to generate configuration screens in `getContent()`:
1. **`HelperForm`**: Fully backwards compatible with PrestaShop 1.7.8, self-contained, generates standard Bootstrap 3 UI.
2. **Symfony Form Types**: Modern, uses Twig and Symfony Form builder, requires Symfony 5.4/6.x controllers.

### The Production `HelperForm` Pattern
```php
public function getContent(): string
{
    $output = '';
    if (Tools::isSubmit('submitSettings')) {
        $rate = (float) Tools::getValue('MY_RATE');
        Configuration::updateValue('MY_RATE', $rate);
        $output .= $this->displayConfirmation($this->l('Settings saved.'));
    }

    $helper = new HelperForm();
    $helper->module = $this;
    $helper->name_controller = $this->name;
    $helper->token = Tools::getAdminTokenLite('AdminModules');
    $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
    $helper->submit_action = 'submitSettings';
    $helper->fields_value['MY_RATE'] = Configuration::get('MY_RATE');

    return $output . $helper->generateForm([$this->getFormConfig()]);
}
```
