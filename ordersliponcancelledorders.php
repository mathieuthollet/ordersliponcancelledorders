<?php
/**
 * 2007-2022 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2022 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class OrderSlipOnCancelledOrders extends Module
{
    public $message = '';

    protected $config_form = false;
    protected $support_url = 'https://addons.prestashop.com/fr/contactez-nous?id_product=49576';

    public function __construct()
    {
        $this->name = 'ordersliponcancelledorders';
        $this->tab = 'administration';
        $this->version = '1.1.1';
        $this->author = 'AWebVision';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->module_key = 'c2ab4fbd7abee04be7a895db2e8a66cd';

        parent::__construct();

        $this->displayName = $this->l('Order Slip On Cancelled Orders');
        $this->description = $this->l('Automatically generates an order slip when you set an order to "Cancelled" state, and when this order already has an invoice.');
    }

    public function install()
    {
        Configuration::updateValue('ORDERSLIPONCANCELLEDORDERS_IDS_ORDER_STATE', serialize([Configuration::get('PS_OS_CANCELED')]));
        return parent::install() && $this->registerHook('actionOrderStatusPostUpdate');
    }

    public function uninstall()
    {
        return parent::uninstall();
    }


    /**
     * Load the configuration form
     */
    public function getContent()
    {
        if ((bool)Tools::isSubmit('submitOrderSlipOnCancelledOrdersSettings')) {
            $this->postProcess();
        }
        $this->context->smarty->assign('module_dir', $this->_path);
        $this->context->smarty->assign('support_url', $this->support_url);
        $output = $this->message .
            $this->renderSettingsForm() .
            $this->context->smarty->fetch($this->local_path . 'views/templates/admin/support.tpl');
        return $output;
    }

    /**
     * PostProcess
     */
    protected function postProcess()
    {
        if (Tools::isSubmit('submitOrderSlipOnCancelledOrdersSettings')) {
            $this->processSaveSettings();
        }
    }

    /**
     * Sets error message
     * @param $message
     */
    protected function setErrorMessage($message)
    {
        $this->context->smarty->assign('message', $message);
        $this->message .= $this->context->smarty->fetch($this->local_path . 'views/templates/admin/alert-danger.tpl');
    }

    /**
     * Sets success message
     * @param $message
     */
    protected function setSuccessMessage($message)
    {
        $this->context->smarty->assign('message', $message);
        $this->message .= $this->context->smarty->fetch($this->local_path . 'views/templates/admin/alert-success.tpl');
    }

    /**
     * Rendering of configuration form
     * @return mixed
     */
    protected function renderSettingsForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitOrderSlipOnCancelledOrdersSettings';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];
        // Form values
        $idsOrderStates = unserialize(Configuration::get('ORDERSLIPONCANCELLEDORDERS_IDS_ORDER_STATE'));
        if (is_array($idsOrderStates)) {
            foreach ($idsOrderStates as $idsOrderState) {
                $helper->fields_value['id_order_state_' . $idsOrderState] = 1;
            }
        }
        return $helper->generateForm([$this->getSettingsForm()]);
    }

    /**
     * Structure of the configuration form
     * @return array
     */
    protected function getSettingsForm()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Order Slip On Cancelled Orders') . ' - ' . $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'checkbox',
                        'label' => $this->l('Order statuses'),
                        'name' => 'id_order_state',
                        'values' => [
                            'query' => OrderState::getOrderStates($this->context->language->id),
                            'id' => 'id_order_state',
                            'name' => 'name'
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save settings'),
                    'id' => 'submitSettings',
                    'icon' => 'process-icon-save'
                ],
            ],
        ];
    }

    /**
     * Save export settings
     */
    protected function processSaveSettings()
    {
        $idsOrderState = [];
        foreach (OrderState::getOrderStates($this->context->language->id) as $order_state) {
            if (Tools::getValue('id_order_state_' . $order_state['id_order_state'])) {
                $idsOrderState[] = $order_state['id_order_state'];
            }
        }
        Configuration::updateValue('ORDERSLIPONCANCELLEDORDERS_IDS_ORDER_STATE', serialize($idsOrderState));
    }

    /**
     * Generates order slip when an order is cancelled, and when this order already has an invoice
     * @param $params
     */
    public function hookActionOrderStatusPostUpdate($params)
    {
        $idsOrderState = unserialize(Configuration::get('ORDERSLIPONCANCELLEDORDERS_IDS_ORDER_STATE'));
        if (is_array($idsOrderState) && in_array($params['newOrderStatus']->id, $idsOrderState)) {
            $order = new Order($params['id_order']);
            if ($order->invoice_number != 0) {
                $order_detail_list = [];
                $details = $order->getOrderDetailList();
                foreach ($details as $detail) {
                    $order_detail_list[$detail['id_order_detail']] = [
                        'quantity' => $detail['product_quantity'],
                        'id_order_detail' => $detail['id_order_detail'],
                        'unit_price' => $detail['unit_price_tax_excl'],
                        'amount' => $detail['unit_price_tax_excl'] * $detail['product_quantity'],
                    ];
                }
                OrderSlip::create($order, $order_detail_list, $order->total_shipping_tax_excl);

                // Change order_slip_type to 1, by default it is 2 and does not handles cart_rules
                $orderSlips = OrderSlip::getOrdersSlip($order->id_customer, $order->id);
                if (is_array($orderSlips[0])) {
                    $orderSlip = new OrderSlip($orderSlips[0]['id_order_slip']);
                    if (Validate::isLoadedObject($orderSlip)) {
                        $orderSlip->order_slip_type = 1;
                        $orderSlip->save();
                    }
                }
            }
        }
    }
}
