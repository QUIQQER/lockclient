<?php

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
     * @throws \Exception
     */
    public function requirePackage($composerJsonPath, $package, $version)
    {
        if (!file_exists($composerJsonPath)) {
            throw new \Exception("Could not find the composer.json file: '".$composerJsonPath."'");
        }

        $json = file_get_contents($composerJsonPath);
        if ($json === false) {
            throw new \Exception("Could not read the composer.json file: '".$composerJsonPath."'");
        }

        $data = json_decode($json, true);
        $data['require'][$package] = $version;

        file_put_contents($composerJsonPath, json_encode($data, JSON_PRETTY_PRINT));

        $params = array(
            "requires" => json_encode($data['require'])
        );

        return $this->sendPostRequest("/generate", $params);
    }

    /**
     * Creates the composer.lock for the current composer.json
     *
     * @param $composerJsonPath
     * @param string|bool $package - (optional) If specified the update will only be executed for this package
     *
     * @return string
     * @throws \Exception
     */
    public function update($composerJsonPath, $package = false)
    {
        if (!file_exists($composerJsonPath)) {
            throw new \Exception("Could not find the composer.json file: '".$composerJsonPath."'");
        }

        $json = file_get_contents($composerJsonPath);
        if ($json === false) {
            throw new \Exception("Could not read the composer.json file: '".$composerJsonPath."'");
        }

        $data = json_decode($json, true);
        $params = array(
            "requires" => json_encode($data['require'])
        );

        $endpoint = "/generate";
        if ($package !== false) {
            $params["package"]  = $package;
            $endpoint = "/updatePackage";
        }

        return $this->sendPostRequest($endpoint, $params);
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
     * @param  bool $onlyStable - if this is set to true only stable packages will be considered.
     *
     * @return array
     * @throws \Exception
     */
    public function getLatestVersionInContraints($packageConstraints, $onlyStable)
    {
        // Check if Lockserver should be used
        if (class_exists('QUI') && !\QUI::conf("globals", "lockserver_enabled")) {
            throw new \Exception("Lockserver is disabled!");
        }

        $fields = array(
            'constraints' => json_encode($packageConstraints),
            'stable' => $onlyStable
        );
        $json = $this->sendPostRequest("/versions/latest", $fields);
        
        $versions = json_decode($json, true);

        return $versions;
    }

    /**
     * Sends a post request to the lock server.
     *
     * @param string $endpoint - The endpoint which the request should get send to
     * @param array $params - Array of paramters
     *
     * @return string
     * @throws \Exception
     */
    protected function sendPostRequest($endpoint, $params = array())
    {
        // Prepare request
        $url = "https://lock.quiqqer.com/";
        $url = $url.$endpoint;

        // Build Curl Request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // ssl
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        //POST
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);

        // Execute the request
        $result = curl_exec($ch);
        $info = curl_getinfo($ch);

        if (curl_errno($ch) !== 0) {
            throw new \Exception("Curl error: ".curl_error($ch));
        }

        if ($info['http_code'] !== 200) {
            throw new \Exception("Could not retrieve lockfile:".PHP_EOL.$result);
        }

        return $result;
    }
}
