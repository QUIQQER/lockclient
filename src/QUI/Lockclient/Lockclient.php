<?php

/**
 * This file contains QUI\Lockclient\Lockclient
 */

namespace QUI\Lockclient;

use QUI\Exception;
use QUI\System\Log;

class Lockclient
{
    /**
     * Adds the package to the composer.json file and retrieves a composer.lock string from the lock server
     *
     * @param $composerJsonPath
     * @param $package
     * @param $version
     *
     * @return string
     * @throws \InvalidArgumentException
     * @throws Exception
     */
    public function requirePackage($composerJsonPath, $package, $version)
    {
        if (!\file_exists($composerJsonPath)) {
            throw new \InvalidArgumentException("Could not find the composer.json file: '".$composerJsonPath."'");
        }

        $json = \file_get_contents($composerJsonPath);

        if ($json === false) {
            throw new \InvalidArgumentException("Could not read the composer.json file: '".$composerJsonPath."'");
        }

        $data                      = \json_decode($json, true);
        $data['require'][$package] = $version;

        \file_put_contents($composerJsonPath, \json_encode($data, JSON_PRETTY_PRINT));

        $params = [
            'requires' => \json_encode($data['require'])
        ];

        return $this->sendPostRequest('/generate', $params);
    }

    /**
     * Creates the composer.lock for the current composer.json
     *
     * @param $composerJsonPath
     * @param string|bool $package - (optional) If specified the update will only be executed for this package
     *
     * @return string
     * @throws \InvalidArgumentException
     * @throws Exception
     */
    public function update($composerJsonPath, $package = false)
    {
        if (!\file_exists($composerJsonPath)) {
            throw new \InvalidArgumentException("Could not find the composer.json file: '".$composerJsonPath."'");
        }

        $json = \file_get_contents($composerJsonPath);

        if ($json === false) {
            throw new \InvalidArgumentException("Could not read the composer.json file: '".$composerJsonPath."'");
        }

        $data     = \json_decode($json, true);
        $endpoint = '/generate';
        $params   = [
            'requires' => \json_encode($data['require'])
        ];

        if ($package !== false) {
            $params['package'] = $package;
            $endpoint          = '/updatePackage';
        }

        return $this->sendPostRequest($endpoint, $params);
    }

    /**
     * Returns an array with outdated packages
     *
     * @return array
     * @throws \Exception
     */
    public function getOutdated()
    {
        // Check if Lockserver should be used
        if (\class_exists('QUI') && !\QUI::conf('globals', 'lockserver_enabled')) {
            throw new Exception([
                'quiqqer/lockclient',
                'error.lock.disabled'
            ]);
        }

        $composerJson = \json_decode(\file_get_contents(VAR_DIR.'/composer/composer.json'), true);

        $fields = [
            'lock_content' => \file_get_contents(VAR_DIR.'/composer/composer.lock'),
            'requires'     => \json_encode($composerJson['require']),
            'repositories' => \json_encode($composerJson['repositories'])
        ];

        $json = $this->sendPostRequest('/versions/outdated', $fields);

        return \json_decode($json, true);
    }

    /**
     * This will prompt the lockserver to generate a list with the latest possible version.
     *
     *
     * **Example input**:
     * ```
     * array(
     *  "quiqqer/test" => array("dev-dev"|"4.6.*"),
     *  "quiqqer/quiqqer" => array("1.0.0")
     * )
     * ```
     * **Example output**:
     * ```
     * array(
     *  "quiqqer/test" => "4.6.7",   // There is an update to version 4.6.7 possible
     *  "quiqqer/quiqqer" => false   // no updates available
     * )
     * ```
     *
     * @param array $packageConstraints - The array of packages. Where the packagename is they key and the value is an array of semver contraints
     * @param bool $onlyStable - if this is set to true only stable packages will be considered.
     *
     * @return array
     * @throws Exception
     */
    public function getLatestVersionInContraints($packageConstraints, $onlyStable)
    {
        // Check if Lockserver should be used
        if (\class_exists('QUI') && !\QUI::conf('globals', 'lockserver_enabled')) {
            throw new Exception([
                'quiqqer/lockclient',
                'error.lock.disabled'
            ]);
        }

        $fields = [
            'constraints' => \json_encode($packageConstraints),
            'stable'      => $onlyStable
        ];

        $json = $this->sendPostRequest('/versions/latest', $fields);

        return \json_decode($json, true);
    }

    /**
     * Sends a post request to the lock server.
     *
     * @param string $endpoint - The endpoint which the request should get send to
     * @param array $params - Array of paramters
     *
     * @return string
     * @throws Exception
     */
    protected function sendPostRequest($endpoint, $params = [])
    {
        // Prepare request
        $url = 'https://lock.quiqqer.com/';
        $url .= $endpoint;

        // Build Curl Request
        $ch = \curl_init();

        \curl_setopt($ch, CURLOPT_URL, $url);
        \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        \curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        \curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        //POST
        \curl_setopt($ch, CURLOPT_POST, 1);
        \curl_setopt($ch, CURLOPT_POSTFIELDS, $params);

        // Execute the request
        $result = \curl_exec($ch);
        $info   = \curl_getinfo($ch);

        if (\curl_errno($ch) !== 0) {
            if (\class_exists('QUI\System\Log')) {
                Log::addError('Lockclient encountered a curl error', [
                    'url'   => $url,
                    'error' => curl_error($ch)
                ]);
            }

            throw new Exception([
                'quiqqer/lockclient',
                'error.curl.unknown'
            ]);
        }

        if ($info['http_code'] !== 200) {
            if (\class_exists('QUI\System\Log')) {
                Log::addError('The lockclient received an unexpected error code for the request', [
                    'url'        => $url,
                    'error_code' => $info['http_code']
                ]);
            }

            throw new Exception([
                'quiqqer/lockclient',
                'error.curl.unknown'
            ]);
        }

        return $result;
    }
}
