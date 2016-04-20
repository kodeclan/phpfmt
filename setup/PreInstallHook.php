<?php
namespace Fmt\Setup;

use Composer\Script\Event;

class PreInstallHook {
    /**
     * @param Event $event
     */
    public static function setupLocalPhpBinary(Event $event) {
        $os = php_uname('s');
        //http://ar2.php.net/distributions/php-7.0.5.tar.gz
        //./configure --enable-tokenizer  --enable-static --prefix=/Users/klein/Projects/php-bin
        $event->getIO()->write("Pre install script started, found OS: " . $os);
        $phpSourceBinaryName = strtolower($os);
        switch ($os) {
        case 'Linux':
        case 'Darwin':
            break;
        case 'Windows':
            $phpSourceBinaryName .= '.exe';
            break;
        }

        echo "Copy source binary..." . PHP_EOL;
        system(sprintf('cp ./src-ext-bin/php7-static-%s ./ext-bin/php', $phpSourceBinaryName));
        echo "$ ./ext-bin/php --version" . PHP_EOL;
        system('./ext-bin/php --version');
        echo "$ Done." . PHP_EOL;
    }

}