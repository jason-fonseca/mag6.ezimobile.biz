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
 * Xmlconnect Config data upgrade
 *
 * @category    Mage
 * @package     Mage_Xmlconnect
 * @author      Magento Core Team <core@magentocommerce.com>
 */

/** @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

$configTable = $installer->getTable('xmlconnect/configData');

$installer->run("CREATE TABLE `{$configTable}` (
    `application_id` smallint(5) unsigned NOT NULL,
    `category` varchar( 60 ) NOT NULL DEFAULT 'default',
    `path` varchar( 250 ) NOT NULL,
    `value` TEXT NOT NULL
) ENGINE = INNODB DEFAULT CHARSET=utf8;");

$installer->getConnection()->addKey(
    $configTable,
    'UNQ_XMLCONNECT_CONFIG',
    array('application_id', 'category', 'path'),
    'unique'
);

$installer->getConnection()->addConstraint(
    'FK_APPLICATION_ID',
    $configTable,
    'application_id',
    $installer->getTable('xmlconnect/application'),
    'application_id'
);

$installer->endSetup();
