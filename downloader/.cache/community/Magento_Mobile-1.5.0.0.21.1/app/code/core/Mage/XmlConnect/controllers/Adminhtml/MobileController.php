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
 * XmlConnect Adminhtml mobile controller
 *
 * @category    Mage
 * @package     Mage_XmlConnect
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Mage_XmlConnect_Adminhtml_MobileController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Initialize application
     *
     * @param string $paramName
     * @param bool|string $type
     * @return Mage_XmlConnect_Model_Application
     */
    protected function _initApp($paramName = 'application_id', $type = false)
    {
        $id = (int) $this->getRequest()->getParam($paramName);
        $app = Mage::getModel('xmlconnect/application');
        if ($id) {
            $app->load($id);
            if ($app->getId()) {
                $app->loadConfiguration();
            }
        } else {
            $app->setType($type);
            Mage::register('current_app', $app);
            $app->loadDefaultConfiguration();
            Mage::unregister('current_app');
        }
        Mage::register('current_app', $app);
        return $app;
    }

    /**
     * Restore data from session $_POST and $_FILES (processed)
     *
     * @param array $data
     * @return array|null
     */
    protected function _restoreSessionFilesFormData($data)
    {
        $filesData = Mage::getSingleton('adminhtml/session')->getUploadedFilesFormData(true);
        if (!empty($filesData) && is_array($filesData)) {
            if (!is_array($data)) {
                $data = array();
            }
            foreach ($filesData as $filePath => $fileName) {
                $target =& $data;
                Mage::helper('xmlconnect')->_injectFieldToArray($target, $filePath, $fileName);
            }
        }
        return $data;
    }

    /**
     * Mobile applications management
     *
     * @return void
     */
    public function indexAction()
    {
        $this->loadLayout();
        $this->_setActiveMenu('xmlconnect/mobile');
        $this->renderLayout();
    }

    /**
     * Create new app
     *
     * @return void
     */
    public function newAction()
    {
        Mage::getSingleton('adminhtml/session')->setData('new_application', true);
        $this->loadLayout();
        $this->_setActiveMenu('xmlconnect/mobile');
        $this->renderLayout();
    }

    /**
     * Submission Action, loads application data
     *
     * @return void
     */
    public function submissionAction()
    {
        try {
            $app = $this->_initApp();
            if (!$app->getId()) {
                $this->_getSession()->addError($this->__('App does not exist.'));
                $this->_redirect('*/*/');
                return;
            }
            $app->loadSubmit();
            if ((bool) Mage::getSingleton('adminhtml/session')->getLoadSessionFlag(true)) {
                $data = $this->_restoreSessionFilesFormData(
                    Mage::getSingleton('adminhtml/session')->getFormSubmissionData(true)
                );
                if (!empty($data)) {
                    $app->setData(Mage::helper('xmlconnect')->arrayMergeRecursive($app->getData(), $data));
                }
            }

            $this->loadLayout();
            $this->_setActiveMenu('xmlconnect/mobile');
            $this->renderLayout();
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            if (isset($app)) {
                $this->_redirect('*/*/edit', array('application_id' => $app->getId()));
            } else {
                $this->_redirect('*/*/');
            }
        } catch (Exception $e) {
            $this->_getSession()->addException($e, $this->__('Can\'t open submission form.'));
            if (isset($app)) {
                $this->_redirect('*/*/edit', array('application_id' => $app->getId()));
            } else {
                $this->_redirect('*/*/');
            }
        }
    }

    /**
     * Edit app form
     *
     * @return void
     */
    public function editAction()
    {
        $redirectBack = false;
        try {
            $id = (int) $this->getRequest()->getParam('application_id');
            $type = $this->getRequest()->getParam('type');
            $app = $this->_initApp('application_id', $type);

            if (!$app->getId() && $id) {
                $this->_getSession()->addError($this->__('App does not exist.'));
                $this->_redirect('*/*/');
                return;
            }

            $newAppData = $this->_restoreSessionFilesFormData(
                Mage::getSingleton('adminhtml/session')->getFormData(true)
            );

            if (!empty($newAppData)) {
                $app->setData(Mage::helper('xmlconnect')->arrayMergeRecursive($app->getData(), $newAppData));
            }

            if ($app->getId() || $app->getType()) {
                Mage::getSingleton('adminhtml/session')->setData('new_application', false);
            } else {
                $this->_redirect('*/*/new');
            }

            $devArray = Mage::helper('xmlconnect')->getSupportedDevices();
            if (array_key_exists($app->getType(), $devArray)) {
                $deviceTitle = $devArray[$app->getType()];
            }
            $deviceTitle = isset($deviceTitle) ? $deviceTitle : $app->getType();
            $app->setDevtype($deviceTitle);
            $app->loadSubmit();
            $this->loadLayout();
            $this->_setActiveMenu('xmlconnect/mobile');
            $this->renderLayout();
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            $redirectBack = true;
        } catch (Exception $e) {
            $this->_getSession()->addError($this->__('Unable to load application form.'));
            $redirectBack = true;
            Mage::logException($e);
        }
        if ($redirectBack) {
            $this->_redirect('*/*/');
            return;
        }
    }

    /**
     * Submit POST application action
     *
     * @return void
     */
    public function submissionPostAction()
    {
        $data = $this->getRequest()->getPost();
        try {
            $isError = false;
            if (!empty($data)) {
                Mage::getSingleton('adminhtml/session')->setFormSubmissionData($this->_filterFormDataForSession($data));
            }
            /** @var $app Mage_XmlConnect_Model_Application */
            $app = $this->_initApp('key');
            $app->loadSubmit();
            $newAppData = $this->_processUploadedFiles($app->getData(), true);
            if (!empty($newAppData)) {
                $app->setData(Mage::helper('xmlconnect')->arrayMergeRecursive($app->getData(), $newAppData));
            }
            $params = $app->prepareSubmitParams($data);
            $errors = $app->validateSubmit($params);
            if ($errors !== true) {
                foreach ($errors as $err) {
                    $this->_getSession()->addError($err);
                }
                $isError = true;
            }
            if (!$isError) {
                $this->_processPostRequest();
                $history = Mage::getModel('xmlconnect/history');
                $history->setData(array(
                    'params' => $params,
                    'application_id' => $app->getId(),
                    'created_at' => Mage::getModel('core/date')->date(),
                    'store_id' => $app->getStoreId(),
                    'title' => isset($params['title']) ? $params['title'] : '',
                    'name' => $app->getName(),
                    'code' => $app->getCode(),
                    'activation_key' => isset($params['resubmission_activation_key'])
                        ? $params['resubmission_activation_key']
                        : $params['key'],
                ));
                $history->save();
                $app->getResource()->updateApplicationStatus($app->getId(),
                    Mage_XmlConnect_Model_Application::APP_STATUS_SUCCESS);
                $this->_getSession()->addSuccess($this->__('App has been submitted.'));
                $this->_clearSessionData();
                $this->_redirect('*/*/edit', array('application_id' => $app->getId()));
            } else {
                Mage::getSingleton('adminhtml/session')->setLoadSessionFlag(true);
                $this->_redirect('*/*/submission', array('application_id' => $app->getId()));
            }
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            if (isset($app)) {
                Mage::getSingleton('adminhtml/session')->setLoadSessionFlag(true);
                $this->_redirect('*/*/submission', array('application_id' => $app->getId()));
            } else {
                $this->_redirect('*/*/');
            }
        } catch (Exception $e) {
            $this->_getSession()->addException($e, $this->__('Can\'t submit application.'));
            Mage::logException($e);
            if (isset($app)) {
                Mage::getSingleton('adminhtml/session')->setLoadSessionFlag(true);
                $this->_redirect('*/*/submission', array('application_id' => $app->getId()));
            } else {
                $this->_redirect('*/*/');
            }
        }
    }

    /**
     * Format post/get data for session storage
     *
     * @param array $data - $_REQUEST[]
     * @return array
     */
    protected function _filterFormDataForSession($data)
    {
        $params = null;
        if (isset($data['conf']) && is_array($data['conf'])) {
            if (isset($data['conf']['submit_text']) && is_array($data['conf']['submit_text'])) {
                $params = &$data['conf']['submit_text'];
            }
        }
        if (isset($params['country']) && is_array($params['country'])) {
            $data['conf']['submit_text']['country'] = implode(',', $params['country']);
        }
        return $data;
    }

    /**
     * Clear session data
     * Used after successful save/submit action
     *
     * @return this
     */
    protected function _clearSessionData()
    {
        Mage::getSingleton('adminhtml/session')->unsFormData();
        Mage::getSingleton('adminhtml/session')->unsFormSubmissionData();
        Mage::getSingleton('adminhtml/session')->unsUploadedFilesFormData();
        return $this;
    }

    /**
     * Send HTTP POST request to magentocommerce.com
     *
     * @return void
     */
    protected function _processPostRequest()
    {
        try {
            $app = Mage::helper('xmlconnect')->getApplication();
            $params = $app->getSubmitParams();

            $appConnectorUrl = Mage::getStoreConfig('xmlconnect/mobile_application/magentocommerce_url');
            $ch = curl_init($appConnectorUrl . $params['key']);

            // set URL and other appropriate options
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);

            // Execute the request.
            $result = curl_exec($ch);
            $succeeded  = curl_errno($ch) == 0 ? true : false;

            // close cURL resource, and free up system resources
            curl_close($ch);

            // Assert that we received an expected message in reponse.
            $resultArray = json_decode($result, true);

            $app->setResult($result);
            $success = (isset($resultArray['success'])) && ($resultArray['success'] === true);

            $app->setSuccess($success);
            if (!$app->getSuccess()) {
                $message = isset($resultArray['message']) ? $resultArray['message']: '';
                if (is_array($message)) {
                    $message = implode(' ,', $message);
                }
                Mage::throwException($this->__('Submit App failure. %s', $message));
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Save action
     *
     * @return void
     */
    public function saveAction()
    {
        $data = $this->getRequest()->getPost();
        $redirectSubmit = $this->getRequest()->getParam('submitapp', false);
        $app = false;
        $isError = false;
        if ($data) {
            Mage::getSingleton('adminhtml/session')->setFormData($data);
            try {
                $id = (int) $this->getRequest()->getParam('application_id');

                if (!$id && isset($data['devtype'])) {
                    $devArray = Mage::helper('xmlconnect')->getSupportedDevices();
                    if (!array_key_exists($data['devtype'], $devArray)) {
                        $this->_getSession()->addError($this->__('Wrong device type.'));
                        $isError = true;
                    }
                }

                $app = $this->_initApp('application_id', $data['devtype']);
                if (!$app->getId() && $id) {
                    $this->_getSession()->addError($this->__('App does not exist.'));
                    $this->_redirect('*/*/');
                    return;
                }
                $app->addData($this->_preparePostData($data));
                $app->addData($this->_processUploadedFiles($app->getData()));
                $errors = $app->validate();

                if ($errors !== true) {
                    foreach ($errors as $err) {
                        $this->_getSession()->addError($err);
                    }
                    $isError = true;
                }

                if (!$isError) {
                    $this->_saveThemeAction($data, 'current_theme');
                    $app->save();
                    $this->_getSession()->addSuccess($this->__('App has been saved.'));
                    $this->_clearSessionData();
                }
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addException($e, $e->getMessage());
                $isError = true;
            } catch (Exception $e) {
                $this->_getSession()->addException($e, $this->__('Unable to save app.'));
                $isError = true;
                Mage::logException($e);
            }
        }
        $isApplication = $app instanceof Mage_XmlConnect_Model_Application;
        if (!$isError && $isApplication && $app->getId() && $redirectSubmit) {
            $this->_redirect('*/*/submission', array('application_id' => $app->getId()));
        } else if ($isError && $isApplication && $app->getId()) {
            Mage::getSingleton('adminhtml/session')->setLoadSessionFlag(true);
            $this->_redirect('*/*/edit', array('application_id' => $app->getId()));
        } else if ($isError && $isApplication && !$app->getId() && $app->getType()) {
            $this->_redirect('*/*/edit', array('type' => $app->getType()));
        } else if ($this->getRequest()->getParam('back') && $isApplication) {
            $this->_redirect('*/*/edit', array('application_id' => $app->getId()));
        } else {
            $this->_redirect('*/*/');
        }
    }

    /**
     * Save changes to theme
     *
     * @param array     $data
     * @param string    $paramId
     */
    protected function _saveThemeAction($data, $paramId = 'saveTheme')
    {
        $themeName = trim($this->getRequest()->getParam($paramId, false));
        if ($themeName) {
            /** @var $themesHelper Mage_XmlConnect_Helper_Theme */
            $themesHelper = Mage::helper('xmlconnect/theme');
            try {
                if (array_key_exists($themeName, $themesHelper->getAllThemes())) {
                    /** @var $theme Mage_XmlConnect_Model_Theme */
                    $theme = $themesHelper->getThemeByName($themeName);
                    if ($theme instanceof Mage_XmlConnect_Model_Theme) {
                        if ($paramId == 'saveTheme') {
                            $convertedConf = $this->_convertPost($data);
                        } else {
                            if (isset($data['conf'])) {
                                $convertedConf = $data['conf'];
                            } else {
                                $response = array(
                                    'error' => true,
                                    'message' => $this->__('Cannot save theme "%s". Incorrect data received', $themeName)
                                );
                            }
                        }
                        if (!isset($response)) {
                            $theme->importAndSaveData($convertedConf);
                            $response = array(
                                'message'   => $this->__('Changes have been saved to theme.'),
                                'themes'    => $themesHelper->getAllThemesArray(true),
                                'themeSelector' => $themesHelper->getThemesSelector($themeName),
                                'selectedTheme' => $themeName
                            );
                        }
                    } elseif ($newThemeName) {
                        $response = array('error' => true, 'message' => $this->__('Cannot load theme "%s".', $themeName));
                    } else {
                        $response = array('error' => true, 'message' => $this->__('Cannot load theme "%s".', $themeName));
                    }
                } else {
                    $convertedConf = $this->_convertPost($data);
                    $newTheme = $themesHelper->createNewTheme($themeName, $convertedConf);
                    $response = array(
                        'message'   => $this->__('Theme has been created.'),
                        'themes'    => $themesHelper->getAllThemesArray(true),
                        'themeSelector' => $themesHelper->getThemesSelector($newTheme->getName()),
                        'selectedTheme' => $newTheme->getName()
                    );
                }
            } catch (Mage_Core_Exception $e) {
                $response = array(
                    'error'     => true,
                    'message'   => $e->getMessage(),
                );
            } catch (Exception $e) {
                $response = array(
                    'error'     => true,
                    'message'   => $this->__('Can\'t save theme.')
                );
            }
        } else {
            $response = array('error' => true, 'message' => $this->__('Theme name is not set.'));
        }
        if (is_array($response)) {
            $response = Mage::helper('core')->jsonEncode($response);
            $this->getResponse()->setBody($response);
        }
    }

    /**
     * Converts native Ajax data from flat to real array
     * Convert array key->value pairs inside array like:
     * "conf_native_bar_tintcolor" => $val   to   $conf['native']['bar']['tintcolor'] => $val
     *
     * @param array $data $_POST
     * @return array
     */
    protected function _convertPost($data)
    {
        $conf = array();
        foreach ($data as $key => $val) {
            $parts = explode('_', $key);
            // "4" - is number of expected params conf_native_bar_tintcolor in correct data
            if (is_array($parts) && (count($parts) == 4)) {
                @list($key0, $key1, $key2, $key3) = $parts;
                if (!isset($conf[$key1])) {
                    $conf[$key1] = array();
                }
                if (!isset($conf[$key1][$key2])) {
                    $conf[$key1][$key2] = array();
                }
            $conf[$key1][$key2][$key3] = $val;
            }
        }
        return $conf;
    }

    /**
     * Save Theme action
     *
     * @return void
     */
    public function saveThemeAction()
    {
        $data = $this->getRequest()->getPost();
        $this->_saveThemeAction($data);
    }

    /**
     * Delete theme action
     *
     * @return void
     */
    public function deleteThemeAction()
    {
        $themeId = $this->getRequest()->getParam('theme_id', false);
        if ($themeId) {
            try {
                /** @var $themesHelper Mage_XmlConnect_Helper_Theme */
                $themesHelper = Mage::helper('xmlconnect/theme');
                $result = $themesHelper->deleteTheme($themeId);
                if ($result) {
                    $response = array(
                        'message'   => $this->__('Theme has been delete.'),
                        'themes'    => $themesHelper->getAllThemesArray(true),
                        'themeSelector' => $themesHelper->getThemesSelector(),
                        'selectedTheme' => $themesHelper->getDefaultThemeName()
                    );
                } else {
                    $response = array(
                        'error'     => true,
                        'message'   => $this->__('Can\'t delete "%s" theme.', $themeId)
                    );
                }
            } catch (Mage_Core_Exception $e) {
                $response = array(
                    'error'     => true,
                    'message'   => $e->getMessage(),
                );
            } catch (Exception $e) {
                $response = array(
                    'error'     => true,
                    'message'   => $this->__('Can\'t delete "%s" theme.', $themeId)
                );
            }
        } else {
            $response = array('error' => true, 'message' => $this->__('Theme name is not set.'));
        }
        if (is_array($response)) {
            $response = Mage::helper('core')->jsonEncode($response);
            $this->getResponse()->setBody($response);
        }
    }

    /**
     * Save Theme action
     *
     * @return void
     */
    public function resetThemeAction()
    {
        try {
            $theme = $this->getRequest()->getPost('theme', null);
            Mage::helper('xmlconnect/theme')->resetTheme($theme);
            $response = Mage::helper('xmlconnect/theme')->getAllThemesArray(true);
        } catch (Mage_Core_Exception $e) {
            $response = array(
                'error'     => true,
                'message'   => $e->getMessage(),
            );
        } catch (Exception $e) {
            $response = array(
                'error'     => true,
                'message'   => $this->__('Can\'t reset theme.')
            );
        }
        if (is_array($response)) {
            $response = Mage::helper('core')->jsonEncode($response);
            $this->getResponse()->setBody($response);
        }
    }

    /**
     * Preview Home action handler
     *
     * @return void
     */
    public function previewHomeAction()
    {
        $this->_previewAction('preview_home_content');
    }

    /**
     * Preview Home landscape mode action handler
     */
    public function previewHomeHorAction()
    {
        $this->_previewAction('preview_home_hor_content');
    }

    /**
     * Preview Catalog action handler
     *
     * @return void
     */
    public function previewCatalogAction()
    {
        $this->_previewAction('preview_catalog_content');
    }

    /**
     * Preview Catalog landscape mode action handler
     */
    public function previewCatalogHorAction()
    {
        $this->_previewAction('preview_catalog_hor_content');
    }

    /**
     * Preview Product Info action handler
     */
    public function previewProductinfoAction()
    {
        $this->_previewAction('preview_productinfo_content');
    }

    /**
     * Preview AirMail Queue Template action handler
     */
    public function previewQueueAction()
    {
        $message = $this->_initMessage();
        if ($message->getId()) {
            $this->getRequest()->setParam('queue_preview', $message->getId());
        }
        $this->_forward('previewTemplate');
    }

    /**
     * Preview AirMail Template action handler
     */
    public function previewTemplateAction()
    {
        $this->loadLayout('adminhtml_mobile_template_preview');
        $this->renderLayout();
    }

    /**
     * Preview action implementation
     *
     * @param string $block
     */
    protected function _previewAction($block)
    {
        $redirectBack = false;
        try {
            $deviceType = $this->getRequest()->getParam('devtype');
            $app = $this->_initApp('application_id', $deviceType);
            if (!$this->getRequest()->getParam('submission_action')) {
                $app->addData($this->_preparePostData($this->getRequest()->getPost()));
            }
            /** render base configuration of application */
            $appConf = $app->getRenderConf();
            try {
                /** try to upload files */
                $dataUploaded = $this->_processUploadedFiles($app->getData());
                $app->addData($dataUploaded);
                /** render configuration with just uploaded images */
                $appConf = $app->getRenderConf();
            } catch (Exception $e) {
                /** when cannot upload - just tell user what is happen */
                $jsErrorMessage = addslashes($e->getMessage());
            }

            $this->loadLayout(false);
            $preview = $this->getLayout()->getBlock($block);

            if (isset($jsErrorMessage)) {
                $preview->setJsErrorMessage($jsErrorMessage);
            }
            $preview->setConf($appConf);
            Mage::helper('xmlconnect')->getPreviewModel()->setConf($appConf);
            $this->renderLayout();
            return;
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addException($e, $e->getMessage());
            $redirectBack = true;
        } catch (Exception $e) {
            $this->_getSession()->addException($e, $this->__('Unable to process preview.'));
            $redirectBack = true;
        }
        if (isset($app) && $app instanceof Mage_XmlConnect_Model_Application && $redirectBack) {
            $this->_redirect('*/*/edit', array('application_id' => $app->getId()));
        } else {
            $this->_redirect('*/*/');
        }
    }

    /**
     * Delete app action
     *
     * @return void
     */
    public function deleteAction()
    {
        try {
            $app = $this->_initApp();
            if (!$app->getIsSubmitted()) {
                $app->delete();
                $this->_getSession()->addSuccess($this->__('App has been deleted.'));
            } else {
                Mage::throwException($this->__('It\'s not allowed to delete submitted application.'));
            }
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addException($e, $e->getMessage());
        } catch (Exception $e) {
            $this->_getSession()->addException($e, $this->__('Unable to find an app to delete.'));
        }
        $this->_redirect('*/*/');
    }

    /**
     * Delete template action
     */
    public function deleteTemplateAction()
    {
        // check if we know what should be deleted
        if ($id = $this->getRequest()->getParam('id')) {
            try {
                // init template and delete
                Mage::getModel('xmlconnect/template')->load($id)->delete();

                // display success message
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    $this->__('Template has been deleted.')
                );

                // go to grid
                $this->_redirect('*/*/template');
                return;

            } catch (Exception $e) {
                // display error message
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                // go back to edit form
                $this->_redirect('*/*/template', array('id' => $id));
                return;
            }
        }

        // display error message
        Mage::getSingleton('adminhtml/session')->addError(
            $this->__('Unable to find template to delete.')
        );
    }

    /**
     * Check the permission to run it
     *
     * @return boolean
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('xmlconnect');
    }

    /**
     * List application submit history
     */
    public function historyAction()
    {
        $this->loadLayout();
        $this->_setActiveMenu('xmlconnect/history');
        $this->renderLayout();
    }

    /**
     * Render apps grid
     *
     * @return void
     */
    public function gridAction()
    {
        $this->loadLayout(false);
        $this->_setActiveMenu('xmlconnect/mobile');
        $this->renderLayout();
    }

    /**
     * Process all uploaded files
     * setup filenames to the configuration return array
     *
     * @param array $data
     * @param bool $restore
     * @return array
     */
    protected function _processUploadedFiles($data, $restore = false)
    {
        if ($restore === true) {
            $this->_uploadedFiles = Mage::getSingleton('adminhtml/session')->getUploadedFilesFormDataSubmit();
        }
        if (!isset($this->_uploadedFiles) || !is_array($this->_uploadedFiles)) {
            $this->_uploadedFiles = array();
        }

        if (!empty($_FILES)) {
            foreach ($_FILES as $field => $file) {
                if (!empty($file['name']) && is_scalar($file['name'])) {
                    $uploadedFileName = Mage::helper('xmlconnect/image')->handleUpload($field, $data);
                    if (!empty($uploadedFileName)) {
                        $this->_uploadedFiles[$field] = $uploadedFileName;
                    }
                }
            }
        }
        foreach ($this->_uploadedFiles as $fieldPath => $fileName) {
            Mage::helper('xmlconnect')->_injectFieldToArray($data, $fieldPath, $fileName);
        }
        Mage::getSingleton('adminhtml/session')->setUploadedFilesFormData($this->_uploadedFiles);
        if ($restore === true) {
            Mage::getSingleton('adminhtml/session')->setUploadedFilesFormDataSubmit($this->_uploadedFiles);
        }
        return $data;
    }

    /**
     * Prepare post data
     * Retains previous data in the object.
     *
     * @param array $arr
     * @return array
     */
    public function _preparePostData(array $arr)
    {
        unset($arr['code']);
        if (!empty($arr['conf']['native']['pages'])) {
            /**
             * Remove emptied pages
             */
            $pages = array();
            foreach ($arr['conf']['native']['pages'] as $_page) {
                if (!empty($_page['id']) && !empty($_page['label'])) {
                    $pages[] = $_page;
                }
            }
            $arr['conf']['native']['pages'] = $pages;
        }
        if (isset($arr['conf']['new_pages']['ids']) && isset($arr['conf']['new_pages']['labels'])) {
            $newPages = array();
            $cmsPages = &$arr['conf']['new_pages'];
            $idx = 0;
            foreach ($cmsPages['ids'] as $key => $value) {
                if (!empty($value) && !empty($cmsPages['labels'][$key])) {
                    $newPages[$idx]['id'] = trim($value);
                    $newPages[$idx++]['label'] = trim($cmsPages['labels'][$key]);
                }
            }
            if (!isset($arr['conf']['native']['pages'])) {
                $arr['conf']['native']['pages'] = array();
            }
            if (!empty($newPages)) {
                $arr['conf']['native']['pages'] = array_merge($arr['conf']['native']['pages'], $newPages);
            }
            unset($arr['conf']['new_pages']);
        }

        /**
         * Check cache settings
         */
        $lifetime = &$arr['conf']['native']['cacheLifetime'];
        $lifetime = $lifetime <= 0 ? '' : (int)$lifetime;

        /**
         * Restoring current_theme over selected but not applied theme
         */
        if (isset($arr['current_theme'])) {
            $arr['conf']['extra']['theme'] = $arr['current_theme'];
        }
        if (!isset($arr['conf']['defaultCheckout'])) {
            $arr['conf']['defaultCheckout'] = array();
        }
        if (!isset($arr['conf']['defaultCheckout']['isActive'])) {
            $arr['conf']['defaultCheckout']['isActive'] = 0;
        }

        if (!isset($arr['conf']['paypal'])) {
            $arr['conf']['paypal'] = array();
        }
        if (!isset($arr['conf']['paypal']['isActive'])) {
            $arr['conf']['paypal']['isActive'] = 0;
        }
        return $arr;
    }

    /**
     * Submission history grid action on submission history tab
     */
    public function submissionHistoryGridAction()
    {
        $this->_initApp();
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Initialize message queue
     *
     * @param string $paramName
     * @return Mage_XmlConnect_Model_Queue
     */
    protected function _initMessage($paramName = 'id')
    {
        $id = (int) $this->getRequest()->getParam($paramName);
        $message = Mage::getModel('xmlconnect/queue')->load($id);
        Mage::unregister('current_message');
        Mage::register('current_message', $message);
        return $message;
    }

    /**
     * Initialize Template object
     *
     * @param string $paramName
     * @return Mage_XmlConnect_Model_Template
     */
    protected function _initTemplate($paramName = 'id')
    {
        $id = (int) $this->getRequest()->getParam($paramName);
        $template = Mage::getModel('xmlconnect/template')->load($id);
        Mage::unregister('current_template');
        Mage::register('current_template', $template);
        return $template;
    }

    /**
     * List AirMail message queue grid
     *
     * @return void
     */
    public function queueAction()
    {
        $this->loadLayout();
        $this->_setActiveMenu('xmlconnect/queue');
        $this->renderLayout();
    }

    /**
     * Edit message action
     *
     * @return void
     */
    public function editQueueAction()
    {
        $message = $this->_initMessage();
        if ($message->getId()) {
            $this->getRequest()->setParam('template_id', $message->getTemplateId());
            $this->_initTemplate('template_id');
        }
        $this->_forward('queueMessage');
    }

    /**
     * Filtering posted data. Converting localized data if needed
     *
     * @param array
     * @return array
     */
    protected function _filterPostData($data)
    {
        $data = $this->_filterDateTime($data, array('exec_time'));
        return $data;
    }

    /**
     * Cancel queue action
     *
     * @return void
     */
    public function cancelQueueAction()
    {
        try {
            $id = $this->getRequest()->getParam('id');
            $message = $this->_initMessage();
            if (!$message->getId() && $id) {
                $this->_getSession()->addError($this->__('Queue does not exist.'));
                $this->_redirect('*/*/');
                return;
            }
            $message->setStatus(Mage_XmlConnect_Model_Queue::STATUS_CANCELED);
            $message->save();
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addException($e, $e->getMessage());
        } catch (Exception $e) {
            $this->_getSession()->addException($e, $this->__('Unable to cancel queue.'));
            Mage::logException($e);
        }

        $this->_redirect('*/*/queue');
    }

    /**
     * Delete queue action
     *
     * @return void
     */
    public function deleteQueueAction()
    {
        try {
            $id = $this->getRequest()->getParam('id');
            $message = $this->_initMessage();
            if (!$message->getId() && $id) {
                $this->_getSession()->addError($this->__('Queue does not exist.'));
                $this->_redirect('*/*/');
                return;
            }
            $message->setStatus(Mage_XmlConnect_Model_Queue::STATUS_DELETED);
            $message->save();
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addException($e, $e->getMessage());
        } catch (Exception $e) {
            $this->_getSession()->addException($e, $this->__('Unable to delete queue.'));
            Mage::logException($e);
        }

        $this->_redirect('*/*/queue');
    }

    /**
     * Cancel selected queue action
     *
     * @return void
     */
    public function massCancelQueueAction()
    {
        $queueIds = $this->getRequest()->getParam('queue');
        if(!is_array($queueIds)) {
             Mage::getSingleton('adminhtml/session')->addError(Mage::helper('adminhtml')->__('Please select message(s).'));
        } else {
            try {
                $queue = Mage::getModel('xmlconnect/queue');
                foreach ($queueIds as $queueId) {
                    $queue->reset()
                        ->load((int)$queueId)
                        ->setStatus(Mage_XmlConnect_Model_Queue::STATUS_CANCELED)
                        ->save();
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('adminhtml')->__('Total of %d record(s) were canceled.', count($queueIds))
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

        $this->_redirect('*/*/queue');
    }

    /**
     * Delete selected queue action
     *
     * @return void
     */
    public function massDeleteQueueAction()
    {
        $queueIds = $this->getRequest()->getParam('queue');
        if(!is_array($queueIds)) {
             Mage::getSingleton('adminhtml/session')->addError(Mage::helper('adminhtml')->__('Please select message(s).'));
        } else {
            try {
                $queue = Mage::getModel('xmlconnect/queue');
                foreach ($queueIds as $queueId) {
                    $queue->reset()
                        ->load($queueId)
                        ->setStatus(Mage_XmlConnect_Model_Queue::STATUS_DELETED)
                        ->save();
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('adminhtml')->__(
                        'Total of %d record(s) were deleted.', count($queueIds)
                    )
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

        $this->_redirect('*/*/queue');
    }

    /**
     * Save AirMail message action
     *
     * @return void
     */
    public function saveMessageAction()
    {
        $data         = $this->_filterPostData($this->getRequest()->getPost());
        $isError      = false;
        $message      = false;

        if ($data) {
            try {
                $data = Mage::getModel('xmlconnect/input_filter_maliciousCode')->filter($data);
                $template = $this->_initTemplate('template_id');
                $message = $this->_initMessage();

                if (!$template->getId() && !$message->getTemplateId()) {
                    $this->_getSession()->addError(
                        $this->__('Template for new AirMail Message does not exist.')
                    );
                    $this->_redirect('*/*/queue');
                    return;
                }
                $temporaryObject = new Varien_Object();
                $temporaryObject->setData($data);

                if ($temporaryObject->getTemplateId()) {
                    $message->setTemplateId($temporaryObject->getTemplateId());
                } else {
                    $message->setTemplateId($template->getId());
                }

                if (!$message->getId()) {
                    // set status for new messages only
                    $message->setStatus(Mage_XmlConnect_Model_Queue::STATUS_IN_QUEUE);
                } elseif ($message->getStatus() != Mage_XmlConnect_Model_Queue::STATUS_IN_QUEUE) {
                    $this->_getSession()->addError(
                        $this->__('Message can be edited when status of the message is "IN QUEUE" only.')
                    );
                    $this->_redirect('*/*/queue');
                    return;
                }

                switch ($temporaryObject->getType()) {
                    case Mage_XmlConnect_Model_Queue::MESSAGE_TYPE_AIRMAIL:
                        $message->setData('type', Mage_XmlConnect_Model_Queue::MESSAGE_TYPE_AIRMAIL);
                        break;

                    case Mage_XmlConnect_Model_Queue::MESSAGE_TYPE_PUSH:
                    default:
                        $message->setData('type', Mage_XmlConnect_Model_Queue::MESSAGE_TYPE_PUSH);
                        break;
                }
                if ($temporaryObject->getExecTime()) {
                    $message->setExecTime(
                        Mage::getSingleton('core/date')->gmtDate(null, $temporaryObject->getExecTime())
                    );
                } else {
                    $message->setExecTime(new Zend_Db_Expr('NULL'));
                }
                if ($template->getId()) {
                    $message->setAppCode($template->getAppCode());
                }
                $message->setPushTitle($temporaryObject->getPushTitle());
                $message->setMessageTitle($temporaryObject->getMessageTitle());
                $message->setContent($temporaryObject->getContent());
                $message->save();
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addException($e, $e->getMessage());
                $isError = true;
            } catch (Exception $e) {
                $this->_getSession()->addException($e, $this->__('Unable to save message.'));
                $isError = true;
                Mage::logException($e);
            }
        }

        if ($isError) {
            if ($isError) {
                Mage::getSingleton('adminhtml/session')->setLoadSessionFlag(true);
            }
            $redirectParams = array();
            if ($message && $message->getId()) {
                $redirectParams['id'] = $message->getId();
            } else {
                $redirectParams['template_id'] = (int) $this->getRequest()->getParam('template_id');
            }
            $this->_redirect('*/*/queueMessage', $redirectParams);
        } else {
            $this->_redirect('*/*/queue');
        }
    }

    /**
     * Temlate grid
     *
     * @return void
     */
    public function templateAction()
    {
        $this->loadLayout();
        $this->_setActiveMenu('xmlconnect/template');
        $this->renderLayout();
    }

    /**
     * Create new template action
     *
     * @return void
     */
    public function newTemplateAction()
    {
        $this->_forward('editTemplate');
    }

    /**
     * Edit template action
     *
     * @return void
     */
    public function editTemplateAction()
    {
        $template = $this->_initTemplate();

        $applicationsFound = Mage::helper('xmlconnect')->getApplicationOptions();
        if (!$template->getId() && empty($applicationsFound)) {
            $this->_getSession()->addError(
                $this->__('Template creation is allowed only for applications which have device type iPhone, but this kind of applications has not been found.')
            );
            $this->_redirect('*/*/template');
            return;
        }

        $this->loadLayout();
        $this->_setActiveMenu('xmlconnect/templates');
        $this->renderLayout();
    }

    /**
     * Save template action
     *
     * @return void
     */
    public function saveTemplateAction()
    {
        $data = $this->getRequest()->getPost();
        $template = false;
        $isError = false;
        if ($data) {
            $data = Mage::getModel('xmlconnect/input_filter_maliciousCode')->filter($data);
            Mage::getSingleton('adminhtml/session')->setFormData($data);
            try {
                $id = $this->getRequest()->getParam('id');
                $template = $this->_initTemplate();
                if (!$template->getId() && $id) {
                    $this->_getSession()->addError($this->__('Template does not exist.'));
                    $this->_redirect('*/*/');
                    return;
                }
                $template->setModifiedAt(Mage::getSingleton('core/date')->gmtDate())->addData($data);
                $template->save();
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addException($e, $e->getMessage());
                $isError = true;
            } catch (Exception $e) {
                $this->_getSession()->addException($e, $this->__('Unable to save template.'));
                $isError = true;
                Mage::logException($e);
            }
        }

        if ($isError && ($template && $template->getId())) {
            Mage::getSingleton('adminhtml/session')->setLoadSessionFlag(true);
            $this->_redirect('*/*/editTemplate', array('id' => $template->getId()));
        } else {
            $this->_redirect('*/*/template');
        }
    }

    /**
     * Add message to queue action
     *
     * @return void
     */
    public function queueMessageAction()
    {
        $message = $this->_initMessage();
        if (!$message->getId()) {
            $template = $this->_initTemplate('template_id');
            if (!$template->getId()) {
                $this->_getSession()->addError(
                    $this->__('Template for new AirMail Message does not exist.')
                );
                $this->_redirect('*/*/template');
            }
        }

        $this->loadLayout();
        if ($message->getId()) {
            $title = $this->__('Edit AirMail Message');
        } else {
            $title = $this->__('New AirMail Message');
        }
        $this->_addBreadcrumb(
            $this->__('AirMail Message Queue'),
            $this->__('AirMail Message Queue'),
            $this->getUrl('*/*/queue')
        );
        $this->_addBreadcrumb($title, $title);

        $this->_setActiveMenu('xmlconnect/queue');
        $this->renderLayout();
    }

    /**
     * Edit queue message action
     *
     * @return void
     */
    public function editMessageAction()
    {
        $this->_forward('queueMessage');
    }
}
