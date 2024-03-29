<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_XmlConnect
 * @copyright   Copyright (c) 2010 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * XmlConnect customer controller
 *
 * @category    Mage
 * @package     Mage_XmlConnect
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Mage_XmlConnect_CustomerController extends Mage_XmlConnect_Controller_Action
{
    /**
     * Customer authentification action
     *
     * @return void
     */
    public function loginAction()
    {
        $session = $this->_getSession();
        $request = $this->getRequest();
        if ($session->isLoggedIn()) {
            $this->_message($this->__('You are already logged in.'), self::MESSAGE_STATUS_ERROR);
            return;
        }

        if ($request->isPost()) {
            $user = $request->getParam('username');
            $pass = $request->getParam('password');
            try {
                if ($session->login($user, $pass)) {
                    if ($session->getCustomer()->getIsJustConfirmed()) {
                        $session->getCustomer()->sendNewAccountEmail('confirmed');
                    }
                    $this->_message($this->__('Authentication complete.'), self::MESSAGE_STATUS_SUCCESS);
                } else {
                    $this->_message($this->__('Invalid login or password.'), self::MESSAGE_STATUS_ERROR);
                }
            } catch (Mage_Core_Exception $e) {
                switch ($e->getCode()) {
                    case Mage_Customer_Model_Customer::EXCEPTION_EMAIL_NOT_CONFIRMED:
                        // TODO: resend confirmation email message with action
                        break;
                    case Mage_Customer_Model_Customer::EXCEPTION_INVALID_EMAIL_OR_PASSWORD:
                        $message = $e->getMessage();
                        break;
                    default:
                        $message = $e->getMessage();
                }
                $this->_message($message, self::MESSAGE_STATUS_ERROR);
            } catch (Exception $e) {
                $this->_message($this->__('Customer authentication problem.'), self::MESSAGE_STATUS_ERROR);
            }
        } else {
            $this->_message($this->__('Login and password are required.'), self::MESSAGE_STATUS_ERROR);
        }
    }

    /**
     * Customer logout
     *
     * @return void
     */
    public function logoutAction()
    {
        try {
            $this->_getSession()->logout();
            $this->_message($this->__('Logout complete.'), self::MESSAGE_STATUS_SUCCESS);
        } catch (Mage_Core_Exception $e) {
            $this->_message($e->getMessage(), self::MESSAGE_STATUS_ERROR);
        } catch (Exception $e) {
            $this->_message($this->__('Customer logout problem.'), self::MESSAGE_STATUS_ERROR);
        }
    }

    /**
     * Customer registration/edit account form
     *
     * @return void
     */
    public function formAction()
    {
        $customer = null;
        $editFlag = (int)$this->getRequest()->getParam('edit');
        if ($editFlag == 1) {
            if (!$this->_getSession()->isLoggedIn()) {
                $this->_message($this->__('Customer not logged in.'), self::MESSAGE_STATUS_ERROR);
                return;
            }
            $customer  = $this->_getSession()->getCustomer();
        }

        $this->loadLayout(false)->getLayout()->getBlock('xmlconnect.customer.form')->setCustomer($customer);
        $this->renderLayout();
    }

    /**
     * Change customer data action
     *
     * @return void
     */
    public function editAction()
    {
        if (!$this->_getSession()->isLoggedIn()) {
            $this->_message($this->__('Customer not logged in.'), self::MESSAGE_STATUS_ERROR);
            return ;
        }
        if ($this->getRequest()->isPost()) {
            $customer = $this->_getSession()->getCustomer();

            /* @var $customerForm Mage_Customer_Model_Form */
            $customerForm = Mage::getModel('customer/form');
            $customerForm->setFormCode('customer_account_edit')
                ->setEntity($customer);

            $customerData = $customerForm->extractData($this->getRequest());

            $errors = array();
            $customerErrors = $customerForm->validateData($customerData);
            if ($customerErrors !== true) {
                $errors = array_merge($customerErrors, $errors);
            } else {
                $customerForm->compactData($customerData);
                $customerErrors = $customer->validate();
                if (is_array($customerErrors)) {
                    $errors = array_merge($customerErrors, $errors);
                }
            }

            if ($this->getRequest()->getParam('change_password')) {
                $currPass   = $this->getRequest()->getPost('current_password');
                $newPass    = $this->getRequest()->getPost('password');
                $confPass   = $this->getRequest()->getPost('confirmation');

                if (empty($currPass) || empty($newPass) || empty($confPass)) {
                    $errors[] = $this->__('Password fields cannot be empty.');
                }

                if ($newPass != $confPass) {
                    $errors[] = $this->__('Please make sure your passwords match.');
                }

                $oldPass = $this->_getSession()->getCustomer()->getPasswordHash();
                if (strpos($oldPass, ':')) {
                    list($_salt, $salt) = explode(':', $oldPass);
                } else {
                    $salt = false;
                }

                if ($customer->hashPassword($currPass, $salt) == $oldPass) {
                    $customer->setPassword($newPass);
                } else {
                    $errors[] = $this->__('Invalid current password.');
                }
            }

            if (!empty($errors)) {
                /** @var $message Mage_XmlConnect_Model_Simplexml_Element */
                $message = Mage::getModel('xmlconnect/simplexml_element', '<message></message>');
                $message->addChild('status', self::MESSAGE_STATUS_ERROR);
                $message->addChild('text', implode(' ', $errors));
                $this->getResponse()->setBody($message->asNiceXml());
                return;
            }

            try {
                $customer->save();
                $this->_getSession()->setCustomer($customer);
                $this->_message($this->__('Account information has been saved.'), self::MESSAGE_STATUS_SUCCESS);
                return;
            } catch (Mage_Core_Exception $e) {
                $this->_message($e->getMessage(), self::MESSAGE_STATUS_ERROR);
            } catch (Exception $e) {
                if ($e instanceof Mage_Eav_Model_Entity_Attribute_Exception) {
                    $this->_message($e->getMessage(), self::MESSAGE_STATUS_ERROR);
                } else {
                    $this->_message($this->__('Can\'t save the customer.'), self::MESSAGE_STATUS_ERROR);
                }
            }
        } else {
            $this->_message($this->__('POST data is not valid.'), self::MESSAGE_STATUS_ERROR);
        }
    }

    /**
     * Save customer account
     *
     * @return void
     */
    public function saveAction()
    {
        $session = $this->_getSession();
        $request = $this->getRequest();
        if ($session->isLoggedIn()) {
            $this->_message($this->__('You are already logged in.'), self::MESSAGE_STATUS_ERROR);
            return;
        }

        $session->setEscapeMessages(true); // prevent XSS injection in user input
        if ($request->isPost()) {
            $errors = array();

            /* @var $customer Mage_Customer_Model_Customer */
            $customer = Mage::registry('current_customer');
            if (is_null($customer)) {
                $customer = Mage::getModel('customer/customer');
            }

            /* @var $customerForm Mage_Customer_Model_Form */
            $customerForm = Mage::getModel('customer/form');
            $customerForm->setFormCode('customer_account_create')
                ->setEntity($customer);

            $customerData = $customerForm->extractData($this->getRequest());

            if ($this->getRequest()->getParam('is_subscribed', false)) {
                $customer->setIsSubscribed(1);
            }

            /**
             * Initialize customer group id
             */
            $customer->getGroupId();

            try {
                $customerErrors = $customerForm->validateData($customerData);
                if ($customerErrors !== true) {
                    $errors = array_merge($customerErrors, $errors);
                } else {
                    $customerForm->compactData($customerData);
                    $customer->setPassword($this->getRequest()->getPost('password'));
                    $customer->setConfirmation($this->getRequest()->getPost('confirmation'));
                    $customerErrors = $customer->validate();
                    if (is_array($customerErrors)) {
                        $errors = array_merge($customerErrors, $errors);
                    }
                }

                $validationResult = count($errors) == 0;
                if (true === $validationResult) {
                    $customer->save();

                    if ($customer->isConfirmationRequired()) {
                        $customer->sendNewAccountEmail('confirmation', $session->getBeforeAuthUrl());
                        $message = $this->__('Account confirmation is required. Please check your email for the confirmation link.');
                        /** @var $messageXmlObj Mage_XmlConnect_Model_Simplexml_Element */
                        $messageXmlObj = Mage::getModel('xmlconnect/simplexml_element', '<message></message>');
                        $messageXmlObj->addChild('status', self::MESSAGE_STATUS_SUCCESS);
                        $messageXmlObj->addChild('text', $message);
                        $messageXmlObj->addChild('confirmation', 1);
                        $this->getResponse()->setBody($messageXmlObj->asNiceXml());
                        return;
                    } else {
                        $session->setCustomerAsLoggedIn($customer);
                        $customer->sendNewAccountEmail('registered');
                        $this->_message($this->__('Thank you for registering!'), self::MESSAGE_STATUS_SUCCESS);
                        return;
                    }
                } else {
                    if (is_array($errors)) {
                        $message = implode("\n", $errors);
                    } else {
                        $message = $this->__('Invalid customer data.');
                    }
                    $this->_message($message, self::MESSAGE_STATUS_ERROR);
                    return ;
                }
            } catch (Mage_Core_Exception $e) {
                if ($e->getCode() === Mage_Customer_Model_Customer::EXCEPTION_EMAIL_EXISTS) {
                    $message = $this->__('An account with this email address already exists.');
                    $session->setEscapeMessages(false);
                } else {
                    $message = $e->getMessage();
                }
                $this->_message($message, self::MESSAGE_STATUS_ERROR);
            } catch (Exception $e) {
                $this->_message($this->__('Can\'t save the customer.'), self::MESSAGE_STATUS_ERROR);
            }
        }
    }

    /**
     * Send new password to customer by specified email
     *
     * @return void
     */
    public function forgotPasswordAction()
    {
        $email = $this->getRequest()->getPost('email');
        if ($email) {
            if (!Zend_Validate::is($email, 'EmailAddress')) {
                $this->_message($this->__('Invalid email address.'), self::MESSAGE_STATUS_ERROR);
                return;
            }
            $customer = Mage::getModel('customer/customer')
                ->setWebsiteId(Mage::app()->getStore()->getWebsiteId())
                ->loadByEmail($email);

            if ($customer->getId()) {
                try {
                    $newPassword = $customer->generatePassword();
                    $customer->changePassword($newPassword, false);
                    $customer->sendPasswordReminderEmail();
                    $this->_message(
                        $this->__('A new password has been sent.'),
                        self::MESSAGE_STATUS_SUCCESS
                    );
                    return;
                } catch (Mage_Core_Exception $e) {
                    $this->_message($e->getMessage(), self::MESSAGE_STATUS_ERROR);
                } catch (Exception $e) {
                    $this->_message(
                        $this->__('Problem changing or sending password.'),
                        self::MESSAGE_STATUS_ERROR
                    );
                }
            } else {
                $this->_message(
                    $this->__('This email address was not found in our records.'),
                    self::MESSAGE_STATUS_ERROR
                );
            }
        } else {
            $this->_message($this->__('Customer email not specified.'), self::MESSAGE_STATUS_ERROR);
        }
    }

    /**
     * Customer addresses list
     *
     * @return void
     */
    public function addressAction()
    {
        if (!$this->_getSession()->isLoggedIn()) {
            $this->_message($this->__('Customer not logged in.'), self::MESSAGE_STATUS_ERROR);
            return ;
        }

        if (count($this->_getSession()->getCustomer()->getAddresses())) {
            $this->loadLayout(false);
            $this->renderLayout();
        } else {
            /** @var $message Mage_XmlConnect_Model_Simplexml_Element */
            $message = Mage::getModel('xmlconnect/simplexml_element', '<message></message>');
            $message->addChild('status', self::MESSAGE_STATUS_ERROR);
            $message->addChild('is_empty_address_book', 1);
            $this->getResponse()->setBody($message->asNiceXml());
        }
    }

    /**
     * Customer add/edit address form
     *
     * @return void
     */
    public function addressFormAction()
    {
        if (!$this->_getSession()->isLoggedIn()) {
            $this->_message($this->__('Customer not logged in.'), self::MESSAGE_STATUS_ERROR);
            return ;
        }

        $address = Mage::getModel('customer/address');

        /**
         * Init address object
         */
        $addressId = (int)$this->getRequest()->getParam('id');
        if ($addressId) {
            $address->load($addressId);
            if ($address->getCustomerId() != $this->_getSession()->getCustomerId()) {
                $this->_message($this->__('Specified address does not exist.'), self::MESSAGE_STATUS_ERROR);
                return ;
            }
        }

        $this->loadLayout(false)->getLayout()->getBlock('xmlconnect.customer.address.form')->setAddress($address);
        $this->renderLayout();
    }

    /**
     * Remove customer address
     *
     * @return void
     */
    public function deleteAddressAction()
    {
        if (!$this->_getSession()->isLoggedIn()) {
            $this->_message($this->__('Customer not logged in.'), self::MESSAGE_STATUS_ERROR);
            return ;
        }

        $addressId = $this->getRequest()->getParam('id', false);

        if ($addressId) {
            $address = Mage::getModel('customer/address')->load($addressId);

            // Validate address_id <=> customer_id
            if ($address->getCustomerId() != $this->_getSession()->getCustomerId()) {
                $this->_message($this->__('Address does not belong to this customer.'), self::MESSAGE_STATUS_ERROR);
                return;
            }

            try {
                $address->delete();
                $this->_message($this->__('Address has been deleted.'), self::MESSAGE_STATUS_SUCCESS);
            } catch (Exception $e) {
                Mage::logException($e);
                $this->_message($this->__('An error occurred while deleting the address.'), self::MESSAGE_STATUS_ERROR);
            }
        }
    }

    /**
     * Add/Save customer address
     *
     * @return void
     */
    public function saveAddressAction()
    {
        if (!$this->_getSession()->isLoggedIn()) {
            $this->_message($this->__('Customer not logged in.'), self::MESSAGE_STATUS_ERROR);
            return;
        }

        // Save data
        if ($this->getRequest()->isPost()) {
            $customer = $this->_getSession()->getCustomer();
            /* @var $address Mage_Customer_Model_Address */
            $address    = Mage::getModel('customer/address');
            $addressId  = $this->getRequest()->getParam('id');
            if ($addressId) {
                $existsAddress = $customer->getAddressById($addressId);
                if ($existsAddress->getId() && $existsAddress->getCustomerId() == $customer->getId()) {
                    $address->setId($existsAddress->getId());
                }
            }

            $errors = array();

            /* @var $addressForm Mage_Customer_Model_Form */
            $addressForm = Mage::getModel('customer/form');
            $addressForm->setFormCode('customer_address_edit')
                ->setEntity($address);
            $addressData    = $addressForm->extractData($this->getRequest());
            $addressErrors  = $addressForm->validateData($addressData);
            if ($addressErrors !== true) {
                $errors = $addressErrors;
            }

            try {
                $addressForm->compactData($addressData);
                $address->setCustomerId($customer->getId())
                    ->setIsDefaultBilling($this->getRequest()->getParam('default_billing', false))
                    ->setIsDefaultShipping($this->getRequest()->getParam('default_shipping', false));

                $addressErrors = $address->validate();
                if ($addressErrors !== true) {
                    $errors = array_merge($errors, $addressErrors);
                }

                $addressValidation = count($errors) == 0;

                if (true === $addressValidation) {
                    $address->save();

                    $message = Mage::getModel('xmlconnect/simplexml_element', '<message></message>');
                    $message->addChild('status', self::MESSAGE_STATUS_SUCCESS);
                    $message->addChild('text', $this->__('Address has been saved.'));
                    $message->addChild('address_id', $address->getId());
                    $this->getResponse()->setBody($message->asNiceXml());
                    return;
                } else {
                    if (is_array($errors)) {
                        $this->_message(implode('. ', $errors), self::MESSAGE_STATUS_ERROR);
                    } else {
                        $this->_message($this->__('Can\'t save address.'), self::MESSAGE_STATUS_ERROR);
                    }
                }
            } catch (Mage_Core_Exception $e) {
                $this->_message($e->getMessage(), self::MESSAGE_STATUS_ERROR);
            } catch (Exception $e) {
                Mage::logException($e);
                $this->_message($this->__('Can\'t save address.'), self::MESSAGE_STATUS_ERROR);
            }
        } else {
            $this->_message($this->__('Address data not specified.'), self::MESSAGE_STATUS_ERROR);
        }
    }

    /**
     * Customer orders list
     *
     * @return void
     */
    public function orderListAction()
    {
        if (!$this->_getSession()->isLoggedIn()) {
            $this->_message($this->__('Customer not logged in.'), self::MESSAGE_STATUS_ERROR);
            return ;
        }

        $this->loadLayout(false);
        $this->renderLayout();
    }

    /**
     * Customer order details
     *
     * @return void
     */
    public function orderDetailsAction()
    {
        try {
            if (!$this->_getSession()->isLoggedIn()) {
                $this->_message($this->__('Customer not logged in.'), self::MESSAGE_STATUS_ERROR);
                return;
            }

            $orderId = (int) $this->getRequest()->getParam('order_id');
            if (!$orderId) {
                $this->_message($this->__('Order id not specified.'), self::MESSAGE_STATUS_ERROR);
                return;
            }

            $order = Mage::getModel('sales/order')->load($orderId);

            if ($this->_canViewOrder($order)) {
                Mage::register('current_order', $order);
            } else {
                $this->_message($this->__('Order is not available.'), self::MESSAGE_STATUS_ERROR);
                return;
            }
            $this->loadLayout(false);
            $this->renderLayout();
            return;
        } catch (Mage_Core_Exception $e) {
            $this->_message($e->getMessage(), self::MESSAGE_STATUS_ERROR);
        } catch (Exception $e) {
            $this->_message($this->__('Unable to render an order.'), self::MESSAGE_STATUS_ERROR);
            Mage::logException($e);
        }
    }

    /**
     * Check order view availability
     *
     * @param   Mage_Sales_Model_Order $order
     * @return  bool
     */
    protected function _canViewOrder($order)
    {
        $customerId = Mage::getSingleton('customer/session')->getCustomerId();
        $availableStates = Mage::getSingleton('sales/order_config')->getVisibleOnFrontStates();
        if ($order->getId()
            && $order->getCustomerId()
            && ($order->getCustomerId() == $customerId)
            && in_array($order->getState(), $availableStates, true)
        ) {
            return true;
        }
        return false;
    }

    /**
     * Check if customer is loggined
     *
     * @return void
     */
    public function isLogginedAction()
    {
        /** @var $message Mage_XmlConnect_Model_Simplexml_Element */
        $message = Mage::getModel('xmlconnect/simplexml_element', '<message></message>');
        $message->addChild('is_loggined', (int)$this->_getSession()->isLoggedIn());
        $this->getResponse()->setBody($message->asNiceXml());
    }

    /**
     * Filtering posted data. Converting localized data if needed
     *
     * @param array $data
     * @return array
     */
    protected function _filterPostData($data)
    {
        $data = $this->_filterDates($data, array('dob'));
        return $data;
    }

    /**
     * Get customer session model
     *
     * @return Mage_Customer_Model_Session
     */
    protected function _getSession()
    {
        return Mage::getSingleton('customer/session');
    }
}
