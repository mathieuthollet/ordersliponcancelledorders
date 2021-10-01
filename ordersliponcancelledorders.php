<?php
/**
 * 2007-2021 PrestaShop
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
 * @copyright 2007-2021 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class OrderSlipOnCancelledOrders extends Module
{
    private $message = '';

    protected $config_form = false;
    protected $support_url = 'https://addons.prestashop.com/fr/contactez-nous?id_product=49576';

    public function __construct()
    {
        $this->name = 'ordersliponcancelledorders';
        $this->tab = 'administration';
        $this->version = '1.0.2';
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
        $this->context->smarty->assign('module_dir', $this->_path);
        $this->context->smarty->assign('support_url', $this->support_url);
        $output = $this->message .
            $this->context->smarty->fetch($this->local_path . 'views/templates/admin/support.tpl');
        return $output;
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
     * Generates order slip when an order is cancelled, and when this order already has an invoice
     * @param $params
     */
    public function hookActionOrderStatusPostUpdate($params)
    {
        if ($params['newOrderStatus']->id == Configuration::get('PS_OS_CANCELED')) {
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
