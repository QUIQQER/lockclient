<?php

namespace QUI\Lockclient;

class Lockclient
{

    /**
     * Retrieves the Lockfile from the lockserver
     *
     * @param array $requires - Array of requirements. Format: [ "package" => "version" ]
     * @param array $repositories - Array of repositories. Format [ ["type" => "<type>", "url" => "<url>"] ]
     *
     * @return string
     *
     * @throws \Exception
     */
    public function getLockfile($requires, $repositories = array())
    {
        // Check if Lockserver should be used
        if (class_exists('QUI') &&
            is_callable(array('QUI', 'getPackage')) &&
            \QUI::getPackage("quiqqer/lockclient")->getConfig()->get("client", "disabled")
        ) {
            throw new \Exception("Lockserver is disabled!");
        }

        // Prepare request
        $url = "https://lock.quiqqer.com/generate";

        $fields = array(
            'requires' => json_encode($requires),
            'repositories' => json_encode($repositories)
        );

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
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);

        // Execute the request
        $result = curl_exec($ch);
        $info = curl_getinfo($ch);

        if (curl_errno($ch) !== 0) {
            throw new \Exception("Curl error: " . curl_error($ch));
        }

        if ($info['http_code'] !== 200) {
            throw new \Exception("Could not retrieve lockfile:" . PHP_EOL . $result);
        }

        return $result;
    }

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
            throw new \Exception("Could not find the composer.json file: '" . $composerJsonPath . "'");
        }

        $json = file_get_contents($composerJsonPath);
        if ($json === false) {
            throw new \Exception("Could not read the composer.json file: '" . $composerJsonPath . "'");
        }

        $data = json_decode($json, true);
        $data['require'][$package] = $version;

        file_put_contents($composerJsonPath, json_encode($data, JSON_PRETTY_PRINT));

        return $this->getLockfile($data['require'], $data['repositories']);
    }

    /**
     * Creates the composer.lock for the current composer.json
     *
     * @param $composerJsonPath
     *
     * @return string
     * @throws \Exception
     */
    public function update($composerJsonPath)
    {
        if (!file_exists($composerJsonPath)) {
            throw new \Exception("Could not find the composer.json file: '" . $composerJsonPath . "'");
        }

        $json = file_get_contents($composerJsonPath);
        if ($json === false) {
            throw new \Exception("Could not read the composer.json file: '" . $composerJsonPath . "'");
        }

        $data = json_decode($json, true);

        return $this->getLockfile($data['require'], $data['repositories']);
    }
}
