<?php

/**
 * This file contains \QUI\Lockserver\Client
 */

namespace QUI\Lockserver;

use QUI;

/**
 * Class Client - for Lockserver v2
 *
 * The client is independent and can not be used only in a quiqqer system
 * A quiqqer system is optional,
 * if a quiqqer system exists, the client used the QUI::getLocale() messages.
 *
 * @author  www.pcsg.de (Henning Leutz)
 * @package QUI\Lockserver
 */
class Client extends QUI\QDOM
{
    /**
     * Constructor
     *
     * @param array $params
     */
    public function __construct($params = array())
    {
        $this->setAttributes(array(
            'lockServer'       => 'https://lock.quiqqer.com',
            'composerJsonFile' => '',
            'composerLockFile' => ''
        ));

        $this->setAttributes($params);

        // are we in a quiqqer system?
        $this->setAttribute('isQuiqqer', false);

        if (class_exists('QUI') && is_callable(array('QUI', 'getLocale'))) {
            $this->setAttribute('isQuiqqer', true);
        }
    }

    /**
     * Execute the install command at the lock server and return the composer.lock file
     *
     * @return string
     * @throws QUI\Exception
     */
    public function install()
    {
        $lockServer = $this->getAttribute('lockServer');
        $jsonFile = $this->getAttribute('composerJsonFile');

        if (empty($lockServer)) {
            if ($this->getAttribute('isQuiqqer')) {
                throw new QUI\Exception(
                    QUI::getLocale()->get(
                        'quiqqer/lockclient',
                        'exception.lock.client.unknown.lock.server'
                    ),
                    400
                );
            }

            throw new QUI\Exception('Unknown Lock-Server', 400);
        }

        // composer json
        if (empty($jsonFile) || !file_exists($jsonFile)) {
            if ($this->getAttribute('isQuiqqer')) {
                throw new QUI\Exception(
                    QUI::getLocale()->get(
                        'quiqqer/lockclient',
                        'exception.lock.client.composerjson.not.found'
                    ),
                    404
                );
            }

            throw new QUI\Exception('composer.json file not found', 404);
        }

        return $this->_request($lockServer.'/v2/install', array(
            'composerJson' => file_get_contents($jsonFile)
        ));
    }

    /**
     * Execute the update command at the lock server and return the composer.lock file
     *
     * @param array|string $packages
     *
     * @return string
     * @throws QUI\Exception
     */
    public function update($packages)
    {
        if (is_string($packages)) {
            $packages = array($packages);
        }

        return $this->_send('/v2/require', array(
            'package' => json_encode($packages)
        ));
    }

    /**
     * Execute the update command with the --dry-run flag
     * With dryUpdate() you can check if updates exist.
     *
     * @param array|string $packages - name of the package
     *
     * @return Array
     * @throws QUI\Exception
     */
    public function dryUpdate($packages = '')
    {
        if (is_string($packages)) {
            $packages = array($packages);
        }

        if (empty($packages)) {
            $packages = '';
        }

        return $this->_send('/v2/require/dry', array(
            'package' => json_encode($packages)
        ));
    }

    /**
     * Execute the require command at the lock server and return the composer.lock file
     *
     * @param string $package - name of the package
     * @param string $version - (optional), version of the package
     *
     * @return string
     * @throws QUI\Exception
     */
    public function requires($package, $version = '')
    {
        return $this->_send('/v2/require', array(
            'package' => $package,
            'version' => $version
        ));
    }

    /**
     * Execute the require command with the --dry-run flag
     *
     * @param string $package - name of the package
     * @param string $version - (optional), version of the package
     *
     * @return string
     * @throws QUI\Exception
     */
    public function dryRequires($package, $version = '')
    {
        return $this->_send('/v2/require/dry', array(
            'package' => $package,
            'version' => $version
        ));
    }

    /**
     * Validate the JSON data
     *
     * @param string $json - json data
     *
     * @throws QUI\Exception
     */
    protected function _checkJSON($json)
    {
        $data = json_decode($json);

        if (!$data) {
            if ($this->getAttribute('isQuiqqer')) {
                throw new QUI\Exception(
                    QUI::getLocale()->get(
                        'quiqqer/lockclient',
                        'exception.lock.client.json.invalid'
                    ),
                    400
                );
            } else {
                throw new QUI\Exception('The JSON is invalid', 400);
            }
        }
    }

    /**
     * Send a requrest to the lockserver
     *
     * @param string $url
     * @param array  $postFields
     *
     * @return string
     *
     * @throws QUI\Exception
     */
    protected function _send($url, array $postFields)
    {
        $lockServer = $this->getAttribute('lockServer');
        $jsonFile = $this->getAttribute('composerJsonFile');
        $lockFile = $this->getAttribute('composerLockFile');

        if (empty($lockServer)) {
            if ($this->getAttribute('isQuiqqer')) {
                throw new QUI\Exception(
                    QUI::getLocale()->get(
                        'quiqqer/lockclient',
                        'exception.lock.client.unknown.lock.server'
                    ),
                    400
                );
            }

            throw new QUI\Exception('Unknown Lock-Server', 400);
        }

        // lock json
        if (empty($lockFile) || !file_exists($lockFile)) {
            if ($this->getAttribute('isQuiqqer')) {
                throw new QUI\Exception(
                    QUI::getLocale()->get(
                        'quiqqer/lockclient',
                        'exception.lock.client.lockfile.not.found'
                    ),
                    404
                );
            }

            throw new QUI\Exception('composer.lock file not found', 404);
        }

        // composer json
        if (empty($jsonFile) || !file_exists($jsonFile)) {
            if ($this->getAttribute('isQuiqqer')) {
                throw new QUI\Exception(
                    QUI::getLocale()->get(
                        'quiqqer/lockclient',
                        'exception.lock.client.composerjson.not.found'
                    ),
                    404
                );
            }

            throw new QUI\Exception('composer.json file not found', 404);
        }

        $postFields['composerJson'] = file_get_contents($jsonFile);
        $postFields['composerLock'] = file_get_contents($lockFile);

        $this->_request($lockServer.$url, $postFields);
    }

    /**
     * @param $url
     * @param $postFields
     *
     * @return mixed
     * @throws QUI\Exception
     */
    protected function _request($url, $postFields)
    {
        $Curl = QUI\Utils\Request\Url::Curl($url, array(
            CURLOPT_POST       => 1,
            CURLOPT_POSTFIELDS => http_build_query($postFields)
        ));

        $result = QUI\Utils\Request\Url::exec($Curl);
        $info = curl_getinfo($Curl);

        if ($info['http_code'] == 200) {
            return $result;
        }

        throw new QUI\Exception($result, 400);
    }
}
