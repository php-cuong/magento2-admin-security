<?php
/**
 * GiaPhuGroup Co., Ltd.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the GiaPhuGroup.com license that is
 * available through the world-wide-web at this URL:
 * https://www.giaphugroup.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    PHPCuong
 * @package     PHPCuong_AdminSecurity
 * @copyright   Copyright (c) 2019-2020 GiaPhuGroup Co., Ltd. All rights reserved. (http://www.giaphugroup.com/)
 * @license     https://www.giaphugroup.com/LICENSE.txt
 */

namespace PHPCuong\AdminSecurity\Observer\Admin;

use Magento\Framework\Stdlib\DateTime;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;

class LoginSuccess implements ObserverInterface
{
    /**
     * @var \Magento\Framework\HTTP\Header
     */
    protected $_httpHeader;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_urlInterface;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\Timezone
     */
    protected $_dateTimeFormater;

    /**
     * @var TransportBuilder
     */
    protected $_transportBuilder;

    /**
     * Core store config
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * Store manager
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @param \Magento\Framework\HTTP\Header $httpHeader
     * @param \Magento\Framework\UrlInterface $urlInterface
     * @param \Magento\Framework\Stdlib\DateTime\Timezone $dateTimeFormater
     * @param TransportBuilder $transportBuilder
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Framework\HTTP\Header $httpHeader,
        \Magento\Framework\UrlInterface $urlInterface,
        \Magento\Framework\Stdlib\DateTime\Timezone $dateTimeFormater,
        TransportBuilder $transportBuilder,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager
    ) {
        $this->_httpHeader = $httpHeader;
        $this->_urlInterface = $urlInterface;
        $this->_dateTimeFormater = $dateTimeFormater;
        $this->_transportBuilder = $transportBuilder;
        $this->_scopeConfig = $scopeConfig;
        $this->_storeManager = $storeManager;
    }

    /**
     * Handler for 'backend_auth_user_login_success' event.
     *
     * @param Observer $observer
     * @return LoginSuccess class | void
     */
    public function execute(Observer $observer)
    {
        $authUser = $observer->getEvent()->getUser();
        $name = $authUser->getName();
        $email = $authUser->getEmail();
        $store = $this->_storeManager->getStore();

        $userData = new \Magento\Framework\DataObject();

        $templateParams = [
            'name' => $name,
            'ip_address' => $this->getIpAddress(),
            'login_url' => $this->getCurrentUrl(),
            'referrer_url' => $this->getReferrerUrl(),
            'logged_in_at' => $this->getLoggedInAt(),
            'browser_information' => $this->getHttpUserAgent()
        ];

        $userData->setData($templateParams);

        $this->_transportBuilder->setTemplateIdentifier(
            'admin_emails_login_success_email_template'
        )->setTemplateOptions(
            [
                'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                'store' => $store->getId(),
            ]
        )->setTemplateVars(
            ['user' => $userData, 'store' => $store]
        )->setFrom(
            'general'
        )->addTo(
            $email,
            $name
        );

        $transport = $this->_transportBuilder->getTransport();

        try {
            $transport->sendMessage();
        } catch (\Exception $e) {}
    }

    /**
     * Get Current Time
     *
     * @return string
     */
    private function getLoggedInAt()
    {
        foreach ($this->_dateTimeFormater->date() as $key => $value) {
            if ($key == 'date') {
                return $value;
            }
        }
        return '';
    }

    /**
     * Get Current URL
     *
     * @return string
     */
    private function getCurrentUrl()
    {
        return $this->_urlInterface->getCurrentUrl();
    }

    /**
     * Get Referer URL
     *
     * @return string
     */
    private function getReferrerUrl()
    {
        return !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    }

    /**
     * Get User agent (i.e. browser) of the device used during the login.
     *
     * @return string
     */
    private function getHttpUserAgent()
    {
        return $this->_httpHeader->getHttpUserAgent();
    }

    /**
     * Get the ip property
     * The function can get Real IP address from visitors when they are using proxy
     *
     * @return string
     */
    private function getIpAddress()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ipAddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED'])) {
            $ipAddress = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipAddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED'])) {
            $ipAddress = $_SERVER['HTTP_FORWARDED'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ipAddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipAddress = 'UNKNOWN';
        }
        return $ipAddress;
    }
}
