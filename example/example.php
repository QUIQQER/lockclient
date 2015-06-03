<?php

require dirname(dirname(__FILE__)).'/vendor/autoload.php';

$Client = new QUI\Lockserver\Client(array(
    'lockServer'       => 'http://localhost/git/quiqqer/Lockserver',
    'composerJsonFile' => dirname(__FILE__).'/composer.json'
));

try {
    $result = $Client->install();

    echo $result;

} catch (QUI\Exception $Exception) {

    echo "\n====== Error ========\n"
        .$Exception->getMessage()
        ."\n=====================\n";
}
